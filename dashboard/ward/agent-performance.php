<?php
// ============================================================
// WARD COORDINATOR - AGENT PERFORMANCE
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
// GET AGENT ID FROM URL
// ============================================================
$agent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$agent = null;

// ============================================================
// FETCH AGENT PERFORMANCE DATA
// ============================================================
$performance_data = [];
$submission_history = [];
$checkin_history = [];

try {
    if ($agent_id > 0) {
        // Get agent details
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.user_code,
                u.email,
                u.phone,
                u.status,
                u.created_at,
                u.pu_id,
                pu.name as pu_name,
                pu.code as pu_code,
                r.name as role_name,
                r.level as role_level
            FROM users u
            LEFT JOIN polling_units pu ON u.pu_id = pu.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND u.tenant_id = ? AND u.ward_id = ?
            AND u.deleted_at IS NULL
        ");
        $stmt->execute([$agent_id, $tenant_id, $ward_id]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($agent) {
            // Performance statistics
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_submissions,
                    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged,
                    COUNT(DISTINCT pu_id) as unique_pus,
                    MAX(created_at) as last_submission
                FROM results_ec8a
                WHERE agent_id = ?
            ");
            $stmt->execute([$agent_id]);
            $performance_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Submission history (last 20)
            $stmt = $db->prepare("
                SELECT 
                    r.id,
                    r.pu_id,
                    r.pu_name,
                    r.pu_code,
                    r.status,
                    r.created_at,
                    r.verified_at,
                    pu.name as pu_display_name
                FROM results_ec8a r
                LEFT JOIN polling_units pu ON r.pu_id = pu.id
                WHERE r.agent_id = ?
                ORDER BY r.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$agent_id]);
            $submission_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check-in history
            $stmt = $db->prepare("
                SELECT 
                    ac.*,
                    pu.name as pu_name,
                    pu.code as pu_code
                FROM agent_checkins ac
                LEFT JOIN polling_units pu ON ac.pu_id = pu.id
                WHERE ac.agent_id = ?
                ORDER BY ac.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$agent_id]);
            $checkin_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching performance data: " . $e->getMessage());
}

// ============================================================
// FETCH ALL AGENTS FOR SELECTION
// ============================================================
$agents = [];
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.user_code,
            u.status,
            pu.name as pu_name
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
        AND EXISTS (SELECT 1 FROM roles r WHERE r.id = u.role_id AND r.level = 'pu_agent')
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching agents: " . $e->getMessage());
}

$page_title = 'Agent Performance';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.performance-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.performance-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.performance-header h2 i {
    color: var(--primary);
}

.agent-selector {
    background: white;
    padding: 16px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    margin-bottom: 20px;
}
.agent-selector .form-row {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}
.agent-selector select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.9rem;
    min-width: 250px;
    background: white;
}

.profile-summary {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 20px;
    margin-bottom: 20px;
}
.profile-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    text-align: center;
}
.profile-card .avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--gray-200);
    margin: 0 auto 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    color: var(--gray-600);
}
.profile-card .name {
    font-size: 1.1rem;
    font-weight: 700;
}
.profile-card .code {
    font-size: 0.75rem;
    color: var(--gray-400);
}
.profile-card .pu {
    font-size: 0.85rem;
    color: var(--gray-600);
    margin-top: 4px;
}
.profile-card .status-badge {
    display: inline-block;
    padding: 2px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 500;
    margin-top: 6px;
}
.profile-card .status-badge.active { background: #ECFDF5; color: #10B981; }
.profile-card .status-badge.suspended { background: #FEF2F2; color: #EF4444; }
.profile-card .status-badge.pending { background: #FFFBEB; color: #F59E0B; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}
.stat-box {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 14px 16px;
    text-align: center;
}
.stat-box .number {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-box .number.green { color: #10B981; }
.stat-box .number.blue { color: #3B82F6; }
.stat-box .number.orange { color: #F59E0B; }
.stat-box .number.red { color: #EF4444; }
.stat-box .number.purple { color: #8B5CF6; }
.stat-box .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    font-weight: 500;
}
.stat-box .sub {
    font-size: 0.6rem;
    color: var(--gray-400);
}

.performance-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
}
.card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.card .card-header h3 {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
}
.card .card-header .count {
    font-size: 0.7rem;
    color: var(--gray-400);
}

.history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.82rem;
}
.history-item:last-child {
    border-bottom: none;
}
.history-item .info {
    flex: 1;
}
.history-item .info .title {
    font-weight: 500;
}
.history-item .info .sub {
    font-size: 0.7rem;
    color: var(--gray-500);
}
.history-item .status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.history-item .status-badge.verified { background: #D1FAE5; color: #065F46; }
.history-item .status-badge.pending { background: #FEF3C7; color: #92400E; }
.history-item .status-badge.rejected { background: #FEE2E2; color: #991B1B; }
.history-item .time {
    font-size: 0.65rem;
    color: var(--gray-400);
    white-space: nowrap;
    margin-left: 8px;
}

.checkin-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 0;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.78rem;
}
.checkin-item:last-child {
    border-bottom: none;
}
.checkin-item .type-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    flex-shrink: 0;
}
.checkin-item .type-icon.arrival { background: #D1FAE5; color: #065F46; }
.checkin-item .type-icon.departure { background: #FEE2E2; color: #991B1B; }
.checkin-item .type-icon.voting { background: #DBEAFE; color: #1E40AF; }
.checkin-item .type-icon.counting { background: #FEF3C7; color: #92400E; }

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
.empty-state p {
    font-size: 0.85rem;
    margin: 0;
}

@media (max-width: 1024px) {
    .profile-summary {
        grid-template-columns: 1fr;
    }
    .performance-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .agent-selector .form-row {
        flex-direction: column;
        align-items: stretch;
    }
    .agent-selector select {
        min-width: unset;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="performance-header">
            <div>
                <h2><i class="fas fa-chart-bar"></i> Agent Performance</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div>
                <a href="manage-pu-agents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Agents
                </a>
            </div>
        </div>

        <!-- Agent Selector -->
        <div class="agent-selector">
            <form method="GET" action="">
                <div class="form-row">
                    <label style="font-weight:600;font-size:0.85rem;">
                        <i class="fas fa-user"></i> Select Agent:
                    </label>
                    <select name="id" onchange="this.form.submit()">
                        <option value="0">-- Select an Agent --</option>
                        <?php foreach ($agents as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $agent_id == $a['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['full_name']); ?> 
                                (<?php echo htmlspecialchars($a['user_code']); ?>)
                                <?php if (!empty($a['pu_name'])): ?>
                                    - <?php echo htmlspecialchars($a['pu_name']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($agent_id > 0 && $agent): ?>
                        <a href="agent-performance.php" class="btn-secondary-sm">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($agent_id > 0 && $agent): ?>
            <!-- Profile Summary -->
            <div class="profile-summary">
                <div class="profile-card">
                    <div class="avatar">
                        <?php echo strtoupper(substr($agent['full_name'] ?? 'U', 0, 2)); ?>
                    </div>
                    <div class="name"><?php echo htmlspecialchars($agent['full_name']); ?></div>
                    <div class="code"><?php echo htmlspecialchars($agent['user_code']); ?></div>
                    <div class="pu">
                        <?php if (!empty($agent['pu_name'])): ?>
                            <i class="fas fa-flag-checkered"></i> <?php echo htmlspecialchars($agent['pu_name']); ?>
                        <?php else: ?>
                            <span style="color:var(--gray-400);">Not Assigned to PU</span>
                        <?php endif; ?>
                    </div>
                    <span class="status-badge <?php echo $agent['status'] ?? 'pending'; ?>">
                        <?php echo ucfirst($agent['status'] ?? 'Pending'); ?>
                    </span>
                    <div style="margin-top:8px;font-size:0.7rem;color:var(--gray-400);">
                        <i class="fas fa-calendar"></i> Joined <?php echo date('M d, Y', strtotime($agent['created_at'])); ?>
                        <br>
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($agent['email'] ?? 'No email'); ?>
                        <br>
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($agent['phone'] ?? 'No phone'); ?>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="number blue"><?php echo number_format($performance_data['total_submissions'] ?? 0); ?></div>
                        <div class="label">Total Submissions</div>
                        <div class="sub">All time</div>
                    </div>
                    <div class="stat-box">
                        <div class="number green"><?php echo number_format($performance_data['verified'] ?? 0); ?></div>
                        <div class="label">Verified</div>
                        <div class="sub">Approved results</div>
                    </div>
                    <div class="stat-box">
                        <div class="number orange"><?php echo number_format($performance_data['pending'] ?? 0); ?></div>
                        <div class="label">Pending</div>
                        <div class="sub">Awaiting review</div>
                    </div>
                    <div class="stat-box">
                        <div class="number red"><?php echo number_format($performance_data['rejected'] ?? 0); ?></div>
                        <div class="label">Rejected</div>
                        <div class="sub">Needs correction</div>
                    </div>
                    <div class="stat-box">
                        <div class="number purple"><?php echo number_format($performance_data['unique_pus'] ?? 0); ?></div>
                        <div class="label">Polling Units</div>
                        <div class="sub">Unique PUs worked</div>
                    </div>
                    <div class="stat-box">
                        <div class="number blue"><?php echo !empty($performance_data['last_submission']) ? date('M d, Y', strtotime($performance_data['last_submission'])) : 'N/A'; ?></div>
                        <div class="label">Last Submission</div>
                        <div class="sub">Most recent activity</div>
                    </div>
                </div>
            </div>

            <!-- Performance Grid -->
            <div class="performance-grid">
                <!-- Submission History -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-alt"></i> Submission History</h3>
                        <span class="count">Last 20 submissions</span>
                    </div>
                    <?php if (count($submission_history) > 0): ?>
                        <?php foreach ($submission_history as $sub): ?>
                            <div class="history-item">
                                <div class="info">
                                    <div class="title"><?php echo htmlspecialchars($sub['pu_display_name'] ?? $sub['pu_name'] ?? 'Unknown PU'); ?></div>
                                    <div class="sub">
                                        <?php echo htmlspecialchars($sub['pu_code'] ?? ''); ?>
                                        <?php if (!empty($sub['pu_id'])): ?>
                                            • PU #<?php echo $sub['pu_id']; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="status-badge <?php echo $sub['status']; ?>">
                                        <?php echo ucfirst($sub['status']); ?>
                                    </span>
                                </div>
                                <div class="time"><?php echo date('M d, H:i', strtotime($sub['created_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>No submissions yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Check-in History -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Check-in History</h3>
                        <span class="count">Last 20 check-ins</span>
                    </div>
                    <?php if (count($checkin_history) > 0): ?>
                        <?php foreach ($checkin_history as $check): 
                            $type_map = [
                                'arrival' => ['icon' => 'fa-sign-in-alt', 'class' => 'arrival', 'label' => 'Arrived'],
                                'departure' => ['icon' => 'fa-sign-out-alt', 'class' => 'departure', 'label' => 'Departed'],
                                'accreditation_started' => ['icon' => 'fa-user-check', 'class' => 'voting', 'label' => 'Accreditation'],
                                'voting_started' => ['icon' => 'fa-vote-yea', 'class' => 'voting', 'label' => 'Voting Started'],
                                'voting_ended' => ['icon' => 'fa-stop', 'class' => 'voting', 'label' => 'Voting Ended'],
                                'counting_started' => ['icon' => 'fa-calculator', 'class' => 'counting', 'label' => 'Counting Started'],
                                'counting_ended' => ['icon' => 'fa-check', 'class' => 'counting', 'label' => 'Counting Ended']
                            ];
                            $type_info = $type_map[$check['checkin_type']] ?? ['icon' => 'fa-clock', 'class' => 'arrival', 'label' => $check['checkin_type']];
                        ?>
                            <div class="checkin-item">
                                <div class="type-icon <?php echo $type_info['class']; ?>">
                                    <i class="fas <?php echo $type_info['icon']; ?>"></i>
                                </div>
                                <div class="info" style="flex:1;">
                                    <div style="font-weight:500;">
                                        <?php echo $type_info['label']; ?>
                                        <?php if (!empty($check['pu_name'])): ?>
                                            • <?php echo htmlspecialchars($check['pu_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($check['gps_lat']) && !empty($check['gps_lng'])): ?>
                                        <div style="font-size:0.65rem;color:var(--gray-400);">
                                            📍 <?php echo round($check['gps_lat'], 6); ?>, <?php echo round($check['gps_lng'], 6); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="time"><?php echo date('M d, H:i', strtotime($check['created_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-clock"></i>
                            <p>No check-ins recorded</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($agent_id > 0): ?>
            <div style="text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                <i class="fas fa-user-tie" style="font-size:4rem;color:var(--gray-300);"></i>
                <h4 style="margin:16px 0 8px;">Agent Not Found</h4>
                <p style="color:var(--gray-500);">The agent you're looking for does not exist or is not in your ward.</p>
                <a href="manage-pu-agents.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-arrow-left"></i> Back to Agents
                </a>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                <i class="fas fa-chart-bar" style="font-size:4rem;color:var(--gray-300);"></i>
                <h4 style="margin:16px 0 8px;">Select an Agent</h4>
                <p style="color:var(--gray-500);">Choose an agent from the dropdown above to view their performance.</p>
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