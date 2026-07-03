<?php
// ============================================================
// DASHBOARD INDEX - SUPER ADMINISTRATOR
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only super_admin can access this dashboard
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// ============================================================
// FETCH DASHBOARD STATISTICS
// ============================================================

// Tenant Statistics
$stats = [
    'tenants' => [
        'total' => 0,
        'active' => 0,
        'suspended' => 0,
        'trial' => 0,
        'expired' => 0
    ],
    'users' => [
        'total' => 0,
        'active' => 0,
        'online' => 0,
        'today' => 0
    ],
    'elections' => [
        'total' => 0,
        'active' => 0,
        'completed' => 0,
        'upcoming' => 0
    ],
    'system' => [
        'cpu' => '0%',
        'ram' => '0%',
        'storage' => '0%',
        'database' => 'online',
        'api' => 'online'
    ]
];

// Tenants
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE deleted_at IS NULL");
    $stats['tenants']['total'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE is_active = 1 AND deleted_at IS NULL");
    $stats['tenants']['active'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE is_active = 0 AND deleted_at IS NULL");
    $stats['tenants']['suspended'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE subscription_status = 'trial' AND deleted_at IS NULL");
    $stats['tenants']['trial'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE subscription_status = 'expired' AND deleted_at IS NULL");
    $stats['tenants']['expired'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Log error but continue
}

// Users
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
    $stats['users']['total'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active' AND deleted_at IS NULL");
    $stats['users']['active'] = $stmt->fetch()['total'] ?? 0;

    // Online users (active sessions in last 5 minutes)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as total FROM user_sessions WHERE is_active = 1 AND last_activity_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->execute();
    $stats['users']['online'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE() AND deleted_at IS NULL");
    $stmt->execute();
    $stats['users']['today'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Log error but continue
}

// Elections
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM elections WHERE deleted_at IS NULL");
    $stats['elections']['total'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM elections WHERE status = 'active' AND deleted_at IS NULL");
    $stats['elections']['active'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM elections WHERE status = 'completed' AND deleted_at IS NULL");
    $stats['elections']['completed'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM elections WHERE status = 'upcoming' AND deleted_at IS NULL");
    $stats['elections']['upcoming'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Log error but continue
}

// System Status (simulated for demo)
$stats['system']['cpu'] = rand(15, 65) . '%';
$stats['system']['ram'] = rand(30, 80) . '%';
$stats['system']['storage'] = rand(20, 70) . '%';
$stats['system']['database'] = 'online';
$stats['system']['api'] = 'online';

// ============================================================
// FETCH RECENT ACTIVITIES
// ============================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as user_name, t.name as tenant_name
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN tenants t ON a.tenant_id = t.id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    // Log error but continue
}

// ============================================================
// FETCH CHART DATA
// ============================================================
// Monthly Revenue (last 6 months)
$monthly_revenue = [];
try {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%b') as month,
            SUM(amount) as revenue
        FROM invoices
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $monthly_revenue = $stmt->fetchAll();
} catch (Exception $e) {
    // Log error but continue
}

// Tenant Growth (last 6 months)
$tenant_growth = [];
try {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%b') as month,
            COUNT(*) as count
        FROM tenants
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)
        AND deleted_at IS NULL
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $tenant_growth = $stmt->fetchAll();
} catch (Exception $e) {
    // Log error but continue
}

// User Growth (last 6 months)
$user_growth = [];
try {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%b') as month,
            COUNT(*) as count
        FROM users
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)
        AND deleted_at IS NULL
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $user_growth = $stmt->fetchAll();
} catch (Exception $e) {
    // Log error but continue
}

// Subscription Statistics
$subscription_stats = [];
try {
    $stmt = $db->query("
        SELECT 
            subscription_plan as plan,
            COUNT(*) as count
        FROM tenants
        WHERE deleted_at IS NULL
        GROUP BY subscription_plan
    ");
    $subscription_stats = $stmt->fetchAll();
} catch (Exception $e) {
    // Log error but continue
}

// ============================================================
// FETCH RECENT BACKUPS
// ============================================================
$recent_backups = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM backups 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_backups = $stmt->fetchAll();
} catch (Exception $e) {
    // Log error but continue
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_id = SessionManager::get('user_id', 0);
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';

include 'includes/sidebar.php';
?>
 
<main class="main-content">
    <!-- Fixed Header -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Main Content Inner -->
    <div class="main-content-inner">
    
                <small>Welcome back, <?php echo htmlspecialchars($user_name); ?></small>    
    <!-- Stats Cards -->

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-building"></i></div>
                <div class="stat-number"><?php echo number_format($stats['tenants']['total']); ?></div>
                <div class="stat-label">Total Tenants</div>
                <div class="stat-change up"><i class="fas fa-arrow-up"></i> <?php echo $stats['tenants']['active']; ?> active</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['tenants']['active']); ?></div>
                <div class="stat-label">Active Tenants</div>
                <div class="stat-change down"><i class="fas fa-arrow-down"></i> <?php echo $stats['tenants']['suspended']; ?> suspended</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($stats['users']['total']); ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-change up"><i class="fas fa-user-plus"></i> <?php echo $stats['users']['today']; ?> today</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
                <div class="stat-number"><?php echo number_format($stats['users']['online']); ?></div>
                <div class="stat-label">Online Now</div>
                <div class="stat-change up"><i class="fas fa-circle" style="color:#10B981;font-size:7px;"></i> Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($stats['elections']['total']); ?></div>
                <div class="stat-label">Total Elections</div>
                <div class="stat-change up"><i class="fas fa-play"></i> <?php echo $stats['elections']['active']; ?> active</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-server"></i></div>
                <div class="stat-number"><span style="font-size:0.9rem;">CPU</span> <?php echo $stats['system']['cpu']; ?></div>
                <div class="stat-label">System Status</div>
                <div class="stat-change up"><i class="fas fa-circle" style="color:#10B981;font-size:7px;"></i> Online</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line" style="color:var(--primary);margin-right:6px;"></i> Revenue Overview</h3>
                    <span class="period">Last 6 months</span>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group" style="color:var(--primary);margin-right:6px;"></i> Growth</h3>
                    <span class="period">Last 6 months</span>
                </div>
                <div class="chart-container">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Activities & Backups -->
        <div class="activities-grid">
            <!-- Recent Activities -->
            <div class="activity-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i> Recent Activities</h3>
                    <a href="audit-logs.php">View All →</a>
                </div>
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach (array_slice($recent_activities, 0, 8) as $activity): ?>
                        <?php 
                            $iconClass = 'system';
                            $icon = 'fa-cog';
                            $type = $activity['activity_type'] ?? '';
                            if (strpos($type, 'login') !== false) {
                                $iconClass = 'login';
                                $icon = 'fa-sign-in-alt';
                            } elseif (strpos($type, 'tenant') !== false) {
                                $iconClass = 'tenant';
                                $icon = 'fa-building';
                            } elseif (strpos($type, 'user') !== false) {
                                $iconClass = 'user';
                                $icon = 'fa-user';
                            } elseif (strpos($type, 'backup') !== false) {
                                $iconClass = 'backup';
                                $icon = 'fa-archive';
                            }
                        ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $iconClass; ?>">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="title text-truncate"><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></div>
                                <div class="desc text-truncate"><?php echo htmlspecialchars($activity['description'] ?? 'Activity'); ?></div>
                                <div class="time">
                                    <?php 
                                        $timestamp = strtotime($activity['created_at'] ?? 'now');
                                        echo date('M j, Y g:i A', $timestamp);
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--gray-500);padding:16px 0;text-align:center;font-size:0.85rem;">No recent activities found.</p>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Recent Backups -->
                <div class="activity-card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <h3><i class="fas fa-archive" style="color:var(--primary);margin-right:6px;"></i> Recent Backups</h3>
                        <a href="backups.php">View All →</a>
                    </div>
                    <?php if (count($recent_backups) > 0): ?>
                        <?php foreach (array_slice($recent_backups, 0, 4) as $backup): ?>
                            <div class="activity-item">
                                <div class="activity-icon backup">
                                    <i class="fas fa-file-archive"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="title text-truncate"><?php echo htmlspecialchars($backup['backup_type'] ?? 'Backup'); ?></div>
                                    <div class="desc">
                                        <?php echo number_format(($backup['file_size'] ?? 0) / 1024, 1); ?> KB
                                        <span style="color:var(--gray-400);">·</span>
                                        <?php echo htmlspecialchars($backup['status'] ?? 'pending'); ?>
                                    </div>
                                    <div class="time">
                                        <?php 
                                            $timestamp = strtotime($backup['created_at'] ?? 'now');
                                            echo date('M j, Y', $timestamp);
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:var(--gray-500);padding:10px 0;text-align:center;font-size:0.85rem;">No backups found.</p>
                    <?php endif; ?>
                </div>

                <!-- Subscription Stats -->
                <div class="activity-card">
                    <div class="card-header">
                        <h3><i class="fas fa-credit-card" style="color:var(--primary);margin-right:6px;"></i> Subscriptions</h3>
                    </div>
                    <div class="subscription-stats">
                        <?php 
                            $planColors = [
                                'enterprise' => 'enterprise',
                                'premium' => 'premium',
                                'standard' => 'standard',
                                'basic' => 'basic',
                                'free' => 'free'
                            ];
                            foreach ($subscription_stats as $sub): 
                        ?>
                            <span class="sub-item">
                                <span class="dot <?php echo $planColors[$sub['plan']] ?? 'free'; ?>"></span>
                                <?php echo ucfirst($sub['plan']); ?>: <?php echo $sub['count']; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:8px;display:flex;gap:8px;font-size:0.7rem;color:var(--gray-500);flex-wrap:wrap;">
                        <span><i class="fas fa-circle" style="color:var(--secondary);font-size:7px;"></i> Trial: <?php echo $stats['tenants']['trial']; ?></span>
                        <span><i class="fas fa-circle" style="color:var(--danger);font-size:7px;"></i> Expired: <?php echo $stats['tenants']['expired']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="margin-top:16px;">
            <div class="activity-card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt" style="color:var(--primary);margin-right:6px;"></i> Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    <a href="tenants-create.php" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i>
                        Create Tenant
                    </a>
                    <a href="subscriptions.php" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i>
                        Add Subscription
                    </a>
                    <a href="inec-data.php" class="quick-action-btn">
                        <i class="fas fa-upload"></i>
                        Upload INEC Data
                    </a>
                    <a href="audit-logs.php" class="quick-action-btn">
                        <i class="fas fa-clipboard-list"></i>
                        View Audit Logs
                    </a>
                    <a href="backups.php" class="quick-action-btn">
                        <i class="fas fa-database"></i>
                        Create Backup
                    </a>
                    <a href="users-create.php" class="quick-action-btn">
                        <i class="fas fa-user-plus"></i>
                        Add User
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    const preloader = document.getElementById('preloader');
    preloader.classList.add('hidden');
    setTimeout(() => {
        preloader.style.display = 'none';
    }, 600);
});

// ============================================================
// SIDEBAR TOGGLE (mobile)
// ============================================================
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const dashboardHeader = document.getElementById('dashboardHeader');

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

sidebarToggle.addEventListener('click', toggleSidebar);
sidebarOverlay.addEventListener('click', toggleSidebar);

window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
        dashboardHeader.style.left = '260px';
    } else if (!sidebar.classList.contains('open')) {
        dashboardHeader.style.left = '0';
    }
});

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const dropdownId = this.dataset.dropdown;
        const dropdown = document.getElementById(dropdownId);
        const chevron = this.querySelector('.chevron');
        
        if (dropdown) {
            dropdown.classList.toggle('open');
            if (chevron) chevron.classList.toggle('open');
        }
    });
});

// ============================================================
// PROFILE DROPDOWN
// ============================================================
const profileBtn = document.getElementById('profileBtn');
const profileMenu = document.getElementById('profileMenu');

profileBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    profileMenu.classList.toggle('active');
});

document.addEventListener('click', function(e) {
    if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
        profileMenu.classList.remove('active');
    }
});

// ============================================================
// SEARCH - Live Database Search
// ============================================================
const searchInput = document.getElementById('searchInput');
const searchResults = document.getElementById('searchResults');
let searchTimeout;

searchInput.addEventListener('input', function() {
    const query = this.value.trim();
    
    clearTimeout(searchTimeout);
    
    if (query.length < 2) {
        searchResults.classList.remove('active');
        return;
    }
    
    searchTimeout = setTimeout(() => {
        performSearch(query);
    }, 300);
});

searchInput.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        searchResults.classList.remove('active');
        this.blur();
    }
});

function performSearch(query) {
    fetch(`search.php?q=${encodeURIComponent(query)}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        renderSearchResults(data);
    })
    .catch(() => {
        // Silently fail - show nothing
    });
}

function renderSearchResults(data) {
    searchResults.innerHTML = '';
    
    if (!data || data.length === 0) {
        searchResults.innerHTML = `
            <div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;">
                <i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>
                No results found
            </div>
        `;
        searchResults.classList.add('active');
        return;
    }
    
    data.forEach(item => {
        const div = document.createElement('a');
        div.className = 'result-item';
        div.href = item.url || '#';
        
        const icon = item.icon || 'fa-file';
        const type = item.type || '';
        const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
        
        div.innerHTML = `
            <i class="fas ${icon}"></i>
            <span class="text-truncate">${item.label || item.name || ''}</span>
            <span class="result-type">${typeLabel}</span>
        `;
        searchResults.appendChild(div);
    });
    
    searchResults.classList.add('active');
}

// Close search results on click outside
document.addEventListener('click', function(e) {
    const searchWrapper = document.querySelector('.search-wrapper');
    if (!searchWrapper.contains(e.target)) {
        searchResults.classList.remove('active');
    }
});

// ============================================================
// CHARTS
// ============================================================
const months = <?php 
    $months = array_column($monthly_revenue, 'month');
    echo json_encode($months ?: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']);
?>;
const revenueData = <?php 
    $revenue = array_column($monthly_revenue, 'revenue');
    echo json_encode($revenue ?: [0, 0, 0, 0, 0, 0]);
?>;
const tenantGrowth = <?php 
    $growth = array_column($tenant_growth, 'count');
    echo json_encode($growth ?: [0, 0, 0, 0, 0, 0]);
?>;
const userGrowth = <?php 
    $ugrowth = array_column($user_growth, 'count');
    echo json_encode($ugrowth ?: [0, 0, 0, 0, 0, 0]);
?>;

// Revenue Chart
const ctx1 = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: months,
        datasets: [{
            label: 'Revenue (₦)',
            data: revenueData,
            borderColor: '#0F4C81',
            backgroundColor: 'rgba(15, 76, 129, 0.08)',
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: '#0F4C81',
            borderWidth: 2
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
                ticks: {
                    callback: function(value) {
                        if (value >= 1000) return '₦' + (value / 1000) + 'k';
                        return '₦' + value;
                    },
                    font: { size: 10 }
                }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 10 } }
            }
        }
    }
});

// Growth Chart
const ctx2 = document.getElementById('growthChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: months,
        datasets: [
            {
                label: 'Tenants',
                data: tenantGrowth,
                backgroundColor: 'rgba(15, 76, 129, 0.7)',
                borderColor: '#0F4C81',
                borderWidth: 1,
                borderRadius: 3
            },
            {
                label: 'Users',
                data: userGrowth,
                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                borderColor: '#10B981',
                borderWidth: 1,
                borderRadius: 3
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                    boxWidth: 6,
                    padding: 12,
                    font: { size: 10, weight: '500' }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: { 
                    stepSize: 1,
                    font: { size: 10 }
                }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 10 } }
            }
        }
    }
});
</script>
</body>
</html>