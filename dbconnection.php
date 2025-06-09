<?php
require_once 'config.php';

class Database {
    private static $instance = null;
    private $pdo;
    private $stmt = null;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_PERSISTENT => true
            ];

            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Set timezone
            $this->pdo->exec("SET time_zone = '+08:00'");

        } catch (PDOException $e) {
            $error_message = "Connection failed: " . $e->getMessage();
            error_log($error_message);
            
            if (DEBUG_MODE) {
                throw new Exception($error_message);
            } else {
                throw new Exception("Database connection error. Please try again later.");
            }
        }
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Get database instance (Singleton pattern)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO instance
     */
    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollBack() {
        return $this->pdo->rollBack();
    }

    /**
     * Prepare a statement
     */
    public function prepare($sql) {
        $this->stmt = $this->pdo->prepare($sql);
        return $this->stmt;
    }

    /**
     * Execute a prepared statement
     */
    public function execute($params = []) {
        try {
            return $this->stmt->execute($params);
        } catch (PDOException $e) {
            $this->logError($e);
            throw $e;
        }
    }

    /**
     * Execute a query and return all results
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError($e);
            throw $e;
        }
    }

    /**
     * Execute a query and return single result
     */
    public function fetch($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->logError($e);
            throw $e;
        }
    }

    /**
     * Execute a query and return single column
     */
    public function fetchColumn($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError($e);
            throw $e;
        }
    }

    /**
     * Insert a record and return last insert id
     */
    public function insert($table, $data) {
        try {
            $fields = array_keys($data);
            $values = array_fill(0, count($fields), '?');
            
            $sql = "INSERT INTO " . $table . " (" . implode(", ", $fields) . ") 
                   VALUES (" . implode(", ", $values) . ")";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($data));
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->logError($e);
            throw $e;
        }
    }

    /**
     * Update records
     */
    public function update($table, $data, $where, $whereParams = []) {
        try {
            $fields = array_keys($data);
            $set = array_map(function($field) {
                return "$field = ?";
            }, $fields);
            
            $sql = "UPDATE " . $table . " SET " . implode(", ", $set) . " WHERE " . $where;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge(array_values($data), $whereParams));
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError($e);
            throw $e;
        }
    }

    /**
     * Delete records
     */
    public function delete($table, $where, $params = []) {
        try {
            $sql = "DELETE FROM " . $table . " WHERE " . $where;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError($e);
            throw $e;
        }
    }

    /**
     * Count records
     */
    public function count($table, $where = '', $params = []) {
        try {
            $sql = "SELECT COUNT(*) FROM " . $table;
            if ($where) {
                $sql .= " WHERE " . $where;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError($e);
            throw $e;
        }
    }

    /**
     * Check if record exists
     */
    public function exists($table, $where, $params = []) {
        return $this->count($table, $where, $params) > 0;
    }

    /**
     * Get the last error info
     */
    public function errorInfo() {
        return $this->stmt ? $this->stmt->errorInfo() : $this->pdo->errorInfo();
    }

    /**
     * Log database errors
     */
    private function logError(PDOException $e) {
        $errorMsg = sprintf(
            "Database Error: %s\nSQL State: %s\nError Code: %s\n",
            $e->getMessage(),
            $e->getCode(),
            isset($this->stmt) ? implode(',', $this->stmt->errorInfo()) : ''
        );
        
        error_log($errorMsg);
        
        if (DEBUG_MODE) {
            throw $e;
        }
    }

    /**
     * Close the connection
     */
    public function close() {
        $this->stmt = null;
        $this->pdo = null;
    }

    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}

// Initialize database connection
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    // Log the error
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Display user-friendly error page
    include 'error.php';
    exit();
}
?>
