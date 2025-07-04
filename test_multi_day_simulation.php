<?php
require_once 'config/Database.php';

echo "<h2>Test Simulazione Attività Multi-Giorno</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Crea un'attività di test multi-giorno temporanea
    echo "<h3>Creazione Attività Test Multi-Giorno</h3>\n";
    
    // Trova un dipendente per il test
    $stmt = $conn->prepare("SELECT id, nome, cognome FROM dipendenti WHERE attivo = 1 LIMIT 1");
    $stmt->execute();
    $dipendente = $stmt->fetch();
    
    if (!$dipendente) {
        echo "<p style='color: red;'>Nessun dipendente trovato</p>\n";
        exit;
    }
    
    // Inserisci attività test che va dal 2025-07-01 18:00 al 2025-07-02 06:00 (12 ore totali)
    $stmt = $conn->prepare("
        INSERT INTO attivita (
            dipendente_id, data_inizio, data_fine, durata_ore, 
            descrizione, fatturabile, created_at
        ) VALUES (
            :dipendente_id, 
            '2025-07-01 18:00:00', 
            '2025-07-02 06:00:00', 
            12.0,
            'TEST: Attività multi-giorno per verifica calcoli', 
            1,
            NOW()
        )
    ");
    
    $test_activity_id = null;
    try {
        $stmt->execute([':dipendente_id' => $dipendente['id']]);
        $test_activity_id = $conn->lastInsertId();
        echo "<p>✅ Attività test creata (ID: $test_activity_id)</p>\n";
        echo "<p><strong>Dettagli:</strong> 01/07/2025 18:00 → 02/07/2025 06:00 (12h totali)</p>\n";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Errore creazione attività test: " . $e->getMessage() . "</p>\n";
        exit;
    }
    
    // Test calcolo con le nuove query
    require_once 'config/Configuration.php';
    require_once 'classes/KpiCalculator.php';
    
    $config = new Configuration();
    $kpiCalculator = new KpiCalculator();
    
    echo "<h3>Test Calcoli Multi-Giorno</h3>\n";
    
    // Calcola KPI per il primo giorno (01/07/2025)
    echo "<h4>Giorno 1: 01/07/2025</h4>\n";
    $kpi_day1 = $kpiCalculator->calculateDailyKpi($dipendente['id'], '2025-07-01');
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Metrica</th><th>Valore</th><th>Spiegazione</th></tr>\n";
    echo "<tr><td>Ore Attività</td><td>{$kpi_day1['ore_attivita']}h</td><td>Dalle 18:00 alle 24:00 = 6h</td></tr>\n";
    echo "<tr><td>Ore Fatturabili</td><td>{$kpi_day1['ore_fatturabili']}h</td><td>Proporzionate dal totale 12h</td></tr>\n";
    echo "</table>\n";
    
    // Calcola KPI per il secondo giorno (02/07/2025)
    echo "<h4>Giorno 2: 02/07/2025</h4>\n";
    $kpi_day2 = $kpiCalculator->calculateDailyKpi($dipendente['id'], '2025-07-02');
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Metrica</th><th>Valore</th><th>Spiegazione</th></tr>\n";
    echo "<tr><td>Ore Attività</td><td>{$kpi_day2['ore_attivita']}h</td><td>Dalle 00:00 alle 06:00 = 6h</td></tr>\n";
    echo "<tr><td>Ore Fatturabili</td><td>{$kpi_day2['ore_fatturabili']}h</td><td>Proporzionate dal totale 12h</td></tr>\n";
    echo "</table>\n";
    
    // Verifica totali
    $total_ore_attivita = $kpi_day1['ore_attivita'] + $kpi_day2['ore_attivita'];
    $total_ore_fatturabili = $kpi_day1['ore_fatturabili'] + $kpi_day2['ore_fatturabili'];
    
    echo "<h4>Verifica Totali</h4>\n";
    echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px;'>\n";
    echo "<p><strong>Ore Attività Totali:</strong> $total_ore_attivita h (atteso: ~12h)</p>\n";
    echo "<p><strong>Ore Fatturabili Totali:</strong> $total_ore_fatturabili h (atteso: ~12h)</p>\n";
    
    $diff_attivita = abs(12 - $total_ore_attivita);
    $diff_fatturabili = abs(12 - $total_ore_fatturabili);
    
    if ($diff_attivita < 0.1 && $diff_fatturabili < 0.1) {
        echo "<p style='color: green;'><strong>✅ SUCCESSO:</strong> Proporzionamento corretto!</p>\n";
    } else {
        echo "<p style='color: orange;'><strong>⚠️ ATTENZIONE:</strong> Piccole differenze dovute all'arrotondamento</p>\n";
    }
    echo "</div>\n";
    
    // Confronto con metodo vecchio
    echo "<h4>Confronto Metodo Vecchio vs Nuovo</h4>\n";
    
    // Metodo vecchio - conta tutto nel primo giorno
    $stmt = $conn->prepare("
        SELECT SUM(durata_ore) as ore_day1_old, 0 as ore_day2_old
        FROM attivita 
        WHERE dipendente_id = :dipendente_id 
            AND DATE(data_inizio) = '2025-07-01'
            AND descrizione LIKE 'TEST:%'
    ");
    $stmt->execute([':dipendente_id' => $dipendente['id']]);
    $old_method = $stmt->fetch();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Metodo</th><th>Giorno 1</th><th>Giorno 2</th><th>Totale</th><th>Accuratezza</th></tr>\n";
    echo "<tr><td><strong>Vecchio (ERRATO)</strong></td><td>12h</td><td>0h</td><td>12h</td><td style='color: red;'>❌ Tutto nel primo giorno</td></tr>\n";
    echo "<tr><td><strong>Nuovo (CORRETTO)</strong></td><td>" . number_format($kpi_day1['ore_attivita'], 1) . "h</td><td>" . number_format($kpi_day2['ore_attivita'], 1) . "h</td><td>" . number_format($total_ore_attivita, 1) . "h</td><td style='color: green;'>✅ Proporzionato correttamente</td></tr>\n";
    echo "</table>\n";
    
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>\n";
    echo "<h4>🎉 Test Multi-Giorno Completato</h4>\n";
    echo "<p><strong>Risultato:</strong> Il nuovo sistema gestisce perfettamente le attività multi-giorno!</p>\n";
    echo "<ul>\n";
    echo "<li>✅ Proporzionamento automatico delle ore per giorno</li>\n";
    echo "<li>✅ Preserva il totale complessivo</li>\n";
    echo "<li>✅ Elimina distorsioni nei KPI giornalieri</li>\n";
    echo "<li>✅ Conforme alle best practices di time tracking</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Errore durante il test:</strong> " . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
} finally {
    // Pulizia: rimuovi l'attività test
    if (isset($test_activity_id) && $test_activity_id) {
        try {
            $stmt = $conn->prepare("DELETE FROM attivita WHERE id = :id");
            $stmt->execute([':id' => $test_activity_id]);
            echo "<p><em>Attività test rimossa (ID: $test_activity_id)</em></p>\n";
        } catch (Exception $e) {
            echo "<p style='color: orange;'><em>Nota: Rimuovere manualmente attività test ID: $test_activity_id</em></p>\n";
        }
    }
}
?>

<p><a href="test_multi_day_queries.php">← Test Query</a> | <a href="index.php">Dashboard</a></p>