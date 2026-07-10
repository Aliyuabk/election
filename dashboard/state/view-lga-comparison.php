<?php
// ============================================================
// STATE COORDINATOR - VIEW LGA COMPARISON DETAILS
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
$lga_id = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

if ($lga_id <= 0 || $election_id <= 0) {
    header('Location: compare-results.php');
    exit();
}

// Get LGA name
$lga_name = 'Unknown LGA';
try {
    $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
    $stmt->execute([$lga_id]);
    $lga = $stmt->fetch(PDO::FETCH_ASSOC);
    $lga_name = $lga['name'] ?? 'Unknown LGA';
} catch (Exception $e) {
    error_log("Error fetching LGA: " . $e->getMessage());
}

// Get election name
$election_name = 'Unknown Election';
try {
    $stmt = $db->prepare("SELECT name FROM elections WHERE id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch(PDO::FETCH_ASSOC);
    $election_name = $election['name'] ?? 'Unknown Election';
} catch (Exception $e) {
    error_log("Error fetching election: " . $e->getMessage());
}

// Get ward-level comparison data
$wards_comparison = [];
$summary = [
    'total_wards' => 0,
    'ec8a_votes' => 0,
    'ec8b_votes' => 0,
    'matches' => 0,
    'mismatches' => 0,
    'total_difference' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            w.id as ward_id,
            w.name as ward_name,
            w.code as ward_code,
            COALESCE(SUM(ec8a.valid_votes), 0) as ec8a_votes,
            COALESCE(SUM(ec8b.valid_votes), 0) as ec8b_votes,
            COUNT(DISTINCT ec8a.pu_id) as ec8a_pus,
            CASE 
                WHEN COALESCE(SUM(ec8a.valid_votes), 0) = COALESCE(SUM(ec8b.valid_votes), 0) THEN 'match'
                WHEN ABS(COALESCE(SUM(ec8a.valid_votes), 0) - COALESCE(SUM(ec8b.valid_votes), 0)) <= 5 THEN 'minor'
                ELSE 'mismatch'
            END as comparison_status,
            ABS(COALESCE(SUM(ec8a.valid_votes), 0) - COALESCE(SUM(ec8b.valid_votes), 0)) as vote_difference,
            (SELECT COUNT(*) FROM polling_units pu WHERE pu.ward_id = w.id AND pu.is_active = 1) as total_pus,
            (SELECT COUNT(*) FROM results_ec8a ra 
             JOIN polling_units pu ON ra.pu_id = pu.id 
             WHERE pu.ward_id = w.id AND ra.election_id = ? AND ra.status IN ('verified', 'approved')) as ec8a_submitted
        FROM wards w
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        LEFT JOIN results_ec8a ec8a ON ec8a.pu_id = pu.id AND ec8a.election_id = ? AND ec8a.tenant_id = ? AND ec8a.status IN ('verified', 'approved')
        LEFT JOIN results_ec8b ec8b ON ec8b.ward_id = w.id AND ec8b.election_id = ? AND ec8b.tenant_id = ? AND ec8b.status IN ('verified', 'approved')
        WHERE w.lga_id = ? AND w.is_active = 1
        GROUP BY w.id, w.name, w.code
        ORDER BY w.name ASC
    ");
    $stmt->execute([$election_id, $election_id, $tenant_id, $election_id, $tenant_id, $lga_id]);
    $wards_comparison = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($wards_comparison as $ward) {
        $summary['total_wards']++;
        $summary['ec8a_votes'] += $ward['ec8a_votes'];
        $summary['ec8b_votes'] += $ward['ec8b_votes'];
        $summary['total_difference'] += $ward['vote_difference'];
        
        if ($ward['comparison_status'] === 'match') {
            $summary['matches']++;
        } else {
            $summary['mismatches']++;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward comparison: " . $e->getMessage());
}

$page_title = 'LGA Comparison';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.comparison-container {
    max-width: 1000px;
    margin: 0 auto;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.summary-stat {
    background: white;
    border-radius: var(--radius);
    padding: 14px 16px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.summary-stat .number {
    font-size: 1.3rem;
    font-weight: 700;
}

.summary-stat .number.match { color: #10B981; }
.summary-stat .number.mismatch { color: #EF4444; }
.summary-stat .number.total { color: #3B82F6; }
.summary-stat .number.diff { color: #F59E0B; }

.summary-stat .label {
    font-size: 0.65rem;
    color: var(--gray-500);
}

.comparison-table-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.comparison-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.comparison-table th {
    background: var(--gray-50);
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.comparison-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.comparison-table tr:hover td {
    background: var(--gray-50);
}

.comparison-table .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
}

.comparison-table .status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.comparison-table .status-badge.match { background: #ECFDF5; color: #065F46; }
.comparison-table .status-badge.match .dot { background: #10B981; }
.comparison-table .status-badge.minor { background: #FFFBEB; color: #92400E; }
.comparison-table .status-badge.minor .dot { background: #F59E0B; }
.comparison-table .status-badge.mismatch { background: #FEF2F2; color: #991B1B; }
.comparison-table .status-badge.mismatch .dot { background: #EF4444; }

.comparison-table .match-indicator {
    font-size: 1.1rem;
}

.comparison-table .match-indicator.match { color: #10B981; }
.comparison-table .match-indicator.minor { color: #F59E0B; }
.comparison-table .match-indicator.mismatch { color: #EF4444; }

.comparison-table .vote-diff {
    font-weight: 600;
}

.comparison-table .vote-diff.match { color: #10B981; }
.comparison-table .vote-diff.minor { color: #F59E0B; }
.comparison-table .vote-diff.mismatch { color: #EF4444; }

.comparison-table .ward-progress {
    width: 80px;
    display: inline-block;
}

.comparison-table .ward-progress .progress-bar {
    height: 4px;
    background: var(--gray-200);
    border-radius: 2px;
    overflow: hidden;
}

.comparison-table .ward-progress .progress-bar .fill {
    height: 100%;
    background: var(--primary);
    border-radius: 2px;
    transition: width 0.8s ease;
}

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
    .summary-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    .comparison-table-container {
        overflow-x: auto;
    }
    .comparison-table {
        font-size: 0.7rem;
    }
    .comparison-table th,
    .comparison-table td {
        padding: 6px 10px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="comparison-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-balance-scale"></i> LGA Comparison</h1>
                    <p class="subtitle">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($lga_name); ?> LGA - 
                        <?php echo htmlspecialchars($election_name); ?>
                    </p>
                </div>
                <div class="actions">
                    <a href="compare-results.php?election_id=<?php echo $election_id; ?>" class="btn-secondary-sm">
                        <i class="fas fa-arrow-left"></i> Back to Comparison
                    </a>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="summary-stats">
                <div class="summary-stat">
                    <div class="number total"><?php echo number_format($summary['total_wards']); ?></div>
                    <div class="label">Total Wards</div>
                </div>
                <div class="summary-stat">
                    <div class="number match"><?php echo number_format($summary['matches']); ?></div>
                    <div class="label">Matching</div>
                </div>
                <div class="summary-stat">
                    <div class="number mismatch"><?php echo number_format($summary['mismatches']); ?></div>
                    <div class="label">Mismatches</div>
                </div>
                <div class="summary-stat">
                    <div class="number"><?php echo number_format($summary['ec8a_votes']); ?></div>
                    <div class="label">EC8A Total</div>
                </div>
                <div class="summary-stat">
                    <div class="number"><?php echo number_format($summary['ec8b_votes']); ?></div>
                    <div class="label">EC8B Total</div>
                </div>
                <div class="summary-stat">
                    <div class="number diff"><?php echo number_format($summary['total_difference']); ?></div>
                    <div class="label">Total Difference</div>
                </div>
            </div>

            <!-- Ward Comparison Table -->
            <div class="comparison-table-container">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Ward</th>
                            <th>EC8A Votes</th>
                            <th>EC8B Votes</th>
                            <th>Difference</th>
                            <th>Status</th>
                            <th>PU Progress</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wards_comparison as $ward): 
                            $status_class = $ward['comparison_status'];
                            $progress = $ward['total_pus'] > 0 ? round(($ward['ec8a_submitted'] / $ward['total_pus']) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($ward['ward_name']); ?></strong>
                                    <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($ward['ward_code']); ?></div>
                                </td>
                                <td><?php echo number_format($ward['ec8a_votes']); ?></td>
                                <td><?php echo number_format($ward['ec8b_votes']); ?></td>
                                <td>
                                    <span class="vote-diff <?php echo $status_class; ?>">
                                        <?php echo $ward['vote_difference'] > 0 ? '+' : ''; ?>
                                        <?php echo number_format($ward['vote_difference']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($ward['comparison_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="ward-progress">
                                        <div class="progress-bar">
                                            <div class="fill" style="width: <?php echo $progress; ?>%;"></div>
                                        </div>
                                        <div style="font-size:0.6rem;color:var(--gray-500);text-align:center;margin-top:2px;">
                                            <?php echo number_format($ward['ec8a_submitted']); ?>/<?php echo number_format($ward['total_pus']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="view-ward-comparison.php?ward_id=<?php echo $ward['ward_id']; ?>&election_id=<?php echo $election_id; ?>" 
                                       style="padding:2px 12px;border-radius:4px;font-size:0.6rem;font-weight:500;text-decoration:none;background:var(--primary);color:white;transition:var(--transition);">
                                        <i class="fas fa-eye"></i> Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($wards_comparison)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-balance-scale"></i>
                                        <h4>No Data Available</h4>
                                        <p>No wards found for this LGA or no results submitted.</p>
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