<?php
require_once 'config/Database.php';
require_once 'classes/EnhancedCsvParser.php';
require_once 'classes/DeduplicationEngine.php';
require_once 'classes/UploadManager.php';

/**
 * Sistema di Testing & Validation completo per Master Tables System
 * Verifica che tutti i componenti funzionino correttamente
 */

echo "<h2>🧪 System Validation - Master Tables & Enhanced Parser</h2>\n";

$validation_results = [
    'database_schema' => [],
    'master_tables_data' => [],
    'enhanced_parser' => [],
    'deduplication' => [],
    'upload_system' => [],
    'overall_score' => 0
];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h3>🔍 1. Database Schema Validation</h3>\n";
    
    // Test 1: Verifica esistenza Master Tables
    $required_tables = [
        'master_dipendenti' => ['nome', 'cognome', 'nome_completo_generated', 'attivo'],
        'master_veicoli' => ['nome', 'tipo', 'attivo'],
        'master_clienti' => ['nome', 'attivo'],
        'master_progetti' => ['nome', 'codice', 'attivo'],
        'dipendenti_aliases' => ['master_dipendente_id', 'alias_nome', 'alias_cognome'],
        'veicoli_aliases' => ['master_veicolo_id', 'alias_nome'],
        'clienti_aliases' => ['master_cliente_id', 'alias_nome']
    ];
    
    $schema_score = 0;
    $max_schema_score = count($required_tables);
    
    foreach ($required_tables as $table => $columns) {
        try {
            $stmt = $conn->prepare("DESCRIBE $table");
            $stmt->execute();
            $table_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $missing_columns = array_diff($columns, $table_columns);
            
            if (empty($missing_columns)) {
                echo "<p style='color: green;'>✅ $table: Schema completo</p>\n";
                $schema_score++;
                $validation_results['database_schema'][$table] = 'OK';
            } else {
                echo "<p style='color: orange;'>⚠️ $table: Colonne mancanti - " . implode(', ', $missing_columns) . "</p>\n";
                $validation_results['database_schema'][$table] = 'Colonne mancanti: ' . implode(', ', $missing_columns);
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ $table: Tabella non trovata</p>\n";
            $validation_results['database_schema'][$table] = 'Tabella non trovata';
        }
    }
    
    echo "<p><strong>Schema Score: $schema_score/$max_schema_score</strong></p>\n";
    
    // Test 2: Verifica FULLTEXT indices
    echo "<h4>🔍 FULLTEXT Indices Validation</h4>\n";
    
    $fulltext_tables = ['master_dipendenti', 'master_veicoli', 'master_clienti'];
    $fulltext_score = 0;
    
    foreach ($fulltext_tables as $table) {
        try {
            $stmt = $conn->prepare("SHOW INDEX FROM $table WHERE Index_type = 'FULLTEXT'");
            $stmt->execute();
            $indices = $stmt->fetchAll();
            
            if (!empty($indices)) {
                echo "<p style='color: green;'>✅ $table: FULLTEXT index presente</p>\n";
                $fulltext_score++;
            } else {
                echo "<p style='color: orange;'>⚠️ $table: FULLTEXT index mancante</p>\n";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ $table: Errore verifica FULLTEXT</p>\n";
        }
    }
    
    echo "<p><strong>FULLTEXT Score: $fulltext_score/" . count($fulltext_tables) . "</strong></p>\n";
    
    echo "<h3>📊 2. Master Tables Data Validation</h3>\n";
    
    // Test 3: Verifica popolazione Master Tables
    $data_score = 0;
    $max_data_score = 4;
    
    $data_checks = [
        'master_dipendenti' => "SELECT COUNT(*) as count, COUNT(CASE WHEN attivo = 1 THEN 1 END) as active FROM master_dipendenti",
        'master_veicoli' => "SELECT COUNT(*) as count, COUNT(CASE WHEN attivo = 1 THEN 1 END) as active FROM master_veicoli",
        'master_clienti' => "SELECT COUNT(*) as count, COUNT(CASE WHEN attivo = 1 THEN 1 END) as active FROM master_clienti",
        'dipendenti_aliases' => "SELECT COUNT(*) as count FROM dipendenti_aliases"
    ];
    
    foreach ($data_checks as $table => $query) {
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                $active_info = isset($result['active']) ? " ({$result['active']} attivi)" : "";
                echo "<p style='color: green;'>✅ $table: {$result['count']} record$active_info</p>\n";
                $data_score++;
                $validation_results['master_tables_data'][$table] = $result['count'] . ' record';
            } else {
                echo "<p style='color: orange;'>⚠️ $table: Nessun dato presente</p>\n";
                $validation_results['master_tables_data'][$table] = 'Vuota';
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ $table: Errore lettura dati</p>\n";
            $validation_results['master_tables_data'][$table] = 'Errore';
        }
    }
    
    echo "<p><strong>Data Population Score: $data_score/$max_data_score</strong></p>\n";
    
    echo "<h3>🔧 3. Enhanced Parser Validation</h3>\n";
    
    // Test 4: Enhanced Parser functionality
    $parser_score = 0;
    $max_parser_score = 5;
    
    try {
        $parser = new EnhancedCsvParser();
        echo "<p style='color: green;'>✅ EnhancedCsvParser: Istanza creata</p>\n";
        $parser_score++;
        $validation_results['enhanced_parser']['instantiation'] = 'OK';
        
        // Test reflection per verificare proprietà private
        $reflection = new ReflectionClass($parser);
        
        // Verifica cache master tables
        if ($reflection->hasProperty('master_dipendenti_cache')) {
            $cache_prop = $reflection->getProperty('master_dipendenti_cache');
            $cache_prop->setAccessible(true);
            $cache_data = $cache_prop->getValue($parser);
            
            if (!empty($cache_data)) {
                echo "<p style='color: green;'>✅ Cache dipendenti: " . count($cache_data) . " record caricati</p>\n";
                $parser_score++;
                $validation_results['enhanced_parser']['dipendenti_cache'] = count($cache_data) . ' record';
            } else {
                echo "<p style='color: orange;'>⚠️ Cache dipendenti: Vuota</p>\n";
                $validation_results['enhanced_parser']['dipendenti_cache'] = 'Vuota';
            }
        }
        
        // Verifica DeduplicationEngine
        if ($reflection->hasProperty('deduplication')) {
            $dedup_prop = $reflection->getProperty('deduplication');
            $dedup_prop->setAccessible(true);
            $dedup_instance = $dedup_prop->getValue($parser);
            
            if ($dedup_instance instanceof DeduplicationEngine) {
                echo "<p style='color: green;'>✅ DeduplicationEngine: Correttamente inizializzato</p>\n";
                $parser_score++;
                $validation_results['enhanced_parser']['deduplication'] = 'OK';
            } else {
                echo "<p style='color: red;'>❌ DeduplicationEngine: Non inizializzato</p>\n";
                $validation_results['enhanced_parser']['deduplication'] = 'Non inizializzato';
            }
        }
        
        // Test parsing nomi multipli
        $test_method = $reflection->getMethod('parseMultipleEmployeeNames');
        $test_method->setAccessible(true);
        
        $test_cases = [
            'Franco Fiorellino/Matteo Signo' => 2,
            'Mario Rossi, Giovanni Bianchi' => 2,
            'Single Name' => 1
        ];
        
        $parsing_tests_passed = 0;
        foreach ($test_cases as $input => $expected_count) {
            try {
                $result = $test_method->invoke($parser, $input);
                if (count($result) === $expected_count) {
                    $parsing_tests_passed++;
                }
            } catch (Exception $e) {
                // Test fallito
            }
        }
        
        if ($parsing_tests_passed === count($test_cases)) {
            echo "<p style='color: green;'>✅ Parsing nomi multipli: Tutti i test passati</p>\n";
            $parser_score++;
            $validation_results['enhanced_parser']['multiple_parsing'] = 'OK';
        } else {
            echo "<p style='color: orange;'>⚠️ Parsing nomi multipli: $parsing_tests_passed/" . count($test_cases) . " test passati</p>\n";
            $validation_results['enhanced_parser']['multiple_parsing'] = "$parsing_tests_passed/" . count($test_cases) . " test OK";
        }
        
        // Test validazione nomi
        $validation_method = $reflection->getMethod('isValidEmployeeName');
        $validation_method->setAccessible(true);
        
        $validation_tests = [
            'Mario Rossi' => true,
            'Punto' => false,
            'Info' => false,
            'test@email.com' => false
        ];
        
        $validation_tests_passed = 0;
        foreach ($validation_tests as $input => $expected) {
            try {
                $result = $validation_method->invoke($parser, $input);
                if ($result === $expected) {
                    $validation_tests_passed++;
                }
            } catch (Exception $e) {
                // Test fallito
            }
        }
        
        if ($validation_tests_passed === count($validation_tests)) {
            echo "<p style='color: green;'>✅ Validazione nomi: Tutti i test passati</p>\n";
            $parser_score++;
            $validation_results['enhanced_parser']['name_validation'] = 'OK';
        } else {
            echo "<p style='color: orange;'>⚠️ Validazione nomi: $validation_tests_passed/" . count($validation_tests) . " test passati</p>\n";
            $validation_results['enhanced_parser']['name_validation'] = "$validation_tests_passed/" . count($validation_tests) . " test OK";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ EnhancedCsvParser: Errore istanziazione - " . htmlspecialchars($e->getMessage()) . "</p>\n";
        $validation_results['enhanced_parser']['error'] = $e->getMessage();
    }
    
    echo "<p><strong>Enhanced Parser Score: $parser_score/$max_parser_score</strong></p>\n";
    
    echo "<h3>🔄 4. Deduplication Engine Validation</h3>\n";
    
    // Test 5: DeduplicationEngine standalone
    $dedup_score = 0;
    $max_dedup_score = 3;
    
    try {
        $dedup_config = [
            'time_threshold_minutes' => 3,
            'similarity_threshold' => 0.85,
            'enable_soft_deduplication' => true
        ];
        
        $dedup_engine = new DeduplicationEngine($dedup_config);
        echo "<p style='color: green;'>✅ DeduplicationEngine: Istanza creata</p>\n";
        $dedup_score++;
        $validation_results['deduplication']['instantiation'] = 'OK';
        
        // Test schema inizializzazione
        $reflection = new ReflectionClass($dedup_engine);
        $init_method = $reflection->getMethod('initializeSchema');
        $init_method->setAccessible(true);
        $init_method->invoke($dedup_engine);
        
        echo "<p style='color: green;'>✅ Schema deduplicazione: Inizializzato</p>\n";
        $dedup_score++;
        $validation_results['deduplication']['schema'] = 'OK';
        
        // Test analisi database
        if (method_exists($dedup_engine, 'analyzeDuplicatesInDatabase')) {
            $analysis = $dedup_engine->analyzeDuplicatesInDatabase();
            if ($analysis) {
                echo "<p style='color: green;'>✅ Analisi duplicati: {$analysis['total_activities']} attività, {$analysis['potential_duplicates']} potenziali duplicati</p>\n";
                $dedup_score++;
                $validation_results['deduplication']['analysis'] = "{$analysis['total_activities']} attività, {$analysis['potential_duplicates']} duplicati";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ DeduplicationEngine: Errore - " . htmlspecialchars($e->getMessage()) . "</p>\n";
        $validation_results['deduplication']['error'] = $e->getMessage();
    }
    
    echo "<p><strong>Deduplication Score: $dedup_score/$max_dedup_score</strong></p>\n";
    
    echo "<h3>📤 5. Upload System Validation</h3>\n";
    
    // Test 6: UploadManager
    $upload_score = 0;
    $max_upload_score = 3;
    
    try {
        $upload_manager = new UploadManager();
        echo "<p style='color: green;'>✅ UploadManager: Istanza creata</p>\n";
        $upload_score++;
        $validation_results['upload_system']['instantiation'] = 'OK';
        
        // Test directory sessione
        $session_dir = $upload_manager->getSessionDirectory();
        if (is_dir($session_dir)) {
            echo "<p style='color: green;'>✅ Directory sessione: $session_dir</p>\n";
            $upload_score++;
            $validation_results['upload_system']['session_directory'] = basename($session_dir);
        } else {
            echo "<p style='color: orange;'>⚠️ Directory sessione: Non trovata</p>\n";
            $validation_results['upload_system']['session_directory'] = 'Non trovata';
        }
        
        // Test statistiche
        $stats = $upload_manager->getUploadStats();
        if (!empty($stats)) {
            echo "<p style='color: green;'>✅ Statistiche upload: {$stats['files_in_session']} file, {$stats['session_size_mb']} MB</p>\n";
            $upload_score++;
            $validation_results['upload_system']['stats'] = "{$stats['files_in_session']} file, {$stats['session_size_mb']} MB";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ UploadManager: Errore - " . htmlspecialchars($e->getMessage()) . "</p>\n";
        $validation_results['upload_system']['error'] = $e->getMessage();
    }
    
    echo "<p><strong>Upload System Score: $upload_score/$max_upload_score</strong></p>\n";
    
    // Calculate overall score
    $total_max_score = $max_schema_score + count($fulltext_tables) + $max_data_score + $max_parser_score + $max_dedup_score + $max_upload_score;
    $total_score = $schema_score + $fulltext_score + $data_score + $parser_score + $dedup_score + $upload_score;
    $validation_results['overall_score'] = round(($total_score / $total_max_score) * 100, 1);
    
    echo "<h3>📊 6. Overall System Health</h3>\n";
    
    $health_color = 'red';
    $health_status = 'CRITICO';
    
    if ($validation_results['overall_score'] >= 90) {
        $health_color = 'green';
        $health_status = 'ECCELLENTE';
    } elseif ($validation_results['overall_score'] >= 75) {
        $health_color = 'blue';
        $health_status = 'BUONO';
    } elseif ($validation_results['overall_score'] >= 60) {
        $health_color = 'orange';
        $health_status = 'ACCETTABILE';
    }
    
    echo "<div style='background: " . ($health_color === 'green' ? '#d4edda' : ($health_color === 'blue' ? '#cce5ff' : ($health_color === 'orange' ? '#fff3cd' : '#f8d7da'))) . "; padding: 20px; border: 2px solid $health_color; border-radius: 10px;'>\n";
    echo "<h4 style='color: $health_color;'>🎯 SYSTEM HEALTH: $health_status ({$validation_results['overall_score']}%)</h4>\n";
    echo "<p><strong>Score Complessivo:</strong> $total_score/$total_max_score punti</p>\n";
    
    if ($validation_results['overall_score'] >= 80) {
        echo "<h5>✅ Sistema Pronto per Produzione</h5>\n";
        echo "<ul>\n";
        echo "<li>Master Tables implementate correttamente</li>\n";
        echo "<li>Enhanced Parser funzionante</li>\n";
        echo "<li>Sistema anti-duplicazione attivo</li>\n";
        echo "<li>Upload management operativo</li>\n";
        echo "</ul>\n";
        
        echo "<h5>🚀 Prossimi Passi Raccomandati:</h5>\n";
        echo "<ol>\n";
        echo "<li><a href='migrate_to_master_tables.php'>Eseguire migrazione dati legacy</a></li>\n";
        echo "<li><a href='cleanup_duplicates.php'>Cleanup duplicati esistenti</a></li>\n";
        echo "<li><a href='enhanced_upload_v2.php'>Test upload con file CSV reali</a></li>\n";
        echo "<li>Monitorare performance import</li>\n";
        echo "</ol>\n";
    } else {
        echo "<h5>⚠️ Problemi da Risolvere</h5>\n";
        echo "<p>Il sistema necessita di correzioni prima dell'uso in produzione.</p>\n";
        
        if ($schema_score < $max_schema_score) {
            echo "<p>🔧 <strong>Azione richiesta:</strong> Eseguire create_master_tables.sql</p>\n";
        }
        
        if ($data_score < 2) {
            echo "<p>📊 <strong>Azione richiesta:</strong> Popolare master tables con dati base</p>\n";
        }
        
        if ($parser_score < 3) {
            echo "<p>🔧 <strong>Azione richiesta:</strong> Verificare Enhanced Parser</p>\n";
        }
    }
    
    echo "</div>\n";
    
    // Test specifici per problemi originali
    echo "<h3>🎯 7. Test Risoluzione Problemi Originali</h3>\n";
    
    echo "<h4>Problema 1: Dipendenti Invalidi (\"Info\", \"Punto\", \"Fiesta\")</h4>\n";
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM dipendenti WHERE nome IN ('Info', 'Punto', 'Fiesta')");
    $stmt->execute();
    $invalid_count = $stmt->fetch()['count'];
    
    if ($invalid_count == 0) {
        echo "<p style='color: green;'>✅ Nessun dipendente invalido trovato</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Trovati $invalid_count dipendenti invalidi</p>\n";
    }
    
    echo "<h4>Problema 2: Duplicazioni Massive</h4>\n";
    $stmt = $conn->prepare("SELECT COUNT(*) as total, COUNT(CASE WHEN is_duplicate = 1 THEN 1 END) as duplicates FROM attivita");
    $stmt->execute();
    $activity_data = $stmt->fetch();
    
    $duplication_rate = $activity_data['total'] > 0 ? round(($activity_data['duplicates'] / $activity_data['total']) * 100, 1) : 0;
    echo "<p><strong>Attività totali:</strong> {$activity_data['total']}</p>\n";
    echo "<p><strong>Marcate come duplicate:</strong> {$activity_data['duplicates']} ($duplication_rate%)</p>\n";
    
    if ($duplication_rate < 50) {
        echo "<p style='color: green;'>✅ Tasso di duplicazione sotto controllo</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠️ Alto tasso di duplicazione - considerare cleanup</p>\n";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante validazione</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h2, h3, h4 { color: #333; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>

<p>
    <a href="enhanced_upload_v2.php">🚀 Enhanced Upload v2</a> | 
    <a href="migrate_to_master_tables.php">🔄 Migrazione Dati</a> | 
    <a href="cleanup_duplicates.php">🧹 Cleanup</a> | 
    <a href="index.php">Dashboard</a>
</p>