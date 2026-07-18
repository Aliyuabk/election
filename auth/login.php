<?php
// ============================================================
// LOGIN PAGE - 5G ELECTION GURU
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

// Handle Login Form Submission
$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please try again.';
        logSecurityEvent(null, 'csrf_validation_failed', 'CSRF token validation failed during login attempt');
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        $captcha_input = trim($_POST['captcha'] ?? '');
        $captcha_expected = $_SESSION['captcha_code'] ?? '';
        
        // Basic validation
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Check if account is locked
                $user = getUserByEmail($email);
                if ($user && isset($user['locked_until']) && strtotime($user['locked_until']) > time()) {
                    $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
                    $error = "Account is temporarily locked. Please try again in {$remaining} minute(s).";
                    logSecurityEvent($user['id'], 'login_lockout', 'Login attempt on locked account');
                } else {
                    // Validate CAPTCHA
                    if (empty($captcha_input) || strtoupper($captcha_input) !== $captcha_expected) {
                        $error = 'Invalid CAPTCHA code. Please try again.';
                        $_SESSION['captcha_code'] = generateCaptcha();
                    } else {
                        // Check login attempts
                        $ip = getClientIP();
                        $attempts = getLoginAttempts($email, $ip);
                        
                        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                            $error = 'Too many failed login attempts. Please try again after 15 minutes.';
                            logSecurityEvent(null, 'too_many_attempts', "Too many login attempts for email: {$email}", 70);
                        } else {
                            // Attempt login
                            $user = authenticateUser($email, $password);
                            
                            if ($user) {
                                // Successful login
                                
                                // Clear any lockout
                                if (isset($user['locked_until']) && strtotime($user['locked_until']) > time()) {
                                    $db = getDB();
                                    $stmt = $db->prepare("UPDATE users SET locked_until = NULL WHERE id = ?");
                                    $stmt->execute([$user['id']]);
                                }
                                
                                // Create session
                                SessionManager::regenerate();
                                SessionManager::set('user_id', $user['id']);
                                SessionManager::set('tenant_id', $user['tenant_id']);
                                SessionManager::set('email', $user['email']);
                                SessionManager::set('full_name', $user['full_name']);
                                SessionManager::set('first_name', $user['first_name']);
                                SessionManager::set('last_name', $user['last_name']);
                                SessionManager::set('role_id', $user['role_id']);
                                SessionManager::set('role_level', $user['role_level'] ?? 0);
                                SessionManager::set('logged_in', true);
                                SessionManager::set('last_activity', time());
                                SessionManager::set('user_code', $user['user_code']);
                                SessionManager::set('avatar', $user['avatar'] ?? null);
                                
                                // Store user permissions if available
                                if (isset($user['permissions_json'])) {
                                    $permissions = json_decode($user['permissions_json'], true);
                                    SessionManager::set('permissions', $permissions ?? []);
                                }
                                
                                // Create persistent session
                                if ($remember_me) {
                                    $token = createSession($user['id'], true);
                                    if ($token) {
                                        setcookie('remember_token', $token, time() + 2592000, '/', '', true, true);
                                    }
                                } else {
                                    createSession($user['id'], false);
                                }
                                
                                // Update last login
                                updateUserLastLogin($user['id']);
                                
                                // Log success
                                logLoginAttempt($user['id'], $email, true);
                                logActivity($user['id'], 'login', 'User logged in successfully');
                                
                                // Redirect based on role level
                                $redirect = $_POST['redirect'] ?? 'dashboard.php';
                                if ($user['role_level'] === 'super_admin' || $user['role_level'] === 'client_admin') {
                                    $redirect = 'admin/dashboard.php';
                                } elseif (in_array($user['role_level'], ['national', 'state'])) {
                                    $redirect = 'coordinator/dashboard.php';
                                } elseif (in_array($user['role_level'], ['lga', 'ward'])) {
                                    $redirect = 'lga/dashboard.php';
                                }
                                
                                header('Location: ' . $redirect);
                                exit();
                            } else {
                                // Failed login
                                logLoginAttempt(null, $email, false);
                                logSecurityEvent(null, 'failed_login', "Failed login attempt for email: {$email}", 30);
                                
                                // Check if user exists to increment attempts
                                $userExists = getUserByEmail($email);
                                if ($userExists) {
                                    $db = getDB();
                                    $stmt = $db->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?");
                                    $stmt->execute([$userExists['id']]);
                                    
                                    // Lock account if too many attempts
                                    if ($userExists['login_attempts'] + 1 >= MAX_LOGIN_ATTEMPTS) {
                                        lockAccount($email);
                                        $error = 'Too many failed attempts. Account locked for ' . (LOCKOUT_TIME / 60) . ' minutes.';
                                        logSecurityEvent($userExists['id'], 'account_locked', "Account locked due to too many failed attempts");
                                    } else {
                                        $remaining_attempts = MAX_LOGIN_ATTEMPTS - ($userExists['login_attempts'] + 1);
                                        $error = "Invalid email or password. {$remaining_attempts} attempt(s) remaining.";
                                    }
                                } else {
                                    $error = 'Invalid email or password.';
                                }
                                
                                // Generate new CAPTCHA
                                $_SESSION['captcha_code'] = generateCaptcha();
                            }
                        }
                    }
                }
            }
        }
    }
}

// Get current year for footer
$current_year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* [Same styles as before - keep all the CSS from the previous version] */
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

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
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

        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 0 24px;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 2px solid #D1D5DB;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .form-check-label {
            font-size: 14px;
            color: #4B5563;
            cursor: pointer;
        }

        .forgot-link {
            font-size: 14px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .btn-login {
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

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(15, 76, 129, 0.35);
            color: white;
        }

        .btn-login:active { transform: translateY(0px); }
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-login .spinner-border {
            width: 20px;
            height: 20px;
            border-width: 2px;
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
            .login-card { padding: 32px 24px; border-radius: 20px; }
            .brand h1 { font-size: 20px; }
            .captcha-box { font-size: 22px; min-width: 100px; height: 44px; line-height: 44px; }
            .captcha-refresh { width: 44px; height: 44px; font-size: 20px; }
            .options-row { flex-direction: column; gap: 12px; align-items: flex-start; }
            .btn-login { height: 48px; font-size: 15px; }
        }

        @media (max-width: 360px) {
            .login-card { padding: 24px 16px; }
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

<div class="login-container">
    <div class="login-card">
        <div class="brand">
            <div class="brand-icon">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h1><?= APP_NAME ?></h1>
            <p>Sign in to continue to your dashboard</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect'] ?? '') ?>">

            <div class="form-group">
                <label class="form-label" for="email">
                    <i class="bi bi-envelope"></i> Email Address
                    <span class="required">*</span>
                </label>
                <div class="input-group <?= isset($error) && strpos($error, 'email') !== false ? 'is-invalid' : '' ?>">
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
                <label class="form-label" for="password">
                    <i class="bi bi-lock"></i> Password
                    <span class="required">*</span>
                </label>
                <div class="input-group <?= isset($error) && strpos($error, 'password') !== false ? 'is-invalid' : '' ?>">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           placeholder="Enter your password" 
                           required>
                    <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Toggle password visibility">
                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
                <div class="invalid-feedback" id="passwordHelp"></div>
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

            <div class="options-row">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                    <label class="form-check-label" for="remember_me">
                        <i class="bi bi-check-circle"></i> Remember Me
                    </label>
                </div>
                <a href="forgot-password.php" class="forgot-link">
                    <i class="bi bi-key"></i> Forgot Password?
                </a>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <span id="btnText"><i class="bi bi-box-arrow-in-right"></i> Sign In</span>
                <span id="btnSpinner" class="spinner-border spinner-border-sm" style="display: none;" role="status" aria-hidden="true"></span>
            </button>
        </form>

        <div class="auth-footer">
            <p>Don't have an account? <a href="register.php">Create Account</a></p>
        </div>
    </div>

    <div class="footer-bottom">
        &copy; <?= $current_year ?> <?= APP_NAME ?>. All rights reserved.
        <br>
        <a href="privacy.php">Privacy Policy</a> &middot; <a href="terms.php">Terms of Service</a>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const icon = document.getElementById('togglePasswordIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        passwordInput.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

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

document.getElementById('loginForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('loginBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    
    btn.disabled = true;
    btnText.innerHTML = 'Signing in...';
    btnSpinner.style.display = 'inline-block';
});

document.getElementById('loginForm').addEventListener('submit', function(e) {
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
    
    const password = document.getElementById('password');
    const passwordHelp = document.getElementById('passwordHelp');
    
    if (!password.value.trim()) {
        passwordHelp.textContent = 'Password is required.';
        password.closest('.input-group').classList.add('is-invalid');
        isValid = false;
    } else if (password.value.length < 6) {
        passwordHelp.textContent = 'Password must be at least 6 characters.';
        password.closest('.input-group').classList.add('is-invalid');
        isValid = false;
    } else {
        password.closest('.input-group').classList.remove('is-invalid');
        passwordHelp.textContent = '';
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
        const btn = document.getElementById('loginBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');
        btn.disabled = false;
        btnText.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Sign In';
        btnSpinner.style.display = 'none';
    }
});

document.getElementById('captcha').addEventListener('input', function() {
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