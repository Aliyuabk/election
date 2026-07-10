<?php
// ============================================================
// WARD COORDINATOR - CREATE BROADCAST
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Ward Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');

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

// Get ward name
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
    error_log("Error fetching ward: " . $e->getMessage());
}

// Get counts for recipient estimation
$pu_agents_count = 0;
$volunteers_count = 0;
$observers_count = 0;

try {
    // PU Agents
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE r.level = 'pu_agent' AND u.ward_id = ? AND u.status = 'active' AND u.deleted_at IS NULL
    ");
    $stmt->execute([$ward_id]);
    $pu_agents_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Volunteers
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE r.level = 'volunteer' AND u.ward_id = ? AND u.status = 'active' AND u.deleted_at IS NULL
    ");
    $stmt->execute([$ward_id]);
    $volunteers_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Observers
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE r.level = 'observer' AND u.ward_id = ? AND u.status = 'active' AND u.deleted_at IS NULL
    ");
    $stmt->execute([$ward_id]);
    $observers_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    error_log("Error fetching counts: " . $e->getMessage());
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message_content = trim($_POST['message'] ?? '');
    $target_audience = $_POST['target_audience'] ?? 'all';
    $target_ids = isset($_POST['target_ids']) ? array_map('intval', $_POST['target_ids']) : [];
    $send_via = isset($_POST['send_via']) ? $_POST['send_via'] : ['email'];
    $schedule = isset($_POST['schedule']) ? $_POST['schedule'] : '';
    $scheduled_at = !empty($schedule) ? $_POST['scheduled_at'] ?? null : null;
    $action = $_POST['action'] ?? 'draft';
    
    if (empty($title) || empty($message_content)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $status = $action === 'send' ? 'sending' : 'draft';
            if ($scheduled_at && !empty($scheduled_at)) {
                $status = 'scheduled';
            }
            
            $send_via_json = json_encode($send_via);
            $target_ids_json = !empty($target_ids) ? json_encode($target_ids) : null;
            
            $stmt = $db->prepare("
                INSERT INTO broadcasts (
                    tenant_id, sender_id, title, message, 
                    target_audience, target_ids_json, send_via,
                    scheduled_at, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $tenant_id,
                $user_id,
                $title,
                $message_content,
                $target_audience,
                $target_ids_json,
                $send_via_json,
                $scheduled_at,
                $status
            ]);
            
            $broadcast_id = $db->lastInsertId();
            
            logActivity($user_id, 'broadcast_created', 
                "Created broadcast: $title (ID: $broadcast_id)",
                'broadcasts', $broadcast_id
            );
            
            if ($action === 'send') {
                try {
                    $recipients = getBroadcastRecipients($tenant_id, $target_audience, $target_ids);
                    $result = sendBroadcastEmails($recipients, $title, $message_content);
                    
                    $stmt = $db->prepare("
                        UPDATE broadcasts 
                        SET status = ?, sent_at = NOW(), total_recipients = ?
                        WHERE id = ?
                    ");
                    $status = $result['success'] ? 'sent' : 'failed';
                    $stmt->execute([$status, count($recipients), $broadcast_id]);
                    
                    if ($result['success']) {
                        $message = "Broadcast created and sent successfully! ({$result['sent']} recipients)";
                    } else {
                        $message = "Broadcast created but sending failed for some recipients.";
                        $error = implode(', ', array_slice($result['errors'], 0, 3));
                    }
                } catch (Exception $e) {
                    $error = "Broadcast created but sending failed: " . $e->getMessage();
                }
            } elseif ($scheduled_at && !empty($scheduled_at)) {
                $message = "Broadcast scheduled successfully for " . date('M j, Y g:i A', strtotime($scheduled_at));
            } else {
                $message = "Broadcast saved as draft successfully!";
            }
        } catch (Exception $e) {
            $error = 'Failed to create broadcast: ' . $e->getMessage();
        }
    }
}

$page_title = 'Create Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.form-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 16px;
}

.form-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.form-card .card-title i {
    color: var(--primary);
    margin-right: 6px;
}

.form-group {
    margin-bottom: 14px;
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
.form-group input[type="datetime-local"],
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
    background: white;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-group .char-count {
    font-size: 0.65rem;
    color: var(--gray-400);
    text-align: right;
    margin-top: 2px;
}

.form-group .help-text {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 6px;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 400;
    font-size: 0.8rem;
    color: var(--gray-700);
    cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
    width: 15px;
    height: 15px;
    accent-color: var(--primary);
}

.target-options {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 6px;
}

.target-options label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 400;
    font-size: 0.8rem;
    color: var(--gray-700);
    cursor: pointer;
    padding: 6px 10px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    transition: var(--transition);
}

.target-options label:hover {
    background: var(--gray-50);
}

.target-options input[type="radio"] {
    width: 15px;
    height: 15px;
    accent-color: var(--primary);
}

.target-options label.selected {
    border-color: var(--primary);
    background: rgba(var(--primary-rgb), 0.05);
}

.recipient-count {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.75rem;
    color: #0369A1;
    margin-top: 8px;
}

.alert {
    padding: 10px 14px;
    border-radius: 8px;
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
    gap: 8px;
    flex-wrap: wrap;
}

.btn-group button {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-group .btn-draft {
    background: var(--gray-100);
    color: var(--gray-700);
}

.btn-group .btn-draft:hover {
    background: var(--gray-200);
}

.btn-group .btn-schedule {
    background: #F59E0B;
    color: white;
}

.btn-group .btn-schedule:hover {
    background: #D97706;
}

.btn-group .btn-send {
    background: #3B82F6;
    color: white;
}

.btn-group .btn-send:hover {
    background: #2563EB;
}

.btn-group .btn-cancel {
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    padding: 8px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.82rem;
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
        padding: 14px 16px;
    }
    .checkbox-group {
        grid-template-columns: 1fr 1fr;
    }
    .target-options {
        grid-template-columns: 1fr 1fr;
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
        <div class="form-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-plus"></i> Create Broadcast</h1>
                    <p class="subtitle">
                        <i class="fas fa-layer-group"></i> 
                        <?php echo htmlspecialchars($ward_name); ?> Ward - Send Messages
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

            <?php if (!$message || strpos($message, 'failed') !== false): ?>
            <form method="POST" action="" id="broadcastForm">
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

                <div class="form-card">
                    <div class="card-title"><i class="fas fa-users"></i> Target Audience</div>
                    
                    <div class="form-group">
                        <label>Select Audience <span class="required">*</span></label>
                        <div class="target-options" id="targetOptions">
                            <label class="selected">
                                <input type="radio" name="target_audience" value="all" checked onchange="toggleTargetIds()" />
                                All Users in Ward
                            </label>
                            <label>
                                <input type="radio" name="target_audience" value="role_specific" onchange="toggleTargetIds()" />
                                Role Specific
                            </label>
                        </div>
                    </div>

                    <div class="target-ids-section" id="targetIdsSection" style="display:none;margin-top:8px;padding:10px 14px;background:var(--gray-50);border-radius:6px;">
                        <div class="form-group">
                            <label>Select Roles</label>
                            <select name="target_ids[]" multiple style="width:100%;padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:0.8rem;font-family:'Inter',sans-serif;background:white;">
                                <option value="pu_agent">PU Agents (<?php echo $pu_agents_count; ?>)</option>
                                <option value="volunteer">Volunteers (<?php echo $volunteers_count; ?>)</option>
                                <option value="observer">Observers (<?php echo $observers_count; ?>)</option>
                            </select>
                            <div class="help-text">Hold Ctrl/Cmd to select multiple roles</div>
                        </div>
                    </div>

                    <div class="recipient-count">
                        <i class="fas fa-users"></i>
                        Estimated recipients: 
                        <strong id="recipientCount">
                            <?php echo number_format($pu_agents_count + $volunteers_count + $observers_count); ?>
                        </strong>
                        <span style="font-size:0.7rem;display:block;color:var(--gray-500);margin-top:2px;">
                            PU Agents: <?php echo number_format($pu_agents_count); ?> | 
                            Volunteers: <?php echo number_format($volunteers_count); ?> | 
                            Observers: <?php echo number_format($observers_count); ?>
                        </span>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-title"><i class="fas fa-share-alt"></i> Delivery Channels</div>
                    
                    <div class="form-group">
                        <label>Send Via <span class="required">*</span></label>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="send_via[]" value="email" checked />
                                <i class="fas fa-envelope"></i> Email
                            </label>
                            <label>
                                <input type="checkbox" name="send_via[]" value="in_app" />
                                <i class="fas fa-bell"></i> In-App
                            </label>
                            <label>
                                <input type="checkbox" name="send_via[]" value="sms" />
                                <i class="fas fa-sms"></i> SMS
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-title"><i class="fas fa-calendar"></i> Schedule (Optional)</div>
                    
                    <div class="form-group">
                        <label>Schedule for later</label>
                        <input type="datetime-local" name="scheduled_at" id="scheduledAt" />
                        <div class="help-text">Leave empty to send immediately</div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="btn-group">
                        <button type="submit" name="action" value="draft" class="btn-draft">
                            <i class="fas fa-save"></i> Save as Draft
                        </button>
                        <button type="submit" name="action" value="schedule" class="btn-schedule" onclick="return validateSchedule()">
                            <i class="fas fa-calendar-plus"></i> Schedule
                        </button>
                        <button type="submit" name="action" value="send" class="btn-send">
                            <i class="fas fa-paper-plane"></i> Send Now
                        </button>
                        <a href="broadcasts.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
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

// Toggle target IDs section
function toggleTargetIds() {
    var selected = document.querySelector('input[name="target_audience"]:checked');
    var section = document.getElementById('targetIdsSection');
    
    if (selected && selected.value === 'role_specific') {
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
    }
}

// Validate schedule
function validateSchedule() {
    var scheduledAt = document.getElementById('scheduledAt');
    if (!scheduledAt.value) {
        alert('Please select a date and time to schedule the broadcast.');
        scheduledAt.focus();
        return false;
    }
    return true;
}

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