<?php
// ============================================================
// WARD COORDINATOR - ACTIVATE AGENT
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

if ($agent['status'] === 'active') {
    header('Location: manage-pu-agents.php?error=already_active');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$agent_id]);
        
        logActivity($user_id, 'agent_activated', 
            "Activated PU Agent: {$agent['first_name']} {$agent['last_name']} (ID: $agent_id)",
            'users', $agent_id
        );
        
        $message = "Agent activated successfully!";
        
        // Redirect after success
        header('Location: manage-pu-agents.php?activated=1');
        exit();
    } catch (Exception $e) {
        $error = 'Failed to activate agent: ' . $e->getMessage();
    }
}

$full_name = $agent['first_name'] . ' ' . $agent['last_name'];
$page_title = 'Activate Agent';
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

.confirm-card .success-icon {
    font-size: 3rem;
    color: #10B981;
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

.confirm-card .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.6rem;
    padding: 3px 12px;
    border-radius: 10px;
    font-weight: 600;
    margin: 8px 0;
}

.confirm-card .status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.confirm-card .status-badge.suspended { background: #FEF2F2; color: #991B1B; }
.confirm-card .status-badge.suspended .dot { background: #EF4444; }
.confirm-card .status-badge.pending { background: #FFFBEB; color: #92400E; }
.confirm-card .status-badge.pending .dot { background: #F59E0B; }

.confirm-card .info-box {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 8px;
    padding: 12px 16px;
    margin: 16px 0;
    font-size: 0.8rem;
    color: #0369A1;
    text-align: left;
}

.confirm-card .info-box i {
    margin-right: 6px;
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

.btn-success {
    padding: 10px 28px;
    background: #10B981;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-success:hover {
    background: #059669;
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
                <div class="success-icon">
                    <i class="fas fa-play"></i>
                </div>
                
                <h3>Activate Agent</h3>
                <p class="agent-name">
                    <?php echo htmlspecialchars($full_name); ?>
                </p>
                <p class="pu-name">
                    <i class="fas fa-flag-checkered"></i> 
                    <?php echo htmlspecialchars($agent['pu_name'] ?? 'Unassigned'); ?>
                </p>
                <p>
                    Status: <span class="status-badge <?php echo $agent['status']; ?>">
                        <span class="dot"></span>
                        <?php echo ucfirst($agent['status']); ?>
                    </span>
                </p>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    Activating this agent will restore their access to the system. 
                    They will be able to login and perform their duties.
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="btn-group">
                        <a href="manage-pu-agents.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn-success">
                            <i class="fas fa-check"></i> Activate Agent
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