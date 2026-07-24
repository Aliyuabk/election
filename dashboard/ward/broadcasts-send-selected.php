<?php
// ============================================================
// WARD COORDINATOR - SEND BROADCAST TO SELECTED USERS
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
// FETCH WARD NAME
// ============================================================
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// FETCH USERS
// ============================================================
$users = [];
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.user_code,
            u.email,
            u.phone,
            r.level as role_level,
            pu.name as pu_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
        AND u.status = 'active'
        AND u.email IS NOT NULL AND u.email != ''
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
}

// ============================================================
// HANDLE SEND TO SELECTED
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $selected_users = isset($_POST['selected_users']) ? $_POST['selected_users'] : [];
    $send_via = isset($_POST['send_via']) ? $_POST['send_via'] : ['email'];
    
    if (empty($title) || empty($message)) {
        $error_message = "Please fill in both title and message.";
    } elseif (empty($selected_users)) {
        $error_message = "Please select at least one recipient.";
    } else {
        try {
            // Get selected recipients
            $recipients = [];
            foreach ($users as $user) {
                if (in_array($user['id'], $selected_users)) {
                    $recipients[] = $user;
                }
            }
            $total_recipients = count($recipients);
            
            // Insert broadcast
            $send_via_json = json_encode($send_via);
            $target_ids_json = json_encode($selected_users);
            $stmt = $db->prepare("
                INSERT INTO broadcasts (
                    tenant_id, sender_id, title, message, target_audience, 
                    target_ids_json, send_via, status, total_recipients, sent_at, created_at
                ) VALUES (?, ?, ?, ?, 'specific', ?, ?, 'sent', ?, NOW(), NOW())
            ");
            $stmt->execute([$tenant_id, $user_id, $title, $message, $target_ids_json, $send_via_json, $total_recipients]);
            $broadcast_id = $db->lastInsertId();
            
            // Send emails
            if (in_array('email', $send_via)) {
                $email_list = array_filter(array_column($recipients, 'email'));
                $email_result = sendBroadcastEmails($email_list, $title, $message);
            }
            
            logActivity($user_id, 'broadcast_sent_selected', "Sent broadcast to selected users: $title", 'broadcasts', $broadcast_id);
            
            $success_message = "Broadcast sent to $total_recipients recipients successfully!";
            header('Location: broadcasts.php?success=' . urlencode($success_message));
            exit();
            
        } catch (Exception $e) {
            $error_message = "Error sending broadcast: " . $e->getMessage();
            error_log("Broadcast send selected error: " . $e->getMessage());
        }
    }
}

$page_title = 'Send Broadcast to Selected Users';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

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

.send-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    max-width: 800px;
}
.send-form .form-group {
    margin-bottom: 16px;
}
.send-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.send-form .form-group label .required {
    color: #EF4444;
}
.send-form .form-group input[type="text"],
.send-form .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.send-form .form-group textarea {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}

.user-select-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 8px;
    max-height: 250px;
    overflow-y: auto;
    padding: 8px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
}
.user-select-grid .user-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 0.78rem;
    cursor: pointer;
}
.user-select-grid .user-item:hover {
    background: var(--gray-50);
}
.user-select-grid .user-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}
.user-select-grid .user-item .user-role {
    font-size: 0.6rem;
    color: var(--gray-400);
}

.send-via-options {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}
.send-via-options label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 400;
    font-size: 0.85rem;
    cursor: pointer;
}
.send-via-options input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
}

.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 1.1rem;
}

.select-all {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0 8px;
    font-size: 0.85rem;
}
.select-all input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

@media (max-width: 768px) {
    .send-form {
        max-width: 100%;
    }
    .user-select-grid {
        grid-template-columns: 1fr;
    }
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
                <h2><i class="fas fa-user-plus"></i> Send Broadcast to Selected Users</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div>
                <a href="broadcasts.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Send Form -->
        <div class="send-form">
            <form method="POST" action="" id="sendForm">
                <div class="form-group">
                    <label>Broadcast Title <span class="required">*</span></label>
                    <input type="text" name="title" id="title" placeholder="Enter broadcast title..." required>
                </div>

                <div class="form-group">
                    <label>Message <span class="required">*</span></label>
                    <textarea name="message" id="message" placeholder="Type your message here..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Select Recipients <span class="required">*</span></label>
                    <div class="select-all">
                        <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
                        <label for="selectAll"><strong>Select All</strong></label>
                        <span style="font-size:0.7rem;color:var(--gray-400);margin-left:8px;">
                            <?php echo count($users); ?> users available
                        </span>
                    </div>
                    <div class="user-select-grid">
                        <?php foreach ($users as $user): ?>
                            <label class="user-item">
                                <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" class="user-checkbox">
                                <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                                <span class="user-role">
                                    (<?php echo ucfirst(str_replace('_', ' ', $user['role_level'] ?? 'User')); ?>)
                                    <?php if (!empty($user['pu_name'])): ?>
                                        - <?php echo htmlspecialchars($user['pu_name']); ?>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Send Via</label>
                    <div class="send-via-options">
                        <label>
                            <input type="checkbox" name="send_via[]" value="email" checked>
                            <i class="fas fa-envelope"></i> Email
                        </label>
                        <label>
                            <input type="checkbox" name="send_via[]" value="in_app">
                            <i class="fas fa-bell"></i> In-App Notification
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary" onclick="return confirm('Send this broadcast to selected users?')">
                        <i class="fas fa-paper-plane"></i> Send to Selected
                    </button>
                    <a href="broadcasts.php" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
function toggleAll(master) {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = master.checked;
    });
}

document.getElementById('sendForm').addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const message = document.getElementById('message').value.trim();
    const selected = document.querySelectorAll('.user-checkbox:checked');
    
    if (!title) {
        e.preventDefault();
        alert('Please enter a broadcast title.');
        return false;
    }
    if (!message) {
        e.preventDefault();
        alert('Please enter a broadcast message.');
        return false;
    }
    if (selected.length === 0) {
        e.preventDefault();
        alert('Please select at least one recipient.');
        return false;
    }
    return true;
});

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