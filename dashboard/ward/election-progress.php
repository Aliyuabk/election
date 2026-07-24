<?php
// ============================================================
// WARD COORDINATOR - ELECTION PROGRESS
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
// FETCH ELECTION PROGRESS
// ============================================================
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

$elections = [];
$progress_data = [];
$summary = [];

try {
    // Get elections
    $stmt = $db->prepare("
        SELECT id, name, type, status, election_date, created_at 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($election_id) && !empty($elections)) {
        $election_id = $elections[0]['id'];
    }
    
    if ($election_id > 0) {
        // Get progress data
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT pu.id) as total_pus,
                COUNT(DISTINCT u.id) as total_agents,
                COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_agents,
                COUNT(DISTINCT r.id) as total_submissions,
                SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                COUNT(DISTINCT i.id) as total_incidents,
                SUM(CASE WHEN i.status = 'resolved' THEN 1 ELSE 0 END) as resolved_incidents,
                COUNT(DISTINCT ac.agent_id) as checked_in_agents,
                COUNT(DISTINCT CASE WHEN ac.checkin_type = 'arrival' THEN ac.agent_id END) as arrived_agents
            FROM polling_units pu
            LEFT JOIN users u ON u.pu_id = pu.id AND u.deleted_at IS NULL
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.election_id = ? AND r.tenant_id = ?
            LEFT JOIN incidents i ON i.pu_id = pu.id
            LEFT JOIN agent_checkins ac ON ac.pu_id = pu.id AND DATE(ac.created_at) = CURDATE()
            WHERE pu.ward_id = ? AND pu.is_active = 1
        ");
        $stmt->execute([$election_id, $tenant_id, $ward_id]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get PU progress
        $stmt = $db->prepare("
            SELECT 
                pu.id,
                pu.name,
                pu.code,
                COUNT(DISTINCT r.id) as submissions,
                SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
                MAX(r.created_at) as last_submission,
                COUNT(DISTINCT ac.id) as checkins
            FROM polling_units pu
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.election_id = ? AND r.tenant_id = ?
            LEFT JOIN agent_checkins ac ON ac.pu_id = pu.id AND DATE(ac.created_at) = CURDATE()
            WHERE pu.ward_id = ? AND pu.is_active = 1
            GROUP BY pu.id, pu.name, pu.code
            ORDER BY pu.name ASC
        ");
        $stmt->execute([$election_id, $tenant_id, $ward_id]);
        $progress_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Error fetching election progress: " . $e->getMessage());
}

$page_title = 'Election Progress';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.progress-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.progress-header h2 i {
    color: var(--primary);
}

.election-selector {
    background: white;
    padding: 12px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    margin-bottom: 16px;
}
.election-selector select {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.9rem;
    background: white;
    min-width: 250px;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.stat-mini {
    background: white;
    padding: 10px 14px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-mini .number {
    font-size: 1.2rem;
    font-weight: 700;
}
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .number.red { color: #EF4444; }
.stat-mini .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    font-weight: 500;
}
.stat-mini .sub {
    font-size: 0.55rem;
    color: var(--gray-400);
}

.progress-table {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.progress-table table {
    width: 100%;
    border-collapse: collapse;
}
.progress-table th {
    background: var(--gray-50);
    padding: 10px 14px;
    text-align: left;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid var(--gray-200);
}
.progress-table td {
    padding: 10px 14px;
    font-size: 0.82rem;
    border-bottom: 1px solid var(--gray-100);
}
.progress-table tr:hover td {
    background: var(--gray-50);
}

.progress-bar {
    width: 100px;
    height: 6px;
    background: var(--gray-200);
    border-radius: 3px;
    overflow: hidden;
    display: inline-block;
    vertical-align: middle;
}
.progress-bar .fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease;
}
.progress-bar .fill.verified { background: #10B981; }
.progress-bar .fill.pending { background: #F59E0B; }
.progress-bar .fill.none { background: #D1D5DB; }

.status-indicator {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 6px;
}
.status-indicator.verified { background: #10B981; }
.status-indicator.pending { background: #F59E0B; }
.status-indicator.none { background: #D1D5DB; }

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

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .progress-table {
        overflow-x: auto;
    }
    .progress-table table {
        min-width: 700px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="progress-header">
            <div>
                <h2><i class="fas fa-chart-line"></i> Election Progress</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div>
                <a href="monitor-pus.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Election Selector -->
        <div class="election-selector">
            <form method="GET" action="">
                <label style="font-weight:600;font-size:0.85rem;margin-right:8px;">
                    <i class="fas fa-vote-yea"></i> Select Election:
                </label>
                <select name="election_id" onchange="this.form.submit()">
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo $election_id == $e['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['name']); ?> 
                            (<?php echo date('Y', strtotime($e['election_date'])); ?>)
                            - <?php echo ucfirst($e['status']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($election_id > 0 && !empty($summary)): ?>
            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-mini">
                    <div class="number blue"><?php echo number_format($summary['total_pus'] ?? 0); ?></div>
                    <div class="label">Total PUs</div>
                </div>
                <div class="stat-mini">
                    <div class="number blue"><?php echo number_format($summary['total_agents'] ?? 0); ?></div>
                    <div class="label">Total Agents</div>
                </div>
                <div class="stat-mini">
                    <div class="number green"><?php echo number_format($summary['active_agents'] ?? 0); ?></div>
                    <div class="label">Active Agents</div>
                </div>
                <div class="stat-mini">
                    <div class="number green"><?php echo number_format($summary['arrived_agents'] ?? 0); ?></div>
                    <div class="label">Checked In Today</div>
                </div>
                <div class="stat-mini">
                    <div class="number green"><?php echo number_format($summary['verified'] ?? 0); ?></div>
                    <div class="label">Verified Results</div>
                </div>
                <div class="stat-mini">
                    <div class="number orange"><?php echo number_format($summary['pending'] ?? 0); ?></div>
                    <div class="label">Pending Results</div>
                </div>
                <div class="stat-mini">
                    <div class="number red"><?php echo number_format($summary['total_incidents'] ?? 0); ?></div>
                    <div class="label">Incidents</div>
                </div>
                <div class="stat-mini">
                    <div class="number green"><?php echo number_format($summary['resolved_incidents'] ?? 0); ?></div>
                    <div class="label">Resolved Incidents</div>
                </div>
            </div>

            <!-- Progress Table -->
            <div class="progress-table">
                <table>
                    <thead>
                        <tr>
                            <th>Polling Unit</th>
                            <th>Code</th>
                            <th>Status</th>
                            <th>Submissions</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Check-ins</th>
                            <th>Last Upload</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($progress_data as $pu): 
                            $status_class = 'none';
                            $status_text = 'Not Started';
                            $progress_percent = 0;
                            
                            if ((int)($pu['verified'] ?? 0) > 0) {
                                $status_class = 'verified';
                                $status_text = 'Verified';
                                $progress_percent = 100;
                            } elseif ((int)($pu['submissions'] ?? 0) > 0) {
                                $status_class = 'pending';
                                $status_text = 'Submitted';
                                $progress_percent = 50;
                            }
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($pu['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($pu['code']); ?></td>
                                <td>
                                    <span class="status-indicator <?php echo $status_class; ?>"></span>
                                    <?php echo $status_text; ?>
                                    <div class="progress-bar">
                                        <div class="fill <?php echo $status_class; ?>" style="width: <?php echo $progress_percent; ?>%;"></div>
                                    </div>
                                </td>
                                <td><?php echo number_format($pu['submissions'] ?? 0); ?></td>
                                <td><span style="color:#10B981;"><?php echo number_format($pu['verified'] ?? 0); ?></span></td>
                                <td><span style="color:#F59E0B;"><?php echo number_format($pu['pending'] ?? 0); ?></span></td>
                                <td><?php echo number_format($pu['checkins'] ?? 0); ?></td>
                                <td>
                                    <?php if (!empty($pu['last_submission'])): ?>
                                        <?php echo date('M d, H:i', strtotime($pu['last_submission'])); ?>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);">Never</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($election_id > 0): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h4>No Data Available</h4>
                <p>No progress data found for this election.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-vote-yea"></i>
                <h4>No Elections</h4>
                <p>No elections found for this ward.</p>
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