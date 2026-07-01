<?php
require_once '../config/database.php';
require_once '../includes/session.php';

SessionManager::start();

// Check if already logged in
if (SessionManager::isLoggedIn()) {
    header('Location: ../dashboard/index.php');
    exit();
}

$error = '';
$show_2fa = false;
$user_id = null;
$remember_token = null;

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance()->getConnection();
    
    // Check if this is 2FA verification
    if (isset($_POST['otp_code']) && isset($_SESSION['2fa_user_id'])) {
        verifyOTP($db);
        exit();
    }
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    $captcha = $_POST['captcha'] ?? '';
    
    // Validate input
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Check login attempts
        $ip = $_SERVER['REMOTE_ADDR'];
        $attempts = checkLoginAttempts($db, $ip, $email);
        
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $error = 'Too many failed login attempts. Please try again later.';
        } else {
            // Verify user
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Check if 2FA is enabled
                if ($user['two_factor_enabled']) {
                    // Generate and send OTP
                    $otp = generateOTP($db, $user['id']);
                    sendOTP($user['email'], $user['phone'], $otp);
                    
                    $_SESSION['2fa_user_id'] = $user['id'];
                    $_SESSION['2fa_remember'] = $remember;
                    $show_2fa = true;
                    $user_id = $user['id'];
                } else {
                    // Direct login
                    loginUser($user, $remember, $db);
                }
            } else {
                // Log failed attempt
                logFailedAttempt($db, $ip, $email);
                $attempts++;
                $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
                $error = "Invalid credentials. $remaining attempts remaining.";
                
                if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                    lockAccount($db, $email);
                    $error = 'Account locked due to multiple failed attempts.';
                }
            }
        }
    }
}

function verifyOTP($db) {
    $otp = trim($_POST['otp_code'] ?? '');
    $user_id = $_SESSION['2fa_user_id'];
    $remember = $_SESSION['2fa_remember'] ?? false;
    
    if (empty($otp)) {
        $_SESSION['2fa_error'] = 'Please enter the OTP code.';
        header('Location: login.php?2fa=1');
        exit();
    }
    
    // Verify OTP
    $stmt = $db->prepare("SELECT * FROM otp_verifications WHERE user_id = ? AND otp_code = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $otp]);
    $otpRecord = $stmt->fetch();
    
    if ($otpRecord) {
        // Mark OTP as used
        $stmt = $db->prepare("UPDATE otp_verifications SET used = 1, used_at = NOW() WHERE id = ?");
        $stmt->execute([$otpRecord['id']]);
        
        // Get user and login
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        loginUser($user, $remember, $db);
    } else {
        $_SESSION['2fa_error'] = 'Invalid or expired OTP code.';
        header('Location: login.php?2fa=1');
        exit();
    }
}

function loginUser($user, $remember, $db) {
    // Regenerate session
    SessionManager::regenerate();
    
    // Set session data
    SessionManager::set('user_id', $user['id']);
    SessionManager::set('user_name', $user['full_name']);
    SessionManager::set('user_email', $user['email']);
    SessionManager::set('user_role', $user['role_id']);
    SessionManager::set('tenant_id', $user['tenant_id']);
    SessionManager::set('logged_in', true);
    SessionManager::set('login_time', time());
    SessionManager::set('last_activity', time());
    
    // Remember me token
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $db->prepare("INSERT INTO user_sessions (user_id, token, device_id, device_type, expires_at) VALUES (?, ?, ?, 'web', ?)");
        $stmt->execute([$user['id'], $token, $_SERVER['REMOTE_ADDR'], $expires]);
        
        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', false, true);
    }
    
    // Update last login
    $stmt = $db->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = ?, login_attempts = 0 WHERE id = ?");
    $stmt->execute([$_SERVER['REMOTE_ADDR'], $user['id']]);
    
    // Log login activity
    logActivity($db, $user['id'], 'login', 'User logged in successfully');
    
    // Clear 2FA session
    unset($_SESSION['2fa_user_id']);
    unset($_SESSION['2fa_remember']);
    unset($_SESSION['2fa_error']);
    
    // Redirect based on role
    $role = getUserRole($db, $user['id']);
    header('Location: ../dashboard/' . $role . '_dashboard.php');
    exit();
}

function generateOTP($db, $user_id) {
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY);
    
    $stmt = $db->prepare("INSERT INTO otp_verifications (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $otp, $expires]);
    
    return $otp;
}

function sendOTP($email, $phone, $otp) {
    // In production, use actual email/SMS service
    $_SESSION['2fa_otp'] = $otp;
    $_SESSION['2fa_otp_expires'] = time() + OTP_EXPIRY;
}

function checkLoginAttempts($db, $ip, $email) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE (ip_address = ? OR email = ?) AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip, $email]);
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

function logFailedAttempt($db, $ip, $email) {
    $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, email, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$ip, $email]);
}

function lockAccount($db, $email) {
    $stmt = $db->prepare("UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE email = ?");
    $stmt->execute([$email]);
}

function logActivity($db, $user_id, $action, $description) {
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, activity_type, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $action, $description, $_SERVER['REMOTE_ADDR']]);
}

function getUserRole($db, $user_id) {
    $stmt = $db->prepare("SELECT r.level FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['level'] ?? 'client_admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5" />
    <title>5G Election Guru · Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&family=Poppins:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        /* ===== RESET & BASE ===== */
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

        /* ===== PRELOADER ===== */
        .preloader {
            position: fixed;
            inset: 0;
            background: #0F172A;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        .preloader.hidden { opacity: 0; visibility: hidden; pointer-events: none; }
        .loader-ring {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            border: 6px solid rgba(37, 99, 235, 0.15);
            border-top-color: #2563EB;
            animation: spin 0.9s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ===== LOGIN CONTAINER ===== */
        .login-wrapper {
            width: 100%;
            max-width: 440px;
            padding: 20px;
            animation: fadeIn 0.6s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-card {
            background: white;
            border-radius: 32px;
            padding: 48px 40px;
            box-shadow: 0 20px 60px rgba(15, 76, 129, 0.08);
            border: 1px solid #E2E8F0;
        }

        /* ===== LOGO ===== */
        .login-logo { text-align: center; margin-bottom: 32px; }
        .login-logo a {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.8rem;
            color: #0F4C81;
            text-decoration: none;
        }
        .login-logo i { font-size: 2.2rem; color: #2563EB; }
        .login-logo p { color: #64748B; font-size: 0.95rem; margin-top: 4px; }

        /* ===== FORM ===== */
        .login-form h2 { font-size: 1.6rem; margin-bottom: 8px; }
        .login-form .subtitle { color: #64748B; margin-bottom: 28px; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 6px;
            color: #0F172A;
        }
        .form-group .input-wrapper { position: relative; }
        .form-group .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 1rem;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 46px;
            border: 1.5px solid #E2E8F0;
            border-radius: 14px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            background: #F8FAFC;
            transition: all 0.2s;
            color: #0F172A;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563EB;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.06);
        }
        .form-group input.error { border-color: #EF4444; }
        .form-group input.success { border-color: #10B981; }
        .form-group input::placeholder { color: #94A3B8; }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0 28px;
        }
        .form-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #475569;
            cursor: default;
        }
        .form-options label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #0F4C81;
            cursor: default;
        }
        .form-options a {
            color: #2563EB;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: 0.15s;
        }
        .form-options a:hover { color: #0F4C81; text-decoration: underline; }

        .btn-login {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 14px;
            background: #0F4C81;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-login:hover {
            background: #1a3f6a;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(15, 76, 129, 0.2);
        }
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .btn-login i { font-size: 1rem; }

        /* ===== OTP SECTION ===== */
        .otp-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .otp-section.active { display: block; }
        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        .otp-input-group input {
            width: 50px;
            height: 56px;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 600;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            background: #F8FAFC;
            transition: all 0.2s;
        }
        .otp-input-group input:focus {
            border-color: #2563EB;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.06);
            outline: none;
        }
        .otp-timer {
            text-align: center;
            color: #64748B;
            font-size: 0.9rem;
            margin: 12px 0;
        }
        .otp-timer span { font-weight: 600; color: #0F4C81; }
        .resend-otp {
            background: none;
            border: none;
            color: #2563EB;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
        }
        .resend-otp:hover { text-decoration: underline; }
        .resend-otp:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ===== ERROR MESSAGES ===== */
        .error-message {
            background: #FEF2F2;
            color: #DC2626;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #FECACA;
        }
        .error-message i { font-size: 1.1rem; }
        .success-message {
            background: #ECFDF5;
            color: #065F46;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #A7F3D0;
        }

        /* ===== DIVIDER ===== */
        .divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 28px 0;
            color: #94A3B8;
            font-size: 0.85rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #E2E8F0;
        }

        /* ===== SOCIAL BUTTONS ===== */
        .social-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .social-btn {
            padding: 12px;
            border: 1.5px solid #E2E8F0;
            border-radius: 14px;
            background: white;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            font-weight: 500;
            color: #0F172A;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .social-btn:hover {
            background: #F8FAFC;
            border-color: #2563EB;
        }
        .social-btn.google i { color: #EA4335; }
        .social-btn.microsoft i { color: #00A4EF; }

        /* ===== LINKS ===== */
        .register-link {
            text-align: center;
            margin-top: 28px;
            color: #64748B;
            font-size: 0.95rem;
        }
        .register-link a {
            color: #2563EB;
            text-decoration: none;
            font-weight: 600;
            transition: 0.15s;
        }
        .register-link a:hover { color: #0F4C81; text-decoration: underline; }
        .back-home {
            text-align: center;
            margin-top: 20px;
        }
        .back-home a {
            color: #64748B;
            text-decoration: none;
            font-size: 0.9rem;
            transition: 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .back-home a:hover { color: #0F4C81; }

        /* ===== CAPTCHA ===== */
        .captcha-container {
            background: #F1F5F9;
            padding: 12px 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .captcha-container .captcha-text {
            font-family: 'Courier New', monospace;
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 4px;
            color: #0F4C81;
            background: white;
            padding: 4px 16px;
            border-radius: 8px;
            user-select: none;
        }
        .captcha-container .refresh-captcha {
            background: none;
            border: none;
            color: #64748B;
            cursor: pointer;
            font-size: 1.2rem;
        }
        .captcha-container .refresh-captcha:hover { color: #0F4C81; }
        .captcha-container input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
        }
        .captcha-container input:focus {
            outline: none;
            border-color: #2563EB;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            .login-card { padding: 32px 24px; border-radius: 24px; }
            .login-logo a { font-size: 1.5rem; }
            .social-buttons { grid-template-columns: 1fr; }
            .form-options { flex-direction: column; gap: 12px; align-items: flex-start; }
            .otp-input-group input { width: 40px; height: 48px; font-size: 1.2rem; }
        }
    </style>
</head>
<body>

<!-- ===== PRELOADER ===== -->
<div class="preloader" id="preloader">
    <div class="loader-ring"></div>
</div>

<!-- ===== LOGIN ===== -->
<div class="login-wrapper">
    <div class="login-card">
        <!-- Logo -->
        <div class="login-logo">
            <a href="index.php">
                <i class="fas fa-bolt"></i>
                5G Election Guru
            </a>
            <p>Enterprise Election Management Platform</p>
        </div>

        <!-- Error/Success Messages -->
        <?php if (!empty($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['2fa_error'])): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($_SESSION['2fa_error']); unset($_SESSION['2fa_error']); ?>
        </div>
        <?php endif; ?>

        <?php if ($show_2fa): ?>
        <!-- ===== 2FA SECTION ===== -->
        <div class="otp-section active">
            <h2>Two-Factor Authentication</h2>
            <p class="subtitle">Enter the 6-digit code sent to your email or phone.</p>
            
            <form method="POST" action="login.php">
                <div class="otp-input-group">
                    <input type="text" maxlength="1" class="otp-input" data-index="0" autofocus />
                    <input type="text" maxlength="1" class="otp-input" data-index="1" />
                    <input type="text" maxlength="1" class="otp-input" data-index="2" />
                    <input type="text" maxlength="1" class="otp-input" data-index="3" />
                    <input type="text" maxlength="1" class="otp-input" data-index="4" />
                    <input type="text" maxlength="1" class="otp-input" data-index="5" />
                </div>
                <input type="hidden" name="otp_code" id="otp_code" />
                
                <div class="otp-timer">
                    Code expires in <span id="otpTimer">5:00</span>
                </div>
                
                <button type="submit" class="btn-login" id="verifyOtpBtn">
                    <i class="fas fa-check-circle"></i>
                    Verify OTP
                </button>
                
                <div style="text-align: center; margin-top: 16px;">
                    <button type="button" class="resend-otp" id="resendOtp">Resend OTP</button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <!-- ===== LOGIN FORM ===== -->
        <form class="login-form" method="POST" action="login.php" id="loginForm">
            <h2>Welcome Back</h2>
            <p class="subtitle">Sign in to access your dashboard and manage elections.</p>

            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="admin@organization.ng" required />
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="••••••••" required />
                </div>
            </div>

            <!-- CAPTCHA (shown after failed attempts) -->
            <?php if (isset($_SESSION['show_captcha']) && $_SESSION['show_captcha']): ?>
            <div class="captcha-container">
                <span class="captcha-text" id="captchaText"><?php echo $_SESSION['captcha_text'] ?? 'A7K9M'; ?></span>
                <button type="button" class="refresh-captcha" id="refreshCaptcha">
                    <i class="fas fa-sync"></i>
                </button>
                <input type="text" name="captcha" placeholder="Enter captcha" maxlength="5" />
            </div>
            <?php endif; ?>

            <div class="form-options">
                <label>
                    <input type="checkbox" name="remember" /> Remember me
                </label>
                <a href="forgot-password.php">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <i class="fas fa-arrow-right-to-bracket"></i>
                Sign In
            </button>
        </form>

        <!-- Divider -->
        <div class="divider">or continue with</div>

        <!-- Social Buttons -->
        <div class="social-buttons">
            <button type="button" class="social-btn google">
                <i class="fab fa-google"></i> Google
            </button>
            <button type="button" class="social-btn microsoft">
                <i class="fab fa-microsoft"></i> Microsoft
            </button>
        </div>

        <!-- Register Link -->
        <div class="register-link">
            Don't have an account? <a href="request-access.php">Request Access</a>
        </div>
        <?php endif; ?>

        <!-- Back to Home -->
        <div class="back-home">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i>
                Back to Homepage
            </a>
        </div>
    </div>
</div>

<!-- ===== JS ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preloader
    const preloader = document.getElementById('preloader');
    window.addEventListener('load', function() {
        preloader.classList.add('hidden');
    });
    setTimeout(() => { preloader.classList.add('hidden'); }, 2200);

    // OTP Input handling
    const otpInputs = document.querySelectorAll('.otp-input');
    if (otpInputs.length) {
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                if (this.value.length === 1) {
                    const next = document.querySelector(`.otp-input[data-index="${index + 1}"]`);
                    if (next) next.focus();
                }
                updateOtpCode();
            });
            
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value.length === 0) {
                    const prev = document.querySelector(`.otp-input[data-index="${index - 1}"]`);
                    if (prev) prev.focus();
                }
            });
            
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const digits = paste.replace(/\D/g, '').slice(0, 6);
                digits.split('').forEach((digit, i) => {
                    const target = document.querySelector(`.otp-input[data-index="${i}"]`);
                    if (target) target.value = digit;
                });
                updateOtpCode();
                const last = document.querySelector(`.otp-input[data-index="${Math.min(digits.length, 6) - 1}"]`);
                if (last) last.focus();
            });
        });
    }

    function updateOtpCode() {
        const inputs = document.querySelectorAll('.otp-input');
        let code = '';
        inputs.forEach(input => code += input.value);
        document.getElementById('otp_code').value = code;
    }

    // OTP Timer
    const timerElement = document.getElementById('otpTimer');
    if (timerElement) {
        let timeLeft = 300; // 5 minutes
        const timerInterval = setInterval(() => {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            timeLeft--;
            
            if (timeLeft < 0) {
                clearInterval(timerInterval);
                timerElement.textContent = 'Expired';
                document.getElementById('verifyOtpBtn').disabled = true;
                document.querySelector('.resend-otp').disabled = false;
            }
        }, 1000);
    }

    // Resend OTP
    const resendBtn = document.getElementById('resendOtp');
    if (resendBtn) {
        resendBtn.addEventListener('click', function() {
            this.disabled = true;
            this.textContent = 'Sending...';
            
            fetch('resend-otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'resend' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.textContent = 'Resent!';
                    setTimeout(() => {
                        this.textContent = 'Resend OTP';
                        this.disabled = false;
                    }, 3000);
                    // Reset timer
                    location.reload();
                } else {
                    this.textContent = 'Failed. Try again';
                    this.disabled = false;
                }
            })
            .catch(() => {
                this.textContent = 'Resend OTP';
                this.disabled = false;
            });
        });
    }

    // CAPTCHA refresh
    const refreshBtn = document.getElementById('refreshCaptcha');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            fetch('captcha.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('captchaText').textContent = data.captcha;
            });
        });
    }

    // Social login buttons
    document.querySelectorAll('.social-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const provider = this.classList.contains('google') ? 'Google' : 'Microsoft';
            alert(`Sign in with ${provider} (Coming soon)`);
        });
    });

    // Form validation
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            let isValid = true;
            
            if (!email.value.trim()) {
                email.classList.add('error');
                isValid = false;
            } else {
                email.classList.remove('error');
                email.classList.add('success');
            }
            
            if (!password.value.trim()) {
                password.classList.add('error');
                isValid = false;
            } else {
                password.classList.remove('error');
                password.classList.add('success');
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
});
</script>
</body>
</html>