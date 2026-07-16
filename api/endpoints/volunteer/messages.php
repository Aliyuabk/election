<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$headers = getallheaders();
$token = null;
if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$host = 'localhost';
$db_name = 'utgoohwm_election';
$username = 'utgoohwm_election';
$password_db = 'Jiddaahh@1';

try {
    $conn = new mysqli($host, $username, $password_db, $db_name);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    $conn->set_charset("utf8mb4");
    
    $sessionStmt = $conn->prepare("
        SELECT user_id FROM user_sessions WHERE token = ? AND is_active = 1
    ");
    $sessionStmt->bind_param("s", $token);
    $sessionStmt->execute();
    $sessionResult = $sessionStmt->get_result();
    
    if ($sessionResult->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid session']);
        $sessionStmt->close();
        $conn->close();
        exit;
    }
    
    $session = $sessionResult->fetch_assoc();
    $userId = $session['user_id'];
    $sessionStmt->close();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare("
            SELECT 
                cm.*,
                u.first_name as sender_first_name,
                u.last_name as sender_last_name
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE cm.room_id IN (
                SELECT room_id FROM chat_room_members WHERE user_id = ?
            )
            ORDER BY cm.created_at DESC
            LIMIT 50
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = [
                'id' => $row['id'],
                'sender_id' => $row['sender_id'],
                'sender_name' => $row['sender_first_name'] . ' ' . $row['sender_last_name'],
                'content' => $row['content'],
                'timestamp' => $row['created_at'],
                'is_from_me' => $row['sender_id'] == $userId
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $messages
        ]);
        
        $stmt->close();
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['content']) || empty($input['content'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Content is required']);
            exit;
        }
        
        $roomId = 1; // Default room for volunteers
        
        $stmt = $conn->prepare("
            INSERT INTO chat_messages (
                room_id, sender_id, receiver_id, content, message_type, created_at
            ) VALUES (?, ?, ?, ?, 'text', NOW())
        ");
        
        $receiverId = $input['receiver_id'] ?? 2; // Default coordinator
        
        $stmt->bind_param("iiis", $roomId, $userId, $receiverId, $input['content']);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Message sent successfully'
            ]);
        } else {
            throw new Exception("Failed to send message: " . $stmt->error);
        }
        
        $stmt->close();
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>