<?php
// ============================================================
// WARD COORDINATOR - WARD REPORTS
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

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$ward_id = SessionManager::get('ward_id');
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
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

// ============================================================
// FETCH WARD, LGA, STATE NAMES
// ============================================================
$ward_name = 'Ward';
$lga_name = 'LGA';
$state_name = 'State';
$total_voters = 0;
$total_pus = 0;

try {
    if ($ward_id) {
        $stmt = $db->prepare("
            SELECT 
                w.name as ward_name,
                l.name as lga_name,
                s.name as state_name,
                SUM(pu.registered_voters) as total_voters,
                COUNT(pu.id) as total_pus
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id
            WHERE w.id = ?
            GROUP BY w.id, w.name, l.name, s.name
        ");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['ward_name'];
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
            $total_voters = (int)($result['total_voters'] ?? 0);
            $total_pus = (int)($result['total_pus'] ?? 0);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward details: " . $e->getMessage());
}

// ============================================================
// FETCH REPORT DATA
// ============================================================
$report_type = isset($_GET['type']) ? $_GET['type'] : 'summary';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

// ============================================================
// FETCH ELECTIONS
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, election_date, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($election_id) && !empty($elections)) {
        $election_id = $elections[0]['id'];
    }
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

// ============================================================
// FETCH SUMMARY STATISTICS
// ============================================================
$summary_stats = [];
$pu_performance = [];
$agent_stats = [];
$incident_stats = [];
$result_stats = [];

try {
    // Summary Statistics
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM polling_units WHERE ward_id = ? AND is_active = 1) as total_pus,
            (SELECT SUM(registered_voters) FROM polling_units WHERE ward_id = ? AND is_active = 1) as total_voters,
            (SELECT COUNT(*) FROM users u WHERE u.ward_id = ? AND u.status = 'active' AND u.deleted_at IS NULL AND EXISTS (SELECT 1 FROM roles r WHERE r.id = u.role_id AND r.level = 'pu_agent')) as total_agents,
            (SELECT COUNT(*) FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id WHERE pu.ward_id = ? AND r.status = 'verified') as verified_results,
            (SELECT COUNT(*) FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id WHERE pu.ward_id = ? AND r.status = 'pending') as pending_results,
            (SELECT COUNT(*) FROM incidents i WHERE i.ward_id = ? AND i.status IN ('reported', 'investigating')) as active_incidents,
            (SELECT COUNT(*) FROM incidents i WHERE i.ward_id = ? AND i.status = 'resolved') as resolved_incidents
    ");
    $stmt->execute([$ward_id, $ward_id, $ward_id, $ward_id, $ward_id, $ward_id, $ward_id]);
    $summary_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // PU Performance
    $stmt = $db->prepare("
        SELECT 
            pu.id,
            pu.name,
            pu.code,
            pu.registered_voters,
            COUNT(DISTINCT u.id) as agents,
            COUNT(DISTINCT r.id) as total_submissions,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified_submissions,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_submissions,
            COUNT(DISTINCT i.id) as incidents
        FROM polling_units pu
        LEFT JOIN users u ON u.pu_id = pu.id AND u.deleted_at IS NULL
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        LEFT JOIN incidents i ON i.pu_id = pu.id
        WHERE pu.ward_id = ? AND pu.is_active = 1
        GROUP BY pu.id, pu.name, pu.code, pu.registered_voters
        ORDER BY pu.name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $pu_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agent Statistics
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            pu.name as pu_name,
            COUNT(DISTINCT r.id) as submissions,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
            COUNT(DISTINCT i.id) as incidents_reported
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN results_ec8a r ON r.agent_id = u.id
        LEFT JOIN incidents i ON i.reporter_id = u.id
        WHERE u.ward_id = ? AND u.deleted_at IS NULL
        AND EXISTS (SELECT 1 FROM roles rl WHERE rl.id = u.role_id AND rl.level = 'pu_agent')
        GROUP BY u.id, u.full_name, u.email, u.phone, u.status, pu.name
        ORDER BY submissions DESC
        LIMIT 50
    ");
    $stmt->execute([$ward_id]);
    $agent_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Incident Statistics
    $stmt = $db->prepare("
        SELECT 
            incident_type,
            severity,
            status,
            COUNT(*) as count
        FROM incidents
        WHERE ward_id = ?
        GROUP BY incident_type, severity, status
        ORDER BY count DESC
    ");
    $stmt->execute([$ward_id]);
    $incident_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Result Statistics by day (last 30 days)
    $stmt = $db->prepare("
        SELECT 
            DATE(r.created_at) as date,
            COUNT(*) as total,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        WHERE pu.ward_id = ? 
        AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(r.created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$ward_id]);
    $result_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching report data: " . $e->getMessage());
}

$page_title = 'Ward Reports';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.report-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.report-header h2 i {
    color: var(--primary);
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
    background: white;
    padding: 12px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filter-bar select,
.filter-bar input[type="date"] {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    min-width: 140px;
}
.filter-bar .export-buttons {
    display: flex;
    gap: 6px;
    margin-left: auto;
}

.report-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    padding: 14px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-card .number {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-card .number.green { color: #10B981; }
.stat-card .number.blue { color: #3B82F6; }
.stat-card .number.orange { color: #F59E0B; }
.stat-card .number.red { color: #EF4444; }
.stat-card .number.purple { color: #8B5CF6; }
.stat-card .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    font-weight: 500;
}
.stat-card .sub {
    font-size: 0.6rem;
    color: var(--gray-400);
    margin-top: 2px;
}

.report-section {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 16px;
}
.report-section .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.report-section .section-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}
.report-section .section-header .count {
    font-size: 0.7rem;
    color: var(--gray-400);
}

.table-container {
    overflow-x: auto;
}
.table-container table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.table-container th {
    background: var(--gray-50);
    padding: 8px 12px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-600);
    border-bottom: 2px solid var(--gray-200);
    white-space: nowrap;
}
.table-container td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--gray-100);
}
.table-container tr:hover td {
    background: var(--gray-50);
}
.table-container .status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.table-container .status-badge.verified { background: #D1FAE5; color: #065F46; }
.table-container .status-badge.pending { background: #FEF3C7; color: #92400E; }
.table-container .status-badge.rejected { background: #FEE2E2; color: #991B1B; }

.chart-container {
    height: 250px;
    position: relative;
}

.export-btn {
    padding: 6px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.75rem;
    cursor: pointer;
    background: white;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--gray-700);
}
.export-btn:hover {
    background: var(--gray-50);
}

@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .export-buttons {
        margin-left: 0;
    }
    .report-summary {
        grid-template-columns: repeat(2, 1fr);
    }
    .table-container {
        font-size: 0.75rem;
    }
    .table-container th,
    .table-container td {
        padding: 6px 8px;
    }
}

@media (max-width: 480px) {
    .report-summary {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="report-header">
            <div>
                <h2><i class="fas fa-file-alt"></i> Ward Reports</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • 
                    <?php echo htmlspecialchars($lga_name); ?> LGA • 
                    <?php echo htmlspecialchars($state_name); ?> State
                </p>
            </div>
            <div>
                <a href="reports-ward.php?type=summary" class="btn-secondary-sm">
                    <i class="fas fa-sync"></i> Refresh
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <select id="electionFilter">
                <option value="0">All Elections</option>
                <?php foreach ($elections as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo $election_id == $e['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['name']); ?> (<?php echo date('Y', strtotime($e['election_date'])); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" id="dateFrom" value="<?php echo htmlspecialchars($date_from); ?>">
            <input type="date" id="dateTo" value="<?php echo htmlspecialchars($date_to); ?>">
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <div class="export-buttons">
                <a href="export-pdf.php?type=ward&ward_id=<?php echo $ward_id; ?>" class="export-btn">
                    <i class="fas fa-file-pdf" style="color:#EF4444;"></i> PDF
                </a>
                <a href="export-excel.php?type=ward&ward_id=<?php echo $ward_id; ?>" class="export-btn">
                    <i class="fas fa-file-excel" style="color:#22C55E;"></i> Excel
                </a>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="report-summary">
            <div class="stat-card">
                <div class="number blue"><?php echo number_format($summary_stats['total_pus'] ?? 0); ?></div>
                <div class="label">Polling Units</div>
                <div class="sub">Total in ward</div>
            </div>
            <div class="stat-card">
                <div class="number purple"><?php echo number_format($summary_stats['total_voters'] ?? 0); ?></div>
                <div class="label">Registered Voters</div>
                <div class="sub">Total voters</div>
            </div>
            <div class="stat-card">
                <div class="number blue"><?php echo number_format($summary_stats['total_agents'] ?? 0); ?></div>
                <div class="label">Active Agents</div>
                <div class="sub">PU Data Agents</div>
            </div>
            <div class="stat-card">
                <div class="number green"><?php echo number_format($summary_stats['verified_results'] ?? 0); ?></div>
                <div class="label">Verified Results</div>
                <div class="sub">EC8A submissions</div>
            </div>
            <div class="stat-card">
                <div class="number orange"><?php echo number_format($summary_stats['pending_results'] ?? 0); ?></div>
                <div class="label">Pending Results</div>
                <div class="sub">Awaiting verification</div>
            </div>
            <div class="stat-card">
                <div class="number red"><?php echo number_format($summary_stats['active_incidents'] ?? 0); ?></div>
                <div class="label">Active Incidents</div>
                <div class="sub"><?php echo number_format($summary_stats['resolved_incidents'] ?? 0); ?> resolved</div>
            </div>
        </div>

        <!-- PU Performance Table -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-flag-checkered"></i> Polling Unit Performance</h3>
                <span class="count"><?php echo count($pu_performance); ?> units</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>PU Name</th>
                            <th>Code</th>
                            <th>Voters</th>
                            <th>Agents</th>
                            <th>Submissions</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Incidents</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pu_performance) > 0): ?>
                            <?php foreach ($pu_performance as $pu): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($pu['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($pu['code']); ?></td>
                                    <td><?php echo number_format($pu['registered_voters'] ?? 0); ?></td>
                                    <td><?php echo number_format($pu['agents'] ?? 0); ?></td>
                                    <td><?php echo number_format($pu['total_submissions'] ?? 0); ?></td>
                                    <td><span style="color:#10B981;"><?php echo number_format($pu['verified_submissions'] ?? 0); ?></span></td>
                                    <td><span style="color:#F59E0B;"><?php echo number_format($pu['pending_submissions'] ?? 0); ?></span></td>
                                    <td><span style="color:#EF4444;"><?php echo number_format($pu['incidents'] ?? 0); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;color:var(--gray-400);padding:20px;">
                                    No polling units found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Agent Performance -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-user-tie"></i> Agent Performance</h3>
                <span class="count">Top <?php echo min(count($agent_stats), 50); ?> agents</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Agent Name</th>
                            <th>PU</th>
                            <th>Status</th>
                            <th>Submissions</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Incidents</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($agent_stats) > 0): ?>
                            <?php foreach ($agent_stats as $agent): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($agent['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($agent['pu_name'] ?? 'Unassigned'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $agent['status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($agent['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($agent['submissions'] ?? 0); ?></td>
                                    <td><span style="color:#10B981;"><?php echo number_format($agent['verified'] ?? 0); ?></span></td>
                                    <td><span style="color:#F59E0B;"><?php echo number_format($agent['pending'] ?? 0); ?></span></td>
                                    <td><span style="color:#EF4444;"><?php echo number_format($agent['incidents_reported'] ?? 0); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;color:var(--gray-400);padding:20px;">
                                    No agents found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Incident Statistics -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Incident Statistics</h3>
                <span class="count"><?php echo count($incident_stats); ?> types</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Incident Type</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($incident_stats) > 0): ?>
                            <?php foreach ($incident_stats as $inc): ?>
                                <tr>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $inc['incident_type'] ?? 'Unknown')); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $inc['severity'] ?? 'medium'; ?>">
                                            <?php echo ucfirst($inc['severity'] ?? 'Medium'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $inc['status'] ?? 'reported'; ?>">
                                            <?php echo ucfirst($inc['status'] ?? 'Reported'); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo number_format($inc['count'] ?? 0); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center;color:var(--gray-400);padding:20px;">
                                    No incidents reported
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
// Apply filters
function applyFilters() {
    const election = document.getElementById('electionFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    window.location.href = `?election_id=${election}&date_from=${dateFrom}&date_to=${dateTo}`;
}

// Preloader
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// Sidebar toggle
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

// Sidebar dropdowns
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

// Profile dropdown
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