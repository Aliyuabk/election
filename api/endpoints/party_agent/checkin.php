<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get token from header
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
$checkinType = $input['checkin_type'] ?? 'arrival';
$puId = $input['pu_id'] ?? null;
$gpsLat = $input['gps_lat'] ?? null;
$gpsLng = $input['gps_lng'] ?? null;
$deviceId = $input['device_id'] ?? null;

if (!$puId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Polling unit ID required']);
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
    
    // Get user from session
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
        SELECT id FROM agent_assignments 
        WHERE user_id = ? AND pu_id = ? AND status = 'active'
    ");
    $assignmentStmt->bind_param("ii", $userId, $puId);
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
    
    // Insert check-in
    $stmt = $conn->prepare("
        INSERT INTO agent_checkins (
            tenant_id, election_id, agent_id, assignment_id, pu_id,
            checkin_type, gps_lat, gps_lng, device_id, created_at
        ) VALUES (
            (SELECT tenant_id FROM agent_assignments WHERE id = ?),
            (SELECT election_id FROM agent_assignments WHERE id = ?),
            ?, ?, ?, ?, ?, ?, ?, NOW()
        )
    ");
    
    $stmt->bind_param(
        "iiiiidss",
        $assignment['id'],
        $assignment['id'],
        $userId,
        $assignment['id'],
        $puId,
        $checkinType,
        $gpsLat,
        $gpsLng,
        $deviceId
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Check-in recorded successfully'
        ]);
    } else {
        throw new Exception("Failed to record check-in");
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>