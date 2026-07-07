<?php
// ============================================================
// STATE COORDINATOR - CREATE BROADCAST
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

// Get parameters
$target_lga = isset($_GET['lga']) ? intval($_GET['lga']) : 0;
$target_lgas = isset($_GET['lgas']) ? explode(',', $_GET['lgas']) : [];
$target = isset($_GET['target']) ? $_GET['target'] : 'all';

$db = getDB();

// ============================================================
// FETCH STATE NAME
// ============================================================
$state_name = 'State';
try {
    if ($state_id) {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state_name = $stmt->fetchColumn() ?: 'State';
    }
} catch (Exception $e) {
    $state_name = 'State';
}

// ============================================================
// FETCH DATA FOR SELECTS
// ============================================================
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lgas = [];
}

$roles = [];
try {
    $stmt = $db->prepare("SELECT id, name, level FROM roles WHERE level IN ('lga', 'ward', 'pu_agent') AND is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $roles = [];
}

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    $target_audience = $_POST['target_audience'] ?? 'all';
    $target_ids = isset($_POST['target_ids']) ? array_map('intval', (array)$_POST['target_ids']) : [];
    $send_via = isset($_POST['send_via']) ? (array)$_POST['send_via'] : ['email'];
    $schedule_date = $_POST['schedule_date'] ?? '';
    $schedule_time = $_POST['schedule_time'] ?? '';
    $send_now = isset($_POST['send_now']) ? true : false;
    
    if (empty($title)) {
        $error = 'Please enter a broadcast title';
    } elseif (empty($message_text)) {
        $error = 'Please enter a broadcast message';
    } elseif ($target_audience === 'lga' && empty($target_ids)) {
        $error = 'Please select at least one LGA';
    } elseif ($target_audience === 'role_specific' && empty($target_ids)) {
        $error = 'Please select at least one role';
    } elseif (empty($send_via)) {
        $error = 'Please select at least one delivery method';
    } else {
        try {
            // Prepare schedule datetime
            $scheduled_at = null;
            if (!empty($schedule_date) && !empty($schedule_time)) {
                $scheduled_at = date('Y-m-d H:i:s', strtotime("$schedule_date $schedule_time"));
            }
            
            // Determine status
            $status = 'draft';
            if ($send_now) {
                $status = 'sending';
            } elseif ($scheduled_at) {
                $status = 'scheduled';
            }
            
            // Insert broadcast
            $stmt = $db->prepare("
                INSERT INTO broadcasts (
                    tenant_id, election_id, sender_id, title, message,
                    target_audience, target_ids_json, target_role_id,
                    send_via, scheduled_at, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $target_ids_json = json_encode($target_ids);
            $send_via_json = json_encode($send_via);
            
            $stmt->execute([
                $tenant_id,
                null,
                $user_id,
                $title,
                $message_text,
                $target_audience,
                $target_ids_json,
                $target_audience === 'role_specific' ? ($target_ids[0] ?? null) : null,
                $send_via_json,
                $scheduled_at,
                $status
            ]);
            
            $broadcast_id = $db->lastInsertId();
            
            // ============================================================
            // SEND EMAILS IF SEND_NOW IS CHECKED
            // ============================================================
            if ($send_now && in_array('email', $send_via)) {
                $recipients = [];
                
                if ($target_audience === 'all') {
                    $stmt = $db->prepare("
                        SELECT email, full_name FROM users 
                        WHERE tenant_id = ? AND status = 'active' 
                        AND jurisdiction_id IN (SELECT id FROM lgas WHERE state_id = ?)
                        AND email IS NOT NULL AND email != ''
                    ");
                    $stmt->execute([$tenant_id, $state_id]);
                    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } elseif ($target_audience === 'lga' && !empty($target_ids)) {
                    $placeholders = implode(',', array_fill(0, count($target_ids), '?'));
                    $stmt = $db->prepare("
                        SELECT email, full_name FROM users u
                        WHERE u.tenant_id = ? AND u.status = 'active'
                        AND u.jurisdiction_id IN ($placeholders)
                        AND u.email IS NOT NULL AND u.email != ''
                    ");
                    $stmt->execute(array_merge([$tenant_id], $target_ids));
                    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } elseif ($target_audience === 'role_specific' && !empty($target_ids)) {
                    $placeholders = implode(',', array_fill(0, count($target_ids), '?'));
                    $stmt = $db->prepare("
                        SELECT email, full_name FROM users u
                        WHERE u.tenant_id = ? AND u.status = 'active'
                        AND u.role_id IN ($placeholders)
                        AND u.email IS NOT NULL AND u.email != ''
                    ");
                    $stmt->execute(array_merge([$tenant_id], $target_ids));
                    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                $email_sent_count = 0;
                $email_failed_count = 0;
                
                foreach ($recipients as $recipient) {
                    if (!empty($recipient['email'])) {
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
                                    .state-badge { background: #EFF6FF; padding: 2px 12px; border-radius: 12px; font-size: 12px; color: #1E40AF; display: inline-block; }
                                </style>
                            </head>
                            <body>
                                <div class=\"container\">
                                    <div class=\"header\">
                                        <h1>📢 " . APP_NAME . "</h1>
                                        <p style=\"color: #64748B;\">Broadcast Message <span class=\"state-badge\">" . htmlspecialchars($state_name) . "</span></p>
                                    </div>
                                    <p>Hello " . htmlspecialchars($recipient['full_name'] ?? 'User') . ",</p>
                                    <div class=\"message-box\">
                                        <h3 style=\"margin-top:0;\">" . htmlspecialchars($title) . "</h3>
                                        <p>" . nl2br(htmlspecialchars($message_text)) . "</p>
                                    </div>
                                    <p style=\"color: #64748B; font-size: 14px;\">
                                        This is an automated message from " . APP_NAME . ".
                                        Please do not reply to this email.
                                    </p>
                                    <div class=\"footer\">
                                        &copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.
                                    </div>
                                </div>
                            </body>
                            </html>
                        ";
                        
                        $result = sendEmail(
                            $recipient['email'],
                            $title,
                            $email_body,
                            strip_tags($message_text)
                        );
                        
                        if ($result['success']) {
                            $email_sent_count++;
                        } else {
                            $email_failed_count++;
                        }
                    }
                }
                
                $stmt = $db->prepare("
                    UPDATE broadcasts 
                    SET sent_at = NOW(), 
                        status = 'sent',
                        total_recipients = ?
                    WHERE id = ?
                ");
                $stmt->execute([count($recipients), $broadcast_id]);
                
                $message = "Broadcast sent to " . $email_sent_count . " recipients!";
                if ($email_failed_count > 0) {
                    $message .= " (" . $email_failed_count . " failed)";
                }
            } else {
                $message = 'Broadcast created successfully!';
            }
            
            // Log activity
            $log_stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                VALUES (?, ?, 'broadcast_created', ?, 'broadcast', ?, NOW())
            ");
            $log_stmt->execute([
                $user_id,
                $tenant_id,
                "Created broadcast: $title",
                $broadcast_id
            ]);
            
            header("Location: broadcasts.php?success=1");
            exit();
            
        } catch (Exception $e) {
            $error = 'Failed to create broadcast: ' . $e->getMessage();
            error_log("Broadcast Create Error: " . $e->getMessage());
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Create Broadcast';
$page_subtitle = 'Send messages to coordinators and agents';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="broadcasts.php" style="text-decoration:none;color:var(--gray-500);">Broadcasts</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Create</span>
            </div>
            
            <h2 style="font-size:1.5rem;font-weight:700;margin:8px 0 0;">Create Broadcast</h2>
            <p style="color:var(--gray-500);margin:2px 0 0;">Send important messages to coordinators and agents</p>
        </div>

        <?php if ($message): ?>
            <div style="background:#D1FAE5;color:#065F46;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #A7F3D0;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#FEE2E2;color:#991B1B;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #FECACA;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Broadcast Form -->
        <form method="POST" action="" style="background:white;border-radius:var(--radius);padding:24px;border:1px solid var(--gray-200);">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <!-- Left Column -->
                <div>
                    <!-- Title -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Broadcast Title <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="title" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                               placeholder="e.g., Election Day Update" 
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Message -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Message <span style="color:#EF4444;">*</span>
                        </label>
                        <textarea name="message" class="form-control" required rows="8"
                                  placeholder="Type your broadcast message here..." 
                                  style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;resize:vertical;transition:var(--transition);"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        <div style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                            <span id="charCount">0</span> characters
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- Target Audience -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Target Audience <span style="color:#EF4444;">*</span>
                        </label>
                        <select name="target_audience" class="form-control" id="targetAudience" 
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="all" <?php echo ($_POST['target_audience'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Users in State</option>
                            <option value="lga" <?php echo ($_POST['target_audience'] ?? '') === 'lga' ? 'selected' : ''; ?>>Specific LGAs</option>
                            <option value="role_specific" <?php echo ($_POST['target_audience'] ?? '') === 'role_specific' ? 'selected' : ''; ?>>Specific Roles</option>
                        </select>
                    </div>
                    
                    <!-- LGA Selection -->
                    <div id="lgaSelection" style="margin-bottom:16px;display:<?php echo ($_POST['target_audience'] ?? '') === 'lga' ? 'block' : 'none'; ?>;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Select LGAs <span style="color:#EF4444;">*</span>
                        </label>
                        <select name="target_ids[]" multiple class="form-control" 
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;min-height:120px;transition:var(--transition);">
                            <?php foreach ($lgas as $lga): ?>
                                <option value="<?php echo $lga['id']; ?>" 
                                    <?php echo (isset($_POST['target_ids']) && in_array($lga['id'], (array)$_POST['target_ids'])) || 
                                            $target_lga == $lga['id'] || 
                                            in_array($lga['id'], $target_lgas) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lga['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                            Hold Ctrl/Cmd to select multiple LGAs
                        </div>
                    </div>
                    
                    <!-- Role Selection -->
                    <div id="roleSelection" style="margin-bottom:16px;display:<?php echo ($_POST['target_audience'] ?? '') === 'role_specific' ? 'block' : 'none'; ?>;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Select Roles <span style="color:#EF4444;">*</span>
                        </label>
                        <select name="target_ids[]" multiple class="form-control" 
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;min-height:120px;transition:var(--transition);">
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" 
                                    <?php echo (isset($_POST['target_ids']) && in_array($role['id'], (array)$_POST['target_ids'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?> (<?php echo ucfirst($role['level']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                            Hold Ctrl/Cmd to select multiple roles
                        </div>
                    </div>
                    
                    <!-- Delivery Methods -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Delivery Methods <span style="color:#EF4444;">*</span>
                        </label>
                        <?php 
                        $send_via_default = isset($_POST['send_via']) ? (array)$_POST['send_via'] : ['email'];
                        ?>
                        <div style="display:flex;flex-wrap:wrap;gap:12px;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;color:var(--gray-600);cursor:pointer;">
                                <input type="checkbox" name="send_via[]" value="email" 
                                    <?php echo in_array('email', $send_via_default) ? 'checked' : ''; ?>>
                                <i class="fas fa-envelope" style="color:#3B82F6;"></i> Email
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;color:var(--gray-600);cursor:pointer;">
                                <input type="checkbox" name="send_via[]" value="sms" 
                                    <?php echo in_array('sms', $send_via_default) ? 'checked' : ''; ?>>
                                <i class="fas fa-sms" style="color:#10B981;"></i> SMS
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;color:var(--gray-600);cursor:pointer;">
                                <input type="checkbox" name="send_via[]" value="push" 
                                    <?php echo in_array('push', $send_via_default) ? 'checked' : ''; ?>>
                                <i class="fas fa-bell" style="color:#8B5CF6;"></i> Push Notification
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;color:var(--gray-600);cursor:pointer;">
                                <input type="checkbox" name="send_via[]" value="in_app" 
                                    <?php echo in_array('in_app', $send_via_default) ? 'checked' : ''; ?>>
                                <i class="fas fa-mobile-alt" style="color:#F59E0B;"></i> In-App
                            </label>
                        </div>
                    </div>
                    
                    <!-- Send Now / Schedule -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div style="margin-bottom:16px;">
                            <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                                Send Options
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;color:var(--gray-600);cursor:pointer;padding:8px 12px;background:#F0FDF4;border-radius:8px;border:1px solid #A7F3D0;">
                                <input type="checkbox" name="send_now" value="1" 
                                    <?php echo isset($_POST['send_now']) ? 'checked' : ''; ?>>
                                <i class="fas fa-paper-plane" style="color:#10B981;"></i> Send Now
                            </label>
                        </div>
                        
                        <div>
                            <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                                Schedule (Optional)
                            </label>
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <input type="date" name="schedule_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['schedule_date'] ?? ''); ?>"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;transition:var(--transition);">
                                <input type="time" name="schedule_time" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['schedule_time'] ?? ''); ?>"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;transition:var(--transition);">
                            </div>
                            <div style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                                Leave empty to save as draft
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div style="display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
                <button type="submit" class="btn-primary" style="padding:10px 32px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-paper-plane"></i> Create & Send
                </button>
                <button type="submit" name="save_draft" value="1" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-save"></i> Save as Draft
                </button>
                <a href="broadcasts.php" class="btn-secondary" style="padding:10px 32px;background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>

        <!-- Quick Tips -->
        <div style="background:#F0FDF4;border-radius:var(--radius);padding:16px 20px;margin-top:20px;border:1px solid #A7F3D0;">
            <h4 style="font-size:0.85rem;font-weight:600;color:#065F46;margin:0 0 8px;">
                <i class="fas fa-lightbulb"></i> Broadcast Tips
            </h4>
            <ul style="font-size:0.8rem;color:#065F46;margin:0;padding-left:20px;">
                <li>Keep messages clear and concise</li>
                <li>Include important dates and action items</li>
                <li>Use specific targeting to avoid message fatigue</li>
                <li>Schedule broadcasts during working hours for better visibility</li>
                <li>Use multiple delivery methods for critical messages</li>
                <li>Check <strong>"Send Now"</strong> to immediately send emails</li>
            </ul>
        </div>
    </div>
</main>

<style>
.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary:hover {
    background: var(--gray-200);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
    div[style*="grid-template-columns:1fr 1fr;gap:12px;"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
// ============================================================
// CHAR COUNTER
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var messageTextarea = document.querySelector('textarea[name="message"]');
    var charCount = document.getElementById('charCount');
    
    if (messageTextarea && charCount) {
        messageTextarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
        charCount.textContent = messageTextarea.value.length;
    }
});

// ============================================================
// TARGET AUDIENCE TOGGLE
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var targetAudience = document.getElementById('targetAudience');
    var lgaSelection = document.getElementById('lgaSelection');
    var roleSelection = document.getElementById('roleSelection');
    
    if (targetAudience) {
        targetAudience.addEventListener('change', function() {
            var value = this.value;
            lgaSelection.style.display = value === 'lga' ? 'block' : 'none';
            roleSelection.style.display = value === 'role_specific' ? 'block' : 'none';
        });
    }
});

// ============================================================
// SIDEBAR TOGGLE, DROPDOWNS, PROFILE, SEARCH
// ============================================================
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