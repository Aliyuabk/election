<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/JWT.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
$device_id = $input['device_id'] ?? null;

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get user with role information
    $stmt = $db->prepare("
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
            // Update login attempt
            $updateStmt = $db->prepare("
                UPDATE users 
                SET login_attempts = 0, 
                    last_login_at = NOW(), 
                    last_login_ip = ?,
                    last_login_device = ?
                WHERE id = ?
            ");
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $device_name = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $updateStmt->bind_param("ssi", $ip, $device_name, $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Generate JWT token
            $token_payload = [
                'user_id' => $user['id'],
                'tenant_id' => $user['tenant_id'],
                'role_id' => $user['role_id'],
                'role_level' => $user['role_level']
            ];
            $token = JWT::generate($token_payload);
            
            // Remove sensitive data from response
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
            $updateStmt = $db->prepare("
                UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?
            ");
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not found or inactive']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>