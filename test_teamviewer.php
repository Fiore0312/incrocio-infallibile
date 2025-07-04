<?php
// Test specifico per TeamViewer import
require_once 'classes/CsvParser.php';

echo "<h2>Test TeamViewer Import</h2>";

try {
    $parser = new CsvParser();
    
    echo "<p><strong>1. Test file teamviewer_bait.csv:</strong></p>";
    
    $filepath = 'file-orig-300625/teamviewer_bait.csv';
    
    if (file_exists($filepath)) {
        // First, let's check the actual file header with proper separator detection
        $handle = fopen($filepath, 'r');
        
        // Detect separator
        $first_line = fgets($handle);
        rewind($handle);
        $comma_count = substr_count($first_line, ',');
        $semicolon_count = substr_count($first_line, ';');
        $separator = ($comma_count > $semicolon_count) ? ',' : ';';
        
        echo "<p><strong>Separator detected:</strong> '$separator' (comma: $comma_count, semicolon: $semicolon_count)</p>";
        
        $header = fgetcsv($handle, 0, $separator);
        fclose($handle);
        
        echo "<p><strong>Header originale:</strong><br>";
        foreach ($header as $i => $col) {
            $hex = bin2hex($col);
            echo "[$i] '$col' (hex: $hex)<br>";
        }
        echo "</p>";
        
        // Remove BOM manually for display
        $first_col = $header[0];
        if (substr($first_col, 0, 3) === "\xEF\xBB\xBF") {
            $header[0] = substr($first_col, 3);
            echo "<p><strong>BOM rimosso dalla prima colonna:</strong> '{$header[0]}'</p>";
        }
        
        // Test single row parsing
        echo "<p><strong>Test riga singola:</strong><br>";
        $handle = fopen($filepath, 'r');
        $header = fgetcsv($handle, 0, $separator);
        $first_row = fgetcsv($handle, 0, $separator);
        fclose($handle);
        
        if ($first_row) {
            $data = array_combine($header, $first_row);
            if ($data) {
                echo "Header: " . implode(', ', $header) . "<br>";
                echo "First row: " . implode(', ', $first_row) . "<br>";
                echo "Combined data Assegnatario: '" . ($data['Assegnatario'] ?? 'NOT_FOUND') . "'<br>";
                echo "Combined data Utente: '" . ($data['Utente'] ?? 'NOT_FOUND') . "'<br>";
            }
        }
        echo "</p>";
        
        // Now test the parser
        echo "<p><strong>Risultato parsing:</strong><br>";
        $result = $parser->parseFile($filepath, 'teamviewer_bait');
        
        if ($result['success']) {
            echo "<span style='color: green;'>✓ SUCCESS</span><br>";
            echo "Processati: {$result['stats']['processed']}<br>";
            echo "Inseriti: {$result['stats']['inserted']}<br>";
            echo "Aggiornati: {$result['stats']['updated']}<br>";
            echo "Saltati: {$result['stats']['skipped']}<br>";
            
            if (!empty($result['warnings'])) {
                echo "<br><strong>Warnings primi 5:</strong><br>";
                for ($i = 0; $i < min(5, count($result['warnings'])); $i++) {
                    echo "- " . htmlspecialchars($result['warnings'][$i]) . "<br>";
                }
                if (count($result['warnings']) > 5) {
                    echo "... e altri " . (count($result['warnings']) - 5) . " warnings<br>";
                }
            }
            
            if (!empty($result['errors'])) {
                echo "<br><strong>Errors:</strong><br>";
                foreach ($result['errors'] as $error) {
                    echo "- " . htmlspecialchars($error) . "<br>";
                }
            }
            
        } else {
            echo "<span style='color: red;'>✗ FAILED</span><br>";
            echo "Errore: " . htmlspecialchars($result['error'] ?? 'Sconosciuto');
        }
        echo "</p>";
        
        // Check created employees
        require_once 'config/Database.php';
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM dipendenti");
        $stmt->execute();
        $dipendenti_count = $stmt->fetch()['count'];
        
        echo "<p><strong>Dipendenti nel database:</strong> $dipendenti_count</p>";
        
        if ($dipendenti_count > 0) {
            $stmt = $conn->prepare("SELECT nome, cognome FROM dipendenti ORDER BY id DESC LIMIT 5");
            $stmt->execute();
            $recent_dipendenti = $stmt->fetchAll();
            
            echo "<p><strong>Ultimi dipendenti creati:</strong><br>";
            foreach ($recent_dipendenti as $dip) {
                echo "- {$dip['nome']} {$dip['cognome']}<br>";
            }
            echo "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>File teamviewer_bait.csv non trovato</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Errore nel test:</strong> " . $e->getMessage() . "</p>";
}

echo "<p><a href='upload.php'>Prova Upload Completo</a> | <a href='index.php'>Torna al Dashboard</a></p>";
?>