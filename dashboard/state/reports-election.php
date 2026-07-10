<?php
// ============================================================
// STATE COORDINATOR - ELECTION REPORT
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
$election_filter = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

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
        SELECT id, name, status, type, election_date, created_at
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
// FETCH ELECTION DETAILS
// ============================================================
$election_data = null;
$election_stats = [];

if ($election_filter > 0) {
    try {
        // Get election details
        $stmt = $db->prepare("
            SELECT e.*, u.first_name as created_by_first, u.last_name as created_by_last
            FROM elections e
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.id = ? AND e.tenant_id = ?
        ");
        $stmt->execute([$election_filter, $tenant_id]);
        $election_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($election_data) {
            // Get statistics
            // Total PUs in state
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM polling_units pu
                JOIN wards w ON pu.ward_id = w.id
                JOIN lgas l ON w.lga_id = l.id
                WHERE l.state_id = ? AND pu.is_active = 1
            ");
            $stmt->execute([$state_id]);
            $total_pus = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Results statistics
            $stmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT pu_id) as total_reported,
                    COUNT(DISTINCT CASE WHEN status IN ('verified', 'approved') THEN pu_id END) as verified,
                    COUNT(DISTINCT CASE WHEN status = 'pending' THEN pu_id END) as pending,
                    COUNT(DISTINCT CASE WHEN status = 'rejected' THEN pu_id END) as rejected,
                    SUM(valid_votes) as total_valid,
                    SUM(rejected_votes) as total_rejected,
                    SUM(total_votes_cast) as total_votes_cast
                FROM results_ec8a
                WHERE election_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$election_filter, $tenant_id]);
            $result_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Incidents
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
                    SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
                    SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
                    SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
                FROM incidents
                WHERE election_id = ? AND state_id = ?
            ");
            $stmt->execute([$election_filter, $state_id]);
            $incident_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Party votes
            $stmt = $db->prepare("
                SELECT party_votes_json
                FROM results_ec8a
                WHERE election_id = ? AND tenant_id = ? AND status IN ('verified', 'approved')
            ");
            $stmt->execute([$election_filter, $tenant_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $party_totals = [];
            foreach ($results as $row) {
                $votes = json_decode($row['party_votes_json'], true);
                if (is_array($votes)) {
                    foreach ($votes as $party => $count) {
                        if (!isset($party_totals[$party])) {
                            $party_totals[$party] = 0;
                        }
                        $party_totals[$party] += (int)$count;
                    }
                }
            }
            arsort($party_totals);
            
            $election_stats = [
                'total_pus' => $total_pus,
                'reported_pus' => (int)($result_stats['total_reported'] ?? 0),
                'verified_pus' => (int)($result_stats['verified'] ?? 0),
                'pending_pus' => (int)($result_stats['pending'] ?? 0),
                'rejected_pus' => (int)($result_stats['rejected'] ?? 0),
                'total_valid' => (int)($result_stats['total_valid'] ?? 0),
                'total_rejected' => (int)($result_stats['total_rejected'] ?? 0),
                'total_votes_cast' => (int)($result_stats['total_votes_cast'] ?? 0),
                'total_incidents' => (int)($incident_stats['total'] ?? 0),
                'critical_incidents' => (int)($incident_stats['critical'] ?? 0),
                'resolved_incidents' => (int)($incident_stats['resolved'] ?? 0),
                'party_totals' => $party_totals,
                'completion_percentage' => $total_pus > 0 ? round((($result_stats['total_reported'] ?? 0) / $total_pus) * 100, 1) : 0
            ];
        }
    } catch (Exception $e) {
        error_log("Error fetching election details: " . $e->getMessage());
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
/* Reuse styles from previous reports */
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

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 20px;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 200px;
}
.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-bar .btn-filter {
    padding: 8px 24px;
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

.election-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 20px;
}
.election-header .title {
    font-size: 1.2rem;
    font-weight: 700;
}
.election-header .meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 6px;
    color: var(--gray-500);
    font-size: 0.85rem;
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
.badge-status.draft { background: #F3F4F6; color: #6B7280; }
.badge-status.draft .dot { background: #9CA3AF; }
.badge-status.upcoming { background: #FFFBEB; color: #92400E; }
.badge-status.upcoming .dot { background: #F59E0B; }
.badge-status.active { background: #ECFDF5; color: #065F46; }
.badge-status.active .dot { background: #10B981; }
.badge-status.closed { background: #FEF2F2; color: #991B1B; }
.badge-status.closed .dot { background: #EF4444; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
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
.stat-card .number.primary { color: #3B82F6; }
.stat-card .number.success { color: #10B981; }
.stat-card .number.warning { color: #F59E0B; }
.stat-card .number.danger { color: #EF4444; }

.party-summary {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
}
.party-summary .title {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 12px;
}
.party-summary .title i {
    color: var(--primary);
    margin-right: 6px;
}
.party-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 8px;
}
.party-item {
    display: flex;
    justify-content: space-between;
    padding: 6px 12px;
    background: var(--gray-50);
    border-radius: 6px;
}
.party-item .name {
    font-weight: 500;
}
.party-item .votes {
    font-weight: 700;
    color: var(--gray-800);
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

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar select {
        width: 100%;
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
                    <i class="fas fa-file-alt" style="color:var(--primary);margin-right:8px;"></i>
                    Election Report
                    <small><?php echo htmlspecialchars($state_name); ?> - Detailed election report</small>
                </h2>
            </div>
            <div>
                <a href="reports-state.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Reports
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="filter-bar">
            <select name="election_id" required>
                <option value="">Select Election</option>
                <?php foreach ($elections as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo $election_filter == $e['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-file-alt"></i> Generate Report</button>
        </form>

        <?php if ($election_filter > 0 && $election_data): ?>
            <!-- Election Header -->
            <div class="election-header">
                <div class="title"><?php echo htmlspecialchars($election_data['name']); ?></div>
                <div class="meta">
                    <span><i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $election_data['type'])); ?></span>
                    <span><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($election_data['election_date'])); ?></span>
                    <span>
                        <span class="badge-status <?php echo $election_data['status']; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst($election_data['status']); ?>
                        </span>
                    </span>
                    <span><i class="fas fa-user"></i> Created by: <?php echo htmlspecialchars($election_data['created_by_first'] . ' ' . $election_data['created_by_last']); ?></span>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number primary"><?php echo number_format($election_stats['total_pus']); ?></div>
                    <div class="label">Total PUs</div>
                </div>
                <div class="stat-card">
                    <div class="number success"><?php echo number_format($election_stats['reported_pus']); ?></div>
                    <div class="label">Reported</div>
                    <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo $election_stats['completion_percentage']; ?>%</div>
                </div>
                <div class="stat-card">
                    <div class="number warning"><?php echo number_format($election_stats['pending_pus']); ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="number success"><?php echo number_format($election_stats['verified_pus']); ?></div>
                    <div class="label">Verified</div>
                </div>
                <div class="stat-card">
                    <div class="number danger"><?php echo number_format($election_stats['rejected_pus']); ?></div>
                    <div class="label">Rejected</div>
                </div>
                <div class="stat-card">
                    <div class="number primary"><?php echo number_format($election_stats['total_votes_cast']); ?></div>
                    <div class="label">Total Votes Cast</div>
                </div>
                <div class="stat-card">
                    <div class="number danger"><?php echo number_format($election_stats['total_incidents']); ?></div>
                    <div class="label">Incidents</div>
                    <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo number_format($election_stats['resolved_incidents']); ?> resolved</div>
                </div>
            </div>

            <!-- Party Summary -->
            <?php if (!empty($election_stats['party_totals'])): ?>
                <div class="party-summary">
                    <div class="title"><i class="fas fa-vote-yea"></i> Party-wise Results</div>
                    <div class="party-grid">
                        <?php foreach ($election_stats['party_totals'] as $party => $votes): ?>
                            <div class="party-item">
                                <span class="name"><?php echo htmlspecialchars($party); ?></span>
                                <span class="votes"><?php echo number_format($votes); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($election_filter > 0): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>Election data not found.</p>
                <p style="font-size:0.8rem;">Please select a valid election.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>Select an election to view the detailed report.</p>
                <p style="font-size:0.8rem;">The report will show comprehensive data for the selected election.</p>
            </div>
        <?php endif; ?>
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
</script>
</body>
</html>