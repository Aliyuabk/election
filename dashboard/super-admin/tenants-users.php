<?php
// ============================================================
// TENANT USERS - SUPER ADMINISTRATOR (PRO STYLE)
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
// FETCH USERS WITH PAGINATION & FILTERS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Build WHERE clause
$where_conditions = ["u.tenant_id = ?", "u.deleted_at IS NULL"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

if (!empty($role_filter)) {
    $where_conditions[] = "u.role_id = ?";
    $params[] = $role_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM users u WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_users / $limit);

// Fetch users
$sql = "
    SELECT 
        u.*,
        r.name as role_name,
        r.level as role_level
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE $where_clause
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// ============================================================
// FETCH ROLES FOR FILTER
// ============================================================
$roles = [];
try {
    $stmt = $db->query("SELECT id, name, level FROM roles WHERE is_active = 1 ORDER BY name");
    $roles = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// HANDLE USER ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    try {
        switch ($action) {
            case 'suspend':
                $stmt = $db->prepare("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$user_id, $tenant_id]);
                if ($stmt->rowCount() > 0) {
                    $action_result = ['success' => true, 'message' => 'User suspended successfully.'];
                    logActivity(SessionManager::get('user_id'), 'user_suspended', "Suspended user ID: $user_id");
                }
                break;
            case 'activate':
                $stmt = $db->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$user_id, $tenant_id]);
                if ($stmt->rowCount() > 0) {
                    $action_result = ['success' => true, 'message' => 'User activated successfully.'];
                    logActivity(SessionManager::get('user_id'), 'user_activated', "Activated user ID: $user_id");
                }
                break;
            case 'delete':
                $stmt = $db->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$user_id, $tenant_id]);
                if ($stmt->rowCount() > 0) {
                    $action_result = ['success' => true, 'message' => 'User deleted successfully.'];
                    logActivity(SessionManager::get('user_id'), 'user_deleted', "Deleted user ID: $user_id");
                }
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
    
    // Refresh the page to show updated data
    if ($action_result['success']) {
        header("Location: tenants-users.php?id=$tenant_id");
        exit();
    }
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       TENANT USERS - PRO STYLES
       ============================================================ */
    
    /* Tenant Profile Header */
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
        background: linear-gradient(90deg, var(--primary), var(--secondary));
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
        color: var(--primary);
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
    .tenant-profile-header .tenant-info .tenant-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .tenant-profile-header .tenant-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* Stats Summary */
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
        color: var(--primary);
    }
    .stats-summary .stat-item .number.green { color: var(--secondary); }
    .stats-summary .stat-item .number.red { color: var(--danger); }
    .stats-summary .stat-item .number.yellow { color: var(--warning); }
    .stats-summary .stat-item .number.purple { color: #8B5CF6; }
    .stats-summary .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 2px;
    }

    /* Filter Bar */
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
    .filter-bar-pro .search-wrap input::placeholder {
        color: var(--gray-400);
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

    /* Table Container */
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
    .table-container .table-header .table-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
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

    /* User Avatar */
    .user-avatar-sm {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.8rem;
        flex-shrink: 0;
        color: white;
    }
    .user-avatar-sm.blue { background: #3B82F6; }
    .user-avatar-sm.green { background: #10B981; }
    .user-avatar-sm.purple { background: #8B5CF6; }
    .user-avatar-sm.orange { background: #F59E0B; }
    .user-avatar-sm.red { background: #EF4444; }
    .user-avatar-sm.pink { background: #EC4899; }
    .user-avatar-sm.teal { background: #14B8A6; }

    .user-info-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .user-info-cell .name {
        font-weight: 500;
        font-size: 0.85rem;
    }
    .user-info-cell .code {
        font-size: 0.65rem;
        color: var(--gray-400);
    }

    /* Badges */
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.suspended { background: #FEF2F2; color: #991B1B; }
    .badge-status.suspended .dot { background: #EF4444; }
    .badge-status.pending { background: #FFFBEB; color: #92400E; }
    .badge-status.pending .dot { background: #F59E0B; }
    .badge-status.archived { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.archived .dot { background: var(--gray-400); }

    .badge-role {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
        background: #EFF6FF;
        color: #1E40AF;
    }
    .badge-role.super_admin { background: #F5F3FF; color: #5B21B6; }
    .badge-role.client_admin { background: #ECFDF5; color: #065F46; }
    .badge-role.national { background: #EFF6FF; color: #1E40AF; }
    .badge-role.state { background: #FFFBEB; color: #92400E; }
    .badge-role.lga { background: #FEF2F2; color: #991B1B; }
    .badge-role.ward { background: #ECFDF5; color: #065F46; }
    .badge-role.pu_agent { background: #F5F3FF; color: #5B21B6; }

    /* Action Dropdown */
    .action-dropdown-pro {
        position: relative;
        display: inline-block;
    }
    .action-dropdown-pro .dropdown-btn {
        background: none;
        border: none;
        padding: 4px 8px;
        cursor: pointer;
        color: var(--gray-400);
        font-size: 1.1rem;
        transition: var(--transition);
        border-radius: 6px;
    }
    .action-dropdown-pro .dropdown-btn:hover {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .action-dropdown-pro .dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        border: 1px solid var(--gray-200);
        min-width: 180px;
        padding: 6px;
        display: none;
        z-index: 50;
        animation: dropdownFade 0.2s ease;
    }
    @keyframes dropdownFade {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .action-dropdown-pro .dropdown-menu.open { display: block; }
    .action-dropdown-pro .dropdown-menu a,
    .action-dropdown-pro .dropdown-menu button {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 14px;
        width: 100%;
        border: none;
        background: none;
        font-family: 'Inter', sans-serif;
        font-size: 0.8rem;
        color: var(--gray-600);
        cursor: pointer;
        border-radius: 8px;
        transition: var(--transition);
        text-decoration: none;
    }
    .action-dropdown-pro .dropdown-menu a:hover,
    .action-dropdown-pro .dropdown-menu button:hover {
        background: var(--gray-50);
        color: var(--primary);
    }
    .action-dropdown-pro .dropdown-menu .danger:hover {
        background: #FEF2F2;
        color: var(--danger);
    }
    .action-dropdown-pro .dropdown-menu i {
        width: 16px;
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .action-dropdown-pro .dropdown-menu .divider {
        height: 1px;
        background: var(--gray-100);
        margin: 4px 8px;
    }

    /* Pagination */
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
    .pagination-pro .info strong {
        color: var(--gray-700);
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
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .pagination-pro .pages .disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    /* Empty State */
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
    .empty-state-pro p {
        font-size: 0.85rem;
        color: var(--gray-400);
    }

    /* Toast Messages */
    .toast-container {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 999;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        animation: slideIn 0.3s ease;
        min-width: 280px;
        max-width: 400px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    @keyframes slideIn {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    /* Responsive */
    @media (max-width: 992px) {
        .stats-summary {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 768px) {
        .tenant-profile-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .tenant-profile-header .tenant-actions {
            margin-left: 0;
            width: 100%;
        }
        .filter-bar-pro {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-bar-pro .search-wrap {
            min-width: auto;
        }
        .filter-bar-pro select {
            width: 100%;
        }
        .stats-summary {
            grid-template-columns: repeat(2, 1fr);
        }
        .table-container .table-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 8px 12px; }
        .pagination-pro {
            flex-direction: column;
            align-items: center;
        }
        .table-container { overflow-x: auto; }
    }
    @media (max-width: 480px) {
        .stats-summary {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .stats-summary .stat-item {
            padding: 10px 12px;
        }
        .stats-summary .stat-item .number {
            font-size: 1.2rem;
        }
        .tenant-profile-header {
            padding: 16px;
        }
        .tenant-profile-header .tenant-avatar {
            width: 48px;
            height: 48px;
            font-size: 1.2rem;
        }
        .tenant-profile-header .tenant-info h2 {
            font-size: 1rem;
        }
        .data-table th, .data-table td { padding: 6px 8px; font-size: 0.7rem; }
        .user-avatar-sm {
            width: 30px;
            height: 30px;
            font-size: 0.65rem;
        }
        .badge-status { font-size: 0.6rem; padding: 2px 8px; }
        .badge-role { font-size: 0.6rem; padding: 1px 8px; }
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
                    <span><i class="fas fa-users"></i> <?php echo number_format($total_users); ?> Users</span>
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
        $total_active = 0;
        $total_suspended = 0;
        $total_pending = 0;
        foreach ($users as $u) {
            if ($u['status'] === 'active') $total_active++;
            elseif ($u['status'] === 'suspended') $total_suspended++;
            elseif ($u['status'] === 'pending') $total_pending++;
        }
        ?>
        <div class="stats-summary">
            <div class="stat-item">
                <div class="number"><?php echo number_format($total_users); ?></div>
                <div class="label">Total Users</div>
            </div>
            <div class="stat-item">
                <div class="number green"><?php echo number_format($total_active); ?></div>
                <div class="label">Active</div>
            </div>
            <div class="stat-item">
                <div class="number red"><?php echo number_format($total_suspended); ?></div>
                <div class="label">Suspended</div>
            </div>
            <div class="stat-item">
                <div class="number yellow"><?php echo number_format($total_pending); ?></div>
                <div class="label">Pending</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar-pro">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <input type="hidden" name="id" value="<?php echo $tenant_id; ?>">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search users by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
                <select name="role">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>" <?php echo $role_filter == $role['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($status_filter) || !empty($role_filter)): ?>
                    <a href="tenants-users.php?id=<?php echo $tenant_id; ?>" class="btn-clear">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-users" style="color:var(--primary);"></i> Users
                    <span class="count"><?php echo number_format($total_users); ?></span>
                </div>
                <div class="table-actions">
                    <a href="users-create.php?tenant=<?php echo $tenant_id; ?>" class="btn-primary" style="padding:6px 14px;font-size:0.8rem;">
                        <i class="fas fa-plus-circle"></i> Add User
                    </a>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>User</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th style="width:60px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php 
                        $avatar_colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
                        foreach ($users as $index => $user): 
                            $color_idx = $index % count($avatar_colors);
                            $avatar_color = $avatar_colors[$color_idx];
                        ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div class="user-info-cell">
                                        <div class="user-avatar-sm <?php echo $avatar_color; ?>">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                            <div class="code"><?php echo htmlspecialchars($user['user_code']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:0.8rem;"><?php echo htmlspecialchars($user['email']); ?></div>
                                    <div style="font-size:0.7rem;color:var(--gray-400);"><?php echo htmlspecialchars($user['phone']); ?></div>
                                </td>
                                <td>
                                    <span class="badge-role <?php echo $user['role_level'] ?? ''; ?>">
                                        <?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $user['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-dropdown-pro">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="users-view.php?id=<?php echo $user['id']; ?>">
                                                <i class="fas fa-eye"></i> View Profile
                                            </a>
                                            <a href="users-edit.php?id=<?php echo $user['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit User
                                            </a>
                                            <div class="divider"></div>
                                            <?php if ($user['status'] === 'active'): ?>
                                                <form method="POST" action="" style="display:inline;width:100%;">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="danger" onclick="return confirm('Suspend this user?')">
                                                        <i class="fas fa-pause"></i> Suspend
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="" style="display:inline;width:100%;">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" onclick="return confirm('Activate this user?')">
                                                        <i class="fas fa-play"></i> Activate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="" style="display:inline;width:100%;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="danger" onclick="return confirm('Delete this user? This action can be reversed.')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state-pro">
                                    <i class="fas fa-users"></i>
                                    <h4>No users found</h4>
                                    <p>This tenant has no users yet. <a href="users-create.php?tenant=<?php echo $tenant_id; ?>" style="color:var(--primary);text-decoration:none;">Create one now</a>.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-pro">
            <div class="info">
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_users); ?></strong> of <strong><?php echo number_format($total_users); ?></strong> users
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?id=<?php echo $tenant_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&role=<?php echo urlencode($role_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?id=' . $tenant_id . '&page=1&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&role=' . urlencode($role_filter) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?id=<?php echo $tenant_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&role=<?php echo urlencode($role_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?id=' . $tenant_id . '&page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&role=' . urlencode($role_filter) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?id=<?php echo $tenant_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&role=<?php echo urlencode($role_filter); ?>">
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

// ============================================================
// ACTION DROPDOWN
// ============================================================
function toggleDropdown(btn) {
    var menu = btn.nextElementSibling;
    var isOpen = menu.classList.contains('open');
    document.querySelectorAll('.action-dropdown-pro .dropdown-menu').forEach(function(m) {
        m.classList.remove('open');
    });
    if (!isOpen) {
        menu.classList.toggle('open');
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown-pro')) {
        document.querySelectorAll('.action-dropdown-pro .dropdown-menu').forEach(function(m) {
            m.classList.remove('open');
        });
    }
});

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