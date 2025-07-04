<?php
require_once 'Database.php';

class Configuration {
    private $conn;
    private $cache = [];
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->loadConfiguration();
    }
    
    private function loadConfiguration() {
        try {
            $stmt = $this->conn->prepare("SELECT chiave, valore, tipo FROM configurazioni");
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            foreach ($results as $row) {
                $value = $this->castValue($row['valore'], $row['tipo']);
                $this->cache[$row['chiave']] = $value;
            }
        } catch(PDOException $e) {
            error_log("Configuration Load Error: " . $e->getMessage());
        }
    }
    
    private function castValue($value, $type) {
        switch($type) {
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'boolean':
                return (bool) $value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
    
    public function get($key, $default = null) {
        return isset($this->cache[$key]) ? $this->cache[$key] : $default;
    }
    
    public function set($key, $value, $type = 'string', $description = null, $category = 'generale') {
        try {
            $sql = "INSERT INTO configurazioni (chiave, valore, tipo, descrizione, categoria) 
                    VALUES (:key, :value, :type, :description, :category)
                    ON DUPLICATE KEY UPDATE 
                    valore = :value, tipo = :type, descrizione = :description, categoria = :category";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':key' => $key,
                ':value' => $value,
                ':type' => $type,
                ':description' => $description,
                ':category' => $category
            ]);
            
            $this->cache[$key] = $this->castValue($value, $type);
            return true;
        } catch(PDOException $e) {
            error_log("Configuration Set Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getCostoGiornaliero($dipendente_id = null) {
        if ($dipendente_id) {
            try {
                $stmt = $this->conn->prepare("SELECT costo_giornaliero FROM dipendenti WHERE id = :id");
                $stmt->execute([':id' => $dipendente_id]);
                $result = $stmt->fetch();
                
                if ($result) {
                    return (float) $result['costo_giornaliero'];
                }
            } catch(PDOException $e) {
                error_log("Get Costo Dipendente Error: " . $e->getMessage());
            }
        }
        
        return $this->get('costo_dipendente_default', 80.00);
    }
    
    public function getKpiThresholds() {
        return [
            'ore_lavorative_giornaliere' => $this->get('ore_lavorative_giornaliere', 8),
            'tolleranza_ore_max' => $this->get('tolleranza_ore_max', 1.0),
            'alert_ore_minime' => $this->get('alert_ore_minime', 7),
            'tariffa_oraria_standard' => $this->get('tariffa_oraria_standard', 50.00),
            'vehicle_cost_per_km' => $this->get('vehicle_cost_per_km', 0.35)
        ];
    }
    
    public function getAll($category = null) {
        if ($category) {
            try {
                $stmt = $this->conn->prepare("SELECT * FROM configurazioni WHERE categoria = :category ORDER BY chiave");
                $stmt->execute([':category' => $category]);
                return $stmt->fetchAll();
            } catch(PDOException $e) {
                error_log("Get All Config Error: " . $e->getMessage());
                return [];
            }
        }
        
        return $this->cache;
    }
    
    public function initializeDefaults() {
        $defaults = [
            'costo_dipendente_default' => ['value' => '80.00', 'type' => 'float', 'desc' => 'Costo giornaliero default per dipendente', 'cat' => 'costi'],
            'ore_lavorative_giornaliere' => ['value' => '8', 'type' => 'integer', 'desc' => 'Ore lavorative standard per giornata', 'cat' => 'parametri'],
            'tolleranza_ore_max' => ['value' => '1.0', 'type' => 'float', 'desc' => 'Tolleranza massima in ore per validazioni', 'cat' => 'validazioni'],
            'tariffa_oraria_standard' => ['value' => '50.00', 'type' => 'float', 'desc' => 'Tariffa oraria standard per fatturazione', 'cat' => 'ricavi'],
            'alert_ore_minime' => ['value' => '7', 'type' => 'integer', 'desc' => 'Soglia minima ore per alert', 'cat' => 'alert'],
            'vehicle_cost_per_km' => ['value' => '0.35', 'type' => 'float', 'desc' => 'Costo per km veicolo aziendale', 'cat' => 'costi'],
            'efficiency_threshold_warning' => ['value' => '70', 'type' => 'integer', 'desc' => 'Soglia warning per efficiency rate (%)', 'cat' => 'kpi'],
            'efficiency_threshold_critical' => ['value' => '50', 'type' => 'integer', 'desc' => 'Soglia critica per efficiency rate (%)', 'cat' => 'kpi'],
            'profit_threshold_warning' => ['value' => '-20.00', 'type' => 'float', 'desc' => 'Soglia warning per profit/loss giornaliero', 'cat' => 'kpi'],
            'correlation_threshold_good' => ['value' => '85', 'type' => 'integer', 'desc' => 'Soglia per buona correlazione dati (%)', 'cat' => 'validazioni']
        ];
        
        foreach ($defaults as $key => $config) {
            $this->set($key, $config['value'], $config['type'], $config['desc'], $config['cat']);
        }
        
        return true;
    }
    
    public function backup() {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "config_backup_{$timestamp}.json";
            $data = json_encode($this->cache, JSON_PRETTY_PRINT);
            
            file_put_contents(__DIR__ . "/../backups/{$filename}", $data);
            return $filename;
        } catch(Exception $e) {
            error_log("Configuration Backup Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function restore($filename) {
        try {
            $data = json_decode(file_get_contents(__DIR__ . "/../backups/{$filename}"), true);
            
            foreach ($data as $key => $value) {
                $this->set($key, $value);
            }
            
            return true;
        } catch(Exception $e) {
            error_log("Configuration Restore Error: " . $e->getMessage());
            return false;
        }
    }
}
?>