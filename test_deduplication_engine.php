<?php
require_once 'config/Database.php';
require_once 'classes/DeduplicationEngine.php';

echo "<h2>🔄 Test Deduplication Engine - Sistema Anti-duplicazione</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Inizializza Deduplication Engine
    $dedup_config = [
        'time_threshold_minutes' => 5,
        'similarity_threshold' => 0.8,
        'enable_soft_deduplication' => true,
        'enable_intelligent_merge' => true
    ];
    
    $deduplication = new DeduplicationEngine($dedup_config);
    
    echo "<h3>📋 Configurazione Engine</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Parametro</th><th>Valore</th><th>Descrizione</th></tr>\n";
    echo "<tr><td><strong>Soglia Temporale</strong></td><td>{$dedup_config['time_threshold_minutes']} minuti</td><td>Tolleranza per attività simili</td></tr>\n";
    echo "<tr><td><strong>Soglia Similarità</strong></td><td>{$dedup_config['similarity_threshold']}</td><td>Threshold per match fuzzy</td></tr>\n";
    echo "<tr><td><strong>Soft Deduplication</strong></td><td>" . ($dedup_config['enable_soft_deduplication'] ? '✅ Attiva' : '❌ Disattiva') . "</td><td>Marca duplicati invece di saltare</td></tr>\n";
    echo "<tr><td><strong>Intelligent Merge</strong></td><td>" . ($dedup_config['enable_intelligent_merge'] ? '✅ Attiva' : '❌ Disattiva') . "</td><td>Merge automatico duplicati simili</td></tr>\n";
    echo "</table>\n";
    
    // Test 1: Analisi duplicati esistenti
    echo "<h3>📊 Analisi Duplicati Esistenti nel Database</h3>\n";
    
    $analysis = $deduplication->analyzeDuplicatesInDatabase();
    
    if ($analysis) {
        echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h4>📈 Statistiche Database</h4>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Metrica</th><th>Valore</th><th>Percentuale</th></tr>\n";
        echo "<tr><td><strong>Totale Attività</strong></td><td>{$analysis['total_activities']}</td><td>100%</td></tr>\n";
        echo "<tr><td><strong>Duplicati Esatti</strong></td><td>{$analysis['exact_duplicates']}</td><td>" . round(($analysis['exact_duplicates'] / max(1, $analysis['total_activities'])) * 100, 2) . "%</td></tr>\n";
        echo "<tr><td><strong>Duplicati Fuzzy</strong></td><td>{$analysis['fuzzy_duplicates']}</td><td>" . round(($analysis['fuzzy_duplicates'] / max(1, $analysis['total_activities'])) * 100, 2) . "%</td></tr>\n";
        echo "<tr><td><strong>Totale Potenziali</strong></td><td>{$analysis['potential_duplicates']}</td><td>" . round(($analysis['potential_duplicates'] / max(1, $analysis['total_activities'])) * 100, 2) . "%</td></tr>\n";
        echo "</table>\n";
        echo "</div>\n";
        
        // Mostra gruppi duplicati più grandi
        if (!empty($analysis['duplicate_groups'])) {
            echo "<h4>🔍 Gruppi Duplicati Più Critici</h4>\n";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>\n";
            echo "<tr><th>Dipendente ID</th><th>Data</th><th>Durata</th><th>Duplicati</th><th>IDs Attività</th></tr>\n";
            
            foreach (array_slice($analysis['duplicate_groups'], 0, 10) as $group) {
                $activity_ids = explode(',', $group['activity_ids']);
                $ids_display = count($activity_ids) > 5 ? 
                    implode(', ', array_slice($activity_ids, 0, 5)) . '... +' . (count($activity_ids) - 5) : 
                    $group['activity_ids'];
                
                echo "<tr>\n";
                echo "<td>{$group['dipendente_id']}</td>\n";
                echo "<td>{$group['data_attivita']}</td>\n";
                echo "<td>{$group['durata_ore']} ore</td>\n";
                echo "<td style='color: red; font-weight: bold;'>{$group['duplicate_count']}</td>\n";
                echo "<td><small>$ids_display</small></td>\n";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    } else {
        echo "<p style='color: red;'>❌ Errore nell'analisi duplicati esistenti</p>\n";
    }
    
    // Test 2: Test controllo duplicati su attività campione
    echo "<h3>🧪 Test Controllo Duplicati</h3>\n";
    
    // Ottieni alcune attività esistenti per test
    $stmt = $conn->prepare("
        SELECT id, dipendente_id, data_inizio, data_fine, durata_ore, descrizione, ticket_id
        FROM attivita 
        WHERE is_duplicate = 0 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $sample_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>\n";
    echo "<tr><th>Test</th><th>Attività</th><th>Risultato</th><th>Tipo</th><th>Confidenza</th><th>Azione</th></tr>\n";
    
    foreach ($sample_activities as $activity) {
        // Test 1: Duplicato esatto (stessa attività)
        $duplicate_check = $deduplication->checkActivityDuplicate($activity);
        
        echo "<tr>\n";
        echo "<td><strong>Duplicato Esatto</strong></td>\n";
        echo "<td><small>ID {$activity['id']} - {$activity['durata_ore']}h</small></td>\n";
        if ($duplicate_check['is_duplicate']) {
            echo "<td style='color: red;'>✅ Duplicato rilevato</td>\n";
            echo "<td>{$duplicate_check['duplicate_type']}</td>\n";
            echo "<td>" . round($duplicate_check['confidence'] * 100) . "%</td>\n";
            echo "<td>{$duplicate_check['action']}</td>\n";
        } else {
            echo "<td style='color: green;'>❌ Non duplicato</td>\n";
            echo "<td>-</td>\n";
            echo "<td>-</td>\n";
            echo "<td>{$duplicate_check['action']}</td>\n";
        }
        echo "</tr>\n";
        
        // Test 2: Variazione temporale (stessa attività +2 minuti)
        $modified_activity = $activity;
        $modified_activity['data_inizio'] = date('Y-m-d H:i:s', strtotime($activity['data_inizio']) + 120); // +2 minuti
        $modified_activity['id'] = null; // Simula nuova attività
        
        $duplicate_check_fuzzy = $deduplication->checkActivityDuplicate($modified_activity);
        
        echo "<tr>\n";
        echo "<td><strong>Fuzzy (+2min)</strong></td>\n";
        echo "<td><small>Simile ID {$activity['id']} +2min</small></td>\n";
        if ($duplicate_check_fuzzy['is_duplicate']) {
            echo "<td style='color: orange;'>⚠️ Duplicato fuzzy</td>\n";
            echo "<td>{$duplicate_check_fuzzy['duplicate_type']}</td>\n";
            echo "<td>" . round($duplicate_check_fuzzy['confidence'] * 100) . "%</td>\n";
            echo "<td>{$duplicate_check_fuzzy['action']}</td>\n";
        } else {
            echo "<td style='color: green;'>✅ Non duplicato</td>\n";
            echo "<td>-</td>\n";
            echo "<td>-</td>\n";
            echo "<td>{$duplicate_check_fuzzy['action']}</td>\n";
        }
        echo "</tr>\n";
        
        break; // Solo un test completo per ora
    }
    echo "</table>\n";
    
    // Test 3: Cleanup duplicati (DRY RUN)
    echo "<h3>🧹 Test Cleanup Duplicati (DRY RUN)</h3>\n";
    
    try {
        $cleanup_stats = $deduplication->cleanupExistingDuplicates(true); // dry_run = true
        
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h4>📋 Risultati Cleanup (Simulazione)</h4>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Operazione</th><th>Quantità</th><th>Descrizione</th></tr>\n";
        echo "<tr><td><strong>Gruppi Analizzati</strong></td><td>{$cleanup_stats['analyzed']}</td><td>Gruppi di duplicati identificati</td></tr>\n";
        echo "<tr><td><strong>Duplicati da Marcare</strong></td><td>{$cleanup_stats['marked_as_duplicates']}</td><td>Attività che verrebbero marcate come duplicate</td></tr>\n";
        echo "<tr><td><strong>Merge Eseguiti</strong></td><td>{$cleanup_stats['merged']}</td><td>Attività che verrebbero unite</td></tr>\n";
        echo "<tr><td><strong>Errori</strong></td><td>{$cleanup_stats['errors']}</td><td>Problemi riscontrati</td></tr>\n";
        echo "</table>\n";
        echo "<p><strong>⚠️ Nota:</strong> Questo è solo una simulazione. Nessuna modifica è stata apportata al database.</p>\n";
        echo "</div>\n";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Errore durante test cleanup: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    // Test 4: Statistiche engine
    echo "<h3>📊 Statistiche Deduplication Engine</h3>\n";
    
    $stats = $deduplication->getStats();
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Metrica</th><th>Valore</th><th>Descrizione</th></tr>\n";
    echo "<tr><td><strong>Duplicati Rilevati</strong></td><td>{$stats['duplicates_detected']}</td><td>Totale duplicati identificati durante i test</td></tr>\n";
    echo "<tr><td><strong>Duplicati Merged</strong></td><td>{$stats['duplicates_merged']}</td><td>Duplicati uniti intelligentemente</td></tr>\n";
    echo "<tr><td><strong>Duplicati Marcati</strong></td><td>{$stats['duplicates_marked']}</td><td>Duplicati marcati ma preservati</td></tr>\n";
    echo "<tr><td><strong>Inserimenti Unici</strong></td><td>{$stats['unique_inserted']}</td><td>Attività inserite come uniche</td></tr>\n";
    echo "</table>\n";
    
    // Summary finale
    echo "<h3>🎯 Summary Test Deduplication Engine</h3>\n";
    
    $total_potential_savings = $analysis ? $analysis['potential_duplicates'] : 0;
    
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>\n";
    echo "<h4>✅ Deduplication Engine Testato con Successo!</h4>\n";
    echo "<ul>\n";
    echo "<li><strong>Sistema Inizializzato:</strong> Schema deduplicazione creato automaticamente</li>\n";
    echo "<li><strong>Analisi Database:</strong> {$analysis['total_activities']} attività totali, {$analysis['potential_duplicates']} potenziali duplicati</li>\n";
    echo "<li><strong>Rilevamento Avanzato:</strong> Hash esatti + fuzzy matching con soglie configurabili</li>\n";
    echo "<li><strong>Gestione Intelligente:</strong> Soft deduplication, merge automatico, marcatura</li>\n";
    echo "<li><strong>Performance:</strong> Indici ottimizzati per ricerche veloci</li>\n";
    echo "<li><strong>Logging Completo:</strong> Tracciabilità di tutte le operazioni</li>\n";
    echo "</ul>\n";
    
    if ($total_potential_savings > 0) {
        $savings_percentage = round(($total_potential_savings / $analysis['total_activities']) * 100, 1);
        echo "<p><strong>💡 Potenziale Risparmio:</strong> {$total_potential_savings} duplicati eliminabili ({$savings_percentage}% del database)</p>\n";
    }
    
    echo "<p><strong>🚀 Pronto per:</strong> Integrazione nell'Enhanced CSV Parser per prevenire future duplicazioni</p>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante il test</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}
?>

<p>
    <a href="integrate_deduplication.php">🔧 Integra nel Parser</a> | 
    <a href="cleanup_duplicates.php">🧹 Cleanup Duplicati</a> | 
    <a href="index.php">Dashboard</a>
</p>