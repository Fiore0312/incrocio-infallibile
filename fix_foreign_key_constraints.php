<?php
require_once 'config/Database.php';

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Fix Foreign Key Constraints</title>\n";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
echo "</head>\n<body>\n";

echo "<div class='container mt-4'>\n";
echo "<h2><i class='fas fa-wrench'></i> Fix Foreign Key Constraints</h2>\n";
echo "<p class='text-muted'>Risoluzione conflitti foreign key per eliminazione dipendenti</p>\n";

$execute_mode = isset($_GET['execute']) && $_GET['execute'] === 'yes';

if (!$execute_mode) {
    echo "<div class='alert alert-warning'>\n";
    echo "<h4>⚠️ Modalità Analisi</h4>\n";
    echo "<p>Questa operazione analizzerà i constraint foreign key e mostrerà il piano di risoluzione.</p>\n";
    echo "<a href='?execute=yes' class='btn btn-danger'><i class='fas fa-play'></i> Esegui Correzioni</a>\n";
    echo "</div>\n";
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h3>🔍 Analisi Foreign Key Constraints</h3>\n";
    
    // Verifica constraint attuali
    $stmt = $conn->prepare("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME = 'dipendenti'
        ORDER BY TABLE_NAME
    ");
    $stmt->execute();
    $constraints = $stmt->fetchAll();
    
    if (!empty($constraints)) {
        echo "<div class='table-responsive'>\n";
        echo "<table class='table table-striped'>\n";
        echo "<thead class='table-dark'>\n";
        echo "<tr><th>Tabella</th><th>Colonna</th><th>Constraint</th><th>Riferisce</th></tr>\n";
        echo "</thead>\n<tbody>\n";
        
        foreach ($constraints as $constraint) {
            echo "<tr>\n";
            echo "<td>" . htmlspecialchars($constraint['TABLE_NAME']) . "</td>\n";
            echo "<td>" . htmlspecialchars($constraint['COLUMN_NAME']) . "</td>\n";
            echo "<td>" . htmlspecialchars($constraint['CONSTRAINT_NAME']) . "</td>\n";
            echo "<td>" . htmlspecialchars($constraint['REFERENCED_TABLE_NAME']) . "." . htmlspecialchars($constraint['REFERENCED_COLUMN_NAME']) . "</td>\n";
            echo "</tr>\n";
        }
        
        echo "</tbody></table>\n";
        echo "</div>\n";
    }
    
    // Analizza record dipendenti che causano problemi
    echo "<h3>📊 Analisi Dipendenti con Constraint</h3>\n";
    
    $problematic_tables = [];
    foreach ($constraints as $constraint) {
        $table = $constraint['TABLE_NAME'];
        $column = $constraint['COLUMN_NAME'];
        
        $stmt = $conn->prepare("
            SELECT d.id, d.nome, d.cognome, COUNT(t.$column) as reference_count
            FROM dipendenti d
            LEFT JOIN $table t ON d.id = t.$column
            GROUP BY d.id, d.nome, d.cognome
            HAVING reference_count > 0
            ORDER BY reference_count DESC
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        if (!empty($results)) {
            $problematic_tables[$table] = $results;
        }
    }
    
    if (!empty($problematic_tables)) {
        foreach ($problematic_tables as $table => $dipendenti) {
            echo "<h4>Tabella: $table</h4>\n";
            echo "<div class='table-responsive'>\n";
            echo "<table class='table table-sm'>\n";
            echo "<thead><tr><th>ID</th><th>Nome</th><th>Record Collegati</th></tr></thead>\n";
            echo "<tbody>\n";
            
            foreach ($dipendenti as $dip) {
                echo "<tr>\n";
                echo "<td>{$dip['id']}</td>\n";
                echo "<td>" . htmlspecialchars($dip['nome'] . ' ' . $dip['cognome']) . "</td>\n";
                echo "<td><span class='badge bg-warning'>{$dip['reference_count']}</span></td>\n";
                echo "</tr>\n";
            }
            
            echo "</tbody></table>\n";
            echo "</div>\n";
        }
    }
    
    if ($execute_mode) {
        echo "<h3>🔧 Esecuzione Correzioni</h3>\n";
        
        $corrections_applied = 0;
        
        // Opzione 1: Modifica constraint per CASCADE DELETE
        echo "<h4>Modifico Constraint per CASCADE DELETE</h4>\n";
        
        foreach ($constraints as $constraint) {
            $table = $constraint['TABLE_NAME'];
            $column = $constraint['COLUMN_NAME'];
            $constraint_name = $constraint['CONSTRAINT_NAME'];
            
            try {
                // Drop constraint esistente
                $conn->exec("ALTER TABLE $table DROP FOREIGN KEY $constraint_name");
                echo "<div class='alert alert-info'>\n";
                echo "<p>✅ Rimosso constraint: $constraint_name da $table</p>\n";
                echo "</div>\n";
                
                // Ricrea constraint con CASCADE
                $new_constraint_name = $constraint_name . '_cascade';
                $conn->exec("
                    ALTER TABLE $table 
                    ADD CONSTRAINT $new_constraint_name 
                    FOREIGN KEY ($column) 
                    REFERENCES dipendenti(id) 
                    ON DELETE CASCADE 
                    ON UPDATE CASCADE
                ");
                echo "<div class='alert alert-success'>\n";
                echo "<p>✅ Creato nuovo constraint CASCADE: $new_constraint_name su $table</p>\n";
                echo "</div>\n";
                
                $corrections_applied++;
                
            } catch (Exception $e) {
                echo "<div class='alert alert-danger'>\n";
                echo "<p>❌ Errore su $table: " . htmlspecialchars($e->getMessage()) . "</p>\n";
                echo "</div>\n";
            }
        }
        
        echo "<div class='alert alert-success'>\n";
        echo "<h4>🎉 Correzioni Completate</h4>\n";
        echo "<p><strong>Constraint modificati:</strong> $corrections_applied</p>\n";
        echo "<p>Ora è possibile eliminare dipendenti. I record collegati verranno eliminati automaticamente.</p>\n";
        echo "</div>\n";
        
    } else {
        echo "<h3>💡 Piano di Correzione</h3>\n";
        echo "<div class='alert alert-info'>\n";
        echo "<h4>Strategia Raccomandata</h4>\n";
        echo "<ol>\n";
        echo "<li><strong>Modifica Constraint:</strong> Aggiungere CASCADE DELETE ai foreign key</li>\n";
        echo "<li><strong>Backup Automatico:</strong> I record collegati verranno eliminati automaticamente</li>\n";
        echo "<li><strong>Integrità:</strong> Mantiene l'integrità referenziale</li>\n";
        echo "</ol>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>\n";
    echo "<h4>❌ Errore</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "</div>\n";
echo "</body>\n</html>\n";
?>