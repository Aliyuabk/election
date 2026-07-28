<?php
// ============================================================
// SENATORIAL COORDINATOR - COMPARE RESULTS
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
$comparison_level = isset($_GET['level']) ? $_GET['level'] : 'lga'; // lga, ward, pu
$selected_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$election_id = isset($_GET['election']) ? (int)$_GET['election'] : 0;

// ============================================================
// GET ELECTIONS
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, election_date 
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
// GET COMPARISON DATA
// ============================================================
$comparison_data = [];
$comparison_labels = [];

if ($selected_id > 0 && $election_id > 0) {
    try {
        if ($comparison_level === 'lga') {
            // Compare wards within an LGA
            $stmt = $db->prepare("
                SELECT 
                    w.name as label,
                    COUNT(DISTINCT pu.id) as total_pus,
                    COUNT(DISTINCT CASE WHEN r.id IS NOT NULL THEN pu.id END) as submitted,
                    COUNT(DISTINCT CASE WHEN r.status = 'verified' THEN pu.id END) as verified,
                    SUM(r.total_votes_cast) as total_votes,
                    SUM(r.valid_votes) as valid_votes
                FROM wards w
                LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
                LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.election_id = ? AND r.tenant_id = ?
                WHERE w.lga_id = ? AND w.is_active = 1
                GROUP BY w.id, w.name
                ORDER BY w.name ASC
            ");
            $stmt->execute([$election_id, $tenant_id, $selected_id]);
            $comparison_data = $stmt->fetchAll();
            $comparison_labels = array_column($comparison_data, 'label');
            
        } elseif ($comparison_level === 'ward') {
            // Compare PUs within a Ward
            $stmt = $db->prepare("
                SELECT 
                    pu.name as label,
                    pu.registered_voters as total_pus,
                    CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END as submitted,
                    CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END as verified,
                    r.total_votes_cast as total_votes,
                    r.valid_votes as valid_votes
                FROM polling_units pu
                LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.election_id = ? AND r.tenant_id = ?
                WHERE pu.ward_id = ? AND pu.is_active = 1
                ORDER BY pu.name ASC
            ");
            $stmt->execute([$election_id, $tenant_id, $selected_id]);
            $comparison_data = $stmt->fetchAll();
            $comparison_labels = array_column($comparison_data, 'label');
            
        } elseif ($comparison_level === 'pu') {
            // Compare results within a PU (different elections)
            $stmt = $db->prepare("
                SELECT 
                    e.name as label,
                    r.total_votes_cast as total_votes,
                    r.valid_votes as valid_votes,
                    r.rejected_votes as rejected_votes,
                    r.status,
                    r.created_at
                FROM results_ec8a r
                JOIN elections e ON r.election_id = e.id
                WHERE r.pu_id = ? AND r.tenant_id = ?
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$selected_id, $tenant_id]);
            $comparison_data = $stmt->fetchAll();
            $comparison_labels = array_column($comparison_data, 'label');
        }
    } catch (Exception $e) {
        error_log("Error fetching comparison data: " . $e->getMessage());
    }
}

// ============================================================
// GET LGAS AND WARDS FOR FILTERS
// ============================================================
$lgas = [];
$wards = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
        
        $stmt = $db->prepare("SELECT id, name, lga_id FROM wards WHERE lga_id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $wards = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching filters: " . $e->getMessage());
}

$page_title = 'Compare Results';
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
    min-width: 150px;
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

.chart-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
}
.chart-container .chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.chart-container .chart-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}
.chart-wrapper {
    height: 300px;
    position: relative;
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
                    <i class="fas fa-balance-scale" style="color:var(--primary);margin-right:8px;"></i> 
                    Compare Results
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
            <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <select name="level" onchange="this.form.submit()">
                    <option value="lga" <?php echo ($comparison_level === 'lga') ? 'selected' : ''; ?>>Compare Wards in LGA</option>
                    <option value="ward" <?php echo ($comparison_level === 'ward') ? 'selected' : ''; ?>>Compare PUs in Ward</option>
                    <option value="pu" <?php echo ($comparison_level === 'pu') ? 'selected' : ''; ?>>Compare Results in PU</option>
                </select>
                
                <select name="election" required>
                    <option value="">Select Election...</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo ($election_id == $e['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <?php if ($comparison_level === 'lga'): ?>
                    <select name="id" required>
                        <option value="">Select LGA...</option>
                        <?php foreach ($lgas as $lga): ?>
                            <option value="<?php echo $lga['id']; ?>" <?php echo ($selected_id == $lga['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lga['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($comparison_level === 'ward'): ?>
                    <select name="id" required>
                        <option value="">Select Ward...</option>
                        <?php foreach ($wards as $ward): ?>
                            <option value="<?php echo $ward['id']; ?>" <?php echo ($selected_id == $ward['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ward['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($comparison_level === 'pu'): ?>
                    <select name="id" required>
                        <option value="">Select Polling Unit...</option>
                        <?php
                        $pus = [];
                        try {
                            if ($lga_list !== '0') {
                                $stmt = $db->prepare("
                                    SELECT pu.id, pu.name, pu.code, w.name as ward_name, l.name as lga_name
                                    FROM polling_units pu
                                    JOIN wards w ON pu.ward_id = w.id
                                    JOIN lgas l ON w.lga_id = l.id
                                    WHERE l.id IN ($lga_list) AND pu.is_active = 1
                                    ORDER BY l.name ASC, w.name ASC, pu.name ASC
                                ");
                                $stmt->execute();
                                $pus = $stmt->fetchAll();
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching PUs: " . $e->getMessage());
                        }
                        foreach ($pus as $pu): ?>
                            <option value="<?php echo $pu['id']; ?>" <?php echo ($selected_id == $pu['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pu['name']); ?> (<?php echo htmlspecialchars($pu['code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                
                <button type="submit" class="btn-filter"><i class="fas fa-chart-bar"></i> Compare</button>
            </form>
        </div>

        <?php if (!empty($comparison_data) && $selected_id > 0 && $election_id > 0): ?>
            <!-- Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:6px;"></i> 
                        Comparison Chart - <?php echo ucfirst($comparison_level); ?> Level
                    </h3>
                    <span style="font-size:0.75rem;color:var(--gray-400);">
                        <?php echo count($comparison_data); ?> items compared
                    </span>
                </div>
                <div class="chart-wrapper">
                    <canvas id="comparisonChart"></canvas>
                </div>
            </div>

            <!-- Data Table -->
            <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:20px;">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="font-size:0.95rem;font-weight:600;margin:0;">
                        <i class="fas fa-table" style="color:var(--primary);margin-right:6px;"></i> 
                        Comparison Data
                    </h3>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <?php if ($comparison_level !== 'pu'): ?>
                                    <th>Total PUs</th>
                                    <th>Submitted</th>
                                    <th>Verified</th>
                                    <th>Total Votes</th>
                                    <th>Valid Votes</th>
                                    <th>Submission Rate</th>
                                <?php else: ?>
                                    <th>Total Votes</th>
                                    <th>Valid Votes</th>
                                    <th>Rejected Votes</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($comparison_data as $item): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($item['label']); ?></strong></td>
                                    <?php if ($comparison_level !== 'pu'): ?>
                                        <td><?php echo number_format($item['total_pus'] ?? 0); ?></td>
                                        <td><?php echo number_format($item['submitted'] ?? 0); ?></td>
                                        <td><?php echo number_format($item['verified'] ?? 0); ?></td>
                                        <td><?php echo number_format($item['total_votes'] ?? 0); ?></td>
                                        <td><?php echo number_format($item['valid_votes'] ?? 0); ?></td>
                                        <td>
                                            <?php 
                                            $rate = ($item['total_pus'] ?? 0) > 0 ? round(($item['submitted'] ?? 0) / ($item['total_pus'] ?? 1) * 100, 1) : 0;
                                            echo $rate; ?>%
                                        </td>
                                    <?php else: ?>
                                        <td><?php echo number_format($item['total_votes'] ?? 0); ?></td>
                                        <td><?php echo number_format($item['valid_votes'] ?? 0); ?></td>
                                        <td><?php echo number_format($item['rejected_votes'] ?? 0); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $item['status'] ?? 'pending'; ?>">
                                                <?php echo ucfirst($item['status'] ?? 'Pending'); ?>
                                            </span>
                                        </td>
                                        <td style="font-size:0.7rem;color:var(--gray-500);">
                                            <?php echo date('M j, Y', strtotime($item['created_at'] ?? 'now')); ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($selected_id > 0 && $election_id > 0): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No comparison data found for the selected criteria.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-balance-scale"></i>
                <h3>Select Comparison Criteria</h3>
                <p>Choose an election and a jurisdiction to compare results.</p>
                <p style="font-size:0.85rem;margin-top:8px;color:var(--gray-400);">
                    <i class="fas fa-lightbulb"></i> 
                    <?php if ($comparison_level === 'lga'): ?>
                        Compare wards within an LGA
                    <?php elseif ($comparison_level === 'ward'): ?>
                        Compare polling units within a ward
                    <?php elseif ($comparison_level === 'pu'): ?>
                        Compare different election results for a polling unit
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ============================================================
// COMPARISON CHART
// ============================================================
<?php if (!empty($comparison_data) && $selected_id > 0 && $election_id > 0): ?>
const ctx = document.getElementById('comparisonChart').getContext('2d');

const labels = <?php echo json_encode($comparison_labels); ?>;
const submittedData = <?php echo json_encode(array_column($comparison_data, 'submitted')); ?>;
const verifiedData = <?php echo json_encode(array_column($comparison_data, 'verified')); ?>;
const totalVotesData = <?php echo json_encode(array_column($comparison_data, 'total_votes')); ?>;

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Submitted',
                data: submittedData,
                backgroundColor: 'rgba(59, 130, 246, 0.7)',
                borderColor: '#3B82F6',
                borderWidth: 1
            },
            {
                label: 'Verified',
                data: verifiedData,
                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                borderColor: '#10B981',
                borderWidth: 1
            },
            {
                label: 'Total Votes',
                data: totalVotesData,
                backgroundColor: 'rgba(234, 88, 12, 0.7)',
                borderColor: '#EA580C',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 12,
                    font: { size: 11 }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: { font: { size: 10 } }
            },
            x: {
                grid: { display: false },
                ticks: { 
                    font: { size: 10 },
                    maxRotation: 45
                }
            }
        }
    }
});
<?php endif; ?>

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