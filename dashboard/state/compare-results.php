<?php
// ============================================================
// STATE COORDINATOR - COMPARE RESULTS
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

// If state_id is not set in session, try to get it from user record
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

// ============================================================
// GET FILTERS
// ============================================================
$election_filter = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
$lga_filter = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;
$ward_filter = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;

// ============================================================
// FETCH STATE NAME
// ============================================================
$state_name = 'Unknown State';
try {
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        $state_name = $state['name'] ?? 'Unknown State';
    }
} catch (Exception $e) {
    error_log("Error fetching state: " . $e->getMessage());
}

// ============================================================
// FETCH ELECTIONS FOR FILTER
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, status 
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

// ============================================================
// FETCH LGAS FOR FILTER
// ============================================================
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// ============================================================
// FETCH WARDS FOR FILTER
// ============================================================
$wards = [];
if ($lga_filter > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$lga_filter]);
        $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching wards: " . $e->getMessage());
    }
}

// ============================================================
// FETCH COMPARISON DATA
// ============================================================
$comparison_data = [];
$summary = [
    'total_pus' => 0,
    'reported_pus' => 0,
    'total_votes' => 0,
    'parties' => [],
    'discrepancies' => 0
];

if ($election_filter > 0) {
    try {
        // Build query based on filters
        $sql = "
            SELECT 
                r.pu_id,
                pu.name as pu_name,
                pu.code as pu_code,
                w.name as ward_name,
                l.name as lga_name,
                r.party_votes_json,
                r.valid_votes,
                r.rejected_votes,
                r.total_votes_cast,
                r.status,
                r.created_at,
                u.first_name as agent_first,
                u.last_name as agent_last
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            JOIN users u ON r.agent_id = u.id
            WHERE r.tenant_id = ?
            AND r.election_id = ?
            AND l.state_id = ?
            AND r.status IN ('verified', 'approved')
        ";
        
        $params = [$tenant_id, $election_filter, $state_id];
        
        if ($lga_filter > 0) {
            $sql .= " AND l.id = ?";
            $params[] = $lga_filter;
        }
        
        if ($ward_filter > 0) {
            $sql .= " AND w.id = ?";
            $params[] = $ward_filter;
        }
        
        $sql .= " ORDER BY l.name, w.name, pu.name";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $comparison_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate summary
        $summary['total_pus'] = count($comparison_data);
        $summary['reported_pus'] = count($comparison_data);
        
        $party_totals = [];
        foreach ($comparison_data as $row) {
            $party_votes = json_decode($row['party_votes_json'], true);
            if (is_array($party_votes)) {
                foreach ($party_votes as $party => $votes) {
                    if (!isset($party_totals[$party])) {
                        $party_totals[$party] = 0;
                    }
                    $party_totals[$party] += (int)$votes;
                }
                $summary['total_votes'] += array_sum($party_votes);
            }
        }
        
        arsort($party_totals);
        $summary['parties'] = $party_totals;
        
        // Check for discrepancies (PU level vs ward level)
        if ($ward_filter > 0) {
            // Check if ward level results match sum of PU results
            $stmt = $db->prepare("
                SELECT party_votes_json, valid_votes, rejected_votes, total_votes
                FROM results_ec8b
                WHERE tenant_id = ? AND election_id = ? AND ward_id = ?
            ");
            $stmt->execute([$tenant_id, $election_filter, $ward_filter]);
            $ward_result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ward_result) {
                $ward_votes = json_decode($ward_result['party_votes_json'], true);
                $pu_total = $summary['total_votes'];
                $ward_total = is_array($ward_votes) ? array_sum($ward_votes) : 0;
                
                if ($pu_total != $ward_total) {
                    $summary['discrepancies'] = abs($pu_total - $ward_total);
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error fetching comparison data: " . $e->getMessage());
    }
}

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
    margin: 0;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.btn-secondary-sm {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-secondary-sm:hover {
    background: var(--gray-200);
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 20px;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 150px;
}
.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-bar .btn-filter {
    padding: 8px 24px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
}
.filter-bar .btn-filter:hover {
    background: var(--primary-dark);
}
.filter-bar .btn-reset {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    font-family: 'Inter', sans-serif;
}
.filter-bar .btn-reset:hover {
    background: var(--gray-200);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.summary-card {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.summary-card .number {
    font-size: 1.6rem;
    font-weight: 700;
}
.summary-card .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    margin-top: 2px;
}
.summary-card .number.primary { color: #3B82F6; }
.summary-card .number.success { color: #10B981; }
.summary-card .number.danger { color: #EF4444; }
.summary-card .number.warning { color: #F59E0B; }

.table-wrapper {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.table-wrapper table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table-wrapper table th {
    background: var(--gray-50);
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-600);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
}
.table-wrapper table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table-wrapper table tr:hover td {
    background: var(--gray-50);
}
.table-wrapper table tr:last-child td {
    border-bottom: none;
}

.party-votes-cell {
    font-size: 0.8rem;
}
.party-votes-cell .party-row {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
}
.party-votes-cell .party-row .party-name {
    font-weight: 500;
}
.party-votes-cell .party-row .votes {
    font-weight: 600;
}

.discrepancy-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
}
.discrepancy-badge.danger {
    background: #FEF2F2;
    color: #DC2626;
}
.discrepancy-badge.success {
    background: #ECFDF5;
    color: #10B981;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-400);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar select {
        width: 100%;
    }
    .table-wrapper {
        overflow-x: auto;
    }
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
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
                    <small><?php echo htmlspecialchars($state_name); ?> - Compare election results across levels</small>
                </h2>
            </div>
            <div>
                <a href="result-verification.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Verification
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="filter-bar">
            <select name="election_id" required>
                <option value="">Select Election</option>
                <?php foreach ($elections as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo $election_filter == $e['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="lga_id" id="lgaSelect">
                <option value="">All LGAs</option>
                <?php foreach ($lgas as $l): ?>
                    <option value="<?php echo $l['id']; ?>" <?php echo $lga_filter == $l['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($l['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="ward_id" id="wardSelect">
                <option value="">All Wards</option>
                <?php foreach ($wards as $w): ?>
                    <option value="<?php echo $w['id']; ?>" <?php echo $ward_filter == $w['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($w['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-chart-bar"></i> Compare</button>
            <a href="compare-results.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>

        <?php if ($election_filter > 0 && count($comparison_data) > 0): ?>
            <!-- Summary -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="label">Total Polling Units</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['reported_pus']); ?></div>
                    <div class="label">Reported PUs</div>
                </div>
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_votes']); ?></div>
                    <div class="label">Total Votes Cast</div>
                </div>
                <?php if ($summary['discrepancies'] > 0): ?>
                    <div class="summary-card">
                        <div class="number danger"><?php echo number_format($summary['discrepancies']); ?></div>
                        <div class="label">Discrepancy</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Party Summary -->
            <?php if (!empty($summary['parties'])): ?>
                <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:16px 20px;margin-bottom:20px;">
                    <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 12px 0;">
                        <i class="fas fa-vote-yea" style="color:var(--primary);"></i> Party-wise Summary
                    </h4>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;">
                        <?php foreach ($summary['parties'] as $party => $votes): ?>
                            <div style="display:flex;justify-content:space-between;padding:6px 12px;background:var(--gray-50);border-radius:6px;">
                                <span style="font-weight:500;"><?php echo htmlspecialchars($party); ?></span>
                                <span style="font-weight:700;color:var(--gray-800);"><?php echo number_format($votes); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Detailed Table -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Polling Unit</th>
                            <th>Ward / LGA</th>
                            <th>Agent</th>
                            <th>Party Votes</th>
                            <th>Valid</th>
                            <th>Rejected</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = 1; ?>
                        <?php foreach ($comparison_data as $row): 
                            $party_votes = json_decode($row['party_votes_json'], true);
                            $total = array_sum($party_votes);
                        ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td>
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($row['pu_name']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);">Code: <?php echo htmlspecialchars($row['pu_code']); ?></div>
                                </td>
                                <td>
                                    <div style="font-size:0.8rem;"><?php echo htmlspecialchars($row['ward_name']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo htmlspecialchars($row['lga_name']); ?></div>
                                </td>
                                <td>
                                    <div style="font-size:0.8rem;"><?php echo htmlspecialchars($row['agent_first'] . ' ' . $row['agent_last']); ?></div>
                                </td>
                                <td>
                                    <div class="party-votes-cell">
                                        <?php if (is_array($party_votes) && count($party_votes) > 0): ?>
                                            <?php foreach ($party_votes as $party => $votes): ?>
                                                <div class="party-row">
                                                    <span class="party-name"><?php echo htmlspecialchars($party); ?></span>
                                                    <span class="votes"><?php echo number_format($votes); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">No data</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="font-weight:600;color:#10B981;"><?php echo number_format($row['valid_votes'] ?? 0); ?></td>
                                <td style="font-weight:600;color:#EF4444;"><?php echo number_format($row['rejected_votes'] ?? 0); ?></td>
                                <td style="font-weight:700;"><?php echo number_format($row['total_votes_cast'] ?? $total); ?></td>
                                <td>
                                    <span class="badge-status <?php echo $row['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($election_filter > 0): ?>
            <div class="empty-state">
                <i class="fas fa-balance-scale"></i>
                <p>No verified results found for comparison.</p>
                <p style="font-size:0.8rem;">Make sure results have been verified and approved.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-balance-scale"></i>
                <p>Select an election to compare results.</p>
                <p style="font-size:0.8rem;">You can compare results across LGAs and Wards.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
    }
});

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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// DYNAMIC WARD LOADING
// ============================================================
document.getElementById('lgaSelect').addEventListener('change', function() {
    var lgaId = this.value;
    var wardSelect = document.getElementById('wardSelect');
    
    if (lgaId) {
        wardSelect.innerHTML = '<option value="">Loading...</option>';
        fetch('ajax/get-wards.php?lga_id=' + lgaId)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                wardSelect.innerHTML = '<option value="">All Wards</option>';
                data.forEach(function(ward) {
                    var option = document.createElement('option');
                    option.value = ward.id;
                    option.textContent = ward.name;
                    wardSelect.appendChild(option);
                });
            })
            .catch(function() {
                wardSelect.innerHTML = '<option value="">Error loading wards</option>';
            });
    } else {
        wardSelect.innerHTML = '<option value="">All Wards</option>';
    }
});
</script>
</body>
</html>