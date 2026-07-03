<?php
// ============================================================
// SETTINGS - SUPER ADMINISTRATOR (FIXED)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only super_admin can access this page
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

// ============================================================
// ENSURE SYSTEM SETTINGS TABLE EXISTS
// ============================================================
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            `key` VARCHAR(100) NOT NULL UNIQUE,
            value TEXT NOT NULL,
            type ENUM('string','integer','boolean','json','array') DEFAULT 'string',
            description TEXT,
            is_editable TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {}

// ============================================================
// FETCH SYSTEM SETTINGS
// ============================================================
$settings = [];
try {
    // Use backticks for reserved keyword 'key'
    $stmt = $db->query("SELECT * FROM system_settings ORDER BY `key`");
    $settings = $stmt->fetchAll();
} catch (Exception $e) {
    // If table doesn't exist, create default settings
    try {
        $default_settings = [
            ['site_name', APP_NAME, 'string', 'Site name'],
            ['site_url', APP_URL, 'string', 'Site URL'],
            ['contact_email', 'admin@example.com', 'string', 'Contact email'],
            ['contact_phone', '', 'string', 'Contact phone'],
            ['timezone', 'Africa/Lagos', 'string', 'System timezone'],
            ['max_login_attempts', '5', 'integer', 'Maximum login attempts'],
            ['lockout_duration', '15', 'integer', 'Lockout duration in minutes'],
            ['session_timeout', '3600', 'integer', 'Session timeout in seconds'],
            ['otp_expiry', '300', 'integer', 'OTP expiry in seconds'],
            ['two_factor_enabled', 'true', 'boolean', 'Enable two-factor authentication'],
            ['captcha_enabled', 'true', 'boolean', 'Enable CAPTCHA on login'],
            ['password_min_length', '8', 'integer', 'Minimum password length'],
            ['smtp_host', 'smtp.gmail.com', 'string', 'SMTP host'],
            ['smtp_port', '587', 'integer', 'SMTP port'],
            ['smtp_username', '', 'string', 'SMTP username'],
            ['smtp_password', '', 'string', 'SMTP password'],
            ['smtp_encryption', 'tls', 'string', 'SMTP encryption'],
            ['sender_name', APP_NAME, 'string', 'Sender name'],
            ['sender_email', 'no-reply@example.com', 'string', 'Sender email'],
            ['auto_backup_enabled', 'true', 'boolean', 'Enable automatic backups'],
            ['backup_frequency', 'daily', 'string', 'Backup frequency'],
            ['backup_retention', '30', 'integer', 'Backup retention in days'],
            ['backup_time', '02:00', 'string', 'Backup time']
        ];
        
        foreach ($default_settings as $setting) {
            $stmt = $db->prepare("INSERT INTO system_settings (`key`, value, type, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$setting[0], $setting[1], $setting[2], $setting[3]]);
        }
        
        $stmt = $db->query("SELECT * FROM system_settings ORDER BY `key`");
        $settings = $stmt->fetchAll();
    } catch (Exception $e2) {
        // Continue with empty settings
    }
}

// Convert to associative array for easier access
$settings_map = [];
foreach ($settings as $setting) {
    $settings_map[$setting['key']] = $setting['value'];
}

// ============================================================
// HANDLE SETTINGS UPDATE
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_general':
                $site_name = trim($_POST['site_name'] ?? '');
                $site_url = trim($_POST['site_url'] ?? '');
                $contact_email = trim($_POST['contact_email'] ?? '');
                $contact_phone = trim($_POST['contact_phone'] ?? '');
                $timezone = $_POST['timezone'] ?? 'Africa/Lagos';
                
                if (empty($site_name) || empty($site_url)) {
                    throw new Exception('Site name and URL are required.');
                }
                
                $updates = [
                    'site_name' => $site_name,
                    'site_url' => $site_url,
                    'contact_email' => $contact_email,
                    'contact_phone' => $contact_phone,
                    'timezone' => $timezone
                ];
                
                foreach ($updates as $key => $value) {
                    $stmt = $db->prepare("UPDATE system_settings SET value = ? WHERE `key` = ?");
                    $stmt->execute([$value, $key]);
                }
                
                $success = "General settings updated successfully!";
                break;
                
            case 'update_security':
                $max_login_attempts = (int)($_POST['max_login_attempts'] ?? 5);
                $lockout_duration = (int)($_POST['lockout_duration'] ?? 15);
                $session_timeout = (int)($_POST['session_timeout'] ?? 3600);
                $otp_expiry = (int)($_POST['otp_expiry'] ?? 300);
                $two_factor_enabled = isset($_POST['two_factor_enabled']) ? 'true' : 'false';
                $captcha_enabled = isset($_POST['captcha_enabled']) ? 'true' : 'false';
                $password_min_length = (int)($_POST['password_min_length'] ?? 8);
                
                $updates = [
                    'max_login_attempts' => $max_login_attempts,
                    'lockout_duration' => $lockout_duration,
                    'session_timeout' => $session_timeout,
                    'otp_expiry' => $otp_expiry,
                    'two_factor_enabled' => $two_factor_enabled,
                    'captcha_enabled' => $captcha_enabled,
                    'password_min_length' => $password_min_length
                ];
                
                foreach ($updates as $key => $value) {
                    $stmt = $db->prepare("UPDATE system_settings SET value = ? WHERE `key` = ?");
                    $stmt->execute([$value, $key]);
                }
                
                $success = "Security settings updated successfully!";
                break;
                
            case 'update_email':
                $smtp_host = trim($_POST['smtp_host'] ?? '');
                $smtp_port = (int)($_POST['smtp_port'] ?? 587);
                $smtp_username = trim($_POST['smtp_username'] ?? '');
                $smtp_password = trim($_POST['smtp_password'] ?? '');
                $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
                $sender_name = trim($_POST['sender_name'] ?? '');
                $sender_email = trim($_POST['sender_email'] ?? '');
                
                $updates = [
                    'smtp_host' => $smtp_host,
                    'smtp_port' => $smtp_port,
                    'smtp_username' => $smtp_username,
                    'smtp_password' => $smtp_password,
                    'smtp_encryption' => $smtp_encryption,
                    'sender_name' => $sender_name,
                    'sender_email' => $sender_email
                ];
                
                foreach ($updates as $key => $value) {
                    $stmt = $db->prepare("UPDATE system_settings SET value = ? WHERE `key` = ?");
                    $stmt->execute([$value, $key]);
                }
                
                $success = "Email settings updated successfully!";
                break;
                
            case 'update_backup':
                $auto_backup_enabled = isset($_POST['auto_backup_enabled']) ? 'true' : 'false';
                $backup_frequency = $_POST['backup_frequency'] ?? 'daily';
                $backup_retention = (int)($_POST['backup_retention'] ?? 30);
                $backup_time = $_POST['backup_time'] ?? '02:00';
                
                $updates = [
                    'auto_backup_enabled' => $auto_backup_enabled,
                    'backup_frequency' => $backup_frequency,
                    'backup_retention' => $backup_retention,
                    'backup_time' => $backup_time
                ];
                
                foreach ($updates as $key => $value) {
                    $stmt = $db->prepare("UPDATE system_settings SET value = ? WHERE `key` = ?");
                    $stmt->execute([$value, $key]);
                }
                
                $success = "Backup settings updated successfully!";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    
    // Refresh settings
    $stmt = $db->query("SELECT * FROM system_settings ORDER BY `key`");
    $settings = $stmt->fetchAll();
    $settings_map = [];
    foreach ($settings as $setting) {
        $settings_map[$setting['key']] = $setting['value'];
    }
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<!-- Rest of the HTML remains the same -->
<style>
    /* ============================================================
       SETTINGS - PRO STYLES
       ============================================================ */
    .settings-container { max-width: 1000px; margin: 0 auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
    .page-header h2 { font-size: 1.3rem; font-weight: 700; }
    .page-header h2 small { font-size: 0.8rem; font-weight: 400; color: var(--gray-500); display: block; margin-top: 2px; }
    
    .settings-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
    .settings-tab { padding: 10px 22px; border-radius: 10px; border: 1px solid var(--gray-200); background: white; color: var(--gray-600); text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: var(--transition); cursor: pointer; display: flex; align-items: center; gap: 8px; }
    .settings-tab:hover { background: var(--gray-50); border-color: var(--gray-300); }
    .settings-tab.active { background: var(--primary); color: white; border-color: var(--primary); }
    .settings-tab i { font-size: 0.9rem; }
    
    .settings-panel { display: none; }
    .settings-panel.active { display: block; }
    
    .settings-card { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 28px 32px; box-shadow: var(--shadow); }
    .settings-card .card-title { font-weight: 600; font-size: 1rem; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
    .settings-card .card-title i { color: var(--primary); }
    .settings-card .card-desc { color: var(--gray-500); font-size: 0.85rem; margin-bottom: 20px; }
    
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; }
    .form-group { display: flex; flex-direction: column; gap: 4px; }
    .form-group.full-width { grid-column: 1 / -1; }
    .form-group label { font-weight: 600; font-size: 0.82rem; color: var(--gray-700); }
    .form-group label .required { color: var(--danger); margin-left: 2px; }
    .form-group .help-text { font-size: 0.7rem; color: var(--gray-400); margin-top: 2px; }
    .form-group input, .form-group select { padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; transition: var(--transition); background: var(--gray-50); color: var(--gray-700); width: 100%; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06); }
    .form-group .checkbox-group { display: flex; align-items: center; gap: 10px; padding-top: 6px; }
    .form-group .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; accent-color: var(--primary); cursor: pointer; flex-shrink: 0; }
    .form-group .checkbox-group label { font-weight: 400; cursor: pointer; font-size: 0.85rem; }
    
    .form-actions { display: flex; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--gray-200); flex-wrap: wrap; }
    .form-actions .btn { padding: 10px 28px; border-radius: 10px; border: none; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .form-actions .btn-primary { background: var(--primary); color: white; }
    .form-actions .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25); }
    .form-actions .btn-secondary { background: var(--gray-100); color: var(--gray-600); }
    .form-actions .btn-secondary:hover { background: var(--gray-200); }
    
    .error-message { background: #FEF2F2; color: #DC2626; padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; border: 1px solid #FECACA; display: flex; align-items: flex-start; gap: 12px; }
    .success-message { background: #ECFDF5; color: #065F46; padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; border: 1px solid #A7F3D0; display: flex; align-items: flex-start; gap: 12px; }
    
    @media (max-width: 768px) {
        .settings-tabs { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .settings-tab { white-space: nowrap; padding: 8px 16px; font-size: 0.8rem; }
        .settings-card { padding: 20px; }
        .form-grid { grid-template-columns: 1fr; gap: 12px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { justify-content: center; width: 100%; }
        .page-header { flex-direction: column; align-items: flex-start; }
    }
    @media (max-width: 480px) {
        .settings-card { padding: 16px; }
        .settings-tab { padding: 6px 12px; font-size: 0.75rem; }
        .form-group input, .form-group select { padding: 8px 12px; font-size: 0.8rem; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="settings-container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h2>
                        <i class="fas fa-cog" style="color:var(--primary);margin-right:8px;"></i> System Settings
                        <small>Configure system-wide settings and preferences</small>
                    </h2>
                </div>
            </div>

            <!-- Error/Success Messages -->
            <?php if (!empty($error)): ?>
                <div class="error-message"><i class="fas fa-exclamation-circle"></i><div><?php echo $error; ?></div></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="success-message"><i class="fas fa-check-circle"></i><div><?php echo $success; ?></div></div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="settings-tab active" data-tab="general" onclick="switchTab('general')">
                    <i class="fas fa-globe"></i> General
                </button>
                <button class="settings-tab" data-tab="security" onclick="switchTab('security')">
                    <i class="fas fa-shield-alt"></i> Security
                </button>
                <button class="settings-tab" data-tab="email" onclick="switchTab('email')">
                    <i class="fas fa-envelope"></i> Email
                </button>
                <button class="settings-tab" data-tab="backup" onclick="switchTab('backup')">
                    <i class="fas fa-archive"></i> Backup
                </button>
            </div>

            <!-- General Settings -->
            <div class="settings-panel active" id="panel-general">
                <div class="settings-card">
                    <div class="card-title"><i class="fas fa-globe"></i> General Settings</div>
                    <div class="card-desc">Configure basic system settings including site name, URL, and contact information.</div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_general">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Site Name <span class="required">*</span></label>
                                <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings_map['site_name'] ?? APP_NAME); ?>" required>
                            </div>
                            <div class="form-group full-width">
                                <label>Site URL <span class="required">*</span></label>
                                <input type="url" name="site_url" value="<?php echo htmlspecialchars($settings_map['site_url'] ?? APP_URL); ?>" required>
                                <div class="help-text">The full URL of your application.</div>
                            </div>
                            <div class="form-group">
                                <label>Contact Email</label>
                                <input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings_map['contact_email'] ?? 'admin@example.com'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Contact Phone</label>
                                <input type="tel" name="contact_phone" value="<?php echo htmlspecialchars($settings_map['contact_phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group full-width">
                                <label>Timezone</label>
                                <select name="timezone">
                                    <option value="Africa/Lagos" <?php echo ($settings_map['timezone'] ?? 'Africa/Lagos') === 'Africa/Lagos' ? 'selected' : ''; ?>>Africa/Lagos (West Africa Time)</option>
                                    <option value="Africa/Cairo" <?php echo ($settings_map['timezone'] ?? '') === 'Africa/Cairo' ? 'selected' : ''; ?>>Africa/Cairo</option>
                                    <option value="Africa/Johannesburg" <?php echo ($settings_map['timezone'] ?? '') === 'Africa/Johannesburg' ? 'selected' : ''; ?>>Africa/Johannesburg</option>
                                    <option value="America/New_York" <?php echo ($settings_map['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                                    <option value="America/Los_Angeles" <?php echo ($settings_map['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>America/Los_Angeles</option>
                                    <option value="Europe/London" <?php echo ($settings_map['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                                    <option value="Europe/Paris" <?php echo ($settings_map['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : ''; ?>>Europe/Paris</option>
                                    <option value="Asia/Dubai" <?php echo ($settings_map['timezone'] ?? '') === 'Asia/Dubai' ? 'selected' : ''; ?>>Asia/Dubai</option>
                                    <option value="Asia/Kolkata" <?php echo ($settings_map['timezone'] ?? '') === 'Asia/Kolkata' ? 'selected' : ''; ?>>Asia/Kolkata</option>
                                    <option value="Asia/Singapore" <?php echo ($settings_map['timezone'] ?? '') === 'Asia/Singapore' ? 'selected' : ''; ?>>Asia/Singapore</option>
                                    <option value="Australia/Sydney" <?php echo ($settings_map['timezone'] ?? '') === 'Australia/Sydney' ? 'selected' : ''; ?>>Australia/Sydney</option>
                                    <option value="UTC" <?php echo ($settings_map['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save General Settings</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="settings-panel" id="panel-security">
                <div class="settings-card">
                    <div class="card-title"><i class="fas fa-shield-alt"></i> Security Settings</div>
                    <div class="card-desc">Configure security settings including login attempts, session timeout, and 2FA.</div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_security">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Max Login Attempts</label>
                                <input type="number" name="max_login_attempts" value="<?php echo htmlspecialchars($settings_map['max_login_attempts'] ?? 5); ?>" min="1" max="20">
                                <div class="help-text">Number of failed attempts before lockout.</div>
                            </div>
                            <div class="form-group">
                                <label>Lockout Duration (minutes)</label>
                                <input type="number" name="lockout_duration" value="<?php echo htmlspecialchars($settings_map['lockout_duration'] ?? 15); ?>" min="1" max="1440">
                            </div>
                            <div class="form-group">
                                <label>Session Timeout (seconds)</label>
                                <input type="number" name="session_timeout" value="<?php echo htmlspecialchars($settings_map['session_timeout'] ?? 3600); ?>" min="60">
                                <div class="help-text">1 hour = 3600 seconds</div>
                            </div>
                            <div class="form-group">
                                <label>OTP Expiry (seconds)</label>
                                <input type="number" name="otp_expiry" value="<?php echo htmlspecialchars($settings_map['otp_expiry'] ?? 300); ?>" min="60" max="1800">
                            </div>
                            <div class="form-group">
                                <label>Password Minimum Length</label>
                                <input type="number" name="password_min_length" value="<?php echo htmlspecialchars($settings_map['password_min_length'] ?? 8); ?>" min="6" max="20">
                            </div>
                            <div class="form-group full-width">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="two_factor_enabled" id="twoFactor" value="1" <?php echo ($settings_map['two_factor_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label for="twoFactor">Enable Two-Factor Authentication (2FA)</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="captcha_enabled" id="captchaEnabled" value="1" <?php echo ($settings_map['captcha_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label for="captchaEnabled">Enable CAPTCHA on Login</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Security Settings</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="settings-panel" id="panel-email">
                <div class="settings-card">
                    <div class="card-title"><i class="fas fa-envelope"></i> Email Settings</div>
                    <div class="card-desc">Configure SMTP settings for sending emails from the system.</div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_email">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>SMTP Host</label>
                                <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings_map['smtp_host'] ?? 'smtp.gmail.com'); ?>" placeholder="smtp.gmail.com">
                            </div>
                            <div class="form-group">
                                <label>SMTP Port</label>
                                <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($settings_map['smtp_port'] ?? 587); ?>" min="1" max="65535">
                            </div>
                            <div class="form-group">
                                <label>SMTP Username</label>
                                <input type="text" name="smtp_username" value="<?php echo htmlspecialchars($settings_map['smtp_username'] ?? ''); ?>" placeholder="your-email@gmail.com">
                            </div>
                            <div class="form-group">
                                <label>SMTP Password</label>
                                <input type="password" name="smtp_password" value="<?php echo htmlspecialchars($settings_map['smtp_password'] ?? ''); ?>" placeholder="••••••••">
                            </div>
                            <div class="form-group">
                                <label>SMTP Encryption</label>
                                <select name="smtp_encryption">
                                    <option value="tls" <?php echo ($settings_map['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo ($settings_map['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="none" <?php echo ($settings_map['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Sender Name</label>
                                <input type="text" name="sender_name" value="<?php echo htmlspecialchars($settings_map['sender_name'] ?? APP_NAME); ?>" placeholder="<?php echo APP_NAME; ?>">
                            </div>
                            <div class="form-group full-width">
                                <label>Sender Email</label>
                                <input type="email" name="sender_email" value="<?php echo htmlspecialchars($settings_map['sender_email'] ?? 'no-reply@example.com'); ?>" placeholder="no-reply@example.com">
                                <div class="help-text">The email address used as the "From" address for system emails.</div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Email Settings</button>
                            <button type="button" class="btn btn-secondary" onclick="testEmail()"><i class="fas fa-paper-plane"></i> Test Email</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Backup Settings -->
            <div class="settings-panel" id="panel-backup">
                <div class="settings-card">
                    <div class="card-title"><i class="fas fa-archive"></i> Backup Settings</div>
                    <div class="card-desc">Configure automatic backup settings for the system.</div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_backup">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="auto_backup_enabled" id="autoBackup" value="1" <?php echo ($settings_map['auto_backup_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label for="autoBackup">Enable Automatic Backups</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Backup Frequency</label>
                                <select name="backup_frequency">
                                    <option value="hourly" <?php echo ($settings_map['backup_frequency'] ?? 'daily') === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                    <option value="daily" <?php echo ($settings_map['backup_frequency'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo ($settings_map['backup_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo ($settings_map['backup_frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Backup Retention (days)</label>
                                <input type="number" name="backup_retention" value="<?php echo htmlspecialchars($settings_map['backup_retention'] ?? 30); ?>" min="1" max="365">
                                <div class="help-text">Number of days to keep backup files.</div>
                            </div>
                            <div class="form-group">
                                <label>Backup Time</label>
                                <input type="time" name="backup_time" value="<?php echo htmlspecialchars($settings_map['backup_time'] ?? '02:00'); ?>">
                                <div class="help-text">Time of day to run the backup (24-hour format).</div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Backup Settings</button>
                            <button type="button" class="btn btn-secondary" onclick="runBackup()"><i class="fas fa-database"></i> Run Backup Now</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE
// ============================================================
var sidebar = document.getElementById('sidebar');
var sidebarToggle = document.getElementById('sidebarToggle');
var sidebarOverlay = document.getElementById('sidebarOverlay');
var dashboardHeader = document.getElementById('dashboardHeader');

function toggleSidebar() {
    sidebar.classList.toggle('open');
    sidebarOverlay.classList.toggle('active');
    updateHeaderPosition();
}

function updateHeaderPosition() {
    if (window.innerWidth > 768) {
        dashboardHeader.style.left = '260px';
    } else if (sidebar.classList.contains('open')) {
        dashboardHeader.style.left = '280px';
    } else {
        dashboardHeader.style.left = '0';
    }
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', toggleSidebar);
}
if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', toggleSidebar);
}

window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
        dashboardHeader.style.left = '260px';
    } else if (!sidebar.classList.contains('open')) {
        dashboardHeader.style.left = '0';
    }
});

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        var dropdownId = this.dataset.dropdown;
        var dropdown = document.getElementById(dropdownId);
        var chevron = this.querySelector('.chevron');
        if (dropdown) {
            dropdown.classList.toggle('open');
            if (chevron) chevron.classList.toggle('open');
        }
    });
});

// ============================================================
// PROFILE DROPDOWN
// ============================================================
var profileBtn = document.getElementById('profileBtn');
var profileMenu = document.getElementById('profileMenu');

if (profileBtn && profileMenu) {
    profileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        profileMenu.classList.toggle('active');
    });
    document.addEventListener('click', function(e) {
        if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
            profileMenu.classList.remove('active');
        }
    });
}

// ============================================================
// SETTINGS TABS
// ============================================================
function switchTab(tab) {
    // Update tabs
    document.querySelectorAll('.settings-tab').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelector('.settings-tab[data-tab="' + tab + '"]').classList.add('active');
    
    // Update panels
    document.querySelectorAll('.settings-panel').forEach(function(el) {
        el.classList.remove('active');
    });
    document.getElementById('panel-' + tab).classList.add('active');
}

// ============================================================
// TEST EMAIL
// ============================================================
function testEmail() {
    var btn = event.target;
    var originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    btn.disabled = true;
    
    fetch('settings-ajax.php?action=test_email', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showToast('success', 'Test email sent successfully!');
        } else {
            showToast('error', 'Failed to send test email: ' + data.message);
        }
    })
    .catch(function() {
        showToast('error', 'An error occurred while sending test email.');
    })
    .finally(function() {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// ============================================================
// RUN BACKUP
// ============================================================
function runBackup() {
    if (!confirm('Run backup now? This may take a few moments.')) return;
    
    var btn = event.target;
    var originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running...';
    btn.disabled = true;
    
    fetch('settings-ajax.php?action=run_backup', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showToast('success', 'Backup completed successfully!');
        } else {
            showToast('error', 'Backup failed: ' + data.message);
        }
    })
    .catch(function() {
        showToast('error', 'An error occurred during backup.');
    })
    .finally(function() {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// ============================================================
// TOAST NOTIFICATIONS
// ============================================================
function showToast(type, message) {
    var container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    var toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + message;
    container.appendChild(toast);
    
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100px)';
        setTimeout(function() {
            toast.remove();
        }, 300);
    }, 4000);
}

// ============================================================
// SEARCH
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        searchTimeout = setTimeout(function() {
            fetch('search.php?q=' + encodeURIComponent(query))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(function(item) {
                                var div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = '<i class="fas ' + (item.icon || 'fa-file') + '"></i><span class="text-truncate">' + (item.label || item.name || '') + '</span><span class="result-type">' + ((item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)) + '</span>';
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = '<div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;"><i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>No results found</div>';
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(function() {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
}
</script>
</body>
</html>