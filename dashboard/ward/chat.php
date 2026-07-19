<?php
// ============================================================
// WARD COORDINATOR - CHAT INTERFACE
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
// HANDLE MESSAGE SENDING
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'send_message') {
        $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        $message_type = isset($_POST['message_type']) ? $_POST['message_type'] : 'text';
        $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
        
        if ($receiver_id > 0 && !empty($message)) {
            try {
                // Check if room exists, create if not
                if ($room_id == 0) {
                    // Check for existing direct chat room
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
                        $stmt->execute([$tenant_id, "Chat between {$user_name} and user", $user_id]);
                        $room_id = $db->lastInsertId();
                        
                        // Add members
                        $stmt = $db->prepare("INSERT INTO chat_room_members (room_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
                        $stmt->execute([$room_id, $user_id]);
                        $stmt->execute([$room_id, $receiver_id]);
                    }
                }
                
                // Insert message
                $stmt = $db->prepare("
                    INSERT INTO chat_messages (
                        room_id, sender_id, receiver_id, message_type, content, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$room_id, $user_id, $receiver_id, $message_type, $message]);
                
                // Log activity
                logActivity($user_id, 'chat_message', "Sent message to user ID: $receiver_id", 'chat', $room_id);
                
                $success_message = "Message sent successfully.";
                
            } catch (Exception $e) {
                $error_message = "Error sending message: " . $e->getMessage();
                error_log("Chat error: " . $e->getMessage());
            }
        } else {
            $error_message = "Please enter a message and select a recipient.";
        }
    }
}

// ============================================================
// FETCH CONTACTS
// ============================================================
$contacts = [];
$contact_categories = [
    'pu_agents' => [],
    'party_agents' => [],
    'observers' => [],
    'volunteers' => []
];

try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.photograph_url,
            u.pu_id,
            pu.name as pu_name,
            r.level as role_level,
            r.name as role_name,
            (SELECT COUNT(*) FROM chat_messages cm WHERE cm.sender_id = u.id AND cm.receiver_id = ? AND cm.is_deleted = 0) as unread_count,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND u.status = 'active'
        AND u.id != ?
        AND r.level IN ('pu_agent', 'party_agent', 'observer', 'volunteer')
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$user_id, $tenant_id, $ward_id, $user_id]);
    $all_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_contacts as $contact) {
        $role = $contact['role_level'] ?? 'other';
        if ($role === 'pu_agent') {
            $contact_categories['pu_agents'][] = $contact;
        } elseif ($role === 'party_agent') {
            $contact_categories['party_agents'][] = $contact;
        } elseif ($role === 'observer') {
            $contact_categories['observers'][] = $contact;
        } elseif ($role === 'volunteer') {
            $contact_categories['volunteers'][] = $contact;
        }
    }
    
    $contacts = $all_contacts;
    
} catch (Exception $e) {
    error_log("Error fetching contacts: " . $e->getMessage());
}

// ============================================================
// FETCH RECENT CHAT MESSAGES
// ============================================================
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$messages = [];
$selected_user = null;

if ($selected_user_id > 0) {
    try {
        // Get selected user details
        $stmt = $db->prepare("
            SELECT id, full_name, email, phone, photograph_url, status, pu_id,
                   (SELECT name FROM polling_units WHERE id = pu_id) as pu_name
            FROM users WHERE id = ? AND tenant_id = ? AND ward_id = ?
        ");
        $stmt->execute([$selected_user_id, $tenant_id, $ward_id]);
        $selected_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get messages
        $stmt = $db->prepare("
            SELECT 
                cm.*,
                u.full_name as sender_name,
                u.photograph_url as sender_photo
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE (cm.sender_id = ? AND cm.receiver_id = ?)
               OR (cm.sender_id = ? AND cm.receiver_id = ?)
            AND cm.is_deleted = 0
            ORDER BY cm.created_at ASC
            LIMIT 100
        ");
        $stmt->execute([$user_id, $selected_user_id, $selected_user_id, $user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark messages as read
        $stmt = $db->prepare("
            UPDATE chat_messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->execute([$selected_user_id, $user_id]);
        
    } catch (Exception $e) {
        error_log("Error fetching messages: " . $e->getMessage());
    }
}

$page_title = 'Chat';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.chat-container {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 16px;
    height: calc(100vh - 200px);
    min-height: 500px;
}

/* Contacts Sidebar */
.contacts-sidebar {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.contacts-sidebar .sidebar-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-200);
    font-weight: 600;
    font-size: 0.9rem;
    background: var(--gray-50);
}
.contacts-sidebar .sidebar-search {
    padding: 10px 12px;
    border-bottom: 1px solid var(--gray-100);
}
.contacts-sidebar .sidebar-search input {
    width: 100%;
    padding: 6px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.8rem;
}
.contacts-sidebar .contacts-list {
    flex: 1;
    overflow-y: auto;
}
.contact-group {
    padding: 8px 0;
}
.contact-group .group-label {
    padding: 4px 16px;
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    cursor: pointer;
    transition: var(--transition);
    border-left: 3px solid transparent;
}
.contact-item:hover {
    background: var(--gray-50);
}
.contact-item.active {
    background: #EFF6FF;
    border-left-color: var(--primary);
}
.contact-item .avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    color: var(--gray-600);
    flex-shrink: 0;
    position: relative;
}
.contact-item .avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.contact-item .avatar .online-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 2px solid white;
}
.contact-item .avatar .online-dot.online {
    background: #10B981;
}
.contact-item .avatar .online-dot.offline {
    background: #9CA3AF;
}
.contact-item .info {
    flex: 1;
    min-width: 0;
}
.contact-item .info .name {
    font-weight: 500;
    font-size: 0.82rem;
}
.contact-item .info .sub {
    font-size: 0.7rem;
    color: var(--gray-400);
}
.contact-item .info .sub .role {
    color: var(--gray-500);
}
.contact-item .badge {
    background: #3B82F6;
    color: white;
    font-size: 0.6rem;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 600;
}

/* Chat Area */
.chat-area {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.chat-area .chat-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--gray-50);
}
.chat-area .chat-header .avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--gray-300);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.7rem;
    color: var(--gray-600);
}
.chat-area .chat-header .info .name {
    font-weight: 600;
    font-size: 0.85rem;
}
.chat-area .chat-header .info .status {
    font-size: 0.65rem;
    color: var(--gray-400);
}
.chat-area .chat-header .info .status.online {
    color: #10B981;
}
.chat-area .chat-messages {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    background: #F8FAFC;
}
.message {
    display: flex;
    margin-bottom: 12px;
}
.message.sent {
    justify-content: flex-end;
}
.message.received {
    justify-content: flex-start;
}
.message .bubble {
    max-width: 70%;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 0.85rem;
    word-wrap: break-word;
}
.message.sent .bubble {
    background: #3B82F6;
    color: white;
    border-bottom-right-radius: 4px;
}
.message.received .bubble {
    background: white;
    color: var(--gray-800);
    border: 1px solid var(--gray-200);
    border-bottom-left-radius: 4px;
}
.message .bubble .time {
    font-size: 0.55rem;
    opacity: 0.7;
    margin-top: 4px;
    display: block;
    text-align: right;
}
.message .bubble .sender {
    font-size: 0.65rem;
    font-weight: 600;
    margin-bottom: 4px;
    display: block;
}
.message .avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.6rem;
    color: var(--gray-600);
    flex-shrink: 0;
    margin: 0 8px;
}
.message.received .avatar {
    order: -1;
}
.message.sent .avatar {
    display: none;
}

.message .message-type-icon {
    font-size: 0.7rem;
    margin-right: 4px;
}

.chat-area .chat-input {
    padding: 12px 16px;
    border-top: 1px solid var(--gray-200);
    background: white;
}
.chat-area .chat-input .input-row {
    display: flex;
    gap: 8px;
    align-items: end;
}
.chat-area .chat-input textarea {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    resize: none;
    min-height: 40px;
    max-height: 120px;
    font-family: inherit;
}
.chat-area .chat-input .input-tools {
    display: flex;
    gap: 4px;
}
.chat-area .chat-input .input-tools button {
    padding: 6px 10px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    background: white;
    cursor: pointer;
    transition: var(--transition);
    font-size: 0.8rem;
}
.chat-area .chat-input .input-tools button:hover {
    background: var(--gray-50);
}
.chat-area .chat-input .input-tools button.send-btn {
    background: #3B82F6;
    color: white;
    border-color: #3B82F6;
}
.chat-area .chat-input .input-tools button.send-btn:hover {
    background: #2563EB;
}

.empty-chat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--gray-400);
}
.empty-chat i {
    font-size: 3rem;
    margin-bottom: 12px;
}
.empty-chat h4 {
    margin: 0 0 4px;
    color: var(--gray-600);
}
.empty-chat p {
    margin: 0;
    font-size: 0.85rem;
}

@media (max-width: 768px) {
    .chat-container {
        grid-template-columns: 1fr;
        height: calc(100vh - 180px);
    }
    .contacts-sidebar {
        display: none;
    }
    .contacts-sidebar.mobile-show {
        display: flex;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1000;
        border-radius: 0;
    }
    .chat-area .chat-header .back-btn {
        display: block;
    }
}
.chat-area .chat-header .back-btn {
    display: none;
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="broadcast-header" style="margin-bottom:16px;">
            <div>
                <h2><i class="fas fa-comment-dots"></i> Chat</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" style="margin-bottom:12px;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" style="margin-bottom:12px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Chat Container -->
        <div class="chat-container">
            <!-- Contacts Sidebar -->
            <div class="contacts-sidebar" id="contactsSidebar">
                <div class="sidebar-header">
                    <i class="fas fa-users"></i> Contacts
                </div>
                <div class="sidebar-search">
                    <input type="text" id="contactSearch" placeholder="Search contacts..." onkeyup="filterContacts()">
                </div>
                <div class="contacts-list" id="contactsList">
                    <?php 
                    $categories = [
                        'pu_agents' => ['label' => 'PU Agents', 'icon' => 'fa-user-check'],
                        'party_agents' => ['label' => 'Party Agents', 'icon' => 'fa-flag'],
                        'observers' => ['label' => 'Observers', 'icon' => 'fa-eye'],
                        'volunteers' => ['label' => 'Volunteers', 'icon' => 'fa-hands-helping']
                    ];
                    
                    foreach ($categories as $key => $cat):
                        if (empty($contact_categories[$key])) continue;
                    ?>
                        <div class="contact-group">
                            <div class="group-label"><i class="fas <?php echo $cat['icon']; ?>"></i> <?php echo $cat['label']; ?></div>
                            <?php foreach ($contact_categories[$key] as $contact): 
                                $is_online = (int)($contact['is_online'] ?? 0) > 0;
                                $initial = strtoupper(substr($contact['full_name'] ?? 'U', 0, 2));
                                $is_active = ($selected_user_id == $contact['id']);
                            ?>
                                <a href="?user_id=<?php echo $contact['id']; ?>" class="contact-item <?php echo $is_active ? 'active' : ''; ?>" data-name="<?php echo strtolower($contact['full_name']); ?>">
                                    <div class="avatar">
                                        <?php if (!empty($contact['photograph_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($contact['photograph_url']); ?>" alt="<?php echo htmlspecialchars($contact['full_name']); ?>">
                                        <?php else: ?>
                                            <?php echo $initial; ?>
                                        <?php endif; ?>
                                        <span class="online-dot <?php echo $is_online ? 'online' : 'offline'; ?>"></span>
                                    </div>
                                    <div class="info">
                                        <div class="name"><?php echo htmlspecialchars($contact['full_name']); ?></div>
                                        <div class="sub">
                                            <span class="role"><?php echo ucfirst(str_replace('_', ' ', $contact['role_level'] ?? '')); ?></span>
                                            <?php if (!empty($contact['pu_name'])): ?>
                                                • <?php echo htmlspecialchars($contact['pu_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (($contact['unread_count'] ?? 0) > 0): ?>
                                        <span class="badge"><?php echo $contact['unread_count']; ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($contacts)): ?>
                        <div style="text-align:center;padding:30px;color:var(--gray-400);">
                            <i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                            <p style="font-size:0.85rem;">No contacts available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="chat-area">
                <?php if ($selected_user): ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <button class="back-btn" onclick="toggleMobileContacts()">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <div class="avatar">
                            <?php echo strtoupper(substr($selected_user['full_name'] ?? 'U', 0, 2)); ?>
                        </div>
                        <div class="info">
                            <div class="name"><?php echo htmlspecialchars($selected_user['full_name']); ?></div>
                            <div class="status online">
                                <i class="fas fa-circle" style="font-size:0.4rem;"></i> Online
                            </div>
                        </div>
                        <div style="margin-left:auto;display:flex;gap:6px;">
                            <a href="chat-search.php?user_id=<?php echo $selected_user_id; ?>" class="btn-secondary-sm" style="padding:4px 8px;font-size:0.7rem;">
                                <i class="fas fa-search"></i>
                            </a>
                            <a href="chat-download.php?user_id=<?php echo $selected_user_id; ?>" class="btn-secondary-sm" style="padding:4px 8px;font-size:0.7rem;">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php if (count($messages) > 0): ?>
                            <?php foreach ($messages as $msg): 
                                $is_sent = ($msg['sender_id'] == $user_id);
                            ?>
                                <div class="message <?php echo $is_sent ? 'sent' : 'received'; ?>">
                                    <?php if (!$is_sent): ?>
                                        <div class="avatar">
                                            <?php echo strtoupper(substr($msg['sender_name'] ?? 'U', 0, 2)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="bubble">
                                        <?php if (!$is_sent): ?>
                                            <span class="sender"><?php echo htmlspecialchars($msg['sender_name'] ?? ''); ?></span>
                                        <?php endif; ?>
                                        <?php if (($msg['message_type'] ?? 'text') !== 'text'): ?>
                                            <span class="message-type-icon">
                                                <?php if ($msg['message_type'] === 'image'): ?>📷
                                                <?php elseif ($msg['message_type'] === 'video'): ?>🎬
                                                <?php elseif ($msg['message_type'] === 'audio'): ?>🎵
                                                <?php elseif ($msg['message_type'] === 'file'): ?>📄
                                                <?php elseif ($msg['message_type'] === 'location'): ?>📍
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php echo nl2br(htmlspecialchars($msg['content'] ?? '')); ?>
                                        <span class="time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-chat">
                                <i class="fas fa-comment"></i>
                                <h4>No Messages</h4>
                                <p>Start a conversation with <?php echo htmlspecialchars($selected_user['full_name']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Chat Input -->
                    <div class="chat-input">
                        <form method="POST" action="" id="chatForm" onsubmit="return validateChatForm()">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="receiver_id" value="<?php echo $selected_user_id; ?>">
                            <input type="hidden" name="room_id" value="0">
                            <input type="hidden" name="message_type" value="text">
                            
                            <div class="input-row">
                                <div class="input-tools">
                                    <button type="button" onclick="attachFile()" title="Attach File">
                                        <i class="fas fa-paperclip"></i>
                                    </button>
                                    <button type="button" onclick="attachImage()" title="Attach Image">
                                        <i class="fas fa-image"></i>
                                    </button>
                                    <button type="button" onclick="shareLocation()" title="Share Location">
                                        <i class="fas fa-location-dot"></i>
                                    </button>
                                </div>
                                <textarea name="message" id="messageInput" placeholder="Type a message..." rows="1" 
                                          onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();document.getElementById('chatForm').submit();}"></textarea>
                                <div class="input-tools">
                                    <button type="submit" class="send-btn">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                <?php else: ?>
                    <!-- No Chat Selected -->
                    <div class="empty-chat" style="height:100%;">
                        <i class="fas fa-comment-dots"></i>
                        <h4>Select a Contact</h4>
                        <p>Choose a contact from the sidebar to start chatting</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
// Filter contacts
function filterContacts() {
    const search = document.getElementById('contactSearch').value.toLowerCase();
    const items = document.querySelectorAll('.contact-item');
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
    
    // Show/hide groups
    document.querySelectorAll('.contact-group').forEach(function(group) {
        const visibleItems = group.querySelectorAll('.contact-item[style*="display: flex"]');
        const label = group.querySelector('.group-label');
        if (label) {
            label.style.display = visibleItems.length > 0 ? 'block' : 'none';
        }
        group.style.display = visibleItems.length > 0 ? 'block' : 'none';
    });
}

// Validate chat form
function validateChatForm() {
    const message = document.getElementById('messageInput').value.trim();
    if (!message) {
        alert('Please enter a message.');
        return false;
    }
    return true;
}

// Attach file (placeholder)
function attachFile() {
    alert('File attachment feature coming soon. You can attach images, documents, and videos.');
}

// Attach image (placeholder)
function attachImage() {
    alert('Image attachment feature coming soon.');
}

// Share location (placeholder)
function shareLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const message = `📍 Location: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            document.getElementById('messageInput').value = message;
            document.querySelector('input[name="message_type"]').value = 'location';
            document.getElementById('chatForm').submit();
        }, function() {
            alert('Unable to get your location. Please try again.');
        });
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}

// Auto-resize textarea
document.getElementById('messageInput').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Scroll to bottom of messages
function scrollToBottom() {
    const container = document.getElementById('chatMessages');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Toggle mobile contacts
function toggleMobileContacts() {
    const sidebar = document.getElementById('contactsSidebar');
    sidebar.classList.toggle('mobile-show');
}

// Auto-scroll on load
window.addEventListener('load', function() {
    scrollToBottom();
    
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