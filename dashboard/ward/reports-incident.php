<?php
// ============================================================
// WARD COORDINATOR - INCIDENT REPORT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Ward Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');

if (empty($ward_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT ward_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            SessionManager::set('ward_id', $ward_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get ward name
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward: " . $e->getMessage());
}

// Get filters
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$pu_filter = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;

// Get polling units for filter
$polling_units = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM polling_units WHERE ward_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

// Build date filter
$date_filter = "";
$date_label = "";
switch ($period) {
    case 'today':
        $date_filter = "DATE(i.created_at) = CURDATE()";
        $date_label = "Today";
        break;
    case 'week':
        $date_filter = "i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_label = "Last 7 Days";
        break;
    case 'month':
        $date_filter = "i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $date_label = "Last 30 Days";
        break;
    case 'quarter':
        $date_filter = "i.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        $date_label = "Last 90 Days";
        break;
    default:
        $date_filter = "1=1";
        $date_label = "All Time";
}

// Fetch incident statistics
$stats = [
    'total' => 0,
    'by_type' => [],
    'by_severity' => ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0],
    'by_status' => ['reported' => 0, 'acknowledged' => 0, 'investigating' => 0, 'resolved' => 0, 'escalated' => 0, 'closed' => 0, 'false_alarm' => 0],
    'by_pu' => [],
    'panic_count' => 0,
    'avg_resolution_time' => 0
];

$incident_list = [];

try {
    $sql = "
        SELECT 
            i.*,
            u.first_name as reporter_first_name,
            u.last_name as reporter_last_name,
            pu.name as pu_name,
            pu.code as pu_code,
            assigned.first_name as assigned_first_name,
            assigned.last_name as assigned_last_name,
            resolved.first_name as resolved_first_name,
            resolved.last_name as resolved_last_name,
            TIMESTAMPDIFF(HOUR, i.created_at, i.resolved_at) as resolution_hours
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN users assigned ON i.assigned_to = assigned.id
        LEFT JOIN users resolved ON i.resolved_by = resolved.id
        WHERE i.tenant_id = ? AND i.ward_id = ? AND $date_filter
    ";
    
    $params = [$tenant_id, $ward_id];
    
    if ($pu_filter > 0) {
        $sql .= " AND i.pu_id = ?";
        $params[] = $pu_filter;
    }
    
    $sql .= " ORDER BY i.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $incident_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_resolution_hours = 0;
    $resolved_count = 0;
    
    foreach ($incident_list as $incident) {
        $stats['total']++;
        
        // By type
        $type = $incident['incident_type'];
        $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
        
        // By severity
        $severity = $incident['severity'];
        $stats['by_severity'][$severity] = ($stats['by_severity'][$severity] ?? 0) + 1;
        
        // By status
        $status = $incident['status'];
        $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + 1;
        
        // By PU
        if ($incident['pu_id']) {
            $pu_key = $incident['pu_id'];
            if (!isset($stats['by_pu'][$pu_key])) {
                $stats['by_pu'][$pu_key] = [
                    'name' => $incident['pu_name'] ?? 'Unknown',
                    'count' => 0
                ];
            }
            $stats['by_pu'][$pu_key]['count']++;
        }
        
        // Panic
        if ($incident['is_panic'] == 1) {
            $stats['panic_count']++;
        }
        
        // Resolution time
        if ($incident['status'] === 'resolved' || $incident['status'] === 'closed') {
            if ($incident['resolution_hours'] !== null) {
                $total_resolution_hours += $incident['resolution_hours'];
                $resolved_count++;
            }
        }
    }
    
    $stats['avg_resolution_time'] = $resolved_count > 0 ? round($total_resolution_hours / $resolved_count, 1) : 0;
    
    // Sort by_pu by count descending
    uasort($stats['by_pu'], function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
} catch (Exception $e) {
    error_log("Error fetching incident report: " . $e->getMessage());
}

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

$severity_labels = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'critical' => 'Critical'
];

$status_labels = [
    'reported' => 'Reported',
    'acknowledged' => 'Acknowledged',
    'investigating' => 'Investigating',
    'resolved' => 'Resolved',
    'escalated' => 'Escalated',
    'closed' => 'Closed',
    'false_alarm' => 'False Alarm'
];

$page_title = 'Incident Report';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.report-container {
    max-width: 1000px;
    margin: 0 auto;
}

.filter-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    align-items: center;
    background: white;
    padding: 12px 18px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 150px;
}

.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-bar .btn-filter {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.filter-bar .btn-filter:hover {
    background: var(--primary-dark);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.summary-card {
    background: white;
    border-radius: var(--radius);
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.summary-card .number {
    font-size: 1.1rem;
    font-weight: 700;
}

.summary-card .number.primary { color: #3B82F6; }
.summary-card .number.danger { color: #EF4444; }
.summary-card .number.success { color: #10B981; }
.summary-card .number.warning { color: #F59E0B; }
.summary-card .number.purple { color: #8B5CF6; }
.summary-card .number.orange { color: #F97316; }

.summary-card .label {
    font-size: 0.55rem;
    color: var(--gray-500);
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
    padding: 14px 16px;
    border: 1px solid var(--gray-200);
}

.chart-card .chart-title {
    font-size: 0.75rem;
    font-weight: 600;
    margin: 0 0 8px;
    color: var(--gray-700);
}

.chart-card .chart-title i {
    color: var(--primary);
    margin-right: 6px;
}

.chart-card canvas {
    max-height: 180px;
    width: 100%;
}

.incident-table-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.incident-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.incident-table th {
    background: var(--gray-50);
    padding: 6px 8px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.55rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.incident-table td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.incident-table tr:hover td {
    background: var(--gray-50);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.5rem;
    padding: 2px 6px;
    border-radius: 6px;
    font-weight: 600;
}

.status-badge .dot {
    width: 3px;
    height: 3px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.reported { background: #FFFBEB; color: #92400E; }
.status-badge.reported .dot { background: #F59E0B; }
.status-badge.acknowledged { background: #EFF6FF; color: #1E40AF; }
.status-badge.acknowledged .dot { background: #3B82F6; }
.status-badge.investigating { background: #F5F3FF; color: #5B21B6; }
.status-badge.investigating .dot { background: #8B5CF6; }
.status-badge.resolved { background: #ECFDF5; color: #065F46; }
.status-badge.resolved .dot { background: #10B981; }
.status-badge.escalated { background: #FEF2F2; color: #991B1B; }
.status-badge.escalated .dot { background: #EF4444; }
.status-badge.closed { background: #F3F4F6; color: #6B7280; }
.status-badge.closed .dot { background: #9CA3AF; }
.status-badge.false_alarm { background: #F3F4F6; color: #6B7280; }
.status-badge.false_alarm .dot { background: #9CA3AF; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h4 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    font-size: 0.85rem;
    margin-top: 4px;
}

.export-buttons {
    display: flex;
    gap: 8px;
}

.export-buttons a {
    padding: 6px 16px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.export-buttons .btn-pdf {
    background: #EF4444;
    color: white;
}

.export-buttons .btn-excel {
    background: #10B981;
    color: white;
}

@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar select {
        width: 100%;
        min-width: unset;
    }
    .summary-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .charts-grid {
        grid-template-columns: 1fr;
    }
    .incident-table-container {
        overflow-x: auto;
    }
    .incident-table {
        font-size: 0.7rem;
    }
    .export-buttons {
        flex-direction: column;
        width: 100%;
    }
    .export-buttons a {
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="report-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-exclamation-triangle"></i> Incident Report</h1>
                    <p class="subtitle">
                        <i class="fas fa-layer-group"></i> 
                        <?php echo htmlspecialchars($ward_name); ?> Ward - Incident Analysis Report
                    </p>
                </div>
                <div class="export-buttons">
                    <a href="export-pdf.php?type=incident_report&period=<?php echo $period; ?>&pu_id=<?php echo $pu_filter; ?>" class="btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="export-excel.php?type=incident_report&period=<?php echo $period; ?>&pu_id=<?php echo $pu_filter; ?>" class="btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>

            <!-- Filter -->
            <div class="filter-bar">
                <select id="puFilter" onchange="applyFilter()">
                    <option value="0">All PUs</option>
                    <?php foreach ($polling_units as $pu): ?>
                        <option value="<?php echo $pu['id']; ?>" <?php echo $pu_filter == $pu['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pu['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select id="periodFilter" onchange="applyFilter()">
                    <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Last 90 Days</option>
                    <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>All Time</option>
                </select>

                <button class="btn-filter" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Apply
                </button>

                <span style="font-size:0.75rem;color:var(--gray-500);margin-left:auto;">
                    <?php echo $date_label; ?> - <?php echo $stats['total']; ?> incidents
                </span>
            </div>

            <!-- Summary Stats -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($stats['total']); ?></div>
                    <div class="label">Total Incidents</div>
                </div>
                <div class="summary-card">
                    <div class="number danger"><?php echo number_format($stats['panic_count']); ?></div>
                    <div class="label">Panic Alerts</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($stats['by_status']['resolved'] ?? 0); ?></div>
                    <div class="label">Resolved</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format(($stats['by_status']['reported'] ?? 0) + ($stats['by_status']['acknowledged'] ?? 0) + ($stats['by_status']['investigating'] ?? 0)); ?></div>
                    <div class="label">Active</div>
                </div>
                <div class="summary-card">
                    <div class="number purple"><?php echo number_format($stats['by_severity']['critical'] ?? 0); ?></div>
                    <div class="label">Critical</div>
                </div>
                <div class="summary-card">
                    <div class="number orange"><?php echo number_format($stats['avg_resolution_time']); ?>h</div>
                    <div class="label">Avg Resolution</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <!-- Incident Types Chart -->
                <div class="chart-card">
                    <div class="chart-title"><i class="fas fa-chart-pie"></i> By Type</div>
                    <canvas id="typeChart"></canvas>
                </div>

                <!-- Incident Status Chart -->
                <div class="chart-card">
                    <div class="chart-title"><i class="fas fa-chart-bar"></i> By Status</div>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- PU Breakdown -->
            <?php if (!empty($stats['by_pu'])): ?>
                <div class="chart-card" style="margin-bottom:20px;">
                    <div class="chart-title"><i class="fas fa-map-marker-alt"></i> Incidents by Polling Unit</div>
                    <canvas id="puChart"></canvas>
                </div>
            <?php endif; ?>

            <!-- Incident List -->
            <div class="incident-table-container">
                <table class="incident-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Title</th>
                            <th>PU</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Reported</th>
                            <th>Resolution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($incident_list, 0, 50) as $incident): ?>
                            <tr>
                                <td>#<?php echo $incident['id']; ?></td>
                                <td><?php echo $incident_types[$incident['incident_type']] ?? ucfirst($incident['incident_type']); ?></td>
                                <td><?php echo htmlspecialchars(substr($incident['title'], 0, 25)) . (strlen($incident['title']) > 25 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars($incident['pu_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="severity-badge <?php echo $incident['severity']; ?>" style="font-size:0.5rem;padding:1px 6px;border-radius:6px;font-weight:600;">
                                        <?php echo ucfirst($incident['severity']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $incident['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst(str_replace('_', ' ', $incident['status'])); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.65rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y', strtotime($incident['created_at'])); ?>
                                </td>
                                <td style="font-size:0.65rem;color:var(--gray-500);">
                                    <?php if ($incident['resolved_at']): ?>
                                        <?php echo date('M j, Y', strtotime($incident['resolved_at'])); ?>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($incident_list)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <h4>No Incidents Found</h4>
                                        <p>No incidents found for the selected period.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function applyFilter() {
    var pu = document.getElementById('puFilter').value;
    var period = document.getElementById('periodFilter').value;
    
    var url = window.location.pathname;
    var params = [];
    if (pu && pu !== '0') params.push('pu_id=' + pu);
    if (period) params.push('period=' + period);
    if (params.length) url += '?' + params.join('&');
    window.location.href = url;
}

// Charts
document.addEventListener('DOMContentLoaded', function() {
    // Type Chart
    var typeCtx = document.getElementById('typeChart');
    if (typeCtx) {
        var typeLabels = <?php echo json_encode(array_values(array_intersect_key($incident_types, array_flip(array_keys($stats['by_type']))))); ?>;
        var typeData = <?php echo json_encode(array_values($stats['by_type'])); ?>;
        var typeColors = ['#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#10B981', '#F97316', '#EC4899', '#6366F1', '#14B8A6'];
        
        if (typeLabels.length === 0) {
            typeLabels = ['No Data'];
            typeData = [1];
        }
        
        new Chart(typeCtx, {
            type: 'pie',
            data: {
                labels: typeLabels,
                datasets: [{
                    data: typeData,
                    backgroundColor: typeColors.slice(0, typeData.length),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 9 } }
                    }
                }
            }
        });
    }

    // Status Chart
    var statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        var statusLabels = <?php echo json_encode(array_values(array_intersect_key($status_labels, array_flip(array_keys($stats['by_status']))))); ?>;
        var statusData = <?php echo json_encode(array_values($stats['by_status'])); ?>;
        var statusColors = ['#F59E0B', '#3B82F6', '#8B5CF6', '#10B981', '#EF4444', '#6B7280', '#6B7280'];
        
        if (statusLabels.length === 0) {
            statusLabels = ['No Data'];
            statusData = [0];
        }
        
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: statusLabels,
                datasets: [{
                    label: 'Incidents',
                    data: statusData,
                    backgroundColor: statusColors.slice(0, statusData.length),
                    borderColor: statusColors.slice(0, statusData.length),
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 8 } }
                    },
                    x: {
                        ticks: { font: { size: 7 } }
                    }
                }
            }
        });
    }

    // PU Chart
    var puCtx = document.getElementById('puChart');
    if (puCtx) {
        var puData = <?php echo json_encode($stats['by_pu']); ?>;
        var puLabels = Object.values(puData).map(function(item) { return item.name; });
        var puValues = Object.values(puData).map(function(item) { return item.count; });
        var puColors = ['#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#10B981', '#F97316', '#EC4899', '#6366F1', '#14B8A6'];
        
        if (puLabels.length) {
            new Chart(puCtx, {
                type: 'bar',
                data: {
                    labels: puLabels,
                    datasets: [{
                        label: 'Incidents',
                        data: puValues,
                        backgroundColor: puColors.slice(0, puLabels.length),
                        borderColor: puColors.slice(0, puLabels.length),
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { font: { size: 8 } }
                        },
                        x: {
                            ticks: { font: { size: 7 } }
                        }
                    }
                }
            });
        }
    }
});

// Same sidebar scripts as index.php
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