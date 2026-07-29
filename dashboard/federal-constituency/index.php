<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR DASHBOARD
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

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$constituency_id = SessionManager::get('federal_constituency_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET CONSTITUENCY NAME
// ============================================================
$constituency_name = 'Federal Constituency';
$state_name = 'State';
try {
    if ($constituency_id) {
        $stmt = $db->prepare("
            SELECT s.name as state_name, fc.name as constituency_name 
            FROM federal_constituencies fc 
            JOIN states s ON fc.state_id = s.id 
            WHERE fc.id = ?
        ");
        $stmt->execute([$constituency_id]);
        $result = $stmt->fetch();
        if ($result) {
            $constituency_name = $result['constituency_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching constituency: " . $e->getMessage());
}

// ============================================================
// GET LGA IDs
// ============================================================
$lga_ids = [];
try {
    $stmt = $db->prepare("SELECT lgas_json FROM federal_constituencies WHERE id = ?");
    $stmt->execute([$constituency_id]);
    $lgas_json = $stmt->fetchColumn();
    if ($lgas_json) {
        $lga_ids = json_decode($lgas_json, true) ?: [];
    }
} catch (Exception $e) {
    error_log("Error fetching LGA IDs: " . $e->getMessage());
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT l.id) as total_lgas,
                COUNT(DISTINCT w.id) as total_wards,
                COUNT(DISTINCT pu.id) as total_pus
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            WHERE l.id IN ($lga_list)
        ");
        $stmt->execute();
        $stats = $stmt->fetch();
    } else {
        $stats = ['total_lgas' => 0, 'total_wards' => 0, 'total_pus' => 0];
    }
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $stats = ['total_lgas' => 0, 'total_wards' => 0, 'total_pus' => 0];
}

// ============================================================
// GET COORDINATOR STATS
// ============================================================
$coordinator_stats = ['lga' => 0, 'ward' => 0];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND r.level = 'lga'
            AND u.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $coordinator_stats['lga'] = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN wards w ON u.ward_id = w.id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND r.level = 'ward'
            AND w.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $coordinator_stats['ward'] = (int)$stmt->fetchColumn();
    }
} catch (Exception $e) {
    error_log("Error fetching coordinator stats: " . $e->getMessage());
}

// ============================================================
// GET AGENT STATS
// ============================================================
$agent_stats = ['pu_agents' => 0, 'party_agents' => 0, 'observers' => 0, 'volunteers' => 0];
try {
    if ($lga_list !== '0') {
        $roles = ['pu_agent', 'party_agent', 'observer', 'volunteer'];
        foreach ($roles as $role) {
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT u.id) as count
                FROM users u
                JOIN roles r ON u.role_id = r.id
                JOIN polling_units pu ON u.pu_id = pu.id
                JOIN wards w ON pu.ward_id = w.id
                WHERE u.tenant_id = ? AND u.status = 'active'
                AND r.level = ?
                AND w.lga_id IN ($lga_list)
            ");
            $stmt->execute([$tenant_id, $role]);
            $key = str_replace('_agent', '_agents', $role);
            $agent_stats[$key] = (int)$stmt->fetchColumn();
        }
    }
} catch (Exception $e) {
    error_log("Error fetching agent stats: " . $e->getMessage());
}

// ============================================================
// GET RESULT STATS
// ============================================================
$result_stats = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            WHERE r.tenant_id = ? AND w.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $result_stats = $stmt->fetch();
    } else {
        $result_stats = ['total' => 0, 'verified' => 0, 'pending' => 0, 'flagged' => 0];
    }
} catch (Exception $e) {
    error_log("Error fetching result stats: " . $e->getMessage());
    $result_stats = ['total' => 0, 'verified' => 0, 'pending' => 0, 'flagged' => 0];
}

// ============================================================
// GET INCIDENT STATS
// ============================================================
$incident_stats = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported,
                SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
            FROM incidents 
            WHERE tenant_id = ? AND lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $incident_stats = $stmt->fetch();
    } else {
        $incident_stats = ['total' => 0, 'reported' => 0, 'investigating' => 0, 'resolved' => 0];
    }
} catch (Exception $e) {
    error_log("Error fetching incident stats: " . $e->getMessage());
}

// ============================================================
// GET PU REPORTING STATUS
// ============================================================
$pu_reporting_status = ['submitted' => 0, 'pending' => 0, 'verified' => 0, 'total' => 0];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT pu.id) as total,
                SUM(CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN r.id IS NOT NULL AND r.status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN r.id IS NULL OR r.status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            WHERE w.lga_id IN ($lga_list) AND pu.is_active = 1
        ");
        $stmt->execute([$tenant_id]);
        $pu_reporting_status = $stmt->fetch();
    }
} catch (Exception $e) {
    error_log("Error fetching PU reporting status: " . $e->getMessage());
}

// ============================================================
// GET RECENT ACTIVITIES
// ============================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as user_name, u.photograph_url as user_avatar
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.tenant_id = ? 
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id]);
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching recent activities: " . $e->getMessage());
}

// ============================================================
// GET BROADCAST STATS
// ============================================================
$broadcast_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
        FROM broadcasts 
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $broadcast_stats = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching broadcast stats: " . $e->getMessage());
}

$page_title = 'Dashboard';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.welcome-section {
    margin-bottom: 24px;
}
.welcome-section h2 {
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 4px;
}
.welcome-section p {
    color: var(--gray-500);
    font-size: 0.9rem;
    margin-bottom: 8px;
}
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    color: var(--gray-400);
}
.breadcrumb i {
    font-size: 0.75rem;
}
.breadcrumb span {
    color: var(--gray-600);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 18px 20px;
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}
.stat-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow);
    transform: translateY(-2px);
}
.stat-card .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    font-size: 1.1rem;
}
.stat-card .stat-icon.blue { background: #DBEAFE; color: #2563EB; }
.stat-card .stat-icon.green { background: #D1FAE5; color: #059669; }
.stat-card .stat-icon.purple { background: #EDE9FE; color: #7C3AED; }
.stat-card .stat-icon.yellow { background: #FEF3C7; color: #D97706; }
.stat-card .stat-icon.red { background: #FEE2E2; color: #DC2626; }
.stat-card .stat-icon.orange { background: #FFEDD5; color: #EA580C; }
.stat-card .stat-icon.teal { background: #CCFBF1; color: #0D9488; }
.stat-card .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
}
.stat-card .stat-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-card .stat-change {
    font-size: 0.7rem;
    margin-top: 6px;
    color: var(--gray-400);
}

.charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}
.chart-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.chart-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.chart-card .card-header h3 {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
}
.chart-card .card-header .period {
    font-size: 0.7rem;
    color: var(--gray-400);
    background: var(--gray-100);
    padding: 2px 12px;
    border-radius: 20px;
}
.chart-container {
    height: 200px;
    position: relative;
}

.activities-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 20px;
}
.activity-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.activity-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.activity-card .card-header h3 {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
}
.activity-card .card-header a {
    font-size: 0.75rem;
    color: var(--primary);
    text-decoration: none;
}
.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-100);
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-item .activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    flex-shrink: 0;
}
.activity-item .activity-icon.login { background: #DBEAFE; color: #2563EB; }
.activity-item .activity-icon.system { background: #EDE9FE; color: #7C3AED; }
.activity-item .activity-icon.result { background: #D1FAE5; color: #059669; }
.activity-item .activity-icon.incident { background: #FEE2E2; color: #DC2626; }
.activity-item .activity-icon.user { background: #FEF3C7; color: #D97706; }
.activity-item .activity-content {
    flex: 1;
    min-width: 0;
}
.activity-item .activity-content .title {
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--gray-700);
}
.activity-item .activity-content .desc {
    font-size: 0.78rem;
    color: var(--gray-500);
}
.activity-item .activity-content .time {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 2px;
}
.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.quick-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    color: var(--gray-700);
    font-size: 0.8rem;
    font-weight: 500;
    transition: var(--transition);
}
.quick-action-btn:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}
.quick-action-btn i {
    font-size: 1rem;
    width: 20px;
    text-align: center;
}

.incident-summary {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px;
}
.incident-stat {
    text-align: center;
    padding: 10px;
    background: var(--gray-50);
    border-radius: 8px;
}
.incident-stat .label {
    display: block;
    font-size: 0.65rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.incident-stat .value {
    font-size: 1.3rem;
    font-weight: 700;
}

@media (max-width: 1024px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    .activities-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 640px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .quick-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h2>Welcome, <?php echo htmlspecialchars($user_name); ?> 👋</h2>
            <p>Federal Constituency Coordinator - <?php echo htmlspecialchars($constituency_name); ?></p>
            <div class="breadcrumb">
                <i class="fas fa-flag"></i>
                <span><?php echo htmlspecialchars($state_name); ?></span>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span><?php echo htmlspecialchars($constituency_name); ?></span>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="color:var(--primary);font-weight:500;">Dashboard</span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-building"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_lgas'] ?? 0); ?></div>
                <div class="stat-label">LGAs</div>
                <div class="stat-change"><i class="fas fa-layer-group"></i> <?php echo number_format($stats['total_wards'] ?? 0); ?> Wards</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_pus'] ?? 0); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change"><i class="fas fa-users"></i> <?php echo number_format($agent_stats['pu_agents']); ?> Agents</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($coordinator_stats['lga'] + $coordinator_stats['ward']); ?></div>
                <div class="stat-label">Coordinators</div>
                <div class="stat-change"><?php echo $coordinator_stats['lga']; ?> LGA · <?php echo $coordinator_stats['ward']; ?> Ward</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($result_stats['verified'] ?? 0); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change"><i class="fas fa-clock"></i> <?php echo $result_stats['pending'] ?? 0; ?> pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($incident_stats['total'] ?? 0); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change"><?php echo $incident_stats['reported'] ?? 0; ?> reported</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-upload"></i></div>
                <div class="stat-number"><?php 
                    $total = $pu_reporting_status['total'] ?? 1;
                    $submitted = $pu_reporting_status['submitted'] ?? 0;
                    echo number_format(($submitted / max($total, 1)) * 100, 1);
                ?>%</div>
                <div class="stat-label">Reporting Rate</div>
                <div class="stat-change"><?php echo number_format($submitted); ?>/<?php echo number_format($total); ?> submitted</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line" style="color:var(--primary);margin-right:6px;"></i> Result Verification Status</h3>
                    <span class="period"><?php echo htmlspecialchars($constituency_name); ?></span>
                </div>
                <div class="chart-container">
                    <canvas id="resultStatusChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group" style="color:var(--primary);margin-right:6px;"></i> Agent Distribution</h3>
                    <span class="period">By Role</span>
                </div>
                <div class="chart-container">
                    <canvas id="agentDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Activities & Quick Actions -->
        <div class="activities-grid">
            <div class="activity-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i> Recent Activities</h3>
                    <a href="activity-logs.php">View All →</a>
                </div>
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach (array_slice($recent_activities, 0, 8) as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php 
                                if (strpos($activity['activity_type'] ?? '', 'login') !== false) echo 'login';
                                elseif (strpos($activity['activity_type'] ?? '', 'result') !== false) echo 'result';
                                elseif (strpos($activity['activity_type'] ?? '', 'incident') !== false) echo 'incident';
                                elseif (strpos($activity['activity_type'] ?? '', 'user') !== false) echo 'user';
                                else echo 'system';
                            ?>">
                                <i class="fas <?php 
                                    if (strpos($activity['activity_type'] ?? '', 'login') !== false) echo 'fa-sign-in-alt';
                                    elseif (strpos($activity['activity_type'] ?? '', 'result') !== false) echo 'fa-file-alt';
                                    elseif (strpos($activity['activity_type'] ?? '', 'incident') !== false) echo 'fa-exclamation-triangle';
                                    elseif (strpos($activity['activity_type'] ?? '', 'user') !== false) echo 'fa-user';
                                    else echo 'fa-cog';
                                ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="title text-truncate"><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></div>
                                <div class="desc text-truncate"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></div>
                                <div class="time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'] ?? 'now')); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--gray-500);padding:16px 0;text-align:center;">No recent activities</p>
                <?php endif; ?>
            </div>

            <div>
                <div class="activity-card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt" style="color:var(--primary);margin-right:6px;"></i> Quick Actions</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="monitor-constituency.php" class="quick-action-btn">
                            <i class="fas fa-building"></i> Monitor Constituency
                        </a>
                        <a href="broadcasts-create.php" class="quick-action-btn">
                            <i class="fas fa-bullhorn"></i> Broadcast
                        </a>
                        <a href="verify-ec8a.php" class="quick-action-btn">
                            <i class="fas fa-check-double"></i> Verify Results
                        </a>
                        <a href="reports-constituency.php" class="quick-action-btn">
                            <i class="fas fa-file-alt"></i> Reports
                        </a>
                        <a href="incidents.php" class="quick-action-btn">
                            <i class="fas fa-exclamation-triangle"></i> Incidents
                        </a>
                        <a href="coordinators.php" class="quick-action-btn">
                            <i class="fas fa-user-tie"></i> Coordinators
                        </a>
                        <a href="chat.php" class="quick-action-btn">
                            <i class="fas fa-comment-dots"></i> Chat
                        </a>
                        <a href="notifications.php" class="quick-action-btn">
                            <i class="fas fa-bell"></i> Notifications
                        </a>
                    </div>
                </div>

                <div class="activity-card">
                    <div class="card-header">
                        <h3><i class="fas fa-exclamation-triangle" style="color:var(--danger);margin-right:6px;"></i> Incident Summary</h3>
                        <a href="incidents.php">View All →</a>
                    </div>
                    <div class="incident-summary">
                        <div class="incident-stat">
                            <span class="label">Reported</span>
                            <span class="value" style="color:var(--warning);"><?php echo $incident_stats['reported'] ?? 0; ?></span>
                        </div>
                        <div class="incident-stat">
                            <span class="label">Investigating</span>
                            <span class="value" style="color:var(--info);"><?php echo $incident_stats['investigating'] ?? 0; ?></span>
                        </div>
                        <div class="incident-stat">
                            <span class="label">Resolved</span>
                            <span class="value" style="color:var(--secondary);"><?php echo $incident_stats['resolved'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Result Status Chart
const ctx1 = document.getElementById('resultStatusChart').getContext('2d');
new Chart(ctx1, {
    type: 'doughnut',
    data: {
        labels: ['Verified', 'Pending', 'Flagged'],
        datasets: [{
            data: [
                <?php echo $result_stats['verified'] ?? 0; ?>,
                <?php echo $result_stats['pending'] ?? 0; ?>,
                <?php echo $result_stats['flagged'] ?? 0; ?>
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

// Agent Distribution Chart
const ctx2 = document.getElementById('agentDistributionChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: ['PU Agents', 'Party Agents', 'Observers', 'Volunteers'],
        datasets: [{
            label: 'Number of Agents',
            data: [
                <?php echo $agent_stats['pu_agents'] ?? 0; ?>,
                <?php echo $agent_stats['party_agents'] ?? 0; ?>,
                <?php echo $agent_stats['observers'] ?? 0; ?>,
                <?php echo $agent_stats['volunteers'] ?? 0; ?>
            ],
            backgroundColor: [
                'rgba(37, 99, 235, 0.7)',
                'rgba(124, 58, 237, 0.7)',
                'rgba(234, 88, 12, 0.7)',
                'rgba(5, 150, 105, 0.7)'
            ],
            borderColor: ['#2563EB', '#7C3AED', '#EA580C', '#059669'],
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
                ticks: { font: { size: 10 } }
            }
        }
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