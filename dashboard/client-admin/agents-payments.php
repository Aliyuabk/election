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
// GET FILTER PARAMETERS
// ============================================================
$agent_filter = isset($_GET['agent']) ? (int)$_GET['agent'] : 0;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// ============================================================
// HANDLE ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_payment':
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
                        payment_reference, status, notes, paid_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
                ");
                $stmt->execute([
                    $tenant_id, $agent_id, $assignment_id, $amount, $payment_type,
                    $payment_method, $bank_name, $account_number, $account_name,
                    $payment_reference, $notes, $user_id
                ]);
                
                logActivity($user_id, 'agent_payment_added', "Added payment for agent ID: $agent_id");
                $action_result = ['success' => true, 'message' => 'Payment recorded successfully.'];
                break;
                
            case 'update_payment_status':
                $payment_id = (int)($_POST['payment_id'] ?? 0);
                $status = trim($_POST['status'] ?? '');
                $paid_at = trim($_POST['paid_at'] ?? '');
                
                if ($payment_id <= 0 || empty($status)) {
                    throw new Exception('Payment ID and status are required.');
                }
                
                $stmt = $db->prepare("
                    UPDATE agent_payments SET 
                        status = ?, paid_at = ?,
                        paid_by = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$status, $paid_at, $user_id, $payment_id, $tenant_id]);
                
                logActivity($user_id, 'payment_status_updated', "Updated payment ID: $payment_id to $status");
                $action_result = ['success' => true, 'message' => 'Payment status updated successfully.'];
                break;
                
            case 'delete_payment':
                $payment_id = (int)($_POST['payment_id'] ?? 0);
                if ($payment_id <= 0) throw new Exception('Invalid payment ID.');
                
                $stmt = $db->prepare("DELETE FROM agent_payments WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$payment_id, $tenant_id]);
                
                logActivity($user_id, 'payment_deleted', "Deleted payment ID: $payment_id");
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
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, r.name as role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND u.status = 'active'
        AND r.level IN ('pu_agent', 'party_agent', 'volunteer', 'observer')
        ORDER BY u.first_name, u.last_name
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
        WHERE a.tenant_id = ? AND a.status IN ('pending', 'active')
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

$where_conditions = ["p.tenant_id = ?"];
$params = [$tenant_id];

if ($agent_filter > 0) {
    $where_conditions[] = "p.agent_id = ?";
    $params[] = $agent_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(p.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(p.created_at) <= ?";
    $params[] = $date_to;
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
           a.pu_id, pu.name as pu_name, pu.code as pu_code,
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
// GET PAYMENT SUMMARY
// ============================================================
$summary = [
    'total_payments' => 0,
    'total_amount' => 0,
    'paid_amount' => 0,
    'pending_amount' => 0,
    'failed_amount' => 0,
    'refunded_amount' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(amount) as total_amount FROM agent_payments WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $result = $stmt->fetch();
    $summary['total_payments'] = $result['total'] ?? 0;
    $summary['total_amount'] = $result['total_amount'] ?? 0;
    
    $stmt = $db->prepare("SELECT SUM(amount) as total FROM agent_payments WHERE tenant_id = ? AND status = 'paid'");
    $stmt->execute([$tenant_id]);
    $summary['paid_amount'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT SUM(amount) as total FROM agent_payments WHERE tenant_id = ? AND status = 'pending'");
    $stmt->execute([$tenant_id]);
    $summary['pending_amount'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT SUM(amount) as total FROM agent_payments WHERE tenant_id = ? AND status = 'failed'");
    $stmt->execute([$tenant_id]);
    $summary['failed_amount'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT SUM(amount) as total FROM agent_payments WHERE tenant_id = ? AND status = 'refunded'");
    $stmt->execute([$tenant_id]);
    $summary['refunded_amount'] = $stmt->fetch()['total'] ?? 0;
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
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    .btn-sm.success { background: #ECFDF5; color: #065F46; }
    .btn-sm.success:hover { background: #D1FAE5; }
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    .btn-sm.warning { background: #FFFBEB; color: #92400E; }
    .btn-sm.warning:hover { background: #FEF3C7; }
    .btn-sm.purple { background: #F5F3FF; color: #5B21B6; }
    .btn-sm.purple:hover { background: #EDE9FE; }
    
    .structure-nav {
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
    .structure-nav a {
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
    .structure-nav a:hover {
        background: var(--gray-50);
        border-color: var(--gray-200);
        color: var(--gray-700);
    }
    .structure-nav a.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .structure-nav a .count {
        background: var(--gray-200);
        color: var(--gray-600);
        padding: 0 8px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-left: 4px;
        transition: var(--transition);
    }
    .structure-nav a.active .count {
        background: rgba(255,255,255,0.25);
        color: white;
    }
    
    .payment-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }
    .summary-card {
        background: white;
        border-radius: 14px;
        padding: 18px 20px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
        cursor: default;
        position: relative;
        overflow: hidden;
    }
    .summary-card::before {
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
    .summary-card:hover::before {
        opacity: 1;
    }
    .summary-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-3px);
    }
    .summary-card .number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
    }
    .summary-card .number.green { color: var(--secondary); }
    .summary-card .number.yellow { color: var(--warning); }
    .summary-card .number.blue { color: #3B82F6; }
    .summary-card .number.red { color: var(--danger); }
    .summary-card .number.purple { color: #8B5CF6; }
    .summary-card .label {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-top: 4px;
        font-weight: 500;
    }
    .summary-card .sub-label {
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
    .filter-bar select,
    .filter-bar input[type="date"] {
        padding: 8px 14px;
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        background: var(--gray-50);
        color: var(--gray-700);
        cursor: pointer;
        transition: var(--transition);
        min-width: 130px;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 36px;
    }
    .filter-bar select:focus,
    .filter-bar input[type="date"]:focus {
        outline: none;
        border-color: var(--primary);
        background-color: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .filter-bar input[type="date"] {
        appearance: auto;
        background-image: none;
        padding-right: 14px;
        min-width: 140px;
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
        padding: 16px 24px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: linear-gradient(135deg, var(--gray-50), white);
    }
    .table-container .table-header .table-title {
        font-weight: 600;
        font-size: 0.95rem;
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
        padding: 2px 12px;
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
        border-bottom: 2px solid var(--gray-200);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--gray-50);
    }
    .data-table tbody td {
        padding: 10px 16px;
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
    .data-table tbody tr:hover td {
        border-color: var(--gray-200);
    }
    
    .amount-positive {
        font-weight: 700;
        color: var(--secondary);
        font-size: 0.95rem;
    }
    .amount-negative {
        font-weight: 700;
        color: var(--danger);
        font-size: 0.95rem;
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
    .badge-status.pending { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
    .badge-status.pending .dot { background: #F59E0B; }
    .badge-status.paid { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
    .badge-status.paid .dot { background: #10B981; }
    .badge-status.failed { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    .badge-status.failed .dot { background: #EF4444; }
    .badge-status.refunded { background: #F5F3FF; color: #5B21B6; border: 1px solid #C4B5FD; }
    .badge-status.refunded .dot { background: #8B5CF6; }
    .badge-status.processing { background: #EFF6FF; color: #1E40AF; border: 1px solid #93C5FD; }
    .badge-status.processing .dot { background: #3B82F6; }
    
    .badge-type {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .badge-type.advance { background: #FFFBEB; color: #92400E; }
    .badge-type.daily_allowance { background: #EFF6FF; color: #1E40AF; }
    .badge-type.completion_bonus { background: #ECFDF5; color: #065F46; }
    .badge-type.transport { background: #F5F3FF; color: #5B21B6; }
    .badge-type.other { background: var(--gray-100); color: var(--gray-500); }
    
    .action-dropdown {
        position: relative;
        display: inline-block;
    }
    .action-dropdown .dropdown-btn {
        background: none;
        border: none;
        padding: 6px 10px;
        cursor: pointer;
        color: var(--gray-400);
        font-size: 1.1rem;
        transition: var(--transition);
        border-radius: 8px;
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
        min-width: 200px;
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
        max-width: 560px;
        width: 100%;
        padding: 28px 32px;
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
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 2px solid var(--gray-100);
    }
    .modal .modal-header h3 {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .modal .modal-header h3 i {
        color: var(--primary);
    }
    .modal .modal-header .close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
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
        margin-bottom: 16px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .modal .form-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .modal .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .modal .form-group .help-text {
        font-size: 0.75rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .modal .form-group select,
    .modal .form-group input,
    .modal .form-group textarea {
        padding: 10px 14px;
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .modal .form-group select:focus,
    .modal .form-group input:focus,
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
        margin-top: 20px;
        padding-top: 16px;
        border-top: 2px solid var(--gray-100);
    }
    .modal .form-actions .btn {
        padding: 10px 24px;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
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
        .payment-summary { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar select,
        .filter-bar input[type="date"] { width: 100%; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 8px 12px; }
        .pagination { flex-direction: column; align-items: center; }
        .modal { padding: 20px; margin: 10px; }
        .modal .form-actions { flex-direction: column; }
        .modal .form-actions .btn { width: 100%; justify-content: center; }
        .structure-nav { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 6px 8px; }
        .structure-nav a { white-space: nowrap; font-size: 0.78rem; padding: 6px 12px; }
    }
    @media (max-width: 480px) {
        .payment-summary { grid-template-columns: 1fr 1fr; gap: 8px; }
        .summary-card { padding: 12px 14px; }
        .summary-card .number { font-size: 1.3rem; }
        .data-table th, .data-table td { padding: 6px 8px; font-size: 0.7rem; }
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
                    <i class="fas fa-money-bill-wave" style="color:var(--primary);margin-right:8px;"></i> Agent Payments
                    <small>Manage agent payments, allowances, and bonuses</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('addPaymentModal')" class="btn-success">
                    <i class="fas fa-plus-circle"></i> Add Payment
                </button>
                <a href="agents.php" class="btn-outline">
                    <i class="fas fa-users"></i> Agents
                </a>
            </div>
        </div>

        <!-- Navigation -->
        <div class="structure-nav">
            <a href="agents.php">
                <i class="fas fa-users"></i> Agents
            </a>
            <a href="agents-assign.php">
                <i class="fas fa-map-marker-alt"></i> Assign
            </a>
            <a href="agents-payments.php" class="active">
                <i class="fas fa-money-bill-wave"></i> Payments
                <span class="count"><?php echo number_format($total_payments); ?></span>
            </a>
        </div>

        <!-- Payment Summary -->
        <div class="payment-summary">
            <div class="summary-card">
                <div class="number">₦<?php echo number_format($summary['total_amount']); ?></div>
                <div class="label">Total Amount</div>
                <div class="sub-label">All payments</div>
            </div>
            <div class="summary-card">
                <div class="number green">₦<?php echo number_format($summary['paid_amount']); ?></div>
                <div class="label">Paid</div>
                <div class="sub-label">Completed payments</div>
            </div>
            <div class="summary-card">
                <div class="number yellow">₦<?php echo number_format($summary['pending_amount']); ?></div>
                <div class="label">Pending</div>
                <div class="sub-label">Awaiting processing</div>
            </div>
            <div class="summary-card">
                <div class="number red">₦<?php echo number_format($summary['failed_amount'] + $summary['refunded_amount']); ?></div>
                <div class="label">Failed/Refunded</div>
                <div class="sub-label">Issues</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <select name="agent">
                    <option value="">All Agents</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?php echo $agent['id']; ?>" <?php echo $agent_filter == $agent['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="refunded" <?php echo $status_filter == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                </select>
                <input type="date" name="date_from" placeholder="From" value="<?php echo htmlspecialchars($date_from); ?>">
                <input type="date" name="date_to" placeholder="To" value="<?php echo htmlspecialchars($date_to); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($agent_filter > 0 || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                    <a href="agents-payments.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Payments List
                    <span class="count"><?php echo number_format($total_payments); ?></span>
                </div>
                <div class="table-actions">
                    <span>Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_payments); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
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
                                        <div style="font-weight:500;font-size:0.85rem;">
                                            <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                        </div>
                                        <div style="font-size:0.7rem;color:var(--gray-400);">
                                            <?php echo htmlspecialchars($payment['pu_code'] ?? ''); ?>
                                            <?php echo htmlspecialchars($payment['pu_name'] ?? ''); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="amount-positive">₦<?php echo number_format($payment['amount'], 2); ?></span>
                                </td>
                                <td>
                                    <span class="badge-type <?php echo $payment['payment_type']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'] ?? 'N/A')); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;">
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
                                    <div style="font-size:0.75rem;">
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
                                                <button onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'processing')">
                                                    <i class="fas fa-spinner"></i> Mark Processing
                                                </button>
                                                <button onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'paid')">
                                                    <i class="fas fa-check-circle"></i> Mark Paid
                                                </button>
                                                <button onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'failed')">
                                                    <i class="fas fa-times-circle"></i> Mark Failed
                                                </button>
                                            <?php elseif ($payment['status'] == 'processing'): ?>
                                                <button onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'paid')">
                                                    <i class="fas fa-check-circle"></i> Mark Paid
                                                </button>
                                                <button onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'failed')">
                                                    <i class="fas fa-times-circle"></i> Mark Failed
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($payment['status'] == 'paid'): ?>
                                                <button onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'refunded')">
                                                    <i class="fas fa-undo"></i> Refund
                                                </button>
                                            <?php endif; ?>
                                            <div class="divider"></div>
                                            <button onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                                <i class="fas fa-info-circle"></i> Details
                                            </button>
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
                                    <p>Record agent payments to track expenses.</p>
                                    <button onclick="openModal('addPaymentModal')" class="btn-success" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Add Payment
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
                    <a href="?page=<?php echo $page - 1; ?>&agent=<?php echo $agent_filter; ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&agent=' . $agent_filter . '&status=' . urlencode($status_filter) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&agent=<?php echo $agent_filter; ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&agent=' . $agent_filter . '&status=' . urlencode($status_filter) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&agent=<?php echo $agent_filter; ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
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
            <h3><i class="fas fa-money-bill-wave" style="color:var(--primary);"></i> Add Payment</h3>
            <button class="close-btn" onclick="closeModal('addPaymentModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_payment">
            <div class="form-group">
                <label>Agent <span class="required">*</span></label>
                <select name="agent_id" required>
                    <option value="">Select Agent</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?php echo $agent['id']; ?>">
                            <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                            (<?php echo htmlspecialchars($agent['role_name'] ?? 'N/A'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Assignment</label>
                <select name="assignment_id">
                    <option value="0">Select Assignment (Optional)</option>
                    <?php foreach ($assignments as $assignment): ?>
                        <option value="<?php echo $assignment['id']; ?>">
                            <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                            - <?php echo htmlspecialchars($assignment['pu_code'] ?? ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Amount <span class="required">*</span></label>
                <input type="number" name="amount" placeholder="0.00" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label>Payment Type <span class="required">*</span></label>
                <select name="payment_type" required>
                    <option value="">Select Payment Type</option>
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
                <input type="text" name="payment_reference" placeholder="e.g., TRANS-001">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" placeholder="Additional notes about this payment" rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPaymentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Record Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Payment Status Modal -->
<div class="modal-overlay" id="updateStatusModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);"></i> Update Payment Status</h3>
            <button class="close-btn" onclick="closeModal('updateStatusModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_payment_status">
            <input type="hidden" name="payment_id" id="updatePaymentId">
            <div class="form-group">
                <label>Status <span class="required">*</span></label>
                <select name="status" id="updateStatusSelect" required>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="paid">Paid</option>
                    <option value="failed">Failed</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Date</label>
                <input type="datetime-local" name="paid_at" id="updatePaidAt">
                <div class="help-text">When was the payment completed?</div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateStatusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================================
// PRELOADER & SIDEBAR FUNCTIONS (same as previous pages)
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
// PAYMENT FUNCTIONS
// ============================================================
function updatePaymentStatus(paymentId, status) {
    document.getElementById('updatePaymentId').value = paymentId;
    document.getElementById('updateStatusSelect').value = status;
    if (status === 'paid') {
        var now = new Date();
        var datetime = now.getFullYear() + '-' + 
            String(now.getMonth() + 1).padStart(2, '0') + '-' + 
            String(now.getDate()).padStart(2, '0') + 'T' + 
            String(now.getHours()).padStart(2, '0') + ':' + 
            String(now.getMinutes()).padStart(2, '0');
        document.getElementById('updatePaidAt').value = datetime;
    }
    openModal('updateStatusModal');
}

function viewPaymentDetails(id) {
    alert('View payment details for ID: ' + id);
}

function deletePayment(id) {
    if (confirm('Delete this payment record? This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_payment"><input type="hidden" name="payment_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
</body>
</html>