<?php
// ============================================================
// STATE COORDINATOR - COORDINATORS REPORT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get LGA filter
$lga_filter = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;

// Get LGAs for filter
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// Fetch coordinators data
$coordinators = [];
$summary = [
    'total_lga' => 0,
    'total_ward' => 0,
    'total_pu_agents' => 0,
    'total_party_agents' => 0,
    'total_all' => 0,
    'active_lga' => 0,
    'active_ward' => 0,
    'active_pu_agents' => 0,
    'active_all' => 0,
    'online_now' => 0,
    'by_lga' => []
];

try {
    // Get all coordinators and agents with their details
    $sql = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.status,
            u.last_login_at,
            u.created_at,
            r.level as role_level,
            r.name as role_name,
            l.name as lga_name,
            l.id as lga_id,
            w.name as ward_name,
            pu.name as pu_name,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online,
            (SELECT COUNT(*) FROM results_ec8a ra WHERE ra.agent_id = u.id AND ra.status IN ('verified', 'approved')) as verified_results,
            (SELECT COUNT(*) FROM results_ec8a ra WHERE ra.agent_id = u.id AND ra.status = 'pending') as pending_results
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN lgas l ON u.lga_id = l.id
        LEFT JOIN wards w ON u.ward_id = w.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? AND u.state_id = ? AND u.deleted_at IS NULL
        AND r.level IN ('lga', 'ward', 'pu_agent', 'party_agent')
    ";
    
    $params = [$tenant_id, $state_id];
    
    if ($lga_filter > 0) {
        $sql .= " AND (u.lga_id = ? OR l.id = ?)";
        $params[] = $lga_filter;
        $params[] = $lga_filter;
    }
    
    $sql .= " ORDER BY r.level ASC, l.name ASC, u.first_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary
    foreach ($coordinators as $c) {
        $level = $c['role_level'];
        $summary['total_' . $level] = ($summary['total_' . $level] ?? 0) + 1;
        $summary['total_all']++;
        
        if ($c['status'] === 'active') {
            $summary['active_' . $level] = ($summary['active_' . $level] ?? 0) + 1;
            $summary['active_all']++;
        }
        
        if ($c['is_online'] > 0) {
            $summary['online_now']++;
        }
        
        // By LGA
        if ($c['lga_id']) {
            $lga_key = $c['lga_id'];
            if (!isset($summary['by_lga'][$lga_key])) {
                $summary['by_lga'][$lga_key] = [
                    'name' => $c['lga_name'] ?? 'Unknown',
                    'total' => 0,
                    'active' => 0,
                    'online' => 0,
                    'lga_coordinators' => 0,
                    'ward_coordinators' => 0,
                    'pu_agents' => 0
                ];
            }
            $summary['by_lga'][$lga_key]['total']++;
            if ($c['status'] === 'active') {
                $summary['by_lga'][$lga_key]['active']++;
            }
            if ($c['is_online'] > 0) {
                $summary['by_lga'][$lga_key]['online']++;
            }
            if ($level === 'lga') {
                $summary['by_lga'][$lga_key]['lga_coordinators']++;
            } elseif ($level === 'ward') {
                $summary['by_lga'][$lga_key]['ward_coordinators']++;
            } elseif ($level === 'pu_agent') {
                $summary['by_lga'][$lga_key]['pu_agents']++;
            }
        }
    }
    
    // Sort by_lga by total descending
    uasort($summary['by_lga'], function($a, $b) {
        return $b['total'] - $a['total'];
    });
    
} catch (Exception $e) {
    error_log("Error fetching coordinators report: " . $e->getMessage());
}

$role_labels = [
    'lga' => 'LGA Coordinators',
    'ward' => 'Ward Coordinators',
    'pu_agent' => 'PU Agents',
    'party_agent' => 'Party Agents'
];

$page_title = 'Coordinators Report';
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
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
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
    padding: 8px 24px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
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
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.summary-card {
    background: white;
    border-radius: var(--radius);
    padding: 12px 14px;
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
.summary-card .number.purple { color: #8B5CF6; }
.summary-card .number.danger { color: #EF4444; }

.summary-card .label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

.summary-card .sub {
    font-size: 0.5rem;
    color: var(--gray-400);
    margin-top: 2px;
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
    padding: 8px 10px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.report-table td {
    padding: 8px 10px;
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
    border-radius: 10px;
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

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.role-badge {
    font-size: 0.55rem;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 500;
    background: var(--gray-100);
    color: var(--gray-700);
}

.role-badge.lga { background: #EFF6FF; color: #1E40AF; }
.role-badge.ward { background: #F5F3FF; color: #5B21B6; }
.role-badge.pu_agent { background: #ECFDF5; color: #065F46; }
.role-badge.party_agent { background: #FFFBEB; color: #92400E; }

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
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="report-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-user-tie"></i> Coordinators Report</h1>
                    <p class="subtitle">
                        <i class="fas fa-flag"></i> 
                        <?php echo htmlspecialchars($state_name); ?> State - Personnel Overview
                    </p>
                </div>
            </div>

            <!-- Filter -->
            <div class="filter-bar">
                <select id="lgaFilter" onchange="applyFilter()">
                    <option value="0">All LGAs</option>
                    <?php foreach ($lgas as $l): ?>
                        <option value="<?php echo $l['id']; ?>" <?php echo $lga_filter == $l['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($l['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn-filter" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Apply
                </button>

                <?php if (!empty($coordinators)): ?>
                    <a href="export-pdf.php?type=coordinators_report&lga_id=<?php echo $lga_filter; ?>" class="btn-primary-sm" style="margin-left:auto;">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </a>
                <?php endif; ?>
            </div>

            <!-- Summary Stats -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number primary"><?php echo number_format($summary['total_all']); ?></div>
                    <div class="label">Total Personnel</div>
                    <div class="sub"><?php echo number_format($summary['active_all']); ?> Active</div>
                </div>
                <div class="summary-card">
                    <div class="number warning"><?php echo number_format($summary['total_lga'] ?? 0); ?></div>
                    <div class="label">LGA Coordinators</div>
                    <div class="sub"><?php echo number_format($summary['active_lga'] ?? 0); ?> Active</div>
                </div>
                <div class="summary-card">
                    <div class="number purple"><?php echo number_format($summary['total_ward'] ?? 0); ?></div>
                    <div class="label">Ward Coordinators</div>
                    <div class="sub"><?php echo number_format($summary['active_ward'] ?? 0); ?> Active</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['total_pu_agents'] ?? 0); ?></div>
                    <div class="label">PU Agents</div>
                    <div class="sub"><?php echo number_format($summary['active_pu_agents'] ?? 0); ?> Active</div>
                </div>
                <div class="summary-card">
                    <div class="number" style="color:#F97316;"><?php echo number_format($summary['total_party_agents'] ?? 0); ?></div>
                    <div class="label">Party Agents</div>
                </div>
                <div class="summary-card">
                    <div class="number success"><?php echo number_format($summary['online_now']); ?></div>
                    <div class="label">Online Now</div>
                </div>
            </div>

            <!-- Coordinators Table -->
            <div class="report-table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Online</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coordinators as $c): 
                            $role_class = $c['role_level'];
                            $full_name = $c['first_name'] . ' ' . $c['last_name'];
                            $location = $c['pu_name'] ?? $c['ward_name'] ?? $c['lga_name'] ?? 'N/A';
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($full_name); ?></strong>
                                    <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($c['email']); ?></div>
                                </td>
                                <td>
                                    <span class="role-badge <?php echo $role_class; ?>">
                                        <?php echo $role_labels[$c['role_level']] ?? ucfirst($c['role_level']); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.7rem;color:var(--gray-600);">
                                    <?php echo htmlspecialchars($location); ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $c['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($c['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($c['is_online'] > 0): ?>
                                        <span class="status-badge online">
                                            <span class="dot"></span>
                                            Online
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size:0.6rem;color:var(--gray-400);">Offline</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($c['verified_results'] ?? 0); ?></td>
                                <td><?php echo number_format($c['pending_results'] ?? 0); ?></td>
                                <td style="font-size:0.7rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y', strtotime($c['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($coordinators)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-user-tie"></i>
                                        <h4>No Personnel Found</h4>
                                        <p>No coordinators or agents found in <?php echo htmlspecialchars($state_name); ?>.</p>
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
    var lga = document.getElementById('lgaFilter').value;
    var url = window.location.pathname;
    if (lga && lga !== '0') url += '?lga_id=' + lga;
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