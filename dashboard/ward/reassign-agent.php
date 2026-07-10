<?php
// ============================================================
// WARD COORDINATOR - SUSPEND AGENT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Ward Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');

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
$agent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($agent_id <= 0) {
    header('Location: manage-pu-agents.php');
    exit();
}

// Get agent details
$agent = null;
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.status, pu.name as pu_name
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.id = ? AND u.tenant_id = ? AND u.ward_id = ?
    ");
    $stmt->execute([$agent_id, $tenant_id, $ward_id]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching agent: " . $e->getMessage());
}

if (!$agent) {
    header('Location: manage-pu-agents.php');
    exit();
}

if ($agent['status'] !== 'active') {
    header('Location: manage-pu-agents.php?error=already_suspended');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason'] ?? 'No reason provided');
    
    try {
        $stmt = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$agent_id]);
        
        // Revoke all sessions
        $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?");
        $stmt->execute([$agent_id]);
        
        logActivity($user_id, 'agent_suspended', 
            "Suspended PU Agent: {$agent['first_name']} {$agent['last_name']} (ID: $agent_id) - Reason: $reason",
            'users', $agent_id
        );
        
        $message = "Agent suspended successfully!";
        
        // Redirect after success
        header('Location: manage-pu-agents.php?suspended=1');
        exit();
    } catch (Exception $e) {
        $error = 'Failed to suspend agent: ' . $e->getMessage();
    }
}

$full_name = $agent['first_name'] . ' ' . $agent['last_name'];
$page_title = 'Suspend Agent';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.confirm-container {
    max-width: 500px;
    margin: 0 auto;
}

.confirm-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    text-align: center;
}

.confirm-card .warning-icon {
    font-size: 3rem;
    color: #EF4444;
    margin-bottom: 12px;
}

.confirm-card h3 {
    color: var(--gray-800);
    margin: 0 0 4px;
}

.confirm-card .agent-name {
    font-weight: 600;
    color: var(--primary);
}

.confirm-card .pu-name {
    color: var(--gray-500);
    font-size: 0.85rem;
}

.confirm-card .warning-text {
    background: #FEF2F2;
    border: 1px solid #FECACA;
    border-radius: 8px;
    padding: 12px 16px;
    margin: 16px 0;
    font-size: 0.8rem;
    color: #991B1B;
    text-align: left;
}

.confirm-card .warning-text i {
    margin-right: 6px;
}

.confirm-card .warning-text ul {
    margin: 6px 0 0 16px;
}

.form-group {
    margin: 16px 0;
    text-align: left;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    resize: vertical;
    min-height: 80px;
    transition: var(--transition);
}

.form-group textarea:focus {
    outline: none;
    border-color: #EF4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.06);
}

.alert {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    text-align: left;
}

.alert-error {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

.alert i {
    margin-right: 6px;
}

.btn-group {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 16px;
}

.btn-danger {
    padding: 10px 28px;
    background: #EF4444;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-danger:hover {
    background: #DC2626;
}

.btn-cancel {
    padding: 10px 28px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-cancel:hover {
    background: var(--gray-200);
}

@media (max-width: 768px) {
    .confirm-card {
        padding: 16px 18px;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group button,
    .btn-group a {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="confirm-container">
            <div class="confirm-card">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                
                <h3>Suspend Agent</h3>
                <p class="agent-name">
                    <?php echo htmlspecialchars($full_name); ?>
                </p>
                <p class="pu-name">
                    <i class="fas fa-flag-checkered"></i> 
                    <?php echo htmlspecialchars($agent['pu_name'] ?? 'Unassigned'); ?>
                </p>

                <div class="warning-text">
                    <i class="fas fa-info-circle"></i>
                    <strong>Suspending this agent will:</strong>
                    <ul>
                        <li>Prevent them from logging in</li>
                        <li>Terminate all active sessions</li>
                        <li>Remove their access to the system</li>
                        <li>Free up their assigned polling unit</li>
                    </ul>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Reason for Suspension <span class="required">*</span></label>
                        <textarea name="reason" required placeholder="Please provide a reason for suspending this agent..."></textarea>
                    </div>

                    <div class="btn-group">
                        <a href="manage-pu-agents.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn-danger">
                            <i class="fas fa-pause"></i> Suspend Agent
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
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