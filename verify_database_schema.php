<?php
require_once 'config/Database.php';

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Verifica Schema Database</title>\n";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
echo "</head>\n<body>\n";

echo "<div class='container mt-4'>\n";
echo "<h2><i class='fas fa-database'></i> Verifica Schema Database</h2>\n";
echo "<p class='text-muted'>Analisi struttura tabelle dipendenti</p>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<div class='alert alert-success'>\n";
    echo "<h4>✅ Database Connesso</h4>\n";
    echo "<p>Connessione al database stabilita con successo.</p>\n";
    echo "</div>\n";
    
    // Verifica tabelle esistenti
    echo "<h3>📋 Tabelle Esistenti</h3>\n";
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $employee_tables = [];
    foreach ($tables as $table) {
        if (strpos($table, 'dipendenti') !== false) {
            $employee_tables[] = $table;
        }
    }
    
    if (empty($employee_tables)) {
        echo "<div class='alert alert-warning'>\n";
        echo "<h4>⚠️ Nessuna Tabella Dipendenti Trovata</h4>\n";
        echo "<p>Non sono state trovate tabelle contenenti 'dipendenti'.</p>\n";
        echo "</div>\n";
    } else {
        echo "<div class='alert alert-info'>\n";
        echo "<h4>📊 Tabelle Dipendenti Trovate</h4>\n";
        echo "<ul>\n";
        foreach ($employee_tables as $table) {
            echo "<li><strong>$table</strong></li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
        
        // Analizza struttura di ogni tabella
        foreach ($employee_tables as $table) {
            echo "<h4>🔍 Struttura: $table</h4>\n";
            
            $stmt = $conn->prepare("DESCRIBE $table");
            $stmt->execute();
            $columns = $stmt->fetchAll();
            
            echo "<div class='table-responsive'>\n";
            echo "<table class='table table-striped table-sm'>\n";
            echo "<thead class='table-dark'>\n";
            echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";
            
            foreach ($columns as $column) {
                echo "<tr>\n";
                echo "<td><strong>" . htmlspecialchars($column['Field']) . "</strong></td>\n";
                echo "<td>" . htmlspecialchars($column['Type']) . "</td>\n";
                echo "<td>" . htmlspecialchars($column['Null']) . "</td>\n";
                echo "<td>" . htmlspecialchars($column['Key']) . "</td>\n";
                echo "<td>" . htmlspecialchars($column['Default'] ?? '') . "</td>\n";
                echo "<td>" . htmlspecialchars($column['Extra']) . "</td>\n";
                echo "</tr>\n";
            }
            
            echo "</tbody>\n";
            echo "</table>\n";
            echo "</div>\n";
            
            // Conta record
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table");
            $stmt->execute();
            $count = $stmt->fetch();
            echo "<p><strong>Record totali:</strong> " . number_format($count['count']) . "</p>\n";
            
            // Mostra qualche record di esempio
            if ($count['count'] > 0) {
                $stmt = $conn->prepare("SELECT * FROM $table LIMIT 5");
                $stmt->execute();
                $samples = $stmt->fetchAll();
                
                echo "<h5>📝 Esempi di Record</h5>\n";
                echo "<div class='table-responsive'>\n";
                echo "<table class='table table-striped table-sm'>\n";
                echo "<thead class='table-light'>\n";
                echo "<tr>\n";
                foreach (array_keys($samples[0]) as $col) {
                    echo "<th>" . htmlspecialchars($col) . "</th>\n";
                }
                echo "</tr>\n";
                echo "</thead>\n";
                echo "<tbody>\n";
                
                foreach ($samples as $row) {
                    echo "<tr>\n";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? '') . "</td>\n";
                    }
                    echo "</tr>\n";
                }
                
                echo "</tbody>\n";
                echo "</table>\n";
                echo "</div>\n";
            }
            
            echo "<hr>\n";
        }
    }
    
    // Verifica indici
    echo "<h3>🔑 Indici delle Tabelle</h3>\n";
    foreach ($employee_tables as $table) {
        echo "<h4>Indici di $table</h4>\n";
        
        $stmt = $conn->prepare("SHOW INDEX FROM $table");
        $stmt->execute();
        $indexes = $stmt->fetchAll();
        
        if (!empty($indexes)) {
            echo "<div class='table-responsive'>\n";
            echo "<table class='table table-striped table-sm'>\n";
            echo "<thead class='table-dark'>\n";
            echo "<tr><th>Nome</th><th>Colonna</th><th>Unico</th><th>Tipo</th></tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";
            
            foreach ($indexes as $index) {
                echo "<tr>\n";
                echo "<td><strong>" . htmlspecialchars($index['Key_name']) . "</strong></td>\n";
                echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>\n";
                echo "<td>" . ($index['Non_unique'] == 0 ? 'Sì' : 'No') . "</td>\n";
                echo "<td>" . htmlspecialchars($index['Index_type']) . "</td>\n";
                echo "</tr>\n";
            }
            
            echo "</tbody>\n";
            echo "</table>\n";
            echo "</div>\n";
        }
    }
    
    // Verifica constraint
    echo "<h3>🔒 Constraint e Relazioni</h3>\n";
    $stmt = $conn->prepare("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
        AND (TABLE_NAME LIKE '%dipendenti%' OR REFERENCED_TABLE_NAME LIKE '%dipendenti%')
    ");
    $stmt->execute();
    $constraints = $stmt->fetchAll();
    
    if (!empty($constraints)) {
        echo "<div class='table-responsive'>\n";
        echo "<table class='table table-striped table-sm'>\n";
        echo "<thead class='table-dark'>\n";
        echo "<tr><th>Tabella</th><th>Colonna</th><th>Constraint</th><th>Riferisce</th><th>Colonna Ref</th></tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";
        
        foreach ($constraints as $constraint) {
            echo "<tr>\n";
            echo "<td><strong>" . htmlspecialchars($constraint['TABLE_NAME']) . "</strong></td>\n";
            echo "<td>" . htmlspecialchars($constraint['COLUMN_NAME']) . "</td>\n";
            echo "<td>" . htmlspecialchars($constraint['CONSTRAINT_NAME']) . "</td>\n";
            echo "<td>" . htmlspecialchars($constraint['REFERENCED_TABLE_NAME']) . "</td>\n";
            echo "<td>" . htmlspecialchars($constraint['REFERENCED_COLUMN_NAME']) . "</td>\n";
            echo "</tr>\n";
        }
        
        echo "</tbody>\n";
        echo "</table>\n";
        echo "</div>\n";
    } else {
        echo "<div class='alert alert-info'>\n";
        echo "<p>Nessun constraint di foreign key trovato per le tabelle dipendenti.</p>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>\n";
    echo "<h4>❌ Errore durante verifica</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "</div>\n";
echo "</body>\n</html>\n";
?>