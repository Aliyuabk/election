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

$required = ['pu_id', 'checkin_type', 'gps_lat', 'gps_lng'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "$field is required"]);
        exit;
    }
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
    
    // Get assignment
    $assignmentStmt = $conn->prepare("
        SELECT id, tenant_id, election_id FROM agent_assignments 
        WHERE user_id = ? AND pu_id = ? AND status IN ('active', 'pending')
        ORDER BY created_at DESC LIMIT 1
    ");
    $assignmentStmt->bind_param("ii", $userId, $input['pu_id']);
    $assignmentStmt->execute();
    $assignmentResult = $assignmentStmt->get_result();
    
    if ($assignmentResult->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No active assignment for this polling unit']);
        $assignmentStmt->close();
        $conn->close();
        exit;
    }
    
    $assignment = $assignmentResult->fetch_assoc();
    $assignmentStmt->close();
    
    // Check if already checked in today
    $checkStmt = $conn->prepare("
        SELECT id FROM agent_checkins 
        WHERE agent_id = ? AND pu_id = ? AND DATE(created_at) = CURDATE()
        AND checkin_type = 'arrival'
    ");
    $checkStmt->bind_param("ii", $userId, $input['pu_id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0 && $input['checkin_type'] === 'arrival') {
        echo json_encode([
            'success' => false,
            'message' => 'Already checked in today'
        ]);
        $checkStmt->close();
        $conn->close();
        exit;
    }
    $checkStmt->close();
    
    // Insert check-in
    $stmt = $conn->prepare("
        INSERT INTO agent_checkins (
            tenant_id, election_id, agent_id, assignment_id, pu_id,
            checkin_type, gps_lat, gps_lng, gps_accuracy, gps_distance_from_pu,
            photo_url, device_id, device_battery, network_type, 
            is_offline_sync, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, NOW()
        )
    ");
    
    $tenantId = $assignment['tenant_id'];
    $electionId = $assignment['election_id'];
    $gpsLat = $input['gps_lat'];
    $gpsLng = $input['gps_lng'];
    $gpsAccuracy = $input['gps_accuracy'] ?? null;
    $gpsDistance = $input['gps_distance_from_pu'] ?? null;
    $photoUrl = $input['photo_url'] ?? null;
    $deviceId = $input['device_id'] ?? null;
    $deviceBattery = $input['device_battery'] ?? null;
    $networkType = $input['network_type'] ?? null;
    $isOfflineSync = $input['is_offline_sync'] ?? 0;
    
    $stmt->bind_param(
        "iiiiisddddssii",
        $tenantId,
        $electionId,
        $userId,
        $assignment['id'],
        $input['pu_id'],
        $input['checkin_type'],
        $gpsLat,
        $gpsLng,
        $gpsAccuracy,
        $gpsDistance,
        $photoUrl,
        $deviceId,
        $deviceBattery,
        $networkType,
        $isOfflineSync
    );
    
    if ($stmt->execute()) {
        $id = $conn->insert_id;
        
        // Log activity
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, activity_type, description, entity_type, entity_id, created_at)
            VALUES (?, 'checkin', 'Checked in at polling unit', 'agent_checkins', ?, NOW())
        ");
        $logStmt->bind_param("ii", $userId, $id);
        $logStmt->execute();
        $logStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Check-in recorded successfully',
            'id' => $id
        ]);
    } else {
        throw new Exception("Failed to record check-in: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>