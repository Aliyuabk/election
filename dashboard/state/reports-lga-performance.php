<?php
// ============================================================
// STATE COORDINATOR - LGA PERFORMANCE REPORT
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
// FETCH LGA PERFORMANCE DATA
// ============================================================
$lga_performance = [];
$summary = [];

if ($election_filter > 0) {
    try {
        // Get election details
        $stmt = $db->prepare("SELECT name, election_date FROM elections WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$election_filter, $tenant_id]);
        $election = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($election) {
            // Get LGAs in state
            $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
            $stmt->execute([$state_id]);
            $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($lgas as $lga) {
                // Get PU counts
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total_pus 
                    FROM polling_units pu
                    JOIN wards w ON pu.ward_id = w.id
                    WHERE w.lga_id = ? AND pu.is_active = 1
                ");
                $stmt->execute([$lga['id']]);
                $total_pus = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total_pus'] ?? 0);
                
                // Get reported PUs
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(DISTINCT r.pu_id) as reported_pus,
                        COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.pu_id END) as verified_pus,
                        COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.pu_id END) as pending_pus
                    FROM results_ec8a r
                    JOIN polling_units pu ON r.pu_id = pu.id
                    JOIN wards w ON pu.ward_id = w.id
                    WHERE r.tenant_id = ? 
                    AND r.election_id = ? 
                    AND w.lga_id = ?
                ");
                $stmt->execute([$tenant_id, $election_filter, $lga['id']]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get coordinator info
                $stmt = $db->prepare("
                    SELECT u.first_name, u.last_name, u.email, u.phone
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.lga_id = ? AND r.level = 'lga' AND u.deleted_at IS NULL AND u.status = 'active'
                ");
                $stmt->execute([$lga['id']]);
                $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $reported = (int)($data['reported_pus'] ?? 0);
                $verified = (int)($data['verified_pus'] ?? 0);
                $pending = (int)($data['pending_pus'] ?? 0);
                
                $completion = $total_pus > 0 ? round(($reported / $total_pus) * 100, 1) : 0;
                $verification = $total_pus > 0 ? round(($verified / $total_pus) * 100, 1) : 0;
                
                // Determine status
                if ($completion >= 80) {
                    $status = 'Excellent';
                    $status_class = 'success';
                } elseif ($completion >= 50) {
                    $status = 'In Progress';
                    $status_class = 'warning';
                } elseif ($completion > 0) {
                    $status = 'Low';
                    $status_class = 'danger';
                } else {
                    $status = 'No Data';
                    $status_class = 'secondary';
                }
                
                $lga_performance[] = [
                    'id' => $lga['id'],
                    'name' => $lga['name'],
                    'coordinator' => $coordinator ? ($coordinator['first_name'] . ' ' . $coordinator['last_name']) : 'Not Assigned',
                    'coordinator_email' => $coordinator['email'] ?? '',
                    'coordinator_phone' => $coordinator['phone'] ?? '',
                    'total_pus' => $total_pus,
                    'reported_pus' => $reported,
                    'verified_pus' => $verified,
                    'pending_pus' => $pending,
                    'unreported_pus' => $total_pus - $reported,
                    'completion' => $completion,
                    'verification' => $verification,
                    'status' => $status,
                    'status_class' => $status_class
                ];
            }
            
            // Calculate summary
            $summary['total_pus'] = array_sum(array_column($lga_performance, 'total_pus'));
            $summary['reported_pus'] = array_sum(array_column($lga_performance, 'reported_pus'));
            $summary['verified_pus'] = array_sum(array_column($lga_performance, 'verified_pus'));
            $summary['pending_pus'] = array_sum(array_column($lga_performance, 'pending_pus'));
            $summary['avg_completion'] = $summary['total_pus'] > 0 ? round(($summary['reported_pus'] / $summary['total_pus']) * 100, 1) : 0;
            $summary['avg_verification'] = $summary['total_pus'] > 0 ? round(($summary['verified_pus'] / $summary['total_pus']) * 100, 1) : 0;
            $summary['lga_count'] = count($lga_performance);
            
            // Sort by completion percentage
            usort($lga_performance, function($a, $b) {
                return $b['completion'] - $a['completion'];
            });
        }
    } catch (Exception $e) {
        error_log("Error fetching LGA performance: " . $e->getMessage());
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
/* Reuse styles from reports-state.php */
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

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.badge-status .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.badge-status.success { background: #ECFDF5; color: #065F46; }
.badge-status.success .dot { background: #10B981; }
.badge-status.warning { background: #FFFBEB; color: #92400E; }
.badge-status.warning .dot { background: #F59E0B; }
.badge-status.danger { background: #FEF2F2; color: #991B1B; }
.badge-status.danger .dot { background: #EF4444; }
.badge-status.secondary { background: #F3F4F6; color: #6B7280; }
.badge-status.secondary .dot { background: #9CA3AF; }

.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-weight: 700;
    font-size: 0.7rem;
}
.rank-badge.gold { background: #FCD34D; color: #92400E; }
.rank-badge.silver { background: #D1D5DB; color: #4B5563; }
.rank-badge.bronze { background: #FCA5A5; color: #7F1D1D; }

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
                    <i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px;"></i>
                    LGA Performance Report
                    <small><?php echo htmlspecialchars($state_name); ?> - LGA performance analysis</small>
                </h2>
            </div>
            <div>
                <a href="reports-state.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Reports
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
            <button type="submit" class="btn-filter"><i class="fas fa-chart-bar"></i> View Performance</button>
        </form>

        <?php if ($election_filter > 0 && !empty($lga_performance)): ?>
            <!-- Summary -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['lga_count']); ?></div>
                    <div class="label">LGAs</div>
                </div>
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="label">Total PUs</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['reported_pus']); ?></div>
                    <div class="label">Reported</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo $summary['avg_completion']; ?>%</div>
                    <div class="label">Avg Completion</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo $summary['avg_verification']; ?>%</div>
                    <div class="label">Avg Verification</div>
                </div>
            </div>

            <!-- LGA Performance Table -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>LGA</th>
                            <th>Coordinator</th>
                            <th>Total PUs</th>
                            <th>Reported</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Completion</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; ?>
                        <?php foreach ($lga_performance as $lga): ?>
                            <tr>
                                <td>
                                    <?php if ($rank == 1): ?>
                                        <span class="rank-badge gold">1</span>
                                    <?php elseif ($rank == 2): ?>
                                        <span class="rank-badge silver">2</span>
                                    <?php elseif ($rank == 3): ?>
                                        <span class="rank-badge bronze">3</span>
                                    <?php else: ?>
                                        <span style="font-weight:600;color:var(--gray-500);"><?php echo $rank; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($lga['name']); ?></strong></td>
                                <td>
                                    <div style="font-size:0.8rem;font-weight:500;"><?php echo htmlspecialchars($lga['coordinator']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo htmlspecialchars($lga['coordinator_phone']); ?></div>
                                </td>
                                <td><?php echo number_format($lga['total_pus']); ?></td>
                                <td><?php echo number_format($lga['reported_pus']); ?></td>
                                <td style="color:#10B981;"><?php echo number_format($lga['verified_pus']); ?></td>
                                <td style="color:#F59E0B;"><?php echo number_format($lga['pending_pus']); ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div style="flex:1;height:6px;background:var(--gray-200);border-radius:3px;overflow:hidden;min-width:60px;">
                                            <div style="height:100%;border-radius:3px;background:<?php echo $lga['completion'] >= 80 ? '#10B981' : ($lga['completion'] >= 50 ? '#F59E0B' : '#EF4444'); ?>;width:<?php echo $lga['completion']; ?>%;"></div>
                                        </div>
                                        <span style="font-size:0.7rem;font-weight:600;min-width:35px;"><?php echo $lga['completion']; ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $lga['status_class']; ?>">
                                        <span class="dot"></span>
                                        <?php echo $lga['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php $rank++; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($election_filter > 0): ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <p>No data found for the selected election.</p>
                <p style="font-size:0.8rem;">Make sure results have been submitted and verified.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <p>Select an election to view LGA performance.</p>
                <p style="font-size:0.8rem;">The report will show performance metrics for each LGA in <?php echo htmlspecialchars($state_name); ?>.</p>
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