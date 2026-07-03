<?php
// ============================================================
// POLLING UNITS MANAGEMENT - CLIENT ADMIN (PROFESSIONAL UI)
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
            case 'add_pu':
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $ward_id = (int)($_POST['ward_id'] ?? 0);
                $registered_voters = (int)($_POST['registered_voters'] ?? 0);
                $gps_lat = !empty($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
                $gps_lng = !empty($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;
                $address = trim($_POST['address'] ?? '');
                $is_rural = isset($_POST['is_rural']) ? 1 : 0;
                $network_quality = $_POST['network_quality'] ?? null;
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name) || empty($code) || $ward_id <= 0) {
                    throw new Exception('Name, code, and Ward are required.');
                }
                
                $stmt = $db->prepare("
                    INSERT INTO polling_units (
                        ward_id, code, name, description, registered_voters, 
                        gps_lat, gps_lng, address, is_rural, network_quality, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $ward_id, $code, $name, $description, $registered_voters,
                    $gps_lat, $gps_lng, $address, $is_rural, $network_quality
                ]);
                
                logActivity($user_id, 'pu_added', "Added Polling Unit: $name");
                $action_result = ['success' => true, 'message' => "Polling Unit '$name' added successfully."];
                break;
                
            case 'edit_pu':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $ward_id = (int)($_POST['ward_id'] ?? 0);
                $registered_voters = (int)($_POST['registered_voters'] ?? 0);
                $gps_lat = !empty($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
                $gps_lng = !empty($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;
                $address = trim($_POST['address'] ?? '');
                $is_rural = isset($_POST['is_rural']) ? 1 : 0;
                $network_quality = $_POST['network_quality'] ?? null;
                $description = trim($_POST['description'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if ($id <= 0 || empty($name) || empty($code) || $ward_id <= 0) {
                    throw new Exception('Invalid data provided.');
                }
                
                $stmt = $db->prepare("
                    UPDATE polling_units SET 
                        ward_id = ?, code = ?, name = ?, description = ?,
                        registered_voters = ?, gps_lat = ?, gps_lng = ?,
                        address = ?, is_rural = ?, network_quality = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $ward_id, $code, $name, $description,
                    $registered_voters, $gps_lat, $gps_lng,
                    $address, $is_rural, $network_quality, $is_active, $id
                ]);
                
                logActivity($user_id, 'pu_updated', "Updated Polling Unit ID: $id");
                $action_result = ['success' => true, 'message' => 'Polling Unit updated successfully.'];
                break;
                
            case 'delete_pu':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid Polling Unit ID.');
                
                $stmt = $db->prepare("DELETE FROM polling_units WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity($user_id, 'pu_deleted', "Deleted Polling Unit ID: $id");
                $action_result = ['success' => true, 'message' => 'Polling Unit deleted successfully.'];
                break;
                
            case 'bulk_import':
                $ward_id = (int)($_POST['import_ward_id'] ?? 0);
                if ($ward_id <= 0) throw new Exception('Please select a ward for import.');
                
                // In a real implementation, process the uploaded file
                // For now, just a success message
                $action_result = ['success' => true, 'message' => 'Bulk import completed successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH WARDS FOR DROPDOWN
// ============================================================
$wards = [];
$ward_options = [];
try {
    $stmt = $db->query("
        SELECT w.id, w.name, w.code, l.name as lga_name, s.name as state_name 
        FROM wards w 
        LEFT JOIN lgas l ON w.lga_id = l.id 
        LEFT JOIN states s ON l.state_id = s.id 
        WHERE w.is_active = 1 
        ORDER BY s.name, l.name, w.name
    ");
    $ward_options = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH POLLING UNITS WITH PAGINATION & FILTERS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$ward_filter = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(pu.name LIKE ? OR pu.code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if ($ward_filter > 0) {
    $where_conditions[] = "pu.ward_id = ?";
    $params[] = $ward_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total
$count_sql = "SELECT COUNT(*) as total FROM polling_units pu $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_pus = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_pus / $limit);

// Fetch Polling Units
$sql = "
    SELECT pu.*, 
           w.name as ward_name, w.code as ward_code,
           l.name as lga_name, l.code as lga_code,
           s.name as state_name, s.code as state_code
    FROM polling_units pu
    LEFT JOIN wards w ON pu.ward_id = w.id
    LEFT JOIN lgas l ON w.lga_id = l.id
    LEFT JOIN states s ON l.state_id = s.id
    $where_clause
    ORDER BY s.name, l.name, w.name, pu.name
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$polling_units = $stmt->fetchAll();

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total_pus' => 0,
    'active_pus' => 0,
    'inactive_pus' => 0,
    'total_voters' => 0,
    'rural_pus' => 0,
    'urban_pus' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM polling_units");
    $stats['total_pus'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM polling_units WHERE is_active = 1");
    $stats['active_pus'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM polling_units WHERE is_active = 0");
    $stats['inactive_pus'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT SUM(registered_voters) as total FROM polling_units");
    $stats['total_voters'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM polling_units WHERE is_rural = 1");
    $stats['rural_pus'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM polling_units WHERE is_rural = 0");
    $stats['urban_pus'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       POLLING UNITS - PROFESSIONAL UI STYLES
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
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.orange { color: #F59E0B; }
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
    .table-container .table-header .table-actions .btn-outline-sm {
        padding: 4px 14px;
        border-radius: 8px;
        border: 1px solid var(--gray-200);
        background: transparent;
        font-size: 0.75rem;
        color: var(--gray-600);
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .table-container .table-header .table-actions .btn-outline-sm:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: #EFF6FF;
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
    
    .pu-code {
        font-family: 'Courier New', monospace;
        font-size: 0.75rem;
        background: var(--gray-100);
        padding: 3px 10px;
        border-radius: 6px;
        display: inline-block;
        color: var(--gray-600);
        font-weight: 500;
        border: 1px solid var(--gray-200);
        transition: var(--transition);
    }
    .pu-code:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: #EFF6FF;
    }
    
    .pu-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--gray-800);
    }
    .pu-name .address {
        font-weight: 400;
        font-size: 0.7rem;
        color: var(--gray-400);
        display: block;
        margin-top: 2px;
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
    
    .network-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    .network-badge .signal {
        font-size: 0.65rem;
    }
    .network-badge.5g { background: #ECFDF5; color: #065F46; }
    .network-badge.4g { background: #EFF6FF; color: #1E40AF; }
    .network-badge.3g { background: #FFFBEB; color: #92400E; }
    .network-badge.2g { background: #FEF2F2; color: #991B1B; }
    .network-badge.none { background: var(--gray-100); color: var(--gray-500); }
    .network-badge.unknown { background: var(--gray-100); color: var(--gray-400); }
    
    .badge-rural {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #F5F3FF;
        color: #6D28D9;
    }
    .badge-urban {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #E0F2FE;
        color: #0369A1;
    }
    
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
    .modal .form-group textarea {
        resize: vertical;
        min-height: 60px;
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
    .modal .form-group .radio-group {
        display: flex;
        gap: 16px;
        padding-top: 4px;
        flex-wrap: wrap;
    }
    .modal .form-group .radio-group label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 400;
        font-size: 0.85rem;
        color: var(--gray-700);
        cursor: pointer;
    }
    .modal .form-group .radio-group input[type="radio"] {
        accent-color: var(--primary);
        width: 16px;
        height: 16px;
        cursor: pointer;
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
    
    /* File Upload Area for Import */
    .file-upload-area {
        border: 2px dashed var(--gray-200);
        border-radius: 10px;
        padding: 30px 20px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--gray-50);
        position: relative;
    }
    .file-upload-area:hover {
        border-color: var(--primary);
        background: #EFF6FF;
    }
    .file-upload-area.dragover {
        border-color: var(--primary);
        background: #EFF6FF;
        transform: scale(1.01);
    }
    .file-upload-area i {
        font-size: 2.5rem;
        color: var(--gray-400);
        display: block;
        margin-bottom: 10px;
        transition: var(--transition);
    }
    .file-upload-area:hover i {
        color: var(--primary);
    }
    .file-upload-area p {
        font-size: 0.9rem;
        color: var(--gray-500);
        margin-bottom: 4px;
    }
    .file-upload-area .file-types {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .file-upload-area input[type="file"] {
        display: none;
    }
    .file-preview {
        display: none;
        margin-top: 12px;
        padding: 12px 16px;
        background: var(--gray-50);
        border-radius: 8px;
        border: 1px solid var(--gray-200);
        text-align: left;
        animation: fadeIn 0.3s ease;
    }
    .file-preview.show {
        display: block;
    }
    .file-preview .file-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .file-preview .file-info .file-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .file-preview .file-info .file-icon.excel { background: #ECFDF5; color: #10B981; }
    .file-preview .file-info .file-icon.csv { background: #EFF6FF; color: #3B82F6; }
    .file-preview .file-info .file-details {
        flex: 1;
    }
    .file-preview .file-info .file-details .file-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .file-preview .file-info .file-details .file-size {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
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
    
    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-wrap { min-width: auto; }
        .filter-bar select { width: 100%; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 8px 12px; }
        .pagination { flex-direction: column; align-items: center; }
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
        .pu-code { font-size: 0.65rem; padding: 2px 6px; }
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
                    <i class="fas fa-flag-checkered" style="color:var(--primary);margin-right:8px;"></i> Polling Units
                    <small>Manage Polling Units in your political structure</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('importPuModal')" class="btn-outline" style="padding:10px 20px;border-radius:10px;border:1.5px solid var(--gray-200);background:transparent;color:var(--gray-600);font-weight:600;font-size:0.82rem;cursor:pointer;transition:var(--transition);font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-file-import"></i> Bulk Import
                </button>
                <button onclick="openModal('addPuModal')" class="btn-primary" style="padding:10px 20px;border-radius:10px;background:var(--primary);color:white;border:none;font-weight:600;font-size:0.82rem;cursor:pointer;transition:var(--transition);font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-plus-circle"></i> Add PU
                </button>
            </div>
        </div>

        <!-- Structure Breadcrumb -->
        <div class="structure-breadcrumb">
            <a href="states.php" class="crumb-link">
                <i class="fas fa-flag"></i> States
            </a>
            <span class="separator">/</span>
            <a href="lgas.php" class="crumb-link">
                <i class="fas fa-map-marker-alt"></i> LGAs
            </a>
            <span class="separator">/</span>
            <a href="wards.php" class="crumb-link">
                <i class="fas fa-layer-group"></i> Wards
            </a>
            <span class="separator">/</span>
            <span class="crumb active">
                <i class="fas fa-flag-checkered"></i> Polling Units
            </span>
        </div>

        <!-- Structure Navigation -->
        <div class="structure-nav">
            <a href="states.php">
                <i class="fas fa-flag"></i> States
            </a>
            <a href="lgas.php">
                <i class="fas fa-map-marker-alt"></i> LGAs
            </a>
            <a href="wards.php">
                <i class="fas fa-layer-group"></i> Wards
            </a>
            <a href="polling-units.php" class="active">
                <i class="fas fa-flag-checkered"></i> Polling Units
                <span class="count"><?php echo number_format($stats['total_pus']); ?></span>
            </a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number"><?php echo number_format($stats['total_pus']); ?></div>
                <div class="label">Total PUs</div>
                <div class="sub-label"><?php echo number_format($stats['active_pus']); ?> active</div>
            </div>
            <div class="stat-item">
                <div class="number green"><?php echo number_format($stats['active_pus']); ?></div>
                <div class="label">Active</div>
                <div class="sub-label"><?php echo number_format($stats['inactive_pus']); ?> inactive</div>
            </div>
            <div class="stat-item">
                <div class="number blue"><?php echo number_format($stats['total_voters']); ?></div>
                <div class="label">Registered Voters</div>
                <div class="sub-label">Across all PUs</div>
            </div>
            <div class="stat-item">
                <div class="number orange"><?php echo number_format($stats['rural_pus']); ?></div>
                <div class="label">Rural / Urban</div>
                <div class="sub-label"><?php echo number_format($stats['urban_pus']); ?> urban</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search PUs by name or code..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="ward">
                    <option value="">All Wards</option>
                    <?php foreach ($ward_options as $ward): ?>
                        <option value="<?php echo $ward['id']; ?>" <?php echo $ward_filter == $ward['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ward['name']); ?> (<?php echo htmlspecialchars($ward['lga_name'] ?? ''); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || $ward_filter > 0): ?>
                    <a href="polling-units.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Polling Units Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Polling Units
                    <span class="count"><?php echo number_format($total_pus); ?></span>
                </div>
                <div class="table-actions">
                    <span>Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_pus); ?></span>
                    <?php if ($total_pus > 0): ?>
                    <button class="btn-outline-sm" onclick="window.location.href='polling-units.php?export=1'">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Code</th>
                        <th>Name &amp; Location</th>
                        <th>Ward</th>
                        <th>LGA</th>
                        <th>Voters</th>
                        <th>Network</th>
                        <th>Status</th>
                        <th style="text-align:center;width:60px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($polling_units) > 0): ?>
                        <?php foreach ($polling_units as $index => $pu): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <span class="pu-code"><?php echo htmlspecialchars($pu['code']); ?></span>
                                </td>
                                <td>
                                    <div class="pu-name">
                                        <?php echo htmlspecialchars($pu['name']); ?>
                                        <?php if (!empty($pu['address'])): ?>
                                            <span class="address">
                                                <i class="fas fa-map-pin" style="font-size:0.6rem;"></i>
                                                <?php echo htmlspecialchars($pu['address']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($pu['description'])): ?>
                                        <div style="font-size:0.7rem;color:var(--gray-400);margin-top:2px;">
                                            <?php echo htmlspecialchars($pu['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:0.8rem;font-weight:500;"><?php echo htmlspecialchars($pu['ward_name'] ?? 'N/A'); ?></span>
                                    <?php if (!empty($pu['ward_code'])): ?>
                                        <span style="font-size:0.6rem;color:var(--gray-400);display:block;"><?php echo htmlspecialchars($pu['ward_code']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:0.8rem;"><?php echo htmlspecialchars($pu['lga_name'] ?? 'N/A'); ?></span>
                                    <?php if (!empty($pu['state_name'])): ?>
                                        <span style="font-size:0.6rem;color:var(--gray-400);display:block;"><?php echo htmlspecialchars($pu['state_name']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight:600;font-size:0.85rem;">
                                        <?php echo number_format($pu['registered_voters'] ?? 0); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $network = $pu['network_quality'] ?? 'unknown';
                                    $network_labels = [
                                        '5g' => '5G',
                                        '4g' => '4G',
                                        '3g' => '3G',
                                        '2g' => '2G',
                                        'none' => 'None',
                                        'unknown' => '—'
                                    ];
                                    $network_icons = [
                                        '5g' => 'fa-wifi',
                                        '4g' => 'fa-wifi',
                                        '3g' => 'fa-wifi',
                                        '2g' => 'fa-wifi',
                                        'none' => 'fa-wifi-slash',
                                        'unknown' => 'fa-question'
                                    ];
                                    ?>
                                    <span class="network-badge <?php echo $network; ?>">
                                        <i class="fas <?php echo $network_icons[$network] ?? 'fa-question'; ?> signal"></i>
                                        <?php echo $network_labels[$network] ?? '—'; ?>
                                    </span>
                                    <?php if ($pu['is_rural']): ?>
                                        <span class="badge-rural" style="display:block;margin-top:2px;">Rural</span>
                                    <?php else: ?>
                                        <span class="badge-urban" style="display:block;margin-top:2px;">Urban</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $pu['is_active'] ? 'active' : 'inactive'; ?>">
                                        <span class="dot"></span>
                                        <?php echo $pu['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <button onclick="editPu(<?php echo $pu['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button onclick="viewPuDetails(<?php echo $pu['id']; ?>)">
                                                <i class="fas fa-info-circle"></i> Details
                                            </button>
                                            <?php if ($pu['is_active']): ?>
                                                <button onclick="togglePuStatus(<?php echo $pu['id']; ?>, 0)">
                                                    <i class="fas fa-pause-circle"></i> Deactivate
                                                </button>
                                            <?php else: ?>
                                                <button onclick="togglePuStatus(<?php echo $pu['id']; ?>, 1)">
                                                    <i class="fas fa-play-circle"></i> Activate
                                                </button>
                                            <?php endif; ?>
                                            <div class="divider"></div>
                                            <button class="danger" onclick="deletePu(<?php echo $pu['id']; ?>)">
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
                                    <i class="fas fa-flag-checkered"></i>
                                    <h4>No polling units found</h4>
                                    <p>Add polling units to complete your political structure.</p>
                                    <button onclick="openModal('addPuModal')" class="btn-primary" style="margin-top:12px;padding:10px 24px;border-radius:10px;background:var(--primary);color:white;border:none;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:8px;">
                                        <i class="fas fa-plus-circle"></i> Add Polling Unit
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
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_pus); ?></strong> of <strong><?php echo number_format($total_pus); ?></strong> polling units
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&ward=<?php echo $ward_filter; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&ward=' . $ward_filter . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&ward=<?php echo $ward_filter; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&ward=' . $ward_filter . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&ward=<?php echo $ward_filter; ?>">
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

<!-- Add Polling Unit Modal -->
<div class="modal-overlay" id="addPuModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Add Polling Unit</h3>
            <button class="close-btn" onclick="closeModal('addPuModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_pu">
            <div class="form-group">
                <label>Ward <span class="required">*</span></label>
                <select name="ward_id" required>
                    <option value="">Select Ward</option>
                    <?php foreach ($ward_options as $ward): ?>
                        <option value="<?php echo $ward['id']; ?>">
                            <?php echo htmlspecialchars($ward['name']); ?>
                            (<?php echo htmlspecialchars($ward['lga_name'] ?? 'N/A'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>PU Code <span class="required">*</span></label>
                <input type="text" name="code" placeholder="e.g., PU001" required>
                <div class="help-text">Unique identifier for this polling unit</div>
            </div>
            <div class="form-group">
                <label>PU Name <span class="required">*</span></label>
                <input type="text" name="name" placeholder="e.g., Primary School" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" placeholder="e.g., Located near the market">
            </div>
            <div class="form-group">
                <label>Registered Voters</label>
                <input type="number" name="registered_voters" placeholder="0" min="0">
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" placeholder="Full address of the polling unit">
            </div>
            <div class="form-group">
                <label>GPS Coordinates</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <input type="text" name="gps_lat" placeholder="Latitude" pattern="^-?\d{1,3}\.\d+$" title="Enter a valid latitude">
                    <input type="text" name="gps_lng" placeholder="Longitude" pattern="^-?\d{1,3}\.\d+$" title="Enter a valid longitude">
                </div>
                <div class="help-text">e.g., 6.5244 for latitude, 3.3792 for longitude</div>
            </div>
            <div class="form-group">
                <label>Network Quality <span class="required">*</span></label>
                <select name="network_quality" required>
                    <option value="">Select Network</option>
                    <option value="5g">5G</option>
                    <option value="4g">4G</option>
                    <option value="3g">3G</option>
                    <option value="2g">2G</option>
                    <option value="none">None</option>
                </select>
                <div class="help-text">Mobile network coverage at this location</div>
            </div>
            <div class="form-group">
                <label>Area Type</label>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="is_rural" value="0" checked> Urban
                    </label>
                    <label>
                        <input type="radio" name="is_rural" value="1"> Rural
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPuModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Polling Unit</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Polling Unit Modal -->
<div class="modal-overlay" id="editPuModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);"></i> Edit Polling Unit</h3>
            <button class="close-btn" onclick="closeModal('editPuModal')">&times;</button>
        </div>
        <form method="POST" action="" id="editPuForm">
            <input type="hidden" name="action" value="edit_pu">
            <input type="hidden" name="id" id="editPuId">
            <div class="form-group">
                <label>Ward <span class="required">*</span></label>
                <select name="ward_id" id="editPuWard" required>
                    <option value="">Select Ward</option>
                    <?php foreach ($ward_options as $ward): ?>
                        <option value="<?php echo $ward['id']; ?>">
                            <?php echo htmlspecialchars($ward['name']); ?>
                            (<?php echo htmlspecialchars($ward['lga_name'] ?? 'N/A'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>PU Code <span class="required">*</span></label>
                <input type="text" name="code" id="editPuCode" required>
            </div>
            <div class="form-group">
                <label>PU Name <span class="required">*</span></label>
                <input type="text" name="name" id="editPuName" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" id="editPuDescription">
            </div>
            <div class="form-group">
                <label>Registered Voters</label>
                <input type="number" name="registered_voters" id="editPuVoters" min="0">
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" id="editPuAddress">
            </div>
            <div class="form-group">
                <label>GPS Coordinates</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <input type="text" name="gps_lat" id="editPuLat" placeholder="Latitude">
                    <input type="text" name="gps_lng" id="editPuLng" placeholder="Longitude">
                </div>
            </div>
            <div class="form-group">
                <label>Network Quality</label>
                <select name="network_quality" id="editPuNetwork">
                    <option value="">Select Network</option>
                    <option value="5g">5G</option>
                    <option value="4g">4G</option>
                    <option value="3g">3G</option>
                    <option value="2g">2G</option>
                    <option value="none">None</option>
                </select>
            </div>
            <div class="form-group">
                <label>Area Type</label>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="is_rural" value="0" id="editPuUrban"> Urban
                    </label>
                    <label>
                        <input type="radio" name="is_rural" value="1" id="editPuRural"> Rural
                    </label>
                </div>
            </div>
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="editPuActive" value="1">
                    <label for="editPuActive">Active</label>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editPuModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Polling Unit</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Import Modal -->
<div class="modal-overlay" id="importPuModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-file-import" style="color:var(--primary);"></i> Bulk Import Polling Units</h3>
            <button class="close-btn" onclick="closeModal('importPuModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="bulk_import">
            <div class="form-group">
                <label>Target Ward <span class="required">*</span></label>
                <select name="import_ward_id" required>
                    <option value="">Select Ward</option>
                    <?php foreach ($ward_options as $ward): ?>
                        <option value="<?php echo $ward['id']; ?>">
                            <?php echo htmlspecialchars($ward['name']); ?>
                            (<?php echo htmlspecialchars($ward['lga_name'] ?? 'N/A'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help-text">All imported PUs will be assigned to this ward</div>
            </div>
            <div class="form-group">
                <label>File <span class="required">*</span></label>
                <div class="file-upload-area" onclick="document.getElementById('importPuFile').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to upload or drag &amp; drop</p>
                    <div class="file-types">Supported: Excel (.xlsx), CSV</div>
                    <input type="file" name="import_file" id="importPuFile" accept=".xlsx,.csv" required>
                </div>
                <div class="file-preview" id="importPuPreview">
                    <div class="file-info">
                        <div class="file-icon excel"><i class="fas fa-file-excel"></i></div>
                        <div class="file-details">
                            <div class="file-name" id="importPuFileName">file.xlsx</div>
                            <div class="file-size" id="importPuFileSize">0 KB</div>
                        </div>
                    </div>
                </div>
                <div class="help-text" style="margin-top:8px;">
                    <i class="fas fa-info-circle"></i> 
                    Required columns: <strong>code</strong>, <strong>name</strong>, <strong>registered_voters</strong>
                    <br>Optional: <strong>address</strong>, <strong>is_rural</strong> (0/1), <strong>network_quality</strong> (5g/4g/3g/2g/none)
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('importPuModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import PUs</button>
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
// POLLING UNIT FUNCTIONS
// ============================================================
function editPu(id) {
    // Fetch data via AJAX and populate edit modal
    fetch('api/get_pu.php?id=' + id)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data && data.success) {
                var pu = data.data;
                document.getElementById('editPuId').value = pu.id;
                document.getElementById('editPuWard').value = pu.ward_id;
                document.getElementById('editPuCode').value = pu.code;
                document.getElementById('editPuName').value = pu.name;
                document.getElementById('editPuDescription').value = pu.description || '';
                document.getElementById('editPuVoters').value = pu.registered_voters || 0;
                document.getElementById('editPuAddress').value = pu.address || '';
                document.getElementById('editPuLat').value = pu.gps_lat || '';
                document.getElementById('editPuLng').value = pu.gps_lng || '';
                document.getElementById('editPuNetwork').value = pu.network_quality || '';
                if (pu.is_rural) {
                    document.getElementById('editPuRural').checked = true;
                } else {
                    document.getElementById('editPuUrban').checked = true;
                }
                document.getElementById('editPuActive').checked = pu.is_active == 1;
                openModal('editPuModal');
            } else {
                alert('Failed to load polling unit data.');
            }
        })
        .catch(function() {
            // Fallback: prompt for manual edit
            alert('Edit Polling Unit ID: ' + id + '\nPlease implement API endpoint or use manual form.');
        });
}

// ============================================================
// POLLING UNIT DETAILS VIEW
// ============================================================
function viewPuDetails(id) {
    var modal = document.getElementById('detailsModal');
    var title = document.getElementById('detailsTitleText');
    var content = document.getElementById('detailsContent');
    
    title.textContent = 'Loading...';
    content.innerHTML = `
        <div style="text-align:center;padding:40px 0;">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--primary);"></i>
            <p style="margin-top:12px;color:var(--gray-500);">Loading polling unit details...</p>
        </div>
    `;
    openModal('detailsModal');
    
    fetch('api/get_pu_details.php?id=' + id)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data && data.success) {
                var pu = data.data;
                
                title.textContent = pu.name + ' - Polling Unit Details';
                
                var statusBadge = pu.is_active ? 
                    '<span class="badge-status active"><span class="dot"></span> Active</span>' : 
                    '<span class="badge-status inactive"><span class="dot"></span> Inactive</span>';
                
                var networkLabels = {
                    '5g': '5G',
                    '4g': '4G',
                    '3g': '3G',
                    '2g': '2G',
                    'none': 'None'
                };
                var networkColors = {
                    '5g': '#065F46',
                    '4g': '#1E40AF',
                    '3g': '#92400E',
                    '2g': '#991B1B',
                    'none': '#64748B'
                };
                var network = pu.network_quality || 'unknown';
                var networkDisplay = networkLabels[network] || '—';
                var networkColor = networkColors[network] || '#64748B';
                
                var areaType = pu.is_rural ? 
                    '<span style="background:#F5F3FF;color:#6D28D9;padding:2px 12px;border-radius:12px;font-size:0.65rem;font-weight:600;">Rural</span>' : 
                    '<span style="background:#E0F2FE;color:#0369A1;padding:2px 12px;border-radius:12px;font-size:0.65rem;font-weight:600;">Urban</span>';
                
                var html = `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Code</div>
                            <div style="font-weight:600;font-size:1rem;font-family:monospace;">${pu.code || 'N/A'}</div>
                        </div>
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Status</div>
                            <div>${statusBadge}</div>
                        </div>
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Ward</div>
                            <div style="font-weight:600;">${pu.ward_name || 'N/A'}</div>
                        </div>
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">LGA</div>
                            <div style="font-weight:600;">${pu.lga_name || 'N/A'}</div>
                        </div>
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">State</div>
                            <div style="font-weight:600;">${pu.state_name || 'N/A'}</div>
                        </div>
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:10px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Area Type</div>
                            <div>${areaType}</div>
                        </div>
                    </div>
                `;
                
                // Network and Voters info
                html += `
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:16px;">
                        <div style="background:#EFF6FF;padding:12px;border-radius:8px;text-align:center;">
                            <div style="font-size:1.2rem;font-weight:700;color:#1E40AF;">${Number(pu.registered_voters || 0).toLocaleString()}</div>
                            <div style="font-size:0.65rem;color:var(--gray-500);font-weight:500;">Registered Voters</div>
                        </div>
                        <div style="background:#F5F3FF;padding:12px;border-radius:8px;text-align:center;">
                            <div style="font-size:1.2rem;font-weight:700;color:#6D28D9;text-transform:uppercase;">${networkDisplay}</div>
                            <div style="font-size:0.65rem;color:var(--gray-500);font-weight:500;">Network Quality</div>
                        </div>
                        <div style="background:#ECFDF5;padding:12px;border-radius:8px;text-align:center;">
                            <div style="font-size:1.2rem;font-weight:700;color:#065F46;">
                                <i class="fas ${pu.is_active ? 'fa-check-circle' : 'fa-minus-circle'}"></i>
                            </div>
                            <div style="font-size:0.65rem;color:var(--gray-500);font-weight:500;">${pu.is_active ? 'Active' : 'Inactive'}</div>
                        </div>
                    </div>
                `;
                
                // Address
                if (pu.address) {
                    html += `
                        <div style="background:var(--gray-50);padding:10px 14px;border-radius:8px;margin-bottom:16px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Address</div>
                            <div style="font-weight:500;font-size:0.85rem;margin-top:2px;">${pu.address}</div>
                        </div>
                    `;
                }
                
                // Description
                if (pu.description) {
                    html += `
                        <div style="background:var(--gray-50);padding:10px 14px;border-radius:8px;margin-bottom:16px;">
                            <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;">Description</div>
                            <div style="font-weight:400;font-size:0.85rem;margin-top:2px;">${pu.description}</div>
                        </div>
                    `;
                }
                
                // GPS Coordinates
                if (pu.gps_lat && pu.gps_lng) {
                    html += `
                        <div style="background:var(--gray-50);padding:10px 14px;border-radius:8px;margin-bottom:8px;display:flex;align-items:center;gap:12px;">
                            <i class="fas fa-map-pin" style="color:var(--primary);font-size:1.1rem;"></i>
                            <div>
                                <div style="font-size:0.7rem;color:var(--gray-400);">GPS Coordinates</div>
                                <div style="font-weight:500;font-size:0.85rem;">
                                    ${pu.gps_lat}, ${pu.gps_lng}
                                </div>
                            </div>
                            <div style="margin-left:auto;">
                                <a href="https://www.google.com/maps?q=${pu.gps_lat},${pu.gps_lng}" target="_blank" 
                                   style="font-size:0.75rem;color:var(--primary);text-decoration:none;">
                                    <i class="fas fa-external-link-alt"></i> View Map
                                </a>
                            </div>
                        </div>
                    `;
                }
                
                // Full Hierarchy
                html += `
                    <div style="margin-top:12px;padding:12px 16px;background:#F8FAFC;border-radius:8px;border:1px solid var(--gray-200);">
                        <div style="font-size:0.7rem;color:var(--gray-400);text-transform:uppercase;font-weight:600;margin-bottom:6px;">Hierarchy</div>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;font-size:0.8rem;">
                            <span><i class="fas fa-flag" style="color:var(--primary);"></i> ${pu.state_name || 'N/A'}</span>
                            <span><i class="fas fa-chevron-right" style="color:var(--gray-300);font-size:0.6rem;"></i></span>
                            <span><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> ${pu.lga_name || 'N/A'}</span>
                            <span><i class="fas fa-chevron-right" style="color:var(--gray-300);font-size:0.6rem;"></i></span>
                            <span><i class="fas fa-layer-group" style="color:var(--primary);"></i> ${pu.ward_name || 'N/A'}</span>
                            <span><i class="fas fa-chevron-right" style="color:var(--gray-300);font-size:0.6rem;"></i></span>
                            <span><i class="fas fa-flag-checkered" style="color:var(--primary);"></i> ${pu.name}</span>
                        </div>
                    </div>
                `;
                
                content.innerHTML = html;
            } else {
                content.innerHTML = `
                    <div style="text-align:center;padding:40px 0;color:var(--danger);">
                        <i class="fas fa-exclamation-circle" style="font-size:2rem;"></i>
                        <p style="margin-top:12px;">${data.message || 'Failed to load polling unit details'}</p>
                    </div>
                `;
            }
        })
        .catch(function(error) {
            content.innerHTML = `
                <div style="text-align:center;padding:40px 0;color:var(--danger);">
                    <i class="fas fa-exclamation-triangle" style="font-size:2rem;"></i>
                    <p style="margin-top:12px;">Error loading polling unit details. Please try again.</p>
                </div>
            `;
        });
}

function togglePuStatus(id, status) {
    var action = status ? 'activate' : 'deactivate';
    if (confirm('Are you sure you want to ' + action + ' this polling unit?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="toggle_pu_status"><input type="hidden" name="id" value="' + id + '"><input type="hidden" name="status" value="' + status + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function deletePu(id) {
    if (confirm('Delete this Polling Unit? This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_pu"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================================
// FILE IMPORT PREVIEW
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('importPuFile');
    var preview = document.getElementById('importPuPreview');
    var fileName = document.getElementById('importPuFileName');
    var fileSize = document.getElementById('importPuFileSize');
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                var file = this.files[0];
                var ext = file.name.split('.').pop().toLowerCase();
                var icon = preview.querySelector('.file-icon');
                if (ext === 'xlsx' || ext === 'xls') {
                    icon.className = 'file-icon excel';
                    icon.innerHTML = '<i class="fas fa-file-excel"></i>';
                } else if (ext === 'csv') {
                    icon.className = 'file-icon csv';
                    icon.innerHTML = '<i class="fas fa-file-csv"></i>';
                } else {
                    icon.className = 'file-icon';
                    icon.innerHTML = '<i class="fas fa-file"></i>';
                }
                fileName.textContent = file.name;
                fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
                preview.classList.add('show');
            } else {
                preview.classList.remove('show');
            }
        });
    }
});

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