<?php
// ============================================================
// NATIONAL COORDINATOR - SYSTEM SETTINGS (FIXED)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only national coordinator can access
if (SessionManager::get('role_level') !== 'national') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// FETCH CURRENT SETTINGS
// ============================================================
$settings = [];
try {
    // KEY is a reserved word, use backticks
    $stmt = $db->prepare("SELECT * FROM system_settings ORDER BY `key` ASC");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array
    $settings_map = [];
    foreach ($settings as $setting) {
        $settings_map[$setting['key']] = $setting;
    }
    $settings = $settings_map;
    
} catch (PDOException $e) {
    error_log("Settings PDO Error: " . $e->getMessage());
} catch (Exception $e) {
    error_log("Settings Error: " . $e->getMessage());
}

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? 'general';
    
    try {
        // Process each setting
        foreach ($_POST as $key => $value) {
            if (in_array($key, ['section', 'submit'])) continue;
            
            // Check if setting exists
            if (isset($settings[$key])) {
                $value = trim($value);
                $type = $settings[$key]['type'];
                
                // Convert value based on type
                if ($type === 'boolean') {
                    $value = ($value === 'true' || $value === '1') ? 'true' : 'false';
                } elseif ($type === 'integer') {
                    $value = intval($value);
                }
                
                // KEY is a reserved word, use backticks
                $stmt = $db->prepare("
                    UPDATE system_settings 
                    SET value = ?, updated_at = NOW() 
                    WHERE `key` = ?
                ");
                $stmt->execute([$value, $key]);
            }
        }
        
        // Log activity
        logActivity($user_id, 'settings_changed', 'Updated system settings');
        
        $success = true;
        $message = 'Settings updated successfully!';
        
        // Refresh settings
        $stmt = $db->prepare("SELECT * FROM system_settings ORDER BY `key` ASC");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings_map = [];
        foreach ($settings as $setting) {
            $settings_map[$setting['key']] = $setting;
        }
        $settings = $settings_map;
        
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        error_log("Settings Update PDO Error: " . $e->getMessage());
    } catch (Exception $e) {
        $error = 'Failed to update settings: ' . $e->getMessage();
        error_log("Settings Update Error: " . $e->getMessage());
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Settings';
$page_subtitle = 'System configuration';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="../national/index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Settings</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-cog" style="color:var(--primary);"></i>
                        System Settings
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-sliders-h"></i> 
                        Configure system-wide settings
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="profile.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="security.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-shield-alt"></i> Security
                    </a>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($message && $success): ?>
            <div style="background:#D1FAE5;color:#065F46;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #A7F3D0;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#FEE2E2;color:#991B1B;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #FECACA;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Settings Form -->
        <form method="POST" action="" style="background:white;border-radius:var(--radius);padding:24px;border:1px solid var(--gray-200);">
            <input type="hidden" name="section" value="general">
            
            <!-- General Settings -->
            <div style="margin-bottom:24px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-globe" style="color:var(--primary);margin-right:6px;"></i>
                    General Settings
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Site Name
                        </label>
                        <input type="text" name="site_name" class="form-control"
                               value="<?php echo htmlspecialchars($settings['site_name']['value'] ?? APP_NAME); ?>"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Site URL
                        </label>
                        <input type="url" name="site_url" class="form-control"
                               value="<?php echo htmlspecialchars($settings['site_url']['value'] ?? APP_URL); ?>"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Contact Email
                        </label>
                        <input type="email" name="contact_email" class="form-control"
                               value="<?php echo htmlspecialchars($settings['contact_email']['value'] ?? ''); ?>"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Contact Phone
                        </label>
                        <input type="tel" name="contact_phone" class="form-control"
                               value="<?php echo htmlspecialchars($settings['contact_phone']['value'] ?? ''); ?>"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Timezone
                        </label>
                        <select name="timezone" class="form-control"
                                style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="Africa/Lagos" <?php echo ($settings['timezone']['value'] ?? 'Africa/Lagos') == 'Africa/Lagos' ? 'selected' : ''; ?>>Africa/Lagos</option>
                            <option value="Africa/Abidjan" <?php echo ($settings['timezone']['value'] ?? '') == 'Africa/Abidjan' ? 'selected' : ''; ?>>Africa/Abidjan</option>
                            <option value="Africa/Cairo" <?php echo ($settings['timezone']['value'] ?? '') == 'Africa/Cairo' ? 'selected' : ''; ?>>Africa/Cairo</option>
                            <option value="Africa/Johannesburg" <?php echo ($settings['timezone']['value'] ?? '') == 'Africa/Johannesburg' ? 'selected' : ''; ?>>Africa/Johannesburg</option>
                            <option value="UTC" <?php echo ($settings['timezone']['value'] ?? '') == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Password Min Length
                        </label>
                        <input type="number" name="password_min_length" class="form-control"
                               value="<?php echo htmlspecialchars($settings['password_min_length']['value'] ?? 8); ?>"
                               min="6" max="20"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Max Login Attempts
                        </label>
                        <input type="number" name="max_login_attempts" class="form-control"
                               value="<?php echo htmlspecialchars($settings['max_login_attempts']['value'] ?? 5); ?>"
                               min="3" max="20"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Session Timeout (seconds)
                        </label>
                        <input type="number" name="session_timeout" class="form-control"
                               value="<?php echo htmlspecialchars($settings['session_timeout']['value'] ?? 3600); ?>"
                               min="600" max="86400"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            OTP Expiry (seconds)
                        </label>
                        <input type="number" name="otp_expiry" class="form-control"
                               value="<?php echo htmlspecialchars($settings['otp_expiry']['value'] ?? 300); ?>"
                               min="60" max="600"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Two-Factor Authentication
                        </label>
                        <select name="two_factor_enabled" class="form-control"
                                style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="true" <?php echo ($settings['two_factor_enabled']['value'] ?? 'true') == 'true' ? 'selected' : ''; ?>>Enabled</option>
                            <option value="false" <?php echo ($settings['two_factor_enabled']['value'] ?? '') == 'false' ? 'selected' : ''; ?>>Disabled</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            CAPTCHA Enabled
                        </label>
                        <select name="captcha_enabled" class="form-control"
                                style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="true" <?php echo ($settings['captcha_enabled']['value'] ?? 'false') == 'true' ? 'selected' : ''; ?>>Enabled</option>
                            <option value="false" <?php echo ($settings['captcha_enabled']['value'] ?? '') == 'false' ? 'selected' : ''; ?>>Disabled</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Email Settings -->
            <div style="margin-bottom:24px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-envelope" style="color:var(--secondary);margin-right:6px;"></i>
                    Email Settings
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            SMTP Host
                        </label>
                        <input type="text" name="smtp_host" class="form-control"
                               value="<?php echo htmlspecialchars($settings['smtp_host']['value'] ?? ''); ?>"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            SMTP Port
                        </label>
                        <input type="number" name="smtp_port" class="form-control"
                               value="<?php echo htmlspecialchars($settings['smtp_port']['value'] ?? 587); ?>"
                               min="1" max="65535"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            SMTP Username
                        </label>
                        <input type="text" name="smtp_username" class="form-control"
                               value="<?php echo htmlspecialchars($settings['smtp_username']['value'] ?? ''); ?>"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            SMTP Password
                        </label>
                        <input type="password" name="smtp_password" class="form-control"
                               value="<?php echo htmlspecialchars($settings['smtp_password']['value'] ?? ''); ?>"
                               placeholder="Enter SMTP password"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            SMTP Encryption
                        </label>
                        <select name="smtp_encryption" class="form-control"
                                style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="tls" <?php echo ($settings['smtp_encryption']['value'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo ($settings['smtp_encryption']['value'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo ($settings['smtp_encryption']['value'] ?? '') == 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Sender Name
                        </label>
                        <input type="text" name="sender_name" class="form-control"
                               value="<?php echo htmlspecialchars($settings['sender_name']['value'] ?? APP_NAME); ?>"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Sender Email
                        </label>
                        <input type="email" name="sender_email" class="form-control"
                               value="<?php echo htmlspecialchars($settings['sender_email']['value'] ?? ''); ?>"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                </div>
            </div>

            <!-- Backup Settings -->
            <div style="margin-bottom:24px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-database" style="color:var(--purple);margin-right:6px;"></i>
                    Backup Settings
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Auto Backup
                        </label>
                        <select name="auto_backup_enabled" class="form-control"
                                style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="true" <?php echo ($settings['auto_backup_enabled']['value'] ?? 'true') == 'true' ? 'selected' : ''; ?>>Enabled</option>
                            <option value="false" <?php echo ($settings['auto_backup_enabled']['value'] ?? '') == 'false' ? 'selected' : ''; ?>>Disabled</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Backup Frequency
                        </label>
                        <select name="backup_frequency" class="form-control"
                                style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="daily" <?php echo ($settings['backup_frequency']['value'] ?? 'daily') == 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($settings['backup_frequency']['value'] ?? '') == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($settings['backup_frequency']['value'] ?? '') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Backup Retention (days)
                        </label>
                        <input type="number" name="backup_retention" class="form-control"
                               value="<?php echo htmlspecialchars($settings['backup_retention']['value'] ?? 30); ?>"
                               min="1" max="365"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Backup Time
                        </label>
                        <input type="time" name="backup_time" class="form-control"
                               value="<?php echo htmlspecialchars($settings['backup_time']['value'] ?? '02:00'); ?>"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                </div>
            </div>

            <!-- File Settings -->
            <div style="margin-bottom:24px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-file" style="color:var(--warning);margin-right:6px;"></i>
                    File Settings
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Max Upload Size (MB)
                        </label>
                        <input type="number" name="max_upload_size" class="form-control"
                               value="<?php echo htmlspecialchars($settings['max_upload_size']['value'] ?? 10); ?>"
                               min="1" max="100"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                            Allowed File Types
                        </label>
                        <input type="text" name="allowed_file_types" class="form-control"
                               value="<?php echo htmlspecialchars($settings['allowed_file_types']['value'] ?? 'jpg,jpeg,png,gif,svg,pdf,doc,docx,xls,xlsx,csv'); ?>"
                               placeholder="jpg,png,pdf"
                               style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div style="display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
                <button type="submit" class="btn-primary" style="padding:10px 32px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-save"></i> Save Settings
                </button>
                <button type="reset" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>
</main>

<style>
.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary:hover {
    background: var(--gray-200);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr;gap:16px;"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
// ============================================================
// SIDEBAR TOGGLE, DROPDOWNS, PROFILE, SEARCH
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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
</script>
</body>
</html>