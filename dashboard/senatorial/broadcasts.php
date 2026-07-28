<?php
// ============================================================
// SENATORIAL COORDINATOR - BROADCASTS
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
$user_id = SessionManager::get('user_id');
$senatorial_id = SessionManager::get('senatorial_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET SENATORIAL DISTRICT NAME
// ============================================================
$district_name = 'Senatorial District';
$state_name = 'State';
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("
            SELECT s.name as state_name, sd.name as district_name 
            FROM senatorial_districts sd 
            JOIN states s ON sd.state_id = s.id 
            WHERE sd.id = ?
        ");
        $stmt->execute([$senatorial_id]);
        $result = $stmt->fetch();
        if ($result) {
            $district_name = $result['district_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching district: " . $e->getMessage());
}

// ============================================================
// GET FILTERS
// ============================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// ============================================================
// BUILD QUERY
// ============================================================
$where_conditions = ["tenant_id = ?"];
$params = [$tenant_id];

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(" AND ", $where_conditions);

// ============================================================
// GET BROADCASTS
// ============================================================
$broadcasts = [];
$total_broadcasts = 0;

try {
    // Get total count
    $count_query = "
        SELECT COUNT(*) as total
        FROM broadcasts
        WHERE $where_clause
    ";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_broadcasts = $stmt->fetchColumn();

    // Get broadcasts
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
    error_log("Error fetching broadcasts: " . $e->getMessage());
}

$total_pages = ceil($total_broadcasts / $per_page);

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'sent' => 0,
    'draft' => 0,
    'scheduled' => 0,
    'failed' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM broadcasts
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $stats = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching broadcast stats: " . $e->getMessage());
}

$page_title = 'Broadcasts';
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 18px 20px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-card .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-card .stat-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-card .stat-icon {
    font-size: 1.2rem;
    margin-bottom: 4px;
}
.stat-card.blue .stat-number { color: #2563EB; }
.stat-card.blue .stat-icon { color: #2563EB; }
.stat-card.green .stat-number { color: #059669; }
.stat-card.green .stat-icon { color: #059669; }
.stat-card.yellow .stat-number { color: #D97706; }
.stat-card.yellow .stat-icon { color: #D97706; }
.stat-card.orange .stat-number { color: #EA580C; }
.stat-card.orange .stat-icon { color: #EA580C; }
.stat-card.red .stat-number { color: #DC2626; }
.stat-card.red .stat-icon { color: #DC2626; }

.filters-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filters-row select,
.filters-row input {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
}
.filters-row select:focus,
.filters-row input:focus {
    outline: none;
    border-color: var(--primary);
}
.filters-row .btn-filter {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
}
.filters-row .btn-filter:hover {
    background: var(--primary-dark);
}
.filters-row .btn-reset {
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
.filters-row .btn-reset:hover {
    background: var(--gray-50);
}

.section-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.section-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.section-card .card-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}

.broadcast-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 12px;
    transition: var(--transition);
}
.broadcast-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}
.broadcast-card .broadcast-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
}
.broadcast-card .broadcast-header .title {
    font-size: 1rem;
    font-weight: 600;
}
.broadcast-card .broadcast-header .title .badge-draft {
    font-size: 0.65rem;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 20px;
    background: var(--gray-200);
    color: var(--gray-600);
}
.broadcast-card .broadcast-header .title .badge-sent {
    font-size: 0.65rem;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 20px;
    background: #D1FAE5;
    color: #059669;
}
.broadcast-card .broadcast-header .title .badge-scheduled {
    font-size: 0.65rem;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 20px;
    background: #DBEAFE;
    color: #2563EB;
}
.broadcast-card .broadcast-header .title .badge-failed {
    font-size: 0.65rem;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 20px;
    background: #FEE2E2;
    color: #DC2626;
}
.broadcast-card .broadcast-message {
    color: var(--gray-600);
    font-size: 0.85rem;
    margin: 8px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.broadcast-card .broadcast-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.7rem;
    color: var(--gray-400);
}
.broadcast-card .broadcast-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}
.broadcast-card .broadcast-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
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
.btn-sm.danger {
    background: #FEE2E2;
    color: #DC2626;
}
.btn-sm.danger:hover {
    background: #FECACA;
}
.btn-sm.success {
    background: #D1FAE5;
    color: #059669;
}
.btn-sm.success:hover {
    background: #A7F3D0;
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
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .filters-row {
        flex-direction: column;
    }
    .filters-row select,
    .filters-row input {
        width: 100%;
    }
    .broadcast-card .broadcast-header {
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
                    <i class="fas fa-bullhorn" style="color:var(--primary);margin-right:8px;"></i> 
                    Broadcasts
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="broadcasts-create.php" class="btn btn-primary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:white;border:none;">
                    <i class="fas fa-plus"></i> New Broadcast
                </a>
                <a href="monitor-district.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-bullhorn"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Broadcasts</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['sent'] ?? 0); ?></div>
                <div class="stat-label">Sent</div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['draft'] ?? 0); ?></div>
                <div class="stat-label">Drafts</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-calendar-plus"></i></div>
                <div class="stat-number"><?php echo number_format($stats['scheduled'] ?? 0); ?></div>
                <div class="stat-label">Scheduled</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['failed'] ?? 0); ?></div>
                <div class="stat-label">Failed</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-row">
            <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="draft" <?php echo ($status_filter === 'draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="sent" <?php echo ($status_filter === 'sent') ? 'selected' : ''; ?>>Sent</option>
                    <option value="scheduled" <?php echo ($status_filter === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="failed" <?php echo ($status_filter === 'failed') ? 'selected' : ''; ?>>Failed</option>
                </select>
                <input type="text" name="search" placeholder="Search broadcasts..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="broadcasts.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Broadcasts List -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i> Broadcast List</h3>
                <span style="font-size:0.75rem;color:var(--gray-400);"><?php echo count($broadcasts); ?> broadcasts (<?php echo number_format($total_broadcasts); ?> total)</span>
            </div>
            <?php if (count($broadcasts) > 0): ?>
                <?php foreach ($broadcasts as $broadcast): 
                    $status_class = $broadcast['status'];
                    $status_label = ucfirst($broadcast['status']);
                ?>
                    <div class="broadcast-card">
                        <div class="broadcast-header">
                            <div class="title">
                                <?php echo htmlspecialchars($broadcast['title']); ?>
                                <span class="badge-<?php echo $broadcast['status']; ?>">
                                    <?php echo $status_label; ?>
                                </span>
                            </div>
                            <div class="broadcast-actions">
                                <?php if ($broadcast['status'] === 'draft'): ?>
                                    <a href="broadcasts-edit.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm outline">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="broadcasts-send.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm success">
                                        <i class="fas fa-paper-plane"></i> Send
                                    </a>
                                    <a href="broadcasts-delete.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm danger" onclick="return confirm('Delete this broadcast?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php elseif ($broadcast['status'] === 'scheduled'): ?>
                                    <a href="broadcasts-edit.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm outline">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="broadcasts-cancel.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm danger" onclick="return confirm('Cancel this scheduled broadcast?')">
                                        <i class="fas fa-calendar-times"></i> Cancel
                                    </a>
                                <?php elseif ($broadcast['status'] === 'sent'): ?>
                                    <a href="broadcasts-history.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm outline">
                                        <i class="fas fa-history"></i> History
                                    </a>
                                <?php endif; ?>
                                <a href="broadcasts-view.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                        <div class="broadcast-message">
                            <?php echo htmlspecialchars(substr($broadcast['message'], 0, 200)); ?>
                            <?php if (strlen($broadcast['message']) > 200): ?>...<?php endif; ?>
                        </div>
                        <div class="broadcast-meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($broadcast['sender_name'] ?? 'System'); ?></span>
                            <span><i class="fas fa-users"></i> <?php echo number_format($broadcast['total_recipients'] ?? 0); ?> recipients</span>
                            <span><i class="fas fa-eye"></i> <?php echo number_format($broadcast['read_count'] ?? 0); ?> read</span>
                            <span><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($broadcast['created_at'])); ?></span>
                            <?php if ($broadcast['scheduled_at']): ?>
                                <span><i class="fas fa-calendar"></i> Scheduled: <?php echo date('M j, Y g:i A', strtotime($broadcast['scheduled_at'])); ?></span>
                            <?php endif; ?>
                            <?php if ($broadcast['sent_at']): ?>
                                <span><i class="fas fa-paper-plane"></i> Sent: <?php echo date('M j, Y g:i A', strtotime($broadcast['sent_at'])); ?></span>
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
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bullhorn"></i>
                    <p>No broadcasts found.</p>
                    <p style="font-size:0.8rem;margin-top:4px;">Click "New Broadcast" to create your first broadcast.</p>
                </div>
            <?php endif; ?>
        </div>
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