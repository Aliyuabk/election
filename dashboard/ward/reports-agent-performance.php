<?php
// ============================================================
// WARD COORDINATOR - AGENT PERFORMANCE REPORT
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

// Get PU filter
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

// Get period filter
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$date_filter = '';
switch ($period) {
    case 'today':
        $date_filter = "DATE(r.created_at) = CURDATE()";
        break;
    case 'week':
        $date_filter = "r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_filter = "r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'quarter':
        $date_filter = "r.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    default:
        $date_filter = "1=1";
}

// Fetch agent performance data
$agents = [];
$summary = [
    'total_agents' => 0,
    'active_agents' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'top_performers' => []
];

try {
    $sql = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.status,
            u.created_at,
            pu.id as pu_id,
            pu.name as pu_name,
            pu.code as pu_code,
            COUNT(DISTINCT r.id) as total_submissions,
            COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_submissions,
            COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.id END) as pending_submissions,
            COUNT(DISTINCT CASE WHEN r.status = 'flagged' THEN r.id END) as flagged_submissions,
            COUNT(DISTINCT i.id) as incidents_reported,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online,
            (SELECT COUNT(*) FROM agent_checkins ac WHERE ac.agent_id = u.id AND ac.checkin_type = 'arrival' AND DATE(ac.created_at) = CURDATE()) as checked_in_today
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN results_ec8a ra ON ra.agent_id = u.id AND ra.tenant_id = ? AND $date_filter
        LEFT JOIN incidents i ON i.reporter_id = u.id AND i.tenant_id = ? AND $date_filter
        WHERE u.tenant_id = ?
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND r.level = 'pu_agent'
    ";
    $params = [$tenant_id, $tenant_id, $tenant_id, $ward_id];
    
    if ($pu_filter > 0) {
        $sql .= " AND u.pu_id = ?";
        $params[] = $pu_filter;
    }
    
    $sql .= " GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone, u.status, u.created_at, pu.id, pu.name, pu.code
              ORDER BY verified_submissions DESC, total_submissions DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($agents as $agent) {
        $summary['total_agents']++;
        if ($agent['status'] === 'active') {
            $summary['active_agents']++;
        }
        $summary['total_results'] += $agent['total_submissions'];
        $summary['verified_results'] += $agent['verified_submissions'];
        $summary['pending_results'] += $agent['pending_submissions'];
    }
    
    $summary['top_performers'] = array_slice($agents, 0, 10);
} catch (Exception $e) {
    error_log("Error fetching agent performance: " . $e->getMessage());
}

$period_labels = [
    'today' => 'Today',
    'week' => 'Last 7 Days',
    'month' => 'Last 30 Days',
    'quarter' => 'Last 90 Days',
    'all' => 'All Time'
];

$page_title = 'Agent Performance Report';
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
.summary-card .number.success { color: #10B981; }
.summary-card .number.warning { color: #F59E0B; }
.summary-card .number.purple { color: #8B5CF6; }

.summary-card .label {
    font-size: 0.55rem;
    color: var(--gray-500);
}

.performance-table-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.performance-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.performance-table th {
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

.performance-table td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.performance-table tr:hover td {
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

.status-badge.active { background: #ECFDF5; color: #065F46; }
.status-badge.active .dot { background: #10B981; }
.status-badge.suspended { background: #FEF2F2; color: #991B1B; }
.status-badge.suspended .dot { background: #EF4444; }
.status-badge.pending { background: #FFFBEB; color: #92400E; }
.status-badge.pending .dot { background: #F59E0B; }

.status-badge.online { background: #ECFDF5; color: #065F46; }
.status-badge.online .dot { background: #10B981; animation: pulse-dot 1.5s ease-in-out infinite; }
.status-badge.offline { background: #F3F4F6; color: #6B7280; }
.status-badge.offline .dot { background: #9CA3AF; }

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

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
    .performance-table-container {
        overflow-x: auto;
    }
    .performance-table {
        font-size: 0.7rem;
    }
    .performance-table th,
    .performance-table td {
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
        <div class="report-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-chart-bar"></i> Agent Performance Report</h1>
                    <p class="subtitle">
                        <i class="fas fa-layer-group"></i> 
                        <?php echo htmlspecialchars($ward_name); ?> Ward - Agent Performance Analysis
                    </p>
                </div>
                <div class="export-buttons">
                    <a href="export-pdf.php?type=agent_performance&period=<?php echo $period; ?>&pu_id=<?php echo $pu_filter; ?>" class="btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="export-excel.php?type=agent_performance&period=<?php echo $period; ?>&pu_id=<?php echo $pu_filter; ?>" class="btn-excel">
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
                    <?php echo $period_labels[$period] ?? 'All Time'; ?> - <?php echo count($agents); ?> agents
                </span>
            </div>

            <!-- Summary -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_agents']); ?></div>
                    <div class="label">Total Agents</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['active_agents']); ?></div>
                    <div class="label">Active</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['total_results']); ?></div>
                    <div class="label">Submissions</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['verified_results']); ?></div>
                    <div class="label">Verified</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['pending_results']); ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="summary-card">
                    <div class="number purple"><?php echo number_format(count($summary['top_performers'])); ?></div>
                    <div class="label">Top Performers</div>
                </div>
            </div>

            <!-- Top Performers -->
            <?php if (!empty($summary['top_performers'])): ?>
                <h4 style="font-size:0.8rem;font-weight:600;margin:12px 0 8px;">
                    <i class="fas fa-trophy" style="color:#F59E0B;"></i> Top Performers
                </h4>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;margin-bottom:16px;">
                    <?php 
                    $ranks = ['🥇', '🥈', '🥉'];
                    foreach (array_slice($summary['top_performers'], 0, 5) as $index => $agent): 
                    ?>
                        <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:10px 14px;text-align:center;">
                            <div style="font-size:1.2rem;margin-bottom:2px;">
                                <?php echo $ranks[$index] ?? '#' . ($index + 1); ?>
                            </div>
                            <div style="font-weight:600;font-size:0.8rem;">
                                <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                            </div>
                            <div style="font-size:0.6rem;color:var(--gray-500);">
                                <?php echo htmlspecialchars($agent['pu_name'] ?? 'Unassigned'); ?>
                            </div>
                            <div style="font-size:0.75rem;font-weight:700;color:#10B981;margin-top:2px;">
                                <?php echo number_format($agent['verified_submissions']); ?> verified
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Performance Table -->
            <div class="performance-table-container">
                <table class="performance-table">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>PU</th>
                            <th>Status</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Total</th>
                            <th>Incidents</th>
                            <th>Check-in</th>
                            <th>Online</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agents as $agent): 
                            $online_status = $agent['is_online'] > 0 ? 'online' : 'offline';
                            $online_label = $agent['is_online'] > 0 ? 'Online' : 'Offline';
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?></strong>
                                    <div style="font-size:0.55rem;color:var(--gray-400);"><?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?></div>
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
                                    <span class="status-badge <?php echo $agent['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($agent['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:600;color:#10B981;">
                                        <?php echo number_format($agent['verified_submissions']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:600;color:#F59E0B;">
                                        <?php echo number_format($agent['pending_submissions']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($agent['total_submissions']); ?></td>
                                <td><?php echo number_format($agent['incidents_reported']); ?></td>
                                <td>
                                    <?php if ($agent['checked_in_today'] > 0): ?>
                                        <span style="color:#10B981;font-size:0.6rem;">
                                            <i class="fas fa-check-circle"></i> Yes
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);font-size:0.6rem;">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $online_status; ?>">
                                        <span class="dot"></span>
                                        <?php echo $online_label; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($agents)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-chart-bar"></i>
                                        <h4>No Performance Data</h4>
                                        <p>No agent performance data available for the selected period.</p>
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
    var pu = document.getElementById('puFilter').value;
    var period = document.getElementById('periodFilter').value;
    
    var url = window.location.pathname;
    var params = [];
    if (pu && pu !== '0') params.push('pu_id=' + pu);
    if (period) params.push('period=' + period);
    if (params.length) url += '?' + params.join('&');
    window.location.href = url;
}

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