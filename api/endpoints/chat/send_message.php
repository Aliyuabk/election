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

if (!$userData) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$receiverId = isset($input['receiver_id']) ? (int)$input['receiver_id'] : 0;
$message = isset($input['message']) ? trim($input['message']) : '';
$messageType = isset($input['message_type']) ? trim($input['message_type']) : 'text';
$mediaUrl = isset($input['media_url']) ? trim($input['media_url']) : '';

if ($receiverId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Receiver ID is required']);
    exit;
}

if (empty($message) && empty($mediaUrl)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message content is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Verify receiver exists and is in the same ward
    $stmt = $conn->prepare("
        SELECT id, full_name, role_id, tenant_id, ward_id FROM users 
        WHERE id = ? AND status = 'active' AND deleted_at IS NULL
    ");
    $stmt->bind_param("i", $receiverId);
    $stmt->execute();
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->close();
    
    if (!$receiver) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Recipient not found']);
        exit;
    }
    
    // Check if sender and receiver are in the same ward (or sender is coordinator)
    $senderRole = $userData['role_level'];
    $isCoordinator = in_array($senderRole, ['ward', 'lga', 'state', 'national', 'super_admin']);
    
    if ($userData['ward_id'] != $receiver['ward_id'] && !$isCoordinator) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only chat with users in your ward']);
        exit;
    }
    
    // Find or create chat room
    $roomId = null;
    
    // Check if direct chat room exists
    $stmt = $conn->prepare("
        SELECT cr.id FROM chat_rooms cr
        JOIN chat_room_members crm1 ON cr.id = crm1.room_id
        JOIN chat_room_members crm2 ON cr.id = crm2.room_id
        WHERE cr.tenant_id = ? AND cr.type = 'direct'
        AND crm1.user_id = ? AND crm2.user_id = ?
    ");
    $stmt->bind_param("iii", $userData['tenant_id'], $userId, $receiverId);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    
    if ($room) {
        $roomId = $room['id'];
    } else {
        // Create new direct room
        $roomName = "Chat between " . $userData['full_name'] . " and " . $receiver['full_name'];
        $stmt = $conn->prepare("
            INSERT INTO chat_rooms (tenant_id, name, type, created_by, created_at) 
            VALUES (?, ?, 'direct', ?, NOW())
        ");
        $stmt->bind_param("isi", $userData['tenant_id'], $roomName, $userId);
        $stmt->execute();
        $roomId = $conn->insert_id;
        $stmt->close();
        
        // Add both members
        $stmt = $conn->prepare("INSERT INTO chat_room_members (room_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
        $stmt->bind_param("ii", $roomId, $userId);
        $stmt->execute();
        $stmt->bind_param("ii", $roomId, $receiverId);
        $stmt->execute();
        $stmt->close();
    }
    
    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO chat_messages (
            room_id, sender_id, receiver_id, message_type, content, 
            media_url, is_read, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
    ");
    $stmt->bind_param("iiisss", $roomId, $userId, $receiverId, $messageType, $message, $mediaUrl);
    $stmt->execute();
    $messageId = $stmt->insert_id;
    $stmt->close();
    
    // Get the created message with sender info
    $stmt = $conn->prepare("
        SELECT cm.*, 
               u_sender.full_name as sender_name,
               u_sender.photograph_url as sender_photo,
               u_receiver.full_name as receiver_name
        FROM chat_messages cm
        LEFT JOIN users u_sender ON cm.sender_id = u_sender.id
        LEFT JOIN users u_receiver ON cm.receiver_id = u_receiver.id
        WHERE cm.id = ?
    ");
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messageData = $result->fetch_assoc();
    $stmt->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'data' => $messageData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>