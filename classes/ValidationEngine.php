<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Configuration.php';

class ValidationEngine {
    private $conn;
    private $config;
    private $thresholds;
    private $anomalie = [];
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->config = new Configuration();
        $this->thresholds = $this->config->getKpiThresholds();
    }
    
    public function validateAllData($data_inizio = null, $data_fine = null) {
        $data_inizio = $data_inizio ?? date('Y-m-d');
        $data_fine = $data_fine ?? date('Y-m-d');
        
        $this->anomalie = [];
        
        $this->validateTimbratureVsAttivita($data_inizio, $data_fine);
        $this->validateSovrapposizioni($data_inizio, $data_fine);
        $this->validateTrasferte($data_inizio, $data_fine);
        $this->validateSessioniRemote($data_inizio, $data_fine);
        $this->validateOreSufficienti($data_inizio, $data_fine);
        $this->validateRapportiniMancanti($data_inizio, $data_fine);
        
        $this->saveAnomalieToDatabase();
        
        return $this->anomalie;
    }
    
    private function validateTimbratureVsAttivita($data_inizio, $data_fine) {
        $sql = "SELECT 
            d.id as dipendente_id,
            d.nome,
            d.cognome,
            DATE(t.data) as data,
            SUM(t.ore_totali) as ore_timbrature,
            COALESCE(SUM(a.durata_ore), 0) as ore_attivita,
            COALESCE(SUM(c.durata_ore), 0) as ore_calendario
        FROM dipendenti d
        LEFT JOIN timbrature t ON d.id = t.dipendente_id 
            AND t.data BETWEEN :data_inizio AND :data_fine
        LEFT JOIN (
            SELECT 
                dipendente_id,
                DATE(data_inizio) as data,
                SUM(durata_ore) as durata_ore
            FROM attivita 
            WHERE DATE(data_inizio) BETWEEN :data_inizio AND :data_fine
            GROUP BY dipendente_id, DATE(data_inizio)
        ) a ON d.id = a.dipendente_id AND DATE(t.data) = a.data
        LEFT JOIN (
            SELECT 
                dipendente_id,
                DATE(data_inizio) as data,
                SUM(TIMESTAMPDIFF(MINUTE, data_inizio, data_fine) / 60) as durata_ore
            FROM calendario 
            WHERE DATE(data_inizio) BETWEEN :data_inizio AND :data_fine
            GROUP BY dipendente_id, DATE(data_inizio)
        ) c ON d.id = c.dipendente_id AND DATE(t.data) = c.data
        WHERE d.attivo = 1 AND t.data IS NOT NULL
        GROUP BY d.id, DATE(t.data)
        HAVING 
            ABS(ore_timbrature - ore_attivita) > :tolleranza OR
            ABS(ore_timbrature - ore_calendario) > :tolleranza
        ORDER BY d.cognome, d.nome, data";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':data_inizio' => $data_inizio,
            ':data_fine' => $data_fine,
            ':tolleranza' => $this->thresholds['tolleranza_ore_max']
        ]);
        
        $results = $stmt->fetchAll();
        
        foreach ($results as $row) {
            $gap_attivita = abs($row['ore_timbrature'] - $row['ore_attivita']);
            $gap_calendario = abs($row['ore_timbrature'] - $row['ore_calendario']);
            
            $severita = 'media';
            if ($gap_attivita > 3 || $gap_calendario > 3) {
                $severita = 'alta';
            } elseif ($gap_attivita > 2 || $gap_calendario > 2) {
                $severita = 'media';
            } else {
                $severita = 'bassa';
            }
            
            $this->anomalie[] = [
                'dipendente_id' => $row['dipendente_id'],
                'data' => $row['data'],
                'tipo_anomalia' => 'ore_eccessive',
                'severita' => $severita,
                'descrizione' => "Discrepanza ore: Timbrature {$row['ore_timbrature']}h vs Attivit� {$row['ore_attivita']}h vs Calendario {$row['ore_calendario']}h",
                'dettagli_json' => json_encode([
                    'ore_timbrature' => $row['ore_timbrature'],
                    'ore_attivita' => $row['ore_attivita'],
                    'ore_calendario' => $row['ore_calendario'],
                    'gap_attivita' => $gap_attivita,
                    'gap_calendario' => $gap_calendario
                ])
            ];
        }
    }
    
    private function validateSovrapposizioni($data_inizio, $data_fine) {
        $sql = "SELECT 
            d.id as dipendente_id,
            d.nome,
            d.cognome,
            DATE(t1.data) as data,
            COUNT(*) as sovrapposizioni
        FROM dipendenti d
        JOIN timbrature t1 ON d.id = t1.dipendente_id
        JOIN timbrature t2 ON d.id = t2.dipendente_id 
            AND t1.id != t2.id
            AND DATE(t1.data) = DATE(t2.data)
            AND (
                (TIME(t1.ora_inizio) < TIME(t2.ora_fine) AND TIME(t1.ora_fine) > TIME(t2.ora_inizio))
                OR
                (TIME(t2.ora_inizio) < TIME(t1.ora_fine) AND TIME(t2.ora_fine) > TIME(t1.ora_inizio))
            )
        WHERE d.attivo = 1 
            AND t1.data BETWEEN :data_inizio AND :data_fine
        GROUP BY d.id, DATE(t1.data)
        HAVING sovrapposizioni > 0
        ORDER BY d.cognome, d.nome, data";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':data_inizio' => $data_inizio,
            ':data_fine' => $data_fine
        ]);
        
        $results = $stmt->fetchAll();
        
        foreach ($results as $row) {
            $this->anomalie[] = [
                'dipendente_id' => $row['dipendente_id'],
                'data' => $row['data'],
                'tipo_anomalia' => 'sovrapposizioni',
                'severita' => 'alta',
                'descrizione' => "Sovrapposizioni temporali rilevate: {$row['sovrapposizioni']} conflitti",
                'dettagli_json' => json_encode([
                    'sovrapposizioni_count' => $row['sovrapposizioni']
                ])
            ];
        }
    }
    
    private function validateTrasferte($data_inizio, $data_fine) {
        $sql = "SELECT 
            d.id as dipendente_id,
            d.nome,
            d.cognome,
            DATE(t.data) as data,
            c.nome as cliente_timbrature,
            ra.cliente_id as cliente_auto,
            c2.nome as cliente_auto_nome
        FROM dipendenti d
        JOIN timbrature t ON d.id = t.dipendente_id
        JOIN clienti c ON t.cliente_id = c.id
        LEFT JOIN registro_auto ra ON d.id = ra.dipendente_id 
            AND DATE(ra.data) = DATE(t.data)
        LEFT JOIN clienti c2 ON ra.cliente_id = c2.id
        WHERE d.attivo = 1 
            AND t.data BETWEEN :data_inizio AND :data_fine
            AND (
                (t.cliente_id IS NOT NULL AND ra.cliente_id IS NULL) OR
                (t.cliente_id IS NOT NULL AND ra.cliente_id IS NOT NULL AND t.cliente_id != ra.cliente_id)
            )
        ORDER BY d.cognome, d.nome, data";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':data_inizio' => $data_inizio,
            ':data_fine' => $data_fine
        ]);
        
        $results = $stmt->fetchAll();
        
        foreach ($results as $row) {
            $descrizione = "Incongruenza trasferta: ";
            if (empty($row['cliente_auto_nome'])) {
                $descrizione .= "Lavoro presso {$row['cliente_timbrature']} senza uso auto registrato";
            } else {
                $descrizione .= "Auto usata per {$row['cliente_auto_nome']} ma lavoro presso {$row['cliente_timbrature']}";
            }
            
            $this->anomalie[] = [
                'dipendente_id' => $row['dipendente_id'],
                'data' => $row['data'],
                'tipo_anomalia' => 'trasferte_incongruenti',
                'severita' => 'media',
                'descrizione' => $descrizione,
                'dettagli_json' => json_encode([
                    'cliente_timbrature' => $row['cliente_timbrature'],
                    'cliente_auto' => $row['cliente_auto_nome']
                ])
            ];
        }
    }
    
    private function validateSessioniRemote($data_inizio, $data_fine) {
        $sql = "SELECT 
            d.id as dipendente_id,
            d.nome,
            d.cognome,
            DATE(ts.data_inizio) as data,
            COUNT(ts.id) as sessioni_remote,
            COUNT(a.id) as attivita_corrispondenti
        FROM dipendenti d
        JOIN teamviewer_sessioni ts ON d.id = ts.dipendente_id
        LEFT JOIN attivita a ON d.id = a.dipendente_id 
            AND DATE(a.data_inizio) = DATE(ts.data_inizio)
            AND (
                a.descrizione LIKE '%remoto%' OR
                a.descrizione LIKE '%teamviewer%' OR
                a.descrizione LIKE '%remote%'
            )
        WHERE d.attivo = 1 
            AND DATE(ts.data_inizio) BETWEEN :data_inizio AND :data_fine
        GROUP BY d.id, DATE(ts.data_inizio)
        HAVING sessioni_remote > attivita_corrispondenti
        ORDER BY d.cognome, d.nome, data";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':data_inizio' => $data_inizio,
            ':data_fine' => $data_fine
        ]);
        
        $results = $stmt->fetchAll();
        
        foreach ($results as $row) {
            $this->anomalie[] = [
                'dipendente_id' => $row['dipendente_id'],
                'data' => $row['data'],
                'tipo_anomalia' => 'sessioni_orphan',
                'severita' => 'media',
                'descrizione' => "Sessioni remote senza attivit� corrispondenti: {$row['sessioni_remote']} sessioni vs {$row['attivita_corrispondenti']} attivit�",
                'dettagli_json' => json_encode([
                    'sessioni_remote' => $row['sessioni_remote'],
                    'attivita_corrispondenti' => $row['attivita_corrispondenti']
                ])
            ];
        }
    }
    
    private function validateOreSufficienti($data_inizio, $data_fine) {
        $sql = "SELECT 
            d.id as dipendente_id,
            d.nome,
            d.cognome,
            DATE(t.data) as data,
            SUM(t.ore_totali) as ore_totali,
            COUNT(ra.id) as assenze_giustificate
        FROM dipendenti d
        JOIN timbrature t ON d.id = t.dipendente_id
        LEFT JOIN richieste_assenze ra ON d.id = ra.dipendente_id 
            AND DATE(ra.data_inizio) <= DATE(t.data)
            AND DATE(ra.data_fine) >= DATE(t.data)
            AND ra.stato = 'approvata'
        WHERE d.attivo = 1 
            AND t.data BETWEEN :data_inizio AND :data_fine
        GROUP BY d.id, DATE(t.data)
        HAVING ore_totali < :ore_minime AND assenze_giustificate = 0
        ORDER BY d.cognome, d.nome, data";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':data_inizio' => $data_inizio,
            ':data_fine' => $data_fine,
            ':ore_minime' => $this->thresholds['alert_ore_minime']
        ]);
        
        $results = $stmt->fetchAll();
        
        foreach ($results as $row) {
            $this->anomalie[] = [
                'dipendente_id' => $row['dipendente_id'],
                'data' => $row['data'],
                'tipo_anomalia' => 'ore_insufficienti',
                'severita' => 'alta',
                'descrizione' => "Ore insufficienti: {$row['ore_totali']}h (minimo {$this->thresholds['alert_ore_minime']}h) senza assenze giustificate",
                'dettagli_json' => json_encode([
                    'ore_totali' => $row['ore_totali'],
                    'ore_minime_richieste' => $this->thresholds['alert_ore_minime']
                ])
            ];
        }
    }
    
    private function validateRapportiniMancanti($data_inizio, $data_fine) {
        $sql = "SELECT 
            d.id as dipendente_id,
            d.nome,
            d.cognome,
            DATE(t.data) as data,
            SUM(t.ore_totali) as ore_timbrature,
            COUNT(a.id) as attivita_count
        FROM dipendenti d
        JOIN timbrature t ON d.id = t.dipendente_id
        LEFT JOIN attivita a ON d.id = a.dipendente_id 
            AND DATE(a.data_inizio) = DATE(t.data)
        WHERE d.attivo = 1 
            AND t.data BETWEEN :data_inizio AND :data_fine
        GROUP BY d.id, DATE(t.data)
        HAVING ore_timbrature > 4 AND attivita_count = 0
        ORDER BY d.cognome, d.nome, data";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':data_inizio' => $data_inizio,
            ':data_fine' => $data_fine
        ]);
        
        $results = $stmt->fetchAll();
        
        foreach ($results as $row) {
            $this->anomalie[] = [
                'dipendente_id' => $row['dipendente_id'],
                'data' => $row['data'],
                'tipo_anomalia' => 'rapportini_mancanti',
                'severita' => 'alta',
                'descrizione' => "Rapportini mancanti: {$row['ore_timbrature']}h lavorate senza attivit� registrate",
                'dettagli_json' => json_encode([
                    'ore_timbrature' => $row['ore_timbrature'],
                    'attivita_count' => $row['attivita_count']
                ])
            ];
        }
    }
    
    private function saveAnomalieToDatabase() {
        $sql = "INSERT INTO anomalie (
            dipendente_id, data, tipo_anomalia, severita, descrizione, dettagli_json
        ) VALUES (
            :dipendente_id, :data, :tipo_anomalia, :severita, :descrizione, :dettagli_json
        ) ON DUPLICATE KEY UPDATE
            severita = VALUES(severita),
            descrizione = VALUES(descrizione),
            dettagli_json = VALUES(dettagli_json),
            updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($this->anomalie as $anomalia) {
            try {
                $stmt->execute($anomalia);
            } catch (PDOException $e) {
                error_log("Errore salvataggio anomalia: " . $e->getMessage());
            }
        }
    }
    
    public function getAnomalieByDipendente($dipendente_id, $data_inizio = null, $data_fine = null) {
        $sql = "SELECT * FROM anomalie 
                WHERE dipendente_id = :dipendente_id 
                AND risolto = 0";
        
        $params = [':dipendente_id' => $dipendente_id];
        
        if ($data_inizio) {
            $sql .= " AND data >= :data_inizio";
            $params[':data_inizio'] = $data_inizio;
        }
        
        if ($data_fine) {
            $sql .= " AND data <= :data_fine";
            $params[':data_fine'] = $data_fine;
        }
        
        $sql .= " ORDER BY data DESC, severita DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getAnomalieStats($data_inizio = null, $data_fine = null) {
        $sql = "SELECT 
            tipo_anomalia,
            severita,
            COUNT(*) as count,
            COUNT(CASE WHEN risolto = 0 THEN 1 END) as non_risolte
        FROM anomalie";
        
        $params = [];
        
        if ($data_inizio || $data_fine) {
            $sql .= " WHERE";
            $conditions = [];
            
            if ($data_inizio) {
                $conditions[] = " data >= :data_inizio";
                $params[':data_inizio'] = $data_inizio;
            }
            
            if ($data_fine) {
                $conditions[] = " data <= :data_fine";
                $params[':data_fine'] = $data_fine;
            }
            
            $sql .= implode(' AND', $conditions);
        }
        
        $sql .= " GROUP BY tipo_anomalia, severita 
                  ORDER BY severita DESC, tipo_anomalia";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function risolviAnomalia($anomalia_id, $note_risoluzione = null, $risolto_da = null) {
        $sql = "UPDATE anomalie 
                SET risolto = 1, 
                    note_risoluzione = :note_risoluzione,
                    risolto_da = :risolto_da,
                    risolto_il = CURRENT_TIMESTAMP
                WHERE id = :anomalia_id";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':anomalia_id' => $anomalia_id,
            ':note_risoluzione' => $note_risoluzione,
            ':risolto_da' => $risolto_da
        ]);
    }
    
    public function getCorrelationScore($dipendente_id, $data_inizio, $data_fine) {
        $sql = "SELECT 
            AVG(
                CASE 
                    WHEN ABS(ore_timbrature - ore_attivita) <= 1 AND ABS(ore_timbrature - ore_calendario) <= 1 THEN 100
                    WHEN ABS(ore_timbrature - ore_attivita) <= 2 OR ABS(ore_timbrature - ore_calendario) <= 2 THEN 75
                    WHEN ABS(ore_timbrature - ore_attivita) <= 3 OR ABS(ore_timbrature - ore_calendario) <= 3 THEN 50
                    ELSE 25
                END
            ) as correlation_score
        FROM kpi_giornalieri
        WHERE dipendente_id = :dipendente_id
            AND data BETWEEN :data_inizio AND :data_fine
            AND ore_timbrature > 0";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':dipendente_id' => $dipendente_id,
            ':data_inizio' => $data_inizio,
            ':data_fine' => $data_fine
        ]);
        
        $result = $stmt->fetch();
        return $result ? round($result['correlation_score'], 2) : 0;
    }
    
    public function getHealthScore($dipendente_id, $data_inizio, $data_fine) {
        $anomalie_count = count($this->getAnomalieByDipendente($dipendente_id, $data_inizio, $data_fine));
        $correlation_score = $this->getCorrelationScore($dipendente_id, $data_inizio, $data_fine);
        
        $health_score = 100 - ($anomalie_count * 5) - (100 - $correlation_score);
        
        return max(0, min(100, $health_score));
    }
    
    public function getAnomalieCount() {
        return count($this->anomalie);
    }
    
    public function getAnomalieDetails() {
        return $this->anomalie;
    }
}
?>