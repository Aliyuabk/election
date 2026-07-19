<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'utgoohwm_election');
define('DB_PASS', 'Jiddaahh@1');
define('DB_NAME', 'utgoohwm_election');

// JWT Secret
define('JWT_SECRET', 'your-secret-key-change-this-in-production');

// Response codes
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_METHOD_NOT_ALLOWED', 405);
define('HTTP_INTERNAL_ERROR', 500);

// Upload paths
define('UPLOAD_PATH', '../uploads/');
define('EC8A_PATH', UPLOAD_PATH . 'ec8a/');
define('MEDIA_PATH', UPLOAD_PATH . 'media/');
define('PROFILE_PATH', UPLOAD_PATH . 'profile/');

// Ensure upload directories exist
if (!file_exists(EC8A_PATH)) {
    mkdir(EC8A_PATH, 0777, true);
}
if (!file_exists(MEDIA_PATH)) {
    mkdir(MEDIA_PATH, 0777, true);
}
if (!file_exists(PROFILE_PATH)) {
    mkdir(PROFILE_PATH, 0777, true);
}

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}