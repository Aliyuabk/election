<?php
// ============================================================
// RESEND OTP - AJAX endpoint to resend OTP
// ============================================================
require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

SessionManager::start();

header('Content-Type: application/json');

// Check if user is in OTP session
if (!SessionManager::has('2fa_user_id')) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit();
}

$user_id = SessionManager::get('2fa_user_id');
$user = getUserById($user_id);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit();
}

// Generate new OTP
$otp = generateOTP();
saveOTP($user['id'], $otp, 'login', 'email');

// Send OTP via email
$result = sendOTPEmail($user['email'], $otp, $user['first_name']);

if ($result['success']) {
    // Update session with new OTP info
    $_SESSION['2fa_email'] = $user['email'];
    echo json_encode(['success' => true, 'message' => 'OTP resent successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send OTP: ' . $result['message']]);
}
?>