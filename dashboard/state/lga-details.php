<?php
// ============================================================
// STATE COORDINATOR - LGA DETAILS
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
// GET LGA ID
// ============================================================
$lga_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($lga_id <= 0) {
    header('Location: monitor-lgas.php');
    exit();
}

// ============================================================
// FETCH LGA DETAILS
// ============================================================
$lga = null;
$wards = [];
$stats = [];

try {
    // Get LGA details
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
    
    // Get wards in LGA
    $stmt = $db->prepare("
        SELECT id, name, code, registered_voters, is_active
        FROM wards
        WHERE lga_id = ? AND is_active = 1
        ORDER BY name
    ");
    $stmt->execute([$lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    // Total PUs
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        WHERE w.lga_id = ?
    ");
    $stmt->execute([$lga_id]);
    $stats['total_pus'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Reported PUs
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT r.pu_id) as count
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        WHERE w.lga_id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$lga_id, $tenant_id]);
    $stats['reported_pus'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Verified PUs
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT r.pu_id) as count
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        WHERE w.lga_id = ? AND r.tenant_id = ? AND r.status IN ('verified', 'approved')
    ");
    $stmt->execute([$lga_id, $tenant_id]);
    $stats['verified_pus'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Total agents
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM users
        WHERE lga_id = ? AND deleted_at IS NULL AND status = 'active'
    ");
    $stmt->execute([$lga_id]);
    $stats['total_agents'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Incidents
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status NOT IN ('resolved', 'false_alarm') THEN 1 ELSE 0 END) as active
        FROM incidents
        WHERE lga_id = ?
    ");
    $stmt->execute([$lga_id]);
    $incident_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_incidents'] = (int)($incident_data['total'] ?? 0);
    $stats['active_incidents'] = (int)($incident_data['active'] ?? 0);
    
    $stats['completion'] = $stats['total_pus'] > 0 ? round(($stats['reported_pus'] / $stats['total_pus']) * 100, 1) : 0;
    $stats['verification'] = $stats['total_pus'] > 0 ? round(($stats['verified_pus'] / $stats['total_pus']) * 100, 1) : 0;
    
} catch (Exception $e) {
    error_log("Error fetching LGA details: " . $e->getMessage());
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

.lga-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-sm);
}
.lga-header .title {
    font-size: 1.2rem;
    font-weight: 700;
}
.lga-header .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-top: 2px;
}
.lga-header .meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 8px;
    font-size: 0.8rem;
    color: var(--gray-500);
}
.lga-header .meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-card .number {
    font-size: 1.6rem;
    font-weight: 700;
}
.stat-card .label {
    font-size: 0.7rem;
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
.badge-status.active { background: #ECFDF5; color: #065F46; }
.badge-status.active .dot { background: #10B981; }
.badge-status.inactive { background: #FEF2F2; color: #991B1B; }
.badge-status.inactive .dot { background: #EF4444; }

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
                    <i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:8px;"></i>
                    LGA Details
                    <small><?php echo htmlspecialchars($lga['name'] ?? 'LGA'); ?> - Local Government Area</small>
                </h2>
            </div>
            <div>
                <a href="monitor-lgas.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to LGAs
                </a>
            </div>
        </div>

        <!-- LGA Header -->
        <div class="lga-header">
            <div class="title"><?php echo htmlspecialchars($lga['name']); ?></div>
            <div class="subtitle">
                <i class="fas fa-flag"></i> <?php echo htmlspecialchars($lga['state_name']); ?>
                <span style="margin-left:12px;">
                    <span class="badge-status <?php echo $lga['is_active'] ? 'active' : 'inactive'; ?>">
                        <span class="dot"></span>
                        <?php echo $lga['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </span>
            </div>
            <div class="meta">
                <span><i class="fas fa-code"></i> Code: <?php echo htmlspecialchars($lga['code'] ?? 'N/A'); ?></span>
                <span><i class="fas fa-users"></i> Registered Voters: <?php echo number_format($lga['registered_voters'] ?? 0); ?></span>
                <span><i class="fas fa-calendar"></i> Created: <?php echo date('M j, Y', strtotime($lga['created_at'] ?? 'now')); ?></span>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number primary"><?php echo number_format($stats['total_pus']); ?></div>
                <div class="label">Total PUs</div>
            </div>
            <div class="stat-card">
                <div class="number success"><?php echo number_format($stats['reported_pus']); ?></div>
                <div class="label">Reported</div>
                <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo $stats['completion']; ?>%</div>
            </div>
            <div class="stat-card">
                <div class="number success"><?php echo number_format($stats['verified_pus']); ?></div>
                <div class="label">Verified</div>
                <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo $stats['verification']; ?>%</div>
            </div>
            <div class="stat-card">
                <div class="number primary"><?php echo number_format($stats['total_agents']); ?></div>
                <div class="label">Agents</div>
            </div>
            <div class="stat-card">
                <div class="number danger"><?php echo number_format($stats['active_incidents']); ?></div>
                <div class="label">Active Incidents</div>
                <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo number_format($stats['total_incidents']); ?> total</div>
            </div>
        </div>

        <!-- Wards Table -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Ward Name</th>
                        <th>Code</th>
                        <th>Registered Voters</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($wards) > 0): ?>
                        <?php $sn = 1; ?>
                        <?php foreach ($wards as $ward): ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td><strong><?php echo htmlspecialchars($ward['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($ward['code'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($ward['registered_voters'] ?? 0); ?></td>
                                <td>
                                    <span class="badge-status <?php echo $ward['is_active'] ? 'active' : 'inactive'; ?>">
                                        <span class="dot"></span>
                                        <?php echo $ward['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="ward-details.php?id=<?php echo $ward['id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <p>No wards found in this LGA.</p>
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