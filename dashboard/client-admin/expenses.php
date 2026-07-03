<?php
// ============================================================
// EXPENSES MANAGEMENT - CLIENT ADMIN (PROFESSIONAL UI)
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
            case 'add_expense':
                $budget_id = (int)($_POST['budget_id'] ?? 0);
                $election_id = (int)($_POST['election_id'] ?? 0);
                $category = trim($_POST['category'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $amount = (float)($_POST['amount'] ?? 0);
                $payment_method = trim($_POST['payment_method'] ?? '');
                $payment_reference = trim($_POST['payment_reference'] ?? '');
                $paid_to_user_id = (int)($_POST['paid_to_user_id'] ?? 0);
                $notes = trim($_POST['notes'] ?? '');
                
                if ($budget_id <= 0 || empty($category) || empty($description) || $amount <= 0) {
                    throw new Exception('Budget, category, description, and amount are required.');
                }
                
                // Check budget remaining
                $stmt = $db->prepare("
                    SELECT total_amount, 
                           (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE budget_id = ? AND tenant_id = ? AND status != 'rejected') as spent
                    FROM budgets 
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$budget_id, $tenant_id, $budget_id, $tenant_id]);
                $budget = $stmt->fetch();
                
                if ($budget && ($budget['total_amount'] - $budget['spent']) < $amount) {
                    throw new Exception('Insufficient budget remaining.');
                }
                
                $stmt = $db->prepare("
                    INSERT INTO expenses (
                        tenant_id, budget_id, election_id, category, description,
                        amount, payment_method, payment_reference, paid_to_user_id,
                        paid_by_user_id, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $tenant_id, $budget_id, $election_id, $category, $description,
                    $amount, $payment_method, $payment_reference, $paid_to_user_id,
                    $user_id
                ]);
                
                logActivity($user_id, 'expense_added', "Added expense: $description");
                $action_result = ['success' => true, 'message' => 'Expense added successfully.'];
                break;
                
            case 'edit_expense':
                $id = (int)($_POST['id'] ?? 0);
                $budget_id = (int)($_POST['budget_id'] ?? 0);
                $election_id = (int)($_POST['election_id'] ?? 0);
                $category = trim($_POST['category'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $amount = (float)($_POST['amount'] ?? 0);
                $payment_method = trim($_POST['payment_method'] ?? '');
                $payment_reference = trim($_POST['payment_reference'] ?? '');
                $paid_to_user_id = (int)($_POST['paid_to_user_id'] ?? 0);
                $status = trim($_POST['status'] ?? '');
                $notes = trim($_POST['notes'] ?? '');
                
                if ($id <= 0 || $budget_id <= 0 || empty($category) || empty($description) || $amount <= 0) {
                    throw new Exception('Invalid data provided.');
                }
                
                $stmt = $db->prepare("
                    UPDATE expenses SET 
                        budget_id = ?, election_id = ?, category = ?, description = ?,
                        amount = ?, payment_method = ?, payment_reference = ?,
                        paid_to_user_id = ?, status = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $budget_id, $election_id, $category, $description,
                    $amount, $payment_method, $payment_reference,
                    $paid_to_user_id, $status, $id, $tenant_id
                ]);
                
                logActivity($user_id, 'expense_updated', "Updated expense ID: $id");
                $action_result = ['success' => true, 'message' => 'Expense updated successfully.'];
                break;
                
            case 'approve_expense':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid expense ID.');
                
                $stmt = $db->prepare("UPDATE expenses SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$user_id, $id, $tenant_id]);
                
                logActivity($user_id, 'expense_approved', "Approved expense ID: $id");
                $action_result = ['success' => true, 'message' => 'Expense approved successfully.'];
                break;
                
            case 'reject_expense':
                $id = (int)($_POST['id'] ?? 0);
                $rejection_reason = trim($_POST['rejection_reason'] ?? '');
                if ($id <= 0) throw new Exception('Invalid expense ID.');
                
                $stmt = $db->prepare("UPDATE expenses SET status = 'rejected', rejection_reason = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$rejection_reason, $id, $tenant_id]);
                
                logActivity($user_id, 'expense_rejected', "Rejected expense ID: $id");
                $action_result = ['success' => true, 'message' => 'Expense rejected successfully.'];
                break;
                
            case 'pay_expense':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid expense ID.');
                
                $stmt = $db->prepare("UPDATE expenses SET status = 'paid' WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                logActivity($user_id, 'expense_paid', "Marked expense as paid ID: $id");
                $action_result = ['success' => true, 'message' => 'Expense marked as paid successfully.'];
                break;
                
            case 'delete_expense':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid expense ID.');
                
                $stmt = $db->prepare("DELETE FROM expenses WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                logActivity($user_id, 'expense_deleted', "Deleted expense ID: $id");
                $action_result = ['success' => true, 'message' => 'Expense deleted successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH BUDGETS FOR DROPDOWN
// ============================================================
$budgets = [];
try {
    $stmt = $db->prepare("SELECT id, name, total_amount FROM budgets WHERE tenant_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$tenant_id]);
    $budgets = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH ELECTIONS FOR DROPDOWN
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM elections WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH USERS FOR DROPDOWN
// ============================================================
$users = [];
try {
    $stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE tenant_id = ? AND status = 'active' ORDER BY first_name");
    $stmt->execute([$tenant_id]);
    $users = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH EXPENSES
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$budget_filter = isset($_GET['budget']) ? (int)$_GET['budget'] : 0;
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$where_conditions = ["e.tenant_id = ?"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(e.description LIKE ?)";
    $params[] = "%$search%";
}

if ($budget_filter > 0) {
    $where_conditions[] = "e.budget_id = ?";
    $params[] = $budget_filter;
}

if (!empty($category_filter)) {
    $where_conditions[] = "e.category = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM expenses e $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_expenses = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_expenses / $limit);

// Fetch expenses
$sql = "
    SELECT e.*, 
           b.name as budget_name,
           el.name as election_name,
           u.first_name as paid_to_first, u.last_name as paid_to_last,
           au.first_name as approved_by_first, au.last_name as approved_by_last,
           pu.first_name as paid_by_first, pu.last_name as paid_by_last
    FROM expenses e
    LEFT JOIN budgets b ON e.budget_id = b.id
    LEFT JOIN elections el ON e.election_id = el.id
    LEFT JOIN users u ON e.paid_to_user_id = u.id
    LEFT JOIN users au ON e.approved_by = au.id
    LEFT JOIN users pu ON e.paid_by_user_id = pu.id
    $where_clause
    ORDER BY e.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total_expenses' => 0,
    'total_amount' => 0,
    'pending_amount' => 0,
    'approved_amount' => 0,
    'paid_amount' => 0,
    'rejected_amount' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as total FROM expenses WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $result = $stmt->fetch();
    $stats['total_expenses'] = $result['total'] ?? 0;
    $stats['total_amount'] = $result['total'] ?? 0;
    
    $statuses = ['pending', 'approved', 'paid', 'rejected'];
    foreach ($statuses as $status) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE tenant_id = ? AND status = ?");
        $stmt->execute([$tenant_id, $status]);
        $stats[$status . '_amount'] = $stmt->fetch()['total'] ?? 0;
    }
} catch (Exception $e) {}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       EXPENSES MANAGEMENT - PROFESSIONAL UI STYLES
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
    .btn-success {
        padding: 10px 20px;
        background: var(--secondary);
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
    .btn-success:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
    }
    .btn-danger {
        padding: 10px 20px;
        background: var(--danger);
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
    .btn-danger:hover {
        background: #DC2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(239, 68, 68, 0.3);
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
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-item {
        background: white;
        border-radius: 12px;
        padding: 14px 16px;
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
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 4px;
        font-weight: 500;
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
        min-width: 160px;
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
        min-width: 110px;
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
    .badge-status.pending { background: #FFFBEB; color: #92400E; }
    .badge-status.pending .dot { background: #F59E0B; }
    .badge-status.approved { background: #EFF6FF; color: #1E40AF; }
    .badge-status.approved .dot { background: #3B82F6; }
    .badge-status.paid { background: #ECFDF5; color: #065F46; }
    .badge-status.paid .dot { background: #10B981; }
    .badge-status.rejected { background: #FEF2F2; color: #991B1B; }
    .badge-status.rejected .dot { background: #EF4444; }
    
    .badge-category {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.6rem;
        font-weight: 600;
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .badge-category.agent_payment { background: #F5F3FF; color: #5B21B6; }
    .badge-category.transport { background: #EFF6FF; color: #1E40AF; }
    .badge-category.materials { background: #ECFDF5; color: #065F46; }
    .badge-category.logistics { background: #FFFBEB; color: #92400E; }
    .badge-category.security { background: #FEF2F2; color: #991B1B; }
    .badge-category.communication { background: #F5F3FF; color: #5B21B6; }
    .badge-category.media { background: #EFF6FF; color: #1E40AF; }
    .badge-category.other { background: var(--gray-100); color: var(--gray-500); }
    
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
        max-width: 540px;
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
                    <i class="fas fa-receipt" style="color:var(--primary);margin-right:8px;"></i> Expense Management
                    <small>Track and manage all election expenses</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('addExpenseModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Add Expense
                </button>
            </div>
        </div>

        <!-- Financial Navigation -->
        <div class="financial-nav">
            <a href="budgets.php">
                <i class="fas fa-wallet"></i> Budgets
            </a>
            <a href="expenses.php" class="active">
                <i class="fas fa-receipt"></i> Expenses
                <span class="count"><?php echo number_format($stats['total_expenses']); ?></span>
            </a>
            <a href="agent-payments.php">
                <i class="fas fa-money-bill-wave"></i> Agent Payments
            </a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number">₦<?php echo number_format($stats['total_amount']); ?></div>
                <div class="label">Total Expenses</div>
            </div>
            <div class="stat-item">
                <div class="number yellow">₦<?php echo number_format($stats['pending_amount']); ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-item">
                <div class="number blue">₦<?php echo number_format($stats['approved_amount']); ?></div>
                <div class="label">Approved</div>
            </div>
            <div class="stat-item">
                <div class="number green">₦<?php echo number_format($stats['paid_amount']); ?></div>
                <div class="label">Paid</div>
            </div>
            <div class="stat-item">
                <div class="number red">₦<?php echo number_format($stats['rejected_amount']); ?></div>
                <div class="label">Rejected</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search expenses..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="budget">
                    <option value="0">All Budgets</option>
                    <?php foreach ($budgets as $budget): ?>
                        <option value="<?php echo $budget['id']; ?>" <?php echo $budget_filter == $budget['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($budget['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="category">
                    <option value="">All Categories</option>
                    <option value="agent_payment" <?php echo $category_filter == 'agent_payment' ? 'selected' : ''; ?>>Agent Payment</option>
                    <option value="transport" <?php echo $category_filter == 'transport' ? 'selected' : ''; ?>>Transport</option>
                    <option value="materials" <?php echo $category_filter == 'materials' ? 'selected' : ''; ?>>Materials</option>
                    <option value="logistics" <?php echo $category_filter == 'logistics' ? 'selected' : ''; ?>>Logistics</option>
                    <option value="security" <?php echo $category_filter == 'security' ? 'selected' : ''; ?>>Security</option>
                    <option value="communication" <?php echo $category_filter == 'communication' ? 'selected' : ''; ?>>Communication</option>
                    <option value="media" <?php echo $category_filter == 'media' ? 'selected' : ''; ?>>Media</option>
                    <option value="other" <?php echo $category_filter == 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || $budget_filter > 0 || !empty($category_filter) || !empty($status_filter)): ?>
                    <a href="expenses.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Expenses Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Expenses List
                    <span class="count"><?php echo number_format($total_expenses); ?></span>
                </div>
                <div class="table-actions">
                    <span>Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_expenses); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Budget</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expenses) > 0): ?>
                        <?php foreach ($expenses as $index => $expense): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div>
                                        <div style="font-weight:500;font-size:0.82rem;">
                                            <?php echo htmlspecialchars($expense['description']); ?>
                                        </div>
                                        <?php if (!empty($expense['notes'])): ?>
                                            <div style="font-size:0.65rem;color:var(--gray-400);">
                                                <?php echo htmlspecialchars($expense['notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($expense['payment_reference'])): ?>
                                            <div style="font-size:0.6rem;color:var(--gray-400);">
                                                Ref: <?php echo htmlspecialchars($expense['payment_reference']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-category <?php echo $expense['category']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $expense['category'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;">
                                        <?php echo htmlspecialchars($expense['budget_name'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:600;font-size:0.9rem;">
                                        ₦<?php echo number_format($expense['amount']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $expense['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($expense['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:0.7rem;">
                                        <?php echo date('M j, Y', strtotime($expense['created_at'])); ?>
                                    </div>
                                    <div style="font-size:0.6rem;color:var(--gray-400);">
                                        <?php echo date('g:i A', strtotime($expense['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <button onclick="editExpense(<?php echo $expense['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($expense['status'] == 'pending'): ?>
                                                <button onclick="approveExpense(<?php echo $expense['id']; ?>)">
                                                    <i class="fas fa-check-circle" style="color:var(--secondary);"></i> Approve
                                                </button>
                                                <button onclick="openRejectModal(<?php echo $expense['id']; ?>)">
                                                    <i class="fas fa-times-circle" style="color:var(--danger);"></i> Reject
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($expense['status'] == 'approved'): ?>
                                                <button onclick="payExpense(<?php echo $expense['id']; ?>)">
                                                    <i class="fas fa-money-bill-wave" style="color:var(--secondary);"></i> Mark Paid
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="viewExpenseDetails(<?php echo $expense['id']; ?>)">
                                                <i class="fas fa-info-circle"></i> Details
                                            </button>
                                            <div class="divider"></div>
                                            <button class="danger" onclick="deleteExpense(<?php echo $expense['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-receipt"></i>
                                    <h4>No expenses found</h4>
                                    <p>Add an expense to start tracking your spending.</p>
                                    <button onclick="openModal('addExpenseModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Add Expense
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
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_expenses); ?></strong> of <strong><?php echo number_format($total_expenses); ?></strong> expenses
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&budget=<?php echo $budget_filter; ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&budget=' . $budget_filter . '&category=' . urlencode($category_filter) . '&status=' . urlencode($status_filter) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&budget=<?php echo $budget_filter; ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&budget=' . $budget_filter . '&category=' . urlencode($category_filter) . '&status=' . urlencode($status_filter) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&budget=<?php echo $budget_filter; ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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

<!-- Add Expense Modal -->
<div class="modal-overlay" id="addExpenseModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Add Expense</h3>
            <button class="close-btn" onclick="closeModal('addExpenseModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_expense">
            <div class="form-group">
                <label>Budget <span class="required">*</span></label>
                <select name="budget_id" required>
                    <option value="">Select Budget</option>
                    <?php foreach ($budgets as $budget): ?>
                        <option value="<?php echo $budget['id']; ?>">
                            <?php echo htmlspecialchars($budget['name']); ?> (₦<?php echo number_format($budget['total_amount']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Category <span class="required">*</span></label>
                <select name="category" required>
                    <option value="">Select Category</option>
                    <option value="agent_payment">Agent Payment</option>
                    <option value="transport">Transport</option>
                    <option value="materials">Materials</option>
                    <option value="logistics">Logistics</option>
                    <option value="security">Security</option>
                    <option value="communication">Communication</option>
                    <option value="media">Media</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description <span class="required">*</span></label>
                <input type="text" name="description" placeholder="Brief description of the expense" required>
            </div>
            <div class="form-group">
                <label>Amount <span class="required">*</span></label>
                <input type="number" name="amount" placeholder="0.00" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option value="">Select Method</option>
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="cheque">Cheque</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Reference</label>
                <input type="text" name="payment_reference" placeholder="Transaction reference">
            </div>
            <div class="form-group">
                <label>Paid To</label>
                <select name="paid_to_user_id">
                    <option value="0">Select User</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" placeholder="Additional notes..." rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addExpenseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Expense Modal -->
<div class="modal-overlay" id="editExpenseModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);"></i> Edit Expense</h3>
            <button class="close-btn" onclick="closeModal('editExpenseModal')">&times;</button>
        </div>
        <form method="POST" action="" id="editExpenseForm">
            <input type="hidden" name="action" value="edit_expense">
            <input type="hidden" name="id" id="editExpenseId">
            <div class="form-group">
                <label>Budget <span class="required">*</span></label>
                <select name="budget_id" id="editExpenseBudget" required>
                    <option value="">Select Budget</option>
                    <?php foreach ($budgets as $budget): ?>
                        <option value="<?php echo $budget['id']; ?>">
                            <?php echo htmlspecialchars($budget['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Category <span class="required">*</span></label>
                <select name="category" id="editExpenseCategory" required>
                    <option value="agent_payment">Agent Payment</option>
                    <option value="transport">Transport</option>
                    <option value="materials">Materials</option>
                    <option value="logistics">Logistics</option>
                    <option value="security">Security</option>
                    <option value="communication">Communication</option>
                    <option value="media">Media</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description <span class="required">*</span></label>
                <input type="text" name="description" id="editExpenseDescription" required>
            </div>
            <div class="form-group">
                <label>Amount <span class="required">*</span></label>
                <input type="number" name="amount" id="editExpenseAmount" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" id="editExpenseMethod">
                    <option value="">Select Method</option>
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="cheque">Cheque</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Reference</label>
                <input type="text" name="payment_reference" id="editExpenseRef">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="editExpenseStatus">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="paid">Paid</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="editExpenseNotes" rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editExpenseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Expense Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-times-circle" style="color:var(--danger);"></i> Reject Expense</h3>
            <button class="close-btn" onclick="closeModal('rejectModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="reject_expense">
            <input type="hidden" name="id" id="rejectExpenseId">
            <div class="form-group">
                <label>Rejection Reason <span class="required">*</span></label>
                <textarea name="rejection_reason" placeholder="Provide a reason for rejecting this expense..." rows="3" required></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Reject Expense</button>
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
// EXPENSE FUNCTIONS
// ============================================================
function editExpense(id) {
    alert('Edit Expense ID: ' + id + '\nImplement with AJAX fetch.');
}

function approveExpense(id) {
    if (confirm('Approve this expense?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="approve_expense"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function openRejectModal(id) {
    document.getElementById('rejectExpenseId').value = id;
    openModal('rejectModal');
}

function payExpense(id) {
    if (confirm('Mark this expense as paid?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="pay_expense"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function viewExpenseDetails(id) {
    alert('View details for Expense ID: ' + id + '\nImplement with modal or page.');
}

function deleteExpense(id) {
    if (confirm('Delete this expense? This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_expense"><input type="hidden" name="id" value="' + id + '">';
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