<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    
    public function __construct() {
        $this->loadEnvVariables();
    }
    
    private function loadEnvVariables() {
        // Load .env file if exists
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') !== 0 && strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
        
        // Set database connection parameters
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'employee_analytics';
        $this->username = $_ENV['DB_USERNAME'] ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? '';
    }
    
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
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Errore di connessione al database. Verificare le credenziali.");
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