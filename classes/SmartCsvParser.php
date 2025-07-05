<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/ImportLogger.php';

/**
 * Smart CSV Parser - Fase 3
 * Parser intelligente che riconosce dati fissi vs dinamici
 * Gestisce associazioni automatiche e validazioni avanzate
 */
class SmartCsvParser {
    private $conn;
    private $logger;
    private $config;
    
    // Cache per performance
    private $master_employees_cache = null;
    private $master_companies_cache = null;
    private $master_vehicles_cache = null;
    private $config_cache = null;
    
    // Statistiche import
    private $stats = [
        'processed' => 0,
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
        'duplicates_detected' => 0,
        'associations_created' => 0,
        'validation_errors' => 0
    ];
    
    private $errors = [];
    private $warnings = [];
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->logger = new ImportLogger('smart_csv_parser');
        
        $this->initializeCaches();
        $this->loadSystemConfig();
    }
    
    /**
     * Inizializza cache master data per performance
     */
    private function initializeCaches() {
        // Cache dipendenti fissi (master)
        $stmt = $this->conn->prepare("
            SELECT id, nome, cognome, nome_completo, email, ruolo, costo_giornaliero, attivo
            FROM master_dipendenti_fixed 
            WHERE attivo = 1
        ");
        $stmt->execute();
        $this->master_employees_cache = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->master_employees_cache[$row['id']] = $row;
            // Crea anche un indice per nome completo per ricerca veloce
            $this->master_employees_cache['by_name'][strtolower($row['nome_completo'])] = $row['id'];
            $this->master_employees_cache['by_name'][strtolower($row['nome'] . ' ' . $row['cognome'])] = $row['id'];
        }
        
        // Cache aziende master
        $stmt = $this->conn->prepare("
            SELECT id, nome, nome_breve, settore, attivo
            FROM master_aziende 
            WHERE attivo = 1
        ");
        $stmt->execute();
        $this->master_companies_cache = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->master_companies_cache[$row['id']] = $row;
            // Indici per ricerca
            $this->master_companies_cache['by_name'][strtolower($row['nome'])] = $row['id'];
            if ($row['nome_breve']) {
                $this->master_companies_cache['by_name'][strtolower($row['nome_breve'])] = $row['id'];
            }
        }
        
        // Cache veicoli
        $stmt = $this->conn->prepare("
            SELECT id, nome, tipo, marca, modello, attivo
            FROM master_veicoli_config 
            WHERE attivo = 1
        ");
        $stmt->execute();
        $this->master_vehicles_cache = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->master_vehicles_cache[$row['id']] = $row;
            $this->master_vehicles_cache['by_name'][strtolower($row['nome'])] = $row['id'];
        }
        
        $this->logger->info("Cache inizializzate", [
            'dipendenti' => count($this->master_employees_cache) - 1, // -1 per by_name
            'aziende' => count($this->master_companies_cache) - 1,
            'veicoli' => count($this->master_vehicles_cache) - 1
        ]);
    }
    
    /**
     * Carica configurazioni sistema
     */
    private function loadSystemConfig() {
        $stmt = $this->conn->prepare("
            SELECT categoria, chiave, valore, tipo 
            FROM system_config 
            ORDER BY categoria, chiave
        ");
        $stmt->execute();
        
        $this->config_cache = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = $row['valore'];
            
            // Converti al tipo appropriato
            switch ($row['tipo']) {
                case 'int':
                    $value = (int) $value;
                    break;
                case 'float':
                    $value = (float) $value;
                    break;
                case 'boolean':
                    $value = ($value === 'true' || $value === '1');
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $this->config_cache[$row['categoria']][$row['chiave']] = $value;
        }
        
        $this->logger->info("Configurazioni caricate", [
            'categorie' => array_keys($this->config_cache)
        ]);
    }
    
    /**
     * Ottiene valore configurazione
     */
    private function getConfig($categoria, $chiave, $default = null) {
        return $this->config_cache[$categoria][$chiave] ?? $default;
    }
    
    /**
     * Riconosce automaticamente tipo CSV e processa di conseguenza
     */
    public function processFile($filepath, $suggested_type = null) {
        try {
            $this->errors = [];
            $this->warnings = [];
            $this->resetStats();
            
            if (!file_exists($filepath)) {
                throw new Exception("File non trovato: $filepath");
            }
            
            $this->logger->info("Inizio processing file", ['file' => $filepath, 'suggested_type' => $suggested_type]);
            
            // Auto-detect tipo file
            $detected_type = $this->detectFileType($filepath, $suggested_type);
            $this->logger->info("Tipo file rilevato", ['type' => $detected_type]);
            
            // Processa file in base al tipo
            switch ($detected_type) {
                case 'attivita':
                    return $this->processAttivitaFile($filepath);
                case 'timbrature':
                    return $this->processTimbratureFile($filepath);
                case 'teamviewer':
                    return $this->processTeamviewerFile($filepath);
                case 'calendario':
                    return $this->processCalendarioFile($filepath);
                default:
                    throw new Exception("Tipo file non riconosciuto: $detected_type");
            }
            
        } catch (Exception $e) {
            $this->logger->error("Errore processing file", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => $this->stats,
                'warnings' => $this->warnings,
                'errors' => $this->errors
            ];
        }
    }
    
    /**
     * Rileva automaticamente il tipo di file CSV
     */
    private function detectFileType($filepath, $suggested_type = null) {
        $filename = strtolower(basename($filepath));
        
        // Se tipo suggerito, usalo
        if ($suggested_type) {
            return $suggested_type;
        }
        
        // Rileva da nome file
        if (strpos($filename, 'attivita') !== false) {
            return 'attivita';
        }
        if (strpos($filename, 'timbrature') !== false) {
            return 'timbrature';
        }
        if (strpos($filename, 'teamviewer') !== false) {
            return 'teamviewer';
        }
        if (strpos($filename, 'calendario') !== false) {
            return 'calendario';
        }
        
        // Rileva dal contenuto (header)
        $handle = fopen($filepath, 'r');
        if ($handle) {
            $header_line = fgets($handle);
            fclose($handle);
            
            $header_lower = strtolower($header_line);
            
            if (strpos($header_lower, 'creato da') !== false && strpos($header_lower, 'durata') !== false) {
                return 'attivita';
            }
            if (strpos($header_lower, 'ora_inizio') !== false && strpos($header_lower, 'ora_fine') !== false) {
                return 'timbrature';
            }
            if (strpos($header_lower, 'computer') !== false || strpos($header_lower, 'sessione') !== false) {
                return 'teamviewer';
            }
        }
        
        // Default fallback
        return 'attivita';
    }
    
    /**
     * Processa file attività con logica smart
     */
    private function processAttivitaFile($filepath) {
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            throw new Exception("Impossibile aprire file attività");
        }
        
        // Auto-detect separator e leggi header
        $separator = $this->detectCsvSeparator($handle);
        $header = fgetcsv($handle, 0, $separator);
        $header = $this->removeBomFromHeader($header);
        
        $this->conn->beginTransaction();
        
        try {
            while (($row = fgetcsv($handle, 0, $separator)) !== FALSE) {
                $this->stats['processed']++;
                
                if (count($row) !== count($header)) {
                    $this->warnings[] = "Riga {$this->stats['processed']}: numero colonne non corrispondente";
                    $this->stats['skipped']++;
                    continue;
                }
                
                $data = array_combine($header, $row);
                $data = $this->cleanData($data);
                
                $result = $this->processAttivitaRow($data);
                if ($result === 'inserted') {
                    $this->stats['inserted']++;
                } elseif ($result === 'updated') {
                    $this->stats['updated']++;
                } elseif ($result === 'duplicate') {
                    $this->stats['duplicates_detected']++;
                } else {
                    $this->stats['skipped']++;
                }
            }
            
            fclose($handle);
            $this->conn->commit();
            
            return [
                'success' => true,
                'type' => 'attivita',
                'stats' => $this->stats,
                'warnings' => $this->warnings,
                'errors' => $this->errors
            ];
            
        } catch (Exception $e) {
            fclose($handle);
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Processa singola riga attività con logica smart
     */
    private function processAttivitaRow($data) {
        try {
            // 1. GESTIONE DIPENDENTE (dato fisso)
            $dipendente_id = $this->getSmartEmployeeId($data['Creato da'] ?? '');
            if (!$dipendente_id) {
                $this->warnings[] = "Dipendente non riconosciuto: " . ($data['Creato da'] ?? 'N/A');
                return false;
            }
            
            // 2. GESTIONE AZIENDA (smart association)
            $azienda_id = null;
            if (!empty($data['Azienda'])) {
                $azienda_id = $this->getSmartCompanyId($data['Azienda']);
            }
            
            // 3. GESTIONE PROGETTO (dinamico)
            $progetto_id = null;
            if (!empty($data['Riferimento Progetto'])) {
                $progetto_id = $this->getOrCreateProject($data['Riferimento Progetto'], $azienda_id);
            }
            
            // 4. ANTI-DUPLICAZIONE INTELLIGENTE
            if ($this->isDuplicateActivity($data, $dipendente_id)) {
                $this->stats['duplicates_detected']++;
                return 'duplicate';
            }
            
            // 5. INSERIMENTO CON GESTIONE DINAMICA
            $sql = "INSERT INTO attivita (
                dipendente_id, cliente_id, progetto_id, ticket_id, 
                data_inizio, data_fine, durata_ore, descrizione, 
                riferimento_progetto, creato_da, fatturabile, 
                is_duplicate, master_azienda_id
            ) VALUES (
                :dipendente_id, :cliente_id, :progetto_id, :ticket_id,
                :data_inizio, :data_fine, :durata_ore, :descrizione,
                :riferimento_progetto, :creato_da, :fatturabile,
                0, :master_azienda_id
            )";
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                ':dipendente_id' => $dipendente_id,
                ':cliente_id' => null, // Legacy compatibility
                ':progetto_id' => $progetto_id,
                ':ticket_id' => $data['Id Ticket'] ?? null,
                ':data_inizio' => $this->parseDateTime($data['Iniziata il'] ?? null),
                ':data_fine' => $this->parseDateTime($data['Conclusa il'] ?? null),
                ':durata_ore' => $this->parseFloat($data['Durata'] ?? 0),
                ':descrizione' => $data['Descrizione'] ?? null,
                ':riferimento_progetto' => $data['Riferimento Progetto'] ?? null,
                ':creato_da' => $data['Creato da'] ?? null,
                ':fatturabile' => 1,
                ':master_azienda_id' => $azienda_id
            ]);
            
            return $result ? 'inserted' : false;
            
        } catch (Exception $e) {
            $this->errors[] = "Errore riga attività: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Ottiene ID dipendente con logica smart (solo dipendenti fissi)
     */
    private function getSmartEmployeeId($fullName) {
        if (empty($fullName)) {
            return null;
        }
        
        $fullName = trim($fullName);
        $normalized_name = strtolower($fullName);
        
        // 1. Ricerca esatta in cache master dipendenti
        if (isset($this->master_employees_cache['by_name'][$normalized_name])) {
            $master_id = $this->master_employees_cache['by_name'][$normalized_name];
            return $this->linkToLegacyEmployee($master_id);
        }
        
        // 2. Parsing nomi multipli (es: "Franco Fiorellino/Matteo Signo")
        $multiple_names = $this->parseMultipleEmployeeNames($fullName);
        if (count($multiple_names) > 1) {
            foreach ($multiple_names as $name) {
                $name_normalized = strtolower(trim($name));
                if (isset($this->master_employees_cache['by_name'][$name_normalized])) {
                    $master_id = $this->master_employees_cache['by_name'][$name_normalized];
                    $this->logger->info("Risolto nome multiplo", ['original' => $fullName, 'matched' => $name, 'master_id' => $master_id]);
                    return $this->linkToLegacyEmployee($master_id);
                }
            }
        }
        
        // 3. Ricerca fuzzy (solo su master fissi)
        $best_match = $this->findBestEmployeeMatch($fullName);
        if ($best_match) {
            return $this->linkToLegacyEmployee($best_match);
        }
        
        // 4. STOP - Non creare dipendenti non presenti nella master list
        $this->warnings[] = "Dipendente '$fullName' non trovato nella lista master (15 dipendenti fissi)";
        return null;
    }
    
    /**
     * Collega master dipendente a record legacy (crea se necessario)
     */
    private function linkToLegacyEmployee($master_id) {
        // Verifica se esiste già collegamento
        $stmt = $this->conn->prepare("SELECT id FROM dipendenti WHERE master_dipendente_id = ? LIMIT 1");
        $stmt->execute([$master_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            return $existing['id'];
        }
        
        // Crea record legacy collegato al master
        $master_data = $this->master_employees_cache[$master_id];
        
        $stmt = $this->conn->prepare("
            INSERT INTO dipendenti (nome, cognome, email, ruolo, costo_giornaliero, master_dipendente_id, attivo)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $master_data['nome'],
            $master_data['cognome'], 
            $master_data['email'],
            $master_data['ruolo'],
            $master_data['costo_giornaliero'],
            $master_id
        ]);
        
        $legacy_id = $this->conn->lastInsertId();
        $this->logger->info("Creato collegamento legacy", ['master_id' => $master_id, 'legacy_id' => $legacy_id]);
        
        return $legacy_id;
    }
    
    /**
     * Ottiene ID azienda con associazione smart
     */
    private function getSmartCompanyId($companyName) {
        $normalized_name = strtolower(trim($companyName));
        
        // 1. Ricerca esatta in master aziende
        if (isset($this->master_companies_cache['by_name'][$normalized_name])) {
            return $this->master_companies_cache['by_name'][$normalized_name];
        }
        
        // 2. Ricerca fuzzy nelle aziende esistenti
        $best_match = $this->findBestCompanyMatch($companyName);
        if ($best_match) {
            return $best_match;
        }
        
        // 3. Controllo se abilitata associazione automatica
        if ($this->getConfig('import', 'auto_associate_clients', false)) {
            // Aggiunge a queue per associazione manuale
            $this->addToAssociationQueue($companyName, 'attivita');
            $this->stats['associations_created']++;
        }
        
        return null;
    }
    
    /**
     * Ottiene o crea progetto dinamicamente
     */
    private function getOrCreateProject($projectCode, $azienda_id = null) {
        if (empty($projectCode)) {
            return null;
        }
        
        // Cerca progetto esistente
        $stmt = $this->conn->prepare("SELECT id FROM master_progetti WHERE codice = ?");
        $stmt->execute([$projectCode]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            return $existing['id'];
        }
        
        // Crea nuovo progetto
        $stmt = $this->conn->prepare("
            INSERT INTO master_progetti (nome, codice, azienda_id, stato)
            VALUES (?, ?, ?, 'attivo')
        ");
        $stmt->execute([
            "Progetto $projectCode",
            $projectCode,
            $azienda_id
        ]);
        
        $project_id = $this->conn->lastInsertId();
        $this->logger->info("Creato nuovo progetto", ['codice' => $projectCode, 'id' => $project_id]);
        
        return $project_id;
    }
    
    /**
     * Verifica se attività è duplicata
     */
    private function isDuplicateActivity($data, $dipendente_id) {
        $time_window = $this->getConfig('import', 'duplicate_time_window', 3);
        $data_inizio = $this->parseDateTime($data['Iniziata il'] ?? null);
        
        if (!$data_inizio) {
            return false;
        }
        
        // Cerca attività simili in finestra temporale
        $stmt = $this->conn->prepare("
            SELECT id FROM attivita 
            WHERE dipendente_id = ? 
              AND ABS(TIMESTAMPDIFF(MINUTE, data_inizio, ?)) <= ?
              AND ABS(durata_ore - ?) < 0.1
              AND is_duplicate = 0
            LIMIT 1
        ");
        
        $stmt->execute([
            $dipendente_id,
            $data_inizio,
            $time_window,
            $this->parseFloat($data['Durata'] ?? 0)
        ]);
        
        return $stmt->fetch() !== false;
    }
    
    // [Altri metodi di utility...]
    
    /**
     * Parsing nomi multipli con separatori
     */
    private function parseMultipleEmployeeNames($fullName) {
        $separators = ['/', ',', ';', '&', '+', ' e ', ' and ', ' with ', ' con '];
        
        foreach ($separators as $separator) {
            if (strpos($fullName, $separator) !== false) {
                $parts = explode($separator, $fullName);
                $parsed_names = [];
                
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (!empty($part)) {
                        $parsed_names[] = $part;
                    }
                }
                
                if (count($parsed_names) > 1) {
                    return $parsed_names;
                }
            }
        }
        
        return [$fullName];
    }
    
    /**
     * Aggiunge client alla queue per associazione manuale
     */
    private function addToAssociationQueue($clientName, $source) {
        try {
            // Verifica se già in queue
            $stmt = $this->conn->prepare("
                SELECT id FROM association_queue 
                WHERE nome_cliente = ? AND stato = 'pending'
            ");
            $stmt->execute([$clientName]);
            
            if ($stmt->fetch()) {
                return; // Già in queue
            }
            
            // Suggerisci azienda con matching fuzzy
            $suggested_company = $this->findBestCompanyMatch($clientName);
            $confidence = $suggested_company ? 0.7 : 0.0;
            
            $stmt = $this->conn->prepare("
                INSERT INTO association_queue (nome_cliente, fonte_import, azienda_suggerita_id, confidenza_match)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$clientName, $source, $suggested_company, $confidence]);
            
            $this->logger->info("Aggiunto a association queue", [
                'cliente' => $clientName,
                'source' => $source,
                'suggested' => $suggested_company
            ]);
            
        } catch (Exception $e) {
            $this->logger->error("Errore aggiunta association queue", ['error' => $e->getMessage()]);
        }
    }
    
    // [Utility methods per parsing, matching, etc...]
    
    private function detectCsvSeparator($handle) {
        $pos = ftell($handle);
        $first_line = fgets($handle);
        fseek($handle, $pos);
        
        if (!$first_line) return ';';
        
        $separators = [',', ';', "\t", '|'];
        $counts = [];
        
        foreach ($separators as $sep) {
            $counts[$sep] = substr_count($first_line, $sep);
        }
        
        return array_search(max($counts), $counts) ?: ';';
    }
    
    private function removeBomFromHeader($header) {
        if (!empty($header[0])) {
            $header[0] = str_replace("\xEF\xBB\xBF", '', $header[0]);
        }
        return $header;
    }
    
    private function cleanData($data) {
        $cleaned = [];
        foreach ($data as $key => $value) {
            $cleaned[trim($key)] = trim($value);
        }
        return $cleaned;
    }
    
    private function parseDateTime($dateString) {
        if (empty($dateString)) return null;
        
        $formats = ['d/m/Y H:i:s', 'Y-m-d H:i:s', 'd/m/Y H:i', 'Y-m-d H:i', 'd/m/Y', 'Y-m-d'];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        
        return null;
    }
    
    private function parseFloat($value) {
        if (empty($value)) return 0;
        return (float) str_replace(',', '.', $value);
    }
    
    private function resetStats() {
        $this->stats = [
            'processed' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'duplicates_detected' => 0,
            'associations_created' => 0,
            'validation_errors' => 0
        ];
    }
    
    public function getStats() {
        return $this->stats;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getWarnings() {
        return $this->warnings;
    }
    
    // Metodi placeholder per ricerca fuzzy (da implementare)
    private function findBestEmployeeMatch($name) {
        // TODO: Implementare ricerca fuzzy sui master dipendenti
        return null;
    }
    
    private function findBestCompanyMatch($name) {
        // TODO: Implementare ricerca fuzzy sulle master aziende
        return null;
    }
    
    // Metodi per altri tipi di file
    private function processTimbratureFile($filepath) {
        $this->logger->info("Inizio processing timbrature", ['file' => $filepath]);
        
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            throw new Exception("Impossibile aprire file timbrature: $filepath");
        }
        
        // Rileva separatore
        $separator = $this->detectCsvSeparator($handle);
        
        // Leggi header
        $header = fgetcsv($handle, 0, $separator);
        if (!$header) {
            throw new Exception("Header timbrature non valido");
        }
        
        $header = $this->removeBomFromHeader($header);
        $this->logger->info("Header timbrature", ['columns' => $header]);
        
        $row_count = 0;
        while (($row = fgetcsv($handle, 0, $separator)) !== FALSE) {
            $row_count++;
            $this->stats['processed']++;
            
            if (count($row) !== count($header)) {
                $this->warnings[] = "Riga $row_count: numero colonne non corrispondente";
                $this->stats['skipped']++;
                continue;
            }
            
            $data = array_combine($header, $row);
            $data = $this->cleanRowData($data);
            
            if ($this->processTimbratureRow($data)) {
                $this->stats['inserted']++;
            } else {
                $this->stats['skipped']++;
            }
        }
        
        fclose($handle);
        $this->logger->info("Completato processing timbrature", ['rows' => $row_count]);
        
        return [
            'success' => true,
            'stats' => $this->stats,
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
    
    private function processTeamviewerFile($filepath) {
        $this->logger->info("Inizio processing teamviewer", ['file' => $filepath]);
        
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            throw new Exception("Impossibile aprire file teamviewer: $filepath");
        }
        
        // Rileva separatore
        $separator = $this->detectCsvSeparator($handle);
        
        // Leggi header
        $header = fgetcsv($handle, 0, $separator);
        if (!$header) {
            throw new Exception("Header teamviewer non valido");
        }
        
        $header = $this->removeBomFromHeader($header);
        $this->logger->info("Header teamviewer", ['columns' => $header]);
        
        $row_count = 0;
        while (($row = fgetcsv($handle, 0, $separator)) !== FALSE) {
            $row_count++;
            $this->stats['processed']++;
            
            if (count($row) !== count($header)) {
                $this->warnings[] = "Riga $row_count: numero colonne non corrispondente";
                $this->stats['skipped']++;
                continue;
            }
            
            $data = array_combine($header, $row);
            $data = $this->cleanRowData($data);
            
            if ($this->processTeamviewerRow($data)) {
                $this->stats['inserted']++;
            } else {
                $this->stats['skipped']++;
            }
        }
        
        fclose($handle);
        $this->logger->info("Completato processing teamviewer", ['rows' => $row_count]);
        
        return [
            'success' => true,
            'stats' => $this->stats,
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
    
    private function processCalendarioFile($filepath) {
        $this->logger->info("Inizio processing calendario", ['file' => $filepath]);
        
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            throw new Exception("Impossibile aprire file calendario: $filepath");
        }
        
        // Rileva separatore
        $separator = $this->detectCsvSeparator($handle);
        
        // Leggi header
        $header = fgetcsv($handle, 0, $separator);
        if (!$header) {
            throw new Exception("Header calendario non valido");
        }
        
        $header = $this->removeBomFromHeader($header);
        $this->logger->info("Header calendario", ['columns' => $header]);
        
        $row_count = 0;
        while (($row = fgetcsv($handle, 0, $separator)) !== FALSE) {
            $row_count++;
            $this->stats['processed']++;
            
            if (count($row) !== count($header)) {
                $this->warnings[] = "Riga $row_count: numero colonne non corrispondente";
                $this->stats['skipped']++;
                continue;
            }
            
            $data = array_combine($header, $row);
            $data = $this->cleanRowData($data);
            
            if ($this->processCalendarioRow($data)) {
                $this->stats['inserted']++;
            } else {
                $this->stats['skipped']++;
            }
        }
        
        fclose($handle);
        $this->logger->info("Completato processing calendario", ['rows' => $row_count]);
        
        return [
            'success' => true,
            'stats' => $this->stats,
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
    
    // Metodi helper per processing righe specifiche
    
    private function processTimbratureRow($data) {
        try {
            // Rileva dipendente
            $dipendente_name = $this->getValueSafe($data, ['dipendente nome']) . ' ' . $this->getValueSafe($data, ['dipendente cognome']);
            $dipendente_id = $this->findOrCreateEmployee($dipendente_name);
            
            if (!$dipendente_id) {
                $this->warnings[] = "Dipendente non trovato per timbratura: $dipendente_name";
                return false;
            }
            
            // Rileva cliente
            $cliente_id = null;
            $cliente_name = $this->getValueSafe($data, ['cliente nome']);
            if (!empty($cliente_name)) {
                $cliente_id = $this->findOrCreateCompany($cliente_name);
            }
            
            // Inserisci timbratura
            $sql = "INSERT INTO timbrature (
                dipendente_id, cliente_id, data, ora_inizio, ora_fine, ore_totali, 
                ore_arrotondate, ore_nette_pause, descrizione_attivita
            ) VALUES (
                :dipendente_id, :cliente_id, :data, :ora_inizio, :ora_fine, :ore_totali,
                :ore_arrotondate, :ore_nette_pause, :descrizione
            ) ON DUPLICATE KEY UPDATE
                ore_totali = VALUES(ore_totali),
                updated_at = CURRENT_TIMESTAMP";
                
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':dipendente_id' => $dipendente_id,
                ':cliente_id' => $cliente_id,
                ':data' => $this->parseDate($this->getValueSafe($data, ['ora inizio'])),
                ':ora_inizio' => $this->parseTime($this->getValueSafe($data, ['ora inizio'])),
                ':ora_fine' => $this->parseTime($this->getValueSafe($data, ['ora fine'])),
                ':ore_totali' => $this->parseFloat($this->getValueSafe($data, ['ore'], '0')),
                ':ore_arrotondate' => $this->parseFloat($this->getValueSafe($data, ['ore arrotondate'], '0')),
                ':ore_nette_pause' => $this->parseFloat($this->getValueSafe($data, ['centesimi al netto delle pause'], '0')),
                ':descrizione' => $this->getValueSafe($data, ['descrizione attivita'])
            ]);
            
        } catch (Exception $e) {
            $this->errors[] = "Errore timbratura: " . $e->getMessage();
            return false;
        }
    }
    
    private function processTeamviewerRow($data) {
        try {
            // Rileva dipendente
            $dipendente_name = $this->getValueSafe($data, ['Utente', 'Assegnatario']);
            $dipendente_id = $this->findOrCreateEmployee($dipendente_name);
            
            if (!$dipendente_id) {
                $this->warnings[] = "Dipendente non trovato per TeamViewer: $dipendente_name";
                return false;
            }
            
            // Inserisci sessione TeamViewer
            $sql = "INSERT INTO teamviewer_sessioni (
                dipendente_id, nome_cliente, codice_sessione, tipo_sessione, 
                data_inizio, data_fine, durata_minuti, descrizione, fatturabile
            ) VALUES (
                :dipendente_id, :nome_cliente, :codice_sessione, :tipo_sessione,
                :data_inizio, :data_fine, :durata_minuti, :descrizione, :fatturabile
            ) ON DUPLICATE KEY UPDATE
                durata_minuti = VALUES(durata_minuti),
                updated_at = CURRENT_TIMESTAMP";
                
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':dipendente_id' => $dipendente_id,
                ':nome_cliente' => $this->getValueSafe($data, ['Nome', 'Computer']),
                ':codice_sessione' => $this->getValueSafe($data, ['Codice', 'ID']),
                ':tipo_sessione' => $this->getValueSafe($data, ['Tipo di sessione'], 'Controllo remoto'),
                ':data_inizio' => $this->parseDateTime($this->getValueSafe($data, ['Inizio'])),
                ':data_fine' => $this->parseDateTime($this->getValueSafe($data, ['Fine'])),
                ':durata_minuti' => $this->parseDuration($this->getValueSafe($data, ['Durata'], '0')),
                ':descrizione' => $this->getValueSafe($data, ['Descrizione']),
                ':fatturabile' => 1
            ]);
            
        } catch (Exception $e) {
            $this->errors[] = "Errore TeamViewer: " . $e->getMessage();
            return false;
        }
    }
    
    private function processCalendarioRow($data) {
        try {
            // Rileva dipendente
            $dipendente_name = $this->getValueSafe($data, ['ATTENDEE']);
            
            // Verifica se è un veicolo invece di un dipendente
            if ($this->isVehicleName($dipendente_name)) {
                $this->warnings[] = "ATTENDEE è un veicolo, non un dipendente: $dipendente_name";
                return false;
            }
            
            $dipendente_id = $this->findOrCreateEmployee($dipendente_name);
            
            if (!$dipendente_id) {
                $this->warnings[] = "Dipendente non trovato per calendario: $dipendente_name";
                return false;
            }
            
            // Inserisci evento calendario
            $sql = "INSERT INTO calendario (
                dipendente_id, titolo, data_inizio, data_fine, location, note, priorita
            ) VALUES (
                :dipendente_id, :titolo, :data_inizio, :data_fine, :location, :note, :priorita
            ) ON DUPLICATE KEY UPDATE
                titolo = VALUES(titolo),
                location = VALUES(location),
                updated_at = CURRENT_TIMESTAMP";
                
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':dipendente_id' => $dipendente_id,
                ':titolo' => $this->getValueSafe($data, ['SUMMARY'], ''),
                ':data_inizio' => $this->parseDateTime($this->getValueSafe($data, ['DTSTART'])),
                ':data_fine' => $this->parseDateTime($this->getValueSafe($data, ['DTEND'])),
                ':location' => $this->getValueSafe($data, ['LOCATION']),
                ':note' => $this->getValueSafe($data, ['NOTES']),
                ':priorita' => $this->getValueSafe($data, ['PRIORITY'], 5)
            ]);
            
        } catch (Exception $e) {
            $this->errors[] = "Errore calendario: " . $e->getMessage();
            return false;
        }
    }
    
    // Metodi helper aggiuntivi
    
    private function detectCsvSeparator($handle) {
        $pos = ftell($handle);
        $first_line = fgets($handle);
        fseek($handle, $pos);
        
        if (!$first_line) {
            return ';';
        }
        
        $separators = [',', ';', "\t", '|'];
        $separator_counts = [];
        
        foreach ($separators as $sep) {
            $test_sep = ($sep === "\t") ? "\t" : $sep;
            $count = substr_count($first_line, $test_sep);
            $separator_counts[$test_sep] = $count;
        }
        
        $best_separator = array_search(max($separator_counts), $separator_counts);
        return $best_separator ?: ';';
    }
    
    private function removeBomFromHeader($header) {
        if (empty($header)) return $header;
        
        $first_col = $header[0];
        if (substr($first_col, 0, 3) === "\xEF\xBB\xBF") {
            $header[0] = substr($first_col, 3);
        }
        
        $cleaned_header = [];
        foreach ($header as $col) {
            $cleaned_header[] = trim($col, " \t\n\r\0\x0B\xEF\xBB\xBF");
        }
        
        return $cleaned_header;
    }
    
    private function getValueSafe($data, $keys, $default = null) {
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        
        foreach ($keys as $key) {
            if (isset($data[$key]) && !empty(trim($data[$key]))) {
                return trim($data[$key]);
            }
        }
        
        return $default;
    }
    
    private function cleanRowData($data) {
        $cleaned = [];
        foreach ($data as $key => $value) {
            $cleaned_key = trim($key);
            $cleaned_value = trim($value);
            $cleaned[$cleaned_key] = $cleaned_value;
        }
        return $cleaned;
    }
    
    private function parseDate($dateString) {
        if (empty($dateString)) return null;
        
        $formats = ['d/m/Y', 'Y-m-d', 'd/m/Y H:i', 'Y-m-d H:i:s'];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        return null;
    }
    
    private function parseDateTime($dateString) {
        if (empty($dateString)) return null;
        
        $formats = ['d/m/Y H:i:s', 'Y-m-d H:i:s', 'd/m/Y H:i', 'Y-m-d H:i', 'd/m/Y', 'Y-m-d'];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        
        return null;
    }
    
    private function parseTime($timeString) {
        if (empty($timeString)) return null;
        
        $date = DateTime::createFromFormat('d/m/Y H:i', $timeString);
        if ($date !== false) {
            return $date->format('H:i:s');
        }
        
        return null;
    }
    
    private function parseFloat($value) {
        if (empty($value)) return 0;
        return (float) str_replace(',', '.', $value);
    }
    
    private function parseDuration($durationString) {
        if (empty($durationString)) return 0;
        
        if (strpos($durationString, 'm') !== false) {
            return (int) str_replace('m', '', $durationString);
        }
        
        if (strpos($durationString, ':') !== false) {
            $parts = explode(':', $durationString);
            if (count($parts) >= 2) {
                return (int)$parts[0] * 60 + (int)$parts[1];
            }
        }
        
        return (int) $durationString;
    }
    
    private function isVehicleName($name) {
        $vehicle_names = ['Punto', 'Fiesta', 'Peugeot', 'Auto', 'Veicolo'];
        return in_array(trim($name), $vehicle_names);
    }
    
    private function findOrCreateEmployee($fullName) {
        if (empty($fullName)) return null;
        
        // Cerca prima nel cache
        if ($this->master_employees_cache) {
            foreach ($this->master_employees_cache as $emp) {
                if (stripos($emp['nome_completo'], $fullName) !== false || 
                    stripos($fullName, $emp['nome_completo']) !== false) {
                    return $emp['id'];
                }
            }
        }
        
        // Se non trovato, cerca nel database
        $stmt = $this->conn->prepare("SELECT id FROM dipendenti WHERE CONCAT(nome, ' ', cognome) LIKE ?");
        $stmt->execute(['%' . $fullName . '%']);
        $result = $stmt->fetch();
        
        if ($result) {
            return $result['id'];
        }
        
        // Crea nuovo dipendente se valido
        if ($this->isValidEmployeeName($fullName)) {
            $parts = explode(' ', trim($fullName));
            $nome = $parts[0] ?? '';
            $cognome = implode(' ', array_slice($parts, 1)) ?: '';
            
            $stmt = $this->conn->prepare("INSERT INTO dipendenti (nome, cognome) VALUES (?, ?)");
            if ($stmt->execute([$nome, $cognome])) {
                return $this->conn->lastInsertId();
            }
        }
        
        return null;
    }
    
    private function findOrCreateCompany($companyName) {
        if (empty($companyName)) return null;
        
        // Cerca prima nel cache
        if ($this->master_companies_cache) {
            foreach ($this->master_companies_cache as $comp) {
                if (stripos($comp['nome'], $companyName) !== false) {
                    return $comp['id'];
                }
            }
        }
        
        // Se non trovato, cerca nel database
        $stmt = $this->conn->prepare("SELECT id FROM clienti WHERE nome LIKE ?");
        $stmt->execute(['%' . $companyName . '%']);
        $result = $stmt->fetch();
        
        if ($result) {
            return $result['id'];
        }
        
        // Crea nuovo cliente
        $stmt = $this->conn->prepare("INSERT INTO clienti (nome) VALUES (?)");
        if ($stmt->execute([$companyName])) {
            return $this->conn->lastInsertId();
        }
        
        return null;
    }
    
    private function isValidEmployeeName($name) {
        $name = trim($name);
        
        if (empty($name) || strlen($name) < 2) return false;
        
        // Blacklist
        $blacklist = ['Punto', 'Fiesta', 'Peugeot', 'Auto', 'Info', 'System', 'Admin', 'Test'];
        if (in_array($name, $blacklist)) return false;
        
        if (preg_match('/^\d+$/', $name)) return false;
        if (strpos($name, '@') !== false) return false;
        
        return true;
    }
}
?>