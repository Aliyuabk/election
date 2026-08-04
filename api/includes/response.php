<?php
/**
 * API Response Handler
 * Standardizes JSON responses
 */

class Response {
    /**
     * Send JSON response
     */
    public static function send($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Success response
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        self::send([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], $statusCode);
    }
    
    /**
     * Error response
     */
    public static function error($message = 'An error occurred', $statusCode = 400, $errors = null) {
        self::send([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ], $statusCode);
    }
    
    /**
     * Unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::send([
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], 401);
    }
    
    /**
     * Validation error response
     */
    public static function validationError($errors) {
        self::send([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ], 422);
    }
}
?>