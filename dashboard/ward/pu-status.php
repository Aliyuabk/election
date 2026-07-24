<?php
// ============================================================
// WARD COORDINATOR - POLLING UNIT STATUS
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

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$ward_id = SessionManager::get('ward_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
if (empty($ward_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT ward_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            SessionManager::set('ward_id', $ward_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// FETCH WARD NAME
// ============================================================
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// FETCH POLLING UNIT STATUS
// ============================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$polling_units = [];
$summary = [];

try {
    // Get all polling units with status
    $stmt = $db->prepare("
        SELECT 
            pu.id,
            pu.name,
            pu.code,
            pu.registered_voters,
            pu.is_active,
            pu.is_rural,
            COUNT(DISTINCT u.id) as total_agents,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_agents,
            COUNT(DISTINCT r.id) as total_submissions,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified_submissions,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_submissions,
            COUNT(DISTINCT i.id) as total_incidents,
            SUM(CASE WHEN i.status IN ('reported', 'investigating') THEN 1 ELSE 0 END) as active_incidents,
            (SELECT COUNT(*) FROM agent_checkins ac WHERE ac.pu_id = pu.id AND DATE(ac.created_at) = CURDATE() AND ac.checkin_type = 'arrival') as checked_in_today
        FROM polling_units pu
        LEFT JOIN users u ON u.pu_id = pu.id AND u.deleted_at IS NULL
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        LEFT JOIN incidents i ON i.pu_id = pu.id
        WHERE pu.ward_id = ?
        GROUP BY pu.id, pu.name, pu.code, pu.registered_voters, pu.is_active, pu.is_rural
        ORDER BY pu.name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Summary statistics
    $summary['total'] = count($polling_units);
    $summary['active'] = count(array_filter($polling_units, function($pu) { return (int)$pu['is_active'] === 1; }));
    $summary['inactive'] = count(array_filter($polling_units, function($pu) { return (int)$pu['is_active'] === 0; }));
    $summary['has_submissions'] = count(array_filter($polling_units, function($pu) { return (int)($pu['total_submissions'] ?? 0) > 0; }));
    $summary['has_incidents'] = count(array_filter($polling_units, function($pu) { return (int)($pu['total_incidents'] ?? 0) > 0; }));
    
} catch (Exception $e) {
    error_log("Error fetching PU status: " . $e->getMessage());
}

$page_title = 'Polling Unit Status';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.status-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.status-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.status-header h2 i {
    color: var(--primary);
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.stat-mini {
    background: white;
    padding: 10px 14px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-mini .number {
    font-size: 1.2rem;
    font-weight: 700;
}
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .number.red { color: #EF4444; }
.stat-mini .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    font-weight: 500;
}

.pu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.pu-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    transition: var(--transition);
}
.pu-card:hover {
    box-shadow: var(--shadow-hover);
}
.pu-card .pu-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}
.pu-card .pu-name {
    font-weight: 600;
    font-size: 0.95rem;
}
.pu-card .pu-code {
    font-size: 0.7rem;
    color: var(--gray-400);
}
.pu-card .status-indicator {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.pu-card .status-indicator.active { background: #D1FAE5; color: #065F46; }
.pu-card .status-indicator.inactive { background: #FEE2E2; color: #991B1B; }

.pu-card .pu-stats {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 4px;
    margin: 10px 0;
    padding: 8px 0;
    border-top: 1px solid var(--gray-100);
    border-bottom: 1px solid var(--gray-100);
}
.pu-card .pu-stats .stat {
    text-align: center;
}
.pu-card .pu-stats .stat .num {
    font-size: 0.95rem;
    font-weight: 700;
}
.pu-card .pu-stats .stat .num.green { color: #10B981; }
.pu-card .pu-stats .stat .num.blue { color: #3B82F6; }
.pu-card .pu-stats .stat .num.orange { color: #F59E0B; }
.pu-card .pu-stats .stat .num.red { color: #EF4444; }
.pu-card .pu-stats .stat .lbl {
    font-size: 0.55rem;
    color: var(--gray-400);
}
.pu-card .pu-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
    font-size: 0.7rem;
    color: var(--gray-500);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
    grid-column: 1/-1;
}
.empty-state i {
    font-size: 4rem;
    color: var(--gray-300);
    margin-bottom: 16px;
}
.empty-state h4 {
    margin: 0 0 8px;
    color: var(--gray-700);
}
.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .pu-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="status-header">
            <div>
                <h2><i class="fas fa-circle"></i> Polling Unit Status</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div>
                <a href="polling-units.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($summary['total']); ?></div>
                <div class="label">Total PUs</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($summary['active']); ?></div>
                <div class="label">Active</div>
            </div>
            <div class="stat-mini">
                <div class="number red"><?php echo number_format($summary['inactive']); ?></div>
                <div class="label">Inactive</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($summary['has_submissions']); ?></div>
                <div class="label">Has Submissions</div>
            </div>
            <div class="stat-mini">
                <div class="number red"><?php echo number_format($summary['has_incidents']); ?></div>
                <div class="label">Has Incidents</div>
            </div>
        </div>

        <!-- Polling Units Grid -->
        <div class="pu-grid">
            <?php if (count($polling_units) > 0): ?>
                <?php foreach ($polling_units as $pu): 
                    $is_active = (int)($pu['is_active'] ?? 0) === 1;
                    $has_incidents = (int)($pu['total_incidents'] ?? 0) > 0;
                    $has_pending = (int)($pu['pending_submissions'] ?? 0) > 0;
                    $checked_in = (int)($pu['checked_in_today'] ?? 0);
                ?>
                    <div class="pu-card">
                        <div class="pu-header">
                            <div>
                                <div class="pu-name"><?php echo htmlspecialchars($pu['name']); ?></div>
                                <div class="pu-code"><?php echo htmlspecialchars($pu['code']); ?></div>
                            </div>
                            <span class="status-indicator <?php echo $is_active ? 'active' : 'inactive'; ?>">
                                <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        
                        <div style="font-size:0.75rem;color:var(--gray-500);margin-bottom:4px;">
                            <i class="fas fa-users"></i> <?php echo number_format($pu['registered_voters'] ?? 0); ?> voters
                            • <?php echo ($pu['is_rural'] ?? 0) ? 'Rural' : 'Urban'; ?>
                        </div>
                        
                        <div class="pu-stats">
                            <div class="stat">
                                <div class="num blue"><?php echo number_format($pu['total_agents'] ?? 0); ?></div>
                                <div class="lbl">Agents</div>
                            </div>
                            <div class="stat">
                                <div class="num <?php echo ($pu['verified_submissions'] ?? 0) > 0 ? 'green' : 'blue'; ?>">
                                    <?php echo number_format($pu['verified_submissions'] ?? 0); ?>
                                </div>
                                <div class="lbl">Verified</div>
                            </div>
                            <div class="stat">
                                <div class="num <?php echo $has_pending ? 'orange' : 'blue'; ?>">
                                    <?php echo number_format($pu['pending_submissions'] ?? 0); ?>
                                </div>
                                <div class="lbl">Pending</div>
                            </div>
                            <div class="stat">
                                <div class="num <?php echo $has_incidents ? 'red' : 'blue'; ?>">
                                    <?php echo number_format($pu['total_incidents'] ?? 0); ?>
                                </div>
                                <div class="lbl">Incidents</div>
                            </div>
                            <div class="stat">
                                <div class="num <?php echo $checked_in > 0 ? 'green' : 'blue'; ?>">
                                    <?php echo number_format($checked_in); ?>
                                </div>
                                <div class="lbl">Checked In</div>
                            </div>
                            <div class="stat">
                                <div class="num blue"><?php echo number_format($pu['active_agents'] ?? 0); ?></div>
                                <div class="lbl">Active Agents</div>
                            </div>
                        </div>
                        
                        <div class="pu-footer">
                            <a href="pu-details.php?id=<?php echo $pu['id']; ?>" class="btn-secondary-sm" style="padding:4px 12px;font-size:0.7rem;">
                                <i class="fas fa-info-circle"></i> Details
                            </a>
                            <a href="reports-pu.php?id=<?php echo $pu['id']; ?>" class="btn-secondary-sm" style="padding:4px 12px;font-size:0.7rem;">
                                <i class="fas fa-file-alt"></i> Report
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-flag-checkered"></i>
                    <h4>No Polling Units</h4>
                    <p>No polling units found in this ward.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Preloader
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// Sidebar toggle
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

// Sidebar dropdowns
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

// Profile dropdown
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