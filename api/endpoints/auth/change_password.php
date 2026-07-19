<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

// Only POST method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

$userId = validateToken();

$input = json_decode(file_get_contents('php://input'), true);
$currentPassword = isset($input['current_password']) ? trim($input['current_password']) : '';
$newPassword = isset($input['new_password']) ? trim($input['new_password']) : '';

if (empty($currentPassword) || empty($newPassword)) {
    sendError('Current password and new password are required', HTTP_BAD_REQUEST);
}

if (strlen($newPassword) < 6) {
    sendError('New password must be at least 6 characters', HTTP_BAD_REQUEST);
}

try {
    $conn = getDBConnection();
    
    // Get user's current password hash
    $stmt = $conn->prepare("
        SELECT id, password_hash FROM users WHERE id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'])) {
            sendError('Current password is incorrect', HTTP_UNAUTHORIZED);
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $updateStmt = $conn->prepare("
            UPDATE users SET password_hash = ? WHERE id = ?
        ");
        $updateStmt->bind_param("si", $hashedPassword, $userId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Log activity
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, activity_type, description, created_at)
            VALUES (?, 'password_change', 'Password changed successfully', NOW())
        ");
        $logStmt->bind_param("i", $userId);
        $logStmt->execute();
        $logStmt->close();
        
        // Revoke all sessions except current
        $revokeStmt = $conn->prepare("
            UPDATE user_sessions 
            SET is_active = 0 
            WHERE user_id = ? AND token != (
                SELECT token FROM (
                    SELECT token FROM user_sessions WHERE user_id = ? AND is_active = 1 LIMIT 1
                ) AS t
            )
        ");
        $revokeStmt->bind_param("ii", $userId, $userId);
        $revokeStmt->execute();
        $revokeStmt->close();
        
        sendSuccess('Password changed successfully');
    } else {
        sendError('User not found', HTTP_NOT_FOUND);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}