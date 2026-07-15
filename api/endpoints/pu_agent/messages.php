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
$username = 'utgoohwm_election'; // Your actual database username
$password_db = 'Jiddaahh@1'; // Your actual database password

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
        
        // If no messages, return mock data
        if (empty($messages)) {
            $messages = [
                [
                    'id' => '1',
                    'sender_id' => '2',
                    'sender_name' => 'Coordinator',
                    'content' => 'Welcome! How is the election going?',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'is_from_me' => false
                ],
                [
                    'id' => '2',
                    'sender_id' => '1',
                    'sender_name' => 'Me',
                    'content' => 'Everything is going smoothly!',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
                    'is_from_me' => true
                ]
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $messages
        ]);
        
        $stmt->close();
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>