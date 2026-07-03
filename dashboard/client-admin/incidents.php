<?php
// ============================================================
// INCIDENT MANAGEMENT - CLIENT ADMIN (PROFESSIONAL UI)
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
            case 'assign_investigator':
                $id = (int)($_POST['id'] ?? 0);
                $investigator_id = (int)($_POST['investigator_id'] ?? 0);
                if ($id <= 0 || $investigator_id <= 0) throw new Exception('Invalid data provided.');
                
                $stmt = $db->prepare("UPDATE incidents SET assigned_to = ?, status = 'investigating', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$investigator_id, $id, $tenant_id]);
                
                logActivity($user_id, 'incident_assigned', "Assigned incident ID: $id to investigator: $investigator_id");
                $action_result = ['success' => true, 'message' => 'Investigator assigned successfully.'];
                break;
                
            case 'update_status':
                $id = (int)($_POST['id'] ?? 0);
                $status = trim($_POST['status'] ?? '');
                if ($id <= 0 || empty($status)) throw new Exception('Invalid data provided.');
                
                $stmt = $db->prepare("UPDATE incidents SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$status, $id, $tenant_id]);
                
                logActivity($user_id, 'incident_status_updated', "Updated incident ID: $id to status: $status");
                $action_result = ['success' => true, 'message' => 'Status updated successfully.'];
                break;
                
            case 'resolve_incident':
                $id = (int)($_POST['id'] ?? 0);
                $resolution_notes = trim($_POST['resolution_notes'] ?? '');
                if ($id <= 0) throw new Exception('Invalid incident ID.');
                
                $stmt = $db->prepare("UPDATE incidents SET status = 'resolved', resolved_by = ?, resolved_at = NOW(), resolution_notes = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$user_id, $resolution_notes, $id, $tenant_id]);
                
                logActivity($user_id, 'incident_resolved', "Resolved incident ID: $id");
                $action_result = ['success' => true, 'message' => 'Incident resolved successfully.'];
                break;
                
            case 'close_incident':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid incident ID.');
                
                $stmt = $db->prepare("UPDATE incidents SET status = 'closed', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                logActivity($user_id, 'incident_closed', "Closed incident ID: $id");
                $action_result = ['success' => true, 'message' => 'Incident closed successfully.'];
                break;
                
            case 'delete_incident':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid incident ID.');
                
                $stmt = $db->prepare("DELETE FROM incidents WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                logActivity($user_id, 'incident_deleted', "Deleted incident ID: $id");
                $action_result = ['success' => true, 'message' => 'Incident deleted successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH INVESTIGATORS FOR DROPDOWN
// ============================================================
$investigators = [];
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, r.name as role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND u.status = 'active' 
        AND r.level IN ('state', 'lga', 'ward', 'pu_agent')
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$tenant_id]);
    $investigators = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH ELECTIONS FOR FILTER
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("SELECT id, name, type, status, election_date FROM elections WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY election_date DESC");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH STATES FOR FILTER
// ============================================================
$states = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH INCIDENTS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$incident_type = isset($_GET['type']) ? trim($_GET['type']) : '';
$severity_filter = isset($_GET['severity']) ? trim($_GET['severity']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$election_filter = isset($_GET['election']) ? (int)$_GET['election'] : 0;
$state_filter = isset($_GET['state']) ? (int)$_GET['state'] : 0;

$where_conditions = ["i.tenant_id = ?"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(i.title LIKE ? OR i.description LIKE ? OR i.incident_type LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($incident_type)) {
    $where_conditions[] = "i.incident_type = ?";
    $params[] = $incident_type;
}

if (!empty($severity_filter)) {
    $where_conditions[] = "i.severity = ?";
    $params[] = $severity_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
}

if ($election_filter > 0) {
    $where_conditions[] = "i.election_id = ?";
    $params[] = $election_filter;
}

if ($state_filter > 0) {
    $where_conditions[] = "i.state_id = ?";
    $params[] = $state_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM incidents i $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_incidents = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_incidents / $limit);

// Fetch incidents
$sql = "
    SELECT i.*, 
           r.full_name as reporter_name,
           a.full_name as assigned_to_name,
           res.full_name as resolved_by_name,
           e.name as election_name,
           s.name as state_name,
           l.name as lga_name,
           w.name as ward_name,
           pu.name as pu_name
    FROM incidents i
    LEFT JOIN users r ON i.reporter_id = r.id
    LEFT JOIN users a ON i.assigned_to = a.id
    LEFT JOIN users res ON i.resolved_by = res.id
    LEFT JOIN elections e ON i.election_id = e.id
    LEFT JOIN states s ON i.state_id = s.id
    LEFT JOIN lgas l ON i.lga_id = l.id
    LEFT JOIN wards w ON i.ward_id = w.id
    LEFT JOIN polling_units pu ON i.pu_id = pu.id
    $where_clause
    ORDER BY 
        CASE WHEN i.status = 'reported' THEN 1 
             WHEN i.status = 'acknowledged' THEN 2 
             WHEN i.status = 'investigating' THEN 3 
             WHEN i.status = 'escalated' THEN 4 
             WHEN i.status = 'resolved' THEN 5 
             WHEN i.status = 'false_alarm' THEN 6 
             ELSE 7 END,
        i.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$incidents = $stmt->fetchAll();

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'reported' => 0,
    'acknowledged' => 0,
    'investigating' => 0,
    'escalated' => 0,
    'resolved' => 0,
    'closed' => 0,
    'false_alarm' => 0,
    'panic' => 0,
    'high_severity' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM incidents WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $stats['total'] = $stmt->fetch()['total'] ?? 0;
    
    $statuses = ['reported', 'acknowledged', 'investigating', 'escalated', 'resolved', 'closed', 'false_alarm'];
    foreach ($statuses as $status) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM incidents WHERE tenant_id = ? AND status = ?");
        $stmt->execute([$tenant_id, $status]);
        $stats[$status] = $stmt->fetch()['total'] ?? 0;
    }
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM incidents WHERE tenant_id = ? AND is_panic = 1");
    $stmt->execute([$tenant_id]);
    $stats['panic'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM incidents WHERE tenant_id = ? AND severity = 'high'");
    $stmt->execute([$tenant_id]);
    $stats['high_severity'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       INCIDENT MANAGEMENT - PROFESSIONAL UI STYLES
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
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
        margin-bottom: 20px;
    }
    .stat-item {
        background: white;
        border-radius: 10px;
        padding: 12px 14px;
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
        transform: translateY(-2px);
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
    .stat-item .number.orange { color: #F59E0B; }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .label {
        font-size: 0.65rem;
        color: var(--gray-500);
        margin-top: 2px;
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
        gap: 8px;
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
        min-width: 100px;
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
    .table-container .table-header .table-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .table-container .table-header .table-actions span {
        font-size: 0.7rem;
        color: var(--gray-400);
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
    
    .incident-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .incident-icon.violence { background: #FEF2F2; color: #DC2626; }
    .incident-icon.vote_buying { background: #FFFBEB; color: #F59E0B; }
    .incident-icon.ballot_stuffing { background: #FEF2F2; color: #DC2626; }
    .incident-icon.intimidation { background: #F5F3FF; color: #7C3AED; }
    .incident-icon.material_shortage { background: #EFF6FF; color: #2563EB; }
    .incident-icon.security { background: #FEF2F2; color: #DC2626; }
    .incident-icon.technical_issue { background: #EFF6FF; color: #2563EB; }
    .incident-icon.panic_button { background: #FEF2F2; color: #DC2626; }
    .incident-icon.other { background: var(--gray-100); color: var(--gray-500); }
    
    .badge-severity {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
    }
    .badge-severity.low { background: #ECFDF5; color: #065F46; }
    .badge-severity.medium { background: #FFFBEB; color: #92400E; }
    .badge-severity.high { background: #FEF2F2; color: #991B1B; }
    .badge-severity.critical { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    
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
    .badge-status.reported { background: #FEF2F2; color: #991B1B; }
    .badge-status.reported .dot { background: #EF4444; animation: pulse-dot 2s ease-in-out infinite; }
    .badge-status.acknowledged { background: #FFFBEB; color: #92400E; }
    .badge-status.acknowledged .dot { background: #F59E0B; }
    .badge-status.investigating { background: #EFF6FF; color: #1E40AF; }
    .badge-status.investigating .dot { background: #3B82F6; }
    .badge-status.escalated { background: #F5F3FF; color: #5B21B6; }
    .badge-status.escalated .dot { background: #8B5CF6; }
    .badge-status.resolved { background: #ECFDF5; color: #065F46; }
    .badge-status.resolved .dot { background: #10B981; }
    .badge-status.closed { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.closed .dot { background: var(--gray-400); }
    .badge-status.false_alarm { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.false_alarm .dot { background: var(--gray-400); }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(0.8); }
    }
    
    .badge-panic {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #FEF2F2;
        color: #991B1B;
        border: 1px solid #FECACA;
        animation: pulse-panic 2s ease-in-out infinite;
    }
    @keyframes pulse-panic {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }
    
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
        .modal { padding: 16px; margin: 10px; }
        .modal .form-actions { flex-direction: column; }
        .modal .form-actions .btn { width: 100%; justify-content: center; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
        .stat-item { padding: 8px 10px; }
        .stat-item .number { font-size: 1.1rem; }
        .data-table th, .data-table td { padding: 4px 8px; font-size: 0.7rem; }
        .badge-status, .badge-severity { font-size: 0.5rem; padding: 1px 6px; }
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
                    <i class="fas fa-exclamation-triangle" style="color:var(--danger);margin-right:8px;"></i> Incident Management
                    <small>Monitor and manage election-related incidents</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="incidents-report.php" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Report Incident
                </a>
                <a href="incidents-export.php" class="btn-outline">
                    <i class="fas fa-file-export"></i> Export Report
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total Incidents</div>
            </div>
            <div class="stat-item">
                <div class="number red"><?php echo number_format($stats['reported']); ?></div>
                <div class="label">Reported</div>
            </div>
            <div class="stat-item">
                <div class="number yellow"><?php echo number_format($stats['investigating']); ?></div>
                <div class="label">Investigating</div>
            </div>
            <div class="stat-item">
                <div class="number orange"><?php echo number_format($stats['escalated']); ?></div>
                <div class="label">Escalated</div>
            </div>
            <div class="stat-item">
                <div class="number green"><?php echo number_format($stats['resolved']); ?></div>
                <div class="label">Resolved</div>
            </div>
            <div class="stat-item">
                <div class="number purple"><?php echo number_format($stats['panic']); ?></div>
                <div class="label">Panic Alerts</div>
            </div>
            <div class="stat-item">
                <div class="number red"><?php echo number_format($stats['high_severity']); ?></div>
                <div class="label">High Severity</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:8px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search incidents..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="type">
                    <option value="">All Types</option>
                    <option value="violence" <?php echo $incident_type == 'violence' ? 'selected' : ''; ?>>Violence</option>
                    <option value="vote_buying" <?php echo $incident_type == 'vote_buying' ? 'selected' : ''; ?>>Vote Buying</option>
                    <option value="ballot_stuffing" <?php echo $incident_type == 'ballot_stuffing' ? 'selected' : ''; ?>>Ballot Stuffing</option>
                    <option value="intimidation" <?php echo $incident_type == 'intimidation' ? 'selected' : ''; ?>>Intimidation</option>
                    <option value="material_shortage" <?php echo $incident_type == 'material_shortage' ? 'selected' : ''; ?>>Material Shortage</option>
                    <option value="security" <?php echo $incident_type == 'security' ? 'selected' : ''; ?>>Security Issues</option>
                    <option value="technical_issue" <?php echo $incident_type == 'technical_issue' ? 'selected' : ''; ?>>Technical Issues</option>
                    <option value="panic_button" <?php echo $incident_type == 'panic_button' ? 'selected' : ''; ?>>Panic Button</option>
                    <option value="other" <?php echo $incident_type == 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
                <select name="severity">
                    <option value="">All Severity</option>
                    <option value="low" <?php echo $severity_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                    <option value="medium" <?php echo $severity_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="high" <?php echo $severity_filter == 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="critical" <?php echo $severity_filter == 'critical' ? 'selected' : ''; ?>>Critical</option>
                </select>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="reported" <?php echo $status_filter == 'reported' ? 'selected' : ''; ?>>Reported</option>
                    <option value="acknowledged" <?php echo $status_filter == 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                    <option value="investigating" <?php echo $status_filter == 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                    <option value="escalated" <?php echo $status_filter == 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                    <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                    <option value="false_alarm" <?php echo $status_filter == 'false_alarm' ? 'selected' : ''; ?>>False Alarm</option>
                </select>
                <select name="state">
                    <option value="">All States</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?php echo $state['id']; ?>" <?php echo $state_filter == $state['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($state['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || !empty($incident_type) || !empty($severity_filter) || !empty($status_filter) || $election_filter > 0 || $state_filter > 0): ?>
                    <a href="incidents.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Incidents Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Incidents List
                    <span class="count"><?php echo number_format($total_incidents); ?></span>
                </div>
                <div class="table-actions">
                    <span>Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_incidents); ?></span>
                    <button class="btn-sm info" onclick="window.location.href='incidents-export.php'">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>Incident</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($incidents) > 0): ?>
                        <?php foreach ($incidents as $index => $incident): 
                            $icon_class = $incident['incident_type'] == 'panic_button' ? 'panic_button' : $incident['incident_type'];
                            $icon_map = [
                                'violence' => 'fa-fist-raised',
                                'vote_buying' => 'fa-hand-holding-usd',
                                'ballot_stuffing' => 'fa-box',
                                'intimidation' => 'fa-user-shield',
                                'material_shortage' => 'fa-box-open',
                                'security' => 'fa-shield-alt',
                                'technical_issue' => 'fa-microchip',
                                'panic_button' => 'fa-exclamation-circle',
                                'other' => 'fa-ellipsis-h'
                            ];
                        ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div class="incident-icon <?php echo $icon_class; ?>">
                                            <i class="fas <?php echo $icon_map[$incident['incident_type']] ?? 'fa-exclamation-triangle'; ?>"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight:500;font-size:0.85rem;">
                                                <?php echo htmlspecialchars($incident['title']); ?>
                                                <?php if ($incident['is_panic']): ?>
                                                    <span class="badge-panic"><i class="fas fa-exclamation-triangle"></i> PANIC</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size:0.65rem;color:var(--gray-400);">
                                                <?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?>
                                                <?php if (!empty($incident['reporter_name'])): ?>
                                                    · <?php echo htmlspecialchars($incident['reporter_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:0.7rem;text-transform:capitalize;">
                                        <?php echo str_replace('_', ' ', $incident['incident_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:0.7rem;">
                                        <?php if (!empty($incident['pu_name'])): ?>
                                            <div><i class="fas fa-flag-checkered" style="font-size:0.6rem;"></i> <?php echo htmlspecialchars($incident['pu_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($incident['ward_name'])): ?>
                                            <div style="color:var(--gray-500);"><?php echo htmlspecialchars($incident['ward_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($incident['lga_name'])): ?>
                                            <div style="color:var(--gray-500);"><?php echo htmlspecialchars($incident['lga_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($incident['state_name'])): ?>
                                            <div style="color:var(--gray-500);"><?php echo htmlspecialchars($incident['state_name']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-severity <?php echo $incident['severity']; ?>">
                                        <?php echo ucfirst($incident['severity']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $incident['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($incident['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;">
                                        <?php echo htmlspecialchars($incident['assigned_to_name'] ?? 'Unassigned'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <button onclick="viewIncident(<?php echo $incident['id']; ?>)">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                            <a href="incidents-edit.php?id=<?php echo $incident['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button onclick="openAssignModal(<?php echo $incident['id']; ?>)">
                                                <i class="fas fa-user-plus"></i> Assign Investigator
                                            </button>
                                            <button onclick="openStatusModal(<?php echo $incident['id']; ?>)">
                                                <i class="fas fa-exchange-alt"></i> Update Status
                                            </button>
                                            <button onclick="openResolveModal(<?php echo $incident['id']; ?>)">
                                                <i class="fas fa-check-circle" style="color:var(--secondary);"></i> Resolve
                                            </button>
                                            <?php if ($incident['status'] == 'resolved' || $incident['status'] == 'false_alarm'): ?>
                                                <button onclick="closeIncident(<?php echo $incident['id']; ?>)">
                                                    <i class="fas fa-times-circle"></i> Close
                                                </button>
                                            <?php endif; ?>
                                            <div class="divider"></div>
                                            <button class="danger" onclick="deleteIncident(<?php echo $incident['id']; ?>)">
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
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <h4>No incidents found</h4>
                                    <p>Incidents reported from the field will appear here.</p>
                                    <button onclick="window.location.href='incidents-report.php'" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Report Incident
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
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_incidents); ?></strong> of <strong><?php echo number_format($total_incidents); ?></strong> incidents
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($incident_type); ?>&severity=<?php echo urlencode($severity_filter); ?>&status=<?php echo urlencode($status_filter); ?>&state=<?php echo $state_filter; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&type=' . urlencode($incident_type) . '&severity=' . urlencode($severity_filter) . '&status=' . urlencode($status_filter) . '&state=' . $state_filter . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($incident_type); ?>&severity=<?php echo urlencode($severity_filter); ?>&status=<?php echo urlencode($status_filter); ?>&state=<?php echo $state_filter; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&type=' . urlencode($incident_type) . '&severity=' . urlencode($severity_filter) . '&status=' . urlencode($status_filter) . '&state=' . $state_filter . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($incident_type); ?>&severity=<?php echo urlencode($severity_filter); ?>&status=<?php echo urlencode($status_filter); ?>&state=<?php echo $state_filter; ?>">
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

<!-- Assign Investigator Modal -->
<div class="modal-overlay" id="assignModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus" style="color:var(--primary);"></i> Assign Investigator</h3>
            <button class="close-btn" onclick="closeModal('assignModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="assign_investigator">
            <input type="hidden" name="id" id="assignIncidentId">
            <div class="form-group">
                <label>Select Investigator <span class="required">*</span></label>
                <select name="investigator_id" required>
                    <option value="">Select Investigator</option>
                    <?php foreach ($investigators as $investigator): ?>
                        <option value="<?php echo $investigator['id']; ?>">
                            <?php echo htmlspecialchars($investigator['first_name'] . ' ' . $investigator['last_name']); ?>
                            (<?php echo htmlspecialchars($investigator['role_name'] ?? 'N/A'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help-text">Assign an investigator to handle this incident</div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Assign</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal-overlay" id="statusModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-exchange-alt" style="color:var(--primary);"></i> Update Status</h3>
            <button class="close-btn" onclick="closeModal('statusModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" id="statusIncidentId">
            <div class="form-group">
                <label>New Status <span class="required">*</span></label>
                <select name="status" required>
                    <option value="reported">Reported</option>
                    <option value="acknowledged">Acknowledged</option>
                    <option value="investigating">Investigating</option>
                    <option value="escalated">Escalated</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                    <option value="false_alarm">False Alarm</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Resolve Incident Modal -->
<div class="modal-overlay" id="resolveModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Resolve Incident</h3>
            <button class="close-btn" onclick="closeModal('resolveModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="resolve_incident">
            <input type="hidden" name="id" id="resolveIncidentId">
            <div class="form-group">
                <label>Resolution Notes <span class="required">*</span></label>
                <textarea name="resolution_notes" placeholder="Describe how this incident was resolved..." rows="4" required></textarea>
                <div class="help-text">Provide detailed notes on the resolution</div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resolveModal')">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Resolve</button>
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
// INCIDENT FUNCTIONS
// ============================================================
function viewIncident(id) {
    window.location.href = 'incidents-view.php?id=' + id;
}

function openAssignModal(id) {
    document.getElementById('assignIncidentId').value = id;
    openModal('assignModal');
}

function openStatusModal(id) {
    document.getElementById('statusIncidentId').value = id;
    openModal('statusModal');
}

function openResolveModal(id) {
    document.getElementById('resolveIncidentId').value = id;
    openModal('resolveModal');
}

function closeIncident(id) {
    if (confirm('Close this incident?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="close_incident"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteIncident(id) {
    if (confirm('Delete this incident? This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_incident"><input type="hidden" name="id" value="' + id + '">';
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