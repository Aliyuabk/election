<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
    
    $type = $_POST['type'] ?? 'image';
    $referenceId = $_POST['reference_id'] ?? null;
    $referenceType = $_POST['reference_type'] ?? null;
    
    // Upload directory
    $uploadDir = '../../uploads/observer/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $fileUrl = '/uploads/observer/' . $filename;
            
            // Save to database if reference provided
            if ($referenceId && $referenceType) {
                if ($referenceType === 'observation') {
                    $updateStmt = $conn->prepare("
                        UPDATE observer_observations 
                        SET " . ($type === 'image' ? 'image_url' : 'video_url') . " = ? 
                        WHERE id = ? AND observer_id = ?
                    ");
                    $updateStmt->bind_param("sii", $fileUrl, $referenceId, $userId);
                    $updateStmt->execute();
                    $updateStmt->close();
                } else if ($referenceType === 'incident') {
                    $updateStmt = $conn->prepare("
                        UPDATE observer_incidents 
                        SET " . ($type === 'image' ? 'image_url' : 'video_url') . " = ? 
                        WHERE id = ? AND observer_id = ?
                    ");
                    $updateStmt->bind_param("sii", $fileUrl, $referenceId, $userId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'File uploaded successfully',
                'url' => $fileUrl,
                'filename' => $filename
            ]);
        } else {
            throw new Exception("Failed to move uploaded file");
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>