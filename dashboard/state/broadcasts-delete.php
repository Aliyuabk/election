<?php
// ============================================================
// STATE COORDINATOR - DELETE BROADCAST
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

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
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
$broadcast_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($broadcast_id <= 0) {
    header('Location: broadcasts.php');
    exit();
}

// Get broadcast details
$broadcast = null;
try {
    $stmt = $db->prepare("
        SELECT * FROM broadcasts 
        WHERE id = ? AND tenant_id = ? AND status IN ('draft', 'scheduled', 'failed')
    ");
    $stmt->execute([$broadcast_id, $tenant_id]);
    $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching broadcast: " . $e->getMessage());
}

if (!$broadcast) {
    header('Location: broadcasts.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';
    
    if ($confirm !== 'yes') {
        $error = 'Please confirm deletion.';
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM broadcasts WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$broadcast_id, $tenant_id]);
            
            logActivity($user_id, 'broadcast_deleted', 
                "Deleted broadcast: {$broadcast['title']} (ID: $broadcast_id)",
                'broadcasts', $broadcast_id
            );
            
            $message = "Broadcast deleted successfully!";
        } catch (Exception $e) {
            $error = 'Failed to delete broadcast: ' . $e->getMessage();
        }
    }
}

$page_title = 'Delete Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.delete-container {
    max-width: 500px;
    margin: 0 auto;
}

.delete-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 28px 32px;
    text-align: center;
}

.delete-card .warning-icon {
    font-size: 3rem;
    color: #EF4444;
    margin-bottom: 12px;
}

.delete-card h3 {
    color: var(--gray-800);
    margin: 0 0 4px;
}

.delete-card .broadcast-title {
    font-weight: 600;
    color: var(--primary);
}

.delete-card .warning-text {
    background: #FEF2F2;
    border: 1px solid #FECACA;
    border-radius: 10px;
    padding: 14px 18px;
    margin: 16px 0;
    font-size: 0.8rem;
    color: #991B1B;
}

.delete-card .warning-text i {
    margin-right: 6px;
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
}

.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
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
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-cancel:hover {
    background: var(--gray-200);
}

@media (max-width: 768px) {
    .delete-card {
        padding: 20px;
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
        <div class="delete-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-trash" style="color:#EF4444;"></i> Delete Broadcast</h1>
                    <p class="subtitle">
                        <i class="fas fa-bullhorn"></i> 
                        <?php echo htmlspecialchars($broadcast['title']); ?>
                    </p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
                <div style="margin-top:12px;text-align:center;">
                    <a href="broadcasts.php" class="btn-cancel">
                        <i class="fas fa-arrow-left"></i> Back to Broadcasts
                    </a>
                </div>
            <?php else: ?>
                <div class="delete-card">
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    
                    <h3>Delete Broadcast</h3>
                    <p style="color:var(--gray-500);font-size:0.9rem;margin:4px 0;">
                        Are you sure you want to delete
                        <span class="broadcast-title">"<?php echo htmlspecialchars($broadcast['title']); ?>"</span>?
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-error" style="text-align:left;">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <div class="warning-text">
                        <i class="fas fa-info-circle"></i>
                        <strong>This action cannot be undone.</strong> The broadcast will be permanently removed.
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="confirm" value="yes" />
                        <div class="btn-group">
                            <a href="broadcasts.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn-danger">
                                <i class="fas fa-trash"></i> Delete Broadcast
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
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