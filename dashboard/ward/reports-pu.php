<?php
// ============================================================
// WARD COORDINATOR - POLLING UNIT REPORT
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
// FETCH WARD, LGA, STATE NAMES
// ============================================================
$ward_name = 'Ward';
$lga_name = 'LGA';
$state_name = 'State';

try {
    if ($ward_id) {
        $stmt = $db->prepare("
            SELECT 
                w.name as ward_name,
                l.name as lga_name,
                s.name as state_name
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            WHERE w.id = ?
        ");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['ward_name'];
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward details: " . $e->getMessage());
}

// ============================================================
// GET PU ID
// ============================================================
$pu_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($pu_id <= 0) {
    header('Location: polling-units.php');
    exit();
}

// ============================================================
// FETCH PU DETAILS AND STATISTICS
// ============================================================
$pu = null;
$pu_stats = [];
$submissions = [];
$agents = [];

try {
    // Get PU details
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        WHERE pu.id = ? AND pu.ward_id = ?
    ");
    $stmt->execute([$pu_id, $ward_id]);
    $pu = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pu) {
        header('Location: polling-units.php?error=notfound');
        exit();
    }
    
    // Get PU statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT u.id) as total_agents,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_agents,
            COUNT(DISTINCT r.id) as total_submissions,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified_submissions,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_submissions,
            SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected_submissions,
            COUNT(DISTINCT i.id) as total_incidents,
            SUM(CASE WHEN i.status IN ('reported', 'investigating') THEN 1 ELSE 0 END) as active_incidents,
            SUM(CASE WHEN i.status = 'resolved' THEN 1 ELSE 0 END) as resolved_incidents
        FROM polling_units pu
        LEFT JOIN users u ON u.pu_id = pu.id AND u.deleted_at IS NULL
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        LEFT JOIN incidents i ON i.pu_id = pu.id
        WHERE pu.id = ?
    ");
    $stmt->execute([$tenant_id, $pu_id]);
    $pu_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get submissions
    $stmt = $db->prepare("
        SELECT 
            r.*,
            u.full_name as agent_name,
            u.user_code as agent_code
        FROM results_ec8a r
        JOIN users u ON r.agent_id = u.id
        WHERE r.pu_id = ? AND r.tenant_id = ?
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$pu_id, $tenant_id]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get agents assigned to this PU
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.user_code,
            u.email,
            u.phone,
            u.status,
            u.created_at,
            r.name as role_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.pu_id = ? AND u.deleted_at IS NULL
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$pu_id]);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching PU report: " . $e->getMessage());
    header('Location: polling-units.php?error=db');
    exit();
}

$page_title = 'Polling Unit Report';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.report-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.report-header h2 i {
    color: var(--primary);
}
.report-header .location {
    font-size: 0.85rem;
    color: var(--gray-500);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 14px 16px;
    text-align: center;
}
.stat-card .number {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-card .number.green { color: #10B981; }
.stat-card .number.blue { color: #3B82F6; }
.stat-card .number.orange { color: #F59E0B; }
.stat-card .number.red { color: #EF4444; }
.stat-card .number.purple { color: #8B5CF6; }
.stat-card .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    font-weight: 500;
}

.report-section {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 16px;
}
.report-section .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.report-section .section-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}
.report-section .section-header .count {
    font-size: 0.7rem;
    color: var(--gray-400);
}

.table-container {
    overflow-x: auto;
}
.table-container table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.table-container th {
    background: var(--gray-50);
    padding: 8px 12px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-600);
    border-bottom: 2px solid var(--gray-200);
    white-space: nowrap;
}
.table-container td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--gray-100);
}
.table-container tr:hover td {
    background: var(--gray-50);
}

.status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.status-badge.verified { background: #D1FAE5; color: #065F46; }
.status-badge.pending { background: #FEF3C7; color: #92400E; }
.status-badge.rejected { background: #FEE2E2; color: #991B1B; }
.status-badge.active { background: #ECFDF5; color: #10B981; }
.status-badge.suspended { background: #FEF2F2; color: #EF4444; }

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 4px 16px;
}
.info-grid .item {
    font-size: 0.85rem;
    padding: 4px 0;
}
.info-grid .item .label {
    color: var(--gray-500);
    font-weight: 500;
}
.info-grid .item .value {
    color: var(--gray-800);
}

.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--gray-400);
}
.empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 8px;
}

.export-buttons {
    display: flex;
    gap: 8px;
}
.export-buttons .btn-sm {
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
.export-buttons .btn-sm.pdf { background: #FEE2E2; color: #991B1B; }
.export-buttons .btn-sm.excel { background: #D1FAE5; color: #065F46; }

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="report-header">
            <div>
                <h2><i class="fas fa-flag-checkered"></i> Polling Unit Report</h2>
                <div class="location">
                    <?php echo htmlspecialchars($pu['name'] ?? ''); ?> (<?php echo htmlspecialchars($pu['code'] ?? ''); ?>) • 
                    <?php echo htmlspecialchars($ward_name); ?> Ward • 
                    <?php echo htmlspecialchars($lga_name); ?> LGA • 
                    <?php echo htmlspecialchars($state_name); ?> State
                </div>
            </div>
            <div class="export-buttons">
                <a href="export-pdf.php?type=pu&pu_id=<?php echo $pu_id; ?>" class="btn-sm pdf">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="export-excel.php?type=pu&pu_id=<?php echo $pu_id; ?>" class="btn-sm excel">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="polling-units.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if ($pu): ?>
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number blue"><?php echo number_format($pu['registered_voters'] ?? 0); ?></div>
                    <div class="label">Registered Voters</div>
                </div>
                <div class="stat-card">
                    <div class="number purple"><?php echo number_format($pu_stats['total_agents'] ?? 0); ?></div>
                    <div class="label">Total Agents</div>
                </div>
                <div class="stat-card">
                    <div class="number green"><?php echo number_format($pu_stats['verified_submissions'] ?? 0); ?></div>
                    <div class="label">Verified Results</div>
                </div>
                <div class="stat-card">
                    <div class="number orange"><?php echo number_format($pu_stats['pending_submissions'] ?? 0); ?></div>
                    <div class="label">Pending Results</div>
                </div>
                <div class="stat-card">
                    <div class="number red"><?php echo number_format($pu_stats['total_incidents'] ?? 0); ?></div>
                    <div class="label">Total Incidents</div>
                </div>
                <div class="stat-card">
                    <div class="number blue"><?php echo number_format($pu_stats['active_agents'] ?? 0); ?></div>
                    <div class="label">Active Agents</div>
                </div>
            </div>

            <!-- PU Information -->
            <div class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-info-circle"></i> Polling Unit Information</h3>
                </div>
                <div class="info-grid">
                    <div class="item"><span class="label">Name</span> <?php echo htmlspecialchars($pu['name']); ?></div>
                    <div class="item"><span class="label">Code</span> <?php echo htmlspecialchars($pu['code']); ?></div>
                    <div class="item"><span class="label">Ward</span> <?php echo htmlspecialchars($pu['ward_name']); ?></div>
                    <div class="item"><span class="label">LGA</span> <?php echo htmlspecialchars($pu['lga_name']); ?></div>
                    <div class="item"><span class="label">State</span> <?php echo htmlspecialchars($pu['state_name']); ?></div>
                    <div class="item"><span class="label">Type</span> <?php echo ($pu['is_rural'] ?? 0) ? 'Rural' : 'Urban'; ?></div>
                    <div class="item"><span class="label">Status</span> <?php echo ($pu['is_active'] ?? 0) ? 'Active' : 'Inactive'; ?></div>
                    <?php if (!empty($pu['gps_lat']) && !empty($pu['gps_lng'])): ?>
                        <div class="item"><span class="label">GPS</span> <?php echo round($pu['gps_lat'], 6); ?>, <?php echo round($pu['gps_lng'], 6); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Agents -->
            <div class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-user-tie"></i> Assigned Agents</h3>
                    <span class="count"><?php echo count($agents); ?> agents</span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($agents) > 0): ?>
                                <?php foreach ($agents as $agent): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($agent['full_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($agent['user_code']); ?></td>
                                        <td><?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $agent['role_name'] ?? 'Agent')); ?></td>
                                        <td><span class="status-badge <?php echo $agent['status'] ?? 'pending'; ?>"><?php echo ucfirst($agent['status'] ?? 'Pending'); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center;color:var(--gray-400);padding:20px;">No agents assigned</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Submissions -->
            <div class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-file-alt"></i> Recent Submissions</h3>
                    <span class="count">Last <?php echo min(count($submissions), 50); ?> submissions</span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Valid Votes</th>
                                <th>Rejected</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($submissions) > 0): ?>
                                <?php foreach ($submissions as $sub): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['agent_name']); ?></td>
                                        <td><?php echo number_format($sub['valid_votes'] ?? 0); ?></td>
                                        <td><?php echo number_format($sub['rejected_votes'] ?? 0); ?></td>
                                        <td><?php echo number_format($sub['total_votes_cast'] ?? 0); ?></td>
                                        <td><span class="status-badge <?php echo $sub['status']; ?>"><?php echo ucfirst($sub['status']); ?></span></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($sub['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center;color:var(--gray-400);padding:20px;">No submissions</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
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