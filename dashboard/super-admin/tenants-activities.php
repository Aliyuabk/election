<?php
// ============================================================
// TENANT ACTIVITIES - SUPER ADMINISTRATOR (PRO STYLE)
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

// Check role - only super_admin can access this page
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// ============================================================
// GET TENANT ID
// ============================================================
$tenant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($tenant_id <= 0) {
    header('Location: tenants.php');
    exit();
}

// ============================================================
// FETCH TENANT DETAILS
// ============================================================
$tenant = null;
try {
    $stmt = $db->prepare("SELECT id, name, slug, logo_url FROM tenants WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$tenant) {
    header('Location: tenants.php');
    exit();
}

// ============================================================
// FETCH ACTIVITIES WITH PAGINATION & FILTERS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';

// Build WHERE clause
$where_conditions = ["a.tenant_id = ?"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(a.description LIKE ? OR a.activity_type LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($type_filter)) {
    $where_conditions[] = "a.activity_type = ?";
    $params[] = $type_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM activity_logs a WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_activities = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_activities / $limit);

// Fetch activities
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
$activities = $stmt->fetchAll();

// ============================================================
// GET ACTIVITY TYPES FOR FILTER
// ============================================================
$activity_types = [];
try {
    $stmt = $db->prepare("SELECT DISTINCT activity_type FROM activity_logs WHERE tenant_id = ? ORDER BY activity_type");
    $stmt->execute([$tenant_id]);
    $activity_types = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       TENANT ACTIVITIES - PRO STYLES
       ============================================================ */
    
    .tenant-profile-header {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .tenant-profile-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #F59E0B, #EF4444);
    }
    .tenant-profile-header .tenant-avatar {
        width: 60px;
        height: 60px;
        border-radius: 14px;
        background: var(--gray-100);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        font-weight: 700;
        color: #F59E0B;
        overflow: hidden;
        flex-shrink: 0;
        border: 2px solid var(--gray-200);
    }
    .tenant-profile-header .tenant-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .tenant-profile-header .tenant-info h2 {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 2px;
    }
    .tenant-profile-header .tenant-info .tenant-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 0.8rem;
        color: var(--gray-500);
    }
    .tenant-profile-header .tenant-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .stats-summary .stat-item {
        background: white;
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
    }
    .stats-summary .stat-item:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
    }
    .stats-summary .stat-item .number {
        font-size: 1.5rem;
        font-weight: 700;
        color: #F59E0B;
    }
    .stats-summary .stat-item .number.green { color: var(--secondary); }
    .stats-summary .stat-item .number.blue { color: #3B82F6; }
    .stats-summary .stat-item .number.purple { color: #8B5CF6; }
    .stats-summary .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 2px;
    }

    .filter-bar-pro {
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
    .filter-bar-pro .search-wrap {
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
    .filter-bar-pro .search-wrap:focus-within {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .filter-bar-pro .search-wrap i {
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .filter-bar-pro .search-wrap input {
        border: none;
        outline: none;
        background: transparent;
        padding: 6px 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        width: 100%;
        color: var(--gray-700);
    }
    .filter-bar-pro select {
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
    .filter-bar-pro select:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
    }
    .filter-bar-pro .btn-filter {
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
    .filter-bar-pro .btn-filter:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .filter-bar-pro .btn-clear {
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
    .filter-bar-pro .btn-clear:hover {
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
        background: #F59E0B;
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
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        border-bottom: 1px solid var(--gray-200);
        white-space: nowrap;
    }
    .data-table tbody td {
        padding: 10px 16px;
        border-bottom: 1px solid var(--gray-100);
        vertical-align: middle;
    }
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    .data-table tbody tr:hover {
        background: var(--gray-50);
    }

    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    }
    .activity-icon.login { background: #EFF6FF; color: #3B82F6; }
    .activity-icon.tenant { background: #ECFDF5; color: #10B981; }
    .activity-icon.user { background: #F5F3FF; color: #8B5CF6; }
    .activity-icon.backup { background: #FFFBEB; color: #F59E0B; }
    .activity-icon.system { background: #FEF2F2; color: #EF4444; }
    .activity-icon.settings { background: #EFF6FF; color: #3B82F6; }
    .activity-icon.election { background: #ECFDF5; color: #10B981; }
    .activity-icon.security { background: #FEF2F2; color: #EF4444; }
    .activity-icon.logout { background: #FEF2F2; color: #EF4444; }
    .activity-icon.password { background: #FFFBEB; color: #F59E0B; }
    .activity-icon.inec { background: #F5F3FF; color: #8B5CF6; }
    .activity-icon.billing { background: #EFF6FF; color: #3B82F6; }
    .activity-icon.api { background: #ECFDF5; color: #10B981; }

    .activity-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .activity-cell .activity-desc {
        font-size: 0.82rem;
        color: var(--gray-700);
        line-height: 1.4;
    }

    .user-avatar-xs {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.6rem;
        color: white;
        flex-shrink: 0;
    }
    .user-avatar-xs.blue { background: #3B82F6; }
    .user-avatar-xs.green { background: #10B981; }
    .user-avatar-xs.purple { background: #8B5CF6; }
    .user-avatar-xs.orange { background: #F59E0B; }
    .user-avatar-xs.red { background: #EF4444; }
    .user-avatar-xs.pink { background: #EC4899; }
    .user-avatar-xs.teal { background: #14B8A6; }

    .user-cell {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .user-cell .user-name {
        font-size: 0.8rem;
        font-weight: 500;
    }
    .user-cell .user-email {
        font-size: 0.65rem;
        color: var(--gray-400);
    }

    .badge-type-sm {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 500;
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .badge-type-sm.login { background: #EFF6FF; color: #3B82F6; }
    .badge-type-sm.tenant { background: #ECFDF5; color: #10B981; }
    .badge-type-sm.user { background: #F5F3FF; color: #8B5CF6; }
    .badge-type-sm.backup { background: #FFFBEB; color: #F59E0B; }
    .badge-type-sm.system { background: #FEF2F2; color: #EF4444; }
    .badge-type-sm.settings { background: #EFF6FF; color: #3B82F6; }
    .badge-type-sm.election { background: #ECFDF5; color: #10B981; }
    .badge-type-sm.security { background: #FEF2F2; color: #EF4444; }

    .ip-address {
        font-family: 'Courier New', monospace;
        font-size: 0.75rem;
        color: var(--gray-500);
        background: var(--gray-50);
        padding: 2px 8px;
        border-radius: 4px;
        display: inline-block;
    }

    .timestamp {
        font-size: 0.75rem;
        color: var(--gray-500);
        white-space: nowrap;
    }
    .timestamp .date {
        font-weight: 500;
        color: var(--gray-600);
    }

    .pagination-pro {
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
    .pagination-pro .info {
        font-size: 0.82rem;
        color: var(--gray-500);
    }
    .pagination-pro .pages {
        display: flex;
        gap: 4px;
        align-items: center;
    }
    .pagination-pro .pages a,
    .pagination-pro .pages span {
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
    .pagination-pro .pages a:hover {
        background: var(--gray-100);
        border-color: var(--gray-200);
    }
    .pagination-pro .pages .active {
        background: #F59E0B;
        color: white;
        border-color: #F59E0B;
    }
    .pagination-pro .pages .disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .empty-state-pro {
        text-align: center;
        padding: 48px 20px;
        color: var(--gray-500);
    }
    .empty-state-pro i {
        font-size: 3rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 12px;
    }
    .empty-state-pro h4 {
        color: var(--gray-700);
        margin-bottom: 4px;
        font-size: 1rem;
    }

    @media (max-width: 992px) {
        .stats-summary { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 768px) {
        .tenant-profile-header { flex-direction: column; align-items: flex-start; }
        .tenant-profile-header .tenant-actions { margin-left: 0; width: 100%; }
        .filter-bar-pro { flex-direction: column; align-items: stretch; }
        .filter-bar-pro .search-wrap { min-width: auto; }
        .filter-bar-pro select { width: 100%; }
        .stats-summary { grid-template-columns: repeat(2, 1fr); }
        .table-container .table-header { flex-direction: column; align-items: flex-start; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 8px 12px; }
        .pagination-pro { flex-direction: column; align-items: center; }
        .table-container { overflow-x: auto; }
        .activity-cell .activity-desc { font-size: 0.75rem; }
    }
    @media (max-width: 480px) {
        .stats-summary { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stats-summary .stat-item { padding: 10px 12px; }
        .stats-summary .stat-item .number { font-size: 1.2rem; }
        .tenant-profile-header { padding: 16px; }
        .data-table th, .data-table td { padding: 6px 8px; font-size: 0.7rem; }
        .activity-icon { width: 28px; height: 28px; font-size: 0.7rem; }
        .user-avatar-xs { width: 22px; height: 22px; font-size: 0.5rem; }
        .badge-type-sm { font-size: 0.55rem; padding: 1px 6px; }
        .ip-address { font-size: 0.65rem; }
        .timestamp { font-size: 0.65rem; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Tenant Profile Header -->
        <div class="tenant-profile-header">
            <div class="tenant-avatar">
                <?php if (!empty($tenant['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($tenant['logo_url']); ?>" alt="<?php echo htmlspecialchars($tenant['name']); ?>">
                <?php else: ?>
                    <?php echo strtoupper(substr($tenant['name'], 0, 2)); ?>
                <?php endif; ?>
            </div>
            <div class="tenant-info">
                <h2><?php echo htmlspecialchars($tenant['name']); ?></h2>
                <div class="tenant-meta">
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($tenant['slug']); ?></span>
                    <span><i class="fas fa-clock"></i> <?php echo number_format($total_activities); ?> Activities</span>
                </div>
            </div>
            <div class="tenant-actions">
                <a href="tenants-view.php?id=<?php echo $tenant_id; ?>" class="btn-outline" style="padding:8px 16px;font-size:0.8rem;">
                    <i class="fas fa-eye"></i> View Tenant
                </a>
                <a href="tenants.php" class="btn-outline" style="padding:8px 16px;font-size:0.8rem;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats Summary -->
        <?php
        $today_activities = 0;
        $this_week = 0;
        $unique_users = [];
        foreach ($activities as $a) {
            $created_at = strtotime($a['created_at']);
            if (date('Y-m-d', $created_at) === date('Y-m-d')) $today_activities++;
            if (date('W', $created_at) === date('W')) $this_week++;
            if ($a['user_id']) $unique_users[$a['user_id']] = true;
        }
        ?>
        <div class="stats-summary">
            <div class="stat-item"><div class="number"><?php echo number_format($total_activities); ?></div><div class="label">Total</div></div>
            <div class="stat-item"><div class="number green"><?php echo number_format($today_activities); ?></div><div class="label">Today</div></div>
            <div class="stat-item"><div class="number blue"><?php echo number_format($this_week); ?></div><div class="label">This Week</div></div>
            <div class="stat-item"><div class="number purple"><?php echo number_format(count($unique_users)); ?></div><div class="label">Active Users</div></div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar-pro">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <input type="hidden" name="id" value="<?php echo $tenant_id; ?>">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search activities..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="type">
                    <option value="">All Types</option>
                    <?php foreach ($activity_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type['activity_type']); ?>" <?php echo $type_filter === $type['activity_type'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $type['activity_type'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || !empty($type_filter)): ?>
                    <a href="tenants-activities.php?id=<?php echo $tenant_id; ?>" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Activities Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-clock" style="color:#F59E0B;"></i> Activity Log
                    <span class="count"><?php echo number_format($total_activities); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Activity</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>IP</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($activities) > 0): ?>
                        <?php foreach ($activities as $index => $activity): ?>
                            <?php 
                                $iconClass = 'system';
                                $icon = 'fa-cog';
                                $type = $activity['activity_type'] ?? '';
                                if (strpos($type, 'login') !== false) { $iconClass = 'login'; $icon = 'fa-sign-in-alt'; }
                                elseif (strpos($type, 'logout') !== false) { $iconClass = 'logout'; $icon = 'fa-sign-out-alt'; }
                                elseif (strpos($type, 'tenant') !== false) { $iconClass = 'tenant'; $icon = 'fa-building'; }
                                elseif (strpos($type, 'user') !== false) { $iconClass = 'user'; $icon = 'fa-user'; }
                                elseif (strpos($type, 'backup') !== false) { $iconClass = 'backup'; $icon = 'fa-archive'; }
                                elseif (strpos($type, 'election') !== false) { $iconClass = 'election'; $icon = 'fa-vote-yea'; }
                                elseif (strpos($type, 'security') !== false || strpos($type, 'password') !== false) { $iconClass = 'security'; $icon = 'fa-shield-alt'; }
                                elseif (strpos($type, 'settings') !== false) { $iconClass = 'settings'; $icon = 'fa-cog'; }
                                elseif (strpos($type, 'inec') !== false) { $iconClass = 'inec'; $icon = 'fa-database'; }
                                elseif (strpos($type, 'billing') !== false || strpos($type, 'invoice') !== false) { $iconClass = 'billing'; $icon = 'fa-file-invoice'; }
                                elseif (strpos($type, 'api') !== false) { $iconClass = 'api'; $icon = 'fa-code'; }
                                
                                $avatar_color = 'blue';
                                $avatar_colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
                                if (!empty($activity['user_id'])) {
                                    $avatar_color = $avatar_colors[($activity['user_id'] % 7)];
                                }
                            ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div class="activity-cell">
                                        <div class="activity-icon <?php echo $iconClass; ?>">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="activity-desc"><?php echo htmlspecialchars($activity['description'] ?? 'Activity'); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-cell">
                                        <?php if (!empty($activity['user_id'])): ?>
                                            <div class="user-avatar-xs <?php echo $avatar_color; ?>">
                                                <?php $name = $activity['user_name'] ?? 'U'; echo strtoupper(substr($name, 0, 2)); ?>
                                            </div>
                                            <div>
                                                <div class="user-name"><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></div>
                                                <div class="user-email"><?php echo htmlspecialchars($activity['user_email'] ?? ''); ?></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="user-avatar-xs" style="background:var(--gray-300);"><i class="fas fa-robot" style="font-size:0.6rem;"></i></div>
                                            <div><div class="user-name" style="color:var(--gray-500);">System</div><div class="user-email">Automated</div></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><span class="badge-type-sm <?php echo str_replace('_', '', $type); ?>"><?php echo ucfirst(str_replace('_', ' ', $type)); ?></span></td>
                                <td><span class="ip-address"><?php echo htmlspecialchars($activity['ip_address'] ?? '—'); ?></span></td>
                                <td>
                                    <div class="timestamp">
                                        <div class="date"><?php echo date('M j, Y', strtotime($activity['created_at'])); ?></div>
                                        <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo date('g:i:s A', strtotime($activity['created_at'])); ?></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6"><div class="empty-state-pro"><i class="fas fa-clock"></i><h4>No activities found</h4></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-pro">
            <div class="info">Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_activities); ?></strong> of <strong><?php echo number_format($total_activities); ?></strong></div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?id=<?php echo $tenant_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) { echo '<a href="?id=' . $tenant_id . '&page=1&search=' . urlencode($search) . '&type=' . urlencode($type_filter) . '">1</a>'; if ($start_page > 2) echo '<span>…</span>'; }
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?id=<?php echo $tenant_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor;
                if ($end_page < $total_pages) { if ($end_page < $total_pages - 1) echo '<span>…</span>'; echo '<a href="?id=' . $tenant_id . '&page=' . $total_pages . '&search=' . urlencode($search) . '&type=' . urlencode($type_filter) . '">' . $total_pages . '</a>'; } ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?id=<?php echo $tenant_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Same JS as tenants-users.php
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