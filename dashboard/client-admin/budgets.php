<?php
// ============================================================
// BUDGET MANAGEMENT - CLIENT ADMIN (PROFESSIONAL UI)
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
            case 'add_budget':
                $name = trim($_POST['name'] ?? '');
                $total_amount = (float)($_POST['total_amount'] ?? 0);
                $start_date = trim($_POST['start_date'] ?? '');
                $end_date = trim($_POST['end_date'] ?? '');
                $election_id = (int)($_POST['election_id'] ?? 0);
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name) || $total_amount <= 0 || empty($start_date)) {
                    throw new Exception('Name, amount, and start date are required.');
                }
                
                $stmt = $db->prepare("
                    INSERT INTO budgets (
                        tenant_id, election_id, name, total_amount, 
                        start_date, end_date, status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW())
                ");
                $stmt->execute([$tenant_id, $election_id, $name, $total_amount, $start_date, $end_date, $user_id]);
                
                logActivity($user_id, 'budget_created', "Created budget: $name");
                $action_result = ['success' => true, 'message' => 'Budget created successfully.'];
                break;
                
            case 'edit_budget':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $total_amount = (float)($_POST['total_amount'] ?? 0);
                $start_date = trim($_POST['start_date'] ?? '');
                $end_date = trim($_POST['end_date'] ?? '');
                $election_id = (int)($_POST['election_id'] ?? 0);
                $status = trim($_POST['status'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if ($id <= 0 || empty($name) || $total_amount <= 0 || empty($start_date)) {
                    throw new Exception('Invalid data provided.');
                }
                
                $stmt = $db->prepare("
                    UPDATE budgets SET 
                        name = ?, total_amount = ?, start_date = ?, 
                        end_date = ?, election_id = ?, status = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$name, $total_amount, $start_date, $end_date, $election_id, $status, $id, $tenant_id]);
                
                logActivity($user_id, 'budget_updated', "Updated budget ID: $id");
                $action_result = ['success' => true, 'message' => 'Budget updated successfully.'];
                break;
                
            case 'delete_budget':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid budget ID.');
                
                // Check if budget has expenses
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM expenses WHERE budget_id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                if ($stmt->fetch()['count'] > 0) {
                    throw new Exception('Cannot delete budget with existing expenses.');
                }
                
                $stmt = $db->prepare("DELETE FROM budgets WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                logActivity($user_id, 'budget_deleted', "Deleted budget ID: $id");
                $action_result = ['success' => true, 'message' => 'Budget deleted successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH ELECTIONS FOR DROPDOWN
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("SELECT id, name, type, status, election_date FROM elections WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY election_date DESC");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH BUDGETS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$where_conditions = ["b.tenant_id = ?"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(b.name LIKE ?)";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "b.status = ?";
    $params[] = $status_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM budgets b $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_budgets = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_budgets / $limit);

// Fetch budgets
$sql = "
    SELECT b.*, 
           e.name as election_name,
           (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE budget_id = b.id AND tenant_id = ? AND status != 'rejected') as spent_amount,
           (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE budget_id = b.id AND tenant_id = ? AND status = 'pending') as pending_amount
    FROM budgets b
    LEFT JOIN elections e ON b.election_id = e.id
    $where_clause
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
";

$params_with_tenant = array_merge([$tenant_id, $tenant_id], $params);
$params_with_tenant[] = $limit;
$params_with_tenant[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params_with_tenant);
$budgets = $stmt->fetchAll();

// Calculate remaining amounts
foreach ($budgets as &$budget) {
    $budget['remaining'] = $budget['total_amount'] - $budget['spent_amount'];
}

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total_budgets' => 0,
    'total_amount' => 0,
    'total_spent' => 0,
    'total_pending' => 0,
    'active_count' => 0,
    'closed_count' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(total_amount) as total_amount FROM budgets WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $result = $stmt->fetch();
    $stats['total_budgets'] = $result['total'] ?? 0;
    $stats['total_amount'] = $result['total_amount'] ?? 0;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE tenant_id = ? AND status != 'rejected'");
    $stmt->execute([$tenant_id]);
    $stats['total_spent'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE tenant_id = ? AND status = 'pending'");
    $stmt->execute([$tenant_id]);
    $stats['total_pending'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM budgets WHERE tenant_id = ? AND status = 'active'");
    $stmt->execute([$tenant_id]);
    $stats['active_count'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM budgets WHERE tenant_id = ? AND status = 'closed'");
    $stmt->execute([$tenant_id]);
    $stats['closed_count'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       BUDGET MANAGEMENT - PROFESSIONAL UI STYLES
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
    
    .financial-nav {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        background: white;
        padding: 8px 12px;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow);
    }
    .financial-nav a {
        padding: 8px 18px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: var(--transition);
        background: transparent;
        border: 1px solid transparent;
        color: var(--gray-600);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        position: relative;
    }
    .financial-nav a:hover {
        background: var(--gray-50);
        border-color: var(--gray-200);
        color: var(--gray-700);
    }
    .financial-nav a.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .financial-nav a .count {
        background: var(--gray-200);
        color: var(--gray-600);
        padding: 0 8px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-left: 4px;
        transition: var(--transition);
    }
    .financial-nav a.active .count {
        background: rgba(255,255,255,0.25);
        color: white;
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
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 4px;
        font-weight: 500;
    }
    .stat-item .sub-label {
        font-size: 0.6rem;
        color: var(--gray-400);
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
        border: 1.5px solid var(--gray-200);
        border-radius: 8px;
        padding: 4px 12px;
        transition: var(--transition);
    }
    .filter-bar .search-wrap:focus-within {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
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
        font-size: 0.82rem;
        width: 100%;
        color: var(--gray-700);
    }
    .filter-bar select {
        padding: 6px 12px;
        border: 1.5px solid var(--gray-200);
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.78rem;
        background: var(--gray-50);
        color: var(--gray-700);
        cursor: pointer;
        transition: var(--transition);
        min-width: 120px;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        padding-right: 30px;
    }
    .filter-bar select:focus {
        outline: none;
        border-color: var(--primary);
        background-color: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .filter-bar .btn-filter {
        padding: 6px 16px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .filter-bar .btn-filter:hover {
        background: var(--primary-dark);
    }
    .filter-bar .btn-clear {
        padding: 6px 14px;
        background: transparent;
        color: var(--gray-500);
        border: 1.5px solid var(--gray-200);
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.78rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
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
        transition: var(--transition);
    }
    .table-container:hover {
        box-shadow: var(--shadow-hover);
    }
    .table-container .table-header {
        padding: 14px 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        background: linear-gradient(135deg, var(--gray-50), white);
    }
    .table-container .table-header .table-title {
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--gray-700);
    }
    .table-container .table-header .table-title i {
        color: var(--primary);
    }
    .table-container .table-header .table-title .count {
        background: var(--primary);
        color: white;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    .data-table thead {
        background: var(--gray-50);
    }
    .data-table thead th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        border-bottom: 2px solid var(--gray-200);
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
        transition: var(--transition);
    }
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    .data-table tbody tr {
        transition: var(--transition);
    }
    .data-table tbody tr:hover {
        background: var(--gray-50);
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
    }
    .badge-status .dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.closed { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.closed .dot { background: var(--gray-400); }
    .badge-status.draft { background: #FFFBEB; color: #92400E; }
    .badge-status.draft .dot { background: #F59E0B; }
    .badge-status.cancelled { background: #FEF2F2; color: #991B1B; }
    .badge-status.cancelled .dot { background: #EF4444; }
    
    .progress-bar {
        width: 100%;
        height: 6px;
        background: var(--gray-200);
        border-radius: 3px;
        overflow: hidden;
        margin-top: 4px;
    }
    .progress-bar .fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.5s ease;
    }
    .progress-bar .fill.green { background: var(--secondary); }
    .progress-bar .fill.yellow { background: var(--warning); }
    .progress-bar .fill.red { background: var(--danger); }
    
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
        font-size: 1rem;
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
        border-radius: 10px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        border: 1px solid var(--gray-200);
        min-width: 180px;
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
        gap: 8px;
        padding: 6px 12px;
        width: 100%;
        border: none;
        background: none;
        font-family: 'Inter', sans-serif;
        font-size: 0.78rem;
        color: var(--gray-600);
        cursor: pointer;
        border-radius: 6px;
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
        width: 14px;
        color: var(--gray-400);
        font-size: 0.8rem;
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
        gap: 10px;
        padding: 12px 20px;
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        margin-top: 16px;
        box-shadow: var(--shadow);
    }
    .pagination .info {
        font-size: 0.78rem;
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
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 0.78rem;
        text-decoration: none;
        color: var(--gray-600);
        transition: var(--transition);
        min-width: 32px;
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
        padding: 50px 20px;
        color: var(--gray-500);
    }
    .empty-state i {
        font-size: 3.5rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 12px;
    }
    .empty-state h4 {
        color: var(--gray-700);
        margin-bottom: 6px;
        font-size: 1rem;
    }
    .empty-state p {
        font-size: 0.85rem;
        color: var(--gray-400);
        max-width: 400px;
        margin: 0 auto;
    }
    
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        backdrop-filter: blur(4px);
        z-index: 300;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active { display: flex; }
    .modal {
        background: white;
        border-radius: var(--radius);
        max-width: 520px;
        width: 100%;
        padding: 24px 28px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        animation: modalIn 0.3s ease;
        max-height: 90vh;
        overflow-y: auto;
    }
    @keyframes modalIn {
        from { opacity: 0; transform: scale(0.95) translateY(20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--gray-100);
    }
    .modal .modal-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .modal .modal-header h3 i {
        color: var(--primary);
    }
    .modal .modal-header .close-btn {
        background: none;
        border: none;
        font-size: 1.4rem;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
        padding: 4px 8px;
        border-radius: 8px;
    }
    .modal .modal-header .close-btn:hover {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .form-group {
        margin-bottom: 14px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .modal .form-group label {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--gray-700);
    }
    .modal .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .modal .form-group .help-text {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .modal .form-group input,
    .modal .form-group select,
    .modal .form-group textarea {
        padding: 8px 12px;
        border: 1.5px solid var(--gray-200);
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .modal .form-group input:focus,
    .modal .form-group select:focus,
    .modal .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .modal .form-group textarea {
        resize: vertical;
        min-height: 60px;
    }
    .modal .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid var(--gray-100);
    }
    .modal .form-actions .btn {
        padding: 8px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.82rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .modal .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .modal .form-actions .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .modal .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .form-actions .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    .toast {
        padding: 12px 18px;
        border-radius: 8px;
        color: white;
        font-size: 0.82rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 100%;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-wrap { min-width: auto; }
        .filter-bar select { width: 100%; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.75rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .pagination { flex-direction: column; align-items: center; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .financial-nav { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 6px 8px; }
        .financial-nav a { white-space: nowrap; font-size: 0.78rem; padding: 6px 12px; }
        .modal { padding: 16px; margin: 10px; }
        .modal .form-actions { flex-direction: column; }
        .modal .form-actions .btn { width: 100%; justify-content: center; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.1rem; }
        .data-table th, .data-table td { padding: 4px 8px; font-size: 0.7rem; }
        .badge-status { font-size: 0.5rem; padding: 1px 6px; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;">
            <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($action_result['message']); ?>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-wallet" style="color:var(--primary);margin-right:8px;"></i> Budget Management
                    <small>Manage election budgets and financial planning</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('addBudgetModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Create Budget
                </button>
            </div>
        </div>

        <!-- Financial Navigation -->
        <div class="financial-nav">
            <a href="budgets.php" class="active">
                <i class="fas fa-wallet"></i> Budgets
                <span class="count"><?php echo number_format($stats['total_budgets']); ?></span>
            </a>
            <a href="expenses.php">
                <i class="fas fa-receipt"></i> Expenses
            </a>
            <a href="agent-payments.php">
                <i class="fas fa-money-bill-wave"></i> Agent Payments
            </a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number">₦<?php echo number_format($stats['total_amount']); ?></div>
                <div class="label">Total Budget</div>
                <div class="sub-label"><?php echo number_format($stats['total_budgets']); ?> budgets</div>
            </div>
            <div class="stat-item">
                <div class="number green">₦<?php echo number_format($stats['total_spent']); ?></div>
                <div class="label">Total Spent</div>
                <div class="sub-label"><?php echo $stats['total_amount'] > 0 ? round(($stats['total_spent'] / $stats['total_amount']) * 100, 1) : 0; ?>% utilized</div>
            </div>
            <div class="stat-item">
                <div class="number yellow">₦<?php echo number_format($stats['total_pending']); ?></div>
                <div class="label">Pending</div>
                <div class="sub-label">Awaiting approval</div>
            </div>
            <div class="stat-item">
                <div class="number blue"><?php echo number_format($stats['active_count']); ?></div>
                <div class="label">Active Budgets</div>
                <div class="sub-label"><?php echo number_format($stats['closed_count']); ?> closed</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search budgets..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || !empty($status_filter)): ?>
                    <a href="budgets.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Budgets Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Budgets List
                    <span class="count"><?php echo number_format($total_budgets); ?></span>
                </div>
                <div class="table-actions">
                    <span>Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_budgets); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>Budget</th>
                        <th>Election</th>
                        <th>Total</th>
                        <th>Spent</th>
                        <th>Remaining</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($budgets) > 0): ?>
                        <?php foreach ($budgets as $index => $budget): 
                            $percent_used = $budget['total_amount'] > 0 ? round(($budget['spent_amount'] / $budget['total_amount']) * 100, 1) : 0;
                            $progress_color = $percent_used < 50 ? 'green' : ($percent_used < 80 ? 'yellow' : 'red');
                        ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div>
                                        <div style="font-weight:500;font-size:0.85rem;">
                                            <?php echo htmlspecialchars($budget['name']); ?>
                                        </div>
                                        <div style="font-size:0.65rem;color:var(--gray-400);">
                                            <?php echo date('M j, Y', strtotime($budget['created_at'])); ?>
                                            <?php if (!empty($budget['start_date'])): ?>
                                                · <?php echo date('M j, Y', strtotime($budget['start_date'])); ?>
                                                <?php if (!empty($budget['end_date'])): ?>
                                                    - <?php echo date('M j, Y', strtotime($budget['end_date'])); ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;">
                                        <?php echo htmlspecialchars($budget['election_name'] ?? 'General'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:600;font-size:0.9rem;">
                                        ₦<?php echo number_format($budget['total_amount']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:500;font-size:0.85rem;color:var(--secondary);">
                                        ₦<?php echo number_format($budget['spent_amount']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:500;font-size:0.85rem;color:var(--primary);">
                                        ₦<?php echo number_format($budget['remaining']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="min-width:80px;">
                                        <div style="display:flex;justify-content:space-between;font-size:0.6rem;color:var(--gray-500);">
                                            <span><?php echo $percent_used; ?>%</span>
                                            <span>₦<?php echo number_format($budget['spent_amount']); ?></span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="fill <?php echo $progress_color; ?>" style="width:<?php echo min($percent_used, 100); ?>%;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $budget['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($budget['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <a href="budgets-edit.php?edit=<?php echo $budget['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="expenses.php?budget=<?php echo $budget['id']; ?>">
                                                <i class="fas fa-receipt"></i> View Expenses
                                            </a>
                                            <a href="budgets-details.php?id=<?php echo $budget['id']; ?>">
                                                <i class="fas fa-info-circle"></i> Details
                                            </a>
                                            <?php if ($budget['status'] == 'active'): ?>
                                                <button onclick="closeBudget(<?php echo $budget['id']; ?>)">
                                                    <i class="fas fa-check-circle" style="color:var(--secondary);"></i> Close
                                                </button>
                                            <?php endif; ?>
                                            <div class="divider"></div>
                                            <button class="danger" onclick="deleteBudget(<?php echo $budget['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div> 
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-wallet"></i>
                                    <h4>No budgets found</h4>
                                    <p>Create a budget to start managing your election finances.</p>
                                    <button onclick="openModal('addBudgetModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Create Budget
                                    </button>
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
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_budgets); ?></strong> of <strong><?php echo number_format($total_budgets); ?></strong> budgets
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
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

<!-- Add Budget Modal -->
<div class="modal-overlay" id="addBudgetModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Create Budget</h3>
            <button class="close-btn" onclick="closeModal('addBudgetModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_budget">
            <div class="form-group">
                <label>Budget Name <span class="required">*</span></label>
                <input type="text" name="name" placeholder="e.g., Election Campaign Budget" required>
            </div>
            <div class="form-group">
                <label>Total Amount <span class="required">*</span></label>
                <input type="number" name="total_amount" placeholder="0.00" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label>Election</label>
                <select name="election_id">
                    <option value="0">General (No Election)</option>
                    <?php foreach ($elections as $election): ?>
                        <option value="<?php echo $election['id']; ?>">
                            <?php echo htmlspecialchars($election['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Start Date <span class="required">*</span></label>
                <input type="date" name="start_date" required>
            </div>
            <div class="form-group">
                <label>End Date <span class="required">*</span></label>
                <input type="date" name="end_date" required>
                <div class="help-text">Optional: Set an end date for the budget period</div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Budget description and notes..." rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addBudgetModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Budget</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Budget Modal -->
<div class="modal-overlay" id="editBudgetModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);"></i> Edit Budget</h3>
            <button class="close-btn" onclick="closeModal('editBudgetModal')">&times;</button>
        </div>
        <form method="POST" action="" id="editBudgetForm">
            <input type="hidden" name="action" value="edit_budget">
            <input type="hidden" name="id" id="editBudgetId">
            <div class="form-group">
                <label>Budget Name <span class="required">*</span></label>
                <input type="text" name="name" id="editBudgetName" required>
            </div>
            <div class="form-group">
                <label>Total Amount <span class="required">*</span></label>
                <input type="number" name="total_amount" id="editBudgetAmount" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label>Election</label>
                <select name="election_id" id="editBudgetElection">
                    <option value="0">General (No Election)</option>
                    <?php foreach ($elections as $election): ?>
                        <option value="<?php echo $election['id']; ?>">
                            <?php echo htmlspecialchars($election['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Start Date <span class="required">*</span></label>
                <input type="date" name="start_date" id="editBudgetStart" required>
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" id="editBudgetEnd">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="editBudgetStatus">
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                    <option value="closed">Closed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="editBudgetDescription" rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editBudgetModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Budget</button>
            </div>
        </form>
    </div>
</div>

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
// MODAL FUNCTIONS
// ============================================================
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// ============================================================
// BUDGET FUNCTIONS
// ============================================================
function editBudget(id) {
    // In production, fetch via AJAX
    alert('Edit Budget ID: ' + id + '\nImplement with AJAX fetch.');
}

function viewBudgetDetails(id) {
    alert('View details for Budget ID: ' + id + '\nImplement with modal or page.');
}

function closeBudget(id) {
    if (confirm('Close this budget? This will mark it as closed.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="close_budget"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteBudget(id) {
    if (confirm('Delete this budget? This will also remove all associated expenses.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_budget"><input type="hidden" name="id" value="' + id + '">';
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