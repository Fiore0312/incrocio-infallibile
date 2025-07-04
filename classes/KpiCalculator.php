<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Configuration.php';

class KpiCalculator {
    private $conn;
    private $config;
    private $thresholds;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->config = new Configuration();
        $this->thresholds = $this->config->getKpiThresholds();
    }
    
    public function calculateAllKpis($data_inizio = null, $data_fine = null) {
        $data_inizio = $data_inizio ?? date('Y-m-d');
        $data_fine = $data_fine ?? date('Y-m-d');
        
        $dipendenti = $this->getDipendentiAttivi();
        $results = [];
        
        foreach ($dipendenti as $dipendente) {
            $current_date = $data_inizio;
            
            while ($current_date <= $data_fine) {
                $kpi = $this->calculateDailyKpi($dipendente['id'], $current_date);
                if ($kpi) {
                    $results[] = $kpi;
                    $this->saveKpiToDatabase($kpi);
                }
                
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            }
        }
        
        return $results;
    }
    
    public function calculateDailyKpi($dipendente_id, $data) {
        $kpi = [
            'dipendente_id' => $dipendente_id,
            'data' => $data,
            'ore_timbrature' => 0,
            'ore_attivita' => 0,
            'ore_calendario' => 0,
            'ore_fatturabili' => 0,
            'efficiency_rate' => 0,
            'costo_giornaliero' => 0,
            'ricavo_stimato' => 0,
            'profit_loss' => 0,
            'remote_sessions' => 0,
            'onsite_hours' => 0,
            'travel_hours' => 0,
            'vehicle_usage' => 0
        ];
        
        $kpi['ore_timbrature'] = $this->getOreTimbrature($dipendente_id, $data);
        $kpi['ore_attivita'] = $this->getOreAttivita($dipendente_id, $data);
        $kpi['ore_calendario'] = $this->getOreCalendario($dipendente_id, $data);
        $kpi['ore_fatturabili'] = $this->getOreFatturabili($dipendente_id, $data);
        $kpi['efficiency_rate'] = $this->calculateEfficiencyRate($kpi['ore_fatturabili'], $kpi['ore_timbrature'], $kpi['ore_attivita']);
        $kpi['costo_giornaliero'] = $this->config->getCostoGiornaliero($dipendente_id);
        $kpi['ricavo_stimato'] = $this->calculateRicavoStimato($kpi['ore_fatturabili']);
        $kpi['profit_loss'] = $kpi['ricavo_stimato'] - $kpi['costo_giornaliero'];
        $kpi['remote_sessions'] = $this->getRemoteSessions($dipendente_id, $data);
        $kpi['onsite_hours'] = $this->getOnsiteHours($dipendente_id, $data);
        $kpi['travel_hours'] = $this->getTravelHours($dipendente_id, $data);
        $kpi['vehicle_usage'] = $this->getVehicleUsage($dipendente_id, $data);
        
        // Aggiungi validazione incrociata e alert
        $kpi['validation_alerts'] = $this->validateDataConsistency($kpi, $dipendente_id, $data);
        
        return $kpi;
    }
    
    private function getOreTimbrature($dipendente_id, $data) {
        $sql = "SELECT COALESCE(SUM(ore_totali), 0) as ore_totali 
                FROM timbrature 
                WHERE dipendente_id = :dipendente_id AND data = :data";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':dipendente_id' => $dipendente_id, ':data' => $data]);
        $result = $stmt->fetch();
        
        return (float) $result['ore_totali'];
    }
    
    private function getOreAttivita($dipendente_id, $data) {
        // Query che gestisce correttamente le attività multi-giorno
        // proporzionando le ore per ogni giorno interessato
        $sql = "SELECT COALESCE(SUM(
                    CASE 
                        WHEN DATE(data_inizio) = DATE(data_fine) AND DATE(data_inizio) = :data THEN 
                            durata_ore
                        WHEN DATE(data_inizio) <> DATE(data_fine) AND :data BETWEEN DATE(data_inizio) AND DATE(data_fine) THEN
                            CASE 
                                WHEN DATE(data_inizio) = :data THEN
                                    -- Primo giorno: ore dalla data_inizio alla fine del giorno
                                    TIMESTAMPDIFF(MINUTE, data_inizio, DATE_ADD(DATE(data_inizio), INTERVAL 1 DAY)) / 60
                                WHEN DATE(data_fine) = :data THEN
                                    -- Ultimo giorno: ore dall'inizio del giorno alla data_fine
                                    TIMESTAMPDIFF(MINUTE, DATE(:data), data_fine) / 60
                                ELSE
                                    -- Giorni intermedi: 24 ore complete
                                    24
                            END
                        ELSE 
                            0
                    END
                ), 0) as ore_attivita 
                FROM attivita 
                WHERE dipendente_id = :dipendente_id 
                    AND (:data BETWEEN DATE(data_inizio) AND DATE(data_fine))";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':dipendente_id' => $dipendente_id, ':data' => $data]);
        $result = $stmt->fetch();
        
        return (float) $result['ore_attivita'];
    }
    
    private function getOreCalendario($dipendente_id, $data) {
        // Query che gestisce correttamente gli appuntamenti calendario multi-giorno
        $sql = "SELECT COALESCE(SUM(
                    CASE 
                        WHEN DATE(data_inizio) = DATE(data_fine) AND DATE(data_inizio) = :data THEN 
                            TIMESTAMPDIFF(MINUTE, data_inizio, data_fine) / 60
                        WHEN DATE(data_inizio) <> DATE(data_fine) AND :data BETWEEN DATE(data_inizio) AND DATE(data_fine) THEN
                            CASE 
                                WHEN DATE(data_inizio) = :data THEN
                                    -- Primo giorno: dalla data_inizio alla fine del giorno
                                    TIMESTAMPDIFF(MINUTE, data_inizio, DATE_ADD(DATE(data_inizio), INTERVAL 1 DAY)) / 60
                                WHEN DATE(data_fine) = :data THEN
                                    -- Ultimo giorno: dall'inizio del giorno alla data_fine
                                    TIMESTAMPDIFF(MINUTE, DATE(:data), data_fine) / 60
                                ELSE
                                    -- Giorni intermedi: 24 ore complete
                                    24
                            END
                        ELSE 
                            0
                    END
                ), 0) as ore_calendario 
                FROM calendario 
                WHERE dipendente_id = :dipendente_id 
                    AND (:data BETWEEN DATE(data_inizio) AND DATE(data_fine))";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':dipendente_id' => $dipendente_id, ':data' => $data]);
        $result = $stmt->fetch();
        
        return (float) $result['ore_calendario'];
    }
    
    private function getOreFatturabili($dipendente_id, $data) {
        // Query che gestisce correttamente le attività fatturabili multi-giorno
        $sql = "SELECT COALESCE(SUM(
                    CASE 
                        WHEN DATE(data_inizio) = DATE(data_fine) AND DATE(data_inizio) = :data THEN 
                            durata_ore
                        WHEN DATE(data_inizio) <> DATE(data_fine) AND :data BETWEEN DATE(data_inizio) AND DATE(data_fine) THEN
                            CASE 
                                WHEN DATE(data_inizio) = :data THEN
                                    -- Primo giorno: proporziona le ore dalla data_inizio alla fine del giorno
                                    (TIMESTAMPDIFF(MINUTE, data_inizio, DATE_ADD(DATE(data_inizio), INTERVAL 1 DAY)) / 60) 
                                    * (durata_ore / TIMESTAMPDIFF(MINUTE, data_inizio, data_fine) * 60)
                                WHEN DATE(data_fine) = :data THEN
                                    -- Ultimo giorno: proporziona le ore dall'inizio del giorno alla data_fine
                                    (TIMESTAMPDIFF(MINUTE, DATE(:data), data_fine) / 60)
                                    * (durata_ore / TIMESTAMPDIFF(MINUTE, data_inizio, data_fine) * 60)
                                ELSE
                                    -- Giorni intermedi: proporziona 24 ore del totale
                                    (24 * durata_ore) / TIMESTAMPDIFF(MINUTE, data_inizio, data_fine) * 60
                            END
                        ELSE 
                            0
                    END
                ), 0) as ore_fatturabili 
                FROM attivita 
                WHERE dipendente_id = :dipendente_id 
                    AND (:data BETWEEN DATE(data_inizio) AND DATE(data_fine))
                    AND fatturabile = 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':dipendente_id' => $dipendente_id, ':data' => $data]);
        $result = $stmt->fetch();
        
        return (float) $result['ore_fatturabili'];
    }
    
    private function calculateEfficiencyRate($ore_fatturabili, $ore_timbrature, $ore_attivita) {
        // Determina le ore effettive lavorate usando la fonte più affidabile
        $ore_effettive = $this->determineOreEffettive($ore_timbrature, $ore_attivita);
        
        // Se non ci sono ore effettive, usa il valore di default per evitare divisione per zero
        if ($ore_effettive <= 0) {
            $ore_effettive = $this->thresholds['ore_lavorative_giornaliere'];
        }
        
        // Calcola efficiency rate basato sulle ore effettive
        $efficiency = ($ore_fatturabili / $ore_effettive) * 100;
        
        // Limita il risultato a un massimo ragionevole (non oltre 150%)
        return min($efficiency, 150.0);
    }
    
    private function determineOreEffettive($ore_timbrature, $ore_attivita) {
        // Se entrambe le fonti hanno dati, usa quella più alta (più conservativa)
        if ($ore_timbrature > 0 && $ore_attivita > 0) {
            return max($ore_timbrature, $ore_attivita);
        }
        
        // Se solo timbrature disponibili, usale
        if ($ore_timbrature > 0) {
            return $ore_timbrature;
        }
        
        // Se solo attività disponibili, usale  
        if ($ore_attivita > 0) {
            return $ore_attivita;
        }
        
        // Nessun dato disponibile
        return 0;
    }
    
    private function calculateRicavoStimato($ore_fatturabili) {
        $tariffa_oraria = $this->thresholds['tariffa_oraria_standard'];
        return $ore_fatturabili * $tariffa_oraria;
    }
    
    private function getRemoteSessions($dipendente_id, $data) {
        $sql = "SELECT COUNT(*) as remote_sessions 
                FROM teamviewer_sessioni 
                WHERE dipendente_id = :dipendente_id AND DATE(data_inizio) = :data";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':dipendente_id' => $dipendente_id, ':data' => $data]);
        $result = $stmt->fetch();
        
        return (int) $result['remote_sessions'];
    }
    
    private function getOnsiteHours($dipendente_id, $data) {
        $sql = "SELECT COALESCE(SUM(t.ore_totali), 0) as onsite_hours 
                FROM timbrature t
                JOIN clienti c ON t.cliente_id = c.id
                WHERE t.dipendente_id = :dipendente_id 
                    AND t.data = :data 
                    AND t.cliente_id IS NOT NULL";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':dipendente_id' => $dipendente_id, ':data' => $data]);
        $result = $stmt->fetch();
        
        return (float) $result['onsite_hours'];
    }
    
    private function getTravelHours($dipendente_id, $data) {
        $sql = "SELECT COALESCE(SUM(
                    CASE 
                        WHEN ra.ora_riconsegna IS NOT NULL 
                        THEN TIMESTAMPDIFF(MINUTE, ra.ora_presa, ra.ora_riconsegna) / 60
                        ELSE 0 
                    END
                ), 0) as travel_hours 
                FROM registro_auto ra
                WHERE ra.dipendente_id = :dipendente_id AND ra.data = :data";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':dipendente_id' => $dipendente_id, ':data' => $data]);
        $result = $stmt->fetch();
        
        return (float) $result['travel_hours'];
    }
    
    private function getVehicleUsage($dipendente_id, $data) {
        $sql = "SELECT COUNT(*) as vehicle_usage 
                FROM registro_auto 
                WHERE dipendente_id = :dipendente_id AND data = :data";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':dipendente_id' => $dipendente_id, ':data' => $data]);
        $result = $stmt->fetch();
        
        return (int) $result['vehicle_usage'] > 0 ? 1 : 0;
    }
    
    private function saveKpiToDatabase($kpi) {
        $sql = "INSERT INTO kpi_giornalieri (
            dipendente_id, data, ore_timbrature, ore_attivita, ore_calendario, ore_fatturabili,
            efficiency_rate, costo_giornaliero, ricavo_stimato, profit_loss, remote_sessions,
            onsite_hours, travel_hours, vehicle_usage, validation_alerts
        ) VALUES (
            :dipendente_id, :data, :ore_timbrature, :ore_attivita, :ore_calendario, :ore_fatturabili,
            :efficiency_rate, :costo_giornaliero, :ricavo_stimato, :profit_loss, :remote_sessions,
            :onsite_hours, :travel_hours, :vehicle_usage, :validation_alerts
        ) ON DUPLICATE KEY UPDATE
            ore_timbrature = VALUES(ore_timbrature),
            ore_attivita = VALUES(ore_attivita),
            ore_calendario = VALUES(ore_calendario),
            ore_fatturabili = VALUES(ore_fatturabili),
            efficiency_rate = VALUES(efficiency_rate),
            costo_giornaliero = VALUES(costo_giornaliero),
            ricavo_stimato = VALUES(ricavo_stimato),
            profit_loss = VALUES(profit_loss),
            remote_sessions = VALUES(remote_sessions),
            onsite_hours = VALUES(onsite_hours),
            travel_hours = VALUES(travel_hours),
            vehicle_usage = VALUES(vehicle_usage),
            validation_alerts = VALUES(validation_alerts),
            updated_at = CURRENT_TIMESTAMP";
        
        // Prepara i dati per il salvataggio
        $params = [
            ':dipendente_id' => $kpi['dipendente_id'],
            ':data' => $kpi['data'],
            ':ore_timbrature' => $kpi['ore_timbrature'],
            ':ore_attivita' => $kpi['ore_attivita'], 
            ':ore_calendario' => $kpi['ore_calendario'],
            ':ore_fatturabili' => $kpi['ore_fatturabili'],
            ':efficiency_rate' => $kpi['efficiency_rate'],
            ':costo_giornaliero' => $kpi['costo_giornaliero'],
            ':ricavo_stimato' => $kpi['ricavo_stimato'],
            ':profit_loss' => $kpi['profit_loss'],
            ':remote_sessions' => $kpi['remote_sessions'],
            ':onsite_hours' => $kpi['onsite_hours'],
            ':travel_hours' => $kpi['travel_hours'],
            ':vehicle_usage' => $kpi['vehicle_usage'],
            ':validation_alerts' => !empty($kpi['validation_alerts']) ? json_encode($kpi['validation_alerts']) : null
        ];
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }
    
    private function getDipendentiAttivi() {
        $sql = "SELECT id, nome, cognome FROM dipendenti WHERE attivo = 1 ORDER BY cognome, nome";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getKpiSummary($dipendente_id = null, $data_inizio = null, $data_fine = null) {
        $sql = "SELECT 
            COALESCE(AVG(efficiency_rate), 0) as avg_efficiency_rate,
            COALESCE(SUM(profit_loss), 0) as total_profit_loss,
            COUNT(*) as giorni_lavorativi,
            COALESCE(SUM(ore_fatturabili), 0) as totale_ore_fatturabili,
            COALESCE(SUM(remote_sessions), 0) as totale_sessioni_remote,
            COALESCE(SUM(vehicle_usage), 0) as giorni_uso_veicoli,
            COALESCE(AVG(onsite_hours), 0) as avg_onsite_hours,
            COALESCE(AVG(travel_hours), 0) as avg_travel_hours
        FROM kpi_giornalieri";
        
        $params = [];
        $conditions = [];
        
        if ($dipendente_id) {
            $conditions[] = "dipendente_id = :dipendente_id";
            $params[':dipendente_id'] = $dipendente_id;
        }
        
        if ($data_inizio) {
            $conditions[] = "data >= :data_inizio";
            $params[':data_inizio'] = $data_inizio;
        }
        
        if ($data_fine) {
            $conditions[] = "data <= :data_fine";
            $params[':data_fine'] = $data_fine;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        if ($result) {
            $result['remote_vs_onsite_ratio'] = $result['totale_sessioni_remote'] > 0 && $result['avg_onsite_hours'] > 0 
                ? round($result['totale_sessioni_remote'] / $result['avg_onsite_hours'], 2)
                : 0;
                
            // Assicuriamo che tutti i valori siano numerici
            foreach ($result as $key => $value) {
                if (is_numeric($value)) {
                    $result[$key] = (float) $value;
                }
            }
        }
        
        return $result;
    }
    
    public function getClientProfitability($data_inizio = null, $data_fine = null) {
        $sql = "SELECT 
            c.id,
            c.nome as cliente_nome,
            COUNT(DISTINCT k.dipendente_id) as dipendenti_coinvolti,
            SUM(k.ore_fatturabili) as ore_fatturabili_totali,
            SUM(k.ricavo_stimato) as ricavo_stimato_totale,
            SUM(k.costo_giornaliero) as costo_totale,
            SUM(k.profit_loss) as profit_loss_totale,
            AVG(k.efficiency_rate) as avg_efficiency_rate
        FROM clienti c
        JOIN timbrature t ON c.id = t.cliente_id
        JOIN kpi_giornalieri k ON t.dipendente_id = k.dipendente_id AND t.data = k.data";
        
        $params = [];
        $conditions = [];
        
        if ($data_inizio) {
            $conditions[] = "k.data >= :data_inizio";
            $params[':data_inizio'] = $data_inizio;
        }
        
        if ($data_fine) {
            $conditions[] = "k.data <= :data_fine";
            $params[':data_fine'] = $data_fine;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql .= " GROUP BY c.id, c.nome 
                  ORDER BY profit_loss_totale DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getVehicleEfficiency($data_inizio = null, $data_fine = null) {
        $sql = "SELECT 
            v.id,
            v.nome as veicolo_nome,
            COUNT(DISTINCT ra.dipendente_id) as dipendenti_utilizzatori,
            COUNT(ra.id) as utilizzi_totali,
            AVG(CASE 
                WHEN ra.ora_riconsegna IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, ra.ora_presa, ra.ora_riconsegna) / 60
                ELSE 0 
            END) as avg_ore_utilizzo,
            SUM(CASE 
                WHEN ra.km_totali IS NOT NULL 
                THEN ra.km_totali * v.costo_km
                ELSE 0 
            END) as costo_totale_stimato
        FROM veicoli v
        LEFT JOIN registro_auto ra ON v.id = ra.veicolo_id";
        
        $params = [];
        $conditions = [];
        
        if ($data_inizio) {
            $conditions[] = "ra.data >= :data_inizio";
            $params[':data_inizio'] = $data_inizio;
        }
        
        if ($data_fine) {
            $conditions[] = "ra.data <= :data_fine";
            $params[':data_fine'] = $data_fine;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql .= " GROUP BY v.id, v.nome 
                  ORDER BY utilizzi_totali DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getMonthlyTrends($mesi = 6) {
        $sql = "SELECT 
            YEAR(data) as anno,
            MONTH(data) as mese,
            COUNT(DISTINCT dipendente_id) as dipendenti_attivi,
            AVG(efficiency_rate) as avg_efficiency_rate,
            SUM(profit_loss) as profit_loss_totale,
            SUM(ore_fatturabili) as ore_fatturabili_totali,
            SUM(remote_sessions) as sessioni_remote_totali,
            AVG(onsite_hours / (onsite_hours + travel_hours) * 100) as onsite_percentage
        FROM kpi_giornalieri
        WHERE data >= DATE_SUB(CURDATE(), INTERVAL :mesi MONTH)
        GROUP BY YEAR(data), MONTH(data)
        ORDER BY anno DESC, mese DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':mesi' => $mesi]);
        
        return $stmt->fetchAll();
    }
    
    public function getTopPerformers($data_inizio = null, $data_fine = null, $limit = 10) {
        // Validazione del limite
        $limit = max(1, min(100, (int)$limit));
        $sql = "SELECT 
            d.id,
            d.nome,
            d.cognome,
            AVG(k.efficiency_rate) as avg_efficiency_rate,
            SUM(k.profit_loss) as profit_loss_totale,
            SUM(k.ore_fatturabili) as ore_fatturabili_totali,
            COUNT(k.data) as giorni_lavorativi,
            SUM(k.remote_sessions) as sessioni_remote_totali
        FROM dipendenti d
        JOIN kpi_giornalieri k ON d.id = k.dipendente_id";
        
        $params = [];
        $conditions = [];
        
        if ($data_inizio) {
            $conditions[] = "k.data >= :data_inizio";
            $params[':data_inizio'] = $data_inizio;
        }
        
        if ($data_fine) {
            $conditions[] = "k.data <= :data_fine";
            $params[':data_fine'] = $data_fine;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql .= " GROUP BY d.id, d.nome, d.cognome 
                  ORDER BY profit_loss_totale DESC, avg_efficiency_rate DESC 
                  LIMIT " . (int)$limit;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getAlertsCount($data_inizio = null, $data_fine = null) {
        $sql = "SELECT 
            COUNT(CASE WHEN efficiency_rate < :warning_threshold THEN 1 END) as efficiency_warnings,
            COUNT(CASE WHEN efficiency_rate < :critical_threshold THEN 1 END) as efficiency_critical,
            COUNT(CASE WHEN profit_loss < :profit_warning THEN 1 END) as profit_warnings,
            COUNT(CASE WHEN ore_fatturabili < :ore_minime THEN 1 END) as ore_insufficienti
        FROM kpi_giornalieri";
        
        $params = [
            ':warning_threshold' => $this->config->get('efficiency_threshold_warning', 70),
            ':critical_threshold' => $this->config->get('efficiency_threshold_critical', 50),
            ':profit_warning' => $this->config->get('profit_threshold_warning', -20),
            ':ore_minime' => $this->thresholds['alert_ore_minime']
        ];
        
        $conditions = [];
        
        if ($data_inizio) {
            $conditions[] = "data >= :data_inizio";
            $params[':data_inizio'] = $data_inizio;
        }
        
        if ($data_fine) {
            $conditions[] = "data <= :data_fine";
            $params[':data_fine'] = $data_fine;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch();
    }
    
    public function recalculateAllKpis($force = false) {
        if ($force) {
            $this->conn->exec("TRUNCATE TABLE kpi_giornalieri");
        }
        
        $sql = "SELECT MIN(data) as min_data, MAX(data) as max_data FROM timbrature";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result && $result['min_data'] && $result['max_data']) {
            return $this->calculateAllKpis($result['min_data'], $result['max_data']);
        }
        
        return [];
    }
    
    private function validateDataConsistency($kpi, $dipendente_id, $data) {
        $alerts = [];
        
        // 1. Controllo: ore fatturabili non possono superare ore timbrature
        if ($kpi['ore_fatturabili'] > $kpi['ore_timbrature'] && $kpi['ore_timbrature'] > 0) {
            $diff = $kpi['ore_fatturabili'] - $kpi['ore_timbrature'];
            $alerts[] = [
                'type' => 'CRITICAL',
                'code' => 'FATTURABILI_EXCEED_TIMBRATURE',
                'message' => "Ore fatturabili ({$kpi['ore_fatturabili']}h) superano timbrature ({$kpi['ore_timbrature']}h) di {$diff}h",
                'dipendente_id' => $dipendente_id,
                'data' => $data
            ];
        }
        
        // 2. Controllo: discrepanza significativa tra ore attività e timbrature
        if ($kpi['ore_timbrature'] > 0 && $kpi['ore_attivita'] > 0) {
            $discrepanza_perc = abs($kpi['ore_attivita'] - $kpi['ore_timbrature']) / $kpi['ore_timbrature'] * 100;
            if ($discrepanza_perc > 25) { // Alert se differenza > 25%
                $alerts[] = [
                    'type' => 'WARNING',
                    'code' => 'TIMBRATURE_ATTIVITA_MISMATCH',
                    'message' => "Discrepanza {$discrepanza_perc}% tra timbrature ({$kpi['ore_timbrature']}h) e attività ({$kpi['ore_attivita']}h)",
                    'dipendente_id' => $dipendente_id,
                    'data' => $data
                ];
            }
        }
        
        // 3. Controllo: ore calendario molto superiori a timbrature (presenza mancante)
        if ($kpi['ore_calendario'] > 0 && $kpi['ore_timbrature'] == 0) {
            $alerts[] = [
                'type' => 'WARNING', 
                'code' => 'MISSING_TIMESHEET',
                'message' => "Appuntamenti calendario ({$kpi['ore_calendario']}h) ma nessuna timbratura",
                'dipendente_id' => $dipendente_id,
                'data' => $data
            ];
        }
        
        // 4. Controllo: efficiency rate molto alta (possibile errore dati)
        if ($kpi['efficiency_rate'] > 120) {
            $alerts[] = [
                'type' => 'INFO',
                'code' => 'HIGH_EFFICIENCY',
                'message' => "Efficiency rate molto alta ({$kpi['efficiency_rate']}%)",
                'dipendente_id' => $dipendente_id,
                'data' => $data
            ];
        }
        
        // 5. Controllo: ore fatturabili senza timbrature
        if ($kpi['ore_fatturabili'] > 0 && $kpi['ore_timbrature'] == 0) {
            $alerts[] = [
                'type' => 'WARNING',
                'code' => 'BILLABLE_WITHOUT_TIMESHEET',
                'message' => "Ore fatturabili ({$kpi['ore_fatturabili']}h) senza timbrature",
                'dipendente_id' => $dipendente_id,
                'data' => $data
            ];
        }
        
        // Salva gli alert nel database se presenti
        if (!empty($alerts)) {
            $this->saveValidationAlerts($alerts);
        }
        
        return $alerts;
    }
    
    private function saveValidationAlerts($alerts) {
        foreach ($alerts as $alert) {
            try {
                $sql = "INSERT INTO anomalie (
                    dipendente_id, data, tipo_anomalia, descrizione, severita
                ) VALUES (
                    :dipendente_id, :data, :tipo, :descrizione, :severita
                ) ON DUPLICATE KEY UPDATE 
                    descrizione = :descrizione,
                    severita = :severita";
                
                $stmt = $this->conn->prepare($sql);
                // Mappa i tipi di alert ai valori enum della tabella
                $severita_mapping = [
                    'CRITICAL' => 'critica',
                    'WARNING' => 'alta', 
                    'INFO' => 'media'
                ];
                
                $stmt->execute([
                    ':dipendente_id' => $alert['dipendente_id'],
                    ':data' => $alert['data'],
                    ':tipo' => $alert['code'],
                    ':descrizione' => $alert['message'],
                    ':severita' => $severita_mapping[$alert['type']] ?? 'media'
                ]);
            } catch (Exception $e) {
                // Log dell'errore ma continua l'elaborazione
                error_log("Errore salvando alert di validazione: " . $e->getMessage());
            }
        }
    }
}
?>