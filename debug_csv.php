<?php
// Debug diretto del CSV TeamViewer
echo "<h2>Debug Diretto CSV TeamViewer</h2>";

$filepath = 'file-orig-300625/teamviewer_bait.csv';

if (file_exists($filepath)) {
    $handle = fopen($filepath, 'r');
    
    // Detect separator first
    $first_line = fgets($handle);
    rewind($handle);
    
    $separators = [',', ';', "\t", '|'];
    $separator_counts = [];
    
    foreach ($separators as $sep) {
        $count = substr_count($first_line, $sep);
        $separator_counts[$sep] = $count;
    }
    
    $best_separator = array_search(max($separator_counts), $separator_counts);
    
    echo "<h3>Separator Detection</h3>";
    echo "First line: " . htmlspecialchars(substr($first_line, 0, 100)) . "...<br>";
    echo "Separator counts: " . json_encode($separator_counts) . "<br>";
    echo "Detected separator: '$best_separator'<br><br>";
    
    // Read header with correct separator
    $header = fgetcsv($handle, 0, $best_separator);
    echo "<h3>Header Originale</h3>";
    foreach ($header as $i => $col) {
        echo "[$i] '" . htmlspecialchars($col) . "' (len: " . strlen($col) . ", hex: " . bin2hex(substr($col, 0, 10)) . ")<br>";
    }
    
    // Clean header (simulate BOM removal)
    $cleaned_header = [];
    foreach ($header as $col) {
        $cleaned = trim($col, " \t\n\r\0\x0B\xEF\xBB\xBF\"'");
        $cleaned_header[] = $cleaned;
    }
    
    echo "<h3>Header Pulito</h3>";
    foreach ($cleaned_header as $i => $col) {
        echo "[$i] '" . htmlspecialchars($col) . "'<br>";
    }
    
    // Read first data row
    $first_row = fgetcsv($handle, 0, $best_separator);
    echo "<h3>Prima Riga Dati</h3>";
    foreach ($first_row as $i => $val) {
        echo "[$i] '" . htmlspecialchars($val) . "'<br>";
    }
    
    // Combine
    $data = array_combine($cleaned_header, $first_row);
    echo "<h3>Array Combinato</h3>";
    foreach ($data as $key => $val) {
        echo "['$key'] = '" . htmlspecialchars($val) . "'<br>";
    }
    
    // Test getValueSafe logic
    echo "<h3>Test getValueSafe Logic</h3>";
    
    $test_keys = ['Assegnatario', 'Utente'];
    foreach ($test_keys as $key) {
        echo "<strong>Testing key '$key':</strong><br>";
        
        if (isset($data[$key])) {
            $raw_value = $data[$key];
            $cleaned_value = trim($raw_value, " \t\n\r\0\x0B\"'");
            
            echo "- Raw value: '" . htmlspecialchars($raw_value) . "'<br>";
            echo "- Cleaned value: '" . htmlspecialchars($cleaned_value) . "'<br>";
            echo "- Empty check: " . (empty($cleaned_value) ? 'TRUE (empty)' : 'FALSE (not empty)') . "<br>";
            echo "- Length: " . strlen($cleaned_value) . "<br>";
        } else {
            echo "- Key not found in array<br>";
        }
        echo "<br>";
    }
    
    fclose($handle);
    
} else {
    echo "<p style='color: red;'>File non trovato: $filepath</p>";
}

echo "<p><a href='test_teamviewer.php'>Test TeamViewer</a> | <a href='upload.php'>Upload</a></p>";
?>