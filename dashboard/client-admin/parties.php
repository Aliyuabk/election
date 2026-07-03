<?php
// ============================================================
// PARTIES MANAGEMENT - CLIENT ADMIN (PROFESSIONAL UI)
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
// HANDLE ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'delete_party':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid party ID.');
                
                // Check if party has candidates
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM candidates WHERE party_id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                $count = $stmt->fetch()['count'] ?? 0;
                
                if ($count > 0) {
                    throw new Exception("Cannot delete party. It has $count candidates assigned.");
                }
                
                $stmt = $db->prepare("DELETE FROM political_parties WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                logActivity($user_id, 'party_deleted', "Deleted party ID: $id");
                $action_result = ['success' => true, 'message' => 'Party deleted successfully.'];
                break;
                
            case 'toggle_party_status':
                $id = (int)($_POST['id'] ?? 0);
                $status = (int)($_POST['status'] ?? 1);
                if ($id <= 0) throw new Exception('Invalid party ID.');
                
                $stmt = $db->prepare("UPDATE political_parties SET is_active = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$status, $id, $tenant_id]);
                
                logActivity($user_id, 'party_status_toggled', "Toggled party ID: $id to " . ($status ? 'active' : 'inactive'));
                $action_result = ['success' => true, 'message' => 'Party status updated successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH PARTIES
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : -1;

$where_conditions = ["tenant_id = ?"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR acronym LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if ($status_filter >= 0) {
    $where_conditions[] = "is_active = ?";
    $params[] = $status_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM political_parties $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_parties = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_parties / $limit);

// Fetch parties
$sql = "
    SELECT p.*,
           (SELECT COUNT(*) FROM candidates WHERE party_id = p.id AND tenant_id = ?) as candidate_count
    FROM political_parties p
    $where_clause
    ORDER BY p.name
    LIMIT ? OFFSET ?
";

// Add tenant_id for subquery
$params_with_tenant = array_merge([$tenant_id], $params);
// Remove the last two params (limit and offset) for the subquery, but we need them
// Rebuild the parameters properly
$params_final = [];
$params_final[] = $tenant_id; // For subquery

// Add the where params
foreach ($params as $param) {
    $params_final[] = $param;
}

// Add limit and offset
$params_final[] = $limit;
$params_final[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params_final);
$parties = $stmt->fetchAll();

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'total_candidates' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM political_parties WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $stats['total'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM political_parties WHERE tenant_id = ? AND is_active = 1");
    $stmt->execute([$tenant_id]);
    $stats['active'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM political_parties WHERE tenant_id = ? AND is_active = 0");
    $stmt->execute([$tenant_id]);
    $stats['inactive'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM candidates WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $stats['total_candidates'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       PARTIES MANAGEMENT - PROFESSIONAL UI STYLES
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
    
    .btn-primary {
        padding: 10px 20px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.3);
    }
    .btn-outline {
        padding: 10px 18px;
        background: transparent;
        color: var(--gray-600);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--primary);
        color: var(--primary);
    }
    .btn-sm {
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-sm.success { background: #ECFDF5; color: #065F46; }
    .btn-sm.success:hover { background: #D1FAE5; }
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    .btn-sm.warning { background: #FFFBEB; color: #92400E; }
    .btn-sm.warning:hover { background: #FEF3C7; }
    .btn-sm.purple { background: #F5F3FF; color: #5B21B6; }
    .btn-sm.purple:hover { background: #EDE9FE; }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-item {
        background: white;
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
        cursor: default;
        position: relative;
        overflow: hidden;
    }
    .stat-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        opacity: 0;
        transition: var(--transition);
    }
    .stat-item:hover::before {
        opacity: 1;
    }
    .stat-item:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-3px);
    }
    .stat-item .number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .label {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-top: 4px;
        font-weight: 500;
    }
    .stat-item .sub-label {
        font-size: 0.65rem;
        color: var(--gray-400);
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
        transition: var(--transition);
    }
    .filter-bar:hover {
        box-shadow: var(--shadow-hover);
    }
    .filter-bar .search-wrap {
        flex: 1;
        min-width: 180px;
        display: flex;
        align-items: center;
        background: var(--gray-50);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        padding: 6px 14px;
        transition: var(--transition);
    }
    .filter-bar .search-wrap:focus-within {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .filter-bar .search-wrap i {
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .filter-bar .search-wrap input {
        border: none;
        outline: none;
        background: transparent;
        padding: 4px 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        width: 100%;
        color: var(--gray-700);
    }
    .filter-bar .search-wrap input::placeholder {
        color: var(--gray-400);
    }
    .filter-bar select {
        padding: 8px 14px;
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        background: var(--gray-50);
        color: var(--gray-700);
        cursor: pointer;
        transition: var(--transition);
        min-width: 120px;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 36px;
    }
    .filter-bar select:focus {
        outline: none;
        border-color: var(--primary);
        background-color: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .filter-bar .btn-filter {
        padding: 8px 20px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.82rem;
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
        padding: 8px 16px;
        background: transparent;
        color: var(--gray-500);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
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
    
    .party-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .party-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        transition: var(--transition);
        box-shadow: var(--shadow);
        position: relative;
    }
    .party-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-4px);
    }
    .party-card .party-header {
        padding: 20px 20px 16px;
        display: flex;
        align-items: center;
        gap: 16px;
        border-bottom: 1px solid var(--gray-100);
        position: relative;
    }
    .party-card .party-logo {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        overflow: hidden;
        flex-shrink: 0;
        border: 2px solid var(--gray-200);
        background: var(--gray-50);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
    }
    .party-card .party-logo:hover {
        border-color: var(--primary);
        transform: scale(1.05);
    }
    .party-card .party-logo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .party-card .party-logo .no-logo {
        font-size: 1.8rem;
        color: var(--gray-400);
    }
    .party-card .party-info {
        flex: 1;
        min-width: 0;
    }
    .party-card .party-info .party-name {
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .party-card .party-info .party-acronym {
        font-size: 0.7rem;
        font-weight: 600;
        background: var(--gray-100);
        padding: 2px 10px;
        border-radius: 12px;
        color: var(--gray-600);
    }
    .party-card .party-status {
        position: absolute;
        top: 12px;
        right: 12px;
    }
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        transition: var(--transition);
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.active { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.inactive { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    .badge-status.inactive .dot { background: #EF4444; }
    
    .party-card .party-body {
        padding: 16px 20px 20px;
    }
    .party-card .party-body .detail-row {
        display: flex;
        padding: 4px 0;
        font-size: 0.82rem;
        gap: 8px;
    }
    .party-card .party-body .detail-row .label {
        color: var(--gray-500);
        min-width: 80px;
        flex-shrink: 0;
        font-weight: 500;
    }
    .party-card .party-body .detail-row .value {
        color: var(--gray-700);
        word-break: break-word;
    }
    .party-card .party-footer {
        padding: 12px 20px;
        border-top: 1px solid var(--gray-100);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--gray-50);
    }
    .party-card .party-footer .candidate-count {
        font-size: 0.75rem;
        color: var(--gray-500);
    }
    .party-card .party-footer .candidate-count strong {
        color: var(--gray-700);
    }
    .party-card .party-footer .actions {
        display: flex;
        gap: 4px;
    }
    
    .action-dropdown {
        position: relative;
        display: inline-block;
    }
    .action-dropdown .dropdown-btn {
        background: none;
        border: none;
        padding: 4px 8px;
        cursor: pointer;
        color: var(--gray-400);
        font-size: 1.1rem;
        transition: var(--transition);
        border-radius: 6px;
    }
    .action-dropdown .dropdown-btn:hover {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .action-dropdown .dropdown-menu {
        position: absolute;
        right: 0;
        top: calc(100% + 4px);
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        border: 1px solid var(--gray-200);
        min-width: 190px;
        padding: 6px;
        display: none;
        z-index: 50;
        animation: dropdownFade 0.2s ease;
    }
    @keyframes dropdownFade {
        from { opacity: 0; transform: translateY(-8px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .action-dropdown .dropdown-menu.open { display: block; }
    .action-dropdown .dropdown-menu a,
    .action-dropdown .dropdown-menu button {
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
    .action-dropdown .dropdown-menu a:hover,
    .action-dropdown .dropdown-menu button:hover {
        background: var(--gray-50);
        color: var(--primary);
    }
    .action-dropdown .dropdown-menu .danger:hover {
        background: #FEF2F2;
        color: var(--danger);
    }
    .action-dropdown .dropdown-menu i {
        width: 16px;
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .action-dropdown .dropdown-menu .divider {
        height: 1px;
        background: var(--gray-100);
        margin: 4px 8px;
    }
    
    .pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 14px 24px;
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
    .pagination .info strong {
        color: var(--gray-700);
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
        box-shadow: 0 2px 8px rgba(var(--primary-rgb), 0.2);
    }
    .pagination .pages .disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--gray-500);
    }
    .empty-state i {
        font-size: 4rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 16px;
    }
    .empty-state h4 {
        color: var(--gray-700);
        margin-bottom: 8px;
        font-size: 1.1rem;
    }
    .empty-state p {
        font-size: 0.9rem;
        color: var(--gray-400);
        max-width: 400px;
        margin: 0 auto;
    }
    
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-wrap { min-width: auto; }
        .filter-bar select { width: 100%; }
        .party-grid { grid-template-columns: 1fr; }
        .pagination { flex-direction: column; align-items: center; }
        .party-card .party-header {
            flex-wrap: wrap;
        }
        .party-card .party-status {
            position: relative;
            top: auto;
            right: auto;
            margin-left: auto;
        }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 12px 14px; }
        .stat-item .number { font-size: 1.3rem; }
        .party-card .party-logo { width: 48px; height: 48px; }
        .party-card .party-info .party-name { font-size: 0.95rem; }
        .badge-status { font-size: 0.55rem; padding: 2px 8px; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;max-width:100%;margin-bottom:16px;">
            <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($action_result['message']); ?>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-flag" style="color:var(--primary);margin-right:8px;"></i> Political Parties
                    <small>Manage political parties and their information</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="parties-add.php" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Create Party
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total Parties</div>
                <div class="sub-label">Registered parties</div>
            </div>
            <div class="stat-item">
                <div class="number green"><?php echo number_format($stats['active']); ?></div>
                <div class="label">Active</div>
                <div class="sub-label">Currently active</div>
            </div>
            <div class="stat-item">
                <div class="number red"><?php echo number_format($stats['inactive']); ?></div>
                <div class="label">Inactive</div>
                <div class="sub-label">Suspended or inactive</div>
            </div>
            <div class="stat-item">
                <div class="number purple"><?php echo number_format($stats['total_candidates']); ?></div>
                <div class="label">Total Candidates</div>
                <div class="sub-label">Across all parties</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search parties by name or acronym..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="status">
                    <option value="-1">All Status</option>
                    <option value="1" <?php echo $status_filter == 1 ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo $status_filter == 0 ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || $status_filter >= 0): ?>
                    <a href="parties.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Party Grid -->
        <?php if (count($parties) > 0): ?>
        <div class="party-grid">
            <?php foreach ($parties as $party): ?>
                <div class="party-card">
                    <div class="party-header">
                        <div class="party-logo">
                            <?php if (!empty($party['logo_url'])): ?>
                                <img src="<?php echo htmlspecialchars($party['logo_url']); ?>" alt="<?php echo htmlspecialchars($party['name']); ?>">
                            <?php else: ?>
                                <div class="no-logo">
                                    <i class="fas fa-flag"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="party-info">
                            <div class="party-name">
                                <?php echo htmlspecialchars($party['name']); ?>
                                <span class="party-acronym"><?php echo htmlspecialchars($party['acronym']); ?></span>
                            </div>
                        </div>
                        <div class="party-status">
                            <span class="badge-status <?php echo $party['is_active'] ? 'active' : 'inactive'; ?>">
                                <span class="dot"></span>
                                <?php echo $party['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="party-body">
                        <?php if (!empty($party['chairman_name'])): ?>
                            <div class="detail-row">
                                <span class="label">Chairman</span>
                                <span class="value"><?php echo htmlspecialchars($party['chairman_name']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($party['secretary_name'])): ?>
                            <div class="detail-row">
                                <span class="label">Secretary</span>
                                <span class="value"><?php echo htmlspecialchars($party['secretary_name']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($party['contact_email']) || !empty($party['contact_phone'])): ?>
                            <div class="detail-row">
                                <span class="label">Contact</span>
                                <span class="value">
                                    <?php if (!empty($party['contact_email'])): ?>
                                        <span style="font-size:0.75rem;">📧 <?php echo htmlspecialchars($party['contact_email']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($party['contact_phone'])): ?>
                                        <span style="font-size:0.75rem;margin-left:8px;">📞 <?php echo htmlspecialchars($party['contact_phone']); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($party['website'])): ?>
                            <div class="detail-row">
                                <span class="label">Website</span>
                                <span class="value">
                                    <a href="<?php echo htmlspecialchars($party['website']); ?>" target="_blank" style="color:var(--primary);text-decoration:none;font-size:0.75rem;">
                                        <?php echo htmlspecialchars($party['website']); ?>
                                        <i class="fas fa-external-link-alt" style="font-size:0.6rem;"></i>
                                    </a>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="party-footer">
                        <div class="candidate-count">
                            <i class="fas fa-user-tie" style="color:var(--gray-400);"></i>
                            <strong><?php echo number_format($party['candidate_count'] ?? 0); ?></strong> candidates
                        </div>
                        <div class="actions">
                            <a href="parties-add.php?id=<?php echo $party['id']; ?>" class="btn-sm info">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <div class="action-dropdown">
                                <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <button onclick="viewPartyDetails(<?php echo $party['id']; ?>)">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                    <button onclick="viewPartyMembers(<?php echo $party['id']; ?>)">
                                        <i class="fas fa-users"></i> View Members
                                    </button>
                                    <?php if ($party['is_active']): ?>
                                        <button onclick="togglePartyStatus(<?php echo $party['id']; ?>, 0)">
                                            <i class="fas fa-pause-circle"></i> Suspend
                                        </button>
                                    <?php else: ?>
                                        <button onclick="togglePartyStatus(<?php echo $party['id']; ?>, 1)">
                                            <i class="fas fa-play-circle"></i> Activate
                                        </button>
                                    <?php endif; ?>
                                    <div class="divider"></div>
                                    <button class="danger" onclick="deleteParty(<?php echo $party['id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state" style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:60px 20px;text-align:center;">
                <i class="fas fa-flag" style="font-size:4rem;color:var(--gray-300);display:block;margin-bottom:16px;"></i>
                <h4 style="color:var(--gray-700);margin-bottom:8px;">No parties found</h4>
                <p style="font-size:0.9rem;color:var(--gray-400);max-width:400px;margin:0 auto;">Create a political party to start building your organization.</p>
                <a href="parties-add.php" class="btn-primary" style="margin-top:12px;text-decoration:none;display:inline-flex;">
                    <i class="fas fa-plus-circle"></i> Create Party
                </a>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <div class="info">
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_parties); ?></strong> of <strong><?php echo number_format($total_parties); ?></strong> parties
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . $status_filter . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . $status_filter . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
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
// DROPDOWN FUNCTIONS
// ============================================================
function toggleDropdown(btn) {
    var menu = btn.nextElementSibling;
    var isOpen = menu.classList.contains('open');
    document.querySelectorAll('.action-dropdown .dropdown-menu').forEach(function(m) {
        m.classList.remove('open');
    });
    if (!isOpen) {
        menu.classList.toggle('open');
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-dropdown .dropdown-menu').forEach(function(m) {
            m.classList.remove('open');
        });
    }
});

// ============================================================
// PARTY FUNCTIONS
// ============================================================
function viewPartyDetails(id) {
    alert('View details for Party ID: ' + id + '\nImplement with modal or page.');
}

function viewPartyMembers(id) {
    alert('View members for Party ID: ' + id + '\nImplement with modal or page.');
}

function togglePartyStatus(id, status) {
    var action = status ? 'activate' : 'suspend';
    if (confirm('Are you sure you want to ' + action + ' this party?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="toggle_party_status"><input type="hidden" name="id" value="' + id + '"><input type="hidden" name="status" value="' + status + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteParty(id) {
    if (confirm('Delete this party? This action cannot be undone and will remove all associated data.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_party"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================================
// SEARCH FUNCTIONALITY
// ============================================================
var searchInput = document.querySelector('.search-wrap input[name="search"]');
if (searchInput) {
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            this.closest('form').submit();
        }
    });
}
</script>
</body>
</html>