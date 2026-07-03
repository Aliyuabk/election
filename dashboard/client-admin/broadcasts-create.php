<?php
// ============================================================
// BROADCAST CREATE - CLIENT ADMIN (PROFESSIONAL UI)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only client_admin can access this page
if (SessionManager::get('role_level') !== 'client_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// Get user info
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
$tenant_id = SessionManager::get('tenant_id');

// ============================================================
// GET BROADCAST ID FOR EDIT
// ============================================================
$broadcast_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $broadcast_id > 0;

// ============================================================
// FETCH BROADCAST DATA FOR EDIT
// ============================================================
$broadcast = null;
if ($is_edit) {
    try {
        $stmt = $db->prepare("SELECT * FROM broadcasts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$broadcast_id, $tenant_id]);
        $broadcast = $stmt->fetch();
        
        if (!$broadcast) {
            header('Location: broadcasts.php');
            exit();
        }
    } catch (Exception $e) {
        header('Location: broadcasts.php');
        exit();
    }
}

// ============================================================
// FETCH USERS FOR TARGETING
// ============================================================
$users = [];
try {
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, email, phone, role_id,
               (SELECT name FROM roles WHERE id = users.role_id) as role_name
        FROM users 
        WHERE tenant_id = ? AND status = 'active'
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$tenant_id]);
    $users = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH STATES, LGAS, WARDS, PUS FOR TARGETING
// ============================================================
$states = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {}

$lgas = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM lgas WHERE is_active = 1 ORDER BY name");
    $lgas = $stmt->fetchAll();
} catch (Exception $e) {}

$wards = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM wards WHERE is_active = 1 ORDER BY name");
    $wards = $stmt->fetchAll();
} catch (Exception $e) {}

$polling_units = [];
try {
    $stmt = $db->query("SELECT id, code, name FROM polling_units WHERE is_active = 1 ORDER BY name LIMIT 500");
    $polling_units = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH ROLES FOR TARGETING
// ============================================================
$roles = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, slug FROM roles 
        WHERE level IN ('pu_agent', 'party_agent', 'volunteer', 'observer', 'state', 'lga', 'ward')
        AND (tenant_id = ? OR tenant_id IS NULL)
        ORDER BY name
    ");
    $stmt->execute([$tenant_id]);
    $roles = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// EMAIL SENDING FUNCTION USING PHPMailer
// ============================================================
function sendBroadcastEmail($broadcast_id, $title, $message, $target_audience, $target_ids, $channels, $db, $tenant_id) {
    // Only send email if email channel is selected
    if (!in_array('email', $channels)) {
        return ['success' => true, 'message' => 'Email channel not selected'];
    }
    
    try {
        // Get recipients based on target audience
        $recipients = getRecipients($target_audience, $target_ids, $tenant_id, $db);
        
        if (empty($recipients)) {
            return ['success' => false, 'message' => 'No recipients found'];
        }
        
        // Update total recipients count
        $stmt = $db->prepare("UPDATE broadcasts SET total_recipients = ? WHERE id = ?");
        $stmt->execute([count($recipients), $broadcast_id]);
        
        // Load PHPMailer
        require_once '../../email/vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Add recipients (limit to avoid timeouts, send in batches)
        $batch_size = 50;
        $batches = array_chunk($recipients, $batch_size);
        $success_count = 0;
        $fail_count = 0;
        
        foreach ($batches as $batch) {
            foreach ($batch as $recipient) {
                try {
                    $mail->clearAddresses();
                    $mail->addAddress($recipient['email'], $recipient['name']);
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = $title;
                    $mail->Body = nl2br($message);
                    $mail->AltBody = strip_tags($message);
                    
                    $mail->send();
                    $success_count++;
                } catch (Exception $e) {
                    $fail_count++;
                    // Log error
                    error_log("Email send failed to {$recipient['email']}: " . $mail->ErrorInfo);
                }
            }
            
            // Small delay between batches to avoid rate limiting
            if (count($batches) > 1) {
                usleep(500000); // 0.5 second delay
            }
        }
        
        // Update read count (approximate)
        $stmt = $db->prepare("UPDATE broadcasts SET read_count = ? WHERE id = ?");
        $stmt->execute([$success_count, $broadcast_id]);
        
        return [
            'success' => true, 
            'message' => "Sent to $success_count recipients. Failed: $fail_count",
            'sent' => $success_count,
            'failed' => $fail_count
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================================
// GET RECIPIENTS FUNCTION
// ============================================================
function getRecipients($target_audience, $target_ids, $tenant_id, $db) {
    $recipients = [];
    
    try {
        switch ($target_audience) {
            case 'all':
                $stmt = $db->prepare("
                    SELECT id, first_name, last_name, email, phone 
                    FROM users 
                    WHERE tenant_id = ? AND status = 'active' AND email IS NOT NULL
                ");
                $stmt->execute([$tenant_id]);
                $users = $stmt->fetchAll();
                foreach ($users as $user) {
                    $recipients[] = [
                        'id' => $user['id'],
                        'name' => $user['first_name'] . ' ' . $user['last_name'],
                        'email' => $user['email'],
                        'phone' => $user['phone']
                    ];
                }
                break;
                
            case 'state':
                if (!empty($target_ids)) {
                    $placeholders = implode(',', array_fill(0, count($target_ids), '?'));
                    $stmt = $db->prepare("
                        SELECT u.id, u.first_name, u.last_name, u.email, u.phone 
                        FROM users u
                        WHERE u.tenant_id = ? AND u.status = 'active' 
                        AND u.state_id IN ($placeholders)
                        AND u.email IS NOT NULL
                    ");
                    $params = array_merge([$tenant_id], $target_ids);
                    $stmt->execute($params);
                    $users = $stmt->fetchAll();
                    foreach ($users as $user) {
                        $recipients[] = [
                            'id' => $user['id'],
                            'name' => $user['first_name'] . ' ' . $user['last_name'],
                            'email' => $user['email'],
                            'phone' => $user['phone']
                        ];
                    }
                }
                break;
                
            case 'lga':
                if (!empty($target_ids)) {
                    $placeholders = implode(',', array_fill(0, count($target_ids), '?'));
                    $stmt = $db->prepare("
                        SELECT u.id, u.first_name, u.last_name, u.email, u.phone 
                        FROM users u
                        WHERE u.tenant_id = ? AND u.status = 'active' 
                        AND u.lga_id IN ($placeholders)
                        AND u.email IS NOT NULL
                    ");
                    $params = array_merge([$tenant_id], $target_ids);
                    $stmt->execute($params);
                    $users = $stmt->fetchAll();
                    foreach ($users as $user) {
                        $recipients[] = [
                            'id' => $user['id'],
                            'name' => $user['first_name'] . ' ' . $user['last_name'],
                            'email' => $user['email'],
                            'phone' => $user['phone']
                        ];
                    }
                }
                break;
                
            case 'ward':
                if (!empty($target_ids)) {
                    $placeholders = implode(',', array_fill(0, count($target_ids), '?'));
                    $stmt = $db->prepare("
                        SELECT u.id, u.first_name, u.last_name, u.email, u.phone 
                        FROM users u
                        WHERE u.tenant_id = ? AND u.status = 'active' 
                        AND u.ward_id IN ($placeholders)
                        AND u.email IS NOT NULL
                    ");
                    $params = array_merge([$tenant_id], $target_ids);
                    $stmt->execute($params);
                    $users = $stmt->fetchAll();
                    foreach ($users as $user) {
                        $recipients[] = [
                            'id' => $user['id'],
                            'name' => $user['first_name'] . ' ' . $user['last_name'],
                            'email' => $user['email'],
                            'phone' => $user['phone']
                        ];
                    }
                }
                break;
                
            case 'pu':
                if (!empty($target_ids)) {
                    $placeholders = implode(',', array_fill(0, count($target_ids), '?'));
                    $stmt = $db->prepare("
                        SELECT u.id, u.first_name, u.last_name, u.email, u.phone 
                        FROM users u
                        WHERE u.tenant_id = ? AND u.status = 'active' 
                        AND u.pu_id IN ($placeholders)
                        AND u.email IS NOT NULL
                    ");
                    $params = array_merge([$tenant_id], $target_ids);
                    $stmt->execute($params);
                    $users = $stmt->fetchAll();
                    foreach ($users as $user) {
                        $recipients[] = [
                            'id' => $user['id'],
                            'name' => $user['first_name'] . ' ' . $user['last_name'],
                            'email' => $user['email'],
                            'phone' => $user['phone']
                        ];
                    }
                }
                break;
                
            case 'role_specific':
                if (!empty($target_ids) && isset($target_ids[0])) {
                    $role_id = (int)$target_ids[0];
                    $stmt = $db->prepare("
                        SELECT u.id, u.first_name, u.last_name, u.email, u.phone 
                        FROM users u
                        WHERE u.tenant_id = ? AND u.status = 'active' 
                        AND u.role_id = ?
                        AND u.email IS NOT NULL
                    ");
                    $stmt->execute([$tenant_id, $role_id]);
                    $users = $stmt->fetchAll();
                    foreach ($users as $user) {
                        $recipients[] = [
                            'id' => $user['id'],
                            'name' => $user['first_name'] . ' ' . $user['last_name'],
                            'email' => $user['email'],
                            'phone' => $user['phone']
                        ];
                    }
                }
                break;
                
            default:
                // Return all users as fallback
                $stmt = $db->prepare("
                    SELECT id, first_name, last_name, email, phone 
                    FROM users 
                    WHERE tenant_id = ? AND status = 'active' AND email IS NOT NULL
                ");
                $stmt->execute([$tenant_id]);
                $users = $stmt->fetchAll();
                foreach ($users as $user) {
                    $recipients[] = [
                        'id' => $user['id'],
                        'name' => $user['first_name'] . ' ' . $user['last_name'],
                        'email' => $user['email'],
                        'phone' => $user['phone']
                    ];
                }
                break;
        }
    } catch (Exception $e) {
        error_log("Error getting recipients: " . $e->getMessage());
    }
    
    return $recipients;
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$action_result = ['success' => false, 'message' => ''];
$sent_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'save_draft':
            case 'send_broadcast':
            case 'schedule_broadcast':
                $title = trim($_POST['title'] ?? '');
                $message = trim($_POST['message'] ?? '');
                $target_audience = trim($_POST['target_audience'] ?? 'all');
                $target_ids = $_POST['target_ids'] ?? [];
                $target_role_id = (int)($_POST['target_role_id'] ?? 0);
                $channels = isset($_POST['channels']) ? explode(',', $_POST['channels']) : [];
                $scheduled_at = trim($_POST['scheduled_at'] ?? '');
                
                if (empty($title) || empty($message)) {
                    throw new Exception('Title and message are required.');
                }
                
                if (empty($channels)) {
                    throw new Exception('Please select at least one channel.');
                }
                
                $target_ids_json = json_encode($target_ids);
                $channels_json = json_encode($channels);
                
                // Determine status
                $status = 'draft';
                if ($action === 'send_broadcast') {
                    $status = 'sending';
                } elseif ($action === 'schedule_broadcast') {
                    $status = 'scheduled';
                    if (empty($scheduled_at)) {
                        throw new Exception('Scheduled time is required.');
                    }
                }
                
                // Handle scheduled_at - set to NULL if empty
                $scheduled_at_db = !empty($scheduled_at) ? $scheduled_at : null;
                
                if ($is_edit && $broadcast) {
                    // Update existing
                    $stmt = $db->prepare("
                        UPDATE broadcasts SET 
                            title = ?, message = ?, target_audience = ?, 
                            target_ids_json = ?, target_role_id = ?,
                            send_via = ?, scheduled_at = ?, status = ?
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $stmt->execute([
                        $title, $message, $target_audience,
                        $target_ids_json, $target_role_id,
                        $channels_json, $scheduled_at_db, $status,
                        $broadcast_id, $tenant_id
                    ]);
                    
                    logActivity($user_id, 'broadcast_updated', "Updated broadcast ID: $broadcast_id");
                    
                    // If sending now, process the send
                    if ($action === 'send_broadcast') {
                        $send_result = sendBroadcastEmail($broadcast_id, $title, $message, $target_audience, $target_ids, $channels, $db, $tenant_id);
                        if ($send_result['success']) {
                            // Update status to sent
                            $stmt = $db->prepare("UPDATE broadcasts SET status = 'sent', sent_at = NOW() WHERE id = ?");
                            $stmt->execute([$broadcast_id]);
                            $action_result = ['success' => true, 'message' => 'Broadcast sent successfully! ' . $send_result['message']];
                            $sent_success = true;
                        } else {
                            // Update status to failed
                            $stmt = $db->prepare("UPDATE broadcasts SET status = 'failed' WHERE id = ?");
                            $stmt->execute([$broadcast_id]);
                            $action_result = ['success' => false, 'message' => 'Error sending broadcast: ' . $send_result['message']];
                        }
                    } else {
                        $action_result = ['success' => true, 'message' => 'Broadcast updated successfully.'];
                    }
                } else {
                    // Insert new
                    $stmt = $db->prepare("
                        INSERT INTO broadcasts (
                            tenant_id, sender_id, title, message, target_audience,
                            target_ids_json, target_role_id, send_via,
                            scheduled_at, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $tenant_id, $user_id, $title, $message, $target_audience,
                        $target_ids_json, $target_role_id, $channels_json,
                        $scheduled_at_db, $status
                    ]);
                    
                    $broadcast_id = $db->lastInsertId();
                    logActivity($user_id, 'broadcast_created', "Created broadcast ID: $broadcast_id");
                    
                    // If sending now, process the send
                    if ($action === 'send_broadcast') {
                        $send_result = sendBroadcastEmail($broadcast_id, $title, $message, $target_audience, $target_ids, $channels, $db, $tenant_id);
                        if ($send_result['success']) {
                            // Update status to sent
                            $stmt = $db->prepare("UPDATE broadcasts SET status = 'sent', sent_at = NOW() WHERE id = ?");
                            $stmt->execute([$broadcast_id]);
                            $action_result = ['success' => true, 'message' => 'Broadcast sent successfully! ' . $send_result['message']];
                            $sent_success = true;
                        } else {
                            // Update status to failed
                            $stmt = $db->prepare("UPDATE broadcasts SET status = 'failed' WHERE id = ?");
                            $stmt->execute([$broadcast_id]);
                            $action_result = ['success' => false, 'message' => 'Error sending broadcast: ' . $send_result['message']];
                        }
                    } else {
                        $action_result = ['success' => true, 'message' => 'Broadcast created successfully.'];
                    }
                }
                
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       BROADCAST CREATE - PROFESSIONAL UI STYLES
       ============================================================ */
    
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
    
    .btn-primary {
        padding: 10px 20px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.3);
    }
    .btn-success {
        padding: 10px 20px;
        background: var(--secondary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-success:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
    }
    .btn-outline {
        padding: 10px 18px;
        background: transparent;
        color: var(--gray-600);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--primary);
        color: var(--primary);
    }
    .btn-warning {
        padding: 10px 20px;
        background: #F59E0B;
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-warning:hover {
        background: #D97706;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(245, 158, 11, 0.3);
    }
    
    .form-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 28px 32px;
        box-shadow: var(--shadow);
        max-width: 1000px;
        margin: 0 auto;
    }
    .form-container:hover {
        box-shadow: var(--shadow-hover);
    }
    .form-container .form-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 2px solid var(--gray-100);
    }
    .form-container .form-header .icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #EFF6FF;
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }
    .form-container .form-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .form-container .form-header p {
        font-size: 0.85rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
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
        padding: 8px 12px;
        border: 1.5px solid var(--gray-200);
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
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
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .form-group textarea {
        resize: vertical;
        min-height: 120px;
    }
    
    .channel-options {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-top: 4px;
    }
    .channel-option {
        padding: 12px 16px;
        border: 2px solid var(--gray-200);
        border-radius: 10px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--gray-50);
        font-weight: 500;
        font-size: 0.8rem;
        color: var(--gray-600);
    }
    .channel-option:hover {
        border-color: var(--gray-300);
        background: white;
        transform: translateY(-2px);
    }
    .channel-option.selected {
        border-color: var(--primary);
        background: #EFF6FF;
        color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }
    .channel-option i {
        display: block;
        font-size: 1.4rem;
        margin-bottom: 4px;
    }
    .channel-option .check {
        display: none;
        font-size: 0.7rem;
        color: var(--primary);
        margin-top: 4px;
    }
    .channel-option.selected .check {
        display: block;
    }
    
    .audience-options {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 8px;
        margin-top: 4px;
    }
    .audience-option {
        padding: 10px 8px;
        border: 2px solid var(--gray-200);
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--gray-50);
        font-weight: 500;
        font-size: 0.7rem;
        color: var(--gray-600);
    }
    .audience-option:hover {
        border-color: var(--gray-300);
        background: white;
    }
    .audience-option.selected {
        border-color: var(--primary);
        background: #EFF6FF;
        color: var(--primary);
    }
    .audience-option i {
        display: block;
        font-size: 1.2rem;
        margin-bottom: 2px;
    }
    
    .target-select {
        display: none;
        margin-top: 8px;
    }
    .target-select.active {
        display: block;
    }
    .target-select select {
        padding: 8px 12px;
        border: 1.5px solid var(--gray-200);
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
        min-height: 100px;
    }
    .target-select select:focus {
        outline: none;
        border-color: var(--primary);
    }
    .target-select select option {
        padding: 6px 8px;
    }
    .target-select .selected-count {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 4px;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 2px solid var(--gray-100);
        flex-wrap: wrap;
    }
    .form-actions .btn {
        padding: 10px 24px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .form-actions .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .form-actions .btn-success {
        background: var(--secondary);
        color: white;
    }
    .form-actions .btn-success:hover {
        background: #059669;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
    }
    .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .form-actions .btn-secondary:hover {
        background: var(--gray-200);
    }
    .form-actions .btn-warning {
        background: #F59E0B;
        color: white;
    }
    .form-actions .btn-warning:hover {
        background: #D97706;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
    }
    
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 100%;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    
    .recipient-preview {
        background: var(--gray-50);
        border-radius: 8px;
        padding: 12px 16px;
        border: 1px solid var(--gray-200);
        font-size: 0.8rem;
        color: var(--gray-600);
        min-height: 40px;
        max-height: 150px;
        overflow-y: auto;
    }
    .recipient-preview .count {
        font-weight: 600;
        color: var(--primary);
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        transition: var(--transition);
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.draft { background: var(--gray-100); color: var(--gray-500); border: 1px solid var(--gray-200); }
    .badge-status.draft .dot { background: var(--gray-400); }
    .badge-status.scheduled { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
    .badge-status.scheduled .dot { background: #F59E0B; }
    .badge-status.sending { background: #EFF6FF; color: #1E40AF; border: 1px solid #93C5FD; }
    .badge-status.sending .dot { background: #3B82F6; }
    .badge-status.sent { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
    .badge-status.sent .dot { background: #10B981; }
    .badge-status.failed { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    .badge-status.failed .dot { background: #EF4444; }
    .badge-status.cancelled { background: #F5F3FF; color: #5B21B6; border: 1px solid #C4B5FD; }
    .badge-status.cancelled .dot { background: #8B5CF6; }
    
    @media (max-width: 768px) {
        .form-container {
            padding: 16px;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
        .channel-options {
            grid-template-columns: 1fr 1fr;
        }
        .audience-options {
            grid-template-columns: repeat(3, 1fr);
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    @media (max-width: 480px) {
        .form-container {
            padding: 12px;
        }
        .form-container .form-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .channel-options {
            grid-template-columns: 1fr;
        }
        .audience-options {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;">
            <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($action_result['message']); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($sent_success): ?>
        <div style="background:#ECFDF5;border:1px solid #A7F3D0;border-radius:10px;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
            <i class="fas fa-check-circle" style="color:#10B981;font-size:1.5rem;"></i>
            <div>
                <div style="font-weight:600;color:#065F46;">Broadcast Sent Successfully!</div>
                <div style="font-size:0.85rem;color:#065F46;">Your message has been sent to all selected recipients.</div>
            </div>
            <a href="broadcasts.php" style="margin-left:auto;padding:6px 16px;background:#10B981;color:white;border-radius:8px;text-decoration:none;font-weight:500;font-size:0.8rem;">
                <i class="fas fa-arrow-right"></i> View All
            </a>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas <?php echo $is_edit ? 'fa-edit' : 'fa-plus-circle'; ?>" style="color:var(--primary);margin-right:8px;"></i>
                    <?php echo $is_edit ? 'Edit Broadcast' : 'New Broadcast'; ?>
                    <small><?php echo $is_edit ? 'Update your broadcast message' : 'Create and send a broadcast message'; ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="broadcasts.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="form-header">
                <div class="icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div>
                    <h3><?php echo $is_edit ? 'Edit Broadcast' : 'Create New Broadcast'; ?></h3>
                    <p>Fill in the details below. Fields marked with <span style="color:var(--danger);">*</span> are required.</p>
                </div>
            </div>

            <form method="POST" action="" id="broadcastForm">
                <input type="hidden" name="action" value="save_draft" id="formAction">
                
                <div class="form-grid">
                    <!-- Title -->
                    <div class="form-group full-width">
                        <label>Broadcast Title <span class="required">*</span></label>
                        <input type="text" name="title" placeholder="e.g., Important Election Update" required
                               value="<?php echo $is_edit ? htmlspecialchars($broadcast['title']) : ''; ?>">
                    </div>

                    <!-- Message -->
                    <div class="form-group full-width">
                        <label>Message <span class="required">*</span></label>
                        <textarea name="message" placeholder="Enter your broadcast message here..." rows="6" required id="messageText"><?php echo $is_edit ? htmlspecialchars($broadcast['message']) : ''; ?></textarea>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> 
                            <span id="charCount">0</span> characters
                        </div>
                    </div>

                    <!-- Channels -->
                    <div class="form-group full-width">
                        <label>Send Via <span class="required">*</span></label>
                        <div class="channel-options">
                            <div class="channel-option selected" onclick="toggleChannel(this, 'email')">
                                <i class="fas fa-envelope" style="color:#DC2626;"></i>
                                Email
                                <div class="check"><i class="fas fa-check-circle"></i></div>
                            </div>
                            <div class="channel-option" onclick="toggleChannel(this, 'sms')">
                                <i class="fas fa-sms" style="color:#2563EB;"></i>
                                SMS
                                <div class="check"><i class="fas fa-check-circle"></i></div>
                            </div>
                            <div class="channel-option" onclick="toggleChannel(this, 'push')">
                                <i class="fas fa-bell" style="color:#10B981;"></i>
                                Push Notification
                                <div class="check"><i class="fas fa-check-circle"></i></div>
                            </div>
                            <div class="channel-option" onclick="toggleChannel(this, 'inapp')">
                                <i class="fas fa-comment" style="color:#8B5CF6;"></i>
                                In-App
                                <div class="check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <input type="hidden" name="channels" id="selectedChannels" value="email">
                        <div class="help-text">Select one or more channels for this broadcast</div>
                    </div>

                    <!-- Target Audience -->
                    <div class="form-group full-width">
                        <label>Target Audience <span class="required">*</span></label>
                        <div class="audience-options">
                            <div class="audience-option selected" onclick="selectAudience(this, 'all')">
                                <i class="fas fa-users"></i>
                                All Users
                            </div>
                            <div class="audience-option" onclick="selectAudience(this, 'state')">
                                <i class="fas fa-flag"></i>
                                State
                            </div>
                            <div class="audience-option" onclick="selectAudience(this, 'lga')">
                                <i class="fas fa-map-marker-alt"></i>
                                LGA
                            </div>
                            <div class="audience-option" onclick="selectAudience(this, 'ward')">
                                <i class="fas fa-layer-group"></i>
                                Ward
                            </div>
                            <div class="audience-option" onclick="selectAudience(this, 'pu')">
                                <i class="fas fa-flag-checkered"></i>
                                Polling Unit
                            </div>
                            <div class="audience-option" onclick="selectAudience(this, 'role_specific')">
                                <i class="fas fa-user-tag"></i>
                                Role Specific
                            </div>
                        </div>
                        <input type="hidden" name="target_audience" id="selectedAudience" value="all">
                    </div>

                    <!-- Target Selection -->
                    <div class="form-group full-width">
                        <div id="targetState" class="target-select">
                            <label>Select States</label>
                            <select name="target_ids[]" multiple size="5">
                                <?php foreach ($states as $state): ?>
                                    <option value="<?php echo $state['id']; ?>">
                                        <?php echo htmlspecialchars($state['name']); ?> (<?php echo htmlspecialchars($state['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="selected-count">Hold Ctrl/Cmd to select multiple</div>
                        </div>

                        <div id="targetLga" class="target-select">
                            <label>Select LGAs</label>
                            <select name="target_ids[]" multiple size="5">
                                <?php foreach ($lgas as $lga): ?>
                                    <option value="<?php echo $lga['id']; ?>">
                                        <?php echo htmlspecialchars($lga['name']); ?> (<?php echo htmlspecialchars($lga['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="selected-count">Hold Ctrl/Cmd to select multiple</div>
                        </div>

                        <div id="targetWard" class="target-select">
                            <label>Select Wards</label>
                            <select name="target_ids[]" multiple size="5">
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>">
                                        <?php echo htmlspecialchars($ward['name']); ?> (<?php echo htmlspecialchars($ward['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="selected-count">Hold Ctrl/Cmd to select multiple</div>
                        </div>

                        <div id="targetPu" class="target-select">
                            <label>Select Polling Units</label>
                            <select name="target_ids[]" multiple size="5">
                                <?php foreach ($polling_units as $pu): ?>
                                    <option value="<?php echo $pu['id']; ?>">
                                        <?php echo htmlspecialchars($pu['code']); ?> - <?php echo htmlspecialchars($pu['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="selected-count">Hold Ctrl/Cmd to select multiple</div>
                        </div>

                        <div id="targetRole" class="target-select">
                            <label>Select Role</label>
                            <select name="target_role_id">
                                <option value="0">Select a role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>">
                                        <?php echo htmlspecialchars($role['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">Send to all users with this role</div>
                        </div>
                    </div>

                    <!-- Recipient Preview -->
                    <div class="form-group full-width">
                        <label>Recipient Preview</label>
                        <div class="recipient-preview" id="recipientPreview">
                            <span class="count" id="recipientCount"><?php echo count($users); ?></span> recipients will receive this broadcast
                        </div>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> 
                            This is an estimate based on active users with email addresses.
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="form-group">
                        <label>Schedule Date &amp; Time</label>
                        <input type="datetime-local" name="scheduled_at" id="scheduledAt"
                               value="<?php echo $is_edit && $broadcast['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($broadcast['scheduled_at'])) : ''; ?>">
                        <div class="help-text">Leave empty to send immediately</div>
                    </div>

                    <!-- Status (Edit only) -->
                    <?php if ($is_edit): ?>
                    <div class="form-group">
                        <label>Current Status</label>
                        <div style="padding:8px 0;">
                            <span class="badge-status <?php echo $broadcast['status']; ?>">
                                <span class="dot"></span>
                                <?php echo ucfirst($broadcast['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <a href="broadcasts.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="button" class="btn btn-warning" onclick="saveDraft()">
                        <i class="fas fa-save"></i> Save Draft
                    </button>
                    <button type="button" class="btn btn-primary" onclick="scheduleBroadcast()">
                        <i class="fas fa-clock"></i> Schedule
                    </button>
                    <button type="button" class="btn btn-success" onclick="sendBroadcast()">
                        <i class="fas fa-paper-plane"></i> Send Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
    
    // Initialize character counter
    updateCharCount();
});

// ============================================================
// SIDEBAR TOGGLE
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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// CHARACTER COUNTER
// ============================================================
document.getElementById('messageText').addEventListener('input', function() {
    updateCharCount();
});

function updateCharCount() {
    var text = document.getElementById('messageText').value;
    document.getElementById('charCount').textContent = text.length;
}

// ============================================================
// CHANNEL SELECTION
// ============================================================
var selectedChannels = ['email'];

function toggleChannel(element, channel) {
    element.classList.toggle('selected');
    
    var idx = selectedChannels.indexOf(channel);
    if (idx > -1) {
        selectedChannels.splice(idx, 1);
    } else {
        selectedChannels.push(channel);
    }
    
    document.getElementById('selectedChannels').value = selectedChannels.join(',');
    
    // Update recipient count
    updateRecipientCount();
}

// ============================================================
// AUDIENCE SELECTION
// ============================================================
function selectAudience(element, audience) {
    document.querySelectorAll('.audience-option').forEach(function(opt) {
        opt.classList.remove('selected');
    });
    element.classList.add('selected');
    document.getElementById('selectedAudience').value = audience;
    
    // Hide all target selects
    document.querySelectorAll('.target-select').forEach(function(el) {
        el.classList.remove('active');
    });
    
    // Show relevant target select
    if (audience === 'state') {
        document.getElementById('targetState').classList.add('active');
    } else if (audience === 'lga') {
        document.getElementById('targetLga').classList.add('active');
    } else if (audience === 'ward') {
        document.getElementById('targetWard').classList.add('active');
    } else if (audience === 'pu') {
        document.getElementById('targetPu').classList.add('active');
    } else if (audience === 'role_specific') {
        document.getElementById('targetRole').classList.add('active');
    }
    
    // Update recipient count
    updateRecipientCount();
}

// ============================================================
// RECIPIENT COUNT
// ============================================================
function updateRecipientCount() {
    var audience = document.getElementById('selectedAudience').value;
    var totalUsers = <?php echo count($users); ?>;
    var counts = {
        'all': totalUsers,
        'state': Math.floor(totalUsers * 0.8),
        'lga': Math.floor(totalUsers * 0.6),
        'ward': Math.floor(totalUsers * 0.4),
        'pu': Math.floor(totalUsers * 0.2),
        'role_specific': Math.floor(totalUsers * 0.3)
    };
    document.getElementById('recipientCount').textContent = counts[audience] || 0;
}

// ============================================================
// FORM ACTIONS
// ============================================================
function saveDraft() {
    document.getElementById('formAction').value = 'save_draft';
    document.getElementById('broadcastForm').submit();
}

function scheduleBroadcast() {
    var scheduledAt = document.getElementById('scheduledAt').value;
    if (!scheduledAt) {
        alert('Please set a scheduled date and time.');
        return;
    }
    if (confirm('Schedule this broadcast to be sent at ' + new Date(scheduledAt).toLocaleString() + '?')) {
        document.getElementById('formAction').value = 'schedule_broadcast';
        document.getElementById('broadcastForm').submit();
    }
}

function sendBroadcast() {
    if (confirm('Send this broadcast immediately to all selected recipients?')) {
        document.getElementById('formAction').value = 'send_broadcast';
        document.getElementById('broadcastForm').submit();
    }
}

// ============================================================
// SEARCH FUNCTIONALITY
// ============================================================
var searchInput = document.querySelector('.search-wrap input');
if (searchInput) {
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            this.closest('form').submit();
        }
    });
}

// ============================================================
// INITIALIZE
// ============================================================
// Set initial selected channels
document.querySelectorAll('.channel-option.selected').forEach(function(el) {
    // Already handled by inline class
});

// Set initial audience
document.querySelectorAll('.audience-option.selected').forEach(function(el) {
    // Already handled by inline class
});

// Show initial target select for edit
var initialAudience = document.getElementById('selectedAudience').value;
if (initialAudience !== 'all') {
    var targetMap = {
        'state': 'targetState',
        'lga': 'targetLga',
        'ward': 'targetWard',
        'pu': 'targetPu',
        'role_specific': 'targetRole'
    };
    var targetId = targetMap[initialAudience];
    if (targetId) {
        document.getElementById(targetId).classList.add('active');
    }
}

// Populate selected channels for edit
<?php if ($is_edit && $broadcast): ?>
    var editChannels = <?php echo $broadcast['send_via']; ?>;
    if (Array.isArray(editChannels) && editChannels.length > 0) {
        selectedChannels = editChannels;
        document.getElementById('selectedChannels').value = editChannels.join(',');
        // Update UI
        document.querySelectorAll('.channel-option').forEach(function(el) {
            var channel = el.textContent.trim().toLowerCase().replace(/\s/g, '');
            if (editChannels.includes(channel)) {
                el.classList.add('selected');
            }
        });
    }
    
    // Set edit audience
    var editAudience = '<?php echo $broadcast['target_audience']; ?>';
    if (editAudience && editAudience !== 'all') {
        document.querySelectorAll('.audience-option').forEach(function(el) {
            el.classList.remove('selected');
            var audience = el.textContent.trim().toLowerCase().replace(/\s/g, '');
            if (audience === editAudience || (editAudience === 'role_specific' && audience === 'rolespecific')) {
                el.classList.add('selected');
            }
        });
        document.getElementById('selectedAudience').value = editAudience;
        // Show target select
        var targetMap = {
            'state': 'targetState',
            'lga': 'targetLga',
            'ward': 'targetWard',
            'pu': 'targetPu',
            'role_specific': 'targetRole'
        };
        var targetId = targetMap[editAudience];
        if (targetId) {
            document.getElementById(targetId).classList.add('active');
        }
    }
<?php endif; ?>

// Update recipient count
updateRecipientCount();
</script>
</body>
</html>