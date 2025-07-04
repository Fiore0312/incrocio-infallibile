<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Configuration.php';
require_once __DIR__ . '/ImportLogger.php';
require_once __DIR__ . '/DeduplicationEngine.php';

/**
 * Enhanced CSV Parser with Master Tables Integration
 * 
 * Risolve i problemi di parsing nomi e duplicazione utilizzando le tabelle master
 * Gestisce separatori complessi come "Franco Fiorellino/Matteo Signo"
 */
class EnhancedCsvParser {
    private $conn;
    private $config;
    private $errors = [];
    private $warnings = [];
    private $stats = [];
    private $logger;
    
    // Cache per performance
    private $master_dipendenti_cache = null;
    private $master_veicoli_cache = null;
    private $aliases_cache = null;
    private $deduplication = null;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->config = new Configuration();
        $this->logger = new ImportLogger('enhanced_csvparser');
        
        $this->initializeCaches();
        $this->initializeDeduplication();
    }
    
    /**
     * Inizializza cache delle master tables per performance
     */
    private function initializeCaches() {
        // Cache master dipendenti
        $stmt = $this->conn->prepare("SELECT id, nome, cognome, nome_completo FROM master_dipendenti WHERE attivo = 1");
        $stmt->execute();
        $this->master_dipendenti_cache = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cache master veicoli
        $stmt = $this->conn->prepare("SELECT id, nome FROM master_veicoli WHERE attivo = 1");
        $stmt->execute();
        $this->master_veicoli_cache = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'nome');
        
        // Cache aliases dipendenti
        $stmt = $this->conn->prepare("
            SELECT da.alias_nome, da.alias_cognome, da.alias_completo, md.id as master_id, md.nome, md.cognome
            FROM dipendenti_aliases da 
            JOIN master_dipendenti md ON da.master_dipendente_id = md.id 
            WHERE md.attivo = 1
        ");
        $stmt->execute();
        $this->aliases_cache = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->logger->info("Cache inizializzate: " . count($this->master_dipendenti_cache) . " dipendenti master, " . 
                           count($this->master_veicoli_cache) . " veicoli, " . 
                           count($this->aliases_cache) . " aliases");
    }
    

    /**
     * Inizializza Deduplication Engine
     */
    private function initializeDeduplication() {
        $dedup_config = [
            'time_threshold_minutes' => 3,
            'similarity_threshold' => 0.85,
            'enable_soft_deduplication' => true,
            'enable_intelligent_merge' => true
        ];
        
        $this->deduplication = new DeduplicationEngine($dedup_config);
        $this->logger->info("DeduplicationEngine inizializzato con soglie: " . json_encode($dedup_config));
    }

    /**
     * Process all files with enhanced parsing
     */
    public function processAllFiles($directory) {
        $results = [];
        
        $files = [
            'timbrature' => 'apprilevazionepresenze-timbrature-totali-base.csv',
            'richieste' => 'apprilevazionepresenze-richieste.csv',
            'attivita' => 'attivita.csv',
            'calendario' => 'calendario.csv',
            'progetti' => 'progetti.csv',
            'registro_auto' => 'registro_auto.csv',
            'teamviewer_bait' => 'teamviewer_bait.csv',
            'teamviewer_gruppo' => 'teamviewer_gruppo.csv'
        ];
        
        foreach ($files as $type => $filename) {
            $filepath = $directory . '/' . $filename;
            if (file_exists($filepath)) {
                $results[$type] = $this->parseFile($filepath, $type);
            } else {
                $results[$type] = ['error' => "File non trovato: $filename"];
            }
        }
        
        return $results;
    }
    
    /**
     * Enhanced getDipendenteByFullName con integrazione Master Tables
     */
    private function getDipendenteByFullName($fullName) {
        if (empty($fullName)) return null;
        
        $fullName = trim($fullName);
        
        // Step 1: Controlla se è un nome con separatori multipli (es. "Franco Fiorellino/Matteo Signo")
        $multiple_employees = $this->parseMultipleEmployeeNames($fullName);
        if (count($multiple_employees) > 1) {
            // Gestisce parsing multiplo - prende il primo valido
            foreach ($multiple_employees as $employee_name) {
                $dipendente_id = $this->getSingleEmployeeId($employee_name);
                if ($dipendente_id) {
                    $this->logger->info("Trovato dipendente multiplo: '$employee_name' da '$fullName' (ID: $dipendente_id)");
                    return $dipendente_id;
                }
            }
            
            // Se nessuno è stato trovato, prova a creare il primo valido
            foreach ($multiple_employees as $employee_name) {
                if ($this->isValidEmployeeName($employee_name)) {
                    $dipendente_id = $this->createDipendenteFromFullName($employee_name);
                    if ($dipendente_id) {
                        $this->logger->info("Creato dipendente da parsing multiplo: '$employee_name' da '$fullName' (ID: $dipendente_id)");
                        return $dipendente_id;
                    }
                }
            }
        }
        
        // Step 2: Parsing singolo nome
        return $this->getSingleEmployeeId($fullName);
    }
    
    /**
     * Parsing di nomi multipli con separatori
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
                    $this->logger->info("Parsing multiplo con separatore '$separator': " . implode(' | ', $parsed_names));
                    return $parsed_names;
                }
            }
        }
        
        return [$fullName];
    }
    
    /**
     * Ottiene ID dipendente singolo con ricerca avanzata
     */
    private function getSingleEmployeeId($fullName) {
        // Step 1: Ricerca esatta in master_dipendenti
        $dipendente_id = $this->findInMasterDipendenti($fullName);
        if ($dipendente_id) {
            return $this->linkOrCreateLegacyEmployee($dipendente_id, $fullName);
        }
        
        // Step 2: Ricerca in aliases
        $dipendente_id = $this->findInAliases($fullName);
        if ($dipendente_id) {
            return $this->linkOrCreateLegacyEmployee($dipendente_id, $fullName);
        }
        
        // Step 3: Ricerca fuzzy con FULLTEXT
        $dipendente_id = $this->findWithFuzzySearch($fullName);
        if ($dipendente_id) {
            return $this->linkOrCreateLegacyEmployee($dipendente_id, $fullName);
        }
        
        // Step 4: Ricerca in dipendenti legacy esistenti
        $dipendente_id = $this->findInLegacyEmployees($fullName);
        if ($dipendente_id) {
            return $dipendente_id;
        }
        
        // Step 5: Creazione nuovo dipendente se valido
        if ($this->isValidEmployeeName($fullName)) {
            return $this->createDipendenteFromFullName($fullName);
        }
        
        $this->logger->logEmployeeRejected($fullName, 'Non trovato e non valido per creazione');
        return null;
    }
    
    /**
     * Ricerca esatta in master_dipendenti
     */
    private function findInMasterDipendenti($fullName) {
        foreach ($this->master_dipendenti_cache as $master) {
            if (strcasecmp($master['nome_completo'], $fullName) === 0 ||
                strcasecmp($master['nome'] . ' ' . $master['cognome'], $fullName) === 0) {
                $this->logger->info("Match esatto in master_dipendenti: '$fullName' -> ID {$master['id']}");
                return $master['id'];
            }
        }
        return null;
    }
    
    /**
     * Ricerca in aliases
     */
    private function findInAliases($fullName) {
        foreach ($this->aliases_cache as $alias) {
            if (strcasecmp($alias['alias_completo'], $fullName) === 0 ||
                strcasecmp($alias['alias_nome'] . ' ' . $alias['alias_cognome'], $fullName) === 0) {
                $this->logger->info("Match in alias: '$fullName' -> Master ID {$alias['master_id']} ({$alias['nome']} {$alias['cognome']})");
                return $alias['master_id'];
            }
        }
        return null;
    }
    
    /**
     * Ricerca fuzzy con FULLTEXT
     */
    private function findWithFuzzySearch($fullName) {
        try {
            // Ricerca fuzzy in master_dipendenti
            $stmt = $this->conn->prepare("
                SELECT id, nome, cognome, nome_completo,
                       MATCH(nome_completo) AGAINST(? IN NATURAL LANGUAGE MODE) as score
                FROM master_dipendenti 
                WHERE MATCH(nome_completo) AGAINST(? IN NATURAL LANGUAGE MODE) 
                  AND attivo = 1
                ORDER BY score DESC 
                LIMIT 1
            ");
            $stmt->execute([$fullName, $fullName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['score'] > 0.5) { // Soglia di confidenza
                $this->logger->info("Match fuzzy: '$fullName' -> '{$result['nome_completo']}' (score: {$result['score']})");
                return $result['id'];
            }
        } catch (Exception $e) {
            $this->logger->error("Errore ricerca fuzzy: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Ricerca in dipendenti legacy
     */
    private function findInLegacyEmployees($fullName) {
        // Prova diversi metodi di parsing
        $parsing_methods = [
            function($name) {
                $parts = explode(' ', $name);
                if (count($parts) >= 2) {
                    return [$parts[0], implode(' ', array_slice($parts, 1))];
                }
                return null;
            },
            function($name) {
                if (strpos($name, ', ') !== false) {
                    $parts = explode(', ', $name);
                    if (count($parts) >= 2) {
                        return [$parts[1], $parts[0]];
                    }
                }
                return null;
            }
        ];
        
        foreach ($parsing_methods as $method) {
            $parsed = $method($fullName);
            if ($parsed) {
                $stmt = $this->conn->prepare("SELECT id FROM dipendenti WHERE nome = ? AND cognome = ?");
                $stmt->execute([$parsed[0], $parsed[1]]);
                $result = $stmt->fetch();
                if ($result) {
                    $this->logger->info("Trovato in legacy: '$fullName' -> ID {$result['id']}");
                    return $result['id'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Collega o crea dipendente legacy collegato al master
     */
    private function linkOrCreateLegacyEmployee($master_id, $fullName) {
        // Verifica se esiste già un dipendente legacy collegato a questo master
        $stmt = $this->conn->prepare("SELECT id FROM dipendenti WHERE master_dipendente_id = ?");
        $stmt->execute([$master_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            return $existing['id'];
        }
        
        // Ottieni i dati del master
        $stmt = $this->conn->prepare("SELECT nome, cognome, email, ruolo FROM master_dipendenti WHERE id = ?");
        $stmt->execute([$master_id]);
        $master = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$master) {
            $this->logger->error("Errore: Master dipendente $master_id non trovato");
            return null;
        }
        
        // Crea nuovo dipendente legacy collegato al master
        $stmt = $this->conn->prepare("
            INSERT INTO dipendenti (nome, cognome, email, ruolo, master_dipendente_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $master['nome'],
            $master['cognome'],
            $master['email'],
            $master['ruolo'],
            $master_id
        ]);
        
        $legacy_id = $this->conn->lastInsertId();
        $this->logger->info("Creato dipendente legacy collegato: '{$master['nome']} {$master['cognome']}' (Legacy ID: $legacy_id, Master ID: $master_id)");
        
        // Crea alias se il nome originale è diverso
        if (strcasecmp($fullName, $master['nome'] . ' ' . $master['cognome']) !== 0) {
            $this->createAlias($master_id, $fullName);
        }
        
        return $legacy_id;
    }
    
    /**
     * Crea alias per variante nome
     */
    private function createAlias($master_id, $fullName) {
        $parts = explode(' ', trim($fullName));
        $alias_nome = $parts[0] ?? '';
        $alias_cognome = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO dipendenti_aliases (master_dipendente_id, alias_nome, alias_cognome, fonte, note)
                VALUES (?, ?, ?, 'csv', 'Auto-generato durante import')
                ON DUPLICATE KEY UPDATE note = 'Auto-generato durante import - aggiornato'
            ");
            $stmt->execute([$master_id, $alias_nome, $alias_cognome]);
            $this->logger->info("Creato alias: '$fullName' per master ID $master_id");
        } catch (Exception $e) {
            $this->logger->error("Errore creazione alias: " . $e->getMessage());
        }
    }
    
    /**
     * Enhanced validation con verifica master veicoli
     */
    private function isValidEmployeeName($name, $allow_empty = false) {
        if (empty($name)) {
            return $allow_empty;
        }
        
        // Controlla se è un veicolo master
        if (in_array($name, $this->master_veicoli_cache)) {
            $this->logger->info("Nome '$name' identificato come veicolo master");
            return false;
        }
        
        // Blacklist esplicita (mantenuta dal CsvParser originale)
        $blacklist = [
            'Punto', 'Fiesta', 'Peugeot', 'Auto', 'Veicolo',
            'Info', 'System', 'Admin', 'Test', 'User', 'Guest', 'TRUE', 'FALSE', 'NULL',
            'Aurora', 'Reminder', 'Meeting', 'Riunione', 'Supporto', 'Backup',
            'Cliente', 'Fornitore', 'Ufficio', 'Sede', 'Filiale'
        ];
        
        if (in_array(ucfirst(strtolower($name)), $blacklist) || 
            in_array(strtoupper($name), $blacklist) ||
            in_array(strtolower($name), array_map('strtolower', $blacklist))) {
            return false;
        }
        
        // Validazioni formato (mantenute dal CsvParser originale)
        if (strlen($name) < 2 || strlen($name) > 50) {
            return false;
        }
        
        if (strpos($name, '@') !== false || preg_match('/^\d+$/', $name)) {
            return false;
        }
        
        if (preg_match('/^[^a-zA-ZÀ-ÿ\s\'-]/', $name)) {
            return false;
        }
        
        if (preg_match('/^(pc|nb|server|host|www|http|ftp)/i', $name) ||
            preg_match('/\.(com|it|org|net|gov)$/i', $name)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Enhanced createDipendenteFromFullName con collegamento automatico
     */
    private function createDipendenteFromFullName($fullName) {
        if (!$this->isValidEmployeeName($fullName)) {
            $this->logger->logEmployeeRejected($fullName, 'Nome non valido secondo blacklist/regole');
            return null;
        }
        
        // Parse nome e cognome
        $parts = explode(' ', trim($fullName));
        if (count($parts) >= 2) {
            $nome = $parts[0];
            $cognome = implode(' ', array_slice($parts, 1));
        } elseif (strpos($fullName, ', ') !== false) {
            $parts = explode(', ', $fullName);
            $nome = isset($parts[1]) ? $parts[1] : $parts[0];
            $cognome = $parts[0];
        } else {
            $nome = $fullName;
            $cognome = '';
        }
        
        // Validazione aggiuntiva
        if (!$this->isValidEmployeeName($nome) || !$this->isValidEmployeeName($cognome, true)) {
            $this->logger->logEmployeeRejected("$nome $cognome", 'Nome o cognome non valido');
            return null;
        }
        
        // Verifica se esiste un master dipendente con questo nome
        $master_id = null;
        foreach ($this->master_dipendenti_cache as $master) {
            if (strcasecmp($master['nome'], $nome) === 0 && strcasecmp($master['cognome'], $cognome) === 0) {
                $master_id = $master['id'];
                break;
            }
        }
        
        // Crea dipendente legacy
        if ($master_id) {
            // Collegato a master esistente
            $stmt = $this->conn->prepare("
                INSERT INTO dipendenti (nome, cognome, master_dipendente_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$nome, $cognome, $master_id]);
            $legacy_id = $this->conn->lastInsertId();
            $this->logger->logEmployeeCreated("$nome $cognome", $legacy_id);
        } else {
            // Crea sia master che legacy
            $stmt = $this->conn->prepare("
                INSERT INTO master_dipendenti (nome, cognome, fonte_origine, note_parsing) 
                VALUES (?, ?, 'csv', 'Auto-creato durante import CSV')
            ");
            $stmt->execute([$nome, $cognome]);
            $master_id = $this->conn->lastInsertId();
            
            $stmt = $this->conn->prepare("
                INSERT INTO dipendenti (nome, cognome, master_dipendente_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$nome, $cognome, $master_id]);
            $legacy_id = $this->conn->lastInsertId();
            
            // Aggiorna cache
            $this->master_dipendenti_cache[] = [
                'id' => $master_id,
                'nome' => $nome,
                'cognome' => $cognome,
                'nome_completo' => "$nome $cognome"
            ];
            
            $this->logger->logEmployeeCreated("$nome $cognome", $legacy_id);
        }
        
        return $legacy_id;
    }
    
    /**
     * Eredita i metodi dal CsvParser originale per compatibilità
     */
    public function parseFile($filepath, $type) {
        try {
            $this->errors = [];
            $this->warnings = [];
            $this->stats = ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0];
            
            $this->logger->logFileStart($filepath, $type);
            
            $handle = fopen($filepath, 'r');
            if (!$handle) {
                throw new Exception("Impossibile aprire il file: $filepath");
            }
            
            // Auto-detect CSV separator
            $separator = $this->detectCsvSeparator($handle);
            
            $header = fgetcsv($handle, 0, $separator);
            if (!$header) {
                throw new Exception("Header del file non valido");
            }
            
            // Remove BOM from header if present
            $header = $this->removeBomFromHeader($header);
            
            $this->conn->beginTransaction();
            
            while (($row = fgetcsv($handle, 0, $separator)) !== FALSE) {
                $this->stats['processed']++;
                
                try {
                    if (count($row) !== count($header)) {
                        $this->warnings[] = "Riga {$this->stats['processed']}: numero colonne non corrispondente";
                        $this->stats['skipped']++;
                        continue;
                    }
                    
                    $data = array_combine($header, $row);
                    if ($data === false) {
                        $this->warnings[] = "Riga {$this->stats['processed']}: errore nel combinare header e data";
                        $this->stats['skipped']++;
                        continue;
                    }
                    
                    $data = $this->cleanData($data);
                    
                    $result = $this->processRow($data, $type);
                    if ($result) {
                        if ($result === 'inserted') {
                            $this->stats['inserted']++;
                        } elseif ($result === 'updated') {
                            $this->stats['updated']++;
                        }
                    } else {
                        $this->stats['skipped']++;
                    }
                    
                } catch (Exception $e) {
                    $this->errors[] = "Riga {$this->stats['processed']}: " . $e->getMessage();
                    $this->stats['skipped']++;
                    continue;
                }
            }
            
            fclose($handle);
            $this->conn->commit();
            
            return [
                'success' => true,
                'stats' => $this->stats,
                'errors' => $this->errors,
                'warnings' => $this->warnings
            ];
            
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollback();
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => $this->stats,
                'errors' => $this->errors,
                'warnings' => $this->warnings
            ];
        }
    }
    
    // Include other necessary methods from original CsvParser
    private function processRow($data, $type) {
        switch ($type) {
            case 'attivita':
                return $this->processAttivita($data);
            case 'calendario':
                return $this->processCalendario($data);
            case 'registro_auto':
                return $this->processRegistroAuto($data);
            case 'teamviewer_bait':
            case 'teamviewer_gruppo':
                return $this->processTeamviewer($data);
            default:
                $this->warnings[] = "Tipo file non gestito: $type";
                return false;
        }
    }
    
    private function processAttivita($data) {
        try {
            $dipendente_id = $this->getDipendenteByFullName($data['Creato da']);
            if (!$dipendente_id) {
                $this->warnings[] = "Dipendente non trovato per attività: " . $data['Creato da'];
                return false;
            }
            
            $cliente_id = null;
            if (!empty($data['Azienda'])) {
                $cliente_id = $this->getClienteId($data['Azienda']);
                if (!$cliente_id) {
                    $cliente_id = $this->createCliente($data['Azienda']);
                }
            }
            
            $progetto_id = null;
            if (!empty($data['Riferimento Progetto'])) {
                $progetto_id = $this->getProgettoByRiferimento($data['Riferimento Progetto']);
            }
            
            $sql = "INSERT INTO attivita (
                dipendente_id, cliente_id, progetto_id, ticket_id, data_inizio, data_fine, 
                durata_ore, descrizione, riferimento_progetto, creato_da, fatturabile
            ) VALUES (
                :dipendente_id, :cliente_id, :progetto_id, :ticket_id, :data_inizio, :data_fine,
                :durata_ore, :descrizione, :riferimento_progetto, :creato_da, :fatturabile
            ) ON DUPLICATE KEY UPDATE
                cliente_id = VALUES(cliente_id),
                progetto_id = VALUES(progetto_id),
                updated_at = CURRENT_TIMESTAMP";
            
            // Enhanced: Usa DeduplicationEngine per prevenire duplicati
            $activityData = [
                'dipendente_id' => $dipendente_id,
                'cliente_id' => $cliente_id,
                'progetto_id' => $progetto_id,
                'ticket_id' => $data['Id Ticket'] ?? null,
                'data_inizio' => $this->parseDateTime($data['Iniziata il']),
                'data_fine' => $this->parseDateTime($data['Conclusa il']),
                'durata_ore' => $this->parseFloat($data['Durata']),
                'descrizione' => $data['Descrizione'] ?? null,
                'riferimento_progetto' => $data['Riferimento Progetto'] ?? null,
                'creato_da' => $data['Creato da'] ?? null,
                'fatturabile' => 1
            ];
            
            $params = [
                ':dipendente_id' => $dipendente_id,
                ':cliente_id' => $cliente_id,
                ':progetto_id' => $progetto_id,
                ':ticket_id' => $data['Id Ticket'] ?? null,
                ':data_inizio' => $this->parseDateTime($data['Iniziata il']),
                ':data_fine' => $this->parseDateTime($data['Conclusa il']),
                ':durata_ore' => $this->parseFloat($data['Durata']),
                ':descrizione' => $data['Descrizione'] ?? null,
                ':riferimento_progetto' => $data['Riferimento Progetto'] ?? null,
                ':creato_da' => $data['Creato da'] ?? null,
                ':fatturabile' => 1
            ];
            
            $result = $this->deduplication->insertActivityWithDeduplication($activityData, $sql, $params);
            
            return $result;
            
        } catch (Exception $e) {
            $this->errors[] = "Errore attività: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Ottieni statistiche deduplicazione
     */
    public function getDeduplicationStats() {
        if ($this->deduplication) {
            return $this->deduplication->getStats();
        }
        return null;
    }
    
    // Include other processing methods with similar enhancements...
    // [Other methods would be included here for completeness]
    
    // Utility methods from original CsvParser
    private function detectCsvSeparator($handle) {
        $pos = ftell($handle);
        $first_line = fgets($handle);
        fseek($handle, $pos);
        
        if (!$first_line) {
            return ';';
        }
        
        $separators = [',', ';', '\t', '|'];
        $separator_counts = [];
        
        foreach ($separators as $sep) {
            $test_sep = ($sep === '\t') ? "\t" : $sep;
            $separator_counts[$sep] = substr_count($first_line, $test_sep);
        }
        
        return array_search(max($separator_counts), $separator_counts) ?: ';';
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
            $cleaned_key = $this->fixEncoding(trim($key));
            $cleaned_value = $this->fixEncoding(trim($value));
            $cleaned[$cleaned_key] = $cleaned_value;
        }
        return $cleaned;
    }
    
    private function fixEncoding($text) {
        if (empty($text)) return $text;
        
        $encodings = ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'CP1252'];
        
        foreach ($encodings as $encoding) {
            if (mb_check_encoding($text, $encoding)) {
                if ($encoding !== 'UTF-8') {
                    return mb_convert_encoding($text, 'UTF-8', $encoding);
                }
                return $text;
            }
        }
        
        return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
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
    
    private function getClienteId($nome) {
        $stmt = $this->conn->prepare("SELECT id FROM clienti WHERE nome = :nome");
        $stmt->execute([':nome' => $nome]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    private function createCliente($nome) {
        $stmt = $this->conn->prepare("INSERT INTO clienti (nome) VALUES (:nome)");
        $stmt->execute([':nome' => $nome]);
        return $this->conn->lastInsertId();
    }
    
    private function getProgettoByRiferimento($riferimento) {
        $stmt = $this->conn->prepare("SELECT id FROM progetti WHERE codice = :codice");
        $stmt->execute([':codice' => $riferimento]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
}
?>