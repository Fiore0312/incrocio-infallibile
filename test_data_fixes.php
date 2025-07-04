<?php
require_once 'config/Database.php';
require_once 'classes/CsvParser.php';
require_once 'classes/ImportLogger.php';

echo "<h2>🧪 Test Validazione Correzioni Database</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    $logger = new ImportLogger('testing');
    
    $logger->info("Avvio test di validazione correzioni");
    
    $test_results = [];
    $all_tests_passed = true;
    
    // Test 1: Validazione nomi dipendenti
    echo "<h3>Test 1: Validazione Nomi Dipendenti</h3>\n";
    
    $csvParser = new CsvParser();
    $reflection = new ReflectionClass($csvParser);
    $isValidMethod = $reflection->getMethod('isValidEmployeeName');
    $isValidMethod->setAccessible(true);
    
    $test_names = [
        'Marco Rossi' => true,      // Nome valido
        'Punto' => false,           // Nome veicolo
        'Info' => false,            // Nome sistema
        'Aurora' => false,          // Nome non valido
        'a' => false,               // Troppo corto
        'test@email.com' => false,  // Email
        '12345' => false,           // Solo numeri
        'Giovanni' => true,         // Nome valido singolo
        '' => false,                // Vuoto
        'Marco123' => true,         // Nome con numeri (valido)
        'pc-desktop' => false,      // Nome computer
        'www.example.com' => false  // Dominio
    ];
    
    $validation_tests_passed = 0;
    $validation_tests_total = count($test_names);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Nome Test</th><th>Atteso</th><th>Risultato</th><th>Status</th></tr>\n";
    
    foreach ($test_names as $name => $expected) {
        $result = $isValidMethod->invoke($csvParser, $name);
        $status = ($result === $expected) ? '✅ PASS' : '❌ FAIL';
        $status_color = ($result === $expected) ? 'green' : 'red';
        
        if ($result === $expected) {
            $validation_tests_passed++;
        } else {
            $all_tests_passed = false;
        }
        
        echo "<tr>\n";
        echo "<td>$name</td>\n";
        echo "<td>" . ($expected ? 'Valido' : 'Non valido') . "</td>\n";
        echo "<td>" . ($result ? 'Valido' : 'Non valido') . "</td>\n";
        echo "<td style='color: $status_color;'>$status</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    $test_results['validazione_nomi'] = [
        'passed' => $validation_tests_passed,
        'total' => $validation_tests_total,
        'success_rate' => round(($validation_tests_passed / $validation_tests_total) * 100, 2)
    ];
    
    echo "<p><strong>Risultato:</strong> $validation_tests_passed/$validation_tests_total test passati ({$test_results['validazione_nomi']['success_rate']}%)</p>\n";
    
    // Test 2: Controllo dipendenti esistenti non validi
    echo "<h3>Test 2: Controllo Dipendenti Non Validi nel Database</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT id, nome, cognome 
        FROM dipendenti 
        WHERE nome IN ('Punto', 'Fiesta', 'Info', 'Aurora') 
           OR (cognome = '' AND LENGTH(nome) < 3)
           OR nome LIKE '%@%'
        LIMIT 10
    ");
    $stmt->execute();
    $invalid_employees_found = $stmt->fetchAll();
    
    if (empty($invalid_employees_found)) {
        echo "<p style='color: green;'>✅ PASS - Nessun dipendente non valido trovato nel database</p>\n";
        $test_results['dipendenti_database'] = ['status' => 'PASS', 'count' => 0];
    } else {
        echo "<p style='color: red;'>❌ FAIL - Trovati " . count($invalid_employees_found) . " dipendenti non validi:</p>\n";
        echo "<ul>\n";
        foreach ($invalid_employees_found as $emp) {
            echo "<li>ID {$emp['id']}: {$emp['nome']} {$emp['cognome']}</li>\n";
        }
        echo "</ul>\n";
        $test_results['dipendenti_database'] = ['status' => 'FAIL', 'count' => count($invalid_employees_found)];
        $all_tests_passed = false;
    }
    
    // Test 3: Controllo duplicati attività
    echo "<h3>Test 3: Controllo Duplicati Attività</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as groups
        FROM (
            SELECT 
                dipendente_id, data_inizio, durata_ore,
                COUNT(*) as duplicates
            FROM attivita 
            GROUP BY dipendente_id, data_inizio, durata_ore
            HAVING COUNT(*) > 1
        ) as dup_groups
    ");
    $stmt->execute();
    $duplicate_groups = $stmt->fetch()['groups'];
    
    if ($duplicate_groups == 0) {
        echo "<p style='color: green;'>✅ PASS - Nessun gruppo di attività duplicate trovato</p>\n";
        $test_results['duplicati_attivita'] = ['status' => 'PASS', 'groups' => 0];
    } else {
        echo "<p style='color: red;'>❌ FAIL - Trovati $duplicate_groups gruppi di attività duplicate</p>\n";
        $test_results['duplicati_attivita'] = ['status' => 'FAIL', 'groups' => $duplicate_groups];
        $all_tests_passed = false;
    }
    
    // Test 4: Test creazione dipendente con nome non valido
    echo "<h3>Test 4: Test Creazione Dipendente Non Valido</h3>\n";
    
    $conn->beginTransaction();
    
    try {
        $createMethod = $reflection->getMethod('createDipendenteFromFullName');
        $createMethod->setAccessible(true);
        
        // Tenta di creare dipendente con nome non valido
        $result = $createMethod->invoke($csvParser, 'Punto');
        
        if ($result === null) {
            echo "<p style='color: green;'>✅ PASS - Creazione dipendente 'Punto' correttamente rifiutata</p>\n";
            $test_results['creazione_rifiutata'] = ['status' => 'PASS'];
        } else {
            echo "<p style='color: red;'>❌ FAIL - Dipendente 'Punto' creato erroneamente (ID: $result)</p>\n";
            $test_results['creazione_rifiutata'] = ['status' => 'FAIL', 'created_id' => $result];
            $all_tests_passed = false;
        }
        
        $conn->rollback(); // Rollback per non salvare il test
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "<p style='color: orange;'>⚠️ WARNING - Errore durante test creazione: {$e->getMessage()}</p>\n";
        $test_results['creazione_rifiutata'] = ['status' => 'ERROR', 'error' => $e->getMessage()];
    }
    
    // Test 5: Verifica logging funzionante
    echo "<h3>Test 5: Verifica Sistema di Logging</h3>\n";
    
    $log_stats = ImportLogger::getLogStats('testing');
    if ($log_stats && $log_stats['total_lines'] > 0) {
        echo "<p style='color: green;'>✅ PASS - Sistema di logging funzionante</p>\n";
        echo "<p><small>Log file: {$log_stats['total_lines']} righe, {$log_stats['error_count']} errori</small></p>\n";
        $test_results['logging'] = ['status' => 'PASS', 'lines' => $log_stats['total_lines']];
    } else {
        echo "<p style='color: red;'>❌ FAIL - Sistema di logging non funzionante</p>\n";
        $test_results['logging'] = ['status' => 'FAIL'];
        $all_tests_passed = false;
    }
    
    // Test 6: Verifica veicoli nella tabella corretta
    echo "<h3>Test 6: Verifica Veicoli in Tabella Corretta</h3>\n";
    
    $stmt = $conn->prepare("SELECT nome FROM veicoli WHERE nome IN ('Punto', 'Fiesta', 'Peugeot')");
    $stmt->execute();
    $vehicles_in_table = $stmt->fetchAll();
    
    $expected_vehicles = ['Punto', 'Fiesta', 'Peugeot'];
    $found_vehicles = array_column($vehicles_in_table, 'nome');
    
    $missing_vehicles = array_diff($expected_vehicles, $found_vehicles);
    
    if (empty($missing_vehicles)) {
        echo "<p style='color: green;'>✅ PASS - Tutti i veicoli sono nella tabella veicoli</p>\n";
        $test_results['veicoli_tabella'] = ['status' => 'PASS', 'found' => count($found_vehicles)];
    } else {
        echo "<p style='color: red;'>❌ FAIL - Veicoli mancanti: " . implode(', ', $missing_vehicles) . "</p>\n";
        $test_results['veicoli_tabella'] = ['status' => 'FAIL', 'missing' => $missing_vehicles];
        $all_tests_passed = false;
    }
    
    // Test 7: Test performance validazione
    echo "<h3>Test 7: Test Performance Validazione</h3>\n";
    
    $start_time = microtime(true);
    
    for ($i = 0; $i < 1000; $i++) {
        $isValidMethod->invoke($csvParser, "TestName$i");
    }
    
    $end_time = microtime(true);
    $execution_time = round(($end_time - $start_time) * 1000, 2);
    
    if ($execution_time < 100) { // Meno di 100ms per 1000 validazioni
        echo "<p style='color: green;'>✅ PASS - Performance validazione OK ($execution_time ms per 1000 validazioni)</p>\n";
        $test_results['performance'] = ['status' => 'PASS', 'time_ms' => $execution_time];
    } else {
        echo "<p style='color: orange;'>⚠️ WARNING - Performance validazione lenta ($execution_time ms per 1000 validazioni)</p>\n";
        $test_results['performance'] = ['status' => 'WARNING', 'time_ms' => $execution_time];
    }
    
    // Summary finale
    echo "<h3>📊 Summary Test Results</h3>\n";
    
    $total_tests = count($test_results);
    $passed_tests = 0;
    $failed_tests = 0;
    $warning_tests = 0;
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>\n";
    echo "<tr><th>Test</th><th>Status</th><th>Dettagli</th></tr>\n";
    
    foreach ($test_results as $test_name => $result) {
        $status = $result['status'] ?? 'UNKNOWN';
        $details = '';
        
        switch ($status) {
            case 'PASS':
                $passed_tests++;
                $status_color = 'green';
                $status_icon = '✅';
                break;
            case 'FAIL':
                $failed_tests++;
                $status_color = 'red';
                $status_icon = '❌';
                break;
            case 'WARNING':
            case 'ERROR':
                $warning_tests++;
                $status_color = 'orange';
                $status_icon = '⚠️';
                break;
            default:
                $status_color = 'gray';
                $status_icon = '❓';
        }
        
        // Crea dettagli specifici per ogni test
        if ($test_name === 'validazione_nomi') {
            $details = "{$result['passed']}/{$result['total']} ({$result['success_rate']}%)";
        } elseif (isset($result['count'])) {
            $details = "Count: {$result['count']}";
        } elseif (isset($result['time_ms'])) {
            $details = "Time: {$result['time_ms']}ms";
        } elseif (isset($result['groups'])) {
            $details = "Groups: {$result['groups']}";
        }
        
        echo "<tr>\n";
        echo "<td>$test_name</td>\n";
        echo "<td style='color: $status_color;'>$status_icon $status</td>\n";
        echo "<td>$details</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Risultato finale
    if ($all_tests_passed && $failed_tests == 0) {
        echo "<div style='background: #d4edda; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h4>🎉 TUTTI I TEST PASSATI!</h4>\n";
        echo "<p>Le correzioni per la gestione dei dipendenti e duplicati funzionano correttamente.</p>\n";
        echo "<p><strong>Risultati:</strong> $passed_tests PASS, $warning_tests WARNING, $failed_tests FAIL</p>\n";
        echo "</div>\n";
        
        $logger->info("Tutti i test passati", [
            'passed' => $passed_tests,
            'warnings' => $warning_tests,
            'failed' => $failed_tests
        ]);
    } else {
        echo "<div style='background: #f8d7da; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h4>⚠️ ALCUNI TEST FALLITI</h4>\n";
        echo "<p>Ci sono ancora problemi che richiedono attenzione.</p>\n";
        echo "<p><strong>Risultati:</strong> $passed_tests PASS, $warning_tests WARNING, $failed_tests FAIL</p>\n";
        
        if ($failed_tests > 0) {
            echo "<h5>Azioni necessarie:</h5>\n";
            echo "<ul>\n";
            if (isset($test_results['dipendenti_database']) && $test_results['dipendenti_database']['status'] === 'FAIL') {
                echo "<li>Eseguire script di pulizia dipendenti non validi</li>\n";
            }
            if (isset($test_results['duplicati_attivita']) && $test_results['duplicati_attivita']['status'] === 'FAIL') {
                echo "<li>Eseguire pulizia attività duplicate</li>\n";
            }
            echo "</ul>\n";
        }
        echo "</div>\n";
        
        $logger->warning("Alcuni test falliti", [
            'passed' => $passed_tests,
            'warnings' => $warning_tests,
            'failed' => $failed_tests,
            'details' => $test_results
        ]);
    }
    
    $logger->close();
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante i test</h4>\n";
    echo "<p><strong>Errore:</strong> " . $e->getMessage() . "</p>\n";
    echo "</div>\n";
    
    if (isset($logger)) {
        $logger->error("Errore durante test", ['error' => $e->getMessage()]);
        $logger->close();
    }
}
?>

<p>
    <a href="verify_data_integrity.php">🔍 Verifica Integrità</a> | 
    <a href="cleanup_database.php">🧹 Pulizia Database</a> | 
    <a href="diagnose_data.php">← Diagnostica</a> | 
    <a href="index.php">Dashboard</a>
</p>