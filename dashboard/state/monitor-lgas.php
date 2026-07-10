<?php
// ============================================================
// STATE COORDINATOR - MONITOR LGAS
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

// If state_id is not set in session, try to get it from user record
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

// ============================================================
// GET FILTERS
// ============================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sort_dir = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'DESC' : 'ASC';

// ============================================================
// FETCH STATE NAME
// ============================================================
$state_name = 'Unknown State';
try {
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        $state_name = $state['name'] ?? 'Unknown State';
    }
} catch (Exception $e) {
    error_log("Error fetching state: " . $e->getMessage());
}

// ============================================================
// FETCH LGAS WITH STATISTICS
// ============================================================
$lgas = [];
$total_lgas = 0;
$total_pus = 0;
$total_reported = 0;
$total_agents = 0;
$total_incidents = 0;

try {
    // Build query
    $sql = "
        SELECT 
            l.id,
            l.code,
            l.name,
            l.registered_voters,
            l.is_active,
            COUNT(DISTINCT w.id) as ward_count,
            COUNT(DISTINCT pu.id) as pu_count,
            COUNT(DISTINCT u.id) as agent_count,
            COUNT(DISTINCT i.id) as incident_count,
            COUNT(DISTINCT r.pu_id) as reported_pu_count,
            (SELECT COUNT(*) FROM polling_units pu2 
             JOIN wards w2 ON pu2.ward_id = w2.id 
             WHERE w2.lga_id = l.id AND pu2.is_active = 1) as total_pu_count,
            (SELECT COUNT(*) FROM results_ec8a r2 
             JOIN polling_units pu3 ON r2.pu_id = pu3.id 
             JOIN wards w3 ON pu3.ward_id = w3.id 
             WHERE w3.lga_id = l.id AND r2.tenant_id = ? AND r2.status = 'pending') as pending_uploads,
            (SELECT COUNT(*) FROM results_ec8a r2 
             JOIN polling_units pu3 ON r2.pu_id = pu3.id 
             JOIN wards w3 ON pu3.ward_id = w3.id 
             WHERE w3.lga_id = l.id AND r2.tenant_id = ? AND r2.status = 'verified') as verified_uploads
        FROM lgas l
        LEFT JOIN wards w ON l.id = w.lga_id AND w.is_active = 1
        LEFT JOIN polling_units pu ON w.id = pu.ward_id AND pu.is_active = 1
        LEFT JOIN users u ON u.lga_id = l.id AND u.deleted_at IS NULL AND u.status = 'active'
        LEFT JOIN incidents i ON i.lga_id = l.id AND i.status NOT IN ('resolved', 'false_alarm')
        LEFT JOIN results_ec8a r ON pu.id = r.pu_id AND r.tenant_id = ?
        WHERE l.state_id = ? 
        AND l.is_active = 1
    ";
    
    $params = [$tenant_id, $tenant_id, $tenant_id, $state_id];
    
    // Add search filter
    if (!empty($search)) {
        $sql .= " AND (l.name LIKE ? OR l.code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Add status filter
    if (!empty($status_filter)) {
        if ($status_filter === 'active') {
            $sql .= " AND l.is_active = 1";
        } elseif ($status_filter === 'inactive') {
            $sql .= " AND l.is_active = 0";
        }
    }
    
    // Group by
    $sql .= " GROUP BY l.id, l.code, l.name, l.registered_voters, l.is_active";
    
    // Order by
    $allowed_sort = ['name', 'code', 'registered_voters', 'pu_count', 'agent_count', 'incident_count'];
    if (!in_array($sort_by, $allowed_sort)) {
        $sort_by = 'name';
    }
    $sql .= " ORDER BY $sort_by $sort_dir";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $total_lgas = count($lgas);
    foreach ($lgas as &$lga) {
        $total_pus += $lga['pu_count'] ?? 0;
        $total_reported += $lga['reported_pu_count'] ?? 0;
        $total_agents += $lga['agent_count'] ?? 0;
        $total_incidents += $lga['incident_count'] ?? 0;
        
        // Calculate completion percentage
        $total_pu = $lga['total_pu_count'] ?? 1;
        $lga['completion_percentage'] = $total_pu > 0 ? round(($lga['reported_pu_count'] / $total_pu) * 100, 1) : 0;
        $lga['pending_uploads'] = (int)($lga['pending_uploads'] ?? 0);
        $lga['verified_uploads'] = (int)($lga['verified_uploads'] ?? 0);
        $lga['total_pu_count'] = (int)$total_pu;
        
        // Determine status color
        $percentage = $lga['completion_percentage'];
        if ($percentage >= 80) {
            $lga['status_color'] = 'success';
            $lga['status_text'] = 'Excellent';
        } elseif ($percentage >= 50) {
            $lga['status_color'] = 'warning';
            $lga['status_text'] = 'In Progress';
        } elseif ($percentage > 0) {
            $lga['status_color'] = 'danger';
            $lga['status_text'] = 'Low';
        } else {
            $lga['status_color'] = 'secondary';
            $lga['status_text'] = 'No Data';
        }
    }
    
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.page-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stats-row .stat-mini {
    background: white;
    border-radius: 10px;
    padding: 14px 18px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stats-row .stat-mini .number {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--gray-800);
}
.stats-row .stat-mini .label {
    font-size: 0.7rem;
    color: var(--gray-500);
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 16px;
}
.filter-bar .search-box {
    flex: 1;
    min-width: 200px;
    display: flex;
    gap: 8px;
}
.filter-bar .search-box input {
    flex: 1;
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
}
.filter-bar .search-box input:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    background: white;
}
.filter-bar .btn-filter {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
}
.filter-bar .btn-filter:hover {
    background: var(--primary-dark);
}
.filter-bar .btn-reset {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    font-family: 'Inter', sans-serif;
}
.filter-bar .btn-reset:hover {
    background: var(--gray-200);
}

.table-wrapper {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.table-wrapper table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table-wrapper table th {
    background: var(--gray-50);
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-600);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
}
.table-wrapper table th a {
    color: var(--gray-600);
    text-decoration: none;
}
.table-wrapper table th a:hover {
    color: var(--primary);
}
.table-wrapper table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table-wrapper table tr:hover td {
    background: var(--gray-50);
}
.table-wrapper table tr:last-child td {
    border-bottom: none;
}

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.badge-status .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.badge-status.success { background: #ECFDF5; color: #065F46; }
.badge-status.success .dot { background: #10B981; }
.badge-status.warning { background: #FFFBEB; color: #92400E; }
.badge-status.warning .dot { background: #F59E0B; }
.badge-status.danger { background: #FEF2F2; color: #991B1B; }
.badge-status.danger .dot { background: #EF4444; }
.badge-status.secondary { background: #F3F4F6; color: #6B7280; }
.badge-status.secondary .dot { background: #9CA3AF; }

.progress-bar-container {
    display: flex;
    align-items: center;
    gap: 8px;
}
.progress-bar-container .progress-track {
    flex: 1;
    height: 6px;
    background: var(--gray-200);
    border-radius: 3px;
    overflow: hidden;
    min-width: 60px;
}
.progress-bar-container .progress-track .progress-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.6s ease;
}
.progress-bar-container .progress-track .progress-fill.success { background: #10B981; }
.progress-bar-container .progress-track .progress-fill.warning { background: #F59E0B; }
.progress-bar-container .progress-track .progress-fill.danger { background: #EF4444; }
.progress-bar-container .progress-track .progress-fill.secondary { background: #9CA3AF; }
.progress-bar-container .progress-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--gray-600);
    min-width: 35px;
    text-align: right;
}

.btn-action {
    padding: 4px 10px;
    border-radius: 6px;
    border: none;
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: 'Inter', sans-serif;
}
.btn-action.btn-view {
    background: #EFF6FF;
    color: #3B82F6;
}
.btn-action.btn-view:hover {
    background: #DBEAFE;
}
.btn-action.btn-report {
    background: #F5F3FF;
    color: #8B5CF6;
}
.btn-action.btn-report:hover {
    background: #EDE9FE;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-400);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .table-wrapper {
        overflow-x: auto;
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .search-box {
        flex-direction: column;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:8px;"></i>
                    Monitor LGAs
                    <small><?php echo htmlspecialchars($state_name); ?> - Local Government Areas</small>
                </h2>
            </div>
            <div>
                <a href="index.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number"><?php echo number_format($total_lgas); ?></div>
                <div class="label">Total LGAs</div>
            </div>
            <div class="stat-mini">
                <div class="number"><?php echo number_format($total_pus); ?></div>
                <div class="label">Polling Units</div>
            </div>
            <div class="stat-mini">
                <div class="number"><?php echo number_format($total_reported); ?></div>
                <div class="label">Reported PUs</div>
            </div>
            <div class="stat-mini">
                <div class="number"><?php echo number_format($total_agents); ?></div>
                <div class="label">Agents</div>
            </div>
            <div class="stat-mini">
                <div class="number"><?php echo number_format($total_incidents); ?></div>
                <div class="label">Active Incidents</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="filter-bar">
            <div class="search-box">
                <input type="text" name="search" placeholder="Search LGA..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
            </div>
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <select name="sort">
                <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Sort by Name</option>
                <option value="code" <?php echo $sort_by === 'code' ? 'selected' : ''; ?>>Sort by Code</option>
                <option value="registered_voters" <?php echo $sort_by === 'registered_voters' ? 'selected' : ''; ?>>Sort by Voters</option>
                <option value="pu_count" <?php echo $sort_by === 'pu_count' ? 'selected' : ''; ?>>Sort by PUs</option>
                <option value="agent_count" <?php echo $sort_by === 'agent_count' ? 'selected' : ''; ?>>Sort by Agents</option>
                <option value="incident_count" <?php echo $sort_by === 'incident_count' ? 'selected' : ''; ?>>Sort by Incidents</option>
            </select>
            <input type="hidden" name="dir" value="<?php echo $sort_dir === 'DESC' ? 'desc' : 'asc'; ?>">
            <a href="monitor-lgas.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>

        <!-- Table -->
        <div class="table-wrapper">
            <?php if (count($lgas) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name', 'dir' => $sort_dir === 'ASC' ? 'desc' : 'asc'])); ?>">LGA</a></th>
                            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'code', 'dir' => $sort_dir === 'ASC' ? 'desc' : 'asc'])); ?>">Code</a></th>
                            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'pu_count', 'dir' => $sort_dir === 'ASC' ? 'desc' : 'asc'])); ?>">PUs</th>
                            <th>Reported</th>
                            <th>Progress</th>
                            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'agent_count', 'dir' => $sort_dir === 'ASC' ? 'desc' : 'asc'])); ?>">Agents</th>
                            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'incident_count', 'dir' => $sort_dir === 'ASC' ? 'desc' : 'asc'])); ?>">Incidents</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = 1; ?>
                        <?php foreach ($lgas as $lga): ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td><strong><?php echo htmlspecialchars($lga['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($lga['code'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($lga['total_pu_count'] ?? 0); ?></td>
                                <td><?php echo number_format($lga['reported_pu_count'] ?? 0); ?></td>
                                <td>
                                    <div class="progress-bar-container">
                                        <div class="progress-track">
                                            <div class="progress-fill <?php echo $lga['status_color']; ?>" 
                                                 style="width: <?php echo $lga['completion_percentage']; ?>%;">
                                            </div>
                                        </div>
                                        <span class="progress-label"><?php echo $lga['completion_percentage']; ?>%</span>
                                    </div>
                                </td>
                                <td><?php echo number_format($lga['agent_count'] ?? 0); ?></td>
                                <td>
                                    <?php if (($lga['incident_count'] ?? 0) > 0): ?>
                                        <span style="color:var(--danger);font-weight:600;"><?php echo $lga['incident_count']; ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $lga['status_color']; ?>">
                                        <span class="dot"></span>
                                        <?php echo $lga['status_text']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="lga-details.php?id=<?php echo $lga['id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="lga-report.php?id=<?php echo $lga['id']; ?>" class="btn-action btn-report">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-map-marker-alt"></i>
                    <p>No LGAs found in <?php echo htmlspecialchars($state_name); ?>.</p>
                    <?php if (!empty($search)): ?>
                        <p style="font-size:0.8rem;">Try adjusting your search criteria.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
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
</script>
</body>
</html>