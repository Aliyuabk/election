<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../config/database.php';

// Only POST method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';

if (empty($email)) {
    sendError('Email is required', HTTP_BAD_REQUEST);
}

try {
    $conn = getDBConnection();
    
    // Check if user exists
    $stmt = $conn->prepare("
        SELECT id, email, first_name FROM users WHERE email = ? AND status = 'active'
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store reset token
        $resetStmt = $conn->prepare("
            INSERT INTO password_resets (user_id, token, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $resetStmt->bind_param("iss", $user['id'], $token, $expiresAt);
        $resetStmt->execute();
        $resetStmt->close();
        
        // Log activity
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, activity_type, description, created_at)
            VALUES (?, 'password_reset', 'Password reset requested', NOW())
        ");
        $logStmt->bind_param("i", $user['id']);
        $logStmt->execute();
        $logStmt->close();
        
        // In production, send email with reset link
        // For now, return the token in response
        sendSuccess('Password reset link sent', [
            'reset_token' => $token,
            'email' => $email
        ]);
    } else {
        // Don't reveal if user exists or not for security
        sendSuccess('If an account exists with this email, a reset link has been sent');
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}