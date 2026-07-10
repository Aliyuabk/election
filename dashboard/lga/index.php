<?php
// ============================================================
// LGA COORDINATOR - DASHBOARD
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

// Only LGA coordinator can access
if (SessionManager::get('role_level') !== 'lga') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'LGA Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

// If lga_id is not set in session, try to get it from user record
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

// ============================================================
// FETCH LGA AND STATE NAMES
// ============================================================
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

// ============================================================
// FETCH DASHBOARD STATISTICS
// ============================================================

$stats = [
    'total_wards' => 0,
    'total_pus' => 0,
    'total_elections' => 0,
    'active_elections' => 0,
    'total_coordinators' => 0,
    'total_agents' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'flagged_results' => 0,
    'total_incidents' => 0,
    'reported_incidents' => 0,
    'investigating_incidents' => 0,
    'resolved_incidents' => 0,
    'pending_approvals' => 0,
    'online_agents' => 0
];

try {
    // Ward and PU Stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT w.id) as total_wards,
            COUNT(DISTINCT pu.id) as total_pus
        FROM wards w
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        WHERE w.lga_id = ? AND w.is_active = 1
    ");
    $stmt->execute([$lga_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_wards'] = (int)($result['total_wards'] ?? 0);
    $stats['total_pus'] = (int)($result['total_pus'] ?? 0);

    // Ward Coordinators
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) as count
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND r.level = 'ward' AND u.status = 'active'
        AND u.lga_id = ?
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $stats['total_coordinators'] = (int)($stmt->fetchColumn() ?? 0);

    // PU Agents
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) as count
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND r.level = 'pu_agent' AND u.status = 'active'
        AND u.lga_id = ?
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $stats['total_agents'] = (int)($stmt->fetchColumn() ?? 0);

    // Elections
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND (JSON_CONTAINS(lgas_json, JSON_QUOTE(?)) OR JSON_CONTAINS(states_json, JSON_QUOTE(?)))
    ");
    $stmt->execute([$tenant_id, $lga_id, $lga_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_elections'] = (int)($result['total'] ?? 0);
    $stats['active_elections'] = (int)($result['active'] ?? 0);

    // Results
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN r.status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        WHERE r.tenant_id = ? AND w.lga_id = ?
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['verified_results'] = (int)($result['verified'] ?? 0);
    $stats['pending_results'] = (int)($result['pending'] ?? 0);
    $stats['flagged_results'] = (int)($result['flagged'] ?? 0);

    // Pending Approvals (EC8B pending)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM results_ec8b r
        JOIN wards w ON r.ward_id = w.id
        WHERE r.tenant_id = ? AND w.lga_id = ? AND r.status = 'pending'
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $stats['pending_approvals'] = (int)($stmt->fetchColumn() ?? 0);

    // Incidents
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported,
            SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
        FROM incidents 
        WHERE tenant_id = ? AND lga_id = ?
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_incidents'] = (int)($result['total'] ?? 0);
    $stats['reported_incidents'] = (int)($result['reported'] ?? 0);
    $stats['investigating_incidents'] = (int)($result['investigating'] ?? 0);
    $stats['resolved_incidents'] = (int)($result['resolved'] ?? 0);

    // Online Agents (active sessions in last 15 minutes)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) as count
        FROM users u
        INNER JOIN user_sessions us ON u.id = us.user_id
        WHERE u.tenant_id = ? AND u.lga_id = ?
        AND us.is_active = 1 
        AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        AND u.status = 'active'
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $stats['online_agents'] = (int)($stmt->fetchColumn() ?? 0);

} catch (Exception $e) {
    error_log("LGA Dashboard Error: " . $e->getMessage());
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
        AND (a.entity_type IN ('lga', 'ward', 'pu') OR a.user_id IN (
            SELECT id FROM users WHERE lga_id = ?
        ))
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching activities: " . $e->getMessage());
}

// ============================================================
// FETCH WARD PERFORMANCE
// ============================================================
$ward_performance = [];
try {
    $stmt = $db->prepare("
        SELECT 
            w.id,
            w.name as ward_name,
            COUNT(DISTINCT pu.id) as total_pus,
            COUNT(DISTINCT r.id) as submitted_results,
            COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_results,
            COUNT(DISTINCT u.id) as coordinators,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_coordinators,
            (SELECT COUNT(*) FROM incidents i WHERE i.ward_id = w.id AND i.status IN ('reported', 'investigating')) as active_incidents
        FROM wards w
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        LEFT JOIN users u ON u.ward_id = w.id AND u.status = 'active'
        WHERE w.lga_id = ? AND w.is_active = 1
        GROUP BY w.id, w.name
        ORDER BY w.name ASC
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $ward_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching ward performance: " . $e->getMessage());
}

$page_title = 'Dashboard';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
/* Dashboard specific styles */
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
                <h2><i class="fas fa-map-marker-alt"></i> Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
                <p>LGA Coordinator - <?php echo htmlspecialchars($lga_name); ?> LGA Dashboard</p>
                <div class="breadcrumb">
                    <i class="fas fa-flag"></i>
                    <span><?php echo htmlspecialchars($state_name); ?></span>
                    <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                    <span><?php echo htmlspecialchars($lga_name); ?></span>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="manage-wards.php" class="btn-primary-sm">
                    <i class="fas fa-layer-group"></i> Manage Wards
                </a>
                <a href="broadcasts-create.php" class="btn-secondary-sm">
                    <i class="fas fa-bullhorn"></i> Broadcast
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_wards']); ?></div>
                <div class="stat-label">Wards</div>
                <div class="stat-sub"><i class="fas fa-users"></i> <?php echo number_format($stats['total_coordinators']); ?> Coordinators</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_pus']); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-sub"><i class="fas fa-users"></i> <?php echo number_format($stats['total_agents']); ?> Agents</div>
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
                <div class="stat-icon teal"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending_approvals']); ?></div>
                <div class="stat-label">Pending Approvals</div>
                <div class="stat-sub"><i class="fas fa-clock"></i> Awaiting Review</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_incidents']); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-sub down"><i class="fas fa-clock"></i> <?php echo number_format($stats['reported_incidents']); ?> Reported</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-wifi"></i></div>
                <div class="stat-number"><?php echo number_format($stats['online_agents']); ?></div>
                <div class="stat-label">Online Agents</div>
                <div class="stat-sub up"><i class="fas fa-circle" style="color:#10B981;font-size:0.4rem;"></i> Active Now</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background:#6366F1;"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_wards']); ?></div>
                <div class="stat-label">Active Wards</div>
                <div class="stat-sub"><i class="fas fa-check-circle" style="color:#10B981;"></i> Full Coverage</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie" style="color:var(--primary);margin-right:6px;"></i> Results Status</h3>
                    <span class="period"><?php echo htmlspecialchars($lga_name); ?></span>
                </div>
                <div class="chart-container">
                    <canvas id="resultsChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:6px;"></i> Ward Performance</h3>
                    <span class="period">Verified Results</span>
                </div>
                <div class="chart-container">
                    <canvas id="wardChart"></canvas>
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
                            <div class="activity-icon <?php echo strpos($activity['activity_type'] ?? '', 'login') !== false ? 'login' : 'system'; ?>">
                                <i class="fas <?php echo strpos($activity['activity_type'] ?? '', 'login') !== false ? 'fa-sign-in-alt' : 'fa-cog'; ?>"></i>
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
                        <a href="manage-wards.php" class="quick-action-item">
                            <div class="action-icon blue"><i class="fas fa-layer-group"></i></div>
                            <div class="action-text">
                                <div class="title">Wards</div>
                                <div class="desc">Manage</div>
                            </div>
                        </a>
                        <a href="broadcasts-create.php" class="quick-action-item">
                            <div class="action-icon purple"><i class="fas fa-bullhorn"></i></div>
                            <div class="action-text">
                                <div class="title">Broadcast</div>
                                <div class="desc">Send Message</div>
                            </div>
                        </a>
                        <a href="incidents.php" class="quick-action-item">
                            <div class="action-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="action-text">
                                <div class="title">Incidents</div>
                                <div class="desc">View & Manage</div>
                            </div>
                        </a>
                        <a href="approve-results.php" class="quick-action-item">
                            <div class="action-icon green"><i class="fas fa-check-double"></i></div>
                            <div class="action-text">
                                <div class="title">Results</div>
                                <div class="desc">Approve</div>
                            </div>
                        </a>
                        <a href="reports-lga.php" class="quick-action-item">
                            <div class="action-icon yellow"><i class="fas fa-file-alt"></i></div>
                            <div class="action-text">
                                <div class="title">Reports</div>
                                <div class="desc">Generate</div>
                            </div>
                        </a>
                        <a href="ward-coordinators.php" class="quick-action-item">
                            <div class="action-icon" style="background:#F5F3FF;color:#8B5CF6;"><i class="fas fa-user-tie"></i></div>
                            <div class="action-text">
                                <div class="title">Coordinators</div>
                                <div class="desc">Manage</div>
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

// Ward Performance Chart
const ctx2 = document.getElementById('wardChart').getContext('2d');
const wardData = <?php 
    $wards = array_column($ward_performance, 'ward_name');
    $verified = array_column($ward_performance, 'verified_results');
    echo json_encode(['labels' => $wards, 'data' => $verified]);
?>;

new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: wardData.labels || ['No Data'],
        datasets: [{
            label: 'Verified Results',
            data: wardData.data || [0],
            backgroundColor: 'rgba(59, 130, 246, 0.7)',
            borderColor: '#3B82F6',
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