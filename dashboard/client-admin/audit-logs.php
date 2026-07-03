<?php
// ============================================================
// AUDIT LOGS - CLIENT ADMIN (FIXED)
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
// FETCH AUDIT LOGS WITH PAGINATION & FILTERS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build WHERE clause - FIX: Specify table alias for tenant_id
$where_conditions = ["a.tenant_id = ?"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(a.activity_type LIKE ? OR a.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($action_filter)) {
    $where_conditions[] = "a.activity_type = ?";
    $params[] = $action_filter;
}

if ($user_filter > 0) {
    $where_conditions[] = "a.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(a.created_at) = ?";
    $params[] = $date_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM activity_logs a WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_logs = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_logs / $limit);

// Fetch audit logs - FIX: Use table aliases for all columns
$sql = "
    SELECT 
        a.*,
        u.full_name as user_name,
        u.email as user_email
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE $where_clause
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ============================================================
// FETCH DISTINCT VALUES FOR FILTERS
// ============================================================
$actions = [];
$users = [];

try {
    $stmt = $db->prepare("SELECT DISTINCT a.activity_type FROM activity_logs a WHERE a.tenant_id = ? ORDER BY a.activity_type");
    $stmt->execute([$tenant_id]);
    $actions = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT u.id, u.full_name, u.email FROM users u WHERE u.tenant_id = ? AND u.deleted_at IS NULL ORDER BY u.full_name");
    $stmt->execute([$tenant_id]);
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'today' => 0,
    'this_week' => 0,
    'this_month' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM activity_logs a WHERE a.tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $stats['total'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM activity_logs a WHERE a.tenant_id = ? AND DATE(a.created_at) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $stats['today'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM activity_logs a WHERE a.tenant_id = ? AND YEARWEEK(a.created_at) = YEARWEEK(NOW())");
    $stmt->execute([$tenant_id]);
    $stats['this_week'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM activity_logs a WHERE a.tenant_id = ? AND MONTH(a.created_at) = MONTH(NOW()) AND YEAR(a.created_at) = YEAR(NOW())");
    $stmt->execute([$tenant_id]);
    $stats['this_month'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<!-- Rest of audit-logs.php remains the same -->
<style>
    /* ============================================================
       AUDIT LOGS - CLIENT ADMIN STYLES
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
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-item {
        background: white;
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
    }
    .stat-item:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
    }
    .stat-item .number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .filter-bar {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 14px 20px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        box-shadow: var(--shadow);
    }
    .filter-bar .search-wrap {
        flex: 1;
        min-width: 200px;
        display: flex;
        align-items: center;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: 6px 14px;
        transition: var(--transition);
    }
    .filter-bar .search-wrap:focus-within {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .filter-bar .search-wrap i {
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .filter-bar .search-wrap input {
        border: none;
        outline: none;
        background: transparent;
        padding: 6px 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        width: 100%;
        color: var(--gray-700);
    }
    .filter-bar select {
        padding: 8px 14px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        background: var(--gray-50);
        color: var(--gray-700);
        cursor: pointer;
        transition: var(--transition);
        min-width: 120px;
    }
    .filter-bar select:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
    }
    .filter-bar .btn-filter {
        padding: 8px 18px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .filter-bar .btn-filter:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .filter-bar .btn-clear {
        padding: 8px 14px;
        background: transparent;
        color: var(--gray-500);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.8rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .filter-bar .btn-clear:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    
    .table-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    .table-container .table-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: var(--gray-50);
    }
    .table-container .table-header .table-title {
        font-weight: 600;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .table-container .table-header .table-title .count {
        background: var(--primary);
        color: white;
        padding: 0 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .data-table thead {
        background: var(--gray-50);
    }
    .data-table thead th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        border-bottom: 1px solid var(--gray-200);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--gray-50);
    }
    .data-table tbody td {
        padding: 8px 14px;
        border-bottom: 1px solid var(--gray-100);
        vertical-align: middle;
    }
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    .data-table tbody tr:hover {
        background: var(--gray-50);
    }
    
    .action-tag {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 500;
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .action-tag.login { background: #EFF6FF; color: #1E40AF; }
    .action-tag.logout { background: #EFF6FF; color: #1E40AF; }
    .action-tag.tenant { background: #ECFDF5; color: #065F46; }
    .action-tag.user { background: #F5F3FF; color: #5B21B6; }
    .action-tag.election { background: #FFFBEB; color: #92400E; }
    .action-tag.result { background: #ECFDF5; color: #065F46; }
    .action-tag.broadcast { background: #EFF6FF; color: #1E40AF; }
    .action-tag.security { background: #FEF2F2; color: #991B1B; }
    
    .pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 14px 20px;
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        margin-top: 16px;
        box-shadow: var(--shadow);
    }
    .pagination .info {
        font-size: 0.82rem;
        color: var(--gray-500);
    }
    .pagination .pages {
        display: flex;
        gap: 4px;
        align-items: center;
    }
    .pagination .pages a,
    .pagination .pages span {
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.82rem;
        text-decoration: none;
        color: var(--gray-600);
        transition: var(--transition);
        min-width: 36px;
        text-align: center;
        border: 1px solid transparent;
    }
    .pagination .pages a:hover {
        background: var(--gray-100);
        border-color: var(--gray-200);
    }
    .pagination .pages .active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .pagination .pages .disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    
    .empty-state {
        text-align: center;
        padding: 48px 20px;
        color: var(--gray-500);
    }
    .empty-state i {
        font-size: 3rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 12px;
    }
    .empty-state h4 {
        color: var(--gray-700);
        margin-bottom: 4px;
        font-size: 1rem;
    }
    .empty-state p {
        font-size: 0.85rem;
        color: var(--gray-400);
    }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-wrap { min-width: auto; }
        .filter-bar select { width: 100%; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .pagination { flex-direction: column; align-items: center; }
        .page-header { flex-direction: column; align-items: flex-start; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.2rem; }
        .data-table th, .data-table td { padding: 4px 8px; font-size: 0.7rem; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-clipboard-list" style="color:var(--primary);margin-right:8px;"></i> Audit Logs
                    <small>Track all activities in your organization</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="audit-logs-export.php" class="btn-outline">
                    <i class="fas fa-file-export"></i> Export
                </a>
                <button onclick="location.reload()" class="btn-outline">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item"><div class="number"><?php echo number_format($stats['total']); ?></div><div class="label">Total Logs</div></div>
            <div class="stat-item"><div class="number green"><?php echo number_format($stats['today']); ?></div><div class="label">Today</div></div>
            <div class="stat-item"><div class="number blue"><?php echo number_format($stats['this_week']); ?></div><div class="label">This Week</div></div>
            <div class="stat-item"><div class="number purple"><?php echo number_format($stats['this_month']); ?></div><div class="label">This Month</div></div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search activities..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?php echo htmlspecialchars($a['activity_type']); ?>" <?php echo $action_filter === $a['activity_type'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a['activity_type']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="user">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $user_filter == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['full_name'] ?? $u['email']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" style="padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.82rem;background:var(--gray-50);color:var(--gray-700);">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || !empty($action_filter) || $user_filter > 0 || !empty($date_filter)): ?>
                    <a href="audit-logs.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Audit Logs Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Activity Logs
                    <span class="count"><?php echo number_format($total_logs); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Activity Type</th>
                        <th>Description</th>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $index => $log): ?>
                            <?php 
                                $action_class = 'action-tag';
                                $type = $log['activity_type'] ?? '';
                                if (strpos($type, 'login') !== false) {
                                    $action_class .= ' login';
                                } elseif (strpos($type, 'logout') !== false) {
                                    $action_class .= ' logout';
                                } elseif (strpos($type, 'tenant') !== false) {
                                    $action_class .= ' tenant';
                                } elseif (strpos($type, 'user') !== false) {
                                    $action_class .= ' user';
                                } elseif (strpos($type, 'election') !== false) {
                                    $action_class .= ' election';
                                } elseif (strpos($type, 'result') !== false) {
                                    $action_class .= ' result';
                                } elseif (strpos($type, 'broadcast') !== false) {
                                    $action_class .= ' broadcast';
                                } elseif (strpos($type, 'security') !== false || strpos($type, 'password') !== false) {
                                    $action_class .= ' security';
                                }
                            ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <span class="<?php echo $action_class; ?>">
                                        <?php echo htmlspecialchars($log['activity_type'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td style="max-width:250px;word-wrap:break-word;">
                                    <?php echo htmlspecialchars($log['description'] ?? ''); ?>
                                </td>
                                <td>
                                    <?php if (!empty($log['user_name'])): ?>
                                        <div style="font-weight:500;font-size:0.82rem;"><?php echo htmlspecialchars($log['user_name']); ?></div>
                                        <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo htmlspecialchars($log['user_email'] ?? ''); ?></div>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);font-size:0.8rem;">System</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.75rem;color:var(--gray-500);font-family:monospace;">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?>
                                </td>
                                <td style="font-size:0.75rem;color:var(--gray-500);white-space:nowrap;">
                                    <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-list"></i>
                                    <h4>No activity logs found</h4>
                                    <p>Try adjusting your filters or check back later.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <div class="info">
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_logs); ?></strong> of <strong><?php echo number_format($total_logs); ?></strong> logs
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>&user=<?php echo $user_filter; ?>&date=<?php echo urlencode($date_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&action=' . urlencode($action_filter) . '&user=' . $user_filter . '&date=' . urlencode($date_filter) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>&user=<?php echo $user_filter; ?>&date=<?php echo urlencode($date_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&action=' . urlencode($action_filter) . '&user=' . $user_filter . '&date=' . urlencode($date_filter) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>&user=<?php echo $user_filter; ?>&date=<?php echo urlencode($date_filter); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
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