<?php
// ============================================================
// RESEND OTP - AJAX endpoint to resend OTP
// ============================================================
require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Set headers
header('Content-Type: application/json');

// Start session
SessionManager::start();

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

try {
    // Delete old OTPs for this user
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM otp_verifications WHERE user_id = ? AND type = 'login'");
    $stmt->execute([$user_id]);
    
    // Generate new OTP
    $otp = '';
    for ($i = 0; $i < 6; $i++) {
        $otp .= rand(0, 9);
    }
    
    // Save OTP
    $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY);
    $stmt = $db->prepare("
        INSERT INTO otp_verifications (user_id, otp_code, type, channel, expires_at, used, attempts) 
        VALUES (?, ?, 'login', 'email', ?, 0, 0)
    ");
    $stmt->execute([$user_id, $otp, $expires]);
    
    // Send OTP via email
    $result = sendOTPEmail($user['email'], $otp, $user['first_name']);
    
    if ($result['success']) {
        // Update session with new OTP info
        $_SESSION['2fa_email'] = $user['email'];
        $_SESSION['2fa_otp_sent_at'] = time();
        
        // Log activity
        logActivity($user_id, 'otp_resend', 'OTP resent for login');
        
        echo json_encode([
            'success' => true, 
            'message' => 'OTP resent successfully. Please check your email.'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send OTP: ' . ($result['message'] ?? 'Unknown error')
        ]);
    }
} catch (Exception $e) {
    error_log("Resend OTP error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again.'
    ]);
}
exit;
?>