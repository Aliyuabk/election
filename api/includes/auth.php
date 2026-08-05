<?php
/**
 * Authentication Handler
 * JWT-based authentication for mobile API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class Auth {
    private $db;
    private $jwtSecret;
    private $jwtExpiry;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->jwtSecret = 'your_super_secret_jwt_key_change_this_2026';
        $this->jwtExpiry = 86400; // 24 hours
    }
    
    /**
     * Generate JWT Token
     */
    public function generateToken($userId, $roleId, $tenantId = null, $deviceId = null) {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $header = $this->base64UrlEncode($header);
        
        $payload = json_encode([
            'user_id' => $userId,
            'role_id' => $roleId,
            'tenant_id' => $tenantId,
            'device_id' => $deviceId,
            'iat' => time(),
            'exp' => time() + $this->jwtExpiry
        ]);
        $payload = $this->base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', $header . '.' . $payload, $this->jwtSecret, true);
        $signature = $this->base64UrlEncode($signature);
        
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
        $expectedSignature = hash_hmac('sha256', $header . '.' . $payload, $this->jwtSecret, true);
        $expectedSignature = $this->base64UrlEncode($expectedSignature);
        
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }
        
        $payloadData = json_decode($this->base64UrlDecode($payload), true);
        
        if (!$payloadData || $payloadData['exp'] < time()) {
            return false;
        }
        
        return $payloadData;
    }
    
    /**
     * Authenticate user from request
     */
    public function authenticate() {
        // Check app version
        $appVersion = $_SERVER['HTTP_X_APP_VERSION'] ?? null;
        if ($appVersion && version_compare($appVersion, MIN_APP_VERSION, '<')) {
            Response::appUpdateRequired('Please update your app to continue');
        }
        
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Response::unauthorized('Missing or invalid authorization token');
        }
        
        $token = $matches[1];
        $payload = $this->verifyToken($token);
        
        if (!$payload) {
            Response::unauthorized('Invalid or expired token');
        }
        
        // Check rate limit
        $rateLimitKey = 'auth_' . $payload['user_id'] . '_' . date('Y-m-d-H');
        if (!checkRateLimit($rateLimitKey, 100, 3600)) {
            Response::rateLimitExceeded();
        }
        
        // Get user from database
        $stmt = $this->db->prepare("
            SELECT id, tenant_id, role_id, first_name, last_name, email, phone, status, 
                   photograph_url, pu_id, ward_id, lga_id, state_id, user_code
            FROM users WHERE id = ? AND status != 'archived'
        ");
        
        if (!$stmt) {
            Response::error('Database error', 500);
        }
        
        $stmt->bind_param("i", $payload['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            Response::unauthorized('User not found');
        }
        
        if ($user['status'] !== 'active') {
            Response::error('Account is ' . $user['status'], 403);
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
        // Check rate limit
        $rateLimitKey = 'login_' . $email . '_' . date('Y-m-d-H');
        if (!checkRateLimit($rateLimitKey, 10, 3600)) {
            return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
        }
        
        // Get user by email
        $stmt = $this->db->prepare("
            SELECT id, tenant_id, role_id, first_name, last_name, email, phone, 
                   password_hash, status, photograph_url, user_code,
                   pu_id, ward_id, lga_id, state_id, device_bound, device_id
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
            $this->logLoginAttempt($email, $ipAddress, $deviceId, false);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is ' . $user['status']];
        }
        
        if (!$this->verifyPassword($password, $user['password_hash'])) {
            $this->logLoginAttempt($email, $ipAddress, $deviceId, false);
            $this->incrementLoginAttempts($user['id']);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Reset login attempts
        $this->resetLoginAttempts($user['id']);
        $this->logLoginAttempt($email, $ipAddress, $deviceId, true);
        
        // Generate token
        $token = $this->generateToken($user['id'], $user['role_id'], $user['tenant_id'], $deviceId);
        
        // Get role details
        $roleResult = $this->db->query("SELECT name, level FROM roles WHERE id = " . $user['role_id']);
        $role = $roleResult->fetch_assoc();
        
        // Update last login
        $this->updateLastLogin($user['id'], $ipAddress, $deviceId);
        
        // Get user permissions
        $permissions = $this->getUserPermissions($user['role_id']);
        
        // Get assigned polling unit info
        $puData = null;
        if ($user['pu_id']) {
            $puResult = $this->db->query("
                SELECT id, code, name, registered_voters 
                FROM polling_units WHERE id = " . $user['pu_id']
            );
            $puData = $puResult->fetch_assoc();
        }
        
        // Get dashboard features based on role
        $dashboardFeatures = $this->getDashboardFeatures($role['level'] ?? '');
        
        return [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'user_code' => $user['user_code'],
                'full_name' => $user['first_name'] . ' ' . $user['last_name'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role_id' => $user['role_id'],
                'role_name' => $role['name'] ?? '',
                'role_level' => $role['level'] ?? '',
                'tenant_id' => $user['tenant_id'],
                'photograph_url' => $user['photograph_url'],
                'permissions' => $permissions,
                'assigned_pu' => $puData,
                'dashboard_features' => $dashboardFeatures
            ]
        ];
    }
    
    /**
     * Get dashboard features based on role
     */
    private function getDashboardFeatures($roleLevel) {
        $features = [];
        
        switch ($roleLevel) {
            case ROLE_PU_AGENT:
                $features = [
                    'home' => true,
                    'checkin' => true,
                    'checklist' => true,
                    'accreditation' => true,
                    'vote_count' => true,
                    'ec8a' => true,
                    'media' => true,
                    'incidents' => true,
                    'panic' => true,
                    'chat' => true,
                    'notifications' => true,
                    'history' => true,
                    'profile' => true,
                    'offline_sync' => true
                ];
                break;
                
            case ROLE_PARTY_AGENT:
                $features = [
                    'home' => true,
                    'observations' => true,
                    'evidence' => true,
                    'incidents' => true,
                    'chat' => true,
                    'notifications' => true,
                    'profile' => true
                ];
                break;
                
            case ROLE_VOLUNTEER:
                $features = [
                    'home' => true,
                    'tasks' => true,
                    'community_reports' => true,
                    'media' => true,
                    'chat' => true,
                    'notifications' => true,
                    'profile' => true
                ];
                break;
                
            case ROLE_OBSERVER:
                $features = [
                    'home' => true,
                    'observation_form' => true,
                    'reports' => true,
                    'media' => true,
                    'incidents' => true,
                    'dashboard' => true,
                    'notifications' => true,
                    'profile' => true
                ];
                break;
                
            default:
                $features = ['home' => true, 'profile' => true];
        }
        
        return $features;
    }
    
    /**
     * Get user permissions
     */
    private function getUserPermissions($roleId) {
        $result = $this->db->query("SELECT permissions_json FROM roles WHERE id = $roleId");
        $row = $result->fetch_assoc();
        if ($row && $row['permissions_json']) {
            return json_decode($row['permissions_json'], true);
        }
        return [];
    }
    
    /**
     * Log login attempt
     */
    private function logLoginAttempt($email, $ipAddress, $deviceId, $success) {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (email, ip_address, user_agent, attempt_type, success, device_id, created_at)
            VALUES (?, ?, ?, 'login', ?, ?, NOW())
        ");
        
        if (!$stmt) return;
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $successInt = $success ? 1 : 0;
        
        $stmt->bind_param("sssis", $email, $ipAddress, $userAgent, $successInt, $deviceId);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Increment login attempts
     */
    private function incrementLoginAttempts($userId) {
        $this->db->query("UPDATE users SET login_attempts = login_attempts + 1 WHERE id = $userId");
    }
    
    /**
     * Reset login attempts
     */
    private function resetLoginAttempts($userId) {
        $this->db->query("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = $userId");
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
     * Base64 URL Encode
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL Decode
     */
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}