<?php
require_once __DIR__ . '/../helpers/JWT.php';

class AuthMiddleware {
    public static function authenticate() {
        $headers = getallheaders();
        
        // Get token from Authorization header
        $token = null;
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        }
        
        if (!$token) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit;
        }
        
        $payload = JWT::verify($token);
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
            exit;
        }
        
        return $payload;
    }
}
?>