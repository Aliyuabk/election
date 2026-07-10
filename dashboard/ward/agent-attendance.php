<?php
// ============================================================
// WARD COORDINATOR - AGENT ATTENDANCE
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

// Get date filter
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$pu_filter = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;

// Get polling units for filter
$polling_units = [];
try {
    $stmt = $db->prepare("SELECT id, name, code FROM polling_units WHERE ward_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

// Fetch attendance data
$attendance_data = [];
$summary = [
    'total_agents' => 0,
    'checked_in' => 0,
    'checked_out' => 0,
    'no_show' => 0,
    'on_time' => 0,
    'late' => 0,
    'by_pu' => []
];

try {
    $sql = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            pu.id as pu_id,
            pu.name as pu_name,
            pu.code as pu_code,
            ac.id as checkin_id,
            ac.checkin_type,
            ac.created_at as checkin_time,
            ac.gps_lat,
            ac.gps_lng,
            ac.photo_url,
            ac.device_id,
            ac.device_battery,
            ac.network_type,
            CASE 
                WHEN ac.id IS NOT NULL AND ac.checkin_type = 'arrival' THEN 'checked_in'
                WHEN ac.id IS NOT NULL AND ac.checkin_type = 'departure' THEN 'checked_out'
                ELSE 'no_show'
            END as attendance_status,
            CASE 
                WHEN ac.id IS NOT NULL AND ac.checkin_type = 'arrival' AND TIME(ac.created_at) <= '08:00:00' THEN 'on_time'
                WHEN ac.id IS NOT NULL AND ac.checkin_type = 'arrival' AND TIME(ac.created_at) > '08:00:00' THEN 'late'
                ELSE NULL
            END as punctuality
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN agent_checkins ac ON ac.agent_id = u.id AND DATE(ac.created_at) = ? AND ac.checkin_type IN ('arrival', 'departure')
        WHERE u.tenant_id = ?
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND r.level = 'pu_agent'
        AND u.status = 'active'
    ";
    $params = [$date, $tenant_id, $ward_id];
    
    if ($pu_filter > 0) {
        $sql .= " AND u.pu_id = ?";
        $params[] = $pu_filter;
    }
    
    $sql .= " ORDER BY pu.name ASC, u.first_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($attendance_data as $agent) {
        $summary['total_agents']++;
        if ($agent['attendance_status'] === 'checked_in') {
            $summary['checked_in']++;
            if ($agent['punctuality'] === 'on_time') {
                $summary['on_time']++;
            } elseif ($agent['punctuality'] === 'late') {
                $summary['late']++;
            }
        } elseif ($agent['attendance_status'] === 'checked_out') {
            $summary['checked_out']++;
        } else {
            $summary['no_show']++;
        }
        
        if ($agent['pu_id']) {
            $pu_key = $agent['pu_id'];
            if (!isset($summary['by_pu'][$pu_key])) {
                $summary['by_pu'][$pu_key] = [
                    'name' => $agent['pu_name'],
                    'total' => 0,
                    'checked_in' => 0,
                    'no_show' => 0
                ];
            }
            $summary['by_pu'][$pu_key]['total']++;
            if ($agent['attendance_status'] === 'checked_in' || $agent['attendance_status'] === 'checked_out') {
                $summary['by_pu'][$pu_key]['checked_in']++;
            } else {
                $summary['by_pu'][$pu_key]['no_show']++;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching attendance: " . $e->getMessage());
}

$page_title = 'Agent Attendance';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.attendance-container {
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

.filter-bar input[type="date"],
.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 150px;
}

.filter-bar input[type="date"]:focus,
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

.filter-bar .btn-today {
    padding: 8px 16px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.8rem;
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.filter-bar .btn-today:hover {
    background: var(--gray-200);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.summary-card {
    background: white;
    border-radius: 10px;
    padding: 12px 14px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.summary-card .number {
    font-size: 1.2rem;
    font-weight: 700;
}

.summary-card .number.success { color: #10B981; }
.summary-card .number.warning { color: #F59E0B; }
.summary-card .number.danger { color: #EF4444; }
.summary-card .number.primary { color: #3B82F6; }
.summary-card .number.purple { color: #8B5CF6; }

.summary-card .label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

.attendance-table-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.attendance-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.attendance-table th {
    background: var(--gray-50);
    padding: 8px 10px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.attendance-table td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.attendance-table tr:hover td {
    background: var(--gray-50);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.5rem;
    padding: 2px 8px;
    border-radius: 8px;
    font-weight: 600;
}

.status-badge .dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.checked_in { background: #ECFDF5; color: #065F46; }
.status-badge.checked_in .dot { background: #10B981; }
.status-badge.checked_out { background: #EFF6FF; color: #1E40AF; }
.status-badge.checked_out .dot { background: #3B82F6; }
.status-badge.no_show { background: #FEF2F2; color: #991B1B; }
.status-badge.no_show .dot { background: #EF4444; }

.status-badge.on_time { background: #ECFDF5; color: #065F46; }
.status-badge.on_time .dot { background: #10B981; }
.status-badge.late { background: #FFFBEB; color: #92400E; }
.status-badge.late .dot { background: #F59E0B; }

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
    .filter-bar input[type="date"],
    .filter-bar select {
        width: 100%;
        min-width: unset;
    }
    .summary-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .attendance-table-container {
        overflow-x: auto;
    }
    .attendance-table {
        font-size: 0.7rem;
    }
    .attendance-table th,
    .attendance-table td {
        padding: 4px 6px;
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
        <div class="attendance-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-calendar-check"></i> Agent Attendance</h1>
                    <p class="subtitle">
                        <i class="fas fa-layer-group"></i> 
                        <?php echo htmlspecialchars($ward_name); ?> Ward - Agent Attendance Tracking
                    </p>
                </div>
                <div class="export-buttons">
                    <a href="export-pdf.php?type=attendance&date=<?php echo $date; ?>&pu_id=<?php echo $pu_filter; ?>" class="btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="export-excel.php?type=attendance&date=<?php echo $date; ?>&pu_id=<?php echo $pu_filter; ?>" class="btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>

            <!-- Filter -->
            <div class="filter-bar">
                <input type="date" id="dateFilter" value="<?php echo $date; ?>" />
                
                <select id="puFilter">
                    <option value="0">All PUs</option>
                    <?php foreach ($polling_units as $pu): ?>
                        <option value="<?php echo $pu['id']; ?>" <?php echo $pu_filter == $pu['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pu['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn-filter" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="agent-attendance.php?date=<?php echo date('Y-m-d'); ?>" class="btn-today">
                    <i class="fas fa-calendar-day"></i> Today
                </a>

                <span style="font-size:0.75rem;color:var(--gray-500);margin-left:auto;">
                    <?php echo date('F j, Y', strtotime($date)); ?>
                </span>
            </div>

            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_agents']); ?></div>
                    <div class="label">Total Agents</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['checked_in']); ?></div>
                    <div class="label">Checked In</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['checked_out']); ?></div>
                    <div class="label">Checked Out</div>
                </div>
                <div class="summary-card">
                    <div class="number danger"><?php echo number_format($summary['no_show']); ?></div>
                    <div class="label">No Show</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['on_time']); ?></div>
                    <div class="label">On Time</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['late']); ?></div>
                    <div class="label">Late</div>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="attendance-table-container">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>PU</th>
                            <th>Status</th>
                            <th>Punctuality</th>
                            <th>Check-in Time</th>
                            <th>Check-out Time</th>
                            <th>Device</th>
                            <th>Network</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_data as $agent): 
                            $status_class = $agent['attendance_status'];
                            $status_label = ucfirst(str_replace('_', ' ', $agent['attendance_status']));
                            $punctuality_class = $agent['punctuality'] ?? 'na';
                            $punctuality_label = $agent['punctuality'] ? ucfirst(str_replace('_', ' ', $agent['punctuality'])) : 'N/A';
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?></strong>
                                    <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?></div>
                                </td>
                                <td>
                                    <?php if ($agent['pu_id']): ?>
                                        <?php echo htmlspecialchars($agent['pu_name']); ?>
                                        <div style="font-size:0.5rem;color:var(--gray-400);"><?php echo htmlspecialchars($agent['pu_code']); ?></div>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <span class="dot"></span>
                                        <?php echo $status_label; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($agent['punctuality']): ?>
                                        <span class="status-badge <?php echo $punctuality_class; ?>">
                                            <span class="dot"></span>
                                            <?php echo $punctuality_label; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);font-size:0.6rem;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.7rem;color:var(--gray-600);">
                                    <?php if ($agent['checkin_id'] && $agent['checkin_type'] === 'arrival'): ?>
                                        <?php echo date('g:i A', strtotime($agent['checkin_time'])); ?>
                                        <?php if ($agent['gps_lat'] && $agent['gps_lng']): ?>
                                            <br /><span style="font-size:0.55rem;color:var(--gray-400);">
                                                <i class="fas fa-map-pin"></i> GPS
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.7rem;color:var(--gray-600);">
                                    <?php if ($agent['checkin_id'] && $agent['checkin_type'] === 'departure'): ?>
                                        <?php echo date('g:i A', strtotime($agent['checkin_time'])); ?>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.65rem;color:var(--gray-500);">
                                    <?php if ($agent['device_id']): ?>
                                        <i class="fas fa-mobile-alt"></i> <?php echo substr($agent['device_id'], 0, 8) . '...'; ?>
                                        <?php if ($agent['device_battery']): ?>
                                            <br /><span style="font-size:0.55rem;">🔋 <?php echo $agent['device_battery']; ?>%</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.65rem;color:var(--gray-500);">
                                    <?php if ($agent['network_type']): ?>
                                        <i class="fas fa-signal"></i> <?php echo strtoupper($agent['network_type']); ?>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($attendance_data)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-check"></i>
                                        <h4>No Attendance Data</h4>
                                        <p>No attendance data available for <?php echo date('F j, Y', strtotime($date)); ?>.</p>
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

<script>
function applyFilter() {
    var date = document.getElementById('dateFilter').value;
    var pu = document.getElementById('puFilter').value;
    var url = window.location.pathname;
    var params = [];
    if (date) params.push('date=' + date);
    if (pu && pu !== '0') params.push('pu_id=' + pu);
    if (params.length) url += '?' + params.join('&');
    window.location.href = url;
}

// Enter key for date filter
document.getElementById('dateFilter')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applyFilter();
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