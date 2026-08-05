<?php
/**
 * Mobile Authentication Helper
 * Device binding and fingerprint authentication support
 */

require_once __DIR__ . '/../config/database.php';

class MobileAuth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Bind device to user
     */
    public function bindDevice($userId, $deviceId, $deviceName = null) {
        // Check if device is already bound to another user
        $checkStmt = $this->db->prepare("
            SELECT user_id FROM users WHERE device_id = ? AND user_id != ?
        ");
        $checkStmt->bind_param("si", $deviceId, $userId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $checkStmt->close();
        
        if ($result->num_rows > 0) {
            return ['success' => false, 'message' => 'Device already bound to another user'];
        }
        
        // Update user device binding
        $stmt = $this->db->prepare("
            UPDATE users SET device_id = ?, device_bound = 1 WHERE id = ?
        ");
        $stmt->bind_param("si", $deviceId, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Log device binding
        $this->db->query("
            INSERT INTO activity_logs (user_id, activity_type, description, device_id, created_at)
            VALUES ($userId, 'device_bind', 'Device bound: $deviceName', '$deviceId', NOW())
        ");
        
        return ['success' => true, 'message' => 'Device bound successfully'];
    }
    
    /**
     * Verify device binding
     */
    public function verifyDevice($userId, $deviceId) {
        $stmt = $this->db->prepare("
            SELECT device_id, device_bound FROM users WHERE id = ? AND status = 'active'
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            return false;
        }
        
        if ($user['device_bound'] == 0) {
            return true;
        }
        
        if ($user['device_id'] && $user['device_id'] === $deviceId) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Enable fingerprint login
     */
    public function enableFingerprint($userId, $fingerprintHash) {
        $stmt = $this->db->prepare("
            UPDATE users SET fingerprint_hash = ?, fingerprint_enabled = 1 WHERE id = ?
        ");
        $stmt->bind_param("si", $fingerprintHash, $userId);
        $stmt->execute();
        $stmt->close();
        
        $this->db->query("
            INSERT INTO activity_logs (user_id, activity_type, description, created_at)
            VALUES ($userId, 'fingerprint_enabled', 'Fingerprint login enabled', NOW())
        ");
        
        return ['success' => true, 'message' => 'Fingerprint login enabled'];
    }
    
    /**
     * Disable fingerprint login
     */
    public function disableFingerprint($userId) {
        $stmt = $this->db->prepare("
            UPDATE users SET fingerprint_hash = NULL, fingerprint_enabled = 0 WHERE id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
        
        $this->db->query("
            INSERT INTO activity_logs (user_id, activity_type, description, created_at)
            VALUES ($userId, 'fingerprint_disabled', 'Fingerprint login disabled', NOW())
        ");
        
        return ['success' => true, 'message' => 'Fingerprint login disabled'];
    }
    
    /**
     * Verify fingerprint
     */
    public function verifyFingerprint($userId, $fingerprintHash) {
        $stmt = $this->db->prepare("
            SELECT fingerprint_hash, fingerprint_enabled FROM users WHERE id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user || !$user['fingerprint_enabled']) {
            return ['success' => false, 'message' => 'Fingerprint not enabled'];
        }
        
        if ($user['fingerprint_hash'] === $fingerprintHash) {
            return ['success' => true, 'message' => 'Fingerprint verified'];
        }
        
        return ['success' => false, 'message' => 'Fingerprint mismatch'];
    }
}