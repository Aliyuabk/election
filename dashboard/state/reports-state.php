<?php
// ============================================================
// STATE COORDINATOR - STATE REPORT
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

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get election filter
$election_filter = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
$elections = [];

try {
    $stmt = $db->prepare("
        SELECT id, name, type, status, election_date 
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

// Fetch report data
$report_data = [];
$summary = [
    'total_lgas' => 0,
    'total_wards' => 0,
    'total_pus' => 0,
    'total_coordinators' => 0,
    'total_agents' => 0,
    'total_elections' => 0,
    'active_elections' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'total_incidents' => 0,
    'pending_incidents' => 0,
    'resolved_incidents' => 0,
    'broadcasts_sent' => 0,
    'reporting_rate' => 0,
    'verification_rate' => 0
];

try {
    // LGAs count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lgas WHERE state_id = ? AND is_active = 1");
    $stmt->execute([$state_id]);
    $summary['total_lgas'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Wards count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM wards w 
        JOIN lgas l ON w.lga_id = l.id 
        WHERE l.state_id = ? AND w.is_active = 1
    ");
    $stmt->execute([$state_id]);
    $summary['total_wards'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // PUs count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE l.state_id = ? AND pu.is_active = 1
    ");
    $stmt->execute([$state_id]);
    $summary['total_pus'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Coordinators count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE r.level IN ('lga', 'ward', 'pu_agent') 
        AND u.state_id = ? AND u.status = 'active' AND u.deleted_at IS NULL
    ");
    $stmt->execute([$state_id]);
    $summary['total_coordinators'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Elections
    $stmt = $db->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND (states_json LIKE ? OR states_json IS NULL OR states_json = '[]')
    ");
    $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
    $election_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['total_elections'] = (int)($election_stats['total'] ?? 0);
    $summary['active_elections'] = (int)($election_stats['active'] ?? 0);

    // Results
    $sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('verified', 'approved') THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE l.state_id = ? AND r.tenant_id = ?
    ";
    $params = [$state_id, $tenant_id];
    
    if ($election_filter > 0) {
        $sql .= " AND r.election_id = ?";
        $params[] = $election_filter;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['total_results'] = (int)($result_stats['total'] ?? 0);
    $summary['verified_results'] = (int)($result_stats['verified'] ?? 0);
    $summary['pending_results'] = (int)($result_stats['pending'] ?? 0);

    // Calculate rates
    $summary['reporting_rate'] = $summary['total_pus'] > 0 ? round(($summary['total_results'] / $summary['total_pus']) * 100, 1) : 0;
    $summary['verification_rate'] = $summary['total_results'] > 0 ? round(($summary['verified_results'] / $summary['total_results']) * 100, 1) : 0;

    // Incidents
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('reported', 'acknowledged', 'investigating') THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved
        FROM incidents 
        WHERE state_id = ?
    ");
    $stmt->execute([$state_id]);
    $incident_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['total_incidents'] = (int)($incident_stats['total'] ?? 0);
    $summary['pending_incidents'] = (int)($incident_stats['pending'] ?? 0);
    $summary['resolved_incidents'] = (int)($incident_stats['resolved'] ?? 0);

    // Broadcasts
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM broadcasts 
        WHERE tenant_id = ? AND status = 'sent'
    ");
    $stmt->execute([$tenant_id]);
    $summary['broadcasts_sent'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // LGA-wise data for detailed report
    $stmt = $db->prepare("
        SELECT 
            l.id,
            l.name,
            COUNT(DISTINCT w.id) as total_wards,
            COUNT(DISTINCT pu.id) as total_pus,
            COUNT(DISTINCT r.id) as total_results,
            COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_results,
            COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.id END) as pending_results,
            COUNT(DISTINCT u.id) as coordinators,
            COUNT(DISTINCT i.id) as incidents,
            COUNT(DISTINCT CASE WHEN i.status IN ('reported', 'acknowledged', 'investigating') THEN i.id END) as pending_incidents
        FROM lgas l
        LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        LEFT JOIN users u ON u.lga_id = l.id AND u.status = 'active'
        LEFT JOIN incidents i ON i.lga_id = l.id
        WHERE l.state_id = ? AND l.is_active = 1
        GROUP BY l.id, l.name
        ORDER BY l.name ASC
    ");
    $stmt->execute([$tenant_id, $state_id]);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching report data: " . $e->getMessage());
}

$page_title = 'State Report';
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
    min-width: 180px;
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

.filter-bar .btn-export {
    padding: 8px 24px;
    background: #10B981;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.filter-bar .btn-export:hover {
    background: #059669;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.summary-card {
    background: white;
    border-radius: var(--radius);
    padding: 14px 16px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.summary-card .number {
    font-size: 1.4rem;
    font-weight: 700;
}

.summary-card .number.primary { color: #3B82F6; }
.summary-card .number.success { color: #10B981; }
.summary-card .number.warning { color: #F59E0B; }
.summary-card .number.danger { color: #EF4444; }
.summary-card .number.purple { color: #8B5CF6; }

.summary-card .label {
    font-size: 0.65rem;
    color: var(--gray-500);
}

.summary-card .sub {
    font-size: 0.55rem;
    color: var(--gray-400);
    margin-top: 2px;
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
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.report-table td {
    padding: 8px 12px;
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
    width: 80px;
    display: inline-block;
    vertical-align: middle;
}

.report-table .progress-bar .fill {
    height: 100%;
    background: var(--primary);
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

.status-badge.excellent { background: #ECFDF5; color: #065F46; }
.status-badge.excellent .dot { background: #10B981; }
.status-badge.good { background: #EFF6FF; color: #1E40AF; }
.status-badge.good .dot { background: #3B82F6; }
.status-badge.fair { background: #FFFBEB; color: #92400E; }
.status-badge.fair .dot { background: #F59E0B; }
.status-badge.poor { background: #FEF2F2; color: #991B1B; }
.status-badge.poor .dot { background: #EF4444; }

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
    .report-table-container {
        overflow-x: auto;
    }
    .report-table {
        font-size: 0.7rem;
    }
    .report-table th,
    .report-table td {
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
                    <h1><i class="fas fa-file-alt"></i> State Report</h1>
                    <p class="subtitle">
                        <i class="fas fa-flag"></i> 
                        <?php echo htmlspecialchars($state_name); ?> State - Comprehensive Report
                    </p>
                </div>
            </div>

            <!-- Filter -->
            <div class="filter-bar">
                <select id="electionFilter" onchange="applyFilter()">
                    <option value="0">All Elections</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo $election_filter == $e['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['name']); ?> (<?php echo date('Y', strtotime($e['election_date'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button class="btn-filter" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Apply
                </button>
                
                <a href="export-pdf.php?type=state_report&election_id=<?php echo $election_filter; ?>" class="btn-export">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
                <a href="export-excel.php?type=state_report&election_id=<?php echo $election_filter; ?>" class="btn-export">
                    <i class="fas fa-file-excel"></i> Export Excel
                </a>
            </div>

            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_lgas']); ?></div>
                    <div class="label">LGAs</div>
                    <div class="sub"><?php echo number_format($summary['total_wards']); ?> Wards</div>
                </div>
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="label">Polling Units</div>
                    <div class="sub"><?php echo number_format($summary['total_coordinators']); ?> Personnel</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['total_elections']); ?></div>
                    <div class="label">Elections</div>
                    <div class="sub"><?php echo number_format($summary['active_elections']); ?> Active</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['total_results']); ?></div>
                    <div class="label">Results Submitted</div>
                    <div class="sub"><?php echo $summary['reporting_rate']; ?>% Reporting</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['verified_results']); ?></div>
                    <div class="label">Verified Results</div>
                    <div class="sub"><?php echo $summary['verification_rate']; ?>% Verified</div>
                </div>
                <div class="summary-card">
                    <div class="number danger"><?php echo number_format($summary['total_incidents']); ?></div>
                    <div class="label">Incidents</div>
                    <div class="sub"><?php echo number_format($summary['pending_incidents']); ?> Pending</div>
                </div>
                <div class="summary-card">
                    <div class="number purple"><?php echo number_format($summary['broadcasts_sent']); ?></div>
                    <div class="label">Broadcasts Sent</div>
                </div>
                <div class="summary-card">
                    <div class="number" style="color:#8B5CF6;"><?php echo number_format($summary['resolved_incidents']); ?></div>
                    <div class="label">Resolved Incidents</div>
                    <div class="sub"><?php echo $summary['total_incidents'] > 0 ? round(($summary['resolved_incidents'] / $summary['total_incidents']) * 100, 1) : 0; ?>% Resolution</div>
                </div>
            </div>

            <!-- LGA Report Table -->
            <div class="report-table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>LGA</th>
                            <th>Wards</th>
                            <th>PUs</th>
                            <th>Results</th>
                            <th>Verification</th>
                            <th>Reporting Rate</th>
                            <th>Coordinators</th>
                            <th>Incidents</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $lga): 
                            $reporting_rate = $lga['total_pus'] > 0 ? round(($lga['total_results'] / $lga['total_pus']) * 100, 1) : 0;
                            $verification_rate = $lga['total_results'] > 0 ? round(($lga['verified_results'] / $lga['total_results']) * 100, 1) : 0;
                            
                            // Determine status
                            if ($reporting_rate >= 90) {
                                $status = 'excellent';
                                $status_label = 'Excellent';
                            } elseif ($reporting_rate >= 70) {
                                $status = 'good';
                                $status_label = 'Good';
                            } elseif ($reporting_rate >= 50) {
                                $status = 'fair';
                                $status_label = 'Fair';
                            } else {
                                $status = 'poor';
                                $status_label = 'Poor';
                            }
                            
                            $progress_class = $reporting_rate >= 70 ? 'success' : ($reporting_rate >= 50 ? 'warning' : 'danger');
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($lga['name']); ?></strong></td>
                                <td><?php echo number_format($lga['total_wards']); ?></td>
                                <td><?php echo number_format($lga['total_pus']); ?></td>
                                <td><?php echo number_format($lga['total_results']); ?></td>
                                <td><?php echo number_format($lga['verified_results']); ?> (<?php echo $verification_rate; ?>%)</td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="fill <?php echo $progress_class; ?>" style="width: <?php echo $reporting_rate; ?>%;"></div>
                                    </div>
                                    <?php echo $reporting_rate; ?>%
                                </td>
                                <td><?php echo number_format($lga['coordinators']); ?></td>
                                <td>
                                    <?php echo number_format($lga['incidents']); ?>
                                    <?php if ($lga['pending_incidents'] > 0): ?>
                                        <span style="color:#EF4444;font-size:0.6rem;">(<?php echo number_format($lga['pending_incidents']); ?> pending)</span>
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
                        
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-file-alt"></i>
                                        <h4>No Data Available</h4>
                                        <p>No LGA data available for <?php echo htmlspecialchars($state_name); ?>.</p>
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
    var election = document.getElementById('electionFilter').value;
    var url = window.location.pathname;
    if (election && election !== '0') url += '?election_id=' + election;
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