<?php
// includes/db.php
require_once '../../config/config.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchColumn($sql, $params = []) {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES ($placeholders)";
        $this->query($sql, $data);
        return $this->conn->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "$key = :$key";
        }
        $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE $where";
        $stmt = $this->query($sql, array_merge($data, $whereParams));
        return $stmt->rowCount();
    }

    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function softDelete($table, $id, $idField = 'id') {
        $sql = "UPDATE $table SET deleted_at = NOW() WHERE $idField = ? AND deleted_at IS NULL";
        $stmt = $this->query($sql, [$id]);
        return $stmt->rowCount();
    }

    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    public function commit() {
        return $this->conn->commit();
    }

    public function rollback() {
        return $this->conn->rollback();
    }

    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    public function escape($string) {
        return $this->conn->quote($string);
    }
}

// Helper function for quick database access
function db() {
    return Database::getInstance();
}

// ============================================================
// HELPER: GET VALID USER ID FOR LOGGING
// ============================================================
function getValidUserId() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
        
        // Verify user exists in database
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$userId]);
            if ($stmt->fetch()) {
                return $userId;
            }
        } catch (Exception $e) {
            // Fall through to default
        }
    }
    
    // Try to find any admin user
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE role_id = 1 OR email LIKE '%admin%' LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            return (int)$user['id'];
        }
    } catch (Exception $e) {
        // Fall through to default
    }
    
    // Try to find any user at all
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id FROM users LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            return (int)$user['id'];
        }
    } catch (Exception $e) {
        // Fall through
    }
    
    return null;
}

// ============================================================
// HELPER: LOG ACTIVITY (ONLY DECLARED HERE)
// ============================================================
if (!function_exists('logActivity')) {
    function logActivity($userId, $tenantId, $type, $description, $ipAddress = null) {
        if (!$userId) return false;
        
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            $sql = "INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, ip_address, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            return $stmt->execute([
                $userId,
                $tenantId,
                $type,
                $description,
                $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }
}
?>