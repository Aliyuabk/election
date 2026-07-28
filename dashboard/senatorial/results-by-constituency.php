<?php
// ============================================================
// SENATORIAL COORDINATOR - RESULTS BY FEDERAL CONSTITUENCY
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
// GET SELECTED CONSTITUENCY
// ============================================================
$constituency_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ============================================================
// GET CONSTITUENCIES FOR FILTER
// ============================================================
$constituencies = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, code 
        FROM federal_constituencies 
        WHERE state_id = ? AND is_active = 1
        ORDER BY name ASC
    ");
    $stmt->execute([$state_id]);
    $constituencies = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching constituencies: " . $e->getMessage());
}

// ============================================================
// GET CONSTITUENCY DATA WITH RESULTS
// ============================================================
$constituency_data = null;
$lga_results = [];

if ($constituency_id > 0) {
    try {
        // Get constituency details
        $stmt = $db->prepare("
            SELECT fc.id, fc.name, fc.code,
                   COUNT(DISTINCT l.id) as lga_count,
                   COUNT(DISTINCT w.id) as ward_count,
                   COUNT(DISTINCT pu.id) as pu_count
            FROM federal_constituencies fc
            LEFT JOIN lgas l ON l.state_id = fc.state_id
            LEFT JOIN wards w ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id
            WHERE fc.id = ? AND fc.is_active = 1
            GROUP BY fc.id, fc.name, fc.code
        ");
        $stmt->execute([$constituency_id]);
        $constituency_data = $stmt->fetch();

        // Get results by LGA within this constituency
        // Note: This uses the lgas_json from federal_constituencies to filter LGAs
        $stmt = $db->prepare("
            SELECT 
                l.id as lga_id,
                l.name as lga_name,
                COUNT(DISTINCT pu.id) as total_pus,
                COUNT(DISTINCT CASE WHEN r.id IS NOT NULL THEN pu.id END) as results_submitted,
                COUNT(DISTINCT CASE WHEN r.status = 'verified' THEN pu.id END) as results_verified,
                SUM(r.total_votes_cast) as total_votes
            FROM federal_constituencies fc
            JOIN lgas l ON l.state_id = fc.state_id
            LEFT JOIN wards w ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            WHERE fc.id = ? AND l.is_active = 1
            GROUP BY l.id, l.name
            ORDER BY l.name ASC
        ");
        $stmt->execute([$tenant_id, $constituency_id]);
        $lga_results = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching constituency data: " . $e->getMessage());
    }
}

// ============================================================
// GET SUMMARY STATISTICS
// ============================================================
$summary = [
    'total_pus' => 0,
    'submitted' => 0,
    'verified' => 0,
    'total_votes' => 0
];

foreach ($lga_results as $lga) {
    $summary['total_pus'] += $lga['total_pus'];
    $summary['submitted'] += $lga['results_submitted'];
    $summary['verified'] += $lga['results_verified'];
    $summary['total_votes'] += $lga['total_votes'];
}

$summary['submission_rate'] = $summary['total_pus'] > 0 ? round(($summary['submitted'] / $summary['total_pus']) * 100, 1) : 0;
$summary['verification_rate'] = $summary['total_pus'] > 0 ? round(($summary['verified'] / $summary['total_pus']) * 100, 1) : 0;

$page_title = 'Results by Federal Constituency';
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
                    <i class="fas fa-building" style="color:var(--primary);margin-right:8px;"></i> 
                    Results by Federal Constituency
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="view-results.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back to Results
                </a>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-section">
            <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;">
                <select name="id" required>
                    <option value="">Select Federal Constituency...</option>
                    <?php foreach ($constituencies as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($constituency_id == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['code']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> View Results</button>
            </form>
        </div>

        <?php if ($constituency_id > 0 && $constituency_data): ?>
            <!-- Constituency Header -->
            <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:20px;margin-bottom:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                    <div>
                        <h3 style="font-size:1.1rem;font-weight:700;margin:0;">
                            <?php echo htmlspecialchars($constituency_data['name']); ?>
                        </h3>
                        <p style="color:var(--gray-500);font-size:0.85rem;margin:4px 0 0;">
                            <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($constituency_data['code']); ?>
                            <span style="margin-left:16px;">
                                <i class="fas fa-map-marker-alt"></i> <?php echo number_format($constituency_data['lga_count'] ?? 0); ?> LGAs
                            </span>
                            <span style="margin-left:16px;">
                                <i class="fas fa-layer-group"></i> <?php echo number_format($constituency_data['ward_count'] ?? 0); ?> Wards
                            </span>
                            <span style="margin-left:16px;">
                                <i class="fas fa-flag-checkered"></i> <?php echo number_format($constituency_data['pu_count'] ?? 0); ?> PUs
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
                    <div class="stat-label">Results Submitted</div>
                    <div class="stat-change"><?php echo $summary['submission_rate']; ?>% of total</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-number"><?php echo number_format($summary['verified']); ?></div>
                    <div class="stat-label">Verified Results</div>
                    <div class="stat-change"><?php echo $summary['verification_rate']; ?>% of total</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-number"><?php echo number_format($summary['total_votes']); ?></div>
                    <div class="stat-label">Total Votes Cast</div>
                </div>
            </div>

            <!-- LGA Results Table -->
            <div class="section-card">
                <div class="card-header">
                    <h3><i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:6px;"></i> Results by LGA</h3>
                    <span style="font-size:0.75rem;color:var(--gray-400);"><?php echo count($lga_results); ?> LGAs</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>LGA Name</th>
                                <th>Total PUs</th>
                                <th>Submitted</th>
                                <th>Verified</th>
                                <th>Total Votes</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lga_results) > 0): ?>
                                <?php $i = 1; foreach ($lga_results as $lga): 
                                    $rate = $lga['total_pus'] > 0 ? round(($lga['results_submitted'] / $lga['total_pus']) * 100, 1) : 0;
                                    $status = $rate >= 100 ? 'completed' : ($rate > 0 ? 'in-progress' : 'pending');
                                    $status_label = $rate >= 100 ? 'Completed' : ($rate > 0 ? 'In Progress' : 'Pending');
                                    $color = $rate >= 80 ? 'green' : ($rate >= 50 ? 'blue' : ($rate >= 30 ? 'yellow' : 'red'));
                                ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($lga['lga_name']); ?></strong></td>
                                        <td><?php echo number_format($lga['total_pus']); ?></td>
                                        <td><?php echo number_format($lga['results_submitted']); ?></td>
                                        <td><?php echo number_format($lga['results_verified']); ?></td>
                                        <td><?php echo number_format($lga['total_votes'] ?? 0); ?></td>
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
                                        <td>
                                            <a href="results-by-lga.php?lga_id=<?php echo $lga['lga_id']; ?>" class="btn-sm">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p>No LGA results found for this constituency.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($constituency_id > 0): ?>
            <div class="empty-state">
                <i class="fas fa-building"></i>
                <p>Constituency not found.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-building"></i>
                <h3>Select a Federal Constituency</h3>
                <p>Choose a federal constituency from the dropdown above to view results.</p>
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