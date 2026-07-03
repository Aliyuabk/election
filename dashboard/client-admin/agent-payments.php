<?php
// ============================================================
// AGENT PAYMENTS - CLIENT ADMIN (PROFESSIONAL UI)
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
            case 'record_payment':
                $agent_id = (int)($_POST['agent_id'] ?? 0);
                $assignment_id = (int)($_POST['assignment_id'] ?? 0);
                $amount = (float)($_POST['amount'] ?? 0);
                $payment_type = trim($_POST['payment_type'] ?? '');
                $payment_method = trim($_POST['payment_method'] ?? '');
                $bank_name = trim($_POST['bank_name'] ?? '');
                $account_number = trim($_POST['account_number'] ?? '');
                $account_name = trim($_POST['account_name'] ?? '');
                $payment_reference = trim($_POST['payment_reference'] ?? '');
                $notes = trim($_POST['notes'] ?? '');
                
                if ($agent_id <= 0 || $amount <= 0 || empty($payment_type)) {
                    throw new Exception('Agent, amount, and payment type are required.');
                }
                
                $stmt = $db->prepare("
                    INSERT INTO agent_payments (
                        tenant_id, agent_id, assignment_id, amount, payment_type,
                        payment_method, bank_name, account_number, account_name,
                        payment_reference, status, notes, paid_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
                ");
                $stmt->execute([
                    $tenant_id, $agent_id, $assignment_id, $amount, $payment_type,
                    $payment_method, $bank_name, $account_number, $account_name,
                    $payment_reference, $notes, $user_id
                ]);
                
                logActivity($user_id, 'agent_payment_recorded', "Recorded payment for agent ID: $agent_id");
                $action_result = ['success' => true, 'message' => 'Payment recorded successfully.'];
                break;
                
            case 'approve_payment':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid payment ID.');
                
                $stmt = $db->prepare("UPDATE agent_payments SET status = 'approved', paid_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                logActivity($user_id, 'payment_approved', "Approved payment ID: $id");
                $action_result = ['success' => true, 'message' => 'Payment approved successfully.'];
                break;
                
            case 'mark_paid':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid payment ID.');
                
                $stmt = $db->prepare("UPDATE agent_payments SET status = 'paid', paid_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                logActivity($user_id, 'payment_marked_paid', "Marked payment as paid ID: $id");
                $action_result = ['success' => true, 'message' => 'Payment marked as paid successfully.'];
                break;
                
            case 'delete_payment':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid payment ID.');
                
                $stmt = $db->prepare("DELETE FROM agent_payments WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                logActivity($user_id, 'payment_deleted', "Deleted payment ID: $id");
                $action_result = ['success' => true, 'message' => 'Payment deleted successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH AGENTS FOR DROPDOWN
// ============================================================
$agents = [];
try {
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, email, phone 
        FROM users 
        WHERE tenant_id = ? AND status = 'active' 
        AND role_id IN (SELECT id FROM roles WHERE level IN ('pu_agent', 'party_agent', 'volunteer', 'observer'))
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$tenant_id]);
    $agents = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH ASSIGNMENTS FOR DROPDOWN
// ============================================================
$assignments = [];
try {
    $stmt = $db->prepare("
        SELECT a.id, u.first_name, u.last_name, pu.name as pu_name, pu.code as pu_code
        FROM agent_assignments a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN polling_units pu ON a.pu_id = pu.id
        WHERE a.tenant_id = ? AND a.status IN ('active', 'pending')
        ORDER BY a.assigned_at DESC
    ");
    $stmt->execute([$tenant_id]);
    $assignments = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH PAYMENTS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$agent_filter = isset($_GET['agent']) ? (int)$_GET['agent'] : 0;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$where_conditions = ["p.tenant_id = ?"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR p.payment_reference LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($agent_filter > 0) {
    $where_conditions[] = "p.agent_id = ?";
    $params[] = $agent_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM agent_payments p $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_payments = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_payments / $limit);

// Fetch payments
$sql = "
    SELECT p.*, 
           u.first_name, u.last_name, u.email, u.phone,
           pu.name as pu_name, pu.code as pu_code,
           paid_u.first_name as paid_by_first, paid_u.last_name as paid_by_last
    FROM agent_payments p
    LEFT JOIN users u ON p.agent_id = u.id
    LEFT JOIN agent_assignments a ON p.assignment_id = a.id
    LEFT JOIN polling_units pu ON a.pu_id = pu.id
    LEFT JOIN users paid_u ON p.paid_by = paid_u.id
    $where_clause
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total_payments' => 0,
    'total_amount' => 0,
    'pending_amount' => 0,
    'approved_amount' => 0,
    'paid_amount' => 0,
    'failed_amount' => 0,
    'refunded_amount' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as total FROM agent_payments WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $result = $stmt->fetch();
    $stats['total_payments'] = $result['total'] ?? 0;
    $stats['total_amount'] = $result['total'] ?? 0;
    
    $statuses = ['pending', 'approved', 'paid', 'failed', 'refunded'];
    foreach ($statuses as $status) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM agent_payments WHERE tenant_id = ? AND status = ?");
        $stmt->execute([$tenant_id, $status]);
        $stats[$status . '_amount'] = $stmt->fetch()['total'] ?? 0;
    }
} catch (Exception $e) {}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       AGENT PAYMENTS - PROFESSIONAL UI STYLES
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
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.purple { color: #8B5CF6; }
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
    .badge-status.failed { background: #FEF2F2; color: #991B1B; }
    .badge-status.failed .dot { background: #EF4444; }
    .badge-status.refunded { background: #F5F3FF; color: #5B21B6; }
    .badge-status.refunded .dot { background: #8B5CF6; }
    
    .badge-payment-type {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.6rem;
        font-weight: 600;
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .badge-payment-type.advance { background: #FFFBEB; color: #92400E; }
    .badge-payment-type.daily_allowance { background: #EFF6FF; color: #1E40AF; }
    .badge-payment-type.completion_bonus { background: #ECFDF5; color: #065F46; }
    .badge-payment-type.transport { background: #F5F3FF; color: #5B21B6; }
    .badge-payment-type.other { background: var(--gray-100); color: var(--gray-500); }
    
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
                    <i class="fas fa-money-bill-wave" style="color:var(--primary);margin-right:8px;"></i> Agent Payments
                    <small>Manage agent payments, approvals, and history</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('addPaymentModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Record Payment
                </button>
            </div>
        </div>

        <!-- Financial Navigation -->
        <div class="financial-nav">
            <a href="budgets.php">
                <i class="fas fa-wallet"></i> Budgets
            </a>
            <a href="expenses.php">
                <i class="fas fa-receipt"></i> Expenses
            </a>
            <a href="agent-payments.php" class="active">
                <i class="fas fa-money-bill-wave"></i> Agent Payments
                <span class="count"><?php echo number_format($stats['total_payments']); ?></span>
            </a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number">₦<?php echo number_format($stats['total_amount']); ?></div>
                <div class="label">Total Payments</div>
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
                <div class="number red">₦<?php echo number_format($stats['failed_amount']); ?></div>
                <div class="label">Failed</div>
            </div>
            <div class="stat-item">
                <div class="number purple">₦<?php echo number_format($stats['refunded_amount']); ?></div>
                <div class="label">Refunded</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search payments..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="agent">
                    <option value="0">All Agents</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?php echo $agent['id']; ?>" <?php echo $agent_filter == $agent['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="refunded" <?php echo $status_filter == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || $agent_filter > 0 || !empty($status_filter)): ?>
                    <a href="agent-payments.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Payment History
                    <span class="count"><?php echo number_format($total_payments); ?></span>
                </div>
                <div class="table-actions">
                    <span>Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_payments); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>Agent</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($payments) > 0): ?>
                        <?php foreach ($payments as $index => $payment): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div>
                                        <div style="font-weight:500;font-size:0.82rem;">
                                            <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                        </div>
                                        <div style="font-size:0.65rem;color:var(--gray-400);">
                                            <?php echo htmlspecialchars($payment['pu_code'] ?? 'N/A'); ?>
                                            <?php if (!empty($payment['pu_name'])): ?>
                                                - <?php echo htmlspecialchars($payment['pu_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight:600;font-size:0.9rem;color:var(--secondary);">
                                        ₦<?php echo number_format($payment['amount']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-payment-type <?php echo $payment['payment_type']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.7rem;">
                                        <?php echo ucfirst($payment['payment_method'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $payment['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:0.7rem;">
                                        <?php echo date('M j, Y', strtotime($payment['created_at'])); ?>
                                    </div>
                                    <div style="font-size:0.6rem;color:var(--gray-400);">
                                        <?php echo date('g:i A', strtotime($payment['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <?php if ($payment['status'] == 'pending'): ?>
                                                <button onclick="approvePayment(<?php echo $payment['id']; ?>)">
                                                    <i class="fas fa-check-circle" style="color:var(--secondary);"></i> Approve
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($payment['status'] == 'approved'): ?>
                                                <button onclick="markAsPaid(<?php echo $payment['id']; ?>)">
                                                    <i class="fas fa-money-bill-wave" style="color:var(--secondary);"></i> Mark Paid
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                                <i class="fas fa-info-circle"></i> Details
                                            </button>
                                            <div class="divider"></div>
                                            <button class="danger" onclick="deletePayment(<?php echo $payment['id']; ?>)">
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
                                    <i class="fas fa-money-bill-wave"></i>
                                    <h4>No payments recorded</h4>
                                    <p>Record agent payments to track disbursements.</p>
                                    <button onclick="openModal('addPaymentModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Record Payment
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
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_payments); ?></strong> of <strong><?php echo number_format($total_payments); ?></strong> payments
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&agent=<?php echo $agent_filter; ?>&status=<?php echo urlencode($status_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&agent=' . $agent_filter . '&status=' . urlencode($status_filter) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&agent=<?php echo $agent_filter; ?>&status=<?php echo urlencode($status_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&agent=' . $agent_filter . '&status=' . urlencode($status_filter) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&agent=<?php echo $agent_filter; ?>&status=<?php echo urlencode($status_filter); ?>">
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

<!-- Add Payment Modal -->
<div class="modal-overlay" id="addPaymentModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Record Payment</h3>
            <button class="close-btn" onclick="closeModal('addPaymentModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="record_payment">
            <div class="form-group">
                <label>Agent <span class="required">*</span></label>
                <select name="agent_id" required>
                    <option value="">Select Agent</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?php echo $agent['id']; ?>">
                            <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Assignment</label>
                <select name="assignment_id">
                    <option value="0">Select Assignment</option>
                    <?php foreach ($assignments as $assignment): ?>
                        <option value="<?php echo $assignment['id']; ?>">
                            <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                            - <?php echo htmlspecialchars($assignment['pu_code'] ?? 'N/A'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Amount <span class="required">*</span></label>
                <input type="number" name="amount" placeholder="0.00" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
                <label>Payment Type <span class="required">*</span></label>
                <select name="payment_type" required>
                    <option value="">Select Type</option>
                    <option value="advance">Advance</option>
                    <option value="daily_allowance">Daily Allowance</option>
                    <option value="completion_bonus">Completion Bonus</option>
                    <option value="transport">Transport</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option value="">Select Method</option>
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="mobile_money">Mobile Money</option>
                </select>
            </div>
            <div class="form-group">
                <label>Bank Name</label>
                <input type="text" name="bank_name" placeholder="e.g., GTBank">
            </div>
            <div class="form-group">
                <label>Account Number</label>
                <input type="text" name="account_number" placeholder="Enter account number">
            </div>
            <div class="form-group">
                <label>Account Name</label>
                <input type="text" name="account_name" placeholder="Enter account name">
            </div>
            <div class="form-group">
                <label>Payment Reference</label>
                <input type="text" name="payment_reference" placeholder="Transaction reference">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" placeholder="Additional notes..." rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPaymentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Record Payment</button>
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
// PAYMENT FUNCTIONS
// ============================================================
function approvePayment(id) {
    if (confirm('Approve this payment?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="approve_payment"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function markAsPaid(id) {
    if (confirm('Mark this payment as paid?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="mark_paid"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function viewPaymentDetails(id) {
    alert('View details for Payment ID: ' + id + '\nImplement with modal or page.');
}

function deletePayment(id) {
    if (confirm('Delete this payment? This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_payment"><input type="hidden" name="id" value="' + id + '">';
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