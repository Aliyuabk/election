<?php
// ============================================================
// WARD COORDINATOR DASHBOARD
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
$user_email = SessionManager::get('user_email');
$ward_id = SessionManager::get('ward_id');
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');
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
// FETCH WARD, LGA, AND STATE NAMES
// ============================================================
$ward_name = 'Ward';
$lga_name = 'LGA';
$state_name = 'State';
try {
    if ($ward_id) {
        $stmt = $db->prepare("
            SELECT 
                w.name as ward_name,
                l.name as lga_name,
                s.name as state_name
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            WHERE w.id = ?
        ");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['ward_name'];
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward details: " . $e->getMessage());
}

// ============================================================
// FETCH DASHBOARD STATISTICS
// ============================================================

$stats = [
    'total_pus' => 0,
    'total_voters' => 0,
    'total_elections' => 0,
    'active_elections' => 0,
    'total_agents' => 0,
    'active_agents' => 0,
    'online_agents' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'flagged_results' => 0,
    'total_incidents' => 0,
    'reported_incidents' => 0,
    'investigating_incidents' => 0,
    'resolved_incidents' => 0,
    'ec8b_total' => 0,
    'ec8b_verified' => 0,
    'ec8b_pending' => 0,
    'pending_ec8a' => 0
];

try {
    // Polling Unit Stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT pu.id) as total_pus,
            SUM(pu.registered_voters) as total_voters
        FROM polling_units pu
        WHERE pu.ward_id = ? AND pu.is_active = 1
    ");
    $stmt->execute([$ward_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_pus'] = (int)($result['total_pus'] ?? 0);
    $stats['total_voters'] = (int)($result['total_voters'] ?? 0);

    // PU Agents
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) as count
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND r.level = 'pu_agent' AND u.status = 'active'
        AND u.ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats['total_agents'] = (int)($stmt->fetchColumn() ?? 0);
    $stats['active_agents'] = $stats['total_agents'];

    // Online Agents
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) as count
        FROM users u
        INNER JOIN user_sessions us ON u.id = us.user_id
        WHERE u.tenant_id = ? AND u.ward_id = ?
        AND us.is_active = 1 
        AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        AND u.status = 'active'
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats['online_agents'] = (int)($stmt->fetchColumn() ?? 0);

    // Elections
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_elections'] = (int)($result['total'] ?? 0);
    $stats['active_elections'] = (int)($result['active'] ?? 0);

    // Results (EC8A)
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN r.status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        WHERE r.tenant_id = ? AND pu.ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['verified_results'] = (int)($result['verified'] ?? 0);
    $stats['pending_results'] = (int)($result['pending'] ?? 0);
    $stats['flagged_results'] = (int)($result['flagged'] ?? 0);

    // Pending EC8A submissions (PU agents who haven't submitted)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT pu.id) as count
        FROM polling_units pu
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        WHERE pu.ward_id = ? AND pu.is_active = 1
        AND r.id IS NULL
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats['pending_ec8a'] = (int)($stmt->fetchColumn() ?? 0);

    // Incidents
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported,
            SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
        FROM incidents 
        WHERE tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_incidents'] = (int)($result['total'] ?? 0);
    $stats['reported_incidents'] = (int)($result['reported'] ?? 0);
    $stats['investigating_incidents'] = (int)($result['investigating'] ?? 0);
    $stats['resolved_incidents'] = (int)($result['resolved'] ?? 0);

    // EC8B Status
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM results_ec8b
        WHERE tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['ec8b_total'] = (int)($result['total'] ?? 0);
    $stats['ec8b_verified'] = (int)($result['verified'] ?? 0);
    $stats['ec8b_pending'] = (int)($result['pending'] ?? 0);

} catch (Exception $e) {
    error_log("Ward Dashboard Error: " . $e->getMessage());
}

// ============================================================
// FETCH RECENT ACTIVITIES
// ============================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as user_name
        FROM activity_logs a
        JOIN users u ON a.user_id = u.id
        WHERE a.tenant_id = ? 
        AND (a.entity_type = 'pu' OR a.user_id IN (
            SELECT id FROM users WHERE ward_id = ?
        ))
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching activities: " . $e->getMessage());
}

// ============================================================
// FETCH PU PERFORMANCE
// ============================================================
$pu_performance = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pu.id,
            pu.name as pu_name,
            pu.code as pu_code,
            pu.registered_voters,
            COUNT(DISTINCT r.id) as submitted_results,
            COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_results,
            COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.id END) as pending_results,
            COUNT(DISTINCT u.id) as agents,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_agents,
            (SELECT COUNT(*) FROM incidents i WHERE i.pu_id = pu.id AND i.status IN ('reported', 'investigating')) as active_incidents
        FROM polling_units pu
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        LEFT JOIN users u ON u.pu_id = pu.id AND u.status = 'active'
        WHERE pu.ward_id = ? AND pu.is_active = 1
        GROUP BY pu.id, pu.name, pu.code, pu.registered_voters
        ORDER BY pu.name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $pu_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching PU performance: " . $e->getMessage());
}

$page_title = 'Dashboard';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
/* Dashboard specific styles */
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 16px 18px;
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}
.stat-card:hover {
    box-shadow: var(--shadow-hover);
    transform: translateY(-2px);
}
.stat-card .stat-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
    margin-bottom: 6px;
}
.stat-card .stat-icon.blue { background: #3B82F6; }
.stat-card .stat-icon.green { background: #10B981; }
.stat-card .stat-icon.purple { background: #8B5CF6; }
.stat-card .stat-icon.yellow { background: #F59E0B; }
.stat-card .stat-icon.red { background: #EF4444; }
.stat-card .stat-icon.teal { background: #0D9488; }
.stat-card .stat-icon.orange { background: #F97316; }
.stat-card .stat-icon.indigo { background: #6366F1; }
.stat-card .stat-icon.pink { background: #EC4899; }
.stat-card .stat-number {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1.2;
}
.stat-card .stat-label {
    font-size: 0.72rem;
    color: var(--gray-500);
    margin-top: 2px;
    font-weight: 500;
}
.stat-card .stat-sub {
    font-size: 0.6rem;
    color: var(--gray-400);
    margin-top: 3px;
}
.stat-card .stat-sub.up { color: #10B981; }
.stat-card .stat-sub.down { color: #EF4444; }

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}
.quick-action-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-decoration: none;
    color: var(--gray-700);
    transition: var(--transition);
}
.quick-action-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}
.quick-action-item .action-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.quick-action-item .action-icon.blue { background: #EFF6FF; color: #3B82F6; }
.quick-action-item .action-icon.purple { background: #F5F3FF; color: #8B5CF6; }
.quick-action-item .action-icon.green { background: #ECFDF5; color: #10B981; }
.quick-action-item .action-icon.red { background: #FEF2F2; color: #EF4444; }
.quick-action-item .action-icon.yellow { background: #FFFBEB; color: #F59E0B; }
.quick-action-item .action-icon.teal { background: #F0FDFA; color: #0D9488; }
.quick-action-item .action-text .title {
    font-weight: 600;
    font-size: 0.78rem;
}
.quick-action-item .action-text .desc {
    font-size: 0.6rem;
    color: var(--gray-400);
}

.welcome-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.welcome-section h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.welcome-section h2 i {
    color: var(--primary);
}
.welcome-section p {
    color: var(--gray-500);
    margin: 2px 0 0;
    font-size: 0.9rem;
}
.welcome-section .breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
    font-size: 0.8rem;
    color: var(--gray-500);
}
.welcome-section .breadcrumb span {
    background: var(--gray-100);
    padding: 2px 12px;
    border-radius: 20px;
    font-weight: 500;
}

.charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
.chart-card {
    background: white;
    border-radius: var(--radius);
    padding: 16px;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
}
.chart-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.chart-card .card-header h3 {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin: 0;
}
.chart-card .card-header .period {
    font-size: 0.65rem;
    color: var(--gray-400);
    font-weight: 500;
}
.chart-container {
    height: 200px;
    position: relative;
}

.activities-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}
.activity-card {
    background: white;
    border-radius: var(--radius);
    padding: 16px;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
}
.activity-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.activity-card .card-header h3 {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin: 0;
}
.activity-card .card-header a {
    font-size: 0.7rem;
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-100);
}
.activity-item:last-child { border-bottom: none; }
.activity-item .activity-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    flex-shrink: 0;
}
.activity-item .activity-icon.system { background: #F1F5F9; color: #64748B; }
.activity-item .activity-icon.login { background: #EFF6FF; color: #3B82F6; }
.activity-item .activity-icon.submit { background: #ECFDF5; color: #10B981; }
.activity-item .activity-content { flex: 1; min-width: 0; }
.activity-item .activity-content .title {
    font-weight: 500;
    font-size: 0.78rem;
    color: var(--gray-700);
}
.activity-item .activity-content .desc {
    font-size: 0.7rem;
    color: var(--gray-500);
}
.activity-item .activity-content .time {
    font-size: 0.6rem;
    color: var(--gray-400);
}

.incident-summary {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 8px;
    padding: 4px 0;
}
.incident-stat {
    text-align: center;
    padding: 8px;
    background: var(--gray-50);
    border-radius: 8px;
}
.incident-stat .label {
    display: block;
    font-size: 0.6rem;
    color: var(--gray-500);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.incident-stat .value {
    display: block;
    font-size: 1.2rem;
    font-weight: 700;
    margin-top: 2px;
}

@media (max-width: 1024px) {
    .charts-grid { grid-template-columns: 1fr; }
    .activities-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .dashboard-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .welcome-section {
        flex-direction: column;
        align-items: flex-start;
    }
    .quick-actions {
        grid-template-columns: 1fr 1fr;
    }
    .chart-container { height: 180px; }
    .incident-summary { grid-template-columns: 1fr 1fr 1fr; }
}

@media (max-width: 480px) {
    .dashboard-stats {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .quick-actions {
        grid-template-columns: 1fr;
    }
    .stat-card {
        padding: 12px 14px;
    }
    .stat-card .stat-number {
        font-size: 1.2rem;
    }
    .incident-summary {
        grid-template-columns: 1fr 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div>
                <h2><i class="fas fa-layer-group"></i> Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
                <p>Ward Coordinator - <?php echo htmlspecialchars($ward_name); ?> Ward</p>
                <div class="breadcrumb">
                    <i class="fas fa-flag"></i>
                    <span><?php echo htmlspecialchars($state_name); ?></span>
                    <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                    <span><?php echo htmlspecialchars($lga_name); ?></span>
                    <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                    <span><?php echo htmlspecialchars($ward_name); ?></span>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="manage-pu-agents.php" class="btn-primary-sm">
                    <i class="fas fa-user-tie"></i> Manage Agents
                </a>
                <a href="ec8b-create.php" class="btn-secondary-sm">
                    <i class="fas fa-upload"></i> Upload EC8B
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_pus']); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-sub"><i class="fas fa-users"></i> <?php echo number_format($stats['total_agents']); ?> Agents</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_voters']); ?></div>
                <div class="stat-label">Registered Voters</div>
                <div class="stat-sub"><i class="fas fa-address-card"></i> Total</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_elections']); ?></div>
                <div class="stat-label">Elections</div>
                <div class="stat-sub up"><i class="fas fa-play"></i> <?php echo number_format($stats['active_elections']); ?> Active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($stats['verified_results']); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-sub down"><i class="fas fa-clock"></i> <?php echo number_format($stats['pending_results']); ?> Pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['ec8b_total']); ?></div>
                <div class="stat-label">EC8B Forms</div>
                <div class="stat-sub"><i class="fas fa-check"></i> <?php echo number_format($stats['ec8b_verified']); ?> Verified</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending_ec8a']); ?></div>
                <div class="stat-label">Pending EC8A</div>
                <div class="stat-sub down"><i class="fas fa-upload"></i> Awaiting Submission</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_incidents']); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-sub down"><i class="fas fa-clock"></i> <?php echo number_format($stats['reported_incidents']); ?> Reported</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon indigo"><i class="fas fa-wifi"></i></div>
                <div class="stat-number"><?php echo number_format($stats['online_agents']); ?></div>
                <div class="stat-label">Online Agents</div>
                <div class="stat-sub up"><i class="fas fa-circle" style="color:#10B981;font-size:0.4rem;"></i> Active Now</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie" style="color:var(--primary);margin-right:6px;"></i> Results Status</h3>
                    <span class="period"><?php echo htmlspecialchars($ward_name); ?></span>
                </div>
                <div class="chart-container">
                    <canvas id="resultsChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:6px;"></i> PU Performance</h3>
                    <span class="period">Verified Results</span>
                </div>
                <div class="chart-container">
                    <canvas id="puChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Activities & Quick Actions -->
        <div class="activities-grid">
            <!-- Recent Activities -->
            <div class="activity-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i> Recent Activities</h3>
                    <a href="activity-logs.php">View All →</a>
                </div>
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach (array_slice($recent_activities, 0, 8) as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo strpos($activity['activity_type'] ?? '', 'login') !== false ? 'login' : (strpos($activity['activity_type'] ?? '', 'submit') !== false ? 'submit' : 'system'); ?>">
                                <i class="fas <?php echo strpos($activity['activity_type'] ?? '', 'login') !== false ? 'fa-sign-in-alt' : (strpos($activity['activity_type'] ?? '', 'submit') !== false ? 'fa-upload' : 'fa-cog'); ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="title text-truncate"><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></div>
                                <div class="desc text-truncate"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></div>
                                <div class="time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'] ?? 'now')); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:20px;color:var(--gray-400);">
                        <i class="fas fa-clock" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                        <p style="margin:0;font-size:0.85rem;">No recent activities</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Quick Actions -->
                <div class="activity-card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt" style="color:var(--primary);margin-right:6px;"></i> Quick Actions</h3>
                    </div>
                    <div class="quick-actions" style="grid-template-columns:1fr 1fr;">
                        <a href="manage-pu-agents.php" class="quick-action-item">
                            <div class="action-icon blue"><i class="fas fa-user-tie"></i></div>
                            <div class="action-text">
                                <div class="title">Agents</div>
                                <div class="desc">Manage</div>
                            </div>
                        </a>
                        <a href="polling-units.php" class="quick-action-item">
                            <div class="action-icon purple"><i class="fas fa-flag-checkered"></i></div>
                            <div class="action-text">
                                <div class="title">PUs</div>
                                <div class="desc">View</div>
                            </div>
                        </a>
                        <a href="ec8b-create.php" class="quick-action-item">
                            <div class="action-icon green"><i class="fas fa-upload"></i></div>
                            <div class="action-text">
                                <div class="title">EC8B</div>
                                <div class="desc">Upload</div>
                            </div>
                        </a>
                        <a href="incidents.php" class="quick-action-item">
                            <div class="action-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="action-text">
                                <div class="title">Incidents</div>
                                <div class="desc">Manage</div>
                            </div>
                        </a>
                        <a href="broadcasts-create.php" class="quick-action-item">
                            <div class="action-icon yellow"><i class="fas fa-bullhorn"></i></div>
                            <div class="action-text">
                                <div class="title">Broadcast</div>
                                <div class="desc">Send</div>
                            </div>
                        </a>
                        <a href="reports-ward.php" class="quick-action-item">
                            <div class="action-icon teal"><i class="fas fa-file-alt"></i></div>
                            <div class="action-text">
                                <div class="title">Reports</div>
                                <div class="desc">Generate</div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Incident Summary -->
                <div class="activity-card">
                    <div class="card-header">
                        <h3><i class="fas fa-exclamation-triangle" style="color:#EF4444;margin-right:6px;"></i> Incident Summary</h3>
                        <a href="incidents.php">View All →</a>
                    </div>
                    <div class="incident-summary">
                        <div class="incident-stat">
                            <span class="label" style="color:#F59E0B;">Reported</span>
                            <span class="value" style="color:#F59E0B;"><?php echo number_format($stats['reported_incidents']); ?></span>
                        </div>
                        <div class="incident-stat">
                            <span class="label" style="color:#8B5CF6;">Investigating</span>
                            <span class="value" style="color:#8B5CF6;"><?php echo number_format($stats['investigating_incidents']); ?></span>
                        </div>
                        <div class="incident-stat">
                            <span class="label" style="color:#10B981;">Resolved</span>
                            <span class="value" style="color:#10B981;"><?php echo number_format($stats['resolved_incidents']); ?></span>
                        </div>
                    </div>
                    <div style="margin-top:10px;text-align:center;font-size:0.7rem;color:var(--gray-400);">
                        Total: <?php echo number_format($stats['total_incidents']); ?> incidents
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Results Chart
const ctx1 = document.getElementById('resultsChart').getContext('2d');
new Chart(ctx1, {
    type: 'doughnut',
    data: {
        labels: ['Verified', 'Pending', 'Flagged'],
        datasets: [{
            data: [
                <?php echo $stats['verified_results']; ?>,
                <?php echo $stats['pending_results']; ?>,
                <?php echo $stats['flagged_results']; ?>
            ],
            backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
            borderWidth: 2,
            borderColor: 'white'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 12,
                    font: { size: 11 }
                }
            }
        },
        cutout: '65%'
    }
});

// PU Performance Chart
const ctx2 = document.getElementById('puChart').getContext('2d');
const puData = <?php 
    $pu_names = array_column($pu_performance, 'pu_name');
    $verified = array_column($pu_performance, 'verified_results');
    echo json_encode(['labels' => $pu_names, 'data' => $verified]);
?>;

new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: puData.labels || ['No Data'],
        datasets: [{
            label: 'Verified Results',
            data: puData.data || [0],
            backgroundColor: 'rgba(220, 38, 38, 0.7)',
            borderColor: '#DC2626',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: { font: { size: 10 } }
            },
            x: {
                grid: { display: false },
                ticks: { 
                    font: { size: 9 },
                    maxRotation: 45
                }
            }
        }
    }
});

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