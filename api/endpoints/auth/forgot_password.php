<?php
/**
 * Forgot Password Endpoint
 * POST /api/auth/forgot-password
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';
require_once dirname(__DIR__, 2) . '/includes/validation.php';

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data || !isset($data['email'])) {
    Response::error('Email is required', 400);
}

$email = Validator::sanitize($data['email']);

if (!Validator::validateEmail($email)) {
    Response::error('Invalid email format', 422);
}

$db = Database::getInstance();

// Check if user exists
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Don't reveal if email exists or not for security
    Response::success(null, 'If your email is registered, you will receive a reset link');
}

$user = $result->fetch_assoc();
$stmt->close();

// Generate reset token
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Save token
$stmt = $db->prepare("
    INSERT INTO password_resets (user_id, token, expires_at, created_at)
    VALUES (?, ?, ?, NOW())
");
$stmt->bind_param("iss", $user['id'], $token, $expires);
$stmt->execute();
$stmt->close();

// Log activity
$db->query("
    INSERT INTO activity_logs (user_id, activity_type, description, created_at)
    VALUES ({$user['id']}, 'password_reset', 'Password reset requested', NOW())
");

// TODO: Send email with reset link
// $resetLink = API_BASE_URL . 'reset-password?token=' . $token;

Response::success(null, 'If your email is registered, you will receive a reset link');
?>