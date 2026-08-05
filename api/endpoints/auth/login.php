<?php
/**
 * Login Endpoint
 * POST /api/auth/login
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/Response.php';
require_once dirname(__DIR__, 2) . '/includes/Validator.php';
require_once dirname(__DIR__, 2) . '/includes/MobileAuth.php';

// Log request
logApiRequest('auth/login');

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
$deviceId = isset($data['device_id']) ? Validator::sanitize($data['device_id']) : null;
$deviceName = isset($data['device_name']) ? Validator::sanitize($data['device_name']) : null;
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

if (!Validator::validateEmail($email)) {
    Response::error('Invalid email format', 422);
}

// Authenticate
$auth = new Auth();
$result = $auth->login($email, $password, $deviceId, $ipAddress);

if ($result['success']) {
    // Bind device if provided
    if ($deviceId) {
        $mobileAuth = new MobileAuth();
        $bindResult = $mobileAuth->bindDevice($result['user']['id'], $deviceId, $deviceName);
        
        if (!$bindResult['success']) {
            error_log("Device binding failed: " . $bindResult['message']);
        }
    }
    
    // Log activity
    $db = Database::getInstance();
    $db->query("
        INSERT INTO activity_logs (user_id, activity_type, description, ip_address, device_id, created_at)
        VALUES (
            {$result['user']['id']}, 
            'login', 
            'User logged in successfully from mobile app', 
            '$ipAddress', 
            '$deviceId', 
            NOW()
        )
    ");
    
    Response::success($result, 'Login successful');
} else {
    Response::error($result['message'], 401);
}