<?php
// ============================================================
// STATE COORDINATOR - BROADCAST TO LGA COORDINATORS
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

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get LGA Coordinators
$lga_coordinators = [];
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, l.name as lga_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        JOIN lgas l ON u.lga_id = l.id
        WHERE r.level = 'lga' AND u.state_id = ? AND u.status = 'active' AND u.deleted_at IS NULL
        ORDER BY l.name ASC, u.first_name ASC
    ");
    $stmt->execute([$state_id]);
    $lga_coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching LGA coordinators: " . $e->getMessage());
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message_content = trim($_POST['message'] ?? '');
    $selected_coordinators = isset($_POST['coordinators']) ? array_map('intval', $_POST['coordinators']) : [];
    
    if (empty($title)) {
        $error = 'Please enter a broadcast title.';
    } elseif (empty($message_content)) {
        $error = 'Please enter a message.';
    } elseif (empty($selected_coordinators)) {
        $error = 'Please select at least one LGA coordinator to send to.';
    } else {
        try {
            // Get selected coordinators details
            $recipients = [];
            foreach ($lga_coordinators as $coordinator) {
                if (in_array($coordinator['id'], $selected_coordinators)) {
                    $recipients[] = [
                        'email' => $coordinator['email'],
                        'full_name' => $coordinator['first_name'] . ' ' . $coordinator['last_name']
                    ];
                }
            }
            
            // Save broadcast
            $send_via_json = json_encode(['email']);
            $target_ids_json = json_encode($selected_coordinators);
            
            $stmt = $db->prepare("
                INSERT INTO broadcasts (
                    tenant_id, sender_id, title, message, 
                    target_audience, target_ids_json, send_via,
                    status, created_at
                ) VALUES (?, ?, ?, ?, 'role_specific', ?, ?, 'sent', NOW())
            ");
            $stmt->execute([$tenant_id, $user_id, $title, $message_content, $target_ids_json, $send_via_json]);
            $broadcast_id = $db->lastInsertId();
            
            // Send emails
            $result = sendBroadcastEmails($recipients, $title, $message_content);
            
            // Update broadcast status
            $status = $result['success'] ? 'sent' : 'failed';
            $stmt = $db->prepare("
                UPDATE broadcasts 
                SET status = ?, sent_at = NOW(), total_recipients = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, count($recipients), $broadcast_id]);
            
            logActivity($user_id, 'broadcast_lga_coordinators', 
                "Sent broadcast to LGA Coordinators: $title (" . count($recipients) . " recipients)",
                'broadcasts', $broadcast_id
            );
            
            if ($result['success']) {
                $message = "Broadcast sent to LGA Coordinators successfully! ({$result['sent']} recipients)";
            } else {
                $message = "Broadcast sent with some errors. Check logs for details.";
                $error = implode(', ', array_slice($result['errors'], 0, 3));
            }
        } catch (Exception $e) {
            $error = 'Failed to send broadcast: ' . $e->getMessage();
        }
    }
}

$page_title = 'Broadcast to LGA Coordinators';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.broadcast-container {
    max-width: 700px;
    margin: 0 auto;
}

.form-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.form-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.form-card .card-title i {
    color: var(--primary);
    margin-right: 6px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group label .required {
    color: #EF4444;
    margin-left: 2px;
}

.form-group input[type="text"],
.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
}

.form-group input[type="text"]:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.form-group textarea {
    resize: vertical;
    min-height: 120px;
}

.form-group .char-count {
    font-size: 0.65rem;
    color: var(--gray-400);
    text-align: right;
    margin-top: 2px;
}

.recipient-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 8px 12px;
}

.recipient-list .recipient-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 8px;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.82rem;
}

.recipient-list .recipient-item:last-child {
    border-bottom: none;
}

.recipient-list .recipient-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
    flex-shrink: 0;
}

.recipient-list .recipient-item .name {
    font-weight: 500;
    color: var(--gray-700);
}

.recipient-list .recipient-item .lga {
    font-size: 0.7rem;
    color: var(--gray-500);
}

.recipient-list .recipient-item .email {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-left: auto;
}

.select-all {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-200);
    margin-bottom: 8px;
    font-weight: 500;
    font-size: 0.8rem;
}

.select-all input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
}

.recipient-count {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 0.75rem;
    color: #0369A1;
    margin-top: 8px;
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
    flex-wrap: wrap;
}

.btn-group button {
    padding: 10px 28px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-group .btn-send {
    background: #3B82F6;
    color: white;
}

.btn-group .btn-send:hover {
    background: #2563EB;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-group .btn-send:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.btn-group .btn-cancel {
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    padding: 10px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-group .btn-cancel:hover {
    background: var(--gray-200);
}

@media (max-width: 768px) {
    .form-card {
        padding: 16px 18px;
    }
    .recipient-list .recipient-item {
        flex-wrap: wrap;
    }
    .recipient-list .recipient-item .email {
        margin-left: 26px;
        width: 100%;
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
        <div class="broadcast-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-user-tie"></i> Broadcast to LGA Coordinators</h1>
                    <p class="subtitle">
                        <i class="fas fa-flag"></i> 
                        <?php echo htmlspecialchars($state_name); ?> State - Send messages to all LGA Coordinators
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

            <?php if (empty($lga_coordinators)): ?>
                <div class="alert alert-error" style="text-align:center;padding:30px;">
                    <i class="fas fa-user-tie" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                    <h4>No LGA Coordinators Found</h4>
                    <p style="font-size:0.85rem;color:var(--gray-500);">No LGA coordinators have been assigned in <?php echo htmlspecialchars($state_name); ?>.</p>
                </div>
            <?php else: ?>
                <form method="POST" action="" id="broadcastForm">
                    <!-- Broadcast Details -->
                    <div class="form-card">
                        <div class="card-title"><i class="fas fa-info-circle"></i> Broadcast Details</div>
                        
                        <div class="form-group">
                            <label>Title <span class="required">*</span></label>
                            <input type="text" name="title" required placeholder="Enter broadcast title..." value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" />
                        </div>

                        <div class="form-group">
                            <label>Message <span class="required">*</span></label>
                            <textarea name="message" id="messageInput" required placeholder="Type your message here..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            <div class="char-count"><span id="charCount">0</span> characters</div>
                        </div>
                    </div>

                    <!-- Select Recipients -->
                    <div class="form-card">
                        <div class="card-title"><i class="fas fa-users"></i> Select LGA Coordinators</div>
                        
                        <div class="recipient-list" id="recipientList">
                            <div class="select-all">
                                <input type="checkbox" id="selectAll" onchange="toggleAll()" />
                                <label for="selectAll">Select All</label>
                                <span style="margin-left:auto;font-size:0.7rem;color:var(--gray-400);">
                                    <?php echo count($lga_coordinators); ?> coordinators
                                </span>
                            </div>
                            
                            <?php foreach ($lga_coordinators as $coordinator): ?>
                                <div class="recipient-item">
                                    <input type="checkbox" name="coordinators[]" value="<?php echo $coordinator['id']; ?>" 
                                           class="recipient-checkbox" onchange="updateCount()" />
                                    <span class="name"><?php echo htmlspecialchars($coordinator['first_name'] . ' ' . $coordinator['last_name']); ?></span>
                                    <span class="lga">(<?php echo htmlspecialchars($coordinator['lga_name']); ?>)</span>
                                    <span class="email"><?php echo htmlspecialchars($coordinator['email']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="recipient-count">
                            <i class="fas fa-users"></i>
                            Selected: <strong id="selectedCount">0</strong> of <?php echo count($lga_coordinators); ?> coordinators
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="form-card">
                        <div class="btn-group">
                            <a href="broadcasts.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn-send" id="sendBtn" disabled>
                                <i class="fas fa-paper-plane"></i> Send to Selected
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Character counter
document.getElementById('messageInput')?.addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

// Toggle all checkboxes
function toggleAll() {
    var selectAll = document.getElementById('selectAll');
    var checkboxes = document.querySelectorAll('.recipient-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = selectAll.checked;
    });
    updateCount();
}

// Update selected count
function updateCount() {
    var checkboxes = document.querySelectorAll('.recipient-checkbox:checked');
    var count = checkboxes.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('sendBtn').disabled = count === 0;
}

// Update count on load
document.addEventListener('DOMContentLoaded', function() {
    updateCount();
});

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