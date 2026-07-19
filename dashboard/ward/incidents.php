<?php
// ============================================================
// WARD COORDINATOR - INCIDENT MANAGEMENT
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

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$ward_id = SessionManager::get('ward_id');
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
if (empty($ward_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT ward_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            SessionManager::set('ward_id', $ward_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// FETCH WARD NAME
// ============================================================
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// HANDLE INCIDENT ACTIONS
// ============================================================
$action_message = '';
$action_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    if ($incident_id > 0 && !empty($action)) {
        try {
            switch ($action) {
                case 'resolve':
                    $stmt = $db->prepare("
                        UPDATE incidents 
                        SET status = 'resolved', resolved_by = ?, resolved_at = NOW(), 
                            resolution_notes = CONCAT(COALESCE(resolution_notes, ''), '\n', ?), updated_at = NOW()
                        WHERE id = ? AND tenant_id = ? AND ward_id = ?
                    ");
                    $stmt->execute([$user_id, $notes, $incident_id, $tenant_id, $ward_id]);
                    if ($stmt->rowCount() > 0) {
                        $action_message = "Incident resolved successfully.";
                        $action_type = 'success';
                        logActivity($user_id, 'incident_resolved', "Resolved incident ID: $incident_id", 'incidents', $incident_id);
                    }
                    break;
                    
                case 'escalate':
                    $stmt = $db->prepare("
                        UPDATE incidents 
                        SET status = 'escalated', updated_at = NOW() 
                        WHERE id = ? AND tenant_id = ? AND ward_id = ?
                    ");
                    $stmt->execute([$incident_id, $tenant_id, $ward_id]);
                    if ($stmt->rowCount() > 0) {
                        $action_message = "Incident escalated successfully.";
                        $action_type = 'success';
                        logActivity($user_id, 'incident_escalated', "Escalated incident ID: $incident_id", 'incidents', $incident_id);
                    }
                    break;
                    
                case 'close':
                    $stmt = $db->prepare("
                        UPDATE incidents 
                        SET status = 'closed', updated_at = NOW() 
                        WHERE id = ? AND tenant_id = ? AND ward_id = ?
                    ");
                    $stmt->execute([$incident_id, $tenant_id, $ward_id]);
                    if ($stmt->rowCount() > 0) {
                        $action_message = "Incident closed successfully.";
                        $action_type = 'success';
                        logActivity($user_id, 'incident_closed', "Closed incident ID: $incident_id", 'incidents', $incident_id);
                    }
                    break;
                    
                case 'reopen':
                    $stmt = $db->prepare("
                        UPDATE incidents 
                        SET status = 'investigating', updated_at = NOW() 
                        WHERE id = ? AND tenant_id = ? AND ward_id = ?
                    ");
                    $stmt->execute([$incident_id, $tenant_id, $ward_id]);
                    if ($stmt->rowCount() > 0) {
                        $action_message = "Incident reopened successfully.";
                        $action_type = 'success';
                        logActivity($user_id, 'incident_reopened', "Reopened incident ID: $incident_id", 'incidents', $incident_id);
                    }
                    break;
                    
                default:
                    $action_message = "Invalid action.";
                    $action_type = 'error';
            }
        } catch (Exception $e) {
            $action_message = "Error performing action: " . $e->getMessage();
            $action_type = 'error';
            error_log("Incident action error: " . $e->getMessage());
        }
    }
}

// ============================================================
// FETCH INCIDENTS WITH FILTERS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : 'all';

$incidents = [];
$total_incidents = 0;

try {
    // Build query conditions
    $conditions = "i.tenant_id = ? AND i.ward_id = ?";
    $params = [$tenant_id, $ward_id];
    
    if (!empty($search)) {
        $conditions .= " AND (i.title LIKE ? OR i.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($status_filter !== 'all') {
        $conditions .= " AND i.status = ?";
        $params[] = $status_filter;
    }
    
    if ($type_filter !== 'all') {
        $conditions .= " AND i.incident_type = ?";
        $params[] = $type_filter;
    }
    
    if ($severity_filter !== 'all') {
        $conditions .= " AND i.severity = ?";
        $params[] = $severity_filter;
    }
    
    // Get total count
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM incidents i WHERE $conditions");
    $count_stmt->execute($params);
    $total_incidents = (int)$count_stmt->fetchColumn();
    
    // Get incidents
    $stmt = $db->prepare("
        SELECT 
            i.*,
            u.full_name as reporter_name,
            u.phone as reporter_phone,
            u.email as reporter_email,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            resolved_user.full_name as resolved_by_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON i.ward_id = w.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        LEFT JOIN states s ON i.state_id = s.id
        LEFT JOIN users resolved_user ON i.resolved_by = resolved_user.id
        WHERE $conditions
        ORDER BY i.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching incidents: " . $e->getMessage());
}

// ============================================================
// FETCH INCIDENT STATISTICS
// ============================================================
$incident_stats = [
    'total' => 0,
    'reported' => 0,
    'investigating' => 0,
    'resolved' => 0,
    'closed' => 0,
    'escalated' => 0,
    'panic' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported,
            SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
            SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated,
            SUM(CASE WHEN is_panic = 1 THEN 1 ELSE 0 END) as panic
        FROM incidents 
        WHERE tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $incident_stats['total'] = (int)($stats['total'] ?? 0);
    $incident_stats['reported'] = (int)($stats['reported'] ?? 0);
    $incident_stats['investigating'] = (int)($stats['investigating'] ?? 0);
    $incident_stats['resolved'] = (int)($stats['resolved'] ?? 0);
    $incident_stats['closed'] = (int)($stats['closed'] ?? 0);
    $incident_stats['escalated'] = (int)($stats['escalated'] ?? 0);
    $incident_stats['panic'] = (int)($stats['panic'] ?? 0);
    
} catch (Exception $e) {
    error_log("Error fetching incident stats: " . $e->getMessage());
}

$page_title = 'Incident Management';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.incident-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.incident-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.incident-header h2 i {
    color: #EF4444;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.stat-mini {
    background: white;
    padding: 10px 14px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-mini .number {
    font-size: 1.2rem;
    font-weight: 700;
}
.stat-mini .number.red { color: #EF4444; }
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .number.yellow { color: #F59E0B; }
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.purple { color: #8B5CF6; }
.stat-mini .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    font-weight: 500;
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
}
.filter-bar .search-box {
    flex: 1;
    min-width: 180px;
    position: relative;
}
.filter-bar .search-box input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.filter-bar .search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
}
.filter-bar select {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    min-width: 120px;
}

.incident-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 12px;
    transition: var(--transition);
}
.incident-card:hover {
    box-shadow: var(--shadow-hover);
}
.incident-card .incident-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}
.incident-card .incident-title {
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.incident-card .incident-title .panic {
    color: #EF4444;
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}
.incident-card .incident-badges {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.incident-card .badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.incident-card .badge.reported { background: #FEF3C7; color: #92400E; }
.incident-card .badge.investigating { background: #DBEAFE; color: #1E40AF; }
.incident-card .badge.resolved { background: #D1FAE5; color: #065F46; }
.incident-card .badge.closed { background: #E5E7EB; color: #374151; }
.incident-card .badge.escalated { background: #FEE2E2; color: #991B1B; }
.incident-card .badge.low { background: #E5E7EB; color: #374151; }
.incident-card .badge.medium { background: #FEF3C7; color: #92400E; }
.incident-card .badge.high { background: #FEE2E2; color: #991B1B; }
.incident-card .badge.critical { background: #7F1D1D; color: white; }

.incident-card .incident-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 16px;
    font-size: 0.78rem;
    color: var(--gray-600);
    margin: 8px 0;
}
.incident-card .incident-details .item i {
    width: 16px;
    font-size: 0.6rem;
    color: var(--gray-400);
}
.incident-card .incident-description {
    font-size: 0.82rem;
    color: var(--gray-600);
    margin: 8px 0;
    padding: 8px 12px;
    background: var(--gray-50);
    border-radius: 6px;
}
.incident-card .incident-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--gray-100);
}
.incident-card .incident-actions .btn-sm {
    padding: 4px 12px;
    font-size: 0.7rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.incident-card .incident-actions .btn-sm.view { background: #EFF6FF; color: #3B82F6; }
.incident-card .incident-actions .btn-sm.resolve { background: #D1FAE5; color: #065F46; }
.incident-card .incident-actions .btn-sm.escalate { background: #FEE2E2; color: #991B1B; }
.incident-card .incident-actions .btn-sm.close { background: #E5E7EB; color: #374151; }
.incident-card .incident-actions .btn-sm.reopen { background: #FEF3C7; color: #92400E; }

.pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    padding: 16px 0;
}
.pagination a, .pagination span {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.8rem;
    text-decoration: none;
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
}
.pagination a:hover {
    background: var(--gray-50);
    border-color: var(--gray-300);
}
.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .disabled {
    opacity: 0.5;
    pointer-events: none;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 4rem;
    color: var(--gray-300);
    margin-bottom: 16px;
}
.empty-state h4 {
    margin: 0 0 8px;
    color: var(--gray-700);
}
.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    justify-content: center;
    align-items: center;
}
.modal-overlay.active {
    display: flex;
}
.modal-content {
    background: white;
    border-radius: var(--radius);
    padding: 24px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}
.modal-content h3 {
    margin: 0 0 16px;
}
.modal-content .form-group {
    margin-bottom: 12px;
}
.modal-content .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 4px;
}
.modal-content .form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    resize: vertical;
    min-height: 80px;
}
.modal-content .form-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}

@media (max-width: 768px) {
    .incident-card .incident-details {
        grid-template-columns: 1fr;
    }
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .search-box {
        min-width: unset;
    }
    .incident-card .incident-top {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="incident-header">
            <div>
                <h2><i class="fas fa-exclamation-triangle"></i> Incident Management</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • <?php echo number_format($incident_stats['total']); ?> incidents
                </p>
            </div>
            <div>
                <a href="incident-create.php" class="btn-primary-sm">
                    <i class="fas fa-plus"></i> Report Incident
                </a>
                <a href="incident-dashboard.php" class="btn-secondary-sm">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Action Message -->
        <?php if (!empty($action_message)): ?>
            <div class="alert alert-<?php echo $action_type === 'success' ? 'success' : 'danger'; ?>" style="margin-bottom:16px;">
                <i class="fas fa-<?php echo $action_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($incident_stats['total']); ?></div>
                <div class="label">Total Incidents</div>
            </div>
            <div class="stat-mini">
                <div class="number red"><?php echo number_format($incident_stats['reported']); ?></div>
                <div class="label">Reported</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($incident_stats['investigating']); ?></div>
                <div class="label">Investigating</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($incident_stats['resolved']); ?></div>
                <div class="label">Resolved</div>
            </div>
            <div class="stat-mini">
                <div class="number purple"><?php echo number_format($incident_stats['escalated']); ?></div>
                <div class="label">Escalated</div>
            </div>
            <div class="stat-mini">
                <div class="number red"><?php echo number_format($incident_stats['panic']); ?></div>
                <div class="label">Panic Alerts</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search incidents..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select id="statusFilter">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="reported" <?php echo $status_filter === 'reported' ? 'selected' : ''; ?>>Reported</option>
                <option value="investigating" <?php echo $status_filter === 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                <option value="escalated" <?php echo $status_filter === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
            </select>
            <select id="typeFilter">
                <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                <option value="violence" <?php echo $type_filter === 'violence' ? 'selected' : ''; ?>>Violence</option>
                <option value="intimidation" <?php echo $type_filter === 'intimidation' ? 'selected' : ''; ?>>Intimidation</option>
                <option value="ballot_stuffing" <?php echo $type_filter === 'ballot_stuffing' ? 'selected' : ''; ?>>Ballot Stuffing</option>
                <option value="vote_buying" <?php echo $type_filter === 'vote_buying' ? 'selected' : ''; ?>>Vote Buying</option>
                <option value="voter_suppression" <?php echo $type_filter === 'voter_suppression' ? 'selected' : ''; ?>>Voter Suppression</option>
                <option value="material_shortage" <?php echo $type_filter === 'material_shortage' ? 'selected' : ''; ?>>Material Shortage</option>
                <option value="technical_issue" <?php echo $type_filter === 'technical_issue' ? 'selected' : ''; ?>>Technical Issue</option>
                <option value="panic_button" <?php echo $type_filter === 'panic_button' ? 'selected' : ''; ?>>Panic Button</option>
            </select>
            <select id="severityFilter">
                <option value="all" <?php echo $severity_filter === 'all' ? 'selected' : ''; ?>>All Severity</option>
                <option value="low" <?php echo $severity_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                <option value="medium" <?php echo $severity_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="high" <?php echo $severity_filter === 'high' ? 'selected' : ''; ?>>High</option>
                <option value="critical" <?php echo $severity_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
            </select>
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button onclick="resetFilters()" class="btn-secondary-sm" style="background: var(--gray-100);">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Incident List -->
        <?php if (count($incidents) > 0): ?>
            <?php foreach ($incidents as $incident): 
                $is_panic = (int)($incident['is_panic'] ?? 0) === 1;
                $severity = $incident['severity'] ?? 'medium';
            ?>
                <div class="incident-card">
                    <div class="incident-top">
                        <div class="incident-title">
                            <?php if ($is_panic): ?>
                                <span class="panic">🔴</span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($incident['title']); ?>
                            <span style="font-weight:400;font-size:0.7rem;color:var(--gray-400);">
                                #<?php echo $incident['id']; ?>
                            </span>
                        </div>
                        <div class="incident-badges">
                            <span class="badge <?php echo $incident['status']; ?>">
                                <?php echo ucfirst($incident['status'] ?? 'Unknown'); ?>
                            </span>
                            <span class="badge <?php echo $severity; ?>">
                                <?php echo ucfirst($severity); ?>
                            </span>
                            <?php if ($is_panic): ?>
                                <span class="badge" style="background:#EF4444;color:white;">🚨 PANIC</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="incident-details">
                        <div class="item"><i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $incident['incident_type'] ?? 'Unknown')); ?></div>
                        <div class="item"><i class="fas fa-user"></i> <?php echo htmlspecialchars($incident['reporter_name'] ?? 'Unknown'); ?></div>
                        <div class="item"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($incident['pu_name'] ?? 'No PU'); ?></div>
                        <div class="item"><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($incident['created_at'])); ?></div>
                        <?php if (!empty($incident['resolved_by_name']) && !empty($incident['resolved_at'])): ?>
                            <div class="item"><i class="fas fa-check-circle" style="color:#10B981;"></i> Resolved by <?php echo htmlspecialchars($incident['resolved_by_name']); ?></div>
                            <div class="item"><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($incident['resolved_at'])); ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($incident['description'])): ?>
                        <div class="incident-description">
                            <?php echo htmlspecialchars(substr($incident['description'], 0, 200)); ?>
                            <?php if (strlen($incident['description']) > 200): ?>...<?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="incident-actions">
                        <a href="incident-details.php?id=<?php echo $incident['id']; ?>" class="btn-sm view">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <?php if ($incident['status'] === 'reported' || $incident['status'] === 'investigating'): ?>
                            <button onclick="openActionModal(<?php echo $incident['id']; ?>, 'resolve')" class="btn-sm resolve">
                                <i class="fas fa-check"></i> Resolve
                            </button>
                            <button onclick="openActionModal(<?php echo $incident['id']; ?>, 'escalate')" class="btn-sm escalate">
                                <i class="fas fa-arrow-up"></i> Escalate
                            </button>
                            <button onclick="openActionModal(<?php echo $incident['id']; ?>, 'close')" class="btn-sm close">
                                <i class="fas fa-times"></i> Close
                            </button>
                        <?php elseif ($incident['status'] === 'resolved' || $incident['status'] === 'closed'): ?>
                            <button onclick="openActionModal(<?php echo $incident['id']; ?>, 'reopen')" class="btn-sm reopen">
                                <i class="fas fa-redo"></i> Reopen
                            </button>
                        <?php endif; ?>
                        <?php if ($incident['status'] === 'escalated'): ?>
                            <span style="font-size:0.7rem;color:var(--gray-400);padding:4px 8px;">
                                <i class="fas fa-arrow-up"></i> Escalated
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php 
            $total_pages = ceil($total_incidents / $limit);
            if ($total_pages > 1): 
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&severity=<?php echo $severity_filter; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&severity=<?php echo $severity_filter; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&severity=<?php echo $severity_filter; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle"></i>
                <h4>No Incidents Found</h4>
                <p>No incidents have been reported in this ward yet.</p>
                <a href="incident-create.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-plus"></i> Report First Incident
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Action Modal -->
<div class="modal-overlay" id="actionModal">
    <div class="modal-content">
        <h3 id="modalTitle">Action on Incident</h3>
        <form method="POST" action="">
            <input type="hidden" name="incident_id" id="modalIncidentId">
            <input type="hidden" name="action" id="modalAction">
            
            <div class="form-group">
                <label for="modalNotes">Notes</label>
                <textarea name="notes" id="modalNotes" placeholder="Add notes about this action..." rows="4"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary" id="modalSubmitBtn">
                    <i class="fas fa-check"></i> Confirm
                </button>
                <button type="button" class="btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Apply filters
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const type = document.getElementById('typeFilter').value;
    const severity = document.getElementById('severityFilter').value;
    window.location.href = `?search=${encodeURIComponent(search)}&status=${status}&type=${type}&severity=${severity}`;
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('typeFilter').value = 'all';
    document.getElementById('severityFilter').value = 'all';
    window.location.href = '?';
}

// Enter key on search
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
    }
});

// Open action modal
function openActionModal(incidentId, action) {
    document.getElementById('modalIncidentId').value = incidentId;
    document.getElementById('modalAction').value = action;
    
    const titles = {
        'resolve': 'Resolve Incident',
        'escalate': 'Escalate Incident',
        'close': 'Close Incident',
        'reopen': 'Reopen Incident'
    };
    document.getElementById('modalTitle').textContent = titles[action] || 'Action on Incident';
    
    const submitLabels = {
        'resolve': 'Resolve',
        'escalate': 'Escalate',
        'close': 'Close',
        'reopen': 'Reopen'
    };
    document.getElementById('modalSubmitBtn').innerHTML = `<i class="fas fa-check"></i> ${submitLabels[action] || 'Confirm'}`;
    
    document.getElementById('modalNotes').value = '';
    document.getElementById('actionModal').classList.add('active');
}

// Close modal
function closeModal() {
    document.getElementById('actionModal').classList.remove('active');
}

// Close modal on overlay click
document.getElementById('actionModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Preloader
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// Sidebar toggle
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

// Sidebar dropdowns
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

// Profile dropdown
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
</script>
</body>
</html>