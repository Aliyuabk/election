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
    
    $type = $_POST['type'] ?? 'photo';
    $referenceId = $_POST['reference_id'] ?? null;
    $referenceType = $_POST['reference_type'] ?? null;
    
    // Upload directory
    $uploadDir = '../../uploads/' . $type . 's/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $fileUrl = '/uploads/' . $type . 's/' . $filename;
            
            // Save to database if reference provided
            if ($referenceId && $referenceType) {
                $table = '';
                $column = '';
                
                if ($referenceType === 'ec8a') {
                    $table = 'results_ec8a';
                    $column = 'photo_url';
                } else if ($referenceType === 'observation') {
                    $table = 'observations';
                    $column = 'image_url';
                } else if ($referenceType === 'incident') {
                    $table = 'incidents';
                    $column = 'photo_urls_json';
                    // For incidents, store as JSON
                    $fileUrl = json_encode([$fileUrl]);
                }
                
                if ($table && $column) {
                    $updateStmt = $conn->prepare("
                        UPDATE $table SET $column = ? WHERE id = ? AND user_id = ?
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