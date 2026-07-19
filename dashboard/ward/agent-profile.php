<?php
// ============================================================
// WARD COORDINATOR - AGENT PROFILE
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
// GET AGENT ID
// ============================================================
$agent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($agent_id <= 0) {
    header('Location: manage-pu-agents.php');
    exit();
}

// ============================================================
// FETCH AGENT DETAILS
// ============================================================
$agent = null;
$agent_stats = [];
$submission_history = [];
$assignment_history = [];

try {
    // Fetch agent details
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            pu.name as pu_name,
            pu.code as pu_code,
            pu.registered_voters as pu_voters,
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
    ");
    $stmt->execute([$agent_id, $tenant_id, $ward_id]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        header('Location: manage-pu-agents.php?error=notfound');
        exit();
    }
    
    // Fetch agent statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT r.id) as total_submissions,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified_submissions,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_submissions,
            SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected_submissions,
            SUM(CASE WHEN r.status = 'flagged' THEN 1 ELSE 0 END) as flagged_submissions,
            COUNT(DISTINCT i.id) as total_incidents,
            SUM(CASE WHEN i.status = 'reported' THEN 1 ELSE 0 END) as reported_incidents,
            SUM(CASE WHEN i.status = 'resolved' THEN 1 ELSE 0 END) as resolved_incidents,
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
    
    // Fetch submission history (last 20)
    $stmt = $db->prepare("
        SELECT 
            r.id,
            r.pu_id,
            r.status,
            r.created_at,
            r.verified_at,
            pu.name as pu_name,
            pu.code as pu_code
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        WHERE r.agent_id = ?
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$agent_id]);
    $submission_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch assignment history
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
        LIMIT 10
    ");
    $stmt->execute([$agent_id]);
    $assignment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch login history (last 10)
    $stmt = $db->prepare("
        SELECT * FROM login_attempts 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$agent_id]);
    $login_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching agent profile: " . $e->getMessage());
    header('Location: manage-pu-agents.php?error=db');
    exit();
}

$page_title = 'Agent Profile - ' . htmlspecialchars($agent['full_name'] ?? '');
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.profile-header {
    display: flex;
    gap: 24px;
    align-items: flex-start;
    background: white;
    padding: 24px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--gray-600);
    flex-shrink: 0;
}
.profile-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.profile-avatar.online {
    border: 3px solid #10B981;
}
.profile-info {
    flex: 1;
    min-width: 200px;
}
.profile-info h2 {
    margin: 0 0 4px;
    font-size: 1.4rem;
}
.profile-info .role {
    color: var(--primary);
    font-weight: 500;
    font-size: 0.9rem;
}
.profile-info .details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px 24px;
    margin-top: 12px;
}
.profile-info .details .item {
    font-size: 0.82rem;
    color: var(--gray-600);
}
.profile-info .details .item strong {
    color: var(--gray-800);
}
.profile-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-box {
    background: white;
    padding: 14px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-box .number {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-box .number.green { color: #10B981; }
.stat-box .number.blue { color: #3B82F6; }
.stat-box .number.yellow { color: #F59E0B; }
.stat-box .number.red { color: #EF4444; }
.stat-box .number.purple { color: #8B5CF6; }
.stat-box .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    font-weight: 500;
}

.profile-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 16px;
}
.card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.card-header h3 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
}
.card-header a {
    font-size: 0.7rem;
    color: var(--primary);
    text-decoration: none;
}

.history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
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
.history-item .status-badge.verified { background: #ECFDF5; color: #10B981; }
.history-item .status-badge.pending { background: #FFFBEB; color: #F59E0B; }
.history-item .status-badge.rejected { background: #FEF2F2; color: #EF4444; }
.history-item .status-badge.flagged { background: #FEF2F2; color: #F59E0B; }
.history-item .time {
    font-size: 0.65rem;
    color: var(--gray-400);
    white-space: nowrap;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 16px;
}
.info-grid .item {
    font-size: 0.82rem;
    padding: 4px 0;
}
.info-grid .item strong {
    color: var(--gray-600);
    font-weight: 500;
    display: inline-block;
    width: 100px;
}
.info-grid .item .value {
    color: var(--gray-800);
}

@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .profile-grid {
        grid-template-columns: 1fr;
    }
    .info-grid {
        grid-template-columns: 1fr;
    }
    .profile-actions {
        justify-content: center;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar <?php echo $agent['status'] === 'active' ? 'online' : ''; ?>">
                <?php if (!empty($agent['photograph_url'])): ?>
                    <img src="<?php echo htmlspecialchars($agent['photograph_url']); ?>" alt="<?php echo htmlspecialchars($agent['full_name']); ?>">
                <?php else: ?>
                    <?php echo strtoupper(substr($agent['full_name'] ?? 'U', 0, 2)); ?>
                <?php endif; ?>
            </div>
            
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($agent['full_name'] ?? 'N/A'); ?></h2>
                <div class="role">
                    <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($agent['role_name'] ?? 'PU Agent'); ?>
                </div>
                <div class="details">
                    <div class="item"><strong>Code:</strong> <?php echo htmlspecialchars($agent['user_code'] ?? 'N/A'); ?></div>
                    <div class="item"><strong>Email:</strong> <?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?></div>
                    <div class="item"><strong>Phone:</strong> <?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?></div>
                    <div class="item"><strong>Status:</strong> <span class="status-badge <?php echo $agent['status'] ?? 'pending'; ?>"><?php echo ucfirst($agent['status'] ?? 'Pending'); ?></span></div>
                    <div class="item"><strong>Polling Unit:</strong> <?php echo htmlspecialchars($agent['pu_name'] ?? 'Not Assigned'); ?></div>
                    <div class="item"><strong>Ward:</strong> <?php echo htmlspecialchars($agent['ward_name'] ?? 'N/A'); ?></div>
                    <div class="item"><strong>LGA:</strong> <?php echo htmlspecialchars($agent['lga_name'] ?? 'N/A'); ?></div>
                    <div class="item"><strong>State:</strong> <?php echo htmlspecialchars($agent['state_name'] ?? 'N/A'); ?></div>
                </div>
            </div>
            
            <div class="profile-actions">
                <a href="manage-pu-agents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <?php if (empty($agent['pu_id'])): ?>
                    <a href="assign-agents.php?agent_id=<?php echo $agent_id; ?>" class="btn-primary-sm">
                        <i class="fas fa-user-plus"></i> Assign to PU
                    </a>
                <?php else: ?>
                    <a href="reassign-agent.php?agent_id=<?php echo $agent_id; ?>" class="btn-primary-sm">
                        <i class="fas fa-exchange-alt"></i> Reassign
                    </a>
                <?php endif; ?>
                <?php if ($agent['status'] === 'active'): ?>
                    <button onclick="confirmAction(<?php echo $agent_id; ?>, 'suspend')" class="btn-secondary-sm" style="background:#FEF2F2;color:#EF4444;border-color:#FEE2E2;">
                        <i class="fas fa-pause"></i> Suspend
                    </button>
                <?php elseif ($agent['status'] === 'suspended'): ?>
                    <button onclick="confirmAction(<?php echo $agent_id; ?>, 'activate')" class="btn-secondary-sm" style="background:#ECFDF5;color:#10B981;border-color:#D1FAE5;">
                        <i class="fas fa-play"></i> Activate
                    </button>
                <?php endif; ?>
                <a href="agent-performance.php?id=<?php echo $agent_id; ?>" class="btn-secondary-sm">
                    <i class="fas fa-chart-bar"></i> Performance
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="number blue"><?php echo number_format($agent_stats['total_submissions'] ?? 0); ?></div>
                <div class="label">Total Submissions</div>
            </div>
            <div class="stat-box">
                <div class="number green"><?php echo number_format($agent_stats['verified_submissions'] ?? 0); ?></div>
                <div class="label">Verified</div>
            </div>
            <div class="stat-box">
                <div class="number yellow"><?php echo number_format($agent_stats['pending_submissions'] ?? 0); ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-box">
                <div class="number red"><?php echo number_format($agent_stats['rejected_submissions'] ?? 0); ?></div>
                <div class="label">Rejected</div>
            </div>
            <div class="stat-box">
                <div class="number purple"><?php echo number_format($agent_stats['total_incidents'] ?? 0); ?></div>
                <div class="label">Incidents Reported</div>
            </div>
            <div class="stat-box">
                <div class="number green"><?php echo number_format($agent_stats['resolved_incidents'] ?? 0); ?></div>
                <div class="label">Resolved</div>
            </div>
        </div>

        <!-- Profile Grid -->
        <div class="profile-grid">
            <!-- Left Column -->
            <div>
                <!-- Submission History -->
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <h3><i class="fas fa-file-alt"></i> Submission History</h3>
                        <a href="agent-performance.php?id=<?php echo $agent_id; ?>">View All →</a>
                    </div>
                    <?php if (count($submission_history) > 0): ?>
                        <?php foreach ($submission_history as $submission): ?>
                            <div class="history-item">
                                <div class="info">
                                    <div class="title"><?php echo htmlspecialchars($submission['pu_name'] ?? 'N/A'); ?></div>
                                    <div class="sub"><?php echo htmlspecialchars($submission['pu_code'] ?? ''); ?></div>
                                </div>
                                <div>
                                    <span class="status-badge <?php echo $submission['status'] ?? 'pending'; ?>">
                                        <?php echo ucfirst($submission['status'] ?? 'Pending'); ?>
                                    </span>
                                </div>
                                <div class="time"><?php echo date('M d, H:i', strtotime($submission['created_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:20px;color:var(--gray-400);font-size:0.85rem;">
                            No submissions yet
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Assignment History -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clipboard-list"></i> Assignment History</h3>
                    </div>
                    <?php if (count($assignment_history) > 0): ?>
                        <?php foreach ($assignment_history as $assignment): ?>
                            <div class="history-item">
                                <div class="info">
                                    <div class="title"><?php echo htmlspecialchars($assignment['pu_name'] ?? 'N/A'); ?></div>
                                    <div class="sub">
                                        <?php echo htmlspecialchars($assignment['assignment_type'] ?? ''); ?> • 
                                        Assigned by <?php echo htmlspecialchars($assignment['assigned_by_name'] ?? 'System'); ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="status-badge <?php echo $assignment['status'] ?? 'pending'; ?>">
                                        <?php echo ucfirst($assignment['status'] ?? 'Pending'); ?>
                                    </span>
                                </div>
                                <div class="time"><?php echo date('M d', strtotime($assignment['assigned_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:20px;color:var(--gray-400);font-size:0.85rem;">
                            No assignment history
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Personal Information -->
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                    </div>
                    <div class="info-grid">
                        <div class="item"><strong>Full Name</strong> <span class="value"><?php echo htmlspecialchars($agent['full_name'] ?? 'N/A'); ?></span></div>
                        <div class="item"><strong>Email</strong> <span class="value"><?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?></span></div>
                        <div class="item"><strong>Phone</strong> <span class="value"><?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?></span></div>
                        <div class="item"><strong>Gender</strong> <span class="value"><?php echo ucfirst($agent['gender'] ?? 'N/A'); ?></span></div>
                        <div class="item"><strong>DOB</strong> <span class="value"><?php echo !empty($agent['date_of_birth']) ? date('M d, Y', strtotime($agent['date_of_birth'])) : 'N/A'; ?></span></div>
                        <div class="item"><strong>NIN</strong> <span class="value"><?php echo htmlspecialchars($agent['nin'] ?? 'N/A'); ?></span></div>
                    </div>
                </div>

                <!-- Banking Information -->
                <?php if (!empty($agent['bank_name']) || !empty($agent['account_number'])): ?>
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <h3><i class="fas fa-university"></i> Banking Information</h3>
                    </div>
                    <div class="info-grid">
                        <div class="item"><strong>Bank</strong> <span class="value"><?php echo htmlspecialchars($agent['bank_name'] ?? 'N/A'); ?></span></div>
                        <div class="item"><strong>Account Number</strong> <span class="value"><?php echo htmlspecialchars($agent['account_number'] ?? 'N/A'); ?></span></div>
                        <div class="item"><strong>Account Name</strong> <span class="value"><?php echo htmlspecialchars($agent['account_name'] ?? 'N/A'); ?></span></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Emergency Contact -->
                <?php if (!empty($agent['emergency_contact_name']) || !empty($agent['emergency_contact_phone'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-phone"></i> Emergency Contact</h3>
                    </div>
                    <div class="info-grid">
                        <div class="item"><strong>Name</strong> <span class="value"><?php echo htmlspecialchars($agent['emergency_contact_name'] ?? 'N/A'); ?></span></div>
                        <div class="item"><strong>Phone</strong> <span class="value"><?php echo htmlspecialchars($agent['emergency_contact_phone'] ?? 'N/A'); ?></span></div>
                        <?php if (!empty($agent['next_of_kin_name'])): ?>
                            <div class="item"><strong>Next of Kin</strong> <span class="value"><?php echo htmlspecialchars($agent['next_of_kin_name'] ?? 'N/A'); ?></span></div>
                            <div class="item"><strong>Next of Kin Phone</strong> <span class="value"><?php echo htmlspecialchars($agent['next_of_kin_phone'] ?? 'N/A'); ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
// Confirm action (Suspend/Activate)
function confirmAction(agentId, action) {
    const confirmMessages = {
        'suspend': 'Are you sure you want to suspend this agent? They will not be able to access the system.',
        'activate': 'Are you sure you want to activate this agent? They will regain access to the system.'
    };
    
    if (confirm(confirmMessages[action] || 'Are you sure?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'manage-pu-agents.php';
        form.style.display = 'none';
        
        const agentInput = document.createElement('input');
        agentInput.type = 'hidden';
        agentInput.name = 'agent_id';
        agentInput.value = agentId;
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        
        form.appendChild(agentInput);
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }
}

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