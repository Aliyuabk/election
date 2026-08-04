<?php
/**
 * Mobile API Configuration
 * Secure configuration for mobile app endpoints
 */

// Error reporting - disable in production
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// CORS Configuration for mobile app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Device-ID, X-App-Version');
header('Access-Control-Expose-Headers: Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Define constants
define('BASE_URL', 'https://eguruelection.kowagurutech.ng/api/');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg', 'image/gif']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/avi', 'video/mov', 'video/3gp']);
define('ALLOWED_AUDIO_TYPES', ['audio/mp3', 'audio/wav', 'audio/m4a']);

// App version
define('APP_VERSION', '1.0.0');
define('MIN_APP_VERSION', '1.0.0');

// Rate limiting
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60); // seconds

// Session configuration
define('SESSION_TIMEOUT', 86400); // 24 hours

// Create upload directories if they don't exist
$directories = [
    UPLOAD_PATH . 'ec8a/',
    UPLOAD_PATH . 'chat/',
    UPLOAD_PATH . 'profiles/',
    UPLOAD_PATH . 'incidents/',
    UPLOAD_PATH . 'media/',
    __DIR__ . '/../logs/'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Rate Limiting function
 */
function checkRateLimit($key, $limit = RATE_LIMIT_REQUESTS, $window = RATE_LIMIT_WINDOW) {
    $rateLimitFile = __DIR__ . '/../logs/rate_limit_' . md5($key) . '.json';
    $currentTime = time();
    
    if (file_exists($rateLimitFile)) {
        $data = json_decode(file_get_contents($rateLimitFile), true);
        
        // Reset if window has passed
        if ($currentTime - $data['window_start'] > $window) {
            $data = [
                'window_start' => $currentTime,
                'count' => 1
            ];
        } else {
            $data['count']++;
        }
    } else {
        $data = [
            'window_start' => $currentTime,
            'count' => 1
        ];
    }
    
    file_put_contents($rateLimitFile, json_encode($data));
    
    if ($data['count'] > $limit) {
        return false;
    }
    
    return true;
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ipAddress = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
    }
    return $ipAddress;
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($originalName, $prefix = '') {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $timestamp = date('Ymd_His');
    $random = bin2hex(random_bytes(8));
    return $prefix . '_' . $timestamp . '_' . $random . '.' . $extension;
}

/**
 * Validate base64 image
 */
function validateBase64Image($base64String) {
    if (empty($base64String)) {
        return false;
    }
    
    // Check if it's a valid base64 string
    if (!preg_match('/^data:image\/(jpeg|png|jpg|gif);base64,/', $base64String)) {
        return false;
    }
    
    return true;
}

/**
 * Save base64 image
 */
function saveBase64Image($base64String, $path, $filename) {
    $data = explode(',', $base64String);
    $imageData = base64_decode($data[1]);
    
    if ($imageData === false) {
        return false;
    }
    
    $fullPath = $path . $filename;
    if (file_put_contents($fullPath, $imageData)) {
        return $filename;
    }
    
    return false;
}

/**
 * Log API request
 */
function logApiRequest($endpoint, $data = null) {
    $logFile = __DIR__ . '/../logs/api_requests.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $deviceId = $_SERVER['HTTP_X_DEVICE_ID'] ?? 'Unknown';
    $appVersion = $_SERVER['HTTP_X_APP_VERSION'] ?? 'Unknown';
    
    $logEntry = sprintf(
        "[%s] IP: %s | Device: %s | Version: %s | Endpoint: %s | User-Agent: %s\n",
        $timestamp,
        $ip,
        $deviceId,
        $appVersion,
        $endpoint,
        $userAgent
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Validate app version
 */
function validateAppVersion($version) {
    if (empty($version)) {
        return true; // Allow if not provided
    }
    
    return version_compare($version, MIN_APP_VERSION, '>=');
}