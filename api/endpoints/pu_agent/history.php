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
    
    // Get EC8A history
    $ec8aStmt = $conn->prepare("
        SELECT 
            id,
            pu_name as title,
            'EC8A' as type,
            status,
            created_at as date
        FROM results_ec8a 
        WHERE agent_id = ? 
        ORDER BY created_at DESC
    ");
    $ec8aStmt->bind_param("i", $userId);
    $ec8aStmt->execute();
    $ec8aResult = $ec8aStmt->get_result();
    
    $history = [];
    while ($row = $ec8aResult->fetch_assoc()) {
        $history[] = $row;
    }
    $ec8aStmt->close();
    
    // Get incidents history
    $incidentStmt = $conn->prepare("
        SELECT 
            id,
            title,
            'Incident' as type,
            status,
            created_at as date
        FROM incidents 
        WHERE reporter_id = ? 
        ORDER BY created_at DESC
    ");
    $incidentStmt->bind_param("i", $userId);
    $incidentStmt->execute();
    $incidentResult = $incidentStmt->get_result();
    
    while ($row = $incidentResult->fetch_assoc()) {
        $history[] = $row;
    }
    $incidentStmt->close();
    
    // Get observations history
    $obsStmt = $conn->prepare("
        SELECT 
            id,
            title,
            'Observation' as type,
            status,
            created_at as date
        FROM observations 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $obsStmt->bind_param("i", $userId);
    $obsStmt->execute();
    $obsResult = $obsStmt->get_result();
    
    while ($row = $obsResult->fetch_assoc()) {
        $history[] = $row;
    }
    $obsStmt->close();
    
    // Sort by date
    usort($history, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    echo json_encode([
        'success' => true,
        'data' => $history
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>