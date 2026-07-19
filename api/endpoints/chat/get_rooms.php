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
$userData = getUserData($userId);

if (!$userData) {
    sendError('User not found', HTTP_NOT_FOUND);
}

try {
    $conn = getDBConnection();
    
    // Get chat rooms for user
    $stmt = $conn->prepare("
        SELECT cr.*, 
               (SELECT COUNT(*) FROM chat_room_members WHERE room_id = cr.id) as member_count,
               (SELECT content FROM chat_messages WHERE room_id = cr.id ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT created_at FROM chat_messages WHERE room_id = cr.id ORDER BY created_at DESC LIMIT 1) as last_message_time
        FROM chat_rooms cr
        JOIN chat_room_members crm ON cr.id = crm.room_id
        WHERE crm.user_id = ? AND cr.is_active = 1
        ORDER BY last_message_time DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    sendSuccess('Chat rooms retrieved successfully', [
        'rooms' => $rooms,
        'total' => count($rooms)
    ]);
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}