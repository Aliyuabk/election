<?php
// ============================================================
// SETTINGS - CLIENT ADMIN
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

// Check role - only client_admin can access this page
if (SessionManager::get('role_level') !== 'client_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// Get user info
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
$tenant_id = SessionManager::get('tenant_id');

// ============================================================
// ENSURE TENANT SETTINGS TABLE EXISTS
// ============================================================
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS tenant_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            `key` VARCHAR(100) NOT NULL,
            value TEXT NOT NULL,
            type ENUM('string','integer','boolean','json','array') DEFAULT 'string',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_tenant_settings (tenant_id, `key`)
        )
    ");
} catch (Exception $e) {
    // Table exists
}

// ============================================================
// FETCH TENANT SETTINGS
// ============================================================
$settings = [];
try {
    $stmt = $db->prepare("SELECT * FROM tenant_settings WHERE tenant_id = ? ORDER BY `key`");
    $stmt->execute([$tenant_id]);
    $settings = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// Convert to associative array
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
            case 'update_organization':
                $org_name = trim($_POST['org_name'] ?? '');
                $org_type = $_POST['org_type'] ?? 'political_party';
                $contact_email = trim($_POST['contact_email'] ?? '');
                $contact_phone = trim($_POST['contact_phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $timezone = $_POST['timezone'] ?? 'Africa/Lagos';
                
                if (empty($org_name)) {
                    throw new Exception('Organization name is required.');
                }
                
                $updates = [
                    'org_name' => $org_name,
                    'org_type' => $org_type,
                    'contact_email' => $contact_email,
                    'contact_phone' => $contact_phone,
                    'address' => $address,
                    'timezone' => $timezone
                ];
                
                foreach ($updates as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO tenant_settings (tenant_id, `key`, value, type) 
                        VALUES (?, ?, ?, 'string') 
                        ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()
                    ");
                    $stmt->execute([$tenant_id, $key, $value, $value]);
                }
                
                logActivity($user_id, 'settings_updated', "Updated organization settings");
                $success = "Organization settings updated successfully!";
                break;
                
            case 'update_security':
                $two_factor_enabled = isset($_POST['two_factor_enabled']) ? 'true' : 'false';
                $session_timeout = (int)($_POST['session_timeout'] ?? 3600);
                $max_login_attempts = (int)($_POST['max_login_attempts'] ?? 5);
                $lockout_duration = (int)($_POST['lockout_duration'] ?? 15);
                
                $updates = [
                    'two_factor_enabled' => $two_factor_enabled,
                    'session_timeout' => $session_timeout,
                    'max_login_attempts' => $max_login_attempts,
                    'lockout_duration' => $lockout_duration
                ];
                
                foreach ($updates as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO tenant_settings (tenant_id, `key`, value, type) 
                        VALUES (?, ?, ?, 'string') 
                        ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()
                    ");
                    $stmt->execute([$tenant_id, $key, $value, $value]);
                }
                
                logActivity($user_id, 'security_settings_updated', "Updated security settings");
                $success = "Security settings updated successfully!";
                break;
                
            case 'update_notifications':
                $email_notifications = isset($_POST['email_notifications']) ? 'true' : 'false';
                $broadcast_notifications = isset($_POST['broadcast_notifications']) ? 'true' : 'false';
                $result_notifications = isset($_POST['result_notifications']) ? 'true' : 'false';
                $incident_notifications = isset($_POST['incident_notifications']) ? 'true' : 'false';
                
                $updates = [
                    'email_notifications' => $email_notifications,
                    'broadcast_notifications' => $broadcast_notifications,
                    'result_notifications' => $result_notifications,
                    'incident_notifications' => $incident_notifications
                ];
                
                foreach ($updates as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO tenant_settings (tenant_id, `key`, value, type) 
                        VALUES (?, ?, ?, 'string') 
                        ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()
                    ");
                    $stmt->execute([$tenant_id, $key, $value, $value]);
                }
                
                logActivity($user_id, 'notification_settings_updated', "Updated notification settings");
                $success = "Notification settings updated successfully!";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    
    // Refresh settings
    $stmt = $db->prepare("SELECT * FROM tenant_settings WHERE tenant_id = ? ORDER BY `key`");
    $stmt->execute([$tenant_id]);
    $settings = $stmt->fetchAll();
    $settings_map = [];
    foreach ($settings as $setting) {
        $settings_map[$setting['key']] = $setting['value'];
    }
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       SETTINGS - CLIENT ADMIN STYLES
       ============================================================ */
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .page-header h2 {
        font-size: 1.3rem;
        font-weight: 700;
    }
    .page-header h2 small {
        font-size: 0.8rem;
        font-weight: 400;
        color: var(--gray-500);
        display: block;
        margin-top: 2px;
    }
    
    .btn-primary {
        padding: 8px 18px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .btn-outline {
        padding: 8px 16px;
        background: transparent;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    
    .settings-container {
        max-width: 1000px;
        margin: 0 auto;
    }
    
    .settings-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        background: white;
        padding: 12px 16px;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow);
    }
    .settings-tab {
        padding: 8px 18px;
        border-radius: 8px;
        border: 1px solid transparent;
        background: transparent;
        color: var(--gray-600);
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .settings-tab:hover {
        background: var(--gray-50);
        border-color: var(--gray-200);
    }
    .settings-tab.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .settings-tab i {
        font-size: 0.9rem;
    }
    
    .settings-panel {
        display: none;
    }
    .settings-panel.active {
        display: block;
    }
    
    .settings-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 28px 32px;
        box-shadow: var(--shadow);
    }
    .settings-card .card-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .settings-card .card-title i {
        color: var(--primary);
    }
    .settings-card .card-desc {
        color: var(--gray-500);
        font-size: 0.85rem;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--gray-100);
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px 24px;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    .form-group label {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--gray-700);
    }
    .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .form-group .help-text {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px 14px;
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .form-group textarea {
        resize: vertical;
        min-height: 60px;
    }
    .form-group .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 6px;
    }
    .form-group .checkbox-group input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: var(--primary);
        cursor: pointer;
        flex-shrink: 0;
    }
    .form-group .checkbox-group label {
        font-weight: 400;
        cursor: pointer;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--gray-200);
        flex-wrap: wrap;
    }
    .form-actions .btn {
        padding: 10px 28px;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .form-actions .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .form-actions .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    .alert {
        padding: 14px 18px;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 16px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        border: 1px solid transparent;
    }
    .alert i {
        margin-top: 2px;
        font-size: 1.1rem;
    }
    .alert-success {
        background: #ECFDF5;
        color: #065F46;
        border-color: #A7F3D0;
    }
    .alert-error {
        background: #FEF2F2;
        color: #DC2626;
        border-color: #FECACA;
    }
    
    @media (max-width: 768px) {
        .settings-tabs {
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .settings-tab {
            white-space: nowrap;
            font-size: 0.75rem;
            padding: 6px 14px;
        }
        .form-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .settings-card {
            padding: 20px;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            justify-content: center;
            width: 100%;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    @media (max-width: 480px) {
        .settings-card {
            padding: 16px;
        }
        .form-group input,
        .form-group select {
            padding: 8px 12px;
            font-size: 0.8rem;
        }
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
                        <i class="fas fa-cog" style="color:var(--primary);margin-right:8px;"></i> Settings
                        <small>Manage your organization settings and preferences</small>
                    </h2>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="profile.php" class="btn-outline">
                        <i class="fas fa-building"></i> Organization Profile
                    </a>
                    <a href="index.php" class="btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="settings-tab active" data-tab="organization" onclick="switchTab('organization')">
                    <i class="fas fa-building"></i> Organization
                </button>
                <button class="settings-tab" data-tab="security" onclick="switchTab('security')">
                    <i class="fas fa-shield-alt"></i> Security
                </button>
                <button class="settings-tab" data-tab="notifications" onclick="switchTab('notifications')">
                    <i class="fas fa-bell"></i> Notifications
                </button>
            </div>

            <!-- Organization Settings -->
            <div class="settings-panel active" id="panel-organization">
                <div class="settings-card">
                    <div class="card-title">
                        <i class="fas fa-building"></i> Organization Settings
                    </div>
                    <div class="card-desc">
                        Configure your organization details and preferences.
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_organization">
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Organization Name <span class="required">*</span></label>
                                <input type="text" name="org_name" value="<?php echo htmlspecialchars($settings_map['org_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Organization Type</label>
                                <select name="org_type">
                                    <option value="political_party" <?php echo ($settings_map['org_type'] ?? '') === 'political_party' ? 'selected' : ''; ?>>Political Party</option>
                                    <option value="candidate" <?php echo ($settings_map['org_type'] ?? '') === 'candidate' ? 'selected' : ''; ?>>Candidate</option>
                                    <option value="ngo" <?php echo ($settings_map['org_type'] ?? '') === 'ngo' ? 'selected' : ''; ?>>NGO</option>
                                    <option value="observer_group" <?php echo ($settings_map['org_type'] ?? '') === 'observer_group' ? 'selected' : ''; ?>>Observer Group</option>
                                    <option value="cso" <?php echo ($settings_map['org_type'] ?? '') === 'cso' ? 'selected' : ''; ?>>CSO</option>
                                    <option value="research_institution" <?php echo ($settings_map['org_type'] ?? '') === 'research_institution' ? 'selected' : ''; ?>>Research Institution</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Contact Email</label>
                                <input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings_map['contact_email'] ?? ''); ?>" placeholder="contact@organization.ng">
                            </div>
                            
                            <div class="form-group">
                                <label>Contact Phone</label>
                                <input type="tel" name="contact_phone" value="<?php echo htmlspecialchars($settings_map['contact_phone'] ?? ''); ?>" placeholder="+234 800 555 5555">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Address</label>
                                <textarea name="address" placeholder="Organization address"><?php echo htmlspecialchars($settings_map['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Timezone</label>
                                <select name="timezone">
                                    <option value="Africa/Lagos" <?php echo ($settings_map['timezone'] ?? '') === 'Africa/Lagos' ? 'selected' : ''; ?>>Africa/Lagos (West Africa Time)</option>
                                    <option value="Africa/Cairo" <?php echo ($settings_map['timezone'] ?? '') === 'Africa/Cairo' ? 'selected' : ''; ?>>Africa/Cairo</option>
                                    <option value="Africa/Johannesburg" <?php echo ($settings_map['timezone'] ?? '') === 'Africa/Johannesburg' ? 'selected' : ''; ?>>Africa/Johannesburg</option>
                                    <option value="UTC" <?php echo ($settings_map['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Organization Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="settings-panel" id="panel-security">
                <div class="settings-card">
                    <div class="card-title">
                        <i class="fas fa-shield-alt"></i> Security Settings
                    </div>
                    <div class="card-desc">
                        Configure security settings for your organization.
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_security">
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="two_factor_enabled" id="twoFactor" value="1" <?php echo ($settings_map['two_factor_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label for="twoFactor">Enable Two-Factor Authentication (2FA) for all users</label>
                                </div>
                                <div class="help-text">Users will be required to verify their identity using a code sent to their email.</div>
                            </div>
                            
                            <div class="form-group">
                                <label>Session Timeout (seconds)</label>
                                <input type="number" name="session_timeout" value="<?php echo htmlspecialchars($settings_map['session_timeout'] ?? 3600); ?>" min="60">
                                <div class="help-text">1 hour = 3600 seconds</div>
                            </div>
                            
                            <div class="form-group">
                                <label>Max Login Attempts</label>
                                <input type="number" name="max_login_attempts" value="<?php echo htmlspecialchars($settings_map['max_login_attempts'] ?? 5); ?>" min="1" max="20">
                                <div class="help-text">Number of failed attempts before lockout.</div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Lockout Duration (minutes)</label>
                                <input type="number" name="lockout_duration" value="<?php echo htmlspecialchars($settings_map['lockout_duration'] ?? 15); ?>" min="1">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Security Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="settings-panel" id="panel-notifications">
                <div class="settings-card">
                    <div class="card-title">
                        <i class="fas fa-bell"></i> Notification Settings
                    </div>
                    <div class="card-desc">
                        Configure which notifications you want to receive.
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_notifications">
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="email_notifications" id="emailNotif" value="1" <?php echo ($settings_map['email_notifications'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label for="emailNotif">Email Notifications</label>
                                </div>
                                <div class="help-text">Receive notifications via email.</div>
                            </div>
                            
                            <div class="form-group full-width">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="broadcast_notifications" id="broadcastNotif" value="1" <?php echo ($settings_map['broadcast_notifications'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label for="broadcastNotif">Broadcast Notifications</label>
                                </div>
                                <div class="help-text">Receive notifications when broadcasts are sent.</div>
                            </div>
                            
                            <div class="form-group full-width">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="result_notifications" id="resultNotif" value="1" <?php echo ($settings_map['result_notifications'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label for="resultNotif">Result Notifications</label>
                                </div>
                                <div class="help-text">Receive notifications when new results are submitted.</div>
                            </div>
                            
                            <div class="form-group full-width">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="incident_notifications" id="incidentNotif" value="1" <?php echo ($settings_map['incident_notifications'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label for="incidentNotif">Incident Notifications</label>
                                </div>
                                <div class="help-text">Receive notifications when incidents are reported.</div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Notification Settings
                            </button>
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
    document.querySelectorAll('.settings-tab').forEach(function(el) {
        el.classList.remove('active');
        if (el.dataset.tab === tab) {
            el.classList.add('active');
        }
    });
    
    document.querySelectorAll('.settings-panel').forEach(function(el) {
        el.classList.remove('active');
    });
    document.getElementById('panel-' + tab).classList.add('active');
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