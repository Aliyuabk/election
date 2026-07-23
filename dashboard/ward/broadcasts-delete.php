<?php
// ============================================================
// WARD COORDINATOR - VIEW BROADCASTS
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
// FETCH BROADCASTS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$broadcasts = [];
$total_broadcasts = 0;

try {
    // Build query conditions
    $conditions = "b.tenant_id = ? AND b.sender_id = ?";
    $params = [$tenant_id, $user_id];
    
    if ($status_filter !== 'all') {
        $conditions .= " AND b.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $conditions .= " AND (b.title LIKE ? OR b.message LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Get total count
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM broadcasts b WHERE $conditions");
    $count_stmt->execute($params);
    $total_broadcasts = (int)$count_stmt->fetchColumn();
    
    // Get broadcasts
    $stmt = $db->prepare("
        SELECT 
            b.*,
            u.full_name as sender_name
        FROM broadcasts b
        LEFT JOIN users u ON b.sender_id = u.id
        WHERE $conditions
        ORDER BY b.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching broadcasts: " . $e->getMessage());
}

// ============================================================
// FETCH BROADCAST STATISTICS
// ============================================================
$broadcast_stats = [
    'total' => 0,
    'draft' => 0,
    'scheduled' => 0,
    'sent' => 0,
    'failed' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM broadcasts 
        WHERE tenant_id = ? AND sender_id = ?
    ");
    $stmt->execute([$tenant_id, $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $broadcast_stats['total'] = (int)($stats['total'] ?? 0);
    $broadcast_stats['draft'] = (int)($stats['draft'] ?? 0);
    $broadcast_stats['scheduled'] = (int)($stats['scheduled'] ?? 0);
    $broadcast_stats['sent'] = (int)($stats['sent'] ?? 0);
    $broadcast_stats['failed'] = (int)($stats['failed'] ?? 0);
    
} catch (Exception $e) {
    error_log("Error fetching broadcast stats: " . $e->getMessage());
}

$page_title = 'Broadcasts';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.broadcast-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.broadcast-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.broadcast-header h2 i {
    color: var(--primary);
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
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
.stat-mini .number.yellow { color: #F59E0B; }
.stat-mini .number.red { color: #EF4444; }
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

.broadcast-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 12px;
    transition: var(--transition);
}
.broadcast-card:hover {
    box-shadow: var(--shadow-hover);
}
.broadcast-card .broadcast-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}
.broadcast-card .broadcast-title {
    font-weight: 600;
    font-size: 0.95rem;
}
.broadcast-card .broadcast-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: var(--gray-500);
}
.broadcast-card .broadcast-meta i {
    width: 14px;
}
.broadcast-card .broadcast-message {
    font-size: 0.85rem;
    color: var(--gray-600);
    margin: 8px 0;
    padding: 8px 12px;
    background: var(--gray-50);
    border-radius: 6px;
    max-height: 80px;
    overflow: hidden;
}
.broadcast-card .broadcast-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--gray-100);
}
.broadcast-card .broadcast-actions .btn-sm {
    padding: 4px 12px;
    font-size: 0.7rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.broadcast-card .broadcast-actions .btn-sm.view { background: #EFF6FF; color: #3B82F6; }
.broadcast-card .broadcast-actions .btn-sm.edit { background: #FEF3C7; color: #92400E; }
.broadcast-card .broadcast-actions .btn-sm.send { background: #D1FAE5; color: #065F46; }
.broadcast-card .broadcast-actions .btn-sm.delete { background: #FEE2E2; color: #991B1B; }
.broadcast-card .broadcast-actions .btn-sm.duplicate { background: #F5F3FF; color: #6D28D9; }
.broadcast-card .broadcast-actions .btn-sm.stats { background: #EFF6FF; color: #3B82F6; }

.badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.badge.draft { background: #E5E7EB; color: #374151; }
.badge.scheduled { background: #DBEAFE; color: #1E40AF; }
.badge.sent { background: #D1FAE5; color: #065F46; }
.badge.failed { background: #FEE2E2; color: #991B1B; }
.badge.cancelled { background: #E5E7EB; color: #374151; }

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

.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    border: 1px solid #D1FAE5;
    color: #065F46;
}
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 1.1rem;
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
    .broadcast-card .broadcast-top {
        flex-direction: column;
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
        <div class="broadcast-header">
            <div>
                <h2><i class="fas fa-bullhorn"></i> Broadcasts</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • 
                    <?php echo number_format($broadcast_stats['total']); ?> broadcasts
                </p>
            </div>
            <div>
                <a href="broadcasts-create.php" class="btn-primary-sm">
                    <i class="fas fa-plus"></i> Create Broadcast
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> 
                <?php 
                    $errors = [
                        'notfound' => 'Broadcast not found.',
                        'already_sent' => 'This broadcast has already been sent and cannot be modified.',
                        'db' => 'Database error occurred.'
                    ];
                    echo htmlspecialchars($errors[$_GET['error']] ?? 'An error occurred.');
                ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($broadcast_stats['total']); ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-mini">
                <div class="number yellow"><?php echo number_format($broadcast_stats['draft']); ?></div>
                <div class="label">Drafts</div>
            </div>
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($broadcast_stats['scheduled']); ?></div>
                <div class="label">Scheduled</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($broadcast_stats['sent']); ?></div>
                <div class="label">Sent</div>
            </div>
            <div class="stat-mini">
                <div class="number red"><?php echo number_format($broadcast_stats['failed']); ?></div>
                <div class="label">Failed</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search broadcasts..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select id="statusFilter">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button onclick="resetFilters()" class="btn-secondary-sm" style="background: var(--gray-100);">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Broadcast List -->
        <?php if (count($broadcasts) > 0): ?>
            <?php foreach ($broadcasts as $broadcast): 
                $is_scheduled = $broadcast['status'] === 'scheduled';
                $is_draft = $broadcast['status'] === 'draft';
                $is_sent = $broadcast['status'] === 'sent';
            ?>
                <div class="broadcast-card">
                    <div class="broadcast-top">
                        <div>
                            <div class="broadcast-title"><?php echo htmlspecialchars($broadcast['title']); ?></div>
                            <div class="broadcast-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($broadcast['sender_name'] ?? 'You'); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($broadcast['created_at'])); ?></span>
                                <?php if ($broadcast['sent_at']): ?>
                                    <span><i class="fas fa-check-circle" style="color:#10B981;"></i> <?php echo date('M d, Y H:i', strtotime($broadcast['sent_at'])); ?></span>
                                <?php endif; ?>
                                <?php if ($broadcast['scheduled_at']): ?>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($broadcast['scheduled_at'])); ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-users"></i> <?php echo number_format($broadcast['total_recipients'] ?? 0); ?> recipients</span>
                                <span><i class="fas fa-eye"></i> <?php echo number_format($broadcast['read_count'] ?? 0); ?> read</span>
                            </div>
                        </div>
                        <span class="badge <?php echo $broadcast['status']; ?>">
                            <?php echo ucfirst($broadcast['status'] ?? 'Unknown'); ?>
                        </span>
                    </div>

                    <?php if (!empty($broadcast['message'])): ?>
                        <div class="broadcast-message">
                            <?php echo nl2br(htmlspecialchars(substr($broadcast['message'], 0, 300))); ?>
                            <?php if (strlen($broadcast['message']) > 300): ?>...<?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="broadcast-actions">
                        <a href="broadcasts-view.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm view">
                            <i class="fas fa-eye"></i> View
                        </a>
                        
                        <?php if ($is_draft): ?>
                            <a href="broadcasts-edit.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="broadcasts-send.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm send">
                                <i class="fas fa-paper-plane"></i> Send
                            </a>
                            <a href="broadcasts-delete.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm delete" onclick="return confirmDelete()">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($is_scheduled): ?>
                            <a href="broadcasts-edit.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="broadcasts-cancel.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm delete" onclick="return confirm('Cancel this scheduled broadcast?')">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($is_sent): ?>
                            <a href="broadcasts-duplicate.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm duplicate">
                                <i class="fas fa-copy"></i> Duplicate
                            </a>
                            <a href="broadcasts-stats.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm stats">
                                <i class="fas fa-chart-bar"></i> Stats
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php 
            $total_pages = ceil($total_broadcasts / $limit);
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

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bullhorn"></i>
                <h4>No Broadcasts Found</h4>
                <p>You haven't created any broadcasts yet.</p>
                <a href="broadcasts-create.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-plus"></i> Create Your First Broadcast
                </a>
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

// Confirm delete
function confirmDelete() {
    return confirm('Are you sure you want to delete this broadcast? This action cannot be undone.');
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