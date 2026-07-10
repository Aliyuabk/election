<?php
// ============================================================
// WARD COORDINATOR - VIEW POLLING UNITS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Ward Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');

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

// Get ward name
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
    error_log("Error fetching ward: " . $e->getMessage());
}

// Fetch polling units
$polling_units = [];
$stats = [
    'total' => 0,
    'with_agents' => 0,
    'without_agents' => 0,
    'with_results' => 0,
    'total_voters' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            pu.id,
            pu.code,
            pu.name,
            pu.registered_voters,
            pu.gps_lat,
            pu.gps_lng,
            pu.is_rural,
            pu.network_quality,
            pu.address,
            (SELECT COUNT(*) FROM users u WHERE u.pu_id = pu.id AND u.status = 'active' AND u.role_id IN (SELECT id FROM roles WHERE level = 'pu_agent')) as agent_count,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.pu_id = pu.id AND r.status IN ('verified', 'approved')) as verified_results,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.pu_id = pu.id AND r.status = 'pending') as pending_results,
            (SELECT COUNT(*) FROM incidents i WHERE i.pu_id = pu.id AND i.status IN ('reported', 'investigating')) as active_incidents
        FROM polling_units pu
        WHERE pu.ward_id = ? AND pu.is_active = 1
        ORDER BY pu.name ASC
    ");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($polling_units as $pu) {
        $stats['total']++;
        $stats['total_voters'] += $pu['registered_voters'];
        if ($pu['agent_count'] > 0) {
            $stats['with_agents']++;
        } else {
            $stats['without_agents']++;
        }
        if ($pu['verified_results'] > 0 || $pu['pending_results'] > 0) {
            $stats['with_results']++;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

$page_title = 'Polling Units';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.summary-stat {
    background: white;
    border-radius: 10px;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.summary-stat .number {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gray-800);
}

.summary-stat .label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

.pu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
}

.pu-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 18px;
    transition: var(--transition);
}

.pu-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.pu-card .pu-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.pu-card .pu-header .pu-name {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--gray-800);
}

.pu-card .pu-header .pu-code {
    font-size: 0.55rem;
    color: var(--gray-400);
    background: var(--gray-100);
    padding: 2px 8px;
    border-radius: 8px;
    font-weight: 500;
}

.pu-card .pu-stats {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 6px;
    margin: 8px 0;
    padding: 8px 0;
    border-top: 1px solid var(--gray-100);
    border-bottom: 1px solid var(--gray-100);
}

.pu-card .pu-stats .stat-item {
    text-align: center;
}

.pu-card .pu-stats .stat-item .number {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--gray-800);
}

.pu-card .pu-stats .stat-item .label {
    font-size: 0.5rem;
    color: var(--gray-500);
    text-transform: uppercase;
}

.pu-card .pu-meta {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 6px;
    font-size: 0.6rem;
    color: var(--gray-400);
}

.pu-card .pu-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.pu-card .pu-actions {
    display: flex;
    gap: 6px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.pu-card .pu-actions a {
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 0.6rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.pu-card .pu-actions .btn-agents {
    background: #EFF6FF;
    color: #3B82F6;
}

.pu-card .pu-actions .btn-agents:hover {
    background: #DBEAFE;
}

.pu-card .pu-actions .btn-results {
    background: #ECFDF5;
    color: #10B981;
}

.pu-card .pu-actions .btn-results:hover {
    background: #D1FAE5;
}

.pu-card .pu-actions .btn-assign {
    background: #F5F3FF;
    color: #8B5CF6;
}

.pu-card .pu-actions .btn-assign:hover {
    background: #EDE9FE;
}

.pu-card .pu-actions .btn-incidents {
    background: #FEF2F2;
    color: #DC2626;
}

.pu-card .pu-actions .btn-incidents:hover {
    background: #FEE2E2;
}

.empty-state {
    grid-column: 1/-1;
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h3 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    margin-top: 6px;
}

@media (max-width: 768px) {
    .pu-grid {
        grid-template-columns: 1fr;
    }
    .summary-stats {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-flag-checkered"></i> Polling Units</h1>
                <p class="subtitle">
                    <i class="fas fa-layer-group"></i> 
                    <?php echo htmlspecialchars($ward_name); ?> Ward - Polling Units
                </p>
            </div>
            <div class="actions">
                <a href="manage-pu-agents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Agents
                </a>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="summary-stats">
            <div class="summary-stat">
                <div class="number"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total PUs</div>
            </div>
            <div class="summary-stat">
                <div class="number" style="color:#10B981;"><?php echo number_format($stats['with_agents']); ?></div>
                <div class="label">With Agents</div>
            </div>
            <div class="summary-stat">
                <div class="number" style="color:#EF4444;"><?php echo number_format($stats['without_agents']); ?></div>
                <div class="label">No Agent</div>
            </div>
            <div class="summary-stat">
                <div class="number" style="color:#3B82F6;"><?php echo number_format($stats['with_results']); ?></div>
                <div class="label">With Results</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo number_format($stats['total_voters']); ?></div>
                <div class="label">Total Voters</div>
            </div>
        </div>

        <!-- PU Grid -->
        <div class="pu-grid">
            <?php foreach ($polling_units as $pu): 
                $has_agent = $pu['agent_count'] > 0;
                $has_results = $pu['verified_results'] > 0 || $pu['pending_results'] > 0;
            ?>
                <div class="pu-card">
                    <div class="pu-header">
                        <div class="pu-name"><?php echo htmlspecialchars($pu['name']); ?></div>
                        <span class="pu-code"><?php echo htmlspecialchars($pu['code']); ?></span>
                    </div>

                    <div class="pu-stats">
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($pu['registered_voters']); ?></div>
                            <div class="label">Voters</div>
                        </div>
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($pu['agent_count']); ?></div>
                            <div class="label">Agents</div>
                        </div>
                        <div class="stat-item">
                            <div class="number" style="color:<?php echo $pu['active_incidents'] > 0 ? '#EF4444' : '#10B981'; ?>;">
                                <?php echo number_format($pu['active_incidents']); ?>
                            </div>
                            <div class="label">Incidents</div>
                        </div>
                    </div>

                    <div class="pu-meta">
                        <?php if ($pu['is_rural']): ?>
                            <span><i class="fas fa-tree"></i> Rural</span>
                        <?php endif; ?>
                        <?php if ($pu['network_quality']): ?>
                            <span><i class="fas fa-signal"></i> <?php echo strtoupper($pu['network_quality']); ?></span>
                        <?php endif; ?>
                        <?php if ($pu['verified_results'] > 0): ?>
                            <span><i class="fas fa-check-circle" style="color:#10B981;"></i> <?php echo number_format($pu['verified_results']); ?> verified</span>
                        <?php endif; ?>
                        <?php if ($pu['pending_results'] > 0): ?>
                            <span><i class="fas fa-clock" style="color:#F59E0B;"></i> <?php echo number_format($pu['pending_results']); ?> pending</span>
                        <?php endif; ?>
                    </div>

                    <div class="pu-actions">
                        <a href="pu-agents.php?pu_id=<?php echo $pu['id']; ?>" class="btn-agents">
                            <i class="fas fa-users"></i> Agents
                        </a>
                        <a href="view-pu-results.php?pu_id=<?php echo $pu['id']; ?>" class="btn-results">
                            <i class="fas fa-check-double"></i> Results
                        </a>
                        <?php if (!$has_agent): ?>
                            <a href="assign-agents.php?pu_id=<?php echo $pu['id']; ?>" class="btn-assign">
                                <i class="fas fa-user-plus"></i> Assign
                            </a>
                        <?php endif; ?>
                        <?php if ($pu['active_incidents'] > 0): ?>
                            <a href="incidents.php?pu_id=<?php echo $pu['id']; ?>" class="btn-incidents">
                                <i class="fas fa-exclamation-triangle"></i> Incidents
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($polling_units)): ?>
                <div class="empty-state">
                    <i class="fas fa-flag-checkered"></i>
                    <h3>No Polling Units Found</h3>
                    <p>No polling units found in <?php echo htmlspecialchars($ward_name); ?>.</p>
                </div>
            <?php endif; ?>
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