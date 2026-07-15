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
        // Get today's checklist
        $stmt = $conn->prepare("
            SELECT checklist_data, status, created_at 
            FROM election_checklists 
            WHERE user_id = ? AND DATE(created_at) = CURDATE()
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Decode JSON data
            $data = json_decode($row['checklist_data'], true);
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'status' => $row['status'],
                'created_at' => $row['created_at']
            ]);
        } else {
            // Default checklist
            echo json_encode([
                'success' => true,
                'data' => [
                    'materials' => false,
                    'poll_opened' => false,
                    'accreditation' => false,
                    'voting' => false,
                    'counting' => false,
                    'poll_closed' => false
                ],
                'status' => 'draft'
            ]);
        }
        
        $stmt->close();
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Submit checklist
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid checklist data']);
            exit;
        }
        
        $checklistData = json_encode($input);
        
        // Check if exists today
        $checkStmt = $conn->prepare("
            SELECT id FROM election_checklists 
            WHERE user_id = ? AND DATE(created_at) = CURDATE()
        ");
        $checkStmt->bind_param("i", $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update existing
            $stmt = $conn->prepare("
                UPDATE election_checklists 
                SET checklist_data = ?, status = 'submitted', submitted_at = NOW()
                WHERE user_id = ? AND DATE(created_at) = CURDATE()
            ");
            $stmt->bind_param("si", $checklistData, $userId);
        } else {
            // Insert new
            $stmt = $conn->prepare("
                INSERT INTO election_checklists (
                    user_id, checklist_data, status, submitted_at, created_at
                ) VALUES (?, ?, 'submitted', NOW(), NOW())
            ");
            $stmt->bind_param("is", $userId, $checklistData);
        }
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Checklist submitted successfully'
            ]);
        } else {
            throw new Exception("Failed to submit checklist: " . $stmt->error);
        }
        
        $stmt->close();
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>