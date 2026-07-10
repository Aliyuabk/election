<?php
// Secure database configuration
class Database {
    private static $instance = null;
    private $conn;
    
    // Database credentials - THESE SHOULD BE OUTSIDE PUBLIC WEB ROOT
    private $host = 'localhost';
    private $db_name = 'utgoohwm_election';
    private $username = 'utgoohwm_election'; // Get from cPanel
    private $password = 'Jiddahhh@1'; // Get from cPanel
    
    private function __construct() {
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            // Set charset to UTF-8
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}
?>