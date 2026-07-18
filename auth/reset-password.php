<?php
// ============================================================
// RESET PASSWORD - 5G ELECTION GURU
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

// Get token and email from URL
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// Validate token
$valid_token = false;
$user_id = null;
$error = '';

if (empty($token) || empty($email)) {
    $error = 'Invalid reset link. Please request a new password reset.';
} else {
    // Verify token in database
    $reset_record = validatePasswordResetToken($token, $email);
    
    if ($reset_record) {
        $valid_token = true;
        $user_id = $reset_record['user_id'];
    } else {
        // Check if token exists but is expired or used
        $db = getDB();
        $stmt = $db->prepare("
            SELECT pr.*, u.id as user_id 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ? AND u.email = ?
            ORDER BY pr.id DESC LIMIT 1
        ");
        $stmt->execute([$token, $email]);
        $record = $stmt->fetch();
        
        if ($record) {
            if ($record['used'] == 1) {
                $error = 'This reset link has already been used. Please request a new one.';
            } else {
                $error = 'This reset link has expired. Please request a new one.';
            }
        } else {
            $error = 'Invalid reset link. Please request a new password reset.';
        }
    }
}

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle Form Submission
$message = '';
$message_type = '';
$reset_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'danger';
        logSecurityEvent(null, 'csrf_validation_failed', 'CSRF token validation failed on password reset');
    } else {
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        // Validate password
        if (empty($password)) {
            $message = 'Please enter a new password.';
            $message_type = 'danger';
        } elseif (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters long.';
            $message_type = 'danger';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
            $message = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
            $message_type = 'danger';
        } elseif ($password !== $password_confirm) {
            $message = 'Passwords do not match.';
            $message_type = 'danger';
        } else {
            // Update password
            $db = getDB();
            $db->beginTransaction();
            
            try {
                // Update user password
                $hashedPassword = hashPassword($password);
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashedPassword, $user_id]);
                
                // Mark reset token as used
                markPasswordResetUsed($reset_record['id']);
                
                // Log the activity
                logActivity($user_id, 'password_change', 'Password reset successfully');
                
                // Revoke all sessions for security
                revokeAllSessions($user_id);
                
                $db->commit();
                
                $message = 'Your password has been reset successfully. You can now login with your new password.';
                $message_type = 'success';
                $reset_success = true;
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = 'An error occurred. Please try again.';
                $message_type = 'danger';
                error_log("Password reset failed for user {$user_id}: " . $e->getMessage());
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
    <title>Reset Password - <?= APP_NAME ?></title>
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

        .brand .email-display {
            font-weight: 500;
            color: var(--primary);
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

        .input-group .toggle-password {
            background: transparent;
            border: none;
            padding: 0 16px 0 0;
            color: #9CA3AF;
            cursor: pointer;
            font-size: 18px;
            transition: color 0.2s;
        }

        .input-group .toggle-password:hover { color: #374151; }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            border-radius: 4px;
            background: #E5E7EB;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .password-strength .progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease, background 0.3s ease;
            width: 0%;
        }

        .password-strength-text {
            font-size: 12px;
            color: #6B7280;
            margin-top: 4px;
            font-weight: 500;
        }

        .password-requirements {
            font-size: 13px;
            color: #6B7280;
            padding: 8px 12px;
            background: #F9FAFB;
            border-radius: 8px;
            margin-top: 8px;
        }

        .password-requirements .req {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 2px 0;
        }

        .password-requirements .req .req-icon {
            color: #9CA3AF;
            font-size: 14px;
        }

        .password-requirements .req .req-icon.pass { color: var(--success); }
        .password-requirements .req .req-icon.fail { color: var(--danger); }

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
            .btn-reset { height: 48px; font-size: 15px; }
        }

        @media (max-width: 360px) {
            .reset-card { padding: 24px 16px; }
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
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h1>Set New Password</h1>
            <?php if ($valid_token && !$reset_success): ?>
                <p>Create a new password for <span class="email-display"><?= htmlspecialchars($email) ?></span></p>
            <?php else: ?>
                <p>Password reset</p>
            <?php endif; ?>
        </div>

        <?php if ($error && !$valid_token): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
            <div style="text-align: center; margin-top: 16px;">
                <a href="forgot-password.php" class="btn-reset" style="text-decoration: none; display: inline-block; padding: 12px 32px; height: auto; width: auto;">
                    <i class="bi bi-envelope-paper"></i> Request New Reset Link
                </a>
            </div>
        <?php endif; ?>

        <?php if ($reset_success): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
            <div style="text-align: center; margin-top: 16px;">
                <a href="login.php" class="btn-reset" style="text-decoration: none; display: inline-block; padding: 12px 32px; height: auto; width: auto;">
                    <i class="bi bi-box-arrow-in-right"></i> Go to Login
                </a>
            </div>
        <?php endif; ?>

        <?php if ($valid_token && !$reset_success): ?>
            <form method="POST" action="" id="resetForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="bi bi-lock"></i> New Password
                        <span class="required">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Enter new password" 
                               required
                               minlength="8"
                               autocomplete="new-password"
                               aria-describedby="passwordHelp passwordRequirements">
                        <button type="button" class="toggle-password" onclick="togglePassword('password', 'togglePasswordIcon')" aria-label="Toggle password visibility">
                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="passwordHelp"></div>

                    <div class="password-strength">
                        <div class="progress-bar" id="strengthBar" style="width: 0%;"></div>
                    </div>
                    <div class="password-strength-text" id="strengthText">Enter a strong password</div>

                    <div class="password-requirements" id="passwordRequirements">
                        <div class="req">
                            <span class="req-icon" id="reqLength">✗</span>
                            <span>At least 8 characters</span>
                        </div>
                        <div class="req">
                            <span class="req-icon" id="reqLower">✗</span>
                            <span>One lowercase letter</span>
                        </div>
                        <div class="req">
                            <span class="req-icon" id="reqUpper">✗</span>
                            <span>One uppercase letter</span>
                        </div>
                        <div class="req">
                            <span class="req-icon" id="reqNumber">✗</span>
                            <span>One number</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirm">
                        <i class="bi bi-lock-fill"></i> Confirm Password
                        <span class="required">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" 
                               class="form-control" 
                               id="password_confirm" 
                               name="password_confirm" 
                               placeholder="Confirm your new password" 
                               required
                               autocomplete="new-password"
                               aria-describedby="confirmHelp">
                        <button type="button" class="toggle-password" onclick="togglePassword('password_confirm', 'toggleConfirmIcon')" aria-label="Toggle password visibility">
                            <i class="bi bi-eye" id="toggleConfirmIcon"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="confirmHelp"></div>
                </div>

                <button type="submit" class="btn-reset" id="resetBtn">
                    <span id="btnText"><i class="bi bi-check-circle"></i> Reset Password</span>
                    <span id="btnSpinner" class="spinner-border spinner-border-sm" style="display: none;" role="status" aria-hidden="true"></span>
                </button>

                <div style="margin-top: 16px; text-align: center;">
                    <a href="login.php" class="btn-back">
                        <i class="bi bi-arrow-left"></i> Back to Login
                    </a>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($valid_token && !$reset_success): ?>
            <div class="auth-footer">
                <p>Remember your password? <a href="login.php">Sign In</a></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer-bottom">
        &copy; <?= $current_year ?> <?= APP_NAME ?>. All rights reserved.
    </div>
</div>

<script>
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

document.getElementById('password')?.addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    
    const hasLength = password.length >= 8;
    const hasLower = /[a-z]/.test(password);
    const hasUpper = /[A-Z]/.test(password);
    const hasNumber = /\d/.test(password);
    
    document.getElementById('reqLength').textContent = hasLength ? '✓' : '✗';
    document.getElementById('reqLength').className = 'req-icon ' + (hasLength ? 'pass' : 'fail');
    document.getElementById('reqLower').textContent = hasLower ? '✓' : '✗';
    document.getElementById('reqLower').className = 'req-icon ' + (hasLower ? 'pass' : 'fail');
    document.getElementById('reqUpper').textContent = hasUpper ? '✓' : '✗';
    document.getElementById('reqUpper').className = 'req-icon ' + (hasUpper ? 'pass' : 'fail');
    document.getElementById('reqNumber').textContent = hasNumber ? '✓' : '✗';
    document.getElementById('reqNumber').className = 'req-icon ' + (hasNumber ? 'pass' : 'fail');
    
    let strength = 0;
    if (hasLength) strength += 25;
    if (hasLower) strength += 25;
    if (hasUpper) strength += 25;
    if (hasNumber) strength += 25;
    
    strengthBar.style.width = strength + '%';
    
    if (strength === 0) {
        strengthBar.style.background = '#E5E7EB';
        strengthText.textContent = 'Enter a strong password';
        strengthText.style.color = '#6B7280';
    } else if (strength <= 25) {
        strengthBar.style.background = '#EF4444';
        strengthText.textContent = 'Weak password';
        strengthText.style.color = '#EF4444';
    } else if (strength <= 50) {
        strengthBar.style.background = '#F59E0B';
        strengthText.textContent = 'Fair password';
        strengthText.style.color = '#F59E0B';
    } else if (strength <= 75) {
        strengthBar.style.background = '#3B82F6';
        strengthText.textContent = 'Good password';
        strengthText.style.color = '#3B82F6';
    } else {
        strengthBar.style.background = '#10B981';
        strengthText.textContent = 'Strong password';
        strengthText.style.color = '#10B981';
    }
});

document.getElementById('resetForm')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('resetBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    
    btn.disabled = true;
    btnText.innerHTML = 'Resetting...';
    btnSpinner.style.display = 'inline-block';
});

document.getElementById('resetForm')?.addEventListener('submit', function(e) {
    let isValid = true;
    
    const password = document.getElementById('password');
    const passwordHelp = document.getElementById('passwordHelp');
    
    if (!password.value.trim()) {
        passwordHelp.textContent = 'Please enter a new password.';
        password.closest('.input-group').classList.add('is-invalid');
        isValid = false;
    } else if (password.value.length < 8) {
        passwordHelp.textContent = 'Password must be at least 8 characters.';
        password.closest('.input-group').classList.add('is-invalid');
        isValid = false;
    } else if (!/[a-z]/.test(password.value) || !/[A-Z]/.test(password.value) || !/\d/.test(password.value)) {
        passwordHelp.textContent = 'Password must contain uppercase, lowercase, and a number.';
        password.closest('.input-group').classList.add('is-invalid');
        isValid = false;
    } else {
        password.closest('.input-group').classList.remove('is-invalid');
        passwordHelp.textContent = '';
    }
    
    const confirm = document.getElementById('password_confirm');
    const confirmHelp = document.getElementById('confirmHelp');
    
    if (!confirm.value.trim()) {
        confirmHelp.textContent = 'Please confirm your password.';
        confirm.closest('.input-group').classList.add('is-invalid');
        isValid = false;
    } else if (confirm.value !== password.value) {
        confirmHelp.textContent = 'Passwords do not match.';
        confirm.closest('.input-group').classList.add('is-invalid');
        isValid = false;
    } else {
        confirm.closest('.input-group').classList.remove('is-invalid');
        confirmHelp.textContent = '';
    }
    
    if (!isValid) {
        e.preventDefault();
        const btn = document.getElementById('resetBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');
        btn.disabled = false;
        btnText.innerHTML = '<i class="bi bi-check-circle"></i> Reset Password';
        btnSpinner.style.display = 'none';
    }
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