<?php
require_once 'config/Database.php';
require_once 'classes/ImportLogger.php';

/**
 * Quick Setup MariaDB - Test Schema Compatibile
 * Setup veloce per testare il nuovo schema MariaDB
 */

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Quick Setup MariaDB - Employee Analytics</title>\n";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
echo "<style>\n";
echo ".result-success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0; }\n";
echo ".result-warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 10px 0; }\n";
echo ".result-error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0; }\n";
echo ".result-info { background: #cce5ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 5px; margin: 10px 0; }\n";
echo "</style>\n";
echo "</head>\n<body>\n";

echo "<div class='container mt-4'>\n";
echo "<h2><i class='fas fa-rocket'></i> Quick Setup MariaDB - Test Schema</h2>\n";
echo "<p class='text-muted'>Setup veloce per testare la compatibilità del nuovo schema con MariaDB</p>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    $logger = new ImportLogger('quick_setup_mariadb');
    
    // Verifica versione database
    $stmt = $conn->prepare("SELECT VERSION() as version, @@sql_mode as sql_mode");
    $stmt->execute();
    $db_info = $stmt->fetch();
    
    echo "<div class='result-info'>\n";
    echo "<h5>🔍 Informazioni Database</h5>\n";
    echo "<p><strong>Versione:</strong> {$db_info['version']}</p>\n";
    echo "<p><strong>SQL Mode:</strong> {$db_info['sql_mode']}</p>\n";
    echo "</div>\n";
    
    $execute_setup = isset($_GET['run']) && $_GET['run'] === 'yes';
    
    if (!$execute_setup) {
        echo "<div class='result-warning'>\n";
        echo "<h5>⚠️ Modalità Preview</h5>\n";
        echo "<p>Questo setup eseguirà il nuovo schema MariaDB compatibile.</p>\n";
        echo "<p><strong>Attenzione:</strong> Tutte le tabelle master esistenti verranno ricreate!</p>\n";
        echo "<div class='d-grid gap-2 mt-3'>\n";
        echo "<a href='?run=yes' class='btn btn-success btn-lg'>\n";
        echo "<i class='fas fa-play'></i> Esegui Setup Schema MariaDB\n";
        echo "</a>\n";
        echo "</div>\n";
        echo "</div>\n";
    } else {
        echo "<div class='result-info'>\n";
        echo "<h5>🚀 Esecuzione Setup in Corso...</h5>\n";
        echo "</div>\n";
        
        $schema_file = 'create_fixed_master_schema_mariadb.sql';
        if (!file_exists($schema_file)) {
            throw new Exception("File schema non trovato: $schema_file");
        }
        
        $schema_sql = file_get_contents($schema_file);
        echo "<p><strong>File schema caricato:</strong> " . number_format(strlen($schema_sql)) . " caratteri</p>\n";
        
        // Parsing robusto per MariaDB con gestione errori
        $statements = [];
        $current_statement = '';
        $lines = explode("\n", $schema_sql);
        $in_multiline_comment = false;
        
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            
            // Gestione commenti multilinea
            if (strpos($line, '/*') !== false) {
                $in_multiline_comment = true;
            }
            if (strpos($line, '*/') !== false) {
                $in_multiline_comment = false;
                continue;
            }
            if ($in_multiline_comment) {
                continue;
            }
            
            // Skip comments, empty lines e DELIMITER
            if (empty($line) || 
                preg_match('/^--/', $line) || 
                preg_match('/^\/\*/', $line) ||
                preg_match('/^\s*\/\*/', $line) ||
                preg_match('/^DELIMITER/i', $line)) {
                continue;
            }
            
            $current_statement .= $line . " ";
            
            // Se la linea termina con ; è la fine del statement
            if (preg_match('/;$/', $line)) {
                $stmt = trim($current_statement);
                
                // Validazione statement più rigorosa
                if (strlen($stmt) > 15 && 
                    !preg_match('/^DELIMITER/i', $stmt) &&
                    !preg_match('/^\s*$/', $stmt) &&
                    !preg_match('/^\s*--/', $stmt)) {
                    
                    // Skip prepared statements problematici
                    if (preg_match('/^(SET|PREPARE|EXECUTE|DEALLOCATE)/i', $stmt)) {
                        echo "<div class='result-info'>\n";
                        echo "<small><strong>ℹ️ SKIPPED:</strong> Prepared statement - " . substr(htmlspecialchars($stmt), 0, 100) . "...</small>\n";
                        echo "</div>\n";
                    } else {
                        $statements[] = $stmt;
                    }
                }
                $current_statement = '';
            }
        }
        
        // Aggiungi statement finale se non termina con ;
        if (!empty(trim($current_statement))) {
            $stmt = trim($current_statement);
            if (strlen($stmt) > 15) {
                $statements[] = $stmt;
            }
        }
        
        echo "<p><strong>Statements SQL identificati:</strong> " . count($statements) . "</p>\n";
        
        // Gestiamo transazioni con controllo stato
        $transaction_started = false;
        
        try {
            $conn->beginTransaction();
            $transaction_started = true;
            
            $executed = 0;
            $warnings = 0;
            $errors = 0;
            $start_time = microtime(true);
            
            foreach ($statements as $index => $statement) {
                // Identifica tipo statement
                $stmt_type = 'UNKNOWN';
                if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS/i', $statement)) {
                    $stmt_type = 'SET FOREIGN_KEY_CHECKS';
                } elseif (preg_match('/^DROP\s+TABLE/i', $statement)) {
                    $stmt_type = 'DROP TABLE';
                } elseif (preg_match('/^CREATE\s+TABLE/i', $statement)) {
                    $stmt_type = 'CREATE TABLE';
                } elseif (preg_match('/^INSERT\s+INTO/i', $statement)) {
                    $stmt_type = 'INSERT DATA';
                } elseif (preg_match('/^SET\s+@/i', $statement)) {
                    $stmt_type = 'SET VARIABLE';
                } elseif (preg_match('/^PREPARE|^EXECUTE|^DEALLOCATE/i', $statement)) {
                    $stmt_type = 'PREPARED STATEMENT';
                } elseif (preg_match('/^ALTER\s+TABLE/i', $statement)) {
                    $stmt_type = 'ALTER TABLE';
                } elseif (preg_match('/^CREATE\s+INDEX/i', $statement)) {
                    $stmt_type = 'CREATE INDEX';
                } elseif (preg_match('/^CREATE.*VIEW/i', $statement)) {
                    $stmt_type = 'CREATE VIEW';
                } elseif (preg_match('/^DROP.*VIEW/i', $statement)) {
                    $stmt_type = 'DROP VIEW';
                }
                
                try {
                    $conn->exec($statement);
                    $executed++;
                    
                    echo "<div class='result-success'>\n";
                    echo "<small><strong>✅ $stmt_type:</strong> Eseguito con successo</small>\n";
                    echo "</div>\n";
                    
                } catch (Exception $e) {
                    $error_msg = $e->getMessage();
                    
                    // Classifica errori con pattern più specifici
                    $is_warning = false;
                    $is_critical = false;
                    
                    $warning_patterns = [
                        'already exists',
                        'Duplicate key name',
                        'already presente in',
                        'Unknown column',
                        'Can\'t DROP.*check that.*exists',
                        'Constraint.*already exists'
                    ];
                    
                    $critical_patterns = [
                        'syntax error.*near.*\'\'',  // Empty statement
                        'Access denied',
                        'Unknown database'
                    ];
                    
                    foreach ($warning_patterns as $pattern) {
                        if (preg_match('/' . $pattern . '/i', $error_msg)) {
                            $is_warning = true;
                            break;
                        }
                    }
                    
                    foreach ($critical_patterns as $pattern) {
                        if (preg_match('/' . $pattern . '/i', $error_msg)) {
                            $is_critical = true;
                            break;
                        }
                    }
                    
                    if ($is_warning) {
                        $warnings++;
                        echo "<div class='result-warning'>\n";
                        echo "<small><strong>⚠️ $stmt_type:</strong> " . htmlspecialchars($error_msg) . "</small>\n";
                        echo "</div>\n";
                    } elseif ($is_critical) {
                        $errors++;
                        echo "<div class='result-error'>\n";
                        echo "<small><strong>🛑 CRITICAL $stmt_type:</strong> " . htmlspecialchars($error_msg) . "</small>\n";
                        echo "</div>\n";
                        throw $e; // Stop immediato su errori critici
                    } else {
                        $errors++;
                        echo "<div class='result-error'>\n";
                        echo "<small><strong>❌ $stmt_type:</strong> " . htmlspecialchars($error_msg) . "</small>\n";
                        echo "</div>\n";
                        
                        // Continua ma traccia errore
                        if ($errors > 10) { // Stop se troppi errori
                            echo "<div class='result-error'>\n";
                            echo "<h5>🛑 Troppi errori, interrompo esecuzione</h5>\n";
                            echo "</div>\n";
                            throw new Exception("Troppi errori durante setup");
                        }
                    }
                }
                
                // Flush output per feedback in tempo reale
                if (ob_get_level()) ob_flush();
                flush();
            }
            
            $execution_time = round(microtime(true) - $start_time, 2);
            $conn->commit();
            
            echo "<div class='result-success'>\n";
            echo "<h4>✅ Setup Completato con Successo!</h4>\n";
            echo "<ul>\n";
            echo "<li><strong>Statements eseguiti:</strong> $executed</li>\n";
            echo "<li><strong>Warning:</strong> $warnings</li>\n";
            echo "<li><strong>Errori:</strong> $errors</li>\n";
            echo "<li><strong>Tempo esecuzione:</strong> {$execution_time}s</li>\n";
            echo "</ul>\n";
            echo "</div>\n";
            
            // Verifica rapida risultati
            echo "<h4>🔍 Verifica Setup</h4>\n";
            
            $verification_queries = [
                'master_dipendenti_fixed' => 'SELECT COUNT(*) as count FROM master_dipendenti_fixed',
                'master_aziende' => 'SELECT COUNT(*) as count FROM master_aziende', 
                'master_veicoli_config' => 'SELECT COUNT(*) as count FROM master_veicoli_config',
                'system_config' => 'SELECT COUNT(*) as count FROM system_config'
            ];
            
            echo "<table class='table table-striped'>\n";
            echo "<thead><tr><th>Tabella</th><th>Record</th><th>Stato</th></tr></thead>\n";
            echo "<tbody>\n";
            
            foreach ($verification_queries as $table => $query) {
                try {
                    $stmt = $conn->prepare($query);
                    $stmt->execute();
                    $result = $stmt->fetch();
                    $count = $result['count'];
                    
                    $status_class = ($count > 0) ? 'success' : 'warning';
                    $status_icon = ($count > 0) ? '✅' : '⚠️';
                    
                    echo "<tr class='table-$status_class'>\n";
                    echo "<td><strong>$table</strong></td>\n";
                    echo "<td>$count</td>\n";
                    echo "<td>$status_icon OK</td>\n";
                    echo "</tr>\n";
                    
                } catch (Exception $e) {
                    echo "<tr class='table-danger'>\n";
                    echo "<td><strong>$table</strong></td>\n";
                    echo "<td>-</td>\n";
                    echo "<td>❌ Errore</td>\n";
                    echo "</tr>\n";
                }
            }
            
            echo "</tbody>\n";
            echo "</table>\n";
            
            // Verifica 15 dipendenti
            echo "<h5>👥 Verifica 15 Dipendenti Master</h5>\n";
            $stmt = $conn->prepare("SELECT nome, cognome FROM master_dipendenti_fixed ORDER BY cognome, nome");
            $stmt->execute();
            $dipendenti = $stmt->fetchAll();
            
            if (count($dipendenti) >= 14) {
                echo "<div class='result-success'>\n";
                echo "<p><strong>✅ " . count($dipendenti) . " dipendenti master caricati correttamente!</strong></p>\n";
                echo "<div class='row'>\n";
                foreach ($dipendenti as $idx => $dip) {
                    if ($idx % 3 == 0) echo "<div class='col-md-4'><ul>\n";
                    echo "<li>{$dip['nome']} {$dip['cognome']}</li>\n";
                    if ($idx % 3 == 2 || $idx == count($dipendenti) - 1) echo "</ul></div>\n";
                }
                echo "</div>\n";
                echo "</div>\n";
            } else {
                echo "<div class='result-error'>\n";
                echo "<p><strong>❌ Solo " . count($dipendenti) . " dipendenti trovati (15 attesi)</strong></p>\n";
                echo "</div>\n";
            }
            
            echo "<div class='d-grid gap-2 mt-4'>\n";
            echo "<a href='smart_upload_final.php' class='btn btn-success btn-lg'>\n";
            echo "<i class='fas fa-rocket'></i> Vai a Smart Upload (Sistema Pronto!)\n";
            echo "</a>\n";
            echo "<a href='master_data_console.php' class='btn btn-primary'>\n";
            echo "<i class='fas fa-database'></i> Master Data Console\n";
            echo "</a>\n";
            echo "<a href='final_system_validation.php' class='btn btn-info'>\n";
            echo "<i class='fas fa-check-double'></i> Validazione Sistema\n";
            echo "</a>\n";
            echo "</div>\n";
            
        } catch (Exception $e) {
            // Rollback sicuro solo se la transazione è attiva
            if ($transaction_started && $conn->inTransaction()) {
                try {
                    $conn->rollback();
                    echo "<div class='result-warning'>\n";
                    echo "<p><strong>⚠️ Rollback eseguito:</strong> Modifiche annullate a causa di errori</p>\n";
                    echo "</div>\n";
                } catch (Exception $rollback_e) {
                    echo "<div class='result-error'>\n";
                    echo "<p><strong>❌ Errore durante rollback:</strong> " . htmlspecialchars($rollback_e->getMessage()) . "</p>\n";
                    echo "</div>\n";
                }
            }
            throw $e;
        }
    }
    
} catch (Exception $e) {
    echo "<div class='result-error'>\n";
    echo "<h4>❌ Errore durante setup</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Rollback eseguito.</strong> Nessuna modifica applicata.</p>\n";
    echo "</div>\n";
}

echo "</div>\n"; // Close container
echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>\n";
echo "</body>\n</html>\n";
?>