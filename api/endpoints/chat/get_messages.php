<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Only GET method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

$userId = validateToken();

$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if ($roomId <= 0) {
    sendError('Room ID is required', HTTP_BAD_REQUEST);
}

try {
    $conn = getDBConnection();
    
    // Check if user is member of room
    $checkStmt = $conn->prepare("
        SELECT id FROM chat_room_members 
        WHERE room_id = ? AND user_id = ?
    ");
    $checkStmt->bind_param("ii", $roomId, $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        sendError('You are not a member of this chat room', HTTP_FORBIDDEN);
    }
    $checkStmt->close();
    
    // Get messages
    $stmt = $conn->prepare("
        SELECT cm.*, 
               u.first_name as sender_first_name, u.last_name as sender_last_name,
               u.avatar as sender_avatar
        FROM chat_messages cm
        LEFT JOIN users u ON cm.sender_id = u.id
        WHERE cm.room_id = ? AND cm.is_deleted = 0
        ORDER BY cm.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $roomId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    // Mark messages as read
    $updateStmt = $conn->prepare("
        UPDATE chat_messages 
        SET is_read = 1 
        WHERE room_id = ? AND sender_id != ? AND is_read = 0
    ");
    $updateStmt->bind_param("ii", $roomId, $userId);
    $updateStmt->execute();
    $updateStmt->close();
    
    $stmt->close();
    $conn->close();
    
    sendSuccess('Messages retrieved successfully', [
        'messages' => array_reverse($messages),
        'total' => count($messages)
    ]);
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}