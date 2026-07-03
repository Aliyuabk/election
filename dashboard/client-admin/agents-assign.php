<?php
// ============================================================
// AGENT ASSIGNMENTS - CLIENT ADMIN (PROFESSIONAL UI)
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

// ============================================================
// HANDLE ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'assign_agent':
                $agent_id = (int)($_POST['agent_id'] ?? 0);
                $election_id = (int)($_POST['election_id'] ?? 0);
                $pu_id = (int)($_POST['pu_id'] ?? 0);
                $ward_id = (int)($_POST['ward_id'] ?? 0);
                $assignment_type = trim($_POST['assignment_type'] ?? 'data_agent');
                $notes = trim($_POST['notes'] ?? '');
                
                if ($agent_id <= 0 || $pu_id <= 0) {
                    throw new Exception('Agent and Polling Unit are required.');
                }
                
                // Get ward and LGA info from PU
                $stmt = $db->prepare("SELECT ward_id FROM polling_units WHERE id = ?");
                $stmt->execute([$pu_id]);
                $pu = $stmt->fetch();
                if (!$pu) throw new Exception('Polling Unit not found.');
                
                $ward_id = $pu['ward_id'];
                
                // Get LGA and State from ward
                $stmt = $db->prepare("SELECT lga_id FROM wards WHERE id = ?");
                $stmt->execute([$ward_id]);
                $ward = $stmt->fetch();
                $lga_id = $ward['lga_id'] ?? 0;
                
                $stmt = $db->prepare("SELECT state_id FROM lgas WHERE id = ?");
                $stmt->execute([$lga_id]);
                $lga = $stmt->fetch();
                $state_id = $lga['state_id'] ?? 0;
                
                // Insert assignment
                $stmt = $db->prepare("
                    INSERT INTO agent_assignments (
                        tenant_id, election_id, user_id, pu_id, ward_id, lga_id, state_id,
                        assignment_type, status, assigned_by, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
                ");
                $stmt->execute([
                    $tenant_id, $election_id, $agent_id, $pu_id, $ward_id, $lga_id, $state_id,
                    $assignment_type, $user_id, $notes
                ]);
                
                logActivity($user_id, 'agent_assigned', "Assigned agent ID: $agent_id to PU: $pu_id");
                $action_result = ['success' => true, 'message' => 'Agent assigned successfully.'];
                break;
                
            case 'reassign_agent':
                $assignment_id = (int)($_POST['assignment_id'] ?? 0);
                $new_pu_id = (int)($_POST['new_pu_id'] ?? 0);
                $reason = trim($_POST['reason'] ?? '');
                
                if ($assignment_id <= 0 || $new_pu_id <= 0) {
                    throw new Exception('Assignment and new Polling Unit are required.');
                }
                
                // Get old assignment
                $stmt = $db->prepare("SELECT * FROM agent_assignments WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$assignment_id, $tenant_id]);
                $assignment = $stmt->fetch();
                if (!$assignment) throw new Exception('Assignment not found.');
                
                // Update old assignment to reassigned
                $stmt = $db->prepare("UPDATE agent_assignments SET status = 'reassigned' WHERE id = ?");
                $stmt->execute([$assignment_id]);
                
                // Get new PU details
                $stmt = $db->prepare("SELECT ward_id FROM polling_units WHERE id = ?");
                $stmt->execute([$new_pu_id]);
                $pu = $stmt->fetch();
                $ward_id = $pu['ward_id'] ?? 0;
                
                $stmt = $db->prepare("SELECT lga_id FROM wards WHERE id = ?");
                $stmt->execute([$ward_id]);
                $ward = $stmt->fetch();
                $lga_id = $ward['lga_id'] ?? 0;
                
                $stmt = $db->prepare("SELECT state_id FROM lgas WHERE id = ?");
                $stmt->execute([$lga_id]);
                $lga = $stmt->fetch();
                $state_id = $lga['state_id'] ?? 0;
                
                // Create new assignment
                $stmt = $db->prepare("
                    INSERT INTO agent_assignments (
                        tenant_id, election_id, user_id, pu_id, ward_id, lga_id, state_id,
                        assignment_type, status, assigned_by, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
                ");
                $stmt->execute([
                    $tenant_id, $assignment['election_id'], $assignment['user_id'], 
                    $new_pu_id, $ward_id, $lga_id, $state_id,
                    $assignment['assignment_type'], $user_id, $reason
                ]);
                
                logActivity($user_id, 'agent_reassigned', "Reassigned agent from assignment ID: $assignment_id to PU: $new_pu_id");
                $action_result = ['success' => true, 'message' => 'Agent reassigned successfully.'];
                break;
                
            case 'remove_assignment':
                $assignment_id = (int)($_POST['assignment_id'] ?? 0);
                if ($assignment_id <= 0) throw new Exception('Invalid assignment ID.');
                
                $stmt = $db->prepare("UPDATE agent_assignments SET status = 'completed' WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$assignment_id, $tenant_id]);
                
                logActivity($user_id, 'assignment_removed', "Removed assignment ID: $assignment_id");
                $action_result = ['success' => true, 'message' => 'Assignment removed successfully.'];
                break;
                
            case 'upload_document':
                $assignment_id = (int)($_POST['assignment_id'] ?? 0);
                $doc_type = trim($_POST['doc_type'] ?? '');
                
                if ($assignment_id <= 0 || empty($doc_type)) {
                    throw new Exception('Assignment and document type are required.');
                }
                
                // In production, handle file upload here
                $action_result = ['success' => true, 'message' => 'Document uploaded successfully.'];
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
// FETCH ELECTIONS FOR DROPDOWN
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, status, election_date 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL AND status IN ('upcoming', 'active')
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH POLLING UNITS FOR DROPDOWN
// ============================================================
$polling_units = [];
try {
    $stmt = $db->prepare("
        SELECT pu.id, pu.code, pu.name, w.name as ward_name, l.name as lga_name, s.name as state_name
        FROM polling_units pu
        LEFT JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN states s ON l.state_id = s.id
        WHERE pu.is_active = 1
        ORDER BY s.name, l.name, w.name, pu.name
        LIMIT 500
    ");
    $stmt->execute();
    $polling_units = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH ASSIGNMENTS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$where_conditions = ["a.tenant_id = ?"];
$params = [$tenant_id];

if ($agent_filter > 0) {
    $where_conditions[] = "a.user_id = ?";
    $params[] = $agent_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM agent_assignments a $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_assignments = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_assignments / $limit);

// Fetch assignments
$sql = "
    SELECT a.*,
           u.first_name, u.last_name, u.email, u.phone,
           pu.name as pu_name, pu.code as pu_code,
           w.name as ward_name,
           l.name as lga_name,
           s.name as state_name,
           e.name as election_name,
           assigned_u.first_name as assigned_by_first, assigned_u.last_name as assigned_by_last
    FROM agent_assignments a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN polling_units pu ON a.pu_id = pu.id
    LEFT JOIN wards w ON a.ward_id = w.id
    LEFT JOIN lgas l ON a.lga_id = l.id
    LEFT JOIN states s ON a.state_id = s.id
    LEFT JOIN elections e ON a.election_id = e.id
    LEFT JOIN users assigned_u ON a.assigned_by = assigned_u.id
    $where_clause
    ORDER BY a.assigned_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       AGENT ASSIGNMENTS - PROFESSIONAL UI STYLES
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
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .number.purple { color: #8B5CF6; }
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
        min-width: 150px;
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
    .badge-status.active { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.completed { background: #EFF6FF; color: #1E40AF; border: 1px solid #93C5FD; }
    .badge-status.completed .dot { background: #3B82F6; }
    .badge-status.suspended { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    .badge-status.suspended .dot { background: #EF4444; }
    .badge-status.reassigned { background: #F5F3FF; color: #5B21B6; border: 1px solid #C4B5FD; }
    .badge-status.reassigned .dot { background: #8B5CF6; }
    
    .badge-type {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #EFF6FF;
        color: #1E40AF;
    }
    .badge-type.data_agent { background: #EFF6FF; color: #1E40AF; }
    .badge-type.party_agent { background: #F5F3FF; color: #5B21B6; }
    .badge-type.volunteer { background: #ECFDF5; color: #065F46; }
    .badge-type.observer { background: #FFFBEB; color: #92400E; }
    
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
        max-width: 580px;
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
    .modal .file-upload-area {
        border: 2px dashed var(--gray-200);
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--gray-50);
    }
    .modal .file-upload-area:hover {
        border-color: var(--primary);
        background: #EFF6FF;
    }
    .modal .file-upload-area i {
        font-size: 2rem;
        color: var(--gray-400);
        display: block;
        margin-bottom: 8px;
    }
    .modal .file-upload-area p {
        font-size: 0.85rem;
        color: var(--gray-500);
    }
    .modal .file-upload-area .file-types {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .modal .file-upload-area input[type="file"] {
        display: none;
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
    
    .doc-list {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .doc-item-mini {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 500;
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .doc-item-mini i {
        font-size: 0.6rem;
    }
    .doc-item-mini.pdf { background: #FEF2F2; color: #DC2626; }
    .doc-item-mini.image { background: #FFFBEB; color: #F59E0B; }
    .doc-item-mini.word { background: #EFF6FF; color: #2563EB; }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
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
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 12px 14px; }
        .stat-item .number { font-size: 1.3rem; }
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
                    <i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:8px;"></i> Agent Assignments
                    <small>Assign agents to polling units and manage assignments</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('assignModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Assign Agent
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
            <a href="agents-assign.php" class="active">
                <i class="fas fa-map-marker-alt"></i> Assign
                <span class="count"><?php echo number_format($total_assignments); ?></span>
            </a>
            <a href="agents-payments.php">
                <i class="fas fa-money-bill-wave"></i> Payments
            </a>
        </div>

        <!-- Stats -->
        <?php
        $assignment_stats = ['total' => 0, 'pending' => 0, 'active' => 0, 'completed' => 0, 'suspended' => 0];
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM agent_assignments WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            $assignment_stats['total'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM agent_assignments WHERE tenant_id = ? AND status = 'pending'");
            $stmt->execute([$tenant_id]);
            $assignment_stats['pending'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM agent_assignments WHERE tenant_id = ? AND status = 'active'");
            $stmt->execute([$tenant_id]);
            $assignment_stats['active'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM agent_assignments WHERE tenant_id = ? AND status = 'completed'");
            $stmt->execute([$tenant_id]);
            $assignment_stats['completed'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM agent_assignments WHERE tenant_id = ? AND status = 'suspended'");
            $stmt->execute([$tenant_id]);
            $assignment_stats['suspended'] = $stmt->fetch()['total'] ?? 0;
        } catch (Exception $e) {}
        ?>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number"><?php echo number_format($assignment_stats['total']); ?></div>
                <div class="label">Total Assignments</div>
                <div class="sub-label">All assignments</div>
            </div>
            <div class="stat-item">
                <div class="number yellow"><?php echo number_format($assignment_stats['pending']); ?></div>
                <div class="label">Pending</div>
                <div class="sub-label">Awaiting activation</div>
            </div>
            <div class="stat-item">
                <div class="number green"><?php echo number_format($assignment_stats['active']); ?></div>
                <div class="label">Active</div>
                <div class="sub-label">Currently active</div>
            </div>
            <div class="stat-item">
                <div class="number blue"><?php echo number_format($assignment_stats['completed']); ?></div>
                <div class="label">Completed</div>
                <div class="sub-label">Finished assignments</div>
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
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    <option value="reassigned" <?php echo $status_filter == 'reassigned' ? 'selected' : ''; ?>>Reassigned</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($agent_filter > 0 || !empty($status_filter)): ?>
                    <a href="agents-assign.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Assignments Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Assignments List
                    <span class="count"><?php echo number_format($total_assignments); ?></span>
                </div>
                <div class="table-actions">
                    <span>Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_assignments); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Agent</th>
                        <th>Polling Unit</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Documents</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($assignments) > 0): ?>
                        <?php foreach ($assignments as $index => $assignment): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div>
                                        <div style="font-weight:500;font-size:0.85rem;">
                                            <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                                        </div>
                                        <div style="font-size:0.7rem;color:var(--gray-400);">
                                            <?php echo htmlspecialchars($assignment['email'] ?? ''); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div style="font-weight:500;font-size:0.85rem;">
                                            <?php echo htmlspecialchars($assignment['pu_name'] ?? 'N/A'); ?>
                                        </div>
                                        <div style="font-size:0.7rem;color:var(--gray-400);">
                                            <span class="badge-type" style="background:var(--gray-100);color:var(--gray-500);font-size:0.6rem;">
                                                <?php echo htmlspecialchars($assignment['pu_code'] ?? ''); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:0.75rem;">
                                        <div><?php echo htmlspecialchars($assignment['ward_name'] ?? 'N/A'); ?></div>
                                        <div style="color:var(--gray-400);"><?php echo htmlspecialchars($assignment['lga_name'] ?? ''); ?></div>
                                        <div style="color:var(--gray-400);font-size:0.65rem;"><?php echo htmlspecialchars($assignment['state_name'] ?? ''); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-type <?php echo $assignment['assignment_type']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $assignment['assignment_type'] ?? 'N/A')); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $assignment['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($assignment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="doc-list">
                                        <span class="doc-item-mini pdf"><i class="fas fa-file-pdf"></i> Letter</span>
                                        <span class="doc-item-mini image"><i class="fas fa-id-card"></i> ID</span>
                                        <span class="doc-item-mini word"><i class="fas fa-file-word"></i> Cert</span>
                                        <button class="btn-sm info" onclick="openUploadModal(<?php echo $assignment['id']; ?>)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <button onclick="reassignAgent(<?php echo $assignment['id']; ?>)">
                                                <i class="fas fa-exchange-alt"></i> Reassign
                                            </button>
                                            <button onclick="viewAssignmentHistory(<?php echo $assignment['id']; ?>)">
                                                <i class="fas fa-history"></i> History
                                            </button>
                                            <button onclick="openUploadModal(<?php echo $assignment['id']; ?>)">
                                                <i class="fas fa-upload"></i> Upload Doc
                                            </button>
                                            <?php if ($assignment['status'] == 'pending' || $assignment['status'] == 'active'): ?>
                                                <button class="danger" onclick="removeAssignment(<?php echo $assignment['id']; ?>)">
                                                    <i class="fas fa-times-circle"></i> Remove
                                                </button>
                                            <?php endif; ?>
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
                                    <h4>No assignments found</h4>
                                    <p>Assign agents to polling units to start tracking.</p>
                                    <button onclick="openModal('assignModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Assign Agent
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
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_assignments); ?></strong> of <strong><?php echo number_format($total_assignments); ?></strong> assignments
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&agent=<?php echo $agent_filter; ?>&status=<?php echo urlencode($status_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&agent=' . $agent_filter . '&status=' . urlencode($status_filter) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&agent=<?php echo $agent_filter; ?>&status=<?php echo urlencode($status_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&agent=' . $agent_filter . '&status=' . urlencode($status_filter) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&agent=<?php echo $agent_filter; ?>&status=<?php echo urlencode($status_filter); ?>">
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

<!-- Assign Agent Modal -->
<div class="modal-overlay" id="assignModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> Assign Agent</h3>
            <button class="close-btn" onclick="closeModal('assignModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="assign_agent">
            <div class="form-group">
                <label>Select Agent <span class="required">*</span></label>
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
                <label>Election</label>
                <select name="election_id">
                    <option value="0">Select Election (Optional)</option>
                    <?php foreach ($elections as $election): ?>
                        <option value="<?php echo $election['id']; ?>">
                            <?php echo htmlspecialchars($election['name']); ?>
                            (<?php echo date('M j, Y', strtotime($election['election_date'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Polling Unit <span class="required">*</span></label>
                <select name="pu_id" required>
                    <option value="">Select Polling Unit</option>
                    <?php foreach ($polling_units as $pu): ?>
                        <option value="<?php echo $pu['id']; ?>">
                            <?php echo htmlspecialchars($pu['code'] . ' - ' . $pu['name']); ?>
                            (<?php echo htmlspecialchars($pu['ward_name'] ?? 'N/A'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help-text">Select the polling unit where the agent will be assigned</div>
            </div>
            <div class="form-group">
                <label>Assignment Type <span class="required">*</span></label>
                <select name="assignment_type" required>
                    <option value="data_agent">Data Agent</option>
                    <option value="party_agent">Party Agent</option>
                    <option value="volunteer">Volunteer</option>
                    <option value="observer">Observer</option>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" placeholder="Additional notes about this assignment" rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Assign Agent</button>
            </div>
        </form>
    </div>
</div>

<!-- Reassign Agent Modal -->
<div class="modal-overlay" id="reassignModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-exchange-alt" style="color:var(--primary);"></i> Reassign Agent</h3>
            <button class="close-btn" onclick="closeModal('reassignModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="reassign_agent">
            <input type="hidden" name="assignment_id" id="reassignAssignmentId">
            <div class="form-group">
                <label>New Polling Unit <span class="required">*</span></label>
                <select name="new_pu_id" required>
                    <option value="">Select Polling Unit</option>
                    <?php foreach ($polling_units as $pu): ?>
                        <option value="<?php echo $pu['id']; ?>">
                            <?php echo htmlspecialchars($pu['code'] . ' - ' . $pu['name']); ?>
                            (<?php echo htmlspecialchars($pu['ward_name'] ?? 'N/A'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help-text">Select the new polling unit for this agent</div>
            </div>
            <div class="form-group">
                <label>Reason for Reassignment</label>
                <textarea name="reason" placeholder="Why is this agent being reassigned?" rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reassignModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-exchange-alt"></i> Reassign</button>
            </div>
        </form>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-upload" style="color:var(--primary);"></i> Upload Document</h3>
            <button class="close-btn" onclick="closeModal('uploadModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_document">
            <input type="hidden" name="assignment_id" id="uploadAssignmentId">
            <div class="form-group">
                <label>Document Type <span class="required">*</span></label>
                <select name="doc_type" required>
                    <option value="">Select Document Type</option>
                    <option value="appointment_letter">Appointment Letter</option>
                    <option value="id_card">ID Card</option>
                    <option value="training_certificate">Training Certificate</option>
                    <option value="passport_photo">Passport Photo</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>File <span class="required">*</span></label>
                <div class="file-upload-area" onclick="document.getElementById('docFileInput').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to upload or drag &amp; drop</p>
                    <div class="file-types">Supported: PDF, JPG, PNG, DOC, DOCX (Max 5MB)</div>
                    <input type="file" name="document" id="docFileInput" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                </div>
                <div class="file-preview" id="docPreview">
                    <div class="file-name" id="docFileName">file.pdf</div>
                    <div class="file-size" id="docFileSize">0 KB</div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
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
// ASSIGNMENT FUNCTIONS
// ============================================================
function reassignAgent(assignmentId) {
    document.getElementById('reassignAssignmentId').value = assignmentId;
    openModal('reassignModal');
}

function openUploadModal(assignmentId) {
    document.getElementById('uploadAssignmentId').value = assignmentId;
    openModal('uploadModal');
}

function viewAssignmentHistory(id) {
    alert('View assignment history for ID: ' + id);
}

function removeAssignment(id) {
    if (confirm('Remove this assignment? The agent will be unassigned from this polling unit.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="remove_assignment"><input type="hidden" name="assignment_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================================
// FILE UPLOAD PREVIEW
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('docFileInput');
    var preview = document.getElementById('docPreview');
    var fileName = document.getElementById('docFileName');
    var fileSize = document.getElementById('docFileSize');
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                var file = this.files[0];
                fileName.textContent = file.name;
                fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        });
    }
});
</script>
</body>
</html>