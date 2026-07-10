<?php
// ============================================================
// STATE COORDINATOR - ELECTIONS (PRO VERSION)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

// If state_id is not set in session, try to get it from user record
if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// GENERATE CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// GET FILTERS
// ============================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// ============================================================
// FETCH STATE NAME
// ============================================================
$state_name = 'Unknown State';
try {
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        $state_name = $state['name'] ?? 'Unknown State';
    }
} catch (Exception $e) {
    error_log("Error fetching state: " . $e->getMessage());
}

// ============================================================
// HANDLE QUICK ACTIONS
// ============================================================
$action_error = '';
$action_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $action_error = 'Security validation failed.';
    } else {
        $election_id = (int)($_POST['election_id'] ?? 0);
        $action = $_POST['quick_action'] ?? '';
        
        try {
            if ($action === 'activate') {
                $stmt = $db->prepare("UPDATE elections SET status = 'active', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$election_id, $tenant_id]);
                $action_success = "Election activated successfully!";
                logActivity($user_id, 'election_status_changed', "Changed election ID: $election_id status to active");
            } elseif ($action === 'close') {
                $stmt = $db->prepare("UPDATE elections SET status = 'closed', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$election_id, $tenant_id]);
                $action_success = "Election closed successfully!";
                logActivity($user_id, 'election_status_changed', "Changed election ID: $election_id status to closed");
            } elseif ($action === 'archive') {
                $stmt = $db->prepare("UPDATE elections SET status = 'archived', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$election_id, $tenant_id]);
                $action_success = "Election archived successfully!";
                logActivity($user_id, 'election_archived', "Archived election ID: $election_id");
            } elseif ($action === 'delete') {
                $stmt = $db->prepare("UPDATE elections SET deleted_at = NOW() WHERE id = ? AND tenant_id = ? AND status = 'draft'");
                $stmt->execute([$election_id, $tenant_id]);
                $action_success = "Election deleted successfully!";
                logActivity($user_id, 'election_deleted', "Deleted election ID: $election_id");
            }
        } catch (Exception $e) {
            $action_error = 'Error performing action: ' . $e->getMessage();
        }
    }
}

// ============================================================
// FETCH ELECTIONS
// ============================================================
$elections = [];
$total_elections = 0;
$total_pages = 0;

try {
    $sql = "
        SELECT 
            e.*,
            u.first_name as created_by_first,
            u.last_name as created_by_last,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.election_id = e.id AND r.status = 'pending') as pending_results,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.election_id = e.id AND r.status = 'verified') as verified_results,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.election_id = e.id) as total_results,
            (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id) as total_candidates,
            (SELECT COUNT(*) FROM agent_assignments a WHERE a.election_id = e.id) as total_assignments
        FROM elections e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.tenant_id = ?
        AND e.deleted_at IS NULL
    ";
    
    $params = [$tenant_id];
    
    // Filter by state
    if (!empty($state_id)) {
        $sql .= " AND (e.states_json LIKE ? OR e.states_json IS NULL OR e.states_json = '[]')";
        $params[] = '%"' . $state_id . '"%';
    }
    
    if (!empty($search)) {
        $sql .= " AND (e.name LIKE ? OR e.type LIKE ? OR e.cycle LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND e.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($type_filter)) {
        $sql .= " AND e.type = ?";
        $params[] = $type_filter;
    }
    
    // Count total
    $count_sql = str_replace(
        "SELECT 
            e.*,
            u.first_name as created_by_first,
            u.last_name as created_by_last,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.election_id = e.id AND r.status = 'pending') as pending_results,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.election_id = e.id AND r.status = 'verified') as verified_results,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.election_id = e.id) as total_results,
            (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id) as total_candidates,
            (SELECT COUNT(*) FROM agent_assignments a WHERE a.election_id = e.id) as total_assignments",
        "SELECT COUNT(*) as count",
        $sql
    );
    
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_elections = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    $total_pages = ceil($total_elections / $per_page);
    
    // Get data
    $sql .= " ORDER BY e.election_date DESC, e.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

// ============================================================
// ELECTION TYPES AND STATUSES
// ============================================================
$election_types = [
    'presidential' => 'Presidential',
    'governorship' => 'Governorship',
    'senatorial' => 'Senatorial',
    'house_of_reps' => 'House of Reps',
    'house_of_assembly' => 'House of Assembly',
    'lga_chairman' => 'LGA Chairman',
    'councillorship' => 'Councillorship',
    'party_primary' => 'Party Primary',
    'internal_party' => 'Internal Party'
];

$status_colors = [
    'draft' => 'secondary',
    'upcoming' => 'warning',
    'active' => 'success',
    'closed' => 'danger',
    'cancelled' => 'danger',
    'archived' => 'secondary'
];

$status_icons = [
    'draft' => 'fa-pencil-alt',
    'upcoming' => 'fa-clock',
    'active' => 'fa-play',
    'closed' => 'fa-stop',
    'cancelled' => 'fa-ban',
    'archived' => 'fa-archive'
];

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
/* ============================================================
   PRO STYLES - ELECTIONS
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
    margin: 0;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

/* Stats Cards */
.stats-grid-pro {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-card-pro {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}
.stat-card-pro:hover {
    box-shadow: var(--shadow-hover);
    transform: translateY(-2px);
}
.stat-card-pro .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: white;
    margin-bottom: 6px;
}
.stat-card-pro .stat-icon.blue { background: #3B82F6; }
.stat-card-pro .stat-icon.green { background: #10B981; }
.stat-card-pro .stat-icon.yellow { background: #F59E0B; }
.stat-card-pro .stat-icon.red { background: #EF4444; }
.stat-card-pro .stat-icon.purple { background: #8B5CF6; }
.stat-card-pro .stat-icon.teal { background: #0D9488; }

.stat-card-pro .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1.2;
}
.stat-card-pro .stat-label {
    font-size: 0.7rem;
    color: var(--gray-500);
    font-weight: 500;
}
.stat-card-pro .stat-sub {
    font-size: 0.6rem;
    color: var(--gray-400);
    margin-top: 2px;
}

/* Button Styles */
.btn-primary-sm {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    cursor: pointer;
}
.btn-primary-sm:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary-sm {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-secondary-sm:hover {
    background: var(--gray-200);
}

.btn-success-sm {
    padding: 8px 20px;
    background: #10B981;
    color: white;
    border: none;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    cursor: pointer;
}
.btn-success-sm:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-danger-sm {
    padding: 8px 20px;
    background: #EF4444;
    color: white;
    border: none;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    cursor: pointer;
}
.btn-danger-sm:hover {
    background: #DC2626;
    transform: translateY(-1px);
}

/* Filter Bar */
.filter-bar-pro {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 16px;
    background: white;
    padding: 14px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow-sm);
}
.filter-bar-pro .search-box {
    flex: 1;
    min-width: 180px;
    display: flex;
    gap: 8px;
}
.filter-bar-pro .search-box input {
    flex: 1;
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
}
.filter-bar-pro .search-box input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}
.filter-bar-pro select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    background: white;
    cursor: pointer;
}
.filter-bar-pro select:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-bar-pro .btn-filter {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
}
.filter-bar-pro .btn-filter:hover {
    background: var(--primary-dark);
}
.filter-bar-pro .btn-reset {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
}
.filter-bar-pro .btn-reset:hover {
    background: var(--gray-200);
}

/* Table */
.table-wrapper-pro {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.table-wrapper-pro table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table-wrapper-pro table th {
    background: var(--gray-50);
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 0.72rem;
    color: var(--gray-600);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.table-wrapper-pro table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table-wrapper-pro table tr:hover td {
    background: var(--gray-50);
}
.table-wrapper-pro table tr:last-child td {
    border-bottom: none;
}

/* Badge Status */
.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 600;
}
.badge-status .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.badge-status.success { background: #ECFDF5; color: #065F46; }
.badge-status.success .dot { background: #10B981; }
.badge-status.warning { background: #FFFBEB; color: #92400E; }
.badge-status.warning .dot { background: #F59E0B; }
.badge-status.danger { background: #FEF2F2; color: #991B1B; }
.badge-status.danger .dot { background: #EF4444; }
.badge-status.secondary { background: #F3F4F6; color: #6B7280; }
.badge-status.secondary .dot { background: #9CA3AF; }

/* Action Buttons */
.btn-action {
    padding: 4px 10px;
    border-radius: 6px;
    border: none;
    font-size: 0.65rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
}
.btn-action.btn-view {
    background: #EFF6FF;
    color: #3B82F6;
}
.btn-action.btn-view:hover {
    background: #DBEAFE;
}
.btn-action.btn-results {
    background: #ECFDF5;
    color: #10B981;
}
.btn-action.btn-results:hover {
    background: #D1FAE5;
}
.btn-action.btn-edit {
    background: #F5F3FF;
    color: #8B5CF6;
}
.btn-action.btn-edit:hover {
    background: #EDE9FE;
}
.btn-action.btn-more {
    background: var(--gray-100);
    color: var(--gray-600);
}
.btn-action.btn-more:hover {
    background: var(--gray-200);
}

/* Dropdown Menu */
.dropdown-menu-pro {
    position: relative;
    display: inline-block;
}
.dropdown-menu-pro .dropdown-toggle {
    padding: 4px 10px;
    border-radius: 6px;
    border: none;
    font-size: 0.65rem;
    font-weight: 600;
    cursor: pointer;
    background: var(--gray-100);
    color: var(--gray-600);
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.dropdown-menu-pro .dropdown-toggle:hover {
    background: var(--gray-200);
}
.dropdown-menu-pro .dropdown-items {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    min-width: 180px;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    z-index: 100;
    padding: 4px 0;
    margin-top: 4px;
}
.dropdown-menu-pro .dropdown-items.show {
    display: block;
}
.dropdown-menu-pro .dropdown-items button {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    font-size: 0.78rem;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    transition: var(--transition);
    color: var(--gray-700);
}
.dropdown-menu-pro .dropdown-items button:hover {
    background: var(--gray-50);
}
.dropdown-menu-pro .dropdown-items button .text-danger { color: #EF4444; }
.dropdown-menu-pro .dropdown-items button .text-success { color: #10B981; }
.dropdown-menu-pro .dropdown-items button .text-warning { color: #F59E0B; }
.dropdown-menu-pro .dropdown-items hr {
    margin: 4px 0;
    border: none;
    border-top: 1px solid var(--gray-200);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    padding: 16px 20px;
    border-top: 1px solid var(--gray-200);
}
.pagination .page-btn {
    padding: 6px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.pagination .page-btn:hover {
    background: var(--gray-50);
    border-color: var(--gray-300);
}
.pagination .page-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .page-btn.disabled {
    opacity: 0.5;
    pointer-events: none;
}
.pagination .page-info {
    font-size: 0.75rem;
    color: var(--gray-500);
}

/* Empty State */
.empty-state-pro {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-400);
}
.empty-state-pro i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}
.empty-state-pro p {
    margin: 0;
}
.empty-state-pro .sub-text {
    font-size: 0.8rem;
    color: var(--gray-400);
    margin-top: 4px;
}

/* Message Alerts */
.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}
.alert-danger {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
}
.alert i {
    margin-top: 2px;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .table-wrapper-pro {
        overflow-x: auto;
    }
    .filter-bar-pro {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar-pro .search-box {
        flex-direction: column;
    }
    .stats-grid-pro {
        grid-template-columns: repeat(2, 1fr);
    }
    .dropdown-menu-pro .dropdown-items {
        left: 0;
        right: auto;
    }
}

@media (max-width: 480px) {
    .stats-grid-pro {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .stat-card-pro {
        padding: 12px 14px;
    }
    .stat-card-pro .stat-number {
        font-size: 1.2rem;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-vote-yea" style="color:var(--primary);margin-right:8px;"></i>
                    Elections
                    <small><?php echo htmlspecialchars($state_name); ?> - Manage elections in your state</small>
                </h2>
            </div>
            <div>
                <a href="election-create.php" class="btn-primary-sm">
                    <i class="fas fa-plus"></i> Create Election
                </a>
                <a href="index.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($action_success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $action_success; ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($action_error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $action_error; ?></div>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <?php
        $total_draft = 0;
        $total_upcoming = 0;
        $total_active = 0;
        $total_closed = 0;
        $total_archived = 0;
        foreach ($elections as $e) {
            if ($e['status'] === 'draft') $total_draft++;
            elseif ($e['status'] === 'upcoming') $total_upcoming++;
            elseif ($e['status'] === 'active') $total_active++;
            elseif ($e['status'] === 'closed') $total_closed++;
            elseif ($e['status'] === 'archived') $total_archived++;
        }
        ?>
        <div class="stats-grid-pro">
            <div class="stat-card-pro">
                <div class="stat-icon blue"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($total_elections); ?></div>
                <div class="stat-label">Total Elections</div>
            </div>
            <div class="stat-card-pro">
                <div class="stat-icon green"><i class="fas fa-play"></i></div>
                <div class="stat-number" style="color:#10B981;"><?php echo $total_active; ?></div>
                <div class="stat-label">Active</div>
                <div class="stat-sub"><?php echo $total_upcoming; ?> upcoming</div>
            </div>
            <div class="stat-card-pro">
                <div class="stat-icon yellow"><i class="fas fa-pencil-alt"></i></div>
                <div class="stat-number" style="color:#F59E0B;"><?php echo $total_draft; ?></div>
                <div class="stat-label">Drafts</div>
                <div class="stat-sub">Pending review</div>
            </div>
            <div class="stat-card-pro">
                <div class="stat-icon red"><i class="fas fa-stop"></i></div>
                <div class="stat-number" style="color:#6B7280;"><?php echo $total_closed + $total_archived; ?></div>
                <div class="stat-label">Closed / Archived</div>
                <div class="stat-sub"><?php echo $total_closed; ?> closed, <?php echo $total_archived; ?> archived</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="filter-bar-pro">
            <div class="search-box">
                <input type="text" name="search" placeholder="Search elections..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
            </div>
            <select name="status">
                <option value="">All Status</option>
                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
            </select>
            <select name="type">
                <option value="">All Types</option>
                <?php foreach ($election_types as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $type_filter === $key ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="elections.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>

        <!-- Table -->
        <div class="table-wrapper-pro">
            <?php if (count($elections) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Election</th>
                            <th>Type</th>
                            <th>Cycle</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = $offset + 1; ?>
                        <?php foreach ($elections as $election): 
                            $total_pu_results = ($election['verified_results'] ?? 0) + ($election['pending_results'] ?? 0);
                            $progress_percent = $election['total_results'] > 0 ? round(($election['verified_results'] / $election['total_results']) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td>
                                    <div style="font-weight:600;color:var(--gray-800);font-size:0.85rem;"><?php echo htmlspecialchars($election['name']); ?></div>
                                    <div style="font-size:0.6rem;color:var(--gray-400);">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($election['created_by_first'] ?? '') . ' ' . htmlspecialchars($election['created_by_last'] ?? ''); ?>
                                        <span style="margin-left:8px;">
                                            <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($election['created_at'])); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;font-weight:500;color:var(--gray-600);">
                                        <?php echo $election_types[$election['type']] ?? ucfirst($election['type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;font-weight:600;color:var(--gray-700);">
                                        <?php echo htmlspecialchars($election['cycle'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:0.78rem;font-weight:500;"><?php echo date('M j, Y', strtotime($election['election_date'])); ?></div>
                                    <?php if ($election['start_time']): ?>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($election['start_time'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $status_colors[$election['status']] ?? 'secondary'; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($election['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div style="flex:1;min-width:80px;">
                                            <div style="height:6px;background:var(--gray-200);border-radius:3px;overflow:hidden;">
                                                <div style="height:100%;border-radius:3px;background:<?php echo $progress_percent >= 80 ? '#10B981' : ($progress_percent >= 50 ? '#F59E0B' : '#EF4444'); ?>;width:<?php echo $progress_percent; ?>%;"></div>
                                            </div>
                                        </div>
                                        <span style="font-size:0.65rem;font-weight:600;min-width:35px;color:var(--gray-600);">
                                            <?php echo $progress_percent; ?>%
                                        </span>
                                    </div>
                                    <div style="font-size:0.6rem;color:var(--gray-400);margin-top:2px;">
                                        <span style="color:#10B981;"><?php echo number_format($election['verified_results'] ?? 0); ?></span>
                                        <span style="color:#F59E0B;"><?php echo number_format($election['pending_results'] ?? 0); ?></span>
                                        <span style="color:var(--gray-400);"><?php echo number_format($election['total_results'] ?? 0); ?></span>
                                        <span style="margin-left:4px;">| <?php echo number_format($election['total_candidates'] ?? 0); ?> candidates</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                        <a href="election-view.php?id=<?php echo $election['id']; ?>" class="btn-action btn-view" title="View Election">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="election-results.php?id=<?php echo $election['id']; ?>" class="btn-action btn-results" title="View Results">
                                            <i class="fas fa-chart-bar"></i>
                                        </a>
                                        <?php if ($election['status'] === 'draft'): ?>
                                            <a href="election-edit.php?id=<?php echo $election['id']; ?>" class="btn-action btn-edit" title="Edit Election">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Dropdown Menu -->
                                        <div class="dropdown-menu-pro">
                                            <button class="dropdown-toggle" onclick="toggleDropdown(this)" title="More Actions">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-items">
                                                <?php if ($election['status'] === 'draft'): ?>
                                                    <button onclick="quickAction('activate', <?php echo $election['id']; ?>)" class="text-success">
                                                        <i class="fas fa-play"></i> Activate Election
                                                    </button>
                                                    <button onclick="quickAction('delete', <?php echo $election['id']; ?>)" class="text-danger">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($election['status'] === 'active'): ?>
                                                    <button onclick="quickAction('close', <?php echo $election['id']; ?>)" class="text-warning">
                                                        <i class="fas fa-stop"></i> Close Election
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($election['status'] === 'closed'): ?>
                                                    <button onclick="quickAction('archive', <?php echo $election['id']; ?>)" class="text-secondary">
                                                        <i class="fas fa-archive"></i> Archive
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($election['status'] === 'upcoming'): ?>
                                                    <button onclick="quickAction('activate', <?php echo $election['id']; ?>)" class="text-success">
                                                        <i class="fas fa-play"></i> Activate Early
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <hr>
                                                <a href="election-view.php?id=<?php echo $election['id']; ?>" style="display:flex;align-items:center;gap:8px;padding:8px 16px;text-decoration:none;color:var(--gray-700);font-size:0.78rem;">
                                                    <i class="fas fa-info-circle"></i> Details
                                                </a>
                                                <a href="election-results.php?id=<?php echo $election['id']; ?>" style="display:flex;align-items:center;gap:8px;padding:8px 16px;text-decoration:none;color:var(--gray-700);font-size:0.78rem;">
                                                    <i class="fas fa-chart-pie"></i> Results
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        ?>
                        
                        <?php if ($start_page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="page-btn disabled">…</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="page-btn disabled">…</span>
                            <?php endif; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-btn">
                                <?php echo $total_pages; ?>
                            </a>
                        <?php endif; ?>
                        
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <span class="page-info">
                            <?php echo number_format($total_elections); ?> elections total
                        </span>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-state-pro">
                    <i class="fas fa-vote-yea"></i>
                    <p>No elections found.</p>
                    <?php if (!empty($search) || !empty($status_filter) || !empty($type_filter)): ?>
                        <p class="sub-text">Try adjusting your filters.</p>
                    <?php else: ?>
                        <p class="sub-text">
                            <a href="election-create.php" style="color:var(--primary);text-decoration:none;font-weight:600;">
                                Create an election
                            </a> to get started.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- ============================================================
QUICK ACTION FORM
============================================================ -->
<form method="POST" action="" id="quickActionForm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="quick_action" id="quickAction" value="">
    <input type="hidden" name="election_id" id="quickActionElectionId" value="">
</form>

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
// DROPDOWN MENU TOGGLE
// ============================================================
function toggleDropdown(button) {
    var dropdown = button.closest('.dropdown-menu-pro');
    var items = dropdown.querySelector('.dropdown-items');
    
    // Close all other dropdowns
    document.querySelectorAll('.dropdown-items.show').forEach(function(el) {
        if (el !== items) {
            el.classList.remove('show');
        }
    });
    
    items.classList.toggle('show');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown-menu-pro')) {
        document.querySelectorAll('.dropdown-items.show').forEach(function(el) {
            el.classList.remove('show');
        });
    }
});

// ============================================================
// QUICK ACTIONS
// ============================================================
function quickAction(action, electionId) {
    var confirmMessages = {
        'activate': 'Are you sure you want to activate this election?',
        'close': 'Are you sure you want to close this election?',
        'archive': 'Are you sure you want to archive this election?',
        'delete': 'Are you sure you want to delete this election? This action cannot be undone.'
    };
    
    if (confirm(confirmMessages[action] || 'Are you sure?')) {
        document.getElementById('quickAction').value = action;
        document.getElementById('quickActionElectionId').value = electionId;
        document.getElementById('quickActionForm').submit();
    }
}
</script>
</body>
</html>