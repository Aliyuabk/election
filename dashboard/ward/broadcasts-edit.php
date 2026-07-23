<?php
// ============================================================
// WARD COORDINATOR - EDIT BROADCAST
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
        WHERE id = ? AND tenant_id = ? AND sender_id = ?
    ");
    $stmt->execute([$broadcast_id, $tenant_id, $user_id]);
    $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$broadcast) {
        header('Location: broadcasts.php?error=notfound');
        exit();
    }
    
    // Check if broadcast can be edited (only draft or scheduled)
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
        AND u.email IS NOT NULL AND u.email != ''
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching agents: " . $e->getMessage());
}

// ============================================================
// PARSE BROADCAST DATA
// ============================================================
$target_ids = json_decode($broadcast['target_ids_json'] ?? '[]', true);
$send_via = json_decode($broadcast['send_via'] ?? '["email"]', true);
$scheduled_at = $broadcast['scheduled_at'] ? strtotime($broadcast['scheduled_at']) : null;

// ============================================================
// HANDLE BROADCAST UPDATE
// ============================================================
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $target_audience = isset($_POST['target_audience']) ? $_POST['target_audience'] : 'all';
    $target_ids = isset($_POST['target_ids']) ? $_POST['target_ids'] : [];
    $send_via = isset($_POST['send_via']) ? $_POST['send_via'] : ['in_app'];
    $status = isset($_POST['status']) ? $_POST['status'] : 'draft';
    $schedule_date = isset($_POST['schedule_date']) ? trim($_POST['schedule_date']) : '';
    $schedule_time = isset($_POST['schedule_time']) ? trim($_POST['schedule_time']) : '';
    
    if (empty($title) || empty($message)) {
        $error_message = "Please fill in both title and message.";
    } else {
        try {
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
            
            // Determine send_via as JSON
            $send_via_json = json_encode($send_via);
            
            // Determine scheduled_at
            $scheduled_at_db = null;
            if (!empty($schedule_date) && !empty($schedule_time) && $status === 'scheduled') {
                $scheduled_at_db = $schedule_date . ' ' . $schedule_time . ':00';
            }
            
            // Get total recipients count using existing function
            $recipients = getBroadcastRecipients($tenant_id, $target_audience, $target_ids);
            $total_recipients = count($recipients);
            
            // Update broadcast
            $stmt = $db->prepare("
                UPDATE broadcasts 
                SET title = ?, message = ?, target_audience = ?, target_ids_json = ?,
                    send_via = ?, scheduled_at = ?, status = ?, total_recipients = ?
                WHERE id = ? AND tenant_id = ? AND sender_id = ?
            ");
            
            $stmt->execute([
                $title,
                $message,
                $target_audience,
                $target_ids_json,
                $send_via_json,
                $scheduled_at_db,
                $status,
                $total_recipients,
                $broadcast_id,
                $tenant_id,
                $user_id
            ]);
            
            // Log activity
            logActivity($user_id, 'broadcast_updated', "Updated broadcast: $title (ID: $broadcast_id)", 'broadcasts', $broadcast_id);
            
            $success_message = "Broadcast updated successfully!";
            
            // If status is 'sent' and broadcast was updated to be sent, send it
            if ($status === 'sent' && !empty($recipients)) {
                $email_recipients = [];
                foreach ($recipients as $recipient) {
                    if (!empty($recipient['email'])) {
                        $email_recipients[] = [
                            'email' => $recipient['email'],
                            'full_name' => $recipient['full_name'] ?? 'User'
                        ];
                    }
                }
                
                if (!empty($email_recipients)) {
                    $email_result = sendBroadcastEmails($email_recipients, $title, $message);
                    $stmt = $db->prepare("UPDATE broadcasts SET sent_at = NOW(), status = 'sent' WHERE id = ?");
                    $stmt->execute([$broadcast_id]);
                    $success_message = "Broadcast updated and sent to " . $email_result['sent'] . " recipients!";
                }
            }
            
            // Refresh broadcast data
            $stmt = $db->prepare("SELECT * FROM broadcasts WHERE id = ?");
            $stmt->execute([$broadcast_id]);
            $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
            $target_ids = json_decode($broadcast['target_ids_json'] ?? '[]', true);
            $send_via = json_decode($broadcast['send_via'] ?? '["email"]', true);
            $scheduled_at = $broadcast['scheduled_at'] ? strtotime($broadcast['scheduled_at']) : null;
            
        } catch (Exception $e) {
            $error_message = "Error updating broadcast: " . $e->getMessage();
            error_log("Broadcast update error: " . $e->getMessage());
        }
    }
}

$page_title = 'Edit Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.edit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.edit-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.edit-header h2 i {
    color: var(--primary);
}

.edit-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
}
.edit-form .form-group {
    margin-bottom: 16px;
}
.edit-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.edit-form .form-group label .required {
    color: #EF4444;
}
.edit-form .form-group input[type="text"],
.edit-form .form-group textarea,
.edit-form .form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.edit-form .form-group textarea {
    resize: vertical;
    min-height: 120px;
}
.edit-form .form-group .helper {
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
.alert-warning {
    background: #FFFBEB;
    border: 1px solid #FEF3C7;
    color: #92400E;
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

.current-status {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 500;
}
.current-status.draft { background: #E5E7EB; color: #374151; }
.current-status.scheduled { background: #DBEAFE; color: #1E40AF; }
.current-status.sent { background: #D1FAE5; color: #065F46; }
.current-status.failed { background: #FEE2E2; color: #991B1B; }

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
        <div class="edit-header">
            <div>
                <h2><i class="fas fa-edit"></i> Edit Broadcast</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                    <span style="margin-left:12px;">
                        Status: <span class="current-status <?php echo $broadcast['status'] ?? 'draft'; ?>">
                            <?php echo ucfirst($broadcast['status'] ?? 'Draft'); ?>
                        </span>
                    </span>
                </p>
            </div>
            <div>
                <a href="broadcasts.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Broadcasts
                </a>
            </div>
        </div>

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

        <?php if ($broadcast): ?>
            <!-- Edit Form -->
            <div class="edit-form">
                <form method="POST" action="" id="broadcastForm">
                    <!-- Title -->
                    <div class="form-group">
                        <label>Broadcast Title <span class="required">*</span></label>
                        <input type="text" name="title" id="title" placeholder="Enter broadcast title..." 
                               value="<?php echo htmlspecialchars($broadcast['title'] ?? ''); ?>" required>
                    </div>

                    <!-- Message -->
                    <div class="form-group">
                        <label>Message <span class="required">*</span></label>
                        <textarea name="message" id="message" placeholder="Type your message here..." 
                                  onkeyup="updateCharCounter()" required><?php echo htmlspecialchars($broadcast['message'] ?? ''); ?></textarea>
                        <div class="char-counter" id="charCounter"><?php echo strlen($broadcast['message'] ?? ''); ?> characters</div>
                    </div>

                    <!-- Target Audience -->
                    <div class="form-group">
                        <label>Target Audience <span class="required">*</span></label>
                        <div class="audience-selector" id="audienceSelector">
                            <label class="audience-option <?php echo ($broadcast['target_audience'] === 'all' || empty($broadcast['target_audience'])) ? 'selected' : ''; ?>">
                                <input type="radio" name="target_audience" value="all" <?php echo ($broadcast['target_audience'] === 'all' || empty($broadcast['target_audience'])) ? 'checked' : ''; ?> onchange="toggleAudience()">
                                <i class="fas fa-users"></i> All Users
                            </label>
                            <label class="audience-option <?php echo $broadcast['target_audience'] === 'pu_agents' ? 'selected' : ''; ?>">
                                <input type="radio" name="target_audience" value="pu_agents" <?php echo $broadcast['target_audience'] === 'pu_agents' ? 'checked' : ''; ?> onchange="toggleAudience()">
                                <i class="fas fa-user-check"></i> PU Agents
                            </label>
                            <label class="audience-option <?php echo $broadcast['target_audience'] === 'party_agents' ? 'selected' : ''; ?>">
                                <input type="radio" name="target_audience" value="party_agents" <?php echo $broadcast['target_audience'] === 'party_agents' ? 'checked' : ''; ?> onchange="toggleAudience()">
                                <i class="fas fa-flag"></i> Party Agents
                            </label>
                            <label class="audience-option <?php echo $broadcast['target_audience'] === 'observers' ? 'selected' : ''; ?>">
                                <input type="radio" name="target_audience" value="observers" <?php echo $broadcast['target_audience'] === 'observers' ? 'checked' : ''; ?> onchange="toggleAudience()">
                                <i class="fas fa-eye"></i> Observers
                            </label>
                            <label class="audience-option <?php echo $broadcast['target_audience'] === 'volunteers' ? 'selected' : ''; ?>">
                                <input type="radio" name="target_audience" value="volunteers" <?php echo $broadcast['target_audience'] === 'volunteers' ? 'checked' : ''; ?> onchange="toggleAudience()">
                                <i class="fas fa-hands-helping"></i> Volunteers
                            </label>
                            <label class="audience-option <?php echo $broadcast['target_audience'] === 'specific' ? 'selected' : ''; ?>">
                                <input type="radio" name="target_audience" value="specific" <?php echo $broadcast['target_audience'] === 'specific' ? 'checked' : ''; ?> onchange="toggleAudience()">
                                <i class="fas fa-user-plus"></i> Specific Users
                            </label>
                        </div>
                    </div>

                    <!-- Specific Users Selection -->
                    <div class="form-group" id="specificUsersContainer" style="<?php echo $broadcast['target_audience'] === 'specific' ? '' : 'display:none;'; ?>">
                        <label>Select Users</label>
                        <div class="user-select">
                            <?php if (count($agents) > 0): ?>
                                <?php foreach ($agents as $agent): ?>
                                    <label class="user-item">
                                        <input type="checkbox" name="target_ids[]" value="<?php echo $agent['id']; ?>"
                                               <?php echo (in_array($agent['id'], $target_ids)) ? 'checked' : ''; ?>>
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
                                <input type="checkbox" name="send_via[]" value="email" <?php echo in_array('email', $send_via) ? 'checked' : 'checked'; ?>>
                                <i class="fas fa-envelope"></i> Email
                            </label>
                            <label>
                                <input type="checkbox" name="send_via[]" value="in_app" <?php echo in_array('in_app', $send_via) ? 'checked' : 'checked'; ?>>
                                <i class="fas fa-bell"></i> In-App Notification
                            </label>
                            <label>
                                <input type="checkbox" name="send_via[]" value="sms" <?php echo in_array('sms', $send_via) ? 'checked' : ''; ?>>
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
                                <option value="draft" <?php echo ($broadcast['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="sent" <?php echo ($broadcast['status'] === 'sent') ? 'selected' : ''; ?>>Send Now</option>
                                <option value="scheduled" <?php echo ($broadcast['status'] === 'scheduled') ? 'selected' : ''; ?>>Schedule</option>
                            </select>
                        </div>
                        <div class="form-group" id="scheduleContainer" style="<?php echo $broadcast['status'] === 'scheduled' ? '' : 'display:none;'; ?>">
                            <label>Schedule Date & Time</label>
                            <div class="schedule-options">
                                <input type="date" name="schedule_date" id="schedule_date" 
                                       value="<?php echo $scheduled_at ? date('Y-m-d', $scheduled_at) : date('Y-m-d'); ?>">
                                <input type="time" name="schedule_time" id="schedule_time" 
                                       value="<?php echo $scheduled_at ? date('H:i', $scheduled_at) : date('H:i', strtotime('+1 hour')); ?>">
                                <span style="font-size:0.7rem;color:var(--gray-400);padding:8px 0;">
                                    <i class="fas fa-info-circle"></i> Scheduled time
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Broadcast Info -->
                    <div style="background:var(--gray-50);padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:0.82rem;color:var(--gray-600);">
                        <div><i class="fas fa-clock"></i> Created: <?php echo date('M d, Y H:i', strtotime($broadcast['created_at'])); ?></div>
                        <div><i class="fas fa-users"></i> Current recipients: <?php echo number_format($broadcast['total_recipients'] ?? 0); ?></div>
                        <?php if ($broadcast['sent_at']): ?>
                            <div><i class="fas fa-check-circle" style="color:#10B981;"></i> Sent: <?php echo date('M d, Y H:i', strtotime($broadcast['sent_at'])); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> <span id="submitLabel">Update Broadcast</span>
                        </button>
                        <?php if ($broadcast['status'] === 'draft' || $broadcast['status'] === 'scheduled'): ?>
                            <a href="broadcasts-send.php?id=<?php echo $broadcast_id; ?>" class="btn-primary" style="background:#10B981;border-color:#10B981;">
                                <i class="fas fa-paper-plane"></i> Send Now
                            </a>
                        <?php endif; ?>
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
                <p style="color:var(--gray-500);">The broadcast you're trying to edit does not exist.</p>
                <a href="broadcasts.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-arrow-left"></i> Back to Broadcasts
                </a>
            </div>
        <?php endif; ?>
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
        submitLabel.textContent = 'Update & Schedule';
        submitBtn.innerHTML = '<i class="fas fa-calendar-plus"></i> Update & Schedule';
    } else if (status === 'sent') {
        scheduleContainer.style.display = 'none';
        submitLabel.textContent = 'Update & Send Now';
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Update & Send';
    } else {
        scheduleContainer.style.display = 'none';
        submitLabel.textContent = 'Update Broadcast';
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Broadcast';
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