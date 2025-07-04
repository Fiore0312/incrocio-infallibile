<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Configuration.php';
require_once __DIR__ . '/ImportLogger.php';

class CsvParser {
    private $conn;
    private $config;
    private $errors = [];
    private $warnings = [];
    private $stats = [];
    private $logger;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->config = new Configuration();
        $this->logger = new ImportLogger('csvparser');
    }
    
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
            
            // Debug: log cleaned header
            if ($type === 'teamviewer_bait' || $type === 'teamviewer_gruppo') {
                error_log("CSV Parser - Cleaned header for $type: " . implode(', ', $header));
            }
            
            $this->conn->beginTransaction();
            
            while (($row = fgetcsv($handle, 0, $separator)) !== FALSE) {
                $this->stats['processed']++;
                
                try {
                    if (count($row) !== count($header)) {
                        $this->warnings[] = "Riga {$this->stats['processed']}: numero colonne non corrispondente (" . count($row) . " vs " . count($header) . ")";
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
        
        // Try different encodings in order of likelihood
        $encodings = ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'CP1252'];
        
        foreach ($encodings as $encoding) {
            if (mb_check_encoding($text, $encoding)) {
                if ($encoding !== 'UTF-8') {
                    return mb_convert_encoding($text, 'UTF-8', $encoding);
                }
                return $text;
            }
        }
        
        // Fallback: force conversion from Windows-1252
        return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }
    
    private function getValueSafe($data, $keys, $default = null) {
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        
        // Try exact key match first
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $value = $this->cleanValue($data[$key]);
                if (!empty($value)) {
                    return $value;
                }
            }
        }
        
        // Try fuzzy matching for keys with potential BOM/encoding issues
        foreach ($keys as $target_key) {
            foreach (array_keys($data) as $actual_key) {
                $clean_actual = $this->cleanColumnName($actual_key);
                $clean_target = $this->cleanColumnName($target_key);
                
                if ($clean_actual === $clean_target) {
                    $value = $this->cleanValue($data[$actual_key]);
                    if (!empty($value)) {
                        return $value;
                    }
                }
            }
        }
        
        return $default;
    }
    
    private function cleanValue($value) {
        if ($value === null || $value === false) {
            return '';
        }
        
        // Convert to string and trim
        $cleaned = trim((string) $value);
        
        // Remove surrounding quotes if present
        if (strlen($cleaned) >= 2) {
            if (($cleaned[0] === '"' && $cleaned[-1] === '"') || 
                ($cleaned[0] === "'" && $cleaned[-1] === "'")) {
                $cleaned = substr($cleaned, 1, -1);
            }
        }
        
        // Remove problematic characters that can break CSV parsing
        // This includes tabs, carriage returns, and other control characters
        $cleaned = preg_replace('/[\t\r\n\v\f]/', ' ', $cleaned);
        
        // Normalize multiple spaces to single space
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        // Final trim
        return trim($cleaned);
    }
    
    private function cleanColumnName($name) {
        // Remove BOM, quotes, spaces, and normalize
        $cleaned = trim($name, " \t\n\r\0\x0B\xEF\xBB\xBF\"'");
        return strtolower($cleaned);
    }
    
    private function detectCsvSeparator($handle) {
        $pos = ftell($handle);
        $first_line = fgets($handle);
        fseek($handle, $pos);
        
        if (!$first_line) {
            return ';'; // default fallback
        }
        
        // Common separators to test
        $separators = [',', ';', '\t', '|'];
        $separator_counts = [];
        
        foreach ($separators as $sep) {
            $test_sep = ($sep === '\t') ? "\t" : $sep;
            $count = substr_count($first_line, $test_sep);
            $separator_counts[$test_sep] = $count;
        }
        
        // Return separator with highest count (most likely)
        $best_separator = array_search(max($separator_counts), $separator_counts);
        
        // Log detection for debugging
        error_log("CSV Separator detected: '$best_separator' (counts: " . json_encode($separator_counts) . ")");
        
        return $best_separator ?: ';';
    }
    
    private function removeBomFromHeader($header) {
        if (empty($header)) return $header;
        
        // Remove BOM from first column if present
        $first_col = $header[0];
        
        // Remove UTF-8 BOM (EF BB BF)
        if (substr($first_col, 0, 3) === "\xEF\xBB\xBF") {
            $header[0] = substr($first_col, 3);
        }
        
        // Clean all header columns
        $cleaned_header = [];
        foreach ($header as $col) {
            $cleaned_header[] = trim($col, " \t\n\r\0\x0B\xEF\xBB\xBF");
        }
        
        return $cleaned_header;
    }
    
    private function processRow($data, $type) {
        switch ($type) {
            case 'timbrature':
                return $this->processTimbrature($data);
            case 'richieste':
                return $this->processRichieste($data);
            case 'attivita':
                return $this->processAttivita($data);
            case 'calendario':
                return $this->processCalendario($data);
            case 'progetti':
                return $this->processProgetti($data);
            case 'registro_auto':
                return $this->processRegistroAuto($data);
            case 'teamviewer_bait':
            case 'teamviewer_gruppo':
                return $this->processTeamviewer($data);
            default:
                return false;
        }
    }
    
    private function processTimbrature($data) {
        try {
            $dipendente_id = $this->getDipendenteId($data['dipendente nome'], $data['dipendente cognome']);
            if (!$dipendente_id) {
                $dipendente_id = $this->createDipendente($data['dipendente nome'], $data['dipendente cognome']);
            }
            
            $cliente_id = null;
            if (!empty($data['cliente nome'])) {
                $cliente_id = $this->getClienteId($data['cliente nome']);
                if (!$cliente_id) {
                    $cliente_id = $this->createCliente($data['cliente nome'], $data['cliente indirizzo'], $data['cliente citt�'], $data['cliente provincia']);
                }
            }
            
            $data_parsed = $this->parseDate($data['ora inizio']);
            $ora_inizio = $this->parseTime($data['ora inizio']);
            $ora_fine = $this->parseTime($data['ora fine']);
            
            $sql = "INSERT INTO timbrature (
                dipendente_id, cliente_id, data, ora_inizio, ora_fine, ore_totali, ore_arrotondate, 
                ore_nette_pause, pausa_minuti, indirizzo_start, citta_start, provincia_start,
                indirizzo_end, citta_end, provincia_end, descrizione_attivita, timbratura_id_originale
            ) VALUES (
                :dipendente_id, :cliente_id, :data, :ora_inizio, :ora_fine, :ore_totali, :ore_arrotondate,
                :ore_nette_pause, :pausa_minuti, :indirizzo_start, :citta_start, :provincia_start,
                :indirizzo_end, :citta_end, :provincia_end, :descrizione_attivita, :timbratura_id_originale
            ) ON DUPLICATE KEY UPDATE
                ore_totali = VALUES(ore_totali),
                ore_arrotondate = VALUES(ore_arrotondate),
                ore_nette_pause = VALUES(ore_nette_pause),
                updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                ':dipendente_id' => $dipendente_id,
                ':cliente_id' => $cliente_id,
                ':data' => $data_parsed,
                ':ora_inizio' => $ora_inizio,
                ':ora_fine' => $ora_fine,
                ':ore_totali' => $this->parseFloat($data['ore']),
                ':ore_arrotondate' => $this->parseFloat($data['ore arrotondate']),
                ':ore_nette_pause' => $this->parseFloat($data['centesimi al netto delle pause']),
                ':pausa_minuti' => $this->parseMinutes($data['pausa sessantesimi']),
                ':indirizzo_start' => $data['indirizzo start'] ?? null,
                ':citta_start' => $data['citt� start'] ?? null,
                ':provincia_start' => $data['provincia start'] ?? null,
                ':indirizzo_end' => $data['indirizzo end'] ?? null,
                ':citta_end' => $data['citt� end'] ?? null,
                ':provincia_end' => $data['provincia end'] ?? null,
                ':descrizione_attivita' => $data['descrizione attivit�'] ?? null,
                ':timbratura_id_originale' => $data['timbratura id'] ?? null
            ]);
            
            return $result ? 'inserted' : false;
            
        } catch (Exception $e) {
            $this->errors[] = "Errore timbratura: " . $e->getMessage();
            return false;
        }
    }
    
    private function processRichieste($data) {
        try {
            $dipendente_id = $this->getDipendenteByFullName($data['Dipendente']);
            if (!$dipendente_id) {
                $parts = explode(', ', $data['Dipendente']);
                $dipendente_id = $this->createDipendente($parts[1] ?? '', $parts[0] ?? '');
            }
            
            $sql = "INSERT INTO richieste_assenze (
                dipendente_id, tipo, data_richiesta, data_inizio, data_fine, stato, note
            ) VALUES (
                :dipendente_id, :tipo, :data_richiesta, :data_inizio, :data_fine, :stato, :note
            ) ON DUPLICATE KEY UPDATE
                stato = VALUES(stato),
                note = VALUES(note),
                updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                ':dipendente_id' => $dipendente_id,
                ':tipo' => $this->mapTipoRichiesta($data['Tipo']),
                ':data_richiesta' => $this->parseDateTime($data['Data della richiesta']),
                ':data_inizio' => $this->parseDateTime($data['Data inizio']),
                ':data_fine' => $this->parseDateTime($data['Data fine']),
                ':stato' => $this->mapStatoRichiesta($data['Stato']),
                ':note' => $data['Note'] ?? null
            ]);
            
            return $result ? 'inserted' : false;
            
        } catch (Exception $e) {
            $this->errors[] = "Errore richiesta: " . $e->getMessage();
            return false;
        }
    }
    
    private function processAttivita($data) {
        try {
            $dipendente_id = $this->getDipendenteByFullName($data['Creato da']);
            if (!$dipendente_id) {
                $this->warnings[] = "Dipendente non trovato per attivit�: " . $data['Creato da'];
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
                ticket_id = VALUES(ticket_id),
                descrizione = VALUES(descrizione),
                riferimento_progetto = VALUES(riferimento_progetto),
                creato_da = VALUES(creato_da),
                fatturabile = VALUES(fatturabile),
                updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
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
            ]);
            
            return $result ? 'inserted' : false;
            
        } catch (Exception $e) {
            $this->errors[] = "Errore attivit�: " . $e->getMessage();
            return false;
        }
    }
    
    private function processCalendario($data) {
        try {
            $attendee = $this->getValueSafe($data, ['ATTENDEE']);
            if (!$attendee) {
                // Log più dettagliato per debugging
                $summary = $this->getValueSafe($data, ['SUMMARY'], 'N/A');
                $this->warnings[] = "Campo ATTENDEE mancante nel calendario per evento: " . $summary;
                return false;
            }
            
            // Log del valore ATTENDEE prima della pulizia per debugging
            $attendee_raw = isset($data['ATTENDEE']) ? $data['ATTENDEE'] : 'N/A';
            
            $dipendente_id = $this->getDipendenteByFullName($attendee);
            if (!$dipendente_id) {
                // Verifica se ATTENDEE è un nome veicolo anziché un dipendente
                if ($this->isVehicleName($attendee)) {
                    $this->warnings[] = "ATTENDEE è un veicolo, non un dipendente: '" . $attendee . "' - evento saltato";
                    return false;
                }
                
                // Prova a creare automaticamente il dipendente
                $this->warnings[] = "Dipendente non trovato per calendario: '" . $attendee . "' (raw: '" . $attendee_raw . "'), tentativo creazione automatica";
                
                // Tentativo di parsing del nome per creazione automatica
                if (strlen($attendee) > 0) {
                    try {
                        $dipendente_id = $this->createDipendenteFromFullName($attendee);
                        if ($dipendente_id) {
                            $this->warnings[] = "Dipendente creato automaticamente: " . $attendee . " (ID: $dipendente_id)";
                        }
                    } catch (Exception $e) {
                        $this->warnings[] = "Impossibile creare dipendente automaticamente: " . $attendee . " - " . $e->getMessage();
                    }
                }
                
                if (!$dipendente_id) {
                    return false;
                }
            }
            
            $sql = "INSERT INTO calendario (
                dipendente_id, titolo, data_inizio, data_fine, location, note, priorita
            ) VALUES (
                :dipendente_id, :titolo, :data_inizio, :data_fine, :location, :note, :priorita
            ) ON DUPLICATE KEY UPDATE
                titolo = VALUES(titolo),
                location = VALUES(location),
                updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                ':dipendente_id' => $dipendente_id,
                ':titolo' => $this->getValueSafe($data, ['SUMMARY'], ''),
                ':data_inizio' => $this->parseDateTime($this->getValueSafe($data, ['DTSTART'])),
                ':data_fine' => $this->parseDateTime($this->getValueSafe($data, ['DTEND'])),
                ':location' => $this->getValueSafe($data, ['LOCATION']),
                ':note' => $this->getValueSafe($data, ['NOTES']),
                ':priorita' => $this->getValueSafe($data, ['PRIORITY'], 5)
            ]);
            
            return $result ? 'inserted' : false;
            
        } catch (Exception $e) {
            $this->errors[] = "Errore calendario: " . $e->getMessage();
            return false;
        }
    }
    
    private function processProgetti($data) {
        try {
            $capo_progetto_id = $this->getDipendenteByFullName($data['Capo Progetto']);
            
            $cliente_id = null;
            if (!empty($data['Azienda Assegnataria'])) {
                $cliente_id = $this->getClienteId($data['Azienda Assegnataria']);
                if (!$cliente_id) {
                    $cliente_id = $this->createCliente($data['Azienda Assegnataria']);
                }
            }
            
            $sql = "INSERT INTO progetti (
                codice, nome, stato, priorita, cliente_id, capo_progetto_id, data_inizio
            ) VALUES (
                :codice, :nome, :stato, :priorita, :cliente_id, :capo_progetto_id, :data_inizio
            ) ON DUPLICATE KEY UPDATE
                nome = VALUES(nome),
                stato = VALUES(stato),
                priorita = VALUES(priorita),
                updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                ':codice' => $data['Codice Progetto'],
                ':nome' => $data['Nome'],
                ':stato' => $this->mapStatoProgetto($data['Stato']),
                ':priorita' => $this->mapPriorità($this->getValueSafe($data, ['Priorità', 'Priorit�', 'Priorita'], 'media')),
                ':cliente_id' => $cliente_id,
                ':capo_progetto_id' => $capo_progetto_id,
                ':data_inizio' => $this->parseDateTime($data['Creato il'])
            ]);
            
            return $result ? 'inserted' : false;
            
        } catch (Exception $e) {
            $this->errors[] = "Errore progetto: " . $e->getMessage();
            return false;
        }
    }
    
    private function processRegistroAuto($data) {
        try {
            $dipendente_id = $this->getDipendenteByFullName($data['Dipendente']);
            if (!$dipendente_id) {
                $this->warnings[] = "Dipendente non trovato per registro auto: " . $data['Dipendente'];
                return false;
            }
            
            $veicolo_id = $this->getVeicoloId($data['Auto']);
            if (!$veicolo_id) {
                $veicolo_id = $this->createVeicolo($data['Auto']);
            }
            
            $cliente_id = null;
            if (!empty($data['Cliente'])) {
                $cliente_id = $this->getClienteId($data['Cliente']);
                if (!$cliente_id) {
                    $cliente_id = $this->createCliente($data['Cliente']);
                }
            }
            
            $sql = "INSERT INTO registro_auto (
                dipendente_id, veicolo_id, cliente_id, data, ora_presa, ora_riconsegna
            ) VALUES (
                :dipendente_id, :veicolo_id, :cliente_id, :data, :ora_presa, :ora_riconsegna
            ) ON DUPLICATE KEY UPDATE
                ora_presa = VALUES(ora_presa),
                ora_riconsegna = VALUES(ora_riconsegna),
                updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                ':dipendente_id' => $dipendente_id,
                ':veicolo_id' => $veicolo_id,
                ':cliente_id' => $cliente_id,
                ':data' => $this->parseDate($data['Data']),
                ':ora_presa' => $this->parseDateTime($data['Presa Data e Ora']),
                ':ora_riconsegna' => !empty($data['Riconsegna Data e Ora']) ? $this->parseDateTime($data['Riconsegna Data e Ora']) : null
            ]);
            
            return $result ? 'inserted' : false;
            
        } catch (Exception $e) {
            $this->errors[] = "Errore registro auto: " . $e->getMessage();
            return false;
        }
    }
    
    private function processTeamviewer($data) {
        try {
            // Debug: log available columns and their values
            $available_columns = array_keys($data);
            
            // Debug: check specific columns content
            $assegnatario_value = isset($data['Assegnatario']) ? $data['Assegnatario'] : 'NOT_FOUND';
            $utente_value = isset($data['Utente']) ? $data['Utente'] : 'NOT_FOUND';
            
            // Gestione flessibile per diverse strutture TeamViewer
            $dipendente_name = $this->getValueSafe($data, ['Utente', 'Assegnatario']);
            if (!$dipendente_name) {
                $this->warnings[] = "Dipendente non trovato nelle colonne TeamViewer. " .
                    "Colonne disponibili: " . implode(', ', $available_columns) . ". " .
                    "Assegnatario='{$assegnatario_value}', Utente='{$utente_value}'";
                return false;
            }
            
            $dipendente_id = $this->getDipendenteByFullName($dipendente_name);
            if (!$dipendente_id) {
                $this->warnings[] = "Dipendente non trovato per TeamViewer: " . $dipendente_name;
                return false;
            }
            
            $sql = "INSERT INTO teamviewer_sessioni (
                dipendente_id, nome_cliente, email_cliente, codice_sessione, tipo_sessione, 
                gruppo, data_inizio, data_fine, durata_minuti, tariffa, modalita_calcolo, 
                descrizione, note, classificazione, fatturabile
            ) VALUES (
                :dipendente_id, :nome_cliente, :email_cliente, :codice_sessione, :tipo_sessione,
                :gruppo, :data_inizio, :data_fine, :durata_minuti, :tariffa, :modalita_calcolo,
                :descrizione, :note, :classificazione, :fatturabile
            ) ON DUPLICATE KEY UPDATE
                durata_minuti = VALUES(durata_minuti),
                updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                ':dipendente_id' => $dipendente_id,
                ':nome_cliente' => $this->getValueSafe($data, ['Nome', 'Computer']),
                ':email_cliente' => $this->getValueSafe($data, ['E-mail']),
                ':codice_sessione' => $this->getValueSafe($data, ['Codice', 'ID']),
                ':tipo_sessione' => $this->getValueSafe($data, ['Tipo di sessione'], 'Controllo remoto'),
                ':gruppo' => $this->getValueSafe($data, ['Gruppo']),
                ':data_inizio' => $this->parseDateTime($this->getValueSafe($data, ['Inizio'])),
                ':data_fine' => $this->parseDateTime($this->getValueSafe($data, ['Fine'])),
                ':durata_minuti' => $this->parseDuration($this->getValueSafe($data, ['Durata'])),
                ':tariffa' => $this->parseFloat($this->getValueSafe($data, ['Tariffa'], '0')),
                ':modalita_calcolo' => $this->getValueSafe($data, ['Calcolo'], 'Fattura'),
                ':descrizione' => $this->getValueSafe($data, ['Descrizione']),
                ':note' => $this->getValueSafe($data, ['Note']),
                ':classificazione' => $this->getValueSafe($data, ['Classificazione']),
                ':fatturabile' => 1
            ]);
            
            return $result ? 'inserted' : false;
            
        } catch (Exception $e) {
            $this->errors[] = "Errore TeamViewer: " . $e->getMessage();
            return false;
        }
    }
    
    private function getDipendenteId($nome, $cognome) {
        $stmt = $this->conn->prepare("SELECT id FROM dipendenti WHERE nome = :nome AND cognome = :cognome");
        $stmt->execute([':nome' => $nome, ':cognome' => $cognome]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    private function getDipendenteByFullName($fullName) {
        if (empty($fullName)) return null;
        
        // Try to find existing employee first
        $dipendente_id = $this->findExistingDipendente($fullName);
        if ($dipendente_id) {
            return $dipendente_id;
        }
        
        // If not found, create new employee
        return $this->createDipendenteFromFullName($fullName);
    }
    
    private function findExistingDipendente($fullName) {
        // Try different parsing methods
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
                $dipendente_id = $this->getDipendenteId($parsed[0], $parsed[1]);
                if ($dipendente_id) {
                    return $dipendente_id;
                }
            }
        }
        
        return null;
    }
    
    private function createDipendenteFromFullName($fullName) {
        // Validazione pre-creazione
        if (!$this->isValidEmployeeName($fullName)) {
            $this->logger->logEmployeeRejected($fullName, 'Nome non valido secondo blacklist/regole');
            return null;
        }
        
        // Parse name into nome and cognome
        $parts = explode(' ', trim($fullName));
        if (count($parts) >= 2) {
            $nome = $parts[0];
            $cognome = implode(' ', array_slice($parts, 1));
        } elseif (strpos($fullName, ', ') !== false) {
            $parts = explode(', ', $fullName);
            $nome = isset($parts[1]) ? $parts[1] : $parts[0];
            $cognome = $parts[0];
        } else {
            // Single name - use as nome
            $nome = $fullName;
            $cognome = '';
        }
        
        // Validazione aggiuntiva sui singoli campi
        if (!$this->isValidEmployeeName($nome) || !$this->isValidEmployeeName($cognome, true)) {
            $this->logger->logEmployeeRejected("$nome $cognome", 'Nome o cognome non valido');
            return null;
        }
        
        $dipendente_id = $this->createDipendente($nome, $cognome);
        if ($dipendente_id) {
            $this->logger->logEmployeeCreated("$nome $cognome", $dipendente_id);
        }
        return $dipendente_id;
    }
    
    private function createDipendente($nome, $cognome) {
        $stmt = $this->conn->prepare("INSERT INTO dipendenti (nome, cognome) VALUES (:nome, :cognome)");
        $stmt->execute([':nome' => $nome, ':cognome' => $cognome]);
        return $this->conn->lastInsertId();
    }
    
    private function getClienteId($nome) {
        $stmt = $this->conn->prepare("SELECT id FROM clienti WHERE nome = :nome");
        $stmt->execute([':nome' => $nome]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    private function createCliente($nome, $indirizzo = null, $citta = null, $provincia = null) {
        $stmt = $this->conn->prepare("INSERT INTO clienti (nome, indirizzo, citta, provincia) VALUES (:nome, :indirizzo, :citta, :provincia)");
        $stmt->execute([':nome' => $nome, ':indirizzo' => $indirizzo, ':citta' => $citta, ':provincia' => $provincia]);
        return $this->conn->lastInsertId();
    }
    
    private function getVeicoloId($nome) {
        $stmt = $this->conn->prepare("SELECT id FROM veicoli WHERE nome = :nome");
        $stmt->execute([':nome' => $nome]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    private function createVeicolo($nome) {
        $stmt = $this->conn->prepare("INSERT INTO veicoli (nome) VALUES (:nome)");
        $stmt->execute([':nome' => $nome]);
        return $this->conn->lastInsertId();
    }
    
    private function getProgettoByRiferimento($riferimento) {
        $stmt = $this->conn->prepare("SELECT id FROM progetti WHERE codice = :codice");
        $stmt->execute([':codice' => $riferimento]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
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
    
    private function parseMinutes($timeString) {
        if (empty($timeString)) return 0;
        
        $parts = explode(':', $timeString);
        if (count($parts) >= 2) {
            return (int)$parts[0] * 60 + (int)$parts[1];
        }
        
        return 0;
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
    
    private function mapTipoRichiesta($tipo) {
        $mapping = [
            'Ferie' => 'ferie',
            'Permessi' => 'permessi',
            'ROL' => 'rol',
            'Permessi ex festivit�' => 'ex_festivita',
            'Malattia' => 'malattia'
        ];
        return $mapping[$tipo] ?? 'permessi';
    }
    
    private function mapStatoRichiesta($stato) {
        $mapping = [
            'Approvata' => 'approvata',
            'Rifiutata' => 'rifiutata',
            'Annullamento approvato' => 'annullata',
            'In attesa' => 'in_attesa'
        ];
        return $mapping[$stato] ?? 'in_attesa';
    }
    
    private function mapStatoProgetto($stato) {
        $mapping = [
            'Attivo' => 'attivo',
            'Sospeso' => 'sospeso',
            'Completato' => 'completato',
            'Cancellato' => 'cancellato'
        ];
        return $mapping[$stato] ?? 'attivo';
    }
    
    private function mapPriorità($priorita) {
        $mapping = [
            'Alta' => 'alta',
            'Media' => 'media',
            'Bassa' => 'bassa',
            'Critica' => 'critica'
        ];
        return $mapping[$priorita] ?? 'media';
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getWarnings() {
        return $this->warnings;
    }
    
    public function getStats() {
        return $this->stats;
    }
    
    private function isVehicleName($name) {
        $stmt = $this->conn->prepare("SELECT id FROM veicoli WHERE nome = :nome");
        $stmt->execute([':nome' => trim($name)]);
        return $stmt->fetch() !== false;
    }
    
    private function isValidEmployeeName($name, $allow_empty = false) {
        $name = trim($name);
        
        // Permetti nomi vuoti solo se esplicitamente consentito (per cognomi)
        if (empty($name)) {
            return $allow_empty;
        }
        
        // Blacklist esplicita di nomi non validi
        $blacklist = [
            // Veicoli
            'Punto', 'Fiesta', 'Peugeot', 'Auto', 'Veicolo',
            // Sistemi/Termini tecnici
            'Info', 'System', 'Admin', 'Test', 'User', 'Guest', 'TRUE', 'FALSE', 'NULL',
            // Termini generici
            'Aurora', 'Reminder', 'Meeting', 'Riunione', 'Supporto', 'Backup',
            // Termini aziendali
            'Cliente', 'Fornitore', 'Ufficio', 'Sede', 'Filiale'
        ];
        
        // Controlla blacklist (case-insensitive)
        if (in_array(ucfirst(strtolower($name)), $blacklist) || 
            in_array(strtoupper($name), $blacklist) ||
            in_array(strtolower($name), array_map('strtolower', $blacklist))) {
            return false;
        }
        
        // Controlla se è un nome di veicolo nel database
        if ($this->isVehicleName($name)) {
            return false;
        }
        
        // Validazioni formato
        if (strlen($name) < 2) {
            return false; // Nome troppo corto
        }
        
        if (strlen($name) > 50) {
            return false; // Nome troppo lungo
        }
        
        // Controlla caratteri non validi
        if (strpos($name, '@') !== false) {
            return false; // Sembra un'email
        }
        
        if (preg_match('/^\d+$/', $name)) {
            return false; // Solo numeri
        }
        
        if (preg_match('/^[^a-zA-ZÀ-ÿ\s\'-]/', $name)) {
            return false; // Inizia con caratteri non alfabetici
        }
        
        // Controlla pattern sospetti
        if (preg_match('/^(pc|nb|server|host|www|http|ftp)/i', $name)) {
            return false; // Sembra un nome di computer/server
        }
        
        if (preg_match('/\.(com|it|org|net|gov)$/i', $name)) {
            return false; // Sembra un dominio
        }
        
        return true;
    }
}
?>