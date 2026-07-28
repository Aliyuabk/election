<?php
// ============================================================
// SENATORIAL COORDINATOR - SECURITY SETTINGS
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
$db = getDB();

// Get user data
$user = null;
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching user: " . $e->getMessage());
}

if (!$user) {
    header('Location: profile.php');
    exit();
}

$error = '';
$success = '';

// Handle 2FA toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'toggle_2fa') {
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        try {
            $stmt = $db->prepare("UPDATE users SET two_factor_enabled = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$enabled, $user_id]);
            
            $success = $enabled ? 'Two-factor authentication enabled.' : 'Two-factor authentication disabled.';
            
            logActivity($user_id, $enabled ? '2fa_enabled' : '2fa_disabled', 
                $enabled ? '2FA enabled' : '2FA disabled');
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Error toggling 2FA: " . $e->getMessage());
            $error = 'Failed to update 2FA setting.';
        }
    }
    
    if ($action === 'revoke_sessions') {
        try {
            $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ? AND token != ?");
            $stmt->execute([$user_id, session_id()]);
            
            $success = 'All other sessions have been revoked.';
            logActivity($user_id, 'session_revoked', 'Revoked all other sessions');
            
        } catch (Exception $e) {
            error_log("Error revoking sessions: " . $e->getMessage());
            $error = 'Failed to revoke sessions.';
        }
    }
}

// Get active sessions
$sessions = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM user_sessions 
        WHERE user_id = ? AND is_active = 1 
        ORDER BY last_activity_at DESC
    ");
    $stmt->execute([$user_id]);
    $sessions = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching sessions: " . $e->getMessage());
}

// Get recent security events
$security_events = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM security_events 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $security_events = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching security events: " . $e->getMessage());
}

$page_title = 'Security Settings';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.security-container {
    max-width: 900px;
    margin: 0 auto;
}
.security-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 30px;
    margin-bottom: 24px;
}
.security-header h2 {
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0;
}
.security-header p {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin: 4px 0 0 0;
}
.security-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    margin-bottom: 20px;
}
.security-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.security-card .card-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.security-card .card-header h3 i {
    color: var(--primary);
}
.security-card .card-header .status-badge {
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 600;
}
.status-badge.enabled { background: #D1FAE5; color: #059669; }
.status-badge.disabled { background: #FEF3C7; color: #D97706; }
.status-badge.active { background: #DBEAFE; color: #2563EB; }

.security-card .card-description {
    color: var(--gray-500);
    font-size: 0.8rem;
    margin-bottom: 16px;
}
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
}
.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--gray-300);
    transition: var(--transition);
    border-radius: 26px;
}
.toggle-slider:before {
    content: "";
    position: absolute;
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: white;
    transition: var(--transition);
    border-radius: 50%;
}
.toggle-switch input:checked + .toggle-slider {
    background: var(--primary);
}
.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(22px);
}
.toggle-label {
    font-size: 0.85rem;
    color: var(--gray-700);
}
.toggle-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.session-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--gray-100);
}
.session-item:last-child {
    border-bottom: none;
}
.session-item .session-info .device {
    font-weight: 500;
    font-size: 0.85rem;
}
.session-item .session-info .details {
    font-size: 0.75rem;
    color: var(--gray-400);
}
.session-item .session-info .current {
    display: inline-block;
    font-size: 0.65rem;
    background: #D1FAE5;
    color: #059669;
    padding: 1px 10px;
    border-radius: 12px;
    margin-left: 8px;
}
.session-item .session-badge {
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 20px;
    background: var(--gray-100);
    color: var(--gray-600);
}
.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-primary {
    background: var(--primary);
    color: white;
}
.btn-primary:hover {
    background: #1D4ED8;
}
.btn-danger {
    background: #EF4444;
    color: white;
}
.btn-danger:hover {
    background: #DC2626;
}
.btn-secondary {
    background: var(--gray-200);
    color: var(--gray-700);
}
.btn-secondary:hover {
    background: var(--gray-300);
}
.btn-warning {
    background: #F59E0B;
    color: white;
}
.btn-warning:hover {
    background: #D97706;
}

.security-event {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-100);
}
.security-event:last-child {
    border-bottom: none;
}
.security-event .event-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    flex-shrink: 0;
}
.security-event .event-icon.success { background: #D1FAE5; color: #059669; }
.security-event .event-icon.danger { background: #FEE2E2; color: #DC2626; }
.security-event .event-icon.warning { background: #FEF3C7; color: #D97706; }
.security-event .event-info {
    flex: 1;
}
.security-event .event-info .type {
    font-size: 0.85rem;
    font-weight: 500;
}
.security-event .event-info .desc {
    font-size: 0.75rem;
    color: var(--gray-500);
}
.security-event .event-time {
    font-size: 0.7rem;
    color: var(--gray-400);
}

.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 16px;
}

@media (max-width: 640px) {
    .security-card .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    .session-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="security-container">
            <div class="security-header">
                <h2><i class="fas fa-shield-alt"></i> Security Settings</h2>
                <p>Manage your account security and authentication preferences</p>
            </div>

            <?php if ($error): ?>
                <div style="background:#FEF2F2;border:1px solid #FEE2E2;color:#DC2626;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="background:#D1FAE5;border:1px solid #A7F3D0;color:#059669;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Two-Factor Authentication -->
            <div class="security-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-mobile-alt"></i> Two-Factor Authentication
                        <span class="status-badge <?php echo ($user['two_factor_enabled'] ?? 0) ? 'enabled' : 'disabled'; ?>">
                            <?php echo ($user['two_factor_enabled'] ?? 0) ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </h3>
                </div>
                <div class="card-description">
                    Add an extra layer of security to your account by requiring a verification code 
                    in addition to your password when logging in.
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="toggle_2fa">
                    <div class="toggle-wrapper">
                        <label class="toggle-switch">
                            <input type="checkbox" name="enabled" value="1" 
                                <?php echo ($user['two_factor_enabled'] ?? 0) ? 'checked' : ''; ?>
                                onchange="this.form.submit()">
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label">
                            <?php echo ($user['two_factor_enabled'] ?? 0) ? '2FA is enabled' : 'Enable 2FA'; ?>
                        </span>
                    </div>
                </form>
            </div>

            <!-- Active Sessions -->
            <div class="security-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-desktop"></i> Active Sessions
                        <span class="status-badge active">
                            <?php echo count($sessions); ?> active
                        </span>
                    </h3>
                </div>
                <div class="card-description">
                    These are the devices and browsers currently logged into your account.
                </div>
                <?php if (count($sessions) > 0): ?>
                    <?php foreach ($sessions as $session): ?>
                        <div class="session-item">
                            <div class="session-info">
                                <div class="device">
                                    <?php echo htmlspecialchars($session['device_type'] ?? 'Unknown Device'); ?>
                                    <?php if ($session['token'] === session_id()): ?>
                                        <span class="current">Current Session</span>
                                    <?php endif; ?>
                                </div>
                                <div class="details">
                                    <?php if (!empty($session['ip_address'])): ?>
                                        IP: <?php echo htmlspecialchars($session['ip_address']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($session['last_activity_at'])): ?>
                                        • Last active: <?php echo date('M d, Y g:i A', strtotime($session['last_activity_at'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="session-badge">
                                <?php echo date('M d', strtotime($session['created_at'] ?? 'now')); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($sessions) > 1): ?>
                        <form method="POST" style="margin-top:16px;">
                            <input type="hidden" name="action" value="revoke_sessions">
                            <button type="submit" class="btn btn-warning" 
                                onclick="return confirm('This will log out all other devices. Continue?')">
                                <i class="fas fa-sign-out-alt"></i> Revoke All Other Sessions
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color:var(--gray-500);padding:12px 0;">No active sessions found.</p>
                <?php endif; ?>
            </div>

            <!-- Recent Security Events -->
            <div class="security-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-history"></i> Recent Security Events
                    </h3>
                </div>
                <div class="card-description">
                    Recent security-related activities on your account.
                </div>
                <?php if (count($security_events) > 0): ?>
                    <?php foreach ($security_events as $event): ?>
                        <?php 
                            $icon_class = 'success';
                            $icon_icon = 'fa-check-circle';
                            
                            if (strpos($event['event_type'] ?? '', 'failed') !== false || 
                                strpos($event['event_type'] ?? '', 'unauthorized') !== false) {
                                $icon_class = 'danger';
                                $icon_icon = 'fa-times-circle';
                            } elseif (strpos($event['event_type'] ?? '', 'alert') !== false || 
                                      ($event['risk_score'] ?? 0) > 30) {
                                $icon_class = 'warning';
                                $icon_icon = 'fa-exclamation-triangle';
                            }
                        ?>
                        <div class="security-event">
                            <div class="event-icon <?php echo $icon_class; ?>">
                                <i class="fas <?php echo $icon_icon; ?>"></i>
                            </div>
                            <div class="event-info">
                                <div class="type">
                                    <?php echo ucfirst(str_replace('_', ' ', $event['event_type'] ?? 'Event')); ?>
                                    <?php if (($event['risk_score'] ?? 0) > 0): ?>
                                        <span style="font-size:0.7rem;color:var(--gray-400);">
                                            (Risk: <?php echo $event['risk_score']; ?>%)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="desc">
                                    <?php echo htmlspecialchars($event['description'] ?? ''); ?>
                                </div>
                            </div>
                            <div class="event-time">
                                <?php echo date('M d, g:i A', strtotime($event['created_at'] ?? 'now')); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--gray-500);padding:12px 0;">No security events recorded.</p>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="security-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-bolt"></i> Quick Security Actions
                    </h3>
                </div>
                <div class="btn-group">
                    <a href="change-password.php" class="btn btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                    <a href="profile-activity.php" class="btn btn-secondary">
                        <i class="fas fa-history"></i> View Activity Log
                    </a>
                    <a href="profile-edit.php" class="btn btn-secondary">
                        <i class="fas fa-edit"></i> Update Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Sidebar toggle functions
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