<?php
/**
 * Mobile App Constants and Helper Functions
 */

// Role levels for mobile app
define('ROLE_PU_AGENT', 'pu_agent');
define('ROLE_PARTY_AGENT', 'party_agent');
define('ROLE_VOLUNTEER', 'volunteer');
define('ROLE_OBSERVER', 'observer');

// Check-in types
define('CHECKIN_ARRIVAL', 'arrival');
define('CHECKIN_DEPARTURE', 'departure');
define('CHECKIN_MATERIAL_RECEIVED', 'material_received');
define('CHECKIN_ACCREDITATION_STARTED', 'accreditation_started');
define('CHECKIN_VOTING_STARTED', 'voting_started');
define('CHECKIN_VOTING_ENDED', 'voting_ended');
define('CHECKIN_COUNTING_STARTED', 'counting_started');
define('CHECKIN_COUNTING_ENDED', 'counting_ended');

// Incident types
define('INCIDENT_VIOLENCE', 'violence');
define('INCIDENT_INTIMIDATION', 'intimidation');
define('INCIDENT_BALLOT_STUFFING', 'ballot_stuffing');
define('INCIDENT_VOTE_BUYING', 'vote_buying');
define('INCIDENT_VOTER_SUPPRESSION', 'voter_suppression');
define('INCIDENT_MATERIAL_SHORTAGE', 'material_shortage');
define('INCIDENT_DELAY', 'delay');
define('INCIDENT_TECHNICAL_ISSUE', 'technical_issue');
define('INCIDENT_PANIC', 'panic_button');
define('INCIDENT_OTHER', 'other');

// Message types
define('MSG_TEXT', 'text');
define('MSG_IMAGE', 'image');
define('MSG_VIDEO', 'video');
define('MSG_AUDIO', 'audio');
define('MSG_FILE', 'file');
define('MSG_LOCATION', 'location');

/**
 * Rate Limiting function
 */
function checkRateLimit($key, $limit = RATE_LIMIT_REQUESTS, $window = RATE_LIMIT_WINDOW) {
    $rateLimitFile = __DIR__ . '/../logs/rate_limit_' . md5($key) . '.json';
    $currentTime = time();
    
    if (file_exists($rateLimitFile)) {
        $data = json_decode(file_get_contents($rateLimitFile), true);
        
        if ($currentTime - $data['window_start'] > $window) {
            $data = ['window_start' => $currentTime, 'count' => 1];
        } else {
            $data['count']++;
        }
    } else {
        $data = ['window_start' => $currentTime, 'count' => 1];
    }
    
    file_put_contents($rateLimitFile, json_encode($data));
    
    return $data['count'] <= $limit;
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
    return preg_match('/^data:image\/(jpeg|png|jpg|gif);base64,/', $base64String);
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
    $platform = $_SERVER['HTTP_X_PLATFORM'] ?? 'Unknown';
    
    $logEntry = sprintf(
        "[%s] IP: %s | Platform: %s | Device: %s | Version: %s | Endpoint: %s | User-Agent: %s\n",
        $timestamp, $ip, $platform, $deviceId, $appVersion, $endpoint, $userAgent
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Validate app version
 */
function validateAppVersion($version) {
    if (empty($version)) {
        return true;
    }
    return version_compare($version, MIN_APP_VERSION, '>=');
}