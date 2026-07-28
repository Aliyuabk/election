<?php
// ============================================================
// SENATORIAL COORDINATOR - ELECTION PROGRESS REPORT
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
$election_id = isset($_GET['election']) ? (int)$_GET['election'] : 0;

// ============================================================
// GET ELECTIONS
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, election_date, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

// ============================================================
// GET PROGRESS DATA
// ============================================================
$progress_data = [];
$summary = [
    'total_pus' => 0,
    'submitted' => 0,
    'verified' => 0,
    'pending' => 0,
    'flagged' => 0,
    'checked_in' => 0,
    'total_votes' => 0
];

if ($election_id > 0 && $lga_list !== '0') {
    try {
        $stmt = $db->prepare("
            SELECT 
                l.id as lga_id,
                l.name as lga_name,
                COUNT(DISTINCT pu.id) as total_pus,
                COUNT(DISTINCT CASE WHEN r.id IS NOT NULL THEN pu.id END) as submitted,
                COUNT(DISTINCT CASE WHEN r.status = 'verified' THEN pu.id END) as verified,
                COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN pu.id END) as pending,
                COUNT(DISTINCT CASE WHEN r.status = 'flagged' THEN pu.id END) as flagged,
                COUNT(DISTINCT CASE WHEN c.id IS NOT NULL THEN pu.id END) as checked_in,
                SUM(r.total_votes_cast) as total_votes
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.election_id = ? AND r.tenant_id = ?
            LEFT JOIN agent_checkins c ON c.pu_id = pu.id AND c.checkin_type = 'arrival' 
                AND c.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            WHERE l.id IN ($lga_list)
            GROUP BY l.id, l.name
            ORDER BY l.name ASC
        ");
        $stmt->execute([$election_id, $tenant_id]);
        $progress_data = $stmt->fetchAll();
        
        foreach ($progress_data as $row) {
            $summary['total_pus'] += $row['total_pus'];
            $summary['submitted'] += $row['submitted'];
            $summary['verified'] += $row['verified'];
            $summary['pending'] += $row['pending'];
            $summary['flagged'] += $row['flagged'];
            $summary['checked_in'] += $row['checked_in'];
            $summary['total_votes'] += $row['total_votes'];
        }
        
        $summary['submission_rate'] = $summary['total_pus'] > 0 ? round(($summary['submitted'] / $summary['total_pus']) * 100, 1) : 0;
        $summary['verification_rate'] = $summary['total_pus'] > 0 ? round(($summary['verified'] / $summary['total_pus']) * 100, 1) : 0;
        $summary['checkin_rate'] = $summary['total_pus'] > 0 ? round(($summary['checked_in'] / $summary['total_pus']) * 100, 1) : 0;
    } catch (Exception $e) {
        error_log("Error fetching progress data: " . $e->getMessage());
    }
}

$page_title = 'Election Progress Report';
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
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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

.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.status-badge.completed { background: #D1FAE5; color: #059669; }
.status-badge.in-progress { background: #DBEAFE; color: #2563EB; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }

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
                    <i class="fas fa-chart-line" style="color:var(--primary);margin-right:8px;"></i> 
                    Election Progress Report
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
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Generate Report</button>
                <a href="report-progress.php" class="btn-filter" style="background:var(--gray-100);color:var(--gray-600);">Reset</a>
            </form>
        </div>

        <?php if ($election_id > 0 && $lga_list !== '0'): ?>
            <!-- Summary Stats -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-number"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="stat-label">Total PUs</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-number"><?php echo number_format($summary['submitted']); ?></div>
                    <div class="stat-label">Results Submitted</div>
                    <div class="stat-change"><?php echo $summary['submission_rate']; ?>%</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-number"><?php echo number_format($summary['verified']); ?></div>
                    <div class="stat-label">Verified</div>
                    <div class="stat-change"><?php echo $summary['verification_rate']; ?>%</div>
                </div>
                <div class="stat-card yellow">
                    <div class="stat-number"><?php echo number_format($summary['pending']); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-number"><?php echo number_format($summary['flagged']); ?></div>
                    <div class="stat-label">Flagged</div>
                </div>
                <div class="stat-card teal">
                    <div class="stat-number"><?php echo number_format($summary['checked_in']); ?></div>
                    <div class="stat-label">Checked In</div>
                    <div class="stat-change"><?php echo $summary['checkin_rate']; ?>%</div>
                </div>
            </div>

            <!-- LGA Progress Table -->
            <div class="section-card">
                <div class="card-header">
                    <h3><i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:6px;"></i> LGA Progress</h3>
                    <span style="font-size:0.75rem;color:var(--gray-400);"><?php echo count($progress_data); ?> LGAs</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>LGA</th>
                                <th>Total PUs</th>
                                <th>Submitted</th>
                                <th>Verified</th>
                                <th>Pending</th>
                                <th>Flagged</th>
                                <th>Checked In</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($progress_data) > 0): ?>
                                <?php $i = 1; foreach ($progress_data as $row): 
                                    $rate = $row['total_pus'] > 0 ? round(($row['submitted'] / $row['total_pus']) * 100, 1) : 0;
                                    $status = $rate >= 100 ? 'completed' : ($rate > 0 ? 'in-progress' : 'pending');
                                    $status_label = $rate >= 100 ? 'Completed' : ($rate > 0 ? 'In Progress' : 'Pending');
                                    $color = $rate >= 80 ? 'green' : ($rate >= 50 ? 'blue' : ($rate >= 30 ? 'yellow' : 'red'));
                                ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['lga_name']); ?></strong></td>
                                        <td><?php echo number_format($row['total_pus']); ?></td>
                                        <td><?php echo number_format($row['submitted']); ?></td>
                                        <td><?php echo number_format($row['verified']); ?></td>
                                        <td><?php echo number_format($row['pending']); ?></td>
                                        <td><?php echo number_format($row['flagged']); ?></td>
                                        <td><?php echo number_format($row['checked_in']); ?></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <span style="font-size:0.75rem;font-weight:600;min-width:40px;"><?php echo $rate; ?>%</span>
                                                <div class="progress-bar" style="flex:1;">
                                                    <div class="progress-fill <?php echo $color; ?>" style="width:<?php echo min($rate, 100); ?>%;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $status; ?>"><?php echo $status_label; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10">
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p>No data available for the selected election.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($election_id > 0): ?>
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle"></i>
                <p>No LGAs found in your senatorial district.</p>
                <p style="font-size:0.8rem;margin-top:4px;">Please ensure LGAs are assigned to your district.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h3>Select an Election</h3>
                <p>Choose an election from the dropdown above to view the progress report.</p>
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