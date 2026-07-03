<?php
// ============================================================
// ELECTIONS MANAGEMENT - CLIENT ADMIN
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
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    try {
        switch ($action) {
            case 'delete':
                $stmt = $db->prepare("UPDATE elections SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                $action_result = ['success' => true, 'message' => 'Election deleted successfully.'];
                logActivity($user_id, 'election_deleted', "Deleted election ID: $id");
                break;
                
            case 'activate':
                $stmt = $db->prepare("UPDATE elections SET status = 'active', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                $action_result = ['success' => true, 'message' => 'Election activated successfully.'];
                logActivity($user_id, 'election_activated', "Activated election ID: $id");
                break;
                
            case 'archive':
                $stmt = $db->prepare("UPDATE elections SET status = 'archived', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                $action_result = ['success' => true, 'message' => 'Election archived successfully.'];
                logActivity($user_id, 'election_archived', "Archived election ID: $id");
                break;
                
            case 'duplicate':
                // Get election details
                $stmt = $db->prepare("SELECT * FROM elections WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
                $stmt->execute([$id, $tenant_id]);
                $election = $stmt->fetch();
                
                if ($election) {
                    unset($election['id']);
                    $election['name'] = $election['name'] . ' (Copy)';
                    $election['status'] = 'draft';
                    $election['created_by'] = $user_id;
                    $election['created_at'] = date('Y-m-d H:i:s');
                    
                    $columns = implode(', ', array_keys($election));
                    $placeholders = implode(', ', array_fill(0, count($election), '?'));
                    
                    $stmt = $db->prepare("INSERT INTO elections ($columns) VALUES ($placeholders)");
                    $stmt->execute(array_values($election));
                    
                    $action_result = ['success' => true, 'message' => 'Election duplicated successfully.'];
                    logActivity($user_id, 'election_duplicated', "Duplicated election ID: $id");
                }
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH ELECTIONS WITH PAGINATION & FILTERS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';

// Build WHERE clause
$where_conditions = ["e.tenant_id = ?", "e.deleted_at IS NULL"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(e.name LIKE ? OR e.description LIKE ? OR e.cycle LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "e.type = ?";
    $params[] = $type_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM elections e WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_elections = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_elections / $limit);

// Fetch elections
$sql = "
    SELECT 
        e.*,
        u.full_name as created_by_name
    FROM elections e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE $where_clause
    ORDER BY e.election_date DESC, e.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$elections = $stmt->fetchAll();

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'active' => 0,
    'upcoming' => 0,
    'completed' => 0,
    'draft' => 0,
    'archived' => 0,
    'cancelled' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['total'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND status = 'active' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['active'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND status = 'upcoming' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['upcoming'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND status = 'completed' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['completed'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND status = 'draft' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['draft'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND status = 'archived' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['archived'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND status = 'cancelled' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['cancelled'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTIONS - CLIENT ADMIN STYLES
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
        padding: 8px 18px;
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
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
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
    .btn-sm {
        padding: 4px 10px;
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
    .btn-sm.warning { background: #FFFBEB; color: #92400E; }
    .btn-sm.warning:hover { background: #FEF3C7; }
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    .btn-sm.purple { background: #F5F3FF; color: #5B21B6; }
    .btn-sm.purple:hover { background: #EDE9FE; }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
        cursor: pointer;
    }
    .stat-item:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
    }
    .stat-item .number {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--primary);
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .filter-bar {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 12px 16px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        box-shadow: var(--shadow);
    }
    .filter-bar .search-wrap {
        flex: 1;
        min-width: 180px;
        display: flex;
        align-items: center;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: 4px 12px;
        transition: var(--transition);
    }
    .filter-bar .search-wrap:focus-within {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .filter-bar .search-wrap i {
        color: var(--gray-400);
        font-size: 0.8rem;
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
    .filter-bar select {
        padding: 6px 12px;
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
        padding: 6px 16px;
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
    }
    .filter-bar .btn-clear {
        padding: 6px 14px;
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
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
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
    .badge-status.draft { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.draft .dot { background: var(--gray-400); }
    .badge-status.upcoming { background: #FFFBEB; color: #92400E; }
    .badge-status.upcoming .dot { background: #F59E0B; }
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.completed { background: #EFF6FF; color: #1E40AF; }
    .badge-status.completed .dot { background: #3B82F6; }
    .badge-status.cancelled { background: #FEF2F2; color: #991B1B; }
    .badge-status.cancelled .dot { background: #EF4444; }
    .badge-status.archived { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.archived .dot { background: var(--gray-400); }
    
    .badge-type {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 500;
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .badge-type.presidential { background: #F5F3FF; color: #5B21B6; }
    .badge-type.governorship { background: #EFF6FF; color: #1E40AF; }
    .badge-type.senatorial { background: #ECFDF5; color: #065F46; }
    .badge-type.house_of_reps { background: #FFFBEB; color: #92400E; }
    .badge-type.house_of_assembly { background: #FEF2F2; color: #991B1B; }
    .badge-type.lga_chairman { background: #F5F3FF; color: #5B21B6; }
    .badge-type.councillorship { background: #EFF6FF; color: #1E40AF; }
    .badge-type.party_primary { background: #ECFDF5; color: #065F46; }
    .badge-type.internal_party { background: #FFFBEB; color: #92400E; }
    
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
        top: 100%;
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
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
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
    
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        z-index: 300;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active { display: flex; }
    .modal {
        background: white;
        border-radius: var(--radius);
        max-width: 480px;
        width: 100%;
        padding: 28px 32px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        animation: modalIn 0.25s ease;
    }
    @keyframes modalIn {
        from { transform: scale(0.95) translateY(10px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .modal .modal-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .modal .modal-header .close-btn {
        background: none;
        border: none;
        font-size: 1.4rem;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
        padding: 0 4px;
    }
    .modal .modal-header .close-btn:hover {
        color: var(--gray-600);
    }
    .modal .modal-body {
        margin-bottom: 20px;
        color: var(--gray-600);
        font-size: 0.9rem;
        line-height: 1.6;
    }
    .modal .modal-body strong {
        color: var(--gray-800);
    }
    .modal .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    .modal .modal-footer .btn {
        padding: 8px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .modal .modal-footer .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .modal-footer .btn-secondary:hover {
        background: var(--gray-200);
    }
    .modal .modal-footer .btn-danger {
        background: var(--danger);
        color: white;
    }
    .modal .modal-footer .btn-danger:hover {
        background: #DC2626;
    }
    
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
        from { opacity: 0; transform: translateX(100px); }
        to { opacity: 1; transform: translateX(0); }
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
        .modal { padding: 20px; margin: 10px; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.2rem; }
        .data-table th, .data-table td { padding: 4px 8px; font-size: 0.7rem; }
        .badge-status { font-size: 0.55rem; padding: 1px 6px; }
        .badge-type { font-size: 0.55rem; padding: 1px 6px; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div style="margin-bottom:16px;">
            <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;max-width:100%;">
                <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_result['message']); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-vote-yea" style="color:var(--primary);margin-right:8px;"></i> Elections
                    <small>Manage all elections in your organization</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="elections-templates.php" class="btn-outline">
                    <i class="fas fa-copy"></i> Templates
                </a>
                <a href="elections-create.php" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Create Election
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item" onclick="applyFilter('status', '')">
                <div class="number"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total Elections</div>
            </div>
            <div class="stat-item" onclick="applyFilter('status', 'active')">
                <div class="number green"><?php echo number_format($stats['active']); ?></div>
                <div class="label">Active</div>
            </div>
            <div class="stat-item" onclick="applyFilter('status', 'upcoming')">
                <div class="number yellow"><?php echo number_format($stats['upcoming']); ?></div>
                <div class="label">Upcoming</div>
            </div>
            <div class="stat-item" onclick="applyFilter('status', 'completed')">
                <div class="number blue"><?php echo number_format($stats['completed']); ?></div>
                <div class="label">Completed</div>
            </div>
            <div class="stat-item" onclick="applyFilter('status', 'draft')">
                <div class="number purple"><?php echo number_format($stats['draft']); ?></div>
                <div class="label">Draft</div>
            </div>
            <div class="stat-item" onclick="applyFilter('status', 'archived')">
                <div class="number red"><?php echo number_format($stats['archived']); ?></div>
                <div class="label">Archived</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search elections..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
                <select name="type">
                    <option value="">All Types</option>
                    <option value="presidential" <?php echo $type_filter === 'presidential' ? 'selected' : ''; ?>>Presidential</option>
                    <option value="governorship" <?php echo $type_filter === 'governorship' ? 'selected' : ''; ?>>Governorship</option>
                    <option value="senatorial" <?php echo $type_filter === 'senatorial' ? 'selected' : ''; ?>>Senatorial</option>
                    <option value="house_of_reps" <?php echo $type_filter === 'house_of_reps' ? 'selected' : ''; ?>>House of Reps</option>
                    <option value="house_of_assembly" <?php echo $type_filter === 'house_of_assembly' ? 'selected' : ''; ?>>House of Assembly</option>
                    <option value="lga_chairman" <?php echo $type_filter === 'lga_chairman' ? 'selected' : ''; ?>>LGA Chairman</option>
                    <option value="councillorship" <?php echo $type_filter === 'councillorship' ? 'selected' : ''; ?>>Councillorship</option>
                    <option value="party_primary" <?php echo $type_filter === 'party_primary' ? 'selected' : ''; ?>>Party Primary</option>
                    <option value="internal_party" <?php echo $type_filter === 'internal_party' ? 'selected' : ''; ?>>Internal Party</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || !empty($status_filter) || !empty($type_filter)): ?>
                    <a href="elections.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Elections Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> All Elections
                    <span class="count"><?php echo number_format($total_elections); ?></span>
                </div>
                <div class="table-actions">
                    <span style="font-size:0.75rem;color:var(--gray-400);">
                        Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_elections); ?>
                    </span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Election</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="width:70px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($elections) > 0): ?>
                        <?php foreach ($elections as $index => $election): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div>
                                        <div style="font-weight:500;font-size:0.85rem;">
                                            <?php echo htmlspecialchars($election['name']); ?>
                                        </div>
                                        <div style="font-size:0.65rem;color:var(--gray-400);">
                                            Cycle: <?php echo htmlspecialchars($election['cycle'] ?? 'N/A'); ?>
                                            <?php if (!empty($election['created_by_name'])): ?>
                                                · <?php echo htmlspecialchars($election['created_by_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-type <?php echo $election['type']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $election['type'])); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.8rem;">
                                    <?php echo date('M j, Y', strtotime($election['election_date'])); ?>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $election['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($election['status']); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y', strtotime($election['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <a href="elections-view.php?id=<?php echo $election['id']; ?>"><i class="fas fa-eye"></i> View</a>
                                            <a href="elections-edit.php?id=<?php echo $election['id']; ?>"><i class="fas fa-edit"></i> Edit</a>
                                            <a href="elections-candidates.php?id=<?php echo $election['id']; ?>"><i class="fas fa-user-tie"></i> Candidates</a>
                                            <a href="elections-pus.php?id=<?php echo $election['id']; ?>"><i class="fas fa-map-marker-alt"></i> Polling Units</a>
                                            <div class="divider"></div>
                                            <?php if ($election['status'] === 'draft' || $election['status'] === 'upcoming'): ?>
                                                <form method="POST" style="display:inline;width:100%;">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="id" value="<?php echo $election['id']; ?>">
                                                    <button type="submit" onclick="return confirm('Activate this election?')">
                                                        <i class="fas fa-play"></i> Activate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($election['status'] !== 'archived' && $election['status'] !== 'cancelled'): ?>
                                                <form method="POST" style="display:inline;width:100%;">
                                                    <input type="hidden" name="action" value="archive">
                                                    <input type="hidden" name="id" value="<?php echo $election['id']; ?>">
                                                    <button type="submit" class="warning" onclick="return confirm('Archive this election?')">
                                                        <i class="fas fa-archive"></i> Archive
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display:inline;width:100%;">
                                                <input type="hidden" name="action" value="duplicate">
                                                <input type="hidden" name="id" value="<?php echo $election['id']; ?>">
                                                <button type="submit" class="info" onclick="return confirm('Duplicate this election?')">
                                                    <i class="fas fa-copy"></i> Duplicate
                                                </button>
                                            </form>
                                            <?php if ($election['status'] !== 'completed' && $election['status'] !== 'active'): ?>
                                                <form method="POST" style="display:inline;width:100%;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $election['id']; ?>">
                                                    <button type="submit" class="danger" onclick="return confirm('Delete this election?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-vote-yea"></i>
                                    <h4>No elections found</h4>
                                    <p>Create your first election to get started.</p>
                                    <a href="elections-create.php" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Create Election
                                    </a>
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
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_elections); ?></strong> of <strong><?php echo number_format($total_elections); ?></strong> elections
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&type=' . urlencode($type_filter) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&type=' . urlencode($type_filter) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
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
// ACTION DROPDOWN
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
// FILTER FUNCTIONS
// ============================================================
function applyFilter(type, value) {
    var url = new URL(window.location.href);
    if (value) {
        url.searchParams.set(type, value);
    } else {
        url.searchParams.delete(type);
    }
    window.location.href = url.toString();
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