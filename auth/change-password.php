<?php
// ============================================================
// CHANGE PASSWORD - Authenticated user changes password
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
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate
    if (empty($current_password)) {
        $error = 'Please enter your current password.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        
        // Verify current password
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($current_password, $user['password_hash'])) {
            // Update password
            $hashed = hashPassword($new_password);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashed, $user_id]);
            
            // Log activity
            logActivity($user_id, 'password_change', 'Password changed successfully');
            logSecurityEvent($user_id, 'password_change', 'Password changed from IP: ' . getClientIP());
            
            $success = 'Password changed successfully!';
        } else {
            $error = 'Current password is incorrect.';
            logSecurityEvent($user_id, 'failed_login', 'Failed password change attempt from IP: ' . getClientIP(), 5);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Change Password - <?php echo APP_NAME; ?></title>
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
            padding: 40px 20px;
        }
        .container { max-width: 500px; margin: 0 auto; }
        .card { background: white; border-radius: 32px; padding: 48px 40px; box-shadow: 0 20px 60px rgba(15, 76, 129, 0.08); border: 1px solid #E2E8F0; }
        .header { text-align: center; margin-bottom: 32px; }
        .header h1 { font-size: 1.8rem; color: #0F4C81; }
        .header p { color: #64748B; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; color: #0F172A; }
        .form-group .input-wrapper { position: relative; }
        .form-group .input-wrapper i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 1rem; }
        .form-group input { width: 100%; padding: 14px 16px 14px 46px; border: 1.5px solid #E2E8F0; border-radius: 14px; font-family: 'Inter', sans-serif; font-size: 0.95rem; background: #F8FAFC; transition: all 0.2s; color: #0F172A; }
        .form-group input:focus { outline: none; border-color: #2563EB; background: white; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.06); }
        .form-group small { color: #64748B; font-size: 0.8rem; display: block; margin-top: 4px; }
        .btn-primary { width: 100%; padding: 16px; border: none; border-radius: 14px; background: #0F4C81; color: white; font-size: 1rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-primary:hover { background: #1a3f6a; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(15, 76, 129, 0.2); }
        .btn-secondary { background: #F1F5F9; color: #0F172A; }
        .btn-secondary:hover { background: #E2E8F0; }
        .error-message { background: #FEF2F2; color: #DC2626; padding: 12px 16px; border-radius: 12px; font-size: 0.9rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; border: 1px solid #FECACA; }
        .success-message { background: #ECFDF5; color: #065F46; padding: 12px 16px; border-radius: 12px; font-size: 0.9rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; border: 1px solid #A7F3D0; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #64748B; text-decoration: none; font-size: 0.9rem; transition: 0.15s; display: inline-flex; align-items: center; gap: 8px; }
        .back-link a:hover { color: #0F4C81; }
        .btn-group { display: flex; gap: 12px; }
        .btn-group .btn-primary { flex: 1; }
        .password-strength { height: 4px; border-radius: 4px; margin-top: 8px; background: #E2E8F0; transition: all 0.3s ease; }
        .password-strength.weak { background: #EF4444; width: 25%; }
        .password-strength.medium { background: #F59E0B; width: 50%; }
        .password-strength.strong { background: #10B981; width: 75%; }
        .password-strength.very-strong { background: #059669; width: 100%; }
        @media (max-width: 480px) { .card { padding: 32px 24px; } .btn-group { flex-direction: column; } }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="header">
            <h1>Change Password</h1>
            <p>Update your account password</p>
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
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="current_password" name="current_password" placeholder="Enter current password" required />
                </div>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-key"></i>
                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required minlength="8" />
                </div>
                <small>Minimum 8 characters with uppercase, lowercase, and number</small>
                <div class="password-strength" id="passwordStrength"></div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required />
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Password
                </button>
                <a href="../dashboard/index.php" class="btn-primary btn-secondary" style="text-decoration: none; text-align: center;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('new_password');
    const strengthBar = document.getElementById('passwordStrength');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength';
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                return;
            }
            
            if (strength <= 2) {
                strengthBar.classList.add('weak');
            } else if (strength === 3) {
                strengthBar.classList.add('medium');
            } else if (strength === 4) {
                strengthBar.classList.add('strong');
            } else {
                strengthBar.classList.add('very-strong');
            }
        });
    }
});
</script>
</body>
</html>