<?php
// ============================================================
// STATE COORDINATOR - MONITOR LGAS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

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

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Fetch LGAs with statistics
$lgas = [];
$total_registered_voters = 0;
$total_reported_pus = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            l.id,
            l.name,
            l.code,
            l.registered_voters,
            l.gps_lat,
            l.gps_lng,
            COUNT(DISTINCT w.id) as total_wards,
            COUNT(DISTINCT pu.id) as total_pus,
            COUNT(DISTINCT r.pu_id) as reported_pus,
            COUNT(DISTINCT u.id) as total_coordinators,
            COUNT(DISTINCT CASE WHEN us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN u.id END) as online_agents,
            (SELECT COUNT(*) FROM incidents i WHERE i.lga_id = l.id AND i.status IN ('reported', 'acknowledged', 'investigating')) as pending_incidents
        FROM lgas l
        LEFT JOIN wards w ON w.lga_id = l.id
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        LEFT JOIN users u ON u.lga_id = l.id AND u.status = 'active' AND u.deleted_at IS NULL
        LEFT JOIN user_sessions us ON us.user_id = u.id AND us.is_active = 1
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.status IN ('pending', 'verified', 'approved')
        WHERE l.state_id = ? AND l.is_active = 1
        GROUP BY l.id, l.name, l.code, l.registered_voters, l.gps_lat, l.gps_lng
        ORDER BY l.name ASC
    ");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($lgas as $lga) {
        $total_registered_voters += $lga['registered_voters'] ?? 0;
        $total_reported_pus += $lga['reported_pus'] ?? 0;
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// Calculate overall stats
$total_pus = array_sum(array_column($lgas, 'total_pus'));
$total_coordinators = array_sum(array_column($lgas, 'total_coordinators'));
$total_online = array_sum(array_column($lgas, 'online_agents'));
$total_pending_incidents = array_sum(array_column($lgas, 'pending_incidents'));
$reporting_rate = $total_pus > 0 ? round(($total_reported_pus / $total_pus) * 100, 1) : 0;

$page_title = 'Monitor LGAs';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.lga-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.lga-card {
    background: white;
    border-radius: var(--radius);
    padding: 18px 20px;
    border: 1px solid var(--gray-200);
    transition: var(--transition);
    position: relative;
}

.lga-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-hover);
}

.lga-card .lga-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.lga-card .lga-header .lga-name {
    font-weight: 600;
    font-size: 1rem;
    color: var(--gray-800);
}

.lga-card .lga-header .lga-code {
    font-size: 0.6rem;
    color: var(--gray-400);
    background: var(--gray-100);
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 500;
}

.lga-card .lga-stats {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 8px;
    margin: 12px 0;
}

.lga-card .lga-stats .stat-item {
    text-align: center;
    padding: 8px 4px;
    background: var(--gray-50);
    border-radius: 8px;
}

.lga-card .lga-stats .stat-item .number {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gray-800);
}

.lga-card .lga-stats .stat-item .label {
    font-size: 0.55rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.lga-card .lga-progress {
    margin: 10px 0;
}

.lga-card .lga-progress .progress-bar {
    height: 6px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
}

.lga-card .lga-progress .progress-bar .fill {
    height: 100%;
    background: var(--primary);
    border-radius: 4px;
    transition: width 0.8s ease;
}

.lga-card .lga-progress .progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.65rem;
    color: var(--gray-500);
    margin-top: 4px;
}

.lga-card .lga-actions {
    display: flex;
    gap: 6px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.lga-card .lga-actions a {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.lga-card .lga-actions .btn-view {
    background: var(--primary);
    color: white;
}

.lga-card .lga-actions .btn-view:hover {
    background: var(--primary-dark);
}

.lga-card .lga-actions .btn-coordinators {
    background: var(--gray-100);
    color: var(--gray-700);
}

.lga-card .lga-actions .btn-coordinators:hover {
    background: var(--gray-200);
}

.lga-card .lga-actions .btn-incidents {
    background: #FEF2F2;
    color: #DC2626;
}

.lga-card .lga-actions .btn-incidents:hover {
    background: #FEE2E2;
}

.lga-card .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
}

.lga-card .status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.lga-card .status-badge.online { background: #ECFDF5; color: #065F46; }
.lga-card .status-badge.online .dot { background: #10B981; }

.lga-card .status-badge.offline { background: #F3F4F6; color: #6B7280; }
.lga-card .status-badge.offline .dot { background: #9CA3AF; }

.lga-card .status-badge.partial { background: #FFFBEB; color: #92400E; }
.lga-card .status-badge.partial .dot { background: #F59E0B; }

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.summary-stat {
    background: white;
    border-radius: 12px;
    padding: 14px 16px;
    border: 1px solid var(--gray-200);
}

.summary-stat .number {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--gray-800);
}

.summary-stat .label {
    font-size: 0.7rem;
    color: var(--gray-500);
}

@media (max-width: 768px) {
    .lga-grid {
        grid-template-columns: 1fr;
    }
    .lga-card .lga-stats {
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
                <h1><i class="fas fa-map-marker-alt"></i> Monitor LGAs</h1>
                <p class="subtitle">
                    <i class="fas fa-flag"></i> 
                    <?php echo htmlspecialchars($state_name); ?> State - LGA Performance Overview
                </p>
            </div>
            <div class="actions">
                <a href="export-excel.php?type=lgas" class="btn-secondary-sm">
                    <i class="fas fa-file-excel"></i> Export
                </a>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="summary-stats">
            <div class="summary-stat">
                <div class="number"><?php echo count($lgas); ?></div>
                <div class="label">Total LGAs</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo number_format($total_pus); ?></div>
                <div class="label">Polling Units</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo number_format($total_reported_pus); ?></div>
                <div class="label">Reported PUs</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo $reporting_rate; ?>%</div>
                <div class="label">Reporting Rate</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo number_format($total_coordinators); ?></div>
                <div class="label">Coordinators</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo number_format($total_online); ?></div>
                <div class="label">Online Agents</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo number_format($total_pending_incidents); ?></div>
                <div class="label">Pending Incidents</div>
            </div>
            <div class="summary-stat">
                <div class="number"><?php echo number_format($total_registered_voters); ?></div>
                <div class="label">Registered Voters</div>
            </div>
        </div>

        <!-- LGA Grid -->
        <div class="lga-grid">
            <?php foreach ($lgas as $lga): 
                $reporting_rate_lga = $lga['total_pus'] > 0 ? round(($lga['reported_pus'] / $lga['total_pus']) * 100, 1) : 0;
                $status_class = $reporting_rate_lga >= 90 ? 'online' : ($reporting_rate_lga >= 50 ? 'partial' : 'offline');
                $status_label = $reporting_rate_lga >= 90 ? 'High Reporting' : ($reporting_rate_lga >= 50 ? 'Partial Reporting' : 'Low Reporting');
            ?>
                <div class="lga-card">
                    <div class="lga-header">
                        <div>
                            <div class="lga-name"><?php echo htmlspecialchars($lga['name']); ?></div>
                            <div style="font-size:0.65rem;color:var(--gray-500);margin-top:2px;">
                                <i class="fas fa-map-pin"></i> Code: <?php echo htmlspecialchars($lga['code']); ?>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <span class="dot"></span>
                            <?php echo $status_label; ?>
                        </span>
                    </div>

                    <div class="lga-stats">
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($lga['total_pus']); ?></div>
                            <div class="label">PUs</div>
                        </div>
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($lga['reported_pus']); ?></div>
                            <div class="label">Reported</div>
                        </div>
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($lga['online_agents']); ?></div>
                            <div class="label">Online</div>
                        </div>
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($lga['total_coordinators']); ?></div>
                            <div class="label">Coordinators</div>
                        </div>
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($lga['registered_voters']); ?></div>
                            <div class="label">Voters</div>
                        </div>
                        <div class="stat-item">
                            <div class="number" style="color:<?php echo $lga['pending_incidents'] > 0 ? '#EF4444' : '#10B981'; ?>;">
                                <?php echo number_format($lga['pending_incidents']); ?>
                            </div>
                            <div class="label">Incidents</div>
                        </div>
                    </div>

                    <div class="lga-progress">
                        <div class="progress-bar">
                            <div class="fill" style="width: <?php echo $reporting_rate_lga; ?>%;"></div>
                        </div>
                        <div class="progress-label">
                            <span>Reporting Rate</span>
                            <span><?php echo $reporting_rate_lga; ?>%</span>
                        </div>
                    </div>

                    <div class="lga-actions">
                        <a href="lga-coordinators.php?lga_id=<?php echo $lga['id']; ?>" class="btn-coordinators">
                            <i class="fas fa-user-tie"></i> Coordinators
                        </a>
                        <a href="incidents.php?lga_id=<?php echo $lga['id']; ?>" class="btn-incidents">
                            <i class="fas fa-exclamation-triangle"></i> Incidents
                        </a>
                        <a href="reports-lga-performance.php?lga_id=<?php echo $lga['id']; ?>" class="btn-view">
                            <i class="fas fa-chart-bar"></i> Report
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($lgas)): ?>
                <div style="grid-column:1/-1;text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                    <i class="fas fa-map-marker-alt" style="font-size:3rem;color:var(--gray-300);display:block;margin-bottom:12px;"></i>
                    <h3 style="color:var(--gray-600);margin:0;">No LGAs Found</h3>
                    <p style="color:var(--gray-400);margin-top:6px;">No local government areas have been added to this state yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Same scripts as index.php
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