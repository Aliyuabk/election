<?php
// ============================================================
// STATE COORDINATOR - LGA REPORT
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
// GET LGA ID AND ELECTION FILTER
// ============================================================
$lga_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$election_filter = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

if ($lga_id <= 0) {
    header('Location: monitor-lgas.php');
    exit();
}

// ============================================================
// GENERATE CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// FETCH LGA DETAILS
// ============================================================
$lga = null;
$report_data = [];

try {
    $stmt = $db->prepare("
        SELECT l.*, s.name as state_name
        FROM lgas l
        JOIN states s ON l.state_id = s.id
        WHERE l.id = ? AND l.is_active = 1
    ");
    $stmt->execute([$lga_id]);
    $lga = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lga) {
        header('Location: monitor-lgas.php');
        exit();
    }
    
    // Get elections
    $elections = [];
    $stmt = $db->prepare("
        SELECT id, name, election_date, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL 
        AND (states_json LIKE ? OR states_json IS NULL OR states_json = '[]')
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no election selected, use the latest one
    if ($election_filter == 0 && count($elections) > 0) {
        $election_filter = $elections[0]['id'];
    }
    
    // Get PUs in LGA
    $stmt = $db->prepare("
        SELECT pu.id, pu.name, pu.code, pu.registered_voters, w.name as ward_name
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        WHERE w.lga_id = ? AND pu.is_active = 1
        ORDER BY pu.name
    ");
    $stmt->execute([$lga_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get results for each PU for the selected election
    if ($election_filter > 0) {
        foreach ($polling_units as &$pu) {
            $stmt = $db->prepare("
                SELECT 
                    r.*,
                    u.first_name as agent_first,
                    u.last_name as agent_last
                FROM results_ec8a r
                LEFT JOIN users u ON r.agent_id = u.id
                WHERE r.pu_id = ? AND r.election_id = ? AND r.tenant_id = ?
                ORDER BY r.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$pu['id'], $election_filter, $tenant_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $pu['result'] = $result;
                $pu['has_result'] = true;
                $pu['status'] = $result['status'];
                $pu['total_votes'] = $result['total_votes_cast'] ?? 0;
                $pu['agent_name'] = ($result['agent_first'] ?? '') . ' ' . ($result['agent_last'] ?? '');
            } else {
                $pu['has_result'] = false;
                $pu['status'] = 'not_submitted';
                $pu['total_votes'] = 0;
                $pu['agent_name'] = 'Not Assigned';
            }
        }
    }
    
    // Get coordinator
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.lga_id = ? AND r.level = 'lga' AND u.deleted_at IS NULL AND u.status = 'active'
    ");
    $stmt->execute([$lga_id]);
    $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get ward coordinators count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.lga_id = ? AND r.level = 'ward' AND u.deleted_at IS NULL AND u.status = 'active'
    ");
    $stmt->execute([$lga_id]);
    $ward_coordinators = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $report_data = [
        'elections' => $elections,
        'selected_election' => $election_filter,
        'polling_units' => $polling_units,
        'coordinator' => $coordinator,
        'ward_coordinators' => $ward_coordinators,
        'total_pus' => count($polling_units),
        'reported_pus' => count(array_filter($polling_units, function($pu) { return $pu['has_result'] ?? false; })),
        'verified_pus' => count(array_filter($polling_units, function($pu) { 
            return ($pu['has_result'] ?? false) && in_array($pu['status'], ['verified', 'approved']); 
        })),
        'pending_pus' => count(array_filter($polling_units, function($pu) { 
            return ($pu['has_result'] ?? false) && $pu['status'] === 'pending'; 
        })),
        'total_votes' => array_sum(array_column(array_filter($polling_units, function($pu) { 
            return $pu['has_result'] ?? false; 
        }), 'total_votes'))
    ];
    
} catch (Exception $e) {
    error_log("Error fetching LGA report: " . $e->getMessage());
}

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
/* Reuse styles from lga-details.php */
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

.lga-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}
.lga-header .title {
    font-size: 1.1rem;
    font-weight: 700;
}
.lga-header .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 16px;
    background: white;
    padding: 14px 20px;
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 14px 16px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-card .number {
    font-size: 1.4rem;
    font-weight: 700;
}
.stat-card .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    margin-top: 2px;
}
.stat-card .number.primary { color: #3B82F6; }
.stat-card .number.success { color: #10B981; }
.stat-card .number.warning { color: #F59E0B; }
.stat-card .number.danger { color: #EF4444; }

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
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-600);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
}
.table-wrapper table td {
    padding: 10px 14px;
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
.badge-status.verified { background: #ECFDF5; color: #065F46; }
.badge-status.verified .dot { background: #10B981; }
.badge-status.pending { background: #FFFBEB; color: #92400E; }
.badge-status.pending .dot { background: #F59E0B; }
.badge-status.rejected { background: #FEF2F2; color: #991B1B; }
.badge-status.rejected .dot { background: #EF4444; }
.badge-status.not_submitted { background: #F3F4F6; color: #6B7280; }
.badge-status.not_submitted .dot { background: #9CA3AF; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-400);
}
.empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 8px;
    color: var(--gray-300);
}

.info-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}
.info-box {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
}
.info-box .label {
    font-size: 0.7rem;
    color: var(--gray-400);
}
.info-box .value {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--gray-800);
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
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .info-row {
        grid-template-columns: 1fr;
    }
    .table-wrapper {
        overflow-x: auto;
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
                    LGA Report
                    <small><?php echo htmlspecialchars($lga['name'] ?? 'LGA'); ?> - Detailed report</small>
                </h2>
            </div>
            <div>
                <a href="lga-details.php?id=<?php echo $lga_id; ?>" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to LGA
                </a>
            </div>
        </div>

        <!-- LGA Header -->
        <div class="lga-header">
            <div>
                <div class="title"><?php echo htmlspecialchars($lga['name']); ?></div>
                <div class="subtitle">
                    <i class="fas fa-flag"></i> <?php echo htmlspecialchars($lga['state_name']); ?>
                    <span style="margin-left:12px;">Code: <?php echo htmlspecialchars($lga['code'] ?? 'N/A'); ?></span>
                </div>
            </div>
            <div>
                <?php if ($report_data['coordinator']): ?>
                    <span style="font-size:0.85rem;color:var(--gray-600);">
                        <i class="fas fa-user-tie"></i> 
                        <?php echo htmlspecialchars($report_data['coordinator']['first_name'] . ' ' . $report_data['coordinator']['last_name']); ?>
                    </span>
                <?php else: ?>
                    <span style="font-size:0.85rem;color:var(--gray-400);">
                        <i class="fas fa-user-tie"></i> No coordinator assigned
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="filter-bar">
            <input type="hidden" name="id" value="<?php echo $lga_id; ?>">
            <select name="election_id">
                <?php foreach ($report_data['elections'] as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo $report_data['selected_election'] == $e['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['name']); ?> (<?php echo date('Y', strtotime($e['election_date'])); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-file-alt"></i> Generate Report</button>
        </form>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number primary"><?php echo number_format($report_data['total_pus']); ?></div>
                <div class="label">Total PUs</div>
            </div>
            <div class="stat-card">
                <div class="number success"><?php echo number_format($report_data['reported_pus']); ?></div>
                <div class="label">Reported</div>
            </div>
            <div class="stat-card">
                <div class="number success"><?php echo number_format($report_data['verified_pus']); ?></div>
                <div class="label">Verified</div>
            </div>
            <div class="stat-card">
                <div class="number warning"><?php echo number_format($report_data['pending_pus']); ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="number primary"><?php echo number_format($report_data['total_votes']); ?></div>
                <div class="label">Total Votes</div>
            </div>
            <div class="stat-card">
                <div class="number primary"><?php echo number_format($report_data['ward_coordinators']); ?></div>
                <div class="label">Ward Coordinators</div>
            </div>
        </div>

        <!-- Polling Units Table -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Polling Unit</th>
                        <th>Ward</th>
                        <th>Code</th>
                        <th>Registered Voters</th>
                        <th>Agent</th>
                        <th>Votes</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($report_data['polling_units']) > 0): ?>
                        <?php $sn = 1; ?>
                        <?php foreach ($report_data['polling_units'] as $pu): ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td><strong><?php echo htmlspecialchars($pu['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($pu['ward_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($pu['code'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($pu['registered_voters'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($pu['agent_name'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($pu['total_votes'] ?? 0); ?></td>
                                <td>
                                    <span class="badge-status <?php echo $pu['status'] ?? 'not_submitted'; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst(str_replace('_', ' ', $pu['status'] ?? 'Not Submitted')); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-flag-checkered"></i>
                                    <p>No polling units found in this LGA.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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