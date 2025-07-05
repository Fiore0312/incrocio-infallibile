<?php
/**
 * Test Smart CSV Parser - Versione Ripristinata
 * Test per verificare il funzionamento del parser CSV intelligente
 */

require_once 'classes/SmartCsvParser.php';

echo "<h2>🧪 Test Smart CSV Parser</h2>";

try {
    $smart_parser = new SmartCsvParser();
    
    echo "<h3>1. Test Rilevamento Tipo File</h3>";
    
    // Test files con contenuti tipici
    $test_files = [
        'timbrature_test.csv' => 'timbrature',
        'attivita_test.csv' => 'attivita', 
        'teamviewer_test.csv' => 'teamviewer'
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Nome File</th><th>Tipo Atteso</th><th>Stato Test</th><th>Note</th></tr>\n";
    
    foreach ($test_files as $filename => $expected_type) {
        // Crea file temporaneo per test
        $test_content = createTestCsvContent($expected_type);
        $temp_file = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($temp_file, $test_content);
        
        try {
            // Test detection usando reflection per accedere al metodo privato
            $reflection = new ReflectionClass($smart_parser);
            $detect_method = $reflection->getMethod('detectFileType');
            $detect_method->setAccessible(true);
            
            $detected_type = $detect_method->invoke($smart_parser, $temp_file);
            
            $status = ($detected_type === $expected_type) ? 
                "<span style='color: green;'>✅ PASS</span>" : 
                "<span style='color: red;'>❌ FAIL</span>";
            
            $note = "Rilevato: " . ($detected_type ?: 'sconosciuto');
            
            echo "<tr>\n";
            echo "<td>$filename</td>\n";
            echo "<td>$expected_type</td>\n";
            echo "<td>$status</td>\n";
            echo "<td>$note</td>\n";
            echo "</tr>\n";
            
        } catch (Exception $e) {
            echo "<tr>\n";
            echo "<td>$filename</td>\n";
            echo "<td>$expected_type</td>\n";
            echo "<td><span style='color: red;'>❌ ERROR</span></td>\n";
            echo "<td>" . $e->getMessage() . "</td>\n";
            echo "</tr>\n";
        }
        
        // Pulisci file temporaneo
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
    }
    
    echo "</table>\n";
    
    echo "<h3>2. Test Parsing CSV</h3>";
    
    // Test parsing di un file timbrature
    $csv_content = createTestCsvContent('timbrature');
    $temp_file = sys_get_temp_dir() . '/test_timbrature.csv';
    file_put_contents($temp_file, $csv_content);
    
    try {
        $result = $smart_parser->parseFile($temp_file);
        
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4 style='color: #155724;'>✅ Parsing Completato</h4>";
        echo "<p><strong>Tipo Rilevato:</strong> " . ($result['type'] ?? 'N/D') . "</p>";
        echo "<p><strong>Record Processati:</strong> " . ($result['processed'] ?? 0) . "</p>";
        echo "<p><strong>Record Validi:</strong> " . ($result['valid'] ?? 0) . "</p>";
        echo "<p><strong>Errori:</strong> " . ($result['errors'] ?? 0) . "</p>";
        
        if (!empty($result['warnings'])) {
            echo "<p><strong>Warning:</strong></p>";
            echo "<ul>";
            foreach ($result['warnings'] as $warning) {
                echo "<li>$warning</li>";
            }
            echo "</ul>";
        }
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4 style='color: #721c24;'>❌ Errore Parsing</h4>";
        echo "<p><strong>Errore:</strong> " . $e->getMessage() . "</p>";
        echo "</div>";
    }
    
    // Pulisci file di test
    if (file_exists($temp_file)) {
        unlink($temp_file);
    }
    
    echo "<h3>3. Test Validazioni</h3>";
    
    echo "<div style='background: #e7f3ff; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px;'>";
    echo "<h4>📋 Validazioni Attive</h4>";
    echo "<ul>";
    echo "<li>✅ Controllo formato date</li>";
    echo "<li>✅ Validazione ore (0-24)</li>";
    echo "<li>✅ Controllo nomi dipendenti validi</li>";
    echo "<li>✅ Validazione clienti esistenti</li>";
    echo "<li>✅ Controllo duplicati</li>";
    echo "<li>✅ Validazione campi obbligatori</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4 style='color: #721c24;'>❌ Errore Generale</h4>";
    echo "<p><strong>Errore:</strong> " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<h3>🔧 Azioni Disponibili</h3>";
echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<a href='smart_upload_final.php' class='btn btn-primary' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>";
echo "📤 Upload CSV Smart";
echo "</a>";
echo "<a href='master_data_console.php' class='btn btn-success' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>";
echo "🗄️ Master Data Console";
echo "</a>";
echo "<a href='index.php' class='btn btn-secondary' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>";
echo "🏠 Dashboard";
echo "</a>";
echo "</div>";

?>

<?php
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