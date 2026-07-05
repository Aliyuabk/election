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

// Only Federal Constituency coordinator can access
if (SessionManager::get('role_level') !== 'federal_constituency') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$constituency_id = SessionManager::get('federal_constituency_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// FETCH CONSTITUENCY AND STATE NAMES
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
    $constituency_name = 'Federal Constituency';
    $state_name = 'State';
}

// ============================================================
// FETCH DASHBOARD STATISTICS
// ============================================================

// Get LGAs in this federal constituency
$lga_ids = [];
try {
    $stmt = $db->prepare("SELECT lgas_json FROM federal_constituencies WHERE id = ?");
    $stmt->execute([$constituency_id]);
    $lgas_json = $stmt->fetchColumn();
    if ($lgas_json) {
        $lga_ids = json_decode($lgas_json, true) ?: [];
    }
} catch (Exception $e) {
    $lga_ids = [];
}

$lga_list = implode(',', array_map('intval', $lga_ids));

// LGA Statistics
$lga_stats = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT l.id) as total_lgas,
                COUNT(DISTINCT w.id) as total_wards,
                COUNT(DISTINCT pu.id) as total_pus
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id
            WHERE l.id IN ($lga_list) AND l.is_active = 1
        ");
        $stmt->execute();
        $lga_stats = $stmt->fetch();
    } else {
        $lga_stats = ['total_lgas' => 0, 'total_wards' => 0, 'total_pus' => 0];
    }
} catch (Exception $e) {
    $lga_stats = ['total_lgas' => 0, 'total_wards' => 0, 'total_pus' => 0];
}

// Election Statistics
$election_stats = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM elections 
            WHERE tenant_id = ? AND deleted_at IS NULL
            AND JSON_OVERLAPS(lgas_json, JSON_ARRAY(?))
        ");
        $stmt->execute([$tenant_id, json_encode($lga_ids)]);
        $election_stats = $stmt->fetch();
    } else {
        $election_stats = ['total' => 0, 'active' => 0, 'upcoming' => 0, 'completed' => 0];
    }
} catch (Exception $e) {
    $election_stats = ['total' => 0, 'active' => 0, 'upcoming' => 0, 'completed' => 0];
}

// Result Statistics
$result_stats = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_results,
                SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN r.status = 'flagged' THEN 1 ELSE 0 END) as flagged
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            WHERE r.tenant_id = ? AND w.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $result_stats = $stmt->fetch();
    } else {
        $result_stats = ['total_results' => 0, 'verified' => 0, 'pending' => 0, 'flagged' => 0];
    }
} catch (Exception $e) {
    $result_stats = ['total_results' => 0, 'verified' => 0, 'pending' => 0, 'flagged' => 0];
}

// Incident Statistics
$incident_stats = [];
try {
    if (!empty($lga_ids)) {
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
    $incident_stats = ['total' => 0, 'reported' => 0, 'investigating' => 0, 'resolved' => 0];
}

// Ward Performance
$ward_performance = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT 
                w.name as ward_name,
                l.name as lga_name,
                COUNT(r.id) as verified_count
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            WHERE r.tenant_id = ? AND l.id IN ($lga_list) AND r.status = 'verified'
            GROUP BY w.id
            ORDER BY verified_count DESC
            LIMIT 10
        ");
        $stmt->execute([$tenant_id]);
        $ward_performance = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $ward_performance = [];
}

// Recent Activities
$recent_activities = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT a.*, u.full_name as user_name
            FROM activity_logs a
            JOIN users u ON a.user_id = u.id
            WHERE a.tenant_id = ? 
            AND a.entity_type IN ('lga', 'ward', 'pu')
            AND a.entity_id IN (
                SELECT id FROM lgas WHERE id IN ($lga_list)
                UNION SELECT id FROM wards WHERE lga_id IN ($lga_list)
                UNION SELECT id FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id IN ($lga_list))
            )
            ORDER BY a.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$tenant_id]);
        $recent_activities = $stmt->fetchAll();
    }
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
            <p>Federal Constituency Coordinator - <?php echo htmlspecialchars($constituency_name); ?></p>
            <div class="breadcrumb">
                <i class="fas fa-flag"></i>
                <span><?php echo htmlspecialchars($state_name); ?></span>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span><?php echo htmlspecialchars($constituency_name); ?></span>
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
                <div class="stat-icon green"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($lga_stats['total_pus'] ?? 0); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change"><i class="fas fa-users"></i> <?php echo number_format($lga_stats['total_lgas'] ?? 0 * 8); ?>+ agents</div>
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
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($incident_stats['total'] ?? 0); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $incident_stats['reported'] ?? 0; ?> reported</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-building"></i></div>
                <div class="stat-number"><?php echo number_format($lga_stats['total_lgas'] ?? 0); ?></div>
                <div class="stat-label">Active LGAs</div>
                <div class="stat-change"><i class="fas fa-check-circle"></i> Full coverage</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line" style="color:var(--primary);margin-right:6px;"></i> Result Progress</h3>
                    <span class="period"><?php echo htmlspecialchars($constituency_name); ?></span>
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
                    <canvas id="wardPerformanceChart"></canvas>
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
                        <a href="verify-results.php" class="quick-action-btn">
                            <i class="fas fa-check-double"></i> Verify Results
                        </a>
                        <a href="reports.php" class="quick-action-btn">
                            <i class="fas fa-file-alt"></i> Generate Report
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

// Ward Performance Chart
const ctx2 = document.getElementById('wardPerformanceChart').getContext('2d');
const wardData = <?php 
    $wards = array_map(function($item) { 
        return $item['ward_name'] . ' (' . $item['lga_name'] . ')'; 
    }, $ward_performance);
    $counts = array_column($ward_performance, 'verified_count');
    echo json_encode(['labels' => $wards, 'data' => $counts]);
?>;

new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: wardData.labels || ['No Data'],
        datasets: [{
            label: 'Verified Results',
            data: wardData.data || [0],
            backgroundColor: 'rgba(5, 150, 105, 0.7)',
            borderColor: '#059669',
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

// ============================================================
// SIDEBAR TOGGLE, DROPDOWNS, PROFILE, SEARCH
// ============================================================
// (Same as previous dashboards - included for completeness)


// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE
// ============================================================
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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// SEARCH
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        searchTimeout = setTimeout(function() {
            fetch('search.php?q=' + encodeURIComponent(query))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(function(item) {
                                var div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = '<i class="fas ' + (item.icon || 'fa-file') + '"></i><span class="text-truncate">' + (item.label || item.name || '') + '</span><span class="result-type">' + ((item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)) + '</span>';
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = '<div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;"><i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>No results found</div>';
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(function() {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
} 
</script>
</body>
</html>