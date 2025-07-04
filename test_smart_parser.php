<?php
require_once 'config/Database.php';
require_once 'classes/SmartCsvParser.php';
require_once 'classes/ImportLogger.php';

/**
 * Test Smart CSV Parser - Fase 3
 * Sistema di test per il parser intelligente
 */

echo "<h2>🧪 Test Smart CSV Parser - Sistema Intelligente</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $test_results = [
        'master_data_check' => [],
        'parser_instantiation' => false,
        'file_type_detection' => [],
        'employee_recognition' => [],
        'company_association' => [],
        'duplicate_detection' => [],
        'overall_status' => 'pending'
    ];
    
    // 1. Verifica Master Data Setup
    echo "<h3>1. 🔍 Verifica Master Data Setup</h3>\n";
    
    $master_checks = [
        'master_dipendenti_fixed' => "SELECT COUNT(*) as count FROM master_dipendenti_fixed WHERE attivo = 1",
        'master_aziende' => "SELECT COUNT(*) as count FROM master_aziende WHERE attivo = 1",
        'master_veicoli_config' => "SELECT COUNT(*) as count FROM master_veicoli_config WHERE attivo = 1",
        'system_config' => "SELECT COUNT(*) as count FROM system_config"
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Tabella Master</th><th>Record</th><th>Stato</th><th>Note</th></tr>\n";
    
    foreach ($master_checks as $table => $query) {
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $count = $result['count'];
            
            $status = 'OK';
            $note = '';
            $color = 'green';
            
            if ($table === 'master_dipendenti_fixed' && $count < 14) {
                $status = 'INSUFFICIENTE';
                $note = 'Richiesti almeno 14 dipendenti fissi';
                $color = 'red';
            } elseif ($count == 0) {
                $status = 'VUOTA';
                $note = 'Tabella vuota';
                $color = 'orange';
            }
            
            echo "<tr>\n";
            echo "<td><strong>$table</strong></td>\n";
            echo "<td>$count</td>\n";
            echo "<td style='color: $color;'>$status</td>\n";
            echo "<td>$note</td>\n";
            echo "</tr>\n";
            
            $test_results['master_data_check'][$table] = ['count' => $count, 'status' => $status];
            
        } catch (Exception $e) {
            echo "<tr>\n";
            echo "<td><strong>$table</strong></td>\n";
            echo "<td>-</td>\n";
            echo "<td style='color: red;'>ERRORE</td>\n";
            echo "<td>" . htmlspecialchars($e->getMessage()) . "</td>\n";
            echo "</tr>\n";
            
            $test_results['master_data_check'][$table] = ['status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }
    echo "</table>\n";
    
    // 2. Test Istanziazione Smart Parser
    echo "<h3>2. 🔧 Test Istanziazione Smart Parser</h3>\n";
    
    try {
        $smart_parser = new SmartCsvParser();
        echo "<p style='color: green;'><strong>✅ SmartCsvParser istanziato con successo</strong></p>\n";
        $test_results['parser_instantiation'] = true;
        
        // Test metodi pubblici
        $methods_to_test = ['getStats', 'getErrors', 'getWarnings'];
        foreach ($methods_to_test as $method) {
            if (method_exists($smart_parser, $method)) {
                $result = $smart_parser->$method();
                echo "<p>📋 <strong>$method():</strong> " . (is_array($result) ? count($result) . " elementi" : $result) . "</p>\n";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>❌ Errore istanziazione SmartCsvParser:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
        $test_results['parser_instantiation'] = false;
    }
    
    // 3. Test Rilevamento Tipo File
    echo "<h3>3. 🔍 Test Rilevamento Tipo File</h3>\n";
    
    $test_files = [
        'test_attivita.csv' => 'attivita',
        'test_timbrature.csv' => 'timbrature', 
        'test_teamviewer.csv' => 'teamviewer',
        'apprilevazionepresenze-timbrature-totali-base.csv' => 'timbrature',
        'attivita_export.csv' => 'attivita'
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Nome File</th><th>Tipo Atteso</th><th>Stato Test</th><th>Note</th></tr>\n";
    
    foreach ($test_files as $filename => $expected_type) {
        // Crea file temporaneo per test
        $test_content = $this->createTestCsvContent($expected_type);
        $temp_file = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($temp_file, $test_content);
        
        try {
            // Test detection usando reflection per accedere al metodo privato
            $reflection = new ReflectionClass($smart_parser);
            $detect_method = $reflection->getMethod('detectFileType');
            $detect_method->setAccessible(true);
            
            $detected_type = $detect_method->invoke($smart_parser, $temp_file, null);
            
            $success = ($detected_type === $expected_type);
            $status = $success ? '✅ OK' : '❌ FAIL';
            $color = $success ? 'green' : 'red';
            $note = $success ? "Rilevato: $detected_type" : "Atteso: $expected_type, Rilevato: $detected_type";
            
            echo "<tr>\n";
            echo "<td>$filename</td>\n";
            echo "<td>$expected_type</td>\n";
            echo "<td style='color: $color;'>$status</td>\n";
            echo "<td>$note</td>\n";
            echo "</tr>\n";
            
            $test_results['file_type_detection'][$filename] = [
                'expected' => $expected_type,
                'detected' => $detected_type,
                'success' => $success
            ];
            
        } catch (Exception $e) {
            echo "<tr>\n";
            echo "<td>$filename</td>\n";
            echo "<td>$expected_type</td>\n";
            echo "<td style='color: red;'>❌ ERRORE</td>\n";
            echo "<td>" . htmlspecialchars($e->getMessage()) . "</td>\n";
            echo "</tr>\n";
            
            $test_results['file_type_detection'][$filename] = [
                'expected' => $expected_type,
                'error' => $e->getMessage(),
                'success' => false
            ];
        }
        
        // Cleanup
        unlink($temp_file);
    }
    echo "</table>\n";
    
    // 4. Test Riconoscimento Dipendenti (solo dipendenti fissi)
    echo "<h3>4. 👥 Test Riconoscimento Dipendenti Fissi</h3>\n";
    
    // Prima ottieni lista dipendenti master
    $stmt = $conn->prepare("SELECT nome, cognome, nome_completo FROM master_dipendenti_fixed WHERE attivo = 1 ORDER BY cognome");
    $stmt->execute();
    $master_employees = $stmt->fetchAll();
    
    if (empty($master_employees)) {
        echo "<p style='color: red;'><strong>❌ Nessun dipendente master trovato!</strong> Eseguire prima setup master schema.</p>\n";
    } else {
        echo "<p><strong>Dipendenti Master Disponibili (" . count($master_employees) . "):</strong></p>\n";
        echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>\n";
        foreach ($master_employees as $emp) {
            echo "<span style='display: inline-block; margin: 2px 5px; padding: 3px 8px; background: #e9ecef; border-radius: 3px; font-size: 0.9em;'>";
            echo htmlspecialchars($emp['nome_completo']);
            echo "</span>\n";
        }
        echo "</div>\n";
        
        // Test recognition con vari formati
        $test_names = [
            'Franco Fiorellino' => 'exact_match',
            'franco fiorellino' => 'case_insensitive',
            'Franco Fiorellino/Matteo Signo' => 'multiple_names',
            'Matteo Signo, Franco Fiorellino' => 'comma_separated',
            'Niccolò Ragusa' => 'exact_match',
            'Andrea Bianchi' => 'not_in_master', // Questo non dovrebbe essere riconosciuto
            'Punto' => 'vehicle_name', // Questo non dovrebbe essere riconosciuto
            '' => 'empty_name'
        ];
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Nome Test</th><th>Tipo Test</th><th>Risultato</th><th>Note</th></tr>\n";
        
        foreach ($test_names as $test_name => $test_type) {
            try {
                $reflection = new ReflectionClass($smart_parser);
                $method = $reflection->getMethod('getSmartEmployeeId');
                $method->setAccessible(true);
                
                $result = $method->invoke($smart_parser, $test_name);
                
                $expected_result = null;
                switch ($test_type) {
                    case 'exact_match':
                    case 'case_insensitive':
                    case 'multiple_names':
                    case 'comma_separated':
                        $expected_result = 'should_find';
                        break;
                    case 'not_in_master':
                    case 'vehicle_name':
                    case 'empty_name':
                        $expected_result = 'should_not_find';
                        break;
                }
                
                $success = false;
                $note = '';
                
                if ($expected_result === 'should_find' && $result !== null) {
                    $success = true;
                    $note = "Trovato ID: $result";
                } elseif ($expected_result === 'should_not_find' && $result === null) {
                    $success = true;
                    $note = "Correttamente non riconosciuto";
                } else {
                    $note = $result !== null ? "Trovato ID: $result (non atteso)" : "Non trovato (atteso)";
                }
                
                $status = $success ? '✅ OK' : '❌ FAIL';
                $color = $success ? 'green' : 'red';
                
                echo "<tr>\n";
                echo "<td>'" . htmlspecialchars($test_name) . "'</td>\n";
                echo "<td>$test_type</td>\n";
                echo "<td style='color: $color;'>$status</td>\n";
                echo "<td>$note</td>\n";
                echo "</tr>\n";
                
                $test_results['employee_recognition'][$test_name] = [
                    'type' => $test_type,
                    'result' => $result,
                    'success' => $success
                ];
                
            } catch (Exception $e) {
                echo "<tr>\n";
                echo "<td>'" . htmlspecialchars($test_name) . "'</td>\n";
                echo "<td>$test_type</td>\n";
                echo "<td style='color: red;'>❌ ERRORE</td>\n";
                echo "<td>" . htmlspecialchars($e->getMessage()) . "</td>\n";
                echo "</tr>\n";
                
                $test_results['employee_recognition'][$test_name] = [
                    'type' => $test_type,
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }
        echo "</table>\n";
    }
    
    // 5. Test Associazione Aziende
    echo "<h3>5. 🏢 Test Associazione Aziende</h3>\n";
    
    $stmt = $conn->prepare("SELECT nome, nome_breve FROM master_aziende WHERE attivo = 1 ORDER BY nome");
    $stmt->execute();
    $master_companies = $stmt->fetchAll();
    
    if (!empty($master_companies)) {
        echo "<p><strong>Aziende Master Disponibili (" . count($master_companies) . "):</strong></p>\n";
        echo "<ul>\n";
        foreach (array_slice($master_companies, 0, 5) as $comp) { // Mostra solo prime 5
            echo "<li><strong>{$comp['nome']}</strong>";
            if ($comp['nome_breve']) echo " ({$comp['nome_breve']})";
            echo "</li>\n";
        }
        if (count($master_companies) > 5) {
            echo "<li><em>... e altre " . (count($master_companies) - 5) . " aziende</em></li>\n";
        }
        echo "</ul>\n";
        
        // Test associazione
        $test_companies = [
            'ITX ITALIA SRL' => 'exact_match',
            'itx italia srl' => 'case_insensitive', 
            'ITX' => 'short_name',
            'Nuova Azienda Test' => 'not_in_master',
            '' => 'empty_name'
        ];
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Nome Azienda</th><th>Tipo Test</th><th>Risultato</th><th>Note</th></tr>\n";
        
        foreach ($test_companies as $company_name => $test_type) {
            try {
                $reflection = new ReflectionClass($smart_parser);
                $method = $reflection->getMethod('getSmartCompanyId');
                $method->setAccessible(true);
                
                $result = $method->invoke($smart_parser, $company_name);
                
                $expected_result = in_array($test_type, ['exact_match', 'case_insensitive', 'short_name']) ? 'should_find' : 'should_not_find';
                
                $success = false;
                $note = '';
                
                if ($expected_result === 'should_find' && $result !== null) {
                    $success = true;
                    $note = "Trovato ID: $result";
                } elseif ($expected_result === 'should_not_find' && $result === null) {
                    $success = true;
                    $note = "Correttamente non trovato";
                } else {
                    $note = $result !== null ? "Trovato ID: $result" : "Non trovato";
                }
                
                $status = $success ? '✅ OK' : '❌ FAIL';
                $color = $success ? 'green' : 'red';
                
                echo "<tr>\n";
                echo "<td>'" . htmlspecialchars($company_name) . "'</td>\n";
                echo "<td>$test_type</td>\n";
                echo "<td style='color: $color;'>$status</td>\n";
                echo "<td>$note</td>\n";
                echo "</tr>\n";
                
                $test_results['company_association'][$company_name] = [
                    'type' => $test_type,
                    'result' => $result,
                    'success' => $success
                ];
                
            } catch (Exception $e) {
                echo "<tr>\n";
                echo "<td>'" . htmlspecialchars($company_name) . "'</td>\n";
                echo "<td>$test_type</td>\n";
                echo "<td style='color: red;'>❌ ERRORE</td>\n";
                echo "<td>" . htmlspecialchars($e->getMessage()) . "</td>\n";
                echo "</tr>\n";
            }
        }
        echo "</table>\n";
    }
    
    // 6. Summary Generale
    echo "<h3>6. 📊 Summary Test Smart Parser</h3>\n";
    
    $total_tests = 0;
    $passed_tests = 0;
    
    // Conta test passati
    foreach ($test_results as $category => $results) {
        if ($category === 'parser_instantiation') {
            $total_tests++;
            if ($results) $passed_tests++;
        } elseif (is_array($results)) {
            foreach ($results as $test) {
                $total_tests++;
                if (isset($test['success']) && $test['success']) {
                    $passed_tests++;
                } elseif (isset($test['status']) && $test['status'] === 'OK') {
                    $passed_tests++;
                }
            }
        }
    }
    
    $success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 1) : 0;
    
    $status_color = 'red';
    $status_text = 'CRITICO';
    
    if ($success_rate >= 90) {
        $status_color = 'green';
        $status_text = 'ECCELLENTE';
    } elseif ($success_rate >= 75) {
        $status_color = 'blue';
        $status_text = 'BUONO';
    } elseif ($success_rate >= 60) {
        $status_color = 'orange';
        $status_text = 'ACCETTABILE';
    }
    
    echo "<div style='background: " . ($status_color === 'green' ? '#d4edda' : ($status_color === 'blue' ? '#cce5ff' : ($status_color === 'orange' ? '#fff3cd' : '#f8d7da'))) . "; padding: 20px; border: 2px solid $status_color; border-radius: 10px;'>\n";
    echo "<h4 style='color: $status_color;'>🎯 SMART PARSER STATUS: $status_text ($success_rate%)</h4>\n";
    echo "<p><strong>Test Passati:</strong> $passed_tests/$total_tests</p>\n";
    
    if ($success_rate >= 75) {
        echo "<h5>✅ Smart Parser Pronto per Uso</h5>\n";
        echo "<ul>\n";
        echo "<li>Master Data correttamente configurati</li>\n";
        echo "<li>Riconoscimento dipendenti fissi funzionante</li>\n";
        echo "<li>Associazione aziende operativa</li>\n";
        echo "<li>Sistema di rilevamento tipo file attivo</li>\n";
        echo "</ul>\n";
        
        echo "<h5>🚀 Prossimi Passi:</h5>\n";
        echo "<ol>\n";
        echo "<li><a href='#'>Test con file CSV reali</a></li>\n";
        echo "<li><a href='#'>Implementare UI Management Console</a></li>\n";
        echo "<li><a href='#'>Setup sistema association queue</a></li>\n";
        echo "</ol>\n";
        
    } else {
        echo "<h5>⚠️ Problemi da Risolvere</h5>\n";
        echo "<p>Il Smart Parser necessita di correzioni prima dell'uso.</p>\n";
        
        if (!$test_results['parser_instantiation']) {
            echo "<p>🔧 <strong>Azione richiesta:</strong> Correggere errori istanziazione SmartCsvParser</p>\n";
        }
        
        $master_issues = 0;
        foreach ($test_results['master_data_check'] as $table => $result) {
            if (isset($result['status']) && $result['status'] !== 'OK') {
                $master_issues++;
            }
        }
        
        if ($master_issues > 0) {
            echo "<p>📊 <strong>Azione richiesta:</strong> Completare setup master schema ($master_issues tabelle problematiche)</p>\n";
        }
    }
    
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante test Smart Parser</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

/**
 * Helper function per creare contenuto CSV di test
 */
function createTestCsvContent($type) {
    switch ($type) {
        case 'attivita':
            return "Creato da,Azienda,Durata,Iniziata il,Conclusa il,Descrizione,Id Ticket,Riferimento Progetto\n" .
                   "Franco Fiorellino,ITX ITALIA SRL,2.5,01/01/2024 09:00,01/01/2024 11:30,Test attività,T001,PRJ001\n";
        
        case 'timbrature':
            return "dipendente,data,ora_inizio,ora_fine,ore_totali\n" .
                   "Franco Fiorellino,2024-01-01,09:00,17:00,8.0\n";
        
        case 'teamviewer':
            return "dipendente,computer,data_inizio,durata_minuti,cliente\n" .
                   "Franco Fiorellino,PC-CLIENT-01,2024-01-01 10:00,30,ITX ITALIA SRL\n";
        
        default:
            return "col1,col2,col3\nval1,val2,val3\n";
    }
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; }
    h2, h3, h4 { color: #333; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; font-weight: bold; }
    ul, ol { margin: 10px 0; padding-left: 20px; }
</style>

<p>
    <a href="setup_master_schema.php">🏗️ Setup Master Schema</a> | 
    <a href="execute_phase1_fixes.php">🔧 Fase 1 Fixes</a> | 
    <a href="index.php">🏠 Dashboard</a>
</p>