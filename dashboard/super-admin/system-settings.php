<?php
$page_title = "System Settings";
require_once 'includes/db.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// ============================================================
// HANDLE ACTIONS
// ============================================================
$message = '';
$error = '';
$message_type = '';

// Get current settings
$settings = [];
$stmt = $conn->query("SELECT * FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'save_settings') {
            // Process each setting
            foreach ($_POST as $key => $value) {
                if (in_array($key, ['action', 'submit'])) continue;
                
                // Check if setting exists
                $stmt = $conn->prepare("SELECT id FROM system_settings WHERE `key` = ?");
                $stmt->execute([$key]);
                $exists = $stmt->fetch();
                
                if ($exists) {
                    // Update existing
                    $stmt = $conn->prepare("UPDATE system_settings SET value = ?, updated_at = NOW() WHERE `key` = ?");
                    $stmt->execute([$value, $key]);
                } else {
                    // Insert new
                    $stmt = $conn->prepare("INSERT INTO system_settings (`key`, `value`, type, created_at) VALUES (?, ?, 'string', NOW())");
                    $stmt->execute([$key, $value]);
                }
            }
            
            // Handle file uploads
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/settings/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $filename = 'logo.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                    $logo_url = '/uploads/settings/' . $filename;
                    $stmt = $conn->prepare("UPDATE system_settings SET value = ?, updated_at = NOW() WHERE `key` = 'logo_url'");
                    $stmt->execute([$logo_url]);
                }
            }
            
            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/settings/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
                $filename = 'favicon.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['favicon']['tmp_name'], $filepath)) {
                    $favicon_url = '/uploads/settings/' . $filename;
                    $stmt = $conn->prepare("UPDATE system_settings SET value = ?, updated_at = NOW() WHERE `key` = 'favicon_url'");
                    $stmt->execute([$favicon_url]);
                }
            }
            
            logActivity(getValidUserId(), null, 'settings_changed', "System settings updated");
            $message = "Settings saved successfully.";
            $message_type = 'success';
            
            // Refresh settings
            $settings = [];
            $stmt = $conn->query("SELECT * FROM system_settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['key']] = $row;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        $message_type = 'error';
    }
}

// Helper function to get setting value
function getSetting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key]['value'] : $default;
}

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<style>
/* ============================================================
   SYSTEM SETTINGS STYLES
   ============================================================ */

.settings-container {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 24px;
}

.settings-sidebar {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #eef3f8;
    height: fit-content;
    position: sticky;
    top: 80px;
}

.settings-sidebar .nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-radius: 10px;
    color: #6d83a5;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
}

.settings-sidebar .nav-item:hover {
    background: #f8faff;
    color: #1f3149;
}

.settings-sidebar .nav-item.active {
    background: #e8f0fe;
    color: #4f9cf7;
}

.settings-sidebar .nav-item i {
    width: 20px;
    font-size: 1rem;
}

.settings-content {
    background: white;
    border-radius: 14px;
    padding: 24px;
    border: 1px solid #eef3f8;
}

.settings-section {
    display: none;
}

.settings-section.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

.settings-section h2 {
    font-size: 1.2rem;
    font-weight: 600;
    color: #0b1a33;
    margin-bottom: 4px;
}

.settings-section .section-desc {
    color: #6d83a5;
    font-size: 0.9rem;
    margin-bottom: 24px;
}

.settings-section .form-group {
    margin-bottom: 20px;
}

.settings-section .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    color: #1f3149;
    margin-bottom: 6px;
}

.settings-section .form-group .required {
    color: #ef4444;
}

.settings-section .form-group .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #dce6f0;
    border-radius: 10px;
    font-size: 0.9rem;
    background: white;
    color: #1f3149;
    transition: all 0.2s ease;
}

.settings-section .form-group .form-control:focus {
    outline: none;
    border-color: #4f9cf7;
    box-shadow: 0 0 0 3px rgba(79, 156, 247, 0.1);
}

.settings-section .form-group .form-control:disabled {
    background: #f8faff;
    cursor: not-allowed;
}

.settings-section .form-group .form-control[readonly] {
    background: #f8faff;
}

.settings-section .form-group small {
    display: block;
    font-size: 0.7rem;
    color: #8b9bb5;
    margin-top: 4px;
}

.settings-section .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.settings-section .form-row .form-group {
    margin-bottom: 0;
}

/* File Upload */
.file-upload-wrapper {
    position: relative;
}

.file-upload-area {
    border: 2px dashed #dce6f0;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.file-upload-area:hover {
    border-color: #4f9cf7;
    background: #f8faff;
}

.file-upload-area .file-input {
    display: none;
}

.file-upload-area i {
    font-size: 2rem;
    color: #8b9bb5;
    display: block;
    margin-bottom: 8px;
}

.file-upload-area .file-text {
    font-size: 0.9rem;
    color: #1f3149;
}

.file-upload-area .file-hint {
    font-size: 0.7rem;
    color: #8b9bb5;
}

.current-file {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 12px;
    padding: 10px 16px;
    background: #f8faff;
    border-radius: 8px;
    border: 1px solid #eef3f8;
}

.current-file img {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    object-fit: cover;
}

.current-file .file-info {
    flex: 1;
}

.current-file .file-name {
    font-weight: 500;
    color: #0b1a33;
}

.current-file .file-size {
    font-size: 0.7rem;
    color: #8b9bb5;
}

/* Toggle Switch */
.switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.switch .slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: #dce6f0;
    transition: 0.3s;
    border-radius: 34px;
}

.switch .slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: white;
    transition: 0.3s;
    border-radius: 50%;
}

.switch input:checked + .slider {
    background: #4f9cf7;
}

.switch input:checked + .slider:before {
    transform: translateX(22px);
}

.switch-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.switch-wrapper .switch-label {
    font-size: 0.85rem;
    color: #1f3149;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #eef3f8;
}

.form-actions .btn-primary,
.form-actions .btn-secondary {
    padding: 10px 24px;
    font-size: 0.9rem;
    border-radius: 10px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    text-decoration: none;
}

/* Responsive */
@media (max-width: 1024px) {
    .settings-container {
        grid-template-columns: 1fr;
    }
    
    .settings-sidebar {
        position: static;
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        padding: 12px;
    }
    
    .settings-sidebar .nav-item {
        flex: 1;
        min-width: 120px;
        justify-content: center;
        padding: 8px 12px;
        font-size: 0.8rem;
    }
}

@media (max-width: 768px) {
    .settings-section .form-row {
        grid-template-columns: 1fr;
    }
    
    .settings-sidebar .nav-item span {
        display: none;
    }
    
    .settings-sidebar .nav-item i {
        font-size: 1.2rem;
        width: auto;
    }
}
</style>

<main class="main-content">
    <!-- ============================================================
    PAGE HEADER
    ============================================================ -->
    <div class="page-header">
        <div class="header-left">
            <h1>
                <i class="fas fa-cog" style="color:#4f9cf7;"></i>
                System Settings
                <span class="page-badge">Configuration</span>
            </h1>
            <p class="subtitle">Manage system configuration and preferences</p>
        </div>
    </div>

    <!-- ============================================================
    ALERTS
    ============================================================ -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type ?: 'success'; ?>">
        <i class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
        <?php echo $message; ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- ============================================================
    SETTINGS CONTAINER
    ============================================================ -->
    <div class="settings-container">
        <!-- Sidebar -->
        <div class="settings-sidebar">
            <button class="nav-item active" data-section="general">
                <i class="fas fa-globe"></i>
                <span>General</span>
            </button>
            <button class="nav-item" data-section="security">
                <i class="fas fa-shield-alt"></i>
                <span>Security</span>
            </button>
            <button class="nav-item" data-section="email">
                <i class="fas fa-envelope"></i>
                <span>Email</span>
            </button>
            <button class="nav-item" data-section="storage">
                <i class="fas fa-hdd"></i>
                <span>Storage</span>
            </button>
            <button class="nav-item" data-section="backup">
                <i class="fas fa-database"></i>
                <span>Backup</span>
            </button>
        </div>

        <!-- Content -->
        <div class="settings-content">
            <form method="POST" enctype="multipart/form-data" id="settingsForm">
                <input type="hidden" name="action" value="save_settings">

                <!-- ============================================================
                GENERAL SECTION
                ============================================================ -->
                <div class="settings-section active" id="section-general">
                    <h2>General Settings</h2>
                    <p class="section-desc">Basic platform configuration and branding</p>

                    <div class="form-group">
                        <label for="site_name">Platform Name <span class="required">*</span></label>
                        <input type="text" name="site_name" id="site_name" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('site_name', '5G Election Guru')); ?>" required>
                        <small>Name of your platform displayed throughout the system</small>
                    </div>

                    <div class="form-group">
                        <label for="site_url">Platform URL</label>
                        <input type="url" name="site_url" id="site_url" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('site_url', 'http://localhost/election')); ?>">
                        <small>Base URL of your platform</small>
                    </div>

                    <div class="form-group">
                        <label for="contact_email">Contact Email</label>
                        <input type="email" name="contact_email" id="contact_email" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('contact_email', 'admin@5gguru.ng')); ?>">
                        <small>Primary contact email for the platform</small>
                    </div>

                    <div class="form-group">
                        <label for="contact_phone">Contact Phone</label>
                        <input type="tel" name="contact_phone" id="contact_phone" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('contact_phone', '+2348005555555')); ?>">
                        <small>Primary contact phone number</small>
                    </div>

                    <div class="form-group">
                        <label>Platform Logo</label>
                        <div class="file-upload-wrapper">
                            <div class="file-upload-area" id="logoUpload">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div class="file-text">Click or drag to upload logo</div>
                                <div class="file-hint">PNG, JPG, SVG (Max 2MB)</div>
                                <input type="file" name="logo" class="file-input" accept="image/*">
                            </div>
                            <?php 
                            $logo_url = getSetting('logo_url', '');
                            if ($logo_url): 
                            ?>
                            <div class="current-file">
                                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo">
                                <div class="file-info">
                                    <div class="file-name">Current Logo</div>
                                    <div class="file-size">Click to replace</div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Favicon</label>
                        <div class="file-upload-wrapper">
                            <div class="file-upload-area" id="faviconUpload">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div class="file-text">Click or drag to upload favicon</div>
                                <div class="file-hint">ICO, PNG (Max 1MB)</div>
                                <input type="file" name="favicon" class="file-input" accept=".ico,image/*">
                            </div>
                            <?php 
                            $favicon_url = getSetting('favicon_url', '');
                            if ($favicon_url): 
                            ?>
                            <div class="current-file">
                                <img src="<?php echo htmlspecialchars($favicon_url); ?>" alt="Favicon" style="width:32px; height:32px;">
                                <div class="file-info">
                                    <div class="file-name">Current Favicon</div>
                                    <div class="file-size">Click to replace</div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="timezone">Timezone</label>
                        <select name="timezone" id="timezone" class="form-control">
                            <?php
                            $timezones = [
                                'Africa/Lagos' => 'Africa/Lagos (UTC+1)',
                                'Africa/Cairo' => 'Africa/Cairo (UTC+2)',
                                'Africa/Johannesburg' => 'Africa/Johannesburg (UTC+2)',
                                'Europe/London' => 'Europe/London (UTC+0)',
                                'Europe/Paris' => 'Europe/Paris (UTC+1)',
                                'America/New_York' => 'America/New_York (UTC-5)',
                                'America/Chicago' => 'America/Chicago (UTC-6)',
                                'America/Denver' => 'America/Denver (UTC-7)',
                                'America/Los_Angeles' => 'America/Los_Angeles (UTC-8)',
                                'Asia/Dubai' => 'Asia/Dubai (UTC+4)',
                                'Asia/Singapore' => 'Asia/Singapore (UTC+8)',
                                'Australia/Sydney' => 'Australia/Sydney (UTC+11)',
                            ];
                            $current_timezone = getSetting('timezone', 'Africa/Lagos');
                            foreach ($timezones as $value => $label):
                            ?>
                            <option value="<?php echo $value; ?>" <?php echo $current_timezone === $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Default timezone for the platform</small>
                    </div>
                </div>

                <!-- ============================================================
                SECURITY SECTION
                ============================================================ -->
                <div class="settings-section" id="section-security">
                    <h2>Security Settings</h2>
                    <p class="section-desc">Platform security and authentication configuration</p>

                    <div class="form-group">
                        <label for="max_login_attempts">Max Login Attempts</label>
                        <input type="number" name="max_login_attempts" id="max_login_attempts" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('max_login_attempts', '5')); ?>" min="1" max="20">
                        <small>Maximum failed login attempts before lockout</small>
                    </div>

                    <div class="form-group">
                        <label for="lockout_duration">Lockout Duration (minutes)</label>
                        <input type="number" name="lockout_duration" id="lockout_duration" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('lockout_duration', '15')); ?>" min="1" max="1440">
                        <small>Duration of lockout after failed attempts</small>
                    </div>

                    <div class="form-group">
                        <label for="session_timeout">Session Timeout (seconds)</label>
                        <input type="number" name="session_timeout" id="session_timeout" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('session_timeout', '3600')); ?>" min="60" max="86400">
                        <small>Maximum session duration before automatic logout</small>
                    </div>

                    <div class="form-group">
                        <label for="otp_expiry">OTP Expiry (seconds)</label>
                        <input type="number" name="otp_expiry" id="otp_expiry" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('otp_expiry', '300')); ?>" min="60" max="900">
                        <small>Time window for OTP verification</small>
                    </div>

                    <div class="form-group">
                        <div class="switch-wrapper">
                            <label class="switch">
                                <input type="checkbox" name="two_factor_enabled" value="true" 
                                       <?php echo getSetting('two_factor_enabled', 'true') === 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span class="switch-label">Enable Two-Factor Authentication</span>
                        </div>
                        <small>Require two-factor authentication for all users</small>
                    </div>

                    <div class="form-group">
                        <div class="switch-wrapper">
                            <label class="switch">
                                <input type="checkbox" name="captcha_enabled" value="true" 
                                       <?php echo getSetting('captcha_enabled', 'true') === 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span class="switch-label">Enable CAPTCHA</span>
                        </div>
                        <small>Protect login and registration with CAPTCHA</small>
                    </div>

                    <div class="form-group">
                        <label for="password_min_length">Minimum Password Length</label>
                        <input type="number" name="password_min_length" id="password_min_length" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('password_min_length', '8')); ?>" min="6" max="20">
                        <small>Minimum characters required for passwords</small>
                    </div>
                </div>

                <!-- ============================================================
                EMAIL SECTION
                ============================================================ -->
                <div class="settings-section" id="section-email">
                    <h2>Email Settings</h2>
                    <p class="section-desc">SMTP configuration for email notifications</p>

                    <div class="form-group">
                        <label for="smtp_host">SMTP Host</label>
                        <input type="text" name="smtp_host" id="smtp_host" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('smtp_host', 'smtp.gmail.com')); ?>">
                        <small>SMTP server hostname</small>
                    </div>

                    <div class="form-group">
                        <label for="smtp_port">SMTP Port</label>
                        <input type="number" name="smtp_port" id="smtp_port" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('smtp_port', '587')); ?>" min="1" max="65535">
                        <small>SMTP server port (587 for TLS, 465 for SSL)</small>
                    </div>

                    <div class="form-group">
                        <label for="smtp_username">SMTP Username</label>
                        <input type="text" name="smtp_username" id="smtp_username" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('smtp_username', '')); ?>">
                        <small>Username for SMTP authentication</small>
                    </div>

                    <div class="form-group">
                        <label for="smtp_password">SMTP Password</label>
                        <input type="password" name="smtp_password" id="smtp_password" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('smtp_password', '')); ?>">
                        <small>Password for SMTP authentication (leave blank to keep current)</small>
                    </div>

                    <div class="form-group">
                        <label for="smtp_encryption">SMTP Encryption</label>
                        <select name="smtp_encryption" id="smtp_encryption" class="form-control">
                            <option value="tls" <?php echo getSetting('smtp_encryption', 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo getSetting('smtp_encryption', 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo getSetting('smtp_encryption', 'tls') === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                        <small>Encryption method for SMTP connection</small>
                    </div>

                    <div class="form-group">
                        <label for="sender_name">Sender Name</label>
                        <input type="text" name="sender_name" id="sender_name" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('sender_name', '5G Election Guru')); ?>">
                        <small>Name displayed as the email sender</small>
                    </div>

                    <div class="form-group">
                        <label for="sender_email">Sender Email</label>
                        <input type="email" name="sender_email" id="sender_email" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('sender_email', 'no-reply@5gguru.ng')); ?>">
                        <small>Email address used as the sender</small>
                    </div>
                </div>

                <!-- ============================================================
                STORAGE SECTION
                ============================================================ -->
                <div class="settings-section" id="section-storage">
                    <h2>Storage Settings</h2>
                    <p class="section-desc">File upload and storage configuration</p>

                    <div class="form-group">
                        <label for="max_upload_size">Maximum Upload Size (MB)</label>
                        <input type="number" name="max_upload_size" id="max_upload_size" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('max_upload_size', '10')); ?>" min="1" max="100">
                        <small>Maximum file size for uploads in megabytes</small>
                    </div>

                    <div class="form-group">
                        <label for="allowed_file_types">Allowed File Types</label>
                        <input type="text" name="allowed_file_types" id="allowed_file_types" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('allowed_file_types', 'jpg,jpeg,png,gif,svg,pdf,doc,docx,xls,xlsx,csv')); ?>">
                        <small>Comma-separated list of allowed file extensions</small>
                    </div>

                    <div class="form-group">
                        <label for="storage_path">Storage Path</label>
                        <input type="text" name="storage_path" id="storage_path" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('storage_path', '../uploads/')); ?>" readonly>
                        <small>File storage directory (read-only)</small>
                    </div>
                </div>

                <!-- ============================================================
                BACKUP SECTION
                ============================================================ -->
                <div class="settings-section" id="section-backup">
                    <h2>Backup Settings</h2>
                    <p class="section-desc">Automated backup configuration</p>

                    <div class="form-group">
                        <div class="switch-wrapper">
                            <label class="switch">
                                <input type="checkbox" name="auto_backup_enabled" value="true" 
                                       <?php echo getSetting('auto_backup_enabled', 'true') === 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span class="switch-label">Enable Automatic Backup</span>
                        </div>
                        <small>Automatically create backups on schedule</small>
                    </div>

                    <div class="form-group">
                        <label for="backup_frequency">Backup Frequency</label>
                        <select name="backup_frequency" id="backup_frequency" class="form-control">
                            <option value="daily" <?php echo getSetting('backup_frequency', 'daily') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo getSetting('backup_frequency', 'daily') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo getSetting('backup_frequency', 'daily') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                        <small>How often to create automatic backups</small>
                    </div>

                    <div class="form-group">
                        <label for="backup_retention">Backup Retention (days)</label>
                        <input type="number" name="backup_retention" id="backup_retention" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('backup_retention', '30')); ?>" min="1" max="365">
                        <small>Number of days to keep backups before auto-deletion</small>
                    </div>

                    <div class="form-group">
                        <label for="backup_time">Backup Time</label>
                        <input type="time" name="backup_time" id="backup_time" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('backup_time', '02:00')); ?>">
                        <small>Time of day to run automatic backups (24-hour format)</small>
                    </div>
                </div>

                <!-- ============================================================
                FORM ACTIONS
                ============================================================ -->
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// ============================================================
// SECTION NAVIGATION
// ============================================================
document.querySelectorAll('.settings-sidebar .nav-item').forEach(item => {
    item.addEventListener('click', function() {
        // Remove active from all nav items
        document.querySelectorAll('.settings-sidebar .nav-item').forEach(nav => nav.classList.remove('active'));
        this.classList.add('active');
        
        // Hide all sections
        document.querySelectorAll('.settings-section').forEach(section => section.classList.remove('active'));
        
        // Show selected section
        const sectionId = this.dataset.section;
        document.getElementById('section-' + sectionId).classList.add('active');
    });
});

// ============================================================
// FILE UPLOAD HANDLING
// ============================================================
document.querySelectorAll('.file-upload-area').forEach(area => {
    const input = area.querySelector('.file-input');
    
    area.addEventListener('click', () => input.click());
    
    input.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            const text = area.querySelector('.file-text');
            if (text) text.textContent = file.name;
            const hint = area.querySelector('.file-hint');
            if (hint) hint.textContent = (file.size / 1024).toFixed(1) + ' KB';
        }
    });
    
    // Drag and drop
    area.addEventListener('dragover', (e) => {
        e.preventDefault();
        area.style.borderColor = '#4f9cf7';
        area.style.background = '#e8f0fe';
    });
    
    area.addEventListener('dragleave', () => {
        area.style.borderColor = '#dce6f0';
        area.style.background = 'transparent';
    });
    
    area.addEventListener('drop', (e) => {
        e.preventDefault();
        area.style.borderColor = '#dce6f0';
        area.style.background = 'transparent';
        
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            input.files = e.dataTransfer.files;
            const file = e.dataTransfer.files[0];
            const text = area.querySelector('.file-text');
            if (text) text.textContent = file.name;
            const hint = area.querySelector('.file-hint');
            if (hint) hint.textContent = (file.size / 1024).toFixed(1) + ' KB';
        }
    });
});

// ============================================================
// FORM RESET CONFIRMATION
// ============================================================
document.querySelector('button[type="reset"]')?.addEventListener('click', function(e) {
    e.preventDefault();
    if (confirm('Reset all form fields to their default values?')) {
        document.getElementById('settingsForm').reset();
    }
});

// ============================================================
// AUTO-SAVE INDICATOR
// ============================================================
let saveTimeout;
document.querySelectorAll('#settingsForm input, #settingsForm select').forEach(field => {
    field.addEventListener('change', function() {
        const indicator = document.querySelector('.auto-save-indicator');
        if (indicator) {
            indicator.textContent = 'Unsaved changes';
            indicator.style.color = '#f59e0b';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>