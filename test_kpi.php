<?php
// Test rapido KPI Calculator
require_once 'config/Database.php';

echo "<h2>Test KPI Calculator</h2>";

try {
    $database = new Database();
    
    if (!$database->databaseExists()) {
        echo "<p style='color: orange;'>⚠ Database non trovato. Creo il database...</p>";
        $database->createDatabase();
        echo "<p style='color: green;'>✓ Database creato</p>";
    }
    
    echo "<p><strong>1. Test connessione:</strong> ";
    $conn = $database->getConnection();
    echo "<span style='color: green;'>✓ OK</span></p>";
    
    echo "<p><strong>2. Test caricamento KpiCalculator:</strong> ";
    require_once 'classes/KpiCalculator.php';
    $kpiCalculator = new KpiCalculator();
    echo "<span style='color: green;'>✓ KpiCalculator caricato</span></p>";
    
    echo "<p><strong>3. Test getTopPerformers (dovrebbe essere vuoto):</strong> ";
    $topPerformers = $kpiCalculator->getTopPerformers(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'), 5);
    echo "<span style='color: green;'>✓ Query eseguita - Risultati: " . count($topPerformers) . "</span></p>";
    
    echo "<p><strong>4. Test getKpiSummary:</strong> ";
    $summary = $kpiCalculator->getKpiSummary(null, date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
    echo "<span style='color: green;'>✓ Query eseguita</span></p>";
    
    echo "<p><strong>5. Test getAlertsCount:</strong> ";
    $alerts = $kpiCalculator->getAlertsCount(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
    echo "<span style='color: green;'>✓ Query eseguita - Alert: " . array_sum($alerts) . "</span></p>";
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Test KPI completato con successo!</strong><br>";
    echo "- Top Performers: " . count($topPerformers) . "<br>";
    echo "- Alert totali: " . array_sum($alerts) . "<br>";
    echo "- Sistema pronto per l'uso";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Errore:</strong> " . $e->getMessage() . "</p>";
    
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Errore nel test KPI:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<p><a href='index.php'>Prova Dashboard</a> | <a href='upload.php'>Vai al Upload</a></p>";
?>