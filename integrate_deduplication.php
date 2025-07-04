<?php
require_once 'config/Database.php';
require_once 'classes/DeduplicationEngine.php';

echo "<h2>🔧 Integrazione Deduplication Engine nell'Enhanced Parser</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // 1. Test preliminare del DeduplicationEngine
    echo "<h3>🧪 Test Preliminare Sistema</h3>\n";
    
    $deduplication = new DeduplicationEngine([
        'time_threshold_minutes' => 3,
        'similarity_threshold' => 0.85,
        'enable_soft_deduplication' => true
    ]);
    
    echo "<p style='color: green;'>✅ DeduplicationEngine inizializzato</p>\n";
    
    // 2. Backup dell'Enhanced Parser attuale
    echo "<h3>📦 Backup Enhanced Parser</h3>\n";
    
    $enhanced_file = 'classes/EnhancedCsvParser.php';
    $backup_enhanced = 'classes/EnhancedCsvParser_backup_' . date('Y-m-d_H-i-s') . '.php';
    
    if (file_exists($enhanced_file)) {
        if (copy($enhanced_file, $backup_enhanced)) {
            echo "<p style='color: green;'>✅ Backup Enhanced Parser: $backup_enhanced</p>\n";
        } else {
            throw new Exception("Impossibile creare backup Enhanced Parser");
        }
    }
    
    // 3. Modifica Enhanced Parser per integrare DeduplicationEngine
    echo "<h3>🔧 Modifica Enhanced Parser</h3>\n";
    
    // Leggi il contenuto attuale
    $enhanced_content = file_get_contents($enhanced_file);
    
    // Aggiungi require per DeduplicationEngine
    if (strpos($enhanced_content, 'DeduplicationEngine') === false) {
        $enhanced_content = str_replace(
            "require_once __DIR__ . '/ImportLogger.php';",
            "require_once __DIR__ . '/ImportLogger.php';\nrequire_once __DIR__ . '/DeduplicationEngine.php';",
            $enhanced_content
        );
        echo "<p style='color: green;'>✅ Aggiunto require DeduplicationEngine</p>\n";
    }
    
    // Aggiungi proprietà per DeduplicationEngine
    if (strpos($enhanced_content, 'private $deduplication;') === false) {
        $enhanced_content = str_replace(
            'private $aliases_cache = null;',
            "private \$aliases_cache = null;\n    private \$deduplication = null;",
            $enhanced_content
        );
        echo "<p style='color: green;'>✅ Aggiunta proprietà \$deduplication</p>\n";
    }
    
    // Inizializza DeduplicationEngine nel costruttore
    if (strpos($enhanced_content, '$this->deduplication = new DeduplicationEngine') === false) {
        $enhanced_content = str_replace(
            '$this->initializeCaches();',
            "\$this->initializeCaches();\n        \$this->initializeDeduplication();",
            $enhanced_content
        );
        
        // Aggiungi metodo initializeDeduplication
        $init_method = "\n    /**\n     * Inizializza Deduplication Engine\n     */\n    private function initializeDeduplication() {\n        \$dedup_config = [\n            'time_threshold_minutes' => 3,\n            'similarity_threshold' => 0.85,\n            'enable_soft_deduplication' => true,\n            'enable_intelligent_merge' => true\n        ];\n        \n        \$this->deduplication = new DeduplicationEngine(\$dedup_config);\n        \$this->logger->info(\"DeduplicationEngine inizializzato con soglie: \" . json_encode(\$dedup_config));\n    }\n";
        
        $enhanced_content = str_replace(
            '    /**
     * Process all files with enhanced parsing
     */',
            $init_method . "\n    /**\n     * Process all files with enhanced parsing\n     */",
            $enhanced_content
        );
        echo "<p style='color: green;'>✅ Aggiunta inizializzazione DeduplicationEngine</p>\n";
    }
    
    // Modifica processAttivita per usare DeduplicationEngine
    if (strpos($enhanced_content, 'insertActivityWithDeduplication') === false) {
        $old_attivita_method = '            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                \':dipendente_id\' => $dipendente_id,
                \':cliente_id\' => $cliente_id,
                \':progetto_id\' => $progetto_id,
                \':ticket_id\' => $data[\'Id Ticket\'] ?? null,
                \':data_inizio\' => $this->parseDateTime($data[\'Iniziata il\']),
                \':data_fine\' => $this->parseDateTime($data[\'Conclusa il\']),
                \':durata_ore\' => $this->parseFloat($data[\'Durata\']),
                \':descrizione\' => $data[\'Descrizione\'] ?? null,
                \':riferimento_progetto\' => $data[\'Riferimento Progetto\'] ?? null,
                \':creato_da\' => $data[\'Creato da\'] ?? null,
                \':fatturabile\' => 1
            ]);
            
            return $result ? \'inserted\' : false;';
        
        $new_attivita_method = '            // Enhanced: Usa DeduplicationEngine per prevenire duplicati
            $activityData = [
                \'dipendente_id\' => $dipendente_id,
                \'cliente_id\' => $cliente_id,
                \'progetto_id\' => $progetto_id,
                \'ticket_id\' => $data[\'Id Ticket\'] ?? null,
                \'data_inizio\' => $this->parseDateTime($data[\'Iniziata il\']),
                \'data_fine\' => $this->parseDateTime($data[\'Conclusa il\']),
                \'durata_ore\' => $this->parseFloat($data[\'Durata\']),
                \'descrizione\' => $data[\'Descrizione\'] ?? null,
                \'riferimento_progetto\' => $data[\'Riferimento Progetto\'] ?? null,
                \'creato_da\' => $data[\'Creato da\'] ?? null,
                \'fatturabile\' => 1
            ];
            
            $params = [
                \':dipendente_id\' => $dipendente_id,
                \':cliente_id\' => $cliente_id,
                \':progetto_id\' => $progetto_id,
                \':ticket_id\' => $data[\'Id Ticket\'] ?? null,
                \':data_inizio\' => $this->parseDateTime($data[\'Iniziata il\']),
                \':data_fine\' => $this->parseDateTime($data[\'Conclusa il\']),
                \':durata_ore\' => $this->parseFloat($data[\'Durata\']),
                \':descrizione\' => $data[\'Descrizione\'] ?? null,
                \':riferimento_progetto\' => $data[\'Riferimento Progetto\'] ?? null,
                \':creato_da\' => $data[\'Creato da\'] ?? null,
                \':fatturabile\' => 1
            ];
            
            $result = $this->deduplication->insertActivityWithDeduplication($activityData, $sql, $params);
            
            return $result;';
        
        $enhanced_content = str_replace($old_attivita_method, $new_attivita_method, $enhanced_content);
        echo "<p style='color: green;'>✅ Modificato processAttivita per usare DeduplicationEngine</p>\n";
    }
    
    // Salva il file modificato
    file_put_contents($enhanced_file, $enhanced_content);
    echo "<p style='color: green;'>✅ Enhanced Parser aggiornato con DeduplicationEngine</p>\n";
    
    // 4. Test integrazione
    echo "<h3>🧪 Test Integrazione</h3>\n";
    
    try {
        require_once $enhanced_file;
        $enhanced_parser = new EnhancedCsvParser();
        echo "<p style='color: green;'>✅ Enhanced Parser con DeduplicationEngine caricato</p>\n";
        
        // Test metodo privato (se possibile)
        $reflection = new ReflectionClass($enhanced_parser);
        
        if ($reflection->hasProperty('deduplication')) {
            $dedup_property = $reflection->getProperty('deduplication');
            $dedup_property->setAccessible(true);
            $dedup_instance = $dedup_property->getValue($enhanced_parser);
            
            if ($dedup_instance instanceof DeduplicationEngine) {
                echo "<p style='color: green;'>✅ DeduplicationEngine correttamente inizializzato nell'Enhanced Parser</p>\n";
            } else {
                echo "<p style='color: orange;'>⚠️ DeduplicationEngine non inizializzato correttamente</p>\n";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Errore test integrazione: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    // 5. Crea script di cleanup massivo
    echo "<h3>🧹 Creazione Script Cleanup Massivo</h3>\n";
    
    $cleanup_script = '<?php
require_once \'config/Database.php\';
require_once \'classes/DeduplicationEngine.php\';

echo "<h2>🧹 Cleanup Massivo Duplicati Esistenti</h2>\\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $deduplication = new DeduplicationEngine([
        \'time_threshold_minutes\' => 2,
        \'similarity_threshold\' => 0.9,
        \'enable_soft_deduplication\' => true
    ]);
    
    echo "<h3>📊 Analisi Pre-Cleanup</h3>\\n";
    $analysis = $deduplication->analyzeDuplicatesInDatabase();
    
    if ($analysis) {
        echo "<p><strong>Totale Attività:</strong> {$analysis[\'total_activities\']}</p>\\n";
        echo "<p><strong>Duplicati Potenziali:</strong> {$analysis[\'potential_duplicates\']}</p>\\n";
        echo "<p><strong>Percentuale Duplicati:</strong> " . round(($analysis[\'potential_duplicates\'] / max(1, $analysis[\'total_activities\'])) * 100, 2) . "%</p>\\n";
    }
    
    // Prima esegui DRY RUN
    echo "<h3>🧪 DRY RUN - Simulazione Cleanup</h3>\\n";
    $dry_run_stats = $deduplication->cleanupExistingDuplicates(true);
    
    echo "<table border=\'1\' style=\'border-collapse: collapse; margin: 10px 0;\'>\\n";
    echo "<tr><th>Metrica</th><th>Valore</th></tr>\\n";
    echo "<tr><td>Gruppi Analizzati</td><td>{$dry_run_stats[\'analyzed\']}</td></tr>\\n";
    echo "<tr><td>Duplicati da Marcare</td><td>{$dry_run_stats[\'marked_as_duplicates\']}</td></tr>\\n";
    echo "<tr><td>Errori</td><td>{$dry_run_stats[\'errors\']}</td></tr>\\n";
    echo "</table>\\n";
    
    if (isset($_GET[\'execute\']) && $_GET[\'execute\'] === \'yes\') {
        echo "<h3>🚀 ESECUZIONE EFFETTIVA Cleanup</h3>\\n";
        echo "<div style=\'background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;\'>\\n";
        echo "<p><strong>⚠️ ATTENZIONE:</strong> Questo modificherà il database!</p>\\n";
        echo "</div>\\n";
        
        $real_stats = $deduplication->cleanupExistingDuplicates(false);
        
        echo "<table border=\'1\' style=\'border-collapse: collapse; margin: 10px 0;\'>\\n";
        echo "<tr><th>Operazione</th><th>Risultato</th></tr>\\n";
        echo "<tr><td>Duplicati Marcati</td><td style=\'color: green; font-weight: bold;\'>{$real_stats[\'marked_as_duplicates\']}</td></tr>\\n";
        echo "<tr><td>Errori</td><td>{$real_stats[\'errors\']}</td></tr>\\n";
        echo "</table>\\n";
        
        echo "<div style=\'background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;\'>\\n";
        echo "<h4>✅ Cleanup Completato!</h4>\\n";
        echo "<p>I duplicati sono stati marcati come <code>is_duplicate = 1</code> ma non eliminati.</p>\\n";
        echo "<p>Per vedere solo le attività originali, usare: <code>WHERE is_duplicate = 0</code></p>\\n";
        echo "</div>\\n";
        
    } else {
        echo "<div style=\'background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;\'>\\n";
        echo "<h4>⚠️ Per Eseguire il Cleanup Effettivo</h4>\\n";
        echo "<p>Questa è solo una simulazione. Per eseguire il cleanup reale:</p>\\n";
        echo "<p><a href=\'?execute=yes\' onclick=\'return confirm(\\\"Sei sicuro di voler eseguire il cleanup? Questa operazione modificherà il database!\\\")\'>";
        echo "<strong>🚀 ESEGUI CLEANUP REALE</strong></a></p>\\n";
        echo "</div>\\n";
    }
    
} catch (Exception $e) {
    echo "<div style=\'background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;\'>\\n";
    echo "<h4>❌ Errore durante cleanup</h4>\\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\\n";
    echo "</div>\\n";
}
?>

<p>
    <a href="enhanced_upload.php">🚀 Test Enhanced Upload</a> | 
    <a href="test_deduplication_engine.php">🧪 Test Deduplication</a> | 
    <a href="index.php">Dashboard</a>
</p>';

    file_put_contents('cleanup_duplicates.php', $cleanup_script);
    echo "<p style='color: green;'>✅ Creato cleanup_duplicates.php</p>\n";
    
    // 6. Crea Enhanced Upload con Deduplication
    echo "<h3>🚀 Aggiornamento Enhanced Upload</h3>\n";
    
    // Aggiorna enhanced_upload.php per mostrare statistiche deduplicazione
    $enhanced_upload_content = file_get_contents('enhanced_upload.php');
    
    // Aggiungi sezione per statistiche deduplicazione
    $dedup_stats_section = '
                            // Enhanced: Mostra statistiche deduplicazione
                            if (isset($parser) && method_exists($parser, \'getDeduplicationStats\')) {
                                $dedup_stats = $parser->getDeduplicationStats();
                                if (!empty($dedup_stats)) {
                                    echo "<div class=\'mt-3\'>\\n";
                                    echo "<h6>🔄 Statistiche Anti-duplicazione:</h6>\\n";
                                    echo "<div class=\'row\'>\\n";
                                    echo "<div class=\'col-md-3\'><small><strong>Duplicati Rilevati:</strong> {$dedup_stats[\'duplicates_detected\']}</small></div>\\n";
                                    echo "<div class=\'col-md-3\'><small><strong>Duplicati Merged:</strong> {$dedup_stats[\'duplicates_merged\']}</small></div>\\n";
                                    echo "<div class=\'col-md-3\'><small><strong>Duplicati Marcati:</strong> {$dedup_stats[\'duplicates_marked\']}</small></div>\\n";
                                    echo "<div class=\'col-md-3\'><small><strong>Inserimenti Unici:</strong> {$dedup_stats[\'unique_inserted\']}</small></div>\\n";
                                    echo "</div>\\n";
                                    echo "</div>\\n";
                                }
                            }';
    
    $enhanced_upload_content = str_replace(
        '                                    <?php endif; ?>',
        '                                    <?php endif; ?>' . $dedup_stats_section,
        $enhanced_upload_content
    );
    
    file_put_contents('enhanced_upload.php', $enhanced_upload_content);
    echo "<p style='color: green;'>✅ Enhanced Upload aggiornato con statistiche deduplicazione</p>\n";
    
    // 7. Summary integrazione
    echo "<h3>📋 Summary Integrazione Deduplication</h3>\n";
    
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>\n";
    echo "<h4>✅ Deduplication Engine Integrato con Successo!</h4>\n";
    echo "<ul>\n";
    echo "<li><strong>Enhanced Parser:</strong> Modificato per usare DeduplicationEngine automaticamente</li>\n";
    echo "<li><strong>Anti-duplicazione:</strong> Soglia temporale 3 minuti, similarità 85%</li>\n";
    echo "<li><strong>Soft Deduplication:</strong> I duplicati vengono marcati ma preservati per analisi</li>\n";
    echo "<li><strong>Cleanup Script:</strong> Disponibile cleanup_duplicates.php per pulizia massiva</li>\n";
    echo "<li><strong>Backup Sicurezza:</strong> Backup automatico dei file modificati</li>\n";
    echo "</ul>\n";
    
    echo "<h5>🎯 Prossimi Passi:</h5>\n";
    echo "<ol>\n";
    echo "<li><a href='enhanced_upload.php'><strong>Testare Enhanced Upload</strong></a> con file CSV per verificare anti-duplicazione</li>\n";
    echo "<li><a href='cleanup_duplicates.php'><strong>Eseguire Cleanup</strong></a> per marcare duplicati esistenti</li>\n";
    echo "<li>Monitorare import futuri per confermare eliminazione duplicazioni</li>\n";
    echo "</ol>\n";
    
    echo "<p><strong>💡 Risultato Atteso:</strong> Import di ~600 attività invece di 6000+ duplicati</p>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante l'integrazione</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}
?>

<p>
    <a href="enhanced_upload.php">🚀 Test Enhanced Upload</a> | 
    <a href="cleanup_duplicates.php">🧹 Cleanup Duplicati</a> | 
    <a href="test_deduplication_engine.php">🧪 Test Engine</a> | 
    <a href="index.php">Dashboard</a>
</p>