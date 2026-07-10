<?php
// ============================================================
// STATE COORDINATOR - RESULT VERIFICATION
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

// If state_id is not set in session, try to get it from user record
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

// ============================================================
// GET FILTERS
// ============================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$election_filter = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
$lga_filter = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// ============================================================
// FETCH STATE NAME
// ============================================================
$state_name = 'Unknown State';
try {
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        $state_name = $state['name'] ?? 'Unknown State';
    }
} catch (Exception $e) {
    error_log("Error fetching state: " . $e->getMessage());
}

// ============================================================
// FETCH ELECTIONS FOR FILTER
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, status 
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

// ============================================================
// FETCH LGAS FOR FILTER
// ============================================================
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// ============================================================
// FETCH RESULTS FOR VERIFICATION
// ============================================================
$results = [];
$total_results = 0;
$total_pages = 0;

try {
    $sql = "
        SELECT 
            r.*,
            u.first_name as agent_first,
            u.last_name as agent_last,
            u.phone as agent_phone,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            e.name as election_name,
            e.type as election_type
        FROM results_ec8a r
        JOIN users u ON r.agent_id = u.id
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        JOIN elections e ON r.election_id = e.id
        WHERE r.tenant_id = ?
        AND l.state_id = ?
        AND r.deleted_at IS NULL
    ";
    
    $params = [$tenant_id, $state_id];
    
    if (!empty($status_filter)) {
        $sql .= " AND r.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($election_filter)) {
        $sql .= " AND r.election_id = ?";
        $params[] = $election_filter;
    }
    
    if (!empty($lga_filter)) {
        $sql .= " AND l.id = ?";
        $params[] = $lga_filter;
    }
    
    if (!empty($search)) {
        $sql .= " AND (pu.name LIKE ? OR pu.code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Count total
    $count_sql = str_replace(
        "SELECT 
            r.*,
            u.first_name as agent_first,
            u.last_name as agent_last,
            u.phone as agent_phone,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            e.name as election_name,
            e.type as election_type",
        "SELECT COUNT(*) as count",
        $sql
    );
    
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_results = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    $total_pages = ceil($total_results / $per_page);
    
    // Get data
    $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching results: " . $e->getMessage());
}

// ============================================================
// GET STATISTICS
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
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE r.tenant_id = ? AND l.state_id = ?
    ");
    $stmt->execute([$tenant_id, $state_id]);
    $stats_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['total'] = (int)($stats_data['total'] ?? 0);
    $stats['pending'] = (int)($stats_data['pending'] ?? 0);
    $stats['verified'] = (int)($stats_data['verified'] ?? 0);
    $stats['rejected'] = (int)($stats_data['rejected'] ?? 0);
    $stats['flagged'] = (int)($stats_data['flagged'] ?? 0);
    
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

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
    margin: 0;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.btn-secondary-sm {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-secondary-sm:hover {
    background: var(--gray-200);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 14px 18px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-card .number {
    font-size: 1.6rem;
    font-weight: 700;
}
.stat-card .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    margin-top: 2px;
}
.stat-card .number.pending { color: #F59E0B; }
.stat-card .number.verified { color: #10B981; }
.stat-card .number.rejected { color: #EF4444; }
.stat-card .number.flagged { color: #8B5CF6; }

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 16px;
    background: white;
    padding: 14px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filter-bar .search-box {
    flex: 1;
    min-width: 180px;
    display: flex;
    gap: 8px;
}
.filter-bar .search-box input {
    flex: 1;
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
}
.filter-bar .search-box input:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    background: white;
}
.filter-bar .btn-filter {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
}
.filter-bar .btn-filter:hover {
    background: var(--primary-dark);
}
.filter-bar .btn-reset {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    font-family: 'Inter', sans-serif;
}
.filter-bar .btn-reset:hover {
    background: var(--gray-200);
}

.table-wrapper {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.table-wrapper table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table-wrapper table th {
    background: var(--gray-50);
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-600);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
}
.table-wrapper table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table-wrapper table tr:hover td {
    background: var(--gray-50);
}
.table-wrapper table tr:last-child td {
    border-bottom: none;
}

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.badge-status .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.badge-status.pending { background: #FFFBEB; color: #92400E; }
.badge-status.pending .dot { background: #F59E0B; }
.badge-status.verified { background: #ECFDF5; color: #065F46; }
.badge-status.verified .dot { background: #10B981; }
.badge-status.rejected { background: #FEF2F2; color: #991B1B; }
.badge-status.rejected .dot { background: #EF4444; }
.badge-status.flagged { background: #F5F3FF; color: #5B21B6; }
.badge-status.flagged .dot { background: #8B5CF6; }
.badge-status.approved { background: #ECFDF5; color: #065F46; }
.badge-status.approved .dot { background: #10B981; }

.btn-action {
    padding: 4px 10px;
    border-radius: 6px;
    border: none;
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
}
.btn-action.btn-view {
    background: #EFF6FF;
    color: #3B82F6;
}
.btn-action.btn-view:hover {
    background: #DBEAFE;
}
.btn-action.btn-verify {
    background: #ECFDF5;
    color: #10B981;
}
.btn-action.btn-verify:hover {
    background: #D1FAE5;
}
.btn-action.btn-reject {
    background: #FEF2F2;
    color: #EF4444;
}
.btn-action.btn-reject:hover {
    background: #FEE2E2;
}
.btn-action.btn-flag {
    background: #F5F3FF;
    color: #8B5CF6;
}
.btn-action.btn-flag:hover {
    background: #EDE9FE;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    padding: 16px 20px;
    border-top: 1px solid var(--gray-200);
}
.pagination .page-btn {
    padding: 6px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.pagination .page-btn:hover {
    background: var(--gray-50);
    border-color: var(--gray-300);
}
.pagination .page-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .page-btn.disabled {
    opacity: 0.5;
    pointer-events: none;
}
.pagination .page-info {
    font-size: 0.8rem;
    color: var(--gray-500);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-400);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

.result-cell {
    font-size: 0.8rem;
}
.result-cell .label {
    font-size: 0.65rem;
    color: var(--gray-400);
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .table-wrapper {
        overflow-x: auto;
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .search-box {
        flex-direction: column;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
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
                    <i class="fas fa-check-double" style="color:var(--primary);margin-right:8px;"></i>
                    Result Verification
                    <small><?php echo htmlspecialchars($state_name); ?> - Verify election results</small>
                </h2>
            </div>
            <div>
                <a href="index.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total Results</div>
            </div>
            <div class="stat-card">
                <div class="number pending"><?php echo number_format($stats['pending']); ?></div>
                <div class="label">Pending Verification</div>
            </div>
            <div class="stat-card">
                <div class="number verified"><?php echo number_format($stats['verified']); ?></div>
                <div class="label">Verified</div>
            </div>
            <div class="stat-card">
                <div class="number rejected"><?php echo number_format($stats['rejected']); ?></div>
                <div class="label">Rejected</div>
            </div>
            <div class="stat-card">
                <div class="number flagged"><?php echo number_format($stats['flagged']); ?></div>
                <div class="label">Flagged</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="filter-bar">
            <div class="search-box">
                <input type="text" name="search" placeholder="Search PU, Agent..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
            </div>
            <select name="status">
                <option value="">All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="flagged" <?php echo $status_filter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
            </select>
            <select name="election_id">
                <option value="">All Elections</option>
                <?php foreach ($elections as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo $election_filter == $e['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="lga_id">
                <option value="">All LGAs</option>
                <?php foreach ($lgas as $l): ?>
                    <option value="<?php echo $l['id']; ?>" <?php echo $lga_filter == $l['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($l['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="result-verification.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>

        <!-- Table -->
        <div class="table-wrapper">
            <?php if (count($results) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Election</th>
                            <th>Polling Unit</th>
                            <th>LGA / Ward</th>
                            <th>Agent</th>
                            <th>Votes</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = $offset + 1; ?>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td>
                                    <div style="font-weight:600;font-size:0.8rem;"><?php echo htmlspecialchars($result['election_name']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo ucfirst(str_replace('_', ' ', $result['election_type'])); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:600;font-size:0.8rem;"><?php echo htmlspecialchars($result['pu_name']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);">Code: <?php echo htmlspecialchars($result['pu_code']); ?></div>
                                </td>
                                <td>
                                    <div style="font-size:0.8rem;"><?php echo htmlspecialchars($result['lga_name']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo htmlspecialchars($result['ward_name']); ?></div>
                                </td>
                                <td>
                                    <div style="font-size:0.8rem;font-weight:500;"><?php echo htmlspecialchars($result['agent_first'] . ' ' . $result['agent_last']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo htmlspecialchars($result['agent_phone'] ?? 'No phone'); ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $party_votes = json_decode($result['party_votes_json'], true);
                                    if (is_array($party_votes)) {
                                        $total = array_sum($party_votes);
                                        echo '<div style="font-weight:700;font-size:1rem;color:var(--gray-800);">' . number_format($total) . '</div>';
                                        echo '<div style="font-size:0.6rem;color:var(--gray-400);">' . count($party_votes) . ' parties</div>';
                                    } else {
                                        echo '<span style="color:var(--gray-400);">N/A</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div style="font-size:0.75rem;"><?php echo date('M j, Y', strtotime($result['created_at'])); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo date('g:i A', strtotime($result['created_at'])); ?></div>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $result['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($result['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="result-view.php?id=<?php echo $result['id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($result['status'] === 'pending'): ?>
                                        <button onclick="verifyResult(<?php echo $result['id']; ?>)" class="btn-action btn-verify">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button onclick="rejectResult(<?php echo $result['id']; ?>)" class="btn-action btn-reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($result['status'] === 'verified' || $result['status'] === 'pending'): ?>
                                        <button onclick="flagResult(<?php echo $result['id']; ?>)" class="btn-action btn-flag">
                                            <i class="fas fa-flag"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <span class="page-info">
                            Showing <?php echo min($total_results, ($page - 1) * $per_page + 1); ?> - 
                            <?php echo min($total_results, $page * $per_page); ?> of <?php echo number_format($total_results); ?>
                        </span>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-double"></i>
                    <p>No results found.</p>
                    <?php if (!empty($search) || !empty($status_filter) || !empty($election_filter) || !empty($lga_filter)): ?>
                        <p style="font-size:0.8rem;">Try adjusting your filters.</p>
                    <?php else: ?>
                        <p style="font-size:0.8rem;">Results will appear here once agents submit them.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
    }
});

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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// RESULT ACTIONS
// ============================================================
function verifyResult(resultId) {
    if (confirm('Are you sure you want to verify this result?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'result-verify.php';
        
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'result_id';
        input.value = resultId;
        form.appendChild(input);
        
        var token = document.createElement('input');
        token.type = 'hidden';
        token.name = 'csrf_token';
        token.value = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        form.appendChild(token);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function rejectResult(resultId) {
    var reason = prompt('Please provide a reason for rejection:');
    if (reason !== null) {
        if (confirm('Are you sure you want to reject this result?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'result-reject.php';
            
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'result_id';
            input.value = resultId;
            form.appendChild(input);
            
            var reasonInput = document.createElement('input');
            reasonInput.type = 'hidden';
            reasonInput.name = 'reason';
            reasonInput.value = reason;
            form.appendChild(reasonInput);
            
            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'csrf_token';
            token.value = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
            form.appendChild(token);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
}

function flagResult(resultId) {
    var reason = prompt('Please provide a reason for flagging:');
    if (reason !== null) {
        if (confirm('Are you sure you want to flag this result for review?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'result-flag.php';
            
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'result_id';
            input.value = resultId;
            form.appendChild(input);
            
            var reasonInput = document.createElement('input');
            reasonInput.type = 'hidden';
            reasonInput.name = 'reason';
            reasonInput.value = reason;
            form.appendChild(reasonInput);
            
            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'csrf_token';
            token.value = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
            form.appendChild(token);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
}
</script>
</body>
</html>