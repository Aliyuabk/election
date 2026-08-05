<?php
/**
 * API Response Handler
 * Standardizes JSON responses for mobile app
 */

class Response {
    /**
     * Send JSON response
     */
    public static function send($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('X-Powered-By: Election Guru Mobile API');
        
        if (is_array($data) && !isset($data['timestamp'])) {
            $data['timestamp'] = date('Y-m-d H:i:s');
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Success response
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        self::send($response, $statusCode);
    }
    
    /**
     * Error response
     */
    public static function error($message = 'An error occurred', $statusCode = 400, $errors = null) {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        self::send($response, $statusCode);
    }
    
    /**
     * Unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized') {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => 'UNAUTHORIZED',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        self::send($response, 401);
    }
    
    /**
     * Validation error response
     */
    public static function validationError($errors) {
        $response = [
            'success' => false,
            'message' => 'Validation error',
            'errors' => $errors,
            'code' => 'VALIDATION_ERROR',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        self::send($response, 422);
    }
    
    /**
     * Rate limit exceeded response
     */
    public static function rateLimitExceeded($message = 'Rate limit exceeded. Please try again later.') {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => 'RATE_LIMIT_EXCEEDED',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        self::send($response, 429);
    }
    
    /**
     * App version outdated response
     */
    public static function appUpdateRequired($message = 'App update required') {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => 'APP_UPDATE_REQUIRED',
            'update_url' => 'https://play.google.com/store/apps/details?id=com.eguruelection.app',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        self::send($response, 426);
    }
}