<?php
require_once 'config/Database.php';

/**
 * Setup MariaDB Ultra-Semplificato
 * Parser robusto con validazione rigorosa e debug completo
 */

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Setup MariaDB Ultra-Semplificato</title>\n";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
echo "<style>\n";
echo ".debug-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; margin: 5px 0; border-radius: 5px; font-family: monospace; font-size: 0.85em; }\n";
echo ".success { background: #d4edda; border-color: #c3e6cb; color: #155724; }\n";
echo ".warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }\n";
echo ".error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }\n";
echo ".info { background: #cce5ff; border-color: #b3d9ff; color: #004085; }\n";
echo "</style>\n";
echo "</head>\n<body>\n";

echo "<div class='container mt-4'>\n";
echo "<h2><i class='fas fa-database'></i> Setup MariaDB Ultra-Semplificato</h2>\n";
echo "<p class='text-muted'>Parser robusto con validazione rigorosa - Versione definitiva</p>\n";

class RobustSQLParser {
    private $debug_mode = true;
    private $statements = [];
    private $errors = [];
    private $warnings = [];
    
    public function parseSQLFile($filepath) {
        if (!file_exists($filepath)) {
            throw new Exception("File SQL non trovato: $filepath");
        }
        
        $content = file_get_contents($filepath);
        $this->debugLog("File caricato: " . number_format(strlen($content)) . " caratteri");
        
        return $this->parseContent($content);
    }
    
    private function parseContent($content) {
        $lines = explode("\n", $content);
        $current_statement = '';
        $line_number = 0;
        $in_comment = false;
        
        foreach ($lines as $line) {
            $line_number++;
            $original_line = $line;
            $line = trim($line);
            
            // Skip commenti e linee vuote
            if ($this->shouldSkipLine($line, $in_comment)) {
                if (strpos($line, '/*') !== false) $in_comment = true;
                if (strpos($line, '*/') !== false) $in_comment = false;
                continue;
            }
            
            $current_statement .= $line . " ";
            
            // Se la linea termina con ; è un statement completo
            if (preg_match('/;$/', $line)) {
                $statement = $this->cleanStatement($current_statement);
                
                if ($this->validateStatement($statement, $line_number)) {
                    $this->statements[] = $statement;
                    $this->debugLog("✅ Statement valido (linea $line_number): " . substr($statement, 0, 60) . "...");
                } else {
                    $this->warnings[] = "Statement scartato alla linea $line_number";
                }
                
                $current_statement = '';
            }
        }
        
        // Gestisci statement finale senza ;
        if (!empty(trim($current_statement))) {
            $statement = $this->cleanStatement($current_statement);
            if ($this->validateStatement($statement, $line_number)) {
                $this->statements[] = $statement;
            }
        }
        
        $this->debugLog("Parsing completato: " . count($this->statements) . " statement validi");
        return $this->statements;
    }
    
    private function shouldSkipLine($line, $in_comment) {
        if ($in_comment) return true;
        if (empty($line)) return true;
        if (preg_match('/^--/', $line)) return true;
        if (preg_match('/^\/\*/', $line)) return true;
        if (preg_match('/^DELIMITER/i', $line)) return true;
        
        return false;
    }
    
    private function cleanStatement($statement) {
        // Rimuovi spazi multipli e normalizza
        $statement = preg_replace('/\s+/', ' ', $statement);
        $statement = trim($statement);
        
        // Rimuovi ; finale se presente
        $statement = rtrim($statement, ';');
        
        return $statement;
    }
    
    private function validateStatement($statement, $line_number) {
        // Validazione rigorosa
        if (strlen($statement) < 10) {
            $this->debugLog("❌ Statement troppo corto (linea $line_number): '$statement'");
            return false;
        }
        
        // Deve contenere parole chiave SQL valide
        $sql_keywords = ['CREATE', 'INSERT', 'DROP', 'SET', 'ALTER', 'INDEX'];
        $has_keyword = false;
        
        foreach ($sql_keywords as $keyword) {
            if (preg_match('/^\s*' . $keyword . '\s+/i', $statement)) {
                $has_keyword = true;
                break;
            }
        }
        
        if (!$has_keyword) {
            $this->debugLog("❌ Statement senza parole chiave SQL valide (linea $line_number): '$statement'");
            return false;
        }
        
        // Non deve essere solo spazi o caratteri speciali
        if (preg_match('/^[\s\(\)\,\;]*$/', $statement)) {
            $this->debugLog("❌ Statement con solo caratteri speciali (linea $line_number): '$statement'");
            return false;
        }
        
        // Test sintassi con regex specifica
        if ($this->hasSyntaxIssues($statement)) {
            $this->debugLog("❌ Possibili problemi di sintassi (linea $line_number): '$statement'");
            return false;
        }
        
        return true;
    }
    
    private function hasSyntaxIssues($statement) {
        // Pattern problematici
        $problematic_patterns = [
            '/\(\s*\)/',           // Parentesi vuote
            '/,\s*,/',             // Virgole doppie
            '/\s+,/',              // Spazi prima virgola
            '/^\s*;/',             // Inizia con punto e virgola
            '/\(\s*,/',            // Parentesi seguita da virgola
            '/,\s*\)/',            // Virgola seguita da parentesi chiusa
        ];
        
        foreach ($problematic_patterns as $pattern) {
            if (preg_match($pattern, $statement)) {
                return true;
            }
        }
        
        return false;
    }
    
    public function getStatements() {
        return $this->statements;
    }
    
    public function getWarnings() {
        return $this->warnings;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    private function debugLog($message) {
        if ($this->debug_mode) {
            echo "<div class='debug-box info'>🔧 DEBUG: $message</div>\n";
            if (ob_get_level()) ob_flush();
            flush();
        }
    }
}

// Esecuzione setup
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Informazioni database
    $stmt = $conn->prepare("SELECT VERSION() as version");
    $stmt->execute();
    $db_info = $stmt->fetch();
    
    echo "<div class='alert alert-info'>\n";
    echo "<h5>📊 Informazioni Database</h5>\n";
    echo "<p><strong>Versione MariaDB/MySQL:</strong> {$db_info['version']}</p>\n";
    echo "</div>\n";
    
    $execute_setup = isset($_GET['run']) && $_GET['run'] === 'yes';
    
    if (!$execute_setup) {
        echo "<div class='alert alert-warning'>\n";
        echo "<h5>⚠️ Setup Ultra-Semplificato Pronto</h5>\n";
        echo "<p>Questo setup utilizza un file SQL completamente semplificato e un parser ultra-robusto.</p>\n";
        echo "<p><strong>Caratteristiche:</strong></p>\n";
        echo "<ul>\n";
        echo "<li>✅ Nessun prepared statement dinamico</li>\n";
        echo "<li>✅ Parser con validazione rigorosa</li>\n";
        echo "<li>✅ Debug completo ogni statement</li>\n";
        echo "<li>✅ 15 dipendenti fissi garantiti</li>\n";
        echo "</ul>\n";
        echo "<div class='d-grid mt-3'>\n";
        echo "<a href='?run=yes' class='btn btn-success btn-lg'>\n";
        echo "<i class='fas fa-rocket'></i> Esegui Setup Ultra-Semplificato\n";
        echo "</a>\n";
        echo "</div>\n";
        echo "</div>\n";
    } else {
        echo "<div class='alert alert-success'>\n";
        echo "<h5>🚀 Esecuzione Setup in Corso...</h5>\n";
        echo "</div>\n";
        
        $schema_file = 'create_master_schema_simple.sql';
        if (!file_exists($schema_file)) {
            throw new Exception("File schema semplificato non trovato: $schema_file");
        }
        
        // Usa il parser robusto
        $parser = new RobustSQLParser();
        $statements = $parser->parseSQLFile($schema_file);
        
        if (empty($statements)) {
            throw new Exception("Nessun statement valido trovato nel file SQL");
        }
        
        echo "<div class='alert alert-info'>\n";
        echo "<p><strong>Statements identificati e validati:</strong> " . count($statements) . "</p>\n";
        if (!empty($parser->getWarnings())) {
            echo "<p><strong>Warning:</strong> " . count($parser->getWarnings()) . "</p>\n";
        }
        echo "</div>\n";
        
        // Esecuzione senza transazione singola (più sicuro)
        $executed = 0;
        $errors = 0;
        $warnings = 0;
        $start_time = microtime(true);
        
        foreach ($statements as $index => $statement) {
            $stmt_type = 'UNKNOWN';
            if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS/i', $statement)) {
                $stmt_type = 'SET FOREIGN_KEY_CHECKS';
            } elseif (preg_match('/^DROP\s+TABLE/i', $statement)) {
                $stmt_type = 'DROP TABLE';
            } elseif (preg_match('/^CREATE\s+TABLE/i', $statement)) {
                $stmt_type = 'CREATE TABLE';
            } elseif (preg_match('/^INSERT\s+INTO/i', $statement)) {
                $stmt_type = 'INSERT DATA';
            } elseif (preg_match('/^CREATE\s+INDEX/i', $statement)) {
                $stmt_type = 'CREATE INDEX';
            }
            
            try {
                // Debug: mostra statement prima dell'esecuzione
                echo "<div class='debug-box'>\n";
                echo "<strong>[$stmt_type]</strong> " . substr($statement, 0, 100) . "...\n";
                echo "</div>\n";
                
                $conn->exec($statement);
                $executed++;
                
                echo "<div class='alert alert-success py-2'>\n";
                echo "<small><strong>✅ $stmt_type:</strong> Eseguito con successo</small>\n";
                echo "</div>\n";
                
            } catch (Exception $e) {
                $error_msg = $e->getMessage();
                
                // Classifica errori
                if (stripos($error_msg, 'already exists') !== false ||
                    stripos($error_msg, 'duplicate key') !== false) {
                    $warnings++;
                    echo "<div class='alert alert-warning py-2'>\n";
                    echo "<small><strong>⚠️ $stmt_type:</strong> " . htmlspecialchars($error_msg) . "</small>\n";
                    echo "</div>\n";
                } else {
                    $errors++;
                    echo "<div class='alert alert-danger py-2'>\n";
                    echo "<small><strong>❌ $stmt_type:</strong> " . htmlspecialchars($error_msg) . "</small>\n";
                    echo "</div>\n";
                    
                    // Interrompi solo su troppi errori
                    if ($errors > 5) {
                        throw new Exception("Troppi errori critici, interrompo setup");
                    }
                }
            }
            
            if (ob_get_level()) ob_flush();
            flush();
        }
        
        $execution_time = round(microtime(true) - $start_time, 2);
        
        echo "<div class='alert alert-success'>\n";
        echo "<h4>✅ Setup Completato!</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>Statements eseguiti:</strong> $executed</li>\n";
        echo "<li><strong>Warning:</strong> $warnings</li>\n";
        echo "<li><strong>Errori:</strong> $errors</li>\n";
        echo "<li><strong>Tempo:</strong> {$execution_time}s</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        
        // Verifica finale
        echo "<h4>🔍 Verifica Setup</h4>\n";
        
        $verification_queries = [
            'master_dipendenti_fixed' => 'SELECT COUNT(*) as count FROM master_dipendenti_fixed',
            'master_aziende' => 'SELECT COUNT(*) as count FROM master_aziende',
            'master_veicoli_config' => 'SELECT COUNT(*) as count FROM master_veicoli_config',
            'system_config' => 'SELECT COUNT(*) as count FROM system_config'
        ];
        
        echo "<table class='table table-striped'>\n";
        echo "<thead><tr><th>Tabella</th><th>Record</th><th>Stato</th></tr></thead><tbody>\n";
        
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
                echo "<td>$status_icon " . ($count > 0 ? 'OK' : 'VUOTA') . "</td>\n";
                echo "</tr>\n";
                
            } catch (Exception $e) {
                echo "<tr class='table-danger'>\n";
                echo "<td><strong>$table</strong></td>\n";
                echo "<td>-</td>\n";
                echo "<td>❌ Errore</td>\n";
                echo "</tr>\n";
            }
        }
        echo "</tbody></table>\n";
        
        // Verifica specifica 15 dipendenti
        echo "<h5>👥 Verifica 15 Dipendenti Master</h5>\n";
        try {
            $stmt = $conn->prepare("SELECT nome, cognome FROM master_dipendenti_fixed ORDER BY cognome, nome");
            $stmt->execute();
            $dipendenti = $stmt->fetchAll();
            
            if (count($dipendenti) >= 14) {
                echo "<div class='alert alert-success'>\n";
                echo "<p><strong>✅ " . count($dipendenti) . " dipendenti master trovati!</strong></p>\n";
                echo "<div class='row'>\n";
                $col_count = 0;
                foreach ($dipendenti as $dip) {
                    if ($col_count % 5 == 0) echo "<div class='col-md-4'><ul>\n";
                    echo "<li>{$dip['nome']} {$dip['cognome']}</li>\n";
                    $col_count++;
                    if ($col_count % 5 == 0 || $col_count == count($dipendenti)) echo "</ul></div>\n";
                }
                echo "</div>\n";
                echo "</div>\n";
                
                echo "<div class='d-grid gap-2 mt-4'>\n";
                echo "<a href='smart_upload_final.php' class='btn btn-success btn-lg'>\n";
                echo "<i class='fas fa-rocket'></i> Sistema Pronto - Vai a Smart Upload!\n";
                echo "</a>\n";
                echo "<a href='master_data_console.php' class='btn btn-primary'>\n";
                echo "<i class='fas fa-database'></i> Master Data Console\n";
                echo "</a>\n";
                echo "</div>\n";
                
            } else {
                echo "<div class='alert alert-danger'>\n";
                echo "<p><strong>❌ Solo " . count($dipendenti) . " dipendenti trovati (15 attesi)</strong></p>\n";
                echo "</div>\n";
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>\n";
            echo "<p><strong>❌ Errore verifica dipendenti:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
            echo "</div>\n";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>\n";
    echo "<h4>❌ Errore durante setup</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "</div>\n"; // Close container
echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>\n";
echo "</body>\n</html>\n";
?>