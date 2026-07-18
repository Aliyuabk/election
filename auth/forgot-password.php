<?php
// ============================================================
// FORGOT PASSWORD - Request password reset
// ============================================================
require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

SessionManager::start();

// If already logged in, redirect to dashboard
if (SessionManager::isLoggedIn()) {
    header('Location: ../dashboard/index.php');
    exit();
}

$message = '';
$error = '';
$email = '';

// ============================================================
// DYNAMIC URL HELPER - Gets the current base URL
// ============================================================
function getCurrentBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    
    // Get the directory where the script is running
    $script_dir = dirname($script_name);
    
    // Remove trailing slash if exists
    $script_dir = rtrim($script_dir, '/');
    
    // If we're in a subdirectory, include it
    $base_url = $protocol . $host . $script_dir;
    
    // Get the parent directory (up one level from 'auth' folder)
    $base_url = dirname($base_url);
    
    // If we're at root, make sure it's properly formatted
    if ($base_url === $protocol . $host) {
        $base_url = $protocol . $host;
    }
    
    return $base_url;
}

// ============================================================
// GET CURRENT APP URL - Uses defined APP_URL or falls back to dynamic
// ============================================================
function getAppUrl() {
    // Try to use the defined APP_URL first
    if (defined('APP_URL') && !empty(APP_URL) && APP_URL !== 'http://localhost/election') {
        return rtrim(APP_URL, '/');
    }
    
    // Fallback to dynamic URL
    return getCurrentBaseUrl();
}

// ============================================================
// DEBUG: Log the app URL for troubleshooting
// ============================================================
function logDebug($message) {
    error_log("Forgot Password Debug: " . $message);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $db = getDB();
            
            // Check if user exists
            $stmt = $db->prepare("SELECT id, email, first_name, last_name, status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && $user['status'] === 'active') {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Delete old tokens for this user
                $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                
                // Save token to database
                $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $token, $expires]);
                
                // ============================================================
                // GENERATE RESET LINK - FIXED FOR CPANEL
                // ============================================================
                $app_url = getAppUrl();
                $resetLink = $app_url . '/auth/reset-password.php?token=' . $token;
                
                // Alternative: Use the full current URL
                // This is more reliable for cPanel
                $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                $base_url = dirname(dirname($current_url)); // Go up two levels from auth/
                $resetLink2 = $base_url . '/auth/reset-password.php?token=' . $token;
                
                // DEBUG: Log both links
                logDebug("APP_URL: " . ($app_url ?? 'not set'));
                logDebug("Reset Link 1: " . $resetLink);
                logDebug("Reset Link 2: " . $resetLink2);
                logDebug("Current URL: " . $current_url);
                logDebug("Base URL: " . $base_url);
                
                // Use the second method which is more reliable
                $resetLink = $resetLink2;
                
                // Send reset email
                $result = sendPasswordResetEmail($user['email'], $resetLink, $user['first_name']);
                
                if ($result['success']) {
                    // Log activity
                    try {
                        logActivity($user['id'], 'password_reset', 'Password reset requested');
                        logSecurityEvent($user['id'], 'password_reset', 'Password reset requested from IP: ' . getClientIP());
                    } catch (Exception $e) {
                        error_log("Activity log failed: " . $e->getMessage());
                    }
                    
                    $message = 'Password reset link has been sent to your email. Please check your inbox.';
                    $email = ''; // Clear email field
                } else {
                    $error = 'Failed to send reset email. Please try again later.';
                    error_log("Password reset email failed: " . ($result['message'] ?? 'Unknown error'));
                }
            } else {
                // Don't reveal if user exists or not for security
                $message = 'If the email exists in our system, a reset link has been sent.';
            }
        } catch (PDOException $e) {
            error_log("Forgot password database error: " . $e->getMessage());
            $error = 'A database error occurred. Please try again later.';
        } catch (Exception $e) {
            error_log("Forgot password general error: " . $e->getMessage());
            $error = 'An unexpected error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&family=Poppins:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #F8FAFC;
            color: #0F172A;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #F8FAFC 0%, #eef4fa 100%);
        }
        .login-wrapper { width: 100%; max-width: 440px; padding: 20px; animation: fadeIn 0.6s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .login-card { background: white; border-radius: 32px; padding: 48px 40px; box-shadow: 0 20px 60px rgba(15, 76, 129, 0.08); border: 1px solid #E2E8F0; }
        .login-logo { text-align: center; margin-bottom: 32px; }
        .login-logo a { display: inline-flex; align-items: center; gap: 10px; font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.8rem; color: #0F4C81; text-decoration: none; }
        .login-logo i { font-size: 2.2rem; color: #2563EB; }
        .login-logo p { color: #64748B; font-size: 0.95rem; margin-top: 4px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; color: #0F172A; }
        .form-group .input-wrapper { position: relative; }
        .form-group .input-wrapper i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 1rem; }
        .form-group input { width: 100%; padding: 14px 16px 14px 46px; border: 1.5px solid #E2E8F0; border-radius: 14px; font-family: 'Inter', sans-serif; font-size: 0.95rem; background: #F8FAFC; transition: all 0.2s; color: #0F172A; }
        .form-group input:focus { outline: none; border-color: #2563EB; background: white; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.06); }
        .btn-login { width: 100%; padding: 16px; border: none; border-radius: 14px; background: #0F4C81; color: white; font-size: 1rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-login:hover { background: #1a3f6a; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(15, 76, 129, 0.2); }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; }
        .error-message { background: #FEF2F2; color: #DC2626; padding: 12px 16px; border-radius: 12px; font-size: 0.9rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; border: 1px solid #FECACA; }
        .error-message i { font-size: 1.1rem; }
        .success-message { background: #ECFDF5; color: #065F46; padding: 12px 16px; border-radius: 12px; font-size: 0.9rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; border: 1px solid #A7F3D0; }
        .success-message i { font-size: 1.1rem; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #64748B; text-decoration: none; font-size: 0.9rem; transition: 0.15s; display: inline-flex; align-items: center; gap: 8px; }
        .back-link a:hover { color: #0F4C81; }
        .info-text { color: #64748B; margin-bottom: 20px; font-size: 0.95rem; }
        .info-text i { color: #2563EB; }
        @media (max-width: 480px) { .login-card { padding: 32px 24px; border-radius: 24px; } .login-logo a { font-size: 1.5rem; } }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <a href="../index.php">
                <i class="fas fa-bolt"></i>
                <?php echo APP_NAME; ?>
            </a>
            <p>Reset your password</p>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($message)): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="resetForm">
            <p class="info-text">
                <i class="fas fa-info-circle"></i>
                Enter your email address and we'll send you a link to reset your password.
            </p>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="admin@organization.ng" value="<?php echo htmlspecialchars($email); ?>" required autofocus />
                </div>
            </div>
            
            <button type="submit" class="btn-login" id="submitBtn">
                <i class="fas fa-paper-plane"></i>
                Send Reset Link
            </button>
        </form>
        
        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetForm');
    const submitBtn = document.getElementById('submitBtn');
    const emailInput = document.getElementById('email');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            setTimeout(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Reset Link';
            }, 5000);
        });
    }
    
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const email = this.value.trim();
            if (email.length > 0 && !email.includes('@')) {
                this.style.borderColor = '#F59E0B';
            } else if (email.length > 0 && email.includes('@') && email.includes('.')) {
                this.style.borderColor = '#10B981';
            } else {
                this.style.borderColor = '#E2E8F0';
            }
        });
    }
});
</script>
</body>
</html>