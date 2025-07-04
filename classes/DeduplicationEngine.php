<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/ImportLogger.php';

/**
 * Deduplication Engine for robust import anti-duplication
 * 
 * Sistema avanzato per prevenire e gestire duplicazioni durante import CSV
 * Supporta soft deduplication, merge intelligente e threshold configurabili
 */
class DeduplicationEngine {
    private $conn;
    private $logger;
    private $config;
    
    // Configurazioni deduplicazione
    private $time_threshold_minutes = 5; // Tolleranza temporale per attività simili
    private $similarity_threshold = 0.8; // Soglia similarità per descrizioni
    private $enable_soft_deduplication = true; // Marca duplicati invece di skippare
    
    // Statistiche sessione
    private $stats = [
        'duplicates_detected' => 0,
        'duplicates_merged' => 0,
        'duplicates_marked' => 0,
        'unique_inserted' => 0
    ];
    
    public function __construct($config = []) {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->logger = new ImportLogger('deduplication');
        
        // Configurazioni personalizzate
        $this->config = array_merge([
            'time_threshold_minutes' => 5,
            'similarity_threshold' => 0.8,
            'enable_soft_deduplication' => true,
            'enable_intelligent_merge' => true,
            'enable_preview_mode' => false
        ], $config);
        
        $this->time_threshold_minutes = $this->config['time_threshold_minutes'];
        $this->similarity_threshold = $this->config['similarity_threshold'];
        $this->enable_soft_deduplication = $this->config['enable_soft_deduplication'];
        
        $this->initializeDeduplicationSchema();
    }
    
    /**
     * Inizializza schema per deduplicazione se necessario
     */
    private function initializeDeduplicationSchema() {
        try {
            // Aggiungi colonne per deduplicazione se non esistono
            $this->addColumnIfNotExists('attivita', 'activity_hash', 'VARCHAR(32) DEFAULT NULL');
            $this->addColumnIfNotExists('attivita', 'is_duplicate', 'TINYINT(1) DEFAULT 0');
            $this->addColumnIfNotExists('attivita', 'original_activity_id', 'INT(11) DEFAULT NULL');
            $this->addColumnIfNotExists('attivita', 'duplicate_reason', 'TEXT DEFAULT NULL');
            $this->addColumnIfNotExists('attivita', 'confidence_score', 'DECIMAL(3,2) DEFAULT NULL');
            
            // Crea indici per performance
            $this->createIndexIfNotExists('attivita', 'idx_activity_hash', 'activity_hash');
            $this->createIndexIfNotExists('attivita', 'idx_duplicate_detection', 'dipendente_id, data_inizio, durata_ore');
            $this->createIndexIfNotExists('attivita', 'idx_is_duplicate', 'is_duplicate');
            
            $this->logger->info("Schema deduplicazione inizializzato");
            
        } catch (Exception $e) {
            $this->logger->error("Errore inizializzazione schema deduplicazione: " . $e->getMessage());
        }
    }
    
    /**
     * Aggiunge colonna se non esiste
     */
    private function addColumnIfNotExists($table, $column, $definition) {
        try {
            $stmt = $this->conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            
            if (!$stmt->fetch()) {
                $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
                $this->conn->exec($sql);
                $this->logger->info("Aggiunta colonna $column a tabella $table");
            }
        } catch (Exception $e) {
            $this->logger->warning("Impossibile aggiungere colonna $column: " . $e->getMessage());
        }
    }
    
    /**
     * Crea indice se non esiste
     */
    private function createIndexIfNotExists($table, $indexName, $columns) {
        try {
            $stmt = $this->conn->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
            $stmt->execute([$indexName]);
            
            if (!$stmt->fetch()) {
                $sql = "CREATE INDEX `$indexName` ON `$table` ($columns)";
                $this->conn->exec($sql);
                $this->logger->info("Creato indice $indexName su tabella $table");
            }
        } catch (Exception $e) {
            $this->logger->warning("Impossibile creare indice $indexName: " . $e->getMessage());
        }
    }
    
    /**
     * Controlla se un'attività è duplicata
     */
    public function checkActivityDuplicate($activityData) {
        try {
            // 1. Genera hash attività
            $activity_hash = $this->generateActivityHash($activityData);
            
            // 2. Ricerca duplicati esatti per hash
            $exact_duplicate = $this->findExactDuplicateByHash($activity_hash);
            if ($exact_duplicate) {
                $this->stats['duplicates_detected']++;
                return [
                    'is_duplicate' => true,
                    'duplicate_type' => 'exact_hash',
                    'original_id' => $exact_duplicate['id'],
                    'confidence' => 1.0,
                    'reason' => 'Hash identico per dipendente, data e durata',
                    'action' => $this->enable_soft_deduplication ? 'mark' : 'skip'
                ];
            }
            
            // 3. Ricerca duplicati fuzzy
            $fuzzy_duplicate = $this->findFuzzyDuplicate($activityData);
            if ($fuzzy_duplicate) {
                $this->stats['duplicates_detected']++;
                return [
                    'is_duplicate' => true,
                    'duplicate_type' => 'fuzzy_match',
                    'original_id' => $fuzzy_duplicate['original_id'],
                    'confidence' => $fuzzy_duplicate['confidence'],
                    'reason' => $fuzzy_duplicate['reason'],
                    'action' => $fuzzy_duplicate['confidence'] > 0.9 ? 'merge' : 'mark'
                ];
            }
            
            // 4. Nessun duplicato trovato
            return [
                'is_duplicate' => false,
                'activity_hash' => $activity_hash,
                'action' => 'insert'
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Errore controllo duplicati: " . $e->getMessage());
            return [
                'is_duplicate' => false,
                'action' => 'insert',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Genera hash univoco per attività
     */
    private function generateActivityHash($activityData) {
        $hashElements = [
            $activityData['dipendente_id'] ?? '',
            $activityData['data_inizio'] ?? '',
            $activityData['durata_ore'] ?? '',
            $activityData['ticket_id'] ?? '' // Include ticket per maggiore precisione
        ];
        
        $hashString = implode('|', $hashElements);
        return md5($hashString);
    }
    
    /**
     * Trova duplicato esatto per hash
     */
    private function findExactDuplicateByHash($hash) {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, dipendente_id, data_inizio, durata_ore, descrizione
                FROM attivita 
                WHERE activity_hash = ? AND is_duplicate = 0
                LIMIT 1
            ");
            $stmt->execute([$hash]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Errore ricerca duplicato esatto: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Trova duplicati fuzzy con soglie temporali e di similarità
     */
    private function findFuzzyDuplicate($activityData) {
        try {
            $dipendente_id = $activityData['dipendente_id'];
            $data_inizio = $activityData['data_inizio'];
            $durata_ore = $activityData['durata_ore'];
            
            if (!$dipendente_id || !$data_inizio) {
                return null;
            }
            
            // Finestra temporale di ricerca
            $time_window_start = date('Y-m-d H:i:s', strtotime($data_inizio) - ($this->time_threshold_minutes * 60));
            $time_window_end = date('Y-m-d H:i:s', strtotime($data_inizio) + ($this->time_threshold_minutes * 60));
            
            $stmt = $this->conn->prepare("
                SELECT id, data_inizio, durata_ore, descrizione, ticket_id
                FROM attivita 
                WHERE dipendente_id = ? 
                  AND data_inizio BETWEEN ? AND ?
                  AND is_duplicate = 0
                  AND id != ?
                ORDER BY ABS(TIMESTAMPDIFF(MINUTE, data_inizio, ?)) ASC
                LIMIT 10
            ");
            
            $stmt->execute([
                $dipendente_id, 
                $time_window_start, 
                $time_window_end, 
                $activityData['id'] ?? 0,
                $data_inizio
            ]);
            
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($candidates as $candidate) {
                $similarity_score = $this->calculateSimilarityScore($activityData, $candidate);
                
                if ($similarity_score >= $this->similarity_threshold) {
                    $time_diff = abs(strtotime($data_inizio) - strtotime($candidate['data_inizio']));
                    
                    return [
                        'original_id' => $candidate['id'],
                        'confidence' => $similarity_score,
                        'reason' => sprintf(
                            'Attività simile (%.0f%%) trovata a distanza di %d minuti',
                            $similarity_score * 100,
                            $time_diff / 60
                        )
                    ];
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->logger->error("Errore ricerca duplicato fuzzy: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calcola score di similarità tra due attività
     */
    private function calculateSimilarityScore($activity1, $activity2) {
        $score = 0;
        $weights = [
            'durata_ore' => 0.3,
            'descrizione' => 0.4,
            'ticket_id' => 0.3
        ];
        
        // Similarità durata (tolleranza ±10%)
        if (isset($activity1['durata_ore']) && isset($activity2['durata_ore'])) {
            $durata1 = (float)$activity1['durata_ore'];
            $durata2 = (float)$activity2['durata_ore'];
            
            if ($durata1 > 0 && $durata2 > 0) {
                $diff = abs($durata1 - $durata2) / max($durata1, $durata2);
                $duration_similarity = max(0, 1 - $diff * 10); // Penalizza differenze > 10%
                $score += $duration_similarity * $weights['durata_ore'];
            }
        }
        
        // Similarità descrizione
        if (isset($activity1['descrizione']) && isset($activity2['descrizione'])) {
            $desc1 = strtolower(trim($activity1['descrizione']));
            $desc2 = strtolower(trim($activity2['descrizione']));
            
            if (!empty($desc1) && !empty($desc2)) {
                $description_similarity = $this->calculateTextSimilarity($desc1, $desc2);
                $score += $description_similarity * $weights['descrizione'];
            }
        }
        
        // Similarità ticket ID
        if (isset($activity1['ticket_id']) && isset($activity2['ticket_id'])) {
            $ticket1 = trim($activity1['ticket_id']);
            $ticket2 = trim($activity2['ticket_id']);
            
            if (!empty($ticket1) && !empty($ticket2)) {
                $ticket_similarity = ($ticket1 === $ticket2) ? 1.0 : 0.0;
                $score += $ticket_similarity * $weights['ticket_id'];
            }
        }
        
        return min(1.0, $score);
    }
    
    /**
     * Calcola similarità tra testi usando Levenshtein distance
     */
    private function calculateTextSimilarity($text1, $text2) {
        $maxLen = max(strlen($text1), strlen($text2));
        if ($maxLen === 0) return 1.0;
        
        $distance = levenshtein(substr($text1, 0, 255), substr($text2, 0, 255));
        return max(0, 1 - ($distance / $maxLen));
    }
    
    /**
     * Inserisce attività con gestione duplicati
     */
    public function insertActivityWithDeduplication($activityData, $sql, $params) {
        try {
            // Controlla duplicati
            $duplicate_check = $this->checkActivityDuplicate($activityData);
            
            if ($duplicate_check['is_duplicate']) {
                return $this->handleDuplicateActivity($duplicate_check, $activityData, $sql, $params);
            } else {
                // Inserimento normale con hash
                return $this->insertUniqueActivity($duplicate_check, $sql, $params);
            }
            
        } catch (Exception $e) {
            $this->logger->error("Errore inserimento con deduplicazione: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Gestisce attività duplicata secondo strategia configurata
     */
    private function handleDuplicateActivity($duplicate_check, $activityData, $sql, $params) {
        $action = $duplicate_check['action'];
        
        switch ($action) {
            case 'skip':
                $this->logger->info("Duplicato skippato", [
                    'reason' => $duplicate_check['reason'],
                    'confidence' => $duplicate_check['confidence']
                ]);
                return 'skipped_duplicate';
                
            case 'mark':
                return $this->markAsDuplicate($duplicate_check, $sql, $params);
                
            case 'merge':
                return $this->mergeWithOriginal($duplicate_check, $activityData);
                
            default:
                return $this->insertUniqueActivity($duplicate_check, $sql, $params);
        }
    }
    
    /**
     * Marca attività come duplicato ma la inserisce
     */
    private function markAsDuplicate($duplicate_check, $sql, $params) {
        try {
            // Modifica SQL per includere campi duplicazione
            $modified_sql = str_replace(
                'VALUES (',
                'VALUES (',
                $sql
            );
            
            // Aggiungi parametri duplicazione
            $params[':is_duplicate'] = 1;
            $params[':original_activity_id'] = $duplicate_check['original_id'];
            $params[':duplicate_reason'] = $duplicate_check['reason'];
            $params[':confidence_score'] = $duplicate_check['confidence'];
            
            // Modifica SQL per includere nuovi campi
            if (strpos($modified_sql, 'is_duplicate') === false) {
                $modified_sql = str_replace(
                    ') VALUES (',
                    ', is_duplicate, original_activity_id, duplicate_reason, confidence_score) VALUES (',
                    $modified_sql
                );
                $modified_sql = str_replace(
                    'VALUES (',
                    'VALUES (',
                    $modified_sql
                );
                $modified_sql = str_replace(
                    ':fatturabile)',
                    ':fatturabile, :is_duplicate, :original_activity_id, :duplicate_reason, :confidence_score)',
                    $modified_sql
                );
            }
            
            $stmt = $this->conn->prepare($modified_sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $this->stats['duplicates_marked']++;
                $this->logger->info("Duplicato marcato e inserito", [
                    'original_id' => $duplicate_check['original_id'],
                    'confidence' => $duplicate_check['confidence']
                ]);
            }
            
            return $result ? 'inserted_as_duplicate' : false;
            
        } catch (Exception $e) {
            $this->logger->error("Errore marcatura duplicato: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Merge con attività originale (aggiorna dati)
     */
    private function mergeWithOriginal($duplicate_check, $activityData) {
        try {
            $original_id = $duplicate_check['original_id'];
            
            // Ottieni attività originale
            $stmt = $this->conn->prepare("SELECT * FROM attivita WHERE id = ?");
            $stmt->execute([$original_id]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$original) {
                $this->logger->warning("Attività originale non trovata per merge: $original_id");
                return false;
            }
            
            // Merge intelligente: mantieni il dato più completo/recente
            $merged_data = $this->intelligentMerge($original, $activityData);
            
            // Aggiorna attività originale
            $update_sql = "
                UPDATE attivita SET 
                    descrizione = ?, 
                    ticket_id = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ";
            
            $stmt = $this->conn->prepare($update_sql);
            $result = $stmt->execute([
                $merged_data['descrizione'],
                $merged_data['ticket_id'],
                $original_id
            ]);
            
            if ($result) {
                $this->stats['duplicates_merged']++;
                $this->logger->info("Duplicato merged con originale", [
                    'original_id' => $original_id,
                    'merged_fields' => array_keys($merged_data)
                ]);
            }
            
            return $result ? 'merged' : false;
            
        } catch (Exception $e) {
            $this->logger->error("Errore merge duplicato: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Merge intelligente di due attività
     */
    private function intelligentMerge($original, $new) {
        $merged = [];
        
        // Descrizione: prendi la più lunga/dettagliata
        $original_desc = trim($original['descrizione'] ?? '');
        $new_desc = trim($new['descrizione'] ?? '');
        
        if (strlen($new_desc) > strlen($original_desc)) {
            $merged['descrizione'] = $new_desc;
        } else {
            $merged['descrizione'] = $original_desc;
        }
        
        // Ticket ID: prendi quello non vuoto
        $merged['ticket_id'] = !empty($new['ticket_id']) ? $new['ticket_id'] : $original['ticket_id'];
        
        return $merged;
    }
    
    /**
     * Inserisce attività unica con hash
     */
    private function insertUniqueActivity($duplicate_check, $sql, $params) {
        try {
            // Aggiungi hash se presente
            if (isset($duplicate_check['activity_hash'])) {
                $params[':activity_hash'] = $duplicate_check['activity_hash'];
                
                // Modifica SQL per includere activity_hash se non già presente
                if (strpos($sql, 'activity_hash') === false) {
                    $sql = str_replace(
                        ', fatturabile)',
                        ', fatturabile, activity_hash)',
                        $sql
                    );
                    $sql = str_replace(
                        ', :fatturabile)',
                        ', :fatturabile, :activity_hash)',
                        $sql
                    );
                }
            }
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $this->stats['unique_inserted']++;
            }
            
            return $result ? 'inserted' : false;
            
        } catch (Exception $e) {
            $this->logger->error("Errore inserimento attività unica: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Analizza duplicati esistenti nel database
     */
    public function analyzeDuplicatesInDatabase() {
        try {
            $analysis = [
                'total_activities' => 0,
                'potential_duplicates' => 0,
                'exact_duplicates' => 0,
                'fuzzy_duplicates' => 0,
                'duplicate_groups' => []
            ];
            
            // Conta totale attività
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM attivita");
            $stmt->execute();
            $analysis['total_activities'] = $stmt->fetch()['count'];
            
            // Trova duplicati esatti (stesso dipendente, data, durata)
            $stmt = $this->conn->prepare("
                SELECT 
                    dipendente_id, 
                    DATE(data_inizio) as data_attivita, 
                    durata_ore,
                    COUNT(*) as duplicate_count,
                    GROUP_CONCAT(id) as activity_ids
                FROM attivita 
                WHERE is_duplicate = 0
                GROUP BY dipendente_id, DATE(data_inizio), durata_ore
                HAVING COUNT(*) > 1
                ORDER BY duplicate_count DESC
                LIMIT 20
            ");
            $stmt->execute();
            $exact_duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $analysis['exact_duplicates'] = count($exact_duplicates);
            $analysis['duplicate_groups'] = $exact_duplicates;
            
            // Stima duplicati fuzzy (stesso dipendente, stesso giorno)
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as fuzzy_count
                FROM (
                    SELECT dipendente_id, DATE(data_inizio) as data_attivita, COUNT(*) as day_count
                    FROM attivita 
                    WHERE is_duplicate = 0
                    GROUP BY dipendente_id, DATE(data_inizio)
                    HAVING COUNT(*) > 3
                ) as potential_fuzzy
            ");
            $stmt->execute();
            $analysis['fuzzy_duplicates'] = $stmt->fetch()['fuzzy_count'];
            
            $analysis['potential_duplicates'] = $analysis['exact_duplicates'] + $analysis['fuzzy_duplicates'];
            
            return $analysis;
            
        } catch (Exception $e) {
            $this->logger->error("Errore analisi duplicati: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Cleanup duplicati esistenti
     */
    public function cleanupExistingDuplicates($dry_run = true) {
        try {
            $cleanup_stats = [
                'analyzed' => 0,
                'marked_as_duplicates' => 0,
                'merged' => 0,
                'errors' => 0
            ];
            
            if ($dry_run) {
                $this->logger->info("Avvio cleanup duplicati in modalità DRY RUN");
            } else {
                $this->logger->info("Avvio cleanup duplicati in modalità EFFETTIVA");
                $this->conn->beginTransaction();
            }
            
            // Trova gruppi di duplicati esatti
            $stmt = $this->conn->prepare("
                SELECT 
                    dipendente_id, 
                    data_inizio, 
                    durata_ore,
                    COUNT(*) as duplicate_count,
                    MIN(id) as first_id,
                    GROUP_CONCAT(id ORDER BY id) as all_ids
                FROM attivita 
                WHERE is_duplicate = 0
                GROUP BY dipendente_id, data_inizio, durata_ore
                HAVING COUNT(*) > 1
                ORDER BY duplicate_count DESC
            ");
            $stmt->execute();
            $duplicate_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($duplicate_groups as $group) {
                $cleanup_stats['analyzed']++;
                $all_ids = explode(',', $group['all_ids']);
                $first_id = $group['first_id'];
                $duplicate_ids = array_filter($all_ids, function($id) use ($first_id) {
                    return $id != $first_id;
                });
                
                if (!$dry_run) {
                    // Marca gli altri come duplicati
                    $ids_placeholder = implode(',', array_fill(0, count($duplicate_ids), '?'));
                    $update_sql = "
                        UPDATE attivita SET 
                            is_duplicate = 1,
                            original_activity_id = ?,
                            duplicate_reason = 'Cleanup automatico - duplicato esatto',
                            confidence_score = 1.0
                        WHERE id IN ($ids_placeholder)
                    ";
                    
                    $params = array_merge([$first_id], $duplicate_ids);
                    $stmt = $this->conn->prepare($update_sql);
                    $result = $stmt->execute($params);
                    
                    if ($result) {
                        $cleanup_stats['marked_as_duplicates'] += count($duplicate_ids);
                    } else {
                        $cleanup_stats['errors']++;
                    }
                } else {
                    $cleanup_stats['marked_as_duplicates'] += count($duplicate_ids);
                }
                
                $this->logger->info("Gruppo duplicati processato", [
                    'first_id' => $first_id,
                    'duplicate_count' => count($duplicate_ids),
                    'dry_run' => $dry_run
                ]);
            }
            
            if (!$dry_run) {
                $this->conn->commit();
                $this->logger->info("Cleanup completato e committato");
            } else {
                $this->logger->info("Cleanup DRY RUN completato");
            }
            
            return $cleanup_stats;
            
        } catch (Exception $e) {
            if (!$dry_run && $this->conn->inTransaction()) {
                $this->conn->rollback();
            }
            $this->logger->error("Errore cleanup duplicati: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Ottieni statistiche sessione
     */
    public function getStats() {
        return $this->stats;
    }
    
    /**
     * Reset statistiche
     */
    public function resetStats() {
        $this->stats = [
            'duplicates_detected' => 0,
            'duplicates_merged' => 0,
            'duplicates_marked' => 0,
            'unique_inserted' => 0
        ];
    }
}
?>