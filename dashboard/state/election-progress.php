<?php
// ============================================================
// STATE COORDINATOR - ELECTION PROGRESS
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
// GET ELECTION ID
// ============================================================
$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($election_id <= 0) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH ELECTION DETAILS
// ============================================================
$election = null;
$state_name = '';

try {
    $stmt = $db->prepare("
        SELECT e.*, u.first_name as created_by_first, u.last_name as created_by_last
        FROM elections e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ? AND e.tenant_id = ? AND e.deleted_at IS NULL
    ");
    $stmt->execute([$election_id, $tenant_id]);
    $election = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($election) {
        // Get state name
        if (!empty($state_id)) {
            $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
            $stmt->execute([$state_id]);
            $state = $stmt->fetch(PDO::FETCH_ASSOC);
            $state_name = $state['name'] ?? 'Unknown State';
        }
    }
} catch (Exception $e) {
    error_log("Error fetching election: " . $e->getMessage());
}

if (!$election) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH PROGRESS STATISTICS
// ============================================================
$progress = [
    'total_pus' => 0,
    'reported_pus' => 0,
    'verified_pus' => 0,
    'pending_pus' => 0,
    'completion_percentage' => 0,
    'verification_percentage' => 0,
    'total_agents' => 0,
    'active_agents' => 0,
    'total_incidents' => 0,
    'resolved_incidents' => 0,
];

try {
    // Get state LGAs
    $stmt = $db->prepare("SELECT id FROM lgas WHERE state_id = ? AND is_active = 1");
    $stmt->execute([$state_id]);
    $lga_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $lga_ids_imploded = implode(',', $lga_ids);
    
    if (!empty($lga_ids_imploded)) {
        // Total PUs in state
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            WHERE w.lga_id IN ($lga_ids_imploded) AND pu.is_active = 1
        ");
        $stmt->execute();
        $progress['total_pus'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Reported PUs for this election
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT pu_id) as count 
            FROM results_ec8a 
            WHERE election_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$election_id, $tenant_id]);
        $progress['reported_pus'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Verified PUs
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT pu_id) as count 
            FROM results_ec8a 
            WHERE election_id = ? AND tenant_id = ? AND status IN ('verified', 'approved')
        ");
        $stmt->execute([$election_id, $tenant_id]);
        $progress['verified_pus'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Pending PUs
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT pu_id) as count 
            FROM results_ec8a 
            WHERE election_id = ? AND tenant_id = ? AND status = 'pending'
        ");
        $stmt->execute([$election_id, $tenant_id]);
        $progress['pending_pus'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Total agents in state
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE state_id = ? AND deleted_at IS NULL AND status = 'active'
        ");
        $stmt->execute([$state_id]);
        $progress['total_agents'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Active agents (last 15 minutes)
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            JOIN user_sessions us ON u.id = us.user_id
            WHERE u.state_id = ? 
            AND us.is_active = 1 
            AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            AND u.status = 'active'
            AND u.deleted_at IS NULL
        ");
        $stmt->execute([$state_id]);
        $progress['active_agents'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Total incidents
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM incidents 
            WHERE state_id = ? AND election_id = ?
        ");
        $stmt->execute([$state_id, $election_id]);
        $progress['total_incidents'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Resolved incidents
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM incidents 
            WHERE state_id = ? AND election_id = ? AND status IN ('resolved', 'false_alarm')
        ");
        $stmt->execute([$state_id, $election_id]);
        $progress['resolved_incidents'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Calculate percentages
    if ($progress['total_pus'] > 0) {
        $progress['completion_percentage'] = round(($progress['reported_pus'] / $progress['total_pus']) * 100, 1);
        $progress['verification_percentage'] = round(($progress['verified_pus'] / $progress['total_pus']) * 100, 1);
    }
    
} catch (Exception $e) {
    error_log("Error fetching progress: " . $e->getMessage());
}

// ============================================================
// FETCH LGA PROGRESS
// ============================================================
$lga_progress = [];

try {
    $stmt = $db->prepare("
        SELECT 
            l.id,
            l.name,
            l.code,
            (SELECT COUNT(*) FROM polling_units pu 
             JOIN wards w ON pu.ward_id = w.id 
             WHERE w.lga_id = l.id AND pu.is_active = 1) as total_pus,
            (SELECT COUNT(DISTINCT r.pu_id) FROM results_ec8a r 
             JOIN polling_units pu ON r.pu_id = pu.id 
             JOIN wards w ON pu.ward_id = w.id 
             WHERE w.lga_id = l.id AND r.election_id = ? AND r.tenant_id = ?) as reported_pus,
            (SELECT COUNT(DISTINCT r.pu_id) FROM results_ec8a r 
             JOIN polling_units pu ON r.pu_id = pu.id 
             JOIN wards w ON pu.ward_id = w.id 
             WHERE w.lga_id = l.id AND r.election_id = ? AND r.tenant_id = ? AND r.status = 'pending') as pending_pus,
            (SELECT COUNT(DISTINCT r.pu_id) FROM results_ec8a r 
             JOIN polling_units pu ON r.pu_id = pu.id 
             JOIN wards w ON pu.ward_id = w.id 
             WHERE w.lga_id = l.id AND r.election_id = ? AND r.tenant_id = ? AND r.status IN ('verified', 'approved')) as verified_pus
        FROM lgas l
        WHERE l.state_id = ? AND l.is_active = 1
        ORDER BY l.name ASC
    ");
    $stmt->execute([$election_id, $tenant_id, $election_id, $tenant_id, $election_id, $tenant_id, $state_id]);
    $lga_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($lga_progress as &$lga) {
        $lga['total_pus'] = (int)($lga['total_pus'] ?? 0);
        $lga['reported_pus'] = (int)($lga['reported_pus'] ?? 0);
        $lga['pending_pus'] = (int)($lga['pending_pus'] ?? 0);
        $lga['verified_pus'] = (int)($lga['verified_pus'] ?? 0);
        
        if ($lga['total_pus'] > 0) {
            $lga['percentage'] = round(($lga['reported_pus'] / $lga['total_pus']) * 100, 1);
        } else {
            $lga['percentage'] = 0;
        }
        
        // Determine status
        if ($lga['percentage'] >= 80) {
            $lga['status'] = 'success';
            $lga['status_text'] = 'Excellent';
        } elseif ($lga['percentage'] >= 50) {
            $lga['status'] = 'warning';
            $lga['status_text'] = 'In Progress';
        } elseif ($lga['percentage'] > 0) {
            $lga['status'] = 'danger';
            $lga['status_text'] = 'Low';
        } else {
            $lga['status'] = 'secondary';
            $lga['status_text'] = 'No Data';
        }
    }
    
} catch (Exception $e) {
    error_log("Error fetching LGA progress: " . $e->getMessage());
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

.election-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
}
.election-header .title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gray-800);
}
.election-header .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-top: 2px;
}
.election-header .meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 8px;
    font-size: 0.8rem;
    color: var(--gray-500);
}
.election-header .meta span {
    display: flex;
    align-items: center;
    gap: 4px;
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-card .number {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--gray-800);
}
.stat-card .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    margin-top: 2px;
}
.stat-card .sub {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.progress-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 24px;
}
.progress-card .progress-title {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 12px;
}
.progress-card .progress-title i {
    color: var(--primary);
    margin-right: 6px;
}
.progress-bar-large {
    height: 24px;
    background: var(--gray-200);
    border-radius: 12px;
    overflow: hidden;
    position: relative;
}
.progress-bar-large .progress-fill {
    height: 100%;
    border-radius: 12px;
    transition: width 1s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
    color: white;
}
.progress-bar-large .progress-fill.success { background: linear-gradient(90deg, #10B981, #34D399); }
.progress-bar-large .progress-fill.warning { background: linear-gradient(90deg, #F59E0B, #FBBF24); }
.progress-bar-large .progress-fill.danger { background: linear-gradient(90deg, #EF4444, #F87171); }

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

.progress-bar-small {
    height: 6px;
    background: var(--gray-200);
    border-radius: 3px;
    overflow: hidden;
    min-width: 80px;
}
.progress-bar-small .progress-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.6s ease;
}
.progress-bar-small .progress-fill.success { background: #10B981; }
.progress-bar-small .progress-fill.warning { background: #F59E0B; }
.progress-bar-small .progress-fill.danger { background: #EF4444; }
.progress-bar-small .progress-fill.secondary { background: #9CA3AF; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
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
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
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
                    <i class="fas fa-chart-line" style="color:var(--primary);margin-right:8px;"></i>
                    Election Progress
                    <small><?php echo htmlspecialchars($state_name); ?> - Track election progress</small>
                </h2>
            </div>
            <div>
                <a href="elections.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Elections
                </a>
            </div>
        </div>

        <!-- Election Header -->
        <div class="election-header">
            <div class="title"><?php echo htmlspecialchars($election['name']); ?></div>
            <div class="subtitle">
                <span class="badge-status <?php echo $status_colors[$election['status']] ?? 'secondary'; ?>">
                    <span class="dot"></span>
                    <?php echo ucfirst($election['status']); ?>
                </span>
                <span style="margin-left:8px;"><?php echo date('F j, Y', strtotime($election['election_date'])); ?></span>
            </div>
            <div class="meta">
                <span><i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y', strtotime($election['election_date'])); ?></span>
                <span><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($election['start_time'] ?? '00:00:00')); ?></span>
                <span><i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $election['type'])); ?></span>
                <span><i class="fas fa-user"></i> Created by: <?php echo htmlspecialchars($election['created_by_first'] . ' ' . $election['created_by_last']); ?></span>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo number_format($progress['total_pus']); ?></div>
                <div class="label">Total Polling Units</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#F59E0B;"><?php echo number_format($progress['reported_pus']); ?></div>
                <div class="label">Reported</div>
                <div class="sub"><?php echo number_format($progress['pending_pus']); ?> pending</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#10B981;"><?php echo number_format($progress['verified_pus']); ?></div>
                <div class="label">Verified</div>
                <div class="sub"><?php echo $progress['verification_percentage']; ?>% of total</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#3B82F6;"><?php echo number_format($progress['total_agents']); ?></div>
                <div class="label">Total Agents</div>
                <div class="sub" style="color:#10B981;"><?php echo number_format($progress['active_agents']); ?> online</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#EF4444;"><?php echo number_format($progress['total_incidents']); ?></div>
                <div class="label">Incidents</div>
                <div class="sub" style="color:#10B981;"><?php echo number_format($progress['resolved_incidents']); ?> resolved</div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-card">
            <div class="progress-title">
                <i class="fas fa-tasks"></i> Overall Progress
                <span style="float:right;font-weight:400;color:var(--gray-500);font-size:0.8rem;">
                    <?php echo $progress['completion_percentage']; ?>% Complete
                </span>
            </div>
            <div class="progress-bar-large">
                <div class="progress-fill <?php 
                    $pct = $progress['completion_percentage'];
                    if ($pct >= 80) echo 'success';
                    elseif ($pct >= 50) echo 'warning';
                    elseif ($pct > 0) echo 'danger';
                    else echo 'secondary';
                ?>" style="width: <?php echo $progress['completion_percentage']; ?>%;">
                    <?php if ($progress['completion_percentage'] > 20): ?>
                        <?php echo $progress['completion_percentage']; ?>%
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                <span><?php echo number_format($progress['reported_pus']); ?> reported</span>
                <span><?php echo number_format($progress['total_pus'] - $progress['reported_pus']); ?> remaining</span>
            </div>
        </div>

        <!-- LGA Progress Table -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>LGA</th>
                        <th>Code</th>
                        <th>Total PUs</th>
                        <th>Reported</th>
                        <th>Pending</th>
                        <th>Verified</th>
                        <th>Progress</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($lga_progress) > 0): ?>
                        <?php $sn = 1; ?>
                        <?php foreach ($lga_progress as $lga): ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td><strong><?php echo htmlspecialchars($lga['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($lga['code'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($lga['total_pus']); ?></td>
                                <td><?php echo number_format($lga['reported_pus']); ?></td>
                                <td style="color:#F59E0B;"><?php echo number_format($lga['pending_pus']); ?></td>
                                <td style="color:#10B981;"><?php echo number_format($lga['verified_pus']); ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div class="progress-bar-small">
                                            <div class="progress-fill <?php echo $lga['status']; ?>" 
                                                 style="width: <?php echo $lga['percentage']; ?>%;">
                                            </div>
                                        </div>
                                        <span style="font-size:0.7rem;font-weight:600;min-width:35px;">
                                            <?php echo $lga['percentage']; ?>%
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $lga['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo $lga['status_text']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <p>No LGAs found in <?php echo htmlspecialchars($state_name); ?>.</p>
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

// ============================================================
// ANIMATE PROGRESS BARS ON LOAD
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.progress-bar-large .progress-fill, .progress-bar-small .progress-fill').forEach(function(bar) {
            var width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(function() {
                bar.style.width = width;
            }, 300);
        });
    }, 500);
});
</script>
</body>
</html>