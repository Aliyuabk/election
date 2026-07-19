<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Only POST method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

$userId = validateToken();

$input = json_decode(file_get_contents('php://input'), true);
validateRequired($input, ['room_id', 'content']);

$roomId = (int)$input['room_id'];
$content = sanitizeInput($input['content']);
$messageType = isset($input['message_type']) ? sanitizeInput($input['message_type']) : 'text';
$receiverId = isset($input['receiver_id']) ? (int)$input['receiver_id'] : null;
$gpsLat = isset($input['gps_lat']) ? (float)$input['gps_lat'] : null;
$gpsLng = isset($input['gps_lng']) ? (float)$input['gps_lng'] : null;

if (empty($content) && $messageType !== 'location') {
    sendError('Message content is required', HTTP_BAD_REQUEST);
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
    
    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO chat_messages 
        (room_id, sender_id, receiver_id, message_type, content, gps_lat, gps_lng, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iiis sdd", $roomId, $userId, $receiverId, $messageType, $content, $gpsLat, $gpsLng);
    $stmt->execute();
    $messageId = $stmt->insert_id;
    $stmt->close();
    
    // Get created message
    $getStmt = $conn->prepare("
        SELECT cm.*, 
               u.first_name as sender_first_name, u.last_name as sender_last_name,
               u.avatar as sender_avatar
        FROM chat_messages cm
        LEFT JOIN users u ON cm.sender_id = u.id
        WHERE cm.id = ?
    ");
    $getStmt->bind_param("i", $messageId);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $message = $result->fetch_assoc();
    $getStmt->close();
    
    $conn->close();
    
    sendSuccess('Message sent successfully', $message, HTTP_CREATED);
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}