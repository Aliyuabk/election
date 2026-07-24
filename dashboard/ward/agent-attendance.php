<?php
// ============================================================
// WARD COORDINATOR - AGENT ATTENDANCE
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

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$ward_id = SessionManager::get('ward_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
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

// ============================================================
// FETCH WARD NAME
// ============================================================
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
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// FETCH ATTENDANCE DATA
// ============================================================
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$agent_filter = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;

$attendance_data = [];
$agents = [];
$summary = [
    'total_agents' => 0,
    'checked_in_today' => 0,
    'checked_out_today' => 0,
    'total_checkins' => 0
];

try {
    // Get all agents in ward
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.user_code,
            pu.name as pu_name
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
        AND u.status = 'active'
        AND EXISTS (SELECT 1 FROM roles r WHERE r.id = u.role_id AND r.level = 'pu_agent')
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $summary['total_agents'] = count($agents);
    
    // Today's check-ins
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT agent_id) as checked_in,
            COUNT(DISTINCT CASE WHEN checkin_type = 'departure' THEN agent_id END) as checked_out
        FROM agent_checkins
        WHERE tenant_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$tenant_id]);
    $today = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['checked_in_today'] = (int)($today['checked_in'] ?? 0);
    $summary['checked_out_today'] = (int)($today['checked_out'] ?? 0);
    
    // Build conditions for attendance history
    $conditions = "ac.tenant_id = ? AND ac.ward_id = ?";
    $params = [$tenant_id, $ward_id];
    
    if (!empty($date_from)) {
        $conditions .= " AND DATE(ac.created_at) >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $conditions .= " AND DATE(ac.created_at) <= ?";
        $params[] = $date_to;
    }
    if ($agent_filter > 0) {
        $conditions .= " AND ac.agent_id = ?";
        $params[] = $agent_filter;
    }
    
    // Get attendance records
    $stmt = $db->prepare("
        SELECT 
            ac.*,
            u.full_name as agent_name,
            u.user_code as agent_code,
            pu.name as pu_name,
            pu.code as pu_code
        FROM agent_checkins ac
        JOIN users u ON ac.agent_id = u.id
        LEFT JOIN polling_units pu ON ac.pu_id = pu.id
        WHERE $conditions
        ORDER BY ac.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $summary['total_checkins'] = count($attendance_data);
    
} catch (Exception $e) {
    error_log("Error fetching attendance: " . $e->getMessage());
}

$page_title = 'Agent Attendance';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.attendance-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.attendance-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.attendance-header h2 i {
    color: var(--primary);
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-mini {
    background: white;
    padding: 12px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-mini .number {
    font-size: 1.3rem;
    font-weight: 700;
}
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .number.purple { color: #8B5CF6; }
.stat-mini .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    font-weight: 500;
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
}
.filter-bar select,
.filter-bar input[type="date"] {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    min-width: 140px;
}

.attendance-table {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.attendance-table table {
    width: 100%;
    border-collapse: collapse;
}
.attendance-table th {
    background: var(--gray-50);
    padding: 10px 14px;
    text-align: left;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid var(--gray-200);
}
.attendance-table td {
    padding: 10px 14px;
    font-size: 0.82rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.attendance-table tr:last-child td {
    border-bottom: none;
}
.attendance-table tr:hover {
    background: var(--gray-50);
}

.type-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.type-badge.arrival { background: #D1FAE5; color: #065F46; }
.type-badge.departure { background: #FEE2E2; color: #991B1B; }
.type-badge.material_received { background: #DBEAFE; color: #1E40AF; }
.type-badge.accreditation_started { background: #FEF3C7; color: #92400E; }
.type-badge.voting_started { background: #FEF3C7; color: #92400E; }
.type-badge.voting_ended { background: #FEF3C7; color: #92400E; }
.type-badge.counting_started { background: #F5F3FF; color: #6D28D9; }
.type-badge.counting_ended { background: #F5F3FF; color: #6D28D9; }

.pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    padding: 16px 0;
}
.pagination a, .pagination span {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.8rem;
    text-decoration: none;
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
}
.pagination a:hover {
    background: var(--gray-50);
    border-color: var(--gray-300);
}
.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .disabled {
    opacity: 0.5;
    pointer-events: none;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 4rem;
    color: var(--gray-300);
    margin-bottom: 16px;
}
.empty-state h4 {
    margin: 0 0 8px;
    color: var(--gray-700);
}
.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar select,
    .filter-bar input {
        min-width: unset;
    }
    .attendance-table {
        overflow-x: auto;
    }
    .attendance-table table {
        min-width: 700px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="attendance-header">
            <div>
                <h2><i class="fas fa-calendar-check"></i> Agent Attendance</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div>
                <a href="manage-pu-agents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Agents
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($summary['total_agents']); ?></div>
                <div class="label">Total Agents</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($summary['checked_in_today']); ?></div>
                <div class="label">Checked In Today</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($summary['checked_out_today']); ?></div>
                <div class="label">Checked Out Today</div>
            </div>
            <div class="stat-mini">
                <div class="number purple"><?php echo number_format($summary['total_checkins']); ?></div>
                <div class="label">Total Check-ins</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <select id="agentFilter" onchange="applyFilters()">
                <option value="0" <?php echo $agent_filter === 0 ? 'selected' : ''; ?>>All Agents</option>
                <?php foreach ($agents as $agent): ?>
                    <option value="<?php echo $agent['id']; ?>" <?php echo $agent_filter === (int)$agent['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($agent['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" id="dateFrom" value="<?php echo htmlspecialchars($date_from); ?>">
            <input type="date" id="dateTo" value="<?php echo htmlspecialchars($date_to); ?>">
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button onclick="resetFilters()" class="btn-secondary-sm" style="background: var(--gray-100);">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Attendance Table -->
        <?php if (count($attendance_data) > 0): ?>
            <div class="attendance-table">
                <table>
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>PU</th>
                            <th>Check-in Type</th>
                            <th>Location</th>
                            <th>Device</th>
                            <th>Date/Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_data as $record): 
                            $type_map = [
                                'arrival' => ['label' => 'Arrived', 'class' => 'arrival'],
                                'departure' => ['label' => 'Departed', 'class' => 'departure'],
                                'material_received' => ['label' => 'Materials Received', 'class' => 'material_received'],
                                'accreditation_started' => ['label' => 'Accreditation Started', 'class' => 'accreditation_started'],
                                'voting_started' => ['label' => 'Voting Started', 'class' => 'voting_started'],
                                'voting_ended' => ['label' => 'Voting Ended', 'class' => 'voting_ended'],
                                'counting_started' => ['label' => 'Counting Started', 'class' => 'counting_started'],
                                'counting_ended' => ['label' => 'Counting Ended', 'class' => 'counting_ended']
                            ];
                            $type_info = $type_map[$record['checkin_type']] ?? ['label' => $record['checkin_type'], 'class' => 'arrival'];
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight:500;"><?php echo htmlspecialchars($record['agent_name']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo htmlspecialchars($record['agent_code']); ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($record['pu_name'])): ?>
                                        <div><?php echo htmlspecialchars($record['pu_name']); ?></div>
                                        <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($record['pu_code'] ?? ''); ?></div>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);font-size:0.75rem;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="type-badge <?php echo $type_info['class']; ?>">
                                        <?php echo $type_info['label']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($record['gps_lat']) && !empty($record['gps_lng'])): ?>
                                        <div style="font-size:0.7rem;">
                                            <i class="fas fa-map-marker-alt" style="color:var(--gray-400);"></i>
                                            <?php echo round($record['gps_lat'], 6); ?>, <?php echo round($record['gps_lng'], 6); ?>
                                        </div>
                                        <?php if (!empty($record['gps_accuracy'])): ?>
                                            <div style="font-size:0.6rem;color:var(--gray-400);">Accuracy: <?php echo $record['gps_accuracy']; ?>m</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);font-size:0.7rem;">No GPS</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($record['device_id'])): ?>
                                        <div style="font-size:0.7rem;">
                                            <i class="fas fa-laptop" style="color:var(--gray-400);"></i>
                                            <?php echo htmlspecialchars(substr($record['device_id'], 0, 12)) . '...'; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);font-size:0.7rem;">N/A</span>
                                    <?php endif; ?>
                                    <?php if (!empty($record['device_battery'])): ?>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            Battery: <?php echo $record['device_battery']; ?>%
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size:0.78rem;"><?php echo date('M d, Y', strtotime($record['created_at'])); ?></div>
                                    <div style="font-size:0.7rem;color:var(--gray-400);"><?php echo date('H:i:s', strtotime($record['created_at'])); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <h4>No Attendance Records</h4>
                <p>No check-in records found for the selected filters.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Apply filters
function applyFilters() {
    const agent = document.getElementById('agentFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    window.location.href = `?agent_id=${agent}&date_from=${dateFrom}&date_to=${dateTo}`;
}

// Reset filtersfunction resetFilters() {
    document.getElementById('agentFilter').value = '0';
    document.getElementById('dateFrom').value = '<?php echo date('Y-m-d', strtotime('-7 days')); ?>';
    document.getElementById('dateTo').value = '<?php echo date('Y-m-d'); ?>';
    window.location.href = '?';
}

// Preloader
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// Sidebar toggle
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

// Sidebar dropdowns
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

// Profile dropdown
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