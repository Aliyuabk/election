<?php
// ============================================================
// SENATORIAL COORDINATOR - INCIDENT REPORT
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
$period = isset($_GET['period']) ? $_GET['period'] : 'month'; // week, month, quarter, year
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : '';
$lga_filter = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;

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
// BUILD QUERY
// ============================================================
$where_conditions = ["tenant_id = ?"];
$params = [$tenant_id];

if ($lga_filter > 0) {
    $where_conditions[] = "lga_id = ?";
    $params[] = $lga_filter;
} elseif ($lga_list !== '0') {
    $where_conditions[] = "lga_id IN ($lga_list)";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "incident_type = ?";
    $params[] = $type_filter;
}

if (!empty($severity_filter)) {
    $where_conditions[] = "severity = ?";
    $params[] = $severity_filter;
}

// Date filter based on period
$date_condition = "";
switch ($period) {
    case 'week':
        $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'quarter':
        $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    case 'year':
        $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
        break;
    default:
        $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$where_conditions[] = $date_condition;
$where_clause = implode(" AND ", $where_conditions);

// ============================================================
// GET INCIDENT DATA
// ============================================================
$incidents = [];
$stats = [
    'total' => 0,
    'reported' => 0,
    'investigating' => 0,
    'resolved' => 0,
    'escalated' => 0,
    'critical' => 0,
    'high' => 0,
    'medium' => 0,
    'low' => 0,
    'by_type' => [],
    'by_lga' => []
];

try {
    $query = "
        SELECT i.*, 
               u.full_name as reporter_name,
               pu.name as pu_name,
               w.name as ward_name,
               l.name as lga_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON i.ward_id = w.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        WHERE $where_clause
        ORDER BY i.created_at DESC
        LIMIT 100
    ";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll();
    
    // Get statistics
    $stats['total'] = count($incidents);
    
    foreach ($incidents as $inc) {
        // Status stats
        if ($inc['status'] === 'reported') $stats['reported']++;
        elseif ($inc['status'] === 'investigating') $stats['investigating']++;
        elseif ($inc['status'] === 'resolved') $stats['resolved']++;
        elseif ($inc['status'] === 'escalated') $stats['escalated']++;
        
        // Severity stats
        if ($inc['severity'] === 'critical') $stats['critical']++;
        elseif ($inc['severity'] === 'high') $stats['high']++;
        elseif ($inc['severity'] === 'medium') $stats['medium']++;
        elseif ($inc['severity'] === 'low') $stats['low']++;
        
        // By type
        $type = $inc['incident_type'];
        if (!isset($stats['by_type'][$type])) {
            $stats['by_type'][$type] = 0;
        }
        $stats['by_type'][$type]++;
        
        // By LGA
        $lga = $inc['lga_name'] ?? 'Unknown';
        if (!isset($stats['by_lga'][$lga])) {
            $stats['by_lga'][$lga] = 0;
        }
        $stats['by_lga'][$lga]++;
    }
} catch (Exception $e) {
    error_log("Error fetching incident data: " . $e->getMessage());
}

$page_title = 'Incident Report';
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

.filter-section {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filter-section select {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
    min-width: 150px;
}
.filter-section select:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-section .btn-filter {
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
.filter-section .btn-filter:hover {
    background: var(--primary-dark);
}
.filter-section .btn-print {
    padding: 8px 20px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
}
.filter-section .btn-print:hover {
    background: var(--gray-50);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 18px 20px;
    border: 1px solid var(--gray-200);
    text-align: center;
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

.incident-type-tag {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    background: var(--gray-100);
    color: var(--gray-600);
}

@media print {
    .filter-section, .page-header .btn, .sidebar, .dashboard-header { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .stats-grid { break-inside: avoid; }
    .section-card { break-inside: avoid; border: 1px solid #ddd !important; }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-section {
        flex-direction: column;
    }
    .filter-section select {
        min-width: unset;
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
                    <i class="fas fa-exclamation-triangle" style="color:var(--primary);margin-right:8px;"></i> 
                    Incident Report
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="window.print()" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="reports.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-section">
            <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <select name="period">
                    <option value="week" <?php echo ($period === 'week') ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo ($period === 'month') ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="quarter" <?php echo ($period === 'quarter') ? 'selected' : ''; ?>>Last 90 Days</option>
                    <option value="year" <?php echo ($period === 'year') ? 'selected' : ''; ?>>Last Year</option>
                </select>
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
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply</button>
                <a href="report-incidents.php" class="btn-filter" style="background:var(--gray-100);color:var(--gray-600);">Reset</a>
            </form>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Incidents</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['reported']); ?></div>
                <div class="stat-label">Reported</div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-icon"><i class="fas fa-search"></i></div>
                <div class="stat-number"><?php echo number_format($stats['investigating']); ?></div>
                <div class="stat-label">Investigating</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['resolved']); ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
                <div class="stat-number"><?php echo number_format($stats['escalated']); ?></div>
                <div class="stat-label">Escalated</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['critical']); ?></div>
                <div class="stat-label">Critical</div>
            </div>
        </div>

        <!-- Incident List -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i> Incident Details</h3>
                <span style="font-size:0.75rem;color:var(--gray-400);"><?php echo count($incidents); ?> incidents found</span>
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
                            <th>Ward</th>
                            <th>PU</th>
                            <th>Status</th>
                            <th>Reported</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($incidents) > 0): ?>
                            <?php $i = 1; foreach ($incidents as $inc): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($inc['title']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="incident-type-tag">
                                            <?php echo ucfirst(str_replace('_', ' ', $inc['incident_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="severity-badge <?php echo $inc['severity']; ?>">
                                            <?php echo ucfirst($inc['severity']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($inc['lga_name'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($inc['ward_name'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($inc['pu_name'] ?? '—'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $inc['status']; ?>">
                                            <?php echo ucfirst($inc['status']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($inc['created_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="incident-details.php?id=<?php echo $inc['id']; ?>" class="btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">
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