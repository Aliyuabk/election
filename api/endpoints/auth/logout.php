<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

// Only POST method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

$userId = validateToken();

// Get token from header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
preg_match('/Bearer\s(\S+)/', $authHeader, $matches);
$token = $matches[1] ?? '';

try {
    $conn = getDBConnection();
    
    // Get session info for logging
    $stmt = $conn->prepare("
        SELECT user_id, ip_address FROM user_sessions WHERE token = ? AND is_active = 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $session = $result->fetch_assoc();
    $stmt->close();
    
    // Revoke session
    $stmt = $conn->prepare("
        UPDATE user_sessions 
        SET is_active = 0 
        WHERE token = ? AND user_id = ?
    ");
    $stmt->bind_param("si", $token, $userId);
    $stmt->execute();
    $stmt->close();
    
    // Log activity
    if ($session) {
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, activity_type, description, ip_address, created_at)
            VALUES (?, 'logout', 'User logged out successfully', ?, NOW())
        ");
        $logStmt->bind_param("is", $userId, $session['ip_address']);
        $logStmt->execute();
        $logStmt->close();
    }
    
    $conn->close();
    
    sendSuccess('Logged out successfully');
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}