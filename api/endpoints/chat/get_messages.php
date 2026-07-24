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
$userData = getUserData($userId);

if (!$userData) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$contactId = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if ($contactId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Contact ID is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get messages between user and contact
    $stmt = $conn->prepare("
        SELECT 
            cm.*,
            u_sender.full_name as sender_first_name,
            u_sender.photograph_url as sender_photo,
            u_receiver.full_name as receiver_name
        FROM chat_messages cm
        LEFT JOIN users u_sender ON cm.sender_id = u_sender.id
        LEFT JOIN users u_receiver ON cm.receiver_id = u_receiver.id
        WHERE (cm.sender_id = ? AND cm.receiver_id = ?)
           OR (cm.sender_id = ? AND cm.receiver_id = ?)
        AND cm.is_deleted = 0
        ORDER BY cm.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iiiiii", $userId, $contactId, $contactId, $userId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
    
    // Mark unread messages as read
    $stmt = $conn->prepare("
        UPDATE chat_messages 
        SET is_read = 1, read_at = NOW() 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("ii", $contactId, $userId);
    $stmt->execute();
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