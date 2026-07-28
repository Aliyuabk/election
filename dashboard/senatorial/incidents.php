<?php
// ============================================================
// SENATORIAL COORDINATOR - INCIDENT MONITORING
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$senatorial_id = SessionManager::get('senatorial_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET SENATORIAL DISTRICT NAME
// ============================================================
$district_name = 'Senatorial District';
$state_name = 'State';
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("
            SELECT s.name as state_name, sd.name as district_name 
            FROM senatorial_districts sd 
            JOIN states s ON sd.state_id = s.id 
            WHERE sd.id = ?
        ");
        $stmt->execute([$senatorial_id]);
        $result = $stmt->fetch();
        if ($result) {
            $district_name = $result['district_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching district: " . $e->getMessage());
}

// ============================================================
// GET LGA IDs FROM SENATORIAL DISTRICT
// ============================================================
$lga_ids = [];
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("SELECT lgas_json FROM senatorial_districts WHERE id = ?");
        $stmt->execute([$senatorial_id]);
        $lgas_json = $stmt->fetchColumn();
        
        if ($lgas_json) {
            $lga_names = json_decode($lgas_json, true) ?: [];
            
            if (!empty($lga_names)) {
                $placeholders = implode(',', array_fill(0, count($lga_names), '?'));
                $stmt = $db->prepare("SELECT id FROM lgas WHERE name IN ($placeholders) AND state_id = ? AND is_active = 1");
                $stmt->execute(array_merge($lga_names, [$state_id]));
                $lga_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error getting LGA IDs: " . $e->getMessage());
    $lga_ids = [];
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// GET FILTERS
// ============================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$lga_filter = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// ============================================================
// GET LGAS FOR FILTER
// ============================================================
$lgas = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// ============================================================
// BUILD INCIDENT QUERY
// ============================================================
$where_conditions = ["i.tenant_id = ?"];
$params = [$tenant_id];

if ($lga_filter > 0) {
    $where_conditions[] = "i.lga_id = ?";
    $params[] = $lga_filter;
} elseif ($lga_list !== '0') {
    $where_conditions[] = "i.lga_id IN ($lga_list)";
}

if (!empty($status_filter)) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
}

if (!empty($severity_filter)) {
    $where_conditions[] = "i.severity = ?";
    $params[] = $severity_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "i.incident_type = ?";
    $params[] = $type_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(i.title LIKE ? OR i.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(" AND ", $where_conditions);

// ============================================================
// GET INCIDENTS
// ============================================================
$incidents = [];
$total_incidents = 0;

try {
    // Get total count
    $count_query = "
        SELECT COUNT(*) as total
        FROM incidents i
        WHERE $where_clause
    ";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_incidents = $stmt->fetchColumn();

    // Get incidents
    $query = "
        SELECT 
            i.*,
            u.full_name as reporter_name,
            pu.name as pu_name,
            w.name as ward_name,
            l.name as lga_name,
            v.full_name as assigned_to_name,
            r.full_name as resolved_by_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON i.ward_id = w.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        LEFT JOIN users v ON i.assigned_to = v.id
        LEFT JOIN users r ON i.resolved_by = r.id
        WHERE $where_clause
        ORDER BY 
            CASE WHEN i.status IN ('reported', 'investigating') THEN 0 ELSE 1 END,
            i.severity = 'critical' DESC,
            i.severity = 'high' DESC,
            i.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($query);
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $incidents = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching incidents: " . $e->getMessage());
}

$total_pages = ceil($total_incidents / $per_page);

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'reported' => 0,
    'investigating' => 0,
    'resolved' => 0,
    'escalated' => 0,
    'critical' => 0,
    'high' => 0,
    'medium' => 0,
    'low' => 0
];

try {
    $stats_where = str_replace('i.', '', $where_clause);
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported,
            SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low
        FROM incidents i
        WHERE $stats_where
    ");
    $stmt->execute($params);
    $stats = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching incident stats: " . $e->getMessage());
}

$page_title = 'Incident Monitoring';
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
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 18px 20px;
    border: 1px solid var(--gray-200);
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
}
.stat-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}
.stat-card .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-card .stat-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-card .stat-icon {
    font-size: 1.2rem;
    margin-bottom: 4px;
}
.stat-card.blue .stat-number { color: #2563EB; }
.stat-card.blue .stat-icon { color: #2563EB; }
.stat-card.green .stat-number { color: #059669; }
.stat-card.green .stat-icon { color: #059669; }
.stat-card.yellow .stat-number { color: #D97706; }
.stat-card.yellow .stat-icon { color: #D97706; }
.stat-card.orange .stat-number { color: #EA580C; }
.stat-card.orange .stat-icon { color: #EA580C; }
.stat-card.purple .stat-number { color: #7C3AED; }
.stat-card.purple .stat-icon { color: #7C3AED; }
.stat-card.red .stat-number { color: #DC2626; }
.stat-card.red .stat-icon { color: #DC2626; }

.filters-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filters-row select,
.filters-row input {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
}
.filters-row select:focus,
.filters-row input:focus {
    outline: none;
    border-color: var(--primary);
}
.filters-row .btn-filter {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
}
.filters-row .btn-filter:hover {
    background: var(--primary-dark);
}
.filters-row .btn-reset {
    padding: 8px 20px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
}
.filters-row .btn-reset:hover {
    background: var(--gray-50);
}

.section-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.section-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.section-card .card-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}

.table-wrap {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.table th {
    text-align: left;
    padding: 10px 12px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    border-bottom: 2px solid var(--gray-200);
}
.table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table tr:hover td {
    background: var(--gray-50);
}

.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.status-badge.reported { background: #FEE2E2; color: #DC2626; }
.status-badge.investigating { background: #FEF3C7; color: #D97706; }
.status-badge.resolved { background: #D1FAE5; color: #059669; }
.status-badge.escalated { background: #FEE2E2; color: #DC2626; }

.severity-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.severity-badge.critical { background: #7F1D1D; color: white; }
.severity-badge.high { background: #DC2626; color: white; }
.severity-badge.medium { background: #F59E0B; color: white; }
.severity-badge.low { background: #10B981; color: white; }

.btn-sm {
    padding: 4px 12px;
    border-radius: 6px;
    background: var(--primary);
    color: white;
    text-decoration: none;
    font-size: 0.7rem;
    border: none;
}
.btn-sm:hover {
    background: var(--primary-dark);
    color: white;
}
.btn-sm.outline {
    background: transparent;
    border: 1px solid var(--gray-300);
    color: var(--gray-600);
}
.btn-sm.outline:hover {
    background: var(--gray-50);
}
.btn-sm.danger {
    background: #FEE2E2;
    color: #DC2626;
}
.btn-sm.danger:hover {
    background: #FECACA;
}

.incident-type-tag {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.6rem;
    background: var(--gray-100);
    color: var(--gray-600);
}

.pagination {
    display: flex;
    gap: 6px;
    justify-content: center;
    margin-top: 16px;
    flex-wrap: wrap;
}
.pagination a, .pagination span {
    padding: 6px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    text-decoration: none;
    color: var(--gray-600);
    font-size: 0.8rem;
    transition: var(--transition);
}
.pagination a:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .filters-row {
        flex-direction: column;
    }
    .filters-row select,
    .filters-row input {
        width: 100%;
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
                    Incident Monitoring
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="incident-create.php" class="btn btn-primary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:white;border:none;">
                    <i class="fas fa-plus"></i> Report Incident
                </a>
                <a href="monitor-district.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue" onclick="window.location.href='incidents.php'">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Incidents</div>
            </div>
            <div class="stat-card red" onclick="window.location.href='incidents.php?status=reported'">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['reported'] ?? 0); ?></div>
                <div class="stat-label">Reported</div>
            </div>
            <div class="stat-card yellow" onclick="window.location.href='incidents.php?status=investigating'">
                <div class="stat-icon"><i class="fas fa-search"></i></div>
                <div class="stat-number"><?php echo number_format($stats['investigating'] ?? 0); ?></div>
                <div class="stat-label">Investigating</div>
            </div>
            <div class="stat-card green" onclick="window.location.href='incidents.php?status=resolved'">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['resolved'] ?? 0); ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            <div class="stat-card purple" onclick="window.location.href='incidents.php?severity=critical'">
                <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['critical'] ?? 0); ?></div>
                <div class="stat-label">Critical</div>
            </div>
            <div class="stat-card orange" onclick="window.location.href='incidents.php?severity=high'">
                <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
                <div class="stat-number"><?php echo number_format($stats['high'] ?? 0); ?></div>
                <div class="stat-label">High Priority</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-row">
            <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="reported" <?php echo ($status_filter === 'reported') ? 'selected' : ''; ?>>Reported</option>
                    <option value="investigating" <?php echo ($status_filter === 'investigating') ? 'selected' : ''; ?>>Investigating</option>
                    <option value="resolved" <?php echo ($status_filter === 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                    <option value="escalated" <?php echo ($status_filter === 'escalated') ? 'selected' : ''; ?>>Escalated</option>
                </select>
                <select name="severity">
                    <option value="">All Severity</option>
                    <option value="critical" <?php echo ($severity_filter === 'critical') ? 'selected' : ''; ?>>Critical</option>
                    <option value="high" <?php echo ($severity_filter === 'high') ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo ($severity_filter === 'medium') ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo ($severity_filter === 'low') ? 'selected' : ''; ?>>Low</option>
                </select>
                <select name="type">
                    <option value="">All Types</option>
                    <option value="violence" <?php echo ($type_filter === 'violence') ? 'selected' : ''; ?>>Violence</option>
                    <option value="intimidation" <?php echo ($type_filter === 'intimidation') ? 'selected' : ''; ?>>Intimidation</option>
                    <option value="ballot_stuffing" <?php echo ($type_filter === 'ballot_stuffing') ? 'selected' : ''; ?>>Ballot Stuffing</option>
                    <option value="vote_buying" <?php echo ($type_filter === 'vote_buying') ? 'selected' : ''; ?>>Vote Buying</option>
                    <option value="voter_suppression" <?php echo ($type_filter === 'voter_suppression') ? 'selected' : ''; ?>>Voter Suppression</option>
                    <option value="material_shortage" <?php echo ($type_filter === 'material_shortage') ? 'selected' : ''; ?>>Material Shortage</option>
                    <option value="delay" <?php echo ($type_filter === 'delay') ? 'selected' : ''; ?>>Delay</option>
                    <option value="technical_issue" <?php echo ($type_filter === 'technical_issue') ? 'selected' : ''; ?>>Technical Issue</option>
                    <option value="other" <?php echo ($type_filter === 'other') ? 'selected' : ''; ?>>Other</option>
                </select>
                <select name="lga">
                    <option value="">All LGAs</option>
                    <?php foreach ($lgas as $lga): ?>
                        <option value="<?php echo $lga['id']; ?>" <?php echo ($lga_filter == $lga['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lga['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search" placeholder="Search incidents..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="incidents.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Incidents Table -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i> Incident List</h3>
                <span style="font-size:0.75rem;color:var(--gray-400);"><?php echo count($incidents); ?> incidents (<?php echo number_format($total_incidents); ?> total)</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>LGA</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Reported</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($incidents) > 0): ?>
                            <?php $i = $offset + 1; foreach ($incidents as $incident): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($incident['title']); ?></strong>
                                        <?php if ($incident['is_panic']): ?>
                                            <span class="status-badge" style="background:#7F1D1D;color:white;font-size:0.6rem;">
                                                <i class="fas fa-exclamation-triangle"></i> PANIC
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="incident-type-tag">
                                            <?php echo ucfirst(str_replace('_', ' ', $incident['incident_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="severity-badge <?php echo $incident['severity']; ?>">
                                            <?php echo ucfirst($incident['severity']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($incident['lga_name'] ?? '—'); ?></td>
                                    <td style="font-size:0.75rem;">
                                        <?php echo htmlspecialchars($incident['ward_name'] ?? ''); ?>
                                        <?php if ($incident['pu_name']): ?>
                                            <br><span style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($incident['pu_name']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $incident['status']; ?>">
                                            <?php echo ucfirst($incident['status']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($incident['created_at'])); ?>
                                        <br><span style="font-size:0.6rem;color:var(--gray-400);">
                                            <?php echo date('g:i A', strtotime($incident['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                            <a href="incident-details.php?id=<?php echo $incident['id']; ?>" class="btn-sm">View</a>
                                            <?php if ($incident['status'] === 'reported' || $incident['status'] === 'investigating'): ?>
                                                <a href="incident-update.php?id=<?php echo $incident['id']; ?>" class="btn-sm outline">Update</a>
                                            <?php endif; ?>
                                            <?php if ($incident['status'] === 'reported'): ?>
                                                <a href="incident-escalate.php?id=<?php echo $incident['id']; ?>" class="btn-sm danger">Escalate</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>No incidents found matching your filters.</p>
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
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
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

window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>