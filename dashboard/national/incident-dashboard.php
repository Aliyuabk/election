<?php
// ============================================================
// NATIONAL COORDINATOR - INCIDENT DASHBOARD
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

// Only national coordinator can access
if (SessionManager::get('role_level') !== 'national') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// FETCH INCIDENT STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'critical' => 0,
    'high' => 0,
    'medium' => 0,
    'low' => 0,
    'reported' => 0,
    'investigating' => 0,
    'resolved' => 0,
    'panic' => 0,
    'by_type' => [],
    'by_state' => [],
    'trend' => []
];

try {
    // Overall stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low,
            SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported,
            SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN is_panic = 1 THEN 1 ELSE 0 END) as panic
        FROM incidents
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['total'] = $result['total'] ?? 0;
    $stats['critical'] = $result['critical'] ?? 0;
    $stats['high'] = $result['high'] ?? 0;
    $stats['medium'] = $result['medium'] ?? 0;
    $stats['low'] = $result['low'] ?? 0;
    $stats['reported'] = $result['reported'] ?? 0;
    $stats['investigating'] = $result['investigating'] ?? 0;
    $stats['resolved'] = $result['resolved'] ?? 0;
    $stats['panic'] = $result['panic'] ?? 0;
    
    // By type
    $stmt = $db->prepare("
        SELECT 
            incident_type,
            COUNT(*) as count,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count
        FROM incidents
        WHERE tenant_id = ?
        GROUP BY incident_type
        ORDER BY count DESC
    ");
    $stmt->execute([$tenant_id]);
    $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // By state
    $stmt = $db->prepare("
        SELECT 
            s.name as state_name,
            COUNT(i.id) as count,
            SUM(CASE WHEN i.severity = 'critical' THEN 1 ELSE 0 END) as critical_count
        FROM incidents i
        JOIN states s ON i.state_id = s.id
        WHERE i.tenant_id = ?
        GROUP BY s.id
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id]);
    $stats['by_state'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Trend (last 7 days)
    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count
        FROM incidents
        WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$tenant_id]);
    $stats['trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Incident Dashboard Error: " . $e->getMessage());
}

// Incident type labels
$incident_types = [
    'violence' => 'Violence',
    'intimidation' => 'Intimidation',
    'ballot_stuffing' => 'Ballot Stuffing',
    'vote_buying' => 'Vote Buying',
    'voter_suppression' => 'Voter Suppression',
    'material_shortage' => 'Material Shortage',
    'delay' => 'Delay',
    'technical_issue' => 'Technical Issue',
    'other' => 'Other',
    'panic_button' => 'Panic Button'
];

$severity_colors = [
    'critical' => '#EF4444',
    'high' => '#F59E0B',
    'medium' => '#3B82F6',
    'low' => '#10B981'
];

$type_colors = [
    'violence' => '#EF4444',
    'intimidation' => '#F59E0B',
    'ballot_stuffing' => '#DC2626',
    'vote_buying' => '#D97706',
    'voter_suppression' => '#6B7280',
    'material_shortage' => '#3B82F6',
    'delay' => '#F59E0B',
    'technical_issue' => '#8B5CF6',
    'other' => '#6B7280',
    'panic_button' => '#EF4444'
];

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Incident Dashboard';
$page_subtitle = 'Analytics and Insights';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="../national/index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="incidents.php" style="text-decoration:none;color:var(--gray-500);">Incidents</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Dashboard</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-chart-pie" style="color:var(--primary);"></i>
                        Incident Dashboard
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-chart-line"></i> 
                        Real-time incident analytics and insights
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="incident-create.php" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-plus"></i> Report Incident
                    </a>
                    <a href="incidents.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-list"></i> All Incidents
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Incidents</div>
                <div class="stat-change">All recorded</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-skull-crossbones"></i></div>
                <div class="stat-number"><?php echo number_format($stats['critical']); ?></div>
                <div class="stat-label">Critical</div>
                <div class="stat-change down"><i class="fas fa-exclamation-circle"></i> Immediate action</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['reported'] + $stats['investigating']); ?></div>
                <div class="stat-label">Open</div>
                <div class="stat-change down"><i class="fas fa-hourglass-half"></i> In progress</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['resolved']); ?></div>
                <div class="stat-label">Resolved</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Completed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-bell"></i></div>
                <div class="stat-number"><?php echo number_format($stats['panic']); ?></div>
                <div class="stat-label">Panic Alerts</div>
                <div class="stat-change"><i class="fas fa-exclamation"></i> Emergency</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-percent"></i></div>
                <div class="stat-number">
                    <?php echo $stats['total'] > 0 ? round(($stats['resolved'] / $stats['total']) * 100) : 0; ?>%
                </div>
                <div class="stat-label">Resolution Rate</div>
                <div class="stat-change <?php echo $stats['total'] > 0 && ($stats['resolved'] / $stats['total']) > 0.7 ? 'up' : 'down'; ?>">
                    <?php echo $stats['total'] > 0 && ($stats['resolved'] / $stats['total']) > 0.7 ? 'Good' : 'Needs improvement'; ?>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
            <!-- Severity Distribution -->
            <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                        <i class="fas fa-chart-pie" style="color:var(--primary);margin-right:6px;"></i>
                        Severity Distribution
                    </h4>
                </div>
                <div style="height:200px;">
                    <canvas id="severityChart"></canvas>
                </div>
            </div>

            <!-- Incident Types -->
            <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                        <i class="fas fa-tags" style="color:var(--primary);margin-right:6px;"></i>
                        Incident Types
                    </h4>
                </div>
                <div style="height:200px;">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Trend Chart -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                    <i class="fas fa-chart-line" style="color:var(--primary);margin-right:6px;"></i>
                    Incident Trend (Last 7 Days)
                </h4>
            </div>
            <div style="height:250px;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <!-- Top States -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
            <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                        <i class="fas fa-flag" style="color:var(--primary);margin-right:6px;"></i>
                        Top States by Incidents
                    </h4>
                </div>
                <?php if (count($stats['by_state']) > 0): ?>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php 
                        $max_count = max(array_column($stats['by_state'], 'count')) ?: 1;
                        foreach ($stats['by_state'] as $state): 
                            $percentage = ($state['count'] / $max_count) * 100;
                        ?>
                            <div>
                                <div style="display:flex;justify-content:space-between;font-size:0.8rem;">
                                    <span style="font-weight:500;"><?php echo htmlspecialchars($state['state_name']); ?></span>
                                    <span style="font-weight:600;">
                                        <?php echo number_format($state['count']); ?>
                                        <?php if ($state['critical_count'] > 0): ?>
                                            <span style="color:#EF4444;font-size:0.7rem;">(<?php echo $state['critical_count']; ?> critical)</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div style="width:100%;height:6px;background:var(--gray-100);border-radius:4px;overflow:hidden;margin-top:2px;">
                                    <div style="width:<?php echo $percentage; ?>%;height:100%;background:linear-gradient(90deg, #3B82F6, #8B5CF6);border-radius:4px;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--gray-400);text-align:center;padding:20px 0;">No incidents by state</p>
                <?php endif; ?>
            </div>

            <!-- Resolution Status -->
            <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                        <i class="fas fa-check-double" style="color:var(--primary);margin-right:6px;"></i>
                        Resolution Status
                    </h4>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
                    <div style="text-align:center;padding:12px;background:var(--gray-50);border-radius:8px;">
                        <div style="font-size:1.5rem;font-weight:700;color:#EF4444;"><?php echo number_format($stats['reported']); ?></div>
                        <div style="font-size:0.65rem;color:var(--gray-500);">Reported</div>
                    </div>
                    <div style="text-align:center;padding:12px;background:var(--gray-50);border-radius:8px;">
                        <div style="font-size:1.5rem;font-weight:700;color:#F59E0B;"><?php echo number_format($stats['investigating']); ?></div>
                        <div style="font-size:0.65rem;color:var(--gray-500);">Investigating</div>
                    </div>
                    <div style="text-align:center;padding:12px;background:var(--gray-50);border-radius:8px;">
                        <div style="font-size:1.5rem;font-weight:700;color:#10B981;"><?php echo number_format($stats['resolved']); ?></div>
                        <div style="font-size:0.65rem;color:var(--gray-500);">Resolved</div>
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <div style="width:100%;height:10px;background:var(--gray-100);border-radius:8px;overflow:hidden;">
                        <div style="width:<?php echo $stats['total'] > 0 ? round(($stats['resolved'] / $stats['total']) * 100) : 0; ?>%;height:100%;background:linear-gradient(90deg, #10B981, #34D399);border-radius:8px;"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                        <span>0%</span>
                        <span>Resolved: <?php echo $stats['total'] > 0 ? round(($stats['resolved'] / $stats['total']) * 100) : 0; ?>%</span>
                        <span>100%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <a href="incidents.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-list" style="color:var(--primary);"></i>
                <span>View All Incidents</span>
            </a>
            <a href="incident-types.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-tags" style="color:var(--secondary);"></i>
                <span>Incident Types</span>
            </a>
            <a href="reports.php?type=incident" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-pdf" style="color:var(--danger);"></i>
                <span>Generate Report</span>
            </a>
            <a href="incident-create.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-plus-circle" style="color:var(--warning);"></i>
                <span>Report New Incident</span>
            </a>
        </div>
    </div>
</main>

<style>
.stat-icon.red { background: #FEF2F2; color: #EF4444; }
.stat-icon.yellow { background: #FFFBEB; color: #F59E0B; }
.stat-icon.green { background: #ECFDF5; color: #10B981; }
.stat-icon.purple { background: #F5F3FF; color: #8B5CF6; }
.stat-icon.blue { background: #EFF6FF; color: #3B82F6; }
.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    div[style*="grid-template-columns:1fr 1fr;gap:20px;"] { grid-template-columns: 1fr !important; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ============================================================
// SEVERITY CHART
// ============================================================
const severityCtx = document.getElementById('severityChart').getContext('2d');
new Chart(severityCtx, {
    type: 'doughnut',
    data: {
        labels: ['Critical', 'High', 'Medium', 'Low'],
        datasets: [{
            data: [
                <?php echo $stats['critical']; ?>,
                <?php echo $stats['high']; ?>,
                <?php echo $stats['medium']; ?>,
                <?php echo $stats['low']; ?>
            ],
            backgroundColor: ['#EF4444', '#F59E0B', '#3B82F6', '#10B981'],
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

// ============================================================
// TYPE CHART
// ============================================================
const typeCtx = document.getElementById('typeChart').getContext('2d');
const typeData = <?php 
    $type_labels = [];
    $type_counts = [];
    $type_colors = [];
    $type_colors_map = [
        'violence' => '#EF4444',
        'intimidation' => '#F59E0B',
        'ballot_stuffing' => '#DC2626',
        'vote_buying' => '#D97706',
        'voter_suppression' => '#6B7280',
        'material_shortage' => '#3B82F6',
        'delay' => '#F59E0B',
        'technical_issue' => '#8B5CF6',
        'other' => '#6B7280',
        'panic_button' => '#EF4444'
    ];
    $type_labels_map = [
        'violence' => 'Violence',
        'intimidation' => 'Intimidation',
        'ballot_stuffing' => 'Ballot Stuffing',
        'vote_buying' => 'Vote Buying',
        'voter_suppression' => 'Voter Suppression',
        'material_shortage' => 'Material Shortage',
        'delay' => 'Delay',
        'technical_issue' => 'Technical Issue',
        'other' => 'Other',
        'panic_button' => 'Panic Button'
    ];
    foreach ($stats['by_type'] as $type) {
        $type_labels[] = $type_labels_map[$type['incident_type']] ?? $type['incident_type'];
        $type_counts[] = $type['count'];
        $type_colors[] = $type_colors_map[$type['incident_type']] ?? '#6B7280';
    }
    echo json_encode(['labels' => $type_labels, 'data' => $type_counts, 'colors' => $type_colors]);
?>;

new Chart(typeCtx, {
    type: 'bar',
    data: {
        labels: typeData.labels || ['No Data'],
        datasets: [{
            label: 'Incidents',
            data: typeData.data || [0],
            backgroundColor: typeData.colors || ['#6B7280'],
            borderColor: typeData.colors || ['#6B7280'],
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

// ============================================================
// TREND CHART
// ============================================================
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendData = <?php 
    $dates = [];
    $counts = [];
    $critical_counts = [];
    foreach ($stats['trend'] as $day) {
        $dates[] = date('M j', strtotime($day['date']));
        $counts[] = $day['count'];
        $critical_counts[] = $day['critical_count'];
    }
    echo json_encode(['dates' => $dates, 'counts' => $counts, 'critical' => $critical_counts]);
?>;

new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: trendData.dates || ['No Data'],
        datasets: [
            {
                label: 'Total Incidents',
                data: trendData.counts || [0],
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#3B82F6'
            },
            {
                label: 'Critical Incidents',
                data: trendData.critical || [0],
                borderColor: '#EF4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#EF4444'
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
                    padding: 12,
                    font: { size: 11 }
                }
            }
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

// ============================================================
// SIDEBAR TOGGLE, DROPDOWNS, PROFILE, SEARCH
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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
</script>
</body>
</html>