<?php
// ============================================================
// VERIFY OTP - Standalone OTP verification
// ============================================================
require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Start session
SessionManager::start();

// Check if user is in OTP session
if (!SessionManager::has('2fa_user_id')) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$user_id = SessionManager::get('2fa_user_id');
$email = SessionManager::get('2fa_email', '');
$remember = SessionManager::get('2fa_remember', false);

// Get user info
$user = getUserById($user_id);
if (!$user) {
    SessionManager::remove('2fa_user_id');
    header('Location: login.php?error=session_expired');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp_code'] ?? '');
    
    if (empty($otp)) {
        $error = 'Please enter the OTP code.';
    } elseif (verifyOTP($user_id, $otp, 'login')) {
        // OTP verified - login user
        $user = getUserById($user_id);
        if ($user) {
            // Create session token
            $token = createSession($user['id'], $remember);
            
            // Set session data
            SessionManager::regenerate();
            SessionManager::set('user_id', $user['id']);
            SessionManager::set('user_name', $user['full_name']);
            SessionManager::set('user_email', $user['email']);
            SessionManager::set('user_role', $user['role_id']);
            SessionManager::set('role_level', $user['role_level'] ?? 'client_admin');
            SessionManager::set('tenant_id', $user['tenant_id']);
            SessionManager::set('logged_in', true);
            SessionManager::set('login_time', time());
            SessionManager::set('last_activity', time());
            SessionManager::set('session_token', $token);
            
            // Set remember me cookie
            if ($remember && $token) {
                setcookie('remember_token', $token, time() + 2592000, '/', '', false, true);
            }
            
            // Update last login
            updateUserLastLogin($user['id']);
            
            // Log successful login
            logLoginAttempt($user['id'], $user['email'], true);
            logActivity($user['id'], 'login', 'User logged in successfully (2FA)');
            logSecurityEvent($user['id'], 'login', 'Successful 2FA login from IP: ' . getClientIP());
            
            // Clear 2FA session
            unset($_SESSION['2fa_user_id']);
            unset($_SESSION['2fa_remember']);
            unset($_SESSION['2fa_email']);
            
            // Redirect based on role
            $role = $user['role_level'] ?? 'client_admin';
            $dashboardMap = [
                'super_admin' => '../dashboard/super-admin/',
                'client_admin' => '../dashboard/client-admin/',
                'national' => '../dashboard/Coordinator/',
                'state' => '../dashboard/Coordinator/',
                'senatorial' => '../dashboard/Coordinator/',
                'federal_constituency' => '../dashboard/Coordinator/',
                'lga' => '../dashboard/Coordinator/',
                'ward' => '../dashboard/Coordinator/',
                'pu_agent' => '../dashboard/agent/',
                'party_agent' => '../dashboard/party-agent/',
                'volunteer' => '../dashboard/volunteer/',
                'observer' => '../dashboard/observer/',
                'situation_room' => '../dashboard/situation-room/',
                'finance_officer' => '../dashboard/finance-officer/',
                'citizen' => '../dashboard/citizen/'
            ];
            $dashboard = $dashboardMap[$role] ?? '../dashboard/client-admin/';
            header('Location: ' . $dashboard);
            exit();
        } else {
            $error = 'User not found. Please login again.';
            unset($_SESSION['2fa_user_id']);
            header('Location: login.php');
            exit();
        }
    } else {
        $error = 'Invalid or expired OTP code. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verify OTP - <?php echo APP_NAME; ?></title>
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
        .login-logo { text-align: center; margin-bottom: 24px; }
        .login-logo a { display: inline-flex; align-items: center; gap: 10px; font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.8rem; color: #0F4C81; text-decoration: none; }
        .login-logo i { font-size: 2.2rem; color: #2563EB; }
        .login-logo p { color: #64748B; font-size: 0.95rem; margin-top: 4px; }
        .login-card h2 { font-size: 1.3rem; margin-bottom: 4px; }
        .login-card .subtitle { color: #64748B; margin-bottom: 20px; }
        .otp-input-group { display: flex; gap: 10px; justify-content: center; margin: 20px 0; }
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
        .otp-input-group input.error { border-color: #EF4444; background: #FEF2F2; }
        .otp-timer { text-align: center; color: #64748B; font-size: 0.9rem; margin: 12px 0; }
        .otp-timer span { font-weight: 600; color: #0F4C81; }
        .otp-timer .expired { color: #EF4444; }
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
        .btn-login:hover { background: #1a3f6a; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(15, 76, 129, 0.2); }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; }
        .btn-login .spinner { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
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
        .resend-otp {
            background: none;
            border: none;
            color: #2563EB;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }
        .resend-otp:hover:not(:disabled) { text-decoration: underline; }
        .resend-otp:disabled { opacity: 0.5; cursor: not-allowed; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #64748B; text-decoration: none; font-size: 0.9rem; transition: 0.15s; display: inline-flex; align-items: center; gap: 8px; }
        .back-link a:hover { color: #0F4C81; }
        .resend-success {
            color: #10B981;
            font-size: 0.85rem;
            text-align: center;
            margin-top: 8px;
            display: none;
        }
        .resend-success.show { display: block; }
        @media (max-width: 480px) {
            .login-card { padding: 32px 24px; }
            .otp-input-group input { width: 40px; height: 48px; font-size: 1.2rem; }
            .otp-input-group { gap: 6px; }
        }
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
            <p>Two-Factor Authentication</p>
        </div>
        
        <h2>Verify Your Identity</h2>
        <p class="subtitle">Enter the 6-digit code sent to your email.</p>
        <p style="font-size: 0.85rem; color: #64748B; margin-bottom: 12px;">
            <i class="fas fa-envelope"></i> 
            Code sent to: <strong><?php echo htmlspecialchars($email); ?></strong>
        </p>
        
        <?php if (!empty($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="otpForm">
            <div class="otp-input-group">
                <input type="text" maxlength="1" class="otp-input" data-index="0" autofocus required />
                <input type="text" maxlength="1" class="otp-input" data-index="1" required />
                <input type="text" maxlength="1" class="otp-input" data-index="2" required />
                <input type="text" maxlength="1" class="otp-input" data-index="3" required />
                <input type="text" maxlength="1" class="otp-input" data-index="4" required />
                <input type="text" maxlength="1" class="otp-input" data-index="5" required />
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
                <div class="resend-success" id="resendSuccess">
                    <i class="fas fa-check-circle"></i> OTP resent successfully!
                </div>
            </div>
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
    const otpInputs = document.querySelectorAll('.otp-input');
    const form = document.getElementById('otpForm');
    const verifyBtn = document.getElementById('verifyOtpBtn');
    const timerElement = document.getElementById('otpTimer');
    const resendBtn = document.getElementById('resendOtp');
    const resendSuccess = document.getElementById('resendSuccess');
    let timeLeft = 300;
    let timerInterval = null;
    
    // Auto-focus first input
    if (otpInputs.length > 0) {
        otpInputs[0].focus();
    }
    
    // OTP input handling
    otpInputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            const value = this.value;
            
            if (!/^\d*$/.test(value)) {
                this.value = value.replace(/\D/g, '');
                return;
            }
            
            if (this.value.length === 1) {
                const next = document.querySelector(`.otp-input[data-index="${index + 1}"]`);
                if (next) next.focus();
            }
            updateOtpCode();
            this.classList.remove('error');
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
    
    function updateOtpCode() {
        const inputs = document.querySelectorAll('.otp-input');
        let code = '';
        inputs.forEach(input => code += input.value);
        document.getElementById('otp_code').value = code;
    }
    
    function startTimer() {
        if (timerInterval) clearInterval(timerInterval);
        timeLeft = 300;
        
        timerInterval = setInterval(() => {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            timeLeft--;
            
            if (timeLeft < 0) {
                clearInterval(timerInterval);
                timerElement.textContent = 'Expired';
                timerElement.className = 'expired';
                verifyBtn.disabled = true;
                resendBtn.disabled = false;
                document.querySelectorAll('.otp-input').forEach(input => input.disabled = true);
            }
        }, 1000);
    }
    
    startTimer();
    
    resendBtn.addEventListener('click', function() {
        const originalText = this.textContent;
        this.disabled = true;
        this.textContent = 'Sending...';
        resendSuccess.classList.remove('show');
        
        fetch('resend-otp.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=resend'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resendSuccess.textContent = data.message || 'OTP resent successfully!';
                resendSuccess.classList.add('show');
                startTimer();
                document.querySelectorAll('.otp-input').forEach(input => {
                    input.disabled = false;
                    input.value = '';
                });
                verifyBtn.disabled = false;
                if (otpInputs.length > 0) otpInputs[0].focus();
                setTimeout(() => {
                    resendSuccess.classList.remove('show');
                }, 3000);
                this.textContent = 'Resend OTP';
                this.disabled = false;
            } else {
                alert(data.message || 'Failed to resend OTP. Please try again.');
                this.textContent = originalText;
                this.disabled = false;
            }
        })
        .catch(() => {
            alert('An error occurred. Please try again.');
            this.textContent = originalText;
            this.disabled = false;
        });
    });
    
    form.addEventListener('submit', function(e) {
        const code = document.getElementById('otp_code').value;
        if (code.length !== 6) {
            e.preventDefault();
            document.querySelectorAll('.otp-input').forEach(input => {
                if (!input.value) input.classList.add('error');
            });
            alert('Please enter all 6 digits of the OTP code.');
            return false;
        }
        
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="fas fa-spinner spinner"></i> Verifying...';
        
        setTimeout(() => {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Verify OTP';
        }, 10000);
    });
});
</script>
</body>
</html>