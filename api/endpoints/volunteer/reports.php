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
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare("
            SELECT * FROM community_reports 
            WHERE volunteer_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'category' => $row['category'],
                'description' => $row['description'],
                'location' => $row['location'],
                'date' => $row['created_at'],
                'status' => $row['status'],
                'image_url' => $row['image_url'],
                'video_url' => $row['video_url'],
                'volunteer_id' => $row['volunteer_id']
            ];
        }
        
        // If no reports, return mock data
        if (empty($reports)) {
            $reports = [
                [
                    'id' => '1',
                    'title' => 'Community Engagement',
                    'category' => 'Campaign Activity',
                    'description' => 'Engaged with community members about the upcoming election',
                    'location' => 'Kangire, Birnin Kudu',
                    'date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                    'status' => 'submitted',
                    'image_url' => null,
                    'video_url' => null,
                    'volunteer_id' => $userId
                ],
                [
                    'id' => '2',
                    'title' => 'Voter Turnout Observation',
                    'category' => 'Election Awareness',
                    'description' => 'Noticed good voter turnout in the community',
                    'location' => 'Birnin Kudu',
                    'date' => date('Y-m-d H:i:s', strtotime('-5 hours')),
                    'status' => 'approved',
                    'image_url' => null,
                    'video_url' => null,
                    'volunteer_id' => $userId
                ]
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $reports
        ]);
        
        $stmt->close();
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Submit report
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['title', 'category', 'description', 'location'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "$field is required"]);
                exit;
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO community_reports (
                volunteer_id, title, category, description, location, 
                image_url, video_url, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted', NOW())
        ");
        
        $imageUrl = $input['image_url'] ?? null;
        $videoUrl = $input['video_url'] ?? null;
        
        $stmt->bind_param(
            "issssss",
            $userId,
            $input['title'],
            $input['category'],
            $input['description'],
            $input['location'],
            $imageUrl,
            $videoUrl
        );
        
        if ($stmt->execute()) {
            $id = $conn->insert_id;
            
            echo json_encode([
                'success' => true,
                'message' => 'Report submitted successfully',
                'id' => $id
            ]);
        } else {
            throw new Exception("Failed to submit report: " . $stmt->error);
        }
        
        $stmt->close();
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>