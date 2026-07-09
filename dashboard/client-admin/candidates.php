<?php
// ============================================================
// CANDIDATES MANAGEMENT - CLIENT ADMIN (FIXED)
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
            case 'delete_candidate':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid candidate ID.');
                
                $stmt = $db->prepare("DELETE FROM candidates WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                logActivity($user_id, 'candidate_deleted', "Deleted candidate ID: $id");
                $action_result = ['success' => true, 'message' => 'Candidate deleted successfully.'];
                break;
                
            case 'toggle_candidate_status':
                $id = (int)($_POST['id'] ?? 0);
                $status = (int)($_POST['status'] ?? 1);
                if ($id <= 0) throw new Exception('Invalid candidate ID.');
                
                $stmt = $db->prepare("UPDATE candidates SET is_active = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$status, $id, $tenant_id]);
                
                logActivity($user_id, 'candidate_status_toggled', "Toggled candidate ID: $id to " . ($status ? 'active' : 'inactive'));
                $action_result = ['success' => true, 'message' => 'Candidate status updated successfully.'];
                break;
        }
    } catch (PDOException $e) {
        $action_result = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        error_log("Candidate action PDO Error: " . $e->getMessage());
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        error_log("Candidate action Error: " . $e->getMessage());
    }
}

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
// FETCH PARTIES FOR FILTER
// ============================================================
$parties = [];
try {
    $stmt = $db->prepare("SELECT id, name, acronym FROM political_parties WHERE tenant_id = ? ORDER BY name");
    $stmt->execute([$tenant_id]);
    $parties = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH CANDIDATES
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$election_filter = isset($_GET['election']) ? (int)$_GET['election'] : 0;
$party_filter = isset($_GET['party']) ? (int)$_GET['party'] : 0;
$position_filter = isset($_GET['position']) ? trim($_GET['position']) : '';
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : -1;

$where_conditions = ["c.tenant_id = ?"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.full_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($election_filter > 0) {
    $where_conditions[] = "c.election_id = ?";
    $params[] = $election_filter;
}

if ($party_filter > 0) {
    $where_conditions[] = "c.party_id = ?";
    $params[] = $party_filter;
}

if (!empty($position_filter)) {
    $where_conditions[] = "c.position = ?";
    $params[] = $position_filter;
}

if ($status_filter >= 0) {
    $where_conditions[] = "c.is_active = ?";
    $params[] = $status_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM candidates c $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_candidates = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_candidates / $limit);

// Fetch candidates
$sql = "
    SELECT c.*, 
           e.name as election_name, e.type as election_type,
           p.name as party_name, p.acronym as party_acronym, p.logo_url as party_logo
    FROM candidates c
    LEFT JOIN elections e ON c.election_id = e.id
    LEFT JOIN political_parties p ON c.party_id = p.id
    $where_clause
    ORDER BY e.election_date DESC, c.position, c.last_name
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'by_position' => []
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM candidates WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $stats['total'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM candidates WHERE tenant_id = ? AND is_active = 1");
    $stmt->execute([$tenant_id]);
    $stats['active'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM candidates WHERE tenant_id = ? AND is_active = 0");
    $stmt->execute([$tenant_id]);
    $stats['inactive'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT position, COUNT(*) as count FROM candidates WHERE tenant_id = ? GROUP BY position ORDER BY count DESC");
    $stmt->execute([$tenant_id]);
    $stats['by_position'] = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching candidate stats: " . $e->getMessage());
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<!-- All HTML and CSS remain the same as your original file -->
<style>
    /* ============================================================
       CANDIDATES MANAGEMENT - PROFESSIONAL UI STYLES
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
    .btn-sm.purple { background: #F5F3FF; color: #5B21B6; }
    .btn-sm.purple:hover { background: #EDE9FE; }
    
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
    .stat-item .number.red { color: var(--danger); }
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
    .filter-bar .search-wrap {
        flex: 1;
        min-width: 180px;
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
        min-width: 120px;
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
    
    .candidate-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        color: white;
        flex-shrink: 0;
        position: relative;
        overflow: hidden;
    }
    .candidate-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .candidate-avatar .initials {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }
    .candidate-avatar.primary { background: var(--primary); }
    .candidate-avatar.green { background: var(--secondary); }
    .candidate-avatar.purple { background: #8B5CF6; }
    .candidate-avatar.orange { background: #F59E0B; }
    .candidate-avatar.red { background: var(--danger); }
    .candidate-avatar.pink { background: #EC4899; }
    .candidate-avatar.teal { background: #14B8A6; }
    
    .candidate-name {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--gray-800);
    }
    .candidate-position {
        font-size: 0.75rem;
        color: var(--gray-500);
    }
    
    .party-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        background: #F5F3FF;
        color: #5B21B6;
        border: 1px solid #EDE9FE;
    }
    .party-badge .party-color {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
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
    .badge-status.active { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.inactive { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    .badge-status.inactive .dot { background: #EF4444; }
    
    .badge-election {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.6rem;
        font-weight: 500;
        background: #EFF6FF;
        color: #1E40AF;
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
    
    /* Position filter chips */
    .position-chips {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        padding: 4px 0;
    }
    .position-chip {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        background: var(--gray-100);
        color: var(--gray-600);
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        border: 1px solid transparent;
    }
    .position-chip:hover {
        background: var(--gray-200);
    }
    .position-chip.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .position-chip .count {
        opacity: 0.7;
        font-size: 0.6rem;
        margin-left: 4px;
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
        .position-chips { flex-wrap: wrap; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 12px 14px; }
        .stat-item .number { font-size: 1.3rem; }
        .data-table th, .data-table td { padding: 6px 8px; font-size: 0.7rem; }
        .candidate-avatar { width: 32px; height: 32px; font-size: 0.7rem; }
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
                    <i class="fas fa-user-tie" style="color:var(--primary);margin-right:8px;"></i> Candidates
                    <small>Manage candidates for elections</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="candidates-add.php" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Add Candidate
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total Candidates</div>
                <div class="sub-label">All registered</div>
            </div>
            <div class="stat-item">
                <div class="number green"><?php echo number_format($stats['active']); ?></div>
                <div class="label">Active</div>
                <div class="sub-label">Currently active</div>
            </div>
            <div class="stat-item">
                <div class="number red"><?php echo number_format($stats['inactive']); ?></div>
                <div class="label">Inactive</div>
                <div class="sub-label">Suspended or inactive</div>
            </div>
            <div class="stat-item">
                <div class="number purple"><?php echo count($stats['by_position']); ?></div>
                <div class="label">Positions</div>
                <div class="sub-label">Unique positions</div>
            </div>
        </div>

        <!-- Position Chips -->
        <?php if (!empty($stats['by_position'])): ?>
        <div style="margin-bottom:16px;">
            <div class="position-chips">
                <a href="candidates.php<?php echo $election_filter > 0 ? '?election=' . $election_filter : ''; ?>" 
                   class="position-chip <?php echo empty($position_filter) ? 'active' : ''; ?>">
                    All
                </a>
                <?php foreach ($stats['by_position'] as $pos): ?>
                    <a href="candidates.php?position=<?php echo urlencode($pos['position']); ?><?php echo $election_filter > 0 ? '&election=' . $election_filter : ''; ?>" 
                       class="position-chip <?php echo $position_filter == $pos['position'] ? 'active' : ''; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $pos['position'])); ?>
                        <span class="count"><?php echo $pos['count']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search candidates by name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="election">
                    <option value="">All Elections</option>
                    <?php foreach ($elections as $election): ?>
                        <option value="<?php echo $election['id']; ?>" <?php echo $election_filter == $election['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($election['name']); ?>
                            (<?php echo date('M j, Y', strtotime($election['election_date'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="party">
                    <option value="">All Parties</option>
                    <?php foreach ($parties as $party): ?>
                        <option value="<?php echo $party['id']; ?>" <?php echo $party_filter == $party['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($party['name']); ?>
                            (<?php echo htmlspecialchars($party['acronym']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="-1">All Status</option>
                    <option value="1" <?php echo $status_filter == 1 ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo $status_filter == 0 ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || $election_filter > 0 || $party_filter > 0 || $status_filter >= 0 || !empty($position_filter)): ?>
                    <a href="candidates.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Candidates Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Candidates List
                    <span class="count"><?php echo number_format($total_candidates); ?></span>
                </div>
                <div class="table-actions">
                    <span>Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_candidates); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Candidate</th>
                        <th>Position</th>
                        <th>Party</th>
                        <th>Election</th>
                        <th>Status</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($candidates) > 0): ?>
                        <?php 
                        $colors = ['primary', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
                        foreach ($candidates as $index => $candidate): 
                            $color = $colors[$index % count($colors)];
                            $initials = strtoupper(substr($candidate['first_name'], 0, 1) . substr($candidate['last_name'], 0, 1));
                            $photo = !empty($candidate['photograph_url']) ? $candidate['photograph_url'] : '';
                        ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div class="candidate-avatar <?php echo $color; ?>">
                                            <?php if (!empty($photo)): ?>
                                                <img src="<?php echo htmlspecialchars($photo); ?>" alt="<?php echo htmlspecialchars($candidate['full_name']); ?>">
                                            <?php else: ?>
                                                <span class="initials"><?php echo $initials; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="candidate-name"><?php echo htmlspecialchars($candidate['full_name']); ?></div>
                                            <div class="candidate-position">
                                                <i class="fas fa-tag" style="font-size:0.6rem;"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $candidate['position'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:0.8rem;">
                                        <?php echo ucfirst(str_replace('_', ' ', $candidate['position'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($candidate['party_name'])): ?>
                                        <span class="party-badge">
                                            <?php if (!empty($candidate['party_logo'])): ?>
                                                <img src="<?php echo htmlspecialchars($candidate['party_logo']); ?>" style="width:16px;height:16px;border-radius:50%;object-fit:cover;">
                                            <?php else: ?>
                                                <span class="party-color" style="background:<?php echo substr(md5($candidate['party_name']), 0, 6); ?>;"></span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($candidate['party_acronym'] ?? $candidate['party_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size:0.7rem;color:var(--gray-400);">No party</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-election">
                                        <?php echo htmlspecialchars($candidate['election_name'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $candidate['is_active'] ? 'active' : 'inactive'; ?>">
                                        <span class="dot"></span>
                                        <?php echo $candidate['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <a href="candidates-edit.php?id=<?php echo $candidate['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="candidates-details.php?id=<?php echo $candidate['id']; ?>">
                                                <i class="fas fa-info-circle"></i> Details
                                            </a>
                                            <?php if ($candidate['is_active']): ?>
                                                <button onclick="toggleCandidateStatus(<?php echo $candidate['id']; ?>, 0)">
                                                    <i class="fas fa-pause-circle"></i> Suspend
                                                </button>
                                            <?php else: ?>
                                                <button onclick="toggleCandidateStatus(<?php echo $candidate['id']; ?>, 1)">
                                                    <i class="fas fa-play-circle"></i> Activate
                                                </button>
                                            <?php endif; ?>
                                            <div class="divider"></div>
                                            <button class="danger" onclick="deleteCandidate(<?php echo $candidate['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-user-tie"></i>
                                    <h4>No candidates found</h4>
                                    <p>Add candidates to start building your election roster.</p>
                                    <a href="candidates-add.php" class="btn-primary" style="margin-top:12px;text-decoration:none;">
                                        <i class="fas fa-plus-circle"></i> Add Candidate
                                    </a>
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
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_candidates); ?></strong> of <strong><?php echo number_format($total_candidates); ?></strong> candidates
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&election=<?php echo $election_filter; ?>&party=<?php echo $party_filter; ?>&position=<?php echo urlencode($position_filter); ?>&status=<?php echo $status_filter; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&election=' . $election_filter . '&party=' . $party_filter . '&position=' . urlencode($position_filter) . '&status=' . $status_filter . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&election=<?php echo $election_filter; ?>&party=<?php echo $party_filter; ?>&position=<?php echo urlencode($position_filter); ?>&status=<?php echo $status_filter; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&election=' . $election_filter . '&party=' . $party_filter . '&position=' . urlencode($position_filter) . '&status=' . $status_filter . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&election=<?php echo $election_filter; ?>&party=<?php echo $party_filter; ?>&position=<?php echo urlencode($position_filter); ?>&status=<?php echo $status_filter; ?>">
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
// CANDIDATE FUNCTIONS
// ============================================================
function viewCandidateDetails(id) {
    // Redirect to the details page
    window.location.href = 'candidates-details.php?id=' + id;
}

function toggleCandidateStatus(id, status) {
    var action = status ? 'activate' : 'suspend';
    if (confirm('Are you sure you want to ' + action + ' this candidate?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="toggle_candidate_status"><input type="hidden" name="id" value="' + id + '"><input type="hidden" name="status" value="' + status + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteCandidate(id) {
    if (confirm('Delete this candidate? This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_candidate"><input type="hidden" name="id" value="' + id + '">';
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