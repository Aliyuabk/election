<?php
// ============================================================
// SENATORIAL COORDINATOR - SEARCH BROADCASTS
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

$user_name = SessionManager::get('user_name', 'Coordinator');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET SEARCH PARAMETERS
// ============================================================
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// ============================================================
// BUILD SEARCH QUERY
// ============================================================
$where_conditions = ["tenant_id = ?"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $where_conditions[] = "status = ?";
    $params[] = $status;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $where_conditions);

// ============================================================
// GET SEARCH RESULTS
// ============================================================
$broadcasts = [];
$total_results = 0;

try {
    // Get total count
    $count_query = "
        SELECT COUNT(*) as total
        FROM broadcasts
        WHERE $where_clause
    ";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_results = $stmt->fetchColumn();

    // Get results
    $query = "
        SELECT b.*, u.full_name as sender_name
        FROM broadcasts b
        LEFT JOIN users u ON b.sender_id = u.id
        WHERE $where_clause
        ORDER BY b.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($query);
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $broadcasts = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error searching broadcasts: " . $e->getMessage());
}

$total_pages = ceil($total_results / $per_page);

$page_title = 'Search Broadcasts';
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
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.search-box {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.search-box input[type="text"],
.search-box input[type="date"] {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
    flex: 1;
    min-width: 150px;
}
.search-box input:focus {
    outline: none;
    border-color: var(--primary);
}
.search-box select {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
    min-width: 120px;
}
.search-box .btn-search {
    padding: 8px 24px;
    border: none;
    border-radius: 8px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
}
.search-box .btn-search:hover {
    background: var(--primary-dark);
}
.search-box .btn-reset {
    padding: 8px 20px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
}
.search-box .btn-reset:hover {
    background: var(--gray-50);
}

.results-stats {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-bottom: 16px;
}

.broadcast-item {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 12px;
    transition: var(--transition);
}
.broadcast-item:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}
.broadcast-item .item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.broadcast-item .item-header .title {
    font-weight: 600;
    font-size: 0.95rem;
}
.broadcast-item .item-header .status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.broadcast-item .item-header .status-badge.sent { background: #D1FAE5; color: #059669; }
.broadcast-item .item-header .status-badge.scheduled { background: #DBEAFE; color: #2563EB; }
.broadcast-item .item-header .status-badge.draft { background: var(--gray-200); color: var(--gray-600); }
.broadcast-item .item-header .status-badge.failed { background: #FEE2E2; color: #DC2626; }

.broadcast-item .item-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 6px;
}
.broadcast-item .item-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.broadcast-item .item-message {
    color: var(--gray-600);
    font-size: 0.85rem;
    margin: 8px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.broadcast-item .item-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 8px;
}

.btn-sm {
    padding: 4px 12px;
    border-radius: 6px;
    background: var(--primary);
    color: white;
    text-decoration: none;
    font-size: 0.7rem;
    border: none;
}
.btn-sm:hover {
    background: var(--primary-dark);
    color: white;
}
.btn-sm.outline {
    background: transparent;
    border: 1px solid var(--gray-300);
    color: var(--gray-600);
}
.btn-sm.outline:hover {
    background: var(--gray-50);
}

.pagination {
    display: flex;
    gap: 6px;
    justify-content: center;
    margin-top: 16px;
    flex-wrap: wrap;
}
.pagination a, .pagination span {
    padding: 6px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    text-decoration: none;
    color: var(--gray-600);
    font-size: 0.8rem;
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
    cursor: not-allowed;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

@media (max-width: 768px) {
    .search-box {
        flex-direction: column;
    }
    .search-box input,
    .search-box select {
        width: 100%;
        min-width: unset;
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
                    <i class="fas fa-search" style="color:var(--primary);margin-right:8px;"></i> 
                    Search Broadcasts
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="broadcasts.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back to Broadcasts
                </a>
            </div>
        </div>

        <!-- Search Form -->
        <div class="search-box">
            <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <input type="text" name="q" placeholder="Search by title or message..." value="<?php echo htmlspecialchars($search); ?>" style="flex:2;">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="draft" <?php echo ($status === 'draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="sent" <?php echo ($status === 'sent') ? 'selected' : ''; ?>>Sent</option>
                    <option value="scheduled" <?php echo ($status === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="failed" <?php echo ($status === 'failed') ? 'selected' : ''; ?>>Failed</option>
                </select>
                <input type="date" name="date_from" placeholder="From" value="<?php echo htmlspecialchars($date_from); ?>">
                <input type="date" name="date_to" placeholder="To" value="<?php echo htmlspecialchars($date_to); ?>">
                <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                <a href="broadcasts-search.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Results -->
        <?php if (!empty($search) || !empty($status) || !empty($date_from) || !empty($date_to)): ?>
            <div class="results-stats">
                Found <?php echo number_format($total_results); ?> result(s)
                <?php if (!empty($search)): ?>
                    for "<?php echo htmlspecialchars($search); ?>"
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (count($broadcasts) > 0): ?>
            <?php foreach ($broadcasts as $broadcast): ?>
                <div class="broadcast-item">
                    <div class="item-header">
                        <div class="title"><?php echo htmlspecialchars($broadcast['title']); ?></div>
                        <span class="status-badge <?php echo $broadcast['status']; ?>">
                            <?php echo ucfirst($broadcast['status']); ?>
                        </span>
                    </div>
                    <div class="item-meta">
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($broadcast['sender_name'] ?? 'System'); ?></span>
                        <span><i class="fas fa-users"></i> <?php echo number_format($broadcast['total_recipients'] ?? 0); ?> recipients</span>
                        <span><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($broadcast['created_at'])); ?></span>
                        <?php if ($broadcast['scheduled_at']): ?>
                            <span><i class="fas fa-calendar"></i> Scheduled: <?php echo date('M j, Y g:i A', strtotime($broadcast['scheduled_at'])); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="item-message">
                        <?php echo htmlspecialchars(substr($broadcast['message'], 0, 200)); ?>
                        <?php if (strlen($broadcast['message']) > 200): ?>...<?php endif; ?>
                    </div>
                    <div class="item-actions">
                        <a href="broadcasts-view.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm">View</a>
                        <?php if ($broadcast['status'] === 'draft'): ?>
                            <a href="broadcasts-edit.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm outline">Edit</a>
                            <a href="broadcasts-send.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm" style="background:#10B981;">Send</a>
                        <?php endif; ?>
                        <?php if ($broadcast['status'] === 'scheduled'): ?>
                            <a href="broadcasts-edit.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm outline">Edit</a>
                            <a href="broadcasts-cancel.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm" style="background:#F59E0B;">Cancel</a>
                        <?php endif; ?>
                        <?php if ($broadcast['status'] === 'sent'): ?>
                            <a href="broadcasts-history.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm outline">History</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php elseif (!empty($search) || !empty($status) || !empty($date_from) || !empty($date_to)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <p>No broadcasts found matching your search criteria.</p>
                <p style="font-size:0.8rem;margin-top:4px;">Try adjusting your search terms or filters.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>Search for Broadcasts</h3>
                <p>Enter search terms above to find broadcasts by title, message, status, or date range.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
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