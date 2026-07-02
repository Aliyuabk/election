<?php
$page_title = "Audit Logs";
require_once 'includes/db.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// ============================================================
// GET FILTERS AND PAGINATION
// ============================================================
$search = $_GET['search'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_tenant = $_GET['tenant'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$per_page = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// ============================================================
// BUILD QUERY
// ============================================================
$base_query = "FROM activity_logs al
               LEFT JOIN users u ON al.user_id = u.id
               LEFT JOIN tenants t ON al.tenant_id = t.id
               WHERE 1=1";

$params = [];

if ($search) {
    $base_query .= " AND (al.description LIKE ? OR al.activity_type LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($filter_action) {
    $base_query .= " AND al.activity_type = ?";
    $params[] = $filter_action;
}

if ($filter_user) {
    $base_query .= " AND al.user_id = ?";
    $params[] = $filter_user;
}

if ($filter_tenant) {
    $base_query .= " AND al.tenant_id = ?";
    $params[] = $filter_tenant;
}

if ($date_from) {
    $base_query .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $base_query .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

// Get total count
$count_query = "SELECT COUNT(*) as total " . $base_query;
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_count = $count_stmt->fetch()['total'];
$total_pages = ceil($total_count / $per_page);

// Get paginated results
$query = "SELECT 
            al.*,
            u.full_name as user_name,
            u.email as user_email,
            t.name as tenant_name,
            t.slug as tenant_slug
          " . $base_query . "
          ORDER BY al.$sort_by $sort_order 
          LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ============================================================
// GET FILTER OPTIONS
// ============================================================
$action_types = $conn->query("
    SELECT DISTINCT activity_type 
    FROM activity_logs 
    ORDER BY activity_type
")->fetchAll();

$users = $conn->query("
    SELECT id, full_name, email 
    FROM users 
    WHERE deleted_at IS NULL 
    ORDER BY full_name
")->fetchAll();

$tenants = $conn->query("
    SELECT id, name, slug 
    FROM tenants 
    WHERE deleted_at IS NULL 
    ORDER BY name
")->fetchAll();

// Get statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN activity_type = 'login' THEN 1 ELSE 0 END) as logins,
        SUM(CASE WHEN activity_type = 'logout' THEN 1 ELSE 0 END) as logouts,
        SUM(CASE WHEN activity_type LIKE '%created%' THEN 1 ELSE 0 END) as creations,
        SUM(CASE WHEN activity_type LIKE '%updated%' THEN 1 ELSE 0 END) as updates,
        SUM(CASE WHEN activity_type LIKE '%deleted%' THEN 1 ELSE 0 END) as deletions,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
    FROM activity_logs
")->fetch();

// Get activity by hour for chart
$hourlyActivity = $conn->query("
    SELECT 
        HOUR(created_at) as hour,
        COUNT(*) as count
    FROM activity_logs
    WHERE DATE(created_at) = CURDATE()
    GROUP BY HOUR(created_at)
    ORDER BY hour ASC
")->fetchAll();

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<style>
/* ============================================================
   AUDIT LOGS STYLES
   ============================================================ */

/* Stats Grid */
.audit-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.audit-stats .stat-card {
    background: white;
    border-radius: 14px;
    padding: 16px 20px;
    text-align: center;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
}

.audit-stats .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}

.audit-stats .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0b1a33;
    line-height: 1.2;
}

.audit-stats .stat-label {
    font-size: 0.7rem;
    color: #6d83a5;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 4px;
}

.audit-stats .stat-icon {
    font-size: 1.5rem;
    display: block;
    margin-bottom: 8px;
}

.stat-icon.total { color: #4f9cf7; }
.stat-icon.logins { color: #10b981; }
.stat-icon.logouts { color: #f59e0b; }
.stat-icon.creations { color: #8b5cf6; }
.stat-icon.today { color: #ef4444; }

/* Activity Chart */
.activity-chart {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #eef3f8;
    margin-bottom: 24px;
}

.activity-chart h3 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1f3149;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-bars {
    display: flex;
    align-items: flex-end;
    height: 120px;
    gap: 4px;
}

.chart-bar {
    flex: 1;
    background: linear-gradient(180deg, #4f9cf7, #3b82d6);
    border-radius: 4px 4px 0 0;
    min-height: 4px;
    transition: height 0.3s ease;
    position: relative;
}

.chart-bar:hover {
    opacity: 0.8;
}

.chart-labels {
    display: flex;
    margin-top: 8px;
    gap: 4px;
}

.chart-label {
    flex: 1;
    text-align: center;
    font-size: 0.6rem;
    color: #8b9bb5;
}

/* Filter Bar */
.filter-bar {
    background: white;
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 24px;
    border: 1px solid #eef3f8;
}

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f8faff;
    border: 1px solid #e8edf4;
    border-radius: 10px;
    padding: 0 14px;
    transition: all 0.2s ease;
    flex: 1;
    min-width: 150px;
}

.filter-group:focus-within {
    border-color: #4f9cf7;
    box-shadow: 0 0 0 3px rgba(79, 156, 247, 0.1);
    background: white;
}

.filter-group i {
    color: #8b9bb5;
    font-size: 0.85rem;
}

.filter-group input,
.filter-group select {
    border: none;
    padding: 10px 0;
    background: transparent;
    font-size: 0.85rem;
    color: #1f3149;
    width: 100%;
    outline: none;
}

.filter-group select {
    cursor: pointer;
    appearance: none;
    padding-right: 20px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238b9bb5' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right center;
}

.filter-group.date-range {
    display: flex;
    gap: 4px;
    min-width: 200px;
}

.filter-group.date-range input {
    min-width: 80px;
}

.filter-group.date-range span {
    color: #8b9bb5;
    font-size: 0.8rem;
}

.filter-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-actions .btn-primary,
.filter-actions .btn-secondary {
    padding: 9px 18px;
    font-size: 0.85rem;
}

/* Table */
.table-container {
    background: white;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.table-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #f0f4fa;
    flex-wrap: wrap;
    gap: 12px;
}

.toolbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.toolbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.8rem;
    color: #6d83a5;
}

/* Data Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.data-table thead {
    background: #f8faff;
    border-bottom: 2px solid #eef3f8;
}

.data-table thead th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #405473;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
    background: #f8faff;
    z-index: 2;
}

.data-table thead th a {
    color: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.data-table thead th a:hover {
    color: #4f9cf7;
}

.data-table tbody tr {
    transition: background 0.15s;
    border-bottom: 1px solid #f5f8fc;
}

.data-table tbody tr:last-child {
    border-bottom: none;
}

.data-table tbody tr:hover {
    background: #f8faff;
}

.data-table tbody td {
    padding: 12px 16px;
    vertical-align: middle;
    color: #1f3149;
}

/* Activity Type Badge */
.activity-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    padding: 3px 12px;
    border-radius: 30px;
    font-weight: 500;
}

.activity-badge.login { background: #d1fae5; color: #065f46; }
.activity-badge.logout { background: #fef3c7; color: #92400e; }
.activity-badge.tenant_created { background: #dbeafe; color: #1e40af; }
.activity-badge.tenant_updated { background: #ede9fe; color: #5b21b6; }
.activity-badge.user_created { background: #d1fae5; color: #065f46; }
.activity-badge.user_updated { background: #ede9fe; color: #5b21b6; }
.activity-badge.user_deleted { background: #fee2e2; color: #991b1b; }
.activity-badge.election_created { background: #fef3c7; color: #92400e; }
.activity-badge.settings_changed { background: #fce4ec; color: #c62828; }
.activity-badge.default { background: #f3f4f6; color: #4b5563; }

/* Device Info */
.device-info {
    font-size: 0.75rem;
    color: #6d83a5;
}

.device-info .device-icon {
    margin-right: 4px;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-top: 1px solid #f0f4fa;
    flex-wrap: wrap;
    gap: 12px;
}

.pagination-info {
    font-size: 0.85rem;
    color: #6d83a5;
}

.pagination-info strong {
    color: #1f3149;
}

.pagination-links {
    display: flex;
    gap: 4px;
    align-items: center;
}

.pagination-links a,
.pagination-links span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 12px;
    border-radius: 8px;
    font-size: 0.85rem;
    color: #405473;
    text-decoration: none;
    transition: all 0.15s;
}

.pagination-links a:hover {
    background: #f0f5fe;
    color: #4f9cf7;
}

.pagination-links a.active {
    background: #4f9cf7;
    color: white;
}

.pagination-links .ellipsis {
    color: #8b9bb5;
}

/* Responsive */
@media (max-width: 768px) {
    .audit-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        width: 100%;
        min-width: unset;
    }
    
    .filter-group.date-range {
        flex-wrap: wrap;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .filter-actions .btn-primary,
    .filter-actions .btn-secondary {
        width: 100%;
        justify-content: center;
    }
    
    .table-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .data-table {
        font-size: 0.8rem;
        min-width: 900px;
    }
    
    .pagination {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<main class="main-content">
    <!-- ============================================================
    PAGE HEADER
    ============================================================ -->
    <div class="page-header">
        <div class="header-left">
            <h1>
                <i class="fas fa-history" style="color:#4f9cf7;"></i>
                Audit Logs
                <span class="page-badge"><?php echo number_format($total_count); ?></span>
            </h1>
            <p class="subtitle">Complete audit trail of all system activities</p>
        </div>
        <div class="header-actions">
            <button class="btn-secondary" onclick="exportLogs('excel')">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button class="btn-secondary" onclick="exportLogs('pdf')">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- ============================================================
    STATISTICS
    ============================================================ -->
    <div class="audit-stats">
        <div class="stat-card">
            <span class="stat-icon total"><i class="fas fa-database"></i></span>
            <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
            <div class="stat-label">Total Records</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon logins"><i class="fas fa-sign-in-alt"></i></span>
            <div class="stat-number"><?php echo number_format($stats['logins'] ?? 0); ?></div>
            <div class="stat-label">Logins</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon logouts"><i class="fas fa-sign-out-alt"></i></span>
            <div class="stat-number"><?php echo number_format($stats['logouts'] ?? 0); ?></div>
            <div class="stat-label">Logouts</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon creations"><i class="fas fa-plus-circle"></i></span>
            <div class="stat-number"><?php echo number_format($stats['creations'] ?? 0); ?></div>
            <div class="stat-label">Creations</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon today"><i class="fas fa-calendar-day"></i></span>
            <div class="stat-number"><?php echo number_format($stats['today'] ?? 0); ?></div>
            <div class="stat-label">Today</div>
        </div>
    </div>

    <!-- ============================================================
    ACTIVITY CHART
    ============================================================ -->
    <?php if (!empty($hourlyActivity)): ?>
    <div class="activity-chart">
        <h3><i class="fas fa-chart-bar"></i> Today's Activity by Hour</h3>
        <div class="chart-bars">
            <?php 
            $max_count = max(array_column($hourlyActivity, 'count')) ?: 1;
            foreach ($hourlyActivity as $data): 
                $height = ($data['count'] / $max_count) * 100;
            ?>
            <div class="chart-bar" style="height: <?php echo max($height, 4); ?>%;" title="<?php echo $data['hour'] . ':00 - ' . $data['count'] . ' activities'; ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-labels">
            <?php foreach ($hourlyActivity as $data): ?>
            <div class="chart-label"><?php echo sprintf('%02d', $data['hour']); ?>:00</div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
    SEARCH & FILTERS
    ============================================================ -->
    <div class="filter-bar">
        <form method="GET" class="filter-form" id="filterForm">
            <div class="filter-group">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search logs..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <i class="fas fa-tag"></i>
                <select name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($action_types as $type): ?>
                    <option value="<?php echo $type['activity_type']; ?>" <?php echo $filter_action === $type['activity_type'] ? 'selected' : ''; ?>>
                        <?php echo ucfirst(str_replace('_', ' ', $type['activity_type'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <i class="fas fa-user"></i>
                <select name="user">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <i class="fas fa-building"></i>
                <select name="tenant">
                    <option value="">All Tenants</option>
                    <?php foreach ($tenants as $tenant): ?>
                    <option value="<?php echo $tenant['id']; ?>" <?php echo $filter_tenant == $tenant['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tenant['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group date-range">
                <i class="fas fa-calendar"></i>
                <input type="date" name="date_from" placeholder="From" value="<?php echo htmlspecialchars($date_from); ?>">
                <span>to</span>
                <input type="date" name="date_to" placeholder="To" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="audit-logs.php" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>

    <!-- ============================================================
    LOGS TABLE
    ============================================================ -->
    <div class="table-container">
        <div class="table-toolbar">
            <div class="toolbar-left">
                <span style="font-size:0.85rem; color:#6d83a5;">
                    <i class="fas fa-clock"></i> Real-time audit trail
                </span>
            </div>
            <div class="toolbar-right">
                <span><i class="fas fa-database"></i> <?php echo number_format($total_count); ?> records</span>
                <span><i class="fas fa-arrow-up"></i> <?php echo ucfirst($sort_by); ?> (<?php echo $sort_order; ?>)</span>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:50px;">
                        <a href="?sort=id&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($filter_action); ?>&user=<?php echo urlencode($filter_user); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&page=<?php echo $page; ?>">
                            ID
                            <?php if ($sort_by === 'id'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th>Device</th>
                    <th>Tenant</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="8" class="empty-table">
                        <i class="fas fa-inbox" style="font-size:3rem; color:#dce6f0; display:block; margin-bottom:16px;"></i>
                        <h3>No logs found</h3>
                        <p>Try adjusting your filters or search criteria.</p>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><span style="font-weight:600; color:#4f9cf7;">#<?php echo $log['id']; ?></span></td>
                    <td>
                        <span style="font-size:0.85rem;">
                            <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                        </span>
                        <span style="font-size:0.7rem; color:#8b9bb5; display:block;">
                            <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($log['user_name']): ?>
                        <div style="font-weight:500; color:#0b1a33;">
                            <?php echo htmlspecialchars($log['user_name']); ?>
                        </div>
                        <div style="font-size:0.7rem; color:#8b9bb5;">
                            <?php echo htmlspecialchars($log['user_email']); ?>
                        </div>
                        <?php else: ?>
                        <span style="color:#8b9bb5; font-size:0.85rem;">System</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="activity-badge <?php echo $log['activity_type'] ?? 'default'; ?>">
                            <?php if ($log['activity_type'] === 'login'): ?>
                            <i class="fas fa-sign-in-alt"></i>
                            <?php elseif ($log['activity_type'] === 'logout'): ?>
                            <i class="fas fa-sign-out-alt"></i>
                            <?php elseif (strpos($log['activity_type'], 'created') !== false): ?>
                            <i class="fas fa-plus-circle"></i>
                            <?php elseif (strpos($log['activity_type'], 'updated') !== false): ?>
                            <i class="fas fa-edit"></i>
                            <?php elseif (strpos($log['activity_type'], 'deleted') !== false): ?>
                            <i class="fas fa-trash"></i>
                            <?php else: ?>
                            <i class="fas fa-circle"></i>
                            <?php endif; ?>
                            <?php echo ucfirst(str_replace('_', ' ', $log['activity_type'] ?? 'Unknown')); ?>
                        </span>
                    </td>
                    <td>
                        <div style="max-width:200px; word-wrap:break-word;">
                            <?php echo htmlspecialchars($log['description']); ?>
                        </div>
                        <?php if ($log['entity_type']): ?>
                        <div style="font-size:0.65rem; color:#8b9bb5; margin-top:2px;">
                            <?php echo htmlspecialchars($log['entity_type']); ?>
                            <?php if ($log['entity_id']): ?>
                            #<?php echo $log['entity_id']; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-size:0.8rem; font-family:monospace;">
                            <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                        </span>
                    </td>
                    <td>
                        <div class="device-info">
                            <?php if ($log['device_id']): ?>
                            <i class="fas fa-laptop device-icon"></i>
                            <span style="font-size:0.7rem;">
                                <?php echo substr(htmlspecialchars($log['device_id']), 0, 12); ?>...
                            </span>
                            <?php else: ?>
                            <span style="color:#8b9bb5; font-size:0.7rem;">N/A</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($log['tenant_name']): ?>
                        <span style="font-size:0.8rem; color:#1f3149;">
                            <?php echo htmlspecialchars($log['tenant_name']); ?>
                        </span>
                        <?php else: ?>
                        <span style="color:#8b9bb5; font-size:0.8rem;">System</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- ============================================================
        PAGINATION
        ============================================================ -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <strong><?php echo $offset + 1; ?></strong> to 
                <strong><?php echo min($offset + $per_page, $total_count); ?></strong> 
                of <strong><?php echo number_format($total_count); ?></strong> logs
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                <a href="?page=1&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($filter_action); ?>&user=<?php echo urlencode($filter_user); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($filter_action); ?>&user=<?php echo urlencode($filter_user); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                    <i class="fas fa-angle-left"></i>
                </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<span class="ellipsis">…</span>';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active = $i === $page ? 'active' : '';
                    echo '<a href="?page=' . $i . '&sort=' . urlencode($sort_by) . '&order=' . urlencode($sort_order) . '&search=' . urlencode($search) . '&action=' . urlencode($filter_action) . '&user=' . urlencode($filter_user) . '&tenant=' . urlencode($filter_tenant) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '" class="' . $active . '">' . $i . '</a>';
                }
                
                if ($end_page < $total_pages) {
                    echo '<span class="ellipsis">…</span>';
                }
                ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($filter_action); ?>&user=<?php echo urlencode($filter_user); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($filter_action); ?>&user=<?php echo urlencode($filter_user); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
// ============================================================
// EXPORT LOGS
// ============================================================
function exportLogs(format) {
    const search = document.querySelector('input[name="search"]')?.value || '';
    const action = document.querySelector('select[name="action"]')?.value || '';
    const user = document.querySelector('select[name="user"]')?.value || '';
    const tenant = document.querySelector('select[name="tenant"]')?.value || '';
    const date_from = document.querySelector('input[name="date_from"]')?.value || '';
    const date_to = document.querySelector('input[name="date_to"]')?.value || '';
    
    window.location.href = `audit-export.php?format=${format}&search=${encodeURIComponent(search)}&action=${encodeURIComponent(action)}&user=${encodeURIComponent(user)}&tenant=${encodeURIComponent(tenant)}&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}`;
}

// ============================================================
// AUTO-SUBMIT ON FILTER CHANGE
// ============================================================
document.querySelectorAll('select[name="action"], select[name="user"], select[name="tenant"]').forEach(select => {
    select.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// ============================================================
// DATE INPUT AUTO-SUBMIT
// ============================================================
let filterTimeout;
document.querySelectorAll('input[name="date_from"], input[name="date_to"]').forEach(input => {
    input.addEventListener('change', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 500);
    });
});
</script>

<?php include 'includes/footer.php'; ?>