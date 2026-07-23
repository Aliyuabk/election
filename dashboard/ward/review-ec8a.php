<?php
// ============================================================
// WARD COORDINATOR - REVIEW EC8A SUBMISSIONS
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
// FETCH PENDING EC8A SUBMISSIONS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$pu_filter = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;

$submissions = [];
$total_submissions = 0;

try {
    // Build query conditions
    $conditions = "r.tenant_id = ? AND pu.ward_id = ? AND r.status = 'pending'";
    $params = [$tenant_id, $ward_id];
    
    if ($pu_filter > 0) {
        $conditions .= " AND r.pu_id = ?";
        $params[] = $pu_filter;
    }
    
    if (!empty($search)) {
        $conditions .= " AND (r.pu_name LIKE ? OR r.pu_code LIKE ? OR u.full_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Get total count
    $count_stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        WHERE $conditions
    ");
    $count_stmt->execute($params);
    $total_submissions = (int)$count_stmt->fetchColumn();
    
    // Get submissions
    $stmt = $db->prepare("
        SELECT 
            r.*,
            u.full_name as agent_name,
            u.user_code as agent_code,
            u.phone as agent_phone,
            pu.name as pu_name,
            pu.code as pu_code,
            pu.registered_voters
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN users u ON r.agent_id = u.id
        WHERE $conditions
        ORDER BY r.created_at ASC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching EC8A submissions: " . $e->getMessage());
}

// ============================================================
// FETCH POLLING UNITS FOR FILTER
// ============================================================
$polling_units = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, code FROM polling_units 
        WHERE ward_id = ? AND is_active = 1 
        ORDER BY name ASC
    ");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'pending' => 0,
    'total_pus' => 0,
    'agents_with_pending' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as pending,
            COUNT(DISTINCT r.pu_id) as total_pus,
            COUNT(DISTINCT r.agent_id) as agents_with_pending
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        WHERE r.tenant_id = ? AND pu.ward_id = ? AND r.status = 'pending'
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['pending'] = (int)($stats_result['pending'] ?? 0);
    $stats['total_pus'] = (int)($stats_result['total_pus'] ?? 0);
    $stats['agents_with_pending'] = (int)($stats_result['agents_with_pending'] ?? 0);
    
} catch (Exception $e) {
    error_log("Error fetching EC8A stats: " . $e->getMessage());
}

$page_title = 'Review EC8A Submissions';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.review-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.review-header h2 i {
    color: var(--primary);
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
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
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .number.blue { color: #3B82F6; }
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
    min-width: 160px;
}

.submission-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 12px;
    transition: var(--transition);
}
.submission-card:hover {
    box-shadow: var(--shadow-hover);
}
.submission-card .card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}
.submission-card .card-title {
    font-weight: 600;
    font-size: 0.95rem;
}
.submission-card .card-title .pu-code {
    font-weight: 400;
    font-size: 0.7rem;
    color: var(--gray-400);
}
.submission-card .card-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: var(--gray-500);
}
.submission-card .card-meta i {
    width: 14px;
}
.submission-card .card-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 4px 12px;
    font-size: 0.82rem;
    color: var(--gray-600);
    margin: 8px 0;
    padding: 8px 12px;
    background: var(--gray-50);
    border-radius: 6px;
}
.submission-card .card-details .item {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
}
.submission-card .card-details .item .label {
    color: var(--gray-500);
}
.submission-card .card-details .item .value {
    font-weight: 500;
}
.submission-card .card-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--gray-100);
}
.submission-card .card-actions .btn-sm {
    padding: 4px 14px;
    font-size: 0.7rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.submission-card .card-actions .btn-sm.view { background: #EFF6FF; color: #3B82F6; }
.submission-card .card-actions .btn-sm.verify { background: #D1FAE5; color: #065F46; }
.submission-card .card-actions .btn-sm.reject { background: #FEE2E2; color: #991B1B; }
.submission-card .card-actions .btn-sm.flag { background: #FEF3C7; color: #92400E; }

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

.status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.status-badge.pending { background: #FEF3C7; color: #92400E; }

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
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .search-box {
        min-width: unset;
    }
    .submission-card .card-details {
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
        <div class="review-header">
            <div>
                <h2><i class="fas fa-file-upload"></i> Review EC8A Submissions</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • 
                    <?php echo number_format($stats['pending']); ?> pending submissions
                </p>
            </div>
            <div>
                <a href="verify-results.php" class="btn-secondary-sm">
                    <i class="fas fa-check-double"></i> Verification Dashboard
                </a>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($stats['pending']); ?></div>
                <div class="label">Pending Submissions</div>
            </div>
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($stats['total_pus']); ?></div>
                <div class="label">Polling Units</div>
                <div class="sub" style="font-size:0.55rem;color:var(--gray-400);">With pending results</div>
            </div>
            <div class="stat-mini">
                <div class="number purple"><?php echo number_format($stats['agents_with_pending']); ?></div>
                <div class="label">Agents</div>
                <div class="sub" style="font-size:0.55rem;color:var(--gray-400);">With pending submissions</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by PU, code or agent..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select id="puFilter">
                <option value="0" <?php echo $pu_filter === 0 ? 'selected' : ''; ?>>All Polling Units</option>
                <?php foreach ($polling_units as $pu): ?>
                    <option value="<?php echo $pu['id']; ?>" <?php echo $pu_filter === (int)$pu['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pu['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button onclick="resetFilters()" class="btn-secondary-sm" style="background: var(--gray-100);">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Submissions List -->
        <?php if (count($submissions) > 0): ?>
            <?php foreach ($submissions as $sub): 
                $party_votes = json_decode($sub['party_votes_json'] ?? '{}', true);
                $total_votes = array_sum($party_votes);
            ?>
                <div class="submission-card">
                    <div class="card-top">
                        <div>
                            <div class="card-title">
                                <?php echo htmlspecialchars($sub['pu_name']); ?>
                                <span class="pu-code">(<?php echo htmlspecialchars($sub['pu_code']); ?>)</span>
                            </div>
                            <div class="card-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($sub['agent_name']); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($sub['created_at'])); ?></span>
                                <span><i class="fas fa-users"></i> <?php echo number_format($sub['registered_voters'] ?? 0); ?> voters</span>
                            </div>
                        </div>
                        <span class="status-badge pending">Pending</span>
                    </div>

                    <div class="card-details">
                        <div class="item">
                            <span class="label">Valid Votes</span>
                            <span class="value"><?php echo number_format($sub['valid_votes'] ?? 0); ?></span>
                        </div>
                        <div class="item">
                            <span class="label">Rejected Votes</span>
                            <span class="value"><?php echo number_format($sub['rejected_votes'] ?? 0); ?></span>
                        </div>
                        <div class="item">
                            <span class="label">Total Votes</span>
                            <span class="value"><?php echo number_format($sub['total_votes_cast'] ?? 0); ?></span>
                        </div>
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
                        <a href="result-details.php?id=<?php echo $sub['id']; ?>" class="btn-sm view">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <a href="verify-results.php?result_id=<?php echo $sub['id']; ?>" class="btn-sm verify">
                            <i class="fas fa-check"></i> Verify
                        </a>
                        <a href="verify-results.php?result_id=<?php echo $sub['id']; ?>&action=reject" class="btn-sm reject">
                            <i class="fas fa-times"></i> Reject
                        </a>
                        <a href="verify-results.php?result_id=<?php echo $sub['id']; ?>&action=flag" class="btn-sm flag">
                            <i class="fas fa-flag"></i> Flag
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php 
            $total_pages = ceil($total_submissions / $limit);
            if ($total_pages > 1): 
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&pu_id=<?php echo $pu_filter; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&pu_id=<?php echo $pu_filter; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&pu_id=<?php echo $pu_filter; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color:#10B981;"></i>
                <h4>No Pending Submissions</h4>
                <p>All EC8A submissions have been reviewed. Great job!</p>
                <a href="verify-results.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-check-double"></i> View All Results
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Apply filters
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const pu = document.getElementById('puFilter').value;
    window.location.href = `?search=${encodeURIComponent(search)}&pu_id=${pu}`;
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('puFilter').value = '0';
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