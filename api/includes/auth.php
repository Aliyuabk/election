<?php
/**
 * Authentication Handler
 * JWT-based authentication for API
 */

require_once dirname(__DIR__) . '/config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generate JWT Token
     */
    public function generateToken($userId, $roleId, $tenantId = null) {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        
        $payload = base64_encode(json_encode([
            'user_id' => $userId,
            'role_id' => $roleId,
            'tenant_id' => $tenantId,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRY
        ]));
        
        $signature = hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true);
        $signature = base64_encode($signature);
        
        return $header . '.' . $payload . '.' . $signature;
    }
    
    /**
     * Verify JWT Token
     */
    public function verifyToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        list($header, $payload, $signature) = $parts;
        
        $expectedSignature = hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true);
        $expectedSignature = base64_encode($expectedSignature);
        
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }
        
        $payloadData = json_decode(base64_decode($payload), true);
        
        if (!$payloadData || $payloadData['exp'] < time()) {
            return false;
        }
        
        return $payloadData;
    }
    
    /**
     * Authenticate user from request
     */
    public function authenticate() {
        $headers = getallheaders();
        
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (empty($authHeader)) {
            // Check for bearer token in $_SERVER
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            }
        }
        
        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return false;
        }
        
        $token = $matches[1];
        $payload = $this->verifyToken($token);
        
        if (!$payload) {
            return false;
        }
        
        // Get user from database
        $stmt = $this->db->prepare("
            SELECT id, tenant_id, role_id, first_name, last_name, email, phone, status 
            FROM users WHERE id = ? AND status = 'active'
        ");
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $payload['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            return false;
        }
        
        return $user;
    }
    
    /**
     * Hash password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Login user
     */
    public function login($email, $password, $deviceId = null, $ipAddress = null) {
        // Get user by email
        $stmt = $this->db->prepare("
            SELECT id, tenant_id, role_id, first_name, last_name, email, phone, 
                   password_hash, status, two_factor_enabled 
            FROM users WHERE email = ? AND status != 'archived'
        ");
        
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            // Log failed attempt
            $this->logLoginAttempt($email, $ipAddress, $deviceId, false);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is ' . $user['status']];
        }
        
        if (!$this->verifyPassword($password, $user['password_hash'])) {
            $this->logLoginAttempt($email, $ipAddress, $deviceId, false);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Log successful login
        $this->logLoginAttempt($email, $ipAddress, $deviceId, true);
        
        // Generate token
        $token = $this->generateToken($user['id'], $user['role_id'], $user['tenant_id']);
        
        // Update last login
        $this->updateLastLogin($user['id'], $ipAddress, $deviceId);
        
        return [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'full_name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role_id' => $user['role_id'],
                'tenant_id' => $user['tenant_id'],
                'two_factor_enabled' => (bool)$user['two_factor_enabled']
            ]
        ];
    }
    
    /**
     * Log login attempt
     */
    private function logLoginAttempt($email, $ipAddress, $deviceId, $success) {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (email, ip_address, user_agent, attempt_type, success, created_at)
            VALUES (?, ?, ?, 'login', ?, NOW())
        ");
        
        if (!$stmt) return;
        
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $successInt = $success ? 1 : 0;
        
        $stmt->bind_param("sssi", $email, $ipAddress, $userAgent, $successInt);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Update last login
     */
    private function updateLastLogin($userId, $ipAddress, $deviceId) {
        $stmt = $this->db->prepare("
            UPDATE users SET 
                last_login_at = NOW(),
                last_login_ip = ?,
                device_id = ?
            WHERE id = ?
        ");
        
        if (!$stmt) return;
        
        $stmt->bind_param("ssi", $ipAddress, $deviceId, $userId);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Logout user
     */
    public function logout($userId) {
        // Log logout activity
        $this->db->query("
            INSERT INTO activity_logs (user_id, activity_type, description, created_at)
            VALUES ($userId, 'logout', 'User logged out successfully', NOW())
        ");
        
        return ['success' => true];
    }
}
?>