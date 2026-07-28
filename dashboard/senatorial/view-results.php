<?php
// ============================================================
// SENATORIAL COORDINATOR - VIEW RESULTS
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
// GET LGA IDs FROM SENATORIAL DISTRICT
// ============================================================
$lga_ids = [];
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("SELECT lgas_json FROM senatorial_districts WHERE id = ?");
        $stmt->execute([$senatorial_id]);
        $lgas_json = $stmt->fetchColumn();
        
        if ($lgas_json) {
            $lga_names = json_decode($lgas_json, true) ?: [];
            
            if (!empty($lga_names)) {
                $placeholders = implode(',', array_fill(0, count($lga_names), '?'));
                $stmt = $db->prepare("SELECT id FROM lgas WHERE name IN ($placeholders) AND state_id = ? AND is_active = 1");
                $stmt->execute(array_merge($lga_names, [$state_id]));
                $lga_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error getting LGA IDs: " . $e->getMessage());
    $lga_ids = [];
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// GET FILTER PARAMETERS
// ============================================================
$filter_lga = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$filter_ward = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_election = isset($_GET['election']) ? (int)$_GET['election'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// GET ELECTIONS
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, election_date, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

// ============================================================
// GET LGAS AND WARDS FOR FILTERS
// ============================================================
$lgas = [];
$wards = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
        
        $stmt = $db->prepare("SELECT id, name, lga_id FROM wards WHERE lga_id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $wards = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching filters: " . $e->getMessage());
}

// ============================================================
// BUILD RESULTS QUERY
// ============================================================
$where_conditions = ["r.tenant_id = ?"];
$params = [$tenant_id];

if ($filter_election > 0) {
    $where_conditions[] = "r.election_id = ?";
    $params[] = $filter_election;
}

if ($filter_lga > 0) {
    $where_conditions[] = "w.lga_id = ?";
    $params[] = $filter_lga;
}

if ($filter_ward > 0) {
    $where_conditions[] = "r.ward_id = ?";
    $params[] = $filter_ward;
}

if (!empty($filter_status)) {
    $where_conditions[] = "r.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $where_conditions[] = "(r.pu_name LIKE ? OR r.pu_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add LGA restriction
if ($lga_list !== '0') {
    $where_conditions[] = "w.lga_id IN ($lga_list)";
} else {
    $where_conditions[] = "1=0";
}

$where_clause = implode(" AND ", $where_conditions);

// ============================================================
// GET RESULTS
// ============================================================
$results = [];
$total_results = 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    // Get total count
    $count_query = "
        SELECT COUNT(*) as total
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        WHERE $where_clause
    ";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_results = $stmt->fetchColumn();

    // Get results
    $query = "
        SELECT 
            r.id, r.pu_id, r.pu_code, r.pu_name,
            r.registered_voters, r.accredited_voters,
            r.valid_votes, r.rejected_votes, r.total_votes_cast,
            r.status, r.created_at, r.verified_at,
            r.photo_url, r.remarks,
            r.party_votes_json,
            w.id as ward_id, w.name as ward_name,
            l.id as lga_id, l.name as lga_name,
            u.full_name as agent_name,
            v.full_name as verified_by_name,
            e.name as election_name,
            e.type as election_type
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN users v ON r.verified_by = v.id
        JOIN elections e ON r.election_id = e.id
        WHERE $where_clause
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($query);
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $results = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching results: " . $e->getMessage());
}

$total_pages = ceil($total_results / $per_page);

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'verified' => 0,
    'pending' => 0,
    'flagged' => 0,
    'rejected' => 0,
    'total_votes' => 0
];

try {
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN r.status = 'flagged' THEN 1 ELSE 0 END) as flagged,
            SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(r.total_votes_cast) as total_votes
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        WHERE $where_clause
    ";
    $stmt = $db->prepare($stats_query);
    $stats_params = array_slice($params, 0, -2);
    $stmt->execute($stats_params);
    $stats = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

$page_title = 'View Results';
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
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
.stat-card.purple .stat-number { color: #7C3AED; }
.stat-card.purple .stat-icon { color: #7C3AED; }
.stat-card.orange .stat-number { color: #EA580C; }
.stat-card.orange .stat-icon { color: #EA580C; }
.stat-card.teal .stat-number { color: #0D9488; }
.stat-card.teal .stat-icon { color: #0D9488; }

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

.table-wrap {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.table th {
    text-align: left;
    padding: 10px 12px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    border-bottom: 2px solid var(--gray-200);
}
.table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table tr:hover td {
    background: var(--gray-50);
}

.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.status-badge.verified { background: #D1FAE5; color: #059669; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.flagged { background: #FEE2E2; color: #DC2626; }
.status-badge.rejected { background: #FEE2E2; color: #DC2626; }

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

.results-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
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
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px;"></i> 
                    View Results
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="download-results.php" class="btn btn-primary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:white;border:none;">
                    <i class="fas fa-download"></i> Download Summary
                </a>
                <a href="monitor-district.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Results</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['verified'] ?? 0); ?></div>
                <div class="stat-label">Verified</div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending'] ?? 0); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($stats['flagged'] ?? 0); ?></div>
                <div class="stat-label">Flagged</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_votes'] ?? 0); ?></div>
                <div class="stat-label">Total Votes Cast</div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" action="" class="filters-row">
            <select name="election">
                <option value="">All Elections</option>
                <?php foreach ($elections as $election): ?>
                    <option value="<?php echo $election['id']; ?>" <?php echo ($filter_election == $election['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($election['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="lga">
                <option value="">All LGAs</option>
                <?php foreach ($lgas as $lga): ?>
                    <option value="<?php echo $lga['id']; ?>" <?php echo ($filter_lga == $lga['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lga['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="ward">
                <option value="">All Wards</option>
                <?php foreach ($wards as $ward): ?>
                    <option value="<?php echo $ward['id']; ?>" <?php echo ($filter_ward == $ward['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ward['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="verified" <?php echo ($filter_status === 'verified') ? 'selected' : ''; ?>>Verified</option>
                <option value="flagged" <?php echo ($filter_status === 'flagged') ? 'selected' : ''; ?>>Flagged</option>
                <option value="rejected" <?php echo ($filter_status === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
            </select>
            <input type="text" name="search" placeholder="Search PU..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
            <a href="view-results.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>

        <!-- Results Table -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i> Results List</h3>
                <span style="font-size:0.75rem;color:var(--gray-400);">Showing <?php echo count($results); ?> results (<?php echo number_format($total_results); ?> total)</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Election</th>
                            <th>Polling Unit</th>
                            <th>LGA</th>
                            <th>Ward</th>
                            <th>Votes</th>
                            <th>Status</th>
                            <th>Agent</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($results) > 0): ?>
                            <?php $i = $offset + 1; foreach ($results as $result): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($result['election_name']); ?>
                                        <br><span style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($result['election_type']); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($result['pu_name']); ?></strong>
                                        <br><span style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($result['pu_code']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($result['lga_name']); ?></td>
                                    <td><?php echo htmlspecialchars($result['ward_name']); ?></td>
                                    <td>
                                        <?php echo number_format($result['total_votes_cast'] ?? 0); ?>
                                        <br><span style="font-size:0.6rem;color:var(--gray-400);">
                                            <?php echo number_format($result['registered_voters'] ?? 0); ?> registered
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $result['status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($result['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-600);">
                                        <?php echo htmlspecialchars($result['agent_name'] ?? '—'); ?>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($result['created_at'] ?? 'now')); ?>
                                        <br><span style="font-size:0.6rem;color:var(--gray-400);">
                                            <?php echo date('g:i A', strtotime($result['created_at'] ?? 'now')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="results-actions">
                                            <a href="result-details.php?id=<?php echo $result['id']; ?>" class="btn-sm">View</a>
                                            <?php if ($result['status'] === 'pending'): ?>
                                                <a href="verify-ec8a.php?id=<?php echo $result['id']; ?>" class="btn-sm" style="background:#10B981;">Verify</a>
                                            <?php endif; ?>
                                            <?php if ($result['photo_url']): ?>
                                                <a href="<?php echo $result['photo_url']; ?>" target="_blank" class="btn-sm outline"><i class="fas fa-image"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>No results found matching your filters.</p>
                                        <p style="font-size:0.8rem;margin-top:4px;">Try adjusting your search or filter criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

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