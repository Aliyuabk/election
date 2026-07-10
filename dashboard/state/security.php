<?php
// ============================================================
// STATE COORDINATOR - SECURITY
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

// If state_id is not set in session, try to get it from user record
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

// ============================================================
// GENERATE CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// FETCH STATE NAME
// ============================================================
$state_name = 'Unknown State';
try {
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        $state_name = $state['name'] ?? 'Unknown State';
    }
} catch (Exception $e) {
    error_log("Error fetching state: " . $e->getMessage());
}

// ============================================================
// FETCH SECURITY EVENTS
// ============================================================
$security_events = [];
$total_events = 0;
$stats = [
    'total' => 0,
    'login' => 0,
    'logout' => 0,
    'failed_login' => 0,
    'password_change' => 0,
    'password_reset' => 0,
    'suspicious' => 0
];

try {
    // Get security events for this user
    $stmt = $db->prepare("
        SELECT * FROM security_events 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $security_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_events = count($security_events);
    
    // Calculate stats
    $stats['total'] = $total_events;
    foreach ($security_events as $event) {
        if (strpos($event['event_type'], 'login') !== false) $stats['login']++;
        if (strpos($event['event_type'], 'logout') !== false) $stats['logout']++;
        if (strpos($event['event_type'], 'failed') !== false) $stats['failed_login']++;
        if (strpos($event['event_type'], 'password_change') !== false) $stats['password_change']++;
        if (strpos($event['event_type'], 'password_reset') !== false) $stats['password_reset']++;
        if (($event['risk_score'] ?? 0) > 50) $stats['suspicious']++;
    }
    
    // Get active sessions
    $stmt = $db->prepare("
        SELECT * FROM user_sessions 
        WHERE user_id = ? AND is_active = 1 
        ORDER BY last_activity_at DESC
    ");
    $stmt->execute([$user_id]);
    $active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get login attempts
    $stmt = $db->prepare("
        SELECT * FROM login_attempts 
        WHERE user_id = ? OR email = (SELECT email FROM users WHERE id = ?)
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id, $user_id]);
    $login_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching security data: " . $e->getMessage());
}

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
    margin: 0;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.btn-secondary-sm {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-secondary-sm:hover {
    background: var(--gray-200);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 14px 16px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-card .number {
    font-size: 1.4rem;
    font-weight: 700;
}
.stat-card .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    margin-top: 2px;
}
.stat-card .number.primary { color: #3B82F6; }
.stat-card .number.success { color: #10B981; }
.stat-card .number.danger { color: #EF4444; }
.stat-card .number.warning { color: #F59E0B; }

.table-wrapper {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 20px;
}
.table-wrapper table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table-wrapper table th {
    background: var(--gray-50);
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-600);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
}
.table-wrapper table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table-wrapper table tr:hover td {
    background: var(--gray-50);
}
.table-wrapper table tr:last-child td {
    border-bottom: none;
}

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.badge-status .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.badge-status.success { background: #ECFDF5; color: #065F46; }
.badge-status.success .dot { background: #10B981; }
.badge-status.danger { background: #FEF2F2; color: #991B1B; }
.badge-status.danger .dot { background: #EF4444; }
.badge-status.warning { background: #FFFBEB; color: #92400E; }
.badge-status.warning .dot { background: #F59E0B; }
.badge-status.primary { background: #EFF6FF; color: #1E40AF; }
.badge-status.primary .dot { background: #3B82F6; }

.risk-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 600;
}
.risk-indicator.low { background: #ECFDF5; color: #065F46; }
.risk-indicator.medium { background: #FFFBEB; color: #92400E; }
.risk-indicator.high { background: #FEF2F2; color: #991B1B; }

.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--gray-400);
}
.empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 8px;
    color: var(--gray-300);
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .table-wrapper {
        overflow-x: auto;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-shield-alt" style="color:var(--primary);margin-right:8px;"></i>
                    Security
                    <small>Monitor your account security</small>
                </h2>
            </div>
            <div>
                <a href="settings.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Settings
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number primary"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total Events</div>
            </div>
            <div class="stat-card">
                <div class="number success"><?php echo number_format($stats['login']); ?></div>
                <div class="label">Logins</div>
            </div>
            <div class="stat-card">
                <div class="number warning"><?php echo number_format($stats['failed_login']); ?></div>
                <div class="label">Failed Logins</div>
            </div>
            <div class="stat-card">
                <div class="number danger"><?php echo number_format($stats['suspicious']); ?></div>
                <div class="label">Suspicious</div>
            </div>
            <div class="stat-card">
                <div class="number primary"><?php echo number_format(count($active_sessions ?? [])); ?></div>
                <div class="label">Active Sessions</div>
            </div>
        </div>

        <!-- Security Events -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Event Type</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Risk Score</th>
                        <th>Date/Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($security_events) > 0): ?>
                        <?php $sn = 1; ?>
                        <?php foreach ($security_events as $event): ?>
                            <?php 
                            $risk = (int)($event['risk_score'] ?? 0);
                            $risk_class = $risk > 70 ? 'high' : ($risk > 40 ? 'medium' : 'low');
                            ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td>
                                    <span class="badge-status <?php 
                                        if (strpos($event['event_type'], 'login') !== false) echo 'success';
                                        elseif (strpos($event['event_type'], 'logout') !== false) echo 'primary';
                                        elseif (strpos($event['event_type'], 'failed') !== false) echo 'danger';
                                        else echo 'warning';
                                    ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($event['description'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($event['ip_address'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="risk-indicator <?php echo $risk_class; ?>">
                                        <?php echo $risk; ?>%
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:0.75rem;"><?php echo date('M j, Y', strtotime($event['created_at'])); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo date('g:i A', strtotime($event['created_at'])); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-shield-alt"></i>
                                    <p>No security events found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Active Sessions -->
        <?php if (!empty($active_sessions)): ?>
            <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:16px 20px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 12px 0;">
                    <i class="fas fa-desktop" style="color:var(--primary);margin-right:6px;"></i>
                    Active Sessions
                </h4>
                <?php foreach ($active_sessions as $session): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--gray-100);">
                        <div>
                            <div style="font-weight:500;font-size:0.85rem;">
                                <?php echo htmlspecialchars($session['device_name'] ?? 'Unknown Device'); ?>
                            </div>
                            <div style="font-size:0.7rem;color:var(--gray-400);">
                                IP: <?php echo htmlspecialchars($session['ip_address'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.7rem;color:var(--gray-500);">
                                Last active: <?php echo date('M j, Y g:i A', strtotime($session['last_activity_at'] ?? 'now')); ?>
                            </div>
                            <span class="badge-status success">
                                <span class="dot"></span>
                                Active
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
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
</script>
</body>
</html>