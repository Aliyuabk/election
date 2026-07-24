<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$userId = validateToken();

$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if ($roomId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Room ID is required']);
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
    
    // Get messages with sender info
    $stmt = $conn->prepare("
        SELECT cm.*, 
               u.first_name as sender_first_name, 
               u.last_name as sender_last_name,
               u.avatar as sender_avatar,
               ru.first_name as receiver_first_name,
               ru.last_name as receiver_last_name
        FROM chat_messages cm
        LEFT JOIN users u ON cm.sender_id = u.id
        LEFT JOIN users ru ON cm.receiver_id = ru.id
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
    
    echo json_encode([
        'success' => true,
        'messages' => array_reverse($messages),
        'total' => count($messages)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}