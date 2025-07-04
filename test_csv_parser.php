<?php
// Test rapido CSV Parser con encoding fix
require_once 'classes/CsvParser.php';

echo "<h2>Test CSV Parser - Encoding Fix</h2>";

try {
    $parser = new CsvParser();
    
    echo "<p><strong>1. Test creazione CsvParser:</strong> ";
    echo "<span style='color: green;'>✓ Parser creato</span></p>";
    
    echo "<p><strong>2. Test processamento file (1 file per volta):</strong></p>";
    
    $test_files = [
        'progetti.csv' => 'progetti',
        'teamviewer_bait.csv' => 'teamviewer_bait',
        'teamviewer_gruppo.csv' => 'teamviewer_gruppo'
    ];
    
    foreach ($test_files as $filename => $type) {
        $filepath = 'file-orig-300625/' . $filename;
        
        if (file_exists($filepath)) {
            echo "<p><strong>- Testing $filename:</strong> ";
            
            $result = $parser->parseFile($filepath, $type);
            
            if ($result['success']) {
                echo "<span style='color: green;'>✓ OK - " . 
                     "Processati: {$result['stats']['processed']}, " . 
                     "Inseriti: {$result['stats']['inserted']}, " . 
                     "Skipped: {$result['stats']['skipped']}</span>";
                
                if (!empty($result['warnings'])) {
                    echo "<br><span style='color: orange;'>⚠ Warnings: " . count($result['warnings']) . "</span>";
                }
                if (!empty($result['errors'])) {
                    echo "<br><span style='color: red;'>⚠ Errors: " . count($result['errors']) . "</span>";
                }
            } else {
                echo "<span style='color: red;'>✗ ERRORE: " . ($result['error'] ?? 'Sconosciuto') . "</span>";
            }
            echo "</p>";
            
        } else {
            echo "<p><strong>- $filename:</strong> <span style='color: orange;'>File non trovato</span></p>";
        }
    }
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Test completato!</strong><br>";
    echo "Il parser dovrebbe ora gestire correttamente encoding e colonne mancanti.";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Errore nel test:</strong> " . $e->getMessage() . "</p>";
}

echo "<p><a href='upload.php'>Prova Upload Completo</a> | <a href='index.php'>Torna al Dashboard</a></p>";
?>