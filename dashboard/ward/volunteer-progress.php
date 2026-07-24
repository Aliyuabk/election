<?php
// ============================================================
// WARD COORDINATOR - VOLUNTEER PROGRESS (FIXED)
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
// FETCH VOLUNTEERS AND TASKS (FIXED)
// ============================================================
$volunteer_id = isset($_GET['volunteer_id']) ? (int)$_GET['volunteer_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$volunteers = [];
$tasks = [];
$summary = [];

try {
    // Get all volunteers - FIXED: Use role_id = 15
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.user_code,
            pu.name as pu_name,
            COUNT(vt.id) as total_tasks,
            SUM(CASE WHEN vt.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN vt.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
            SUM(CASE WHEN vt.status = 'pending' THEN 1 ELSE 0 END) as pending_tasks
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN volunteer_tasks vt ON vt.volunteer_id = u.id
        WHERE u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
        AND u.status = 'active'
        AND u.role_id = 15
        GROUP BY u.id, u.full_name, u.user_code, pu.name
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build conditions for tasks
    $conditions = "vt.tenant_id = ? AND vt.ward_id = ?";
    $params = [$tenant_id, $ward_id];
    
    if ($volunteer_id > 0) {
        $conditions .= " AND vt.volunteer_id = ?";
        $params[] = $volunteer_id;
    }
    
    if ($status_filter !== 'all') {
        $conditions .= " AND vt.status = ?";
        $params[] = $status_filter;
    }
    
    // Get tasks
    $stmt = $db->prepare("
        SELECT 
            vt.*,
            u.full_name as volunteer_name,
            u.user_code as volunteer_code,
            assigned.full_name as assigned_by_name
        FROM volunteer_tasks vt
        JOIN users u ON vt.volunteer_id = u.id
        LEFT JOIN users assigned ON vt.assigned_by = assigned.id
        WHERE $conditions
        ORDER BY vt.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Summary statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM volunteer_tasks
        WHERE tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching volunteer data: " . $e->getMessage());
}

$page_title = 'Volunteer Progress';
include '../includes/base.php';
include '../includes/sidebar.php';
?> 
<style>
.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.progress-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.progress-header h2 i {
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
    font-size: 1.2rem;
    font-weight: 700;
}
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.green { color: #10B981; }
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
.filter-bar select {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    min-width: 160px;
}

.volunteer-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 16px;
}
.volunteer-list {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    max-height: 500px;
    overflow-y: auto;
}
.volunteer-list .list-header {
    background: var(--gray-50);
    padding: 10px 16px;
    font-weight: 600;
    font-size: 0.8rem;
    border-bottom: 1px solid var(--gray-200);
}
.volunteer-list .list-item {
    padding: 10px 16px;
    border-bottom: 1px solid var(--gray-100);
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: block;
    color: var(--gray-800);
}
.volunteer-list .list-item:hover {
    background: var(--gray-50);
}
.volunteer-list .list-item.active {
    background: #EFF6FF;
    border-left: 3px solid #3B82F6;
}
.volunteer-list .list-item .name {
    font-weight: 500;
    font-size: 0.85rem;
}
.volunteer-list .list-item .sub {
    font-size: 0.7rem;
    color: var(--gray-500);
}
.volunteer-list .list-item .progress-bar {
    height: 4px;
    background: var(--gray-200);
    border-radius: 2px;
    margin-top: 6px;
    overflow: hidden;
}
.volunteer-list .list-item .progress-bar .fill {
    height: 100%;
    border-radius: 2px;
    background: #10B981;
    transition: width 0.3s ease;
}

.task-list {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.task-list .list-header {
    background: var(--gray-50);
    padding: 10px 16px;
    font-weight: 600;
    font-size: 0.8rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.task-list .list-body {
    max-height: 450px;
    overflow-y: auto;
}
.task-item {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
}
.task-item:last-child {
    border-bottom: none;
}
.task-item .task-title {
    font-weight: 500;
    font-size: 0.85rem;
}
.task-item .task-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 0.7rem;
    color: var(--gray-500);
    margin-top: 4px;
}
.task-item .task-meta i {
    width: 14px;
}
.task-item .task-description {
    font-size: 0.8rem;
    color: var(--gray-600);
    margin-top: 4px;
}
.task-item .task-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
}
.task-item .task-actions .btn-sm {
    padding: 2px 10px;
    font-size: 0.65rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
}
.task-item .task-actions .btn-sm.update { background: #DBEAFE; color: #1E40AF; }
.task-item .task-actions .btn-sm.complete { background: #D1FAE5; color: #065F46; }

.status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.status-badge.pending { background: #FEF3C7; color: #92400E; }
.status-badge.in_progress { background: #DBEAFE; color: #1E40AF; }
.status-badge.completed { background: #D1FAE5; color: #065F46; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-400);
}
.empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 8px;
}
.empty-state p {
    font-size: 0.85rem;
    margin: 0;
}

@media (max-width: 1024px) {
    .volunteer-grid {
        grid-template-columns: 1fr;
    }
    .volunteer-list {
        max-height: 200px;
    }
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="progress-header">
            <div>
                <h2><i class="fas fa-chart-line"></i> Volunteer Progress</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div>
                <a href="assign-tasks.php" class="btn-primary-sm">
                    <i class="fas fa-plus"></i> Assign Task
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($summary['total_tasks'] ?? 0); ?></div>
                <div class="label">Total Tasks</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($summary['completed'] ?? 0); ?></div>
                <div class="label">Completed</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($summary['in_progress'] ?? 0); ?></div>
                <div class="label">In Progress</div>
            </div>
            <div class="stat-mini">
                <div class="number purple"><?php echo number_format($summary['pending'] ?? 0); ?></div>
                <div class="label">Pending</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <select id="statusFilter" onchange="applyFilters()">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button onclick="resetFilters()" class="btn-secondary-sm" style="background: var(--gray-100);">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Volunteer Grid -->
        <div class="volunteer-grid">
            <!-- Volunteer List -->
            <div class="volunteer-list">
                <div class="list-header">
                    <i class="fas fa-users"></i> Volunteers
                    <span style="font-weight:400;font-size:0.7rem;color:var(--gray-500);"><?php echo count($volunteers); ?></span>
                </div>
                <?php if (count($volunteers) > 0): ?>
                    <?php foreach ($volunteers as $vol): 
                        $total = $vol['total_tasks'] ?? 0;
                        $completed = $vol['completed_tasks'] ?? 0;
                        $percent = $total > 0 ? round(($completed / $total) * 100) : 0;
                    ?>
                        <a href="?volunteer_id=<?php echo $vol['id']; ?>&status=<?php echo $status_filter; ?>" 
                           class="list-item <?php echo ($volunteer_id == $vol['id'] || ($volunteer_id == 0 && $loop->first)) ? 'active' : ''; ?>">
                            <div class="name"><?php echo htmlspecialchars($vol['full_name']); ?></div>
                            <div class="sub">
                                <?php echo htmlspecialchars($vol['user_code']); ?>
                                <?php if (!empty($vol['pu_name'])): ?>
                                    • <?php echo htmlspecialchars($vol['pu_name']); ?>
                                <?php endif; ?>
                                • <?php echo number_format($completed); ?>/<?php echo number_format($total); ?> tasks
                            </div>
                            <div class="progress-bar">
                                <div class="fill" style="width: <?php echo $percent; ?>%;"></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No volunteers found</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Task List -->
            <div class="task-list">
                <div class="list-header">
                    <span><i class="fas fa-list"></i> Tasks</span>
                    <span style="font-weight:400;font-size:0.7rem;color:var(--gray-500);"><?php echo count($tasks); ?> tasks</span>
                </div>
                <div class="list-body">
                    <?php if (count($tasks) > 0): ?>
                        <?php foreach ($tasks as $task): ?>
                            <div class="task-item">
                                <div class="task-title">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                    <span class="status-badge <?php echo $task['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                    </span>
                                </div>
                                <?php if (!empty($task['description'])): ?>
                                    <div class="task-description">
                                        <?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?>
                                        <?php if (strlen($task['description']) > 100): ?>...<?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="task-meta">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($task['volunteer_name']); ?></span>
                                    <span><i class="fas fa-user-check"></i> Assigned by <?php echo htmlspecialchars($task['assigned_by_name'] ?? 'System'); ?></span>
                                    <?php if (!empty($task['location'])): ?>
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($task['location']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($task['due_date']): ?>
                                        <span><i class="fas fa-clock"></i> Due: <?php echo date('M d, Y H:i', strtotime($task['due_date'])); ?></span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($task['assigned_date'] ?? $task['created_at'])); ?></span>
                                </div>
                                <div class="task-actions">
                                    <a href="volunteer-task-update.php?id=<?php echo $task['id']; ?>" class="btn-sm update">
                                        <i class="fas fa-edit"></i> Update
                                    </a>
                                    <?php if ($task['status'] !== 'completed'): ?>
                                        <a href="volunteer-task-complete.php?id=<?php echo $task['id']; ?>" class="btn-sm complete" onclick="return confirm('Mark this task as completed?')">
                                            <i class="fas fa-check"></i> Complete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <p>No tasks found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Apply filters
function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const volunteer = document.getElementById('volunteerSelect')?.value || 0;
    window.location.href = `?volunteer_id=${volunteer}&status=${status}`;
}

// Reset filters
function resetFilters() {
    document.getElementById('statusFilter').value = 'all';
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