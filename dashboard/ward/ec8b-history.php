<?php
// ============================================================
// WARD COORDINATOR - EC8B SUBMISSION HISTORY
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Ward Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');

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

// Get ward name
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
    error_log("Error fetching ward: " . $e->getMessage());
}

// Get filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$election_filter = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

// Get elections for filter
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, election_date 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

// Fetch EC8B history
$history = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'verified' => 0,
    'rejected' => 0,
    'flagged' => 0
];

try {
    $sql = "
        SELECT 
            r.*,
            e.name as election_name,
            e.type as election_type,
            w.name as ward_name,
            u.first_name as verifier_first_name,
            u.last_name as verifier_last_name
        FROM results_ec8b r
        JOIN elections e ON r.election_id = e.id
        JOIN wards w ON r.ward_id = w.id
        LEFT JOIN users u ON r.verified_by = u.id
        WHERE r.tenant_id = ? AND r.ward_id = ?
    ";
    $params = [$tenant_id, $ward_id];
    
    if ($election_filter > 0) {
        $sql .= " AND r.election_id = ?";
        $params[] = $election_filter;
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND r.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY r.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($history as $item) {
        $stats['total']++;
        $stats[$item['status']] = ($stats[$item['status']] ?? 0) + 1;
    }
} catch (Exception $e) {
    error_log("Error fetching EC8B history: " . $e->getMessage());
}

$status_colors = [
    'pending' => 'warning',
    'verified' => 'success',
    'rejected' => 'danger',
    'flagged' => 'purple'
];

$page_title = 'EC8B History';
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
    padding: 12px 18px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 150px;
}

.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-bar .btn-filter {
    padding: 8px 20px;
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
.stats-row .stat-box .number.verified { color: #10B981; }
.stats-row .stat-box .number.rejected { color: #EF4444; }
.stats-row .stat-box .number.flagged { color: #8B5CF6; }

.stats-row .stat-box .label {
    font-size: 0.55rem;
    color: var(--gray-500);
    text-transform: uppercase;
}

.history-table-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.history-table th {
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

.history-table td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.history-table tr:hover td {
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
.status-badge.verified { background: #ECFDF5; color: #065F46; }
.status-badge.verified .dot { background: #10B981; }
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
    padding: 3px 10px;
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

.action-buttons .btn-edit {
    background: #FFFBEB;
    color: #D97706;
}

.action-buttons .btn-edit:hover {
    background: #FEF3C7;
}

.action-buttons .btn-submit {
    background: #EFF6FF;
    color: #3B82F6;
}

.action-buttons .btn-submit:hover {
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

.export-buttons {
    display: flex;
    gap: 8px;
}

.export-buttons a {
    padding: 6px 16px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.export-buttons .btn-pdf {
    background: #EF4444;
    color: white;
}

.export-buttons .btn-excel {
    background: #10B981;
    color: white;
}

@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar select {
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
    .history-table-container {
        overflow-x: auto;
    }
    .history-table {
        font-size: 0.7rem;
    }
    .history-table th,
    .history-table td {
        padding: 4px 6px;
    }
    .export-buttons {
        flex-direction: column;
        width: 100%;
    }
    .export-buttons a {
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="history-container" style="max-width:1000px;margin:0 auto;">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-history"></i> EC8B History</h1>
                    <p class="subtitle">
                        <i class="fas fa-layer-group"></i> 
                        <?php echo htmlspecialchars($ward_name); ?> Ward - EC8B Submission History
                    </p>
                </div>
                <div class="export-buttons">
                    <a href="export-pdf.php?type=ec8b_history" class="btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="export-excel.php?type=ec8b_history" class="btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="ec8b-create.php" class="btn-primary-sm">
                        <i class="fas fa-plus"></i> New EC8B
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
                <select id="electionFilter" onchange="applyFilters()">
                    <option value="0">All Elections</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo $election_filter == $e['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select id="statusFilter" onchange="applyFilters()">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="flagged" <?php echo $status_filter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                </select>

                <button class="btn-filter" onclick="applyFilters()">
                    <i class="fas fa-filter"></i> Apply
                </button>

                <span class="filter-info">
                    <i class="fas fa-list"></i> <?php echo $stats['total']; ?> EC8B forms found
                </span>
            </div>

            <!-- History Table -->
            <div class="history-table-container">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Election</th>
                            <th>Valid Votes</th>
                            <th>Total Votes</th>
                            <th>Status</th>
                            <th>Mismatch</th>
                            <th>Verified By</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $item): ?>
                            <tr>
                                <td><strong>#<?php echo $item['id']; ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($item['election_name']); ?>
                                    <div style="font-size:0.55rem;color:var(--gray-400);">
                                        <?php echo ucfirst($item['election_type']); ?>
                                    </div>
                                </td>
                                <td><?php echo number_format($item['valid_votes']); ?></td>
                                <td><?php echo number_format($item['total_votes']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $item['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['mismatch_alert']): ?>
                                        <span style="color:#EF4444;font-size:0.6rem;">
                                            <i class="fas fa-exclamation-triangle"></i> Yes
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#10B981;font-size:0.6rem;">
                                            <i class="fas fa-check-circle"></i> No
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($item['verifier_first_name'] ?? '') . ' ' . htmlspecialchars($item['verifier_last_name'] ?? ''); ?>
                                    <?php if (empty($item['verifier_first_name'])): ?>
                                        <span style="color:var(--gray-400);">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.65rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y g:i A', strtotime($item['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="ec8b-view.php?id=<?php echo $item['id']; ?>" class="btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($item['status'] === 'pending'): ?>
                                            <a href="ec8b-edit.php?id=<?php echo $item['id']; ?>" class="btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="ec8b-submit.php?id=<?php echo $item['id']; ?>" class="btn-submit">
                                                <i class="fas fa-paper-plane"></i> Submit
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($item['status'] === 'verified'): ?>
                                            <span style="font-size:0.6rem;color:#10B981;">
                                                <i class="fas fa-check-circle"></i> Approved
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-file-alt"></i>
                                        <h4>No EC8B Forms Found</h4>
                                        <p>No EC8B forms have been created for <?php echo htmlspecialchars($ward_name); ?> yet.</p>
                                        <a href="ec8b-create.php" class="btn-primary-sm" style="margin-top:12px;">
                                            <i class="fas fa-plus"></i> Create EC8B
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
function applyFilters() {
    var election = document.getElementById('electionFilter').value;
    var status = document.getElementById('statusFilter').value;
    
    var url = window.location.pathname;
    var params = [];
    if (election && election !== '0') params.push('election_id=' + election);
    if (status) params.push('status=' + status);
    if (params.length) url += '?' + params.join('&');
    window.location.href = url;
}

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