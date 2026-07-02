<?php
$page_title = "Email Settings";
require_once 'includes/db.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$error = '';
$message_type = '';

// Get current settings
$settings = [];
$stmt = $conn->query("SELECT * FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row;
}

function getSetting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key]['value'] : $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update email settings
        foreach ($_POST as $key => $value) {
            if (in_array($key, ['action', 'submit'])) continue;
            
            $stmt = $conn->prepare("UPDATE system_settings SET value = ?, updated_at = NOW() WHERE `key` = ?");
            $stmt->execute([$value, $key]);
        }
        
        logActivity(getValidUserId(), null, 'settings_changed', "Email settings updated");
        $message = "Email settings saved successfully.";
        $message_type = 'success';
        
        // Refresh settings
        $settings = [];
        $stmt = $conn->query("SELECT * FROM system_settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        $message_type = 'error';
    }
}

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<main class="main-content">
    <div class="page-header">
        <div class="header-left">
            <h1><i class="fas fa-envelope" style="color:#4f9cf7;"></i> Email Settings</h1>
            <p class="subtitle">Configure email server and notification settings</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type ?: 'success'; ?>">
        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <div class="settings-container">
        <div class="settings-sidebar">
            <a href="system-settings.php" class="nav-item">
                <i class="fas fa-arrow-left"></i> <span>Back to Settings</span>
            </a>
        </div>
        
        <div class="settings-content">
            <form method="POST">
                <input type="hidden" name="action" value="save_email_settings">
                
                <h2>SMTP Configuration</h2>
                <p class="section-desc">Configure your email server settings</p>

                <div class="form-group">
                    <label for="smtp_host">SMTP Host <span class="required">*</span></label>
                    <input type="text" name="smtp_host" id="smtp_host" class="form-control" 
                           value="<?php echo htmlspecialchars(getSetting('smtp_host', 'smtp.gmail.com')); ?>" required>
                </div>

                <div class="form-group">
                    <label for="smtp_port">SMTP Port <span class="required">*</span></label>
                    <input type="number" name="smtp_port" id="smtp_port" class="form-control" 
                           value="<?php echo htmlspecialchars(getSetting('smtp_port', '587')); ?>" required>
                </div>

                <div class="form-group">
                    <label for="smtp_username">SMTP Username <span class="required">*</span></label>
                    <input type="text" name="smtp_username" id="smtp_username" class="form-control" 
                           value="<?php echo htmlspecialchars(getSetting('smtp_username', '')); ?>" required>
                </div>

                <div class="form-group">
                    <label for="smtp_password">SMTP Password <span class="required">*</span></label>
                    <input type="password" name="smtp_password" id="smtp_password" class="form-control" 
                           value="<?php echo htmlspecialchars(getSetting('smtp_password', '')); ?>" required>
                    <small>App password recommended for Gmail accounts</small>
                </div>

                <div class="form-group">
                    <label for="smtp_encryption">SMTP Encryption</label>
                    <select name="smtp_encryption" id="smtp_encryption" class="form-control">
                        <option value="tls" <?php echo getSetting('smtp_encryption', 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                        <option value="ssl" <?php echo getSetting('smtp_encryption', 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        <option value="none" <?php echo getSetting('smtp_encryption', 'tls') === 'none' ? 'selected' : ''; ?>>None</option>
                    </select>
                </div>

                <h2 style="margin-top:24px;">Sender Information</h2>
                <p class="section-desc">Configure email sender details</p>

                <div class="form-group">
                    <label for="sender_name">Sender Name <span class="required">*</span></label>
                    <input type="text" name="sender_name" id="sender_name" class="form-control" 
                           value="<?php echo htmlspecialchars(getSetting('sender_name', '5G Election Guru')); ?>" required>
                </div>

                <div class="form-group">
                    <label for="sender_email">Sender Email <span class="required">*</span></label>
                    <input type="email" name="sender_email" id="sender_email" class="form-control" 
                           value="<?php echo htmlspecialchars(getSetting('sender_email', 'no-reply@5gguru.ng')); ?>" required>
                </div>

                <div class="form-group">
                    <label for="reply_to_email">Reply-To Email</label>
                    <input type="email" name="reply_to_email" id="reply_to_email" class="form-control" 
                           value="<?php echo htmlspecialchars(getSetting('reply_to_email', '')); ?>">
                    <small>Email address for replies (leave blank to use sender email)</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                    <button type="button" class="btn-secondary" onclick="testEmail()">
                        <i class="fas fa-paper-plane"></i> Test Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
function testEmail() {
    if (confirm('Send a test email to verify SMTP configuration?')) {
        window.location.href = 'test-email.php';
    }
}
</script>

<?php include 'includes/footer.php'; ?>