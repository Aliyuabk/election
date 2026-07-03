<?php
// ============================================================
// ELECTION POLLING UNITS - CLIENT ADMIN (PROFESSIONAL UI)
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
// GET ELECTION ID
// ============================================================
$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($election_id <= 0) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH ELECTION DETAILS
// ============================================================
$election = null;
try {
    $stmt = $db->prepare("SELECT id, name, type, tenant_id, pus_json FROM elections WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$election_id, $tenant_id]);
    $election = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$election) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// DECODE JSON DATA
// ============================================================
$selected_pus = json_decode($election['pus_json'] ?? '[]', true);

// ============================================================
// FETCH POLLING UNITS
// ============================================================
$polling_units = [];
try {
    $stmt = $db->query("
        SELECT pu.*, w.name as ward_name, l.name as lga_name, s.name as state_name
        FROM polling_units pu
        LEFT JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN states s ON l.state_id = s.id
        WHERE pu.is_active = 1
        ORDER BY s.name, l.name, w.name, pu.name
    ");
    $polling_units = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH ASSIGNED PUS
// ============================================================
$assigned_pus = [];
if (!empty($selected_pus)) {
    $placeholders = implode(',', array_fill(0, count($selected_pus), '?'));
    try {
        $stmt = $db->prepare("
            SELECT pu.*, w.name as ward_name, l.name as lga_name, s.name as state_name
            FROM polling_units pu
            LEFT JOIN wards w ON pu.ward_id = w.id
            LEFT JOIN lgas l ON w.lga_id = l.id
            LEFT JOIN states s ON l.state_id = s.id
            WHERE pu.id IN ($placeholders)
            ORDER BY s.name, l.name, w.name, pu.name
        ");
        $stmt->execute($selected_pus);
        $assigned_pus = $stmt->fetchAll();
    } catch (Exception $e) {}
}

// ============================================================
// HANDLE ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'assign_pus':
                $pu_ids = isset($_POST['pu_ids']) ? $_POST['pu_ids'] : [];
                if (empty($pu_ids)) {
                    throw new Exception('Please select at least one polling unit.');
                }
                $all_pus = array_merge($selected_pus, $pu_ids);
                $all_pus = array_unique($all_pus);
                $pus_json = json_encode($all_pus);
                
                $stmt = $db->prepare("UPDATE elections SET pus_json = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$pus_json, $election_id, $tenant_id]);
                
                logActivity($user_id, 'election_pus_assigned', "Assigned " . count($pu_ids) . " polling units to election ID: $election_id");
                $action_result = ['success' => true, 'message' => count($pu_ids) . ' polling units assigned successfully.'];
                
                // Refresh data
                $stmt = $db->prepare("SELECT pus_json FROM elections WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$election_id, $tenant_id]);
                $election['pus_json'] = $stmt->fetch()['pus_json'];
                $selected_pus = json_decode($election['pus_json'] ?? '[]', true);
                
                if (!empty($selected_pus)) {
                    $placeholders = implode(',', array_fill(0, count($selected_pus), '?'));
                    $stmt = $db->prepare("
                        SELECT pu.*, w.name as ward_name, l.name as lga_name, s.name as state_name
                        FROM polling_units pu
                        LEFT JOIN wards w ON pu.ward_id = w.id
                        LEFT JOIN lgas l ON w.lga_id = l.id
                        LEFT JOIN states s ON l.state_id = s.id
                        WHERE pu.id IN ($placeholders)
                        ORDER BY s.name, l.name, w.name, pu.name
                    ");
                    $stmt->execute($selected_pus);
                    $assigned_pus = $stmt->fetchAll();
                }
                break;
                
            case 'remove_pu':
                $pu_id = (int)($_POST['pu_id'] ?? 0);
                if ($pu_id <= 0) throw new Exception('Invalid polling unit ID.');
                
                $selected_pus = array_filter($selected_pus, function($id) use ($pu_id) {
                    return $id != $pu_id;
                });
                $selected_pus = array_values($selected_pus);
                $pus_json = json_encode($selected_pus);
                
                $stmt = $db->prepare("UPDATE elections SET pus_json = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$pus_json, $election_id, $tenant_id]);
                
                logActivity($user_id, 'election_pu_removed', "Removed polling unit ID: $pu_id from election ID: $election_id");
                $action_result = ['success' => true, 'message' => 'Polling unit removed successfully.'];
                
                // Refresh data
                $stmt = $db->prepare("SELECT pus_json FROM elections WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$election_id, $tenant_id]);
                $election['pus_json'] = $stmt->fetch()['pus_json'];
                $selected_pus = json_decode($election['pus_json'] ?? '[]', true);
                
                if (!empty($selected_pus)) {
                    $placeholders = implode(',', array_fill(0, count($selected_pus), '?'));
                    $stmt = $db->prepare("
                        SELECT pu.*, w.name as ward_name, l.name as lga_name, s.name as state_name
                        FROM polling_units pu
                        LEFT JOIN wards w ON pu.ward_id = w.id
                        LEFT JOIN lgas l ON w.lga_id = l.id
                        LEFT JOIN states s ON l.state_id = s.id
                        WHERE pu.id IN ($placeholders)
                        ORDER BY s.name, l.name, w.name, pu.name
                    ");
                    $stmt->execute($selected_pus);
                    $assigned_pus = $stmt->fetchAll();
                } else {
                    $assigned_pus = [];
                }
                break;
                
            case 'assign_all':
                $all_pu_ids = array_column($polling_units, 'id');
                if (empty($all_pu_ids)) {
                    throw new Exception('No polling units available.');
                }
                $pus_json = json_encode($all_pu_ids);
                
                $stmt = $db->prepare("UPDATE elections SET pus_json = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$pus_json, $election_id, $tenant_id]);
                
                logActivity($user_id, 'election_pus_assigned_all', "Assigned all polling units to election ID: $election_id");
                $action_result = ['success' => true, 'message' => 'All polling units assigned successfully.'];
                
                // Refresh data
                $stmt = $db->prepare("SELECT pus_json FROM elections WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$election_id, $tenant_id]);
                $election['pus_json'] = $stmt->fetch()['pus_json'];
                $selected_pus = json_decode($election['pus_json'] ?? '[]', true);
                $assigned_pus = $polling_units;
                break;
                
            case 'clear_all':
                $pus_json = json_encode([]);
                
                $stmt = $db->prepare("UPDATE elections SET pus_json = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$pus_json, $election_id, $tenant_id]);
                
                logActivity($user_id, 'election_pus_cleared', "Cleared all polling units from election ID: $election_id");
                $action_result = ['success' => true, 'message' => 'All polling units cleared successfully.'];
                
                // Refresh data
                $stmt = $db->prepare("SELECT pus_json FROM elections WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$election_id, $tenant_id]);
                $election['pus_json'] = $stmt->fetch()['pus_json'];
                $selected_pus = json_decode($election['pus_json'] ?? '[]', true);
                $assigned_pus = [];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION POLLING UNITS - PROFESSIONAL UI STYLES
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
    .btn-danger {
        padding: 8px 18px;
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
        transform: translateY(-1px);
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
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    .btn-sm.success { background: #ECFDF5; color: #065F46; }
    .btn-sm.success:hover { background: #D1FAE5; }
    .btn-sm.warning { background: #FFFBEB; color: #92400E; }
    .btn-sm.warning:hover { background: #FEF3C7; }
    
    .election-banner {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 16px 20px;
        box-shadow: var(--shadow);
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    .election-banner .info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .election-banner .info .icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: #F5F3FF;
        color: #8B5CF6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    .election-banner .info h3 {
        font-size: 1.1rem;
        font-weight: 700;
    }
    .election-banner .info .meta {
        font-size: 0.8rem;
        color: var(--gray-500);
    }
    .election-banner .actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
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
    
    .pu-code {
        font-family: 'Courier New', monospace;
        font-size: 0.75rem;
        background: var(--gray-50);
        padding: 2px 8px;
        border-radius: 4px;
        display: inline-block;
    }
    
    .location-tag {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.65rem;
        background: var(--gray-100);
        color: var(--gray-600);
    }
    
    .assigned-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .assigned-badge.yes {
        background: #ECFDF5;
        color: #065F46;
    }
    .assigned-badge.no {
        background: #FEF2F2;
        color: #991B1B;
    }
    .assigned-badge .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .assigned-badge.yes .dot { background: #10B981; }
    .assigned-badge.no .dot { background: #EF4444; }
    
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
    .empty-state .btn-primary {
        margin-top: 12px;
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
        max-width: 560px;
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
    .modal .modal-body {
        margin-bottom: 16px;
    }
    .modal .modal-body .form-group {
        margin-bottom: 14px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .modal .modal-body .form-group label {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--gray-700);
    }
    .modal .modal-body .form-group .help-text {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .modal .modal-body .form-group select,
    .modal .modal-body .form-group input {
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
    .modal .modal-body .form-group select:focus,
    .modal .modal-body .form-group input:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .modal .modal-body .pu-list-container {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: 8px;
        background: var(--gray-50);
    }
    .modal .modal-body .pu-list-container::-webkit-scrollbar {
        width: 4px;
    }
    .modal .modal-body .pu-list-container::-webkit-scrollbar-thumb {
        background: var(--gray-300);
        border-radius: 8px;
    }
    .modal .modal-body .pu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 8px;
        border-bottom: 1px solid var(--gray-100);
        transition: var(--transition);
    }
    .modal .modal-body .pu-item:hover {
        background: var(--gray-50);
        border-radius: 4px;
    }
    .modal .modal-body .pu-item:last-child {
        border-bottom: none;
    }
    .modal .modal-body .pu-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--primary);
        cursor: pointer;
        flex-shrink: 0;
    }
    .modal .modal-body .pu-item .pu-info {
        flex: 1;
        font-size: 0.82rem;
    }
    .modal .modal-body .pu-item .pu-info .pu-name {
        font-weight: 500;
    }
    .modal .modal-body .pu-item .pu-info .pu-location {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .modal .modal-body .pu-item .pu-status {
        font-size: 0.65rem;
    }
    .modal .modal-body .select-all-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 8px 12px;
        border-bottom: 1px solid var(--gray-200);
        margin-bottom: 8px;
    }
    .modal .modal-body .select-all-row input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--primary);
        cursor: pointer;
    }
    .modal .modal-body .select-all-row label {
        font-weight: 600;
        font-size: 0.82rem;
        cursor: pointer;
        color: var(--gray-700);
    }
    .modal .modal-body .filter-row {
        display: flex;
        gap: 8px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }
    .modal .modal-body .filter-row select,
    .modal .modal-body .filter-row input {
        padding: 6px 10px;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.8rem;
        background: var(--gray-50);
        color: var(--gray-700);
        flex: 1;
        min-width: 120px;
    }
    .modal .modal-body .filter-row select:focus,
    .modal .modal-body .filter-row input:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
    }
    .modal .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--gray-200);
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
    .modal .modal-footer .btn-primary {
        background: var(--primary);
        color: white;
    }
    .modal .modal-footer .btn-primary:hover {
        background: var(--primary-dark);
    }
    .modal .modal-footer .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .modal-footer .btn-secondary:hover {
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
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-wrap { min-width: auto; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .election-banner { flex-direction: column; align-items: flex-start; }
        .modal { padding: 16px; margin: 10px; }
        .modal .modal-footer { flex-direction: column; }
        .modal .modal-footer .btn { width: 100%; justify-content: center; }
        .modal .modal-body .filter-row { flex-direction: column; }
        .modal .modal-body .filter-row select,
        .modal .modal-body .filter-row input { min-width: auto; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.2rem; }
        .data-table th, .data-table td { padding: 4px 8px; font-size: 0.7rem; }
        .pu-code { font-size: 0.65rem; padding: 1px 6px; }
        .location-tag { font-size: 0.55rem; padding: 1px 6px; }
        .election-banner .info .icon {
            width: 36px;
            height: 36px;
            font-size: 1rem;
        }
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
                    <i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:8px;"></i> Polling Units
                    <small>Manage polling units for <?php echo htmlspecialchars($election['name']); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('assignPuModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Assign PUs
                </button>
                <a href="elections-view.php?id=<?php echo $election_id; ?>" class="btn-outline">
                    <i class="fas fa-eye"></i> View Election
                </a>
                <a href="elections.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Election Banner -->
        <div class="election-banner">
            <div class="info">
                <div class="icon">
                    <i class="fas fa-vote-yea"></i>
                </div>
                <div>
                    <h3><?php echo htmlspecialchars($election['name']); ?></h3>
                    <div class="meta">
                        <span><i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $election['type'])); ?></span>
                        <span><i class="fas fa-flag-checkered"></i> <?php echo count($assigned_pus); ?> assigned PUs</span>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo count($polling_units); ?> available</span>
                    </div>
                </div>
            </div>
            <div class="actions">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="assign_all">
                    <button type="submit" class="btn-sm success" onclick="return confirm('Assign all available polling units to this election?')">
                        <i class="fas fa-check-double"></i> Assign All
                    </button>
                </form>
                <?php if (!empty($assigned_pus)): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="clear_all">
                    <button type="submit" class="btn-sm danger" onclick="return confirm('Remove all polling units from this election?')">
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item"><div class="number"><?php echo number_format(count($polling_units)); ?></div><div class="label">Total PUs Available</div></div>
            <div class="stat-item"><div class="number green"><?php echo number_format(count($assigned_pus)); ?></div><div class="label">Assigned to Election</div></div>
            <div class="stat-item"><div class="number purple"><?php echo number_format(count($polling_units) - count($assigned_pus)); ?></div><div class="label">Unassigned</div></div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <input type="hidden" name="id" value="<?php echo $election_id; ?>">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search assigned PUs by name or code..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($_GET['search'])): ?>
                    <a href="elections-pus.php?id=<?php echo $election_id; ?>" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Assigned PUs Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Assigned Polling Units
                    <span class="count"><?php echo count($assigned_pus); ?></span>
                </div>
                <div class="table-actions">
                    <span style="font-size:0.75rem;color:var(--gray-400);">
                        <?php echo count($assigned_pus); ?> assigned
                    </span>
                </div>
            </div>
            <?php if (count($assigned_pus) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Ward</th>
                        <th>LGA</th>
                        <th>State</th>
                        <th>Voters</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assigned_pus as $index => $pu): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><span class="pu-code"><?php echo htmlspecialchars($pu['code']); ?></span></td>
                        <td><?php echo htmlspecialchars($pu['name']); ?></td>
                        <td><?php echo htmlspecialchars($pu['ward_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($pu['lga_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($pu['state_name'] ?? 'N/A'); ?></td>
                        <td><?php echo number_format($pu['registered_voters'] ?? 0); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="remove_pu">
                                <input type="hidden" name="pu_id" value="<?php echo $pu['id']; ?>">
                                <button type="submit" class="btn-sm danger" onclick="return confirm('Remove this polling unit from the election?')" title="Remove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-map-marker-alt"></i>
                <h4>No polling units assigned</h4>
                <p>Assign polling units to this election using the "Assign PUs" button.</p>
                <button onclick="openModal('assignPuModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Assign Polling Units
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- ============================================================
ASSIGN PU MODAL
============================================================ -->
<div class="modal-overlay" id="assignPuModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Assign Polling Units</h3>
            <button class="close-btn" onclick="closeModal('assignPuModal')">&times;</button>
        </div>
        <form method="POST" action="" id="assignPuForm">
            <input type="hidden" name="action" value="assign_pus">
            <div class="modal-body">
                <p style="margin-bottom:12px;color:var(--gray-600);font-size:0.9rem;">
                    Select polling units to assign to <strong><?php echo htmlspecialchars($election['name']); ?></strong>
                </p>
                
                <!-- Filters -->
                <div class="filter-row">
                    <select id="filterState">
                        <option value="">All States</option>
                        <?php 
                        $unique_states = [];
                        foreach ($polling_units as $pu) {
                            if (!empty($pu['state_name']) && !in_array($pu['state_name'], $unique_states)) {
                                $unique_states[] = $pu['state_name'];
                            }
                        }
                        sort($unique_states);
                        foreach ($unique_states as $state): 
                        ?>
                            <option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filterLga">
                        <option value="">All LGAs</option>
                    </select>
                    <input type="text" id="filterSearch" placeholder="Search by name or code...">
                </div>
                
                <!-- PU List -->
                <div class="pu-list-container" id="puListContainer">
                    <div class="select-all-row">
                        <input type="checkbox" id="selectAllPu" onchange="toggleAllPu(this.checked)">
                        <label for="selectAllPu">Select All</label>
                        <span style="margin-left:auto;font-size:0.7rem;color:var(--gray-400);" id="selectedCount">0 selected</span>
                    </div>
                    <div id="puList">
                        <?php 
                        $assigned_ids = array_column($assigned_pus, 'id');
                        foreach ($polling_units as $pu): 
                            $is_assigned = in_array($pu['id'], $assigned_ids);
                        ?>
                            <div class="pu-item" 
                                 data-state="<?php echo htmlspecialchars($pu['state_name'] ?? ''); ?>" 
                                 data-lga="<?php echo htmlspecialchars($pu['lga_name'] ?? ''); ?>" 
                                 data-name="<?php echo strtolower($pu['name']); ?>" 
                                 data-code="<?php echo strtolower($pu['code']); ?>">
                                <input type="checkbox" name="pu_ids[]" value="<?php echo $pu['id']; ?>" <?php echo $is_assigned ? 'checked disabled' : ''; ?> onchange="updateSelectedCount()">
                                <div class="pu-info">
                                    <div class="pu-name">
                                        <?php echo htmlspecialchars($pu['code'] . ' - ' . $pu['name']); ?>
                                        <?php if ($is_assigned): ?>
                                            <span style="color:var(--secondary);font-size:0.65rem;font-weight:500;margin-left:6px;">
                                                <i class="fas fa-check-circle"></i> Assigned
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pu-location">
                                        <?php echo htmlspecialchars($pu['ward_name'] ?? ''); ?>
                                        <?php if (!empty($pu['lga_name'])): ?>
                                            · <?php echo htmlspecialchars($pu['lga_name']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($pu['state_name'])): ?>
                                            · <?php echo htmlspecialchars($pu['state_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="pu-status" style="font-size:0.65rem;color:var(--gray-400);">
                                    <?php echo number_format($pu['registered_voters'] ?? 0); ?> voters
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div style="margin-top:12px;padding:10px 14px;background:#F5F3FF;border-radius:8px;border:1px solid #EDE9FE;color:#5B21B6;font-size:0.8rem;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> Already assigned PUs are disabled. Uncheck to remove them.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignPuModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Selected</button>
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

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// ============================================================
// POLLING UNIT FILTERS
// ============================================================
document.getElementById('filterState').addEventListener('change', function() {
    var state = this.value;
    var lgaSelect = document.getElementById('filterLga');
    lgaSelect.innerHTML = '<option value="">All LGAs</option>';
    
    if (state) {
        var lgas = [];
        document.querySelectorAll('#puList .pu-item').forEach(function(item) {
            if (item.dataset.state === state) {
                var lga = item.dataset.lga;
                if (lga && !lgas.includes(lga)) {
                    lgas.push(lga);
                }
            }
        });
        lgas.sort();
        lgas.forEach(function(lga) {
            var option = document.createElement('option');
            option.value = lga;
            option.textContent = lga;
            lgaSelect.appendChild(option);
        });
    }
    filterPuList();
});

document.getElementById('filterLga').addEventListener('change', filterPuList);
document.getElementById('filterSearch').addEventListener('input', filterPuList);

function filterPuList() {
    var state = document.getElementById('filterState').value;
    var lga = document.getElementById('filterLga').value;
    var search = document.getElementById('filterSearch').value.toLowerCase();
    
    document.querySelectorAll('#puList .pu-item').forEach(function(item) {
        var show = true;
        
        if (state && item.dataset.state !== state) {
            show = false;
        }
        if (show && lga && item.dataset.lga !== lga) {
            show = false;
        }
        if (show && search) {
            var nameMatch = item.dataset.name.includes(search);
            var codeMatch = item.dataset.code.includes(search);
            if (!nameMatch && !codeMatch) {
                show = false;
            }
        }
        
        item.style.display = show ? 'flex' : 'none';
    });
    
    updateSelectedCount();
}

// ============================================================
// SELECT ALL / SELECTED COUNT
// ============================================================
function toggleAllPu(checked) {
    document.querySelectorAll('#puList input[type="checkbox"]:not(:disabled)').forEach(function(cb) {
        cb.checked = checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    var total = document.querySelectorAll('#puList input[type="checkbox"]:not(:disabled)').length;
    var checked = document.querySelectorAll('#puList input[type="checkbox"]:not(:disabled):checked').length;
    var selectedCount = document.getElementById('selectedCount');
    if (selectedCount) {
        selectedCount.textContent = checked + ' of ' + total + ' selected';
    }
}

// Update selected count on checkbox change
document.addEventListener('change', function(e) {
    if (e.target.closest('#puList input[type="checkbox"]')) {
        updateSelectedCount();
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