<?php
// ============================================================
// SENATORIAL COORDINATOR - DELETE BROADCAST
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET BROADCAST ID
// ============================================================
$broadcast_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$broadcast_id) {
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// GET BROADCAST DATA
// ============================================================
$broadcast = null;
try {
    $stmt = $db->prepare("SELECT * FROM broadcasts WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$broadcast_id, $tenant_id]);
    $broadcast = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching broadcast: " . $e->getMessage());
}

if (!$broadcast) {
    header('Location: broadcasts.php');
    exit();
}

// Only drafts can be deleted
if ($broadcast['status'] !== 'draft') {
    $_SESSION['flash_error'] = 'Only drafts can be deleted.';
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// HANDLE DELETION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        $stmt = $db->prepare("DELETE FROM broadcasts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$broadcast_id, $tenant_id]);
        
        logActivity($user_id, 'broadcast_deleted', "Deleted broadcast: {$broadcast['title']} (ID: $broadcast_id)");
        
        $_SESSION['flash_success'] = 'Broadcast deleted successfully.';
        header('Location: broadcasts.php');
        exit();
    } catch (Exception $e) {
        $error = 'Error deleting broadcast: ' . $e->getMessage();
        error_log("Broadcast deletion error: " . $e->getMessage());
    }
}

$page_title = 'Delete Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.page-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.confirm-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 32px;
    text-align: center;
    max-width: 500px;
    margin: 0 auto;
}
.confirm-card .icon {
    font-size: 3rem;
    color: #DC2626;
    margin-bottom: 16px;
}
.confirm-card h3 {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 8px;
}
.confirm-card p {
    color: var(--gray-500);
    margin-bottom: 20px;
}
.confirm-card .broadcast-title {
    font-weight: 600;
    color: var(--gray-700);
    background: var(--gray-50);
    padding: 8px 16px;
    border-radius: 8px;
    display: inline-block;
    margin-bottom: 20px;
}
.confirm-card .btn-group {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}
.confirm-card .btn {
    padding: 10px 28px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.confirm-card .btn-danger {
    background: #DC2626;
    color: white;
}
.confirm-card .btn-danger:hover {
    background: #B91C1C;
}
.confirm-card .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.confirm-card .btn-secondary:hover {
    background: var(--gray-200);
}

.alert {
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    border: 1px solid transparent;
}
.alert i {
    margin-top: 2px;
    font-size: 1.1rem;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border-color: #FECACA;
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-trash" style="color:var(--danger);margin-right:8px;"></i> 
                    Delete Broadcast
                </h2>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <div class="confirm-card">
            <div class="icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3>Delete Broadcast?</h3>
            <p>This action cannot be undone. Are you sure you want to delete this broadcast?</p>
            <div class="broadcast-title">
                "<?php echo htmlspecialchars($broadcast['title']); ?>"
            </div>
            
            <form method="POST" action="">
                <div class="btn-group">
                    <button type="submit" name="confirm" value="1" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Yes, Delete
                    </button>
                    <a href="broadcasts.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// ============================================================
// SIDEBAR TOGGLE (same as previous files)
// ============================================================
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

window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>