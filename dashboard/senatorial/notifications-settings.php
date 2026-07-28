<?php
// ============================================================
// SENATORIAL COORDINATOR - NOTIFICATION SETTINGS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Coordinator');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET CURRENT SETTINGS
// ============================================================
$settings = [
    'email_notifications' => true,
    'in_app_notifications' => true,
    'election_alerts' => true,
    'result_alerts' => true,
    'incident_alerts' => true,
    'broadcast_alerts' => true,
    'chat_alerts' => true,
    'security_alerts' => true,
    'payment_alerts' => false
];

try {
    $stmt = $db->prepare("
        SELECT `key`, `value` FROM tenant_settings 
        WHERE tenant_id = ? AND `key` LIKE 'notification_%'
    ");
    $stmt->execute([$tenant_id]);
    $db_settings = $stmt->fetchAll();
    
    foreach ($db_settings as $row) {
        $key = str_replace('notification_', '', $row['key']);
        $settings[$key] = (bool)$row['value'];
    }
} catch (Exception $e) {
    error_log("Error fetching notification settings: " . $e->getMessage());
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $in_app_notifications = isset($_POST['in_app_notifications']) ? 1 : 0;
    $election_alerts = isset($_POST['election_alerts']) ? 1 : 0;
    $result_alerts = isset($_POST['result_alerts']) ? 1 : 0;
    $incident_alerts = isset($_POST['incident_alerts']) ? 1 : 0;
    $broadcast_alerts = isset($_POST['broadcast_alerts']) ? 1 : 0;
    $chat_alerts = isset($_POST['chat_alerts']) ? 1 : 0;
    $security_alerts = isset($_POST['security_alerts']) ? 1 : 0;
    $payment_alerts = isset($_POST['payment_alerts']) ? 1 : 0;
    
    try {
        // Update or insert settings
        $settings_data = [
            'notification_email' => $email_notifications,
            'notification_in_app' => $in_app_notifications,
            'notification_election' => $election_alerts,
            'notification_result' => $result_alerts,
            'notification_incident' => $incident_alerts,
            'notification_broadcast' => $broadcast_alerts,
            'notification_chat' => $chat_alerts,
            'notification_security' => $security_alerts,
            'notification_payment' => $payment_alerts
        ];
        
        foreach ($settings_data as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO tenant_settings (tenant_id, `key`, `value`, type) 
                VALUES (?, ?, ?, 'boolean')
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
            ");
            $stmt->execute([$tenant_id, $key, $value]);
        }
        
        logActivity($user_id, 'settings_changed', "Updated notification settings");
        
        $success = 'Notification settings updated successfully!';
        
        // Refresh settings        foreach ($settings_data as $key => $value) {
            $setting_key = str_replace('notification_', '', $key);
            $settings[$setting_key] = (bool)$value;
        }
        
    } catch (Exception $e) {
        $error = 'Error saving settings: ' . $e->getMessage();
        error_log("Notification settings error: " . $e->getMessage());
    }
}

$page_title = 'Notification Settings';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
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

.form-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 28px 32px;
    max-width: 700px;
    margin: 0 auto;
}
.form-container .form-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-container .form-subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gray-100);
}

.setting-group {
    margin-bottom: 20px;
}
.setting-group .group-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-700);
    margin-bottom: 12px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--gray-100);
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-50);
}
.setting-item:last-child {
    border-bottom: none;
}
.setting-item .info .label {
    font-weight: 500;
    font-size: 0.9rem;
}
.setting-item .info .desc {
    font-size: 0.75rem;
    color: var(--gray-400);
}
.setting-item .toggle {
    position: relative;
    width: 48px;
    height: 26px;
    flex-shrink: 0;
    cursor: pointer;
}
.setting-item .toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}
.setting-item .toggle .slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--gray-300);
    transition: .3s;
    border-radius: 26px;
}
.setting-item .toggle .slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: white;
    transition: .3s;
    border-radius: 50%;
}
.setting-item .toggle input:checked + .slider {
    background: var(--primary);
}
.setting-item .toggle input:checked + .slider:before {
    transform: translateX(22px);
}

.btn {
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
.btn-primary {
    background: var(--primary);
    color: white;
}
.btn-primary:hover {
    background: var(--primary-dark);
}
.btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.btn-secondary:hover {
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

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-cog" style="color:var(--primary);margin-right:8px;"></i> 
                    Notification Settings
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="notifications.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Notifications
                </a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <div class="form-title">
                <i class="fas fa-bell"></i> Notification Preferences
            </div>
            <div class="form-subtitle">
                Configure how and when you receive notifications.
            </div>

            <form method="POST" action="">
                <!-- Delivery Channels -->
                <div class="setting-group">
                    <div class="group-title">Delivery Channels</div>
                    <div class="setting-item">
                        <div class="info">
                            <div class="label">Email Notifications</div>
                            <div class="desc">Receive notifications via email</div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="email_notifications" value="1" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="info">
                            <div class="label">In-App Notifications</div>
                            <div class="desc">Receive notifications within the platform</div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="in_app_notifications" value="1" <?php echo $settings['in_app_notifications'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Alert Types -->
                <div class="setting-group">
                    <div class="group-title">Alert Types</div>
                    <div class="setting-item">
                        <div class="info">
                            <div class="label">Election Alerts</div>
                            <div class="desc">Election updates and reminders</div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="election_alerts" value="1" <?php echo $settings['election_alerts'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="info">
                            <div class="label">Result Alerts</div>
                            <div class="desc">Result submissions and verifications</div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="result_alerts" value="1" <?php echo $settings['result_alerts'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="info">
                            <div class="label">Incident Alerts</div>
                            <div class="desc">New and updated incidents</div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="incident_alerts" value="1" <?php echo $settings['incident_alerts'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="info">
                            <div class="label">Broadcast Alerts</div>
                            <div class="desc">Broadcast messages from coordinators</div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="broadcast_alerts" value="1" <?php echo $settings['broadcast_alerts'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="info">
                            <div class="label">Chat Alerts</div>
                            <div class="desc">New chat messages</div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="chat_alerts" value="1" <?php echo $settings['chat_alerts'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="info">
                            <div class="label">Security Alerts</div>
                            <div class="desc">Login attempts and security events</div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="security_alerts" value="1" <?php echo $settings['security_alerts'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="info">
                            <div class="label">Payment Alerts</div>
                            <div class="desc">Subscription and payment notifications</div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="payment_alerts" value="1" <?php echo $settings['payment_alerts'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <a href="notifications.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
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

window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>