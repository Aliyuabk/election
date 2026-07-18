<?php
// ============================================================
// RESET PASSWORD - Set new password using token
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

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$error = '';
$success = '';
$valid_token = false;
$user_id = null;
$reset_id = null;

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (empty($token) || empty($email)) {
    $error = 'Invalid reset link. Please request a new password reset.';
} else {
    try {
        $db = getDB();
        $current_timestamp = time();
        $current_time = date('Y-m-d H:i:s', $current_timestamp);
        
        // Verify token - using PHP timezone for expiration check
        $stmt = $db->prepare("
            SELECT pr.*, u.email, u.first_name, u.full_name 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? AND u.email = ? AND pr.used = 0
            ORDER BY pr.id DESC LIMIT 1
        ");
        $stmt->execute([$token, $email]);
        $reset = $stmt->fetch();
        
        if ($reset) {
            // Check expiration using PHP time
            $expires_timestamp = strtotime($reset['expires_at']);
            
            if ($expires_timestamp < $current_timestamp) {
                $error = 'This reset link has expired. Please request a new one.';
                logSecurityEvent(null, 'expired_reset_token', 'Expired reset token used for email: ' . $email);
            } else {
                $valid_token = true;
                $user_id = $reset['user_id'];
                $reset_id = $reset['id'];
                $email = $reset['email'];
            }
        } else {
            // Check if token exists but might be used
            $stmt = $db->prepare("
                SELECT pr.*, u.email 
                FROM password_resets pr 
                JOIN users u ON pr.user_id = u.id 
                WHERE pr.token = ? AND u.email = ?
                ORDER BY pr.id DESC LIMIT 1
            ");
            $stmt->execute([$token, $email]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                if ($existing['used'] == 1) {
                    $error = 'This reset link has already been used. Please request a new one.';
                } else {
                    $error = 'Invalid reset link. Please request a new one.';
                }
            } else {
                $error = 'Invalid reset link. Please request a new one.';
            }
        }
    } catch (Exception $e) {
        error_log("Reset password token verification error: " . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please try again.';
        logSecurityEvent(null, 'csrf_validation_failed', 'CSRF token validation failed on reset password');
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate password
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $error = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = 'Password must contain at least one number.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $db = getDB();
                
                // Start transaction
                $db->beginTransaction();
                
                // Update password
                $hashed = hashPassword($password);
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
                
                // Mark token as used
                $stmt = $db->prepare("UPDATE password_resets SET used = 1, used_at = NOW() WHERE id = ?");
                $stmt->execute([$reset_id]);
                
                // Revoke all sessions for security
                revokeAllSessions($user_id);
                
                // Commit transaction
                $db->commit();
                
                // Log activity
                logActivity($user_id, 'password_change', 'Password reset successfully');
                logSecurityEvent($user_id, 'password_change', 'Password reset completed from IP: ' . getClientIP());
                
                $success = 'Password has been reset successfully. You can now login with your new password.';
                $valid_token = false; // Hide form
                
            } catch (Exception $e) {
                // Rollback transaction on error
                if (isset($db)) {
                    $db->rollBack();
                }
                error_log("Reset password update error: " . $e->getMessage());
                $error = 'Failed to reset password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password - <?php echo APP_NAME; ?></title>
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
        .form-group small { color: #64748B; font-size: 0.8rem; display: block; margin-top: 4px; }
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
        .password-strength { height: 4px; border-radius: 4px; margin-top: 8px; background: #E2E8F0; transition: all 0.3s ease; }
        .password-strength.weak { background: #EF4444; width: 25%; }
        .password-strength.medium { background: #F59E0B; width: 50%; }
        .password-strength.strong { background: #10B981; width: 75%; }
        .password-strength.very-strong { background: #059669; width: 100%; }
        .password-requirements {
            font-size: 0.8rem;
            color: #64748B;
            margin-top: 8px;
            padding: 8px 12px;
            background: #F8FAFC;
            border-radius: 8px;
        }
        .password-requirements .req {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 2px 0;
        }
        .password-requirements .req.pass { color: #10B981; }
        .password-requirements .req.fail { color: #EF4444; }
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
            <p>Set new password</p>
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
        <div style="text-align: center; margin-top: 20px;">
            <a href="login.php" class="btn-login" style="text-decoration: none; display: inline-block; width: auto; padding: 12px 32px;">
                <i class="fas fa-sign-in-alt"></i> Login Now
            </a>
        </div>
        <?php endif; ?>
        
        <?php if ($valid_token && empty($success)): ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <p style="color: #64748B; margin-bottom: 20px;">
                Reset password for: <strong><?php echo htmlspecialchars($email); ?></strong>
            </p>
            
            <div class="form-group">
                <label for="password">New Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="••••••••" required minlength="8" autofocus />
                </div>
                <small>Minimum 8 characters with uppercase, lowercase, and number</small>
                <div class="password-strength" id="passwordStrength"></div>
                
                <div class="password-requirements" id="passwordRequirements">
                    <div class="req fail" id="reqLength">✗ At least 8 characters</div>
                    <div class="req fail" id="reqLower">✗ One lowercase letter</div>
                    <div class="req fail" id="reqUpper">✗ One uppercase letter</div>
                    <div class="req fail" id="reqNumber">✗ One number</div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required />
                </div>
                <div id="confirmMatch" style="font-size: 0.8rem; margin-top: 4px;"></div>
            </div>
            
            <button type="submit" class="btn-login" id="submitBtn">
                <i class="fas fa-key"></i>
                <span id="btnText">Reset Password</span>
                <span id="btnSpinner" class="fas fa-spinner fa-spin" style="display: none;"></span>
            </button>
        </form>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password strength indicator
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('passwordStrength');
            const confirmMatch = document.getElementById('confirmMatch');
            
            // Requirements
            const reqLength = document.getElementById('reqLength');
            const reqLower = document.getElementById('reqLower');
            const reqUpper = document.getElementById('reqUpper');
            const reqNumber = document.getElementById('reqNumber');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    
                    // Check requirements
                    const hasLength = password.length >= 8;
                    const hasLower = /[a-z]/.test(password);
                    const hasUpper = /[A-Z]/.test(password);
                    const hasNumber = /[0-9]/.test(password);
                    
                    // Update requirement indicators
                    updateRequirement(reqLength, hasLength);
                    updateRequirement(reqLower, hasLower);
                    updateRequirement(reqUpper, hasUpper);
                    updateRequirement(reqNumber, hasNumber);
                    
                    // Calculate strength
                    let strength = 0;
                    if (hasLength) strength++;
                    if (hasLower) strength++;
                    if (hasUpper) strength++;
                    if (hasNumber) strength++;
                    
                    strengthBar.className = 'password-strength';
                    if (password.length === 0) {
                        strengthBar.style.width = '0%';
                        return;
                    }
                    
                    if (strength <= 2) {
                        strengthBar.classList.add('weak');
                    } else if (strength === 3) {
                        strengthBar.classList.add('medium');
                    } else {
                        strengthBar.classList.add('strong');
                    }
                    
                    // Check confirm password match
                    checkConfirmMatch();
                });
            }
            
            function updateRequirement(element, passed) {
                if (passed) {
                    element.className = 'req pass';
                    element.textContent = '✓ ' + element.textContent.substring(2);
                } else {
                    element.className = 'req fail';
                    element.textContent = '✗ ' + element.textContent.substring(2);
                }
            }
            
            function checkConfirmMatch() {
                const password = passwordInput.value;
                const confirm = confirmInput.value;
                
                if (confirm.length === 0) {
                    confirmMatch.textContent = '';
                    confirmMatch.style.color = '#64748B';
                } else if (password === confirm) {
                    confirmMatch.textContent = '✓ Passwords match';
                    confirmMatch.style.color = '#10B981';
                } else {
                    confirmMatch.textContent = '✗ Passwords do not match';
                    confirmMatch.style.color = '#EF4444';
                }
            }
            
            if (confirmInput) {
                confirmInput.addEventListener('input', checkConfirmMatch);
            }
            
            // Form submit handler
            document.getElementById('submitBtn').addEventListener('click', function(e) {
                const btn = this;
                const btnText = document.getElementById('btnText');
                const btnSpinner = document.getElementById('btnSpinner');
                
                // Check if passwords match before submitting
                const password = passwordInput.value;
                const confirm = confirmInput.value;
                
                if (password !== confirm) {
                    e.preventDefault();
                    confirmMatch.textContent = '✗ Passwords do not match';
                    confirmMatch.style.color = '#EF4444';
                    return;
                }
                
                btn.disabled = true;
                btnText.textContent = 'Resetting...';
                btnSpinner.style.display = 'inline-block';
            });
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