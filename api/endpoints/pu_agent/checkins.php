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
    
    $stmt = $conn->prepare("
        SELECT * FROM agent_checkins 
        WHERE agent_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $checkins = [];
    while ($row = $result->fetch_assoc()) {
        $checkins[] = [
            'id' => $row['id'],
            'tenant_id' => $row['tenant_id'],
            'election_id' => $row['election_id'],
            'agent_id' => $row['agent_id'],
            'assignment_id' => $row['assignment_id'],
            'pu_id' => $row['pu_id'],
            'checkin_type' => $row['checkin_type'],
            'gps_lat' => $row['gps_lat'],
            'gps_lng' => $row['gps_lng'],
            'gps_accuracy' => $row['gps_accuracy'],
            'gps_distance_from_pu' => $row['gps_distance_from_pu'],
            'photo_url' => $row['photo_url'],
            'device_id' => $row['device_id'],
            'device_battery' => $row['device_battery'],
            'network_type' => $row['network_type'],
            'is_offline_sync' => $row['is_offline_sync'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $checkins
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>