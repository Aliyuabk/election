<?php
$page_title = "SMS Settings";
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
        foreach ($_POST as $key => $value) {
            if (in_array($key, ['action', 'submit'])) continue;
            
            $stmt = $conn->prepare("UPDATE system_settings SET value = ?, updated_at = NOW() WHERE `key` = ?");
            $stmt->execute([$value, $key]);
        }
        
        logActivity(getValidUserId(), null, 'settings_changed', "SMS settings updated");
        $message = "SMS settings saved successfully.";
        $message_type = 'success';
        
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
            <h1><i class="fas fa-sms" style="color:#4f9cf7;"></i> SMS Settings</h1>
            <p class="subtitle">Configure SMS provider and notification settings</p>
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
                <input type="hidden" name="action" value="save_sms_settings">
                
                <h2>SMS Provider Configuration</h2>
                <p class="section-desc">Configure your SMS service provider</p>

                <div class="form-group">
                    <label for="sms_provider">SMS Provider <span class="required">*</span></label>
                    <select name="sms_provider" id="sms_provider" class="form-control">
                        <option value="twilio" <?php echo getSetting('sms_provider', 'twilio') === 'twilio' ? 'selected' : ''; ?>>Twilio</option>
                        <option value="africastalking" <?php echo getSetting('sms_provider', 'twilio') === 'africastalking' ? 'selected' : ''; ?>>Africa's Talking</option>
                        <option value="termii" <?php echo getSetting('sms_provider', 'twilio') === 'termii' ? 'selected' : ''; ?>>Termii</option>
                        <option value="messagebird" <?php echo getSetting('sms_provider', 'twilio') === 'messagebird' ? 'selected' : ''; ?>>MessageBird</option>
                        <option value="vonage" <?php echo getSetting('sms_provider', 'twilio') === 'vonage' ? 'selected' : ''; ?>>Vonage (Nexmo)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="sms_api_key">API Key <span class="required">*</span></label>
                    <input type="password" name="sms_api_key" id="sms_api_key" class="form-control" 
                           value="<?php echo htmlspecialchars(getSetting('sms_api_key', '')); ?>" required>
                    <small>Your SMS provider API key</small>
                </div>

                <div class="form-group">
                    <label for="sms_api_secret">API Secret <span class="required">*</span></label>
                    <input type="password" name="sms_api_secret" id="sms_api_secret" class="form-control" 
                           value="<?php echo htmlspecialchars(getSetting('sms_api_secret', '')); ?>" required>
                    <small>Your SMS provider API secret</small>
                </div>

                <div class="form-group">
                    <label for="sms_sender_id">Sender ID</label>
                    <input type="text" name="sms_sender_id" id="sms_sender_id" class="form-control" 
                           value="<?php echo htmlspecialchars(getSetting('sms_sender_id', '5G Election')); ?>">
                    <small>Sender name displayed to recipients (max 11 characters)</small>
                </div>

                <div class="form-group">
                    <div class="switch-wrapper">
                        <label class="switch">
                            <input type="checkbox" name="sms_enabled" value="true" 
                                   <?php echo getSetting('sms_enabled', 'true') === 'true' ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <span class="switch-label">Enable SMS Notifications</span>
                    </div>
                    <small>Enable SMS notifications for the platform</small>
                </div>

                <h2 style="margin-top:24px;">SMS Templates</h2>
                <p class="section-desc">Configure SMS message templates</p>

                <div class="form-group">
                    <label for="sms_verification_template">Verification Template</label>
                    <textarea name="sms_verification_template" id="sms_verification_template" class="form-control" rows="3">Your verification code is: {code}. Valid for {expiry} minutes.</textarea>
                    <small>Available variables: {code}, {expiry}, {name}</small>
                </div>

                <div class="form-group">
                    <label for="sms_alert_template">Alert Template</label>
                    <textarea name="sms_alert_template" id="sms_alert_template" class="form-control" rows="3">Alert: {message}. Please check the system.</textarea>
                    <small>Available variables: {message}, {type}, {time}</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                    <button type="button" class="btn-secondary" onclick="testSMS()">
                        <i class="fas fa-paper-plane"></i> Test SMS
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
function testSMS() {
    const phone = prompt('Enter phone number to send test SMS (e.g., +2348005555555):');
    if (phone) {
        if (confirm(`Send test SMS to ${phone}?`)) {
            window.location.href = `test-sms.php?phone=${encodeURIComponent(phone)}`;
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>