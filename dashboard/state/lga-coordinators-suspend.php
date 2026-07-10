<?php
// ============================================================
// STATE COORDINATOR - SUSPEND LGA COORDINATOR
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

$user_id = SessionManager::get('user_id');
$state_id = SessionManager::get('state_id');

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
$coordinator_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($coordinator_id <= 0) {
    header('Location: lga-coordinators.php');
    exit();
}

// Get coordinator info
$coordinator = null;
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.status, l.name as lga_name
        FROM users u
        LEFT JOIN lgas l ON u.lga_id = l.id
        WHERE u.id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$coordinator_id]);
    $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching coordinator: " . $e->getMessage());
}

if (!$coordinator) {
    header('Location: lga-coordinators.php');
    exit();
}

if ($coordinator['status'] !== 'active') {
    header('Location: lga-coordinators-profiles.php?id=' . $coordinator_id . '&error=already_suspended');
    exit();
}

// Handle suspension
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason'] ?? 'No reason provided');
    
    try {
        $stmt = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$coordinator_id]);
        
        // Revoke all sessions
        $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?");
        $stmt->execute([$coordinator_id]);
        
        logActivity($user_id, 'coordinator_suspended', 
            "Suspended LGA Coordinator: {$coordinator['first_name']} {$coordinator['last_name']} (ID: $coordinator_id) - Reason: $reason",
            'user', $coordinator_id
        );
        
        // Send notification email
        try {
            $full_name = $coordinator['first_name'] . ' ' . $coordinator['last_name'];
            $subject = "Account Suspended - " . APP_NAME;
            $body = "
                <h2>Account Suspended</h2>
                <p>Dear $full_name,</p>
                <p>Your LGA Coordinator account has been suspended.</p>
                <p><strong>Reason:</strong> $reason</p>
                <p>Please contact your State Coordinator for more information.</p>
            ";
            // Send email if we have email address
        } catch (Exception $e) {
            error_log("Suspension email failed: " . $e->getMessage());
        }
        
        header('Location: lga-coordinators-profiles.php?id=' . $coordinator_id . '&suspended=1');
        exit();
    } catch (Exception $e) {
        $error = 'Failed to suspend coordinator: ' . $e->getMessage();
    }
}

$page_title = 'Suspend Coordinator';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.confirm-container {
    max-width: 500px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 28px 32px;
    margin-top: 16px;
    text-align: center;
}

.confirm-container .warning-icon {
    font-size: 3rem;
    color: #EF4444;
    margin-bottom: 12px;
}

.confirm-container h3 {
    color: var(--gray-800);
    margin: 0 0 4px;
}

.confirm-container .coordinator-name {
    font-weight: 600;
    color: var(--primary);
}

.confirm-container .lga-name {
    color: var(--gray-500);
    font-size: 0.85rem;
}

.confirm-container .warning-text {
    background: #FEF2F2;
    border: 1px solid #FECACA;
    border-radius: 10px;
    padding: 14px 18px;
    margin: 16px 0;
    font-size: 0.8rem;
    color: #991B1B;
}

.confirm-container .warning-text i {
    margin-right: 6px;
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
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    resize: vertical;
    min-height: 80px;
    transition: var(--transition);
}

.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.btn-group {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 16px;
}

.btn-danger {
    padding: 10px 32px;
    background: #EF4444;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-danger:hover {
    background: #DC2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-cancel {
    padding: 10px 32px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-cancel:hover {
    background: var(--gray-200);
}

@media (max-width: 768px) {
    .confirm-container {
        padding: 20px;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group a, .btn-group button {
        width: 100%;
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="confirm-container">
            <div class="warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h3>Suspend Coordinator</h3>
            <p class="coordinator-name">
                <?php echo htmlspecialchars($coordinator['first_name'] . ' ' . $coordinator['last_name']); ?>
            </p>
            <p class="lga-name">
                <i class="fas fa-map-marker-alt"></i> 
                <?php echo htmlspecialchars($coordinator['lga_name'] ?? 'N/A'); ?> LGA
            </p>

            <div class="warning-text">
                <i class="fas fa-info-circle"></i>
                Suspending this coordinator will:
                <ul style="margin:8px 0 0 20px;text-align:left;">
                    <li>Prevent them from logging in</li>
                    <li>Terminate all active sessions</li>
                    <li>Remove their access to the system</li>
                </ul>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error" style="text-align:left;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Reason for Suspension <span class="required">*</span></label>
                    <textarea name="reason" required placeholder="Please provide a reason for suspending this coordinator..."></textarea>
                </div>

                <div class="btn-group">
                    <a href="lga-coordinators-profiles.php?id=<?php echo $coordinator_id; ?>" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-pause"></i> Suspend Coordinator
                    </button>
                </div>
            </form>
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