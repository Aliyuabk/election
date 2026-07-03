<?php
// ============================================================
// LGAS MANAGEMENT - CLIENT ADMIN (PROFESSIONAL UI)
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
            case 'add_lga':
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $state_id = (int)($_POST['state_id'] ?? 0);
                $registered_voters = (int)($_POST['registered_voters'] ?? 0);
                $gps_lat = !empty($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
                $gps_lng = !empty($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;
                
                if (empty($name) || empty($code) || $state_id <= 0) {
                    throw new Exception('Name, code, and state are required.');
                }
                
                $stmt = $db->prepare("
                    INSERT INTO lgas (state_id, code, name, registered_voters, gps_lat, gps_lng, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$state_id, $code, $name, $registered_voters, $gps_lat, $gps_lng]);
                
                logActivity($user_id, 'lga_added', "Added LGA: $name");
                $action_result = ['success' => true, 'message' => "LGA '$name' added successfully."];
                break;
                
            case 'edit_lga':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $state_id = (int)($_POST['state_id'] ?? 0);
                $registered_voters = (int)($_POST['registered_voters'] ?? 0);
                $gps_lat = !empty($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
                $gps_lng = !empty($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if ($id <= 0 || empty($name) || empty($code) || $state_id <= 0) {
                    throw new Exception('Invalid data provided.');
                }
                
                $stmt = $db->prepare("
                    UPDATE lgas SET 
                        state_id = ?, code = ?, name = ?, 
                        registered_voters = ?, gps_lat = ?, gps_lng = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$state_id, $code, $name, $registered_voters, $gps_lat, $gps_lng, $is_active, $id]);
                
                logActivity($user_id, 'lga_updated', "Updated LGA ID: $id");
                $action_result = ['success' => true, 'message' => 'LGA updated successfully.'];
                break;
                
            case 'delete_lga':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid LGA ID.');
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM wards WHERE lga_id = ?");
                $stmt->execute([$id]);
                $count = $stmt->fetch()['count'] ?? 0;
                
                if ($count > 0) {
                    throw new Exception("Cannot delete LGA. It has $count wards assigned.");
                }
                
                $stmt = $db->prepare("DELETE FROM lgas WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity($user_id, 'lga_deleted', "Deleted LGA ID: $id");
                $action_result = ['success' => true, 'message' => 'LGA deleted successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH STATES FOR DROPDOWN
// ============================================================
$states = [];
try {
    $stmt = $db->query("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH LGAS WITH PAGINATION & FILTERS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$state_filter = isset($_GET['state']) ? (int)$_GET['state'] : 0;

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(l.name LIKE ? OR l.code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if ($state_filter > 0) {
    $where_conditions[] = "l.state_id = ?";
    $params[] = $state_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total
$count_sql = "SELECT COUNT(*) as total FROM lgas l $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_lgas = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_lgas / $limit);

// Fetch LGAs
$sql = "
    SELECT l.*, s.name as state_name 
    FROM lgas l
    LEFT JOIN states s ON l.state_id = s.id
    $where_clause
    ORDER BY s.name, l.name
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$lgas = $stmt->fetchAll();

// Get ward count for each LGA
foreach ($lgas as &$lga) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM wards WHERE lga_id = ?");
    $stmt->execute([$lga['id']]);
    $lga['ward_count'] = $stmt->fetch()['count'] ?? 0;
}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total_lgas' => 0,
    'active_lgas' => 0,
    'total_wards' => 0,
    'total_pus' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM lgas");
    $stats['total_lgas'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM lgas WHERE is_active = 1");
    $stats['active_lgas'] = $stmt->fetch()['total'] ?? 0;
    
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
       LGAS - PROFESSIONAL UI STYLES
       ============================================================ */
    
    .structure-breadcrumb {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 14px 24px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        box-shadow: var(--shadow);
        transition: var(--transition);
    }
    .structure-breadcrumb:hover {
        box-shadow: var(--shadow-hover);
    }
    .structure-breadcrumb .crumb {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        color: var(--gray-500);
        padding: 4px 6px;
        border-radius: 6px;
        transition: var(--transition);
    }
    .structure-breadcrumb .crumb.active {
        color: var(--primary);
        font-weight: 600;
        background: #EFF6FF;
    }
    .structure-breadcrumb .crumb-link {
        color: var(--gray-500);
        text-decoration: none;
        transition: var(--transition);
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 500;
    }
    .structure-breadcrumb .crumb-link:hover {
        background: var(--gray-100);
        color: var(--primary);
        transform: translateY(-1px);
    }
    .structure-breadcrumb .separator {
        color: var(--gray-300);
        font-size: 0.7rem;
    }
    
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
    .structure-nav a:hover .count {
        background: var(--gray-300);
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
        cursor: pointer;
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
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .label {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-top: 4px;
        font-weight: 500;
    }
    .stat-item .trend {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-top: 4px;
        padding: 2px 10px;
        border-radius: 12px;
    }
    .stat-item .trend.up { background: #ECFDF5; color: var(--secondary); }
    .stat-item .trend.down { background: #FEF2F2; color: var(--danger); }
    
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
        min-width: 200px;
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
        min-width: 140px;
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
    .table-container .table-header .table-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .table-container .table-header .table-actions span {
        font-size: 0.75rem;
        color: var(--gray-400);
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
    
    .lga-code {
        font-family: 'Courier New', monospace;
        font-size: 0.75rem;
        background: var(--gray-100);
        padding: 3px 10px;
        border-radius: 6px;
        display: inline-block;
        color: var(--gray-600);
        font-weight: 500;
        border: 1px solid var(--gray-200);
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
    .badge-status.active { 
        background: #ECFDF5; 
        color: #065F46;
        border: 1px solid #A7F3D0;
    }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.inactive { 
        background: #FEF2F2; 
        color: #991B1B;
        border: 1px solid #FECACA;
    }
    .badge-status.inactive .dot { background: #EF4444; }
    
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
    .modal .form-group input,
    .modal .form-group select,
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
    .modal .form-group input:focus,
    .modal .form-group select:focus,
    .modal .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .modal .form-group .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 4px;
    }
    .modal .form-group .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary);
        cursor: pointer;
        flex-shrink: 0;
    }
    .modal .form-group .checkbox-group label {
        font-weight: 400;
        cursor: pointer;
        font-size: 0.85rem;
        color: var(--gray-700);
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
        from { opacity: 0; transform: translateX(40px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-wrap { min-width: auto; }
        .filter-bar select { width: 100%; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 8px 12px; }
        .pagination { flex-direction: column; align-items: center; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .modal { padding: 20px; margin: 10px; }
        .modal .form-actions { flex-direction: column; }
        .modal .form-actions .btn { width: 100%; justify-content: center; }
        .structure-nav { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 6px 8px; }
        .structure-nav a { white-space: nowrap; font-size: 0.78rem; padding: 6px 12px; }
        .structure-breadcrumb { flex-wrap: nowrap; overflow-x: auto; font-size: 0.75rem; padding: 10px 16px; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 12px 14px; }
        .stat-item .number { font-size: 1.3rem; }
        .data-table th, .data-table td { padding: 6px 8px; font-size: 0.7rem; }
        .lga-code { font-size: 0.65rem; padding: 2px 6px; }
        .badge-status { font-size: 0.55rem; padding: 2px 8px; }
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
                    <i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:8px;"></i> Local Government Areas
                    <small>Manage LGAs in your political structure</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('addLgaModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Add LGA
                </button>
            </div>
        </div>

        <!-- Structure Breadcrumb -->
        <div class="structure-breadcrumb">
            <a href="states.php" class="crumb-link">
                <i class="fas fa-flag"></i> States
            </a>
            <span class="separator">/</span>
            <span class="crumb active">
                <i class="fas fa-map-marker-alt"></i> LGAs
            </span>
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
            <a href="states.php">
                <i class="fas fa-flag"></i> States
                <span class="count"><?php echo $stats['total_lgas'] ?? 0; ?></span>
            </a>
            <a href="lgas.php" class="active">
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
            <div class="stat-item">
                <div class="number"><?php echo number_format($stats['total_lgas']); ?></div>
                <div class="label">Total LGAs</div>
                <div class="trend up"><i class="fas fa-arrow-up"></i> Active</div>
            </div>
            <div class="stat-item">
                <div class="number green"><?php echo number_format($stats['active_lgas']); ?></div>
                <div class="label">Active</div>
                <div class="trend up"><i class="fas fa-check-circle"></i> Online</div>
            </div>
            <div class="stat-item">
                <div class="number purple"><?php echo number_format($stats['total_wards']); ?></div>
                <div class="label">Wards</div>
                <div class="trend up"><i class="fas fa-layer-group"></i> Total</div>
            </div>
            <div class="stat-item">
                <div class="number yellow"><?php echo number_format($stats['total_pus']); ?></div>
                <div class="label">Polling Units</div>
                <div class="trend up"><i class="fas fa-flag-checkered"></i> Total</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search LGAs by name or code..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="state">
                    <option value="">All States</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?php echo $state['id']; ?>" <?php echo $state_filter == $state['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($state['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || $state_filter > 0): ?>
                    <a href="lgas.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- LGAs Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list"></i> Local Government Areas
                    <span class="count"><?php echo number_format($total_lgas); ?></span>
                </div>
                <div class="table-actions">
                    <span>Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_lgas); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>State</th>
                        <th>Voters</th>
                        <th>Wards</th>
                        <th>Status</th>
                        <th style="text-align:center;width:60px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($lgas) > 0): ?>
                        <?php foreach ($lgas as $index => $lga): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div style="font-weight:500;font-size:0.85rem;">
                                        <?php echo htmlspecialchars($lga['name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="lga-code"><?php echo htmlspecialchars($lga['code']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($lga['state_name'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($lga['registered_voters'] ?? 0); ?></td>
                                <td><?php echo number_format($lga['ward_count'] ?? 0); ?></td>
                                <td>
                                    <span class="badge-status <?php echo $lga['is_active'] ? 'active' : 'inactive'; ?>">
                                        <span class="dot"></span>
                                        <?php echo $lga['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <button onclick="editLga(<?php echo $lga['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                            <button onclick="viewLgaDetails(<?php echo $lga['id']; ?>)">
                                                    <i class="fas fa-info-circle"></i> Details
                                                </button>
                                            <a href="wards.php?lga=<?php echo $lga['id']; ?>"><i class="fas fa-layer-group"></i> View Wards</a>
                                            <div class="divider"></div>
                                            <button class="danger" onclick="deleteLga(<?php echo $lga['id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <h4>No LGAs found</h4>
                                    <p>Add an LGA to start building your political structure.</p>
                                    <button onclick="openModal('addLgaModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Add LGA
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
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_lgas); ?></strong> of <strong><?php echo number_format($total_lgas); ?></strong> LGAs
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&state=<?php echo $state_filter; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&state=' . $state_filter . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&state=<?php echo $state_filter; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&state=' . $state_filter . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&state=<?php echo $state_filter; ?>">
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

<!-- Add LGA Modal -->
<div class="modal-overlay" id="addLgaModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Add LGA</h3>
            <button class="close-btn" onclick="closeModal('addLgaModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_lga">
            <div class="form-group">
                <label>State <span class="required">*</span></label>
                <select name="state_id" required>
                    <option value="">Select State</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?php echo $state['id']; ?>"><?php echo htmlspecialchars($state['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>LGA Name <span class="required">*</span></label>
                <input type="text" name="name" placeholder="e.g., Ikeja" required>
            </div>
            <div class="form-group">
                <label>LGA Code <span class="required">*</span></label>
                <input type="text" name="code" placeholder="e.g., IK" maxlength="10" required>
            </div>
            <div class="form-group">
                <label>Registered Voters</label>
                <input type="number" name="registered_voters" placeholder="0" min="0">
                <div class="help-text">Total registered voters in this LGA</div>
            </div>
            <div class="form-group">
                <label>GPS Coordinates (Optional)</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <input type="text" name="gps_lat" placeholder="Latitude" style="width:100%;">
                    <input type="text" name="gps_lng" placeholder="Longitude" style="width:100%;">
                </div>
                <div class="help-text">e.g., 6.5244 for latitude, 3.3792 for longitude</div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addLgaModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add LGA</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit LGA Modal -->
<div class="modal-overlay" id="editLgaModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);"></i> Edit LGA</h3>
            <button class="close-btn" onclick="closeModal('editLgaModal')">&times;</button>
        </div>
        <form method="POST" action="" id="editLgaForm">
            <input type="hidden" name="action" value="edit_lga">
            <input type="hidden" name="id" id="editLgaId">
            <div class="form-group">
                <label>State <span class="required">*</span></label>
                <select name="state_id" id="editLgaState" required>
                    <option value="">Select State</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?php echo $state['id']; ?>"><?php echo htmlspecialchars($state['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>LGA Name <span class="required">*</span></label>
                <input type="text" name="name" id="editLgaName" required>
            </div>
            <div class="form-group">
                <label>LGA Code <span class="required">*</span></label>
                <input type="text" name="code" id="editLgaCode" maxlength="10" required>
            </div>
            <div class="form-group">
                <label>Registered Voters</label>
                <input type="number" name="registered_voters" id="editLgaVoters" min="0">
            </div>
            <div class="form-group">
                <label>GPS Coordinates (Optional)</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <input type="text" name="gps_lat" id="editLgaLat" placeholder="Latitude">
                    <input type="text" name="gps_lng" id="editLgaLng" placeholder="Longitude">
                </div>
            </div>
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="editLgaActive" value="1">
                    <label for="editLgaActive">Active</label>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editLgaModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update LGA</button>
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

// ============================================================
// LGA FUNCTIONS
// ============================================================
// ============================================================
// LGA FUNCTIONS - WITH AJAX POPUP
// ============================================================
function editLga(id) {
    var modal = document.getElementById('editLgaModal');
    var form = document.getElementById('editLgaForm');
    var submitBtn = form.querySelector('.btn-primary');
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    submitBtn.disabled = true;
    
    fetch('api/get_lga.php?id=' + id)
        .then(function(response) { 
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json(); 
        })
        .then(function(data) {
            if (data && data.success) {
                var lga = data.data;
                document.getElementById('editLgaId').value = lga.id;
                document.getElementById('editLgaState').value = lga.state_id || '';
                document.getElementById('editLgaName').value = lga.name || '';
                document.getElementById('editLgaCode').value = lga.code || '';
                document.getElementById('editLgaVoters').value = lga.registered_voters || 0;
                document.getElementById('editLgaLat').value = lga.gps_lat || '';
                document.getElementById('editLgaLng').value = lga.gps_lng || '';
                document.getElementById('editLgaActive').checked = lga.is_active == 1;
                
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                openModal('editLgaModal');
            } else {
                alert('Error: ' + (data.message || 'Failed to load LGA data'));
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('Error loading LGA data. Please try again.');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
}

function deleteLga(id) {
    if (confirm('Delete this LGA? This will also remove all associated Wards and Polling Units.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_lga"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}


// ============================================================
// LGA DETAILS VIEW
// ============================================================
function viewLgaDetails(id) {
    var modal = document.getElementById('detailsModal');
    var title = document.getElementById('detailsTitleText');
    var content = document.getElementById('detailsContent');
    
    title.textContent = 'Loading...';
    content.innerHTML = `
        <div style="text-align:center;padding:40px 0;">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--primary);"></i>
            <p style="margin-top:12px;color:var(--gray-500);">Loading LGA details...</p>
        </div>
    `;
    openModal('detailsModal');
    
    fetch('api/get_lga_details.php?id=' + id)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data && data.success) {
                var d = data.data;
                var lga = d.lga;
                var stats = d.stats;
                var recentWards = d.recent_wards || [];
                
                title.textContent = lga.name + ' - LGA Details';
                
                var statusBadge = lga.is_active ? 
                    '<span class="badge-status active"><span class="dot"></span> Active</span>' : 
                    '<span class="badge-status inactive"><span class="dot"></span> Inactive</span>';
                
                var html = `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Code</div>
                            <div style="font-weight:600;font-size:1rem;font-family:monospace;">${lga.code || 'N/A'}</div>
                        </div>
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Status</div>
                            <div>${statusBadge}</div>
                        </div>
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">State</div>
                            <div style="font-weight:600;">${lga.state_name || 'N/A'} (${lga.state_code || ''})</div>
                        </div>
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Registered Voters</div>
                            <div style="font-weight:600;font-size:1rem;">${Number(lga.registered_voters || 0).toLocaleString()}</div>
                        </div>
                    </div>
                `;
                
                // Statistics Cards
                html += `
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
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
                
                // GPS Coordinates
                if (lga.gps_lat && lga.gps_lng) {
                    html += `
                        <div style="background:var(--gray-50);padding:10px 14px;border-radius:8px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
                            <i class="fas fa-map-pin" style="color:var(--primary);font-size:1.1rem;"></i>
                            <div>
                                <div style="font-size:0.7rem;color:var(--gray-400);">GPS Coordinates</div>
                                <div style="font-weight:500;font-size:0.85rem;">
                                    ${lga.gps_lat}, ${lga.gps_lng}
                                </div>
                            </div>
                            <div style="margin-left:auto;">
                                <a href="https://www.google.com/maps?q=${lga.gps_lat},${lga.gps_lng}" target="_blank" 
                                   style="font-size:0.75rem;color:var(--primary);text-decoration:none;">
                                    <i class="fas fa-external-link-alt"></i> View Map
                                </a>
                            </div>
                        </div>
                    `;
                }
                
                // Recent Wards
                if (recentWards.length > 0) {
                    html += `
                        <div style="margin-top:8px;">
                            <div style="font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:8px;">
                                <i class="fas fa-layer-group" style="color:var(--primary);"></i> Recent Wards
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
                    recentWards.forEach(function(ward) {
                        var status = ward.is_active ? 
                            '<span style="color:#10B981;font-size:0.6rem;"><i class="fas fa-circle"></i> Active</span>' : 
                            '<span style="color:#EF4444;font-size:0.6rem;"><i class="fas fa-circle"></i> Inactive</span>';
                        html += `
                            <tr style="border-bottom:1px solid var(--gray-200);">
                                <td style="padding:6px 12px;font-weight:500;">${ward.name}</td>
                                <td style="padding:6px 12px;font-family:monospace;font-size:0.7rem;color:var(--gray-500);">${ward.code}</td>
                                <td style="padding:6px 12px;text-align:right;">${Number(ward.registered_voters || 0).toLocaleString()}</td>
                                <td style="padding:6px 12px;text-align:center;">${status}</td>
                            </tr>
                        `;
                    });
                    html += `
                                    </tbody>
                                </table>
                            </div>
                            ${stats.ward_count > 5 ? `<div style="text-align:right;margin-top:6px;font-size:0.7rem;color:var(--gray-400);">+ ${stats.ward_count - 5} more wards</div>` : ''}
                        </div>
                    `;
                }
                
                content.innerHTML = html;
            } else {
                content.innerHTML = `
                    <div style="text-align:center;padding:40px 0;color:var(--danger);">
                        <i class="fas fa-exclamation-circle" style="font-size:2rem;"></i>
                        <p style="margin-top:12px;">${data.message || 'Failed to load LGA details'}</p>
                    </div>
                `;
            }
        })
        .catch(function(error) {
            content.innerHTML = `
                <div style="text-align:center;padding:40px 0;color:var(--danger);">
                    <i class="fas fa-exclamation-triangle" style="font-size:2rem;"></i>
                    <p style="margin-top:12px;">Error loading LGA details. Please try again.</p>
                </div>
            `;
        });
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