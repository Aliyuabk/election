<?php
// ============================================================
// WARD COORDINATOR - CHAT WITH AGENTS (COMPLETE VERSION)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

// Check if user is logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only Ward coordinator can access
$user_role_level = SessionManager::get('role_level');
if ($user_role_level !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

// Get user data from session
$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');

// Get database connection
$db = getDB();

// ============================================================
// FIX: Ensure ward_id is properly set
// ============================================================
if (empty($ward_id)) {
    try {
        $stmt = $db->prepare("SELECT ward_id, lga_id, state_id FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$user_id, $tenant_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            $lga_id = $user['lga_id'] ?? $lga_id;
            $state_id = $user['state_id'] ?? $state_id;
            SessionManager::set('ward_id', $ward_id);
            SessionManager::set('lga_id', $lga_id);
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

// ============================================================
// FETCH WARD NAME
// ============================================================
$ward_name = 'Unknown Ward';
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
// ROLE DEFINITIONS
// ============================================================
$role_definitions = [
    9 => ['name' => 'PU Agent', 'icon' => 'fa-user-check', 'color' => '#3B82F6', 'level' => 'pu_agent'],
    10 => ['name' => 'Party Agent', 'icon' => 'fa-flag', 'color' => '#8B5CF6', 'level' => 'party_agent'],
    11 => ['name' => 'Observer', 'icon' => 'fa-eye', 'color' => '#10B981', 'level' => 'observer'],
    15 => ['name' => 'Volunteer', 'icon' => 'fa-hands-helping', 'color' => '#F59E0B', 'level' => 'volunteer']
];

// ============================================================
// GET SELECTED ROLE AND CONTACT
// ============================================================
$selected_role = isset($_GET['role']) ? (int)$_GET['role'] : 9;
$selected_contact_id = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;
$selected_contact = null;
$messages = [];
$contacts = [];

// ============================================================
// HANDLE AJAX REQUESTS
// ============================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'messages' => [], 'contacts' => [], 'new_messages' => 0];
    
    try {
        if ($selected_contact_id > 0) {
            $last_msg_id = isset($_GET['last_msg_id']) ? (int)$_GET['last_msg_id'] : 0;
            
            $stmt = $db->prepare("
                SELECT 
                    cm.*,
                    u_sender.full_name as sender_name,
                    u_sender.photograph_url as sender_photo,
                    u_receiver.full_name as receiver_name
                FROM chat_messages cm
                LEFT JOIN users u_sender ON cm.sender_id = u_sender.id
                LEFT JOIN users u_receiver ON cm.receiver_id = u_receiver.id
                WHERE ((cm.sender_id = ? AND cm.receiver_id = ?)
                   OR (cm.sender_id = ? AND cm.receiver_id = ?))
                AND cm.is_deleted = 0
                AND cm.id > ?
                ORDER BY cm.created_at ASC
            ");
            $stmt->execute([$user_id, $selected_contact_id, $selected_contact_id, $user_id, $last_msg_id]);
            $new_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['messages'] = $new_messages;
            $response['new_messages'] = count($new_messages);
            
            if (count($new_messages) > 0) {
                $stmt = $db->prepare("
                    UPDATE chat_messages 
                    SET is_read = 1, read_at = NOW() 
                    WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
                ");
                $stmt->execute([$selected_contact_id, $user_id]);
            }
        }
        
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.user_code,
                u.email,
                u.phone,
                u.status,
                u.photograph_url,
                u.last_login_at,
                u.pu_id,
                u.role_id,
                pu.name as pu_name,
                pu.code as pu_code,
                r.level as role_level,
                r.name as role_name,
                (SELECT COUNT(*) FROM chat_messages cm 
                 WHERE cm.sender_id = u.id AND cm.receiver_id = ? AND cm.is_read = 0 AND cm.is_deleted = 0) as unread_count,
                (SELECT COUNT(*) FROM user_sessions us 
                 WHERE us.user_id = u.id AND us.is_active = 1 
                 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as is_online,
                (SELECT MAX(created_at) FROM chat_messages 
                 WHERE (sender_id = u.id AND receiver_id = ?) 
                    OR (sender_id = ? AND receiver_id = u.id)) as last_message_time,
                (SELECT content FROM chat_messages 
                 WHERE (sender_id = u.id AND receiver_id = ?) 
                    OR (sender_id = ? AND receiver_id = u.id) 
                 ORDER BY created_at DESC LIMIT 1) as last_message
            FROM users u
            LEFT JOIN polling_units pu ON u.pu_id = pu.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.tenant_id = ? 
            AND u.ward_id = ?
            AND u.deleted_at IS NULL
            AND u.status = 'active'
            AND u.id != ?
            AND u.role_id = ?
            ORDER BY last_message_time DESC, u.full_name ASC
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $tenant_id, $ward_id, $user_id, $selected_role]);
        $response['contacts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response['success'] = true;
        
    } catch (Exception $e) {
        error_log("AJAX error: " . $e->getMessage());
        $response['error'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// ============================================================
// HANDLE FILE UPLOAD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_file') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Upload failed'];
    
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $upload_dir = '../../uploads/chat/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'ppt', 'pptx'];
        
        if (in_array($file_ext, $allowed_types) && $file['size'] < 10485760) {
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $response['success'] = true;
                $response['message'] = 'File uploaded successfully';
                $response['url'] = '/election/uploads/chat/' . $file_name;
                $response['filename'] = $file['name'];
                $response['filesize'] = $file['size'];
                $response['filetype'] = $file_ext;
                $response['type'] = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'file';
            } else {
                $response['message'] = 'Failed to move uploaded file';
            }
        } else {
            $response['message'] = 'Invalid file type or file too large (max 10MB)';
        }
    } else {
        $response['message'] = 'No file uploaded or upload error';
    }
    
    echo json_encode($response);
    exit();
}

// ============================================================
// FETCH CONTACTS BY ROLE
// ============================================================
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.user_code,
            u.email,
            u.phone,
            u.status,
            u.photograph_url,
            u.last_login_at,
            u.pu_id,
            u.role_id,
            pu.name as pu_name,
            pu.code as pu_code,
            r.level as role_level,
            r.name as role_name,
            (SELECT COUNT(*) FROM chat_messages cm 
             WHERE cm.sender_id = u.id AND cm.receiver_id = ? AND cm.is_read = 0 AND cm.is_deleted = 0) as unread_count,
            (SELECT COUNT(*) FROM user_sessions us 
             WHERE us.user_id = u.id AND us.is_active = 1 
             AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as is_online,
            (SELECT MAX(created_at) FROM chat_messages 
             WHERE (sender_id = u.id AND receiver_id = ?) 
                OR (sender_id = ? AND receiver_id = u.id)) as last_message_time,
            (SELECT content FROM chat_messages 
             WHERE (sender_id = u.id AND receiver_id = ?) 
                OR (sender_id = ? AND receiver_id = u.id) 
             ORDER BY created_at DESC LIMIT 1) as last_message
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND u.status = 'active'
        AND u.id != ?
        AND u.role_id = ?
        ORDER BY last_message_time DESC, u.full_name ASC
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $tenant_id, $ward_id, $user_id, $selected_role]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($selected_contact_id > 0) {
        foreach ($contacts as $contact) {
            if ($contact['id'] == $selected_contact_id) {
                $selected_contact = $contact;
                break;
            }
        }
        
        if ($selected_contact) {
            $stmt = $db->prepare("
                SELECT 
                    cm.*,
                    u_sender.full_name as sender_name,
                    u_sender.photograph_url as sender_photo,
                    u_receiver.full_name as receiver_name
                FROM chat_messages cm
                LEFT JOIN users u_sender ON cm.sender_id = u_sender.id
                LEFT JOIN users u_receiver ON cm.receiver_id = u_receiver.id
                WHERE (cm.sender_id = ? AND cm.receiver_id = ?)
                   OR (cm.sender_id = ? AND cm.receiver_id = ?)
                AND cm.is_deleted = 0
                ORDER BY cm.created_at ASC
            ");
            $stmt->execute([$user_id, $selected_contact_id, $selected_contact_id, $user_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("
                UPDATE chat_messages 
                SET is_read = 1, read_at = NOW() 
                WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
            ");
            $stmt->execute([$selected_contact_id, $user_id]);
        }
    }
    
} catch (Exception $e) {
    error_log("Error fetching contacts: " . $e->getMessage());
}

// ============================================================
// HANDLE SEND MESSAGE
// ============================================================
$success_message = '';
$error_message = '';
$show_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $message_type = isset($_POST['message_type']) ? $_POST['message_type'] : 'text';
    $media_url = isset($_POST['media_url']) ? trim($_POST['media_url']) : '';
    $media_filename = isset($_POST['media_filename']) ? trim($_POST['media_filename']) : '';
    $media_filesize = isset($_POST['media_filesize']) ? (int)$_POST['media_filesize'] : 0;
    $media_filetype = isset($_POST['media_filetype']) ? trim($_POST['media_filetype']) : '';
    $role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 9;
    
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $session_token = SessionManager::get('csrf_token');
    
    if (empty($csrf_token) || $csrf_token !== $session_token) {
        $error_message = 'Security validation failed. Please try again.';
    } elseif ($receiver_id <= 0) {
        $error_message = 'Invalid recipient.';
    } elseif (empty($message) && empty($media_url)) {
        $error_message = 'Please enter a message or attach a file.';
    } else {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                SELECT id, full_name, role_id FROM users 
                WHERE id = ? AND tenant_id = ? AND ward_id = ? AND role_id = ? AND status = 'active'
            ");
            $stmt->execute([$receiver_id, $tenant_id, $ward_id, $role_id]);
            $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$receiver) {
                throw new Exception('Recipient not found or not in your ward.');
            }
            
            $stmt = $db->prepare("
                SELECT cr.id FROM chat_rooms cr
                JOIN chat_room_members crm1 ON cr.id = crm1.room_id
                JOIN chat_room_members crm2 ON cr.id = crm2.room_id
                WHERE cr.tenant_id = ? AND cr.type = 'direct'
                AND crm1.user_id = ? AND crm2.user_id = ?
            ");
            $stmt->execute([$tenant_id, $user_id, $receiver_id]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($room) {
                $room_id = $room['id'];
            } else {
                $stmt = $db->prepare("
                    INSERT INTO chat_rooms (tenant_id, name, type, created_by, created_at) 
                    VALUES (?, ?, 'direct', ?, NOW())
                ");
                $room_name = "Chat between " . $user_name . " and " . $receiver['full_name'];
                $stmt->execute([$tenant_id, $room_name, $user_id]);
                $room_id = $db->lastInsertId();
                
                $stmt = $db->prepare("INSERT INTO chat_room_members (room_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
                $stmt->execute([$room_id, $user_id]);
                $stmt->execute([$room_id, $receiver_id]);
            }
            
            // If it's a file, include file info in the content
            $content = $message;
            if ($media_url && $message_type === 'file') {
                $file_info = json_encode([
                    'url' => $media_url,
                    'filename' => $media_filename,
                    'filesize' => $media_filesize,
                    'filetype' => $media_filetype
                ]);
                $content = $file_info;
            }
            
            $stmt = $db->prepare("
                INSERT INTO chat_messages (
                    room_id, sender_id, receiver_id, message_type, content, 
                    media_url, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$room_id, $user_id, $receiver_id, $message_type, $content, $media_url]);
            
            logActivity($user_id, 'chat_message', "Sent message to {$receiver['full_name']} (ID: $receiver_id)", 'chat', $room_id);
            
            $db->commit();
            $success_message = 'Message sent successfully!';
            $show_success = true;
            
            if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
                if (ob_get_level()) ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Message sent successfully', 'msg_id' => $db->lastInsertId()]);
                exit();
            }
            
            if (!isset($_POST['ajax']) || $_POST['ajax'] !== '1') {
                header('Location: chat-agents.php?role=' . $role_id . '&contact_id=' . $receiver_id . '&sent=1');
                exit();
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Error sending message: " . $e->getMessage();
            error_log("Chat send error: " . $e->getMessage());
            
            if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error_message]);
                exit();
            }
        }
    }
}

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
SessionManager::set('csrf_token', $csrf_token);

// Count contacts per role
$role_counts = [];
foreach ($role_definitions as $role_id => $role) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM users 
            WHERE tenant_id = ? AND ward_id = ? AND role_id = ? AND status = 'active' AND deleted_at IS NULL
        ");
        $stmt->execute([$tenant_id, $ward_id, $role_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $role_counts[$role_id] = $result['count'] ?? 0;
    } catch (Exception $e) {
        $role_counts[$role_id] = 0;
    }
}

$page_title = 'Chat with Agents';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
/* ============================================================
   CHAT INTERFACE - COMPLETE VERSION
   ============================================================ */
:root {
    --chat-primary: #0F4C81;
    --chat-primary-light: #E8F0FE;
    --chat-primary-dark: #0a3a62;
    --chat-sent-bg: #0F4C81;
    --chat-sent-text: #ffffff;
    --chat-received-bg: #ffffff;
    --chat-received-text: #1a1a2e;
    --chat-sidebar-bg: #f8fafc;
    --chat-border: #e5e7eb;
    --chat-online: #22c55e;
    --chat-offline: #9ca3af;
    --chat-unread-bg: #0F4C81;
    --chat-unread-text: #ffffff;
    --chat-shadow: 0 2px 12px rgba(0,0,0,0.08);
    --chat-radius: 12px;
}

/* Main Container - FIXED */
.chat-container {
    display: flex;
    height: calc(100vh - 200px);
    min-height: 450px;
    max-height: calc(100vh - 200px);
    background: white;
    border-radius: var(--chat-radius);
    border: 1px solid var(--chat-border);
    overflow: hidden;
    box-shadow: var(--chat-shadow);
    position: relative;
}

/* ============================================================
   LEFT SIDEBAR - CONTACT LIST
   ============================================================ */
.chat-sidebar {
    width: 340px;
    min-width: 280px;
    background: var(--chat-sidebar-bg);
    border-right: 1px solid var(--chat-border);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    overflow: hidden;
}

.role-tabs {
    display: flex;
    background: white;
    border-bottom: 1px solid var(--chat-border);
    padding: 4px;
    gap: 2px;
    flex-wrap: wrap;
    flex-shrink: 0;
}
.role-tab {
    flex: 1;
    min-width: 55px;
    padding: 5px 6px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.6rem;
    font-weight: 600;
    transition: all 0.2s ease;
    background: transparent;
    color: var(--gray-500);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
    position: relative;
    text-decoration: none;
}
.role-tab i {
    font-size: 0.8rem;
}
.role-tab .role-count {
    font-size: 0.45rem;
    background: var(--gray-200);
    color: var(--gray-600);
    padding: 1px 5px;
    border-radius: 8px;
    font-weight: 600;
}
.role-tab:hover {
    background: var(--gray-100);
    color: var(--gray-700);
}
.role-tab.active {
    background: var(--chat-primary);
    color: white;
}
.role-tab.active .role-count {
    background: rgba(255,255,255,0.3);
    color: white;
}

.chat-sidebar-header {
    padding: 10px 14px;
    background: white;
    border-bottom: 1px solid var(--chat-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}
.chat-sidebar-header h3 {
    font-size: 0.85rem;
    font-weight: 700;
    margin: 0;
    color: var(--gray-800);
}
.chat-sidebar-header h3 i {
    color: var(--chat-primary);
}
.chat-sidebar-header .badge {
    background: var(--chat-primary);
    color: white;
    font-size: 0.55rem;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.chat-sidebar-search {
    padding: 6px 10px;
    background: white;
    border-bottom: 1px solid var(--chat-border);
    flex-shrink: 0;
}
.chat-sidebar-search .search-wrapper {
    position: relative;
}
.chat-sidebar-search .search-wrapper i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
    font-size: 0.7rem;
}
.chat-sidebar-search input {
    width: 100%;
    padding: 5px 8px 5px 28px;
    border: 1px solid var(--chat-border);
    border-radius: 16px;
    font-size: 0.75rem;
    background: #F1F5F9;
    transition: all 0.3s ease;
}
.chat-sidebar-search input:focus {
    outline: none;
    border-color: var(--chat-primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(15, 76, 129, 0.1);
}

.chat-contact-list {
    flex: 1;
    overflow-y: auto;
    padding: 2px 0;
    min-height: 0;
}
.chat-contact-list::-webkit-scrollbar {
    width: 3px;
}
.chat-contact-list::-webkit-scrollbar-track {
    background: transparent;
}
.chat-contact-list::-webkit-scrollbar-thumb {
    background: var(--gray-300);
    border-radius: 3px;
}

.chat-contact-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
    position: relative;
    text-decoration: none;
    color: inherit;
}
.chat-contact-item:hover {
    background: var(--gray-100);
}
.chat-contact-item.active {
    background: var(--chat-primary-light);
    border-left-color: var(--chat-primary);
}

.chat-contact-item .avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.7rem;
    color: var(--gray-600);
    flex-shrink: 0;
    position: relative;
}
.chat-contact-item .avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.chat-contact-item .avatar .online-dot {
    position: absolute;
    bottom: 1px;
    right: 1px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    border: 2px solid white;
}
.chat-contact-item .avatar .online-dot.online {
    background: var(--chat-online);
}
.chat-contact-item .avatar .online-dot.offline {
    background: var(--chat-offline);
}

.chat-contact-item .contact-info {
    flex: 1;
    min-width: 0;
}
.chat-contact-item .contact-info .name {
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-800);
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}
.chat-contact-item .contact-info .name .role-tag {
    font-size: 0.45rem;
    padding: 1px 5px;
    border-radius: 6px;
    font-weight: 500;
}
.chat-contact-item .contact-info .last-msg {
    font-size: 0.65rem;
    color: var(--gray-500);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 1px;
}

.chat-contact-item .contact-meta {
    text-align: right;
    flex-shrink: 0;
}
.chat-contact-item .contact-meta .time {
    font-size: 0.5rem;
    color: var(--gray-400);
}
.chat-contact-item .contact-meta .unread {
    background: var(--chat-unread-bg);
    color: var(--chat-unread-text);
    font-size: 0.45rem;
    padding: 1px 5px;
    border-radius: 8px;
    font-weight: 600;
    margin-top: 2px;
    display: inline-block;
}
.chat-contact-item .contact-meta .online-status {
    font-size: 0.45rem;
    color: var(--chat-online);
    font-weight: 500;
}

/* ============================================================
   RIGHT CONTENT - CHAT AREA (FIXED SCROLLING)
   ============================================================ */
.chat-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #f8fafc;
    min-width: 0;
    min-height: 0;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.chat-content-header {
    padding: 8px 14px;
    background: white;
    border-bottom: 1px solid var(--chat-border);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    min-height: 50px;
    z-index: 2;
}

.chat-content-header .avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.7rem;
    color: var(--gray-600);
    flex-shrink: 0;
}
.chat-content-header .avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.chat-content-header .header-info {
    flex: 1;
    min-width: 0;
}
.chat-content-header .header-info .name {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--gray-800);
}
.chat-content-header .header-info .status {
    font-size: 0.6rem;
    color: var(--gray-500);
}
.chat-content-header .header-info .status.online {
    color: var(--chat-online);
}
.chat-content-header .header-actions {
    display: flex;
    gap: 2px;
    flex-shrink: 0;
}
.chat-content-header .header-actions button {
    padding: 3px 6px;
    border: none;
    background: none;
    cursor: pointer;
    color: var(--gray-500);
    border-radius: 4px;
    transition: all 0.2s ease;
}
.chat-content-header .header-actions button:hover {
    background: var(--gray-100);
    color: var(--gray-700);
}
.chat-content-header .header-actions button i {
    font-size: 0.8rem;
}

/* Chat Messages - FIXED SCROLLING */
.chat-messages {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 10px 14px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-height: 0;
    height: 100%;
    max-height: 100%;
    position: relative;
    scroll-behavior: smooth;
}
.chat-messages::-webkit-scrollbar {
    width: 4px;
}
.chat-messages::-webkit-scrollbar-track {
    background: transparent;
}
.chat-messages::-webkit-scrollbar-thumb {
    background: var(--gray-300);
    border-radius: 4px;
}

/* Message rows - prevent shrinking */
.message-row {
    display: flex;
    margin-bottom: 2px;
    animation: messageIn 0.3s ease;
    flex-shrink: 0;
}
.message-row.sent {
    justify-content: flex-end;
}
.message-row.received {
    justify-content: flex-start;
}

@keyframes messageIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.message-bubble {
    max-width: 75%;
    padding: 6px 10px;
    border-radius: 10px;
    font-size: 0.8rem;
    line-height: 1.5;
    word-wrap: break-word;
    position: relative;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.message-row.sent .message-bubble {
    background: var(--chat-sent-bg);
    color: var(--chat-sent-text);
    border-bottom-right-radius: 3px;
}
.message-row.received .message-bubble {
    background: var(--chat-received-bg);
    color: var(--chat-received-text);
    border: 1px solid var(--chat-border);
    border-bottom-left-radius: 3px;
}

.message-bubble .message-time {
    font-size: 0.45rem;
    opacity: 0.7;
    margin-top: 2px;
    display: block;
    text-align: right;
}
.message-row.sent .message-bubble .message-time {
    color: rgba(255,255,255,0.7);
}
.message-row.received .message-bubble .message-time {
    color: var(--gray-400);
}

.message-bubble .message-sender {
    font-size: 0.6rem;
    font-weight: 600;
    margin-bottom: 2px;
    display: block;
    color: var(--chat-primary);
}

/* ============================================================
   FILE MESSAGE - WHATSAPP STYLE
   ============================================================ */
.file-message {
    background: rgba(59, 130, 246, 0.05);
    border-radius: 8px;
    padding: 8px 10px;
    margin: 4px 0;
    border: 1px solid rgba(59, 130, 246, 0.15);
    min-width: 180px;
    max-width: 260px;
}

.file-message .file-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    margin-right: 8px;
}
.file-message .file-icon.pdf { background: #FEE2E2; color: #DC2626; }
.file-message .file-icon.doc { background: #DBEAFE; color: #2563EB; }
.file-message .file-icon.docx { background: #DBEAFE; color: #2563EB; }
.file-message .file-icon.xls { background: #D1FAE5; color: #059669; }
.file-message .file-icon.xlsx { background: #D1FAE5; color: #059669; }
.file-message .file-icon.ppt { background: #FEF3C7; color: #D97706; }
.file-message .file-icon.pptx { background: #FEF3C7; color: #D97706; }
.file-message .file-icon.txt { background: #E5E7EB; color: #6B7280; }
.file-message .file-icon.zip { background: #F3E8FF; color: #7C3AED; }
.file-message .file-icon.rar { background: #F3E8FF; color: #7C3AED; }
.file-message .file-icon.image { background: #FCE7F3; color: #DB2777; }
.file-message .file-icon.default { background: #E5E7EB; color: #6B7280; }

.file-message .file-info {
    flex: 1;
    min-width: 0;
}
.file-message .file-info .file-name {
    font-weight: 500;
    font-size: 0.8rem;
    color: var(--gray-800);
    word-break: break-all;
}
.file-message .file-info .file-size {
    font-size: 0.6rem;
    color: var(--gray-500);
}
.file-message .file-info .file-type {
    font-size: 0.5rem;
    color: var(--gray-400);
    text-transform: uppercase;
    background: var(--gray-100);
    padding: 1px 5px;
    border-radius: 4px;
    margin-left: 4px;
}

.file-message .file-actions {
    display: flex;
    gap: 4px;
    margin-top: 4px;
}
.file-message .file-actions a {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.file-message .file-actions .download {
    background: var(--chat-primary);
    color: white;
}
.file-message .file-actions .download:hover {
    background: var(--chat-primary-dark);
}
.file-message .file-actions .view {
    background: var(--gray-100);
    color: var(--gray-600);
}
.file-message .file-actions .view:hover {
    background: var(--gray-200);
}

.message-row.sent .file-message {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
}
.message-row.sent .file-message .file-info .file-name {
    color: white;
}
.message-row.sent .file-message .file-info .file-size {
    color: rgba(255,255,255,0.6);
}
.message-row.sent .file-message .file-info .file-type {
    background: rgba(255,255,255,0.2);
    color: rgba(255,255,255,0.7);
}
.message-row.sent .file-message .file-actions .view {
    background: rgba(255,255,255,0.2);
    color: rgba(255,255,255,0.8);
}
.message-row.sent .file-message .file-actions .view:hover {
    background: rgba(255,255,255,0.3);
}
.message-row.sent .file-message .file-actions .download {
    background: rgba(255,255,255,0.2);
    color: white;
}
.message-row.sent .file-message .file-actions .download:hover {
    background: rgba(255,255,255,0.3);
}

/* ============================================================
   LOCATION MESSAGE STYLES
   ============================================================ */
.location-message {
    background: rgba(59, 130, 246, 0.05);
    border-radius: 8px;
    padding: 6px 10px;
    margin: 4px 0;
    border-left: 3px solid #3B82F6;
}
.location-message .location-header {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
    font-size: 0.8rem;
    color: #1E40AF;
}
.location-message .location-header i {
    font-size: 0.9rem;
}
.location-message .location-details {
    margin-top: 3px;
    font-size: 0.75rem;
    color: var(--gray-600);
}
.location-message .location-details .coord {
    font-family: monospace;
    background: var(--gray-100);
    padding: 1px 4px;
    border-radius: 4px;
    font-size: 0.65rem;
}
.location-message .location-map-link {
    display: inline-block;
    margin-top: 4px;
    padding: 3px 10px;
    background: #3B82F6;
    color: white;
    border-radius: 6px;
    font-size: 0.65rem;
    text-decoration: none;
    transition: all 0.2s ease;
}
.location-message .location-map-link:hover {
    background: #2563EB;
    transform: translateY(-1px);
}
.location-message .location-name {
    font-weight: 600;
    color: var(--gray-800);
    margin-top: 3px;
    font-size: 0.8rem;
}

/* Date Divider */
.date-divider {
    text-align: center;
    padding: 4px 0;
    margin: 4px 0;
}
.date-divider span {
    font-size: 0.6rem;
    color: var(--gray-400);
    background: #F1F5F9;
    padding: 2px 10px;
    border-radius: 8px;
}

/* Chat Input */
.chat-input-area {
    padding: 6px 10px;
    background: white;
    border-top: 1px solid var(--chat-border);
    flex-shrink: 0;
    min-height: 48px;
    z-index: 2;
}
.chat-input-area .input-row {
    display: flex;
    gap: 4px;
    align-items: end;
}
.chat-input-area .input-row .input-tools {
    display: flex;
    gap: 2px;
}
.chat-input-area .input-row .input-tools button {
    padding: 3px 6px;
    border: none;
    background: none;
    cursor: pointer;
    color: var(--gray-500);
    border-radius: 4px;
    transition: all 0.2s ease;
    font-size: 0.8rem;
}
.chat-input-area .input-row .input-tools button:hover {
    background: var(--gray-100);
    color: var(--gray-700);
}
.chat-input-area .input-row textarea {
    flex: 1;
    padding: 5px 10px;
    border: 1px solid var(--chat-border);
    border-radius: 14px;
    font-size: 0.8rem;
    resize: none;
    min-height: 30px;
    max-height: 80px;
    font-family: inherit;
    background: #F1F5F9;
    transition: all 0.3s ease;
}
.chat-input-area .input-row textarea:focus {
    outline: none;
    border-color: var(--chat-primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(15, 76, 129, 0.1);
}
.chat-input-area .input-row .send-btn {
    padding: 5px 12px;
    border: none;
    background: var(--chat-primary);
    color: white;
    border-radius: 14px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 3px;
    white-space: nowrap;
}
.chat-input-area .input-row .send-btn:hover {
    background: var(--chat-primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 76, 129, 0.3);
}
.chat-input-area .input-row .send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Typing Indicator */
.typing-indicator {
    padding: 3px 12px;
    font-size: 0.65rem;
    color: var(--gray-400);
    display: none;
    background: rgba(255,255,255,0.9);
    border-radius: 8px;
    margin: 0 16px 2px 16px;
    flex-shrink: 0;
}
.typing-indicator .dots {
    display: inline-block;
}
.typing-indicator .dots span {
    display: inline-block;
    width: 3px;
    height: 3px;
    border-radius: 50%;
    background: var(--gray-400);
    margin: 0 1px;
    animation: typingDot 1.4s infinite;
}
.typing-indicator .dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator .dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typingDot {
    0%, 60%, 100% { opacity: 0.3; transform: scale(1); }
    30% { opacity: 1; transform: scale(1.3); }
}

/* Empty State */
.empty-chat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--gray-400);
    padding: 30px;
}
.empty-chat i {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: var(--gray-300);
}
.empty-chat h4 {
    margin: 0 0 4px;
    font-size: 1rem;
    color: var(--gray-600);
}
.empty-chat p {
    margin: 0;
    font-size: 0.8rem;
    text-align: center;
    max-width: 280px;
}

/* Alerts */
.alert {
    padding: 8px 12px;
    border-radius: var(--radius);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid transparent;
    font-size: 0.8rem;
}
.alert-success {
    background: #ECFDF5;
    border-color: #D1FAE5;
    color: #065F46;
}
.alert-danger {
    background: #FEF2F2;
    border-color: #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 0.9rem;
}
.alert .alert-close {
    margin-left: auto;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    opacity: 0.7;
    color: inherit;
}
.alert .alert-close:hover {
    opacity: 1;
}

/* Mobile Toggle */
.mobile-toggle {
    display: none;
    padding: 3px 10px;
    border: 1px solid var(--chat-border);
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.7rem;
    color: var(--gray-600);
    transition: all 0.2s ease;
}
.mobile-toggle:hover {
    background: var(--gray-50);
}

.connection-status {
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: 6px 14px;
    border-radius: 16px;
    font-size: 0.65rem;
    font-weight: 500;
    z-index: 999;
    display: none;
}
.connection-status.online {
    background: #D1FAE5;
    color: #065F46;
    display: block;
}
.connection-status.offline {
    background: #FEE2E2;
    color: #991B1B;
    display: block;
}

/* Responsive */
@media (max-width: 1024px) {
    .chat-sidebar {
        width: 280px;
        min-width: 220px;
    }
}

@media (max-width: 768px) {
    .chat-container {
        height: calc(100vh - 160px);
        flex-direction: column;
        border-radius: 8px;
    }
    .chat-sidebar {
        width: 100%;
        min-width: unset;
        max-height: 200px;
        border-right: none;
        border-bottom: 1px solid var(--chat-border);
        transition: max-height 0.3s ease;
    }
    .chat-sidebar.mobile-collapsed {
        max-height: 0;
        overflow: hidden;
        border-bottom: none;
    }
    .chat-content {
        height: calc(100% - 200px);
    }
    .mobile-toggle {
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    .role-tab {
        padding: 3px 4px;
        font-size: 0.5rem;
        min-width: 40px;
    }
    .role-tab i {
        font-size: 0.65rem;
    }
    .chat-contact-item .avatar {
        width: 28px;
        height: 28px;
        font-size: 0.6rem;
    }
    .chat-contact-item .avatar .online-dot {
        width: 7px;
        height: 7px;
    }
    .message-bubble {
        max-width: 85%;
        font-size: 0.75rem;
        padding: 4px 8px;
    }
    .file-message {
        min-width: 130px;
        max-width: 200px;
        padding: 6px 8px;
    }
    .file-message .file-icon {
        width: 28px;
        height: 28px;
        font-size: 0.8rem;
    }
    .file-message .file-actions a {
        font-size: 0.5rem;
        padding: 2px 6px;
    }
    .chat-content-header {
        padding: 6px 10px;
        min-height: 40px;
    }
    .chat-content-header .avatar {
        width: 28px;
        height: 28px;
        font-size: 0.6rem;
    }
    .chat-messages {
        padding: 8px 10px;
    }
}

@media (max-width: 480px) {
    .chat-container {
        height: calc(100vh - 140px);
    }
    .chat-sidebar {
        max-height: 160px;
    }
    .chat-content {
        height: calc(100% - 160px);
    }
    .role-tab {
        padding: 2px 3px;
        font-size: 0.45rem;
        min-width: 35px;
    }
    .role-tab i {
        font-size: 0.55rem;
    }
    .role-tab .role-count {
        font-size: 0.4rem;
        padding: 0 3px;
    }
    .file-message {
        min-width: 100px;
        max-width: 160px;
        padding: 4px 6px;
    }
    .file-message .file-actions a {
        font-size: 0.45rem;
        padding: 1px 4px;
    }
    .chat-input-area .input-row textarea {
        font-size: 0.7rem;
        min-height: 24px;
        padding: 4px 8px;
    }
    .chat-input-area .input-row .send-btn {
        font-size: 0.7rem;
        padding: 4px 10px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:6px;">
            <div>
                <h2 style="font-size:1rem;font-weight:700;margin:0;">
                    <i class="fas fa-comment-dots" style="color:var(--chat-primary);"></i> Chat with Agents
                </h2>
                <p style="color:var(--gray-500);font-size:0.7rem;margin:1px 0 0;">
                    <i class="fas fa-map-marker-alt" style="color:var(--gray-400);"></i> 
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                    <span style="margin:0 4px;">•</span>
                    <?php echo isset($role_definitions[$selected_role]) ? $role_definitions[$selected_role]['name'] : 'Agents'; ?>
                    <span id="connectionStatus" style="margin-left:6px;font-size:0.55rem;color:var(--chat-online);">
                        <i class="fas fa-circle" style="font-size:0.25rem;"></i> Live
                    </span>
                </p>
            </div>
            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <button class="mobile-toggle" onclick="toggleMobileSidebar()">
                    <i class="fas fa-users"></i> Contacts
                </button>
                <a href="manage-pu-agents.php" class="btn-secondary-sm" style="padding:3px 10px;border:1px solid var(--gray-200);border-radius:var(--radius);color:var(--gray-600);text-decoration:none;font-size:0.7rem;transition:all 0.2s ease;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success_message) && $show_success): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
                <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
                <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['sent'])): ?>
            <div class="alert alert-success" id="sentAlert">
                <i class="fas fa-check-circle"></i>
                <span>Message sent successfully!</span>
                <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Chat Container -->
        <div class="chat-container" id="chatContainer">
            <!-- Left Sidebar - Contacts -->
            <div class="chat-sidebar" id="chatSidebar">
                <div class="role-tabs">
                    <?php foreach ($role_definitions as $role_id => $role): ?>
                        <a href="?role=<?php echo $role_id; ?><?php echo $selected_contact_id > 0 ? '&contact_id=' . $selected_contact_id : ''; ?>" 
                           class="role-tab <?php echo $selected_role == $role_id ? 'active' : ''; ?>"
                           style="<?php echo $selected_role == $role_id ? 'border-bottom:2px solid ' . $role['color'] . ';' : ''; ?>">
                            <i class="fas <?php echo $role['icon']; ?>" style="color:<?php echo $selected_role == $role_id ? $role['color'] : 'inherit'; ?>;"></i>
                            <?php echo $role['name']; ?>
                            <span class="role-count" id="roleCount_<?php echo $role_id; ?>"><?php echo $role_counts[$role_id] ?? 0; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <div class="chat-sidebar-header">
                    <h3><i class="fas fa-user-check"></i> Contacts</h3>
                    <span class="badge" id="contactBadge"><?php echo count($contacts); ?></span>
                </div>
                <div class="chat-sidebar-search">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="contactSearch" placeholder="Search contacts..." onkeyup="filterContacts()">
                    </div>
                </div>
                <div class="chat-contact-list" id="contactList">
                    <?php if (count($contacts) > 0): ?>
                        <?php foreach ($contacts as $contact): 
                            $is_online = isset($contact['is_online']) ? (int)$contact['is_online'] > 0 : false;
                            $unread = isset($contact['unread_count']) ? (int)$contact['unread_count'] : 0;
                            $initial = strtoupper(substr($contact['full_name'] ?? 'U', 0, 2));
                            $avatar = !empty($contact['photograph_url']) ? $contact['photograph_url'] : '';
                            $last_msg = isset($contact['last_message']) ? $contact['last_message'] : 'No messages yet';
                            $last_time = isset($contact['last_message_time']) && $contact['last_message_time'] ? date('M d, H:i', strtotime($contact['last_message_time'])) : '';
                            $role_info = isset($role_definitions[$contact['role_id']]) ? $role_definitions[$contact['role_id']] : null;
                            $role_color = $role_info ? $role_info['color'] : '#6B7280';
                            $role_name = $role_info ? $role_info['name'] : 'Agent';
                        ?>
                            <a href="?role=<?php echo $selected_role; ?>&contact_id=<?php echo $contact['id']; ?>" 
                               class="chat-contact-item <?php echo $selected_contact_id == $contact['id'] ? 'active' : ''; ?>"
                               data-name="<?php echo strtolower($contact['full_name']); ?>"
                               data-id="<?php echo $contact['id']; ?>"
                               data-unread="<?php echo $unread; ?>">
                                <div class="avatar">
                                    <?php if ($avatar): ?>
                                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="<?php echo htmlspecialchars($contact['full_name']); ?>">
                                    <?php else: ?>
                                        <?php echo $initial; ?>
                                    <?php endif; ?>
                                    <span class="online-dot <?php echo $is_online ? 'online' : 'offline'; ?>"></span>
                                </div>
                                <div class="contact-info">
                                    <div class="name">
                                        <?php echo htmlspecialchars($contact['full_name']); ?>
                                        <span class="role-tag" style="background:<?php echo $role_color; ?>20;color:<?php echo $role_color; ?>;">
                                            <?php echo $role_name; ?>
                                        </span>
                                    </div>
                                    <div class="last-msg" id="lastMsg_<?php echo $contact['id']; ?>">
                                        <?php if ($last_msg && $last_msg !== 'No messages yet'): ?>
                                            <?php echo htmlspecialchars(substr($last_msg, 0, 50)) . (strlen($last_msg) > 50 ? '...' : ''); ?>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">No messages yet</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="contact-meta">
                                    <?php if ($last_time): ?>
                                        <div class="time" id="lastTime_<?php echo $contact['id']; ?>"><?php echo $last_time; ?></div>
                                    <?php endif; ?>
                                    <?php if ($unread > 0): ?>
                                        <div class="unread" id="unreadBadge_<?php echo $contact['id']; ?>"><?php echo $unread; ?></div>
                                    <?php endif; ?>
                                    <?php if ($is_online): ?>
                                        <div class="online-status"><i class="fas fa-circle" style="font-size:0.2rem;"></i> Online</div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:20px 12px;color:var(--gray-400);">
                            <i class="fas fa-users" style="font-size:1.2rem;display:block;margin-bottom:4px;"></i>
                            <p style="font-size:0.75rem;">No <?php echo isset($role_definitions[$selected_role]) ? strtolower($role_definitions[$selected_role]['name']) : 'agents'; ?> available</p>
                            <p style="font-size:0.6rem;margin-top:2px;">Agents will appear here once assigned.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Content - Chat Area -->
            <div class="chat-content" id="chatContent">
                <?php if ($selected_contact): ?>
                    <div class="chat-content-header">
                        <div class="avatar">
                            <?php if (!empty($selected_contact['photograph_url'])): ?>
                                <img src="<?php echo htmlspecialchars($selected_contact['photograph_url']); ?>" alt="<?php echo htmlspecialchars($selected_contact['full_name']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($selected_contact['full_name'] ?? 'U', 0, 2)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="header-info">
                            <div class="name">
                                <?php echo htmlspecialchars($selected_contact['full_name']); ?>
                                <?php 
                                $role_info = isset($role_definitions[$selected_contact['role_id']]) ? $role_definitions[$selected_contact['role_id']] : null;
                                $role_color = $role_info ? $role_info['color'] : '#6B7280';
                                ?>
                                <span style="font-size:0.55rem;padding:1px 6px;border-radius:6px;background:<?php echo $role_color; ?>20;color:<?php echo $role_color; ?>;">
                                    <?php echo $role_info ? $role_info['name'] : 'Agent'; ?>
                                </span>
                            </div>
                            <div class="status <?php echo ((int)($selected_contact['is_online'] ?? 0) > 0) ? 'online' : ''; ?>">
                                <?php if ((int)($selected_contact['is_online'] ?? 0) > 0): ?>
                                    <i class="fas fa-circle" style="font-size:0.2rem;"></i> Online
                                <?php else: ?>
                                    Last seen <?php echo $selected_contact['last_login_at'] ? date('M d, H:i', strtotime($selected_contact['last_login_at'])) : 'recently'; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="header-actions">
                            <button onclick="window.location.href='agent-profile.php?id=<?php echo $selected_contact['id']; ?>'" title="View Profile">
                                <i class="fas fa-user"></i>
                            </button>
                            <button onclick="refreshChat()" title="Refresh Chat">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php if (count($messages) > 0): ?>
                            <?php 
                            $last_date = '';
                            $last_msg_id = 0;
                            foreach ($messages as $msg): 
                                $msg_date = date('Y-m-d', strtotime($msg['created_at']));
                                $is_sent = ($msg['sender_id'] == $user_id);
                                $display_date = date('l, M d, Y', strtotime($msg['created_at']));
                                $time = date('H:i', strtotime($msg['created_at']));
                                $last_msg_id = max($last_msg_id, $msg['id']);
                                
                                if ($msg_date != $last_date): 
                                    $last_date = $msg_date;
                            ?>
                                <div class="date-divider">
                                    <span><?php echo $display_date; ?></span>
                                </div>
                            <?php endif; ?>
                                <div class="message-row <?php echo $is_sent ? 'sent' : 'received'; ?>">
                                    <div class="message-bubble" data-msg-id="<?php echo $msg['id']; ?>">
                                        <?php if (!$is_sent): ?>
                                            <span class="message-sender"><?php echo htmlspecialchars($msg['sender_name']); ?></span>
                                        <?php endif; ?>
                                        
                                        <?php
                                        // Parse file message
                                        $file_data = null;
                                        if ($msg['message_type'] === 'file' && !empty($msg['content'])) {
                                            $file_data = json_decode($msg['content'], true);
                                            if (!isset($file_data['filename']) && !empty($msg['media_url'])) {
                                                $file_data = [
                                                    'url' => $msg['media_url'],
                                                    'filename' => basename($msg['media_url']),
                                                    'filesize' => 0,
                                                    'filetype' => pathinfo($msg['media_url'], PATHINFO_EXTENSION)
                                                ];
                                            }
                                        }
                                        ?>
                                        
                                        <?php if ($msg['message_type'] === 'file' && $file_data): ?>
                                            <div class="file-message">
                                                <div style="display:flex;align-items:center;">
                                                    <?php
                                                    $ext = strtolower($file_data['filetype'] ?? pathinfo($file_data['filename'] ?? '', PATHINFO_EXTENSION));
                                                    $icon_class = 'default';
                                                    $icon_icon = 'fa-file';
                                                    if (in_array($ext, ['pdf'])) { $icon_class = 'pdf'; $icon_icon = 'fa-file-pdf'; }
                                                    elseif (in_array($ext, ['doc', 'docx'])) { $icon_class = 'doc'; $icon_icon = 'fa-file-word'; }
                                                    elseif (in_array($ext, ['xls', 'xlsx'])) { $icon_class = 'xls'; $icon_icon = 'fa-file-excel'; }
                                                    elseif (in_array($ext, ['ppt', 'pptx'])) { $icon_class = 'ppt'; $icon_icon = 'fa-file-powerpoint'; }
                                                    elseif (in_array($ext, ['txt'])) { $icon_class = 'txt'; $icon_icon = 'fa-file-alt'; }
                                                    elseif (in_array($ext, ['zip', 'rar'])) { $icon_class = 'zip'; $icon_icon = 'fa-file-archive'; }
                                                    elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) { $icon_class = 'image'; $icon_icon = 'fa-file-image'; }
                                                    ?>
                                                    <div class="file-icon <?php echo $icon_class; ?>">
                                                        <i class="fas <?php echo $icon_icon; ?>"></i>
                                                    </div>
                                                    <div class="file-info">
                                                        <div class="file-name"><?php echo htmlspecialchars($file_data['filename'] ?? 'File'); ?></div>
                                                        <div>
                                                            <span class="file-size"><?php echo $file_data['filesize'] ? formatFileSize($file_data['filesize']) : 'Unknown size'; ?></span>
                                                            <span class="file-type"><?php echo strtoupper($ext ?: 'FILE'); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="file-actions">
                                                    <a href="<?php echo htmlspecialchars($file_data['url'] ?? $msg['media_url']); ?>" download class="download">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                    <?php if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif'])): ?>
                                                        <a href="<?php echo htmlspecialchars($file_data['url'] ?? $msg['media_url']); ?>" target="_blank" class="view">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php elseif ($msg['message_type'] === 'location' && !empty($msg['content'])): 
                                            $lat = ''; $lng = ''; $locationName = '';
                                            if (preg_match('/📍 (.*?): ([\d.-]+), ([\d.-]+)/', $msg['content'], $matches)) {
                                                $locationName = $matches[1];
                                                $lat = $matches[2];
                                                $lng = $matches[3];
                                            } elseif (preg_match('/📍 Location: ([\d.-]+), ([\d.-]+)/', $msg['content'], $matches)) {
                                                $lat = $matches[1];
                                                $lng = $matches[2];
                                            }
                                        ?>
                                            <div class="location-message">
                                                <div class="location-header">
                                                    <i class="fas fa-map-marker-alt" style="color:#3B82F6;"></i>
                                                    <span>📍 Location Shared</span>
                                                </div>
                                                <?php if ($locationName && $locationName !== 'Location'): ?>
                                                    <div class="location-name">
                                                        <i class="fas fa-building" style="font-size:0.65rem;"></i>
                                                        <?php echo htmlspecialchars($locationName); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="location-details">
                                                    <?php if ($lat && $lng): ?>
                                                        <span class="coord">Lat: <?php echo $lat; ?></span>
                                                        <span class="coord" style="margin-left:6px;">Lng: <?php echo $lng; ?></span>
                                                        <br>
                                                        <a href="https://www.google.com/maps?q=<?php echo urlencode($lat . ',' . $lng); ?>" 
                                                           target="_blank" 
                                                           class="location-map-link">
                                                            <i class="fas fa-map"></i> View on Google Maps
                                                        </a>
                                                        <a href="https://www.openstreetmap.org/?mlat=<?php echo $lat; ?>&mlon=<?php echo $lng; ?>&zoom=15" 
                                                           target="_blank" 
                                                           class="location-map-link" 
                                                           style="background:#10B981;margin-left:4px;">
                                                            <i class="fas fa-globe"></i> OpenStreetMap
                                                        </a>
                                                    <?php else: ?>
                                                        <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php elseif (!empty($msg['media_url']) && $msg['message_type'] === 'image'): ?>
                                            <div style="margin:3px 0;">
                                                <img src="<?php echo htmlspecialchars($msg['media_url']); ?>" alt="Image" style="max-width:180px;border-radius:6px;cursor:pointer;" onclick="window.open(this.src)">
                                            </div>
                                            <?php if (!empty($msg['content'])): ?>
                                                <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                            <?php endif; ?>
                                        <?php elseif (!empty($msg['content'])): ?>
                                            <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                        <?php endif; ?>
                                        
                                        <span class="message-time">
                                            <?php echo $time; ?>
                                            <?php if ($is_sent): ?>
                                                <?php if ($msg['is_read'] ?? 0): ?>
                                                    <i class="fas fa-check-double" style="margin-left:2px;color:#34D399;"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-check" style="margin-left:2px;opacity:0.5;"></i>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <input type="hidden" id="lastMsgId" value="<?php echo $last_msg_id; ?>">
                        <?php else: ?>
                            <div class="empty-chat">
                                <i class="fas fa-comment"></i>
                                <h4>No Messages Yet</h4>
                                <p>Start a conversation with <?php echo htmlspecialchars($selected_contact['full_name']); ?></p>
                            </div>
                            <input type="hidden" id="lastMsgId" value="0">
                        <?php endif; ?>
                    </div>

                    <!-- Typing Indicator -->
                    <div class="typing-indicator" id="typingIndicator" style="display:none;">
                        <span>Agent is typing</span>
                        <span class="dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </span>
                    </div>

                    <!-- Chat Input -->
                    <div class="chat-input-area">
                        <form method="POST" action="" id="chatForm" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="receiver_id" value="<?php echo $selected_contact['id']; ?>">
                            <input type="hidden" name="role_id" value="<?php echo $selected_role; ?>">
                            <input type="hidden" name="message_type" id="messageType" value="text">
                            <input type="hidden" name="media_url" id="mediaUrl" value="">
                            <input type="hidden" name="media_filename" id="mediaFilename" value="">
                            <input type="hidden" name="media_filesize" id="mediaFilesize" value="0">
                            <input type="hidden" name="media_filetype" id="mediaFiletype" value="">
                            <input type="hidden" name="ajax" value="1">
                            
                            <div class="input-row">
                                <div class="input-tools">
                                    <button type="button" onclick="document.getElementById('fileInput').click()" title="Attach File">
                                        <i class="fas fa-paperclip"></i>
                                    </button>
                                    <button type="button" onclick="document.getElementById('imageInput').click()" title="Attach Image">
                                        <i class="fas fa-image"></i>
                                    </button>
                                    <button type="button" onclick="shareLocation()" title="Share Location" style="color:#3B82F6;">
                                        <i class="fas fa-location-dot"></i>
                                    </button>
                                </div>
                                <textarea name="message" id="messageInput" placeholder="Type a message..." rows="1" 
                                          onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();sendMessage();}"></textarea>
                                <button type="submit" class="send-btn" id="sendBtn">
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </div>
                            
                            <input type="file" id="fileInput" name="attachment" style="display:none" 
                                   onchange="uploadFile(this)" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar,.ppt,.pptx,.jpg,.jpeg,.png,.gif">
                            <input type="file" id="imageInput" name="attachment" style="display:none" 
                                   onchange="uploadFile(this)" accept="image/*">
                        </form>
                    </div>

                <?php else: ?>
                    <div class="empty-chat" style="height:100%;">
                        <i class="fas fa-comment-dots" style="color:var(--gray-300);"></i>
                        <h4 style="color:var(--gray-600);">Select a Contact</h4>
                        <p style="color:var(--gray-400);">Choose an agent from the sidebar to start chatting</p>
                        <?php if (count($contacts) > 0): ?>
                            <p style="font-size:0.6rem;color:var(--gray-400);margin-top:4px;">
                                <i class="fas fa-arrow-left"></i> Click on a contact on the left
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

<script>
// ============================================================
// CHAT FUNCTIONS - COMPLETE VERSION
// ============================================================

let currentContactId = <?php echo $selected_contact_id ?: 0; ?>;
let lastMsgId = parseInt(document.getElementById('lastMsgId')?.value || 0);
let isPolling = false;
let pollInterval = null;
let typingTimeout = null;

// Send message
function sendMessage() {
    const form = document.getElementById('chatForm');
    const message = document.getElementById('messageInput').value.trim();
    if (message) {
        form.submit();
    }
}

// Auto-resize textarea
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('messageInput');
    if (textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 80) + 'px';
        });
    }
    
    if (currentContactId > 0) {
        startPolling();
        // Scroll to bottom after a short delay
        setTimeout(function() {
            scrollToBottom();
        }, 300);
    }
    
    const typingIndicator = document.getElementById('typingIndicator');
    if (typingIndicator) {
        typingIndicator.style.display = 'none';
    }
});

// Scroll to bottom - FIXED
function scrollToBottom() {
    const container = document.getElementById('chatMessages');
    if (container) {
        requestAnimationFrame(function() {
            container.scrollTop = container.scrollHeight;
        });
    }
}

// ============================================================
// SHOW/HIDE TYPING INDICATOR
// ============================================================
function showTyping() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
        indicator.style.display = 'block';
        if (typingTimeout) {
            clearTimeout(typingTimeout);
        }
        typingTimeout = setTimeout(function() {
            hideTyping();
        }, 5000);
    }
}

function hideTyping() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
        indicator.style.display = 'none';
    }
    if (typingTimeout) {
        clearTimeout(typingTimeout);
        typingTimeout = null;
    }
}

// ============================================================
// SHARE LOCATION
// ============================================================
function shareLocation() {
    if (navigator.geolocation) {
        const sendBtn = document.getElementById('sendBtn');
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting location...';
        
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            getLocationName(lat, lng, function(locationName) {
                const message = `📍 ${locationName || 'Location'}: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                document.getElementById('messageInput').value = message;
                document.getElementById('messageType').value = 'location';
                
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                
                document.getElementById('chatForm').submit();
            });
        }, function(error) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
            alert('Unable to get your location. Please try again.');
        }, {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        });
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}

// ============================================================
// GET LOCATION NAME
// ============================================================
function getLocationName(lat, lng, callback) {
    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`;
    
    fetch(url, {
        headers: { 'User-Agent': 'ElectionGuruChat/1.0' }
    })
    .then(response => response.json())
    .then(data => {
        if (data && data.display_name) {
            const address = data.address || {};
            const nameParts = [];
            
            if (address.road || address.street) nameParts.push(address.road || address.street);
            if (address.suburb || address.neighbourhood) nameParts.push(address.suburb || address.neighbourhood);
            if (address.city || address.town || address.village) nameParts.push(address.city || address.town || address.village);
            if (address.state) nameParts.push(address.state);
            
            if (data.name && data.name !== '') {
                callback(data.name);
            } else if (nameParts.length > 0) {
                if (address.building || address.house_number) {
                    const building = address.building || address.house_number;
                    if (building) nameParts.unshift(building);
                }
                callback(nameParts.join(', '));
            } else {
                const shortName = data.display_name.split(',').slice(0, 3).join(', ');
                callback(shortName);
            }
        } else {
            callback(`Location (${lat.toFixed(6)}, ${lng.toFixed(6)})`);
        }
    })
    .catch(function(error) {
        console.error('Reverse geocoding error:', error);
        callback(`Location (${lat.toFixed(6)}, ${lng.toFixed(6)})`);
    });
}

// ============================================================
// FILE UPLOAD - WHATSAPP STYLE
// ============================================================
function uploadFile(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const formData = new FormData();
        formData.append('attachment', file);
        formData.append('action', 'upload_file');
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        
        const sendBtn = document.getElementById('sendBtn');
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'chat-agents.php', true);
        xhr.onload = function() {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        document.getElementById('mediaUrl').value = response.url;
                        document.getElementById('mediaFilename').value = response.filename;
                        document.getElementById('mediaFilesize').value = response.filesize;
                        document.getElementById('mediaFiletype').value = response.filetype;
                        document.getElementById('messageType').value = 'file';
                        document.getElementById('chatForm').submit();
                    } else {
                        alert('Upload failed: ' + response.message);
                    }
                } catch (e) {
                    alert('Upload failed. Please try again.');
                }
            } else {
                alert('Upload failed. Server error.');
            }
        };
        xhr.onerror = function() {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
            alert('Upload failed. Please check your connection.');
        };
        xhr.send(formData);
    }
}

// Filter contacts
function filterContacts() {
    const search = document.getElementById('contactSearch').value.toLowerCase();
    const items = document.querySelectorAll('.chat-contact-item');
    let visibleCount = 0;
    
    items.forEach(function(item) {
        const name = item.dataset.name || '';
        if (name.includes(search)) {
            item.style.display = 'flex';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
    
    document.getElementById('contactBadge').textContent = visibleCount;
}

function toggleMobileSidebar() {
    const sidebar = document.getElementById('chatSidebar');
    sidebar.classList.toggle('mobile-collapsed');
}

function refreshChat() {
    const contactId = document.querySelector('input[name="receiver_id"]');
    const roleId = document.querySelector('input[name="role_id"]');
    if (contactId && contactId.value && roleId && roleId.value) {
        window.location.href = 'chat-agents.php?role=' + roleId.value + '&contact_id=' + contactId.value;
    }
}

// ============================================================
// REAL-TIME POLLING
// ============================================================
function startPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
    
    pollInterval = setInterval(function() {
        if (!isPolling) {
            checkForNewMessages();
        }
    }, 3000);
}

function checkForNewMessages() {
    if (currentContactId <= 0) return;
    
    isPolling = true;
    
    const lastMsgId = document.getElementById('lastMsgId')?.value || 0;
    const roleId = document.querySelector('input[name="role_id"]')?.value || <?php echo $selected_role; ?>;
    
    fetch('chat-agents.php?ajax=1&contact_id=' + currentContactId + '&last_msg_id=' + lastMsgId + '&role=' + roleId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.new_messages > 0) {
                    displayNewMessages(data.messages);
                }
                if (data.contacts) {
                    updateContacts(data.contacts);
                }
                document.getElementById('connectionStatus').innerHTML = '<i class="fas fa-circle" style="font-size:0.25rem;"></i> Live';
            }
            isPolling = false;
        })
        .catch(err => {
            console.log('Polling error:', err);
            document.getElementById('connectionStatus').innerHTML = '<i class="fas fa-circle" style="font-size:0.25rem;color:#EF4444;"></i> Reconnecting...';
            isPolling = false;
        });
}

function displayNewMessages(messages) {
    const container = document.getElementById('chatMessages');
    const emptyState = container.querySelector('.empty-chat');
    
    if (emptyState) {
        emptyState.remove();
    }
    
    let lastDate = '';
    let lastMsgId = 0;
    
    messages.forEach(function(msg) {
        const msgDate = new Date(msg.created_at).toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
        const msgDateKey = new Date(msg.created_at).toDateString();
        const isSent = msg.sender_id == <?php echo $user_id; ?>;
        const time = new Date(msg.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        
        const lastDivider = container.querySelector('.date-divider:last-child');
        const lastDividerDate = lastDivider ? lastDivider.dataset.date : '';
        
        if (lastDividerDate !== msgDateKey) {
            const divider = document.createElement('div');
            divider.className = 'date-divider';
            divider.dataset.date = msgDateKey;
            divider.innerHTML = '<span>' + msgDate + '</span>';
            container.appendChild(divider);
        }
        
        const row = document.createElement('div');
        row.className = 'message-row ' + (isSent ? 'sent' : 'received');
        
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.dataset.msgId = msg.id;
        
        if (!isSent) {
            const sender = document.createElement('span');
            sender.className = 'message-sender';
            sender.textContent = msg.sender_name || 'Unknown';
            bubble.appendChild(sender);
        }
        
        // Parse file message
        let file_data = null;
        if (msg.message_type === 'file' && msg.content) {
            try {
                file_data = JSON.parse(msg.content);
                if (!file_data.filename && msg.media_url) {
                    file_data = {
                        url: msg.media_url,
                        filename: basename(msg.media_url),
                        filesize: 0,
                        filetype: msg.media_url.split('.').pop()
                    };
                }
            } catch(e) {
                if (msg.media_url) {
                    file_data = {
                        url: msg.media_url,
                        filename: basename(msg.media_url),
                        filesize: 0,
                        filetype: msg.media_url.split('.').pop()
                    };
                }
            }
        }
        
        if (msg.message_type === 'file' && file_data) {
            const ext = (file_data.filetype || '').toLowerCase();
            let iconClass = 'default', iconIcon = 'fa-file';
            if (['pdf'].includes(ext)) { iconClass = 'pdf'; iconIcon = 'fa-file-pdf'; }
            else if (['doc', 'docx'].includes(ext)) { iconClass = 'doc'; iconIcon = 'fa-file-word'; }
            else if (['xls', 'xlsx'].includes(ext)) { iconClass = 'xls'; iconIcon = 'fa-file-excel'; }
            else if (['ppt', 'pptx'].includes(ext)) { iconClass = 'ppt'; iconIcon = 'fa-file-powerpoint'; }
            else if (['txt'].includes(ext)) { iconClass = 'txt'; iconIcon = 'fa-file-alt'; }
            else if (['zip', 'rar'].includes(ext)) { iconClass = 'zip'; iconIcon = 'fa-file-archive'; }
            else if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) { iconClass = 'image'; iconIcon = 'fa-file-image'; }
            
            const fileSizeText = file_data.filesize ? formatFileSize(file_data.filesize) : 'Unknown size';
            
            const fileDiv = document.createElement('div');
            fileDiv.className = 'file-message';
            fileDiv.innerHTML = `
                <div style="display:flex;align-items:center;">
                    <div class="file-icon ${iconClass}">
                        <i class="fas ${iconIcon}"></i>
                    </div>
                    <div class="file-info">
                        <div class="file-name">${escapeHtml(file_data.filename || 'File')}</div>
                        <div>
                            <span class="file-size">${fileSizeText}</span>
                            <span class="file-type">${(ext || 'FILE').toUpperCase()}</span>
                        </div>
                    </div>
                </div>
                <div class="file-actions">
                    <a href="${escapeHtml(file_data.url || msg.media_url)}" download class="download">
                        <i class="fas fa-download"></i> Download
                    </a>
                    ${['pdf', 'jpg', 'jpeg', 'png', 'gif'].includes(ext) ? `
                        <a href="${escapeHtml(file_data.url || msg.media_url)}" target="_blank" class="view">
                            <i class="fas fa-eye"></i> View
                        </a>
                    ` : ''}
                </div>
            `;
            bubble.appendChild(fileDiv);
        } else if (msg.message_type === 'location' && msg.content) {
            let lat = '', lng = '', locationName = '';
            if (msg.content.match(/📍 (.*?): ([\d.-]+), ([\d.-]+)/)) {
                const matches = msg.content.match(/📍 (.*?): ([\d.-]+), ([\d.-]+)/);
                if (matches) {
                    locationName = matches[1];
                    lat = matches[2];
                    lng = matches[3];
                }
            } else if (msg.content.match(/📍 Location: ([\d.-]+), ([\d.-]+)/)) {
                const matches = msg.content.match(/📍 Location: ([\d.-]+), ([\d.-]+)/);
                if (matches) {
                    lat = matches[1];
                    lng = matches[2];
                }
            }
            
            const locationDiv = document.createElement('div');
            locationDiv.className = 'location-message';
            locationDiv.innerHTML = `
                <div class="location-header">
                    <i class="fas fa-map-marker-alt" style="color:#3B82F6;"></i>
                    <span>📍 Location Shared</span>
                </div>
                ${locationName && locationName !== 'Location' ? `
                    <div class="location-name">
                        <i class="fas fa-building" style="font-size:0.65rem;"></i> ${escapeHtml(locationName)}
                    </div>
                ` : ''}
                <div class="location-details">
                    ${lat && lng ? `
                        <span class="coord">Lat: ${lat}</span>
                        <span class="coord" style="margin-left:6px;">Lng: ${lng}</span>
                        <br>
                        <a href="https://www.google.com/maps?q=${encodeURIComponent(lat + ',' + lng)}" 
                           target="_blank" class="location-map-link">
                            <i class="fas fa-map"></i> View on Google Maps
                        </a>
                        <a href="https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}&zoom=15" 
                           target="_blank" class="location-map-link" 
                           style="background:#10B981;margin-left:4px;">
                            <i class="fas fa-globe"></i> OpenStreetMap
                        </a>
                    ` : nl2br(escapeHtml(msg.content))}
                </div>
            `;
            bubble.appendChild(locationDiv);
        } else if (msg.media_url && msg.message_type === 'image') {
            const imgDiv = document.createElement('div');
            imgDiv.style.cssText = 'margin:3px 0;';
            const img = document.createElement('img');
            img.src = msg.media_url;
            img.alt = 'Image';
            img.style.cssText = 'max-width:180px;border-radius:6px;cursor:pointer;';
            img.onclick = function() { window.open(this.src); };
            imgDiv.appendChild(img);
            bubble.appendChild(imgDiv);
        }
        
        if (msg.content && !['location', 'file'].includes(msg.message_type)) {
            const contentSpan = document.createElement('span');
            contentSpan.innerHTML = nl2br(escapeHtml(msg.content));
            bubble.appendChild(contentSpan);
        }
        
        const timeSpan = document.createElement('span');
        timeSpan.className = 'message-time';
        timeSpan.innerHTML = time;
        if (isSent) {
            const checkIcon = document.createElement('i');
            checkIcon.className = msg.is_read ? 'fas fa-check-double' : 'fas fa-check';
            checkIcon.style.cssText = 'margin-left:2px;' + (msg.is_read ? 'color:#34D399;' : 'opacity:0.5;');
            timeSpan.appendChild(checkIcon);
        }
        bubble.appendChild(timeSpan);
        
        row.appendChild(bubble);
        container.appendChild(row);
        
        lastMsgId = Math.max(lastMsgId, msg.id);
    });
    
    if (lastMsgId > 0) {
        document.getElementById('lastMsgId').value = lastMsgId;
    }
    
    scrollToBottom();
}

function updateContacts(contacts) {
    contacts.forEach(function(contact) {
        const lastMsgEl = document.getElementById('lastMsg_' + contact.id);
        if (lastMsgEl) {
            if (contact.last_message) {
                lastMsgEl.textContent = contact.last_message.substring(0, 50) + (contact.last_message.length > 50 ? '...' : '');
            }
        }
        
        const unreadBadge = document.getElementById('unreadBadge_' + contact.id);
        if (contact.unread_count > 0) {
            if (unreadBadge) {
                unreadBadge.textContent = contact.unread_count;
                unreadBadge.style.display = 'inline-block';
            }
        } else if (unreadBadge) {
            unreadBadge.style.display = 'none';
        }
    });
}

function formatFileSize(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' bytes';
}

function basename(path) {
    return path.split('/').pop();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function nl2br(text) {
    return text.replace(/\n/g, '<br>');
}

// ============================================================
// HANDLE FORM SUBMISSION - AJAX
// ============================================================
document.getElementById('chatForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const message = document.getElementById('messageInput').value.trim();
    const mediaUrl = document.getElementById('mediaUrl').value;
    
    if (!message && !mediaUrl) {
        return;
    }
    
    const formData = new FormData(this);
    const sendBtn = document.getElementById('sendBtn');
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    fetch('chat-agents.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
        
        try {
            const data = JSON.parse(text);
            
            if (data.success) {
                document.getElementById('messageInput').value = '';
                document.getElementById('messageInput').style.height = 'auto';
                document.getElementById('mediaUrl').value = '';
                document.getElementById('mediaFilename').value = '';
                document.getElementById('mediaFilesize').value = '0';
                document.getElementById('mediaFiletype').value = '';
                document.getElementById('messageType').value = 'text';
                
                const container = document.getElementById('chatMessages');
                const emptyState = container.querySelector('.empty-chat');
                if (emptyState) emptyState.remove();
                
                const time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                const row = document.createElement('div');
                row.className = 'message-row sent';
                
                let bubbleContent = '';
                
                if (mediaUrl) {
                    const ext = (document.getElementById('mediaFiletype').value || '').toLowerCase();
                    const filename = document.getElementById('mediaFilename').value || 'File';
                    const filesize = parseInt(document.getElementById('mediaFilesize').value) || 0;
                    
                    if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                        bubbleContent += `<div style="margin:3px 0;"><img src="${mediaUrl}" alt="Image" style="max-width:180px;border-radius:6px;cursor:pointer;" onclick="window.open(this.src)"></div>`;
                    } else {
                        let iconClass = 'default', iconIcon = 'fa-file';
                        if (['pdf'].includes(ext)) { iconClass = 'pdf'; iconIcon = 'fa-file-pdf'; }
                        else if (['doc', 'docx'].includes(ext)) { iconClass = 'doc'; iconIcon = 'fa-file-word'; }
                        else if (['xls', 'xlsx'].includes(ext)) { iconClass = 'xls'; iconIcon = 'fa-file-excel'; }
                        else if (['ppt', 'pptx'].includes(ext)) { iconClass = 'ppt'; iconIcon = 'fa-file-powerpoint'; }
                        else if (['txt'].includes(ext)) { iconClass = 'txt'; iconIcon = 'fa-file-alt'; }
                        else if (['zip', 'rar'].includes(ext)) { iconClass = 'zip'; iconIcon = 'fa-file-archive'; }
                        
                        const fileSizeText = filesize ? formatFileSize(filesize) : 'Unknown size';
                        
                        bubbleContent += `
                            <div class="file-message">
                                <div style="display:flex;align-items:center;">
                                    <div class="file-icon ${iconClass}">
                                        <i class="fas ${iconIcon}"></i>
                                    </div>
                                    <div class="file-info">
                                        <div class="file-name">${escapeHtml(filename)}</div>
                                        <div>
                                            <span class="file-size">${fileSizeText}</span>
                                            <span class="file-type">${(ext || 'FILE').toUpperCase()}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="file-actions">
                                    <a href="${mediaUrl}" download class="download">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    ${['pdf', 'jpg', 'jpeg', 'png', 'gif'].includes(ext) ? `
                                        <a href="${mediaUrl}" target="_blank" class="view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                    }
                }
                
                if (message) {
                    bubbleContent += nl2br(escapeHtml(message));
                }
                
                row.innerHTML = `
                    <div class="message-bubble">
                        ${bubbleContent}
                        <span class="message-time">${time} <i class="fas fa-check" style="margin-left:2px;opacity:0.5;"></i></span>
                    </div>
                `;
                container.appendChild(row);
                scrollToBottom();
                
                if (data.msg_id) {
                    document.getElementById('lastMsgId').value = data.msg_id;
                }
            } else {
                alert('Failed to send message: ' + (data.message || 'Unknown error'));
            }
        } catch (e) {
            if (text.includes('Message sent successfully')) {
                document.getElementById('messageInput').value = '';
                document.getElementById('messageInput').style.height = 'auto';
                document.getElementById('mediaUrl').value = '';
                document.getElementById('messageType').value = 'text';
                
                setTimeout(function() {
                    const contactId = document.querySelector('input[name="receiver_id"]');
                    const roleId = document.querySelector('input[name="role_id"]');
                    if (contactId && contactId.value && roleId && roleId.value) {
                        window.location.href = 'chat-agents.php?role=' + roleId.value + '&contact_id=' + contactId.value;
                    }
                }, 1000);
            } else {
                console.error('Unexpected response:', text);
                alert('An unexpected error occurred. Please try again.');
            }
        }
    })
    .catch(err => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
        alert('Failed to send message. Please check your connection.');
        console.error('Send error:', err);
    });
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
    scrollToBottom();
    hideTyping();
});

window.addEventListener('beforeunload', function() {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>
</body>
</html>