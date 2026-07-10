<?php
// ============================================================
// LGA COORDINATOR - AGENT REPORT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'lga') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'LGA Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$lga_id = SessionManager::get('lga_id');

if (empty($lga_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT lga_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['lga_id'])) {
            $lga_id = $user['lga_id'];
            SessionManager::set('lga_id', $lga_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching lga_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get LGA name
$lga_name = 'LGA';
try {
    if ($lga_id) {
        $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA: " . $e->getMessage());
}

// Get ward filter
$ward_filter = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;

// Get wards for filter
$wards = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}

// Fetch agents report
$agents = [];
$summary = [
    'total_agents' => 0,
    'active_agents' => 0,
    'online_agents' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'total_incidents' => 0
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
            u.last_login_at,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            w.id as ward_id,
            r.level as role_level,
            COUNT(DISTINCT ra.id) as total_submissions,
            COUNT(DISTINCT CASE WHEN ra.status IN ('verified', 'approved') THEN ra.id END) as verified_submissions,
            COUNT(DISTINCT CASE WHEN ra.status = 'pending' THEN ra.id END) as pending_submissions,
            COUNT(DISTINCT i.id) as incidents_reported,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN results_ec8a ra ON ra.agent_id = u.id
        LEFT JOIN incidents i ON i.reporter_id = u.id
        WHERE u.tenant_id = ? AND u.lga_id = ?
        AND r.level IN ('pu_agent', 'party_agent')
        AND u.deleted_at IS NULL
    ";
    $params = [$tenant_id, $lga_id];
    
    if ($ward_filter > 0) {
        $sql .= " AND w.id = ?";
        $params[] = $ward_filter;
    }
    
    $sql .= " GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone, u.status, u.created_at, u.last_login_at, pu.name, pu.code, w.name, w.id, r.level
              ORDER BY w.name ASC, u.first_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($agents as $agent) {
        $summary['total_agents']++;
        if ($agent['status'] === 'active') {
            $summary['active_agents']++;
        }
        if ($agent['is_online'] > 0) {
            $summary['online_agents']++;
        }
        $summary['total_results'] += $agent['total_submissions'];
        $summary['verified_results'] += $agent['verified_submissions'];
        $summary['pending_results'] += $agent['pending_submissions'];
        $summary['total_incidents'] += $agent['incidents_reported'];
    }
} catch (Exception $e) {
    error_log("Error fetching agents report: " . $e->getMessage());
}

$page_title = 'Agent Report';
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
    min-width: 180px;
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
    font-size: 1.2rem;
    font-weight: 700;
}

.summary-card .number.primary { color: #3B82F6; }
.summary-card .number.success { color: #10B981; }
.summary-card .number.warning { color: #F59E0B; }
.summary-card .number.danger { color: #EF4444; }
.summary-card .number.purple { color: #8B5CF6; }

.summary-card .label {
    font-size: 0.55rem;
    color: var(--gray-500);
}

.report-table-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.report-table th {
    background: var(--gray-50);
    padding: 6px 8px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.report-table td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.report-table tr:hover td {
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

.export-buttons .btn-excel:hover {
    background: #059669;
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
    .report-table-container {
        overflow-x: auto;
    }
    .report-table {
        font-size: 0.7rem;
    }
    .report-table th,
    .report-table td {
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
                    <h1><i class="fas fa-users"></i> Agent Report</h1>
                    <p class="subtitle">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($lga_name); ?> LGA - PU Agents Performance
                    </p>
                </div>
                <div class="export-buttons">
                    <a href="export-pdf.php?type=agent_report&ward_id=<?php echo $ward_filter; ?>" class="btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="export-excel.php?type=agent_report&ward_id=<?php echo $ward_filter; ?>" class="btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>

            <!-- Filter -->
            <div class="filter-bar">
                <select id="wardFilter" onchange="applyFilter()">
                    <option value="0">All Wards</option>
                    <?php foreach ($wards as $w): ?>
                        <option value="<?php echo $w['id']; ?>" <?php echo $ward_filter == $w['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($w['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-filter" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <span style="font-size:0.75rem;color:var(--gray-500);margin-left:auto;">
                    <?php echo count($agents); ?> agents found
                </span>
            </div>

            <!-- Summary Cards -->
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
                    <div class="number success"><?php echo number_format($summary['online_agents']); ?></div>
                    <div class="label">Online Now</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['total_results']); ?></div>
                    <div class="label">Results</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['verified_results']); ?></div>
                    <div class="label">Verified</div>
                </div>
                <div class="summary-card">
                    <div class="number danger"><?php echo number_format($summary['total_incidents']); ?></div>
                    <div class="label">Incidents</div>
                </div>
            </div>

            <!-- Agents Table -->
            <div class="report-table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>PU</th>
                            <th>Ward</th>
                            <th>Status</th>
                            <th>Online</th>
                            <th>Results</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Incidents</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agents as $agent): 
                            $full_name = $agent['first_name'] . ' ' . $agent['last_name'];
                            $online_status = $agent['is_online'] > 0 ? 'online' : 'offline';
                            $online_label = $agent['is_online'] > 0 ? 'Online' : 'Offline';
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($full_name); ?></strong>
                                    <div style="font-size:0.55rem;color:var(--gray-400);"><?php echo htmlspecialchars($agent['email']); ?></div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($agent['pu_name'] ?? 'N/A'); ?>
                                    <div style="font-size:0.5rem;color:var(--gray-400);"><?php echo htmlspecialchars($agent['pu_code'] ?? ''); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($agent['ward_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $agent['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($agent['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $online_status; ?>">
                                        <span class="dot"></span>
                                        <?php echo $online_label; ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($agent['total_submissions']); ?></td>
                                <td><?php echo number_format($agent['verified_submissions']); ?></td>
                                <td><?php echo number_format($agent['pending_submissions']); ?></td>
                                <td><?php echo number_format($agent['incidents_reported']); ?></td>
                                <td style="font-size:0.6rem;color:var(--gray-400);">
                                    <?php echo date('M j, Y', strtotime($agent['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($agents)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h4>No Agents Found</h4>
                                        <p>No agents found in <?php echo htmlspecialchars($lga_name); ?>.</p>
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
    var ward = document.getElementById('wardFilter').value;
    var url = window.location.pathname;
    if (ward && ward !== '0') url += '?ward_id=' + ward;
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