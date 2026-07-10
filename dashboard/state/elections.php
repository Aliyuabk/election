<?php
// ============================================================
// STATE COORDINATOR - ELECTIONS LIST
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

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';

// Fetch elections
$elections = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'upcoming' => 0,
    'closed' => 0,
    'draft' => 0
];

try {
    // Build the query to check if state_id exists in states_json
    // The states_json column stores JSON array like [1, 2, 3] or [1]
    // We need to check if the state_id is in that array
    
    $sql = "
        SELECT 
            e.*,
            u.full_name as created_by_name,
            (SELECT COUNT(*) FROM candidates WHERE election_id = e.id) as candidate_count,
            (SELECT COUNT(*) FROM results_ec8a WHERE election_id = e.id AND status = 'pending') as pending_results,
            (SELECT COUNT(*) FROM results_ec8a WHERE election_id = e.id AND status IN ('verified', 'approved')) as verified_results,
            (SELECT COUNT(*) FROM incidents WHERE election_id = e.id AND status IN ('reported', 'acknowledged', 'investigating')) as active_incidents
        FROM elections e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.tenant_id = ?
        AND e.deleted_at IS NULL
    ";
    
    $params = [$tenant_id];
    
    // Filter by state - check if state_id is in states_json
    // The states_json can be NULL, '[]', or JSON array like '[1,2,3]'
    if (!empty($state_id)) {
        $sql .= " AND (
            JSON_CONTAINS(e.states_json, ?) 
            OR e.states_json IS NULL 
            OR e.states_json = '[]'
        )";
        $params[] = json_encode($state_id);
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND e.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($type_filter)) {
        $sql .= " AND e.type = ?";
        $params[] = $type_filter;
    }
    
    $sql .= " ORDER BY e.election_date DESC, e.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    foreach ($elections as $election) {
        $stats['total']++;
        $status = $election['status'] ?? 'draft';
        $stats[$status] = ($stats[$status] ?? 0) + 1;
    }
    
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
    // Fallback query without JSON_CONTAINS if the function fails
    try {
        $sql = "
            SELECT 
                e.*,
                u.full_name as created_by_name,
                (SELECT COUNT(*) FROM candidates WHERE election_id = e.id) as candidate_count,
                (SELECT COUNT(*) FROM results_ec8a WHERE election_id = e.id AND status = 'pending') as pending_results,
                (SELECT COUNT(*) FROM results_ec8a WHERE election_id = e.id AND status IN ('verified', 'approved')) as verified_results,
                (SELECT COUNT(*) FROM incidents WHERE election_id = e.id AND status IN ('reported', 'acknowledged', 'investigating')) as active_incidents
            FROM elections e
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.tenant_id = ?
            AND e.deleted_at IS NULL
            AND (
                e.states_json LIKE ?
                OR e.states_json IS NULL 
                OR e.states_json = '[]'
            )
        ";
        
        $params = [$tenant_id, '%"' . $state_id . '"%'];
        
        if (!empty($status_filter)) {
            $sql .= " AND e.status = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($type_filter)) {
            $sql .= " AND e.type = ?";
            $params[] = $type_filter;
        }
        
        $sql .= " ORDER BY e.election_date DESC, e.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recalculate stats
        $stats = [
            'total' => 0,
            'active' => 0,
            'upcoming' => 0,
            'closed' => 0,
            'draft' => 0
        ];
        foreach ($elections as $election) {
            $stats['total']++;
            $status = $election['status'] ?? 'draft';
            $stats[$status] = ($stats[$status] ?? 0) + 1;
        }
    } catch (Exception $e2) {
        error_log("Fallback query also failed: " . $e2->getMessage());
    }
}

// Election types
$election_types = [
    'presidential' => 'Presidential',
    'governorship' => 'Governorship',
    'senatorial' => 'Senatorial',
    'house_of_reps' => 'House of Reps',
    'house_of_assembly' => 'House of Assembly',
    'lga_chairman' => 'LGA Chairman',
    'councillorship' => 'Councillorship',
    'party_primary' => 'Party Primary',
    'internal_party' => 'Internal Party'
];

$status_colors = [
    'draft' => 'secondary',
    'upcoming' => 'warning',
    'active' => 'success',
    'closed' => 'danger',
    'cancelled' => 'danger',
    'archived' => 'secondary'
];

$status_icons = [
    'draft' => 'fa-pencil-alt',
    'upcoming' => 'fa-clock',
    'active' => 'fa-play',
    'closed' => 'fa-stop',
    'cancelled' => 'fa-times',
    'archived' => 'fa-archive'
];

$page_title = 'Elections';
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
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    color: var(--gray-700);
    min-width: 150px;
}

.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-bar .filter-info {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-left: auto;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.stats-row .stat-box {
    background: white;
    border-radius: 10px;
    padding: 12px 16px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.stats-row .stat-box .number {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gray-800);
}

.stats-row .stat-box .label {
    font-size: 0.65rem;
    color: var(--gray-500);
}

.election-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 16px;
}

.election-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 20px;
    transition: var(--transition);
}

.election-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.election-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.election-card .card-header .election-name {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--gray-800);
}

.election-card .card-header .election-type {
    font-size: 0.6rem;
    color: var(--gray-500);
    background: var(--gray-100);
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 500;
}

.election-card .election-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
    margin: 10px 0;
    padding: 10px 0;
    border-top: 1px solid var(--gray-100);
    border-bottom: 1px solid var(--gray-100);
}

.election-card .election-meta .meta-item {
    font-size: 0.7rem;
    color: var(--gray-600);
}

.election-card .election-meta .meta-item .value {
    font-weight: 600;
    color: var(--gray-800);
}

.election-card .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 3px 12px;
    border-radius: 12px;
    font-weight: 600;
}

.election-card .status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.election-card .status-badge.draft { background: #F3F4F6; color: #6B7280; }
.election-card .status-badge.draft .dot { background: #9CA3AF; }
.election-card .status-badge.upcoming { background: #FFFBEB; color: #92400E; }
.election-card .status-badge.upcoming .dot { background: #F59E0B; }
.election-card .status-badge.active { background: #ECFDF5; color: #065F46; }
.election-card .status-badge.active .dot { background: #10B981; animation: pulse-dot 1.5s ease-in-out infinite; }
.election-card .status-badge.closed { background: #FEF2F2; color: #991B1B; }
.election-card .status-badge.closed .dot { background: #EF4444; }
.election-card .status-badge.cancelled { background: #FEF2F2; color: #991B1B; }
.election-card .status-badge.cancelled .dot { background: #EF4444; }
.election-card .status-badge.archived { background: #F3F4F6; color: #6B7280; }
.election-card .status-badge.archived .dot { background: #9CA3AF; }

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.election-card .election-progress {
    margin: 8px 0;
}

.election-card .election-progress .progress-bar {
    height: 4px;
    background: var(--gray-200);
    border-radius: 2px;
    overflow: hidden;
}

.election-card .election-progress .progress-bar .fill {
    height: 100%;
    background: var(--primary);
    border-radius: 2px;
    transition: width 0.8s ease;
}

.election-card .election-progress .progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.6rem;
    color: var(--gray-500);
    margin-top: 3px;
}

.election-card .card-actions {
    display: flex;
    gap: 6px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.election-card .card-actions a {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.election-card .card-actions .btn-view {
    background: var(--primary);
    color: white;
}

.election-card .card-actions .btn-view:hover {
    background: var(--primary-dark);
}

.election-card .card-actions .btn-results {
    background: var(--gray-100);
    color: var(--gray-700);
}

.election-card .card-actions .btn-results:hover {
    background: var(--gray-200);
}

.election-card .card-actions .btn-incidents {
    background: #FEF2F2;
    color: #DC2626;
}

.election-card .card-actions .btn-incidents:hover {
    background: #FEE2E2;
}

.election-card .card-actions .btn-progress {
    background: #FFFBEB;
    color: #D97706;
}

.election-card .card-actions .btn-progress:hover {
    background: #FEF3C7;
}

.empty-state {
    grid-column: 1/-1;
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h3 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    margin-top: 6px;
}

/* State info badge */
.state-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 12px;
    background: #EFF6FF;
    color: #1E40AF;
}

.state-badge i {
    font-size: 0.5rem;
}

@media (max-width: 768px) {
    .election-grid {
        grid-template-columns: 1fr;
    }
    .filter-bar {
        flex-direction: column;
    }
    .filter-bar select {
        width: 100%;
        min-width: unset;
    }
    .filter-bar .filter-info {
        margin-left: 0;
        width: 100%;
        text-align: center;
    }
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    .election-card .election-meta {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-vote-yea"></i> Elections</h1>
                <p class="subtitle">
                    <i class="fas fa-flag"></i> 
                    <?php echo htmlspecialchars($state_name); ?> State - Manage Elections
                    <?php if (!empty($state_id)): ?>
                        <span class="state-badge">
                            <i class="fas fa-check-circle"></i> Viewing: <?php echo htmlspecialchars($state_name); ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="actions">
                <a href="election-progress.php" class="btn-secondary-sm">
                    <i class="fas fa-chart-line"></i> Progress
                </a>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="number"><?php echo $stats['total']; ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-box">
                <div class="number" style="color:#10B981;"><?php echo $stats['active'] ?? 0; ?></div>
                <div class="label">Active</div>
            </div>
            <div class="stat-box">
                <div class="number" style="color:#F59E0B;"><?php echo $stats['upcoming'] ?? 0; ?></div>
                <div class="label">Upcoming</div>
            </div>
            <div class="stat-box">
                <div class="number" style="color:#EF4444;"><?php echo $stats['closed'] ?? 0; ?></div>
                <div class="label">Closed</div>
            </div>
            <div class="stat-box">
                <div class="number" style="color:#6B7280;"><?php echo $stats['draft'] ?? 0; ?></div>
                <div class="label">Draft</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <select id="statusFilter" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
            </select>

            <select id="typeFilter" onchange="applyFilters()">
                <option value="">All Types</option>
                <?php foreach ($election_types as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $type_filter === $key ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <span class="filter-info">
                <i class="fas fa-list"></i> <?php echo count($elections); ?> elections found in <?php echo htmlspecialchars($state_name); ?>
            </span>
        </div>

        <!-- Election Grid -->
        <div class="election-grid" id="electionGrid">
            <?php foreach ($elections as $election): 
                $status_class = $election['status'];
                $total_results = ($election['pending_results'] ?? 0) + ($election['verified_results'] ?? 0);
                $progress = $total_results > 0 ? min(100, round((($election['verified_results'] ?? 0) / max(1, $total_results)) * 100)) : 0;
                $election_date = new DateTime($election['election_date']);
                $today = new DateTime();
                $days_until = $today->diff($election_date)->days;
                $is_past = $election_date < $today;
            ?>
                <div class="election-card" data-status="<?php echo $election['status']; ?>" data-type="<?php echo $election['type']; ?>">
                    <div class="card-header">
                        <div>
                            <div class="election-name"><?php echo htmlspecialchars($election['name']); ?></div>
                            <div style="font-size:0.65rem;color:var(--gray-500);margin-top:2px;">
                                <i class="fas fa-tag"></i> <?php echo $election_types[$election['type']] ?? ucfirst($election['type']); ?>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst($election['status']); ?>
                        </span>
                    </div>

                    <div class="election-meta">
                        <div class="meta-item">
                            <div class="value"><?php echo date('M j, Y', strtotime($election['election_date'])); ?></div>
                            <div>Election Date</div>
                        </div>
                        <div class="meta-item">
                            <div class="value"><?php echo $is_past ? $days_until . ' days ago' : 'In ' . $days_until . ' days'; ?></div>
                            <div><?php echo $is_past ? 'Past' : 'Until'; ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="value"><?php echo number_format($election['candidate_count'] ?? 0); ?></div>
                            <div>Candidates</div>
                        </div>
                        <div class="meta-item">
                            <div class="value">
                                <?php echo number_format($election['verified_results'] ?? 0); ?>
                                <span style="font-weight:400;color:var(--gray-400);font-size:0.6rem;">
                                    / <?php echo number_format($total_results); ?>
                                </span>
                            </div>
                            <div>Verified Results</div>
                        </div>
                        <div class="meta-item" style="grid-column: span 2;">
                            <div class="value" style="color:<?php echo ($election['active_incidents'] ?? 0) > 0 ? '#EF4444' : '#10B981'; ?>;">
                                <?php echo number_format($election['active_incidents'] ?? 0); ?>
                            </div>
                            <div>Active Incidents</div>
                        </div>
                    </div>

                    <div class="election-progress">
                        <div class="progress-bar">
                            <div class="fill" style="width: <?php echo $progress; ?>%;"></div>
                        </div>
                        <div class="progress-label">
                            <span>Verification Progress</span>
                            <span><?php echo $progress; ?>%</span>
                        </div>
                    </div>

                    <div class="card-actions">
                        <a href="election-progress.php?id=<?php echo $election['id']; ?>" class="btn-progress">
                            <i class="fas fa-chart-line"></i> Progress
                        </a>
                        <a href="result-verification.php?election_id=<?php echo $election['id']; ?>" class="btn-results">
                            <i class="fas fa-check-double"></i> Results
                        </a>
                        <a href="incidents.php?election_id=<?php echo $election['id']; ?>" class="btn-incidents">
                            <i class="fas fa-exclamation-triangle"></i> Incidents
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($elections)): ?>
                <div class="empty-state">
                    <i class="fas fa-vote-yea"></i>
                    <h3>No Elections Found</h3>
                    <p>No elections have been created for <?php echo htmlspecialchars($state_name); ?> yet.</p>
                    <p style="font-size:0.75rem;color:var(--gray-400);margin-top:8px;">
                        Elections are created by the National Coordinator or Client Administrator.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function applyFilters() {
    var status = document.getElementById('statusFilter').value;
    var type = document.getElementById('typeFilter').value;
    
    var url = new URL(window.location.href);
    if (status) url.searchParams.set('status', status);
    else url.searchParams.delete('status');
    if (type) url.searchParams.set('type', type);
    else url.searchParams.delete('type');
    
    window.location.href = url.toString();
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