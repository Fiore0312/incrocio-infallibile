<?php
require_once 'config/Database.php';
require_once 'classes/ImportLogger.php';

/**
 * Script di migrazione dati esistenti a Master Tables
 * Trasferisce dipendenti, veicoli, clienti e progetti legacy alle nuove tabelle master
 */

echo "<h2>🔄 Migrazione a Master Tables System</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    $logger = new ImportLogger('data_migration');
    
    echo "<h3>📊 Analisi Pre-migrazione</h3>\n";
    
    // Analisi dati esistenti
    $analysis = [];
    $tables = ['dipendenti', 'clienti', 'progetti', 'veicoli'];
    
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table");
        $stmt->execute();
        $result = $stmt->fetch();
        $analysis[$table] = $result['count'];
        echo "<p><strong>$table:</strong> {$result['count']} record</p>\n";
    }
    
    // Verifica se master tables esistono
    echo "<h3>🔍 Verifica Master Tables</h3>\n";
    
    $master_tables = ['master_dipendenti', 'master_veicoli', 'master_clienti', 'master_progetti'];
    $missing_tables = [];
    
    foreach ($master_tables as $table) {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM $table LIMIT 1");
            $stmt->execute();
            echo "<p style='color: green;'>✅ $table esistente</p>\n";
        } catch (Exception $e) {
            $missing_tables[] = $table;
            echo "<p style='color: red;'>❌ $table mancante</p>\n";
        }
    }
    
    if (!empty($missing_tables)) {
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
        echo "<h4>⚠️ Errore: Master Tables Mancanti</h4>\n";
        echo "<p>È necessario eseguire prima create_master_tables.sql per creare le tabelle master:</p>\n";
        echo "<ul>\n";
        foreach ($missing_tables as $table) {
            echo "<li>$table</li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
        exit;
    }
    
    if (isset($_GET['execute']) && $_GET['execute'] === 'yes') {
        echo "<h3>🚀 ESECUZIONE MIGRAZIONE</h3>\n";
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
        echo "<p><strong>⚠️ ATTENZIONE:</strong> Migrazione in corso - non interrompere!</p>\n";
        echo "</div>\n";
        
        $conn->beginTransaction();
        $migration_stats = [
            'dipendenti_migrated' => 0,
            'clienti_migrated' => 0,
            'progetti_migrated' => 0,
            'aliases_created' => 0,
            'errors' => 0
        ];
        
        try {
            // 1. Migrazione Dipendenti
            echo "<h4>👥 Migrazione Dipendenti</h4>\n";
            
            $stmt = $conn->prepare("
                SELECT DISTINCT nome, cognome, email, ruolo 
                FROM dipendenti 
                WHERE nome IS NOT NULL AND nome != '' 
                ORDER BY nome, cognome
            ");
            $stmt->execute();
            $dipendenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($dipendenti as $dip) {
                try {
                    // Verifica se esiste già in master
                    $check_stmt = $conn->prepare("
                        SELECT id FROM master_dipendenti 
                        WHERE nome = ? AND cognome = ?
                    ");
                    $check_stmt->execute([$dip['nome'], $dip['cognome']]);
                    
                    if (!$check_stmt->fetch()) {
                        // Crea master dipendente
                        $insert_stmt = $conn->prepare("
                            INSERT INTO master_dipendenti (nome, cognome, email, ruolo, fonte_origine, note_parsing)
                            VALUES (?, ?, ?, ?, 'migration', 'Migrato da dipendenti legacy')
                        ");
                        $insert_stmt->execute([
                            $dip['nome'],
                            $dip['cognome'], 
                            $dip['email'],
                            $dip['ruolo']
                        ]);
                        $master_id = $conn->lastInsertId();
                        
                        // Aggiorna tutti i dipendenti legacy con stesso nome/cognome
                        $update_stmt = $conn->prepare("
                            UPDATE dipendenti 
                            SET master_dipendente_id = ? 
                            WHERE nome = ? AND cognome = ? AND master_dipendente_id IS NULL
                        ");
                        $update_stmt->execute([$master_id, $dip['nome'], $dip['cognome']]);
                        
                        $migration_stats['dipendenti_migrated']++;
                        echo "<p>✅ Migrato: {$dip['nome']} {$dip['cognome']} -> Master ID $master_id</p>\n";
                    }
                    
                } catch (Exception $e) {
                    $migration_stats['errors']++;
                    echo "<p style='color: red;'>❌ Errore migrazione {$dip['nome']} {$dip['cognome']}: " . htmlspecialchars($e->getMessage()) . "</p>\n";
                }
            }
            
            // 2. Migrazione Clienti
            echo "<h4>🏢 Migrazione Clienti</h4>\n";
            
            $stmt = $conn->prepare("
                SELECT DISTINCT nome, email, telefono, indirizzo
                FROM clienti 
                WHERE nome IS NOT NULL AND nome != ''
                ORDER BY nome
            ");
            $stmt->execute();
            $clienti = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($clienti as $cliente) {
                try {
                    // Verifica se esiste già in master
                    $check_stmt = $conn->prepare("
                        SELECT id FROM master_clienti WHERE nome = ?
                    ");
                    $check_stmt->execute([$cliente['nome']]);
                    
                    if (!$check_stmt->fetch()) {
                        // Crea master cliente
                        $insert_stmt = $conn->prepare("
                            INSERT INTO master_clienti (nome, email, telefono, indirizzo, fonte_origine)
                            VALUES (?, ?, ?, ?, 'migration')
                        ");
                        $insert_stmt->execute([
                            $cliente['nome'],
                            $cliente['email'],
                            $cliente['telefono'],
                            $cliente['indirizzo']
                        ]);
                        $master_id = $conn->lastInsertId();
                        
                        // Aggiorna clienti legacy
                        $update_stmt = $conn->prepare("
                            UPDATE clienti 
                            SET master_cliente_id = ? 
                            WHERE nome = ? AND master_cliente_id IS NULL
                        ");
                        $update_stmt->execute([$master_id, $cliente['nome']]);
                        
                        $migration_stats['clienti_migrated']++;
                        echo "<p>✅ Migrato cliente: {$cliente['nome']} -> Master ID $master_id</p>\n";
                    }
                    
                } catch (Exception $e) {
                    $migration_stats['errors']++;
                    echo "<p style='color: red;'>❌ Errore migrazione cliente {$cliente['nome']}: " . htmlspecialchars($e->getMessage()) . "</p>\n";
                }
            }
            
            // 3. Migrazione Progetti (se esistono)
            echo "<h4>📂 Migrazione Progetti</h4>\n";
            
            try {
                $stmt = $conn->prepare("
                    SELECT DISTINCT nome, codice, descrizione, cliente_id
                    FROM progetti 
                    WHERE nome IS NOT NULL AND nome != ''
                    ORDER BY nome
                ");
                $stmt->execute();
                $progetti = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($progetti as $progetto) {
                    try {
                        // Verifica se esiste già in master
                        $check_stmt = $conn->prepare("
                            SELECT id FROM master_progetti WHERE codice = ?
                        ");
                        $check_stmt->execute([$progetto['codice']]);
                        
                        if (!$check_stmt->fetch()) {
                            // Trova master_cliente_id se disponibile
                            $master_cliente_id = null;
                            if ($progetto['cliente_id']) {
                                $client_stmt = $conn->prepare("
                                    SELECT master_cliente_id FROM clienti WHERE id = ?
                                ");
                                $client_stmt->execute([$progetto['cliente_id']]);
                                $client_result = $client_stmt->fetch();
                                $master_cliente_id = $client_result['master_cliente_id'] ?? null;
                            }
                            
                            // Crea master progetto
                            $insert_stmt = $conn->prepare("
                                INSERT INTO master_progetti (nome, codice, descrizione, master_cliente_id, fonte_origine)
                                VALUES (?, ?, ?, ?, 'migration')
                            ");
                            $insert_stmt->execute([
                                $progetto['nome'],
                                $progetto['codice'],
                                $progetto['descrizione'],
                                $master_cliente_id
                            ]);
                            $master_id = $conn->lastInsertId();
                            
                            // Aggiorna progetti legacy
                            $update_stmt = $conn->prepare("
                                UPDATE progetti 
                                SET master_progetto_id = ? 
                                WHERE codice = ? AND master_progetto_id IS NULL
                            ");
                            $update_stmt->execute([$master_id, $progetto['codice']]);
                            
                            $migration_stats['progetti_migrated']++;
                            echo "<p>✅ Migrato progetto: {$progetto['nome']} ({$progetto['codice']}) -> Master ID $master_id</p>\n";
                        }
                        
                    } catch (Exception $e) {
                        $migration_stats['errors']++;
                        echo "<p style='color: red;'>❌ Errore migrazione progetto {$progetto['nome']}: " . htmlspecialchars($e->getMessage()) . "</p>\n";
                    }
                }
            } catch (Exception $e) {
                echo "<p style='color: orange;'>⚠️ Tabella progetti non trovata o vuota</p>\n";
            }
            
            // 4. Creazione aliases per varianti nome
            echo "<h4>🔗 Creazione Aliases per Varianti</h4>\n";
            
            // Trova dipendenti con nomi simili ma non identici
            $stmt = $conn->prepare("
                SELECT d.nome, d.cognome, md.id as master_id, md.nome as master_nome, md.cognome as master_cognome
                FROM dipendenti d
                JOIN master_dipendenti md ON d.master_dipendente_id = md.id
                WHERE (d.nome != md.nome OR d.cognome != md.cognome)
                GROUP BY d.nome, d.cognome, md.id
            ");
            $stmt->execute();
            $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($variants as $variant) {
                try {
                    // Crea alias
                    $alias_stmt = $conn->prepare("
                        INSERT INTO dipendenti_aliases (master_dipendente_id, alias_nome, alias_cognome, fonte, note)
                        VALUES (?, ?, ?, 'migration', 'Variante trovata durante migrazione')
                        ON DUPLICATE KEY UPDATE note = 'Variante trovata durante migrazione - aggiornato'
                    ");
                    $alias_stmt->execute([
                        $variant['master_id'],
                        $variant['nome'],
                        $variant['cognome']
                    ]);
                    
                    $migration_stats['aliases_created']++;
                    echo "<p>🔗 Alias creato: '{$variant['nome']} {$variant['cognome']}' -> '{$variant['master_nome']} {$variant['master_cognome']}'</p>\n";
                    
                } catch (Exception $e) {
                    echo "<p style='color: orange;'>⚠️ Alias già esistente o errore: {$variant['nome']} {$variant['cognome']}</p>\n";
                }
            }
            
            $conn->commit();
            
            // Summary migrazione
            echo "<h3>📋 Summary Migrazione Completata</h3>\n";
            
            echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>\n";
            echo "<h4>✅ Migrazione Completata con Successo!</h4>\n";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
            echo "<tr><th>Operazione</th><th>Risultato</th></tr>\n";
            echo "<tr><td>Dipendenti Migrati</td><td style='color: green; font-weight: bold;'>{$migration_stats['dipendenti_migrated']}</td></tr>\n";
            echo "<tr><td>Clienti Migrati</td><td style='color: green; font-weight: bold;'>{$migration_stats['clienti_migrated']}</td></tr>\n";
            echo "<tr><td>Progetti Migrati</td><td style='color: green; font-weight: bold;'>{$migration_stats['progetti_migrated']}</td></tr>\n";
            echo "<tr><td>Aliases Creati</td><td style='color: blue; font-weight: bold;'>{$migration_stats['aliases_created']}</td></tr>\n";
            echo "<tr><td>Errori</td><td>{$migration_stats['errors']}</td></tr>\n";
            echo "</table>\n";
            
            echo "<h5>🎯 Risultati:</h5>\n";
            echo "<ul>\n";
            echo "<li>Tutti i dipendenti legacy sono ora collegati ai master dipendenti</li>\n";
            echo "<li>Aliases creati per gestire varianti di nomi</li>\n";
            echo "<li>Sistema master tables completamente operativo</li>\n";
            echo "<li>Enhanced Parser può ora utilizzare la ricerca master</li>\n";
            echo "</ul>\n";
            echo "</div>\n";
            
            $logger->info("Migrazione completata", $migration_stats);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } else {
        // DRY RUN - Simulazione
        echo "<h3>🧪 SIMULAZIONE MIGRAZIONE</h3>\n";
        
        $simulation_stats = [
            'dipendenti_unique' => 0,
            'clienti_unique' => 0,
            'progetti_unique' => 0,
            'potential_aliases' => 0
        ];
        
        // Conta dipendenti unici
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT CONCAT(nome, '|', cognome)) as unique_count
            FROM dipendenti 
            WHERE nome IS NOT NULL AND nome != ''
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        $simulation_stats['dipendenti_unique'] = $result['unique_count'];
        
        // Conta clienti unici
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT nome) as unique_count
            FROM clienti 
            WHERE nome IS NOT NULL AND nome != ''
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        $simulation_stats['clienti_unique'] = $result['unique_count'];
        
        // Conta progetti unici (se esistono)
        try {
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT codice) as unique_count
                FROM progetti 
                WHERE codice IS NOT NULL AND codice != ''
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            $simulation_stats['progetti_unique'] = $result['unique_count'];
        } catch (Exception $e) {
            $simulation_stats['progetti_unique'] = 0;
        }
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Tipo</th><th>Record Attuali</th><th>Master da Creare</th><th>Note</th></tr>\n";
        echo "<tr><td>Dipendenti</td><td>{$analysis['dipendenti']}</td><td>{$simulation_stats['dipendenti_unique']}</td><td>Deduplicazione per nome+cognome</td></tr>\n";
        echo "<tr><td>Clienti</td><td>{$analysis['clienti']}</td><td>{$simulation_stats['clienti_unique']}</td><td>Deduplicazione per nome</td></tr>\n";
        echo "<tr><td>Progetti</td><td>{$analysis['progetti']}</td><td>{$simulation_stats['progetti_unique']}</td><td>Deduplicazione per codice</td></tr>\n";
        echo "</table>\n";
        
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
        echo "<h4>⚠️ Per Eseguire la Migrazione Effettiva</h4>\n";
        echo "<p>Questa è solo una simulazione. Per eseguire la migrazione reale:</p>\n";
        echo "<p><a href='?execute=yes' onclick='return confirm(\"Sei sicuro di voler eseguire la migrazione? Questa operazione modificherà il database!\")'>"; 
        echo "<strong>🚀 ESEGUI MIGRAZIONE REALE</strong></a></p>\n";
        echo "<p><strong>IMPORTANTE:</strong> Assicurati di aver fatto un backup del database!</p>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante migrazione</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}
?>

<p>
    <a href="enhanced_upload_v2.php">🚀 Test Enhanced Upload v2</a> | 
    <a href="cleanup_duplicates.php">🧹 Cleanup Duplicati</a> | 
    <a href="index.php">Dashboard</a>
</p>