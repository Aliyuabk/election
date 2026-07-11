<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// For testing - allow GET with test parameter
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test'])) {
    echo json_encode([
        'success' => true,
        'message' => 'Login endpoint is working',
        'method' => 'GET',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Method not allowed. Please use POST request.'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Debug logging
error_log("Login attempt: " . print_r($input, true));

$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
$device_id = $input['device_id'] ?? null;

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Email and password are required'
    ]);
    exit;
}

// Database configuration - UPDATE THESE VALUES
$host = 'localhost';
$db_name = 'utgoohwm_election';
$username = 'utgoohwm_election'; // Your actual database username
$password_db = 'Jiddaahh@1'; // Your actual database password

try {
    // Create connection
    $conn = new mysqli($host, $username, $password_db, $db_name);

    // Check connection
    if ($conn->connect_error) {
        error_log("DB Connection failed: " . $conn->connect_error);
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Database connection failed'
        ]);
        exit;
    }

    $conn->set_charset("utf8mb4");

    // Debug: Check if connection is successful
    error_log("Database connected successfully");

    // Check if user exists
    $stmt = $conn->prepare("
        SELECT u.*, r.name as role_name, r.level as role_level, t.name as tenant_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN tenants t ON u.tenant_id = t.id
        WHERE u.email = ? AND u.status != 'archived'
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Database query preparation failed'
        ]);
        exit;
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Debug: User found
        error_log("User found: " . $user['email'] . " with role: " . ($user['role_name'] ?? 'No role'));
        
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            error_log("Password verified for: " . $email);
            
            // Update login info
            $updateStmt = $conn->prepare("
                UPDATE users 
                SET login_attempts = 0, 
                    last_login_at = NOW(), 
                    last_login_ip = ?,
                    last_login_device = ?
                WHERE id = ?
            ");
            $ip = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $updateStmt->bind_param("ssi", $ip, $user_agent, $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Generate token
            $token = bin2hex(random_bytes(32));
            error_log("Token generated for user: " . $email);
            
            // Store session
            $sessionStmt = $conn->prepare("
                INSERT INTO user_sessions (
                    user_id, token, device_type, device_name, 
                    ip_address, user_agent, is_active, created_at
                ) VALUES (?, ?, 'mobile', ?, ?, ?, 1, NOW())
            ");
            $device_name = $device_id ?? 'unknown_device';
            $sessionStmt->bind_param("issss", $user['id'], $token, $device_name, $ip, $user_agent);
            $sessionStmt->execute();
            $sessionStmt->close();
            
            // Remove sensitive data
            unset($user['password_hash']);
            unset($user['remember_token']);
            unset($user['two_factor_secret']);
            
            // Add token
            $user['token'] = $token;
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => $user,
                'token' => $token
            ]);
        } else {
            error_log("Password verification FAILED for: " . $email);
            
            // Increment login attempts
            $updateStmt = $conn->prepare("
                UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?
            ");
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            http_response_code(401);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid password. Please try again.'
            ]);
        }
    } else {
        error_log("User NOT found: " . $email);
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'User not found. Please check your email address.'
        ]);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>