<?php
// ============================================================
// STATE COORDINATOR - LGA COORDINATOR ACTIVITY
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
$coordinator_id = isset($_GET['coordinator_id']) ? (int)$_GET['coordinator_id'] : 0;
$activity_type = isset($_GET['type']) ? $_GET['type'] : '';
$date_from = isset($_GET['from']) ? $_GET['from'] : '';
$date_to = isset($_GET['to']) ? $_GET['to'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// ============================================================
// FETCH COORDINATORS FOR FILTER
// ============================================================
$coordinators = [];
$coordinator_name = '';

try {
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, full_name 
        FROM users 
        WHERE tenant_id = ? AND state_id = ? AND role_id IN (SELECT id FROM roles WHERE level = 'lga')
        AND deleted_at IS NULL
        ORDER BY last_name ASC
    ");
    $stmt->execute([$tenant_id, $state_id]);
    $coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($coordinator_id > 0) {
        foreach ($coordinators as $c) {
            if ($c['id'] == $coordinator_id) {
                $coordinator_name = $c['full_name'] ?? $c['first_name'] . ' ' . $c['last_name'];
                break;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching coordinators: " . $e->getMessage());
}

// ============================================================
// FETCH ACTIVITY LOGS
// ============================================================
$activities = [];
$total_activities = 0;
$total_pages = 0;

try {
    $sql = "SELECT * FROM activity_logs WHERE 1=1";
    $params = [];
    
    if ($coordinator_id > 0) {
        $sql .= " AND user_id = ?";
        $params[] = $coordinator_id;
    }
    
    if (!empty($activity_type)) {
        $sql .= " AND activity_type = ?";
        $params[] = $activity_type;
    }
    
    if (!empty($date_from)) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $date_to;
    }
    
    // Count total
    $count_sql = str_replace("SELECT *", "SELECT COUNT(*) as count", $sql);
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_activities = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    $total_pages = ceil($total_activities / $per_page);
    
    // Get data
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching activities: " . $e->getMessage());
}

// ============================================================
// ACTIVITY TYPE OPTIONS
// ============================================================
$activity_types = [
    'login' => 'Login',
    'logout' => 'Logout',
    'user_created' => 'User Created',
    'user_updated' => 'User Updated',
    'user_suspended' => 'User Suspended',
    'user_activated' => 'User Activated',
    'user_deleted' => 'User Deleted',
    'election_created' => 'Election Created',
    'election_updated' => 'Election Updated',
    'election_deleted' => 'Election Deleted',
    'result_submitted' => 'Result Submitted',
    'result_verified' => 'Result Verified',
    'incident_reported' => 'Incident Reported',
    'incident_resolved' => 'Incident Resolved',
    'broadcast_sent' => 'Broadcast Sent',
    'agent_assigned' => 'Agent Assigned',
    'agent_reassigned' => 'Agent Reassigned',
];

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

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 16px;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filter-bar select,
.filter-bar input {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    background: white;
}
.filter-bar select:focus,
.filter-bar input:focus {
    outline: none;
    border-color: var(--primary);
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

.activity-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--gray-100);
    transition: var(--transition);
}
.activity-item:hover {
    background: var(--gray-50);
}
.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.activity-icon.login { background: #EFF6FF; color: #3B82F6; }
.activity-icon.logout { background: #FEF2F2; color: #EF4444; }
.activity-icon.user { background: #F5F3FF; color: #8B5CF6; }
.activity-icon.election { background: #ECFDF5; color: #10B981; }
.activity-icon.result { background: #FFFBEB; color: #F59E0B; }
.activity-icon.incident { background: #FEF2F2; color: #EF4444; }
.activity-icon.broadcast { background: #F0FDFA; color: #0D9488; }
.activity-icon.agent { background: #EFF6FF; color: #3B82F6; }
.activity-icon.default { background: var(--gray-100); color: var(--gray-500); }

.activity-content {
    flex: 1;
}
.activity-content .description {
    font-size: 0.85rem;
    color: var(--gray-700);
}
.activity-content .description strong {
    color: var(--gray-800);
}
.activity-content .meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 2px;
    font-size: 0.7rem;
    color: var(--gray-400);
}
.activity-content .meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.activity-type-badge {
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.6rem;
    font-weight: 600;
    background: var(--gray-100);
    color: var(--gray-600);
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    padding: 16px 20px;
    border-top: 1px solid var(--gray-200);
}
.pagination .page-btn {
    padding: 6px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.pagination .page-btn:hover {
    background: var(--gray-50);
    border-color: var(--gray-300);
}
.pagination .page-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .page-btn.disabled {
    opacity: 0.5;
    pointer-events: none;
}
.pagination .page-info {
    font-size: 0.8rem;
    color: var(--gray-500);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
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
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .date-group {
        display: flex;
        gap: 8px;
    }
    .filter-bar .date-group input {
        flex: 1;
    }
    .activity-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    .activity-content .meta {
        flex-direction: column;
        gap: 4px;
    }
    .pagination {
        flex-wrap: wrap;
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
                    <i class="fas fa-clock" style="color:var(--primary);margin-right:8px;"></i>
                    Coordinator Activity Log
                    <small>Track activities of LGA Coordinators</small>
                </h2>
            </div>
            <div>
                <a href="lga-coordinators.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Coordinators
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="filter-bar">
            <select name="coordinator_id">
                <option value="">All Coordinators</option>
                <?php foreach ($coordinators as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $coordinator_id == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['full_name'] ?? $c['first_name'] . ' ' . $c['last_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="type">
                <option value="">All Activities</option>
                <?php foreach ($activity_types as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $activity_type === $key ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <div class="date-group" style="display:flex;gap:8px;align-items:center;">
                <input type="date" name="from" value="<?php echo htmlspecialchars($date_from); ?>" placeholder="From">
                <span style="color:var(--gray-400);">to</span>
                <input type="date" name="to" value="<?php echo htmlspecialchars($date_to); ?>" placeholder="To">
            </div>
            
            <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
            <a href="lga-coordinators-activity.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>

        <!-- Activity List -->
        <div class="activity-container">
            <?php if (count($activities) > 0): ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php 
                            $icon_class = 'default';
                            if (strpos($activity['activity_type'], 'login') !== false) $icon_class = 'login';
                            elseif (strpos($activity['activity_type'], 'logout') !== false) $icon_class = 'logout';
                            elseif (strpos($activity['activity_type'], 'user') !== false) $icon_class = 'user';
                            elseif (strpos($activity['activity_type'], 'election') !== false) $icon_class = 'election';
                            elseif (strpos($activity['activity_type'], 'result') !== false) $icon_class = 'result';
                            elseif (strpos($activity['activity_type'], 'incident') !== false) $icon_class = 'incident';
                            elseif (strpos($activity['activity_type'], 'broadcast') !== false) $icon_class = 'broadcast';
                            elseif (strpos($activity['activity_type'], 'agent') !== false) $icon_class = 'agent';
                            echo $icon_class;
                        ?>">
                            <i class="fas fa-<?php 
                                if (strpos($activity['activity_type'], 'login') !== false) echo 'sign-in-alt';
                                elseif (strpos($activity['activity_type'], 'logout') !== false) echo 'sign-out-alt';
                                elseif (strpos($activity['activity_type'], 'user') !== false) echo 'user';
                                elseif (strpos($activity['activity_type'], 'election') !== false) echo 'vote-yea';
                                elseif (strpos($activity['activity_type'], 'result') !== false) echo 'file-alt';
                                elseif (strpos($activity['activity_type'], 'incident') !== false) echo 'exclamation-triangle';
                                elseif (strpos($activity['activity_type'], 'broadcast') !== false) echo 'bullhorn';
                                elseif (strpos($activity['activity_type'], 'agent') !== false) echo 'user-check';
                                else echo 'circle';
                            ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="description">
                                <?php echo htmlspecialchars($activity['description'] ?? 'Activity recorded'); ?>
                            </div>
                            <div class="meta">
                                <span>
                                    <i class="fas fa-user"></i>
                                    <?php 
                                    // Get user name
                                    $user_name_display = 'System';
                                    foreach ($coordinators as $c) {
                                        if ($c['id'] == $activity['user_id']) {
                                            $user_name_display = $c['full_name'] ?? $c['first_name'] . ' ' . $c['last_name'];
                                            break;
                                        }
                                    }
                                    echo htmlspecialchars($user_name_display);
                                    ?>
                                </span>
                                <span><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></span>
                                <?php if (!empty($activity['ip_address'])): ?>
                                    <span><i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($activity['ip_address']); ?></span>
                                <?php endif; ?>
                                <span class="activity-type-badge">
                                    <?php echo $activity_types[$activity['activity_type']] ?? ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <span class="page-info">
                            Showing <?php echo min($total_activities, ($page - 1) * $per_page + 1); ?> - 
                            <?php echo min($total_activities, $page * $per_page); ?> of <?php echo number_format($total_activities); ?>
                        </span>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clock"></i>
                    <p>No activities found.</p>
                    <?php if ($coordinator_id > 0 || !empty($activity_type) || !empty($date_from) || !empty($date_to)): ?>
                        <p style="font-size:0.8rem;">Try adjusting your filters.</p>
                    <?php else: ?>
                        <p style="font-size:0.8rem;color:var(--gray-400);">Activities will appear here as coordinators perform actions.</p>
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