<?php
// ============================================================
// STATE COORDINATOR - STATE REPORT
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
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

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
// FETCH REPORT DATA
// ============================================================
$report_data = [];
$summary = [];

if ($election_filter > 0) {
    try {
        // Get election details
        $stmt = $db->prepare("
            SELECT e.*, u.first_name as created_by_first, u.last_name as created_by_last
            FROM elections e
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.id = ? AND e.tenant_id = ?
        ");
        $stmt->execute([$election_filter, $tenant_id]);
        $election = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($election) {
            // Get LGAs in state
            $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
            $stmt->execute([$state_id]);
            $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get results per LGA
            $lga_results = [];
            foreach ($lgas as $lga) {
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(DISTINCT r.pu_id) as reported_pus,
                        COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.pu_id END) as verified_pus,
                        COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.pu_id END) as pending_pus,
                        SUM(r.valid_votes) as total_valid,
                        SUM(r.rejected_votes) as total_rejected,
                        SUM(r.total_votes_cast) as total_votes
                    FROM results_ec8a r
                    JOIN polling_units pu ON r.pu_id = pu.id
                    JOIN wards w ON pu.ward_id = w.id
                    WHERE r.tenant_id = ? 
                    AND r.election_id = ? 
                    AND w.lga_id = ?
                ");
                $stmt->execute([$tenant_id, $election_filter, $lga['id']]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $lga_results[] = [
                    'id' => $lga['id'],
                    'name' => $lga['name'],
                    'reported_pus' => (int)($data['reported_pus'] ?? 0),
                    'verified_pus' => (int)($data['verified_pus'] ?? 0),
                    'pending_pus' => (int)($data['pending_pus'] ?? 0),
                    'total_valid' => (int)($data['total_valid'] ?? 0),
                    'total_rejected' => (int)($data['total_rejected'] ?? 0),
                    'total_votes' => (int)($data['total_votes'] ?? 0)
                ];
            }
            
            // Get party-wise summary
            $stmt = $db->prepare("
                SELECT 
                    r.party_votes_json
                FROM results_ec8a r
                JOIN polling_units pu ON r.pu_id = pu.id
                JOIN wards w ON pu.ward_id = w.id
                JOIN lgas l ON w.lga_id = l.id
                WHERE r.tenant_id = ? 
                AND r.election_id = ? 
                AND l.state_id = ?
                AND r.status IN ('verified', 'approved')
            ");
            $stmt->execute([$tenant_id, $election_filter, $state_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $party_totals = [];
            foreach ($results as $row) {
                $votes = json_decode($row['party_votes_json'], true);
                if (is_array($votes)) {
                    foreach ($votes as $party => $count) {
                        if (!isset($party_totals[$party])) {
                            $party_totals[$party] = 0;
                        }
                        $party_totals[$party] += (int)$count;
                    }
                }
            }
            arsort($party_totals);
            
            $report_data = [
                'election' => $election,
                'lgas' => $lga_results,
                'party_totals' => $party_totals,
                'total_lgas' => count($lgas)
            ];
            
            // Calculate summary
            $summary['total_pus'] = 0;
            $summary['reported_pus'] = 0;
            $summary['verified_pus'] = 0;
            $summary['total_votes'] = 0;
            
            foreach ($lga_results as $lga) {
                $summary['total_pus'] += $lga['reported_pus'] + $lga['pending_pus'];
                $summary['reported_pus'] += $lga['reported_pus'];
                $summary['verified_pus'] += $lga['verified_pus'];
                $summary['total_votes'] += $lga['total_votes'];
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching report data: " . $e->getMessage());
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

.btn-primary-sm {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-primary-sm:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
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
    min-width: 200px;
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
.filter-bar .btn-export {
    padding: 8px 20px;
    background: #10B981;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    font-family: 'Inter', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.filter-bar .btn-export:hover {
    background: #059669;
}
.filter-bar .btn-export.pdf {
    background: #EF4444;
}
.filter-bar .btn-export.pdf:hover {
    background: #DC2626;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
.summary-card .number.warning { color: #F59E0B; }
.summary-card .number.danger { color: #EF4444; }

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
.table-wrapper table tr.total-row td {
    background: var(--gray-100);
    font-weight: 700;
}

.party-summary {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-top: 20px;
}
.party-summary .title {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 12px;
}
.party-summary .title i {
    color: var(--primary);
    margin-right: 6px;
}
.party-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 8px;
}
.party-item {
    display: flex;
    justify-content: space-between;
    padding: 6px 12px;
    background: var(--gray-50);
    border-radius: 6px;
}
.party-item .name {
    font-weight: 500;
}
.party-item .votes {
    font-weight: 700;
    color: var(--gray-800);
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
                    <i class="fas fa-file-alt" style="color:var(--primary);margin-right:8px;"></i>
                    State Report
                    <small><?php echo htmlspecialchars($state_name); ?> - Comprehensive state report</small>
                </h2>
            </div>
            <div>
                <a href="index.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
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
            <button type="submit" class="btn-filter"><i class="fas fa-file-alt"></i> Generate Report</button>
            <?php if ($election_filter > 0 && !empty($report_data)): ?>
                <a href="reports-state.php?election_id=<?php echo $election_filter; ?>&format=pdf" class="btn-export pdf">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
                <a href="reports-state.php?election_id=<?php echo $election_filter; ?>&format=csv" class="btn-export">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
            <?php endif; ?>
        </form>

        <?php if ($election_filter > 0 && !empty($report_data)): ?>
            <!-- Election Info -->
            <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:16px 20px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h3 style="font-size:1rem;font-weight:700;margin:0;"><?php echo htmlspecialchars($report_data['election']['name']); ?></h3>
                        <div style="color:var(--gray-500);font-size:0.85rem;">
                            <?php echo ucfirst(str_replace('_', ' ', $report_data['election']['type'])); ?> - 
                            <?php echo date('F j, Y', strtotime($report_data['election']['election_date'])); ?>
                        </div>
                    </div>
                    <div style="font-size:0.85rem;color:var(--gray-500);">
                        Status: <span class="badge-status <?php echo $report_data['election']['status']; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst($report_data['election']['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="label">Total Polling Units</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['reported_pus']); ?></div>
                    <div class="label">Reported</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['total_pus'] - $summary['reported_pus']); ?></div>
                    <div class="label">Not Reported</div>
                </div>
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_votes']); ?></div>
                    <div class="label">Total Votes Cast</div>
                </div>
            </div>

            <!-- Party Summary -->
            <?php if (!empty($report_data['party_totals'])): ?>
                <div class="party-summary">
                    <div class="title"><i class="fas fa-vote-yea"></i> Party-wise Summary</div>
                    <div class="party-grid">
                        <?php foreach ($report_data['party_totals'] as $party => $votes): ?>
                            <div class="party-item">
                                <span class="name"><?php echo htmlspecialchars($party); ?></span>
                                <span class="votes"><?php echo number_format($votes); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- LGA Details -->
            <div class="table-wrapper" style="margin-top:20px;">
                <table>
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>LGA</th>
                            <th>Reported PUs</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Valid Votes</th>
                            <th>Rejected</th>
                            <th>Total Votes</th>
                            <th>Completion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = 1; ?>
                        <?php foreach ($report_data['lgas'] as $lga): 
                            $total = $lga['reported_pus'] + $lga['pending_pus'];
                            $percentage = $total > 0 ? round(($lga['reported_pus'] / $total) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td><strong><?php echo htmlspecialchars($lga['name']); ?></strong></td>
                                <td><?php echo number_format($lga['reported_pus']); ?></td>
                                <td style="color:#10B981;"><?php echo number_format($lga['verified_pus']); ?></td>
                                <td style="color:#F59E0B;"><?php echo number_format($lga['pending_pus']); ?></td>
                                <td><?php echo number_format($lga['total_valid']); ?></td>
                                <td style="color:#EF4444;"><?php echo number_format($lga['total_rejected']); ?></td>
                                <td><strong><?php echo number_format($lga['total_votes']); ?></strong></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div style="flex:1;height:6px;background:var(--gray-200);border-radius:3px;overflow:hidden;min-width:60px;">
                                            <div style="height:100%;border-radius:3px;background:<?php echo $percentage >= 80 ? '#10B981' : ($percentage >= 50 ? '#F59E0B' : '#EF4444'); ?>;width:<?php echo $percentage; ?>%;"></div>
                                        </div>
                                        <span style="font-size:0.7rem;font-weight:600;min-width:35px;"><?php echo $percentage; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2"><strong>TOTAL</strong></td>
                            <td><strong><?php echo number_format($summary['reported_pus']); ?></strong></td>
                            <td><strong><?php echo number_format($summary['verified_pus']); ?></strong></td>
                            <td><strong><?php echo number_format($summary['total_pus'] - $summary['reported_pus']); ?></strong></td>
                            <td colspan="3"><strong><?php echo number_format($summary['total_votes']); ?></strong></td>
                            <td><strong><?php echo $summary['total_pus'] > 0 ? round(($summary['reported_pus'] / $summary['total_pus']) * 100, 1) : 0; ?>%</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        <?php elseif ($election_filter > 0): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>No data found for the selected election.</p>
                <p style="font-size:0.8rem;">Make sure results have been submitted and verified.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>Select an election to generate the state report.</p>
                <p style="font-size:0.8rem;">The report will show comprehensive data for <?php echo htmlspecialchars($state_name); ?>.</p>
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
</script>
</body>
</html>