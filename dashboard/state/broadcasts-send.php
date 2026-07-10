<?php
// ============================================================
// STATE COORDINATOR - SEND BROADCAST
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
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send') {
        try {
            // Update status to sending
            $stmt = $db->prepare("UPDATE broadcasts SET status = 'sending' WHERE id = ?");
            $stmt->execute([$broadcast_id]);
            
            // Get recipients based on target
            $target_ids = json_decode($broadcast['target_ids_json'] ?? '[]', true) ?: [];
            $recipients = getBroadcastRecipients($tenant_id, $broadcast['target_audience'], $target_ids);
            
            if (empty($recipients)) {
                throw new Exception('No recipients found for this broadcast.');
            }
            
            // Send the broadcast
            $result = sendBroadcastEmails(
                $recipients,
                $broadcast['title'],
                $broadcast['message']
            );
            
            // Update broadcast status
            $status = $result['success'] ? 'sent' : 'failed';
            $stmt = $db->prepare("
                UPDATE broadcasts 
                SET status = ?, sent_at = NOW(), total_recipients = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, count($recipients), $broadcast_id]);
            
            logActivity($user_id, 'broadcast_sent', 
                "Sent broadcast: {$broadcast['title']} to " . count($recipients) . " recipients",
                'broadcasts', $broadcast_id
            );
            
            if ($result['success']) {
                $message = "Broadcast sent successfully! ({$result['sent']} recipients)";
            } else {
                $message = "Broadcast sent with some errors. Check logs for details.";
                $error = implode(', ', array_slice($result['errors'], 0, 3));
            }
        } catch (Exception $e) {
            $error = 'Failed to send broadcast: ' . $e->getMessage();
            
            // Update status to failed
            try {
                $stmt = $db->prepare("UPDATE broadcasts SET status = 'failed' WHERE id = ?");
                $stmt->execute([$broadcast_id]);
            } catch (Exception $e2) {
                // Ignore
            }
        }
    }
}

$page_title = 'Send Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.send-container {
    max-width: 600px;
    margin: 0 auto;
}

.send-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
}

.broadcast-preview {
    background: var(--gray-50);
    border-radius: 10px;
    padding: 16px 18px;
    margin-bottom: 16px;
}

.broadcast-preview .title {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--gray-800);
}

.broadcast-preview .message {
    font-size: 0.85rem;
    color: var(--gray-600);
    margin: 8px 0;
    white-space: pre-wrap;
}

.broadcast-preview .meta {
    display: flex;
    gap: 16px;
    font-size: 0.7rem;
    color: var(--gray-500);
    flex-wrap: wrap;
}

.broadcast-preview .meta .label {
    font-weight: 500;
    color: var(--gray-700);
}

.recipient-info {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 10px;
    padding: 12px 16px;
    margin: 12px 0;
    font-size: 0.8rem;
    color: #0369A1;
}

.recipient-info i {
    margin-right: 6px;
}

.recipient-info .count {
    font-weight: 700;
    font-size: 1.1rem;
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

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.6rem;
    padding: 3px 12px;
    border-radius: 12px;
    font-weight: 600;
}

.status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.draft { background: #F3F4F6; color: #6B7280; }
.status-badge.draft .dot { background: #9CA3AF; }
.status-badge.scheduled { background: #FFFBEB; color: #92400E; }
.status-badge.scheduled .dot { background: #F59E0B; }
.status-badge.failed { background: #FEF2F2; color: #991B1B; }
.status-badge.failed .dot { background: #EF4444; }

.btn-group {
    display: flex;
    gap: 10px;
    margin-top: 16px;
}

.btn-send {
    padding: 10px 32px;
    background: #3B82F6;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-send:hover {
    background: #2563EB;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
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
    .send-card {
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
        <div class="send-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-paper-plane"></i> Send Broadcast</h1>
                    <p class="subtitle">
                        <i class="fas fa-bullhorn"></i> 
                        <?php echo htmlspecialchars($broadcast['title']); ?>
                        <span class="status-badge <?php echo $broadcast['status']; ?>" style="margin-left:8px;">
                            <span class="dot"></span>
                            <?php echo ucfirst($broadcast['status']); ?>
                        </span>
                    </p>
                </div>
                <div class="actions">
                    <a href="broadcasts.php" class="btn-secondary-sm">
                        <i class="fas fa-arrow-left"></i> Back to Broadcasts
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$message || strpos($message, 'errors') !== false): ?>
                <div class="send-card">
                    <div class="broadcast-preview">
                        <div class="title"><?php echo htmlspecialchars($broadcast['title']); ?></div>
                        <div class="message"><?php echo htmlspecialchars($broadcast['message']); ?></div>
                        <div class="meta">
                            <span><span class="label">Target:</span> <?php echo ucfirst($broadcast['target_audience']); ?></span>
                            <span><span class="label">Channels:</span> <?php echo implode(', ', json_decode($broadcast['send_via'], true) ?: ['Email']); ?></span>
                            <?php if ($broadcast['scheduled_at']): ?>
                                <span><span class="label">Scheduled:</span> <?php echo date('M j, Y g:i A', strtotime($broadcast['scheduled_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php 
                        $target_ids = json_decode($broadcast['target_ids_json'] ?? '[]', true) ?: [];
                        $recipients = getBroadcastRecipients($tenant_id, $broadcast['target_audience'], $target_ids);
                        $recipient_count = count($recipients);
                    ?>
                    <div class="recipient-info">
                        <i class="fas fa-users"></i>
                        This broadcast will be sent to 
                        <span class="count"><?php echo number_format($recipient_count); ?></span> 
                        recipient<?php echo $recipient_count !== 1 ? 's' : ''; ?>.
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="action" value="send" />
                        <div class="btn-group">
                            <a href="broadcasts.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn-send" <?php echo $recipient_count === 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-paper-plane"></i> 
                                <?php echo $broadcast['status'] === 'failed' ? 'Retry Send' : 'Send Now'; ?>
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