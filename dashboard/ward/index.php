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

// Only ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$user_ward_id = SessionManager::get('ward_id');
$user_lga_id = SessionManager::get('lga_id');

$db = getDB();

// ============================================================
// FETCH DASHBOARD STATISTICS
// ============================================================

$tenant_id = SessionManager::get('tenant_id');

// Get Ward Name
$ward_name = 'Ward';
try {
    $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
    $stmt->execute([$user_ward_id]);
    $ward_name = $stmt->fetchColumn() ?: 'Ward';
} catch (Exception $e) {
    $ward_name = 'Ward';
}

// Polling Unit Statistics
$pu_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT pu.id) as total_pus,
            SUM(pu.registered_voters) as total_voters,
            SUM(CASE WHEN pu.is_active = 1 THEN pu.registered_voters ELSE 0 END) as active_voters,
            AVG(pu.registered_voters) as avg_voters_per_pu
        FROM polling_units pu
        WHERE pu.ward_id = ? AND pu.is_active = 1
    ");
    $stmt->execute([$user_ward_id]);
    $pu_stats = $stmt->fetch();
} catch (Exception $e) {
    $pu_stats = ['total_pus' => 0, 'total_voters' => 0, 'active_voters' => 0, 'avg_voters_per_pu' => 0];
}

// Polling Unit Agents Count
$agent_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT aa.id) as total_agents,
            SUM(CASE WHEN aa.status = 'active' THEN 1 ELSE 0 END) as active_agents,
            SUM(CASE WHEN aa.status = 'pending' THEN 1 ELSE 0 END) as pending_agents
        FROM agent_assignments aa
        JOIN users u ON aa.user_id = u.id
        WHERE aa.tenant_id = ? AND aa.ward_id = ?
    ");
    $stmt->execute([$tenant_id, $user_ward_id]);
    $agent_stats = $stmt->fetch();
} catch (Exception $e) {
    $agent_stats = ['total_agents' => 0, 'active_agents' => 0, 'pending_agents' => 0];
}

// Result Statistics (Ward level)
$result_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_results,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN r.status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        WHERE r.tenant_id = ? AND pu.ward_id = ?
    ");
    $stmt->execute([$tenant_id, $user_ward_id]);
    $result_stats = $stmt->fetch();
} catch (Exception $e) {
    $result_stats = ['total_results' => 0, 'verified' => 0, 'pending' => 0, 'flagged' => 0];
}

// EC8B Upload Status
$ec8b_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_uploads,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM results_ec8b
        WHERE tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$tenant_id, $user_ward_id]);
    $ec8b_stats = $stmt->fetch();
} catch (Exception $e) {
    $ec8b_stats = ['total_uploads' => 0, 'verified' => 0, 'pending' => 0];
}

// Incident Statistics
$incident_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported,
            SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
        FROM incidents 
        WHERE tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$tenant_id, $user_ward_id]);
    $incident_stats = $stmt->fetch();
} catch (Exception $e) {
    $incident_stats = ['total' => 0, 'reported' => 0, 'investigating' => 0, 'resolved' => 0];
}

// PU Performance (top PUs by verified results)
$top_pus = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pu.name as pu_name,
            COUNT(r.id) as verified_count
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        WHERE r.tenant_id = ? AND pu.ward_id = ? AND r.status = 'verified'
        GROUP BY pu.id
        ORDER BY verified_count DESC
        LIMIT 5
    ");
    $stmt->execute([$tenant_id, $user_ward_id]);
    $top_pus = $stmt->fetchAll();
} catch (Exception $e) {
    $top_pus = [];
}

// Recent Activities
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as user_name
        FROM activity_logs a
        JOIN users u ON a.user_id = u.id
        WHERE a.tenant_id = ? 
        AND (a.ward_id = ? OR a.ward_id IS NULL)
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id, $user_ward_id]);
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_activities = [];
}

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h2>Welcome, <?php echo htmlspecialchars($user_name); ?> 👋</h2>
            <p>Ward Coordinator Dashboard - <?php echo htmlspecialchars($ward_name); ?> Ward</p>
            <div class="breadcrumb">
                <span>📍 <?php echo htmlspecialchars($ward_name); ?></span>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($pu_stats['total_pus'] ?? 0); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change"><i class="fas fa-users"></i> <?php echo number_format($pu_stats['avg_voters_per_pu'] ?? 0); ?> avg voters</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($pu_stats['total_voters'] ?? 0); ?></div>
                <div class="stat-label">Registered Voters</div>
                <div class="stat-change"><i class="fas fa-check-circle"></i> <?php echo number_format($pu_stats['active_voters'] ?? 0); ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($agent_stats['total_agents'] ?? 0); ?></div>
                <div class="stat-label">PU Agents</div>
                <div class="stat-change up"><i class="fas fa-check-circle"></i> <?php echo $agent_stats['active_agents'] ?? 0; ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($result_stats['verified'] ?? 0); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $result_stats['pending'] ?? 0; ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-upload"></i></div>
                <div class="stat-number"><?php echo number_format($ec8b_stats['total_uploads'] ?? 0); ?></div>
                <div class="stat-label">EC8B Uploads</div>
                <div class="stat-change up"><i class="fas fa-check-circle"></i> <?php echo $ec8b_stats['verified'] ?? 0; ?> verified</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($incident_stats['total'] ?? 0); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $incident_stats['reported'] ?? 0; ?> reported</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line" style="color:var(--primary);margin-right:6px;"></i> Result Status</h3>
                    <span class="period">By Verification</span>
                </div>
                <div class="chart-container">
                    <canvas id="progressChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group" style="color:var(--primary);margin-right:6px;"></i> Top Performing PUs</h3>
                    <span class="period">Verified results</span>
                </div>
                <div class="chart-container">
                    <canvas id="topPUsChart"></canvas>
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
                    <p style="color:var(--gray-500);padding:16px 0;text-align:center;">No recent activities</p>
                <?php endif; ?>
            </div>

            <!-- Right Column: Quick Actions & Stats -->
            <div>
                <!-- Quick Actions -->
                <div class="activity-card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt" style="color:var(--primary);margin-right:6px;"></i> Quick Actions</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="manage-pu-agents.php" class="quick-action-btn">
                            <i class="fas fa-user-tie"></i> Manage Agents
                        </a>
                        <a href="upload-ec8b.php" class="quick-action-btn">
                            <i class="fas fa-upload"></i> Upload EC8B
                        </a>
                        <a href="broadcasts-create.php" class="quick-action-btn">
                            <i class="fas fa-bullhorn"></i> Broadcast
                        </a>
                        <a href="incidents.php" class="quick-action-btn">
                            <i class="fas fa-exclamation-triangle"></i> View Incidents
                        </a>
                        <a href="reports.php" class="quick-action-btn">
                            <i class="fas fa-file-alt"></i> Generate Report
                        </a>
                    </div>
                </div>

                <!-- Incident Summary -->
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
 
<?php include '../includes/footer.php'; ?>