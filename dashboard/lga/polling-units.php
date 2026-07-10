<?php
// ============================================================
// LGA COORDINATOR - VIEW POLLING UNITS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'lga') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'LGA Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$lga_id = SessionManager::get('lga_id');
$ward_filter = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;

if (empty($lga_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT lga_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['lga_id'])) {
            $lga_id = $user['lga_id'];
            SessionManager::set('lga_id', $lga_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching lga_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get LGA name
$lga_name = 'LGA';
try {
    if ($lga_id) {
        $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA: " . $e->getMessage());
}

// Get wards for filter
$wards = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}

// Fetch polling units
$polling_units = [];
try {
    $sql = "
        SELECT 
            pu.id,
            pu.code,
            pu.name,
            pu.registered_voters,
            pu.gps_lat,
            pu.gps_lng,
            pu.is_rural,
            pu.network_quality,
            w.name as ward_name,
            w.id as ward_id,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.pu_id = pu.id AND r.status IN ('verified', 'approved')) as verified_results,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.pu_id = pu.id AND r.status = 'pending') as pending_results,
            (SELECT COUNT(*) FROM users u WHERE u.pu_id = pu.id AND u.status = 'active') as total_agents,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id IN (SELECT id FROM users WHERE pu_id = pu.id) AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as online_agents
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        WHERE w.lga_id = ?
        AND pu.is_active = 1
    ";
    $params = [$lga_id];
    
    if ($ward_filter > 0) {
        $sql .= " AND pu.ward_id = ?";
        $params[] = $ward_filter;
    }
    
    $sql .= " ORDER BY w.name ASC, pu.name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

$page_title = 'Polling Units';
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
    padding: 12px 18px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
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

.filter-bar .filter-info {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-left: auto;
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

.pu-card .pu-ward {
    font-size: 0.7rem;
    color: var(--primary);
    margin: 4px 0 8px;
}

.pu-card .pu-ward i {
    margin-right: 4px;
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

.pu-card .pu-actions .btn-results {
    background: #EFF6FF;
    color: #3B82F6;
}

.pu-card .pu-actions .btn-results:hover {
    background: #DBEAFE;
}

.pu-card .pu-actions .btn-agents {
    background: #ECFDF5;
    color: #10B981;
}

.pu-card .pu-actions .btn-agents:hover {
    background: #D1FAE5;
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
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar select {
        width: 100%;
        min-width: unset;
    }
    .filter-bar .filter-info {
        margin-left: 0;
        text-align: center;
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
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo htmlspecialchars($lga_name); ?> LGA - Polling Units
                </p>
            </div>
            <div class="actions">
                <a href="manage-wards.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Wards
                </a>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <select id="wardFilter" onchange="window.location.href='?ward_id='+this.value">
                <option value="0">All Wards</option>
                <?php foreach ($wards as $w): ?>
                    <option value="<?php echo $w['id']; ?>" <?php echo $ward_filter == $w['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($w['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <span class="filter-info">
                <i class="fas fa-list"></i> <?php echo count($polling_units); ?> polling units found
            </span>
        </div>

        <!-- PU Grid -->
        <div class="pu-grid">
            <?php foreach ($polling_units as $pu): ?>
                <div class="pu-card">
                    <div class="pu-header">
                        <div class="pu-name"><?php echo htmlspecialchars($pu['name']); ?></div>
                        <span class="pu-code"><?php echo htmlspecialchars($pu['code']); ?></span>
                    </div>
                    <div class="pu-ward">
                        <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($pu['ward_name']); ?>
                    </div>

                    <div class="pu-stats">
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($pu['registered_voters']); ?></div>
                            <div class="label">Voters</div>
                        </div>
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($pu['verified_results']); ?></div>
                            <div class="label">Verified</div>
                        </div>
                        <div class="stat-item">
                            <div class="number" style="color:<?php echo $pu['pending_results'] > 0 ? '#F59E0B' : '#10B981'; ?>;">
                                <?php echo number_format($pu['pending_results']); ?>
                            </div>
                            <div class="label">Pending</div>
                        </div>
                    </div>

                    <div class="pu-meta">
                        <span><i class="fas fa-users"></i> <?php echo number_format($pu['total_agents']); ?> agents</span>
                        <span><i class="fas fa-wifi"></i> <?php echo number_format($pu['online_agents']); ?> online</span>
                        <?php if ($pu['is_rural']): ?>
                            <span><i class="fas fa-tree"></i> Rural</span>
                        <?php endif; ?>
                        <?php if ($pu['network_quality']): ?>
                            <span><i class="fas fa-signal"></i> <?php echo strtoupper($pu['network_quality']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="pu-actions">
                        <a href="view-pu-results.php?pu_id=<?php echo $pu['id']; ?>" class="btn-results">
                            <i class="fas fa-check-double"></i> Results
                        </a>
                        <a href="pu-agents.php?pu_id=<?php echo $pu['id']; ?>" class="btn-agents">
                            <i class="fas fa-users"></i> Agents
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($polling_units)): ?>
                <div class="empty-state">
                    <i class="fas fa-flag-checkered"></i>
                    <h3>No Polling Units Found</h3>
                    <p>No polling units found in <?php echo htmlspecialchars($lga_name); ?>.</p>
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