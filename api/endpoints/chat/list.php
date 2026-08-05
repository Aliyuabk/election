<?php
/**
 * List Chat Conversations
 * GET /api/chat/list
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

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

$db = Database::getInstance();

// Get rooms with last message
$sql = "
    SELECT 
        cr.id as room_id, cr.name as room_name, cr.type,
        (
            SELECT cm.content 
            FROM chat_messages cm 
            WHERE cm.room_id = cr.id 
            ORDER BY cm.created_at DESC 
            LIMIT 1
        ) as last_message,
        (
            SELECT cm.created_at 
            FROM chat_messages cm 
            WHERE cm.room_id = cr.id 
            ORDER BY cm.created_at DESC 
            LIMIT 1
        ) as last_message_time,
        (
            SELECT COUNT(*) 
            FROM chat_messages cm 
            WHERE cm.room_id = cr.id AND cm.receiver_id = {$user['id']} AND cm.is_read = 0
        ) as unread_count
    FROM chat_rooms cr
    JOIN chat_room_members crm ON cr.id = crm.room_id
    WHERE crm.user_id = {$user['id']}
    ORDER BY last_message_time DESC
    LIMIT $limit OFFSET $offset
";

$result = $db->query($sql);

$rooms = [];
while ($row = $result->fetch_assoc()) {
    // Get other member info for direct chats
    if ($row['type'] === 'direct') {
        $memberResult = $db->query("
            SELECT u.id, u.first_name, u.last_name, u.photograph_url
            FROM chat_room_members crm
            JOIN users u ON crm.user_id = u.id
            WHERE crm.room_id = {$row['room_id']} AND crm.user_id != {$user['id']}
            LIMIT 1
        ");
        $row['other_member'] = $memberResult->fetch_assoc();
    }
    
    $row['unread_count'] = intval($row['unread_count']);
    $rooms[] = $row;
}

Response::success([
    'conversations' => $rooms,
    'pagination' => [
        'limit' => $limit,
        'offset' => $offset
    ]
]);