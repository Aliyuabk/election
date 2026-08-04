<?php
/**
 * Login Endpoint
 * POST /api/auth/login
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';
require_once dirname(__DIR__, 2) . '/includes/validation.php';

// Get POST data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data) {
    Response::error('Invalid request data', 400);
}

// Validate input
$errors = Validator::required($data, ['email', 'password']);
if (!empty($errors)) {
    Response::validationError($errors);
}

// Sanitize input
$email = Validator::sanitize($data['email']);
$password = $data['password'];

if (!Validator::validateEmail($email)) {
    Response::error('Invalid email format', 422);
}

// Get device info
$deviceId = isset($data['device_id']) ? Validator::sanitize($data['device_id']) : null;
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

// Authenticate
$auth = new Auth();
$result = $auth->login($email, $password, $deviceId, $ipAddress);

if ($result['success']) {
    // Log activity
    $db = Database::getInstance();
    $db->query("
        INSERT INTO activity_logs (user_id, activity_type, description, ip_address, device_id, created_at)
        VALUES ({$result['user']['id']}, 'login', 'User logged in successfully', '$ipAddress', '$deviceId', NOW())
    ");
    
    Response::success($result, 'Login successful');
} else {
    Response::error($result['message'], 401);
}
?>