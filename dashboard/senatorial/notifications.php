<?php
// ============================================================
// SENATORIAL COORDINATOR - NOTIFICATIONS
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

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET FILTERS
// ============================================================
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$read_filter = isset($_GET['read']) ? $_GET['read'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// ============================================================
// BUILD QUERY
// ============================================================
$where_conditions = ["user_id = ?"];
$params = [$user_id];

if (!empty($type_filter)) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

if ($read_filter === 'unread') {
    $where_conditions[] = "is_read = 0";
} elseif ($read_filter === 'read') {
    $where_conditions[] = "is_read = 1";
}

$where_clause = implode(" AND ", $where_conditions);

// ============================================================
// GET NOTIFICATIONS
// ============================================================
$notifications = [];
$total_notifications = 0;

try {
    // Get total count
    $count_query = "
        SELECT COUNT(*) as total
        FROM notifications
        WHERE $where_clause
    ";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_notifications = $stmt->fetchColumn();

    // Get notifications
    $query = "
        SELECT *
        FROM notifications
        WHERE $where_clause
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($query);
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}

$total_pages = ceil($total_notifications / $per_page);

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
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
    $stats = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching notification stats: " . $e->getMessage());
}

// ============================================================
// MARK ALL AS READ
// ============================================================
if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW()
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user_id]);
        
        $_SESSION['flash_success'] = 'All notifications marked as read.';
        header('Location: notifications.php');
        exit();
    } catch (Exception $e) {
        error_log("Error marking all as read: " . $e->getMessage());
    }
}

$page_title = 'Notifications';
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
.page-header .unread-badge {
    background: #EF4444;
    color: white;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 14px 16px;
    border: 1px solid var(--gray-200);
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
}
.stat-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}
.stat-card .stat-number {
    font-size: 1.3rem;
    font-weight: 700;
}
.stat-card .stat-label {
    font-size: 0.65rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-card .stat-icon {
    font-size: 1rem;
    margin-bottom: 2px;
}
.stat-card.blue .stat-number { color: #2563EB; }
.stat-card.blue .stat-icon { color: #2563EB; }
.stat-card.green .stat-number { color: #059669; }
.stat-card.green .stat-icon { color: #059669; }
.stat-card.yellow .stat-number { color: #D97706; }
.stat-card.yellow .stat-icon { color: #D97706; }
.stat-card.red .stat-number { color: #DC2626; }
.stat-card.red .stat-icon { color: #DC2626; }
.stat-card.purple .stat-number { color: #7C3AED; }
.stat-card.purple .stat-icon { color: #7C3AED; }
.stat-card.orange .stat-number { color: #EA580C; }
.stat-card.orange .stat-icon { color: #EA580C; }

.filters-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    background: white;
    padding: 12px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filters-row select {
    padding: 6px 12px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
}
.filters-row select:focus {
    outline: none;
    border-color: var(--primary);
}
.filters-row .btn-filter {
    padding: 6px 16px;
    border: none;
    border-radius: 8px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
}
.filters-row .btn-filter:hover {
    background: var(--primary-dark);
}
.filters-row .btn-reset {
    padding: 6px 16px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
}
.filters-row .btn-reset:hover {
    background: var(--gray-50);
}

.notification-item {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 10px;
    transition: var(--transition);
    display: flex;
    gap: 14px;
    align-items: flex-start;
}
.notification-item:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}
.notification-item.unread {
    border-left: 4px solid var(--primary);
    background: #F8FAFF;
}
.notification-item .icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.notification-item .icon.system { background: #EDE9FE; color: #7C3AED; }
.notification-item .icon.election { background: #DBEAFE; color: #2563EB; }
.notification-item .icon.result { background: #D1FAE5; color: #059669; }
.notification-item .icon.incident { background: #FEE2E2; color: #DC2626; }
.notification-item .icon.chat { background: #FEF3C7; color: #D97706; }
.notification-item .icon.broadcast { background: #FFEDD5; color: #EA580C; }
.notification-item .icon.payment { background: #D1FAE5; color: #059669; }
.notification-item .icon.security { background: #FEE2E2; color: #DC2626; }

.notification-item .content {
    flex: 1;
    min-width: 0;
}
.notification-item .content .title {
    font-weight: 600;
    font-size: 0.9rem;
}
.notification-item .content .message {
    color: var(--gray-600);
    font-size: 0.85rem;
    margin-top: 2px;
}
.notification-item .content .time {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.notification-item .actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.btn-sm {
    padding: 4px 10px;
    border-radius: 6px;
    background: var(--primary);
    color: white;
    text-decoration: none;
    font-size: 0.7rem;
    border: none;
    cursor: pointer;
}
.btn-sm:hover {
    background: var(--primary-dark);
    color: white;
}
.btn-sm.outline {
    background: transparent;
    border: 1px solid var(--gray-300);
    color: var(--gray-600);
}
.btn-sm.outline:hover {
    background: var(--gray-50);
}
.btn-sm.danger {
    background: #FEE2E2;
    color: #DC2626;
}
.btn-sm.danger:hover {
    background: #FECACA;
}

.pagination {
    display: flex;
    gap: 6px;
    justify-content: center;
    margin-top: 16px;
    flex-wrap: wrap;
}
.pagination a, .pagination span {
    padding: 6px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    text-decoration: none;
    color: var(--gray-600);
    font-size: 0.8rem;
    transition: var(--transition);
}
.pagination a:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .notification-item {
        flex-wrap: wrap;
    }
    .notification-item .actions {
        width: 100%;
        justify-content: flex-end;
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
                    <i class="fas fa-bell" style="color:var(--primary);margin-right:8px;"></i> 
                    Notifications
                    <small>Stay updated with important alerts</small>
                    <?php if (($stats['unread'] ?? 0) > 0): ?>
                        <span class="unread-badge"><?php echo $stats['unread']; ?> unread</span>
                    <?php endif; ?>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php if (($stats['unread'] ?? 0) > 0): ?>
                    <a href="notifications.php?mark_all_read=1" class="btn btn-primary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:white;border:none;">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </a>
                <?php endif; ?>
                <a href="notifications-settings.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue" onclick="window.location.href='notifications.php'">
                <div class="stat-icon"><i class="fas fa-bell"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card red" onclick="window.location.href='notifications.php?read=unread'">
                <div class="stat-icon"><i class="fas fa-circle" style="color:#EF4444;"></i></div>
                <div class="stat-number"><?php echo number_format($stats['unread'] ?? 0); ?></div>
                <div class="stat-label">Unread</div>
            </div>
            <div class="stat-card green" onclick="window.location.href='notifications.php?read=read'">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['read'] ?? 0); ?></div>
                <div class="stat-label">Read</div>
            </div>
            <div class="stat-card yellow" onclick="window.location.href='notifications.php?type=broadcast'">
                <div class="stat-icon"><i class="fas fa-bullhorn"></i></div>
                <div class="stat-number"><?php echo number_format($stats['broadcast'] ?? 0); ?></div>
                <div class="stat-label">Broadcasts</div>
            </div>
            <div class="stat-card orange" onclick="window.location.href='notifications.php?type=incident'">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['incident'] ?? 0); ?></div>
                <div class="stat-label">Incidents</div>
            </div>
            <div class="stat-card purple" onclick="window.location.href='notifications.php?type=result'">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['result'] ?? 0); ?></div>
                <div class="stat-label">Results</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-row">
            <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <select name="type">
                    <option value="">All Types</option>
                    <option value="system" <?php echo ($type_filter === 'system') ? 'selected' : ''; ?>>System</option>
                    <option value="election" <?php echo ($type_filter === 'election') ? 'selected' : ''; ?>>Election</option>
                    <option value="result" <?php echo ($type_filter === 'result') ? 'selected' : ''; ?>>Results</option>
                    <option value="incident" <?php echo ($type_filter === 'incident') ? 'selected' : ''; ?>>Incidents</option>
                    <option value="chat" <?php echo ($type_filter === 'chat') ? 'selected' : ''; ?>>Chat</option>
                    <option value="broadcast" <?php echo ($type_filter === 'broadcast') ? 'selected' : ''; ?>>Broadcast</option>
                    <option value="payment" <?php echo ($type_filter === 'payment') ? 'selected' : ''; ?>>Payment</option>
                    <option value="security" <?php echo ($type_filter === 'security') ? 'selected' : ''; ?>>Security</option>
                </select>
                <select name="read">
                    <option value="">All Status</option>
                    <option value="unread" <?php echo ($read_filter === 'unread') ? 'selected' : ''; ?>>Unread</option>
                    <option value="read" <?php echo ($read_filter === 'read') ? 'selected' : ''; ?>>Read</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="notifications.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Notifications List -->
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notification): 
                $is_unread = !$notification['is_read'];
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
                $icon = $icon_map[$notification['type']] ?? 'fa-bell';
            ?>
                <div class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>">
                    <div class="icon <?php echo $notification['type']; ?>">
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="content">
                        <div class="title"><?php echo htmlspecialchars($notification['title']); ?></div>
                        <div class="message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        <div class="time">
                            <i class="fas fa-clock"></i> 
                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                            <?php if ($notification['read_at']): ?>
                                <span style="margin-left:12px;">
                                    <i class="fas fa-check"></i> Read at <?php echo date('M j, Y g:i A', strtotime($notification['read_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="actions">
                        <?php if ($is_unread): ?>
                            <a href="notifications-mark-read.php?id=<?php echo $notification['id']; ?>" class="btn-sm">
                                <i class="fas fa-check"></i> Mark Read
                            </a>
                        <?php endif; ?>
                        <?php if ($notification['action_url']): ?>
                            <a href="<?php echo $notification['action_url']; ?>" class="btn-sm outline">
                                <i class="fas fa-arrow-right"></i> View
                            </a>
                        <?php endif; ?>
                        <a href="notifications-delete.php?id=<?php echo $notification['id']; ?>" class="btn-sm danger" onclick="return confirm('Delete this notification?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <p>No notifications found.</p>
                <p style="font-size:0.8rem;margin-top:4px;">You're all caught up!</p>
            </div>
        <?php endif; ?>
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