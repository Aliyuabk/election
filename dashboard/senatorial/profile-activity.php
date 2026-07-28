<?php
// ============================================================
// SENATORIAL COORDINATOR - ACTIVITY LOG
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$db = getDB();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$type_filter = $_GET['type'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query
$params = [$user_id];
$where = "user_id = ?";

if (!empty($type_filter)) {
    $where .= " AND activity_type = ?";
    $params[] = $type_filter;
}

if (!empty($date_filter)) {
    $where .= " AND DATE(created_at) = ?";
    $params[] = $date_filter;
}

// Get total count
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $total_pages = ceil($total / $limit);
} catch (Exception $e) {
    error_log("Error counting activities: " . $e->getMessage());
    $total = 0;
    $total_pages = 1;
}

// Get activities
$activities = [];
try {
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE $where 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $activities = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching activities: " . $e->getMessage());
}

// Get activity types for filter
$activity_types = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT activity_type, COUNT(*) as count 
        FROM activity_logs 
        WHERE user_id = ? 
        GROUP BY activity_type 
        ORDER BY count DESC
    ");
    $stmt->execute([$user_id]);
    $activity_types = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching activity types: " . $e->getMessage());
}

$page_title = 'Activity Log';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.activity-container {
    max-width: 1200px;
    margin: 0 auto;
}
.activity-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.activity-header h2 {
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0;
}
.activity-header p {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin: 0;
}
.activity-filters {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.activity-filters select,
.activity-filters input {
    padding: 8px 12px;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
}
.activity-filters .btn-filter {
    padding: 8px 16px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.8rem;
    cursor: pointer;
}
.activity-filters .btn-filter:hover {
    background: #1D4ED8;
}
.activity-filters .btn-reset {
    padding: 8px 16px;
    background: var(--gray-200);
    color: var(--gray-700);
    border: none;
    border-radius: 8px;
    font-size: 0.8rem;
    cursor: pointer;
    text-decoration: none;
}
.activity-filters .btn-reset:hover {
    background: var(--gray-300);
}

.activity-list {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--gray-100);
    transition: var(--transition);
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-item:hover {
    background: var(--gray-50);
}
.activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.activity-icon.login { background: #DBEAFE; color: #2563EB; }
.activity-icon.system { background: #EDE9FE; color: #7C3AED; }
.activity-icon.result { background: #D1FAE5; color: #059669; }
.activity-icon.incident { background: #FEE2E2; color: #DC2626; }
.activity-icon.user { background: #FEF3C7; color: #D97706; }
.activity-icon.profile { background: #E0F2FE; color: #0284C7; }
.activity-icon.security { background: #FCE4EC; color: #C62828; }
.activity-icon.broadcast { background: #F3E8FF; color: #7C3AED; }
.activity-content {
    flex: 1;
    min-width: 0;
}
.activity-content .title {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--gray-800);
}
.activity-content .description {
    font-size: 0.8rem;
    color: var(--gray-600);
    margin-top: 2px;
}
.activity-content .meta {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
    display: flex;
    gap: 16px;
    align-items: center;
}
.activity-content .meta .badge-type {
    display: inline-block;
    padding: 1px 10px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
    background: var(--gray-100);
    color: var(--gray-600);
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    padding: 20px;
}
.pagination a,
.pagination span {
    padding: 6px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    font-size: 0.8rem;
    text-decoration: none;
    color: var(--gray-700);
    transition: var(--transition);
}
.pagination a:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
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
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 16px;
}
.empty-state h3 {
    font-size: 1.1rem;
    color: var(--gray-700);
    margin: 0 0 8px 0;
}

@media (max-width: 768px) {
    .activity-header {
        flex-direction: column;
        align-items: stretch;
    }
    .activity-filters {
        flex-wrap: wrap;
    }
    .activity-filters select,
    .activity-filters input {
        flex: 1;
        min-width: 120px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="activity-container">
            <div class="activity-header">
                <div>
                    <h2><i class="fas fa-history"></i> Activity Log</h2>
                    <p>Your recent activities and actions</p>
                </div>
                <div class="activity-filters">
                    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <select name="type">
                            <option value="">All Types</option>
                            <?php foreach ($activity_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['activity_type']); ?>" 
                                    <?php echo $type_filter === $type['activity_type'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $type['activity_type'])); ?>
                                    (<?php echo $type['count']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <?php if (!empty($type_filter) || !empty($date_filter)): ?>
                            <a href="profile-activity.php" class="btn-reset">
                                <i class="fas fa-times"></i> Reset
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="activity-list">
                <?php if (count($activities) > 0): ?>
                    <?php foreach ($activities as $activity): ?>
                        <?php 
                            $icon_class = 'system';
                            $icon_icon = 'fa-cog';
                            
                            if (strpos($activity['activity_type'] ?? '', 'login') !== false) {
                                $icon_class = 'login';
                                $icon_icon = 'fa-sign-in-alt';
                            } elseif (strpos($activity['activity_type'] ?? '', 'logout') !== false) {
                                $icon_class = 'login';
                                $icon_icon = 'fa-sign-out-alt';
                            } elseif (strpos($activity['activity_type'] ?? '', 'result') !== false) {
                                $icon_class = 'result';
                                $icon_icon = 'fa-file-alt';
                            } elseif (strpos($activity['activity_type'] ?? '', 'incident') !== false) {
                                $icon_class = 'incident';
                                $icon_icon = 'fa-exclamation-triangle';
                            } elseif (strpos($activity['activity_type'] ?? '', 'user') !== false) {
                                $icon_class = 'user';
                                $icon_icon = 'fa-user';
                            } elseif (strpos($activity['activity_type'] ?? '', 'profile') !== false) {
                                $icon_class = 'profile';
                                $icon_icon = 'fa-user-edit';
                            } elseif (strpos($activity['activity_type'] ?? '', 'broadcast') !== false) {
                                $icon_class = 'broadcast';
                                $icon_icon = 'fa-bullhorn';
                            } elseif (strpos($activity['activity_type'] ?? '', 'password') !== false || 
                                      strpos($activity['activity_type'] ?? '', 'security') !== false) {
                                $icon_class = 'security';
                                $icon_icon = 'fa-shield-alt';
                            }
                        ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $icon_class; ?>">
                                <i class="fas <?php echo $icon_icon; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="title">
                                    <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'] ?? 'Action')); ?>
                                </div>
                                <div class="description">
                                    <?php echo htmlspecialchars($activity['description'] ?? ''); ?>
                                </div>
                                <div class="meta">
                                    <span>
                                        <i class="fas fa-clock"></i> 
                                        <?php echo date('M d, Y g:i A', strtotime($activity['created_at'] ?? 'now')); ?>
                                    </span>
                                    <?php if (!empty($activity['ip_address'])): ?>
                                        <span>
                                            <i class="fas fa-network-wired"></i> 
                                            <?php echo htmlspecialchars($activity['ip_address']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge-type">
                                        <?php echo $activity['activity_type'] ?? 'system'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No activities found</h3>
                        <p>Your activities will appear here as you use the system.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&type=<?php echo urlencode($type_filter); ?>&date=<?php echo urlencode($date_filter); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&type=<?php echo urlencode($type_filter); ?>&date=<?php echo urlencode($date_filter); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&type=<?php echo urlencode($type_filter); ?>&date=<?php echo urlencode($date_filter); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Sidebar toggle functions
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

window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>