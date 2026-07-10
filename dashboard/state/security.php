<?php
// ============================================================
// STATE COORDINATOR - SECURITY SETTINGS
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

// Get user data
$user_data = null;
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$user_id, $tenant_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// Get security events
$security_events = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM security_events 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $security_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching security events: " . $e->getMessage());
}

// Get login history
$login_history = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM login_attempts 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $login_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching login history: " . $e->getMessage());
}

// Get active sessions
$active_sessions = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM user_sessions 
        WHERE user_id = ? AND is_active = 1 
        ORDER BY last_activity_at DESC
    ");
    $stmt->execute([$user_id]);
    $active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching active sessions: " . $e->getMessage());
}

// Handle security actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_2fa') {
        $enabled = isset($_POST['two_factor_enabled']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
            $stmt->execute([$enabled, $user_id]);
            
            logActivity($user_id, $enabled ? '2fa_enabled' : '2fa_disabled', 
                $enabled ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled');
            
            $message = $enabled ? '2FA enabled successfully!' : '2FA disabled successfully!';
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'Failed to update 2FA settings: ' . $e->getMessage();
        }
    } elseif ($action === 'revoke_session') {
        $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
        
        if ($session_id > 0) {
            try {
                $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE id = ? AND user_id = ?");
                $stmt->execute([$session_id, $user_id]);
                
                logActivity($user_id, 'session_revoked', "Revoked session ID: $session_id");
                $message = 'Session revoked successfully!';
                
                // Refresh sessions
                $stmt = $db->prepare("SELECT * FROM user_sessions WHERE user_id = ? AND is_active = 1 ORDER BY last_activity_at DESC");
                $stmt->execute([$user_id]);
                $active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $error = 'Failed to revoke session: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'revoke_all') {
        try {
            $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ? AND id != (SELECT MAX(id) FROM user_sessions WHERE user_id = ?)");
            $stmt->execute([$user_id, $user_id]);
            
            logActivity($user_id, 'all_sessions_revoked', 'All other sessions revoked');
            $message = 'All other sessions revoked successfully!';
            
            // Refresh sessions
            $stmt = $db->prepare("SELECT * FROM user_sessions WHERE user_id = ? AND is_active = 1 ORDER BY last_activity_at DESC");
            $stmt->execute([$user_id]);
            $active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'Failed to revoke sessions: ' . $e->getMessage();
        }
    }
}

$page_title = 'Security';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.security-container {
    max-width: 900px;
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

.form-group .help-text {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 4px;
    margin-left: 26px;
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

.btn-group .btn-danger {
    background: #EF4444;
    color: white;
}

.btn-group .btn-danger:hover {
    background: #DC2626;
}

.btn-group .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-700);
}

.btn-group .btn-secondary:hover {
    background: var(--gray-200);
}

.session-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
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
    font-size: 0.7rem;
    color: var(--gray-500);
}

.session-item .session-status {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 10px;
}

.session-item .session-status.active {
    background: #ECFDF5;
    color: #065F46;
}

.session-item .session-status.current {
    background: #EFF6FF;
    color: #1E40AF;
}

.event-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.8rem;
}

.event-item:last-child {
    border-bottom: none;
}

.event-item .event-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    flex-shrink: 0;
}

.event-item .event-icon.login { background: #EFF6FF; color: #3B82F6; }
.event-item .event-icon.logout { background: #FEF2F2; color: #EF4444; }
.event-item .event-icon.security { background: #F5F3FF; color: #8B5CF6; }
.event-item .event-icon.warning { background: #FFFBEB; color: #F59E0B; }
.event-item .event-icon.danger { background: #FEF2F2; color: #DC2626; }

.event-item .event-content {
    flex: 1;
}

.event-item .event-content .description {
    color: var(--gray-700);
}

.event-item .event-content .time {
    font-size: 0.6rem;
    color: var(--gray-400);
}

@media (max-width: 768px) {
    .settings-card {
        padding: 16px 18px;
    }
    .session-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
    .session-item .session-status {
        align-self: flex-start;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="security-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-shield-alt"></i> Security</h1>
                    <p class="subtitle">
                        <i class="fas fa-flag"></i> 
                        <?php echo htmlspecialchars($state_name); ?> State - Security Settings
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

            <!-- Two-Factor Authentication -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-lock"></i> Two-Factor Authentication</div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="toggle_2fa" />
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="two_factor_enabled" id="two_factor" 
                                   <?php echo ($user_data['two_factor_enabled'] ?? 0) == 1 ? 'checked' : ''; ?> />
                            <label for="two_factor">Enable Two-Factor Authentication (2FA)</label>
                        </div>
                        <div class="help-text">
                            Adds an extra layer of security by requiring a verification code in addition to your password.
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Update 2FA Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Active Sessions -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-desktop"></i> Active Sessions</div>
                
                <?php if (!empty($active_sessions)): ?>
                    <?php foreach ($active_sessions as $session): 
                        $is_current = $session['id'] == SessionManager::get('session_id');
                    ?>
                        <div class="session-item">
                            <div class="session-info">
                                <div class="device">
                                    <i class="fas fa-<?php echo $session['device_type'] === 'web' ? 'desktop' : ($session['device_type'] === 'android' ? 'android' : 'apple'); ?>"></i>
                                    <?php echo htmlspecialchars($session['device_name'] ?? 'Unknown Device'); ?>
                                </div>
                                <div class="details">
                                    IP: <?php echo htmlspecialchars($session['ip_address'] ?? 'N/A'); ?>
                                    • Last active: <?php echo date('M j, Y g:i A', strtotime($session['last_activity_at'])); ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($is_current): ?>
                                    <span class="session-status current">Current Session</span>
                                <?php else: ?>
                                    <form method="POST" action="" style="display:inline;">
                                        <input type="hidden" name="action" value="revoke_session" />
                                        <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>" />
                                        <button type="submit" class="btn-danger" style="padding:3px 12px;font-size:0.65rem;border-radius:6px;">
                                            <i class="fas fa-times"></i> Revoke
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="margin-top:12px;">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="revoke_all" />
                            <button type="submit" class="btn-danger" onclick="return confirm('Are you sure you want to revoke all other sessions?')">
                                <i class="fas fa-sign-out-alt"></i> Revoke All Other Sessions
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="text-align:center;padding:20px;color:var(--gray-400);">
                        <i class="fas fa-desktop" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                        <p>No active sessions found.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Login History -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-history"></i> Login History</div>
                
                <?php if (!empty($login_history)): ?>
                    <?php foreach ($login_history as $login): ?>
                        <div class="event-item">
                            <div class="event-icon <?php echo $login['success'] ? 'login' : 'danger'; ?>">
                                <i class="fas fa-<?php echo $login['success'] ? 'sign-in-alt' : 'times'; ?>"></i>
                            </div>
                            <div class="event-content">
                                <div class="description">
                                    <?php echo $login['success'] ? 'Successful login' : 'Failed login attempt'; ?>
                                    <?php if (!empty($login['ip_address'])): ?>
                                        from <strong><?php echo htmlspecialchars($login['ip_address']); ?></strong>
                                    <?php endif; ?>
                                </div>
                                <div class="time">
                                    <?php echo date('M j, Y g:i A', strtotime($login['created_at'])); ?>
                                    <?php if (!empty($login['user_agent'])): ?>
                                        • <?php echo htmlspecialchars(substr($login['user_agent'], 0, 50)) . (strlen($login['user_agent']) > 50 ? '...' : ''); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($login['success']): ?>
                                <span style="font-size:0.6rem;color:#10B981;"><i class="fas fa-check-circle"></i></span>
                            <?php else: ?>
                                <span style="font-size:0.6rem;color:#EF4444;"><i class="fas fa-times-circle"></i></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:20px;color:var(--gray-400);">
                        <i class="fas fa-history" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                        <p>No login history found.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Security Events -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-shield-alt"></i> Security Events</div>
                
                <?php if (!empty($security_events)): ?>
                    <?php foreach ($security_events as $event): 
                        $icon_class = 'security';
                        if (strpos($event['event_type'], 'login') !== false) $icon_class = 'login';
                        elseif (strpos($event['event_type'], 'logout') !== false) $icon_class = 'logout';
                        elseif (strpos($event['event_type'], 'warning') !== false) $icon_class = 'warning';
                        elseif (strpos($event['event_type'], 'danger') !== false || strpos($event['event_type'], 'critical') !== false) $icon_class = 'danger';
                    ?>
                        <div class="event-item">
                            <div class="event-icon <?php echo $icon_class; ?>">
                                <i class="fas fa-<?php echo $icon_class === 'security' ? 'shield-alt' : ($icon_class === 'login' ? 'sign-in-alt' : ($icon_class === 'logout' ? 'sign-out-alt' : ($icon_class === 'warning' ? 'exclamation-triangle' : 'times'))); ?>"></i>
                            </div>
                            <div class="event-content">
                                <div class="description">
                                    <?php echo htmlspecialchars($event['description'] ?? $event['event_type']); ?>
                                    <?php if (!empty($event['risk_score'])): ?>
                                        <span style="font-size:0.6rem;color:#EF4444;background:#FEF2F2;padding:1px 6px;border-radius:4px;">
                                            Risk: <?php echo $event['risk_score']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="time">
                                    <?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?>
                                    <?php if (!empty($event['ip_address'])): ?>
                                        • IP: <?php echo htmlspecialchars($event['ip_address']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:20px;color:var(--gray-400);">
                        <i class="fas fa-shield-alt" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                        <p>No security events recorded.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
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