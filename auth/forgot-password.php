<?php
require_once '../config/database.php';
require_once '../includes/session.php';

SessionManager::start();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance()->getConnection();
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        // Check if user exists
        $stmt = $db->prepare("SELECT id, email, full_name FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expires]);
            
            // Send reset email (in production, use actual email service)
            $resetLink = APP_URL . "/auth/reset-password.php?token=" . $token;
            $message = "Password reset link has been sent to your email. Check your inbox.";
            
            // Log activity
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, activity_type, description, created_at) VALUES (?, 'password_reset', 'Password reset requested', NOW())");
            $stmt->execute([$user['id']]);
        } else {
            // Don't reveal if email exists or not for security
            $message = "If the email exists, a reset link has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>5G Election Guru · Forgot Password</title>
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
            <p>Reset your password</p>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <p style="color: #64748B; margin-bottom: 20px;">
                Enter your email address and we'll send you a link to reset your password.
            </p>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="admin@organization.ng" required />
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-paper-plane"></i>
                Send Reset Link
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="login.php" style="color: #2563EB; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</div>
</body>
</html>