<?php
require_once 'config/Database.php';

echo "<h2>🔄 Migrazione Master Tables - Esecuzione Schema</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Leggi il file SQL
    $sql_content = file_get_contents('create_master_tables.sql');
    if (!$sql_content) {
        throw new Exception("Impossibile leggere il file create_master_tables.sql");
    }
    
    echo "<h3>📋 Contenuto Migrazione</h3>\n";
    echo "<p><strong>File SQL:</strong> create_master_tables.sql</p>\n";
    echo "<p><strong>Dimensione:</strong> " . strlen($sql_content) . " bytes</p>\n";
    
    // Divide il contenuto in statements separati
    $statements = explode(';', $sql_content);
    $executed_statements = 0;
    $errors = [];
    
    echo "<h3>🚀 Esecuzione Statements</h3>\n";
    echo "<div style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; border-radius: 5px; margin: 10px 0;'>\n";
    
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        
        // Salta statements vuoti e commenti
        if (empty($statement) || 
            strpos($statement, '--') === 0 || 
            strpos($statement, '/*') === 0 ||
            in_array(strtoupper($statement), ['SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"', 'START TRANSACTION', 'COMMIT'])) {
            continue;
        }
        
        try {
            $conn->exec($statement);
            $executed_statements++;
            echo "<p style='color: green;'>✅ Statement " . ($index + 1) . " eseguito con successo</p>\n";
            
            // Mostra info su cosa è stato creato
            if (strpos($statement, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE `([^`]+)`/', $statement, $matches);
                if (isset($matches[1])) {
                    echo "<p style='margin-left: 20px;'><strong>Creata tabella:</strong> {$matches[1]}</p>\n";
                }
            } elseif (strpos($statement, 'ALTER TABLE') !== false) {
                preg_match('/ALTER TABLE `([^`]+)`/', $statement, $matches);
                if (isset($matches[1])) {
                    echo "<p style='margin-left: 20px;'><strong>Alterata tabella:</strong> {$matches[1]}</p>\n";
                }
            } elseif (strpos($statement, 'CREATE VIEW') !== false) {
                preg_match('/CREATE VIEW `([^`]+)`/', $statement, $matches);
                if (isset($matches[1])) {
                    echo "<p style='margin-left: 20px;'><strong>Creata vista:</strong> {$matches[1]}</p>\n";
                }
            } elseif (strpos($statement, 'CREATE TRIGGER') !== false) {
                preg_match('/CREATE TRIGGER `([^`]+)`/', $statement, $matches);
                if (isset($matches[1])) {
                    echo "<p style='margin-left: 20px;'><strong>Creato trigger:</strong> {$matches[1]}</p>\n";
                }
            } elseif (strpos($statement, 'CREATE INDEX') !== false) {
                preg_match('/CREATE INDEX `([^`]+)`/', $statement, $matches);
                if (isset($matches[1])) {
                    echo "<p style='margin-left: 20px;'><strong>Creato indice:</strong> {$matches[1]}</p>\n";
                }
            }
            
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
            $errors[] = "Statement " . ($index + 1) . ": " . $error_msg;
            echo "<p style='color: red;'>❌ Errore Statement " . ($index + 1) . ": " . htmlspecialchars($error_msg) . "</p>\n";
            
            // Mostra parte dello statement che ha causato l'errore
            $preview = substr($statement, 0, 100) . (strlen($statement) > 100 ? '...' : '');
            echo "<p style='margin-left: 20px; color: #666;'><small>Statement: " . htmlspecialchars($preview) . "</small></p>\n";
        }
    }
    
    echo "</div>\n";
    
    // Verifica tabelle create
    echo "<h3>🔍 Verifica Tabelle Create</h3>\n";
    
    $expected_tables = [
        'master_dipendenti' => 'Dipendenti consolidati',
        'master_veicoli' => 'Veicoli aziendali',
        'master_clienti' => 'Clienti consolidati',
        'master_progetti' => 'Progetti aziendali',
        'dipendenti_aliases' => 'Alias dipendenti',
        'clienti_aliases' => 'Alias clienti'
    ];
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Tabella</th><th>Descrizione</th><th>Stato</th><th>Record</th></tr>\n";
    
    foreach ($expected_tables as $table => $description) {
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM `$table`");
                $stmt->execute();
                $count = $stmt->fetch()['count'];
                
                echo "<tr>\n";
                echo "<td><strong>$table</strong></td>\n";
                echo "<td>$description</td>\n";
                echo "<td style='color: green;'>✅ Creata</td>\n";
                echo "<td>$count record</td>\n";
                echo "</tr>\n";
            } else {
                echo "<tr>\n";
                echo "<td><strong>$table</strong></td>\n";
                echo "<td>$description</td>\n";
                echo "<td style='color: red;'>❌ Mancante</td>\n";
                echo "<td>-</td>\n";
                echo "</tr>\n";
            }
        } catch (Exception $e) {
            echo "<tr>\n";
            echo "<td><strong>$table</strong></td>\n";
            echo "<td>$description</td>\n";
            echo "<td style='color: red;'>❌ Errore</td>\n";
            echo "<td>" . htmlspecialchars($e->getMessage()) . "</td>\n";
            echo "</tr>\n";
        }
    }
    echo "</table>\n";
    
    // Verifica viste
    echo "<h3>🔍 Verifica Viste Create</h3>\n";
    
    $expected_views = [
        'v_dipendenti_unified' => 'Vista unificata dipendenti',
        'v_clienti_unified' => 'Vista unificata clienti'
    ];
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Vista</th><th>Descrizione</th><th>Stato</th></tr>\n";
    
    foreach ($expected_views as $view => $description) {
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$view]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                echo "<tr>\n";
                echo "<td><strong>$view</strong></td>\n";
                echo "<td>$description</td>\n";
                echo "<td style='color: green;'>✅ Creata</td>\n";
                echo "</tr>\n";
            } else {
                echo "<tr>\n";
                echo "<td><strong>$view</strong></td>\n";
                echo "<td>$description</td>\n";
                echo "<td style='color: red;'>❌ Mancante</td>\n";
                echo "</tr>\n";
            }
        } catch (Exception $e) {
            echo "<tr>\n";
            echo "<td><strong>$view</strong></td>\n";
            echo "<td>$description</td>\n";
            echo "<td style='color: red;'>❌ Errore: " . htmlspecialchars($e->getMessage()) . "</td>\n";
            echo "</tr>\n";
        }
    }
    echo "</table>\n";
    
    // Verifica trigger
    echo "<h3>🔍 Verifica Trigger Creati</h3>\n";
    
    try {
        $stmt = $conn->prepare("SHOW TRIGGERS");
        $stmt->execute();
        $triggers = $stmt->fetchAll();
        
        if (!empty($triggers)) {
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
            echo "<tr><th>Trigger</th><th>Tabella</th><th>Evento</th><th>Timing</th></tr>\n";
            
            foreach ($triggers as $trigger) {
                echo "<tr>\n";
                echo "<td><strong>{$trigger['Trigger']}</strong></td>\n";
                echo "<td>{$trigger['Table']}</td>\n";
                echo "<td>{$trigger['Event']}</td>\n";
                echo "<td>{$trigger['Timing']}</td>\n";
                echo "</tr>\n";
            }
            echo "</table>\n";
        } else {
            echo "<p>Nessun trigger trovato.</p>\n";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Errore nella verifica trigger: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    // Summary finale
    echo "<h3>📊 Summary Migrazione</h3>\n";
    
    if (empty($errors)) {
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>\n";
        echo "<h4>✅ Migrazione Master Tables Completata con Successo!</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>Statements eseguiti:</strong> $executed_statements</li>\n";
        echo "<li><strong>Tabelle master:</strong> create con successo</li>\n";
        echo "<li><strong>Viste di compatibilità:</strong> create con successo</li>\n";
        echo "<li><strong>Trigger automatici:</strong> attivati per sincronizzazione</li>\n";
        echo "<li><strong>Indici performance:</strong> creati per ricerche veloci</li>\n";
        echo "</ul>\n";
        echo "<p><strong>🎯 Prossimo Step:</strong> Procedere con Data Seeding per popolare le tabelle master con dipendenti e veicoli noti.</p>\n";
        echo "</div>\n";
    } else {
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
        echo "<h4>⚠️ Migrazione Completata con Avvisi</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>Statements eseguiti:</strong> $executed_statements</li>\n";
        echo "<li><strong>Errori riscontrati:</strong> " . count($errors) . "</li>\n";
        echo "</ul>\n";
        echo "<h5>Errori dettagliati:</h5>\n";
        echo "<ul>\n";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante la migrazione</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}
?>

<p>
    <a href="populate_master_tables.php">📊 Popolare Tabelle Master</a> | 
    <a href="analyze_csv_patterns.php">🔍 Analizza Pattern CSV</a> | 
    <a href="index.php">Dashboard</a>
</p>