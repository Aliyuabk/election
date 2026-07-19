<?php
// ============================================================
// WARD COORDINATOR - NOTIFICATIONS
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

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$ward_id = SessionManager::get('ward_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
if (empty($ward_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT ward_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            SessionManager::set('ward_id', $ward_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// FETCH WARD NAME
// ============================================================
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// HANDLE NOTIFICATION ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
    
    try {
        switch ($action) {
            case 'mark_read':
                if ($notification_id > 0) {
                    $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
                    $stmt->execute([$notification_id, $user_id]);
                    $success_message = "Notification marked as read.";
                } else {
                    // Mark all as read
                    $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
                    $stmt->execute([$user_id]);
                    $success_message = "All notifications marked as read.";
                }
                break;
                
            case 'delete':
                if ($notification_id > 0) {
                    $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                    $stmt->execute([$notification_id, $user_id]);
                    $success_message = "Notification deleted.";
                }
                break;
                
            case 'delete_all':
                $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
                $stmt->execute([$user_id]);
                $success_message = "All read notifications deleted.";
                break;
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
        error_log("Notification action error: " . $e->getMessage());
    }
}

// ============================================================
// FETCH NOTIFICATIONS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$read_filter = isset($_GET['read']) ? $_GET['read'] : 'all';

$notifications = [];
$total_notifications = 0;

try {
    // Build query conditions
    $conditions = "user_id = ?";
    $params = [$user_id];
    
    if ($type_filter !== 'all') {
        $conditions .= " AND type = ?";
        $params[] = $type_filter;
    }
    
    if ($read_filter === 'unread') {
        $conditions .= " AND is_read = 0";
    } elseif ($read_filter === 'read') {
        $conditions .= " AND is_read = 1";
    }
    
    // Get total count
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE $conditions");
    $count_stmt->execute($params);
    $total_notifications = (int)$count_stmt->fetchColumn();
    
    // Get notifications
    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE $conditions
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}

// ============================================================
// FETCH NOTIFICATION STATISTICS
// ============================================================
$notification_stats = [
    'total' => 0,
    'unread' => 0,
    'read' => 0,
    'system' => 0,
    'election' => 0,
    'result' => 0,
    'incident' => 0,
    'chat' => 0,
    'broadcast' => 0,
    'payment' => 0,
    'security' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
            SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read,
            SUM(CASE WHEN type = 'system' THEN 1 ELSE 0 END) as system,
            SUM(CASE WHEN type = 'election' THEN 1 ELSE 0 END) as election,
            SUM(CASE WHEN type = 'result' THEN 1 ELSE 0 END) as result,
            SUM(CASE WHEN type = 'incident' THEN 1 ELSE 0 END) as incident,
            SUM(CASE WHEN type = 'chat' THEN 1 ELSE 0 END) as chat,
            SUM(CASE WHEN type = 'broadcast' THEN 1 ELSE 0 END) as broadcast,
            SUM(CASE WHEN type = 'payment' THEN 1 ELSE 0 END) as payment,
            SUM(CASE WHEN type = 'security' THEN 1 ELSE 0 END) as security
        FROM notifications 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $notification_stats['total'] = (int)($stats['total'] ?? 0);
    $notification_stats['unread'] = (int)($stats['unread'] ?? 0);
    $notification_stats['read'] = (int)($stats['read'] ?? 0);
    $notification_stats['system'] = (int)($stats['system'] ?? 0);
    $notification_stats['election'] = (int)($stats['election'] ?? 0);
    $notification_stats['result'] = (int)($stats['result'] ?? 0);
    $notification_stats['incident'] = (int)($stats['incident'] ?? 0);
    $notification_stats['chat'] = (int)($stats['chat'] ?? 0);
    $notification_stats['broadcast'] = (int)($stats['broadcast'] ?? 0);
    $notification_stats['payment'] = (int)($stats['payment'] ?? 0);
    $notification_stats['security'] = (int)($stats['security'] ?? 0);
    
} catch (Exception $e) {
    error_log("Error fetching notification stats: " . $e->getMessage());
}

$page_title = 'Notifications';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.notification-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.notification-header h2 i {
    color: var(--primary);
}
.notification-header .actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.stat-mini {
    background: white;
    padding: 10px 14px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-mini .number {
    font-size: 1.2rem;
    font-weight: 700;
}
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.red { color: #EF4444; }
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .number.purple { color: #8B5CF6; }
.stat-mini .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    font-weight: 500;
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
}
.filter-bar select {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    min-width: 140px;
}

.notification-item {
    display: flex;
    gap: 14px;
    padding: 14px 16px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    margin-bottom: 10px;
    transition: var(--transition);
}
.notification-item:hover {
    box-shadow: var(--shadow-hover);
}
.notification-item.unread {
    border-left: 3px solid #3B82F6;
    background: #F8FAFC;
}
.notification-item .icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.notification-item .icon.system { background: #F1F5F9; color: #64748B; }
.notification-item .icon.election { background: #EFF6FF; color: #3B82F6; }
.notification-item .icon.result { background: #ECFDF5; color: #10B981; }
.notification-item .icon.incident { background: #FEF2F2; color: #EF4444; }
.notification-item .icon.chat { background: #F5F3FF; color: #8B5CF6; }
.notification-item .icon.broadcast { background: #FFFBEB; color: #F59E0B; }
.notification-item .icon.payment { background: #ECFDF5; color: #0D9488; }
.notification-item .icon.security { background: #FEF2F2; color: #DC2626; }

.notification-item .content {
    flex: 1;
    min-width: 0;
}
.notification-item .content .title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-800);
}
.notification-item .content .message {
    font-size: 0.82rem;
    color: var(--gray-600);
    margin-top: 2px;
}
.notification-item .content .meta {
    display: flex;
    gap: 12px;
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.notification-item .content .meta i {
    width: 14px;
}
.notification-item .actions {
    display: flex;
    gap: 4px;
    align-items: flex-start;
}
.notification-item .actions button {
    padding: 4px 8px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.7rem;
    transition: var(--transition);
}
.notification-item .actions .mark-read {
    background: #EFF6FF;
    color: #3B82F6;
}
.notification-item .actions .mark-read:hover {
    background: #DBEAFE;
}
.notification-item .actions .delete {
    background: #FEF2F2;
    color: #EF4444;
}
.notification-item .actions .delete:hover {
    background: #FEE2E2;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    padding: 16px 0;
}
.pagination a, .pagination span {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.8rem;
    text-decoration: none;
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
}
.pagination a:hover {
    background: var(--gray-50);
    border-color: var(--gray-300);
}
.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .disabled {
    opacity: 0.5;
    pointer-events: none;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 4rem;
    color: var(--gray-300);
    margin-bottom: 16px;
}
.empty-state h4 {
    margin: 0 0 8px;
    color: var(--gray-700);
}
.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    border: 1px solid #D1FAE5;
    color: #065F46;
}
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .notification-item {
        flex-wrap: wrap;
    }
    .notification-item .actions {
        width: 100%;
        justify-content: flex-end;
    }
    .notification-header {
        flex-direction: column;
        align-items: stretch;
    }
    .notification-header .actions {
        justify-content: stretch;
    }
    .notification-header .actions button,
    .notification-header .actions a {
        flex: 1;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="notification-header">
            <div>
                <h2><i class="fas fa-bell"></i> Notifications</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • 
                    <?php echo number_format($notification_stats['total']); ?> notifications
                    <?php if ($notification_stats['unread'] > 0): ?>
                        <span style="color:#EF4444;font-weight:600;">(<?php echo number_format($notification_stats['unread']); ?> unread)</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="actions">
                <form method="POST" action="" style="display:inline;">
                    <input type="hidden" name="action" value="mark_read">
                    <button type="submit" class="btn-secondary-sm">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                </form>
                <form method="POST" action="" style="display:inline;">
                    <input type="hidden" name="action" value="delete_all">
                    <button type="submit" class="btn-secondary-sm" style="background:#FEF2F2;color:#EF4444;border-color:#FEE2E2;" onclick="return confirm('Delete all read notifications?')">
                        <i class="fas fa-trash"></i> Clear Read
                    </button>
                </form>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($notification_stats['total']); ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-mini">
                <div class="number red"><?php echo number_format($notification_stats['unread']); ?></div>
                <div class="label">Unread</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($notification_stats['read']); ?></div>
                <div class="label">Read</div>
            </div>
            <div class="stat-mini">
                <div class="number purple"><?php echo number_format($notification_stats['chat']); ?></div>
                <div class="label">Chat</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($notification_stats['broadcast']); ?></div>
                <div class="label">Broadcast</div>
            </div>
            <div class="stat-mini">
                <div class="number red"><?php echo number_format($notification_stats['incident']); ?></div>
                <div class="label">Incident</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <select id="typeFilter" onchange="applyFilters()">
                <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                <option value="system" <?php echo $type_filter === 'system' ? 'selected' : ''; ?>>System</option>
                <option value="election" <?php echo $type_filter === 'election' ? 'selected' : ''; ?>>Election</option>
                <option value="result" <?php echo $type_filter === 'result' ? 'selected' : ''; ?>>Result</option>
                <option value="incident" <?php echo $type_filter === 'incident' ? 'selected' : ''; ?>>Incident</option>
                <option value="chat" <?php echo $type_filter === 'chat' ? 'selected' : ''; ?>>Chat</option>
                <option value="broadcast" <?php echo $type_filter === 'broadcast' ? 'selected' : ''; ?>>Broadcast</option>
                <option value="payment" <?php echo $type_filter === 'payment' ? 'selected' : ''; ?>>Payment</option>
                <option value="security" <?php echo $type_filter === 'security' ? 'selected' : ''; ?>>Security</option>
            </select>
            <select id="readFilter" onchange="applyFilters()">
                <option value="all" <?php echo $read_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="unread" <?php echo $read_filter === 'unread' ? 'selected' : ''; ?>>Unread</option>
                <option value="read" <?php echo $read_filter === 'read' ? 'selected' : ''; ?>>Read</option>
            </select>
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button onclick="resetFilters()" class="btn-secondary-sm" style="background: var(--gray-100);">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Notifications List -->
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notification): 
                $is_unread = (int)($notification['is_read'] ?? 0) === 0;
                $type = $notification['type'] ?? 'system';
                $icon_map = [
                    'system' => 'fa-cog',
                    'election' => 'fa-vote-yea',
                    'result' => 'fa-file-alt',
                    'incident' => 'fa-exclamation-triangle',
                    'chat' => 'fa-comment-dots',
                    'broadcast' => 'fa-bullhorn',
                    'payment' => 'fa-money-bill-wave',
                    'security' => 'fa-shield-alt'
                ];
                $icon = $icon_map[$type] ?? 'fa-bell';
            ?>
                <div class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>">
                    <div class="icon <?php echo $type; ?>">
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="content">
                        <div class="title"><?php echo htmlspecialchars($notification['title'] ?? 'Notification'); ?></div>
                        <div class="message"><?php echo htmlspecialchars($notification['message'] ?? ''); ?></div>
                        <div class="meta">
                            <span><i class="fas fa-tag"></i> <?php echo ucfirst($type); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></span>
                            <?php if ($notification['read_at']): ?>
                                <span><i class="fas fa-check-circle" style="color:#10B981;"></i> Read <?php echo date('M d, Y H:i', strtotime($notification['read_at'])); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($notification['action_url'])): ?>
                                <span><a href="<?php echo htmlspecialchars($notification['action_url']); ?>" style="color:var(--primary);font-weight:500;">View Details →</a></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="actions">
                        <?php if ($is_unread): ?>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <button type="submit" class="mark-read" title="Mark as read">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                            <button type="submit" class="delete" title="Delete" onclick="return confirm('Delete this notification?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php 
            $total_pages = ceil($total_notifications / $limit);
            if ($total_pages > 1): 
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $type_filter; ?>&read=<?php echo $read_filter; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&type=<?php echo $type_filter; ?>&read=<?php echo $read_filter; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $type_filter; ?>&read=<?php echo $read_filter; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h4>No Notifications</h4>
                <p>You don't have any notifications at the moment.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Apply filters
function applyFilters() {
    const type = document.getElementById('typeFilter').value;
    const read = document.getElementById('readFilter').value;
    window.location.href = `?type=${type}&read=${read}`;
}

// Reset filters
function resetFilters() {
    document.getElementById('typeFilter').value = 'all';
    document.getElementById('readFilter').value = 'all';
    window.location.href = '?';
}

// Preloader
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// Sidebar toggle
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

// Sidebar dropdowns
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

// Profile dropdown
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