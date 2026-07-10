<?php
// ============================================================
// STATE COORDINATOR - RESULT VERIFICATION DASHBOARD
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();
$election_filter = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get elections for filter
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND (states_json LIKE ? OR states_json IS NULL OR states_json = '[]')
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

// Fetch verification statistics
$stats = [
    'pending' => 0,
    'verified' => 0,
    'rejected' => 0,
    'flagged' => 0,
    'approved' => 0,
    'total' => 0,
    'mismatch_alerts' => 0
];

$recent_results = [];
$pending_results = [];

try {
    $sql = "
        SELECT 
            r.*,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            u.first_name as agent_first_name,
            u.last_name as agent_last_name,
            e.name as election_name
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN elections e ON r.election_id = e.id
        WHERE r.tenant_id = ? AND l.state_id = ?
    ";
    
    $params = [$tenant_id, $state_id];
    
    if ($election_filter > 0) {
        $sql .= " AND r.election_id = ?";
        $params[] = $election_filter;
    }
    
    $sql .= " ORDER BY r.created_at DESC LIMIT 50";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $recent_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    foreach ($recent_results as $result) {
        $stats['total']++;
        $status = $result['status'] ?? 'pending';
        $stats[$status] = ($stats[$status] ?? 0) + 1;
        if (!empty($result['mismatch_alert']) && $result['mismatch_alert'] == 1) {
            $stats['mismatch_alerts']++;
        }
        if ($status === 'pending') {
            $pending_results[] = $result;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching results: " . $e->getMessage());
}

// Get EC8B stats
$ec8b_stats = [
    'total' => 0,
    'pending' => 0,
    'verified' => 0,
    'rejected' => 0
];

try {
    $sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM results_ec8b
        WHERE tenant_id = ? AND state_id = ?
    ";
    
    $params = [$tenant_id, $state_id];
    if ($election_filter > 0) {
        $sql .= " AND election_id = ?";
        $params[] = $election_filter;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $ec8b_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ec8b_result) {
        $ec8b_stats['total'] = (int)($ec8b_result['total'] ?? 0);
        $ec8b_stats['pending'] = (int)($ec8b_result['pending'] ?? 0);
        $ec8b_stats['verified'] = (int)($ec8b_result['verified'] ?? 0);
        $ec8b_stats['rejected'] = (int)($ec8b_result['rejected'] ?? 0);
    }
} catch (Exception $e) {
    error_log("Error fetching EC8B stats: " . $e->getMessage());
}

// Helper function to safely format numbers
function safe_number_format($num, $decimals = 0) {
    return number_format((float)($num ?? 0), $decimals);
}

$page_title = 'Result Verification';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.verification-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.verification-stat {
    background: white;
    border-radius: var(--radius);
    padding: 14px 16px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.verification-stat .number {
    font-size: 1.4rem;
    font-weight: 700;
}

.verification-stat .number.pending { color: #F59E0B; }
.verification-stat .number.verified { color: #3B82F6; }
.verification-stat .number.approved { color: #10B981; }
.verification-stat .number.rejected { color: #EF4444; }
.verification-stat .number.flagged { color: #8B5CF6; }
.verification-stat .number.mismatch { color: #DC2626; }

.verification-stat .label {
    font-size: 0.65rem;
    color: var(--gray-500);
}

.filter-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    align-items: center;
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 160px;
}

.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
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
    font-size: 0.82rem;
}

.results-table th {
    background: var(--gray-50);
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.results-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.results-table tr:hover td {
    background: var(--gray-50);
}

.results-table .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
}

.results-table .status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.results-table .status-badge.pending { background: #FFFBEB; color: #92400E; }
.results-table .status-badge.pending .dot { background: #F59E0B; }
.results-table .status-badge.verified { background: #EFF6FF; color: #1E40AF; }
.results-table .status-badge.verified .dot { background: #3B82F6; }
.results-table .status-badge.approved { background: #ECFDF5; color: #065F46; }
.results-table .status-badge.approved .dot { background: #10B981; }
.results-table .status-badge.rejected { background: #FEF2F2; color: #991B1B; }
.results-table .status-badge.rejected .dot { background: #EF4444; }
.results-table .status-badge.flagged { background: #F5F3FF; color: #5B21B6; }
.results-table .status-badge.flagged .dot { background: #8B5CF6; }

.results-table .action-buttons {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}

.results-table .action-buttons a {
    padding: 2px 10px;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.results-table .action-buttons .btn-verify {
    background: #EFF6FF;
    color: #3B82F6;
}

.results-table .action-buttons .btn-verify:hover {
    background: #DBEAFE;
}

.results-table .action-buttons .btn-view {
    background: var(--gray-100);
    color: var(--gray-700);
}

.results-table .action-buttons .btn-view:hover {
    background: var(--gray-200);
}

.results-table .action-buttons .btn-reject {
    background: #FEF2F2;
    color: #DC2626;
}

.results-table .action-buttons .btn-reject:hover {
    background: #FEE2E2;
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

.quick-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.quick-actions a {
    padding: 8px 18px;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.quick-actions .btn-ec8a {
    background: var(--primary);
    color: white;
}

.quick-actions .btn-ec8a:hover {
    background: var(--primary-dark);
}

.quick-actions .btn-ec8b {
    background: var(--gray-100);
    color: var(--gray-700);
}

.quick-actions .btn-ec8b:hover {
    background: var(--gray-200);
}

.quick-actions .btn-compare {
    background: #F5F3FF;
    color: #7C3AED;
}

.quick-actions .btn-compare:hover {
    background: #EDE9FE;
}

@media (max-width: 768px) {
    .verification-stats {
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
        padding: 6px 10px;
    }
    .filter-bar {
        flex-direction: column;
    }
    .filter-bar select {
        width: 100%;
        min-width: unset;
    }
    .quick-actions {
        flex-direction: column;
    }
    .quick-actions a {
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-check-double"></i> Result Verification</h1>
                <p class="subtitle">
                    <i class="fas fa-flag"></i> 
                    <?php echo htmlspecialchars($state_name); ?> State - Verify Election Results
                </p>
            </div>
            <div class="actions">
                <a href="export-excel.php?type=results" class="btn-secondary-sm">
                    <i class="fas fa-file-excel"></i> Export
                </a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="verify-ec8a.php" class="btn-ec8a">
                <i class="fas fa-file-alt"></i> Verify EC8A
            </a>
            <a href="verify-ec8b.php" class="btn-ec8b">
                <i class="fas fa-file-alt"></i> Verify EC8B
            </a>
            <a href="compare-results.php" class="btn-compare">
                <i class="fas fa-balance-scale"></i> Compare Results
            </a>
        </div>

        <!-- Stats - Using safe_number_format -->
        <div class="verification-stats">
            <div class="verification-stat">
                <div class="number pending"><?php echo safe_number_format($stats['pending'] ?? 0); ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="verification-stat">
                <div class="number verified"><?php echo safe_number_format($stats['verified'] ?? 0); ?></div>
                <div class="label">Verified</div>
            </div>
            <div class="verification-stat">
                <div class="number approved"><?php echo safe_number_format($stats['approved'] ?? 0); ?></div>
                <div class="label">Approved</div>
            </div>
            <div class="verification-stat">
                <div class="number rejected"><?php echo safe_number_format($stats['rejected'] ?? 0); ?></div>
                <div class="label">Rejected</div>
            </div>
            <div class="verification-stat">
                <div class="number flagged"><?php echo safe_number_format($stats['flagged'] ?? 0); ?></div>
                <div class="label">Flagged</div>
            </div>
            <div class="verification-stat">
                <div class="number mismatch"><?php echo safe_number_format($stats['mismatch_alerts'] ?? 0); ?></div>
                <div class="label">Mismatch Alerts</div>
            </div>
            <div class="verification-stat">
                <div class="number"><?php echo safe_number_format($ec8b_stats['pending'] ?? 0); ?></div>
                <div class="label">EC8B Pending</div>
            </div>
            <div class="verification-stat">
                <div class="number"><?php echo safe_number_format($stats['total'] ?? 0); ?></div>
                <div class="label">Total EC8A</div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <select id="electionFilter" onchange="window.location.href='?election_id='+this.value">
                <option value="0">All Elections</option>
                <?php foreach ($elections as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo $election_filter == $e['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="statusFilter" onchange="filterTable()">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="flagged">Flagged</option>
            </select>

            <span style="font-size:0.75rem;color:var(--gray-500);margin-left:auto;">
                <?php echo safe_number_format($stats['total'] ?? 0); ?> results found
            </span>
        </div>

        <!-- Results Table -->
        <div class="results-table-container">
            <table class="results-table" id="resultsTable">
                <thead>
                    <tr>
                        <th>PU</th>
                        <th>LGA</th>
                        <th>Ward</th>
                        <th>Election</th>
                        <th>Agent</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_results as $result): ?>
                        <tr data-status="<?php echo htmlspecialchars($result['status'] ?? 'pending'); ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($result['pu_name'] ?? 'N/A'); ?></strong>
                                <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($result['pu_code'] ?? ''); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($result['lga_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($result['ward_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($result['election_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($result['agent_first_name'] ?? '') . ' ' . htmlspecialchars($result['agent_last_name'] ?? ''); ?></td>
                            <td>
                                <span class="status-badge <?php echo htmlspecialchars($result['status'] ?? 'pending'); ?>">
                                    <span class="dot"></span>
                                    <?php echo ucfirst(htmlspecialchars($result['status'] ?? 'pending')); ?>
                                </span>
                                <?php if (!empty($result['mismatch_alert']) && $result['mismatch_alert'] == 1): ?>
                                    <span style="display:inline-block;margin-left:4px;font-size:0.5rem;color:#DC2626;background:#FEF2F2;padding:1px 6px;border-radius:4px;">
                                        ⚠ Mismatch
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.7rem;color:var(--gray-500);">
                                <?php echo !empty($result['created_at']) ? date('M j, Y', strtotime($result['created_at'])) : 'N/A'; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if (($result['status'] ?? '') === 'pending'): ?>
                                        <a href="verify-ec8a.php?id=<?php echo $result['id']; ?>" class="btn-verify">
                                            <i class="fas fa-check"></i> Verify
                                        </a>
                                    <?php endif; ?>
                                    <a href="view-result.php?id=<?php echo $result['id']; ?>" class="btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if (in_array($result['status'] ?? '', ['pending', 'verified'])): ?>
                                        <a href="reject-results.php?id=<?php echo $result['id']; ?>" class="btn-reject" onclick="return confirm('Are you sure you want to reject this result?')">
                                            <i class="fas fa-times"></i> Reject
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($recent_results)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-file-alt"></i>
                                    <h4>No Results Found</h4>
                                    <p>No EC8A results have been submitted for <?php echo htmlspecialchars($state_name); ?> yet.</p>
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
function filterTable() {
    var status = document.getElementById('statusFilter').value;
    var rows = document.querySelectorAll('#resultsTable tbody tr');
    
    rows.forEach(function(row) {
        if (row.dataset.status) {
            if (!status || row.dataset.status === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
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