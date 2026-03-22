<?php
require_once 'config.php';

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    
    private $conn;
    private static $instance = null;
    
    private function __construct() {
        try {
            $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
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
    
    // Prepare and execute query with parameters
    public function query($sql, $params = [], $types = "") {
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt;
    }
    
    // Get single record
    public function fetchOne($sql, $params = [], $types = "") {
        $stmt = $this->query($sql, $params, $types);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    // Get multiple records
    public function fetchAll($sql, $params = [], $types = "") {
        $stmt = $this->query($sql, $params, $types);
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Insert record and return ID
    public function insert($sql, $params = [], $types = "") {
        $stmt = $this->query($sql, $params, $types);
        return $this->conn->insert_id;
    }
    
    // Update/Delete record
    public function execute($sql, $params = [], $types = "") {
        $stmt = $this->query($sql, $params, $types);
        return $stmt->affected_rows;
    }
    
    // Escape string
    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }
}

// Global function to get database instance
function db() {
    return Database::getInstance()->getConnection();
}
?>