<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'employee_analytics';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown database') !== false) {
                throw new Exception("Database 'employee_analytics' non trovato. Eseguire prima il setup.");
            }
            throw new Exception("Connection Error: " . $e->getMessage());
        }
        
        return $this->conn;
    }
    
    public function getConnectionWithoutDatabase() {
        try {
            $conn = new PDO(
                "mysql:host=" . $this->host . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $conn;
        } catch(PDOException $e) {
            throw new Exception("MySQL Connection Error: " . $e->getMessage());
        }
    }
    
    public function createDatabase() {
        try {
            $conn = $this->getConnectionWithoutDatabase();
            $sql = file_get_contents(__DIR__ . '/../database_schema.sql');
            
            if (!$sql) {
                throw new Exception("Impossibile leggere il file database_schema.sql");
            }
            
            $conn->exec($sql);
            return true;
        } catch(PDOException $e) {
            throw new Exception("Database Creation Error: " . $e->getMessage());
        }
    }
    
    public function databaseExists() {
        try {
            $conn = $this->getConnectionWithoutDatabase();
            $stmt = $conn->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :dbname");
            $stmt->execute([':dbname' => $this->db_name]);
            return $stmt->fetch() !== false;
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>