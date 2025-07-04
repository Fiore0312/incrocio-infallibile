<?php
// Test rapido connessione database
require_once 'config/Database.php';

echo "<h2>Test Connessione Database</h2>";

try {
    $database = new Database();
    
    echo "<p><strong>1. Test connessione MySQL (senza database):</strong> ";
    $conn = $database->getConnectionWithoutDatabase();
    echo "<span style='color: green;'>✓ OK</span></p>";
    
    echo "<p><strong>2. Verifica esistenza database:</strong> ";
    if ($database->databaseExists()) {
        echo "<span style='color: green;'>✓ Database employee_analytics trovato</span></p>";
        
        echo "<p><strong>3. Test connessione al database:</strong> ";
        $conn = $database->getConnection();
        echo "<span style='color: green;'>✓ Connessione riuscita</span></p>";
        
        echo "<p><strong>4. Test query di base:</strong> ";
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM dipendenti");
        $stmt->execute();
        $result = $stmt->fetch();
        echo "<span style='color: green;'>✓ Query eseguita - Dipendenti trovati: " . $result['count'] . "</span></p>";
        
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>Sistema pronto!</strong> Puoi procedere con il caricamento dei dati.";
        echo "</div>";
        
    } else {
        echo "<span style='color: orange;'>⚠ Database non trovato</span></p>";
        
        echo "<p><strong>3. Creazione database:</strong> ";
        $database->createDatabase();
        echo "<span style='color: green;'>✓ Database creato con successo</span></p>";
        
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>Database creato!</strong> Ora puoi utilizzare il sistema.";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Errore:</strong> " . $e->getMessage() . "</p>";
    
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Possibili soluzioni:</strong><br>";
    echo "1. Verificare che MySQL sia in esecuzione<br>";
    echo "2. Controllare le credenziali in config/Database.php<br>";
    echo "3. Verificare i permessi dell'utente MySQL<br>";
    echo "</div>";
}

echo "<p><a href='index.php'>← Torna al Dashboard</a> | <a href='setup.php'>Vai al Setup</a></p>";
?>