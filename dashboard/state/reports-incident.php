<?php
// ============================================================
// STATE COORDINATOR - INCIDENT REPORT
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
// GET FILTERS
// ============================================================
$election_filter = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
$lga_filter = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;
$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['from']) ? $_GET['from'] : '';
$date_to = isset($_GET['to']) ? $_GET['to'] : '';

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
// FETCH ELECTIONS FOR FILTER
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL 
        AND (states_json LIKE ? OR states_json IS NULL OR states_json = '[]')
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
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
// FETCH INCIDENT DATA
// ============================================================
$incidents = [];
$summary = [];

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
            assigned.first_name as assigned_first,
            assigned.last_name as assigned_last,
            resolved.first_name as resolved_first,
            resolved.last_name as resolved_last
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON i.ward_id = w.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        LEFT JOIN users assigned ON i.assigned_to = assigned.id
        LEFT JOIN users resolved ON i.resolved_by = resolved.id
        WHERE i.tenant_id = ?
        AND i.state_id = ?
    ";
    
    $params = [$tenant_id, $state_id];
    
    if (!empty($election_filter)) {
        $sql .= " AND i.election_id = ?";
        $params[] = $election_filter;
    }
    
    if (!empty($lga_filter)) {
        $sql .= " AND i.lga_id = ?";
        $params[] = $lga_filter;
    }
    
    if (!empty($severity_filter)) {
        $sql .= " AND i.severity = ?";
        $params[] = $severity_filter;
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND i.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_from)) {
        $sql .= " AND DATE(i.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $sql .= " AND DATE(i.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $sql .= " ORDER BY i.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary
    $summary['total'] = count($incidents);
    $summary['critical'] = 0;
    $summary['high'] = 0;
    $summary['medium'] = 0;
    $summary['low'] = 0;
    $summary['resolved'] = 0;
    $summary['pending'] = 0;
    
    $type_counts = [];
    $lga_counts = [];
    
    foreach ($incidents as $incident) {
        // Severity counts
        if ($incident['severity'] === 'critical') $summary['critical']++;
        elseif ($incident['severity'] === 'high') $summary['high']++;
        elseif ($incident['severity'] === 'medium') $summary['medium']++;
        elseif ($incident['severity'] === 'low') $summary['low']++;
        
        // Status counts
        if ($incident['status'] === 'resolved' || $incident['status'] === 'false_alarm') {
            $summary['resolved']++;
        } else {
            $summary['pending']++;
        }
        
        // Type counts
        $type = $incident['incident_type'];
        if (!isset($type_counts[$type])) {
            $type_counts[$type] = 0;
        }
        $type_counts[$type]++;
        
        // LGA counts
        $lga = $incident['lga_name'] ?? 'Unknown';
        if (!isset($lga_counts[$lga])) {
            $lga_counts[$lga] = 0;
        }
        $lga_counts[$lga]++;
    }
    
    arsort($type_counts);
    arsort($lga_counts);
    
    $summary['type_counts'] = $type_counts;
    $summary['lga_counts'] = $lga_counts;
    $summary['avg_response_time'] = 'N/A'; // Would require tracking
    
} catch (Exception $e) {
    error_log("Error fetching incident data: " . $e->getMessage());
}

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
/* Reuse styles from previous reports */
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

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 20px;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filter-bar select, .filter-bar input {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    background: white;
}
.filter-bar select:focus, .filter-bar input:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-bar .btn-filter {
    padding: 8px 24px;
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

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.summary-card {
    background: white;
    border-radius: 12px;
    padding: 14px 16px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.summary-card .number {
    font-size: 1.4rem;
    font-weight: 700;
}
.summary-card .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    margin-top: 2px;
}
.summary-card .number.danger { color: #EF4444; }
.summary-card .number.warning { color: #F59E0B; }
.summary-card .number.success { color: #10B981; }
.summary-card .number.primary { color: #3B82F6; }

.chart-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
.chart-box {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
}
.chart-box .title {
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 12px;
}
.chart-item {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.8rem;
}
.chart-item .value {
    font-weight: 600;
}
.chart-item:last-child {
    border-bottom: none;
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

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .date-group {
        display: flex;
        gap: 8px;
    }
    .filter-bar .date-group input {
        flex: 1;
    }
    .chart-grid {
        grid-template-columns: 1fr;
    }
    .table-wrapper {
        overflow-x: auto;
    }
    .summary-grid {
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
                    Incident Report
                    <small><?php echo htmlspecialchars($state_name); ?> - Incident analysis report</small>
                </h2>
            </div>
            <div>
                <a href="incidents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Incidents
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="filter-bar">
            <select name="election_id">
                <option value="">All Elections</option>
                <?php foreach ($elections as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo $election_filter == $e['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['name']); ?>
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
            <select name="severity">
                <option value="">All Severity</option>
                <option value="critical" <?php echo $severity_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                <option value="high" <?php echo $severity_filter === 'high' ? 'selected' : ''; ?>>High</option>
                <option value="medium" <?php echo $severity_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="low" <?php echo $severity_filter === 'low' ? 'selected' : ''; ?>>Low</option>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="reported" <?php echo $status_filter === 'reported' ? 'selected' : ''; ?>>Reported</option>
                <option value="acknowledged" <?php echo $status_filter === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                <option value="investigating" <?php echo $status_filter === 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                <option value="escalated" <?php echo $status_filter === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
            </select>
            <div class="date-group" style="display:flex;gap:8px;align-items:center;">
                <input type="date" name="from" value="<?php echo htmlspecialchars($date_from); ?>" placeholder="From">
                <span style="color:var(--gray-400);">to</span>
                <input type="date" name="to" value="<?php echo htmlspecialchars($date_to); ?>" placeholder="To">
            </div>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
            <a href="reports-incident.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>

        <?php if (count($incidents) > 0): ?>
            <!-- Summary -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total']); ?></div>
                    <div class="label">Total Incidents</div>
                </div>
                <div class="summary-card">
                    <div class="number danger"><?php echo number_format($summary['critical']); ?></div>
                    <div class="label">Critical</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['high'] + $summary['medium']); ?></div>
                    <div class="label">High/Medium</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['resolved']); ?></div>
                    <div class="label">Resolved</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['pending']); ?></div>
                    <div class="label">Pending</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="chart-grid">
                <div class="chart-box">
                    <div class="title"><i class="fas fa-chart-pie"></i> By Type</div>
                    <?php foreach ($summary['type_counts'] as $type => $count): ?>
                        <div class="chart-item">
                            <span><?php echo $incident_types[$type] ?? ucfirst($type); ?></span>
                            <span class="value"><?php echo number_format($count); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="chart-box">
                    <div class="title"><i class="fas fa-chart-bar"></i> By LGA</div>
                    <?php foreach ($summary['lga_counts'] as $lga => $count): ?>
                        <div class="chart-item">
                            <span><?php echo htmlspecialchars($lga); ?></span>
                            <span class="value"><?php echo number_format($count); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Incidents Table -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Title</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = 1; ?>
                        <?php foreach ($incidents as $incident): ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td>
                                    <div style="font-weight:500;font-size:0.8rem;"><?php echo htmlspecialchars($incident['title']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);">
                                        Reported by: <?php echo htmlspecialchars($incident['reporter_first'] . ' ' . $incident['reporter_last']); ?>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle"></i>
                <p>No incidents found for the selected filters.</p>
                <p style="font-size:0.8rem;">Try adjusting your filters or select different criteria.</p>
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
</script>
</body>
</html>