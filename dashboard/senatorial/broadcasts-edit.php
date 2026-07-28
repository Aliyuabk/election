<?php
// ============================================================
// SENATORIAL COORDINATOR - EDIT BROADCAST
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

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$senatorial_id = SessionManager::get('senatorial_id');
$state_id = SessionManager::get('state_id');
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
// GET SENATORIAL DISTRICT NAME
// ============================================================
$district_name = 'Senatorial District';
$state_name = 'State';
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("
            SELECT s.name as state_name, sd.name as district_name 
            FROM senatorial_districts sd 
            JOIN states s ON sd.state_id = s.id 
            WHERE sd.id = ?
        ");
        $stmt->execute([$senatorial_id]);
        $result = $stmt->fetch();
        if ($result) {
            $district_name = $result['district_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching district: " . $e->getMessage());
}

// ============================================================
// GET LGA IDs FROM SENATORIAL DISTRICT
// ============================================================
$lga_ids = [];
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("SELECT lgas_json FROM senatorial_districts WHERE id = ?");
        $stmt->execute([$senatorial_id]);
        $lgas_json = $stmt->fetchColumn();
        
        if ($lgas_json) {
            $lga_names = json_decode($lgas_json, true) ?: [];
            
            if (!empty($lga_names)) {
                $placeholders = implode(',', array_fill(0, count($lga_names), '?'));
                $stmt = $db->prepare("SELECT id FROM lgas WHERE name IN ($placeholders) AND state_id = ? AND is_active = 1");
                $stmt->execute(array_merge($lga_names, [$state_id]));
                $lga_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error getting LGA IDs: " . $e->getMessage());
    $lga_ids = [];
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

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

// Check if broadcast exists and is editable
if (!$broadcast) {
    header('Location: broadcasts.php');
    exit();
}

// Only drafts and scheduled can be edited
if (!in_array($broadcast['status'], ['draft', 'scheduled'])) {
    $_SESSION['flash_error'] = 'This broadcast cannot be edited.';
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// GET LGAS AND ROLES FOR TARGET SELECTION
// ============================================================
$lgas = [];
$roles = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
    }
    
    $stmt = $db->prepare("
        SELECT id, name, level 
        FROM roles 
        WHERE level IN ('federal_constituency', 'lga', 'ward', 'pu_agent', 'party_agent', 'volunteer', 'observer')
        AND (tenant_id = ? OR tenant_id IS NULL)
        AND is_active = 1
        ORDER BY name ASC
    ");
    $stmt->execute([$tenant_id]);
    $roles = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching target options: " . $e->getMessage());
}

// ============================================================
// PARSE EXISTING DATA
// ============================================================
$target_ids = [];
if ($broadcast['target_ids_json']) {
    $target_ids = json_decode($broadcast['target_ids_json'], true) ?: [];
}

$send_via = [];
if ($broadcast['send_via']) {
    $send_via = json_decode($broadcast['send_via'], true) ?: ['email'];
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $target_audience = $_POST['target_audience'] ?? 'all';
    $target_ids_post = isset($_POST['target_ids']) ? (array)$_POST['target_ids'] : [];
    $target_role_id = isset($_POST['target_role_id']) ? (int)$_POST['target_role_id'] : 0;
    $send_via_post = isset($_POST['send_via']) ? (array)$_POST['send_via'] : ['email', 'in_app'];
    $schedule = isset($_POST['schedule']) ? true : false;
    $scheduled_at = $_POST['scheduled_at'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    
    $errors = [];
    
    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($message)) $errors[] = 'Message is required.';
    if (empty($send_via_post)) $errors[] = 'At least one delivery method is required.';
    
    if ($schedule && empty($scheduled_at)) {
        $errors[] = 'Scheduled time is required when scheduling a broadcast.';
    }
    
    if ($target_audience === 'role_specific' && empty($target_role_id)) {
        $errors[] = 'Please select a target role for role-specific broadcasts.';
    }
    
    if (empty($errors)) {
        try {
            // Determine status
            $status = $broadcast['status']; // Keep existing status
            if ($schedule && $broadcast['status'] === 'draft') {
                $status = 'scheduled';
            } elseif (!$schedule && $broadcast['status'] === 'scheduled') {
                $status = 'draft';
            }
            
            $stmt = $db->prepare("
                UPDATE broadcasts SET
                    title = ?,
                    message = ?,
                    target_audience = ?,
                    target_ids_json = ?,
                    target_role_id = ?,
                    send_via = ?,
                    scheduled_at = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            
            $target_ids_json = !empty($target_ids_post) ? json_encode($target_ids_post) : null;
            $send_via_json = json_encode($send_via_post);
            
            $stmt->execute([
                $title,
                $message,
                $target_audience,
                $target_ids_json,
                $target_role_id ?: null,
                $send_via_json,
                $scheduled_at ?: null,
                $status,
                $broadcast_id,
                $tenant_id
            ]);
            
            logActivity($user_id, 'broadcast_updated', "Updated broadcast: $title (ID: $broadcast_id)");
            
            $success = "Broadcast updated successfully!";
            
            // Refresh broadcast data
            $stmt = $db->prepare("SELECT * FROM broadcasts WHERE id = ?");
            $stmt->execute([$broadcast_id]);
            $broadcast = $stmt->fetch();
            
        } catch (Exception $e) {
            $error = 'Error updating broadcast: ' . $e->getMessage();
            error_log("Broadcast update error: " . $e->getMessage());
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// ============================================================
// GET RECIPIENT COUNT
// ============================================================
function getRecipientCount($target_audience, $target_ids, $target_role_id, $tenant_id, $lga_list) {
    $db = getDB();
    $count = 0;
    
    try {
        if ($target_audience === 'all') {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM users 
                WHERE tenant_id = ? AND status = 'active'
                AND role_id IN (SELECT id FROM roles WHERE level IN ('federal_constituency', 'lga', 'ward', 'pu_agent', 'party_agent', 'volunteer', 'observer'))
            ");
            $stmt->execute([$tenant_id]);
            $count = $stmt->fetchColumn();
        } elseif ($target_audience === 'lga' && !empty($target_ids)) {
            $placeholders = implode(',', array_fill(0, count($target_ids), '?'));
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM users 
                WHERE tenant_id = ? AND status = 'active' AND lga_id IN ($placeholders)
            ");
            $params = array_merge([$tenant_id], $target_ids);
            $stmt->execute($params);
            $count = $stmt->fetchColumn();
        } elseif ($target_audience === 'role_specific' && $target_role_id) {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM users 
                WHERE tenant_id = ? AND status = 'active' AND role_id = ?
            ");
            $stmt->execute([$tenant_id, $target_role_id]);
            $count = $stmt->fetchColumn();
        }
    } catch (Exception $e) {
        error_log("Error getting recipient count: " . $e->getMessage());
    }
    
    return $count;
}

$recipient_count = getRecipientCount(
    $broadcast['target_audience'],
    $target_ids,
    $broadcast['target_role_id'] ?? 0,
    $tenant_id,
    $lga_list
);

$page_title = 'Edit Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
/* Same styles as broadcasts-create.php */
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

.form-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 28px 32px;
    box-shadow: var(--shadow);
}
.form-container .form-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-container .form-subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gray-100);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px 24px;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.form-group.full-width {
    grid-column: 1 / -1;
}
.form-group label {
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--gray-700);
}
.form-group label .required {
    color: var(--danger);
    margin-left: 2px;
}
.form-group .help-text {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 2px;
}
.form-group input,
.form-group select,
.form-group textarea {
    padding: 10px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    transition: var(--transition);
    background: var(--gray-50);
    color: var(--gray-700);
    width: 100%;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}
.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

.form-section-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-700);
    grid-column: 1 / -1;
    padding-top: 8px;
    border-bottom: 1px solid var(--gray-100);
    padding-bottom: 8px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-section-title i {
    color: var(--primary);
    font-size: 0.85rem;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
    flex-wrap: wrap;
}
.form-actions .btn {
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
.form-actions .btn-primary {
    background: var(--primary);
    color: white;
}
.form-actions .btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
}
.form-actions .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.form-actions .btn-secondary:hover {
    background: var(--gray-200);
}
.form-actions .btn-success {
    background: #10B981;
    color: white;
}
.form-actions .btn-success:hover {
    background: #059669;
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

.recipient-count {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 0.85rem;
    color: var(--gray-600);
}
.recipient-count strong {
    color: var(--primary);
}

.target-options {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}
.target-options label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 400;
    font-size: 0.85rem;
    cursor: pointer;
}

.hidden {
    display: none !important;
}

.broadcast-status {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}
.broadcast-status.draft { background: var(--gray-200); color: var(--gray-600); }
.broadcast-status.scheduled { background: #DBEAFE; color: #2563EB; }
.broadcast-status.sent { background: #D1FAE5; color: #059669; }
.broadcast-status.failed { background: #FEE2E2; color: #DC2626; }

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .form-container {
        padding: 20px;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions .btn {
        justify-content: center;
        width: 100%;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i> 
                    Edit Broadcast
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="broadcasts.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back to Broadcasts
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
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

        <!-- Form -->
        <div class="form-container">
            <div class="form-title">
                <i class="fas fa-bullhorn"></i> Edit Broadcast
                <span class="broadcast-status <?php echo $broadcast['status']; ?>" style="margin-left:auto;">
                    <?php echo ucfirst($broadcast['status']); ?>
                </span>
            </div>
            <div class="form-subtitle">
                Update your broadcast message. Only drafts and scheduled broadcasts can be edited.
            </div>
            
            <form method="POST" action="" id="broadcastForm">
                <div class="form-grid">
                    <!-- Broadcast Details -->
                    <div class="form-section-title">
                        <i class="fas fa-cog"></i> Broadcast Details
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" placeholder="e.g., Election Day Update" value="<?php echo htmlspecialchars($broadcast['title']); ?>" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Message <span class="required">*</span></label>
                        <textarea name="message" placeholder="Type your broadcast message here..." required><?php echo htmlspecialchars($broadcast['message']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="low" <?php echo ($broadcast['priority'] ?? 'medium') == 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo ($broadcast['priority'] ?? 'medium') == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo ($broadcast['priority'] ?? 'medium') == 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="emergency" <?php echo ($broadcast['priority'] ?? 'medium') == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                        </select>
                    </div>
                    
                    <!-- Target Audience -->
                    <div class="form-section-title">
                        <i class="fas fa-users"></i> Target Audience
                    </div>
                    
                    <div class="form-group full-width">
                        <div class="target-options">
                            <label>
                                <input type="radio" name="target_audience" value="all" <?php echo $broadcast['target_audience'] == 'all' ? 'checked' : ''; ?> onchange="updateTargetFields()">
                                All Personnel
                            </label>
                            <label>
                                <input type="radio" name="target_audience" value="lga" <?php echo $broadcast['target_audience'] == 'lga' ? 'checked' : ''; ?> onchange="updateTargetFields()">
                                Specific LGAs
                            </label>
                            <label>
                                <input type="radio" name="target_audience" value="role_specific" <?php echo $broadcast['target_audience'] == 'role_specific' ? 'checked' : ''; ?> onchange="updateTargetFields()">
                                Specific Role
                            </label>
                        </div>
                        <div class="help-text">Select who should receive this broadcast.</div>
                    </div>
                    
                    <!-- LGA Selection -->
                    <div class="form-group full-width <?php echo $broadcast['target_audience'] != 'lga' ? 'hidden' : ''; ?>" id="lgaTargetGroup">
                        <label>Select LGAs</label>
                        <select name="target_ids[]" id="lgaTargetSelect" multiple style="height:100px;">
                            <?php foreach ($lgas as $lga): ?>
                                <option value="<?php echo $lga['id']; ?>" <?php echo in_array($lga['id'], $target_ids) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lga['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">Hold Ctrl/Cmd to select multiple LGAs.</div>
                    </div>
                    
                    <!-- Role Selection -->
                    <div class="form-group full-width <?php echo $broadcast['target_audience'] != 'role_specific' ? 'hidden' : ''; ?>" id="roleTargetGroup">
                        <label>Select Role</label>
                        <select name="target_role_id" id="roleTargetSelect">
                            <option value="">Select Role...</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" <?php echo ($broadcast['target_role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <div class="recipient-count">
                            <i class="fas fa-users"></i> 
                            Estimated Recipients: <strong id="recipientCount"><?php echo number_format($recipient_count); ?></strong>
                            <button type="button" onclick="updateRecipientCount()" class="btn-sm" style="margin-left:10px;">Refresh</button>
                        </div>
                    </div>
                    
                    <!-- Delivery Options -->
                    <div class="form-section-title">
                        <i class="fas fa-paper-plane"></i> Delivery Options
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Send Via</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="font-weight:400;display:flex;align-items:center;gap:6px;">
                                <input type="checkbox" name="send_via[]" value="email" <?php echo in_array('email', $send_via) ? 'checked' : ''; ?>>
                                Email
                            </label>
                            <label style="font-weight:400;display:flex;align-items:center;gap:6px;">
                                <input type="checkbox" name="send_via[]" value="in_app" <?php echo in_array('in_app', $send_via) ? 'checked' : ''; ?>>
                                In-App Notification
                            </label>
                            <label style="font-weight:400;display:flex;align-items:center;gap:6px;">
                                <input type="checkbox" name="send_via[]" value="sms" <?php echo in_array('sms', $send_via) ? 'checked' : ''; ?>>
                                SMS
                            </label>
                        </div>
                    </div>
                    
                    <!-- Scheduling -->
                    <div class="form-section-title">
                        <i class="fas fa-clock"></i> Scheduling
                    </div>
                    
                    <div class="form-group full-width">
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
                                <input type="checkbox" name="schedule" value="1" <?php echo $broadcast['status'] == 'scheduled' ? 'checked' : ''; ?> onchange="toggleSchedule()">
                                Schedule for later
                            </label>
                            <div id="scheduleDatetime" class="<?php echo $broadcast['status'] != 'scheduled' ? 'hidden' : ''; ?>">
                                <input type="datetime-local" name="scheduled_at" value="<?php echo $broadcast['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($broadcast['scheduled_at'])) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Broadcast
                    </button>
                    <?php if ($broadcast['status'] === 'draft'): ?>
                        <button type="submit" class="btn btn-success" name="send_now" value="1">
                            <i class="fas fa-paper-plane"></i> Send Now
                        </button>
                    <?php endif; ?>
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
// TOGGLE TARGET FIELDS
// ============================================================
function updateTargetFields() {
    var selected = document.querySelector('input[name="target_audience"]:checked');
    if (!selected) return;
    
    var value = selected.value;
    document.getElementById('lgaTargetGroup').classList.toggle('hidden', value !== 'lga');
    document.getElementById('roleTargetGroup').classList.toggle('hidden', value !== 'role_specific');
    updateRecipientCount();
}

// ============================================================
// TOGGLE SCHEDULE
// ============================================================
function toggleSchedule() {
    var checked = document.querySelector('input[name="schedule"]').checked;
    document.getElementById('scheduleDatetime').classList.toggle('hidden', !checked);
}

// ============================================================
// UPDATE RECIPIENT COUNT
// ============================================================
function updateRecipientCount() {
    var target = document.querySelector('input[name="target_audience"]:checked');
    if (!target) return;
    
    var formData = new FormData();
    formData.append('action', 'get_recipient_count');
    formData.append('target_audience', target.value);
    
    if (target.value === 'lga') {
        var selects = document.getElementById('lgaTargetSelect');
        var selected = [];
        for (var i = 0; i < selects.options.length; i++) {
            if (selects.options[i].selected) {
                selected.push(selects.options[i].value);
            }
        }
        formData.append('target_ids', JSON.stringify(selected));
    } else if (target.value === 'role_specific') {
        formData.append('target_role_id', document.getElementById('roleTargetSelect').value);
    }
    
    fetch('ajax/get_recipient_count.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        document.getElementById('recipientCount').textContent = data.count || 0;
    })
    .catch(function() {});
}

// ============================================================
// INITIALIZE
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    updateTargetFields();
    updateRecipientCount();
});

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