<?php
function sendResponse($success, $message = '', $data = null, $code = HTTP_OK) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function sendError($message, $code = HTTP_BAD_REQUEST) {
    sendResponse(false, $message, null, $code);
}

function sendSuccess($message, $data = null, $code = HTTP_OK) {
    sendResponse(true, $message, $data, $code);
}

function validateToken() {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        sendError('Unauthorized: No token provided', HTTP_UNAUTHORIZED);
    }
    
    $token = $matches[1];
    
    // Verify token in database
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT user_id, expires_at 
        FROM user_sessions 
        WHERE token = ? AND is_active = 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendError('Unauthorized: Invalid token', HTTP_UNAUTHORIZED);
    }
    
    $session = $result->fetch_assoc();
    
    // Check if token expired
    $expiresAt = strtotime($session['expires_at']);
    if ($expiresAt < time()) {
        sendError('Unauthorized: Token expired', HTTP_UNAUTHORIZED);
    }
    
    $stmt->close();
    $conn->close();
    
    return $session['user_id'];
}

function getUserRole($userId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT r.level as role_level, r.name as role_name, u.tenant_id
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ? AND u.status = 'active'
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $user;
}

function checkRole($userId, $allowedRoles = []) {
    $user = getUserRole($userId);
    if (!$user) {
        return false;
    }
    
    if (empty($allowedRoles)) {
        return true;
    }
    
    return in_array($user['role_level'], $allowedRoles);
}

function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendError("Missing required field: $field", HTTP_BAD_REQUEST);
        }
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}