<?php
// ============================================================
// WARD COORDINATOR - DELETE BROADCAST
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
    padding: 24px 28px;
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
    border-radius: 8px;
    padding: 12px 16px;
    margin: 16px 0;
    font-size: 0.8rem;
    color: #991B1B;
}

.delete-card .warning-text i {
    margin-right: 6px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 8px;
    font-weight: 600;
}

.status-badge .dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.draft { background: #F3F4F6; color: #6B7280; }
.status-badge.draft .dot { background: #9CA3AF; }
.status-badge.scheduled { background: #FFFBEB; color: #92400E; }
.status-badge.scheduled .dot { background: #F59E0B; }
.status-badge.failed { background: #FEF2F2; color: #991B1B; }
.status-badge.failed .dot { background: #EF4444; }

.alert {
    padding: 10px 14px;
    border-radius: 8px;
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
    gap: 8px;
    justify-content: center;
    margin-top: 16px;
}

.btn-danger {
    padding: 10px 24px;
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
    padding: 10px 24px;
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
    .delete-card {
        padding: 16px 18px;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group button,
    .btn-group .btn-cancel {
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
                <div>
                    <span class="status-badge <?php echo $broadcast['status']; ?>">
                        <span class="dot"></span>
                        <?php echo ucfirst($broadcast['status']); ?>
                    </span>
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