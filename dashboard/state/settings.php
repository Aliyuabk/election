<?php
// ============================================================
// STATE COORDINATOR - SETTINGS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get user settings
$settings = [];
$message = '';
$error = '';

try {
    // Get user settings from tenant_settings or user preferences
    $stmt = $db->prepare("SELECT * FROM tenant_settings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $settings_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settings_list as $s) {
        $settings[$s['key']] = $s['value'];
    }
} catch (Exception $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'profile') {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            if (empty($first_name) || empty($last_name)) {
                $error = 'Name fields are required.';
            } else {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, phone = ?, email = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$first_name, $last_name, $phone, $email, $user_id, $tenant_id]);
                
                // Update session
                SessionManager::set('user_name', $first_name . ' ' . $last_name);
                SessionManager::set('user_email', $email);
                
                $message = 'Profile updated successfully!';
            }
        } elseif ($action === 'password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Get current user password hash
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!verifyPassword($current_password, $user['password_hash'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($new_password) < 8) {
                $error = 'New password must be at least 8 characters.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match.';
            } else {
                $new_hash = hashPassword($new_password);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$new_hash, $user_id]);
                
                // Log activity
                logActivity($user_id, 'password_change', 'Password changed successfully');
                
                $message = 'Password updated successfully!';
            }
        } elseif ($action === 'preferences') {
            $notification_email = isset($_POST['notification_email']) ? 1 : 0;
            $notification_inapp = isset($_POST['notification_inapp']) ? 1 : 0;
            $language = $_POST['language'] ?? 'en';
            $timezone = $_POST['timezone'] ?? 'Africa/Lagos';
            
            // Save preferences - use tenant_settings or a user_preferences table
            $preferences = [
                'notification_email' => $notification_email,
                'notification_inapp' => $notification_inapp,
                'language' => $language,
                'timezone' => $timezone
            ];
            
            // Update each setting
            foreach ($preferences as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO tenant_settings (tenant_id, `key`, value, type) 
                    VALUES (?, ?, ?, 'string')
                    ON DUPLICATE KEY UPDATE value = VALUES(value)
                ");
                $stmt->execute([$tenant_id, 'user_' . $key, $value]);
            }
            
            $message = 'Preferences updated successfully!';
        } elseif ($action === 'security') {
            $two_factor = isset($_POST['two_factor']) ? 1 : 0;
            $session_timeout = (int)($_POST['session_timeout'] ?? 3600);
            
            $stmt = $db->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
            $stmt->execute([$two_factor, $user_id]);
            
            $stmt = $db->prepare("
                INSERT INTO tenant_settings (tenant_id, `key`, value, type) 
                VALUES (?, 'session_timeout', ?, 'integer')
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
            $stmt->execute([$tenant_id, $session_timeout]);
            
            $message = 'Security settings updated successfully!';
        }
    } catch (Exception $e) {
        $error = 'Failed to update settings: ' . $e->getMessage();
        error_log("Settings update error: " . $e->getMessage());
    }
}

// Get user data for profile
$user_data = null;
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

$page_title = 'Settings';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.settings-container {
    max-width: 800px;
    margin: 0 auto;
}

.settings-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.settings-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.settings-card .card-title i {
    color: var(--primary);
    margin-right: 6px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group label .required {
    color: #EF4444;
    margin-left: 2px;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="tel"],
.form-group input[type="password"],
.form-group input[type="number"],
.form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
    background: white;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.form-group .help-text {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.form-group .checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group .checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
}

.form-group .checkbox-group label {
    font-weight: 400;
    font-size: 0.82rem;
    color: var(--gray-700);
    cursor: pointer;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
}

.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}

.alert-error {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

.alert i {
    margin-right: 6px;
}

.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-group button {
    padding: 10px 28px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-group .btn-save {
    background: var(--primary);
    color: white;
}

.btn-group .btn-save:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-group .btn-cancel {
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    padding: 10px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-group .btn-cancel:hover {
    background: var(--gray-200);
}

.tab-nav {
    display: flex;
    gap: 4px;
    border-bottom: 1px solid var(--gray-200);
    margin-bottom: 20px;
    padding-bottom: 0;
}

.tab-nav button {
    padding: 10px 20px;
    border: none;
    background: none;
    font-weight: 500;
    font-size: 0.82rem;
    color: var(--gray-500);
    cursor: pointer;
    transition: var(--transition);
    border-bottom: 2px solid transparent;
    font-family: 'Inter', sans-serif;
}

.tab-nav button:hover {
    color: var(--gray-700);
}

.tab-nav button.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

@media (max-width: 768px) {
    .settings-card {
        padding: 16px 18px;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .tab-nav {
        overflow-x: auto;
    }
    .tab-nav button {
        padding: 8px 14px;
        font-size: 0.75rem;
        white-space: nowrap;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="settings-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-cog"></i> Settings</h1>
                    <p class="subtitle">
                        <i class="fas fa-flag"></i> 
                        <?php echo htmlspecialchars($state_name); ?> State - Manage Your Settings
                    </p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="tab-nav">
                <button class="active" data-tab="profile" onclick="switchTab('profile')">
                    <i class="fas fa-user"></i> Profile
                </button>
                <button data-tab="password" onclick="switchTab('password')">
                    <i class="fas fa-key"></i> Password
                </button>
                <button data-tab="preferences" onclick="switchTab('preferences')">
                    <i class="fas fa-sliders-h"></i> Preferences
                </button>
                <button data-tab="security" onclick="switchTab('security')">
                    <i class="fas fa-shield-alt"></i> Security
                </button>
            </div>

            <!-- Profile Tab -->
            <div class="tab-content active" id="tab-profile">
                <div class="settings-card">
                    <div class="card-title"><i class="fas fa-user"></i> Profile Information</div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="profile" />
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" required value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" />
                            </div>
                            <div class="form-group">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" required value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" required value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" />
                        </div>

                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" />
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Tab -->
            <div class="tab-content" id="tab-password">
                <div class="settings-card">
                    <div class="card-title"><i class="fas fa-key"></i> Change Password</div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="password" />
                        
                        <div class="form-group">
                            <label>Current Password <span class="required">*</span></label>
                            <input type="password" name="current_password" required />
                        </div>

                        <div class="form-group">
                            <label>New Password <span class="required">*</span></label>
                            <input type="password" name="new_password" required minlength="8" />
                            <div class="help-text">Minimum 8 characters</div>
                        </div>

                        <div class="form-group">
                            <label>Confirm New Password <span class="required">*</span></label>
                            <input type="password" name="confirm_password" required minlength="8" />
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Preferences Tab -->
            <div class="tab-content" id="tab-preferences">
                <div class="settings-card">
                    <div class="card-title"><i class="fas fa-sliders-h"></i> Preferences</div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="preferences" />
                        
                        <div class="form-group">
                            <label>Language</label>
                            <select name="language">
                                <option value="en" <?php echo ($settings['user_language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                <option value="fr" <?php echo ($settings['user_language'] ?? '') === 'fr' ? 'selected' : ''; ?>>French</option>
                                <option value="pt" <?php echo ($settings['user_language'] ?? '') === 'pt' ? 'selected' : ''; ?>>Portuguese</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Timezone</label>
                            <select name="timezone">
                                <option value="Africa/Lagos" <?php echo ($settings['user_timezone'] ?? 'Africa/Lagos') === 'Africa/Lagos' ? 'selected' : ''; ?>>Africa/Lagos (WAT)</option>
                                <option value="Africa/Abidjan" <?php echo ($settings['user_timezone'] ?? '') === 'Africa/Abidjan' ? 'selected' : ''; ?>>Africa/Abidjan (GMT)</option>
                                <option value="Africa/Cairo" <?php echo ($settings['user_timezone'] ?? '') === 'Africa/Cairo' ? 'selected' : ''; ?>>Africa/Cairo (EET)</option>
                                <option value="Africa/Johannesburg" <?php echo ($settings['user_timezone'] ?? '') === 'Africa/Johannesburg' ? 'selected' : ''; ?>>Africa/Johannesburg (SAST)</option>
                                <option value="UTC" <?php echo ($settings['user_timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Notifications</label>
                            <div class="checkbox-group">
                                <input type="checkbox" name="notification_email" id="notif_email" <?php echo ($settings['user_notification_email'] ?? '1') == 1 ? 'checked' : ''; ?> />
                                <label for="notif_email">Email Notifications</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="notification_inapp" id="notif_inapp" <?php echo ($settings['user_notification_inapp'] ?? '1') == 1 ? 'checked' : ''; ?> />
                                <label for="notif_inapp">In-App Notifications</label>
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Tab -->
            <div class="tab-content" id="tab-security">
                <div class="settings-card">
                    <div class="card-title"><i class="fas fa-shield-alt"></i> Security Settings</div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="security" />
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="two_factor" id="two_factor" <?php echo ($user_data['two_factor_enabled'] ?? 0) == 1 ? 'checked' : ''; ?> />
                                <label for="two_factor">Enable Two-Factor Authentication (2FA)</label>
                            </div>
                            <div class="help-text">Adds an extra layer of security to your account</div>
                        </div>

                        <div class="form-group">
                            <label>Session Timeout (seconds)</label>
                            <input type="number" name="session_timeout" value="<?php echo htmlspecialchars($settings['session_timeout'] ?? 3600); ?>" min="300" max="86400" />
                            <div class="help-text">How long before your session expires (300 - 86400 seconds)</div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Save Security Settings
                            </button>
                        </div>
                    </form>
                </div>

                <div class="settings-card">
                    <div class="card-title"><i class="fas fa-history"></i> Session Management</div>
                    
                    <div style="padding:10px 0;">
                        <p style="font-size:0.82rem;color:var(--gray-600);">
                            <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                            Active sessions: <strong><?php echo count(getTrustedDevices($user_id)); ?></strong>
                        </p>
                        <a href="../../auth/logout-all.php" class="btn-save" style="padding:8px 20px;font-size:0.78rem;background:#EF4444;color:white;text-decoration:none;border-radius:8px;display:inline-block;">
                            <i class="fas fa-sign-out-alt"></i> Logout All Devices
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(function(tab) {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-nav button').forEach(function(btn) {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    var tab = document.getElementById('tab-' + tabName);
    if (tab) tab.classList.add('active');
    
    // Activate button
    var btn = document.querySelector('.tab-nav button[data-tab="' + tabName + '"]');
    if (btn) btn.classList.add('active');
}

// Same sidebar scripts as index.php
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