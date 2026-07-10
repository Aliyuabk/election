<?php
// ============================================================
// STATE COORDINATOR - VIEW WARD COMPARISON DETAILS
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
$ward_id = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

if ($ward_id <= 0 || $election_id <= 0) {
    header('Location: compare-results.php');
    exit();
}

// Get ward name
$ward_name = 'Unknown Ward';
$lga_name = 'Unknown LGA';
try {
    $stmt = $db->prepare("
        SELECT w.name, w.code, l.name as lga_name 
        FROM wards w 
        JOIN lgas l ON w.lga_id = l.id 
        WHERE w.id = ?
    ");
    $stmt->execute([$ward_id]);
    $ward = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ward) {
        $ward_name = $ward['name'];
        $lga_name = $ward['lga_name'];
    }
} catch (Exception $e) {
    error_log("Error fetching ward: " . $e->getMessage());
}

// Get PU-level comparison data
$pus_comparison = [];
$summary = [
    'total_pus' => 0,
    'ec8a_votes' => 0,
    'ec8b_votes' => 0,
    'matches' => 0,
    'mismatches' => 0,
    'total_difference' => 0,
    'reported_pus' => 0,
    'missing_pus' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            pu.id as pu_id,
            pu.name as pu_name,
            pu.code as pu_code,
            COALESCE(ec8a.valid_votes, 0) as ec8a_votes,
            COALESCE(ec8b.valid_votes, 0) as ec8b_votes,
            ec8a.id as ec8a_id,
            ec8b.id as ec8b_id,
            ec8a.status as ec8a_status,
            ec8b.status as ec8b_status,
            CASE 
                WHEN ec8a.id IS NULL THEN 'no_ec8a'
                WHEN ec8b.id IS NULL THEN 'no_ec8b'
                WHEN COALESCE(ec8a.valid_votes, 0) = COALESCE(ec8b.valid_votes, 0) THEN 'match'
                WHEN ABS(COALESCE(ec8a.valid_votes, 0) - COALESCE(ec8b.valid_votes, 0)) <= 5 THEN 'minor'
                ELSE 'mismatch'
            END as comparison_status,
            ABS(COALESCE(ec8a.valid_votes, 0) - COALESCE(ec8b.valid_votes, 0)) as vote_difference
        FROM polling_units pu
        LEFT JOIN results_ec8a ec8a ON ec8a.pu_id = pu.id AND ec8a.election_id = ? AND ec8a.tenant_id = ? AND ec8a.status IN ('verified', 'approved')
        LEFT JOIN results_ec8b ec8b ON ec8b.ward_id = pu.ward_id AND ec8b.election_id = ? AND ec8b.tenant_id = ? AND ec8b.status IN ('verified', 'approved')
        WHERE pu.ward_id = ? AND pu.is_active = 1
        ORDER BY pu.name ASC
    ");
    $stmt->execute([$election_id, $tenant_id, $election_id, $tenant_id, $ward_id]);
    $pus_comparison = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($pus_comparison as $pu) {
        $summary['total_pus']++;
        $summary['ec8a_votes'] += $pu['ec8a_votes'];
        $summary['ec8b_votes'] += $pu['ec8b_votes'];
        $summary['total_difference'] += $pu['vote_difference'];
        
        if ($pu['comparison_status'] === 'match') {
            $summary['matches']++;
        } elseif ($pu['comparison_status'] === 'no_ec8a' || $pu['comparison_status'] === 'no_ec8b') {
            $summary['missing_pus']++;
        } else {
            $summary['mismatches']++;
        }
        
        if ($pu['ec8a_id'] || $pu['ec8b_id']) {
            $summary['reported_pus']++;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching PU comparison: " . $e->getMessage());
}

$page_title = 'Ward Comparison';
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
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.summary-stat {
    background: white;
    border-radius: var(--radius);
    padding: 12px 14px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.summary-stat .number {
    font-size: 1.2rem;
    font-weight: 700;
}

.summary-stat .number.match { color: #10B981; }
.summary-stat .number.mismatch { color: #EF4444; }
.summary-stat .number.total { color: #3B82F6; }
.summary-stat .number.diff { color: #F59E0B; }
.summary-stat .number.missing { color: #8B5CF6; }

.summary-stat .label {
    font-size: 0.6rem;
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
    font-size: 0.8rem;
}

.comparison-table th {
    background: var(--gray-50);
    padding: 8px 12px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.comparison-table td {
    padding: 8px 12px;
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
    font-size: 0.5rem;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.comparison-table .status-badge .dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    display: inline-block;
}

.comparison-table .status-badge.match { background: #ECFDF5; color: #065F46; }
.comparison-table .status-badge.match .dot { background: #10B981; }
.comparison-table .status-badge.minor { background: #FFFBEB; color: #92400E; }
.comparison-table .status-badge.minor .dot { background: #F59E0B; }
.comparison-table .status-badge.mismatch { background: #FEF2F2; color: #991B1B; }
.comparison-table .status-badge.mismatch .dot { background: #EF4444; }
.comparison-table .status-badge.no_ec8a { background: #F3F4F6; color: #6B7280; }
.comparison-table .status-badge.no_ec8a .dot { background: #9CA3AF; }
.comparison-table .status-badge.no_ec8b { background: #FEF3C7; color: #92400E; }
.comparison-table .status-badge.no_ec8b .dot { background: #F59E0B; }

.comparison-table .vote-diff {
    font-weight: 600;
}

.comparison-table .vote-diff.match { color: #10B981; }
.comparison-table .vote-diff.minor { color: #F59E0B; }
.comparison-table .vote-diff.mismatch { color: #EF4444; }

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

.status-label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

@media (max-width: 768px) {
    .summary-stats {
        grid-template-columns: repeat(3, 1fr);
    }
    .comparison-table-container {
        overflow-x: auto;
    }
    .comparison-table {
        font-size: 0.65rem;
    }
    .comparison-table th,
    .comparison-table td {
        padding: 4px 8px;
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
                    <h1><i class="fas fa-balance-scale"></i> Ward Comparison</h1>
                    <p class="subtitle">
                        <i class="fas fa-layer-group"></i> 
                        <?php echo htmlspecialchars($ward_name); ?> Ward - 
                        <?php echo htmlspecialchars($lga_name); ?> LGA
                    </p>
                </div>
                <div class="actions">
                    <a href="view-lga-comparison.php?lga_id=<?php echo $ward_id; ?>&election_id=<?php echo $election_id; ?>" class="btn-secondary-sm">
                        <i class="fas fa-arrow-left"></i> Back to LGA
                    </a>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="summary-stats">
                <div class="summary-stat">
                    <div class="number total"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="label">Total PUs</div>
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
                    <div class="number missing"><?php echo number_format($summary['missing_pus']); ?></div>
                    <div class="label">Missing</div>
                </div>
                <div class="summary-stat">
                    <div class="number"><?php echo number_format($summary['reported_pus']); ?></div>
                    <div class="label">Reported</div>
                </div>
                <div class="summary-stat">
                    <div class="number diff"><?php echo number_format($summary['total_difference']); ?></div>
                    <div class="label">Total Diff</div>
                </div>
            </div>

            <!-- PU Comparison Table -->
            <div class="comparison-table-container">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Polling Unit</th>
                            <th>EC8A Votes</th>
                            <th>EC8A Status</th>
                            <th>EC8B Votes</th>
                            <th>EC8B Status</th>
                            <th>Difference</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pus_comparison as $pu): 
                            $status_class = $pu['comparison_status'];
                            $status_label = $pu['comparison_status'];
                            if ($status_label === 'no_ec8a') $status_label = 'No EC8A';
                            elseif ($status_label === 'no_ec8b') $status_label = 'No EC8B';
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($pu['pu_name']); ?></strong>
                                    <div style="font-size:0.55rem;color:var(--gray-400);"><?php echo htmlspecialchars($pu['pu_code']); ?></div>
                                </td>
                                <td><?php echo number_format($pu['ec8a_votes']); ?></td>
                                <td>
                                    <?php if ($pu['ec8a_id']): ?>
                                        <span class="status-badge <?php echo $pu['ec8a_status']; ?>">
                                            <span class="dot"></span>
                                            <?php echo ucfirst($pu['ec8a_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-label">Not submitted</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($pu['ec8b_votes']); ?></td>
                                <td>
                                    <?php if ($pu['ec8b_id']): ?>
                                        <span class="status-badge <?php echo $pu['ec8b_status']; ?>">
                                            <span class="dot"></span>
                                            <?php echo ucfirst($pu['ec8b_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-label">Not submitted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="vote-diff <?php echo $status_class; ?>">
                                        <?php echo $pu['vote_difference'] > 0 ? '+' : ''; ?>
                                        <?php echo number_format($pu['vote_difference']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($status_label); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($pus_comparison)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-balance-scale"></i>
                                        <h4>No Data Available</h4>
                                        <p>No polling units found for this ward.</p>
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