<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

include '../../config/config.php';

try {
    $conn = new mysqli($host, $username, $password_db, $db_name);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    $conn->set_charset("utf8mb4");
    
    // Get user with role
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
        if (password_verify($password, $user['password_hash'])) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            
            // Store session
            $sessionStmt = $conn->prepare("
                INSERT INTO user_sessions (user_id, token, device_type, ip_address, user_agent, is_active, created_at)
                VALUES (?, ?, 'mobile', ?, ?, 1, NOW())
            ");
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $sessionStmt->bind_param("isss", $user['id'], $token, $ip, $userAgent);
            $sessionStmt->execute();
            $sessionStmt->close();
            
            // Update last login
            $updateStmt = $conn->prepare("
                UPDATE users SET last_login_at = NOW(), last_login_ip = ?, login_attempts = 0 WHERE id = ?
            ");
            $updateStmt->bind_param("si", $ip, $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            unset($user['password_hash']);
            unset($user['remember_token']);
            unset($user['two_factor_secret']);
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
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>