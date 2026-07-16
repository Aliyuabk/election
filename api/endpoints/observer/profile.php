<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
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
            SELECT u.*, t.name as tenant_name, r.name as role_name
            FROM users u
            LEFT JOIN tenants t ON u.tenant_id = t.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($profile = $result->fetch_assoc()) {
            unset($profile['password_hash']);
            unset($profile['remember_token']);
            unset($profile['two_factor_secret']);
            
            echo json_encode([
                'success' => true,
                'profile' => $profile
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        
        $stmt->close();
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $updateFields = [];
        $params = [];
        $types = "";
        
        if (isset($input['phone'])) {
            $updateFields[] = "phone = ?";
            $params[] = $input['phone'];
            $types .= "s";
        }
        
        if (isset($input['email'])) {
            $updateFields[] = "email = ?";
            $params[] = $input['email'];
            $types .= "s";
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit;
        }
        
        $params[] = $userId;
        $types .= "i";
        
        $query = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully'
            ]);
        } else {
            throw new Exception("Failed to update profile");
        }
        
        $stmt->close();
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>