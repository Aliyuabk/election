<?php
// ============================================================
// WARD COORDINATOR - APPROVAL HISTORY
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
// FETCH APPROVAL HISTORY
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 30;
$offset = ($page - 1) * $limit;

$action_filter = isset($_GET['action']) ? $_GET['action'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$approvals = [];
$total_approvals = 0;

try {
    // Build query conditions
    $conditions = "a.tenant_id = ? AND pu.ward_id = ?";
    $params = [$tenant_id, $ward_id];
    
    if ($action_filter !== 'all') {
        $conditions .= " AND a.status = ?";
        $params[] = $action_filter;
    }
    
    if (!empty($date_from)) {
        $conditions .= " AND DATE(a.verified_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $conditions .= " AND DATE(a.verified_at) <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search)) {
        $conditions .= " AND (a.pu_name LIKE ? OR a.pu_code LIKE ? OR u.full_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Get total count
    $count_stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM results_ec8a a
        JOIN polling_units pu ON a.pu_id = pu.id
        WHERE $conditions
        AND a.status IN ('verified', 'rejected', 'flagged')
    ");
    $count_stmt->execute($params);
    $total_approvals = (int)$count_stmt->fetchColumn();
    
    // Get approvals
    $stmt = $db->prepare("
        SELECT 
            a.*,
            u.full_name as agent_name,
            u.user_code as agent_code,
            verified_user.full_name as verified_by_name,
            verified_user.user_code as verified_by_code,
            pu.name as pu_name,
            pu.code as pu_code
        FROM results_ec8a a
        JOIN polling_units pu ON a.pu_id = pu.id
        JOIN users u ON a.agent_id = u.id
        LEFT JOIN users verified_user ON a.verified_by = verified_user.id
        WHERE $conditions
        AND a.status IN ('verified', 'rejected', 'flagged')
        ORDER BY a.verified_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching approval history: " . $e->getMessage());
}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'verified' => 0,
    'rejected' => 0,
    'flagged' => 0,
    'total' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM results_ec8a a
        JOIN polling_units pu ON a.pu_id = pu.id
        WHERE a.tenant_id = ? AND pu.ward_id = ?
        AND a.status IN ('verified', 'rejected', 'flagged')
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['total'] = (int)($stats_result['total'] ?? 0);
    $stats['verified'] = (int)($stats_result['verified'] ?? 0);
    $stats['rejected'] = (int)($stats_result['rejected'] ?? 0);
    $stats['flagged'] = (int)($stats_result['flagged'] ?? 0);
    
} catch (Exception $e) {
    error_log("Error fetching approval stats: " . $e->getMessage());
}

$page_title = 'Approval History';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.approval-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.approval-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.approval-header h2 i {
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
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.red { color: #EF4444; }
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .number.blue { color: #3B82F6; }
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

.approval-item {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 14px 16px;
    margin-bottom: 10px;
    transition: var(--transition);
}
.approval-item:hover {
    box-shadow: var(--shadow-hover);
}
.approval-item .item-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
}
.approval-item .item-title {
    font-weight: 600;
    font-size: 0.9rem;
}
.approval-item .item-title .pu-code {
    font-weight: 400;
    font-size: 0.7rem;
    color: var(--gray-400);
}
.approval-item .item-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 4px;
}
.approval-item .item-meta i {
    width: 14px;
}
.approval-item .item-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 4px 12px;
    font-size: 0.82rem;
    color: var(--gray-600);
    margin: 8px 0;
    padding: 8px 12px;
    background: var(--gray-50);
    border-radius: 6px;
}
.approval-item .item-details .field {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
}
.approval-item .item-details .field .label {
    color: var(--gray-500);
}
.approval-item .item-details .field .value {
    font-weight: 500;
}
.approval-item .item-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--gray-100);
}
.approval-item .item-actions .btn-sm {
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
.approval-item .item-actions .btn-sm.view { background: #EFF6FF; color: #3B82F6; }
.approval-item .item-actions .btn-sm.undo { background: #FEF3C7; color: #92400E; }

.status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
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
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .search-box {
        min-width: unset;
    }
    .approval-item .item-details {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .stats-row {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="approval-header">
            <div>
                <h2><i class="fas fa-history"></i> Approval History</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • 
                    <?php echo number_format($stats['total']); ?> total approvals
                </p>
            </div>
            <div>
                <a href="verify-results.php" class="btn-secondary-sm">
                    <i class="fas fa-check-double"></i> Verify Results
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total</div>
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
                <input type="text" id="searchInput" placeholder="Search by PU, agent or code..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select id="actionFilter">
                <option value="all" <?php echo $action_filter === 'all' ? 'selected' : ''; ?>>All Actions</option>
                <option value="verified" <?php echo $action_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                <option value="rejected" <?php echo $action_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="flagged" <?php echo $action_filter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
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

        <!-- Approval List -->
        <?php if (count($approvals) > 0): ?>
            <?php foreach ($approvals as $approval): 
                $party_votes = json_decode($approval['party_votes_json'] ?? '{}', true);
                $total_votes = array_sum($party_votes);
            ?>
                <div class="approval-item">
                    <div class="item-top">
                        <div>
                            <div class="item-title">
                                <?php echo htmlspecialchars($approval['pu_name']); ?>
                                <span class="pu-code">(<?php echo htmlspecialchars($approval['pu_code']); ?>)</span>
                            </div>
                            <div class="item-meta">
                                <span><i class="fas fa-user"></i> Agent: <?php echo htmlspecialchars($approval['agent_name']); ?></span>
                                <span><i class="fas fa-user-check"></i> Verified by: <?php echo htmlspecialchars($approval['verified_by_name'] ?? 'N/A'); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($approval['verified_at'] ?? $approval['created_at'])); ?></span>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $approval['status']; ?>">
                            <?php echo ucfirst($approval['status']); ?>
                        </span>
                    </div>

                    <div class="item-details">
                        <div class="field">
                            <span class="label">Valid Votes</span>
                            <span class="value"><?php echo number_format($approval['valid_votes'] ?? 0); ?></span>
                        </div>
                        <div class="field">
                            <span class="label">Rejected Votes</span>
                            <span class="value"><?php echo number_format($approval['rejected_votes'] ?? 0); ?></span>
                        </div>
                        <div class="field">
                            <span class="label">Total Votes</span>
                            <span class="value"><?php echo number_format($approval['total_votes_cast'] ?? 0); ?></span>
                        </div>
                        <?php if (!empty($approval['rejection_reason'])): ?>
                            <div class="field" style="grid-column:1/-1;color:#EF4444;">
                                <span class="label">Note</span>
                                <span class="value"><?php echo htmlspecialchars($approval['rejection_reason']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="item-actions">
                        <a href="result-details.php?id=<?php echo $approval['id']; ?>" class="btn-sm view">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <?php if ($approval['status'] === 'rejected' || $approval['status'] === 'flagged'): ?>
                            <a href="verify-results.php?result_id=<?php echo $approval['id']; ?>" class="btn-sm undo">
                                <i class="fas fa-undo"></i> Review Again
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php 
            $total_pages = ceil($total_approvals / $limit);
            if ($total_pages > 1): 
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&action=<?php echo $action_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&action=<?php echo $action_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&action=<?php echo $action_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <h4>No Approval History</h4>
                <p>No results have been verified, rejected, or flagged yet.</p>
                <a href="verify-results.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-check-double"></i> Start Verifying
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Apply filters
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const action = document.getElementById('actionFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    window.location.href = `?search=${encodeURIComponent(search)}&action=${action}&date_from=${dateFrom}&date_to=${dateTo}`;
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('actionFilter').value = 'all';
    document.getElementById('dateFrom').value = '<?php echo date('Y-m-d', strtotime('-30 days')); ?>';
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