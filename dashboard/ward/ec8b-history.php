<?php
// ============================================================
// WARD COORDINATOR - EC8B HISTORY
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
// FETCH EC8B HISTORY
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$ec8b_records = [];
$total_records = 0;

try {
    // Build query conditions
    $conditions = "e.tenant_id = ? AND e.ward_id = ?";
    $params = [$tenant_id, $ward_id];
    
    if ($status_filter !== 'all') {
        $conditions .= " AND e.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $conditions .= " AND (e.id LIKE ? OR u.full_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Get total count
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8b e WHERE $conditions");
    $count_stmt->execute($params);
    $total_records = (int)$count_stmt->fetchColumn();
    
    // Get records
    $stmt = $db->prepare("
        SELECT 
            e.*,
            u.full_name as coordinator_name,
            u.user_code as coordinator_code,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            verified_user.full_name as verified_by_name
        FROM results_ec8b e
        JOIN users u ON e.coordinator_id = u.id
        JOIN wards w ON e.ward_id = w.id
        JOIN lgas l ON e.lga_id = l.id
        JOIN states s ON e.state_id = s.id
        LEFT JOIN users verified_user ON e.verified_by = verified_user.id
        WHERE $conditions
        ORDER BY e.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $ec8b_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching EC8B history: " . $e->getMessage());
}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'pending' => 0,
    'verified' => 0,
    'rejected' => 0,
    'flagged' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM results_ec8b
        WHERE tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['total'] = (int)($stats_result['total'] ?? 0);
    $stats['pending'] = (int)($stats_result['pending'] ?? 0);
    $stats['verified'] = (int)($stats_result['verified'] ?? 0);
    $stats['rejected'] = (int)($stats_result['rejected'] ?? 0);
    $stats['flagged'] = (int)($stats_result['flagged'] ?? 0);
    
} catch (Exception $e) {
    error_log("Error fetching EC8B stats: " . $e->getMessage());
}

$page_title = 'EC8B History';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.ec8b-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.ec8b-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.ec8b-header h2 i {
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
.filter-bar select {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    min-width: 140px;
}

.ec8b-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 12px;
    transition: var(--transition);
}
.ec8b-card:hover {
    box-shadow: var(--shadow-hover);
}
.ec8b-card .card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}
.ec8b-card .card-title {
    font-weight: 600;
    font-size: 0.95rem;
}
.ec8b-card .card-title .id {
    font-weight: 400;
    font-size: 0.7rem;
    color: var(--gray-400);
}
.ec8b-card .card-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: var(--gray-500);
}
.ec8b-card .card-meta i {
    width: 14px;
}
.ec8b-card .card-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 4px 12px;
    font-size: 0.82rem;
    color: var(--gray-600);
    margin: 8px 0;
    padding: 8px 12px;
    background: var(--gray-50);
    border-radius: 6px;
}
.ec8b-card .card-details .item {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
}
.ec8b-card .card-details .item .label {
    color: var(--gray-500);
}
.ec8b-card .card-details .item .value {
    font-weight: 500;
}
.ec8b-card .card-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--gray-100);
}
.ec8b-card .card-actions .btn-sm {
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
.ec8b-card .card-actions .btn-sm.view { background: #EFF6FF; color: #3B82F6; }
.ec8b-card .card-actions .btn-sm.edit { background: #FEF3C7; color: #92400E; }
.ec8b-card .card-actions .btn-sm.verify { background: #D1FAE5; color: #065F46; }

.status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.status-badge.pending { background: #FEF3C7; color: #92400E; }
.status-badge.verified { background: #D1FAE5; color: #065F46; }
.status-badge.rejected { background: #FEE2E2; color: #991B1B; }
.status-badge.flagged { background: #FEF3C7; color: #92400E; }

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
        <div class="ec8b-header">
            <div>
                <h2><i class="fas fa-file-alt"></i> EC8B History</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • 
                    <?php echo number_format($stats['total']); ?> forms submitted
                </p>
            </div>
            <div>
                <a href="ec8b-create.php" class="btn-primary-sm">
                    <i class="fas fa-plus"></i> Create EC8B
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total Forms</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($stats['pending']); ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($stats['verified']); ?></div>
                <div class="label">Verified</div>
            </div>
            <div class="stat-mini">
                <div class="number red"><?php echo number_format($stats['rejected']); ?></div>
                <div class="label">Rejected</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($stats['flagged']); ?></div>
                <div class="label">Flagged</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by ID or coordinator..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select id="statusFilter">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="flagged" <?php echo $status_filter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
            </select>
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button onclick="resetFilters()" class="btn-secondary-sm" style="background: var(--gray-100);">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- EC8B List -->
        <?php if (count($ec8b_records) > 0): ?>
            <?php foreach ($ec8b_records as $record): 
                $party_votes = json_decode($record['party_votes_json'] ?? '{}', true);
                $total_votes = array_sum($party_votes);
                $calculated_total = json_decode($record['calculated_total_json'] ?? '{}', true);
            ?>
                <div class="ec8b-card">
                    <div class="card-top">
                        <div>
                            <div class="card-title">
                                EC8B Form #<?php echo $record['id']; ?>
                                <span class="id">(<?php echo htmlspecialchars($record['ward_name']); ?>)</span>
                            </div>
                            <div class="card-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($record['coordinator_name']); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($record['created_at'])); ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($record['lga_name']); ?></span>
                                <?php if ($record['verified_by_name']): ?>
                                    <span><i class="fas fa-check-circle" style="color:#10B981;"></i> Verified by <?php echo htmlspecialchars($record['verified_by_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $record['status']; ?>">
                            <?php echo ucfirst($record['status']); ?>
                        </span>
                    </div>

                    <div class="card-details">
                        <div class="item">
                            <span class="label">Total Valid Votes</span>
                            <span class="value"><?php echo number_format($record['valid_votes'] ?? 0); ?></span>
                        </div>
                        <div class="item">
                            <span class="label">Rejected Votes</span>
                            <span class="value"><?php echo number_format($record['rejected_votes'] ?? 0); ?></span>
                        </div>
                        <div class="item">
                            <span class="label">Total Votes</span>
                            <span class="value"><?php echo number_format($record['total_votes'] ?? 0); ?></span>
                        </div>
                        <?php if ($record['mismatch_alert'] ?? 0): ?>
                            <div class="item" style="grid-column:1/-1;color:#EF4444;">
                                <span class="label">⚠️ Mismatch Alert</span>
                                <span class="value">Calculated total does not match entered total</span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($party_votes)): ?>
                            <div class="item" style="grid-column:1/-1;border-top:1px solid var(--gray-200);padding-top:4px;">
                                <span class="label">Party Votes</span>
                                <span class="value">
                                    <?php 
                                    $party_strings = [];
                                    foreach ($party_votes as $party => $votes) {
                                        if ($votes > 0) {
                                            $party_strings[] = $party . ': ' . number_format($votes);
                                        }
                                    }
                                    echo implode(' | ', $party_strings);
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-actions">
                        <a href="ec8b-details.php?id=<?php echo $record['id']; ?>" class="btn-sm view">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <?php if ($record['status'] === 'pending'): ?>
                            <a href="ec8b-edit.php?id=<?php echo $record['id']; ?>" class="btn-sm edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="verify-ec8b.php?id=<?php echo $record['id']; ?>" class="btn-sm verify">
                                <i class="fas fa-check"></i> Verify
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php 
            $total_pages = ceil($total_records / $limit);
            if ($total_pages > 1): 
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h4>No EC8B Forms Found</h4>
                <p>No EC8B forms have been created for this ward yet.</p>
                <a href="ec8b-create.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-plus"></i> Create First EC8B
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