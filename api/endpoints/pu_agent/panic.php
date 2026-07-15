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

if (!isset($input['message']) || empty($input['message'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message is required']);
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
    
    // Get user info
    $userStmt = $conn->prepare("
        SELECT first_name, last_name, email, phone FROM users WHERE id = ?
    ");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    $userStmt->close();
    
    // Get polling unit
    $puStmt = $conn->prepare("
        SELECT pu.name, pu.code, w.name as ward_name, l.name as lga_name 
        FROM agent_assignments aa
        JOIN polling_units pu ON aa.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE aa.user_id = ? AND aa.status IN ('active', 'pending')
        LIMIT 1
    ");
    $puStmt->bind_param("i", $userId);
    $puStmt->execute();
    $puResult = $puStmt->get_result();
    $pu = $puResult->fetch_assoc();
    $puStmt->close();
    
    // Insert panic alert
    $stmt = $conn->prepare("
        INSERT INTO incidents (
            tenant_id, reporter_id, pu_id, incident_type, severity, is_panic,
            title, description, gps_lat, gps_lng, status, created_at
        ) VALUES (
            (SELECT tenant_id FROM agent_assignments WHERE user_id = ? AND status IN ('active', 'pending') LIMIT 1),
            ?, 
            (SELECT pu_id FROM agent_assignments WHERE user_id = ? AND status IN ('active', 'pending') LIMIT 1),
            'panic_button',
            'critical',
            1,
            'EMERGENCY ALERT',
            ?,
            ?,
            ?,
            'reported',
            NOW()
        )
    ");
    
    $message = $input['message'];
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;
    
    // Add location to message
    if ($latitude && $longitude) {
        $message .= " | Location: $latitude, $longitude";
    }
    
    $stmt->bind_param("iisssdd", $userId, $userId, $message, $latitude, $longitude);
    
    if ($stmt->execute()) {
        $id = $conn->insert_id;
        
        // Log activity
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, activity_type, description, entity_type, entity_id, created_at)
            VALUES (?, 'panic_alert', 'Panic alert sent', 'incidents', ?, NOW())
        ");
        $logStmt->bind_param("ii", $userId, $id);
        $logStmt->execute();
        $logStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Panic alert sent successfully',
            'id' => $id
        ]);
    } else {
        throw new Exception("Failed to send panic alert: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>