<?php
// ============================================================
// WARD COORDINATOR - UPLOAD STATUS
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
// FETCH UPLOAD STATUS
// ============================================================
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

$upload_status = [];
$summary = [];
$elections = [];

try {
    // Get elections
    $stmt = $db->prepare("
        SELECT id, name, status, election_date 
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
        // Get upload status for each PU
        $stmt = $db->prepare("
            SELECT 
                pu.id,
                pu.name,
                pu.code,
                pu.registered_voters,
                COUNT(DISTINCT u.id) as total_agents,
                COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_agents,
                COUNT(DISTINCT r.id) as total_submissions,
                SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                MAX(r.created_at) as last_submission
            FROM polling_units pu
            LEFT JOIN users u ON u.pu_id = pu.id AND u.deleted_at IS NULL
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.election_id = ? AND r.tenant_id = ?
            WHERE pu.ward_id = ? AND pu.is_active = 1
            GROUP BY pu.id, pu.name, pu.code, pu.registered_voters
            ORDER BY pu.name ASC
        ");
        $stmt->execute([$election_id, $tenant_id, $ward_id]);
        $upload_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Summary
        $total_pus = count($upload_status);
        $submitted = count(array_filter($upload_status, function($pu) { return (int)($pu['total_submissions'] ?? 0) > 0; }));
        $pending = count(array_filter($upload_status, function($pu) { return (int)($pu['pending'] ?? 0) > 0; }));
        $verified = count(array_filter($upload_status, function($pu) { return (int)($pu['verified'] ?? 0) > 0; }));
        
        $summary['total_pus'] = $total_pus;
        $summary['submitted'] = $submitted;
        $summary['pending'] = $pending;
        $summary['verified'] = $verified;
        $summary['not_submitted'] = $total_pus - $submitted;
        $summary['completion_rate'] = $total_pus > 0 ? round(($verified / $total_pus) * 100, 1) : 0;
    }
    
} catch (Exception $e) {
    error_log("Error fetching upload status: " . $e->getMessage());
}

$page_title = 'Upload Status';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.status-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.status-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.status-header h2 i {
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
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
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

.status-table {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.status-table table {
    width: 100%;
    border-collapse: collapse;
}
.status-table th {
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
.status-table td {
    padding: 10px 14px;
    font-size: 0.82rem;
    border-bottom: 1px solid var(--gray-100);
}
.status-table tr:hover td {
    background: var(--gray-50);
}

.status-indicator {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 6px;
}
.status-indicator.verified { background: #10B981; }
.status-indicator.pending { background: #F59E0B; }
.status-indicator.rejected { background: #EF4444; }
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
    .status-table {
        overflow-x: auto;
    }
    .status-table table {
        min-width: 700px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="status-header">
            <div>
                <h2><i class="fas fa-upload"></i> Upload Status</h2>
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

        <?php if ($election_id > 0 && !empty($upload_status)): ?>
            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-mini">
                    <div class="number blue"><?php echo number_format($summary['total_pus']); ?></div>
                    <div class="label">Total PUs</div>
                </div>
                <div class="stat-mini">
                    <div class="number green"><?php echo number_format($summary['submitted']); ?></div>
                    <div class="label">Submitted</div>
                    <div class="sub"><?php echo $summary['total_pus'] > 0 ? round(($summary['submitted'] / $summary['total_pus']) * 100, 1) : 0; ?>%</div>
                </div>
                <div class="stat-mini">
                    <div class="number red"><?php echo number_format($summary['not_submitted']); ?></div>
                    <div class="label">Not Submitted</div>
                </div>
                <div class="stat-mini">
                    <div class="number green"><?php echo number_format($summary['verified']); ?></div>
                    <div class="label">Verified</div>
                </div>
                <div class="stat-mini">
                    <div class="number orange"><?php echo number_format($summary['pending']); ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-mini">
                    <div class="number green"><?php echo number_format($summary['completion_rate']); ?>%</div>
                    <div class="label">Completion Rate</div>
                </div>
            </div>

            <!-- Status Table -->
            <div class="status-table">
                <table>
                    <thead>
                        <tr>
                            <th>Polling Unit</th>
                            <th>Code</th>
                            <th>Voters</th>
                            <th>Agents</th>
                            <th>Status</th>
                            <th>Submissions</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Last Upload</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upload_status as $pu): 
                            $status_class = 'none';
                            $status_text = 'Not Uploaded';
                            if ((int)($pu['verified'] ?? 0) > 0) {
                                $status_class = 'verified';
                                $status_text = 'Verified';
                            } elseif ((int)($pu['pending'] ?? 0) > 0) {
                                $status_class = 'pending';
                                $status_text = 'Pending';
                            } elseif ((int)($pu['total_submissions'] ?? 0) > 0) {
                                $status_class = 'pending';
                                $status_text = 'Submitted';
                            }
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($pu['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($pu['code']); ?></td>
                                <td><?php echo number_format($pu['registered_voters'] ?? 0); ?></td>
                                <td><?php echo number_format($pu['active_agents'] ?? 0); ?></td>
                                <td>
                                    <span class="status-indicator <?php echo $status_class; ?>"></span>
                                    <?php echo $status_text; ?>
                                </td>
                                <td><?php echo number_format($pu['total_submissions'] ?? 0); ?></td>
                                <td><span style="color:#10B981;"><?php echo number_format($pu['verified'] ?? 0); ?></span></td>
                                <td><span style="color:#F59E0B;"><?php echo number_format($pu['pending'] ?? 0); ?></span></td>
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
                <i class="fas fa-upload"></i>
                <h4>No Data Available</h4>
                <p>No polling units found for this election.</p>
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