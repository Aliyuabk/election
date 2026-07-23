<?php
// ============================================================
// WARD COORDINATOR - SEND BROADCAST
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
$success_message = '';
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT * FROM broadcasts 
        WHERE id = ? AND tenant_id = ? AND sender_id = ?
    ");
    $stmt->execute([$broadcast_id, $tenant_id, $user_id]);
    $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$broadcast) {
        header('Location: broadcasts.php?error=notfound');
        exit();
    }
    
    if ($broadcast['status'] === 'sent') {
        header('Location: broadcasts.php?error=already_sent');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error fetching broadcast: " . $e->getMessage());
    header('Location: broadcasts.php?error=db');
    exit();
}

// ============================================================
// HANDLE SEND
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    try {
        // Get target_ids from broadcast
        $target_ids = json_decode($broadcast['target_ids_json'] ?? '[]', true);
        
        // Get recipients using existing function
        $recipients = getBroadcastRecipients($tenant_id, $broadcast['target_audience'], $target_ids);
        
        // Prepare email recipients
        $email_recipients = [];
        foreach ($recipients as $recipient) {
            if (!empty($recipient['email'])) {
                $email_recipients[] = [
                    'email' => $recipient['email'],
                    'full_name' => $recipient['full_name'] ?? 'User'
                ];
            }
        }
        
        // Get send channels
        $send_via = json_decode($broadcast['send_via'] ?? '["email"]', true);
        
        // Send via email
        if (in_array('email', $send_via) && !empty($email_recipients)) {
            $email_result = sendBroadcastEmails($email_recipients, $broadcast['title'], $broadcast['message']);
        }
        
        // Send via In-App
        if (in_array('in_app', $send_via)) {
            $sent = 0;
            foreach ($recipients as $recipient) {
                // Get user_id from recipient
                $user_id_recipient = isset($recipient['id']) ? $recipient['id'] : 0;
                
                // If no id in recipient array, try to get from database
                if (!$user_id_recipient && !empty($recipient['email'])) {
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ?");
                    $stmt->execute([$recipient['email'], $tenant_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        $user_id_recipient = $user['id'];
                    }
                }
                
                if ($user_id_recipient > 0) {
                    $stmt = $db->prepare("
                        INSERT INTO notifications (user_id, type, title, message, data_json, created_at) 
                        VALUES (?, 'broadcast', ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $user_id_recipient,
                        $broadcast['title'],
                        $broadcast['message'],
                        json_encode(['broadcast_id' => $broadcast_id])
                    ]);
                    $sent++;
                }
            }
        }
        
        // Update broadcast status
        $stmt = $db->prepare("
            UPDATE broadcasts 
            SET status = 'sent', sent_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$broadcast_id]);
        
        // Log activity
        logActivity($user_id, 'broadcast_sent', "Sent broadcast: {$broadcast['title']} (ID: $broadcast_id)", 'broadcasts', $broadcast_id);
        
        $success_message = "Broadcast sent successfully to " . count($recipients) . " recipients!";
        
        // Redirect
        header('Location: broadcasts.php?success=' . urlencode($success_message));
        exit();
        
    } catch (Exception $e) {
        $error_message = "Error sending broadcast: " . $e->getMessage();
        error_log("Broadcast send error: " . $e->getMessage());
    }
}

$page_title = 'Send Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<!-- HTML content remains the same as before -->
<!-- [All HTML and CSS from the previous broadcasts-send.php file] -->

<style>
.send-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.send-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.send-header h2 i {
    color: var(--primary);
}

.broadcast-preview {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
}
.broadcast-preview .preview-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 8px;
}
.broadcast-preview .preview-meta {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-bottom: 12px;
}
.broadcast-preview .preview-message {
    font-size: 0.95rem;
    color: var(--gray-700);
    padding: 12px 16px;
    background: var(--gray-50);
    border-radius: 6px;
    white-space: pre-wrap;
}
.broadcast-preview .preview-recipients {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--gray-200);
    font-size: 0.85rem;
    color: var(--gray-600);
}

.confirm-box {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    border-radius: var(--radius);
    padding: 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.confirm-box i {
    color: #EF4444;
    font-size: 1.2rem;
    margin-top: 2px;
}
.confirm-box .text {
    font-size: 0.9rem;
    color: #991B1B;
}
.confirm-box .text strong {
    display: block;
    margin-bottom: 4px;
}

.form-actions {
    display: flex;
    gap: 12px;
}
.form-actions .btn-primary {
    background: #EF4444;
    border-color: #EF4444;
}
.form-actions .btn-primary:hover {
    background: #DC2626;
    border-color: #DC2626;
}

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
        <div class="send-header">
            <div>
                <h2><i class="fas fa-paper-plane"></i> Send Broadcast</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    Review and send your broadcast message
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
            <!-- Broadcast Preview -->
            <div class="broadcast-preview">
                <div class="preview-title">
                    <i class="fas fa-bullhorn" style="color:var(--primary);"></i>
                    <?php echo htmlspecialchars($broadcast['title']); ?>
                </div>
                <div class="preview-meta">
                    <span><i class="fas fa-tag"></i> Status: <?php echo ucfirst($broadcast['status']); ?></span>
                    <span style="margin-left:12px;"><i class="fas fa-clock"></i> Created: <?php echo date('M d, Y H:i', strtotime($broadcast['created_at'])); ?></span>
                </div>
                <div class="preview-message">
                    <?php echo nl2br(htmlspecialchars($broadcast['message'])); ?>
                </div>
                <div class="preview-recipients">
                    <i class="fas fa-users"></i> 
                    Target Audience: <?php echo ucfirst(str_replace('_', ' ', $broadcast['target_audience'])); ?>
                    <?php if ($broadcast['total_recipients'] > 0): ?>
                        • <?php echo number_format($broadcast['total_recipients']); ?> recipients
                    <?php endif; ?>
                </div>
            </div>

            <!-- Send Confirmation -->
            <div class="confirm-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="text">
                    <strong>⚠️ Confirm Send</strong>
                    Are you sure you want to send this broadcast to all selected recipients?
                    This action cannot be undone.
                </div>
            </div>

            <!-- Send Form -->
            <form method="POST" action="">
                <input type="hidden" name="action" value="send">
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary" onclick="return confirm('Are you sure you want to send this broadcast? This action cannot be undone.')">
                        <i class="fas fa-paper-plane"></i> Send Broadcast
                    </button>
                    <a href="broadcasts-edit.php?id=<?php echo $broadcast_id; ?>" class="btn-secondary">
                        <i class="fas fa-edit"></i> Edit Before Sending
                    </a>
                    <a href="broadcasts.php" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>

        <?php else: ?>
            <div style="text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                <i class="fas fa-bullhorn" style="font-size:4rem;color:var(--gray-300);"></i>
                <h4 style="margin:16px 0 8px;">Broadcast Not Found</h4>
                <p style="color:var(--gray-500);">The broadcast you're trying to send does not exist.</p>
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