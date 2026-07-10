<?php
// ============================================================
// STATE COORDINATOR - INCIDENT MANAGEMENT
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
$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$lga_filter = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;
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
// FETCH LGAS FOR FILTER
// ============================================================
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// ============================================================
// FETCH INCIDENTS
// ============================================================
$incidents = [];
$total_incidents = 0;
$total_pages = 0;

try {
    $sql = "
        SELECT 
            i.*,
            u.first_name as reporter_first,
            u.last_name as reporter_last,
            u.phone as reporter_phone,
            pu.name as pu_name,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            assigned.first_name as assigned_first,
            assigned.last_name as assigned_last,
            resolved.first_name as resolved_first,
            resolved.last_name as resolved_last
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON i.ward_id = w.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        LEFT JOIN states s ON i.state_id = s.id
        LEFT JOIN users assigned ON i.assigned_to = assigned.id
        LEFT JOIN users resolved ON i.resolved_by = resolved.id
        WHERE i.tenant_id = ?
        AND i.state_id = ?
    ";
    
    $params = [$tenant_id, $state_id];
    
    if (!empty($status_filter)) {
        $sql .= " AND i.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($severity_filter)) {
        $sql .= " AND i.severity = ?";
        $params[] = $severity_filter;
    }
    
    if (!empty($type_filter)) {
        $sql .= " AND i.incident_type = ?";
        $params[] = $type_filter;
    }
    
    if (!empty($lga_filter)) {
        $sql .= " AND i.lga_id = ?";
        $params[] = $lga_filter;
    }
    
    if (!empty($search)) {
        $sql .= " AND (i.title LIKE ? OR i.description LIKE ? OR pu.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Count total
    $count_sql = str_replace(
        "SELECT 
            i.*,
            u.first_name as reporter_first,
            u.last_name as reporter_last,
            u.phone as reporter_phone,
            pu.name as pu_name,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            assigned.first_name as assigned_first,
            assigned.last_name as assigned_last,
            resolved.first_name as resolved_first,
            resolved.last_name as resolved_last",
        "SELECT COUNT(*) as count",
        $sql
    );
    
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_incidents = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    $total_pages = ceil($total_incidents / $per_page);
    
    // Get data
    $sql .= " ORDER BY 
        CASE 
            WHEN i.severity = 'critical' THEN 1
            WHEN i.severity = 'high' THEN 2
            WHEN i.severity = 'medium' THEN 3
            ELSE 4
        END,
        i.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching incidents: " . $e->getMessage());
}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'critical' => 0,
    'high' => 0,
    'medium' => 0,
    'low' => 0,
    'reported' => 0,
    'acknowledged' => 0,
    'investigating' => 0,
    'resolved' => 0,
    'escalated' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low,
            SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported,
            SUM(CASE WHEN status = 'acknowledged' THEN 1 ELSE 0 END) as acknowledged,
            SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated
        FROM incidents
        WHERE tenant_id = ? AND state_id = ?
    ");
    $stmt->execute([$tenant_id, $state_id]);
    $stats_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['total'] = (int)($stats_data['total'] ?? 0);
    $stats['critical'] = (int)($stats_data['critical'] ?? 0);
    $stats['high'] = (int)($stats_data['high'] ?? 0);
    $stats['medium'] = (int)($stats_data['medium'] ?? 0);
    $stats['low'] = (int)($stats_data['low'] ?? 0);
    $stats['reported'] = (int)($stats_data['reported'] ?? 0);
    $stats['acknowledged'] = (int)($stats_data['acknowledged'] ?? 0);
    $stats['investigating'] = (int)($stats_data['investigating'] ?? 0);
    $stats['resolved'] = (int)($stats_data['resolved'] ?? 0);
    $stats['escalated'] = (int)($stats_data['escalated'] ?? 0);
    
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

// ============================================================
// INCIDENT TYPES AND SEVERITY
// ============================================================
$incident_types = [
    'violence' => 'Violence',
    'intimidation' => 'Intimidation',
    'ballot_stuffing' => 'Ballot Stuffing',
    'vote_buying' => 'Vote Buying',
    'voter_suppression' => 'Voter Suppression',
    'material_shortage' => 'Material Shortage',
    'delay' => 'Delay',
    'technical_issue' => 'Technical Issue',
    'other' => 'Other',
    'panic_button' => 'Panic Button'
];

$severity_colors = [
    'critical' => 'danger',
    'high' => 'warning',
    'medium' => 'warning',
    'low' => 'secondary'
];

$status_colors = [
    'reported' => 'danger',
    'acknowledged' => 'warning',
    'investigating' => 'primary',
    'resolved' => 'success',
    'escalated' => 'danger',
    'false_alarm' => 'secondary'
];

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 12px 16px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-card .number {
    font-size: 1.4rem;
    font-weight: 700;
}
.stat-card .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    margin-top: 2px;
}
.stat-card .number.danger { color: #EF4444; }
.stat-card .number.warning { color: #F59E0B; }
.stat-card .number.success { color: #10B981; }
.stat-card .number.primary { color: #3B82F6; }
.stat-card .number.secondary { color: #6B7280; }

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 16px;
    background: white;
    padding: 14px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filter-bar .search-box {
    flex: 1;
    min-width: 180px;
    display: flex;
    gap: 8px;
}
.filter-bar .search-box input {
    flex: 1;
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
}
.filter-bar .search-box input:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    background: white;
}
.filter-bar .btn-filter {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
}
.filter-bar .btn-filter:hover {
    background: var(--primary-dark);
}
.filter-bar .btn-reset {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    font-family: 'Inter', sans-serif;
}
.filter-bar .btn-reset:hover {
    background: var(--gray-200);
}

.table-wrapper {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.table-wrapper table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table-wrapper table th {
    background: var(--gray-50);
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-600);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
}
.table-wrapper table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table-wrapper table tr:hover td {
    background: var(--gray-50);
}
.table-wrapper table tr:last-child td {
    border-bottom: none;
}

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
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
.badge-status.danger { background: #FEF2F2; color: #991B1B; }
.badge-status.danger .dot { background: #EF4444; }
.badge-status.warning { background: #FFFBEB; color: #92400E; }
.badge-status.warning .dot { background: #F59E0B; }
.badge-status.success { background: #ECFDF5; color: #065F46; }
.badge-status.success .dot { background: #10B981; }
.badge-status.primary { background: #EFF6FF; color: #1E40AF; }
.badge-status.primary .dot { background: #3B82F6; }
.badge-status.secondary { background: #F3F4F6; color: #6B7280; }
.badge-status.secondary .dot { background: #9CA3AF; }

.btn-action {
    padding: 4px 10px;
    border-radius: 6px;
    border: none;
    font-size: 0.7rem;
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
.btn-action.btn-assign {
    background: #F5F3FF;
    color: #8B5CF6;
}
.btn-action.btn-assign:hover {
    background: #EDE9FE;
}
.btn-action.btn-resolve {
    background: #ECFDF5;
    color: #10B981;
}
.btn-action.btn-resolve:hover {
    background: #D1FAE5;
}
.btn-action.btn-escalate {
    background: #FEF2F2;
    color: #EF4444;
}
.btn-action.btn-escalate:hover {
    background: #FEE2E2;
}

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
    font-size: 0.8rem;
    color: var(--gray-500);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-400);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

.panic-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 700;
    background: #FEF2F2;
    color: #DC2626;
    animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .table-wrapper {
        overflow-x: auto;
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .search-box {
        flex-direction: column;
    }
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
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
                    <i class="fas fa-exclamation-triangle" style="color:var(--danger);margin-right:8px;"></i>
                    Incident Management
                    <small><?php echo htmlspecialchars($state_name); ?> - Manage and track incidents</small>
                </h2>
            </div>
            <div>
                <a href="incident-report.php" class="btn-primary-sm">
                    <i class="fas fa-plus"></i> Report Incident
                </a>
                <a href="index.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-card">
                <div class="number danger"><?php echo number_format($stats['critical']); ?></div>
                <div class="label">Critical</div>
            </div>
            <div class="stat-card">
                <div class="number warning"><?php echo number_format($stats['high']); ?></div>
                <div class="label">High</div>
            </div>
            <div class="stat-card">
                <div class="number warning"><?php echo number_format($stats['medium']); ?></div>
                <div class="label">Medium</div>
            </div>
            <div class="stat-card">
                <div class="number secondary"><?php echo number_format($stats['low']); ?></div>
                <div class="label">Low</div>
            </div>
            <div class="stat-card">
                <div class="number danger"><?php echo number_format($stats['reported'] + $stats['escalated']); ?></div>
                <div class="label">Active</div>
            </div>
            <div class="stat-card">
                <div class="number success"><?php echo number_format($stats['resolved']); ?></div>
                <div class="label">Resolved</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="filter-bar">
            <div class="search-box">
                <input type="text" name="search" placeholder="Search incidents..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
            </div>
            <select name="status">
                <option value="">All Status</option>
                <option value="reported" <?php echo $status_filter === 'reported' ? 'selected' : ''; ?>>Reported</option>
                <option value="acknowledged" <?php echo $status_filter === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                <option value="investigating" <?php echo $status_filter === 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                <option value="escalated" <?php echo $status_filter === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                <option value="false_alarm" <?php echo $status_filter === 'false_alarm' ? 'selected' : ''; ?>>False Alarm</option>
            </select>
            <select name="severity">
                <option value="">All Severity</option>
                <option value="critical" <?php echo $severity_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                <option value="high" <?php echo $severity_filter === 'high' ? 'selected' : ''; ?>>High</option>
                <option value="medium" <?php echo $severity_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="low" <?php echo $severity_filter === 'low' ? 'selected' : ''; ?>>Low</option>
            </select>
            <select name="type">
                <option value="">All Types</option>
                <?php foreach ($incident_types as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $type_filter === $key ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="lga_id">
                <option value="">All LGAs</option>
                <?php foreach ($lgas as $l): ?>
                    <option value="<?php echo $l['id']; ?>" <?php echo $lga_filter == $l['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($l['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="incidents.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>

        <!-- Table -->
        <div class="table-wrapper">
            <?php if (count($incidents) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Incident</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Reported</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = $offset + 1; ?>
                        <?php foreach ($incidents as $incident): ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td>
                                    <div style="font-weight:600;font-size:0.8rem;">
                                        <?php if ($incident['is_panic']): ?>
                                            <span class="panic-indicator">
                                                <i class="fas fa-bell"></i> PANIC
                                            </span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($incident['title']); ?>
                                    </div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);">
                                        By: <?php echo htmlspecialchars($incident['reporter_first'] . ' ' . $incident['reporter_last']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:0.8rem;"><?php echo htmlspecialchars($incident['lga_name'] ?? 'N/A'); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);">
                                        <?php echo htmlspecialchars($incident['ward_name'] ?? ''); ?>
                                        <?php if ($incident['pu_name']): ?>
                                            - <?php echo htmlspecialchars($incident['pu_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;">
                                        <?php echo $incident_types[$incident['incident_type']] ?? ucfirst($incident['incident_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $severity_colors[$incident['severity']] ?? 'secondary'; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($incident['severity']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $status_colors[$incident['status']] ?? 'secondary'; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst(str_replace('_', ' ', $incident['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:0.75rem;"><?php echo date('M j, Y', strtotime($incident['created_at'])); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo date('g:i A', strtotime($incident['created_at'])); ?></div>
                                </td>
                                <td>
                                    <a href="incident-view.php?id=<?php echo $incident['id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($incident['status'] !== 'resolved' && $incident['status'] !== 'false_alarm'): ?>
                                        <button onclick="assignIncident(<?php echo $incident['id']; ?>)" class="btn-action btn-assign">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                        <button onclick="resolveIncident(<?php echo $incident['id']; ?>)" class="btn-action btn-resolve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php if ($incident['severity'] === 'critical' || $incident['severity'] === 'high'): ?>
                                            <button onclick="escalateIncident(<?php echo $incident['id']; ?>)" class="btn-action btn-escalate">
                                                <i class="fas fa-arrow-up"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
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
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <span class="page-info">
                            Showing <?php echo min($total_incidents, ($page - 1) * $per_page + 1); ?> - 
                            <?php echo min($total_incidents, $page * $per_page); ?> of <?php echo number_format($total_incidents); ?>
                        </span>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>No incidents found.</p>
                    <?php if (!empty($search) || !empty($status_filter) || !empty($severity_filter) || !empty($type_filter) || !empty($lga_filter)): ?>
                        <p style="font-size:0.8rem;">Try adjusting your filters.</p>
                    <?php else: ?>
                        <p style="font-size:0.8rem;">No incidents have been reported in <?php echo htmlspecialchars($state_name); ?> yet.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
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
// INCIDENT ACTIONS
// ============================================================
function assignIncident(incidentId) {
    var userId = prompt('Enter the user ID to assign this incident to:');
    if (userId && !isNaN(userId)) {
        if (confirm('Are you sure you want to assign this incident?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'incident-assign.php';
            
            var input1 = document.createElement('input');
            input1.type = 'hidden';
            input1.name = 'incident_id';
            input1.value = incidentId;
            form.appendChild(input1);
            
            var input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'user_id';
            input2.value = userId;
            form.appendChild(input2);
            
            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'csrf_token';
            token.value = '<?php echo $csrf_token; ?>';
            form.appendChild(token);
            
            document.body.appendChild(form);
            form.submit();
        }
    } else if (userId !== null) {
        alert('Please enter a valid user ID.');
    }
}

function resolveIncident(incidentId) {
    var resolution = prompt('Enter resolution notes:');
    if (resolution !== null) {
        if (confirm('Are you sure you want to mark this incident as resolved?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'incident-resolve.php';
            
            var input1 = document.createElement('input');
            input1.type = 'hidden';
            input1.name = 'incident_id';
            input1.value = incidentId;
            form.appendChild(input1);
            
            var input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'resolution_notes';
            input2.value = resolution;
            form.appendChild(input2);
            
            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'csrf_token';
            token.value = '<?php echo $csrf_token; ?>';
            form.appendChild(token);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
}

function escalateIncident(incidentId) {
    var reason = prompt('Enter reason for escalation:');
    if (reason !== null) {
        if (confirm('Are you sure you want to escalate this incident?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'incident-escalate.php';
            
            var input1 = document.createElement('input');
            input1.type = 'hidden';
            input1.name = 'incident_id';
            input1.value = incidentId;
            form.appendChild(input1);
            
            var input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'reason';
            input2.value = reason;
            form.appendChild(input2);
            
            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'csrf_token';
            token.value = '<?php echo $csrf_token; ?>';
            form.appendChild(token);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
}
</script>
</body>
</html>