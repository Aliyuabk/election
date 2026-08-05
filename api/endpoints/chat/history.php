<?php
/**
 * Get Chat History
 * GET /api/chat/history?room_id={room_id}
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/Response.php';

$auth = new Auth();
$user = $auth->authenticate();

if (!$user) {
    Response::unauthorized();
}

$roomId = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

if ($roomId <= 0) {
    Response::error('Room ID is required', 400);
}

$db = Database::getInstance();

// Verify user is in room
$stmt = $db->prepare("
    SELECT id FROM chat_room_members 
    WHERE room_id = ? AND user_id = ?
");
$stmt->bind_param("ii", $roomId, $user['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    Response::error('Access denied to this conversation', 403);
}
$stmt->close();

$sql = "
    SELECT 
        cm.id, cm.sender_id, cm.receiver_id, cm.message_type,
        cm.content, cm.media_url, cm.gps_lat, cm.gps_lng,
        cm.is_read, cm.is_deleted, cm.created_at,
        u.first_name, u.last_name, u.photograph_url
    FROM chat_messages cm
    LEFT JOIN users u ON cm.sender_id = u.id
    WHERE cm.room_id = $roomId
    ORDER BY cm.created_at DESC LIMIT $limit OFFSET $offset
";

$result = $db->query($sql);

$messages = [];
while ($row = $result->fetch_assoc()) {
    $row['is_read'] = (bool)$row['is_read'];
    $row['is_deleted'] = (bool)$row['is_deleted'];
    $row['sender_name'] = $row['first_name'] . ' ' . $row['last_name'];
    unset($row['first_name'], $row['last_name']);
    $messages[] = $row;
}

// Mark messages as read
$db->query("
    UPDATE chat_messages 
    SET is_read = 1, read_at = NOW()
    WHERE room_id = $roomId 
    AND receiver_id = {$user['id']} 
    AND is_read = 0
");

// Reverse to chronological order
$messages = array_reverse($messages);

Response::success([
    'messages' => $messages,
    'pagination' => [
        'limit' => $limit,
        'offset' => $offset
    ]
]);