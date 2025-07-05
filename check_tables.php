<?php
require_once 'config/Database.php';

echo "<h2>Verifica Struttura Tabelle</h2>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $tables = ['dipendenti', 'timbrature', 'attivita', 'teamviewer_sessioni', 'kpi_giornalieri', 'configurazioni'];
    
    foreach ($tables as $table) {
        echo "<h3>Tabella: $table</h3>";
        
        try {
            $stmt = $conn->prepare("DESCRIBE $table");
            $stmt->execute();
            $columns = $stmt->fetchAll();
            
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td><strong>{$col['Field']}</strong></td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "<td>{$col['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Count records
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table");
            $stmt->execute();
            $count = $stmt->fetch();
            echo "<p><strong>Records:</strong> {$count['count']}</p>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>Errore: " . $e->getMessage() . "</p>";
        }
        
        echo "<hr>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Errore connessione:</strong> " . $e->getMessage() . "</p>";
}
?>

<p><a href="diagnose_data_master.php">← Torna alla Diagnostica</a></p>