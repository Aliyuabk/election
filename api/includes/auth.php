<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/response.php';

function verifyToken($token) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT user_id, expires_at 
        FROM user_sessions 
        WHERE token = ? AND is_active = 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        return false;
    }
    
    $session = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    // Check if token expired
    $expiresAt = strtotime($session['expires_at']);
    if ($expiresAt < time()) {
        return false;
    }
    
    return $session['user_id'];
}

function generateAuthToken($userId) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        INSERT INTO user_sessions (user_id, token, device_type, ip_address, user_agent, is_active, expires_at)
        VALUES (?, ?, 'mobile', ?, ?, 1, ?)
    ");
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt->bind_param("issss", $userId, $token, $ip, $userAgent, $expiresAt);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $token;
}

function revokeToken($token) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        UPDATE user_sessions 
        SET is_active = 0 
        WHERE token = ?
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function getUserData($userId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT u.*, r.name as role_name, r.level as role_level, 
               t.name as tenant_name, t.id as tenant_id
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN tenants t ON u.tenant_id = t.id
        WHERE u.id = ? AND u.status = 'active'
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        return null;
    }
    
    $user = $result->fetch_assoc();
    unset($user['password_hash']);
    unset($user['remember_token']);
    unset($user['two_factor_secret']);
    
    $stmt->close();
    $conn->close();
    
    return $user;
}