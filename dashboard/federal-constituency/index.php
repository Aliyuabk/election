<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR DASHBOARD
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

// Only federal constituency coordinator can access
if (SessionManager::get('role_level') !== 'federal_constituency') {
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

// LGA Statistics (in federal constituency)
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

// Result Statistics
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
        WHERE tenant_id = ? AND state_id = ?
    ");
    $stmt->execute([$tenant_id, $user_state_id]);
    $incident_stats = $stmt->fetch();
} catch (Exception $e) {
    $incident_stats = ['total' => 0, 'reported' => 0, 'investigating' => 0, 'resolved' => 0];
}

// LGA Performance
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

// Recent Activities
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
            <p>Federal Constituency Coordinator Dashboard - <?php echo htmlspecialchars($state_name); ?> State</p>
            <div class="breadcrumb">
                <span>🏛️ Federal Constituency</span>
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
                <div class="stat-icon yellow"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($result_stats['verified'] ?? 0); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $result_stats['pending'] ?? 0; ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-percentage"></i></div>
                <div class="stat-number"><?php 
                    $total = ($result_stats['total_results'] ?? 0);
                    $verified = ($result_stats['verified'] ?? 0);
                    $percentage = $total > 0 ? round(($verified / $total) * 100) : 0;
                    echo $percentage . '%';
                ?></div>
                <div class="stat-label">Completion Rate</div>
                <div class="stat-change <?php echo $percentage >= 70 ? 'up' : 'down'; ?>">
                    <i class="fas fa-<?php echo $percentage >= 70 ? 'arrow-up' : 'arrow-down'; ?>"></i> 
                    <?php echo $percentage >= 70 ? 'On track' : 'Needs attention'; ?>
                </div>
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
                    <span class="period">By Status</span>
                </div>
                <div class="chart-container">
                    <canvas id="progressChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group" style="color:var(--primary);margin-right:6px;"></i> LGA Performance</h3>
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
                        <a href="monitor-constituency.php" class="quick-action-btn">
                            <i class="fas fa-building"></i> Monitor Constituency
                        </a>
                        <a href="broadcasts-create.php" class="quick-action-btn">
                            <i class="fas fa-bullhorn"></i> Broadcast
                        </a>
                        <a href="result-verification.php" class="quick-action-btn">
                            <i class="fas fa-check-double"></i> Verify Results
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
// CHARTS - Federal Constituency Coordinator Dashboard
// ============================================================

// Progress Chart
const ctx1 = document.getElementById('progressChart').getContext('2d');
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

// Top LGAs Chart
const ctx2 = document.getElementById('topLgasChart').getContext('2d');
const topLgasData = <?php 
    $lgas = array_column($top_lgas, 'lga_name');
    $counts = array_column($top_lgas, 'verified_count');
    echo json_encode(['labels' => $lgas, 'data' => $counts]);
?>;

new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: topLgasData.labels || ['No Data'],
        datasets: [{
            label: 'Verified Results',
            data: topLgasData.data || [0],
            backgroundColor: 'rgba(245, 158, 11, 0.7)',
            borderColor: '#F59E0B',
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