<?php
// ============================================================
// SENATORIAL COORDINATOR - RESULTS BY WARD
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
// GET SELECTED WARD
// ============================================================
$selected_ward = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;

// ============================================================
// GET WARDS FOR FILTER
// ============================================================
$wards = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT w.id, w.name, w.lga_id, l.name as lga_name
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            WHERE l.id IN ($lga_list) AND w.is_active = 1
            ORDER BY l.name ASC, w.name ASC
        ");
        $stmt->execute();
        $wards = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}

// ============================================================
// GET WARD DATA WITH RESULTS
// ============================================================
$ward_data = null;
$pu_results = [];

if ($selected_ward > 0) {
    try {
        // Get Ward details
        $stmt = $db->prepare("
            SELECT w.id, w.name, w.code, w.lga_id, l.name as lga_name
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            WHERE w.id = ? AND w.is_active = 1
        ");
        $stmt->execute([$selected_ward]);
        $ward_data = $stmt->fetch();

        // Get results by Polling Unit within this Ward
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
                r.verified_at,
                u.full_name as agent_name,
                v.full_name as verified_by_name,
                r.photo_url,
                r.remarks
            FROM polling_units pu
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            LEFT JOIN users u ON r.agent_id = u.id
            LEFT JOIN users v ON r.verified_by = v.id
            WHERE pu.ward_id = ? AND pu.is_active = 1
            ORDER BY pu.name ASC
        ");
        $stmt->execute([$tenant_id, $selected_ward]);
        $pu_results = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching ward data: " . $e->getMessage());
    }
}

// ============================================================
// GET SUMMARY STATISTICS
// ============================================================
$summary = [
    'total_pus' => count($pu_results),
    'submitted' => 0,
    'verified' => 0,
    'total_votes' => 0,
    'valid_votes' => 0,
    'rejected_votes' => 0,
    'total_voters' => 0
];

foreach ($pu_results as $pu) {
    if ($pu['result_id']) {
        $summary['submitted']++;
        if ($pu['result_status'] === 'verified') $summary['verified']++;
    }
    $summary['total_votes'] += $pu['total_votes_cast'] ?? 0;
    $summary['valid_votes'] += $pu['valid_votes'] ?? 0;
    $summary['rejected_votes'] += $pu['rejected_votes'] ?? 0;
    $summary['total_voters'] += $pu['registered_voters'] ?? 0;
}

$summary['submission_rate'] = $summary['total_pus'] > 0 ? round(($summary['submitted'] / $summary['total_pus']) * 100, 1) : 0;

$page_title = 'Results by Ward';
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
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
.status-badge.rejected { background: #FEE2E2; color: #DC2626; }

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

.results-actions {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
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
                    Results by Ward
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
                <select name="ward_id" required>
                    <option value="">Select Ward...</option>
                    <?php foreach ($wards as $ward): ?>
                        <option value="<?php echo $ward['id']; ?>" <?php echo ($selected_ward == $ward['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ward['name']); ?> (<?php echo htmlspecialchars($ward['lga_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> View Results</button>
            </form>
        </div>

        <?php if ($selected_ward > 0 && $ward_data): ?>
            <!-- Ward Header -->
            <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:20px;margin-bottom:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                    <div>
                        <h3 style="font-size:1.1rem;font-weight:700;margin:0;">
                            <?php echo htmlspecialchars($ward_data['name']); ?>
                        </h3>
                        <p style="color:var(--gray-500);font-size:0.85rem;margin:4px 0 0;">
                            <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($ward_data['code']); ?>
                            <span style="margin-left:16px;">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ward_data['lga_name']); ?>
                            </span>
                            <span style="margin-left:16px;">
                                <i class="fas fa-flag-checkered"></i> <?php echo number_format($summary['total_pus']); ?> PUs
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
                </div>
                <div class="stat-card orange">
                    <div class="stat-number"><?php echo number_format($summary['total_votes']); ?></div>
                    <div class="stat-label">Total Votes Cast</div>
                </div>
                <div class="stat-card teal">
                    <div class="stat-number"><?php echo number_format($summary['total_voters']); ?></div>
                    <div class="stat-label">Registered Voters</div>
                </div>
            </div>

            <!-- PU Results Table -->
            <div class="section-card">
                <div class="card-header">
                    <h3><i class="fas fa-flag-checkered" style="color:var(--primary);margin-right:6px;"></i> Polling Unit Results</h3>
                    <span style="font-size:0.75rem;color:var(--gray-400);"><?php echo count($pu_results); ?> polling units</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Polling Unit</th>
                                <th>Code</th>
                                <th>Voters</th>
                                <th>Accredited</th>
                                <th>Votes Cast</th>
                                <th>Valid</th>
                                <th>Rejected</th>
                                <th>Status</th>
                                <th>Agent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($pu_results) > 0): ?>
                                <?php $i = 1; foreach ($pu_results as $pu): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($pu['pu_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($pu['pu_code']); ?></td>
                                        <td><?php echo number_format($pu['registered_voters'] ?? 0); ?></td>
                                        <td><?php echo number_format($pu['accredited_voters'] ?? 0); ?></td>
                                        <td><?php echo number_format($pu['total_votes_cast'] ?? 0); ?></td>
                                        <td><?php echo number_format($pu['valid_votes'] ?? 0); ?></td>
                                        <td><?php echo number_format($pu['rejected_votes'] ?? 0); ?></td>
                                        <td>
                                            <?php if ($pu['result_id']): ?>
                                                <span class="status-badge <?php echo $pu['result_status'] ?? 'pending'; ?>">
                                                    <?php echo ucfirst($pu['result_status'] ?? 'Pending'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.7rem;color:var(--gray-600);">
                                            <?php echo htmlspecialchars($pu['agent_name'] ?? '—'); ?>
                                        </td>
                                        <td>
                                            <div class="results-actions">
                                                <a href="pu-details.php?id=<?php echo $pu['pu_id']; ?>" class="btn-sm">View</a>
                                                <?php if ($pu['result_id']): ?>
                                                    <a href="result-details.php?id=<?php echo $pu['result_id']; ?>" class="btn-sm outline">Result</a>
                                                <?php endif; ?>
                                                <?php if ($pu['photo_url']): ?>
                                                    <a href="<?php echo $pu['photo_url']; ?>" target="_blank" class="btn-sm outline"><i class="fas fa-image"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11">
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p>No polling units found in this ward.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($selected_ward > 0): ?>
            <div class="empty-state">
                <i class="fas fa-layer-group"></i>
                <p>Ward not found.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-layer-group"></i>
                <h3>Select a Ward</h3>
                <p>Choose a ward from the dropdown above to view results.</p>
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