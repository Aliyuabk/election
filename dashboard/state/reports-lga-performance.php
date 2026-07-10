<?php
// ============================================================
// STATE COORDINATOR - LGA PERFORMANCE REPORT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

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
$lga_id = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get all LGAs for dropdown
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// Get LGA name
$lga_name = 'All LGAs';
if ($lga_id > 0) {
    foreach ($lgas as $l) {
        if ($l['id'] == $lga_id) {
            $lga_name = $l['name'];
            break;
        }
    }
}

// Fetch performance data
$performance_data = [];
$summary = [
    'total_pus' => 0,
    'reported_pus' => 0,
    'verified_pus' => 0,
    'approved_pus' => 0,
    'total_incidents' => 0,
    'resolved_incidents' => 0,
    'total_coordinators' => 0,
    'active_coordinators' => 0,
    'total_agents' => 0,
    'active_agents' => 0,
    'reporting_rate' => 0,
    'verification_rate' => 0,
    'approval_rate' => 0
];

try {
    $sql = "
        SELECT 
            l.id as lga_id,
            l.name as lga_name,
            COUNT(DISTINCT pu.id) as total_pus,
            COUNT(DISTINCT r.id) as reported_pus,
            COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_pus,
            COUNT(DISTINCT CASE WHEN r.status = 'approved' THEN r.id END) as approved_pus,
            COUNT(DISTINCT i.id) as total_incidents,
            COUNT(DISTINCT CASE WHEN i.status IN ('resolved', 'closed') THEN i.id END) as resolved_incidents,
            COUNT(DISTINCT u.id) as total_coordinators,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_coordinators,
            COUNT(DISTINCT a.id) as total_agents,
            COUNT(DISTINCT CASE WHEN a.status = 'active' THEN a.id END) as active_agents
        FROM lgas l
        LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        LEFT JOIN incidents i ON i.lga_id = l.id
        LEFT JOIN users u ON u.lga_id = l.id AND u.role_id IN (SELECT id FROM roles WHERE level = 'lga')
        LEFT JOIN users a ON a.lga_id = l.id AND a.role_id IN (SELECT id FROM roles WHERE level = 'pu_agent')
        WHERE l.state_id = ? AND l.is_active = 1
    ";
    
    $params = [$tenant_id, $state_id];
    
    if ($lga_id > 0) {
        $sql .= " AND l.id = ?";
        $params[] = $lga_id;
    }
    
    $sql .= " GROUP BY l.id, l.name ORDER BY l.name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $performance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary
    foreach ($performance_data as $data) {
        $summary['total_pus'] += $data['total_pus'];
        $summary['reported_pus'] += $data['reported_pus'];
        $summary['verified_pus'] += $data['verified_pus'];
        $summary['approved_pus'] += $data['approved_pus'];
        $summary['total_incidents'] += $data['total_incidents'];
        $summary['resolved_incidents'] += $data['resolved_incidents'];
        $summary['total_coordinators'] += $data['total_coordinators'];
        $summary['active_coordinators'] += $data['active_coordinators'];
        $summary['total_agents'] += $data['total_agents'];
        $summary['active_agents'] += $data['active_agents'];
    }
    
    $summary['reporting_rate'] = $summary['total_pus'] > 0 ? round(($summary['reported_pus'] / $summary['total_pus']) * 100, 1) : 0;
    $summary['verification_rate'] = $summary['reported_pus'] > 0 ? round(($summary['verified_pus'] / $summary['reported_pus']) * 100, 1) : 0;
    $summary['approval_rate'] = $summary['verified_pus'] > 0 ? round(($summary['approved_pus'] / $summary['verified_pus']) * 100, 1) : 0;
    
} catch (Exception $e) {
    error_log("Error fetching performance data: " . $e->getMessage());
}

$page_title = 'LGA Performance Report';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.report-container {
    max-width: 1000px;
    margin: 0 auto;
}

.filter-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    align-items: center;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 200px;
}

.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-bar .btn-filter {
    padding: 8px 24px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.filter-bar .btn-filter:hover {
    background: var(--primary-dark);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.summary-card {
    background: white;
    border-radius: var(--radius);
    padding: 12px 14px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.summary-card .number {
    font-size: 1.3rem;
    font-weight: 700;
}

.summary-card .number.primary { color: #3B82F6; }
.summary-card .number.success { color: #10B981; }
.summary-card .number.warning { color: #F59E0B; }
.summary-card .number.danger { color: #EF4444; }
.summary-card .number.purple { color: #8B5CF6; }

.summary-card .label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

.summary-card .sub {
    font-size: 0.55rem;
    color: var(--gray-400);
    margin-top: 2px;
}

.performance-table-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.performance-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.performance-table th {
    background: var(--gray-50);
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.performance-table td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.performance-table tr:hover td {
    background: var(--gray-50);
}

.performance-table .progress-bar {
    height: 4px;
    background: var(--gray-200);
    border-radius: 2px;
    overflow: hidden;
    width: 80px;
    display: inline-block;
    vertical-align: middle;
}

.performance-table .progress-bar .fill {
    height: 100%;
    border-radius: 2px;
    transition: width 0.8s ease;
}

.performance-table .progress-bar .fill.success { background: #10B981; }
.performance-table .progress-bar .fill.warning { background: #F59E0B; }
.performance-table .progress-bar .fill.danger { background: #EF4444; }

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
}

.status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.high { background: #ECFDF5; color: #065F46; }
.status-badge.high .dot { background: #10B981; }
.status-badge.medium { background: #FFFBEB; color: #92400E; }
.status-badge.medium .dot { background: #F59E0B; }
.status-badge.low { background: #FEF2F2; color: #991B1B; }
.status-badge.low .dot { background: #EF4444; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h4 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    font-size: 0.85rem;
    margin-top: 4px;
}

@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar select {
        width: 100%;
        min-width: unset;
    }
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .performance-table-container {
        overflow-x: auto;
    }
    .performance-table {
        font-size: 0.7rem;
    }
    .performance-table th,
    .performance-table td {
        padding: 6px 8px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="report-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-chart-bar"></i> LGA Performance Report</h1>
                    <p class="subtitle">
                        <i class="fas fa-flag"></i> 
                        <?php echo htmlspecialchars($state_name); ?> State - LGA Performance Analysis
                    </p>
                </div>
            </div>

            <!-- Filter -->
            <div class="filter-bar">
                <select id="lgaFilter" onchange="applyFilter()">
                    <option value="0">All LGAs</option>
                    <?php foreach ($lgas as $l): ?>
                        <option value="<?php echo $l['id']; ?>" <?php echo $lga_id == $l['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($l['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button class="btn-filter" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Apply
                </button>
                
                <a href="export-pdf.php?type=lga_performance&lga_id=<?php echo $lga_id; ?>" class="btn-primary-sm" style="margin-left:auto;">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
            </div>

            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="label">Total PUs</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['reported_pus']); ?></div>
                    <div class="label">Reported PUs</div>
                    <div class="sub"><?php echo $summary['reporting_rate']; ?>% Rate</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['verified_pus']); ?></div>
                    <div class="label">Verified PUs</div>
                    <div class="sub"><?php echo $summary['verification_rate']; ?>% Rate</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['approved_pus']); ?></div>
                    <div class="label">Approved PUs</div>
                    <div class="sub"><?php echo $summary['approval_rate']; ?>% Rate</div>
                </div>
                <div class="summary-card">
                    <div class="number danger"><?php echo number_format($summary['total_incidents']); ?></div>
                    <div class="label">Incidents</div>
                    <div class="sub"><?php echo number_format($summary['resolved_incidents']); ?> Resolved</div>
                </div>
                <div class="summary-card">
                    <div class="number purple"><?php echo number_format($summary['total_coordinators']); ?></div>
                    <div class="label">Coordinators</div>
                    <div class="sub"><?php echo number_format($summary['active_coordinators']); ?> Active</div>
                </div>
                <div class="summary-card">
                    <div class="number" style="color:#8B5CF6;"><?php echo number_format($summary['total_agents']); ?></div>
                    <div class="label">Agents</div>
                    <div class="sub"><?php echo number_format($summary['active_agents']); ?> Active</div>
                </div>
                <div class="summary-card">
                    <div class="number" style="color:#F97316;"><?php echo number_format($summary['total_incidents'] > 0 ? round(($summary['resolved_incidents'] / $summary['total_incidents']) * 100, 1) : 0); ?>%</div>
                    <div class="label">Resolution Rate</div>
                </div>
            </div>

            <!-- Performance Table -->
            <div class="performance-table-container">
                <table class="performance-table">
                    <thead>
                        <tr>
                            <th>LGA</th>
                            <th>PUs</th>
                            <th>Reported</th>
                            <th>Verified</th>
                            <th>Approved</th>
                            <th>Reporting Rate</th>
                            <th>Coordinators</th>
                            <th>Agents</th>
                            <th>Incidents</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($performance_data as $data): 
                            $reporting_rate = $data['total_pus'] > 0 ? round(($data['reported_pus'] / $data['total_pus']) * 100, 1) : 0;
                            $verification_rate = $data['reported_pus'] > 0 ? round(($data['verified_pus'] / $data['reported_pus']) * 100, 1) : 0;
                            
                            if ($reporting_rate >= 90) {
                                $status = 'high';
                                $status_label = 'High';
                            } elseif ($reporting_rate >= 60) {
                                $status = 'medium';
                                $status_label = 'Medium';
                            } else {
                                $status = 'low';
                                $status_label = 'Low';
                            }
                            
                            $progress_class = $reporting_rate >= 70 ? 'success' : ($reporting_rate >= 50 ? 'warning' : 'danger');
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($data['lga_name']); ?></strong></td>
                                <td><?php echo number_format($data['total_pus']); ?></td>
                                <td><?php echo number_format($data['reported_pus']); ?></td>
                                <td><?php echo number_format($data['verified_pus']); ?></td>
                                <td><?php echo number_format($data['approved_pus']); ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="fill <?php echo $progress_class; ?>" style="width: <?php echo $reporting_rate; ?>%;"></div>
                                    </div>
                                    <?php echo $reporting_rate; ?>%
                                </td>
                                <td><?php echo number_format($data['total_coordinators']); ?></td>
                                <td><?php echo number_format($data['total_agents']); ?></td>
                                <td>
                                    <?php echo number_format($data['total_incidents']); ?>
                                    <?php if ($data['total_incidents'] - $data['resolved_incidents'] > 0): ?>
                                        <span style="color:#EF4444;font-size:0.6rem;">(<?php echo number_format($data['total_incidents'] - $data['resolved_incidents']); ?> pending)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status; ?>">
                                        <span class="dot"></span>
                                        <?php echo $status_label; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($performance_data)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="fas fa-chart-bar"></i>
                                        <h4>No Data Available</h4>
                                        <p>No performance data available for <?php echo htmlspecialchars($state_name); ?>.</p>
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
function applyFilter() {
    var lga = document.getElementById('lgaFilter').value;
    var url = window.location.pathname;
    if (lga && lga !== '0') url += '?lga_id=' + lga;
    window.location.href = url;
}

// Same sidebar scripts as index.php
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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
</script>
</body>
</html>