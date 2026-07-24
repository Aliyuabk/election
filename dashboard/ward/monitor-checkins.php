<?php
// ============================================================
// WARD COORDINATOR - MONITOR CHECK-INS
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
// FETCH TODAY'S CHECK-INS
// ============================================================
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$agent_filter = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;

$checkins = [];
$summary = [];
$agents = [];

try {
    // Get all agents for filter
    $stmt = $db->prepare("
        SELECT id, full_name, user_code 
        FROM users 
        WHERE tenant_id = ? AND ward_id = ? AND deleted_at IS NULL
        AND status = 'active'
        AND EXISTS (SELECT 1 FROM roles r WHERE r.id = users.role_id AND r.level = 'pu_agent')
        ORDER BY full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build conditions
    $conditions = "ac.tenant_id = ? AND ac.ward_id = ? AND DATE(ac.created_at) = ?";
    $params = [$tenant_id, $ward_id, $date];
    
    if ($agent_filter > 0) {
        $conditions .= " AND ac.agent_id = ?";
        $params[] = $agent_filter;
    }
    
    // Get check-ins
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
    ");
    $stmt->execute($params);
    $checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Summary
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT agent_id) as unique_agents,
            COUNT(*) as total_checkins,
            SUM(CASE WHEN checkin_type = 'arrival' THEN 1 ELSE 0 END) as arrivals,
            SUM(CASE WHEN checkin_type = 'departure' THEN 1 ELSE 0 END) as departures
        FROM agent_checkins
        WHERE tenant_id = ? AND ward_id = ? AND DATE(created_at) = ?
    ");
    $stmt->execute([$tenant_id, $ward_id, $date]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching check-ins: " . $e->getMessage());
}

$page_title = 'Monitor Check-ins';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.monitor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.monitor-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.monitor-header h2 i {
    color: var(--primary);
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
    background: white;
    padding: 12px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
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

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.stat-mini {
    background: white;
    padding: 10px 14px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-mini .number {
    font-size: 1.2rem;
    font-weight: 700;
}
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    font-weight: 500;
}

.checkin-table {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.checkin-table table {
    width: 100%;
    border-collapse: collapse;
}
.checkin-table th {
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
.checkin-table td {
    padding: 10px 14px;
    font-size: 0.82rem;
    border-bottom: 1px solid var(--gray-100);
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
    .checkin-table {
        overflow-x: auto;
    }
    .checkin-table table {
        min-width: 700px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="monitor-header">
            <div>
                <h2><i class="fas fa-clock"></i> Monitor Check-ins</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • <?php echo date('M d, Y', strtotime($date)); ?>
                </p>
            </div>
            <div>
                <a href="monitor-pus.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <input type="date" id="dateFilter" value="<?php echo htmlspecialchars($date); ?>" onchange="applyFilters()">
            <select id="agentFilter" onchange="applyFilters()">
                <option value="0" <?php echo $agent_filter === 0 ? 'selected' : ''; ?>>All Agents</option>
                <?php foreach ($agents as $agent): ?>
                    <option value="<?php echo $agent['id']; ?>" <?php echo $agent_filter === (int)$agent['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($agent['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button onclick="resetFilters()" class="btn-secondary-sm" style="background: var(--gray-100);">
                <i class="fas fa-undo"></i> Today
            </button>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($summary['unique_agents'] ?? 0); ?></div>
                <div class="label">Agents Checked In</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($summary['arrivals'] ?? 0); ?></div>
                <div class="label">Arrivals</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($summary['departures'] ?? 0); ?></div>
                <div class="label">Departures</div>
            </div>
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($summary['total_checkins'] ?? 0); ?></div>
                <div class="label">Total Check-ins</div>
            </div>
        </div>

        <!-- Check-ins Table -->
        <div class="checkin-table">
            <?php if (count($checkins) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>PU</th>
                            <th>Check-in Type</th>
                            <th>Location</th>
                            <th>Device</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checkins as $check): 
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
                            $type_info = $type_map[$check['checkin_type']] ?? ['label' => $check['checkin_type'], 'class' => 'arrival'];
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight:500;"><?php echo htmlspecialchars($check['agent_name']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo htmlspecialchars($check['agent_code']); ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($check['pu_name'])): ?>
                                        <div><?php echo htmlspecialchars($check['pu_name']); ?></div>
                                        <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($check['pu_code'] ?? ''); ?></div>
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
                                    <?php if (!empty($check['gps_lat']) && !empty($check['gps_lng'])): ?>
                                        <div style="font-size:0.7rem;">
                                            <?php echo round($check['gps_lat'], 6); ?>, <?php echo round($check['gps_lng'], 6); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);font-size:0.7rem;">No GPS</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($check['device_id'])): ?>
                                        <div style="font-size:0.7rem;">
                                            <?php echo htmlspecialchars(substr($check['device_id'], 0, 12)) . '...'; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);font-size:0.7rem;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size:0.78rem;"><?php echo date('H:i:s', strtotime($check['created_at'])); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clock"></i>
                    <h4>No Check-ins</h4>
                    <p>No check-in records found for the selected date.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function applyFilters() {
    const date = document.getElementById('dateFilter').value;
    const agent = document.getElementById('agentFilter').value;
    window.location.href = `?date=${date}&agent_id=${agent}`;
}

function resetFilters() {
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