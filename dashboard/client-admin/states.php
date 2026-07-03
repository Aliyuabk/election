<?php
// ============================================================
// STATES MANAGEMENT - CLIENT ADMIN
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
            case 'add_state':
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $capital = trim($_POST['capital'] ?? '');
                $registered_voters = (int)($_POST['registered_voters'] ?? 0);
                
                if (empty($name) || empty($code)) {
                    throw new Exception('State name and code are required.');
                }
                
                $stmt = $db->prepare("
                    INSERT INTO states (name, code, capital, registered_voters, is_active)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmt->execute([$name, $code, $capital, $registered_voters]);
                
                logActivity($user_id, 'state_added', "Added state: $name");
                $action_result = ['success' => true, 'message' => "State '$name' added successfully."];
                break;
                
            case 'edit_state':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $capital = trim($_POST['capital'] ?? '');
                $registered_voters = (int)($_POST['registered_voters'] ?? 0);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if ($id <= 0 || empty($name) || empty($code)) {
                    throw new Exception('Invalid data provided.');
                }
                
                $stmt = $db->prepare("
                    UPDATE states SET 
                        name = ?, code = ?, capital = ?, 
                        registered_voters = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $code, $capital, $registered_voters, $is_active, $id]);
                
                logActivity($user_id, 'state_updated', "Updated state ID: $id");
                $action_result = ['success' => true, 'message' => "State updated successfully."];
                break;
                
            case 'delete_state':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid state ID.');
                
                // Check if state has LGAs
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM lgas WHERE state_id = ?");
                $stmt->execute([$id]);
                $count = $stmt->fetch()['count'] ?? 0;
                
                if ($count > 0) {
                    throw new Exception("Cannot delete state. It has $count LGAs assigned.");
                }
                
                $stmt = $db->prepare("DELETE FROM states WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity($user_id, 'state_deleted', "Deleted state ID: $id");
                $action_result = ['success' => true, 'message' => 'State deleted successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH STATES
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clause = "";
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE name LIKE ? OR code LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param];
}

// Count total
$count_sql = "SELECT COUNT(*) as total FROM states $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_states = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_states / $limit);

// Fetch states
$sql = "SELECT * FROM states $where_clause ORDER BY name LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$states = $stmt->fetchAll();

// Get counts for each state
foreach ($states as &$state) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lgas WHERE state_id = ?");
    $stmt->execute([$state['id']]);
    $state['lga_count'] = $stmt->fetch()['count'] ?? 0;
}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total_states' => 0,
    'active_states' => 0,
    'total_lgas' => 0,
    'total_wards' => 0,
    'total_pus' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM states");
    $stats['total_states'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM states WHERE is_active = 1");
    $stats['active_states'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM lgas");
    $stats['total_lgas'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM wards");
    $stats['total_wards'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM polling_units");
    $stats['total_pus'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       POLITICAL STRUCTURE - PRO STYLES
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
    
    .structure-breadcrumb {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 12px 20px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        box-shadow: var(--shadow);
    }
    .structure-breadcrumb .crumb {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        color: var(--gray-500);
    }
    .structure-breadcrumb .crumb.active {
        color: var(--primary);
        font-weight: 600;
    }
    .structure-breadcrumb .crumb i {
        font-size: 0.8rem;
    }
    .structure-breadcrumb .separator {
        color: var(--gray-300);
    }
    .structure-breadcrumb .crumb-link {
        color: var(--gray-500);
        text-decoration: none;
        transition: var(--transition);
        padding: 4px 8px;
        border-radius: 6px;
    }
    .structure-breadcrumb .crumb-link:hover {
        background: var(--gray-100);
        color: var(--primary);
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
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.inactive { background: #FEF2F2; color: #991B1B; }
    .badge-status.inactive .dot { background: #EF4444; }
    
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
        max-width: 520px;
        width: 100%;
        padding: 24px 28px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        animation: modalIn 0.25s ease;
        max-height: 90vh;
        overflow-y: auto;
    }
    @keyframes modalIn {
        from { transform: scale(0.95) translateY(10px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--gray-100);
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
        padding: 0 4px;
    }
    .modal .modal-header .close-btn:hover {
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
        padding: 10px 14px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
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
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .modal .form-group .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 6px;
    }
    .modal .form-group .checkbox-group input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: var(--primary);
        cursor: pointer;
        flex-shrink: 0;
    }
    .modal .form-group .checkbox-group label {
        font-weight: 400;
        cursor: pointer;
        font-size: 0.85rem;
    }
    .modal .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--gray-200);
    }
    .modal .form-actions .btn {
        padding: 8px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .modal .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .modal .form-actions .btn-primary:hover {
        background: var(--primary-dark);
    }
    .modal .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .form-actions .btn-secondary:hover {
        background: var(--gray-200);
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
    
    .structure-nav {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .structure-nav a {
        padding: 8px 18px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: var(--transition);
        background: white;
        border: 1px solid var(--gray-200);
        color: var(--gray-600);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .structure-nav a:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    .structure-nav a.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .structure-nav a .count {
        background: var(--gray-200);
        color: var(--gray-600);
        padding: 0 8px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-left: 4px;
    }
    .structure-nav a.active .count {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-wrap { min-width: auto; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .pagination { flex-direction: column; align-items: center; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .modal { padding: 16px; margin: 10px; }
        .modal .form-actions { flex-direction: column; }
        .modal .form-actions .btn { width: 100%; justify-content: center; }
        .structure-nav { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .structure-nav a { white-space: nowrap; font-size: 0.78rem; padding: 6px 14px; }
        .structure-breadcrumb { flex-wrap: nowrap; overflow-x: auto; font-size: 0.75rem; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.2rem; }
        .data-table th, .data-table td { padding: 4px 8px; font-size: 0.7rem; }
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
                    <i class="fas fa-sitemap" style="color:var(--primary);margin-right:8px;"></i> Political Structure
                    <small>Build and manage your organization's election hierarchy</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('importModal')" class="btn-outline">
                    <i class="fas fa-file-import"></i> Import
                </button>
                <button onclick="openModal('addStateModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Add State
                </button>
            </div>
        </div>

        <!-- Structure Breadcrumb -->
        <div class="structure-breadcrumb">
            <span class="crumb active">
                <i class="fas fa-flag"></i> States
            </span>
            <span class="separator">/</span>
            <a href="lgas.php" class="crumb-link">
                <i class="fas fa-map-marker-alt"></i> LGAs
            </a>
            <span class="separator">/</span>
            <a href="wards.php" class="crumb-link">
                <i class="fas fa-layer-group"></i> Wards
            </a>
            <span class="separator">/</span>
            <a href="polling-units.php" class="crumb-link">
                <i class="fas fa-flag-checkered"></i> Polling Units
            </a>
        </div>

        <!-- Structure Navigation -->
        <div class="structure-nav">
            <a href="states.php" class="active">
                <i class="fas fa-flag"></i> States
                <span class="count"><?php echo $stats['total_states']; ?></span>
            </a>
            <a href="lgas.php">
                <i class="fas fa-map-marker-alt"></i> LGAs
                <span class="count"><?php echo $stats['total_lgas']; ?></span>
            </a>
            <a href="wards.php">
                <i class="fas fa-layer-group"></i> Wards
                <span class="count"><?php echo $stats['total_wards']; ?></span>
            </a>
            <a href="polling-units.php">
                <i class="fas fa-flag-checkered"></i> Polling Units
                <span class="count"><?php echo $stats['total_pus']; ?></span>
            </a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item"><div class="number"><?php echo number_format($stats['total_states']); ?></div><div class="label">Total States</div></div>
            <div class="stat-item"><div class="number green"><?php echo number_format($stats['active_states']); ?></div><div class="label">Active</div></div>
            <div class="stat-item"><div class="number purple"><?php echo number_format($stats['total_lgas']); ?></div><div class="label">LGAs</div></div>
            <div class="stat-item"><div class="number blue"><?php echo number_format($stats['total_wards']); ?></div><div class="label">Wards</div></div>
            <div class="stat-item"><div class="number yellow"><?php echo number_format($stats['total_pus']); ?></div><div class="label">Polling Units</div></div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search states by name or code..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search)): ?>
                    <a href="states.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- States Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> States
                    <span class="count"><?php echo number_format($total_states); ?></span>
                </div>
                <div class="table-actions">
                    <span style="font-size:0.75rem;color:var(--gray-400);">
                        Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_states); ?>
                    </span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Capital</th>
                        <th>Voters</th>
                        <th>LGAs</th>
                        <th>Status</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($states) > 0): ?>
                        <?php foreach ($states as $index => $state): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div style="font-weight:500;font-size:0.85rem;">
                                        <?php echo htmlspecialchars($state['name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-family:monospace;font-size:0.8rem;background:var(--gray-50);padding:2px 8px;border-radius:4px;">
                                        <?php echo htmlspecialchars($state['code']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($state['capital'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($state['registered_voters'] ?? 0); ?></td>
                                <td><?php echo number_format($state['lga_count'] ?? 0); ?></td>
                                <td>
                                    <span class="badge-status <?php echo $state['is_active'] ? 'active' : 'inactive'; ?>">
                                        <span class="dot"></span>
                                        <?php echo $state['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <button onclick="editState(<?php echo $state['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                            <button onclick="viewStateDetails(<?php echo $state['id']; ?>)">
                                                <i class="fas fa-info-circle"></i> Details
                                            </button>
                                            <a href="lgas.php?state=<?php echo $state['id']; ?>"><i class="fas fa-map-marker-alt"></i> View LGAs</a>
                                            <button class="danger" onclick="deleteState(<?php echo $state['id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-flag"></i>
                                    <h4>No states found</h4>
                                    <p>Add a state to start building your political structure.</p>
                                    <button onclick="openModal('addStateModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Add State
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
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_states); ?></strong> of <strong><?php echo number_format($total_states); ?></strong> states
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
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

<!-- Add State Modal -->
<div class="modal-overlay" id="addStateModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Add State</h3>
            <button class="close-btn" onclick="closeModal('addStateModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_state">
            <div class="form-group">
                <label>State Name <span class="required">*</span></label>
                <input type="text" name="name" placeholder="e.g., Lagos" required>
            </div>
            <div class="form-group">
                <label>State Code <span class="required">*</span></label>
                <input type="text" name="code" placeholder="e.g., LA" maxlength="10" required>
                <div class="help-text">Unique code for the state (e.g., LA, AB, KD)</div>
            </div>
            <div class="form-group">
                <label>Capital</label>
                <input type="text" name="capital" placeholder="e.g., Ikeja">
            </div>
            <div class="form-group">
                <label>Registered Voters</label>
                <input type="number" name="registered_voters" placeholder="0" min="0">
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addStateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add State</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit State Modal -->
<div class="modal-overlay" id="editStateModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);"></i> Edit State</h3>
            <button class="close-btn" onclick="closeModal('editStateModal')">&times;</button>
        </div>
        <form method="POST" action="" id="editStateForm">
            <input type="hidden" name="action" value="edit_state">
            <input type="hidden" name="id" id="editStateId">
            <div class="form-group">
                <label>State Name <span class="required">*</span></label>
                <input type="text" name="name" id="editStateName" required>
            </div>
            <div class="form-group">
                <label>State Code <span class="required">*</span></label>
                <input type="text" name="code" id="editStateCode" maxlength="10" required>
            </div>
            <div class="form-group">
                <label>Capital</label>
                <input type="text" name="capital" id="editStateCapital">
            </div>
            <div class="form-group">
                <label>Registered Voters</label>
                <input type="number" name="registered_voters" id="editStateVoters" min="0">
            </div>
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="editStateActive" value="1">
                    <label for="editStateActive">Active</label>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editStateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update State</button>
            </div>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div class="modal-overlay" id="importModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-file-import" style="color:var(--primary);"></i> Import Structure</h3>
            <button class="close-btn" onclick="closeModal('importModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label>File <span class="required">*</span></label>
                <div class="file-upload-area" onclick="document.getElementById('importFile').click()" style="border:2px dashed var(--gray-200);border-radius:10px;padding:24px;text-align:center;cursor:pointer;transition:var(--transition);background:var(--gray-50);">
                    <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:var(--gray-400);display:block;margin-bottom:8px;"></i>
                    <p style="font-size:0.9rem;color:var(--gray-500);">Click to upload or drag &amp; drop</p>
                    <div style="font-size:0.7rem;color:var(--gray-400);margin-top:4px;">Supported: Excel (.xlsx), CSV</div>
                    <input type="file" name="import_file" id="importFile" accept=".xlsx,.csv" required>
                </div>
                <div class="file-preview" id="importPreview" style="display:none;margin-top:12px;padding:10px;background:var(--gray-50);border-radius:8px;">
                    <span id="importFileName" style="font-weight:500;font-size:0.85rem;">file.xlsx</span>
                    <span id="importFileSize" style="font-size:0.7rem;color:var(--gray-400);margin-left:8px;">0 KB</span>
                </div>
                <div class="help-text">
                    <i class="fas fa-info-circle"></i> 
                    Required columns: name, code, capital, registered_voters
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('importModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Import Data</button>
            </div>
        </form>
    </div>
</div>
<!-- Details Modal - Add this to all structure pages -->
<div class="modal-overlay" id="detailsModal">
    <div class="modal" style="max-width: 640px;">
        <div class="modal-header">
            <h3 id="detailsModalTitle">
                <i class="fas fa-info-circle" style="color:var(--primary);"></i> 
                <span id="detailsTitleText">Details</span>
            </h3>
            <button class="close-btn" onclick="closeModal('detailsModal')">&times;</button>
        </div>
        <div id="detailsContent" style="padding: 4px 0;">
            <!-- Content loaded via AJAX -->
            <div style="text-align:center;padding:40px 0;">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--primary);"></i>
                <p style="margin-top:12px;color:var(--gray-500);">Loading details...</p>
            </div>
        </div>
        <div class="form-actions" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-100);">
            <button type="button" class="btn btn-secondary" onclick="closeModal('detailsModal')">Close</button>
        </div>
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

// // ============================================================
// STATE FUNCTIONS - WITH AJAX POPUP
// ============================================================
function editState(id) {
    // Show loading state
    var modal = document.getElementById('editStateModal');
    var form = document.getElementById('editStateForm');
    var submitBtn = form.querySelector('.btn-primary');
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    submitBtn.disabled = true;
    
    // Fetch data via AJAX
    fetch('api/get_state.php?id=' + id)
        .then(function(response) { 
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json(); 
        })
        .then(function(data) {
            if (data && data.success) {
                var state = data.data;
                document.getElementById('editStateId').value = state.id;
                document.getElementById('editStateName').value = state.name || '';
                document.getElementById('editStateCode').value = state.code || '';
                document.getElementById('editStateCapital').value = state.capital || '';
                document.getElementById('editStateVoters').value = state.registered_voters || 0;
                document.getElementById('editStateActive').checked = state.is_active == 1;
                
                // Restore button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                // Open modal
                openModal('editStateModal');
            } else {
                alert('Error: ' + (data.message || 'Failed to load state data'));
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('Error loading state data. Please try again.');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
}

function deleteState(id) {
    if (confirm('Delete this state? This will also remove all associated LGAs, Wards, and Polling Units.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_state"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================================
// STATE DETAILS VIEW
// ============================================================
function viewStateDetails(id) {
    var modal = document.getElementById('detailsModal');
    var title = document.getElementById('detailsTitleText');
    var content = document.getElementById('detailsContent');
    
    // Show loading
    title.textContent = 'Loading...';
    content.innerHTML = `
        <div style="text-align:center;padding:40px 0;">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--primary);"></i>
            <p style="margin-top:12px;color:var(--gray-500);">Loading state details...</p>
        </div>
    `;
    openModal('detailsModal');
    
    fetch('api/get_state_details.php?id=' + id)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data && data.success) {
                var d = data.data;
                var state = d.state;
                var stats = d.stats;
                var recentLgas = d.recent_lgas || [];
                
                title.textContent = state.name + ' - State Details';
                
                var html = '';
                
                // Status Badge
                var statusBadge = state.is_active ? 
                    '<span class="badge-status active"><span class="dot"></span> Active</span>' : 
                    '<span class="badge-status inactive"><span class="dot"></span> Inactive</span>';
                
                // Main Info
                html += `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Code</div>
                            <div style="font-weight:600;font-size:1rem;font-family:monospace;">${state.code || 'N/A'}</div>
                        </div>
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Status</div>
                            <div>${statusBadge}</div>
                        </div>
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Capital</div>
                            <div style="font-weight:600;">${state.capital || 'N/A'}</div>
                        </div>
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Registered Voters</div>
                            <div style="font-weight:600;font-size:1rem;">${Number(state.registered_voters || 0).toLocaleString()}</div>
                        </div>
                    </div>
                `;
                
                // Statistics Cards
                html += `
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px;">
                        <div style="background:#EFF6FF;padding:12px;border-radius:8px;text-align:center;">
                            <div style="font-size:1.2rem;font-weight:700;color:#1E40AF;">${stats.lga_count}</div>
                            <div style="font-size:0.65rem;color:var(--gray-500);font-weight:500;">LGAs</div>
                        </div>
                        <div style="background:#F5F3FF;padding:12px;border-radius:8px;text-align:center;">
                            <div style="font-size:1.2rem;font-weight:700;color:#6D28D9;">${stats.ward_count}</div>
                            <div style="font-size:0.65rem;color:var(--gray-500);font-weight:500;">Wards</div>
                        </div>
                        <div style="background:#FEF3C7;padding:12px;border-radius:8px;text-align:center;">
                            <div style="font-size:1.2rem;font-weight:700;color:#92400E;">${stats.pu_count}</div>
                            <div style="font-size:0.65rem;color:var(--gray-500);font-weight:500;">Polling Units</div>
                        </div>
                        <div style="background:#ECFDF5;padding:12px;border-radius:8px;text-align:center;">
                            <div style="font-size:1.2rem;font-weight:700;color:#065F46;">${Number(stats.total_voters).toLocaleString()}</div>
                            <div style="font-size:0.65rem;color:var(--gray-500);font-weight:500;">Total Voters</div>
                        </div>
                    </div>
                `;
                
                // Recent LGAs
                if (recentLgas.length > 0) {
                    html += `
                        <div style="margin-top:8px;">
                            <div style="font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:8px;">
                                <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> Recent LGAs
                            </div>
                            <div style="background:var(--gray-50);border-radius:8px;overflow:hidden;">
                                <table style="width:100%;font-size:0.8rem;border-collapse:collapse;">
                                    <thead style="background:var(--gray-100);">
                                        <tr>
                                            <th style="padding:6px 12px;text-align:left;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--gray-500);">Name</th>
                                            <th style="padding:6px 12px;text-align:left;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--gray-500);">Code</th>
                                            <th style="padding:6px 12px;text-align:right;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--gray-500);">Voters</th>
                                            <th style="padding:6px 12px;text-align:center;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--gray-500);">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            `;
                    recentLgas.forEach(function(lga) {
                        var status = lga.is_active ? 
                            '<span style="color:#10B981;font-size:0.6rem;"><i class="fas fa-circle"></i> Active</span>' : 
                            '<span style="color:#EF4444;font-size:0.6rem;"><i class="fas fa-circle"></i> Inactive</span>';
                        html += `
                            <tr style="border-bottom:1px solid var(--gray-200);">
                                <td style="padding:6px 12px;font-weight:500;">${lga.name}</td>
                                <td style="padding:6px 12px;font-family:monospace;font-size:0.7rem;color:var(--gray-500);">${lga.code}</td>
                                <td style="padding:6px 12px;text-align:right;">${Number(lga.registered_voters || 0).toLocaleString()}</td>
                                <td style="padding:6px 12px;text-align:center;">${status}</td>
                            </tr>
                        `;
                    });
                    html += `
                                    </tbody>
                                </table>
                            </div>
                            ${stats.lga_count > 5 ? `<div style="text-align:right;margin-top:6px;font-size:0.7rem;color:var(--gray-400);">+ ${stats.lga_count - 5} more LGAs</div>` : ''}
                        </div>
                    `;
                }
                
                content.innerHTML = html;
            } else {
                content.innerHTML = `
                    <div style="text-align:center;padding:40px 0;color:var(--danger);">
                        <i class="fas fa-exclamation-circle" style="font-size:2rem;"></i>
                        <p style="margin-top:12px;">${data.message || 'Failed to load state details'}</p>
                    </div>
                `;
            }
        })
        .catch(function(error) {
            content.innerHTML = `
                <div style="text-align:center;padding:40px 0;color:var(--danger);">
                    <i class="fas fa-exclamation-triangle" style="font-size:2rem;"></i>
                    <p style="margin-top:12px;">Error loading state details. Please try again.</p>
                </div>
            `;
        });
}
// ============================================================
// FILE IMPORT PREVIEW
// ============================================================
document.getElementById('importFile').addEventListener('change', function() {
    var preview = document.getElementById('importPreview');
    var fileName = document.getElementById('importFileName');
    var fileSize = document.getElementById('importFileSize');
    
    if (this.files && this.files[0]) {
        var file = this.files[0];
        fileName.textContent = file.name;
        fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
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