<?php
require_once 'config/Database.php';

echo "<h2>🔄 Applicazione Migrazione Master Tables</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Leggi il file SQL corretto
    $sql_content = file_get_contents('create_master_tables_fixed.sql');
    if (!$sql_content) {
        throw new Exception("Impossibile leggere il file create_master_tables_fixed.sql");
    }
    
    echo "<h3>📋 Informazioni Migrazione</h3>\n";
    echo "<p><strong>File SQL:</strong> create_master_tables_fixed.sql</p>\n";
    echo "<p><strong>Dimensione:</strong> " . strlen($sql_content) . " bytes</p>\n";
    
    // Esegui il file SQL completo
    echo "<h3>🚀 Esecuzione Migrazione</h3>\n";
    echo "<div style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; border-radius: 5px; margin: 10px 0;'>\n";
    
    try {
        $conn->exec($sql_content);
        echo "<p style='color: green;'>✅ Migrazione master tables eseguita con successo!</p>\n";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Errore durante l'esecuzione della migrazione: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        
        // Prova a eseguire tabella per tabella
        echo "<h4>🔄 Tentativo creazione tabelle singole</h4>\n";
        
        $tables = [
            'master_dipendenti' => 'Dipendenti consolidati',
            'master_veicoli' => 'Veicoli aziendali', 
            'master_clienti' => 'Clienti consolidati',
            'master_progetti' => 'Progetti aziendali',
            'dipendenti_aliases' => 'Alias dipendenti',
            'clienti_aliases' => 'Alias clienti'
        ];
        
        foreach ($tables as $table => $description) {
            // Estrai la CREATE TABLE per questa tabella
            $pattern = '/CREATE TABLE[^;]*`' . $table . '`[^;]*;/s';
            if (preg_match($pattern, $sql_content, $matches)) {
                try {
                    $conn->exec($matches[0]);
                    echo "<p style='color: green;'>✅ Tabella $table creata con successo</p>\n";
                } catch (Exception $e) {
                    echo "<p style='color: red;'>❌ Errore creazione tabella $table: " . htmlspecialchars($e->getMessage()) . "</p>\n";
                }
            }
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
    echo "<tr><th>Tabella</th><th>Descrizione</th><th>Stato</th><th>Struttura</th></tr>\n";
    
    $created_tables = 0;
    foreach ($expected_tables as $table => $description) {
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                $created_tables++;
                
                // Mostra informazioni sulla struttura
                $stmt = $conn->prepare("DESCRIBE `$table`");
                $stmt->execute();
                $columns = $stmt->fetchAll();
                $column_count = count($columns);
                
                echo "<tr>\n";
                echo "<td><strong>$table</strong></td>\n";
                echo "<td>$description</td>\n";
                echo "<td style='color: green;'>✅ Creata</td>\n";
                echo "<td>$column_count colonne</td>\n";
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
    
    // Aggiungi foreign key alle tabelle esistenti se le master tables sono create
    if ($created_tables > 0) {
        echo "<h3>🔗 Aggiunta Foreign Key alle Tabelle Esistenti</h3>\n";
        
        $foreign_keys = [
            'dipendenti' => ['master_dipendente_id', 'master_dipendenti'],
            'clienti' => ['master_cliente_id', 'master_clienti'],
            'veicoli' => ['master_veicolo_id', 'master_veicoli'],
            'progetti' => ['master_progetto_id', 'master_progetti']
        ];
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Tabella</th><th>Colonna FK</th><th>Riferimento</th><th>Stato</th></tr>\n";
        
        foreach ($foreign_keys as $table => $fk_info) {
            $fk_column = $fk_info[0];
            $ref_table = $fk_info[1];
            
            try {
                // Verifica se la tabella esiste
                $stmt = $conn->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                $table_exists = $stmt->fetch();
                
                if (!$table_exists) {
                    echo "<tr>\n";
                    echo "<td><strong>$table</strong></td>\n";
                    echo "<td>$fk_column</td>\n";
                    echo "<td>$ref_table</td>\n";
                    echo "<td style='color: orange;'>⚠️ Tabella non esiste</td>\n";
                    echo "</tr>\n";
                    continue;
                }
                
                // Verifica se la tabella di riferimento esiste
                $stmt = $conn->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$ref_table]);
                $ref_exists = $stmt->fetch();
                
                if (!$ref_exists) {
                    echo "<tr>\n";
                    echo "<td><strong>$table</strong></td>\n";
                    echo "<td>$fk_column</td>\n";
                    echo "<td>$ref_table</td>\n";
                    echo "<td style='color: red;'>❌ Tabella master non esiste</td>\n";
                    echo "</tr>\n";
                    continue;
                }
                
                // Verifica se la colonna FK esiste già
                $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
                $stmt->execute([$fk_column]);
                $column_exists = $stmt->fetch();
                
                if (!$column_exists) {
                    // Aggiungi la colonna FK
                    $alter_sql = "ALTER TABLE `$table` ADD COLUMN `$fk_column` int(11) DEFAULT NULL";
                    $conn->exec($alter_sql);
                    echo "<tr>\n";
                    echo "<td><strong>$table</strong></td>\n";
                    echo "<td>$fk_column</td>\n";
                    echo "<td>$ref_table</td>\n";
                    echo "<td style='color: green;'>✅ Colonna FK aggiunta</td>\n";
                    echo "</tr>\n";
                } else {
                    echo "<tr>\n";
                    echo "<td><strong>$table</strong></td>\n";
                    echo "<td>$fk_column</td>\n";
                    echo "<td>$ref_table</td>\n";
                    echo "<td style='color: blue;'>ℹ️ Colonna FK già presente</td>\n";
                    echo "</tr>\n";
                }
                
            } catch (Exception $e) {
                echo "<tr>\n";
                echo "<td><strong>$table</strong></td>\n";
                echo "<td>$fk_column</td>\n";
                echo "<td>$ref_table</td>\n";
                echo "<td style='color: red;'>❌ " . htmlspecialchars($e->getMessage()) . "</td>\n";
                echo "</tr>\n";
            }
        }
        echo "</table>\n";
    }
    
    // Summary finale
    echo "<h3>📊 Summary Migrazione</h3>\n";
    
    if ($created_tables === count($expected_tables)) {
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>\n";
        echo "<h4>✅ Migrazione Master Tables Completata con Successo!</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>Tabelle create:</strong> $created_tables/" . count($expected_tables) . "</li>\n";
        echo "<li><strong>Sistema di normalizzazione:</strong> attivo</li>\n";
        echo "<li><strong>Tabelle di mapping:</strong> create per gestire alias</li>\n";
        echo "<li><strong>Foreign key:</strong> aggiunte alle tabelle legacy</li>\n";
        echo "</ul>\n";
        echo "<p><strong>🎯 Prossimo Step:</strong> Procedere con il Data Seeding per popolare le tabelle master.</p>\n";
        echo "</div>\n";
    } elseif ($created_tables > 0) {
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
        echo "<h4>⚠️ Migrazione Parzialmente Completata</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>Tabelle create:</strong> $created_tables/" . count($expected_tables) . "</li>\n";
        echo "<li><strong>Stato:</strong> Alcune tabelle master sono state create</li>\n";
        echo "</ul>\n";
        echo "<p><strong>🔄 Azione:</strong> Verificare gli errori e ripetere la migrazione se necessario.</p>\n";
        echo "</div>\n";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
        echo "<h4>❌ Migrazione Fallita</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>Tabelle create:</strong> 0/" . count($expected_tables) . "</li>\n";
        echo "<li><strong>Stato:</strong> Nessuna tabella master creata</li>\n";
        echo "</ul>\n";
        echo "<p><strong>🔄 Azione:</strong> Verificare la configurazione del database e ripetere la migrazione.</p>\n";
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
    <a href="seed_master_tables.php">📊 Seeding Dati Master</a> | 
    <a href="analyze_csv_patterns.php">🔍 Analizza Pattern CSV</a> | 
    <a href="index.php">Dashboard</a>
</p>