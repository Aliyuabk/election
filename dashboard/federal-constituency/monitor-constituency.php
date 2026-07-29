<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - MONITOR CONSTITUENCY
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'federal_constituency') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$constituency_id = SessionManager::get('federal_constituency_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// Get constituency details
$constituency = null;
$lga_ids = [];
try {
    $stmt = $db->prepare("
        SELECT fc.*, s.name as state_name
        FROM federal_constituencies fc
        JOIN states s ON fc.state_id = s.id
        WHERE fc.id = ?
    ");
    $stmt->execute([$constituency_id]);
    $constituency = $stmt->fetch();
    
    if ($constituency && !empty($constituency['lgas_json'])) {
        $lga_ids = json_decode($constituency['lgas_json'], true) ?: [];
    }
} catch (Exception $e) {
    error_log("Error fetching constituency: " . $e->getMessage());
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// Get LGA data
$lgas = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                l.id,
                l.name,
                l.code,
                COUNT(DISTINCT w.id) as ward_count,
                COUNT(DISTINCT pu.id) as pu_count,
                (SELECT COUNT(*) FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 WHERE u.lga_id = l.id AND u.tenant_id = ? AND u.status = 'active' AND r.level = 'lga') as coordinator_count
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            WHERE l.id IN ($lga_list) AND l.is_active = 1
            GROUP BY l.id
            ORDER BY l.name ASC
        ");
        $stmt->execute([$tenant_id]);
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// Get polling unit status summary
$pu_status = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END) as has_result,
                SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            WHERE w.lga_id IN ($lga_list) AND pu.is_active = 1
        ");
        $stmt->execute([$tenant_id]);
        $pu_status = $stmt->fetch();
    }
} catch (Exception $e) {
    error_log("Error fetching PU status: " . $e->getMessage());
}

$page_title = 'Monitor Constituency';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.constituency-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 30px;
    margin-bottom: 24px;
}
.constituency-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.constituency-header .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-top: 4px;
}
.constituency-header .stats-row {
    display: flex;
    gap: 30px;
    margin-top: 12px;
    flex-wrap: wrap;
}
.constituency-header .stats-row .stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
}
.constituency-header .stats-row .stat-item .number {
    font-weight: 700;
    color: var(--primary);
}

.lga-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}
.lga-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    transition: var(--transition);
}
.lga-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow);
}
.lga-card .lga-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.lga-card .lga-header .lga-name {
    font-weight: 600;
    font-size: 0.95rem;
}
.lga-card .lga-header .lga-code {
    font-size: 0.7rem;
    color: var(--gray-400);
    background: var(--gray-100);
    padding: 2px 10px;
    border-radius: 12px;
}
.lga-card .lga-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-top: 12px;
}
.lga-card .lga-stats .stat {
    text-align: center;
    padding: 8px;
    background: var(--gray-50);
    border-radius: 8px;
}
.lga-card .lga-stats .stat .number {
    font-weight: 700;
    font-size: 1.1rem;
}
.lga-card .lga-stats .stat .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    text-transform: uppercase;
}
.lga-card .lga-actions {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--gray-100);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.lga-card .lga-actions a {
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
}
.lga-card .lga-actions .btn-view {
    background: var(--primary);
    color: white;
}
.lga-card .lga-actions .btn-view:hover {
    background: var(--primary-dark);
}
.lga-card .lga-actions .btn-details {
    background: var(--gray-100);
    color: var(--gray-600);
}
.lga-card .lga-actions .btn-details:hover {
    background: var(--gray-200);
}

@media (max-width: 768px) {
    .lga-grid {
        grid-template-columns: 1fr;
    }
    .constituency-header .stats-row {
        gap: 16px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Constituency Header -->
        <div class="constituency-header">
            <h2>
                <i class="fas fa-building" style="color:var(--primary);"></i>
                <?php echo htmlspecialchars($constituency['name'] ?? 'Federal Constituency'); ?>
            </h2>
            <div class="subtitle">
                <?php echo htmlspecialchars($constituency['state_name'] ?? 'State'); ?>
                <span style="margin-left:12px;color:var(--gray-300);">|</span>
                Code: <?php echo htmlspecialchars($constituency['code'] ?? 'N/A'); ?>
            </div>
            <div class="stats-row">
                <div class="stat-item">
                    <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i>
                    <span class="number"><?php echo count($lgas); ?></span> LGAs
                </div>
                <div class="stat-item">
                    <i class="fas fa-layer-group" style="color:var(--primary);"></i>
                    <span class="number"><?php echo array_sum(array_column($lgas, 'ward_count')); ?></span> Wards
                </div>
                <div class="stat-item">
                    <i class="fas fa-flag-checkered" style="color:var(--primary);"></i>
                    <span class="number"><?php echo array_sum(array_column($lgas, 'pu_count')); ?></span> PUs
                </div>
                <div class="stat-item">
                    <i class="fas fa-user-tie" style="color:var(--primary);"></i>
                    <span class="number"><?php echo array_sum(array_column($lgas, 'coordinator_count')); ?></span> Coordinators
                </div>
                <div class="stat-item">
                    <i class="fas fa-check-circle" style="color:#10B981;"></i>
                    <span class="number"><?php echo $pu_status['verified'] ?? 0; ?></span> Verified
                </div>
                <div class="stat-item">
                    <i class="fas fa-clock" style="color:#F59E0B;"></i>
                    <span class="number"><?php echo $pu_status['pending'] ?? 0; ?></span> Pending
                </div>
            </div>
        </div>

        <!-- LGA Grid -->
        <div class="lga-grid">
            <?php if (count($lgas) > 0): ?>
                <?php foreach ($lgas as $lga): ?>
                    <div class="lga-card">
                        <div class="lga-header">
                            <div>
                                <div class="lga-name"><?php echo htmlspecialchars($lga['name']); ?></div>
                                <div style="font-size:0.7rem;color:var(--gray-400);">
                                    <?php echo htmlspecialchars($lga['code'] ?? 'N/A'); ?>
                                </div>
                            </div>
                            <span class="lga-code">
                                <?php echo $lga['coordinator_count'] ?? 0; ?> coordinator(s)
                            </span>
                        </div>
                        <div class="lga-stats">
                            <div class="stat">
                                <div class="number"><?php echo number_format($lga['ward_count'] ?? 0); ?></div>
                                <div class="label">Wards</div>
                            </div>
                            <div class="stat">
                                <div class="number"><?php echo number_format($lga['pu_count'] ?? 0); ?></div>
                                <div class="label">PUs</div>
                            </div>
                            <div class="stat">
                                <div class="number"><?php echo number_format($lga['coordinator_count'] ?? 0); ?></div>
                                <div class="label">Coordinators</div>
                            </div>
                        </div>
                        <div class="lga-actions">
                            <a href="view-lga-details.php?id=<?php echo $lga['id']; ?>" class="btn-view">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <a href="coordinators.php?lga=<?php echo $lga['id']; ?>" class="btn-details">
                                <i class="fas fa-user-tie"></i> Coordinators
                            </a>
                            <a href="monitor-pus.php?lga=<?php echo $lga['id']; ?>" class="btn-details">
                                <i class="fas fa-flag-checkered"></i> PUs
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--gray-500);">
                    <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p>No LGAs found in this constituency.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
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