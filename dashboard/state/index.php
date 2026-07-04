<?php
// ============================================================
// STATE COORDINATOR DASHBOARD
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$user_state_id = SessionManager::get('state_id');

$db = getDB();

// ============================================================
// FETCH DASHBOARD STATISTICS
// ============================================================

$tenant_id = SessionManager::get('tenant_id');

// Get State Name
$state_name = 'State';
try {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$user_state_id]);
    $state_name = $stmt->fetchColumn() ?: 'State';
} catch (Exception $e) {
    $state_name = 'State';
}

// LGA Statistics
$lga_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT l.id) as total_lgas,
            COUNT(DISTINCT w.id) as total_wards,
            COUNT(DISTINCT pu.id) as total_pus,
            SUM(pu.registered_voters) as total_voters
        FROM lgas l
        LEFT JOIN wards w ON w.lga_id = l.id
        LEFT JOIN polling_units pu ON pu.ward_id = w.id
        WHERE l.state_id = ? AND l.is_active = 1
    ");
    $stmt->execute([$user_state_id]);
    $lga_stats = $stmt->fetch();
} catch (Exception $e) {
    $lga_stats = ['total_lgas' => 0, 'total_wards' => 0, 'total_pus' => 0, 'total_voters' => 0];
}

// Election Statistics (State level)
$election_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND JSON_CONTAINS(states_json, JSON_QUOTE(?))
    ");
    $stmt->execute([$tenant_id, $user_state_id]);
    $election_stats = $stmt->fetch();
} catch (Exception $e) {
    $election_stats = ['total' => 0, 'active' => 0, 'upcoming' => 0, 'completed' => 0];
}

// Result Statistics (State level)
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
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE r.tenant_id = ? AND l.state_id = ?
    ");
    $stmt->execute([$tenant_id, $user_state_id]);
    $result_stats = $stmt->fetch();
} catch (Exception $e) {
    $result_stats = ['total_results' => 0, 'verified' => 0, 'pending' => 0, 'flagged' => 0];
}

// LGA Coordinators Count
$coordinator_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_coordinators,
            SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) as active
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND r.level = 'lga' 
        AND u.state_id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id, $user_state_id]);
    $coordinator_stats = $stmt->fetch();
} catch (Exception $e) {
    $coordinator_stats = ['total_coordinators' => 0, 'active' => 0];
}

// Incident Statistics (State level)
$incident_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported,
            SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
        FROM incidents 
        WHERE tenant_id = ? AND state_id = ?
    ");
    $stmt->execute([$tenant_id, $user_state_id]);
    $incident_stats = $stmt->fetch();
} catch (Exception $e) {
    $incident_stats = ['total' => 0, 'reported' => 0, 'investigating' => 0, 'resolved' => 0];
}

// LGA Performance (top LGAs by verified results)
$top_lgas = [];
try {
    $stmt = $db->prepare("
        SELECT 
            l.name as lga_name,
            COUNT(r.id) as verified_count
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE r.tenant_id = ? AND l.state_id = ? AND r.status = 'verified'
        GROUP BY l.id
        ORDER BY verified_count DESC
        LIMIT 5
    ");
    $stmt->execute([$tenant_id, $user_state_id]);
    $top_lgas = $stmt->fetchAll();
} catch (Exception $e) {
    $top_lgas = [];
}

// Recent Activities (State level)
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as user_name
        FROM activity_logs a
        JOIN users u ON a.user_id = u.id
        WHERE a.tenant_id = ? 
        AND (a.state_id = ? OR a.state_id IS NULL)
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id, $user_state_id]);
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
            <p>State Coordinator Dashboard - <?php echo htmlspecialchars($state_name); ?> State</p>
            <div class="breadcrumb">
                <span>📍 <?php echo htmlspecialchars($state_name); ?></span>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-number"><?php echo number_format($lga_stats['total_lgas'] ?? 0); ?></div>
                <div class="stat-label">LGAs</div>
                <div class="stat-change"><i class="fas fa-layer-group"></i> <?php echo number_format($lga_stats['total_wards'] ?? 0); ?> wards</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($lga_stats['total_voters'] ?? 0); ?></div>
                <div class="stat-label">Registered Voters</div>
                <div class="stat-change"><i class="fas fa-flag-checkered"></i> <?php echo number_format($lga_stats['total_pus'] ?? 0); ?> PUs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($election_stats['total'] ?? 0); ?></div>
                <div class="stat-label">Elections</div>
                <div class="stat-change up"><i class="fas fa-play"></i> <?php echo $election_stats['active'] ?? 0; ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($result_stats['verified'] ?? 0); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $result_stats['pending'] ?? 0; ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($coordinator_stats['total_coordinators'] ?? 0); ?></div>
                <div class="stat-label">LGA Coordinators</div>
                <div class="stat-change up"><i class="fas fa-check-circle"></i> <?php echo $coordinator_stats['active'] ?? 0; ?> active</div>
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
                    <h3><i class="fas fa-chart-line" style="color:var(--primary);margin-right:6px;"></i> Result Progress</h3>
                    <span class="period">By LGA</span>
                </div>
                <div class="chart-container">
                    <canvas id="progressChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group" style="color:var(--primary);margin-right:6px;"></i> Top Performing LGAs</h3>
                    <span class="period">Verified results</span>
                </div>
                <div class="chart-container">
                    <canvas id="topLgasChart"></canvas>
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
                        <a href="monitor-lgas.php" class="quick-action-btn">
                            <i class="fas fa-map-marker-alt"></i> Monitor LGAs
                        </a>
                        <a href="manage-lga-coordinators.php" class="quick-action-btn">
                            <i class="fas fa-user-tie"></i> Manage Coordinators
                        </a>
                        <a href="broadcasts-create.php" class="quick-action-btn">
                            <i class="fas fa-bullhorn"></i> Broadcast
                        </a>
                        <a href="result-verification.php" class="quick-action-btn">
                            <i class="fas fa-check-double"></i> Verify Results
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
