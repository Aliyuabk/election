<?php
$page_title = "Security Monitoring";
require_once 'includes/db.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// ============================================================
// HANDLE ACTIONS
// ============================================================
$message = '';
$error = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'block_ip':
                $ip = $_POST['ip_address'] ?? '';
                $reason = $_POST['reason'] ?? '';
                blockIP($ip, $reason);
                $message = "IP $ip blocked successfully.";
                $message_type = 'success';
                break;
                
            case 'unblock_ip':
                $ip = $_POST['ip_address'] ?? '';
                unblockIP($ip);
                $message = "IP $ip unblocked successfully.";
                $message_type = 'success';
                break;
                
            case 'force_logout':
                $user_id = (int)($_POST['user_id'] ?? 0);
                forceLogout($user_id);
                $message = "User logged out successfully.";
                $message_type = 'success';
                break;
                
            case 'disable_account':
                $user_id = (int)($_POST['user_id'] ?? 0);
                disableAccount($user_id);
                $message = "Account disabled successfully.";
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        $message_type = 'error';
    }
}

// ============================================================
// SECURITY FUNCTIONS
// ============================================================
function blockIP($ip, $reason) {
    // Implement IP blocking logic here
    logActivity(getValidUserId(), null, 'ip_blocked', "Blocked IP: $ip - $reason");
    return true;
}

function unblockIP($ip) {
    // Implement IP unblocking logic here
    logActivity(getValidUserId(), null, 'ip_unblocked', "Unblocked IP: $ip");
    return true;
}

function forceLogout($user_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    logActivity(getValidUserId(), null, 'force_logout', "Force logout user ID: $user_id");
    return true;
}

function disableAccount($user_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
    $stmt->execute([$user_id]);
    
    logActivity(getValidUserId(), null, 'account_disabled', "Disabled account user ID: $user_id");
    return true;
}

// ============================================================
// GET SECURITY DATA
// ============================================================
// Failed login attempts
$failedLogins = $conn->query("
    SELECT * FROM login_attempts 
    WHERE success = 0 
    ORDER BY created_at DESC 
    LIMIT 20
")->fetchAll();

// Security events
$securityEvents = $conn->query("
    SELECT * FROM security_events 
    ORDER BY created_at DESC 
    LIMIT 20
")->fetchAll();

// Active sessions
$activeSessions = $conn->query("
    SELECT us.*, u.full_name, u.email 
    FROM user_sessions us
    LEFT JOIN users u ON us.user_id = u.id
    WHERE us.is_active = 1 
    AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ORDER BY us.last_activity_at DESC
")->fetchAll();

// Online users count
$onlineUsers = $conn->query("
    SELECT COUNT(DISTINCT user_id) as online 
    FROM user_sessions 
    WHERE is_active = 1 
    AND last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
")->fetch()['online'];

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<style>
.security-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.security-stats .stat-card {
    background: white;
    border-radius: 14px;
    padding: 16px 20px;
    text-align: center;
    border: 1px solid #eef3f8;
}

.security-stats .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0b1a33;
}

.security-stats .stat-label {
    font-size: 0.7rem;
    color: #6d83a5;
    text-transform: uppercase;
}

.security-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.security-table thead {
    background: #f8faff;
    border-bottom: 1px solid #eef3f8;
}

.security-table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #405473;
    font-size: 0.7rem;
    text-transform: uppercase;
}

.security-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #f5f8fc;
}

.security-table tr:hover {
    background: #f8faff;
}

.risk-badge {
    font-size: 0.7rem;
    padding: 2px 12px;
    border-radius: 30px;
    font-weight: 500;
}

.risk-badge.low { background: #d1fae5; color: #065f46; }
.risk-badge.medium { background: #fef3c7; color: #92400e; }
.risk-badge.high { background: #fee2e2; color: #991b1b; }
.risk-badge.critical { background: #fecaca; color: #7f1d1d; }
</style>

<main class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-left">
            <h1>
                <i class="fas fa-shield-alt" style="color:#4f9cf7;"></i>
                Security Monitoring
                <span class="page-badge">Live</span>
            </h1>
            <p class="subtitle">Monitor and manage platform security</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="security-stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($failedLogins); ?></div>
            <div class="stat-label">Failed Logins</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $onlineUsers; ?></div>
            <div class="stat-label">Online Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count($activeSessions); ?></div>
            <div class="stat-label">Active Sessions</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count($securityEvents); ?></div>
            <div class="stat-label">Security Events</div>
        </div>
    </div>

    <!-- Failed Login Attempts -->
    <div class="table-container" style="margin-bottom:24px;">
        <div class="table-toolbar">
            <div class="toolbar-left">
                <span style="font-size:0.85rem; font-weight:600; color:#1f3149;">
                    <i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i>
                    Failed Login Attempts
                </span>
            </div>
        </div>
        <table class="security-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Email</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($failedLogins)): ?>
                <tr><td colspan="4" style="text-align:center; color:#8b9bb5; padding:20px;">No failed login attempts</td></tr>
                <?php else: ?>
                <?php foreach ($failedLogins as $attempt): ?>
                <tr>
                    <td><?php echo date('M d, Y H:i:s', strtotime($attempt['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($attempt['email']); ?></td>
                    <td><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                    <td style="font-size:0.8rem; color:#6d83a5;"><?php echo htmlspecialchars(substr($attempt['user_agent'] ?? '', 0, 50)); ?>...</td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Active Sessions -->
    <div class="table-container" style="margin-bottom:24px;">
        <div class="table-toolbar">
            <div class="toolbar-left">
                <span style="font-size:0.85rem; font-weight:600; color:#1f3149;">
                    <i class="fas fa-users" style="color:#10b981;"></i>
                    Active Sessions
                </span>
            </div>
            <div class="toolbar-right">
                <span style="font-size:0.8rem; color:#6d83a5;"><?php echo count($activeSessions); ?> online</span>
            </div>
        </div>
        <table class="security-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>IP Address</th>
                    <th>Device</th>
                    <th>Last Activity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activeSessions)): ?>
                <tr><td colspan="5" style="text-align:center; color:#8b9bb5; padding:20px;">No active sessions</td></tr>
                <?php else: ?>
                <?php foreach ($activeSessions as $session): ?>
                <tr>
                    <td>
                        <div><?php echo htmlspecialchars($session['full_name'] ?? 'Unknown'); ?></div>
                        <div style="font-size:0.7rem; color:#8b9bb5;"><?php echo htmlspecialchars($session['email'] ?? ''); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($session['ip_address']); ?></td>
                    <td style="font-size:0.8rem; color:#6d83a5;">
                        <i class="fas fa-<?php echo $session['device_type'] === 'web' ? 'laptop' : ($session['device_type'] === 'android' ? 'android' : 'apple'); ?>"></i>
                        <?php echo ucfirst($session['device_type']); ?>
                    </td>
                    <td><?php echo date('M d, Y H:i:s', strtotime($session['last_activity_at'])); ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Force logout this user?');">
                            <input type="hidden" name="action" value="force_logout">
                            <input type="hidden" name="user_id" value="<?php echo $session['user_id']; ?>">
                            <button type="submit" class="btn-icon" title="Force Logout" style="color:#ef4444;">
                                <i class="fas fa-sign-out-alt"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Security Events -->
    <div class="table-container">
        <div class="table-toolbar">
            <div class="toolbar-left">
                <span style="font-size:0.85rem; font-weight:600; color:#1f3149;">
                    <i class="fas fa-bell" style="color:#f59e0b;"></i>
                    Security Events
                </span>
            </div>
        </div>
        <table class="security-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Event Type</th>
                    <th>Description</th>
                    <th>Risk Score</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($securityEvents)): ?>
                <tr><td colspan="5" style="text-align:center; color:#8b9bb5; padding:20px;">No security events</td></tr>
                <?php else: ?>
                <?php foreach ($securityEvents as $event): ?>
                <tr>
                    <td><?php echo date('M d, Y H:i:s', strtotime($event['created_at'])); ?></td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?></td>
                    <td><?php echo htmlspecialchars($event['description']); ?></td>
                    <td>
                        <?php if ($event['risk_score']): ?>
                        <span class="risk-badge <?php echo $event['risk_score'] >= 70 ? 'critical' : ($event['risk_score'] >= 50 ? 'high' : ($event['risk_score'] >= 30 ? 'medium' : 'low')); ?>">
                            <?php echo $event['risk_score']; ?>%
                        </span>
                        <?php else: ?>
                        <span style="color:#8b9bb5;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($event['resolved']): ?>
                        <span style="color:#10b981;">Resolved</span>
                        <?php else: ?>
                        <span style="color:#f59e0b;">Active</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include 'includes/footer.php'; ?>