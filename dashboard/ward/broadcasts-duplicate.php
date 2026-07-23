<?php
// ============================================================
// WARD COORDINATOR - DUPLICATE BROADCAST
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
// GET BROADCAST ID
// ============================================================
$broadcast_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($broadcast_id <= 0) {
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// FETCH BROADCAST DETAILS
// ============================================================
$broadcast = null;
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT * FROM broadcasts 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$broadcast_id, $tenant_id]);
    $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$broadcast) {
        header('Location: broadcasts.php?error=notfound');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error fetching broadcast: " . $e->getMessage());
    header('Location: broadcasts.php?error=db');
    exit();
}

// ============================================================
// HANDLE DUPLICATION
// ============================================================
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'duplicate') {
    $new_title = isset($_POST['title']) ? trim($_POST['title']) : '';
    
    if (empty($new_title)) {
        $error_message = "Please enter a title for the duplicated broadcast.";
    } else {
        try {
            // Insert duplicate broadcast
            $stmt = $db->prepare("
                INSERT INTO broadcasts (
                    tenant_id, sender_id, title, message, target_audience, 
                    target_ids_json, send_via, status, total_recipients, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?, NOW())
            ");
            
            $stmt->execute([
                $tenant_id,
                $user_id,
                $new_title,
                $broadcast['message'],
                $broadcast['target_audience'],
                $broadcast['target_ids_json'],
                $broadcast['send_via'],
                $broadcast['total_recipients']
            ]);
            
            $new_broadcast_id = $db->lastInsertId();
            
            // Log activity
            logActivity($user_id, 'broadcast_duplicated', "Duplicated broadcast: {$broadcast['title']} (New ID: $new_broadcast_id)", 'broadcasts', $new_broadcast_id);
            
            $success_message = "Broadcast duplicated successfully!";
            header('Location: broadcasts-edit.php?id=' . $new_broadcast_id . '&success=' . urlencode($success_message));
            exit();
            
        } catch (Exception $e) {
            $error_message = "Error duplicating broadcast: " . $e->getMessage();
            error_log("Broadcast duplicate error: " . $e->getMessage());
        }
    }
}

$page_title = 'Duplicate Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.duplicate-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.duplicate-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.duplicate-header h2 i {
    color: var(--primary);
}

.broadcast-preview {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 20px;
}
.broadcast-preview .preview-title {
    font-weight: 600;
    font-size: 0.95rem;
}
.broadcast-preview .preview-meta {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin: 4px 0 8px;
}
.broadcast-preview .preview-message {
    font-size: 0.85rem;
    color: var(--gray-600);
    padding: 8px 12px;
    background: var(--gray-50);
    border-radius: 6px;
    max-height: 150px;
    overflow-y: auto;
    white-space: pre-wrap;
}

.duplicate-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.duplicate-form .form-group {
    margin-bottom: 16px;
}
.duplicate-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.duplicate-form .form-group label .required {
    color: #EF4444;
}
.duplicate-form .form-group input[type="text"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    border: 1px solid #D1FAE5;
    color: #065F46;
}
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 1.1rem;
}

.badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.badge.sent { background: #D1FAE5; color: #065F46; }

@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
    }
    .form-actions button,
    .form-actions a {
        width: 100%;
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="duplicate-header">
            <div>
                <h2><i class="fas fa-copy"></i> Duplicate Broadcast</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    Create a copy of an existing broadcast
                </p>
            </div>
            <div>
                <a href="broadcasts.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Broadcasts
                </a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($broadcast): ?>
            <!-- Original Broadcast Preview -->
            <div class="broadcast-preview">
                <div class="preview-title">
                    <i class="fas fa-bullhorn" style="color:var(--primary);"></i>
                    Original: <?php echo htmlspecialchars($broadcast['title']); ?>
                    <span class="badge sent" style="margin-left:12px;"><?php echo ucfirst($broadcast['status']); ?></span>
                </div>
                <div class="preview-meta">
                    <span><i class="fas fa-clock"></i> Created: <?php echo date('M d, Y H:i', strtotime($broadcast['created_at'])); ?></span>
                    <span style="margin-left:12px;"><i class="fas fa-users"></i> <?php echo number_format($broadcast['total_recipients'] ?? 0); ?> recipients</span>
                </div>
                <div class="preview-message">
                    <?php echo nl2br(htmlspecialchars(substr($broadcast['message'], 0, 300))); ?>
                    <?php if (strlen($broadcast['message']) > 300): ?>...<?php endif; ?>
                </div>
            </div>

            <!-- Duplicate Form -->
            <div class="duplicate-form">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="duplicate">
                    
                    <div class="form-group">
                        <label>New Broadcast Title <span class="required">*</span></label>
                        <input type="text" name="title" id="title" 
                               value="<?php echo htmlspecialchars($broadcast['title'] . ' (Copy)'); ?>" 
                               required autofocus>
                        <div style="font-size:0.7rem;color:var(--gray-400);margin-top:4px;">
                            <i class="fas fa-info-circle"></i> Give your duplicated broadcast a new title
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-copy"></i> Duplicate Broadcast
                        </button>
                        <a href="broadcasts.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <div style="text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                <i class="fas fa-bullhorn" style="font-size:4rem;color:var(--gray-300);"></i>
                <h4 style="margin:16px 0 8px;">Broadcast Not Found</h4>
                <p style="color:var(--gray-500);">The broadcast you're trying to duplicate does not exist.</p>
                <a href="broadcasts.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-arrow-left"></i> Back to Broadcasts
                </a>
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