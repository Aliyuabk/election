<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['first_name', 'last_name', 'email', 'phone', 'password', 'role_id'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "$field is required"]);
        exit;
    }
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if email already exists
    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $input['email']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();
    
    // Generate user code
    $user_code = 'USR' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    // Hash password
    $hashed_password = password_hash($input['password'], PASSWORD_BCRYPT);
    
    // Insert user
    $stmt = $db->prepare("
        INSERT INTO users (
            tenant_id, user_code, role_id, first_name, last_name, email, phone, 
            password_hash, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
    ");
    
    $tenant_id = $input['tenant_id'] ?? null;
    $stmt->bind_param(
        "isississ", 
        $tenant_id, 
        $user_code, 
        $input['role_id'], 
        $input['first_name'], 
        $input['last_name'], 
        $input['email'], 
        $input['phone'], 
        $hashed_password
    );
    
    if ($stmt->execute()) {
        $user_id = $db->insert_id;
        
        // Get the created user
        $selectStmt = $db->prepare("
            SELECT id, first_name, last_name, email, phone, role_id, tenant_id, user_code
            FROM users WHERE id = ?
        ");
        $selectStmt->bind_param("i", $user_id);
        $selectStmt->execute();
        $user = $selectStmt->get_result()->fetch_assoc();
        $selectStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'User registered successfully',
            'user' => $user
        ]);
    } else {
        throw new Exception("Failed to register user: " . $stmt->error);
    }
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}
?>