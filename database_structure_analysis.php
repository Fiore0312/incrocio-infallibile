<?php
require_once 'config/Database.php';

/**
 * Analisi Completa Struttura Database
 * Identifica problemi con doppia struttura legacy vs master tables
 */

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Analisi Struttura Database - Diagnosi Problemi</title>\n";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
echo "<style>\n";
echo ".analysis-section { margin: 20px 0; padding: 15px; border: 1px solid #dee2e6; border-radius: 8px; }\n";
echo ".problem-found { background: #f8d7da; border-color: #f5c6cb; }\n";
echo ".solution-ready { background: #d4edda; border-color: #c3e6cb; }\n";
echo ".warning-section { background: #fff3cd; border-color: #ffeaa7; }\n";
echo ".info-section { background: #d1ecf1; border-color: #bee5eb; }\n";
echo ".data-table { font-size: 0.9em; }\n";
echo ".data-table td, .data-table th { padding: 8px; }\n";
echo "</style>\n";
echo "</head>\n<body>\n";

echo "<div class='container mt-4'>\n";
echo "<h1><i class='fas fa-database'></i> Analisi Completa Struttura Database</h1>\n";
echo "<p class='text-muted'>Diagnosi problemi: perché diagnose_data_master.php mostra ancora i vecchi dati?</p>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Informazioni database
    $stmt = $conn->prepare("SELECT DATABASE() as current_db, VERSION() as version");
    $stmt->execute();
    $db_info = $stmt->fetch();
    
    echo "<div class='analysis-section info-section'>\n";
    echo "<h3>📊 Informazioni Database</h3>\n";
    echo "<p><strong>Database corrente:</strong> {$db_info['current_db']}</p>\n";
    echo "<p><strong>Versione MySQL/MariaDB:</strong> {$db_info['version']}</p>\n";
    echo "</div>\n";
    
    // 1. MAPPATURA COMPLETA TABELLE
    echo "<div class='analysis-section'>\n";
    echo "<h3>1. 📋 Mappatura Completa Tabelle</h3>\n";
    
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Categorie tabelle
    $legacy_tables = [];
    $master_tables = [];
    $other_tables = [];
    
    $legacy_patterns = ['dipendenti', 'clienti', 'progetti', 'veicoli', 'timbrature', 'attivita', 'teamviewer_sessioni', 'kpi_giornalieri', 'configurazioni'];
    $master_patterns = ['master_dipendenti_fixed', 'master_aziende', 'master_veicoli_config', 'clienti_aziende', 'association_queue', 'master_progetti', 'system_config'];
    
    foreach ($all_tables as $table) {
        if (in_array($table, $legacy_patterns)) {
            $legacy_tables[] = $table;
        } elseif (in_array($table, $master_patterns)) {
            $master_tables[] = $table;
        } else {
            $other_tables[] = $table;
        }
    }
    
    echo "<div class='row'>\n";
    echo "<div class='col-md-4'>\n";
    echo "<h5>🟡 Tabelle Legacy (" . count($legacy_tables) . ")</h5>\n";
    if (!empty($legacy_tables)) {
        echo "<ul>\n";
        foreach ($legacy_tables as $table) {
            echo "<li class='text-warning'>$table</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p class='text-success'>Nessuna tabella legacy trovata</p>\n";
    }
    echo "</div>\n";
    
    echo "<div class='col-md-4'>\n";
    echo "<h5>🟢 Tabelle Master (" . count($master_tables) . ")</h5>\n";
    if (!empty($master_tables)) {
        echo "<ul>\n";
        foreach ($master_tables as $table) {
            echo "<li class='text-success'>$table</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p class='text-danger'>Nessuna tabella master trovata!</p>\n";
    }
    echo "</div>\n";
    
    echo "<div class='col-md-4'>\n";
    echo "<h5>🔵 Altre Tabelle (" . count($other_tables) . ")</h5>\n";
    if (!empty($other_tables)) {
        echo "<ul>\n";
        foreach ($other_tables as $table) {
            echo "<li class='text-info'>$table</li>\n";
        }
        echo "</ul>\n";
    }
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    // 2. ANALISI RECORD COUNT
    echo "<div class='analysis-section'>\n";
    echo "<h3>2. 📊 Conteggio Record per Tabella</h3>\n";
    
    echo "<table class='table table-striped data-table'>\n";
    echo "<thead><tr><th>Tabella</th><th>Tipo</th><th>Record</th><th>Stato</th></tr></thead>\n";
    echo "<tbody>\n";
    
    $tables_with_counts = [];
    
    foreach ($all_tables as $table) {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM `$table`");
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            
            $type = 'Altri';
            $row_class = 'table-light';
            if (in_array($table, $legacy_tables)) {
                $type = 'Legacy';
                $row_class = ($count > 0) ? 'table-warning' : 'table-light';
            } elseif (in_array($table, $master_tables)) {
                $type = 'Master';
                $row_class = ($count > 0) ? 'table-success' : 'table-danger';
            }
            
            $status = ($count > 0) ? "✅ $count record" : "⚠️ Vuota";
            
            echo "<tr class='$row_class'>\n";
            echo "<td><strong>$table</strong></td>\n";
            echo "<td>$type</td>\n";
            echo "<td>$count</td>\n";
            echo "<td>$status</td>\n";
            echo "</tr>\n";
            
            $tables_with_counts[$table] = ['count' => $count, 'type' => $type];
            
        } catch (Exception $e) {
            echo "<tr class='table-danger'>\n";
            echo "<td><strong>$table</strong></td>\n";
            echo "<td>Errore</td>\n";
            echo "<td>-</td>\n";
            echo "<td>❌ " . htmlspecialchars($e->getMessage()) . "</td>\n";
            echo "</tr>\n";
        }
    }
    echo "</tbody>\n";
    echo "</table>\n";
    echo "</div>\n";
    
    // 3. DIAGNOSI PRINCIPALE PROBLEMA
    echo "<div class='analysis-section problem-found'>\n";
    echo "<h3>3. 🚨 Diagnosi Principale Problema</h3>\n";
    
    $legacy_has_data = false;
    $master_has_data = false;
    
    foreach ($tables_with_counts as $table => $info) {
        if ($info['type'] === 'Legacy' && $info['count'] > 0) {
            $legacy_has_data = true;
        }
        if ($info['type'] === 'Master' && $info['count'] > 0) {
            $master_has_data = true;
        }
    }
    
    echo "<div class='alert alert-danger'>\n";
    echo "<h4>🔍 Problema Identificato:</h4>\n";
    
    if ($legacy_has_data && $master_has_data) {
        echo "<p><strong>DOPPIA STRUTTURA ATTIVA:</strong> Sia le tabelle legacy che master contengono dati!</p>\n";
        echo "<p>📄 <code>diagnose_data_master.php</code> sta interrogando le tabelle legacy che contengono ancora i vecchi dati problematici.</p>\n";
        echo "<p>🎯 <strong>Soluzione:</strong> Aggiornare <code>diagnose_data_master.php</code> per utilizzare le master tables.</p>\n";
    } elseif ($legacy_has_data && !$master_has_data) {
        echo "<p><strong>MIGRAZIONE NON COMPLETATA:</strong> Solo le tabelle legacy contengono dati!</p>\n";
        echo "<p>📄 Le master tables sono vuote, il setup non ha migrato i dati.</p>\n";
        echo "<p>🎯 <strong>Soluzione:</strong> Eseguire migrazione dati da legacy a master tables.</p>\n";
    } elseif (!$legacy_has_data && $master_has_data) {
        echo "<p><strong>MIGRAZIONE COMPLETATA:</strong> Solo le master tables contengono dati!</p>\n";
        echo "<p>📄 <code>diagnose_data_master.php</code> deve essere aggiornato per utilizzare le master tables.</p>\n";
        echo "<p>🎯 <strong>Soluzione:</strong> Aggiornare query in <code>diagnose_data_master.php</code>.</p>\n";
    } else {
        echo "<p><strong>NESSUN DATO:</strong> Né legacy né master tables contengono dati!</p>\n";
        echo "<p>🎯 <strong>Soluzione:</strong> Importare dati di base nel sistema.</p>\n";
    }
    echo "</div>\n";
    echo "</div>\n";
    
    // 4. ANALISI PROBLEMI SPECIFICI DIPENDENTI
    if (isset($tables_with_counts['dipendenti']) && $tables_with_counts['dipendenti']['count'] > 0) {
        echo "<div class='analysis-section warning-section'>\n";
        echo "<h3>4. 👥 Analisi Problemi Specifici Dipendenti (Tabella Legacy)</h3>\n";
        
        // Cerca Andrea Bianchi
        $stmt = $conn->prepare("SELECT * FROM dipendenti WHERE nome LIKE '%Andrea%' AND cognome LIKE '%Bianchi%'");
        $stmt->execute();
        $andrea_bianchi = $stmt->fetchAll();
        
        if (!empty($andrea_bianchi)) {
            echo "<div class='alert alert-danger'>\n";
            echo "<h5>🚨 PROBLEMA: Andrea Bianchi Trovato!</h5>\n";
            foreach ($andrea_bianchi as $record) {
                echo "<p><strong>ID:</strong> {$record['id']} | <strong>Nome:</strong> {$record['nome']} {$record['cognome']} | <strong>Email:</strong> {$record['email']}</p>\n";
            }
            echo "</div>\n";
        } else {
            echo "<div class='alert alert-success'>\n";
            echo "<h5>✅ Andrea Bianchi non trovato in tabella legacy</h5>\n";
            echo "</div>\n";
        }
        
        // Cerca errori parsing Franco/Matteo
        $stmt = $conn->prepare("SELECT * FROM dipendenti WHERE nome LIKE '%Franco%' OR cognome LIKE '%Fiorellino/Matteo%' OR cognome LIKE '%Signo%'");
        $stmt->execute();
        $franco_matteo = $stmt->fetchAll();
        
        if (!empty($franco_matteo)) {
            echo "<div class='alert alert-warning'>\n";
            echo "<h5>⚠️ Possibili problemi parsing Franco/Matteo:</h5>\n";
            foreach ($franco_matteo as $record) {
                echo "<p><strong>ID:</strong> {$record['id']} | <strong>Nome:</strong> {$record['nome']} | <strong>Cognome:</strong> {$record['cognome']}</p>\n";
            }
            echo "</div>\n";
        }
        
        // Verifica dipendenti mancanti
        $dipendenti_richiesti = ['Niccolò Ragusa', 'Arlind Hoxha', 'Lorenzo Serratore'];
        $dipendenti_mancanti = [];
        
        foreach ($dipendenti_richiesti as $nome_completo) {
            list($nome, $cognome) = explode(' ', $nome_completo, 2);
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM dipendenti WHERE nome = ? AND cognome = ?");
            $stmt->execute([$nome, $cognome]);
            $found = $stmt->fetch()['count'];
            
            if ($found == 0) {
                $dipendenti_mancanti[] = $nome_completo;
            }
        }
        
        if (!empty($dipendenti_mancanti)) {
            echo "<div class='alert alert-danger'>\n";
            echo "<h5>🚨 Dipendenti Mancanti in Legacy:</h5>\n";
            echo "<ul>\n";
            foreach ($dipendenti_mancanti as $mancante) {
                echo "<li>$mancante</li>\n";
            }
            echo "</ul>\n";
            echo "</div>\n";
        }
        
        // Mostra tutti i dipendenti legacy
        echo "<h5>📋 Tutti i Dipendenti nella Tabella Legacy:</h5>\n";
        $stmt = $conn->prepare("SELECT id, nome, cognome, email, attivo FROM dipendenti ORDER BY cognome, nome");
        $stmt->execute();
        $all_dipendenti = $stmt->fetchAll();
        
        echo "<table class='table table-sm data-table'>\n";
        echo "<thead><tr><th>ID</th><th>Nome</th><th>Cognome</th><th>Email</th><th>Attivo</th></tr></thead>\n";
        echo "<tbody>\n";
        foreach ($all_dipendenti as $dip) {
            $row_class = '';
            if (stripos($dip['nome'] . ' ' . $dip['cognome'], 'Andrea Bianchi') !== false) {
                $row_class = 'table-danger';
            } elseif (stripos($dip['cognome'], '/') !== false) {
                $row_class = 'table-warning';
            }
            
            echo "<tr class='$row_class'>\n";
            echo "<td>{$dip['id']}</td>\n";
            echo "<td>{$dip['nome']}</td>\n";
            echo "<td>{$dip['cognome']}</td>\n";
            echo "<td>{$dip['email']}</td>\n";
            echo "<td>" . ($dip['attivo'] ? '✅' : '❌') . "</td>\n";
            echo "</tr>\n";
        }
        echo "</tbody>\n";
        echo "</table>\n";
        echo "</div>\n";
    }
    
    // 5. ANALISI MASTER TABLES (se esistono)
    if (isset($tables_with_counts['master_dipendenti_fixed'])) {
        echo "<div class='analysis-section solution-ready'>\n";
        echo "<h3>5. 🟢 Analisi Master Tables</h3>\n";
        
        if ($tables_with_counts['master_dipendenti_fixed']['count'] > 0) {
            echo "<h5>👥 Dipendenti nella Master Table:</h5>\n";
            $stmt = $conn->prepare("SELECT id, nome, cognome, ruolo, attivo FROM master_dipendenti_fixed ORDER BY cognome, nome");
            $stmt->execute();
            $master_dipendenti = $stmt->fetchAll();
            
            echo "<table class='table table-sm data-table table-success'>\n";
            echo "<thead><tr><th>ID</th><th>Nome</th><th>Cognome</th><th>Ruolo</th><th>Attivo</th></tr></thead>\n";
            echo "<tbody>\n";
            foreach ($master_dipendenti as $dip) {
                echo "<tr>\n";
                echo "<td>{$dip['id']}</td>\n";
                echo "<td>{$dip['nome']}</td>\n";
                echo "<td>{$dip['cognome']}</td>\n";
                echo "<td>{$dip['ruolo']}</td>\n";
                echo "<td>" . ($dip['attivo'] ? '✅' : '❌') . "</td>\n";
                echo "</tr>\n";
            }
            echo "</tbody>\n";
            echo "</table>\n";
            
            // Verifica se i 15 dipendenti richiesti sono presenti
            $dipendenti_richiesti_completi = [
                'Niccolò Ragusa', 'Davide Cestone', 'Arlind Hoxha', 'Lorenzo Serratore', 
                'Gabriele De Palma', 'Franco Fiorellino', 'Matteo Signo', 'Marco Birocchi', 
                'Roberto Birocchi', 'Alex Ferrario', 'Gianluca Ghirindelli', 'Matteo Di Salvo', 
                'Cristian La Bella', 'Giuseppe Anastasio'
            ];
            
            $dipendenti_master_trovati = 0;
            foreach ($dipendenti_richiesti_completi as $nome_completo) {
                list($nome, $cognome) = explode(' ', $nome_completo, 2);
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM master_dipendenti_fixed WHERE nome = ? AND cognome = ?");
                $stmt->execute([$nome, $cognome]);
                $found = $stmt->fetch()['count'];
                if ($found > 0) $dipendenti_master_trovati++;
            }
            
            if ($dipendenti_master_trovati >= 14) {
                echo "<div class='alert alert-success'>\n";
                echo "<h5>✅ Master Tables Corrette!</h5>\n";
                echo "<p>Trovati $dipendenti_master_trovati/15 dipendenti richiesti nella master table.</p>\n";
                echo "</div>\n";
            }
        } else {
            echo "<div class='alert alert-warning'>\n";
            echo "<h5>⚠️ Master Tables Vuote</h5>\n";
            echo "<p>Le master tables esistono ma sono vuote. Il setup non ha inserito i dati.</p>\n";
            echo "</div>\n";
        }
        echo "</div>\n";
    }
    
    // 6. RACCOMANDAZIONI FINALI
    echo "<div class='analysis-section info-section'>\n";
    echo "<h3>6. 💡 Raccomandazioni e Azioni</h3>\n";
    
    echo "<div class='row'>\n";
    echo "<div class='col-md-6'>\n";
    echo "<h5>🎯 Azioni Immediate:</h5>\n";
    echo "<ol>\n";
    
    if ($legacy_has_data && $master_has_data) {
        echo "<li><strong>Aggiornare diagnose_data_master.php</strong> per utilizzare master tables invece di legacy</li>\n";
        echo "<li>Verificare altri file che usano tabelle legacy</li>\n";
        echo "<li>Pianificare eliminazione tabelle legacy dopo verifica</li>\n";
    } elseif ($legacy_has_data && !$master_has_data) {
        echo "<li><strong>Eseguire setup master tables</strong> con <code>simple_setup_mariadb.php</code></li>\n";
        echo "<li>Migrare dati puliti da legacy a master</li>\n";
        echo "<li>Aggiornare diagnose_data_master.php dopo migrazione</li>\n";
    } elseif (!$legacy_has_data && $master_has_data) {
        echo "<li><strong>Aggiornare diagnose_data_master.php</strong> immediatamente</li>\n";
        echo "<li>Rimuovere riferimenti a tabelle legacy</li>\n";
    } else {
        echo "<li><strong>Importare dati iniziali</strong> nel sistema</li>\n";
        echo "<li>Eseguire setup completo database</li>\n";
    }
    
    echo "</ol>\n";
    echo "</div>\n";
    
    echo "<div class='col-md-6'>\n";
    echo "<h5>🔧 File da Modificare:</h5>\n";
    echo "<ul>\n";
    echo "<li><code>diagnose_data_master.php</code> - Query su master tables</li>\n";
    echo "<li>Altri file di analisi e reporting</li>\n";
    echo "<li>Dashboard principale se necessario</li>\n";
    echo "</ul>\n";
    
    echo "<h5>📋 Script Pronti:</h5>\n";
    echo "<ul>\n";
    echo "<li><code>simple_setup_mariadb.php</code> - Setup master tables</li>\n";
    echo "<li><code>smart_upload_final.php</code> - Upload con master data</li>\n";
    echo "<li><code>master_data_console.php</code> - Gestione master data</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    // 7. COLLEGAMENTI RAPIDI
    echo "<div class='analysis-section'>\n";
    echo "<h3>7. 🚀 Azioni Rapide</h3>\n";
    echo "<div class='d-grid gap-2 d-md-flex justify-content-md-start'>\n";
    
    if (!$master_has_data) {
        echo "<a href='simple_setup_mariadb.php' class='btn btn-primary'>🔧 Setup Master Tables</a>\n";
    }
    
    echo "<a href='diagnose_data_master.php' class='btn btn-warning'>📊 Diagnose Data (Legacy)</a>\n";
    echo "<a href='master_data_console.php' class='btn btn-success'>🎛️ Master Data Console</a>\n";
    echo "<a href='smart_upload_final.php' class='btn btn-info'>📤 Smart Upload</a>\n";
    echo "</div>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>\n";
    echo "<h4>❌ Errore durante analisi</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "</div>\n"; // Close container
echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>\n";
echo "</body>\n</html>\n";
?>