<?php
// ============================================================
// LGA COORDINATOR - LGA REPORT
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
$state_name = 'State';
try {
    if ($lga_id) {
        $stmt = $db->prepare("
            SELECT l.name as lga_name, s.name as state_name 
            FROM lgas l 
            JOIN states s ON l.state_id = s.id 
            WHERE l.id = ?
        ");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA/State: " . $e->getMessage());
}

// Fetch report data
$report_data = [];
$summary = [
    'total_wards' => 0,
    'total_pus' => 0,
    'total_coordinators' => 0,
    'total_agents' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'total_incidents' => 0,
    'pending_incidents' => 0,
    'resolved_incidents' => 0,
    'broadcasts_sent' => 0,
    'reporting_rate' => 0,
    'verification_rate' => 0,
    'approval_rate' => 0,
    'total_elections' => 0,
    'active_elections' => 0,
    'online_agents' => 0
];

try {
    // Ward count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM wards WHERE lga_id = ? AND is_active = 1");
    $stmt->execute([$lga_id]);
    $summary['total_wards'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // PUs count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        WHERE w.lga_id = ? AND pu.is_active = 1
    ");
    $stmt->execute([$lga_id]);
    $summary['total_pus'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Coordinators count (Ward Coordinators)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE r.level = 'ward' 
        AND u.lga_id = ? AND u.status = 'active' AND u.deleted_at IS NULL
    ");
    $stmt->execute([$lga_id]);
    $summary['total_coordinators'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Agents count (PU Agents)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE r.level = 'pu_agent' 
        AND u.lga_id = ? AND u.status = 'active' AND u.deleted_at IS NULL
    ");
    $stmt->execute([$lga_id]);
    $summary['total_agents'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Online agents
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) as count
        FROM users u
        INNER JOIN user_sessions us ON u.id = us.user_id
        WHERE u.lga_id = ? AND us.is_active = 1 
        AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        AND u.status = 'active'
    ");
    $stmt->execute([$lga_id]);
    $summary['online_agents'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Elections
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND JSON_CONTAINS(lgas_json, JSON_QUOTE(?))
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $election_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['total_elections'] = (int)($election_stats['total'] ?? 0);
    $summary['active_elections'] = (int)($election_stats['active'] ?? 0);

    // Results
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('verified', 'approved') THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        WHERE w.lga_id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$lga_id, $tenant_id]);
    $result_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['total_results'] = (int)($result_stats['total'] ?? 0);
    $summary['verified_results'] = (int)($result_stats['verified'] ?? 0);
    $summary['pending_results'] = (int)($result_stats['pending'] ?? 0);

    // EC8B stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified
        FROM results_ec8b r
        JOIN wards w ON r.ward_id = w.id
        WHERE w.lga_id = ?
    ");
    $stmt->execute([$lga_id]);
    $ec8b_stats = $stmt->fetch(PDO::FETCH_ASSOC);

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
        WHERE lga_id = ?
    ");
    $stmt->execute([$lga_id]);
    $incident_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['total_incidents'] = (int)($incident_stats['total'] ?? 0);
    $summary['pending_incidents'] = (int)($incident_stats['pending'] ?? 0);
    $summary['resolved_incidents'] = (int)($incident_stats['resolved'] ?? 0);

    // Broadcasts sent
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM broadcasts 
        WHERE tenant_id = ? AND status = 'sent'
        AND (target_ids_json LIKE ? OR target_audience IN ('all', 'lga'))
    ");
    $stmt->execute([$tenant_id, '%"' . $lga_id . '"%']);
    $summary['broadcasts_sent'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Ward-wise data
    $stmt = $db->prepare("
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
        GROUP BY w.id, w.name, w.code, w.registered_voters
        ORDER BY w.name ASC
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching report data: " . $e->getMessage());
}

$page_title = 'LGA Report';
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
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
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
                    <h1><i class="fas fa-file-alt"></i> LGA Report</h1>
                    <p class="subtitle">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($lga_name); ?> LGA - Comprehensive Report
                    </p>
                </div>
                <div class="export-buttons">
                    <a href="export-pdf.php?type=lga_report" class="btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="export-excel.php?type=lga_report" class="btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_wards']); ?></div>
                    <div class="label">Wards</div>
                    <div class="sub"><?php echo number_format($summary['total_coordinators']); ?> Coordinators</div>
                </div>
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="label">Polling Units</div>
                    <div class="sub"><?php echo number_format($summary['total_agents']); ?> Agents</div>
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
                    <div class="label">Verified</div>
                    <div class="sub"><?php echo $summary['verification_rate']; ?>% Rate</div>
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
                    <div class="number orange"><?php echo number_format($summary['online_agents']); ?></div>
                    <div class="label">Online Agents</div>
                    <div class="sub">Currently Active</div>
                </div>
            </div>

            <!-- Ward Report Table -->
            <div class="report-table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Ward</th>
                            <th>Code</th>
                            <th>PUs</th>
                            <th>Results</th>
                            <th>Verified</th>
                            <th>Reporting Rate</th>
                            <th>Coordinators</th>
                            <th>Incidents</th>
                            <th>Voters</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $ward): 
                            $reporting_rate = $ward['total_pus'] > 0 ? round(($ward['total_results'] / $ward['total_pus']) * 100, 1) : 0;
                            $verification_rate = $ward['total_results'] > 0 ? round(($ward['verified_results'] / $ward['total_results']) * 100, 1) : 0;
                            
                            if ($reporting_rate >= 80) {
                                $status = 'excellent';
                                $status_label = 'Excellent';
                            } elseif ($reporting_rate >= 60) {
                                $status = 'good';
                                $status_label = 'Good';
                            } elseif ($reporting_rate >= 40) {
                                $status = 'fair';
                                $status_label = 'Fair';
                            } else {
                                $status = 'poor';
                                $status_label = 'Poor';
                            }
                            
                            $progress_class = $reporting_rate >= 70 ? 'success' : ($reporting_rate >= 50 ? 'warning' : 'danger');
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($ward['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($ward['code']); ?></td>
                                <td><?php echo number_format($ward['total_pus']); ?></td>
                                <td><?php echo number_format($ward['total_results']); ?></td>
                                <td><?php echo number_format($ward['verified_results']); ?></td>
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