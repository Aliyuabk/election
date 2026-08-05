<?php
/**
 * Database Configuration
 * Secure connection to MySQL database
 */

// Error reporting - disable in production
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// CORS Configuration for mobile app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Device-ID, X-App-Version, X-Platform');
header('Access-Control-Expose-Headers: Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'utgoohwm_election');
define('DB_USER', 'utgoohwm_election');
define('DB_PASS', 'Jiddahhh@1');

// App configuration
define('BASE_URL', 'https://eguruelection.kowagurutech.ng/api/');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB
define('APP_VERSION', '1.0.0');
define('MIN_APP_VERSION', '1.0.0');

// Rate limiting
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60); // seconds

// Create upload directories
$directories = [
    UPLOAD_PATH . 'profiles/',
    UPLOAD_PATH . 'ec8a/',
    UPLOAD_PATH . 'incidents/',
    UPLOAD_PATH . 'chat/',
    UPLOAD_PATH . 'media/',
    __DIR__ . '/../logs/'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Database connection class
class Database {
    private static $instance = null;
    private $connection;
    private $isConnected = false;
    
    private function __construct() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->connection->connect_error) {
            error_log("Database connection failed: " . $this->connection->connect_error);
            throw new Exception("Database connection failed");
        }
        
        $this->connection->set_charset("utf8mb4");
        $this->connection->query("SET time_zone = '+00:00'");
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
    
    private function __clone() {}
    private function __wakeup() {}
}