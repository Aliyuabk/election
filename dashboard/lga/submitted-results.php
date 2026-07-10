<?php
// ============================================================
// LGA COORDINATOR - VIEW SUBMITTED RESULTS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'lga') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'LGA Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$lga_id = SessionManager::get('lga_id');

if (empty($lga_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT lga_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['lga_id'])) {
            $lga_id = $user['lga_id'];
            SessionManager::set('lga_id', $lga_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching lga_id: " . $e->getMessage());
    }
}

$db = getDB();

// Filters
$ward_filter = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get LGA name
$lga_name = 'LGA';
try {
    if ($lga_id) {
        $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA: " . $e->getMessage());
}

// Get wards for filter
$wards = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}

// Fetch submitted results (EC8A)
$results = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'verified' => 0,
    'approved' => 0,
    'rejected' => 0,
    'flagged' => 0
];

try {
    $sql = "
        SELECT 
            r.id,
            r.pu_id,
            r.valid_votes,
            r.total_votes_cast,
            r.status,
            r.created_at,
            r.verified_at,
            r.remarks,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            w.id as ward_id,
            u.first_name as agent_first_name,
            u.last_name as agent_last_name,
            e.name as election_name
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN elections e ON r.election_id = e.id
        WHERE r.tenant_id = ? AND w.lga_id = ?
    ";
    $params = [$tenant_id, $lga_id];
    
    if ($ward_filter > 0) {
        $sql .= " AND w.id = ?";
        $params[] = $ward_filter;
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND r.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $sql .= " AND (pu.name LIKE ? OR pu.code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY r.created_at DESC LIMIT 100";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    foreach ($results as $result) {
        $stats['total']++;
        $status = $result['status'] ?? 'pending';
        $stats[$status] = ($stats[$status] ?? 0) + 1;
    }
} catch (Exception $e) {
    error_log("Error fetching results: " . $e->getMessage());
}

$status_colors = [
    'pending' => 'warning',
    'verified' => 'info',
    'approved' => 'success',
    'rejected' => 'danger',
    'flagged' => 'purple'
];

$page_title = 'Submitted Results';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.filter-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    align-items: center;
    background: white;
    padding: 14px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.filter-bar select,
.filter-bar input[type="text"] {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 150px;
}

.filter-bar select:focus,
.filter-bar input[type="text"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-bar .btn-filter {
    padding: 8px 24px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.filter-bar .btn-filter:hover {
    background: var(--primary-dark);
}

.filter-bar .filter-info {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-left: auto;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.stats-row .stat-box {
    background: white;
    border-radius: 8px;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.stats-row .stat-box .number {
    font-size: 1.1rem;
    font-weight: 700;
}

.stats-row .stat-box .number.total { color: #3B82F6; }
.stats-row .stat-box .number.pending { color: #F59E0B; }
.stats-row .stat-box .number.verified { color: #8B5CF6; }
.stats-row .stat-box .number.approved { color: #10B981; }
.stats-row .stat-box .number.rejected { color: #EF4444; }

.stats-row .stat-box .label {
    font-size: 0.55rem;
    color: var(--gray-500);
    text-transform: uppercase;
}

.results-table-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.results-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.results-table th {
    background: var(--gray-50);
    padding: 8px 10px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.results-table td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.results-table tr:hover td {
    background: var(--gray-50);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.5rem;
    padding: 2px 8px;
    border-radius: 8px;
    font-weight: 600;
}

.status-badge .dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.pending { background: #FFFBEB; color: #92400E; }
.status-badge.pending .dot { background: #F59E0B; }
.status-badge.verified { background: #EFF6FF; color: #1E40AF; }
.status-badge.verified .dot { background: #3B82F6; }
.status-badge.approved { background: #ECFDF5; color: #065F46; }
.status-badge.approved .dot { background: #10B981; }
.status-badge.rejected { background: #FEF2F2; color: #991B1B; }
.status-badge.rejected .dot { background: #EF4444; }
.status-badge.flagged { background: #F5F3FF; color: #5B21B6; }
.status-badge.flagged .dot { background: #8B5CF6; }

.action-buttons {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}

.action-buttons a {
    padding: 2px 10px;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.action-buttons .btn-view {
    background: var(--gray-100);
    color: var(--gray-700);
}

.action-buttons .btn-view:hover {
    background: var(--gray-200);
}

.action-buttons .btn-approve {
    background: #ECFDF5;
    color: #10B981;
}

.action-buttons .btn-approve:hover {
    background: #D1FAE5;
}

.action-buttons .btn-reject {
    background: #FEF2F2;
    color: #DC2626;
}

.action-buttons .btn-reject:hover {
    background: #FEE2E2;
}

.action-buttons .btn-review {
    background: #EFF6FF;
    color: #3B82F6;
}

.action-buttons .btn-review:hover {
    background: #DBEAFE;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h4 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    font-size: 0.85rem;
    margin-top: 4px;
}

@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar select,
    .filter-bar input[type="text"] {
        width: 100%;
        min-width: unset;
    }
    .filter-bar .filter-info {
        margin-left: 0;
        text-align: center;
    }
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    .results-table-container {
        overflow-x: auto;
    }
    .results-table {
        font-size: 0.7rem;
    }
    .results-table th,
    .results-table td {
        padding: 4px 6px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-list"></i> Submitted Results</h1>
                <p class="subtitle">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo htmlspecialchars($lga_name); ?> LGA - View Submitted Results
                </p>
            </div>
            <div class="actions">
                <a href="approve-results.php" class="btn-primary-sm">
                    <i class="fas fa-check-double"></i> Approve Results
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="number total"><?php echo $stats['total']; ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-box">
                <div class="number pending"><?php echo $stats['pending']; ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-box">
                <div class="number verified"><?php echo $stats['verified']; ?></div>
                <div class="label">Verified</div>
            </div>
            <div class="stat-box">
                <div class="number approved"><?php echo $stats['approved']; ?></div>
                <div class="label">Approved</div>
            </div>
            <div class="stat-box">
                <div class="number rejected"><?php echo $stats['rejected']; ?></div>
                <div class="label">Rejected</div>
            </div>
            <div class="stat-box">
                <div class="number flagged"><?php echo $stats['flagged']; ?></div>
                <div class="label">Flagged</div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <select id="wardFilter" onchange="applyFilters()">
                <option value="0">All Wards</option>
                <?php foreach ($wards as $w): ?>
                    <option value="<?php echo $w['id']; ?>" <?php echo $ward_filter == $w['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($w['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="statusFilter" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="flagged" <?php echo $status_filter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
            </select>

            <input type="text" id="searchInput" placeholder="Search PU or Agent..." value="<?php echo htmlspecialchars($search); ?>" />

            <button class="btn-filter" onclick="applyFilters()">
                <i class="fas fa-filter"></i> Apply
            </button>

            <span class="filter-info">
                <i class="fas fa-list"></i> <?php echo $stats['total']; ?> results found
            </span>
        </div>

        <!-- Results Table -->
        <div class="results-table-container">
            <table class="results-table">
                <thead>
                    <tr>
                        <th>PU</th>
                        <th>Ward</th>
                        <th>Election</th>
                        <th>Agent</th>
                        <th>Valid Votes</th>
                        <th>Total Votes</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($result['pu_name']); ?></strong>
                                <div style="font-size:0.55rem;color:var(--gray-400);"><?php echo htmlspecialchars($result['pu_code']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($result['ward_name']); ?></td>
                            <td><?php echo htmlspecialchars($result['election_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php echo htmlspecialchars($result['agent_first_name'] ?? '') . ' ' . htmlspecialchars($result['agent_last_name'] ?? ''); ?>
                            </td>
                            <td><?php echo number_format($result['valid_votes']); ?></td>
                            <td><?php echo number_format($result['total_votes_cast']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $result['status']; ?>">
                                    <span class="dot"></span>
                                    <?php echo ucfirst($result['status']); ?>
                                </span>
                            </td>
                            <td style="font-size:0.65rem;color:var(--gray-500);">
                                <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view-result.php?id=<?php echo $result['id']; ?>" class="btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($result['status'] === 'pending' || $result['status'] === 'verified'): ?>
                                        <a href="approve-results.php?id=<?php echo $result['id']; ?>" class="btn-approve">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="reject-results.php?id=<?php echo $result['id']; ?>" class="btn-reject" onclick="return confirm('Reject this result?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($result['status'] === 'pending'): ?>
                                        <a href="review-ec8b.php?result_id=<?php echo $result['id']; ?>" class="btn-review">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-file-alt"></i>
                                    <h4>No Results Found</h4>
                                    <p>No results have been submitted in <?php echo htmlspecialchars($lga_name); ?> yet.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
function applyFilters() {
    var ward = document.getElementById('wardFilter').value;
    var status = document.getElementById('statusFilter').value;
    var search = document.getElementById('searchInput').value;
    
    var url = window.location.pathname;
    var params = [];
    if (ward && ward !== '0') params.push('ward_id=' + ward);
    if (status) params.push('status=' + status);
    if (search) params.push('search=' + encodeURIComponent(search));
    if (params.length) url += '?' + params.join('&');
    window.location.href = url;
}

// Enter key for search
document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
    }
});

// Same sidebar scripts as index.php
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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
</script>
</body>
</html>