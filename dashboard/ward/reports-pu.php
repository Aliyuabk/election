<?php
// ============================================================
// WARD COORDINATOR - POLLING UNIT REPORT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Ward Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');

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

// Get ward name
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
    error_log("Error fetching ward: " . $e->getMessage());
}

// Get PU filter
$pu_filter = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;

// Get polling units for filter
$polling_units = [];
try {
    $stmt = $db->prepare("SELECT id, name, code FROM polling_units WHERE ward_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

// Fetch PU report data
$report_data = [];
$summary = [
    'total_pus' => 0,
    'total_voters' => 0,
    'total_agents' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'total_incidents' => 0,
    'pending_incidents' => 0
];

try {
    $sql = "
        SELECT 
            pu.id,
            pu.name,
            pu.code,
            pu.registered_voters,
            pu.gps_lat,
            pu.gps_lng,
            pu.is_rural,
            pu.network_quality,
            COUNT(DISTINCT u.id) as agents,
            COUNT(DISTINCT r.id) as total_results,
            COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_results,
            COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.id END) as pending_results,
            COUNT(DISTINCT i.id) as incidents,
            COUNT(DISTINCT CASE WHEN i.status IN ('reported', 'investigating') THEN i.id END) as active_incidents
        FROM polling_units pu
        LEFT JOIN users u ON u.pu_id = pu.id AND u.status = 'active'
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        LEFT JOIN incidents i ON i.pu_id = pu.id
        WHERE pu.ward_id = ? AND pu.is_active = 1
    ";
    $params = [$tenant_id, $ward_id];
    
    if ($pu_filter > 0) {
        $sql .= " AND pu.id = ?";
        $params[] = $pu_filter;
    }
    
    $sql .= " GROUP BY pu.id, pu.name, pu.code, pu.registered_voters, pu.gps_lat, pu.gps_lng, pu.is_rural, pu.network_quality
              ORDER BY pu.name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($report_data as $pu) {
        $summary['total_pus']++;
        $summary['total_voters'] += $pu['registered_voters'];
        $summary['total_agents'] += $pu['agents'];
        $summary['total_results'] += $pu['total_results'];
        $summary['verified_results'] += $pu['verified_results'];
        $summary['pending_results'] += $pu['pending_results'];
        $summary['total_incidents'] += $pu['incidents'];
        $summary['pending_incidents'] += $pu['active_incidents'];
    }
} catch (Exception $e) {
    error_log("Error fetching PU report: " . $e->getMessage());
}

// Get selected PU name
$selected_pu_name = 'All Polling Units';
if ($pu_filter > 0) {
    foreach ($polling_units as $pu) {
        if ($pu['id'] == $pu_filter) {
            $selected_pu_name = $pu['name'];
            break;
        }
    }
}

$page_title = 'Polling Unit Report';
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
    padding: 12px 18px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 180px;
}

.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-bar .btn-filter {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
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
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.summary-card {
    background: white;
    border-radius: var(--radius);
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.summary-card .number {
    font-size: 1.1rem;
    font-weight: 700;
}

.summary-card .number.primary { color: #3B82F6; }
.summary-card .number.success { color: #10B981; }
.summary-card .number.warning { color: #F59E0B; }
.summary-card .number.danger { color: #EF4444; }

.summary-card .label {
    font-size: 0.55rem;
    color: var(--gray-500);
}

.report-table-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.report-table th {
    background: var(--gray-50);
    padding: 6px 8px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.55rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.report-table td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.report-table tr:hover td {
    background: var(--gray-50);
}

.report-table .progress-bar {
    height: 4px;
    background: var(--gray-200);
    border-radius: 2px;
    overflow: hidden;
    width: 50px;
    display: inline-block;
    vertical-align: middle;
}

.report-table .progress-bar .fill {
    height: 100%;
    border-radius: 2px;
    transition: width 0.8s ease;
}

.report-table .progress-bar .fill.success { background: #10B981; }
.report-table .progress-bar .fill.warning { background: #F59E0B; }
.report-table .progress-bar .fill.danger { background: #EF4444; }

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

.export-buttons {
    display: flex;
    gap: 8px;
}

.export-buttons a {
    padding: 6px 16px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.export-buttons .btn-pdf {
    background: #EF4444;
    color: white;
}

.export-buttons .btn-excel {
    background: #10B981;
    color: white;
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
        grid-template-columns: repeat(3, 1fr);
    }
    .report-table-container {
        overflow-x: auto;
    }
    .report-table {
        font-size: 0.7rem;
    }
    .report-table th,
    .report-table td {
        padding: 4px 6px;
    }
    .export-buttons {
        flex-direction: column;
        width: 100%;
    }
    .export-buttons a {
        text-align: center;
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
                    <h1><i class="fas fa-flag-checkered"></i> Polling Unit Report</h1>
                    <p class="subtitle">
                        <i class="fas fa-layer-group"></i> 
                        <?php echo htmlspecialchars($ward_name); ?> Ward - Polling Unit Report
                    </p>
                </div>
                <div class="export-buttons">
                    <a href="export-pdf.php?type=pu_report&pu_id=<?php echo $pu_filter; ?>" class="btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="export-excel.php?type=pu_report&pu_id=<?php echo $pu_filter; ?>" class="btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>

            <!-- Filter -->
            <div class="filter-bar">
                <select id="puFilter" onchange="applyFilter()">
                    <option value="0">All Polling Units</option>
                    <?php foreach ($polling_units as $pu): ?>
                        <option value="<?php echo $pu['id']; ?>" <?php echo $pu_filter == $pu['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pu['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-filter" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <span style="font-size:0.75rem;color:var(--gray-500);margin-left:auto;">
                    <?php echo count($report_data); ?> polling units found
                </span>
            </div>

            <!-- Summary -->
            <?php if ($pu_filter > 0): ?>
                <div style="background:#F0F9FF;border:1px solid #BAE6FD;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:0.85rem;color:#0369A1;">
                    <i class="fas fa-info-circle"></i> Showing report for: <strong><?php echo htmlspecialchars($selected_pu_name); ?></strong>
                </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="label">Total PUs</div>
                </div>
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_voters']); ?></div>
                    <div class="label">Voters</div>
                </div>
                <div class="summary-card">
                    <div class="number purple"><?php echo number_format($summary['total_agents']); ?></div>
                    <div class="label">Agents</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['total_results']); ?></div>
                    <div class="label">Results</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['verified_results']); ?></div>
                    <div class="label">Verified</div>
                </div>
                <div class="summary-card">
                    <div class="number danger"><?php echo number_format($summary['total_incidents']); ?></div>
                    <div class="label">Incidents</div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="report-table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>PU</th>
                            <th>Code</th>
                            <th>Voters</th>
                            <th>Agents</th>
                            <th>Results</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Reporting Rate</th>
                            <th>Incidents</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $pu): 
                            $reporting_rate = $pu['registered_voters'] > 0 ? round(($pu['total_results'] / max(1, $pu['registered_voters'])) * 100, 1) : 0;
                            $progress_class = $reporting_rate >= 50 ? 'success' : ($reporting_rate >= 30 ? 'warning' : 'danger');
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($pu['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($pu['code']); ?></td>
                                <td><?php echo number_format($pu['registered_voters']); ?></td>
                                <td><?php echo number_format($pu['agents']); ?></td>
                                <td><?php echo number_format($pu['total_results']); ?></td>
                                <td><?php echo number_format($pu['verified_results']); ?></td>
                                <td><?php echo number_format($pu['pending_results']); ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="fill <?php echo $progress_class; ?>" style="width: <?php echo $reporting_rate; ?>%;"></div>
                                    </div>
                                    <?php echo $reporting_rate; ?>%
                                </td>
                                <td>
                                    <?php echo number_format($pu['incidents']); ?>
                                    <?php if ($pu['active_incidents'] > 0): ?>
                                        <span style="color:#EF4444;font-size:0.55rem;">(<?php echo number_format($pu['active_incidents']); ?> active)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view-pu-results.php?pu_id=<?php echo $pu['id']; ?>" style="padding:2px 8px;border-radius:4px;font-size:0.6rem;font-weight:500;text-decoration:none;background:var(--gray-100);color:var(--gray-700);">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="fas fa-flag-checkered"></i>
                                        <h4>No Data Available</h4>
                                        <p>No polling unit data available for <?php echo htmlspecialchars($ward_name); ?>.</p>
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
    var pu = document.getElementById('puFilter').value;
    var url = window.location.pathname;
    if (pu && pu !== '0') url += '?pu_id=' + pu;
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