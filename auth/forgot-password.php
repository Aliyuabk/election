<?php
// ============================================================
// FORGOT PASSWORD - Request password reset link
// ============================================================
require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

SessionManager::start();

$error = '';
$success = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please try again.';
        logSecurityEvent(null, 'csrf_validation_failed', 'CSRF token validation failed on forgot password');
    } else {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $db = getDB();
                
                // Check rate limiting - max 3 requests per hour per email
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count FROM password_resets pr
                    JOIN users u ON pr.user_id = u.id
                    WHERE u.email = ? AND pr.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ");
                $stmt->execute([$email]);
                $result = $stmt->fetch();
                
                if ($result && $result['count'] >= 3) {
                    $error = 'Too many password reset requests. Please try again after 1 hour.';
                    logSecurityEvent(null, 'rate_limit_exceeded', "Password reset rate limit exceeded for email: {$email}", 50);
                } else {
                    // Find user by email
                    $stmt = $db->prepare("SELECT id, email, first_name, full_name FROM users WHERE email = ? AND status = 'active'");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        // Generate reset token
                        $raw_token = generateRandomToken(32);
                        
                        // Calculate expiry (1 hour from now) using PHP timezone
                        $expires = date('Y-m-d H:i:s', time() + 3600);
                        
                        // Delete any existing reset tokens for this user
                        $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
                        $stmt->execute([$user['id']]);
                        
                        // Insert new reset token
                        $stmt = $db->prepare("
                            INSERT INTO password_resets (user_id, token, expires_at, used, created_at) 
                            VALUES (?, ?, ?, 0, NOW())
                        ");
                        $stmt->execute([$user['id'], $raw_token, $expires]);
                        
                        // Build reset link with raw token
                        $resetLink = APP_URL . 'auth/reset-password.php?token=' . urlencode($raw_token) . '&email=' . urlencode($email);
                        
                        // Send email with reset link
                        $name = $user['full_name'] ?? $user['first_name'] ?? 'User';
                        sendPasswordResetEmail($email, $resetLink, $name);
                        
                        logActivity($user['id'], 'password_reset', 'Password reset requested');
                        logSecurityEvent($user['id'], 'password_reset', 'Password reset requested from IP: ' . getClientIP());
                        
                        $success = 'Password reset link has been sent to your email. Please check your inbox.';
                        
                        // Clear CSRF token after successful submission
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    } else {
                        // Don't reveal if email exists or not for security
                        // Add random delay to prevent timing attacks
                        usleep(rand(100000, 500000));
                        $success = 'If an account exists with that email, a password reset link has been sent.';
                    }
                }
            } catch (Exception $e) {
                error_log("Forgot password error: " . $e->getMessage());
                $error = 'An error occurred. Please try again later.';
            }
        }
    }
}

// Generate new CSRF token for the form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
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
        .login-card h2 { font-size: 1.3rem; margin-bottom: 4px; }
        .login-card .subtitle { color: #64748B; margin-bottom: 20px; }
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
        @media (max-width: 480px) { .login-card { padding: 32px 24px; border-radius: 24px; } }
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
        
        <h2>Forgot Password</h2>
        <p class="subtitle">Enter your email and we'll send you a reset link.</p>
        
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
        
        <?php if (empty($success)): ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required autofocus />
                </div>
            </div>
            
            <button type="submit" class="btn-login" id="submitBtn">
                <i class="fas fa-paper-plane"></i>
                <span id="btnText">Send Reset Link</span>
                <span id="btnSpinner" class="fas fa-spinner fa-spin" style="display: none;"></span>
            </button>
        </form>
        
        <script>
        document.getElementById('submitBtn').addEventListener('click', function(e) {
            const btn = this;
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            
            btn.disabled = true;
            btnText.textContent = 'Sending...';
            btnSpinner.style.display = 'inline-block';
            
            // Form will submit normally
        });
        </script>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</div>
</body>
</html>