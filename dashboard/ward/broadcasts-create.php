<?php
// ============================================================
// WARD COORDINATOR - CREATE BROADCAST
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
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');
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
// FETCH TARGET AUDIENCE OPTIONS
// ============================================================
$audience_options = [
    'all' => 'All Users',
    'pu_agents' => 'PU Agents Only',
    'party_agents' => 'Party Agents Only',
    'observers' => 'Observers Only',
    'volunteers' => 'Volunteers Only',
    'specific' => 'Specific Users'
];

// ============================================================
// FETCH AGENTS FOR SELECTION
// ============================================================
$agents = [];
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            r.level as role_level,
            pu.name as pu_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND u.status = 'active'
        AND r.level IN ('pu_agent', 'party_agent', 'observer', 'volunteer')
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching agents: " . $e->getMessage());
}

// ============================================================
// HANDLE BROADCAST CREATION
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
    $status = isset($_POST['status']) ? $_POST['status'] : 'draft';
    
    if (empty($title) || empty($message)) {
        $error_message = "Please fill in both title and message.";
    } else {
        try {
            // Build target_ids_json
            $target_ids_json = null;
            if ($target_audience === 'specific' && !empty($target_ids)) {
                $target_ids_json = json_encode($target_ids);
            } elseif ($target_audience !== 'all') {
                // For role-based targeting, get all user IDs with that role
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
                    ");
                    $stmt->execute([$tenant_id, $ward_id, $role_map[$target_audience]]);
                    $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($user_ids)) {
                        $target_ids_json = json_encode($user_ids);
                    }
                }
            }
            
            // Determine send_via as JSON
            $send_via_json = json_encode($send_via);
            
            // Determine scheduled_at
            $scheduled_at = null;
            if (!empty($schedule_date) && !empty($schedule_time) && $status === 'scheduled') {
                $scheduled_at = $schedule_date . ' ' . $schedule_time . ':00';
            }
            
            // Insert broadcast
            $stmt = $db->prepare("
                INSERT INTO broadcasts (
                    tenant_id, sender_id, title, message, target_audience, 
                    target_ids_json, send_via, scheduled_at, status, total_recipients, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            
            $stmt->execute([
                $tenant_id,
                $user_id,
                $title,
                $message,
                $target_audience,
                $target_ids_json,
                $send_via_json,
                $scheduled_at,
                $status
            ]);
            
            $broadcast_id = $db->lastInsertId();
            
            // Log activity
            logActivity($user_id, 'broadcast_created', "Created broadcast: $title (ID: $broadcast_id)", 'broadcasts', $broadcast_id);
            
            // If status is 'sent', send immediately
            if ($status === 'sent') {
                // Process sending in background or here
                // For now, just mark as sent
                $stmt = $db->prepare("UPDATE broadcasts SET sent_at = NOW(), status = 'sent' WHERE id = ?");
                $stmt->execute([$broadcast_id]);
                $success_message = "Broadcast created and sent successfully!";
            } elseif ($status === 'scheduled') {
                $success_message = "Broadcast scheduled successfully!";
            } else {
                $success_message = "Broadcast saved as draft successfully!";
            }
            
            // Redirect to broadcasts list
            header('Location: broadcasts.php?success=' . urlencode($success_message));
            exit();
            
        } catch (Exception $e) {
            $error_message = "Error creating broadcast: " . $e->getMessage();
            error_log("Broadcast creation error: " . $e->getMessage());
        }
    }
}

$page_title = 'Create Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.broadcast-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.broadcast-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.broadcast-header h2 i {
    color: var(--primary);
}

.broadcast-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
}
.broadcast-form .form-group {
    margin-bottom: 16px;
}
.broadcast-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.broadcast-form .form-group label .required {
    color: #EF4444;
}
.broadcast-form .form-group input[type="text"],
.broadcast-form .form-group textarea,
.broadcast-form .form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.broadcast-form .form-group textarea {
    resize: vertical;
    min-height: 120px;
}
.broadcast-form .form-group .helper {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.audience-selector {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin: 8px 0;
}
.audience-option {
    padding: 8px 16px;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius);
    cursor: pointer;
    transition: var(--transition);
    font-size: 0.82rem;
}
.audience-option:hover {
    border-color: var(--primary);
}
.audience-option.selected {
    border-color: var(--primary);
    background: #EFF6FF;
}
.audience-option input {
    display: none;
}

.user-select {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 8px;
    max-height: 200px;
    overflow-y: auto;
    padding: 8px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
}
.user-select .user-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 0.78rem;
    cursor: pointer;
}
.user-select .user-item:hover {
    background: var(--gray-50);
}
.user-select .user-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
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

.schedule-options {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 12px;
    align-items: end;
}
.schedule-options input[type="date"],
.schedule-options input[type="time"] {
    padding: 8px 12px;
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

.char-counter {
    font-size: 0.7rem;
    color: var(--gray-400);
    text-align: right;
    margin-top: 4px;
}
.char-counter.warning {
    color: #F59E0B;
}
.char-counter.danger {
    color: #EF4444;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
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
    .user-select {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="broadcast-header">
            <div>
                <h2><i class="fas fa-bullhorn"></i> Create Broadcast</h2>
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

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Broadcast Form -->
        <div class="broadcast-form">
            <form method="POST" action="" id="broadcastForm">
                <!-- Title -->
                <div class="form-group">
                    <label>Broadcast Title <span class="required">*</span></label>
                    <input type="text" name="title" id="title" placeholder="Enter broadcast title..." 
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                </div>

                <!-- Message -->
                <div class="form-group">
                    <label>Message <span class="required">*</span></label>
                    <textarea name="message" id="message" placeholder="Type your message here..." 
                              onkeyup="updateCharCounter()" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    <div class="char-counter" id="charCounter">0 characters</div>
                </div>

                <!-- Target Audience -->
                <div class="form-group">
                    <label>Target Audience <span class="required">*</span></label>
                    <div class="audience-selector" id="audienceSelector">
                        <label class="audience-option <?php echo (!isset($_POST['target_audience']) || $_POST['target_audience'] === 'all') ? 'selected' : ''; ?>">
                            <input type="radio" name="target_audience" value="all" <?php echo (!isset($_POST['target_audience']) || $_POST['target_audience'] === 'all') ? 'checked' : ''; ?> onchange="toggleAudience()">
                            <i class="fas fa-users"></i> All Users
                        </label>
                        <label class="audience-option <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'pu_agents') ? 'selected' : ''; ?>">
                            <input type="radio" name="target_audience" value="pu_agents" <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'pu_agents') ? 'checked' : ''; ?> onchange="toggleAudience()">
                            <i class="fas fa-user-check"></i> PU Agents
                        </label>
                        <label class="audience-option <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'party_agents') ? 'selected' : ''; ?>">
                            <input type="radio" name="target_audience" value="party_agents" <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'party_agents') ? 'checked' : ''; ?> onchange="toggleAudience()">
                            <i class="fas fa-flag"></i> Party Agents
                        </label>
                        <label class="audience-option <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'observers') ? 'selected' : ''; ?>">
                            <input type="radio" name="target_audience" value="observers" <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'observers') ? 'checked' : ''; ?> onchange="toggleAudience()">
                            <i class="fas fa-eye"></i> Observers
                        </label>
                        <label class="audience-option <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'volunteers') ? 'selected' : ''; ?>">
                            <input type="radio" name="target_audience" value="volunteers" <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'volunteers') ? 'checked' : ''; ?> onchange="toggleAudience()">
                            <i class="fas fa-hands-helping"></i> Volunteers
                        </label>
                        <label class="audience-option <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'specific') ? 'selected' : ''; ?>">
                            <input type="radio" name="target_audience" value="specific" <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'specific') ? 'checked' : ''; ?> onchange="toggleAudience()">
                            <i class="fas fa-user-plus"></i> Specific Users
                        </label>
                    </div>
                </div>

                <!-- Specific Users Selection -->
                <div class="form-group" id="specificUsersContainer" style="<?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'specific') ? '' : 'display:none;'; ?>">
                    <label>Select Users</label>
                    <div class="user-select">
                        <?php if (count($agents) > 0): ?>
                            <?php foreach ($agents as $agent): ?>
                                <label class="user-item">
                                    <input type="checkbox" name="target_ids[]" value="<?php echo $agent['id']; ?>"
                                           <?php echo (isset($_POST['target_ids']) && in_array($agent['id'], $_POST['target_ids'])) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($agent['full_name']); ?></span>
                                    <span style="font-size:0.65rem;color:var(--gray-400);">
                                        (<?php echo ucfirst(str_replace('_', ' ', $agent['role_level'] ?? '')); ?>)
                                        <?php if (!empty($agent['pu_name'])): ?>
                                            - <?php echo htmlspecialchars($agent['pu_name']); ?>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center;padding:16px;color:var(--gray-400);">
                                No active agents found in this ward.
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="helper">Select specific users to receive this broadcast. Leave empty to send to all.</div>
                </div>

                <!-- Send Via -->
                <div class="form-group">
                    <label>Send Via</label>
                    <div class="send-via-options">
                        <label>
                            <input type="checkbox" name="send_via[]" value="email" <?php echo (isset($_POST['send_via']) && in_array('email', $_POST['send_via'])) ? 'checked' : 'checked'; ?>>
                            <i class="fas fa-envelope"></i> Email
                        </label>
                        <label>
                            <input type="checkbox" name="send_via[]" value="in_app" <?php echo (isset($_POST['send_via']) && in_array('in_app', $_POST['send_via'])) ? 'checked' : 'checked'; ?>>
                            <i class="fas fa-bell"></i> In-App Notification
                        </label>
                        <label>
                            <input type="checkbox" name="send_via[]" value="sms" <?php echo (isset($_POST['send_via']) && in_array('sms', $_POST['send_via'])) ? 'checked' : ''; ?>>
                            <i class="fas fa-sms"></i> SMS
                        </label>
                    </div>
                    <div class="helper">Select at least one channel to send the broadcast.</div>
                </div>

                <!-- Status & Scheduling -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="status" onchange="toggleSchedule()">
                            <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] === 'draft') ? 'selected' : 'selected'; ?>>Draft</option>
                            <option value="sent" <?php echo (isset($_POST['status']) && $_POST['status'] === 'sent') ? 'selected' : ''; ?>>Send Now</option>
                            <option value="scheduled" <?php echo (isset($_POST['status']) && $_POST['status'] === 'scheduled') ? 'selected' : ''; ?>>Schedule</option>
                        </select>
                    </div>
                    <div class="form-group" id="scheduleContainer" style="<?php echo (isset($_POST['status']) && $_POST['status'] === 'scheduled') ? '' : 'display:none;'; ?>">
                        <label>Schedule Date & Time</label>
                        <div class="schedule-options">
                            <input type="date" name="schedule_date" id="schedule_date" 
                                   value="<?php echo isset($_POST['schedule_date']) ? htmlspecialchars($_POST['schedule_date']) : date('Y-m-d'); ?>">
                            <input type="time" name="schedule_time" id="schedule_time" 
                                   value="<?php echo isset($_POST['schedule_time']) ? htmlspecialchars($_POST['schedule_time']) : date('H:i', strtotime('+1 hour')); ?>">
                            <span style="font-size:0.7rem;color:var(--gray-400);padding:8px 0;">
                                <i class="fas fa-info-circle"></i> Scheduled time
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> <span id="submitLabel">Save as Draft</span>
                    </button>
                    <button type="button" class="btn-secondary" onclick="resetForm()">
                        <i class="fas fa-undo"></i> Reset
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
// Toggle audience selection
function toggleAudience() {
    const selected = document.querySelector('input[name="target_audience"]:checked');
    const specificContainer = document.getElementById('specificUsersContainer');
    
    if (selected && selected.value === 'specific') {
        specificContainer.style.display = 'block';
    } else {
        specificContainer.style.display = 'none';
    }
}

// Toggle schedule options
function toggleSchedule() {
    const status = document.getElementById('status').value;
    const scheduleContainer = document.getElementById('scheduleContainer');
    const submitLabel = document.getElementById('submitLabel');
    const submitBtn = document.getElementById('submitBtn');
    
    if (status === 'scheduled') {
        scheduleContainer.style.display = 'block';
        submitLabel.textContent = 'Schedule Broadcast';
        submitBtn.innerHTML = '<i class="fas fa-calendar-plus"></i> Schedule Broadcast';
    } else if (status === 'sent') {
        scheduleContainer.style.display = 'none';
        submitLabel.textContent = 'Send Now';
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Now';
    } else {
        scheduleContainer.style.display = 'none';
        submitLabel.textContent = 'Save as Draft';
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save as Draft';
    }
}

// Update character counter
function updateCharCounter() {
    const message = document.getElementById('message');
    const counter = document.getElementById('charCounter');
    const length = message.value.length;
    const maxLength = 5000;
    
    counter.textContent = length + ' characters';
    counter.className = 'char-counter';
    
    if (length > maxLength * 0.8) {
        counter.classList.add('warning');
    }
    if (length > maxLength) {
        counter.classList.add('danger');
        message.style.borderColor = '#EF4444';
    } else {
        message.style.borderColor = '';
    }
}

// Reset form
function resetForm() {
    if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
        document.getElementById('broadcastForm').reset();
        document.getElementById('message').value = '';
        updateCharCounter();
        toggleAudience();
        toggleSchedule();
        
        // Reset audience selection
        document.querySelectorAll('.audience-option').forEach(function(el) {
            el.classList.remove('selected');
        });
        document.querySelector('.audience-option input[value="all"]').closest('.audience-option').classList.add('selected');
    }
}

// Validate form
document.getElementById('broadcastForm').addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const message = document.getElementById('message').value.trim();
    
    if (!title || !message) {
        e.preventDefault();
        alert('Please fill in both the title and message fields.');
        return false;
    }
    
    // Check if at least one send via is selected
    const sendVia = document.querySelectorAll('input[name="send_via[]"]:checked');
    if (sendVia.length === 0) {
        e.preventDefault();
        alert('Please select at least one delivery channel (Email, In-App, or SMS).');
        return false;
    }
    
    // Check if specific users are selected when target is specific
    const targetAudience = document.querySelector('input[name="target_audience"]:checked');
    if (targetAudience && targetAudience.value === 'specific') {
        const selectedUsers = document.querySelectorAll('input[name="target_ids[]"]:checked');
        if (selectedUsers.length === 0) {
            e.preventDefault();
            alert('Please select at least one user when targeting specific users.');
            return false;
        }
    }
    
    const status = document.getElementById('status').value;
    if (status === 'scheduled') {
        const date = document.getElementById('schedule_date').value;
        const time = document.getElementById('schedule_time').value;
        if (!date || !time) {
            e.preventDefault();
            alert('Please select both a date and time for the scheduled broadcast.');
            return false;
        }
        
        const now = new Date();
        const scheduled = new Date(date + 'T' + time);
        if (scheduled <= now) {
            e.preventDefault();
            alert('Scheduled time must be in the future.');
            return false;
        }
    }
    
    return true;
});

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    toggleAudience();
    toggleSchedule();
    updateCharCounter();
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