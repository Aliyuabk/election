<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

$user = AuthMiddleware::authenticate();

try {
    $db = Database::getInstance()->getConnection();
    $user_id = $user['user_id'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get user profile
        $stmt = $db->prepare("
            SELECT u.*, r.name as role_name, r.level as role_level, t.name as tenant_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN tenants t ON u.tenant_id = t.id
            WHERE u.id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($profile = $result->fetch_assoc()) {
            // Remove sensitive fields
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
        // Update user profile
        $input = json_decode(file_get_contents('php://input'), true);
        
        $updateFields = [];
        $params = [];
        $types = "";
        
        $allowedFields = ['first_name', 'last_name', 'phone', 'gender', 'date_of_birth', 'residential_address'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field]) && $input[$field] !== '') {
                $updateFields[] = "$field = ?";
                $params[] = $input[$field];
                $types .= "s";
            }
        }
        
        if (isset($input['password']) && !empty($input['password'])) {
            $updateFields[] = "password_hash = ?";
            $params[] = password_hash($input['password'], PASSWORD_BCRYPT);
            $types .= "s";
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit;
        }
        
        $params[] = $user_id;
        $types .= "i";
        
        $query = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully'
            ]);
        } else {
            throw new Exception("Failed to update profile: " . $stmt->error);
        }
        $stmt->close();
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Operation failed: ' . $e->getMessage()]);
}
?>