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
        SELECT 
            pu.id,
            pu.code,
            pu.name,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            e.name as election_name,
            CONCAT(u.first_name, ' ', u.last_name) as coordinator_name
        FROM agent_assignments aa
        JOIN polling_units pu ON aa.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        JOIN elections e ON aa.election_id = e.id
        LEFT JOIN users u ON aa.assigned_by = u.id
        WHERE aa.user_id = ? AND aa.status IN ('active', 'pending')
        ORDER BY aa.created_at DESC
        LIMIT 1
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $row['id'],
                'code' => $row['code'],
                'name' => $row['name'],
                'ward' => $row['ward_name'],
                'lga' => $row['lga_name'],
                'state' => $row['state_name'],
                'election' => $row['election_name'],
                'coordinator' => $row['coordinator_name'] ?? 'Not Assigned'
            ]
        ]);
    } else {
        // Return mock data for testing
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => '1',
                'code' => 'PU-001',
                'name' => 'KANGIRE YAMMA/AREWA/KANGIRE P.S',
                'ward' => 'Kangire',
                'lga' => 'Birnin Kudu',
                'state' => 'Jigawa',
                'election' => '2027 Governorship Election',
                'coordinator' => 'Aliyu Abubakar'
            ]
        ]);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>