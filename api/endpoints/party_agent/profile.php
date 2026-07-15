<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
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
        // Get profile
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
            
            // Get assigned polling unit
            $puStmt = $conn->prepare("
                SELECT pu.name, pu.code FROM agent_assignments aa
                JOIN polling_units pu ON aa.pu_id = pu.id
                WHERE aa.user_id = ? AND aa.status = 'active'
                LIMIT 1
            ");
            $puStmt->bind_param("i", $userId);
            $puStmt->execute();
            $puResult = $puStmt->get_result();
            
            if ($pu = $puResult->fetch_assoc()) {
                $profile['polling_unit'] = $pu['name'] . ' (' . $pu['code'] . ')';
            } else {
                $profile['polling_unit'] = 'Not Assigned';
            }
            $puStmt->close();
            
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
        // Update profile
        $input = json_decode(file_get_contents('php://input'), true);
        
        $updateFields = [];
        $params = [];
        $types = "";
        
        if (isset($input['phone'])) {
            $updateFields[] = "phone = ?";
            $params[] = $input['phone'];
            $types .= "s";
        }
        
        if (isset($input['first_name'])) {
            $updateFields[] = "first_name = ?";
            $params[] = $input['first_name'];
            $types .= "s";
        }
        
        if (isset($input['last_name'])) {
            $updateFields[] = "last_name = ?";
            $params[] = $input['last_name'];
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