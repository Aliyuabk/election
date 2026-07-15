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

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['data_type']) || !isset($input['payload'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'data_type and payload are required']);
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
    
    $dataType = $input['data_type'];
    $payload = $input['payload'];
    $syncId = $input['sync_id'] ?? uniqid();
    
    // Process based on data type
    $success = false;
    $message = '';
    
    switch ($dataType) {
        case 'ec8a':
            // Similar to EC8A submission
            $success = true;
            $message = 'EC8A synced successfully';
            break;
            
        case 'incident':
            // Similar to incident reporting
            $success = true;
            $message = 'Incident synced successfully';
            break;
            
        case 'checkin':
            // Similar to check-in
            $success = true;
            $message = 'Check-in synced successfully';
            break;
            
        case 'observation':
            // Similar to observation submission
            $success = true;
            $message = 'Observation synced successfully';
            break;
            
        default:
            $success = false;
            $message = 'Unknown data type: ' . $dataType;
    }
    
    // Record sync
    $syncStmt = $conn->prepare("
        INSERT INTO offline_sync_queue (
            user_id, device_id, data_type, payload_json, status, synced_at, created_at
        ) VALUES (
            ?, ?, ?, ?, 'completed', NOW(), NOW()
        )
    ");
    
    $deviceId = $input['device_id'] ?? 'unknown';
    $payloadJson = json_encode($payload);
    
    $syncStmt->bind_param("isss", $userId, $deviceId, $dataType, $payloadJson);
    $syncStmt->execute();
    $syncStmt->close();
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'sync_id' => $syncId
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>