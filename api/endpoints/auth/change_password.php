<?php
/**
 * Change Password Endpoint
 * POST /api/auth/change-password
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';
require_once dirname(__DIR__, 2) . '/includes/validation.php';

$auth = new Auth();
$user = $auth->authenticate();

if (!$user) {
    Response::unauthorized();
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data) {
    Response::error('Invalid request data', 400);
}

$errors = Validator::required($data, ['current_password', 'new_password']);
if (!empty($errors)) {
    Response::validationError($errors);
}

$currentPassword = $data['current_password'];
$newPassword = $data['new_password'];

if (strlen($newPassword) < 8) {
    Response::error('New password must be at least 8 characters', 422);
}

$db = Database::getInstance();

// Get current password hash
$stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

if (!$auth->verifyPassword($currentPassword, $userData['password_hash'])) {
    Response::error('Current password is incorrect', 401);
}

// Hash new password
$newHash = $auth->hashPassword($newPassword);

// Update password
$stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$stmt->bind_param("si", $newHash, $user['id']);
$stmt->execute();
$stmt->close();

// Log activity
$db->query("
    INSERT INTO activity_logs (user_id, activity_type, description, created_at)
    VALUES ({$user['id']}, 'password_change', 'Password changed successfully', NOW())
");

Response::success(null, 'Password changed successfully');
?>