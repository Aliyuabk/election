<?php
// ============================================================
// BILLING & INVOICES - SUPER ADMINISTRATOR (FIXED)
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
// ENSURE INVOICES TABLE EXISTS
// ============================================================
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS invoices (
            id INT PRIMARY KEY AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            subscription_id INT,
            invoice_number VARCHAR(50) UNIQUE NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            tax_amount DECIMAL(15,2) DEFAULT 0,
            total_amount DECIMAL(15,2) NOT NULL,
            status ENUM('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
            due_date DATE NOT NULL,
            paid_at TIMESTAMP NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {}

// ============================================================
// HANDLE AJAX REQUESTS
// ============================================================
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($action) {
            case 'get_invoice':
                $id = (int)($_GET['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid invoice ID.');
                
                $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
                $stmt->execute([$id]);
                $invoice = $stmt->fetch();
                
                if ($invoice) {
                    $response = ['success' => true, 'data' => $invoice];
                } else {
                    throw new Exception('Invoice not found.');
                }
                break;
                
            case 'get_stats':
                $stmt = $db->query("SELECT COUNT(*) as total FROM invoices");
                $total = $stmt->fetch()['total'] ?? 0;
                
                $stmt = $db->query("SELECT COUNT(*) as total FROM invoices WHERE status = 'paid'");
                $paid = $stmt->fetch()['total'] ?? 0;
                
                $stmt = $db->query("SELECT COUNT(*) as total FROM invoices WHERE status = 'draft'");
                $draft = $stmt->fetch()['total'] ?? 0;
                
                $stmt = $db->query("SELECT COUNT(*) as total FROM invoices WHERE status = 'overdue'");
                $overdue = $stmt->fetch()['total'] ?? 0;
                
                $stmt = $db->query("SELECT SUM(total_amount) as total FROM invoices WHERE status = 'paid'");
                $revenue = $stmt->fetch()['total'] ?? 0;
                
                $response = [
                    'success' => true,
                    'total_invoices' => $total,
                    'paid' => $paid,
                    'draft' => $draft,
                    'overdue' => $overdue,
                    'total_revenue' => $revenue
                ];
                break;
                
            case 'generate_invoice':
                $tenant_id = (int)($_POST['tenant_id'] ?? 0);
                $amount = (float)($_POST['amount'] ?? 0);
                $tax = (float)($_POST['tax'] ?? 0);
                $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
                $notes = trim($_POST['notes'] ?? '');
                
                if ($tenant_id <= 0 || $amount <= 0) {
                    throw new Exception('Tenant and amount are required.');
                }
                
                // Get tenant name
                $stmt = $db->prepare("SELECT name FROM tenants WHERE id = ?");
                $stmt->execute([$tenant_id]);
                $tenant = $stmt->fetch();
                
                $invoice_number = 'INV-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                $total = $amount + $tax;
                
                $stmt = $db->prepare("
                    INSERT INTO invoices (tenant_id, invoice_number, amount, tax_amount, total_amount, due_date, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$tenant_id, $invoice_number, $amount, $tax, $total, $due_date, $notes]);
                $invoice_id = $db->lastInsertId();
                
                logActivity(SessionManager::get('user_id'), 'invoice_generated', "Generated invoice: $invoice_number");
                
                $response = [
                    'success' => true, 
                    'message' => 'Invoice generated successfully: ' . $invoice_number,
                    'invoice_id' => $invoice_id,
                    'invoice_number' => $invoice_number,
                    'tenant_name' => $tenant['name'] ?? 'N/A',
                    'amount' => $amount,
                    'tax' => $tax,
                    'total' => $total,
                    'due_date' => $due_date,
                    'status' => 'draft'
                ];
                break;
                
            case 'mark_paid':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid invoice ID.');
                
                $stmt = $db->prepare("UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE id = ? AND status != 'paid'");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() > 0) {
                    logActivity(SessionManager::get('user_id'), 'invoice_paid', "Marked invoice ID: $id as paid");
                    $response = ['success' => true, 'message' => 'Invoice marked as paid.'];
                } else {
                    throw new Exception('Invoice not found or already paid.');
                }
                break;
                
            case 'cancel_invoice':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid invoice ID.');
                
                $stmt = $db->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = ? AND status != 'paid'");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() > 0) {
                    logActivity(SessionManager::get('user_id'), 'invoice_cancelled', "Cancelled invoice ID: $id");
                    $response = ['success' => true, 'message' => 'Invoice cancelled.'];
                } else {
                    throw new Exception('Invoice not found or cannot be cancelled.');
                }
                break;
                
            case 'send_invoice':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid invoice ID.');
                
                $stmt = $db->prepare("SELECT i.*, t.name as tenant_name, t.contact_email FROM invoices i JOIN tenants t ON i.tenant_id = t.id WHERE i.id = ?");
                $stmt->execute([$id]);
                $invoice = $stmt->fetch();
                
                if (!$invoice) throw new Exception('Invoice not found.');
                
                // Send email
                $subject = "Invoice #{$invoice['invoice_number']} from " . APP_NAME;
                $message = "Dear {$invoice['tenant_name']},\n\n";
                $message .= "Please find your invoice #{$invoice['invoice_number']} attached.\n\n";
                $message .= "Amount: ₦" . number_format($invoice['total_amount'], 2) . "\n";
                $message .= "Due Date: " . date('M j, Y', strtotime($invoice['due_date'])) . "\n\n";
                $message .= "Best regards,\n" . APP_NAME . " Team";
                
                sendEmail($invoice['contact_email'], $subject, $message);
                
                $stmt = $db->prepare("UPDATE invoices SET status = 'sent' WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity(SessionManager::get('user_id'), 'invoice_sent', "Sent invoice ID: $id");
                $response = ['success' => true, 'message' => 'Invoice sent successfully.'];
                break;
                
            default:
                throw new Exception('Invalid action.');
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}

// ============================================================
// HANDLE REGULAR FORM SUBMISSION (non-AJAX fallback)
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'generate_invoice':
                $tenant_id = (int)($_POST['tenant_id'] ?? 0);
                $amount = (float)($_POST['amount'] ?? 0);
                $tax = (float)($_POST['tax'] ?? 0);
                $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
                $notes = trim($_POST['notes'] ?? '');
                
                if ($tenant_id <= 0 || $amount <= 0) {
                    throw new Exception('Tenant and amount are required.');
                }
                
                $invoice_number = 'INV-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                $total = $amount + $tax;
                
                $stmt = $db->prepare("
                    INSERT INTO invoices (tenant_id, invoice_number, amount, tax_amount, total_amount, due_date, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$tenant_id, $invoice_number, $amount, $tax, $total, $due_date, $notes]);
                
                logActivity(SessionManager::get('user_id'), 'invoice_generated', "Generated invoice: $invoice_number");
                $action_result = ['success' => true, 'message' => 'Invoice generated successfully: ' . $invoice_number];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================================
// FETCH INVOICES
// ============================================================
$invoices = [];
try {
    $stmt = $db->query("
        SELECT i.*, t.name as tenant_name 
        FROM invoices i
        LEFT JOIN tenants t ON i.tenant_id = t.id
        WHERE t.deleted_at IS NULL OR t.deleted_at IS NULL
        ORDER BY i.created_at DESC
        LIMIT 50
    ");
    $invoices = $stmt->fetchAll();
} catch (Exception $e) {}

$tenants = [];
try {
    $stmt = $db->query("SELECT id, name FROM tenants WHERE deleted_at IS NULL ORDER BY name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       BILLING - PRO STYLES
       ============================================================ */
    
    .billing-container { max-width: 1400px; margin: 0 auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
    .page-header h2 { font-size: 1.3rem; font-weight: 700; }
    .page-header h2 small { font-size: 0.8rem; font-weight: 400; color: var(--gray-500); display: block; margin-top: 2px; }
    
    .btn-primary { padding: 8px 18px; background: var(--primary); color: white; border: none; border-radius: 10px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(var(--primary-rgb),0.3); }
    .btn-outline { padding: 8px 16px; background: transparent; color: var(--gray-600); border: 1px solid var(--gray-200); border-radius: 10px; font-weight: 500; font-size: 0.82rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .btn-outline:hover { background: var(--gray-50); border-color: var(--gray-300); }
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; border: none; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; }
    .btn-sm.success { background: #ECFDF5; color: #065F46; }
    .btn-sm.success:hover { background: #D1FAE5; }
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    .btn-sm.warning { background: #FFFBEB; color: #92400E; }
    .btn-sm.warning:hover { background: #FEF3C7; }
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .stat-item { background: white; border-radius: 12px; padding: 14px 18px; border: 1px solid var(--gray-200); text-align: center; transition: var(--transition); }
    .stat-item:hover { box-shadow: var(--shadow-hover); transform: translateY(-2px); }
    .stat-item .number { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .label { font-size: 0.7rem; color: var(--gray-500); margin-top: 2px; }

    .filter-bar { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 14px 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; box-shadow: var(--shadow); }
    .filter-bar .search-wrap { flex: 1; min-width: 200px; display: flex; align-items: center; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 10px; padding: 6px 14px; transition: var(--transition); }
    .filter-bar .search-wrap:focus-within { border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(var(--primary-rgb),0.06); }
    .filter-bar .search-wrap i { color: var(--gray-400); font-size: 0.85rem; }
    .filter-bar .search-wrap input { border: none; outline: none; background: transparent; padding: 6px 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; width: 100%; color: var(--gray-700); }
    .filter-bar select { padding: 8px 14px; border: 1px solid var(--gray-200); border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.82rem; background: var(--gray-50); color: var(--gray-700); cursor: pointer; transition: var(--transition); min-width: 120px; }
    .filter-bar select:focus { outline: none; border-color: var(--primary); background: white; }

    .table-container { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow); }
    .table-container .table-header { padding: 16px 20px; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: var(--gray-50); }
    .table-container .table-header .table-title { font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }
    .table-container .table-header .table-title .count { background: var(--primary); color: white; padding: 0 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }

    .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .data-table thead { background: var(--gray-50); }
    .data-table thead th { padding: 12px 16px; text-align: left; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
    .data-table tbody td { padding: 10px 16px; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background: var(--gray-50); }

    .badge-status { display: inline-flex; align-items: center; gap: 5px; padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
    .badge-status .dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
    .badge-status.paid { background: #ECFDF5; color: #065F46; }
    .badge-status.paid .dot { background: #10B981; }
    .badge-status.draft { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.draft .dot { background: var(--gray-400); }
    .badge-status.sent { background: #EFF6FF; color: #1E40AF; }
    .badge-status.sent .dot { background: #3B82F6; }
    .badge-status.overdue { background: #FEF2F2; color: #991B1B; }
    .badge-status.overdue .dot { background: #EF4444; }
    .badge-status.cancelled { background: #FEF2F2; color: #991B1B; }
    .badge-status.cancelled .dot { background: #EF4444; }

    .action-dropdown { position: relative; display: inline-block; }
    .action-dropdown .dropdown-btn { background: none; border: none; padding: 4px 8px; cursor: pointer; color: var(--gray-400); font-size: 1.1rem; transition: var(--transition); border-radius: 6px; }
    .action-dropdown .dropdown-btn:hover { background: var(--gray-100); color: var(--gray-600); }
    .action-dropdown .dropdown-menu { position: absolute; right: 0; top: 100%; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.12); border: 1px solid var(--gray-200); min-width: 180px; padding: 6px; display: none; z-index: 50; animation: dropdownFade 0.2s ease; }
    @keyframes dropdownFade { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
    .action-dropdown .dropdown-menu.open { display: block; }
    .action-dropdown .dropdown-menu a, .action-dropdown .dropdown-menu button { display: flex; align-items: center; gap: 10px; padding: 8px 14px; width: 100%; border: none; background: none; font-family: 'Inter', sans-serif; font-size: 0.8rem; color: var(--gray-600); cursor: pointer; border-radius: 8px; transition: var(--transition); text-decoration: none; }
    .action-dropdown .dropdown-menu a:hover, .action-dropdown .dropdown-menu button:hover { background: var(--gray-50); color: var(--primary); }
    .action-dropdown .dropdown-menu .danger:hover { background: #FEF2F2; color: var(--danger); }
    .action-dropdown .dropdown-menu i { width: 16px; color: var(--gray-400); font-size: 0.85rem; }
    .action-dropdown .dropdown-menu .divider { height: 1px; background: var(--gray-100); margin: 4px 8px; }

    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 300; align-items: center; justify-content: center; padding: 20px; }
    .modal-overlay.active { display: flex; }
    .modal { background: white; border-radius: var(--radius); max-width: 520px; width: 100%; padding: 28px 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); animation: modalIn 0.25s ease; max-height: 90vh; overflow-y: auto; }
    @keyframes modalIn { from { transform: scale(0.95) translateY(10px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
    .modal .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    .modal .modal-header h3 { font-size: 1.1rem; font-weight: 700; color: var(--gray-800); }
    .modal .modal-header .close-btn { background: none; border: none; font-size: 1.4rem; color: var(--gray-400); cursor: pointer; transition: var(--transition); padding: 0 4px; }
    .modal .modal-header .close-btn:hover { color: var(--gray-600); }
    .modal .form-group { margin-bottom: 14px; display: flex; flex-direction: column; gap: 4px; }
    .modal .form-group label { font-weight: 600; font-size: 0.82rem; color: var(--gray-700); }
    .modal .form-group label .required { color: var(--danger); margin-left: 2px; }
    .modal .form-group input, .modal .form-group select, .modal .form-group textarea { padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; transition: var(--transition); background: var(--gray-50); color: var(--gray-700); width: 100%; }
    .modal .form-group input:focus, .modal .form-group select:focus, .modal .form-group textarea:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(var(--primary-rgb),0.06); }
    .modal .form-group textarea { resize: vertical; min-height: 60px; }
    .modal .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200); }
    .modal .form-actions .btn { padding: 8px 20px; border-radius: 8px; border: none; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .modal .form-actions .btn-primary { background: var(--primary); color: white; }
    .modal .form-actions .btn-primary:hover { background: var(--primary-dark); }
    .modal .form-actions .btn-secondary { background: var(--gray-100); color: var(--gray-600); }
    .modal .form-actions .btn-secondary:hover { background: var(--gray-200); }

    .toast-container { position: fixed; top: 80px; right: 20px; z-index: 999; display: flex; flex-direction: column; gap: 8px; }
    .toast { padding: 14px 20px; border-radius: 10px; color: white; font-size: 0.85rem; font-weight: 500; box-shadow: var(--shadow-hover); animation: slideIn 0.3s ease; min-width: 280px; max-width: 400px; display: flex; align-items: center; gap: 10px; }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    @keyframes slideIn { from { transform: translateX(100px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    .pagination { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 20px; background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); margin-top: 16px; box-shadow: var(--shadow); }
    .pagination .info { font-size: 0.82rem; color: var(--gray-500); }
    .pagination .pages { display: flex; gap: 4px; align-items: center; }
    .pagination .pages a, .pagination .pages span { padding: 6px 14px; border-radius: 8px; font-size: 0.82rem; text-decoration: none; color: var(--gray-600); transition: var(--transition); min-width: 36px; text-align: center; border: 1px solid transparent; }
    .pagination .pages a:hover { background: var(--gray-100); border-color: var(--gray-200); }
    .pagination .pages .active { background: var(--primary); color: white; border-color: var(--primary); }
    .pagination .pages .disabled { opacity: 0.4; cursor: not-allowed; }

    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-wrap { min-width: auto; }
        .filter-bar select { width: 100%; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 8px 12px; }
        .modal { padding: 20px; margin: 10px; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .pagination { flex-direction: column; align-items: center; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.2rem; }
        .data-table th, .data-table td { padding: 6px 8px; font-size: 0.7rem; }
        .badge-status { font-size: 0.6rem; padding: 2px 8px; }
        .modal .form-actions { flex-direction: column; }
        .modal .form-actions .btn { width: 100%; justify-content: center; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="billing-container">
            <!-- Toast Messages -->
            <?php if (!empty($action_result['message'])): ?>
            <div class="toast-container" style="position:static;margin-bottom:16px;">
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
                        <i class="fas fa-file-invoice" style="color:var(--primary);margin-right:8px;"></i> Billing & Invoices
                        <small>Manage invoices and billing across the platform</small>
                    </h2>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button onclick="openModal('generateInvoiceModal')" class="btn-primary">
                        <i class="fas fa-plus-circle"></i> Generate Invoice
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <?php
            $total_invoices = count($invoices);
            $paid = $draft = $overdue = 0;
            $total_revenue = 0;
            foreach ($invoices as $inv) {
                if ($inv['status'] === 'paid') { $paid++; $total_revenue += $inv['total_amount']; }
                elseif ($inv['status'] === 'draft') $draft++;
                elseif ($inv['status'] === 'overdue') $overdue++;
            }
            ?>
            <div class="stats-grid">
                <div class="stat-item"><div class="number" id="statTotal"><?php echo $total_invoices; ?></div><div class="label">Total Invoices</div></div>
                <div class="stat-item"><div class="number green" id="statPaid"><?php echo $paid; ?></div><div class="label">Paid</div></div>
                <div class="stat-item"><div class="number yellow" id="statDraft"><?php echo $draft; ?></div><div class="label">Draft</div></div>
                <div class="stat-item"><div class="number red" id="statOverdue"><?php echo $overdue; ?></div><div class="label">Overdue</div></div>
                <div class="stat-item"><div class="number" id="statRevenue">₦<?php echo number_format($total_revenue, 2); ?></div><div class="label">Total Revenue</div></div>
            </div>

            <!-- Invoices Table -->
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-list" style="color:var(--primary);"></i> All Invoices
                        <span class="count"><?php echo count($invoices); ?></span>
                    </div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Tenant</th>
                            <th>Amount</th>
                            <th>Tax</th>
                            <th>Total</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($invoices) > 0): ?>
                            <?php foreach ($invoices as $inv): ?>
                                <tr data-invoice-id="<?php echo $inv['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($inv['tenant_name'] ?? 'N/A'); ?></td>
                                    <td>₦<?php echo number_format($inv['amount'], 2); ?></td>
                                    <td>₦<?php echo number_format($inv['tax_amount'], 2); ?></td>
                                    <td><strong>₦<?php echo number_format($inv['total_amount'], 2); ?></strong></td>
                                    <td><?php echo date('M j, Y', strtotime($inv['due_date'])); ?></td>
                                    <td class="status-cell">
                                        <span class="badge-status <?php echo $inv['status']; ?>">
                                            <span class="dot"></span>
                                            <?php echo ucfirst($inv['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                            <div class="dropdown-menu">
                                                <a href="#" onclick="viewInvoice(<?php echo $inv['id']; ?>)"><i class="fas fa-eye"></i> View</a>
                                                <?php if ($inv['status'] === 'draft' || $inv['status'] === 'sent'): ?>
                                                    <button onclick="markPaid(<?php echo $inv['id']; ?>)"><i class="fas fa-check"></i> Mark Paid</button>
                                                    <button onclick="sendInvoice(<?php echo $inv['id']; ?>)"><i class="fas fa-envelope"></i> Send</button>
                                                <?php endif; ?>
                                                <?php if ($inv['status'] !== 'paid' && $inv['status'] !== 'cancelled'): ?>
                                                    <button class="danger" onclick="cancelInvoice(<?php echo $inv['id']; ?>)"><i class="fas fa-times"></i> Cancel</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--gray-500);">No invoices found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Generate Invoice Modal -->
<div class="modal-overlay" id="generateInvoiceModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Generate Invoice</h3>
            <button class="close-btn" onclick="closeModal('generateInvoiceModal')">&times;</button>
        </div>
        <form method="POST" action="" id="generateInvoiceForm">
            <input type="hidden" name="action" value="generate_invoice">
            <div class="form-group">
                <label>Tenant <span class="required">*</span></label>
                <select name="tenant_id" required>
                    <option value="">Select Tenant</option>
                    <?php foreach ($tenants as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Amount (₦) <span class="required">*</span></label>
                <input type="number" name="amount" step="0.01" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label>Tax (₦)</label>
                <input type="number" name="tax" step="0.01" placeholder="0.00" value="0">
            </div>
            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" placeholder="Additional notes..."></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('generateInvoiceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate Invoice</button>
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
// MODAL FUNCTIONS
// ============================================================
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
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
// AJAX FUNCTIONS
// ============================================================

// Generate Invoice via AJAX
document.getElementById('generateInvoiceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    formData.append('action', 'generate_invoice');
    
    var submitBtn = this.querySelector('button[type="submit"]');
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    submitBtn.disabled = true;
    
    fetch('billing.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showToast('success', data.message);
            
            // Add new row to table
            var tbody = document.querySelector('.data-table tbody');
            var newRow = document.createElement('tr');
            newRow.setAttribute('data-invoice-id', data.invoice_id);
            var statusBadge = 'draft';
            newRow.innerHTML = `
                <td><strong>${data.invoice_number}</strong></td>
                <td>${data.tenant_name}</td>
                <td>₦${parseFloat(data.amount).toFixed(2)}</td>
                <td>₦${parseFloat(data.tax).toFixed(2)}</td>
                <td><strong>₦${parseFloat(data.total).toFixed(2)}</strong></td>
                <td>${new Date(data.due_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                <td class="status-cell"><span class="badge-status draft"><span class="dot"></span>Draft</span></td>
                <td>
                    <div class="action-dropdown">
                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                        <div class="dropdown-menu">
                            <a href="#" onclick="viewInvoice(${data.invoice_id})"><i class="fas fa-eye"></i> View</a>
                            <button onclick="markPaid(${data.invoice_id})"><i class="fas fa-check"></i> Mark Paid</button>
                            <button onclick="sendInvoice(${data.invoice_id})"><i class="fas fa-envelope"></i> Send</button>
                            <button class="danger" onclick="cancelInvoice(${data.invoice_id})"><i class="fas fa-times"></i> Cancel</button>
                        </div>
                    </div>
                </td>
            `;
            tbody.insertBefore(newRow, tbody.firstChild);
            
            // Update stats
            updateStats();
            
            // Close modal and reset form
            closeModal('generateInvoiceModal');
            this.reset();
        } else {
            showToast('error', data.message);
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showToast('error', 'An error occurred. Please try again.');
    })
    .finally(function() {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Mark Invoice as Paid via AJAX
function markPaid(id) {
    if (!confirm('Mark this invoice as paid?')) return;
    
    var formData = new FormData();
    formData.append('action', 'mark_paid');
    formData.append('id', id);
    
    fetch('billing.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showToast('success', data.message);
            // Update row status
            var row = document.querySelector('tr[data-invoice-id="' + id + '"]');
            if (row) {
                var statusCell = row.querySelector('.status-cell');
                statusCell.innerHTML = '<span class="badge-status paid"><span class="dot"></span>Paid</span>';
                // Update actions
                var menu = row.querySelector('.dropdown-menu');
                if (menu) {
                    menu.innerHTML = `
                        <a href="#" onclick="viewInvoice(${id})"><i class="fas fa-eye"></i> View</a>
                        <button class="danger" onclick="cancelInvoice(${id})"><i class="fas fa-times"></i> Cancel</button>
                    `;
                }
            }
            updateStats();
        } else {
            showToast('error', data.message);
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showToast('error', 'An error occurred. Please try again.');
    });
}

// Send Invoice via AJAX
function sendInvoice(id) {
    if (!confirm('Send this invoice to the tenant?')) return;
    
    var formData = new FormData();
    formData.append('action', 'send_invoice');
    formData.append('id', id);
    
    fetch('billing.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showToast('success', data.message);
            // Update status
            var row = document.querySelector('tr[data-invoice-id="' + id + '"]');
            if (row) {
                var statusCell = row.querySelector('.status-cell');
                statusCell.innerHTML = '<span class="badge-status sent"><span class="dot"></span>Sent</span>';
            }
        } else {
            showToast('error', data.message);
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showToast('error', 'An error occurred. Please try again.');
    });
}

// Cancel Invoice via AJAX
function cancelInvoice(id) {
    if (!confirm('Cancel this invoice?')) return;
    
    var formData = new FormData();
    formData.append('action', 'cancel_invoice');
    formData.append('id', id);
    
    fetch('billing.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showToast('success', data.message);
            var row = document.querySelector('tr[data-invoice-id="' + id + '"]');
            if (row) {
                var statusCell = row.querySelector('.status-cell');
                statusCell.innerHTML = '<span class="badge-status cancelled"><span class="dot"></span>Cancelled</span>';
                // Update actions
                var menu = row.querySelector('.dropdown-menu');
                if (menu) {
                    menu.innerHTML = `
                        <a href="#" onclick="viewInvoice(${id})"><i class="fas fa-eye"></i> View</a>
                    `;
                }
            }
            updateStats();
        } else {
            showToast('error', data.message);
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showToast('error', 'An error occurred. Please try again.');
    });
}

// View Invoice
function viewInvoice(id) {
    fetch('billing.php?action=get_invoice&id=' + id, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            var inv = data.data;
            alert('Invoice #' + inv.invoice_number + '\n' +
                  'Amount: ₦' + parseFloat(inv.total_amount).toFixed(2) + '\n' +
                  'Status: ' + inv.status.toUpperCase() + '\n' +
                  'Due Date: ' + new Date(inv.due_date).toLocaleDateString());
        } else {
            showToast('error', data.message);
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showToast('error', 'An error occurred.');
    });
}

// Update Stats
function updateStats() {
    fetch('billing.php?action=get_stats', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('statTotal').textContent = data.total_invoices;
            document.getElementById('statPaid').textContent = data.paid;
            document.getElementById('statDraft').textContent = data.draft;
            document.getElementById('statOverdue').textContent = data.overdue;
            document.getElementById('statRevenue').textContent = '₦' + parseFloat(data.total_revenue).toFixed(2);
        }
    })
    .catch(function(error) {
        console.error('Error updating stats:', error);
    });
}

// Toast Notifications
function showToast(type, message) {
    var container = document.querySelector('.toast-container');
    if (!container || container.style.position === 'static') {
        container = document.createElement('div');
        container.className = 'toast-container';
        container.style.position = 'fixed';
        container.style.top = '80px';
        container.style.right = '20px';
        container.style.zIndex = '999';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '8px';
        document.body.appendChild(container);
    }
    
    var toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + message;
    container.appendChild(toast);
    
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(function() {
            toast.remove();
        }, 300);
    }, 4000);
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