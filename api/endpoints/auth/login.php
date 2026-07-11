<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Database connection
try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Debug: log received data
    error_log("Login attempt for: " . ($input['email'] ?? 'no email'));
    
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $device_id = $input['device_id'] ?? null;

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password required']);
        exit;
    }

    // Database configuration - Update these with your actual credentials
    $host = 'localhost';
    $db_name = 'utgoohwm_election';
    $username = 'utgoohwm_admin'; // Your cPanel username
    $password_db = 'your_db_password'; // Your database password

    $conn = new mysqli($host, $username, $password_db, $db_name);

    if ($conn->connect_error) {
        error_log("DB Connection failed: " . $conn->connect_error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    // Set charset
    $conn->set_charset("utf8mb4");

    // Get user with role information
    $stmt = $conn->prepare("
        SELECT u.*, r.name as role_name, r.level as role_level, t.name as tenant_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN tenants t ON u.tenant_id = t.id
        WHERE u.email = ? AND u.status = 'active'
    ");
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            // Update login info
            $updateStmt = $conn->prepare("
                UPDATE users 
                SET login_attempts = 0, 
                    last_login_at = NOW(), 
                    last_login_ip = ?,
                    last_login_device = ?
                WHERE id = ?
            ");
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $updateStmt->bind_param("ssi", $ip, $user_agent, $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Generate simple token
            $token = bin2hex(random_bytes(32));
            
            // Store token in user_sessions
            $sessionStmt = $conn->prepare("
                INSERT INTO user_sessions (user_id, token, device_type, device_name, ip_address, user_agent, is_active, created_at)
                VALUES (?, ?, 'web', ?, ?, ?, 1, NOW())
            ");
            $device_type = 'mobile';
            $sessionStmt->bind_param("issss", $user['id'], $token, $device_type, $ip, $user_agent);
            $sessionStmt->execute();
            $sessionStmt->close();
            
            // Remove sensitive data
            unset($user['password_hash']);
            unset($user['remember_token']);
            unset($user['two_factor_secret']);
            
            // Add token to response
            $user['token'] = $token;
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => $user,
                'token' => $token
            ]);
        } else {
            // Increment login attempts
            $updateStmt = $conn->prepare("
                UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?
            ");
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid password']);
        }
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not found or inactive']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>