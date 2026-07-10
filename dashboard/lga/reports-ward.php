<?php
// ============================================================
// LGA COORDINATOR - WARD REPORT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'lga') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'LGA Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$lga_id = SessionManager::get('lga_id');

if (empty($lga_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT lga_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['lga_id'])) {
            $lga_id = $user['lga_id'];
            SessionManager::set('lga_id', $lga_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching lga_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get LGA name
$lga_name = 'LGA';
try {
    if ($lga_id) {
        $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA: " . $e->getMessage());
}

// Get ward filter
$ward_filter = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;

// Get wards for filter
$wards = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}

// Fetch ward report data
$report_data = [];
$summary = [
    'total_wards' => 0,
    'total_pus' => 0,
    'total_agents' => 0,
    'total_coordinators' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'total_incidents' => 0,
    'pending_incidents' => 0
];

try {
    $sql = "
        SELECT 
            w.id,
            w.name,
            w.code,
            w.registered_voters,
            COUNT(DISTINCT pu.id) as total_pus,
            COUNT(DISTINCT r.id) as total_results,
            COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_results,
            COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.id END) as pending_results,
            COUNT(DISTINCT u.id) as coordinators,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_coordinators,
            COUNT(DISTINCT i.id) as incidents,
            COUNT(DISTINCT CASE WHEN i.status IN ('reported', 'investigating') THEN i.id END) as active_incidents
        FROM wards w
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        LEFT JOIN users u ON u.ward_id = w.id
        LEFT JOIN incidents i ON i.ward_id = w.id
        WHERE w.lga_id = ? AND w.is_active = 1
    ";
    $params = [$tenant_id, $lga_id];
    
    if ($ward_filter > 0) {
        $sql .= " AND w.id = ?";
        $params[] = $ward_filter;
    }
    
    $sql .= " GROUP BY w.id, w.name, w.code, w.registered_voters ORDER BY w.name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($report_data as $ward) {
        $summary['total_wards']++;
        $summary['total_pus'] += $ward['total_pus'];
        $summary['total_results'] += $ward['total_results'];
        $summary['verified_results'] += $ward['verified_results'];
        $summary['pending_results'] += $ward['pending_results'];
        $summary['total_incidents'] += $ward['incidents'];
        $summary['pending_incidents'] += $ward['active_incidents'];
        $summary['total_coordinators'] += $ward['coordinators'];
    }
} catch (Exception $e) {
    error_log("Error fetching ward report: " . $e->getMessage());
}

// Get selected ward name
$selected_ward_name = 'All Wards';
if ($ward_filter > 0) {
    foreach ($wards as $w) {
        if ($w['id'] == $ward_filter) {
            $selected_ward_name = $w['name'];
            break;
        }
    }
}

$page_title = 'Ward Report';
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
    font-size: 1.2rem;
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
    padding: 8px 10px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.report-table td {
    padding: 8px 10px;
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
    width: 60px;
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

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.5rem;
    padding: 2px 8px;
    border-radius: 8px;
    font-weight: 600;
}

.status-badge .dot {
    width: 4px;
    height: 4px;
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

.export-buttons .btn-pdf:hover {
    background: #DC2626;
}

.export-buttons .btn-excel {
    background: #10B981;
    color: white;
}

.export-buttons .btn-excel:hover {
    background: #059669;
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
                    <h1><i class="fas fa-layer-group"></i> Ward Report</h1>
                    <p class="subtitle">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($lga_name); ?> LGA - Ward Performance Report
                    </p>
                </div>
                <div class="export-buttons">
                    <a href="export-pdf.php?type=ward_report&ward_id=<?php echo $ward_filter; ?>" class="btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="export-excel.php?type=ward_report&ward_id=<?php echo $ward_filter; ?>" class="btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>

            <!-- Filter -->
            <div class="filter-bar">
                <select id="wardFilter" onchange="applyFilter()">
                    <option value="0">All Wards</option>
                    <?php foreach ($wards as $w): ?>
                        <option value="<?php echo $w['id']; ?>" <?php echo $ward_filter == $w['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($w['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-filter" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <span style="font-size:0.75rem;color:var(--gray-500);margin-left:auto;">
                    <?php echo count($report_data); ?> wards found
                </span>
            </div>

            <!-- Summary -->
            <?php if ($ward_filter > 0): ?>
                <div style="background:#F0F9FF;border:1px solid #BAE6FD;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:0.85rem;color:#0369A1;">
                    <i class="fas fa-info-circle"></i> Showing report for: <strong><?php echo htmlspecialchars($selected_ward_name); ?></strong>
                </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_wards']); ?></div>
                    <div class="label">Wards</div>
                </div>
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="label">PUs</div>
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
                <div class="summary-card">
                    <div class="number" style="color:#8B5CF6;"><?php echo number_format($summary['total_coordinators']); ?></div>
                    <div class="label">Coordinators</div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="report-table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Ward</th>
                            <th>Code</th>
                            <th>PUs</th>
                            <th>Results</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Reporting Rate</th>
                            <th>Coordinators</th>
                            <th>Incidents</th>
                            <th>Voters</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $ward): 
                            $reporting_rate = $ward['total_pus'] > 0 ? round(($ward['total_results'] / $ward['total_pus']) * 100, 1) : 0;
                            $progress_class = $reporting_rate >= 70 ? 'success' : ($reporting_rate >= 50 ? 'warning' : 'danger');
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($ward['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($ward['code']); ?></td>
                                <td><?php echo number_format($ward['total_pus']); ?></td>
                                <td><?php echo number_format($ward['total_results']); ?></td>
                                <td><?php echo number_format($ward['verified_results']); ?></td>
                                <td><?php echo number_format($ward['pending_results']); ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="fill <?php echo $progress_class; ?>" style="width: <?php echo $reporting_rate; ?>%;"></div>
                                    </div>
                                    <?php echo $reporting_rate; ?>%
                                </td>
                                <td><?php echo number_format($ward['coordinators']); ?></td>
                                <td>
                                    <?php echo number_format($ward['incidents']); ?>
                                    <?php if ($ward['active_incidents'] > 0): ?>
                                        <span style="color:#EF4444;font-size:0.55rem;">(<?php echo number_format($ward['active_incidents']); ?> active)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($ward['registered_voters']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="fas fa-layer-group"></i>
                                        <h4>No Data Available</h4>
                                        <p>No ward data available for <?php echo htmlspecialchars($lga_name); ?>.</p>
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
    var ward = document.getElementById('wardFilter').value;
    var url = window.location.pathname;
    if (ward && ward !== '0') url += '?ward_id=' + ward;
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