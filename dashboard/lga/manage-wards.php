<?php
// ============================================================
// LGA COORDINATOR - MANAGE WARDS
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
$state_id = SessionManager::get('state_id');

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
$state_name = 'State';
try {
    if ($lga_id) {
        $stmt = $db->prepare("
            SELECT l.name as lga_name, s.name as state_name 
            FROM lgas l 
            JOIN states s ON l.state_id = s.id 
            WHERE l.id = ?
        ");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA/State: " . $e->getMessage());
}

// Fetch wards with statistics
$wards = [];
try {
    $stmt = $db->prepare("
        SELECT 
            w.id,
            w.name,
            w.code,
            w.registered_voters,
            COUNT(DISTINCT pu.id) as total_pus,
            COUNT(DISTINCT u.id) as total_coordinators,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_coordinators,
            COUNT(DISTINCT r.id) as submitted_results,
            COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_results,
            COUNT(DISTINCT i.id) as incidents,
            COUNT(DISTINCT CASE WHEN i.status IN ('reported', 'investigating') THEN i.id END) as active_incidents
        FROM wards w
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        LEFT JOIN users u ON u.ward_id = w.id
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        LEFT JOIN incidents i ON i.ward_id = w.id
        WHERE w.lga_id = ? AND w.is_active = 1
        GROUP BY w.id, w.name, w.code, w.registered_voters
        ORDER BY w.name ASC
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}

$page_title = 'Manage Wards';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.ward-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.ward-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 20px;
    transition: var(--transition);
}

.ward-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-hover);
}

.ward-card .ward-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.ward-card .ward-header .ward-name {
    font-weight: 600;
    font-size: 1rem;
    color: var(--gray-800);
}

.ward-card .ward-header .ward-code {
    font-size: 0.6rem;
    color: var(--gray-400);
    background: var(--gray-100);
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 500;
}

.ward-card .ward-stats {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 6px;
    margin: 10px 0;
}

.ward-card .ward-stats .stat-item {
    text-align: center;
    padding: 6px 4px;
    background: var(--gray-50);
    border-radius: 6px;
}

.ward-card .ward-stats .stat-item .number {
    font-size: 1rem;
    font-weight: 700;
    color: var(--gray-800);
}

.ward-card .ward-stats .stat-item .label {
    font-size: 0.5rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.ward-card .ward-progress {
    margin: 8px 0;
}

.ward-card .ward-progress .progress-bar {
    height: 4px;
    background: var(--gray-200);
    border-radius: 2px;
    overflow: hidden;
}

.ward-card .ward-progress .progress-bar .fill {
    height: 100%;
    background: var(--primary);
    border-radius: 2px;
    transition: width 0.8s ease;
}

.ward-card .ward-progress .progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.6rem;
    color: var(--gray-500);
    margin-top: 3px;
}

.ward-card .ward-actions {
    display: flex;
    gap: 6px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.ward-card .ward-actions a {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.ward-card .ward-actions .btn-view {
    background: var(--primary);
    color: white;
}

.ward-card .ward-actions .btn-view:hover {
    background: var(--primary-dark);
}

.ward-card .ward-actions .btn-coordinators {
    background: var(--gray-100);
    color: var(--gray-700);
}

.ward-card .ward-actions .btn-coordinators:hover {
    background: var(--gray-200);
}

.ward-card .ward-actions .btn-pus {
    background: #EFF6FF;
    color: #3B82F6;
}

.ward-card .ward-actions .btn-pus:hover {
    background: #DBEAFE;
}

.ward-card .ward-actions .btn-results {
    background: #ECFDF5;
    color: #10B981;
}

.ward-card .ward-actions .btn-results:hover {
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

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.summary-stat {
    background: white;
    border-radius: 12px;
    padding: 14px 16px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.summary-stat .number {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gray-800);
}

.summary-stat .label {
    font-size: 0.65rem;
    color: var(--gray-500);
}

@media (max-width: 768px) {
    .ward-grid {
        grid-template-columns: 1fr;
    }
    .ward-card .ward-stats {
        grid-template-columns: 1fr 1fr 1fr;
    }
    .summary-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-layer-group"></i> Manage Wards</h1>
                <p class="subtitle">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo htmlspecialchars($lga_name); ?> LGA - Ward Management
                </p>
            </div>
            <div class="actions">
                <a href="ward-coordinators.php" class="btn-secondary-sm">
                    <i class="fas fa-user-tie"></i> Coordinators
                </a>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="summary-stats">
            <div class="summary-stat">
                <div class="number"><?php echo count($wards); ?></div>
                <div class="label">Total Wards</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo number_format(array_sum(array_column($wards, 'total_pus'))); ?></div>
                <div class="label">Polling Units</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo number_format(array_sum(array_column($wards, 'total_coordinators'))); ?></div>
                <div class="label">Coordinators</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo number_format(array_sum(array_column($wards, 'verified_results'))); ?></div>
                <div class="label">Verified Results</div>
            </div>
        </div>

        <!-- Ward Grid -->
        <div class="ward-grid">
            <?php foreach ($wards as $ward): 
                $reporting_rate = $ward['total_pus'] > 0 ? round(($ward['submitted_results'] / $ward['total_pus']) * 100, 1) : 0;
                $verification_rate = $ward['submitted_results'] > 0 ? round(($ward['verified_results'] / $ward['submitted_results']) * 100, 1) : 0;
            ?>
                <div class="ward-card">
                    <div class="ward-header">
                        <div>
                            <div class="ward-name"><?php echo htmlspecialchars($ward['name']); ?></div>
                            <div style="font-size:0.65rem;color:var(--gray-500);margin-top:2px;">
                                <i class="fas fa-map-pin"></i> Code: <?php echo htmlspecialchars($ward['code']); ?>
                                <span style="margin:0 4px;">•</span>
                                <i class="fas fa-users"></i> <?php echo number_format($ward['registered_voters']); ?> voters
                            </div>
                        </div>
                        <span class="ward-code"><?php echo number_format($ward['total_pus']); ?> PUs</span>
                    </div>

                    <div class="ward-stats">
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($ward['total_coordinators']); ?></div>
                            <div class="label">Coordinators</div>
                        </div>
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($ward['verified_results']); ?></div>
                            <div class="label">Verified</div>
                        </div>
                        <div class="stat-item">
                            <div class="number" style="color:<?php echo $ward['active_incidents'] > 0 ? '#EF4444' : '#10B981'; ?>;">
                                <?php echo number_format($ward['active_incidents']); ?>
                            </div>
                            <div class="label">Incidents</div>
                        </div>
                    </div>

                    <div class="ward-progress">
                        <div class="progress-bar">
                            <div class="fill" style="width: <?php echo $reporting_rate; ?>%;"></div>
                        </div>
                        <div class="progress-label">
                            <span>Reporting Rate</span>
                            <span><?php echo $reporting_rate; ?>%</span>
                        </div>
                    </div>

                    <div class="ward-actions">
                        <a href="polling-units.php?ward_id=<?php echo $ward['id']; ?>" class="btn-pus">
                            <i class="fas fa-flag-checkered"></i> PUs
                        </a>
                        <a href="ward-coordinators.php?ward_id=<?php echo $ward['id']; ?>" class="btn-coordinators">
                            <i class="fas fa-user-tie"></i> Coordinators
                        </a>
                        <a href="approve-results.php?ward_id=<?php echo $ward['id']; ?>" class="btn-results">
                            <i class="fas fa-check-double"></i> Results
                        </a>
                        <a href="incidents.php?ward_id=<?php echo $ward['id']; ?>" class="btn-view">
                            <i class="fas fa-exclamation-triangle"></i> Incidents
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($wards)): ?>
                <div class="empty-state">
                    <i class="fas fa-layer-group"></i>
                    <h3>No Wards Found</h3>
                    <p>No wards have been added to <?php echo htmlspecialchars($lga_name); ?> LGA yet.</p>
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