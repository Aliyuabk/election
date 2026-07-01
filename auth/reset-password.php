<?php
require_once '../config/database.php';
require_once '../includes/session.php';

SessionManager::start();

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$valid_token = false;
$user_id = null;

if (empty($token)) {
    header('Location: forgot-password.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Verify token
$stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if ($reset) {
    $valid_token = true;
    $user_id = $reset['user_id'];
} else {
    $error = 'Invalid or expired reset link. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Update password
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
        
        // Mark token as used
        $stmt = $db->prepare("UPDATE password_resets SET used = 1, used_at = NOW() WHERE id = ?");
        $stmt->execute([$reset['id']]);
        
        // Log activity
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, activity_type, description, created_at) VALUES (?, 'password_change', 'Password reset successfully', NOW())");
        $stmt->execute([$user_id]);
        
        $success = 'Password has been reset successfully. You can now login with your new password.';
        $valid_token = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>5G Election Guru · Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&family=Poppins:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
<div class="login-wrapper" style="max-width: 440px;">
    <div class="login-card">
        <div class="login-logo">
            <a href="index.php">
                <i class="fas fa-bolt"></i>
                5G Election Guru
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
            <a href="login.php" class="btn-login" style="text-decoration: none; display: inline-block;">
                <i class="fas fa-sign-in-alt"></i> Login Now
            </a>
        </div>
        <?php endif; ?>
        
        <?php if ($valid_token && empty($success)): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="password">New Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="••••••••" required minlength="8" />
                </div>
                <small style="color: #64748B; font-size: 0.8rem;">Minimum 8 characters</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required />
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-key"></i>
                Reset Password
            </button>
        </form>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="login.php" style="color: #2563EB; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</div>
</body>
</html>