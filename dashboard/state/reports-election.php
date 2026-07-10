<?php
// ============================================================
// STATE COORDINATOR - ELECTION REPORT
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
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get elections for dropdown
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

// Get election name
$election_name = 'All Elections';
$election_data = null;
if ($election_id > 0) {
    foreach ($elections as $e) {
        if ($e['id'] == $election_id) {
            $election_name = $e['name'];
            $election_data = $e;
            break;
        }
    }
}

// Fetch election report data
$report_data = [];
$summary = [
    'total_pus' => 0,
    'total_wards' => 0,
    'total_lgas' => 0,
    'submitted_results' => 0,
    'verified_results' => 0,
    'approved_results' => 0,
    'rejected_results' => 0,
    'pending_results' => 0,
    'total_incidents' => 0,
    'pending_incidents' => 0,
    'resolved_incidents' => 0,
    'total_agents' => 0,
    'active_agents' => 0,
    'reporting_rate' => 0,
    'verification_rate' => 0,
    'approval_rate' => 0,
    'turnout' => 0
];

$lga_results = [];

try {
    if ($election_id > 0) {
        // Get election statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT pu.id) as total_pus,
                COUNT(DISTINCT w.id) as total_wards,
                COUNT(DISTINCT l.id) as total_lgas,
                COUNT(DISTINCT r.id) as submitted_results,
                COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_results,
                COUNT(DISTINCT CASE WHEN r.status = 'approved' THEN r.id END) as approved_results,
                COUNT(DISTINCT CASE WHEN r.status = 'rejected' THEN r.id END) as rejected_results,
                COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.id END) as pending_results,
                COUNT(DISTINCT i.id) as total_incidents,
                COUNT(DISTINCT CASE WHEN i.status IN ('reported', 'acknowledged', 'investigating') THEN i.id END) as pending_incidents,
                COUNT(DISTINCT CASE WHEN i.status IN ('resolved', 'closed') THEN i.id END) as resolved_incidents,
                COUNT(DISTINCT u.id) as total_agents,
                COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_agents,
                SUM(r.total_votes_cast) as total_votes_cast,
                SUM(r.registered_voters) as total_registered_voters
            FROM elections e
            LEFT JOIN lgas l ON l.state_id = ?
            LEFT JOIN wards w ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.election_id = e.id AND r.tenant_id = ?
            LEFT JOIN incidents i ON i.election_id = e.id AND i.state_id = ?
            LEFT JOIN users u ON u.pu_id = pu.id AND u.role_id IN (SELECT id FROM roles WHERE level = 'pu_agent')
            WHERE e.id = ? AND e.tenant_id = ?
        ");
        $stmt->execute([$state_id, $tenant_id, $state_id, $election_id, $tenant_id]);
        $election_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($election_stats) {
            $summary = array_merge($summary, $election_stats);
            $summary['reporting_rate'] = $summary['total_pus'] > 0 ? round(($summary['submitted_results'] / $summary['total_pus']) * 100, 1) : 0;
            $summary['verification_rate'] = $summary['submitted_results'] > 0 ? round(($summary['verified_results'] / $summary['submitted_results']) * 100, 1) : 0;
            $summary['approval_rate'] = $summary['verified_results'] > 0 ? round(($summary['approved_results'] / $summary['verified_results']) * 100, 1) : 0;
            $summary['turnout'] = $summary['total_registered_voters'] > 0 ? round(($summary['total_votes_cast'] / $summary['total_registered_voters']) * 100, 1) : 0;
        }

        // Get LGA-wise results
        $stmt = $db->prepare("
            SELECT 
                l.id,
                l.name as lga_name,
                COUNT(DISTINCT pu.id) as total_pus,
                COUNT(DISTINCT r.id) as submitted_results,
                COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_results,
                COUNT(DISTINCT CASE WHEN r.status = 'approved' THEN r.id END) as approved_results,
                COUNT(DISTINCT i.id) as incidents,
                SUM(r.valid_votes) as valid_votes,
                SUM(r.total_votes_cast) as total_votes,
                SUM(r.registered_voters) as registered_voters
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.election_id = ? AND r.tenant_id = ?
            LEFT JOIN incidents i ON i.lga_id = l.id AND i.election_id = ?
            WHERE l.state_id = ? AND l.is_active = 1
            GROUP BY l.id, l.name
            ORDER BY l.name ASC
        ");
        $stmt->execute([$election_id, $tenant_id, $election_id, $state_id]);
        $lga_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching election report: " . $e->getMessage());
}

$election_types = [
    'presidential' => 'Presidential',
    'governorship' => 'Governorship',
    'senatorial' => 'Senatorial',
    'house_of_reps' => 'House of Reps',
    'house_of_assembly' => 'House of Assembly',
    'lga_chairman' => 'LGA Chairman',
    'councillorship' => 'Councillorship',
    'party_primary' => 'Party Primary',
    'internal_party' => 'Internal Party'
];

$page_title = 'Election Report';
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

.election-info-card {
    background: white;
    border-radius: var(--radius);
    padding: 18px 20px;
    border: 1px solid var(--gray-200);
    margin-bottom: 20px;
}

.election-info-card .election-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gray-800);
}

.election-info-card .election-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-top: 4px;
}

.election-info-card .election-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.6rem;
    padding: 3px 12px;
    border-radius: 12px;
    font-weight: 600;
}

.status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.draft { background: #F3F4F6; color: #6B7280; }
.status-badge.draft .dot { background: #9CA3AF; }
.status-badge.upcoming { background: #FFFBEB; color: #92400E; }
.status-badge.upcoming .dot { background: #F59E0B; }
.status-badge.active { background: #ECFDF5; color: #065F46; }
.status-badge.active .dot { background: #10B981; }
.status-badge.closed { background: #FEF2F2; color: #991B1B; }
.status-badge.closed .dot { background: #EF4444; }

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 10px;
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

.empty-state {
    text-align: center;
    padding: 60px 20px;
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
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="report-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-vote-yea"></i> Election Report</h1>
                    <p class="subtitle">
                        <i class="fas fa-flag"></i> 
                        <?php echo htmlspecialchars($state_name); ?> State - Election Results Report
                    </p>
                </div>
            </div>

            <!-- Filter -->
            <div class="filter-bar">
                <select id="electionFilter" onchange="applyFilter()">
                    <option value="0">Select Election...</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo $election_id == $e['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['name']); ?> (<?php echo date('Y', strtotime($e['election_date'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button class="btn-filter" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Generate Report
                </button>
                
                <?php if ($election_id > 0): ?>
                    <a href="export-pdf.php?type=election_report&election_id=<?php echo $election_id; ?>" class="btn-primary-sm" style="margin-left:auto;">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($election_id > 0 && $election_data): ?>
                <!-- Election Info -->
                <div class="election-info-card">
                    <div class="election-title">
                        <?php echo htmlspecialchars($election_data['name']); ?>
                    </div>
                    <div class="election-meta">
                        <span><i class="fas fa-tag"></i> <?php echo $election_types[$election_data['type']] ?? ucfirst($election_data['type']); ?></span>
                        <span><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($election_data['election_date'])); ?></span>
                        <span>
                            <span class="status-badge <?php echo $election_data['status']; ?>">
                                <span class="dot"></span>
                                <?php echo ucfirst($election_data['status']); ?>
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="number primary"><?php echo number_format($summary['total_pus']); ?></div>
                        <div class="label">Polling Units</div>
                    </div>
                    <div class="summary-card">
                        <div class="number warning"><?php echo number_format($summary['submitted_results']); ?></div>
                        <div class="label">Results Submitted</div>
                        <div class="sub"><?php echo $summary['reporting_rate']; ?>% Rate</div>
                    </div>
                    <div class="summary-card">
                        <div class="number success"><?php echo number_format($summary['verified_results']); ?></div>
                        <div class="label">Verified</div>
                        <div class="sub"><?php echo $summary['verification_rate']; ?>% Rate</div>
                    </div>
                    <div class="summary-card">
                        <div class="number success"><?php echo number_format($summary['approved_results']); ?></div>
                        <div class="label">Approved</div>
                        <div class="sub"><?php echo $summary['approval_rate']; ?>% Rate</div>
                    </div>
                    <div class="summary-card">
                        <div class="number danger"><?php echo number_format($summary['rejected_results']); ?></div>
                        <div class="label">Rejected</div>
                    </div>
                    <div class="summary-card">
                        <div class="number danger"><?php echo number_format($summary['total_incidents']); ?></div>
                        <div class="label">Incidents</div>
                        <div class="sub"><?php echo number_format($summary['pending_incidents']); ?> Pending</div>
                    </div>
                    <div class="summary-card">
                        <div class="number purple"><?php echo number_format($summary['total_agents']); ?></div>
                        <div class="label">Agents</div>
                        <div class="sub"><?php echo number_format($summary['active_agents']); ?> Active</div>
                    </div>
                    <div class="summary-card">
                        <div class="number orange"><?php echo $summary['turnout']; ?>%</div>
                        <div class="label">Voter Turnout</div>
                    </div>
                </div>

                <!-- LGA Results Table -->
                <div class="report-table-container">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>LGA</th>
                                <th>PUs</th>
                                <th>Submitted</th>
                                <th>Verified</th>
                                <th>Approved</th>
                                <th>Reporting Rate</th>
                                <th>Valid Votes</th>
                                <th>Turnout</th>
                                <th>Incidents</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lga_results as $lga): 
                                $reporting_rate = $lga['total_pus'] > 0 ? round(($lga['submitted_results'] / $lga['total_pus']) * 100, 1) : 0;
                                $turnout = $lga['registered_voters'] > 0 ? round(($lga['total_votes'] / $lga['registered_voters']) * 100, 1) : 0;
                                $progress_class = $reporting_rate >= 70 ? 'success' : ($reporting_rate >= 50 ? 'warning' : 'danger');
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($lga['lga_name']); ?></strong></td>
                                    <td><?php echo number_format($lga['total_pus']); ?></td>
                                    <td><?php echo number_format($lga['submitted_results']); ?></td>
                                    <td><?php echo number_format($lga['verified_results']); ?></td>
                                    <td><?php echo number_format($lga['approved_results']); ?></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="fill <?php echo $progress_class; ?>" style="width: <?php echo $reporting_rate; ?>%;"></div>
                                        </div>
                                        <?php echo $reporting_rate; ?>%
                                    </td>
                                    <td><?php echo number_format($lga['valid_votes']); ?></td>
                                    <td><?php echo $turnout; ?>%</td>
                                    <td>
                                        <?php echo number_format($lga['incidents']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($lga_results)): ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="empty-state">
                                            <i class="fas fa-vote-yea"></i>
                                            <h4>No Data Available</h4>
                                            <p>No results data available for this election.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($election_id > 0 && !$election_data): ?>
                <div class="empty-state" style="background:white;border-radius:var(--radius);padding:40px;border:1px solid var(--gray-200);">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4>Election Not Found</h4>
                    <p>The selected election could not be found.</p>
                </div>
            <?php else: ?>
                <div class="empty-state" style="background:white;border-radius:var(--radius);padding:40px;border:1px solid var(--gray-200);">
                    <i class="fas fa-vote-yea"></i>
                    <h4>Select an Election</h4>
                    <p>Please select an election from the dropdown above to generate the report.</p>
                </div>
            <?php endif; ?>
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