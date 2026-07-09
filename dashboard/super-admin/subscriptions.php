<?php
// ============================================================
// SUBSCRIPTION MANAGEMENT - SUPER ADMINISTRATOR (FIXED)
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
// ENSURE SUBSCRIPTION PLANS TABLE EXISTS
// ============================================================
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS subscription_plans (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            duration_days INT NOT NULL DEFAULT 30,
            user_limit INT NOT NULL DEFAULT 100,
            storage_limit_mb INT NOT NULL DEFAULT 10240,
            features TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
    // Table exists or error - continue
}

// ============================================================
// HANDLE ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    try {
        switch ($action) {
            case 'create_plan':
                $name = trim($_POST['name'] ?? '');
                $price = (float)($_POST['price'] ?? 0);
                $duration = (int)($_POST['duration'] ?? 30);
                $user_limit = (int)($_POST['user_limit'] ?? 100);
                $storage_limit = (int)($_POST['storage_limit'] ?? 10240);
                $features = trim($_POST['features'] ?? '');
                
                if (empty($name)) throw new Exception('Plan name is required.');
                if ($price < 0) throw new Exception('Price cannot be negative.');
                if ($duration <= 0) throw new Exception('Duration must be greater than 0.');
                
                $stmt = $db->prepare("
                    INSERT INTO subscription_plans (name, price, duration_days, user_limit, storage_limit_mb, features)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $price, $duration, $user_limit, $storage_limit, $features]);
                $action_result = ['success' => true, 'message' => 'Plan created successfully.'];
                break;
                
            case 'edit_plan':
                $name = trim($_POST['name'] ?? '');
                $price = (float)($_POST['price'] ?? 0);
                $duration = (int)($_POST['duration'] ?? 30);
                $user_limit = (int)($_POST['user_limit'] ?? 100);
                $storage_limit = (int)($_POST['storage_limit'] ?? 10240);
                $features = trim($_POST['features'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name)) throw new Exception('Plan name is required.');
                if ($price < 0) throw new Exception('Price cannot be negative.');
                if ($duration <= 0) throw new Exception('Duration must be greater than 0.');
                
                $stmt = $db->prepare("
                    UPDATE subscription_plans 
                    SET name = ?, price = ?, duration_days = ?, user_limit = ?, storage_limit_mb = ?, features = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $price, $duration, $user_limit, $storage_limit, $features, $is_active, $id]);
                $action_result = ['success' => true, 'message' => 'Plan updated successfully.'];
                break;
                
            case 'delete_plan':
                $stmt = $db->prepare("DELETE FROM subscription_plans WHERE id = ?");
                $stmt->execute([$id]);
                $action_result = ['success' => true, 'message' => 'Plan deleted successfully.'];
                break;
                
            case 'assign_plan':
                $tenant_id = (int)($_POST['tenant_id'] ?? 0);
                $plan_id = (int)($_POST['plan_id'] ?? 0);
                $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
                $start_date = $_POST['start_date'] ?? date('Y-m-d');
                $payment_status = $_POST['payment_status'] ?? 'pending';
                
                if ($tenant_id <= 0) {
                    throw new Exception('Please select a tenant.');
                }
                if ($plan_id <= 0) {
                    throw new Exception('Please select a plan.');
                }
                
                // Get plan details
                $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1");
                $stmt->execute([$plan_id]);
                $plan = $stmt->fetch();
                
                if (!$plan) {
                    throw new Exception('Selected plan not found or inactive.');
                }
                
                // Calculate end date based on billing cycle
                $days = ($billing_cycle === 'monthly') ? 30 : (($billing_cycle === 'quarterly') ? 90 : 365);
                $end_date = date('Y-m-d', strtotime("+$days days", strtotime($start_date)));
                
                // Check if tenant already has a subscription
                $stmt = $db->prepare("SELECT id FROM subscriptions WHERE tenant_id = ? AND payment_status IN ('paid', 'pending')");
                $stmt->execute([$tenant_id]);
                if ($stmt->fetch()) {
                    // Update existing subscription
                    $stmt = $db->prepare("
                        UPDATE subscriptions SET 
                            plan = ?,
                            amount = ?,
                            billing_cycle = ?,
                            start_date = ?,
                            end_date = ?,
                            payment_status = ?,
                            updated_at = NOW()
                        WHERE tenant_id = ? AND payment_status IN ('paid', 'pending')
                    ");
                    $stmt->execute([$plan['name'], $plan['price'], $billing_cycle, $start_date, $end_date, $payment_status, $tenant_id]);
                } else {
                    // Insert new subscription
                    $stmt = $db->prepare("
                        INSERT INTO subscriptions (tenant_id, plan, amount, billing_cycle, start_date, end_date, payment_status, currency)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'NGN')
                    ");
                    $stmt->execute([$tenant_id, $plan['name'], $plan['price'], $billing_cycle, $start_date, $end_date, $payment_status]);
                }
                
                // Update tenant with subscription info
                $subscription_status = ($payment_status === 'paid') ? 'active' : 'trial';
                $stmt = $db->prepare("
                    UPDATE tenants SET 
                        subscription_plan = ?, 
                        subscription_status = ?, 
                        subscription_start = ?, 
                        subscription_end = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$plan['name'], $subscription_status, $start_date, $end_date, $tenant_id]);
                
                $action_result = ['success' => true, 'message' => 'Subscription assigned successfully.'];
                break;
                
            case 'renew':
                $stmt = $db->prepare("SELECT * FROM subscriptions WHERE id = ?");
                $stmt->execute([$id]);
                $sub = $stmt->fetch();
                
                if ($sub) {
                    $days = ($sub['billing_cycle'] === 'monthly') ? 30 : (($sub['billing_cycle'] === 'quarterly') ? 90 : 365);
                    $new_end = date('Y-m-d', strtotime($sub['end_date'] . " + $days days"));
                    
                    $stmt = $db->prepare("UPDATE subscriptions SET end_date = ?, payment_status = 'paid', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$new_end, $id]);
                    
                    $stmt = $db->prepare("UPDATE tenants SET subscription_end = ?, subscription_status = 'active' WHERE id = ?");
                    $stmt->execute([$new_end, $sub['tenant_id']]);
                    
                    $action_result = ['success' => true, 'message' => 'Subscription renewed successfully.'];
                }
                break;
                
            case 'suspend':
                $stmt = $db->prepare("UPDATE subscriptions SET payment_status = 'overdue' WHERE id = ?");
                $stmt->execute([$id]);
                
                $stmt = $db->prepare("SELECT tenant_id FROM subscriptions WHERE id = ?");
                $stmt->execute([$id]);
                $sub = $stmt->fetch();
                if ($sub) {
                    $stmt = $db->prepare("UPDATE tenants SET subscription_status = 'suspended' WHERE id = ?");
                    $stmt->execute([$sub['tenant_id']]);
                }
                $action_result = ['success' => true, 'message' => 'Subscription suspended successfully.'];
                break;
                
            case 'activate':
                $stmt = $db->prepare("UPDATE subscriptions SET payment_status = 'paid' WHERE id = ?");
                $stmt->execute([$id]);
                
                $stmt = $db->prepare("SELECT tenant_id FROM subscriptions WHERE id = ?");
                $stmt->execute([$id]);
                $sub = $stmt->fetch();
                if ($sub) {
                    $stmt = $db->prepare("UPDATE tenants SET subscription_status = 'active' WHERE id = ?");
                    $stmt->execute([$sub['tenant_id']]);
                }
                $action_result = ['success' => true, 'message' => 'Subscription activated successfully.'];
                break;
                
            case 'upgrade':
            case 'downgrade':
                $new_plan_id = (int)($_POST['new_plan_id'] ?? 0);
                if ($new_plan_id <= 0) throw new Exception('Please select a plan.');
                
                $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1");
                $stmt->execute([$new_plan_id]);
                $new_plan = $stmt->fetch();
                if (!$new_plan) throw new Exception('Plan not found or inactive.');
                
                $stmt = $db->prepare("SELECT tenant_id FROM subscriptions WHERE id = ?");
                $stmt->execute([$id]);
                $sub = $stmt->fetch();
                
                if ($sub) {
                    $stmt = $db->prepare("
                        UPDATE subscriptions SET 
                            plan = ?, 
                            amount = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_plan['name'], $new_plan['price'], $id]);
                    
                    $stmt = $db->prepare("UPDATE tenants SET subscription_plan = ? WHERE id = ?");
                    $stmt->execute([$new_plan['name'], $sub['tenant_id']]);
                    
                    $action_result = ['success' => true, 'message' => 'Plan ' . ($action === 'upgrade' ? 'upgraded' : 'downgraded') . ' successfully.'];
                }
                break;
        }
    } catch (PDOException $e) {
        $action_result = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        error_log("Subscription error: " . $e->getMessage());
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => $e->getMessage()];
        error_log("Subscription error: " . $e->getMessage());
    }
}

// ============================================================
// FETCH DATA
// ============================================================
$plans = [];
try {
    $stmt = $db->query("SELECT * FROM subscription_plans ORDER BY price ASC");
    $plans = $stmt->fetchAll();
} catch (Exception $e) {}

$subscriptions = [];
try {
    $stmt = $db->query("
        SELECT s.*, t.name as tenant_name
        FROM subscriptions s
        LEFT JOIN tenants t ON s.tenant_id = t.id
        WHERE t.deleted_at IS NULL OR t.deleted_at IS NULL
        ORDER BY s.created_at DESC
        LIMIT 50
    ");
    $subscriptions = $stmt->fetchAll();
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
    .subscription-container { max-width: 1400px; margin: 0 auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
    .page-header h2 { font-size: 1.3rem; font-weight: 700; }
    .page-header h2 small { font-size: 0.8rem; font-weight: 400; color: var(--gray-500); display: block; }
    
    .btn-primary { padding: 8px 18px; background: #8B5CF6; color: white; border: none; border-radius: 10px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .btn-primary:hover { background: #7C3AED; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(139,92,246,0.3); }
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
    .btn-sm.purple { background: #F5F3FF; color: #5B21B6; }
    .btn-sm.purple:hover { background: #EDE9FE; }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .stat-item { background: white; border-radius: 12px; padding: 14px 18px; border: 1px solid var(--gray-200); text-align: center; transition: var(--transition); }
    .stat-item:hover { box-shadow: var(--shadow-hover); transform: translateY(-2px); }
    .stat-item .number { font-size: 1.5rem; font-weight: 700; color: #8B5CF6; }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .label { font-size: 0.7rem; color: var(--gray-500); margin-top: 2px; }

    .filter-bar { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 14px 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; box-shadow: var(--shadow); }
    .filter-bar .search-wrap { flex: 1; min-width: 200px; display: flex; align-items: center; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 10px; padding: 6px 14px; transition: var(--transition); }
    .filter-bar .search-wrap:focus-within { border-color: #8B5CF6; background: white; box-shadow: 0 0 0 3px rgba(139,92,246,0.1); }
    .filter-bar .search-wrap i { color: var(--gray-400); font-size: 0.85rem; }
    .filter-bar .search-wrap input { border: none; outline: none; background: transparent; padding: 6px 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; width: 100%; color: var(--gray-700); }
    .filter-bar select { padding: 8px 14px; border: 1px solid var(--gray-200); border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.82rem; background: var(--gray-50); color: var(--gray-700); cursor: pointer; transition: var(--transition); min-width: 120px; }
    .filter-bar select:focus { outline: none; border-color: #8B5CF6; background: white; }

    .table-container { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow); }
    .table-container .table-header { padding: 16px 20px; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: var(--gray-50); }
    .table-container .table-header .table-title { font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }
    .table-container .table-header .table-title .count { background: #8B5CF6; color: white; padding: 0 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }

    .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .data-table thead { background: var(--gray-50); }
    .data-table thead th { padding: 12px 16px; text-align: left; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
    .data-table tbody td { padding: 10px 16px; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background: var(--gray-50); }

    .badge-status { display: inline-flex; align-items: center; gap: 5px; padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
    .badge-status .dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.suspended { background: #FEF2F2; color: #991B1B; }
    .badge-status.suspended .dot { background: #EF4444; }
    .badge-status.expired { background: #FEF2F2; color: #991B1B; }
    .badge-status.expired .dot { background: #EF4444; }
    .badge-status.trial { background: #FFFBEB; color: #92400E; }
    .badge-status.trial .dot { background: #F59E0B; }

    .plan-card { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 20px; text-align: center; transition: var(--transition); box-shadow: var(--shadow); }
    .plan-card:hover { box-shadow: var(--shadow-hover); transform: translateY(-4px); }
    .plan-card .plan-price { font-size: 2rem; font-weight: 700; color: #8B5CF6; }
    .plan-card .plan-price small { font-size: 1rem; font-weight: 400; color: var(--gray-500); }
    .plan-card .plan-name { font-size: 1.1rem; font-weight: 700; margin-bottom: 4px; }
    .plan-card .plan-features { font-size: 0.8rem; color: var(--gray-500); margin: 10px 0; }
    .plan-card .plan-features li { list-style: none; padding: 4px 0; border-bottom: 1px solid var(--gray-100); }
    .plan-card .plan-features li:last-child { border-bottom: none; }
    .plan-card .plan-features i { color: #8B5CF6; margin-right: 6px; width: 16px; }

    .action-dropdown { position: relative; display: inline-block; }
    .action-dropdown .dropdown-btn { background: none; border: none; padding: 4px 8px; cursor: pointer; color: var(--gray-400); font-size: 1.1rem; transition: var(--transition); border-radius: 6px; }
    .action-dropdown .dropdown-btn:hover { background: var(--gray-100); color: var(--gray-600); }
    .action-dropdown .dropdown-menu { position: absolute; right: 0; top: 100%; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.12); border: 1px solid var(--gray-200); min-width: 180px; padding: 6px; display: none; z-index: 50; animation: dropdownFade 0.2s ease; }
    @keyframes dropdownFade { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
    .action-dropdown .dropdown-menu.open { display: block; }
    .action-dropdown .dropdown-menu a, .action-dropdown .dropdown-menu button { display: flex; align-items: center; gap: 10px; padding: 8px 14px; width: 100%; border: none; background: none; font-family: 'Inter', sans-serif; font-size: 0.8rem; color: var(--gray-600); cursor: pointer; border-radius: 8px; transition: var(--transition); text-decoration: none; }
    .action-dropdown .dropdown-menu a:hover, .action-dropdown .dropdown-menu button:hover { background: var(--gray-50); color: #8B5CF6; }
    .action-dropdown .dropdown-menu .danger:hover { background: #FEF2F2; color: var(--danger); }
    .action-dropdown .dropdown-menu i { width: 16px; color: var(--gray-400); font-size: 0.85rem; }
    .action-dropdown .dropdown-menu .divider { height: 1px; background: var(--gray-100); margin: 4px 8px; }

    .pagination { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 20px; background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); margin-top: 16px; box-shadow: var(--shadow); }
    .pagination .info { font-size: 0.82rem; color: var(--gray-500); }
    .pagination .pages { display: flex; gap: 4px; align-items: center; }
    .pagination .pages a, .pagination .pages span { padding: 6px 14px; border-radius: 8px; font-size: 0.82rem; text-decoration: none; color: var(--gray-600); transition: var(--transition); min-width: 36px; text-align: center; border: 1px solid transparent; }
    .pagination .pages a:hover { background: var(--gray-100); border-color: var(--gray-200); }
    .pagination .pages .active { background: #8B5CF6; color: white; border-color: #8B5CF6; }
    .pagination .pages .disabled { opacity: 0.4; cursor: not-allowed; }

    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 300; align-items: center; justify-content: center; padding: 20px; }
    .modal-overlay.active { display: flex; }
    .modal { background: white; border-radius: var(--radius); max-width: 560px; width: 100%; padding: 28px 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); animation: modalIn 0.25s ease; max-height: 90vh; overflow-y: auto; }
    @keyframes modalIn { from { transform: scale(0.95) translateY(10px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
    .modal .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    .modal .modal-header h3 { font-size: 1.1rem; font-weight: 700; color: var(--gray-800); }
    .modal .modal-header .close-btn { background: none; border: none; font-size: 1.4rem; color: var(--gray-400); cursor: pointer; transition: var(--transition); padding: 0 4px; }
    .modal .modal-header .close-btn:hover { color: var(--gray-600); }
    .modal .form-group { margin-bottom: 14px; display: flex; flex-direction: column; gap: 4px; }
    .modal .form-group label { font-weight: 600; font-size: 0.82rem; color: var(--gray-700); }
    .modal .form-group label .required { color: var(--danger); margin-left: 2px; }
    .modal .form-group input, .modal .form-group select, .modal .form-group textarea { padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; transition: var(--transition); background: var(--gray-50); color: var(--gray-700); width: 100%; }
    .modal .form-group input:focus, .modal .form-group select:focus, .modal .form-group textarea:focus { outline: none; border-color: #8B5CF6; background: white; box-shadow: 0 0 0 3px rgba(139,92,246,0.1); }
    .modal .form-group textarea { resize: vertical; min-height: 60px; }
    .modal .form-group .help-text { font-size: 0.7rem; color: var(--gray-400); margin-top: 2px; }
    .modal .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200); }
    .modal .form-actions .btn { padding: 8px 20px; border-radius: 8px; border: none; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .modal .form-actions .btn-primary { background: #8B5CF6; color: white; }
    .modal .form-actions .btn-primary:hover { background: #7C3AED; }
    .modal .form-actions .btn-secondary { background: var(--gray-100); color: var(--gray-600); }
    .modal .form-actions .btn-secondary:hover { background: var(--gray-200); }
    .modal .form-actions .btn-danger { background: var(--danger); color: white; }
    .modal .form-actions .btn-danger:hover { background: #DC2626; }

    .toast-container { position: fixed; top: 80px; right: 20px; z-index: 999; display: flex; flex-direction: column; gap: 8px; }
    .toast { padding: 14px 20px; border-radius: 10px; color: white; font-size: 0.85rem; font-weight: 500; box-shadow: var(--shadow-hover); animation: slideIn 0.3s ease; min-width: 280px; max-width: 400px; display: flex; align-items: center; gap: 10px; }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    @keyframes slideIn { from { transform: translateX(100px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    .plan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; }

    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .plan-grid { grid-template-columns: 1fr; }
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
        <div class="subscription-container">
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
                        <i class="fas fa-credit-card" style="color:#8B5CF6;margin-right:8px;"></i> Subscription Management
                        <small>Manage plans and subscriptions across the platform</small>
                    </h2>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button onclick="openModal('assignModal')" class="btn-primary">
                        <i class="fas fa-plus-circle"></i> Assign Plan
                    </button>
                    <button onclick="openModal('createPlanModal')" class="btn-primary">
                        <i class="fas fa-plus-circle"></i> Create Plan
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <?php
            $total_plans = count($plans);
            $active_subs = 0;
            $suspended_subs = 0;
            $expired_subs = 0;
            foreach ($subscriptions as $s) {
                if ($s['payment_status'] === 'paid') $active_subs++;
                elseif ($s['payment_status'] === 'overdue') $suspended_subs++;
                elseif ($s['payment_status'] === 'cancelled' || $s['payment_status'] === 'refunded') $expired_subs++;
            }
            ?>
            <div class="stats-grid">
                <div class="stat-item"><div class="number"><?php echo $total_plans; ?></div><div class="label">Total Plans</div></div>
                <div class="stat-item"><div class="number green"><?php echo $active_subs; ?></div><div class="label">Active Subscriptions</div></div>
                <div class="stat-item"><div class="number red"><?php echo $suspended_subs; ?></div><div class="label">Suspended</div></div>
                <div class="stat-item"><div class="number yellow"><?php echo $expired_subs; ?></div><div class="label">Expired</div></div>
            </div>

            <!-- Plans Grid -->
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-cubes" style="color:#8B5CF6;"></i> Subscription Plans
            </h3>
            <div class="plan-grid">
                <?php if (count($plans) > 0): ?>
                    <?php foreach ($plans as $plan): ?>
                        <div class="plan-card" data-plan-id="<?php echo $plan['id']; ?>">
                            <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                            <div class="plan-price">
                                ₦<?php echo number_format($plan['price'], 2); ?>
                                <small>/ <?php echo $plan['duration_days']; ?> days</small>
                            </div>
                            <div style="font-size:0.75rem;color:var(--gray-500);margin:4px 0;">
                                <i class="fas fa-users"></i> <?php echo $plan['user_limit']; ?> users
                                <span style="margin:0 6px;">·</span>
                                <i class="fas fa-database"></i> <?php echo number_format($plan['storage_limit_mb']); ?> MB
                            </div>
                            <ul class="plan-features">
                                <?php 
                                $features = !empty($plan['features']) ? explode("\n", $plan['features']) : ['No features listed'];
                                foreach (array_slice($features, 0, 3) as $feature): 
                                ?>
                                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars(trim($feature)); ?></li>
                                <?php endforeach; ?>
                                <?php if (count($features) > 3): ?>
                                    <li style="color:#8B5CF6;font-weight:500;">+ <?php echo count($features) - 3; ?> more</li>
                                <?php endif; ?>
                            </ul>
                            <div style="margin-top:10px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                                <button onclick="editPlan(<?php echo $plan['id']; ?>)" class="btn-sm info">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deletePlan(<?php echo $plan['id']; ?>)" class="btn-sm danger">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--gray-500);">
                        <i class="fas fa-cubes" style="font-size:2rem;display:block;margin-bottom:10px;color:var(--gray-300);"></i>
                        <p>No subscription plans created yet.</p>
                        <button onclick="openModal('createPlanModal')" class="btn-primary" style="margin-top:10px;">
                            <i class="fas fa-plus-circle"></i> Create First Plan
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Subscriptions Table -->
            <div class="table-container" style="margin-top:20px;">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-list" style="color:#8B5CF6;"></i> Active Subscriptions
                        <span class="count"><?php echo count($subscriptions); ?></span>
                    </div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Cycle</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($subscriptions) > 0): ?>
                            <?php foreach ($subscriptions as $sub): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($sub['tenant_name'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($sub['plan'] ?? 'N/A'); ?></td>
                                    <td>₦<?php echo number_format($sub['amount'] ?? 0, 2); ?></td>
                                    <td><?php echo ucfirst($sub['billing_cycle'] ?? 'monthly'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($sub['start_date'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($sub['end_date'])); ?></td>
                                    <td>
                                        <span class="badge-status <?php echo $sub['payment_status']; ?>">
                                            <span class="dot"></span>
                                            <?php echo ucfirst($sub['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                            <div class="dropdown-menu">
                                                <?php if ($sub['payment_status'] === 'paid'): ?>
                                                    <button onclick="renewSubscription(<?php echo $sub['id']; ?>)"><i class="fas fa-sync"></i> Renew</button>
                                                    <button onclick="suspendSubscription(<?php echo $sub['id']; ?>)"><i class="fas fa-pause"></i> Suspend</button>
                                                    <button onclick="openUpgradeModal(<?php echo $sub['id']; ?>)"><i class="fas fa-arrow-up"></i> Upgrade</button>
                                                <?php elseif ($sub['payment_status'] === 'overdue'): ?>
                                                    <button onclick="activateSubscription(<?php echo $sub['id']; ?>)"><i class="fas fa-play"></i> Activate</button>
                                                <?php endif; ?>
                                                <button class="danger" onclick="if(confirm('Delete this subscription?')){alert('Deleting...');}"><i class="fas fa-trash"></i> Delete</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--gray-500);">No subscriptions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- ============================================================
MODALS
============================================================ -->

<!-- Create Plan Modal -->
<div class="modal-overlay" id="createPlanModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:#8B5CF6;"></i> Create Subscription Plan</h3>
            <button class="close-btn" onclick="closeModal('createPlanModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_plan">
            <div class="form-group">
                <label>Plan Name <span class="required">*</span></label>
                <input type="text" name="name" placeholder="e.g., Premium" required>
            </div>
            <div class="form-group">
                <label>Price (₦) <span class="required">*</span></label>
                <input type="number" name="price" step="0.01" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label>Duration (days) <span class="required">*</span></label>
                <input type="number" name="duration" value="30" required>
            </div>
            <div class="form-group">
                <label>User Limit</label>
                <input type="number" name="user_limit" value="100">
            </div>
            <div class="form-group">
                <label>Storage Limit (MB)</label>
                <input type="number" name="storage_limit" value="10240">
            </div>
            <div class="form-group">
                <label>Features (one per line)</label>
                <textarea name="features" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createPlanModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal-overlay" id="editPlanModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:#8B5CF6;"></i> Edit Subscription Plan</h3>
            <button class="close-btn" onclick="closeModal('editPlanModal')">&times;</button>
        </div>
        <form method="POST" action="" id="editPlanForm">
            <input type="hidden" name="action" value="edit_plan">
            <input type="hidden" name="id" id="editPlanId">
            <div class="form-group">
                <label>Plan Name <span class="required">*</span></label>
                <input type="text" name="name" id="editPlanName" placeholder="e.g., Premium" required>
            </div>
            <div class="form-group">
                <label>Price (₦) <span class="required">*</span></label>
                <input type="number" name="price" id="editPlanPrice" step="0.01" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label>Duration (days) <span class="required">*</span></label>
                <input type="number" name="duration" id="editPlanDuration" required>
            </div>
            <div class="form-group">
                <label>User Limit</label>
                <input type="number" name="user_limit" id="editPlanUserLimit">
            </div>
            <div class="form-group">
                <label>Storage Limit (MB)</label>
                <input type="number" name="storage_limit" id="editPlanStorageLimit">
            </div>
            <div class="form-group">
                <label>Features (one per line)</label>
                <textarea name="features" id="editPlanFeatures" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"></textarea>
            </div>
            <div class="form-group">
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" name="is_active" id="editPlanActive" value="1">
                    <label for="editPlanActive" style="font-weight:400;cursor:pointer;">Active</label>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editPlanModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Plan Modal - FIXED -->
<div class="modal-overlay" id="assignModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus" style="color:#8B5CF6;"></i> Assign Subscription</h3>
            <button class="close-btn" onclick="closeModal('assignModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="assign_plan">
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
                <label>Plan <span class="required">*</span></label>
                <select name="plan_id" required>
                    <option value="">Select Plan</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> - ₦<?php echo number_format($p['price'], 2); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Billing Cycle</label>
                <select name="billing_cycle">
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="yearly">Yearly</option>
                </select>
            </div>
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Payment Status</label>
                <select name="payment_status">
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Upgrade/Downgrade Modal -->
<div class="modal-overlay" id="upgradeModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-arrow-up" style="color:#8B5CF6;"></i> Change Plan</h3>
            <button class="close-btn" onclick="closeModal('upgradeModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" id="upgradeAction" value="upgrade">
            <input type="hidden" name="id" id="upgradeSubscriptionId">
            <div class="form-group">
                <label>Select New Plan <span class="required">*</span></label>
                <select name="new_plan_id" required>
                    <option value="">Select Plan</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> - ₦<?php echo number_format($p['price'], 2); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('upgradeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Change Plan</button>
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
// MODAL FUNCTIONS
// ============================================================
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// ============================================================
// EDIT PLAN FUNCTION
// ============================================================
function editPlan(id) {
    // Fetch plan data via AJAX
    fetch('subscription-ajax.php?action=get_plan&id=' + id)
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                document.getElementById('editPlanId').value = data.plan.id;
                document.getElementById('editPlanName').value = data.plan.name;
                document.getElementById('editPlanPrice').value = data.plan.price;
                document.getElementById('editPlanDuration').value = data.plan.duration_days;
                document.getElementById('editPlanUserLimit').value = data.plan.user_limit;
                document.getElementById('editPlanStorageLimit').value = data.plan.storage_limit_mb;
                document.getElementById('editPlanFeatures').value = data.plan.features || '';
                document.getElementById('editPlanActive').checked = data.plan.is_active == 1;
                openModal('editPlanModal');
            } else {
                alert('Error loading plan data: ' + data.message);
            }
        })
        .catch(function(error) {
            // Fallback: Use pre-populated data from the page
            var card = document.querySelector('.plan-card[data-plan-id="' + id + '"]');
            if (card) {
                var name = card.querySelector('.plan-name').textContent;
                var price = card.querySelector('.plan-price').textContent.replace(/[^0-9.]/g, '');
                var details = card.querySelector('.plan-features');
                var features = '';
                if (details) {
                    var items = details.querySelectorAll('li');
                    items.forEach(function(item) {
                        features += item.textContent.replace('✓', '').trim() + '\n';
                    });
                }
                
                document.getElementById('editPlanId').value = id;
                document.getElementById('editPlanName').value = name;
                document.getElementById('editPlanPrice').value = price;
                document.getElementById('editPlanDuration').value = 30;
                document.getElementById('editPlanUserLimit').value = 100;
                document.getElementById('editPlanStorageLimit').value = 10240;
                document.getElementById('editPlanFeatures').value = features;
                document.getElementById('editPlanActive').checked = true;
                openModal('editPlanModal');
            } else {
                alert('Plan data not found. Please refresh the page.');
            }
        });
}

// ============================================================
// ACTION FUNCTIONS
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

function deletePlan(id) {
    if (confirm('Are you sure you want to delete this plan?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_plan"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function renewSubscription(id) {
    if (confirm('Renew this subscription?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="renew"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function suspendSubscription(id) {
    if (confirm('Suspend this subscription?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="suspend"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function activateSubscription(id) {
    if (confirm('Activate this subscription?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function openUpgradeModal(id) {
    document.getElementById('upgradeSubscriptionId').value = id;
    document.getElementById('upgradeAction').value = 'upgrade';
    openModal('upgradeModal');
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