<?php
// ============================================================
// WARD COORDINATOR - SCHEDULE BROADCAST
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
// GET BROADCAST ID
// ============================================================
$broadcast_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($broadcast_id > 0) {
    // Fetch existing broadcast for rescheduling
    try {
        $stmt = $db->prepare("
            SELECT * FROM broadcasts 
            WHERE id = ? AND tenant_id = ? AND sender_id = ?
        ");
        $stmt->execute([$broadcast_id, $tenant_id, $user_id]);
        $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($broadcast && $broadcast['status'] !== 'sent') {
            // Pre-fill form data
            $scheduled_at = $broadcast['scheduled_at'] ? strtotime($broadcast['scheduled_at']) : null;
        }
    } catch (Exception $e) {
        error_log("Error fetching broadcast: " . $e->getMessage());
    }
}

// ============================================================
// HANDLE SCHEDULING
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $target_audience = isset($_POST['target_audience']) ? $_POST['target_audience'] : 'all';
    $target_ids = isset($_POST['target_ids']) ? $_POST['target_ids'] : [];
    $send_via = isset($_POST['send_via']) ? $_POST['send_via'] : ['in_app'];
    $schedule_date = isset($_POST['schedule_date']) ? trim($_POST['schedule_date']) : '';
    $schedule_time = isset($_POST['schedule_time']) ? trim($_POST['schedule_time']) : '';
    
    if (empty($title) || empty($message)) {
        $error_message = "Please fill in both title and message.";
    } elseif (empty($schedule_date) || empty($schedule_time)) {
        $error_message = "Please select both date and time for scheduling.";
    } else {
        try {
            $scheduled_at = $schedule_date . ' ' . $schedule_time . ':00';
            $now = date('Y-m-d H:i:s');
            
            if ($scheduled_at <= $now) {
                $error_message = "Scheduled time must be in the future.";
            } else {
                // Build target_ids_json
                $target_ids_json = null;
                if ($target_audience === 'specific' && !empty($target_ids)) {
                    $target_ids_json = json_encode($target_ids);
                } elseif ($target_audience !== 'all' && $target_audience !== 'specific') {
                    $role_map = [
                        'pu_agents' => 'pu_agent',
                        'party_agents' => 'party_agent',
                        'observers' => 'observer',
                        'volunteers' => 'volunteer'
                    ];
                    if (isset($role_map[$target_audience])) {
                        $stmt = $db->prepare("
                            SELECT id FROM users 
                            WHERE tenant_id = ? AND ward_id = ? AND deleted_at IS NULL AND status = 'active'
                            AND EXISTS (SELECT 1 FROM roles r WHERE r.id = users.role_id AND r.level = ?)
                            AND email IS NOT NULL AND email != ''
                        ");
                        $stmt->execute([$tenant_id, $ward_id, $role_map[$target_audience]]);
                        $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        if (!empty($user_ids)) {
                            $target_ids_json = json_encode($user_ids);
                        }
                    }
                }
                
                $send_via_json = json_encode($send_via);
                
                if ($broadcast_id > 0) {
                    // Update existing broadcast
                    $stmt = $db->prepare("
                        UPDATE broadcasts 
                        SET title = ?, message = ?, target_audience = ?, target_ids_json = ?,
                            send_via = ?, scheduled_at = ?, status = 'scheduled'
                        WHERE id = ? AND tenant_id = ? AND sender_id = ?
                    ");
                    $stmt->execute([
                        $title, $message, $target_audience, $target_ids_json,
                        $send_via_json, $scheduled_at,
                        $broadcast_id, $tenant_id, $user_id
                    ]);
                    
                    logActivity($user_id, 'broadcast_rescheduled', "Rescheduled broadcast: $title (ID: $broadcast_id)", 'broadcasts', $broadcast_id);
                    $success_message = "Broadcast rescheduled successfully!";
                    
                } else {
                    // Create new scheduled broadcast
                    $stmt = $db->prepare("
                        INSERT INTO broadcasts (
                            tenant_id, sender_id, title, message, target_audience, 
                            target_ids_json, send_via, scheduled_at, status, total_recipients, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', 0, NOW())
                    ");
                    $stmt->execute([
                        $tenant_id, $user_id, $title, $message, $target_audience,
                        $target_ids_json, $send_via_json, $scheduled_at
                    ]);
                    
                    $broadcast_id = $db->lastInsertId();
                    logActivity($user_id, 'broadcast_scheduled', "Scheduled broadcast: $title (ID: $broadcast_id)", 'broadcasts', $broadcast_id);
                    $success_message = "Broadcast scheduled successfully!";
                }
                
                header('Location: broadcasts.php?success=' . urlencode($success_message));
                exit();
            }
            
        } catch (Exception $e) {
            $error_message = "Error scheduling broadcast: " . $e->getMessage();
            error_log("Broadcast scheduling error: " . $e->getMessage());
        }
    }
}

$page_title = 'Schedule Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.schedule-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.schedule-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.schedule-header h2 i {
    color: var(--primary);
}

.schedule-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    max-width: 700px;
}
.schedule-form .form-group {
    margin-bottom: 16px;
}
.schedule-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.schedule-form .form-group label .required {
    color: #EF4444;
}
.schedule-form .form-group input[type="text"],
.schedule-form .form-group textarea,
.schedule-form .form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.schedule-form .form-group textarea {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}

.schedule-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.schedule-options input[type="date"],
.schedule-options input[type="time"] {
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    width: 100%;
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

.broadcast-info {
    background: #F8FAFC;
    border-radius: var(--radius);
    padding: 12px 16px;
    margin-bottom: 16px;
    font-size: 0.85rem;
    color: var(--gray-600);
}
.broadcast-info i {
    color: var(--gray-400);
    width: 16px;
}

@media (max-width: 768px) {
    .schedule-form {
        max-width: 100%;
    }
    .schedule-options {
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
        <div class="schedule-header">
            <div>
                <h2><i class="fas fa-calendar-plus"></i> <?php echo $broadcast_id > 0 ? 'Reschedule' : 'Schedule'; ?> Broadcast</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
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

        <!-- Schedule Form -->
        <div class="schedule-form">
            <form method="POST" action="" id="scheduleForm">
                <?php if ($broadcast_id > 0): ?>
                    <input type="hidden" name="broadcast_id" value="<?php echo $broadcast_id; ?>">
                    <div class="broadcast-info">
                        <i class="fas fa-info-circle"></i> 
                        Rescheduling broadcast: <strong><?php echo htmlspecialchars($broadcast['title'] ?? ''); ?></strong>
                        <?php if (!empty($broadcast['scheduled_at'])): ?>
                            <br>Previously scheduled for: <?php echo date('M d, Y H:i', strtotime($broadcast['scheduled_at'])); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Broadcast Title <span class="required">*</span></label>
                    <input type="text" name="title" id="title" placeholder="Enter broadcast title..." 
                           value="<?php echo htmlspecialchars($broadcast['title'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>Message <span class="required">*</span></label>
                    <textarea name="message" id="message" placeholder="Type your message here..." required><?php echo htmlspecialchars($broadcast['message'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target_audience">
                        <option value="all" <?php echo ($broadcast['target_audience'] ?? '') === 'all' ? 'selected' : ''; ?>>All Users</option>
                        <option value="pu_agents" <?php echo ($broadcast['target_audience'] ?? '') === 'pu_agents' ? 'selected' : ''; ?>>PU Agents</option>
                        <option value="party_agents" <?php echo ($broadcast['target_audience'] ?? '') === 'party_agents' ? 'selected' : ''; ?>>Party Agents</option>
                        <option value="observers" <?php echo ($broadcast['target_audience'] ?? '') === 'observers' ? 'selected' : ''; ?>>Observers</option>
                        <option value="volunteers" <?php echo ($broadcast['target_audience'] ?? '') === 'volunteers' ? 'selected' : ''; ?>>Volunteers</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Schedule Date & Time <span class="required">*</span></label>
                    <div class="schedule-options">
                        <input type="date" name="schedule_date" id="schedule_date" 
                               value="<?php echo $scheduled_at ? date('Y-m-d', $scheduled_at) : date('Y-m-d', strtotime('+1 day')); ?>" required>
                        <input type="time" name="schedule_time" id="schedule_time" 
                               value="<?php echo $scheduled_at ? date('H:i', $scheduled_at) : '09:00'; ?>" required>
                    </div>
                    <div style="font-size:0.7rem;color:var(--gray-400);margin-top:4px;">
                        <i class="fas fa-info-circle"></i> The broadcast will be sent automatically at the scheduled time
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-calendar-plus"></i> <?php echo $broadcast_id > 0 ? 'Reschedule' : 'Schedule'; ?> Broadcast
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
// Validate form
document.getElementById('scheduleForm').addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const message = document.getElementById('message').value.trim();
    const date = document.getElementById('schedule_date').value;
    const time = document.getElementById('schedule_time').value;
    
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
    if (!date || !time) {
        e.preventDefault();
        alert('Please select both date and time for scheduling.');
        return false;
    }
    
    const now = new Date();
    const scheduled = new Date(date + 'T' + time);
    if (scheduled <= now) {
        e.preventDefault();
        alert('Scheduled time must be in the future.');
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