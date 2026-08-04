<?php
/**
 * Database Configuration
 * Secure connection to MySQL database
 */


define('DB_HOST', 'localhost');
define('DB_NAME', 'utgoohwm_election');
define('DB_USER', 'utgoohwm_election');
define('DB_PASS', 'Jiddahhh@1');

define('API_BASE_URL', 'https://eguruelection.kowagurutech.ng/api/');
define('JWT_SECRET', 'your_super_secret_jwt_key_change_this');
define('JWT_EXPIRY', 86400); // 24 hours

// CORS Configuration
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Error reporting - disable in production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/error.log');

// Database connection class
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function escapeString($str) {
        return $this->connection->real_escape_string($str);
    }
    
    public function lastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function affectedRows() {
        return $this->connection->affected_rows;
    }
    
    public function close() {
        $this->connection->close();
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialize
    private function __wakeup() {}
}
?>