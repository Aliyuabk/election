<?php
// ============================================================
// SENATORIAL COORDINATOR - LGA DETAILS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$db = getDB();

// Get LGA ID from URL
$lga_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($lga_id <= 0) {
    header('Location: monitor-district.php');
    exit();

## 3. LGA Details (view-lga-details.php) - Continued

    exit();
}

// ============================================================
// GET LGA DETAILS
// ============================================================
$lga = null;
try {
    $stmt = $db->prepare("
        SELECT 
            l.*,
            s.name as state_name,
            s.id as state_id,
            fc.name as federal_constituency_name,
            sd.name as senatorial_name
        FROM lgas l
        JOIN states s ON l.state_id = s.id
        LEFT JOIN federal_constituencies fc ON fc.state_id = s.id
        LEFT JOIN senatorial_districts sd ON sd.state_id = s.id
        WHERE l.id = ? AND l.is_active = 1
    ");
    $stmt->execute([$lga_id]);
    $lga = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching LGA: " . $e->getMessage());
}

if (!$lga) {
    header('Location: monitor-district.php');
    exit();
}

// ============================================================
// GET WARDS WITH PU COUNTS
// ============================================================
$wards = [];
try {
    $stmt = $db->prepare("
        SELECT 
            w.*,
            COUNT(DISTINCT pu.id) as pu_count,
            (SELECT COUNT(*) FROM users u 
             JOIN polling_units pu2 ON u.pu_id = pu2.id 
             WHERE pu2.ward_id = w.id AND u.tenant_id = ? AND u.status = 'active') as agent_count
        FROM wards w
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        WHERE w.lga_id = ? AND w.is_active = 1
        GROUP BY w.id
        ORDER BY w.name ASC
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $wards = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}

// ============================================================
// GET PU LIST
// ============================================================
$polling_units = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.name as ward_name,
            w.id as ward_id,
            (SELECT COUNT(*) FROM agent_assignments aa WHERE aa.pu_id = pu.id AND aa.status = 'active') as agent_count,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.pu_id = pu.id) as result_count
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        WHERE w.lga_id = ? AND pu.is_active = 1
        ORDER BY w.name ASC, pu.name ASC
        LIMIT 100
    ");
    $stmt->execute([$lga_id]);
    $polling_units = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching PUs: " . $e->getMessage());
}

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total_wards' => count($wards),
    'total_pus' => count($polling_units),
    'total_agents' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'total_incidents' => 0,
    'active_incidents' => 0
];

try {
    // Agents
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) as total_agents
        FROM users u
        JOIN roles r ON u.role_id = r.id
        JOIN polling_units pu ON u.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        WHERE u.tenant_id = ? AND u.status = 'active'
        AND r.level IN ('pu_agent', 'party_agent', 'observer')
        AND w.lga_id = ?
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $stats['total_agents'] = (int)$stmt->fetchColumn();
    
    // Results
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_results,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_results,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_results
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        WHERE r.tenant_id = ? AND w.lga_id = ?
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $result = $stmt->fetch();
    $stats['total_results'] = (int)($result['total_results'] ?? 0);
    $stats['verified_results'] = (int)($result['verified_results'] ?? 0);
    $stats['pending_results'] = (int)($result['pending_results'] ?? 0);
    
    // Incidents
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_incidents,
            SUM(CASE WHEN status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as active_incidents
        FROM incidents i
        WHERE i.tenant_id = ? AND i.lga_id = ?
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $result = $stmt->fetch();
    $stats['total_incidents'] = (int)($result['total_incidents'] ?? 0);
    $stats['active_incidents'] = (int)($result['active_incidents'] ?? 0);
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

// ============================================================
// GET RECENT ACTIVITIES
// ============================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT 
            a.*,
            u.full_name as user_name,
            u.photograph_url as user_avatar
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.tenant_id = ? 
        ORDER BY a.created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$tenant_id]);
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching recent activities: " . $e->getMessage());
}

$page_title = 'LGA Details';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.lga-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 30px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
}
.lga-header .header-left h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.lga-header .header-left .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-top: 4px;
}
.lga-header .header-left .subtitle i {
    margin: 0 4px;
    font-size: 0.6rem;
    color: var(--gray-300);
}
.lga-header .header-right .badge-status {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-status.active { background: #D1FAE5; color: #059669; }
.badge-status.pending { background: #FEF3C7; color: #D97706; }

.stats-grid-lga {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.stat-card-small {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 18px;
    text-align: center;
}
.stat-card-small .number {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--gray-800);
}
.stat-card-small .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.lga-tabs {
    display: flex;
    gap: 4px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 4px;
    margin-bottom: 24px;
    overflow-x: auto;
}
.lga-tabs .tab-btn {
    padding: 10px 20px;
    border: none;
    background: transparent;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--gray-600);
    cursor: pointer;
    transition: var(--transition);
    white-space: nowrap;
}
.lga-tabs .tab-btn:hover {
    background: var(--gray-50);
    color: var(--gray-800);
}
.lga-tabs .tab-btn.active {
    background: var(--primary);
    color: white;
}
.lga-tabs .tab-btn .count {
    display: inline-block;
    background: var(--gray-200);
    color: var(--gray-600);
    padding: 0 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    margin-left: 4px;
}
.lga-tabs .tab-btn.active .count {
    background: rgba(255,255,255,0.3);
    color: white;
}

.tab-content {
    display: none;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.tab-content.active {
    display: block;
}

.table-responsive {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table th {
    text-align: left;
    padding: 10px 12px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--gray-200);
}
.table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table tr:hover td {
    background: var(--gray-50);
}
.pu-link {
    color: var(--primary);
    text-decoration: none;
}
.pu-link:hover {
    text-decoration: underline;
}
.status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
}
.status-dot.active { background: #10B981; }
.status-dot.inactive { background: #6B7280; }
.status-dot.verified { background: #10B981; }
.status-dot.pending { background: #F59E0B; }
.status-dot.rejected { background: #EF4444; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 2.5rem;
    color: var(--gray-300);
    margin-bottom: 12px;
}

.info-grid-lga {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.info-item {
    display: flex;
    flex-direction: column;
}
.info-item .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.info-item .value {
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--gray-800);
    margin-top: 2px;
}

@media (max-width: 768px) {
    .lga-header {
        flex-direction: column;
    }
    .stats-grid-lga {
        grid-template-columns: repeat(2, 1fr);
    }
    .info-grid-lga {
        grid-template-columns: 1fr;
    }
    .lga-tabs .tab-btn {
        padding: 8px 14px;
        font-size: 0.75rem;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- LGA Header -->
        <div class="lga-header">
            <div class="header-left">
                <h2>
                    <i class="fas fa-building" style="color:var(--primary);"></i>
                    <?php echo htmlspecialchars($lga['name'] ?? 'LGA'); ?>
                </h2>
                <div class="subtitle">
                    <?php echo htmlspecialchars($lga['code'] ?? 'N/A'); ?>
                    <i class="fas fa-chevron-right"></i>
                    <?php echo htmlspecialchars($lga['state_name'] ?? 'N/A'); ?>
                    <i class="fas fa-chevron-right"></i>
                    <?php echo htmlspecialchars($lga['senatorial_name'] ?? 'N/A'); ?>
                </div>
            </div>
            <div class="header-right">
                <span class="badge-status <?php echo ($lga['is_active'] ?? 1) ? 'active' : 'pending'; ?>">
                    <?php echo ($lga['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid-lga">
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_wards']); ?></div>
                <div class="label">Wards</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_pus']); ?></div>
                <div class="label">Polling Units</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_agents']); ?></div>
                <div class="label">Agents</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_results']); ?></div>
                <div class="label">Results</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['verified_results']); ?></div>
                <div class="label">Verified</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_incidents']); ?></div>
                <div class="label">Incidents</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($lga['registered_voters'] ?? 0); ?></div>
                <div class="label">Registered Voters</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="lga-tabs">
            <button class="tab-btn active" data-tab="wards">
                <i class="fas fa-layer-group"></i> Wards
                <span class="count"><?php echo count($wards); ?></span>
            </button>
            <button class="tab-btn" data-tab="pus">
                <i class="fas fa-map-pin"></i> Polling Units
                <span class="count"><?php echo count($polling_units); ?></span>
            </button>
            <button class="tab-btn" data-tab="info">
                <i class="fas fa-info-circle"></i> Information
            </button>
        </div>

        <!-- Tab: Wards -->
        <div class="tab-content active" id="tab-wards">
            <?php if (count($wards) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ward Name</th>
                                <th>Code</th>
                                <th>Polling Units</th>
                                <th>Agents</th>
                                <th>Registered Voters</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wards as $ward): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:500;"><?php echo htmlspecialchars($ward['name']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($ward['code'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($ward['pu_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($ward['agent_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($ward['registered_voters'] ?? 0); ?></td>
                                    <td>
                                        <span class="status-dot <?php echo ($ward['is_active'] ?? 1) ? 'active' : 'inactive'; ?>"></span>
                                        <?php echo ($ward['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-layer-group"></i>
                    <p>No wards found in this LGA.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Polling Units -->
        <div class="tab-content" id="tab-pus">
            <?php if (count($polling_units) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Polling Unit</th>
                                <th>Ward</th>
                                <th>Code</th>
                                <th>Agents</th>
                                <th>Results</th>
                                <th>Voters</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($polling_units as $pu): ?>
                                <tr>
                                    <td>
                                        <a href="pu-details.php?id=<?php echo $pu['id']; ?>" class="pu-link">
                                            <?php echo htmlspecialchars($pu['name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($pu['ward_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($pu['code'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($pu['agent_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($pu['result_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($pu['registered_voters'] ?? 0); ?></td>
                                    <td>
                                        <a href="pu-details.php?id=<?php echo $pu['id']; ?>" 
                                           style="color:var(--primary);text-decoration:none;font-size:0.8rem;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($polling_units) >= 100): ?>
                    <p style="text-align:center;color:var(--gray-400);font-size:0.8rem;margin-top:12px;">
                        Showing first 100 polling units. <a href="monitor-district.php" style="color:var(--primary);">View all</a>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-map-pin"></i>
                    <p>No polling units found in this LGA.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Information -->
        <div class="tab-content" id="tab-info">
            <div class="info-grid-lga">
                <div class="info-item">
                    <span class="label">LGA Name</span>
                    <span class="value"><?php echo htmlspecialchars($lga['name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Code</span>
                    <span class="value"><?php echo htmlspecialchars($lga['code'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">State</span>
                    <span class="value"><?php echo htmlspecialchars($lga['state_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Senatorial District</span>
                    <span class="value"><?php echo htmlspecialchars($lga['senatorial_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Federal Constituency</span>
                    <span class="value"><?php echo htmlspecialchars($lga['federal_constituency_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Registered Voters</span>
                    <span class="value"><?php echo number_format($lga['registered_voters'] ?? 0); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Total Wards</span>
                    <span class="value"><?php echo number_format($stats['total_wards']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Total Polling Units</span>
                    <span class="value"><?php echo number_format($stats['total_pus']); ?></span>
                </div>
                <?php if (!empty($lga['gps_lat']) && !empty($lga['gps_lng'])): ?>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="label">GPS Location</span>
                        <span class="value">
                            <a href="https://maps.google.com/?q=<?php echo $lga['gps_lat']; ?>,<?php echo $lga['gps_lng']; ?>" 
                               target="_blank" style="color:var(--primary);text-decoration:none;">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo $lga['gps_lat']; ?>, <?php echo $lga['gps_lng']; ?>
                            </a>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(function(b) {
            b.classList.remove('active');
        });
        document.querySelectorAll('.tab-content').forEach(function(c) {
            c.classList.remove('active');
        });
        this.classList.add('active');
        var tabId = this.dataset.tab;
        document.getElementById('tab-' + tabId).classList.add('active');
    });
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