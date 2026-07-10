<?php
// ============================================================
// STATE COORDINATOR - COMPARE RESULTS
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
$lga_id = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get elections for filter
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, status 
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

// Get LGAs for filter
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// Comparison data
$comparison_data = [];
$summary = [
    'total_compared' => 0,
    'matches' => 0,
    'mismatches' => 0,
    'total_votes_ec8a' => 0,
    'total_votes_ec8b' => 0,
    'difference' => 0
];

if ($election_id > 0) {
    try {
        // Compare EC8A vs EC8B results per LGA
        $sql = "
            SELECT 
                l.id as lga_id,
                l.name as lga_name,
                COALESCE(SUM(ec8a.valid_votes), 0) as ec8a_votes,
                COALESCE(SUM(ec8b.valid_votes), 0) as ec8b_votes,
                COUNT(DISTINCT ec8a.pu_id) as ec8a_pus,
                COUNT(DISTINCT ec8b.ward_id) as ec8b_wards,
                COALESCE(SUM(ec8a.total_votes_cast), 0) as ec8a_total,
                COALESCE(SUM(ec8b.total_votes), 0) as ec8b_total,
                CASE 
                    WHEN COALESCE(SUM(ec8a.valid_votes), 0) = COALESCE(SUM(ec8b.valid_votes), 0) THEN 'match'
                    WHEN ABS(COALESCE(SUM(ec8a.valid_votes), 0) - COALESCE(SUM(ec8b.valid_votes), 0)) <= 5 THEN 'minor'
                    ELSE 'mismatch'
                END as comparison_status,
                ABS(COALESCE(SUM(ec8a.valid_votes), 0) - COALESCE(SUM(ec8b.valid_votes), 0)) as vote_difference
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            LEFT JOIN results_ec8a ec8a ON ec8a.pu_id = pu.id AND ec8a.election_id = ? AND ec8a.tenant_id = ? AND ec8a.status IN ('verified', 'approved')
            LEFT JOIN results_ec8b ec8b ON ec8b.ward_id = w.id AND ec8b.election_id = ? AND ec8b.tenant_id = ? AND ec8b.status IN ('verified', 'approved')
            WHERE l.state_id = ? AND l.is_active = 1
        ";
        
        $params = [$election_id, $tenant_id, $election_id, $tenant_id, $state_id];
        
        if ($lga_id > 0) {
            $sql .= " AND l.id = ?";
            $params[] = $lga_id;
        }
        
        $sql .= " GROUP BY l.id, l.name ORDER BY l.name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $comparison_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate summary
        foreach ($comparison_data as $data) {
            $summary['total_compared']++;
            $summary['total_votes_ec8a'] += $data['ec8a_votes'];
            $summary['total_votes_ec8b'] += $data['ec8b_votes'];
            $summary['difference'] += $data['vote_difference'];
            
            if ($data['comparison_status'] === 'match') {
                $summary['matches']++;
            } elseif ($data['comparison_status'] === 'minor') {
                $summary['mismatches']++;
            } else {
                $summary['mismatches']++;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching comparison data: " . $e->getMessage());
    }
}

$page_title = 'Compare Results';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
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
    min-width: 180px;
}

.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-bar .btn-compare {
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

.filter-bar .btn-compare:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
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

.no-data {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-500);
}

.no-data i {
    font-size: 2rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 8px;
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
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-balance-scale"></i> Compare Results</h1>
                <p class="subtitle">
                    <i class="fas fa-flag"></i> 
                    <?php echo htmlspecialchars($state_name); ?> State - Compare EC8A vs EC8B Results
                </p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <select id="electionSelect">
                <option value="">Select Election...</option>
                <?php foreach ($elections as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo $election_id == $e['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['name']); ?> (<?php echo ucfirst($e['status']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="lgaSelect">
                <option value="0">All LGAs</option>
                <?php foreach ($lgas as $l): ?>
                    <option value="<?php echo $l['id']; ?>" <?php echo $lga_id == $l['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($l['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="btn-compare" onclick="applyFilters()">
                <i class="fas fa-balance-scale"></i> Compare
            </button>
        </div>

        <?php if ($election_id > 0 && !empty($comparison_data)): ?>
            <!-- Summary Stats -->
            <div class="summary-stats">
                <div class="summary-stat">
                    <div class="number total"><?php echo number_format($summary['total_compared']); ?></div>
                    <div class="label">LGAs Compared</div>
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
                    <div class="number diff"><?php echo number_format($summary['difference']); ?></div>
                    <div class="label">Total Difference</div>
                </div>
                <div class="summary-stat">
                    <div class="number"><?php echo number_format($summary['total_votes_ec8a']); ?></div>
                    <div class="label">EC8A Total Votes</div>
                </div>
                <div class="summary-stat">
                    <div class="number"><?php echo number_format($summary['total_votes_ec8b']); ?></div>
                    <div class="label">EC8B Total Votes</div>
                </div>
            </div>

            <!-- Comparison Table -->
            <div class="comparison-table-container">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>LGA</th>
                            <th>EC8A Votes</th>
                            <th>EC8B Votes</th>
                            <th>Difference</th>
                            <th>Status</th>
                            <th>PUs</th>
                            <th>Wards</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparison_data as $data): 
                            $status_class = $data['comparison_status'];
                            $status_icon = $data['comparison_status'] === 'match' ? '✓' : ($data['comparison_status'] === 'minor' ? '~' : '✗');
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($data['lga_name']); ?></strong></td>
                                <td><?php echo number_format($data['ec8a_votes']); ?></td>
                                <td><?php echo number_format($data['ec8b_votes']); ?></td>
                                <td>
                                    <span class="vote-diff <?php echo $status_class; ?>">
                                        <?php echo $data['vote_difference'] > 0 ? '+' : ''; ?>
                                        <?php echo number_format($data['vote_difference']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($data['comparison_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($data['ec8a_pus']); ?></td>
                                <td><?php echo number_format($data['ec8b_wards']); ?></td>
                                <td>
                                    <a href="view-lga-comparison.php?lga_id=<?php echo $data['lga_id']; ?>&election_id=<?php echo $election_id; ?>" 
                                       class="btn-view" style="padding:2px 12px;border-radius:4px;font-size:0.6rem;font-weight:500;text-decoration:none;background:var(--primary);color:white;transition:var(--transition);">
                                        <i class="fas fa-eye"></i> Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($election_id > 0 && empty($comparison_data)): ?>
            <div class="empty-state">
                <i class="fas fa-balance-scale"></i>
                <h4>No Data Available</h4>
                <p>No results available for comparison in the selected election.</p>
                <p style="font-size:0.75rem;color:var(--gray-400);">Make sure EC8A and EC8B results have been submitted and verified.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-balance-scale"></i>
                <h4>Select an Election</h4>
                <p>Please select an election and click Compare to view result comparisons.</p>
                <p style="font-size:0.75rem;color:var(--gray-400);">This tool compares EC8A (Polling Unit) results with EC8B (Ward) results.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
function applyFilters() {
    var election = document.getElementById('electionSelect').value;
    var lga = document.getElementById('lgaSelect').value;
    
    if (!election) {
        alert('Please select an election to compare.');
        return;
    }
    
    var url = window.location.pathname + '?election_id=' + election;
    if (lga) url += '&lga_id=' + lga;
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