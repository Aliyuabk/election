<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$userId = validateToken();
$userData = getUserData($userId);

$input = json_decode(file_get_contents('php://input'), true);
$roomId = isset($input['room_id']) ? (int)$input['room_id'] : 0;
$content = isset($input['content']) ? trim($input['content']) : '';
$messageType = isset($input['message_type']) ? trim($input['message_type']) : 'text';
$receiverId = isset($input['receiver_id']) ? (int)$input['receiver_id'] : null;
$gpsLat = isset($input['gps_lat']) ? (float)$input['gps_lat'] : null;
$gpsLng = isset($input['gps_lng']) ? (float)$input['gps_lng'] : null;

if ($roomId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Room ID is required']);
    exit;
}

if (empty($content) && $messageType !== 'location' && $messageType !== 'image' && $messageType !== 'video' && $messageType !== 'audio' && $messageType !== 'file') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message content is required']);
    exit;
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
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not a member of this chat room']);
        exit;
    }
    $checkStmt->close();
    
    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO chat_messages 
        (room_id, sender_id, receiver_id, message_type, content, gps_lat, gps_lng, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iiis sdd", $roomId, $userId, $receiverId, $messageType, $content, $gpsLat, $gpsLng);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $stmt->error]);
        exit;
    }
    
    $messageId = $stmt->insert_id;
    $stmt->close();
    
    // Get created message with sender info
    $getStmt = $conn->prepare("
        SELECT cm.*, 
               u.first_name as sender_first_name, 
               u.last_name as sender_last_name,
               u.avatar as sender_avatar,
               ru.first_name as receiver_first_name,
               ru.last_name as receiver_last_name
        FROM chat_messages cm
        LEFT JOIN users u ON cm.sender_id = u.id
        LEFT JOIN users ru ON cm.receiver_id = ru.id
        WHERE cm.id = ?
    ");
    $getStmt->bind_param("i", $messageId);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $message = $result->fetch_assoc();
    $getStmt->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'data' => $message
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}