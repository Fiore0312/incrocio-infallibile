<?php
require_once 'config/Database.php';
require_once 'classes/DeduplicationEngine.php';

echo "<h2>🧹 Cleanup Massivo Duplicati Esistenti</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $deduplication = new DeduplicationEngine([
        'time_threshold_minutes' => 2,
        'similarity_threshold' => 0.9,
        'enable_soft_deduplication' => true
    ]);
    
    echo "<h3>📊 Analisi Pre-Cleanup</h3>\n";
    $analysis = $deduplication->analyzeDuplicatesInDatabase();
    
    if ($analysis) {
        echo "<p><strong>Totale Attività:</strong> {$analysis['total_activities']}</p>\n";
        echo "<p><strong>Duplicati Potenziali:</strong> {$analysis['potential_duplicates']}</p>\n";
        echo "<p><strong>Percentuale Duplicati:</strong> " . round(($analysis['potential_duplicates'] / max(1, $analysis['total_activities'])) * 100, 2) . "%</p>\n";
    }
    
    // Prima esegui DRY RUN
    echo "<h3>🧪 DRY RUN - Simulazione Cleanup</h3>\n";
    $dry_run_stats = $deduplication->cleanupExistingDuplicates(true);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Metrica</th><th>Valore</th></tr>\n";
    echo "<tr><td>Gruppi Analizzati</td><td>{$dry_run_stats['analyzed']}</td></tr>\n";
    echo "<tr><td>Duplicati da Marcare</td><td>{$dry_run_stats['marked_as_duplicates']}</td></tr>\n";
    echo "<tr><td>Errori</td><td>{$dry_run_stats['errors']}</td></tr>\n";
    echo "</table>\n";
    
    if (isset($_GET['execute']) && $_GET['execute'] === 'yes') {
        echo "<h3>🚀 ESECUZIONE EFFETTIVA Cleanup</h3>\n";
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
        echo "<p><strong>⚠️ ATTENZIONE:</strong> Questo modificherà il database!</p>\n";
        echo "</div>\n";
        
        $real_stats = $deduplication->cleanupExistingDuplicates(false);
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Operazione</th><th>Risultato</th></tr>\n";
        echo "<tr><td>Duplicati Marcati</td><td style='color: green; font-weight: bold;'>{$real_stats['marked_as_duplicates']}</td></tr>\n";
        echo "<tr><td>Errori</td><td>{$real_stats['errors']}</td></tr>\n";
        echo "</table>\n";
        
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>\n";
        echo "<h4>✅ Cleanup Completato!</h4>\n";
        echo "<p>I duplicati sono stati marcati come <code>is_duplicate = 1</code> ma non eliminati.</p>\n";
        echo "<p>Per vedere solo le attività originali, usare: <code>WHERE is_duplicate = 0</code></p>\n";
        echo "</div>\n";
        
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
        echo "<h4>⚠️ Per Eseguire il Cleanup Effettivo</h4>\n";
        echo "<p>Questa è solo una simulazione. Per eseguire il cleanup reale:</p>\n";
        echo "<p><a href='?execute=yes' onclick='return confirm(\\"Sei sicuro di voler eseguire il cleanup? Questa operazione modificherà il database!\\")'>";
        echo "<strong>🚀 ESEGUI CLEANUP REALE</strong></a></p>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante cleanup</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}
?>

<p>
    <a href="enhanced_upload.php">🚀 Test Enhanced Upload</a> | 
    <a href="test_deduplication_engine.php">🧪 Test Deduplication</a> | 
    <a href="index.php">Dashboard</a>
</p>