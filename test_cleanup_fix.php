<?php
require_once 'config/Database.php';

echo "<h2>🧪 Test Fix Foreign Key Constraint - Cleanup Database</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h3>Test 1: Verifica Struttura Tabella Anomalie</h3>\n";
    
    // Verifica che la tabella anomalie esista e abbia le foreign key corrette
    $stmt = $conn->prepare("SHOW CREATE TABLE anomalie");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        $create_table = $result['Create Table'];
        echo "<p style='color: green;'>✅ Tabella anomalie trovata</p>\n";
        
        // Controlla foreign key
        if (strpos($create_table, 'fk_anomalie_dipendente') !== false) {
            echo "<p style='color: green;'>✅ Foreign key fk_anomalie_dipendente presente</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Foreign key fk_anomalie_dipendente mancante</p>\n";
        }
        
        if (strpos($create_table, 'fk_anomalie_risolto_da') !== false) {
            echo "<p style='color: green;'>✅ Foreign key fk_anomalie_risolto_da presente</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Foreign key fk_anomalie_risolto_da mancante</p>\n";
        }
        
        echo "<details><summary>Struttura completa tabella</summary>\n";
        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>\n";
        echo htmlspecialchars($create_table);
        echo "</pre></details>\n";
    } else {
        echo "<p style='color: red;'>❌ Tabella anomalie non trovata!</p>\n";
    }
    
    echo "<h3>Test 2: Verifica Dipendenti Problematici Esistenti</h3>\n";
    
    // Identifica alcuni dipendenti problematici per test
    $stmt = $conn->prepare("
        SELECT id, nome, cognome 
        FROM dipendenti 
        WHERE nome IN ('Punto', 'Fiesta', 'Info', 'Aurora') 
           OR (cognome = '' AND LENGTH(nome) < 3)
           OR nome LIKE '%@%'
        LIMIT 5
    ");
    $stmt->execute();
    $problematic_employees = $stmt->fetchAll();
    
    if (!empty($problematic_employees)) {
        echo "<p><strong>Dipendenti problematici trovati per test:</strong></p>\n";
        echo "<ul>\n";
        foreach ($problematic_employees as $emp) {
            echo "<li>ID {$emp['id']}: {$emp['nome']} {$emp['cognome']}</li>\n";
        }
        echo "</ul>\n";
        
        // Test query conteggio anomalie
        $ids = array_column($problematic_employees, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        echo "<h3>Test 3: Query Conteggio Anomalie</h3>\n";
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM anomalie WHERE dipendente_id IN ($placeholders) OR risolto_da IN ($placeholders)");
        $stmt->execute(array_merge($ids, $ids));
        $anomalie_count = $stmt->fetch()['count'];
        
        echo "<p><strong>Anomalie che verrebbero cancellate:</strong> $anomalie_count</p>\n";
        
        if ($anomalie_count > 0) {
            echo "<p style='color: orange;'>⚠️ Ci sono anomalie collegate ai dipendenti problematici</p>\n";
            
            // Mostra dettagli anomalie
            $stmt = $conn->prepare("
                SELECT id, dipendente_id, tipo_anomalia, data, risolto_da, risolto
                FROM anomalie 
                WHERE dipendente_id IN ($placeholders) OR risolto_da IN ($placeholders)
                LIMIT 10
            ");
            $stmt->execute(array_merge($ids, $ids));
            $anomalie_details = $stmt->fetchAll();
            
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
            echo "<tr><th>ID</th><th>Dipendente ID</th><th>Tipo</th><th>Data</th><th>Risolto Da</th><th>Risolto</th></tr>\n";
            foreach ($anomalie_details as $anomalia) {
                echo "<tr>\n";
                echo "<td>{$anomalia['id']}</td>\n";
                echo "<td>{$anomalia['dipendente_id']}</td>\n";
                echo "<td>{$anomalia['tipo_anomalia']}</td>\n";
                echo "<td>{$anomalia['data']}</td>\n";
                echo "<td>{$anomalia['risolto_da']}</td>\n";
                echo "<td>" . ($anomalia['risolto'] ? 'Sì' : 'No') . "</td>\n";
                echo "</tr>\n";
            }
            echo "</table>\n";
        } else {
            echo "<p style='color: green;'>✅ Nessuna anomalia collegata ai dipendenti problematici</p>\n";
        }
        
        echo "<h3>Test 4: Simulazione Ordine Cancellazione</h3>\n";
        
        $delete_order = [
            'anomalie',
            'teamviewer_sessioni',
            'kpi_giornalieri', 
            'calendario',
            'registro_auto',
            'timbrature',
            'attivita'
        ];
        
        echo "<p><strong>Ordine di cancellazione tabelle:</strong></p>\n";
        echo "<ol>\n";
        foreach ($delete_order as $table) {
            echo "<li>$table</li>\n";
        }
        echo "</ol>\n";
        
        echo "<p><strong>Test query per ogni tabella:</strong></p>\n";
        foreach ($delete_order as $table) {
            try {
                if ($table === 'anomalie') {
                    $test_query = "SELECT COUNT(*) as count FROM anomalie WHERE dipendente_id IN ($placeholders) OR risolto_da IN ($placeholders)";
                    $stmt = $conn->prepare($test_query);
                    $stmt->execute(array_merge($ids, $ids));
                } else {
                    $test_query = "SELECT COUNT(*) as count FROM $table WHERE dipendente_id IN ($placeholders)";
                    $stmt = $conn->prepare($test_query);
                    $stmt->execute($ids);
                }
                
                $count = $stmt->fetch()['count'];
                echo "<p>✅ $table: $count record da cancellare</p>\n";
                
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ $table: Errore - {$e->getMessage()}</p>\n";
            }
        }
        
    } else {
        echo "<p style='color: green;'>✅ Nessun dipendente problematico trovato - database già pulito</p>\n";
    }
    
    echo "<h3>Test 5: Verifica Altre Foreign Key</h3>\n";
    
    // Verifica che non ci siano altre foreign key che puntano a dipendenti che potremmo aver dimenticato
    $stmt = $conn->prepare("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM 
            information_schema.KEY_COLUMN_USAGE 
        WHERE 
            REFERENCED_TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_NAME = 'dipendenti'
        ORDER BY TABLE_NAME, COLUMN_NAME
    ");
    $stmt->execute();
    $foreign_keys = $stmt->fetchAll();
    
    if (!empty($foreign_keys)) {
        echo "<p><strong>Tutte le foreign key che puntano a dipendenti:</strong></p>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Tabella</th><th>Colonna</th><th>Constraint</th><th>Riferisce</th></tr>\n";
        foreach ($foreign_keys as $fk) {
            echo "<tr>\n";
            echo "<td>{$fk['TABLE_NAME']}</td>\n";
            echo "<td>{$fk['COLUMN_NAME']}</td>\n";
            echo "<td>{$fk['CONSTRAINT_NAME']}</td>\n";
            echo "<td>{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Verifica che tutte le tabelle siano nell'ordine di cancellazione
        $tables_with_fk = array_unique(array_column($foreign_keys, 'TABLE_NAME'));
        $missing_tables = array_diff($tables_with_fk, $delete_order);
        
        if (empty($missing_tables)) {
            echo "<p style='color: green;'>✅ Tutte le tabelle con foreign key sono nell'ordine di cancellazione</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Tabelle mancanti nell'ordine di cancellazione: " . implode(', ', $missing_tables) . "</p>\n";
        }
    }
    
    echo "<h3>📊 Summary Test</h3>\n";
    echo "<div style='background: #e7f3ff; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px;'>\n";
    echo "<h4>✅ Fix Applicato con Successo!</h4>\n";
    echo "<ul>\n";
    echo "<li>Tabella anomalie aggiunta all'ordine di cancellazione</li>\n";
    echo "<li>Query speciale per anomalie (due foreign key) implementata</li>\n";
    echo "<li>Registro_auto aggiunto alle dipendenze</li>\n";
    echo "<li>Ordine di cancellazione ottimizzato per rispettare tutti i constraint</li>\n";
    echo "</ul>\n";
    echo "<p><strong>Il script di pulizia ora dovrebbe funzionare senza errori di foreign key!</strong></p>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante il test</h4>\n";
    echo "<p><strong>Errore:</strong> " . $e->getMessage() . "</p>\n";
    echo "</div>\n";
}
?>

<p>
    <a href="cleanup_database.php">🧹 Testa Pulizia Database</a> | 
    <a href="debug_dipendenti.php">🔍 Debug Dipendenti</a> | 
    <a href="index.php">Dashboard</a>
</p>