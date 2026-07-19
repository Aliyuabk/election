<?php
// ============================================================
// WARD COORDINATOR - VIEW POLLING UNITS
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
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');
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
// FETCH WARD, LGA, AND STATE NAMES
// ============================================================
$ward_name = 'Ward';
$lga_name = 'LGA';
$state_name = 'State';
try {
    if ($ward_id) {
        $stmt = $db->prepare("
            SELECT 
                w.name as ward_name,
                l.name as lga_name,
                s.name as state_name
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            WHERE w.id = ?
        ");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['ward_name'];
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward details: " . $e->getMessage());
}

// ============================================================
// FETCH POLLING UNITS WITH DETAILS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$polling_units = [];
$total_pus = 0;

try {
    // Build query conditions
    $conditions = "pu.ward_id = ?";
    $params = [$ward_id];
    
    if (!empty($search)) {
        $conditions .= " AND (pu.name LIKE ? OR pu.code LIKE ? OR pu.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($status_filter !== 'all') {
        $conditions .= " AND pu.is_active = ?";
        $params[] = $status_filter === 'active' ? 1 : 0;
    }
    
    // Get total count
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM polling_units pu WHERE $conditions");
    $count_stmt->execute($params);
    $total_pus = (int)$count_stmt->fetchColumn();
    
    // Get polling units with stats
    $stmt = $db->prepare("
        SELECT 
            pu.id,
            pu.code,
            pu.name,
            pu.description,
            pu.registered_voters,
            pu.accredited_voters,
            pu.is_rural,
            pu.is_active,
            pu.gps_lat,
            pu.gps_lng,
            pu.created_at,
            COUNT(DISTINCT u.id) as total_agents,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_agents,
            COUNT(DISTINCT r.id) as total_submissions,
            COUNT(DISTINCT CASE WHEN r.status = 'verified' THEN r.id END) as verified_submissions,
            COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.id END) as pending_submissions,
            COUNT(DISTINCT i.id) as total_incidents,
            COUNT(DISTINCT CASE WHEN i.status IN ('reported', 'investigating') THEN i.id END) as active_incidents,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.pu_id = pu.id AND r2.status = 'pending') as pending_ec8a
        FROM polling_units pu
        LEFT JOIN users u ON u.pu_id = pu.id AND u.deleted_at IS NULL
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        LEFT JOIN incidents i ON i.pu_id = pu.id
        WHERE $conditions
        GROUP BY pu.id, pu.code, pu.name, pu.description, pu.registered_voters, 
                 pu.accredited_voters, pu.is_rural, pu.is_active, pu.gps_lat, 
                 pu.gps_lng, pu.created_at
        ORDER BY pu.name ASC
        LIMIT ? OFFSET ?
    ");
    
    $stmt_params = array_merge([$tenant_id], $params, [$limit, $offset]);
    $stmt->execute($stmt_params);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

// ============================================================
// FETCH SUMMARY STATISTICS
// ============================================================
$summary = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'total_voters' => 0,
    'total_agents' => 0,
    'total_submissions' => 0,
    'verified_submissions' => 0,
    'total_incidents' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
            SUM(registered_voters) as total_voters,
            (SELECT COUNT(*) FROM users u WHERE u.ward_id = ? AND u.deleted_at IS NULL AND u.pu_id IS NOT NULL) as total_agents,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.tenant_id = ? AND r.pu_id IN (SELECT id FROM polling_units WHERE ward_id = ?)) as total_submissions,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.tenant_id = ? AND r.status = 'verified' AND r.pu_id IN (SELECT id FROM polling_units WHERE ward_id = ?)) as verified_submissions,
            (SELECT COUNT(*) FROM incidents i WHERE i.ward_id = ?) as total_incidents
        FROM polling_units pu
        WHERE pu.ward_id = ?
    ");
    $stmt->execute([$ward_id, $tenant_id, $ward_id, $tenant_id, $ward_id, $ward_id, $ward_id]);
    $summary_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $summary['total'] = (int)($summary_data['total'] ?? 0);
    $summary['active'] = (int)($summary_data['active'] ?? 0);
    $summary['inactive'] = (int)($summary_data['inactive'] ?? 0);
    $summary['total_voters'] = (int)($summary_data['total_voters'] ?? 0);
    $summary['total_agents'] = (int)($summary_data['total_agents'] ?? 0);
    $summary['total_submissions'] = (int)($summary_data['total_submissions'] ?? 0);
    $summary['verified_submissions'] = (int)($summary_data['verified_submissions'] ?? 0);
    $summary['total_incidents'] = (int)($summary_data['total_incidents'] ?? 0);
    
} catch (Exception $e) {
    error_log("Error fetching summary: " . $e->getMessage());
}

$page_title = 'Polling Units';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.pu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.pu-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.pu-header h2 i {
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
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.red { color: #EF4444; }
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
.filter-bar .search-box {
    flex: 1;
    min-width: 200px;
    position: relative;
}
.filter-bar .search-box input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.filter-bar .search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
}
.filter-bar select {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    min-width: 140px;
}

.pu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}
.pu-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    transition: var(--transition);
}
.pu-card:hover {
    box-shadow: var(--shadow-hover);
    transform: translateY(-2px);
}
.pu-card .pu-header-card {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}
.pu-card .pu-name {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--gray-800);
}
.pu-card .pu-code {
    font-size: 0.7rem;
    color: var(--gray-400);
}
.pu-card .pu-status {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.pu-card .pu-status.active { background: #ECFDF5; color: #10B981; }
.pu-card .pu-status.inactive { background: #FEF2F2; color: #EF4444; }

.pu-card .pu-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 12px;
    font-size: 0.78rem;
    color: var(--gray-600);
    margin: 8px 0;
}
.pu-card .pu-details .item {
    display: flex;
    align-items: center;
    gap: 4px;
}
.pu-card .pu-details .item i {
    font-size: 0.6rem;
    color: var(--gray-400);
    width: 16px;
}
.pu-card .pu-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 4px;
    margin: 10px 0;
    padding: 8px 0;
    border-top: 1px solid var(--gray-100);
    border-bottom: 1px solid var(--gray-100);
}
.pu-card .pu-stats .stat {
    text-align: center;
}
.pu-card .pu-stats .stat .num {
    font-size: 0.95rem;
    font-weight: 700;
}
.pu-card .pu-stats .stat .num.green { color: #10B981; }
.pu-card .pu-stats .stat .num.blue { color: #3B82F6; }
.pu-card .pu-stats .stat .num.orange { color: #F59E0B; }
.pu-card .pu-stats .stat .num.red { color: #EF4444; }
.pu-card .pu-stats .stat .lbl {
    font-size: 0.55rem;
    color: var(--gray-400);
}
.pu-card .pu-actions {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
    margin-top: 8px;
}
.pu-card .pu-actions .btn-sm {
    padding: 4px 10px;
    font-size: 0.7rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pu-card .pu-actions .btn-sm.view { background: #EFF6FF; color: #3B82F6; }
.pu-card .pu-actions .btn-sm.view:hover { background: #DBEAFE; }
.pu-card .pu-actions .btn-sm.assign { background: #ECFDF5; color: #10B981; }
.pu-card .pu-actions .btn-sm.assign:hover { background: #D1FAE5; }
.pu-card .pu-actions .btn-sm.details { background: #F5F3FF; color: #8B5CF6; }
.pu-card .pu-actions .btn-sm.details:hover { background: #EDE9FE; }

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
    .pu-grid {
        grid-template-columns: 1fr;
    }
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .search-box {
        min-width: unset;
    }
    .pu-card .pu-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
}

@media (max-width: 480px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .pu-card .pu-details {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="pu-header">
            <div>
                <h2><i class="fas fa-flag-checkered"></i> Polling Units</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • <?php echo htmlspecialchars($lga_name); ?> LGA • <?php echo htmlspecialchars($state_name); ?> State
                </p>
            </div>
            <div>
                <a href="pu-details.php" class="btn-secondary-sm">
                    <i class="fas fa-info-circle"></i> View Details
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($summary['total']); ?></div>
                <div class="label">Total PUs</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($summary['active']); ?></div>
                <div class="label">Active</div>
            </div>
            <div class="stat-mini">
                <div class="number red"><?php echo number_format($summary['inactive']); ?></div>
                <div class="label">Inactive</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($summary['total_voters']); ?></div>
                <div class="label">Registered Voters</div>
            </div>
            <div class="stat-mini">
                <div class="number purple"><?php echo number_format($summary['total_agents']); ?></div>
                <div class="label">Agents</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($summary['verified_submissions']); ?></div>
                <div class="label">Verified Results</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by name, code or description..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select id="statusFilter">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button onclick="resetFilters()" class="btn-secondary-sm" style="background: var(--gray-100);">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Polling Units Grid -->
        <div class="pu-grid">
            <?php if (count($polling_units) > 0): ?>
                <?php foreach ($polling_units as $pu): 
                    $has_incidents = ($pu['total_incidents'] ?? 0) > 0;
                    $has_pending = ($pu['pending_ec8a'] ?? 0) > 0;
                ?>
                    <div class="pu-card">
                        <div class="pu-header-card">
                            <div>
                                <div class="pu-name"><?php echo htmlspecialchars($pu['name']); ?></div>
                                <div class="pu-code"><?php echo htmlspecialchars($pu['code']); ?></div>
                            </div>
                            <span class="pu-status <?php echo ($pu['is_active'] ?? 0) ? 'active' : 'inactive'; ?>">
                                <?php echo ($pu['is_active'] ?? 0) ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($pu['description'])): ?>
                            <div style="font-size:0.75rem;color:var(--gray-500);margin-bottom:6px;">
                                <?php echo htmlspecialchars(substr($pu['description'], 0, 100)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="pu-details">
                            <div class="item"><i class="fas fa-users"></i> <?php echo number_format($pu['registered_voters'] ?? 0); ?> voters</div>
                            <div class="item"><i class="fas fa-user-check"></i> <?php echo number_format($pu['accredited_voters'] ?? 0); ?> accredited</div>
                            <div class="item"><i class="fas fa-user-tie"></i> <?php echo number_format($pu['total_agents'] ?? 0); ?> agents</div>
                            <div class="item"><i class="fas fa-<?php echo ($pu['is_rural'] ?? 0) ? 'tree' : 'city'; ?>"></i> <?php echo ($pu['is_rural'] ?? 0) ? 'Rural' : 'Urban'; ?></div>
                            <?php if (!empty($pu['gps_lat']) && !empty($pu['gps_lng'])): ?>
                                <div class="item"><i class="fas fa-map-marker-alt"></i> <?php echo round($pu['gps_lat'], 6); ?>, <?php echo round($pu['gps_lng'], 6); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pu-stats">
                            <div class="stat">
                                <div class="num green"><?php echo number_format($pu['verified_submissions'] ?? 0); ?></div>
                                <div class="lbl">Verified</div>
                            </div>
                            <div class="stat">
                                <div class="num <?php echo ($pu['pending_submissions'] ?? 0) > 0 ? 'orange' : 'blue'; ?>">
                                    <?php echo number_format($pu['pending_submissions'] ?? 0); ?>
                                </div>
                                <div class="lbl">Pending</div>
                            </div>
                            <div class="stat">
                                <div class="num <?php echo $has_incidents ? 'red' : 'blue'; ?>">
                                    <?php echo number_format($pu['total_incidents'] ?? 0); ?>
                                </div>
                                <div class="lbl">Incidents</div>
                            </div>
                            <div class="stat">
                                <div class="num <?php echo $has_pending ? 'orange' : 'green'; ?>">
                                    <?php echo number_format($pu['pending_ec8a'] ?? 0); ?>
                                </div>
                                <div class="lbl">Pending EC8A</div>
                            </div>
                        </div>
                        
                        <div class="pu-actions">
                            <a href="pu-details.php?id=<?php echo $pu['id']; ?>" class="btn-sm details">
                                <i class="fas fa-info-circle"></i> Details
                            </a>
                            <a href="assign-agents.php?pu_id=<?php echo $pu['id']; ?>" class="btn-sm assign">
                                <i class="fas fa-user-plus"></i> Assign
                            </a>
                            <a href="reports-pu.php?pu_id=<?php echo $pu['id']; ?>" class="btn-sm view">
                                <i class="fas fa-file-alt"></i> Report
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="grid-column:1/-1;">
                    <i class="fas fa-flag-checkered"></i>
                    <h4>No Polling Units Found</h4>
                    <p>No polling units have been added to this ward yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php 
        $total_pages = ceil($total_pus / $limit);
        if ($total_pages > 1): 
        ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Apply filters
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    window.location.href = `?search=${encodeURIComponent(search)}&status=${status}`;
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = 'all';
    window.location.href = '?';
}

// Enter key on search
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
    }
});

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