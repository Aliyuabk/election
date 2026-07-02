<?php
// ============================================================
// TWO FACTOR SETUP - Enable/Disable 2FA
// ============================================================
require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

SessionManager::start();

// Check if logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = SessionManager::get('user_id');
$db = getDB();
$error = '';
$success = '';
$otp = '';
$show_otp_verify = false;

// Get current 2FA status
$stmt = $db->prepare("SELECT two_factor_enabled, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'enable') {
        // Generate OTP for verification
        $otp = generateOTP();
        $result = saveOTP($user_id, $otp, '2fa_enable', 'email');
        
        if ($result) {
            // Send OTP via email
            $emailResult = sendOTPEmail($user['email'], $otp, SessionManager::get('user_name'));
            
            if ($emailResult['success']) {
                $_SESSION['2fa_setup_otp'] = $otp;
                $_SESSION['2fa_setup_step'] = 'verify';
                $show_otp_verify = true;
                $success = 'OTP sent to your email. Please verify to enable 2FA.';
            } else {
                $error = 'Failed to send OTP: ' . $emailResult['message'];
            }
        } else {
            $error = 'Failed to generate OTP. Please try again.';
        }
    } elseif ($action === 'verify' && isset($_POST['otp_code'])) {
        $otp_code = trim($_POST['otp_code']);
        
        if (verifyOTP($user_id, $otp_code, '2fa_enable')) {
            // Enable 2FA
            $stmt = $db->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?");
            $stmt->execute([generateRandomToken(32), $user_id]);
            
            logActivity($user_id, '2fa_enabled', 'Two-factor authentication enabled');
            logSecurityEvent($user_id, '2fa_enabled', '2FA enabled from IP: ' . getClientIP());
            
            $success = 'Two-factor authentication has been enabled successfully!';
            unset($_SESSION['2fa_setup_step']);
            unset($_SESSION['2fa_setup_otp']);
            
            // Refresh user data
            $stmt = $db->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } else {
            $error = 'Invalid OTP code. Please try again.';
            $show_otp_verify = true;
        }
    } elseif ($action === 'disable') {
        // Disable 2FA
        $stmt = $db->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        
        logActivity($user_id, '2fa_disabled', 'Two-factor authentication disabled');
        logSecurityEvent($user_id, '2fa_disabled', '2FA disabled from IP: ' . getClientIP());
        
        $success = 'Two-factor authentication has been disabled.';
        
        // Refresh user data
        $stmt = $db->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
}

// Check if we're in verify step
if (isset($_SESSION['2fa_setup_step']) && $_SESSION['2fa_setup_step'] === 'verify') {
    $show_otp_verify = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>2FA Setup - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&family=Poppins:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #F8FAFC;
            color: #0F172A;
            line-height: 1.6;
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container { max-width: 500px; margin: 0 auto; }
        .card { background: white; border-radius: 32px; padding: 48px 40px; box-shadow: 0 20px 60px rgba(15, 76, 129, 0.08); border: 1px solid #E2E8F0; }
        .header { text-align: center; margin-bottom: 32px; }
        .header h1 { font-size: 1.8rem; color: #0F4C81; }
        .header p { color: #64748B; }
        .status-badge { display: inline-block; padding: 4px 16px; border-radius: 30px; font-size: 0.85rem; font-weight: 600; }
        .status-badge.enabled { background: #ECFDF5; color: #065F46; }
        .status-badge.disabled { background: #FEF2F2; color: #DC2626; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; color: #0F172A; }
        .form-group input { width: 100%; padding: 14px 16px; border: 1.5px solid #E2E8F0; border-radius: 14px; font-family: 'Inter', sans-serif; font-size: 0.95rem; background: #F8FAFC; transition: all 0.2s; color: #0F172A; text-align: center; letter-spacing: 4px; }
        .form-group input:focus { outline: none; border-color: #2563EB; background: white; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.06); }
        .btn-primary { width: 100%; padding: 16px; border: none; border-radius: 14px; background: #0F4C81; color: white; font-size: 1rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-primary:hover { background: #1a3f6a; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(15, 76, 129, 0.2); }
        .btn-danger { background: #DC2626; }
        .btn-danger:hover { background: #B91C1C; }
        .btn-secondary { background: #F1F5F9; color: #0F172A; }
        .btn-secondary:hover { background: #E2E8F0; }
        .error-message { background: #FEF2F2; color: #DC2626; padding: 12px 16px; border-radius: 12px; font-size: 0.9rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; border: 1px solid #FECACA; }
        .success-message { background: #ECFDF5; color: #065F46; padding: 12px 16px; border-radius: 12px; font-size: 0.9rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; border: 1px solid #A7F3D0; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #64748B; text-decoration: none; font-size: 0.9rem; transition: 0.15s; display: inline-flex; align-items: center; gap: 8px; }
        .back-link a:hover { color: #0F4C81; }
        .info-box { background: #F1F5F9; padding: 16px; border-radius: 12px; margin: 16px 0; }
        .info-box i { color: #2563EB; margin-right: 8px; }
        .otp-hint { text-align: center; color: #64748B; font-size: 0.9rem; margin: 10px 0; }
        @media (max-width: 480px) { .card { padding: 32px 24px; } }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="header">
            <h1>Two-Factor Authentication</h1>
            <p>Add an extra layer of security to your account</p>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 16px; background: #F8FAFC; border-radius: 12px;">
            <span style="font-weight: 600;">Current Status:</span>
            <span class="status-badge <?php echo $user['two_factor_enabled'] ? 'enabled' : 'disabled'; ?>">
                <?php echo $user['two_factor_enabled'] ? '✅ Enabled' : '❌ Disabled'; ?>
            </span>
        </div>
        
        <?php if ($show_otp_verify): ?>
        <!-- Verify OTP Step -->
        <form method="POST" action="">
            <input type="hidden" name="action" value="verify" />
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                Enter the 6-digit code sent to your email to enable 2FA.
            </div>
            <div class="otp-hint">
                <i class="fas fa-envelope"></i> Code sent to: <?php echo htmlspecialchars($user['email']); ?>
            </div>
            <div class="form-group">
                <label for="otp_code">Enter OTP Code</label>
                <input type="text" id="otp_code" name="otp_code" placeholder="000000" maxlength="6" required />
            </div>
            <button type="submit" class="btn-primary">
                <i class="fas fa-check-circle"></i> Verify & Enable 2FA
            </button>
            <div style="text-align: center; margin-top: 10px;">
                <small style="color: #64748B;">Didn't receive the code? <a href="?resend=1" style="color: #2563EB;">Resend</a></small>
            </div>
        </form>
        <?php elseif ($user['two_factor_enabled']): ?>
        <!-- 2FA Enabled - Disable Option -->
        <form method="POST" action="">
            <input type="hidden" name="action" value="disable" />
            <div class="info-box">
                <i class="fas fa-shield-alt" style="color: #10B981;"></i>
                Two-factor authentication is currently <strong>enabled</strong> on your account.
            </div>
            <button type="submit" class="btn-primary btn-danger" onclick="return confirm('Are you sure you want to disable 2FA?');">
                <i class="fas fa-times-circle"></i> Disable 2FA
            </button>
        </form>
        <?php else: ?>
        <!-- 2FA Disabled - Enable Option -->
        <form method="POST" action="">
            <input type="hidden" name="action" value="enable" />
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                When enabled, you'll need to enter a 6-digit code from your email each time you login.
            </div>
            <button type="submit" class="btn-primary">
                <i class="fas fa-shield-alt"></i> Enable 2FA
            </button>
        </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="../dashboard/index.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>
</body>
</html>