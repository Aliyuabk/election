<?php
// ============================================================
// WARD COORDINATOR - AGENT REPORT
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
GET AGENT ID
// ============================================================
$agent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($agent_id <= 0) {
    header('Location: manage-pu-agents.php');
    exit();
}

// ============================================================
// FETCH AGENT DETAILS AND STATISTICS
// ============================================================
$agent = null;
$agent_stats = [];
$submissions = [];
$assignments = [];

try {
    // Get agent details
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN wards w ON u.ward_id = w.id
        LEFT JOIN lgas l ON u.lga_id = l.id
        LEFT JOIN states s ON u.state_id = s.id
        WHERE u.id = ? AND u.tenant_id = ? AND u.ward_id = ?
        AND u.deleted_at IS NULL
    ");
    $stmt->execute([$agent_id, $tenant_id, $ward_id]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        header('Location: manage-pu-agents.php?error=notfound');
        exit();
    }
    
    // Get agent statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT r.id) as total_submissions,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN r.status = 'flagged' THEN 1 ELSE 0 END) as flagged,
            COUNT(DISTINCT i.id) as incidents_reported,
            SUM(CASE WHEN i.status = 'resolved' THEN 1 ELSE 0 END) as incidents_resolved,
            COUNT(DISTINCT aa.id) as total_assignments,
            SUM(CASE WHEN aa.status = 'active' THEN 1 ELSE 0 END) as active_assignments
        FROM users u
        LEFT JOIN results_ec8a r ON r.agent_id = u.id
        LEFT JOIN incidents i ON i.reporter_id = u.id
        LEFT JOIN agent_assignments aa ON aa.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$agent_id]);
    $agent_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get submissions
    $stmt = $db->prepare("
        SELECT 
            r.*,
            pu.name as pu_name,
            pu.code as pu_code
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        WHERE r.agent_id = ?
        ORDER BY r.created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$agent_id]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get assignments
    $stmt = $db->prepare("
        SELECT 
            aa.*,
            pu.name as pu_name,
            pu.code as pu_code,
            assigned.full_name as assigned_by_name
        FROM agent_assignments aa
        LEFT JOIN polling_units pu ON aa.pu_id = pu.id
        LEFT JOIN users assigned ON aa.assigned_by = assigned.id
        WHERE aa.user_id = ?
        ORDER BY aa.assigned_at DESC
        LIMIT 20
    ");
    $stmt->execute([$agent_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching agent report: " . $e->getMessage());
    header('Location: manage-pu-agents.php?error=db');
    exit();
}

$page_title = 'Agent Report';
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

.agent-profile {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    gap: 24px;
    align-items: center;
    flex-wrap: wrap;
}
.agent-profile .avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    color: var(--gray-600);
    flex-shrink: 0;
}
.agent-profile .info {
    flex: 1;
}
.agent-profile .info .name {
    font-size: 1.2rem;
    font-weight: 700;
}
.agent-profile .info .details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 4px 16px;
    margin-top: 8px;
}
.agent-profile .info .details .item {
    font-size: 0.82rem;
}
.agent-profile .info .details .item .label {
    color: var(--gray-500);
    font-weight: 500;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 12px 14px;
    text-align: center;
}
.stat-card .number {
    font-size: 1.3rem;
    font-weight: 700;
}
.stat-card .number.green { color: #10B981; }
.stat-card .number.blue { color: #3B82F6; }
.stat-card .number.orange { color: #F59E0B; }
.stat-card .number.red { color: #EF4444; }
.stat-card .number.purple { color: #8B5CF6; }
.stat-card .label {
    font-size: 0.65rem;
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
    .agent-profile {
        flex-direction: column;
        text-align: center;
    }
    .agent-profile .info .details {
        grid-template-columns: 1fr;
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
        <div class="report-header">
            <div>
                <h2><i class="fas fa-user-tie"></i> Agent Report</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div class="export-buttons">
                <a href="export-pdf.php?type=agent&agent_id=<?php echo $agent_id; ?>" class="btn-sm pdf">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="export-excel.php?type=agent&agent_id=<?php echo $agent_id; ?>" class="btn-sm excel">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="manage-pu-agents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if ($agent): ?>
            <!-- Agent Profile -->
            <div class="agent-profile">
                <div class="avatar">
                    <?php echo strtoupper(substr($agent['full_name'] ?? 'U', 0, 2)); ?>
                </div>
                <div class="info">
                    <div class="name"><?php echo htmlspecialchars($agent['full_name']); ?></div>
                    <div class="details">
                        <div class="item"><span class="label">Code</span> <?php echo htmlspecialchars($agent['user_code']); ?></div>
                        <div class="item"><span class="label">Email</span> <?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?></div>
                        <div class="item"><span class="label">Phone</span> <?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?></div>
                        <div class="item"><span class="label">Role</span> <?php echo ucfirst(str_replace('_', ' ', $agent['role_name'] ?? 'Agent')); ?></div>
                        <div class="item"><span class="label">PU</span> <?php echo htmlspecialchars($agent['pu_name'] ?? 'Not Assigned'); ?></div>
                        <div class="item"><span class="label">Status</span> <span class="status-badge <?php echo $agent['status']; ?>"><?php echo ucfirst($agent['status']); ?></span></div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number blue"><?php echo number_format($agent_stats['total_submissions'] ?? 0); ?></div>
                    <div class="label">Total Submissions</div>
                </div>
                <div class="stat-card">
                    <div class="number green"><?php echo number_format($agent_stats['verified'] ?? 0); ?></div>
                    <div class="label">Verified</div>
                </div>
                <div class="stat-card">
                    <div class="number orange"><?php echo number_format($agent_stats['pending'] ?? 0); ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="number red"><?php echo number_format($agent_stats['rejected'] ?? 0); ?></div>
                    <div class="label">Rejected</div>
                </div>
                <div class="stat-card">
                    <div class="number purple"><?php echo number_format($agent_stats['incidents_reported'] ?? 0); ?></div>
                    <div class="label">Incidents Reported</div>
                </div>
                <div class="stat-card">
                    <div class="number blue"><?php echo number_format($agent_stats['total_assignments'] ?? 0); ?></div>
                    <div class="label">Assignments</div>
                </div>
            </div>

            <!-- Submissions -->
            <div class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-file-alt"></i> Submission History</h3>
                    <span class="count">Last <?php echo min(count($submissions), 30); ?> submissions</span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>PU</th>
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
                                        <td><?php echo htmlspecialchars($sub['pu_name']); ?></td>
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

            <!-- Assignments -->
            <div class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-clipboard-list"></i> Assignment History</h3>
                    <span class="count">Last <?php echo min(count($assignments), 20); ?> assignments</span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>PU</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Assigned By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($assignments) > 0): ?>
                                <?php foreach ($assignments as $assign): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assign['pu_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $assign['assignment_type'] ?? '')); ?></td>
                                        <td><span class="status-badge <?php echo $assign['status']; ?>"><?php echo ucfirst($assign['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($assign['assigned_by_name'] ?? 'System'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($assign['assigned_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center;color:var(--gray-400);padding:20px;">No assignments</td></tr>
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