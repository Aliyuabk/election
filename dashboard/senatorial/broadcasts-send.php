<?php
// ============================================================
// SENATORIAL COORDINATOR - SEND BROADCAST
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
    $stmt = $db->prepare("
        SELECT b.*, u.full_name as sender_name
        FROM broadcasts b
        LEFT JOIN users u ON b.sender_id = u.id
        WHERE b.id = ? AND b.tenant_id = ?
    ");
    $stmt->execute([$broadcast_id, $tenant_id]);
    $broadcast = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching broadcast: " . $e->getMessage());
}

if (!$broadcast) {
    header('Location: broadcasts.php');
    exit();
}

// Only drafts can be sent
if ($broadcast['status'] !== 'draft') {
    $_SESSION['flash_error'] = 'This broadcast has already been sent or scheduled.';
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// GET RECIPIENTS
// ============================================================
$recipients = [];
$recipient_count = 0;

try {
    $target_audience = $broadcast['target_audience'];
    $target_ids = $broadcast['target_ids_json'] ? json_decode($broadcast['target_ids_json'], true) : [];
    $target_role_id = $broadcast['target_role_id'];
    
    if ($target_audience === 'all') {
        $stmt = $db->prepare("
            SELECT id, email, full_name, phone
            FROM users 
            WHERE tenant_id = ? AND status = 'active'
            AND role_id IN (SELECT id FROM roles WHERE level IN ('federal_constituency', 'lga', 'ward', 'pu_agent', 'party_agent', 'volunteer', 'observer'))
            AND email IS NOT NULL AND email != ''
        ");
        $stmt->execute([$tenant_id]);
        $recipients = $stmt->fetchAll();
    } elseif ($target_audience === 'lga' && !empty($target_ids)) {
        $placeholders = implode(',', array_fill(0, count($target_ids), '?'));
        $stmt = $db->prepare("
            SELECT id, email, full_name, phone
            FROM users 
            WHERE tenant_id = ? AND status = 'active' AND lga_id IN ($placeholders)
            AND email IS NOT NULL AND email != ''
        ");
        $params = array_merge([$tenant_id], $target_ids);
        $stmt->execute($params);
        $recipients = $stmt->fetchAll();
    } elseif ($target_audience === 'role_specific' && $target_role_id) {
        $stmt = $db->prepare("
            SELECT id, email, full_name, phone
            FROM users 
            WHERE tenant_id = ? AND status = 'active' AND role_id = ?
            AND email IS NOT NULL AND email != ''
        ");
        $stmt->execute([$tenant_id, $target_role_id]);
        $recipients = $stmt->fetchAll();
    }
    
    $recipient_count = count($recipients);
} catch (Exception $e) {
    error_log("Error fetching recipients: " . $e->getMessage());
}

// ============================================================
// HANDLE SEND
// ============================================================
$error = '';
$success = '';
$sent_count = 0;
$failed_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    if ($recipient_count === 0) {
        $error = 'No recipients found for this broadcast.';
    } else {
        try {
            // Update broadcast status to sending
            $stmt = $db->prepare("UPDATE broadcasts SET status = 'sending', sent_at = NOW() WHERE id = ?");
            $stmt->execute([$broadcast_id]);
            
            // Send emails
            $send_via = json_decode($broadcast['send_via'], true) ?: ['email'];
            $subject = $broadcast['title'];
            $message = $broadcast['message'];
            
            foreach ($recipients as $recipient) {
                try {
                    if (in_array('email', $send_via) && !empty($recipient['email'])) {
                        $email_body = "
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <style>
                                    body { font-family: Arial, sans-serif; background: #f4f6fa; padding: 20px; }
                                    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
                                    .header { text-align: center; margin-bottom: 30px; }
                                    .header h1 { color: #0F4C81; margin: 0; }
                                    .message-box { background: #F8FAFC; padding: 20px; border-radius: 12px; margin: 20px 0; border-left: 4px solid #0F4C81; }
                                    .footer { text-align: center; color: #64748B; font-size: 12px; margin-top: 30px; border-top: 1px solid #E2E8F0; padding-top: 20px; }
                                </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <div class='header'>
                                        <h1>📢 " . APP_NAME . "</h1>
                                        <p style='color: #64748B;'>Broadcast Message</p>
                                    </div>
                                    <p>Hello " . htmlspecialchars($recipient['full_name']) . ",</p>
                                    <div class='message-box'>
                                        <p>" . nl2br(htmlspecialchars($message)) . "</p>
                                    </div>
                                    <p style='color: #64748B; font-size: 14px;'>
                                        This is an automated message from " . APP_NAME . ".
                                        Please do not reply to this email.
                                    </p>
                                    <div class='footer'>
                                        &copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.
                                    </div>
                                </div>
                            </body>
                            </html>
                        ";
                        
                        sendEmail($recipient['email'], $subject, $email_body, strip_tags($message));
                        $sent_count++;
                    } else {
                        $sent_count++;
                    }
                } catch (Exception $e) {
                    $failed_count++;
                    error_log("Failed to send to {$recipient['email']}: " . $e->getMessage());
                }
            }
            
            // Update broadcast status
            $status = $failed_count > 0 && $sent_count > 0 ? 'sent' : ($failed_count > 0 ? 'failed' : 'sent');
            $stmt = $db->prepare("
                UPDATE broadcasts SET 
                    status = ?, 
                    total_recipients = ?,
                    sent_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $sent_count, $broadcast_id]);
            
            logActivity($user_id, 'broadcast_sent', "Sent broadcast: {$broadcast['title']} to $sent_count recipients (ID: $broadcast_id)");
            
            if ($sent_count > 0) {
                $success = "Broadcast sent successfully to $sent_count recipients!";
                if ($failed_count > 0) {
                    $success .= " ($failed_count failed)";
                }
            } else {
                $error = "Failed to send broadcast to all recipients.";
            }
            
            // Redirect after success
            if (!empty($success)) {
                $_SESSION['flash_success'] = $success;
                header('Location: broadcasts.php');
                exit();
            }
            
        } catch (Exception $e) {
            $error = 'Error sending broadcast: ' . $e->getMessage();
            error_log("Broadcast send error: " . $e->getMessage());
        }
    }
}

$page_title = 'Send Broadcast';
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
    max-width: 600px;
    margin: 0 auto;
}
.confirm-card .icon {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 16px;
    text-align: center;
}
.confirm-card h3 {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 8px;
    text-align: center;
}
.confirm-card .broadcast-preview {
    background: var(--gray-50);
    border-radius: 10px;
    padding: 16px 20px;
    margin: 16px 0;
}
.confirm-card .broadcast-preview .label {
    font-size: 0.7rem;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.confirm-card .broadcast-preview .title {
    font-weight: 600;
    font-size: 1rem;
}
.confirm-card .broadcast-preview .message {
    color: var(--gray-600);
    font-size: 0.85rem;
    margin-top: 4px;
}
.confirm-card .recipient-info {
    background: #F0F9FF;
    border-radius: 10px;
    padding: 12px 16px;
    margin: 16px 0;
    border: 1px solid #BAE6FD;
}
.confirm-card .recipient-info .count {
    font-size: 1.2rem;
    font-weight: 700;
    color: #0369A1;
}
.confirm-card .btn-group {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 16px;
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
.confirm-card .btn-success {
    background: #10B981;
    color: white;
}
.confirm-card .btn-success:hover {
    background: #059669;
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
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border-color: #A7F3D0;
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
                    <i class="fas fa-paper-plane" style="color:var(--primary);margin-right:8px;"></i> 
                    Send Broadcast
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="broadcasts.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back to Broadcasts
                </a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <div class="confirm-card">
            <div class="icon">
                <i class="fas fa-bullhorn"></i>
            </div>
            <h3>Send Broadcast</h3>
            <p style="text-align:center;color:var(--gray-500);margin-bottom:16px;">
                Review the broadcast details below before sending.
            </p>

            <div class="broadcast-preview">
                <div class="label">Title</div>
                <div class="title"><?php echo htmlspecialchars($broadcast['title']); ?></div>
            </div>
            
            <div class="broadcast-preview">
                <div class="label">Message</div>
                <div class="message"><?php echo nl2br(htmlspecialchars($broadcast['message'])); ?></div>
            </div>

            <div class="recipient-info">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                    <span><i class="fas fa-users"></i> Recipients</span>
                    <span class="count"><?php echo number_format($recipient_count); ?></span>
                </div>
                <?php if ($recipient_count > 0): ?>
                    <div style="font-size:0.7rem;color:var(--gray-400);margin-top:4px;">
                        <?php 
                        $send_via = json_decode($broadcast['send_via'], true) ?: ['email'];
                        echo 'Sending via: ' . implode(', ', array_map('ucfirst', $send_via));
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($recipient_count === 0): ?>
                <div style="background:#FEF2F2;border-radius:10px;padding:12px 16px;border:1px solid #FECACA;color:#DC2626;text-align:center;">
                    <i class="fas fa-exclamation-circle"></i> No recipients found for this broadcast.
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="btn-group">
                    <button type="submit" name="confirm" value="1" class="btn btn-success" <?php echo $recipient_count === 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-paper-plane"></i> Send Now
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