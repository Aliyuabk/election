<?php
// ============================================================
// FORGOT PASSWORD - 5G ELECTION GURU
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

// Initialize session
SessionManager::start();

// If already logged in, redirect to dashboard
if (SessionManager::isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Generate CAPTCHA if not set
if (empty($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = generateCaptcha();
}

// Handle Form Submission
$message = '';
$message_type = '';
$email = '';
$show_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'danger';
        logSecurityEvent(null, 'csrf_validation_failed', 'CSRF token validation failed on forgot password');
    } else {
        $email = trim($_POST['email'] ?? '');
        $captcha_input = trim($_POST['captcha'] ?? '');
        $captcha_expected = $_SESSION['captcha_code'] ?? '';
        
        // Validate email
        if (empty($email)) {
            $message = 'Please enter your email address.';
            $message_type = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $message_type = 'danger';
        } elseif (empty($captcha_input) || strtoupper($captcha_input) !== $captcha_expected) {
            $message = 'Invalid CAPTCHA code. Please try again.';
            $message_type = 'danger';
            $_SESSION['captcha_code'] = generateCaptcha();
        } else {
            // Check rate limiting - prevent abuse (using email only since no ip_address in password_resets)
            $db = getDB();
            
            $stmt = $db->prepare("
                SELECT COUNT(*) as count FROM password_resets pr
                JOIN users u ON pr.user_id = u.id
                WHERE u.email = ? AND pr.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$email]);
            $result = $stmt->fetch();
            
            if ($result && $result['count'] >= 3) {
                $message = 'Too many password reset requests. Please try again after 1 hour.';
                $message_type = 'danger';
                logSecurityEvent(null, 'rate_limit_exceeded', "Password reset rate limit exceeded for email: {$email}", 50);
            } else {
                // Check if user exists
                $user = getUserByEmail($email);
                
                if ($user) {
                    // Generate reset token
                    $token = generateRandomToken(32);
                    
                    // Store in database using the function
                    createPasswordReset($user['id'], $token);
                    
                    // Build reset link
                    $reset_link = APP_URL . 'reset-password.php?token=' . $token . '&email=' . urlencode($email);
                    
                    // Send email
                    $name = $user['full_name'] ?? $user['first_name'] . ' ' . $user['last_name'];
                    $email_result = sendPasswordResetEmail($email, $reset_link, $name);
                    
                    if ($email_result['success']) {
                        $message = 'Password reset link has been sent to your email address. Please check your inbox.';
                        $message_type = 'success';
                        $show_success = true;
                        logActivity($user['id'], 'password_reset', 'Password reset requested');
                        
                        // Clear CAPTCHA for security
                        $_SESSION['captcha_code'] = generateCaptcha();
                    } else {
                        $message = 'Unable to send reset email. Please try again later.';
                        $message_type = 'danger';
                        error_log("Password reset email failed for {$email}: " . $email_result['message']);
                    }
                } else {
                    // Don't reveal if email exists or not (security)
                    $message = 'If an account exists with this email, a reset link has been sent.';
                    $message_type = 'success';
                    $show_success = true;
                    
                    // Random delay to prevent timing attacks
                    usleep(rand(100000, 500000));
                }
            }
        }
    }
}

// Get current year
$current_year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Forgot Password - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* [Same styles as login page - copy from login.php styles] */
        :root {
            --primary: #0F4C81;
            --primary-dark: #0A3A63;
            --primary-light: #E8F0FE;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0F4C81 0%, #1a6bb0 50%, #3a8fd4 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            animation: float 15s infinite linear;
        }

        .particle:nth-child(1) { left: 10%; animation-duration: 18s; animation-delay: 0s; }
        .particle:nth-child(2) { left: 20%; animation-duration: 22s; animation-delay: 2s; width: 6px; height: 6px; }
        .particle:nth-child(3) { left: 35%; animation-duration: 14s; animation-delay: 4s; }
        .particle:nth-child(4) { left: 50%; animation-duration: 20s; animation-delay: 1s; width: 8px; height: 8px; }
        .particle:nth-child(5) { left: 65%; animation-duration: 16s; animation-delay: 3s; }
        .particle:nth-child(6) { left: 75%; animation-duration: 24s; animation-delay: 5s; width: 5px; height: 5px; }
        .particle:nth-child(7) { left: 85%; animation-duration: 19s; animation-delay: 2s; }
        .particle:nth-child(8) { left: 95%; animation-duration: 21s; animation-delay: 0s; width: 7px; height: 7px; }

        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }

        .reset-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
        }

        .reset-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .reset-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.3);
        }

        .brand {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(15, 76, 129, 0.25);
        }

        .brand h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: -0.5px;
            margin: 0 0 4px 0;
        }

        .brand p {
            color: #6B7280;
            font-size: 14px;
            margin: 0;
        }

        .alert {
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 14px;
            border: none;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-danger {
            background: #FEF2F2;
            color: #991B1B;
            border-left: 4px solid #EF4444;
        }

        .alert-success {
            background: #F0FDF4;
            color: #065F46;
            border-left: 4px solid #10B981;
        }

        .alert i { font-size: 18px; margin-top: 1px; }
        .alert .alert-link { color: inherit; font-weight: 600; text-decoration: underline; }

        .form-group { margin-bottom: 20px; }

        .form-label {
            font-weight: 500;
            font-size: 14px;
            color: #374151;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-label .required { color: #EF4444; }

        .input-group {
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #E5E7EB;
            background: white;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .input-group:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15, 76, 129, 0.1);
        }

        .input-group .input-group-text {
            background: transparent;
            border: none;
            color: #9CA3AF;
            padding: 0 0 0 16px;
            font-size: 18px;
        }

        .input-group .form-control {
            border: none;
            padding: 12px 16px;
            font-size: 15px;
            background: transparent;
            box-shadow: none;
            height: 50px;
        }

        .input-group .form-control:focus { box-shadow: none; }
        .input-group .form-control::placeholder { color: #9CA3AF; }

        .captcha-container {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .captcha-box {
            background: linear-gradient(135deg, #F3F4F6, #E5E7EB);
            border-radius: 12px;
            padding: 8px 16px;
            font-family: 'Courier New', monospace;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 6px;
            color: var(--primary);
            min-width: 120px;
            text-align: center;
            user-select: none;
            border: 2px dashed #D1D5DB;
            line-height: 50px;
            height: 50px;
        }

        .captcha-refresh {
            background: transparent;
            border: none;
            color: var(--primary);
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .captcha-refresh:hover { background: rgba(15, 76, 129, 0.08); }
        .captcha-refresh i { transition: transform 0.5s ease; }
        .captcha-refresh:hover i { transform: rotate(180deg); }

        .captcha-input .form-control {
            height: 50px;
            padding: 12px 16px;
            font-size: 20px;
            letter-spacing: 4px;
            font-family: 'Courier New', monospace;
            text-transform: uppercase;
        }

        .btn-reset {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            transition: all 0.3s ease;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(15, 76, 129, 0.35);
            color: white;
        }

        .btn-reset:active { transform: translateY(0px); }
        .btn-reset:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.2s;
        }

        .btn-back:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .auth-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #F3F4F6;
        }

        .auth-footer p {
            font-size: 14px;
            color: #6B7280;
            margin: 0;
        }

        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .auth-footer a:hover { text-decoration: underline; }

        .footer-bottom {
            text-align: center;
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 13px;
        }

        .footer-bottom a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }

        .footer-bottom a:hover {
            color: white;
            text-decoration: underline;
        }

        .is-invalid { border-color: #EF4444 !important; }
        .is-invalid:focus-within {
            border-color: #EF4444 !important;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1) !important;
        }

        .invalid-feedback {
            color: #EF4444;
            font-size: 13px;
            margin-top: 4px;
        }

        @media (max-width: 480px) {
            .reset-card { padding: 32px 24px; border-radius: 20px; }
            .brand h1 { font-size: 20px; }
            .captcha-box { font-size: 22px; min-width: 100px; height: 44px; line-height: 44px; }
            .captcha-refresh { width: 44px; height: 44px; font-size: 20px; }
            .btn-reset { height: 48px; font-size: 15px; }
        }

        @media (max-width: 360px) {
            .reset-card { padding: 24px 16px; }
            .captcha-container { flex-wrap: wrap; justify-content: center; }
            .captcha-box { min-width: 80px; font-size: 18px; letter-spacing: 3px; height: 38px; line-height: 38px; }
        }
    </style>
</head>
<body>

<div class="particles">
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
</div>

<div class="reset-container">
    <div class="reset-card">
        <div class="brand">
            <div class="brand-icon">
                <i class="bi bi-key"></i>
            </div>
            <h1>Reset Password</h1>
            <p>Enter your email to receive a reset link</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>" role="alert">
                <i class="bi bi-<?= $message_type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?>"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!$show_success): ?>
        <form method="POST" action="" id="resetForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="form-group">
                <label class="form-label" for="email">
                    <i class="bi bi-envelope"></i> Email Address
                    <span class="required">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           placeholder="you@example.com"
                           value="<?= htmlspecialchars($email) ?>" 
                           required 
                           autofocus
                           aria-describedby="emailHelp">
                </div>
                <div class="invalid-feedback" id="emailHelp"></div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-shield-check"></i> Security Verification
                    <span class="required">*</span>
                </label>
                <div class="captcha-container">
                    <div class="captcha-box" id="captchaDisplay">
                        <?= htmlspecialchars($_SESSION['captcha_code']) ?>
                    </div>
                    <button type="button" class="captcha-refresh" onclick="refreshCaptcha()" aria-label="Refresh CAPTCHA">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
                <div class="captcha-input" style="margin-top: 10px;">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="text" 
                               class="form-control" 
                               id="captcha" 
                               name="captcha" 
                               placeholder="Enter CAPTCHA code" 
                               maxlength="6"
                               autocomplete="off"
                               required
                               style="text-transform: uppercase; letter-spacing: 4px; font-family: 'Courier New', monospace;">
                    </div>
                </div>
                <div class="invalid-feedback" id="captchaHelp"></div>
            </div>

            <button type="submit" class="btn-reset" id="resetBtn">
                <span id="btnText"><i class="bi bi-envelope-paper"></i> Send Reset Link</span>
                <span id="btnSpinner" class="spinner-border spinner-border-sm" style="display: none;" role="status" aria-hidden="true"></span>
            </button>

            <div style="margin-top: 16px; text-align: center;">
                <a href="login.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </div>
        </form>
        <?php else: ?>
        <div style="text-align: center; margin-top: 16px;">
            <p style="color: #6B7280; font-size: 14px; margin-bottom: 16px;">
                <i class="bi bi-info-circle"></i> If you don't see the email, please check your spam folder.
            </p>
            <a href="login.php" class="btn-reset" style="text-decoration: none; display: inline-block; padding: 12px 32px; height: auto; width: auto;">
                <i class="bi bi-box-arrow-in-right"></i> Return to Login
            </a>
            <br><br>
            <a href="forgot-password.php" style="color: var(--primary); text-decoration: none; font-size: 14px;">
                <i class="bi bi-arrow-repeat"></i> Resend reset link
            </a>
        </div>
        <?php endif; ?>

        <div class="auth-footer">
            <p>Remember your password? <a href="login.php">Sign In</a></p>
        </div>
    </div>

    <div class="footer-bottom">
        &copy; <?= $current_year ?> <?= APP_NAME ?>. All rights reserved.
    </div>
</div>

<script>
function refreshCaptcha() {
    fetch('refresh-captcha.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('captchaDisplay').textContent = data.captcha;
                document.getElementById('captcha').value = '';
                document.getElementById('captcha').focus();
            }
        })
        .catch(error => {
            console.error('Error refreshing CAPTCHA:', error);
        });
}

document.getElementById('resetForm')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('resetBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    
    btn.disabled = true;
    btnText.innerHTML = 'Sending...';
    btnSpinner.style.display = 'inline-block';
});

document.getElementById('resetForm')?.addEventListener('submit', function(e) {
    let isValid = true;
    
    const email = document.getElementById('email');
    const emailHelp = document.getElementById('emailHelp');
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!email.value.trim()) {
        emailHelp.textContent = 'Email address is required.';
        email.closest('.input-group').classList.add('is-invalid');
        isValid = false;
    } else if (!emailPattern.test(email.value.trim())) {
        emailHelp.textContent = 'Please enter a valid email address.';
        email.closest('.input-group').classList.add('is-invalid');
        isValid = false;
    } else {
        email.closest('.input-group').classList.remove('is-invalid');
        emailHelp.textContent = '';
    }
    
    const captcha = document.getElementById('captcha');
    const captchaHelp = document.getElementById('captchaHelp');
    
    if (!captcha.value.trim()) {
        captchaHelp.textContent = 'Please enter the CAPTCHA code.';
        captcha.closest('.input-group').classList.add('is-invalid');
        isValid = false;
    } else if (captcha.value.length < 4) {
        captchaHelp.textContent = 'CAPTCHA code must be at least 4 characters.';
        captcha.closest('.input-group').classList.add('is-invalid');
        isValid = false;
    } else {
        captcha.closest('.input-group').classList.remove('is-invalid');
        captchaHelp.textContent = '';
    }
    
    if (!isValid) {
        e.preventDefault();
        const btn = document.getElementById('resetBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');
        btn.disabled = false;
        btnText.innerHTML = '<i class="bi bi-envelope-paper"></i> Send Reset Link';
        btnSpinner.style.display = 'none';
    }
});

document.getElementById('captcha')?.addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

document.querySelectorAll('.form-control').forEach(input => {
    input.addEventListener('input', function() {
        const group = this.closest('.input-group');
        if (group) {
            group.classList.remove('is-invalid');
        }
        const help = document.getElementById(this.id + 'Help');
        if (help) {
            help.textContent = '';
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>