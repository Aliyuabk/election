<?php
// ============================================================
// WARD COORDINATOR - WARD REPORT
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

// Get ward, lga, state names
$ward_name = 'Ward';
$lga_name = 'LGA';
$state_name = 'State';
try {
    if ($ward_id) {
        $stmt = $db->prepare("
            SELECT w.name as ward_name, l.name as lga_name, s.name as state_name
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            WHERE w.id = ?
        ");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['ward_name'];
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching location names: " . $e->getMessage());
}

// Fetch report data
$report_data = [];
$summary = [
    'total_pus' => 0,
    'total_voters' => 0,
    'total_agents' => 0,
    'active_agents' => 0,
    'online_agents' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'flagged_results' => 0,
    'total_incidents' => 0,
    'pending_incidents' => 0,
    'resolved_incidents' => 0,
    'ec8b_total' => 0,
    'ec8b_verified' => 0,
    'ec8b_pending' => 0,
    'reporting_rate' => 0,
    'verification_rate' => 0,
    'total_elections' => 0,
    'active_elections' => 0
];

try {
    // PU count
    $stmt = $db->prepare("SELECT COUNT(*) as count, SUM(registered_voters) as voters FROM polling_units WHERE ward_id = ? AND is_active = 1");
    $stmt->execute([$ward_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['total_pus'] = (int)($result['count'] ?? 0);
    $summary['total_voters'] = (int)($result['voters'] ?? 0);

    // Agents
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id IN (SELECT id FROM users WHERE ward_id = ?) AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as online
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.ward_id = ? AND r.level = 'pu_agent' AND u.deleted_at IS NULL
    ");
    $stmt->execute([$ward_id, $ward_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['total_agents'] = (int)($result['total'] ?? 0);
    $summary['active_agents'] = (int)($result['active'] ?? 0);
    $summary['online_agents'] = (int)($result['online'] ?? 0);

    // Elections
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['total_elections'] = (int)($result['total'] ?? 0);
    $summary['active_elections'] = (int)($result['active'] ?? 0);

    // Results (EC8A)
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN r.status IN ('verified', 'approved') THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN r.status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        WHERE pu.ward_id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$ward_id, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['total_results'] = (int)($result['total'] ?? 0);
    $summary['verified_results'] = (int)($result['verified'] ?? 0);
    $summary['pending_results'] = (int)($result['pending'] ?? 0);
    $summary['flagged_results'] = (int)($result['flagged'] ?? 0);

    // EC8B
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM results_ec8b
        WHERE tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['ec8b_total'] = (int)($result['total'] ?? 0);
    $summary['ec8b_verified'] = (int)($result['verified'] ?? 0);
    $summary['ec8b_pending'] = (int)($result['pending'] ?? 0);

    // Incidents
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('reported', 'acknowledged', 'investigating') THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved
        FROM incidents 
        WHERE ward_id = ?
    ");
    $stmt->execute([$ward_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['total_incidents'] = (int)($result['total'] ?? 0);
    $summary['pending_incidents'] = (int)($result['pending'] ?? 0);
    $summary['resolved_incidents'] = (int)($result['resolved'] ?? 0);

    // Calculate rates
    $summary['reporting_rate'] = $summary['total_pus'] > 0 ? round(($summary['total_results'] / $summary['total_pus']) * 100, 1) : 0;
    $summary['verification_rate'] = $summary['total_results'] > 0 ? round(($summary['verified_results'] / $summary['total_results']) * 100, 1) : 0;

    // PU-wise data
    $stmt = $db->prepare("
        SELECT 
            pu.id,
            pu.name,
            pu.code,
            pu.registered_voters,
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
        GROUP BY pu.id, pu.name, pu.code, pu.registered_voters
        ORDER BY pu.name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching report data: " . $e->getMessage());
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

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
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
    font-size: 1.2rem;
    font-weight: 700;
}

.summary-card .number.primary { color: #3B82F6; }
.summary-card .number.success { color: #10B981; }
.summary-card .number.warning { color: #F59E0B; }
.summary-card .number.danger { color: #EF4444; }
.summary-card .number.purple { color: #8B5CF6; }
.summary-card .number.orange { color: #F97316; }

.summary-card .label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

.summary-card .sub {
    font-size: 0.5rem;
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

.export-buttons .btn-csv {
    background: #8B5CF6;
    color: white;
}

@media (max-width: 768px) {
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
                    <h1><i class="fas fa-file-alt"></i> Ward Report</h1>
                    <p class="subtitle">
                        <i class="fas fa-layer-group"></i> 
                        <?php echo htmlspecialchars($ward_name); ?> Ward - Comprehensive Report
                    </p>
                </div>
                <div class="export-buttons">
                    <a href="export-pdf.php?type=ward_report" class="btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="export-excel.php?type=ward_report" class="btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="export-csv.php?type=ward_report" class="btn-csv">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="label">Polling Units</div>
                    <div class="sub"><?php echo number_format($summary['total_voters']); ?> Voters</div>
                </div>
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_agents']); ?></div>
                    <div class="label">Agents</div>
                    <div class="sub"><?php echo number_format($summary['active_agents']); ?> Active</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['total_elections']); ?></div>
                    <div class="label">Elections</div>
                    <div class="sub"><?php echo number_format($summary['active_elections']); ?> Active</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['total_results']); ?></div>
                    <div class="label">Results Submitted</div>
                    <div class="sub"><?php echo $summary['reporting_rate']; ?>% Rate</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['verified_results']); ?></div>
                    <div class="label">Verified</div>
                    <div class="sub"><?php echo $summary['verification_rate']; ?>% Rate</div>
                </div>
                <div class="summary-card">
                    <div class="number orange"><?php echo number_format($summary['online_agents']); ?></div>
                    <div class="label">Online Agents</div>
                </div>
                <div class="summary-card">
                    <div class="number purple"><?php echo number_format($summary['ec8b_total']); ?></div>
                    <div class="label">EC8B Forms</div>
                    <div class="sub"><?php echo number_format($summary['ec8b_verified']); ?> Verified</div>
                </div>
                <div class="summary-card">
                    <div class="number danger"><?php echo number_format($summary['total_incidents']); ?></div>
                    <div class="label">Incidents</div>
                    <div class="sub"><?php echo number_format($summary['pending_incidents']); ?> Pending</div>
                </div>
            </div>

            <!-- PU Report Table -->
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
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $pu): 
                            $reporting_rate = $pu['registered_voters'] > 0 ? round(($pu['total_results'] / max(1, $pu['registered_voters'])) * 100, 1) : 0;
                            $verification_rate = $pu['total_results'] > 0 ? round(($pu['verified_results'] / $pu['total_results']) * 100, 1) : 0;
                            
                            if ($reporting_rate >= 50) {
                                $status = 'excellent';
                                $status_label = 'Excellent';
                            } elseif ($reporting_rate >= 30) {
                                $status = 'good';
                                $status_label = 'Good';
                            } elseif ($reporting_rate >= 15) {
                                $status = 'fair';
                                $status_label = 'Fair';
                            } else {
                                $status = 'poor';
                                $status_label = 'Poor';
                            }
                            
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
                                    <span class="status-badge <?php echo $status; ?>">
                                        <span class="dot"></span>
                                        <?php echo $status_label; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="fas fa-file-alt"></i>
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