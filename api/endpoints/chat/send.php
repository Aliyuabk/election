<?php
/**
 * Send Chat Message
 * POST /api/chat/send
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';
require_once dirname(__DIR__, 2) . '/includes/validation.php';

$auth = new Auth();
$user = $auth->authenticate();

if (!$user) {
    Response::unauthorized();
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data) {
    Response::error('Invalid request data', 400);
}

$errors = Validator::required($data, ['receiver_id', 'content']);
if (!empty($errors)) {
    Response::validationError($errors);
}

$receiverId = intval($data['receiver_id']);
$content = Validator::sanitize($data['content']);
$messageType = isset($data['message_type']) ? Validator::sanitize($data['message_type']) : 'text';
$mediaUrl = isset($data['media_url']) ? Validator::sanitize($data['media_url']) : null;
$gpsLat = isset($data['gps_lat']) ? floatval($data['gps_lat']) : null;
$gpsLng = isset($data['gps_lng']) ? floatval($data['gps_lng']) : null;
$isOfflineSync = isset($data['is_offline_sync']) ? 1 : 0;

$validTypes = ['text', 'image', 'video', 'audio', 'file', 'location', 'system'];
if (!in_array($messageType, $validTypes)) {
    $messageType = 'text';
}

$db = Database::getInstance();

// Verify receiver exists and is in same tenant or allowed
$stmt = $db->prepare("
    SELECT id, tenant_id FROM users WHERE id = ? AND status = 'active'
");
$stmt->bind_param("i", $receiverId);
$stmt->execute();
$result = $stmt->get_result();
$receiver = $result->fetch_assoc();
$stmt->close();

if (!$receiver) {
    Response::error('Receiver not found', 404);
}

// Check if receiver is in same tenant or sender has permission
if ($receiver['tenant_id'] != $user['tenant_id']) {
    // Check if sender is admin/coordinator
    $roleCheck = $db->query("
        SELECT level FROM roles WHERE id = {$user['role_id']}
    ");
    $role = $roleCheck->fetch_assoc();
    $adminLevels = ['super_admin', 'client_admin', 'national', 'state', 'lga'];
    
    if (!in_array($role['level'] ?? '', $adminLevels)) {
        Response::error('Cannot message users from different organizations', 403);
    }
}

// Find or create chat room
$stmt = $db->prepare("
    SELECT id FROM chat_rooms 
    WHERE type = 'direct' 
    AND id IN (
        SELECT room_id FROM chat_room_members 
        WHERE user_id = ?
    ) AND id IN (
        SELECT room_id FROM chat_room_members 
        WHERE user_id = ?
    )
");
$stmt->bind_param("ii", $user['id'], $receiverId);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();
$stmt->close();

if (!$room) {
    // Create new room
    $roomName = 'Chat between ' . $user['first_name'] . ' ' . $user['last_name'] . 
                ' and ' . $receiver['first_name'] . ' ' . $receiver['last_name'];
    
    $stmt = $db->prepare("
        INSERT INTO chat_rooms (tenant_id, name, type, created_by, created_at)
        VALUES (?, ?, 'direct', ?, NOW())
    ");
    $tenantId = $user['tenant_id'];
    $stmt->bind_param("isi", $tenantId, $roomName, $user['id']);
    $stmt->execute();
    $roomId = $db->lastInsertId();
    $stmt->close();
    
    // Add members
    $stmt = $db->prepare("
        INSERT INTO chat_room_members (room_id, user_id, joined_at)
        VALUES (?, ?, NOW())
    ");
    
    $stmt->bind_param("ii", $roomId, $user['id']);
    $stmt->execute();
    
    $stmt->bind_param("ii", $roomId, $receiverId);
    $stmt->execute();
    $stmt->close();
} else {
    $roomId = $room['id'];
}

// Insert message
$stmt = $db->prepare("
    INSERT INTO chat_messages (
        room_id, sender_id, receiver_id, message_type, content,
        media_url, gps_lat, gps_lng, is_offline_sync, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param(
    "iiisssddi",
    $roomId, $user['id'], $receiverId, $messageType, $content,
    $mediaUrl, $gpsLat, $gpsLng, $isOfflineSync
);

if (!$stmt->execute()) {
    Response::error('Failed to send message', 500);
}

$messageId = $db->lastInsertId();
$stmt->close();

// Create notification for receiver
$db->query("
    INSERT INTO notifications (user_id, type, title, message, created_at)
    VALUES (
        $receiverId, 'chat',
        'New message from ' . {$user['first_name']},
        '$content',
        NOW()
    )
");

Response::success([
    'message_id' => $messageId,
    'room_id' => $roomId,
    'sender_id' => $user['id'],
    'receiver_id' => $receiverId,
    'content' => $content,
    'message_type' => $messageType,
    'created_at' => date('Y-m-d H:i:s')
], 'Message sent successfully');
?>