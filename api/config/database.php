<?php
/**
 * Database Configuration
 * Secure connection to MySQL database
 */

require_once __DIR__ . '/config.php';

define('DB_HOST', 'localhost');
define('DB_NAME', 'utgoohwm_election');
define('DB_USER', 'utgoohwm_election');
define('DB_PASS', 'Jiddahhh@1');

// Database connection class with improved security
class Database {
    private static $instance = null;
    private $connection;
    private $isConnected = false;
    
    private function __construct() {
        // Don't use persistent connections for security
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->connection->connect_error) {
            error_log("Database connection failed: " . $this->connection->connect_error);
            throw new Exception("Database connection failed");
        }
        
        $this->connection->set_charset("utf8mb4");
        
        // Set timezone
        $this->connection->query("SET time_zone = '+00:00'");
        
        // Enable strict mode for better data integrity
        $this->connection->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
        
        $this->isConnected = true;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            try {
                self::$instance = new Database();
            } catch (Exception $e) {
                error_log("Database initialization error: " . $e->getMessage());
                throw $e;
            }
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($sql) {
        if (!$this->isConnected) {
            $this->reconnect();
        }
        return $this->connection->prepare($sql);
    }
    
    public function query($sql) {
        if (!$this->isConnected) {
            $this->reconnect();
        }
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
        if ($this->isConnected) {
            $this->connection->close();
            $this->isConnected = false;
        }
    }
    
    private function reconnect() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->connection->connect_error) {
            throw new Exception("Reconnection failed: " . $this->connection->connect_error);
        }
        $this->connection->set_charset("utf8mb4");
        $this->isConnected = true;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialize
    private function __wakeup() {}
}