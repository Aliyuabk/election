<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'data_json' => $row['data_json'],
            'action_url' => $row['action_url'],
            'is_read' => $row['is_read'],
            'read_at' => $row['read_at'],
            'created_at' => $row['created_at']
        ];
    }
    
    // If no notifications, return mock data
    if (empty($notifications)) {
        $notifications = [
            [
                'id' => '1',
                'user_id' => $userId,
                'type' => 'election',
                'title' => 'Election Day Observation',
                'message' => 'Please submit your observations for today\'s election process.',
                'data_json' => null,
                'action_url' => null,
                'is_read' => 0,
                'read_at' => null,
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $notifications
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>