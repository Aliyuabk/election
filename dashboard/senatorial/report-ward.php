<?php
// ============================================================
// SENATORIAL COORDINATOR - WARD REPORT
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
$selected_ward = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;
$election_id = isset($_GET['election']) ? (int)$_GET['election'] : 0;

// ============================================================
// GET ELECTIONS AND WARDS
// ============================================================
$elections = [];
$wards = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, election_date, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
    
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT w.id, w.name, w.code, l.name as lga_name
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            WHERE l.id IN ($lga_list) AND w.is_active = 1
            ORDER BY l.name ASC, w.name ASC
        ");
        $stmt->execute();
        $wards = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching filters: " . $e->getMessage());
}

// ============================================================
// GET WARD DATA
// ============================================================
$report_data = null;
$pu_data = [];
$summary = [
    'total_pus' => 0,
    'submitted' => 0,
    'verified' => 0,
    'pending' => 0,
    'flagged' => 0,
    'total_votes' => 0,
    'total_registered' => 0
];

if ($selected_ward > 0 && $election_id > 0) {
    try {
        // Get Ward details
        $stmt = $db->prepare("
            SELECT w.id, w.name, w.code, l.name as lga_name,
                   COUNT(DISTINCT pu.id) as pu_count,
                   SUM(pu.registered_voters) as total_registered_voters
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            WHERE w.id = ? AND w.is_active = 1
            GROUP BY w.id, w.name, w.code, l.name
        ");
        $stmt->execute([$selected_ward]);
        $report_data = $stmt->fetch();

        // Get PU data
        $stmt = $db->prepare("
            SELECT 
                pu.id as pu_id,
                pu.name as pu_name,
                pu.code as pu_code,
                pu.registered_voters,
                r.id as result_id,
                r.status as result_status,
                r.total_votes_cast,
                r.valid_votes,
                r.rejected_votes,
                r.accredited_voters,
                r.created_at as submitted_at,
                u.full_name as agent_name
            FROM polling_units pu
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.election_id = ? AND r.tenant_id = ?
            LEFT JOIN users u ON r.agent_id = u.id
            WHERE pu.ward_id = ? AND pu.is_active = 1
            ORDER BY pu.name ASC
        ");
        $stmt->execute([$election_id, $tenant_id, $selected_ward]);
        $pu_data = $stmt->fetchAll();
        
        foreach ($pu_data as $row) {
            $summary['total_pus']++;
            if ($row['result_id']) {
                $summary['submitted']++;
                if ($row['result_status'] === 'verified') $summary['verified']++;
                elseif ($row['result_status'] === 'flagged') $summary['flagged']++;
            } else {
                $summary['pending']++;
            }
            $summary['total_votes'] += $row['total_votes_cast'] ?? 0;
            $summary['total_registered'] += $row['registered_voters'] ?? 0;
        }
        
        $summary['submission_rate'] = $summary['total_pus'] > 0 ? round(($summary['submitted'] / $summary['total_pus']) * 100, 1) : 0;
    } catch (Exception $e) {
        error_log("Error fetching report data: " . $e->getMessage());
    }
}

$page_title = 'Ward Report';
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
    min-width: 200px;
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
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
.stat-card .stat-change {
    font-size: 0.7rem;
    margin-top: 4px;
    color: var(--gray-400);
}
.stat-card.blue .stat-number { color: #2563EB; }
.stat-card.green .stat-number { color: #059669; }
.stat-card.purple .stat-number { color: #7C3AED; }
.stat-card.orange .stat-number { color: #EA580C; }
.stat-card.teal .stat-number { color: #0D9488; }
.stat-card.yellow .stat-number { color: #D97706; }

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
.status-badge.verified { background: #D1FAE5; color: #059669; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.flagged { background: #FEE2E2; color: #DC2626; }

.progress-bar {
    height: 6px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
    margin-top: 4px;
}
.progress-bar .progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}
.progress-bar .progress-fill.green { background: #10B981; }
.progress-bar .progress-fill.blue { background: #3B82F6; }
.progress-bar .progress-fill.yellow { background: #F59E0B; }
.progress-bar .progress-fill.red { background: #EF4444; }

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
.btn-sm.outline {
    background: transparent;
    border: 1px solid var(--gray-300);
    color: var(--gray-600);
}
.btn-sm.outline:hover {
    background: var(--gray-50);
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
                    <i class="fas fa-layer-group" style="color:var(--primary);margin-right:8px;"></i> 
                    Ward Report
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
                <select name="election" required>
                    <option value="">Select Election...</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo ($election_id == $e['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="ward" required>
                    <option value="">Select Ward...</option>
                    <?php foreach ($wards as $ward): ?>
                        <option value="<?php echo $ward['id']; ?>" <?php echo ($selected_ward == $ward['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ward['name']); ?> (<?php echo htmlspecialchars($ward['lga_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Generate Report</button>
                <a href="report-ward.php" class="btn-filter" style="background:var(--gray-100);color:var(--gray-600);">Reset</a>
            </form>
        </div>

        <?php if ($selected_ward > 0 && $election_id > 0 && $report_data): ?>
            <!-- Ward Header -->
            <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:20px;margin-bottom:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                    <div>
                        <h3 style="font-size:1.1rem;font-weight:700;margin:0;">
                            <?php echo htmlspecialchars($report_data['name']); ?>
                        </h3>
                        <p style="color:var(--gray-500);font-size:0.85rem;margin:4px 0 0;">
                            <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($report_data['code']); ?>
                            <span style="margin-left:16px;">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($report_data['lga_name']); ?>
                            </span>
                            <span style="margin-left:16px;">
                                <i class="fas fa-flag-checkered"></i> <?php echo number_format($report_data['pu_count'] ?? 0); ?> PUs
                            </span>
                            <span style="margin-left:16px;">
                                <i class="fas fa-users"></i> <?php echo number_format($report_data['total_registered_voters'] ?? 0); ?> Voters
                            </span>
                        </p>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:0.85rem;color:var(--gray-500);">
                            Submission Rate
                            <span style="font-size:1.2rem;font-weight:700;color:var(--primary);display:block;">
                                <?php echo $summary['submission_rate']; ?>%
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-number"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="stat-label">Total PUs</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-number"><?php echo number_format($summary['submitted']); ?></div>
                    <div class="stat-label">Submitted</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-number"><?php echo number_format($summary['verified']); ?></div>
                    <div class="stat-label">Verified</div>
                </div>
                <div class="stat-card yellow">
                    <div class="stat-number"><?php echo number_format($summary['pending']); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-number"><?php echo number_format($summary['total_votes']); ?></div>
                    <div class="stat-label">Total Votes</div>
                </div>
                <div class="stat-card teal">
                    <div class="stat-number"><?php echo number_format($summary['total_registered']); ?></div>
                    <div class="stat-label">Registered Voters</div>
                </div>
            </div>

            <!-- PU Table -->
            <div class="section-card">
                <div class="card-header">
                    <h3><i class="fas fa-flag-checkered" style="color:var(--primary);margin-right:6px;"></i> Polling Unit Breakdown</h3>
                    <span style="font-size:0.75rem;color:var(--gray-400);"><?php echo count($pu_data); ?> polling units</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Polling Unit</th>
                                <th>Code</th>
                                <th>Registered</th>
                                <th>Accredited</th>
                                <th>Votes Cast</th>
                                <th>Valid</th>
                                <th>Rejected</th>
                                <th>Status</th>
                                <th>Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($pu_data) > 0): ?>
                                <?php $i = 1; foreach ($pu_data as $row): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['pu_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['pu_code']); ?></td>
                                        <td><?php echo number_format($row['registered_voters'] ?? 0); ?></td>
                                        <td><?php echo number_format($row['accredited_voters'] ?? 0); ?></td>
                                        <td><?php echo number_format($row['total_votes_cast'] ?? 0); ?></td>
                                        <td><?php echo number_format($row['valid_votes'] ?? 0); ?></td>
                                        <td><?php echo number_format($row['rejected_votes'] ?? 0); ?></td>
                                        <td>
                                            <?php if ($row['result_id']): ?>
                                                <span class="status-badge <?php echo $row['result_status'] ?? 'pending'; ?>">
                                                    <?php echo ucfirst($row['result_status'] ?? 'Pending'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.7rem;color:var(--gray-600);">
                                            <?php echo htmlspecialchars($row['agent_name'] ?? '—'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10">
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p>No polling unit data available.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($selected_ward > 0 && $election_id > 0): ?>
            <div class="empty-state">
                <i class="fas fa-layer-group"></i>
                <p>Ward not found.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-layer-group"></i>
                <h3>Select a Ward and Election</h3>
                <p>Choose a ward and election from the dropdowns above to generate the report.</p>
            </div>
        <?php endif; ?>
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