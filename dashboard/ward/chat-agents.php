<?php
// ============================================================
// WARD COORDINATOR - CHAT WITH PU AGENTS
// Professional Chat Interface like WhatsApp/Telegram
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
// GET SELECTED CONTACT
// ============================================================
$selected_contact_id = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;
$selected_contact = null;
$messages = [];
$contacts = [];

// ============================================================
// FETCH CONTACTS (PU AGENTS ONLY)
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
        AND r.level = 'pu_agent'
        ORDER BY last_message_time DESC, u.full_name ASC
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $tenant_id, $ward_id, $user_id]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If a contact is selected, get their details and messages
    if ($selected_contact_id > 0) {
        foreach ($contacts as $contact) {
            if ($contact['id'] == $selected_contact_id) {
                $selected_contact = $contact;
                break;
            }
        }
        
        if ($selected_contact) {
            // Get messages between user and selected contact
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
            
            // Mark messages as read
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $message_type = isset($_POST['message_type']) ? $_POST['message_type'] : 'text';
    $media_url = isset($_POST['media_url']) ? trim($_POST['media_url']) : '';
    
    if ($receiver_id > 0 && !empty($message)) {
        try {
            // Check if chat room exists, create if not
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
                // Create new room
                $stmt = $db->prepare("
                    INSERT INTO chat_rooms (tenant_id, name, type, created_by, created_at) 
                    VALUES (?, ?, 'direct', ?, NOW())
                ");
                $room_name = "Chat between " . $user_name . " and agent";
                $stmt->execute([$tenant_id, $room_name, $user_id]);
                $room_id = $db->lastInsertId();
                
                // Add members
                $stmt = $db->prepare("INSERT INTO chat_room_members (room_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
                $stmt->execute([$room_id, $user_id]);
                $stmt->execute([$room_id, $receiver_id]);
            }
            
            // Insert message
            $stmt = $db->prepare("
                INSERT INTO chat_messages (
                    room_id, sender_id, receiver_id, message_type, content, 
                    media_url, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$room_id, $user_id, $receiver_id, $message_type, $message, $media_url]);
            
            logActivity($user_id, 'chat_message', "Sent message to PU Agent ID: $receiver_id", 'chat', $room_id);
            
            // Redirect to refresh chat
            header('Location: chat-agents.php?contact_id=' . $receiver_id . '&sent=1');
            exit();
            
        } catch (Exception $e) {
            $error_message = "Error sending message: " . $e->getMessage();
            error_log("Chat send error: " . $e->getMessage());
        }
    } else {
        $error_message = "Please enter a message.";
    }
}

// ============================================================
// HANDLE ATTACHMENT UPLOAD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attachment']) && isset($_POST['receiver_id'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $file = $_FILES['attachment'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'video/mp4', 'audio/mpeg', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $max_size = 20 * 1024 * 1024; // 20MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Upload error: " . $file['error'];
    } elseif (!in_array($file['type'], $allowed_types)) {
        $error_message = "File type not allowed.";
    } elseif ($file['size'] > $max_size) {
        $error_message = "File size exceeds 20MB limit.";
    } else {
        try {
            $upload_dir = '../../uploads/chat/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = 'chat_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $filepath = $upload_dir . $filename;
            $file_url = '/election/uploads/chat/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Determine message type
                $message_type = 'file';
                if (strpos($file['type'], 'image/') === 0) $message_type = 'image';
                elseif (strpos($file['type'], 'video/') === 0) $message_type = 'video';
                elseif (strpos($file['type'], 'audio/') === 0) $message_type = 'audio';
                
                // Send message with attachment
                $message = '📎 ' . $file['name'];
                
                // Get or create room
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
                    $room_name = "Chat between " . $user_name . " and agent";
                    $stmt->execute([$tenant_id, $room_name, $user_id]);
                    $room_id = $db->lastInsertId();
                    
                    $stmt = $db->prepare("INSERT INTO chat_room_members (room_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
                    $stmt->execute([$room_id, $user_id]);
                    $stmt->execute([$room_id, $receiver_id]);
                }
                
                $stmt = $db->prepare("
                    INSERT INTO chat_messages (
                        room_id, sender_id, receiver_id, message_type, content, 
                        media_url, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$room_id, $user_id, $receiver_id, $message_type, $message, $file_url]);
                
                logActivity($user_id, 'chat_attachment', "Sent attachment to PU Agent ID: $receiver_id", 'chat', $room_id);
                
                header('Location: chat-agents.php?contact_id=' . $receiver_id . '&sent=1');
                exit();
            }
            
        } catch (Exception $e) {
            $error_message = "Error uploading file: " . $e->getMessage();
            error_log("Chat upload error: " . $e->getMessage());
        }
    }
}

$page_title = 'Chat with PU Agents';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
/* ============================================================
   WHATSAPP/TELEGRAM STYLE CHAT INTERFACE
   ============================================================ */
.chat-container {
    display: flex;
    height: calc(100vh - 180px);
    min-height: 500px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

/* ============================================================
   LEFT SIDEBAR - CONTACT LIST
   ============================================================ */
.chat-sidebar {
    width: 340px;
    min-width: 280px;
    background: #F8FAFC;
    border-right: 1px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.chat-sidebar-header {
    padding: 16px 20px;
    background: white;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-sidebar-header h3 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
    color: var(--gray-800);
}

.chat-sidebar-header .badge {
    background: #3B82F6;
    color: white;
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 12px;
}

.chat-sidebar-search {
    padding: 10px 16px;
    background: white;
    border-bottom: 1px solid var(--gray-100);
}

.chat-sidebar-search input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid var(--gray-200);
    border-radius: 20px;
    font-size: 0.85rem;
    background: #F1F5F9;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%239CA3AF' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: 12px center;
    transition: var(--transition);
}
.chat-sidebar-search input:focus {
    outline: none;
    border-color: #3B82F6;
    background: white;
}

.chat-contact-list {
    flex: 1;
    overflow-y: auto;
    padding: 4px 0;
}

.chat-contact-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    cursor: pointer;
    transition: var(--transition);
    border-left: 3px solid transparent;
    position: relative;
}
.chat-contact-item:hover {
    background: #F1F5F9;
}
.chat-contact-item.active {
    background: #EFF6FF;
    border-left-color: #3B82F6;
}

.chat-contact-item .avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
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
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
}
.chat-contact-item .avatar .online-dot.online {
    background: #22C55E;
}
.chat-contact-item .avatar .online-dot.offline {
    background: #9CA3AF;
}

.chat-contact-item .contact-info {
    flex: 1;
    min-width: 0;
}
.chat-contact-item .contact-info .name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-800);
}
.chat-contact-item .contact-info .sub {
    font-size: 0.75rem;
    color: var(--gray-500);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chat-contact-item .contact-info .sub .role {
    font-size: 0.6rem;
    color: #3B82F6;
    background: #EFF6FF;
    padding: 1px 8px;
    border-radius: 10px;
}
.chat-contact-item .contact-info .last-msg {
    font-size: 0.75rem;
    color: var(--gray-500);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}

.chat-contact-item .contact-meta {
    text-align: right;
    flex-shrink: 0;
}
.chat-contact-item .contact-meta .time {
    font-size: 0.6rem;
    color: var(--gray-400);
}
.chat-contact-item .contact-meta .unread {
    background: #3B82F6;
    color: white;
    font-size: 0.55rem;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 600;
    margin-top: 4px;
    display: inline-block;
}
.chat-contact-item .contact-meta .online-status {
    font-size: 0.55rem;
    color: #22C55E;
}
.chat-contact-item .contact-meta .offline-status {
    font-size: 0.55rem;
    color: #9CA3AF;
}

/* ============================================================
   RIGHT CONTENT - CHAT AREA
   ============================================================ */
.chat-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #F8FAFC;
    min-width: 0;
}

/* Chat Header */
.chat-content-header {
    padding: 12px 20px;
    background: white;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}
.chat-content-header .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
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
    font-size: 0.95rem;
    color: var(--gray-800);
}
.chat-content-header .header-info .status {
    font-size: 0.7rem;
    color: var(--gray-500);
}
.chat-content-header .header-info .status.online {
    color: #22C55E;
}
.chat-content-header .header-actions {
    display: flex;
    gap: 8px;
}
.chat-content-header .header-actions button {
    padding: 6px 10px;
    border: none;
    background: none;
    cursor: pointer;
    color: var(--gray-500);
    border-radius: 6px;
    transition: var(--transition);
}
.chat-content-header .header-actions button:hover {
    background: var(--gray-100);
    color: var(--gray-700);
}

/* Chat Messages */
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.message-row {
    display: flex;
    margin-bottom: 2px;
}
.message-row.sent {
    justify-content: flex-end;
}
.message-row.received {
    justify-content: flex-start;
}

.message-bubble {
    max-width: 70%;
    padding: 8px 14px;
    border-radius: 12px;
    font-size: 0.9rem;
    line-height: 1.5;
    word-wrap: break-word;
    position: relative;
}
.message-row.sent .message-bubble {
    background: #3B82F6;
    color: white;
    border-bottom-right-radius: 4px;
}
.message-row.received .message-bubble {
    background: white;
    color: var(--gray-800);
    border: 1px solid var(--gray-200);
    border-bottom-left-radius: 4px;
}

.message-bubble .message-time {
    font-size: 0.55rem;
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
    font-size: 0.7rem;
    font-weight: 600;
    margin-bottom: 2px;
    display: block;
    color: #3B82F6;
}
.message-row.received .message-bubble .message-sender {
    color: #3B82F6;
}
.message-row.sent .message-bubble .message-sender {
    display: none;
}

.message-bubble .message-type-icon {
    margin-right: 4px;
}
.message-bubble .message-media {
    max-width: 250px;
    border-radius: 8px;
    margin: 4px 0;
}
.message-bubble .message-media img {
    max-width: 100%;
    border-radius: 8px;
}
.message-bubble .message-media video {
    max-width: 100%;
    border-radius: 8px;
}

/* Date Divider */
.date-divider {
    text-align: center;
    padding: 8px 0;
    margin: 4px 0;
}
.date-divider span {
    font-size: 0.7rem;
    color: var(--gray-400);
    background: #F1F5F9;
    padding: 4px 16px;
    border-radius: 12px;
}

/* Chat Input */
.chat-input-area {
    padding: 12px 16px;
    background: white;
    border-top: 1px solid var(--gray-200);
    flex-shrink: 0;
}
.chat-input-area .input-row {
    display: flex;
    gap: 8px;
    align-items: end;
}
.chat-input-area .input-row .input-tools {
    display: flex;
    gap: 4px;
}
.chat-input-area .input-row .input-tools button {
    padding: 6px 10px;
    border: none;
    background: none;
    cursor: pointer;
    color: var(--gray-500);
    border-radius: 6px;
    transition: var(--transition);
    font-size: 0.9rem;
}
.chat-input-area .input-row .input-tools button:hover {
    background: var(--gray-100);
    color: var(--gray-700);
}
.chat-input-area .input-row textarea {
    flex: 1;
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 20px;
    font-size: 0.9rem;
    resize: none;
    min-height: 40px;
    max-height: 120px;
    font-family: inherit;
    background: #F1F5F9;
    transition: var(--transition);
}
.chat-input-area .input-row textarea:focus {
    outline: none;
    border-color: #3B82F6;
    background: white;
}
.chat-input-area .input-row .send-btn {
    padding: 8px 16px;
    border: none;
    background: #3B82F6;
    color: white;
    border-radius: 20px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 4px;
}
.chat-input-area .input-row .send-btn:hover {
    background: #2563EB;
}
.chat-input-area .input-row .send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Typing Indicator */
.typing-indicator {
    padding: 4px 14px;
    font-size: 0.75rem;
    color: var(--gray-400);
    display: none;
}
.typing-indicator .dots {
    display: inline-block;
    animation: typingDots 1.4s infinite;
}
@keyframes typingDots {
    0%, 60%, 100% { opacity: 1; }
    30% { opacity: 0.3; }
}

/* Empty State */
.empty-chat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--gray-400);
    padding: 40px;
}
.empty-chat i {
    font-size: 4rem;
    margin-bottom: 16px;
    color: var(--gray-300);
}
.empty-chat h4 {
    margin: 0 0 8px;
    color: var(--gray-600);
}
.empty-chat p {
    margin: 0;
    font-size: 0.9rem;
    text-align: center;
    max-width: 300px;
}

/* Responsive */
@media (max-width: 768px) {
    .chat-container {
        height: calc(100vh - 160px);
        flex-direction: column;
    }
    .chat-sidebar {
        width: 100%;
        min-width: unset;
        max-height: 200px;
        border-right: none;
        border-bottom: 1px solid var(--gray-200);
    }
    .chat-content {
        height: calc(100% - 200px);
    }
    .chat-sidebar.mobile-collapsed {
        max-height: 0;
        overflow: hidden;
        border-bottom: none;
    }
    .mobile-toggle {
        display: flex !important;
    }
}

.mobile-toggle {
    display: none;
    padding: 6px 12px;
    border: none;
    background: var(--gray-100);
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
    color: var(--gray-600);
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <div>
                <h2 style="font-size:1.2rem;font-weight:700;margin:0;">
                    <i class="fas fa-comment-dots" style="color:var(--primary);"></i> Chat with PU Agents
                </h2>
                <p style="color:var(--gray-500);font-size:0.8rem;margin:2px 0 0;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • <?php echo count($contacts); ?> agents online
                </p>
            </div>
            <button class="mobile-toggle" onclick="toggleMobileSidebar()">
                <i class="fas fa-users"></i> Contacts
            </button>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" style="margin-bottom:12px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['sent'])): ?>
            <div class="alert alert-success" style="margin-bottom:12px;">
                <i class="fas fa-check-circle"></i> Message sent successfully!
            </div>
        <?php endif; ?>

        <!-- Chat Container -->
        <div class="chat-container" id="chatContainer">
            <!-- Left Sidebar - Contacts -->
            <div class="chat-sidebar" id="chatSidebar">
                <div class="chat-sidebar-header">
                    <h3><i class="fas fa-user-check"></i> PU Agents</h3>
                    <span class="badge"><?php echo count($contacts); ?></span>
                </div>
                <div class="chat-sidebar-search">
                    <input type="text" id="contactSearch" placeholder="Search contacts..." onkeyup="filterContacts()">
                </div>
                <div class="chat-contact-list" id="contactList">
                    <?php if (count($contacts) > 0): ?>
                        <?php foreach ($contacts as $contact): 
                            $is_online = (int)($contact['is_online'] ?? 0) > 0;
                            $unread = (int)($contact['unread_count'] ?? 0);
                            $initial = strtoupper(substr($contact['full_name'] ?? 'U', 0, 2));
                            $avatar = !empty($contact['photograph_url']) ? $contact['photograph_url'] : '';
                            $last_msg = $contact['last_message'] ?? 'No messages yet';
                            $last_time = $contact['last_message_time'] ? date('M d, H:i', strtotime($contact['last_message_time'])) : '';
                        ?>
                            <a href="?contact_id=<?php echo $contact['id']; ?>" 
                               class="chat-contact-item <?php echo $selected_contact_id == $contact['id'] ? 'active' : ''; ?>"
                               data-name="<?php echo strtolower($contact['full_name']); ?>">
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
                                        <span class="role"><?php echo ucfirst(str_replace('_', ' ', $contact['role_level'] ?? '')); ?></span>
                                    </div>
                                    <div class="last-msg">
                                        <?php if ($last_msg): ?>
                                            <?php echo htmlspecialchars(substr($last_msg, 0, 40)) . (strlen($last_msg) > 40 ? '...' : ''); ?>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">No messages yet</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="contact-meta">
                                    <?php if ($last_time): ?>
                                        <div class="time"><?php echo $last_time; ?></div>
                                    <?php endif; ?>
                                    <?php if ($unread > 0): ?>
                                        <div class="unread"><?php echo $unread; ?></div>
                                    <?php endif; ?>
                                    <?php if ($is_online): ?>
                                        <div class="online-status"><i class="fas fa-circle" style="font-size:0.3rem;"></i> Online</div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:40px 20px;color:var(--gray-400);">
                            <i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                            <p>No PU agents available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Content - Chat Area -->
            <div class="chat-content">
                <?php if ($selected_contact): ?>
                    <!-- Chat Header -->
                    <div class="chat-content-header">
                        <div class="avatar">
                            <?php if (!empty($selected_contact['photograph_url'])): ?>
                                <img src="<?php echo htmlspecialchars($selected_contact['photograph_url']); ?>" alt="<?php echo htmlspecialchars($selected_contact['full_name']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($selected_contact['full_name'] ?? 'U', 0, 2)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="header-info">
                            <div class="name"><?php echo htmlspecialchars($selected_contact['full_name']); ?></div>
                            <div class="status <?php echo ((int)($selected_contact['is_online'] ?? 0) > 0) ? 'online' : ''; ?>">
                                <?php if ((int)($selected_contact['is_online'] ?? 0) > 0): ?>
                                    <i class="fas fa-circle" style="font-size:0.3rem;"></i> Online
                                <?php else: ?>
                                    Last seen <?php echo $selected_contact['last_login_at'] ? date('M d, H:i', strtotime($selected_contact['last_login_at'])) : 'recently'; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="header-actions">
                            <button onclick="window.location.href='agent-profile.php?id=<?php echo $selected_contact['id']; ?>'" title="View Profile">
                                <i class="fas fa-user"></i>
                            </button>
                            <button onclick="window.location.href='agent-performance.php?id=<?php echo $selected_contact['id']; ?>'" title="View Performance">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                            <button onclick="clearChat()" title="Clear Chat">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php if (count($messages) > 0): ?>
                            <?php 
                            $last_date = '';
                            foreach ($messages as $msg): 
                                $msg_date = date('Y-m-d', strtotime($msg['created_at']));
                                $is_sent = ($msg['sender_id'] == $user_id);
                                $display_date = date('l, M d, Y', strtotime($msg['created_at']));
                                
                                if ($msg_date != $last_date): 
                                    $last_date = $msg_date;
                            ?>
                                <div class="date-divider">
                                    <span><?php echo $display_date; ?></span>
                                </div>
                            <?php endif; ?>
                                <div class="message-row <?php echo $is_sent ? 'sent' : 'received'; ?>">
                                    <div class="message-bubble">
                                        <?php if (!$is_sent): ?>
                                            <span class="message-sender"><?php echo htmlspecialchars($msg['sender_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (($msg['message_type'] ?? 'text') !== 'text'): ?>
                                            <span class="message-type-icon">
                                                <?php if ($msg['message_type'] === 'image'): ?>📷
                                                <?php elseif ($msg['message_type'] === 'video'): ?>🎬
                                                <?php elseif ($msg['message_type'] === 'audio'): ?>🎵
                                                <?php elseif ($msg['message_type'] === 'file'): ?>📄
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($msg['media_url'])): ?>
                                            <?php if ($msg['message_type'] === 'image'): ?>
                                                <div class="message-media">
                                                    <img src="<?php echo htmlspecialchars($msg['media_url']); ?>" alt="Image" onclick="window.open(this.src)">
                                                </div>
                                            <?php elseif ($msg['message_type'] === 'video'): ?>
                                                <div class="message-media">
                                                    <video controls>
                                                        <source src="<?php echo htmlspecialchars($msg['media_url']); ?>">
                                                    </video>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php echo nl2br(htmlspecialchars($msg['content'] ?? '')); ?>
                                        <span class="message-time">
                                            <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                            <?php if ($is_sent && ($msg['is_read'] ?? 0)): ?>
                                                <i class="fas fa-check-double" style="margin-left:4px;color:#34D399;"></i>
                                            <?php elseif ($is_sent): ?>
                                                <i class="fas fa-check" style="margin-left:4px;opacity:0.5;"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-chat">
                                <i class="fas fa-comment"></i>
                                <h4>No Messages Yet</h4>
                                <p>Start a conversation with <?php echo htmlspecialchars($selected_contact['full_name']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Typing Indicator -->
                    <div class="typing-indicator" id="typingIndicator">
                        <i class="fas fa-circle" style="font-size:0.3rem;color:#3B82F6;"></i>
                        <span>Agent is typing</span>
                        <span class="dots">...</span>
                    </div>

                    <!-- Chat Input -->
                    <div class="chat-input-area">
                        <form method="POST" action="" id="chatForm" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="receiver_id" value="<?php echo $selected_contact['id']; ?>">
                            <input type="hidden" name="message_type" id="messageType" value="text">
                            <input type="hidden" name="media_url" id="mediaUrl" value="">
                            
                            <div class="input-row">
                                <div class="input-tools">
                                    <button type="button" onclick="document.getElementById('fileInput').click()" title="Attach File">
                                        <i class="fas fa-paperclip"></i>
                                    </button>
                                    <button type="button" onclick="document.getElementById('imageInput').click()" title="Attach Image">
                                        <i class="fas fa-image"></i>
                                    </button>
                                    <button type="button" onclick="shareLocation()" title="Share Location">
                                        <i class="fas fa-location-dot"></i>
                                    </button>
                                </div>
                                <textarea name="message" id="messageInput" placeholder="Type a message..." rows="1" 
                                          onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();sendMessage();}"></textarea>
                                <button type="submit" class="send-btn" id="sendBtn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                            
                            <!-- Hidden file inputs -->
                            <input type="file" id="fileInput" name="attachment" style="display:none" 
                                   onchange="uploadFile(this, 'file')" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar">
                            <input type="file" id="imageInput" name="attachment" style="display:none" 
                                   onchange="uploadFile(this, 'image')" accept="image/*">
                        </form>
                    </div>

                <?php else: ?>
                    <!-- No Contact Selected -->
                    <div class="empty-chat" style="height:100%;">
                        <i class="fas fa-comment-dots" style="color:var(--gray-300);"></i>
                        <h4 style="color:var(--gray-600);">Select a PU Agent</h4>
                        <p style="color:var(--gray-400);">Choose an agent from the sidebar to start chatting</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
// ============================================================
// CHAT FUNCTIONS
// ============================================================

// Send message
function sendMessage() {
    const form = document.getElementById('chatForm');
    const message = document.getElementById('messageInput').value.trim();
    if (message) {
        form.submit();
    }
}

// Auto-resize textarea
document.getElementById('messageInput').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Scroll to bottom
function scrollToBottom() {
    const container = document.getElementById('chatMessages');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Upload file
function uploadFile(input, type) {
    if (input.files && input.files[0]) {
        const formData = new FormData();
        formData.append('attachment', input.files[0]);
        formData.append('receiver_id', document.querySelector('input[name="receiver_id"]').value);
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'chat-agents.php', true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                window.location.reload();
            }
        };
        xhr.send(formData);
    }
}

// Share location
function shareLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const message = `📍 Location: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            document.getElementById('messageInput').value = message;
            document.getElementById('messageType').value = 'location';
            document.getElementById('chatForm').submit();
        }, function() {
            alert('Unable to get your location. Please try again.');
        });
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}

// Filter contacts
function filterContacts() {
    const search = document.getElementById('contactSearch').value.toLowerCase();
    const items = document.querySelectorAll('.chat-contact-item');
    
    items.forEach(function(item) {
        const name = item.dataset.name || '';
        if (name.includes(search)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// Toggle mobile sidebar
function toggleMobileSidebar() {
    const sidebar = document.getElementById('chatSidebar');
    sidebar.classList.toggle('mobile-collapsed');
}

// Clear chat (placeholder)
function clearChat() {
    if (confirm('Clear all messages in this conversation?')) {
        // Implement clear chat functionality
        alert('Chat cleared (placeholder)');
    }
}

// Auto-scroll on load
window.addEventListener('load', function() {
    scrollToBottom();
    
    // Auto-refresh messages every 30 seconds
    setInterval(function() {
        const contactId = document.querySelector('input[name="receiver_id"]');
        if (contactId && contactId.value) {
            // Refresh page to get new messages
            // Could implement AJAX polling here
        }
    }, 30000);
    
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE (from parent)
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