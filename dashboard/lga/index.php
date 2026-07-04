<?php
// ============================================================
// LGA COORDINATOR DASHBOARD
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

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$user_lga_id = SessionManager::get('lga_id');
$user_state_id = SessionManager::get('state_id');

$db = getDB();

// ============================================================
// FETCH DASHBOARD STATISTICS
// ============================================================

$tenant_id = SessionManager::get('tenant_id');

// Get LGA Name
$lga_name = 'LGA';
try {
    $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
    $stmt->execute([$user_lga_id]);
    $lga_name = $stmt->fetchColumn() ?: 'LGA';
} catch (Exception $e) {
    $lga_name = 'LGA';
}

// Ward Statistics
$ward_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT w.id) as total_wards,
            COUNT(DISTINCT pu.id) as total_pus,
            SUM(pu.registered_voters) as total_voters,
            SUM(pu.registered_voters) as active_voters
        FROM wards w
        LEFT JOIN polling_units pu ON pu.ward_id = w.id
        WHERE w.lga_id = ? AND w.is_active = 1
    ");
    $stmt->execute([$user_lga_id]);
    $ward_stats = $stmt->fetch();
} catch (Exception $e) {
    $ward_stats = ['total_wards' => 0, 'total_pus' => 0, 'total_voters' => 0, 'active_voters' => 0];
}

// Election Statistics
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
    ");
    $stmt->execute([$tenant_id]);
    $election_stats = $stmt->fetch();
} catch (Exception $e) {
    $election_stats = ['total' => 0, 'active' => 0, 'upcoming' => 0, 'completed' => 0];
}

// Result Statistics (LGA level)
$result_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_results,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN r.status = 'flagged' THEN 1 ELSE 0 END) as flagged,
            SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        WHERE r.tenant_id = ? AND w.lga_id = ?
    ");
    $stmt->execute([$tenant_id, $user_lga_id]);
    $result_stats = $stmt->fetch();
} catch (Exception $e) {
    $result_stats = ['total_results' => 0, 'verified' => 0, 'pending' => 0, 'flagged' => 0, 'approved' => 0];
}

// Ward Coordinators Count
$coordinator_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_coordinators,
            SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) as active
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND r.level = 'ward' 
        AND u.lga_id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id, $user_lga_id]);
    $coordinator_stats = $stmt->fetch();
} catch (Exception $e) {
    $coordinator_stats = ['total_coordinators' => 0, 'active' => 0];
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
        WHERE tenant_id = ? AND lga_id = ?
    ");
    $stmt->execute([$tenant_id, $user_lga_id]);
    $incident_stats = $stmt->fetch();
} catch (Exception $e) {
    $incident_stats = ['total' => 0, 'reported' => 0, 'investigating' => 0, 'resolved' => 0];
}

// Ward Performance (top wards by verified results)
$top_wards = [];
try {
    $stmt = $db->prepare("
        SELECT 
            w.name as ward_name,
            COUNT(r.id) as verified_count
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        WHERE r.tenant_id = ? AND w.lga_id = ? AND r.status = 'verified'
        GROUP BY w.id
        ORDER BY verified_count DESC
        LIMIT 5
    ");
    $stmt->execute([$tenant_id, $user_lga_id]);
    $top_wards = $stmt->fetchAll();
} catch (Exception $e) {
    $top_wards = [];
}

// Recent Activities
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as user_name
        FROM activity_logs a
        JOIN users u ON a.user_id = u.id
        WHERE a.tenant_id = ? 
        AND (a.lga_id = ? OR a.lga_id IS NULL)
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id, $user_lga_id]);
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
            <p>LGA Coordinator Dashboard - <?php echo htmlspecialchars($lga_name); ?> LGA</p>
            <div class="breadcrumb">
                <span>📍 <?php echo htmlspecialchars($lga_name); ?></span>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number"><?php echo number_format($ward_stats['total_wards'] ?? 0); ?></div>
                <div class="stat-label">Wards</div>
                <div class="stat-change"><i class="fas fa-flag-checkered"></i> <?php echo number_format($ward_stats['total_pus'] ?? 0); ?> PUs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($ward_stats['total_voters'] ?? 0); ?></div>
                <div class="stat-label">Registered Voters</div>
                <div class="stat-change"><i class="fas fa-check-circle"></i> Active voters</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($election_stats['total'] ?? 0); ?></div>
                <div class="stat-label">Elections</div>
                <div class="stat-change up"><i class="fas fa-play"></i> <?php echo $election_stats['active'] ?? 0; ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($result_stats['approved'] ?? 0); ?></div>
                <div class="stat-label">Approved Results</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $result_stats['pending'] ?? 0; ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($coordinator_stats['total_coordinators'] ?? 0); ?></div>
                <div class="stat-label">Ward Coordinators</div>
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
                    <h3><i class="fas fa-chart-line" style="color:var(--primary);margin-right:6px;"></i> Result Status</h3>
                    <span class="period">By Approval Status</span>
                </div>
                <div class="chart-container">
                    <canvas id="progressChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group" style="color:var(--primary);margin-right:6px;"></i> Top Performing Wards</h3>
                    <span class="period">Verified results</span>
                </div>
                <div class="chart-container">
                    <canvas id="topWardsChart"></canvas>
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
                        <a href="manage-wards.php" class="quick-action-btn">
                            <i class="fas fa-layer-group"></i> Manage Wards
                        </a>
                        <a href="approve-results.php" class="quick-action-btn">
                            <i class="fas fa-check-circle"></i> Approve Results
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

<script>
// ============================================================
// CHARTS - LGA Coordinator Dashboard
// ============================================================

// Progress Chart
const ctx1 = document.getElementById('progressChart').getContext('2d');
new Chart(ctx1, {
    type: 'doughnut',
    data: {
        labels: ['Approved', 'Verified', 'Pending', 'Flagged'],
        datasets: [{
            data: [
                <?php echo $result_stats['approved'] ?? 0; ?>,
                <?php echo $result_stats['verified'] ?? 0; ?>,
                <?php echo $result_stats['pending'] ?? 0; ?>,
                <?php echo $result_stats['flagged'] ?? 0; ?>
            ],
            backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444'],
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

// Top Wards Chart
const ctx2 = document.getElementById('topWardsChart').getContext('2d');
const topWardsData = <?php 
    $wards = array_column($top_wards, 'ward_name');
    $counts = array_column($top_wards, 'verified_count');
    echo json_encode(['labels' => $wards, 'data' => $counts]);
?>;

new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: topWardsData.labels || ['No Data'],
        datasets: [{
            label: 'Verified Results',
            data: topWardsData.data || [0],
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderColor: '#10B981',
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
                    font: { size: 10 },
                    maxRotation: 45
                }
            }
        }
    }
});
</script>
<?php include '../includes/footer.php'; ?>