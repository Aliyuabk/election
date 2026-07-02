<?php
$page_title = "Dashboard";
require_once 'includes/db.php';
$db = Database::getInstance()->getConnection();

// ============================================================
// PLATFORM STATISTICS
// ============================================================

// Tenant Statistics
$tenantStats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN subscription_status = 'active' AND deleted_at IS NULL THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN subscription_status = 'suspended' AND deleted_at IS NULL THEN 1 ELSE 0 END) as suspended,
        SUM(CASE WHEN subscription_status = 'trial' AND deleted_at IS NULL THEN 1 ELSE 0 END) as trial,
        SUM(CASE WHEN subscription_status = 'expired' AND deleted_at IS NULL THEN 1 ELSE 0 END) as expired
    FROM tenants WHERE deleted_at IS NULL
")->fetch();

// User Statistics
$userStats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' AND deleted_at IS NULL THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN DATE(created_at) = CURDATE() AND deleted_at IS NULL THEN 1 ELSE 0 END) as new_today
    FROM users WHERE deleted_at IS NULL
")->fetch();

// Online Users (last 15 minutes)
$onlineUsers = $db->query("
    SELECT COUNT(DISTINCT user_id) as online
    FROM user_sessions 
    WHERE is_active = 1 
    AND last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
")->fetch()['online'];

// Election Statistics
$electionStats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' AND deleted_at IS NULL THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'closed' AND deleted_at IS NULL THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'upcoming' AND deleted_at IS NULL THEN 1 ELSE 0 END) as upcoming
    FROM elections WHERE deleted_at IS NULL
")->fetch();

// ============================================================
// RECENT ACTIVITIES
// ============================================================

$recentActivities = $db->query("
    SELECT 
        al.*, 
        u.full_name, 
        u.email,
        t.name as tenant_name,
        CASE 
            WHEN al.activity_type = 'tenant_created' THEN 'tenant'
            WHEN al.activity_type = 'user_registered' THEN 'user'
            WHEN al.activity_type = 'login' THEN 'login'
            WHEN al.activity_type = 'logout' THEN 'logout'
            WHEN al.activity_type = 'backup_created' THEN 'backup'
            WHEN al.activity_type = 'system_updated' THEN 'system'
            ELSE 'other'
        END as activity_category
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN tenants t ON al.tenant_id = t.id
    ORDER BY al.created_at DESC 
    LIMIT 20
")->fetchAll();

// ============================================================
// MONTHLY REVENUE (Last 12 Months)
// ============================================================

$monthlyRevenue = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(amount) as revenue,
        COUNT(*) as count
    FROM invoices 
    WHERE status = 'paid'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
")->fetchAll();

// ============================================================
// SUBSCRIPTION STATISTICS
// ============================================================

$subscriptionStats = $db->query("
    SELECT 
        subscription_plan,
        COUNT(*) as count
    FROM tenants 
    WHERE deleted_at IS NULL
    GROUP BY subscription_plan
")->fetchAll();

// ============================================================
// SERVER STATUS (Simulated - Replace with actual server monitoring)
// ============================================================

// In production, use: sys_getloadavg(), disk_free_space(), memory_get_usage()
$serverStatus = [
    'cpu' => rand(15, 65),
    'ram' => rand(40, 85),
    'storage' => rand(20, 75),
    'database' => 'operational',
    'api' => 'operational'
];

// ============================================================
// RECENT BACKUPS
// ============================================================

$recentBackups = $db->query("
    SELECT * FROM backups 
    WHERE status = 'completed'
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<main class="main-content">
    <!-- ============================================================
    STATS CARDS
    ============================================================ -->
    <div class="stats-grid">
        <!-- Tenants -->
        <div class="stat-card" style="border-left-color: #4f9cf7;">
            <div class="stat-icon" style="background: #e8f0fe; color: #4f9cf7;">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-content">
                <div class="stat-main">
                    <div class="stat-value"><?php echo $tenantStats['total']; ?></div>
                    <div class="stat-label">Total Tenants</div>
                </div>
                <div class="stat-details">
                    <span class="stat-detail active"><i class="fas fa-circle"></i> <?php echo $tenantStats['active']; ?> Active</span>
                    <span class="stat-detail suspended"><i class="fas fa-circle"></i> <?php echo $tenantStats['suspended']; ?> Suspended</span>
                    <span class="stat-detail trial"><i class="fas fa-circle"></i> <?php echo $tenantStats['trial']; ?> Trial</span>
                    <span class="stat-detail expired"><i class="fas fa-circle"></i> <?php echo $tenantStats['expired']; ?> Expired</span>
                </div>
            </div>
        </div>

        <!-- Users -->
        <div class="stat-card" style="border-left-color: #10b981;">
            <div class="stat-icon" style="background: #e6f9f0; color: #10b981;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-main">
                    <div class="stat-value"><?php echo $userStats['total']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-details">
                    <span class="stat-detail active"><i class="fas fa-circle"></i> <?php echo $userStats['active']; ?> Active</span>
                    <span class="stat-detail online"><i class="fas fa-circle"></i> <?php echo $onlineUsers; ?> Online</span>
                    <span class="stat-detail new"><i class="fas fa-circle"></i> +<?php echo $userStats['new_today']; ?> Today</span>
                </div>
            </div>
        </div>

        <!-- Elections -->
        <div class="stat-card" style="border-left-color: #f59e0b;">
            <div class="stat-icon" style="background: #fef3e2; color: #f59e0b;">
                <i class="fas fa-vote-yea"></i>
            </div>
            <div class="stat-content">
                <div class="stat-main">
                    <div class="stat-value"><?php echo $electionStats['total']; ?></div>
                    <div class="stat-label">Total Elections</div>
                </div>
                <div class="stat-details">
                    <span class="stat-detail active"><i class="fas fa-circle"></i> <?php echo $electionStats['active']; ?> Active</span>
                    <span class="stat-detail completed"><i class="fas fa-circle"></i> <?php echo $electionStats['completed']; ?> Completed</span>
                    <span class="stat-detail upcoming"><i class="fas fa-circle"></i> <?php echo $electionStats['upcoming']; ?> Upcoming</span>
                </div>
            </div>
        </div>

        <!-- Server Status -->
        <div class="stat-card" style="border-left-color: #8b5cf6;">
            <div class="stat-icon" style="background: #ede9fe; color: #8b5cf6;">
                <i class="fas fa-server"></i>
            </div>
            <div class="stat-content">
                <div class="stat-main">
                    <div class="stat-value" style="font-size:1.2rem;">Server Status</div>
                    <div class="stat-label">All Systems <?php echo ($serverStatus['database'] === 'operational' && $serverStatus['api'] === 'operational') ? '✅ Operational' : '⚠️ Issues'; ?></div>
                </div>
                <div class="stat-details server-status">
                    <span class="stat-detail"><i class="fas fa-microchip"></i> CPU <?php echo $serverStatus['cpu']; ?>%</span>
                    <span class="stat-detail"><i class="fas fa-memory"></i> RAM <?php echo $serverStatus['ram']; ?>%</span>
                    <span class="stat-detail"><i class="fas fa-hdd"></i> Storage <?php echo $serverStatus['storage']; ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
    CHARTS ROW
    ============================================================ -->
    <div class="charts-grid">
        <!-- Monthly Revenue Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-chart-line" style="color:#4f9cf7;"></i> Monthly Revenue</h3>
                <span class="chart-period">Last 12 Months</span>
            </div>
            <div class="chart-container" id="revenueChart">
                <canvas id="revenueCanvas"></canvas>
            </div>
        </div>

        <!-- Subscription Distribution -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-chart-pie" style="color:#10b981;"></i> Subscription Plans</h3>
                <span class="chart-period">Distribution</span>
            </div>
            <div class="chart-container" id="subscriptionChart">
                <canvas id="subscriptionCanvas"></canvas>
            </div>
        </div>
    </div>

    <!-- ============================================================
    RECENT ACTIVITIES & BACKUPS
    ============================================================ -->
    <div class="dashboard-grid">
        <!-- Recent Activities -->
        <div class="dashboard-panel">
            <div class="panel-header">
                <h3><i class="fas fa-clock"></i> Recent Activities</h3>
                <a href="audit-logs.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="activity-list">
                <?php foreach (array_slice($recentActivities, 0, 10) as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon <?php echo $activity['activity_category']; ?>">
                        <?php if ($activity['activity_category'] === 'tenant'): ?>
                            <i class="fas fa-building"></i>
                        <?php elseif ($activity['activity_category'] === 'user'): ?>
                            <i class="fas fa-user-plus"></i>
                        <?php elseif ($activity['activity_category'] === 'login'): ?>
                            <i class="fas fa-sign-in-alt"></i>
                        <?php elseif ($activity['activity_category'] === 'logout'): ?>
                            <i class="fas fa-sign-out-alt"></i>
                        <?php elseif ($activity['activity_category'] === 'backup'): ?>
                            <i class="fas fa-database"></i>
                        <?php elseif ($activity['activity_category'] === 'system'): ?>
                            <i class="fas fa-sync-alt"></i>
                        <?php else: ?>
                            <i class="fas fa-circle"></i>
                        <?php endif; ?>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">
                            <?php if ($activity['full_name']): ?>
                            <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($activity['description']); ?>
                            <?php if ($activity['tenant_name']): ?>
                            <span class="activity-tenant">· <?php echo htmlspecialchars($activity['tenant_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="activity-time">
                            <?php echo date('M d, H:i', strtotime($activity['created_at'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($recentActivities)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No recent activities</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Backups -->
        <div class="dashboard-panel">
            <div class="panel-header">
                <h3><i class="fas fa-database"></i> Recent Backups</h3>
                <a href="backups.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="backup-list">
                <?php foreach ($recentBackups as $backup): ?>
                <div class="backup-item">
                    <div class="backup-icon">
                        <i class="fas fa-file-archive"></i>
                    </div>
                    <div class="backup-info">
                        <div class="backup-name">
                            <?php echo htmlspecialchars($backup['backup_type']); ?> Backup
                            <span class="backup-status completed">Completed</span>
                        </div>
                        <div class="backup-meta">
                            <span><i class="fas fa-file"></i> <?php echo number_format($backup['file_size'] / 1024 / 1024, 1); ?> MB</span>
                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, H:i', strtotime($backup['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($recentBackups)): ?>
                <div class="empty-state">
                    <i class="fas fa-database"></i>
                    <p>No backups available</p>
                    <small>Run your first backup from the Backup & Restore section</small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================================
    QUICK ACTIONS
    ============================================================ -->
    <div class="quick-actions">
        <h3><i class="fas fa-bolt" style="color:#f59e0b;"></i> Quick Actions</h3>
        <div class="quick-actions-grid">
            <a href="tenant-edit.php" class="quick-action">
                <div class="quick-action-icon" style="background:#e8f0fe; color:#4f9cf7;">
                    <i class="fas fa-building"></i>
                </div>
                <div class="quick-action-text">
                    <div class="quick-action-title">Create Tenant</div>
                    <div class="quick-action-desc">Add a new client tenant</div>
                </div>
            </a>
            <a href="tenants.php" class="quick-action">
                <div class="quick-action-icon" style="background:#e6f9f0; color:#10b981;">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="quick-action-text">
                    <div class="quick-action-title">Add Subscription</div>
                    <div class="quick-action-desc">Manage subscription plans</div>
                </div>
            </a>
            <a href="inec-upload.php" class="quick-action">
                <div class="quick-action-icon" style="background:#fef3e2; color:#f59e0b;">
                    <i class="fas fa-upload"></i>
                </div>
                <div class="quick-action-text">
                    <div class="quick-action-title">Upload INEC Data</div>
                    <div class="quick-action-desc">Import master election data</div>
                </div>
            </a>
            <a href="audit-logs.php" class="quick-action">
                <div class="quick-action-icon" style="background:#fde8e8; color:#ef4444;">
                    <i class="fas fa-history"></i>
                </div>
                <div class="quick-action-text">
                    <div class="quick-action-title">View Audit Logs</div>
                    <div class="quick-action-desc">Monitor system activities</div>
                </div>
            </a>
        </div>
    </div>
</main>

<!-- ============================================================
CHART.JS FOR CHARTS
============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================================
    // REVENUE CHART
    // ============================================================
    const revenueCtx = document.getElementById('revenueCanvas').getContext('2d');
    const revenueData = <?php echo json_encode($monthlyRevenue); ?>;
    
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: revenueData.map(d => d.month),
            datasets: [{
                label: 'Revenue (₦)',
                data: revenueData.map(d => d.revenue),
                borderColor: '#4f9cf7',
                backgroundColor: 'rgba(79, 156, 247, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#4f9cf7',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '₦' + Number(context.raw).toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₦' + Number(value).toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // ============================================================
    // SUBSCRIPTION CHART
    // ============================================================
    const subCtx = document.getElementById('subscriptionCanvas').getContext('2d');
    const subData = <?php echo json_encode($subscriptionStats); ?>;
    
    const colors = {
        free: '#9ca3af',
        basic: '#60a5fa',
        standard: '#34d399',
        premium: '#fbbf24',
        enterprise: '#a78bfa'
    };

    new Chart(subCtx, {
        type: 'doughnut',
        data: {
            labels: subData.map(d => d.subscription_plan.charAt(0).toUpperCase() + d.subscription_plan.slice(1)),
            datasets: [{
                data: subData.map(d => d.count),
                backgroundColor: subData.map(d => colors[d.subscription_plan] || '#4f9cf7'),
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 16,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            },
            cutout: '70%'
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>