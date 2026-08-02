<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Only POST method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';
$deviceId = isset($input['device_id']) ? trim($input['device_id']) : null;

// Validate input
if (empty($email) || empty($password)) {
    sendError('Email and password are required', HTTP_BAD_REQUEST);
}

try {
    $conn = getDBConnection();
    
    // Get user with role
    $stmt = $conn->prepare("
        SELECT u.*, r.name as role_name, r.level as role_level, 
               t.name as tenant_name
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
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                sendError('Account temporarily locked. Please try again later.', HTTP_UNAUTHORIZED);
            }
            
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            // Store session
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $sessionStmt = $conn->prepare("
                INSERT INTO user_sessions (user_id, token, device_type, device_id, ip_address, user_agent, is_active, expires_at)
                VALUES (?, ?, 'mobile', ?, ?, ?, 1, ?)
            ");
            $sessionStmt->bind_param("isssss", $user['id'], $token, $deviceId, $ip, $userAgent, $expiresAt);
            $sessionStmt->execute();
            $sessionStmt->close();
            
            // Update last login
            $updateStmt = $conn->prepare("
                UPDATE users 
                SET last_login_at = NOW(), last_login_ip = ?, login_attempts = 0, locked_until = NULL 
                WHERE id = ?
            ");
            $updateStmt->bind_param("si", $ip, $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Remove sensitive data
            unset($user['password_hash']);
            unset($user['remember_token']);
            unset($user['two_factor_secret']);
            $user['token'] = $token;
            
            // Log activity
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, ip_address, created_at)
                VALUES (?, ?, 'login', 'User logged in successfully', ?, NOW())
            ");
            $logStmt->bind_param("iis", $user['id'], $user['tenant_id'], $ip);
            $logStmt->execute();
            $logStmt->close();
            
            // Check if 2FA is enabled
            $requires2FA = isset($user['two_factor_enabled']) && $user['two_factor_enabled'] == 1;
            
            sendSuccess('Login successful', [
                'user' => $user,
                'token' => $token,
                'requires_2fa' => $requires2FA
            ]);
            
        } else {
            // Increment login attempts
            $updateStmt = $conn->prepare("
                UPDATE users 
                SET login_attempts = login_attempts + 1 
                WHERE id = ?
            ");
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Check if should lock account
            $attempts = $user['login_attempts'] + 1;
            if ($attempts >= 10) {
                $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $lockStmt = $conn->prepare("
                    UPDATE users SET locked_until = ? WHERE id = ?
                ");
                $lockStmt->bind_param("si", $lockUntil, $user['id']);
                $lockStmt->execute();
                $lockStmt->close();
                sendError('Too many failed attempts. Account locked for 15 minutes.', HTTP_UNAUTHORIZED);
            }
            
            sendError('Invalid password', HTTP_UNAUTHORIZED);
        }
    } else {
        sendError('User not found', HTTP_UNAUTHORIZED);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}