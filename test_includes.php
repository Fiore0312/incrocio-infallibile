<?php
// Test rapido degli include
echo "<h2>Test Include Files</h2>";

echo "<p><strong>1. Test CsvParser:</strong> ";
try {
    require_once 'classes/CsvParser.php';
    echo "<span style='color: green;'>✓ CsvParser caricato correttamente</span></p>";
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Errore: " . $e->getMessage() . "</span></p>";
}

echo "<p><strong>2. Test KpiCalculator:</strong> ";
try {
    require_once 'classes/KpiCalculator.php';
    echo "<span style='color: green;'>✓ KpiCalculator caricato correttamente</span></p>";
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Errore: " . $e->getMessage() . "</span></p>";
}

echo "<p><strong>3. Test ValidationEngine:</strong> ";
try {
    require_once 'classes/ValidationEngine.php';
    echo "<span style='color: green;'>✓ ValidationEngine caricato correttamente</span></p>";
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Errore: " . $e->getMessage() . "</span></p>";
}

echo "<p><strong>4. Test API performance_data:</strong> ";
try {
    ob_start();
    include 'api/performance_data.php';
    $output = ob_get_clean();
    echo "<span style='color: green;'>✓ API performance_data caricata</span></p>";
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Errore: " . $e->getMessage() . "</span></p>";
}

echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
echo "<strong>Test completato!</strong> Se tutti i test sono verdi, gli include funzionano correttamente.";
echo "</div>";

echo "<p><a href='upload.php'>Prova Upload</a> | <a href='index.php'>Torna al Dashboard</a></p>";
?>