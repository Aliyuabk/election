<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// Database configuration
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
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get incidents
        $stmt = $conn->prepare("
            SELECT * FROM incidents 
            WHERE reporter_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $incidents = [];
        while ($row = $result->fetch_assoc()) {
            $incidents[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'location' => $row['location'] ?? 'Unknown',
                'type' => $row['incident_type'],
                'date' => $row['created_at'],
                'status' => $row['status'],
                'image_url' => $row['photo_urls_json'],
                'polling_unit_id' => $row['pu_id']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $incidents
        ]);
        
        $stmt->close();
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Report incident
        $input = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $conn->prepare("
            INSERT INTO incidents (
                reporter_id, pu_id, incident_type, title, description, 
                location, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'reported', NOW())
        ");
        
        $stmt->bind_param(
            "issss", 
            $userId, 
            $input['polling_unit_id'],
            $input['type'],
            $input['title'],
            $input['description'],
            $input['location']
        );
        
        if ($stmt->execute()) {
            $id = $conn->insert_id;
            
            // Log activity
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, activity_type, description, entity_type, entity_id, created_at)
                VALUES (?, 'incident_reported', ?, 'incidents', ?, NOW())
            ");
            $logStmt->bind_param("isi", $userId, $input['title'], $id);
            $logStmt->execute();
            $logStmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Incident reported successfully',
                'id' => $id
            ]);
        } else {
            throw new Exception("Failed to report incident");
        }
        
        $stmt->close();
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>