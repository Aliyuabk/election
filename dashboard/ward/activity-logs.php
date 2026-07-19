<?php
// ============================================================
// WARD COORDINATOR - ACTIVITY LOGS
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
// FETCH ACTIVITY LOGS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 30;
$offset = ($page - 1) * $limit;

$activity_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$activities = [];
$total_activities = 0;

try {
    // Build query conditions
    $conditions = "a.tenant_id = ?";
    $params = [$tenant_id];
    
    // Only show activities related to this ward
    $conditions .= " AND (a.ward_id = ? OR a.user_id IN (SELECT id FROM users WHERE ward_id = ?))";
    $params[] = $ward_id;
    $params[] = $ward_id;
    
    if ($activity_type !== 'all') {
        $conditions .= " AND a.activity_type = ?";
        $params[] = $activity_type;
    }
    
    if (!empty($date_from)) {
        $conditions .= " AND DATE(a.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $conditions .= " AND DATE(a.created_at) <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search)) {
        $conditions .= " AND (a.description LIKE ? OR u.full_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Get total count
    $count_stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE $conditions
    ");
    $count_stmt->execute($params);
    $total_activities = (int)$count_stmt->fetchColumn();
    
    // Get activities
    $stmt = $db->prepare("
        SELECT 
            a.*,
            u.full_name as user_name,
            u.email as user_email
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE $conditions
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching activities: " . $e->getMessage());
}

// ============================================================
// FETCH ACTIVITY TYPE STATISTICS
// ============================================================
$activity_stats = [];
$total_count = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            activity_type,
            COUNT(*) as count
        FROM activity_logs
        WHERE tenant_id = ? 
        AND (ward_id = ? OR user_id IN (SELECT id FROM users WHERE ward_id = ?))
        GROUP BY activity_type
        ORDER BY count DESC
    ");
    $stmt->execute([$tenant_id, $ward_id, $ward_id]);
    $activity_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_count = array_sum(array_column($activity_stats, 'count'));
    
} catch (Exception $e) {
    error_log("Error fetching activity stats: " . $e->getMessage());
}

$page_title = 'Activity Logs';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.activity-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.activity-header h2 i {
    color: var(--primary);
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
    font-size: 1.1rem;
    font-weight: 700;
}
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.red { color: #EF4444; }
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .number.purple { color: #8B5CF6; }
.stat-mini .label {
    font-size: 0.6rem;
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
    min-width: 180px;
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
.filter-bar select,
.filter-bar input[type="date"] {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    min-width: 130px;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 12px 16px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    margin-bottom: 8px;
    transition: var(--transition);
}
.activity-item:hover {
    box-shadow: var(--shadow-hover);
}
.activity-item .icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.activity-item .icon.login { background: #EFF6FF; color: #3B82F6; }
.activity-item .icon.logout { background: #F1F5F9; color: #64748B; }
.activity-item .icon.create { background: #ECFDF5; color: #10B981; }
.activity-item .icon.update { background: #FFFBEB; color: #F59E0B; }
.activity-item .icon.delete { background: #FEF2F2; color: #EF4444; }
.activity-item .icon.submit { background: #ECFDF5; color: #0D9488; }
.activity-item .icon.system { background: #F1F5F9; color: #64748B; }

.activity-item .content {
    flex: 1;
    min-width: 0;
}
.activity-item .content .user {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--gray-800);
}
.activity-item .content .description {
    font-size: 0.82rem;
    color: var(--gray-600);
}
.activity-item .content .meta {
    display: flex;
    gap: 12px;
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 2px;
}
.activity-item .content .meta i {
    width: 14px;
}
.activity-item .content .entity {
    display: inline-block;
    padding: 1px 8px;
    background: var(--gray-100);
    border-radius: 12px;
    font-size: 0.6rem;
    color: var(--gray-500);
    margin-left: 4px;
}

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
        grid-template-columns: repeat(3, 1fr);
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .search-box {
        min-width: unset;
    }
    .activity-item {
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="activity-header">
            <div>
                <h2><i class="fas fa-clipboard-list"></i> Activity Logs</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • 
                    <?php echo number_format($total_count); ?> total activities
                </p>
            </div>
            <div>
                <a href="reports-ward.php?type=activity" class="btn-secondary-sm">
                    <i class="fas fa-file-pdf"></i> Export Report
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <?php 
            $top_activities = array_slice($activity_stats, 0, 6);
            $colors = ['blue', 'green', 'orange', 'purple', 'red', 'teal'];
            $color_map = [
                'login' => 'blue',
                'logout' => 'gray',
                'create' => 'green',
                'update' => 'orange',
                'delete' => 'red',
                'submit' => 'teal'
            ];
            $i = 0;
            foreach ($top_activities as $stat):
                $color = $color_map[$stat['activity_type']] ?? $colors[$i % count($colors)];
            ?>
                <div class="stat-mini">
                    <div class="number <?php echo $color; ?>"><?php echo number_format($stat['count']); ?></div>
                    <div class="label"><?php echo ucfirst(str_replace('_', ' ', $stat['activity_type'])); ?></div>
                </div>
            <?php 
                $i++; 
            endforeach; 
            if (empty($top_activities)):
            ?>
                <div class="stat-mini">
                    <div class="number blue">0</div>
                    <div class="label">No Activities</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search activities..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select id="typeFilter">
                <option value="all" <?php echo $activity_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                <option value="login" <?php echo $activity_type === 'login' ? 'selected' : ''; ?>>Login</option>
                <option value="logout" <?php echo $activity_type === 'logout' ? 'selected' : ''; ?>>Logout</option>
                <option value="create" <?php echo $activity_type === 'create' ? 'selected' : ''; ?>>Create</option>
                <option value="update" <?php echo $activity_type === 'update' ? 'selected' : ''; ?>>Update</option>
                <option value="delete" <?php echo $activity_type === 'delete' ? 'selected' : ''; ?>>Delete</option>
                <option value="submit" <?php echo $activity_type === 'submit' ? 'selected' : ''; ?>>Submit</option>
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

        <!-- Activities List -->
        <?php if (count($activities) > 0): ?>
            <?php foreach ($activities as $activity): 
                $icon_map = [
                    'login' => 'fa-sign-in-alt',
                    'logout' => 'fa-sign-out-alt',
                    'create' => 'fa-plus-circle',
                    'update' => 'fa-edit',
                    'delete' => 'fa-trash',
                    'submit' => 'fa-upload',
                    'assign' => 'fa-user-plus',
                    'resolve' => 'fa-check-circle',
                    'escalate' => 'fa-arrow-up',
                    'close' => 'fa-times-circle'
                ];
                $icon = $icon_map[$activity['activity_type']] ?? 'fa-cog';
                $type_class = strpos($activity['activity_type'] ?? '', 'login') !== false ? 'login' :
                              (strpos($activity['activity_type'] ?? '', 'logout') !== false ? 'logout' :
                              (strpos($activity['activity_type'] ?? '', 'create') !== false ? 'create' :
                              (strpos($activity['activity_type'] ?? '', 'update') !== false ? 'update' :
                              (strpos($activity['activity_type'] ?? '', 'delete') !== false ? 'delete' :
                              (strpos($activity['activity_type'] ?? '', 'submit') !== false ? 'submit' : 'system')))));
            ?>
                <div class="activity-item">
                    <div class="icon <?php echo $type_class; ?>">
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="content">
                        <div class="user">
                            <?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?>
                            <?php if (!empty($activity['user_email'])): ?>
                                <span style="font-weight:400;font-size:0.7rem;color:var(--gray-400);">
                                    (<?php echo htmlspecialchars($activity['user_email']); ?>)
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($activity['entity_type'])): ?>
                                <span class="entity">
                                    <?php echo ucfirst(str_replace('_', ' ', $activity['entity_type'])); ?>
                                    <?php if (!empty($activity['entity_id'])): ?>
                                        #<?php echo $activity['entity_id']; ?>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="description"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></div>
                        <div class="meta">
                            <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i:s', strtotime($activity['created_at'])); ?></span>
                            <?php if (!empty($activity['ip_address'])): ?>
                                <span><i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($activity['ip_address']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($activity['device_id'])): ?>
                                <span><i class="fas fa-laptop"></i> <?php echo htmlspecialchars(substr($activity['device_id'], 0, 12)) . '...'; ?></span>
                            <?php endif; ?>
                            <?php if (!empty($activity['activity_type'])): ?>
                                <span><i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php 
            $total_pages = ceil($total_activities / $limit);
            if ($total_pages > 1): 
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $activity_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&type=<?php echo $activity_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $activity_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h4>No Activities Found</h4>
                <p>No activity logs match your filters.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Apply filters
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const type = document.getElementById('typeFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    window.location.href = `?search=${encodeURIComponent(search)}&type=${type}&date_from=${dateFrom}&date_to=${dateTo}`;
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('typeFilter').value = 'all';
    document.getElementById('dateFrom').value = '<?php echo date('Y-m-d', strtotime('-7 days')); ?>';
    document.getElementById('dateTo').value = '<?php echo date('Y-m-d'); ?>';
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