<?php
require_once 'config/Database.php';
require_once 'classes/ImportLogger.php';

/**
 * Esecuzione Correzioni Fase 1 - Pulizia Database
 * Script per correggere tutti i problemi identificati
 */

echo "<h2>🔧 Esecuzione Correzioni Fase 1 - Pulizia Database</h2>\n";

// Verifica se l'utente vuole eseguire le correzioni
$execute_mode = isset($_GET['execute']) && $_GET['execute'] === 'yes';
$dry_run = !$execute_mode;

if ($dry_run) {
    echo "<div style='background: #cce5ff; padding: 15px; border: 2px solid blue; border-radius: 10px;'>\n";
    echo "<h3 style='color: blue;'>🧪 MODALITÀ SIMULAZIONE (DRY RUN)</h3>\n";
    echo "<p>Questa è una simulazione. Nessuna modifica verrà apportata al database.</p>\n";
    echo "<p>Per eseguire le correzioni reali: <a href='?execute=yes' style='background: red; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px;'><strong>ESEGUI CORREZIONI</strong></a></p>\n";
    echo "</div>\n";
} else {
    echo "<div style='background: #fff3cd; padding: 15px; border: 2px solid orange; border-radius: 10px;'>\n";
    echo "<h3 style='color: red;'>⚠️ MODALITÀ ESECUZIONE - MODIFICHE REALI AL DATABASE</h3>\n";
    echo "<p>Le modifiche verranno applicate al database. Assicurati di aver fatto un backup!</p>\n";
    echo "</div>\n";
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $logger = new ImportLogger('phase1_fixes');
    
    $fixes_applied = [];
    $errors_encountered = [];
    
    if (!$dry_run) {
        $conn->beginTransaction();
    }
    
    // CORREZIONE 1: Elimina Andrea Bianchi
    echo "<h3>1. 🗑️ Eliminazione 'Andrea Bianchi'</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT id, nome, cognome, email, attivo,
               (SELECT COUNT(*) FROM attivita WHERE dipendente_id = d.id) as attivita_count,
               (SELECT COUNT(*) FROM timbrature WHERE dipendente_id = d.id) as timbrature_count,
               (SELECT COUNT(*) FROM kpi_giornalieri WHERE dipendente_id = d.id) as kpi_count
        FROM dipendenti d 
        WHERE nome = 'Andrea' AND cognome = 'Bianchi'
    ");
    $stmt->execute();
    $andrea_records = $stmt->fetchAll();
    
    if (!empty($andrea_records)) {
        foreach ($andrea_records as $ab) {
            $safe_to_delete = ($ab['attivita_count'] == 0 && $ab['timbrature_count'] == 0 && $ab['kpi_count'] == 0);
            
            echo "<div style='background: " . ($safe_to_delete ? '#e6ffe6' : '#ffe6e6') . "; padding: 10px; margin: 5px 0; border-left: 4px solid " . ($safe_to_delete ? 'green' : 'red') . ";'>\n";
            echo "<p><strong>Andrea Bianchi (ID: {$ab['id']})</strong></p>\n";
            echo "<ul>\n";
            echo "<li>Email: {$ab['email']}</li>\n";
            echo "<li>Attività: {$ab['attivita_count']}</li>\n";
            echo "<li>Timbrature: {$ab['timbrature_count']}</li>\n";
            echo "<li>KPI: {$ab['kpi_count']}</li>\n";
            echo "</ul>\n";
            
            if ($safe_to_delete) {
                if ($dry_run) {
                    echo "<p style='color: green;'><strong>🧪 SIMULAZIONE:</strong> Verrebbe eliminato (nessuna dipendenza)</p>\n";
                } else {
                    $stmt = $conn->prepare("DELETE FROM dipendenti WHERE id = ?");
                    $stmt->execute([$ab['id']]);
                    echo "<p style='color: green;'><strong>✅ ELIMINATO:</strong> Andrea Bianchi (ID: {$ab['id']})</p>\n";
                    $fixes_applied[] = "Eliminato Andrea Bianchi (ID: {$ab['id']})";
                    $logger->info("Eliminato dipendente invalido: Andrea Bianchi", ['id' => $ab['id']]);
                }
            } else {
                echo "<p style='color: red;'><strong>❌ NON ELIMINATO:</strong> Ha dipendenze nel database</p>\n";
                $errors_encountered[] = "Andrea Bianchi (ID: {$ab['id']}) non eliminato - ha dipendenze";
            }
            echo "</div>\n";
        }
    } else {
        echo "<p style='color: green;'>✅ Andrea Bianchi non trovato nel database</p>\n";
    }
    
    // CORREZIONE 2: Fix parsing "Franco Fiorellino/Matteo Signo"
    echo "<h3>2. 🔧 Correzione Parsing Nomi Errati</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT id, nome, cognome, email, attivo
        FROM dipendenti 
        WHERE (nome LIKE '%Franco%' AND cognome LIKE '%Fiorellino/Matteo Signo%')
           OR (cognome LIKE '%/%')
           OR (nome LIKE '%/%')
           OR (CONCAT(nome, ' ', cognome) LIKE '%Fiorellino/Matteo Signo%')
    ");
    $stmt->execute();
    $parsing_errors = $stmt->fetchAll();
    
    if (!empty($parsing_errors)) {
        foreach ($parsing_errors as $pe) {
            echo "<div style='background: #fff3cd; padding: 10px; margin: 5px 0; border-left: 4px solid orange;'>\n";
            echo "<p><strong>Parsing errato: '{$pe['nome']}' '{$pe['cognome']}' (ID: {$pe['id']})</strong></p>\n";
            
            // Determina la correzione
            if (strpos($pe['cognome'], 'Fiorellino/Matteo Signo') !== false) {
                $new_nome = 'Matteo';
                $new_cognome = 'Signo';
                echo "<p><strong>🔧 CORREZIONE:</strong> Cambio in nome='$new_nome', cognome='$new_cognome'</p>\n";
                
                if ($dry_run) {
                    echo "<p style='color: blue;'><strong>🧪 SIMULAZIONE:</strong> Verrebbe corretto</p>\n";
                } else {
                    $stmt = $conn->prepare("UPDATE dipendenti SET nome = ?, cognome = ? WHERE id = ?");
                    $stmt->execute([$new_nome, $new_cognome, $pe['id']]);
                    echo "<p style='color: green;'><strong>✅ CORRETTO:</strong> {$pe['nome']} {$pe['cognome']} → $new_nome $new_cognome</p>\n";
                    $fixes_applied[] = "Corretto parsing: ID {$pe['id']} → $new_nome $new_cognome";
                    $logger->info("Corretto parsing errato", ['id' => $pe['id'], 'old' => "{$pe['nome']} {$pe['cognome']}", 'new' => "$new_nome $new_cognome"]);
                }
            } else {
                echo "<p style='color: orange;'><strong>⚠️ PARSING NON STANDARD:</strong> Richiede verifica manuale</p>\n";
                $errors_encountered[] = "Parsing non standard: {$pe['nome']} {$pe['cognome']} (ID: {$pe['id']})";
            }
            echo "</div>\n";
        }
    } else {
        echo "<p style='color: green;'>✅ Nessun errore di parsing trovato</p>\n";
    }
    
    // CORREZIONE 3: Aggiungi dipendenti mancanti
    echo "<h3>3. ➕ Aggiunta Dipendenti Mancanti</h3>\n";
    
    $dipendenti_richiesti = [
        ['nome' => 'Niccolò', 'cognome' => 'Ragusa'],
        ['nome' => 'Davide', 'cognome' => 'Cestone'],
        ['nome' => 'Arlind', 'cognome' => 'Hoxha'],
        ['nome' => 'Lorenzo', 'cognome' => 'Serratore'],
        ['nome' => 'Gabriele', 'cognome' => 'De Palma'],
        ['nome' => 'Franco', 'cognome' => 'Fiorellino'],
        ['nome' => 'Matteo', 'cognome' => 'Signo'],
        ['nome' => 'Marco', 'cognome' => 'Birocchi'],
        ['nome' => 'Roberto', 'cognome' => 'Birocchi'],
        ['nome' => 'Alex', 'cognome' => 'Ferrario'],
        ['nome' => 'Gianluca', 'cognome' => 'Ghirindelli'],
        ['nome' => 'Matteo', 'cognome' => 'Di Salvo'],
        ['nome' => 'Cristian', 'cognome' => 'La Bella'],
        ['nome' => 'Giuseppe', 'cognome' => 'Anastasio']
    ];
    
    $dipendenti_aggiunti = 0;
    
    foreach ($dipendenti_richiesti as $dip_req) {
        $stmt = $conn->prepare("SELECT id FROM dipendenti WHERE nome = ? AND cognome = ?");
        $stmt->execute([$dip_req['nome'], $dip_req['cognome']]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            echo "<div style='background: #e6ffe6; padding: 10px; margin: 5px 0; border-left: 4px solid green;'>\n";
            echo "<p><strong>➕ AGGIUNGENDO: {$dip_req['nome']} {$dip_req['cognome']}</strong></p>\n";
            
            if ($dry_run) {
                echo "<p style='color: blue;'><strong>🧪 SIMULAZIONE:</strong> Verrebbe aggiunto come dipendente attivo</p>\n";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO dipendenti (nome, cognome, attivo, costo_giornaliero, ruolo) 
                    VALUES (?, ?, 1, 200.00, 'Tecnico')
                ");
                $stmt->execute([$dip_req['nome'], $dip_req['cognome']]);
                $new_id = $conn->lastInsertId();
                echo "<p style='color: green;'><strong>✅ AGGIUNTO:</strong> {$dip_req['nome']} {$dip_req['cognome']} (ID: $new_id)</p>\n";
                $fixes_applied[] = "Aggiunto dipendente: {$dip_req['nome']} {$dip_req['cognome']} (ID: $new_id)";
                $logger->info("Aggiunto dipendente mancante", ['nome' => $dip_req['nome'], 'cognome' => $dip_req['cognome'], 'id' => $new_id]);
                $dipendenti_aggiunti++;
            }
            echo "</div>\n";
        }
    }
    
    if ($dipendenti_aggiunti == 0 && !$dry_run) {
        echo "<p style='color: green;'>✅ Tutti i dipendenti richiesti sono già presenti</p>\n";
    }
    
    // CORREZIONE 4: Cleanup duplicati timbrature
    echo "<h3>4. 🧹 Cleanup Duplicati Timbrature</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT 
            dipendente_id,
            data,
            ora_inizio,
            ora_fine,
            COUNT(*) as duplicates,
            GROUP_CONCAT(id ORDER BY id) as record_ids
        FROM timbrature 
        GROUP BY dipendente_id, data, ora_inizio, ora_fine
        HAVING COUNT(*) > 1
        ORDER BY duplicates DESC
    ");
    $stmt->execute();
    $duplicati_timbrature = $stmt->fetchAll();
    
    if (!empty($duplicati_timbrature)) {
        $duplicati_processati = 0;
        foreach ($duplicati_timbrature as $dt) {
            $ids = explode(',', $dt['record_ids']);
            $keep_id = $ids[0]; // Mantieni il primo
            $delete_ids = array_slice($ids, 1); // Elimina gli altri
            
            echo "<div style='background: #fff3cd; padding: 10px; margin: 5px 0; border-left: 4px solid orange;'>\n";
            echo "<p><strong>Duplicato Timbrature:</strong> Dipendente {$dt['dipendente_id']}, {$dt['data']} {$dt['ora_inizio']}-{$dt['ora_fine']}</p>\n";
            echo "<p>Record: {$dt['record_ids']} | Mantieni: $keep_id | Elimina: " . implode(',', $delete_ids) . "</p>\n";
            
            if ($dry_run) {
                echo "<p style='color: blue;'><strong>🧪 SIMULAZIONE:</strong> Verrebbero eliminati " . count($delete_ids) . " duplicati</p>\n";
            } else {
                foreach ($delete_ids as $delete_id) {
                    $stmt = $conn->prepare("DELETE FROM timbrature WHERE id = ?");
                    $stmt->execute([$delete_id]);
                }
                echo "<p style='color: green;'><strong>✅ ELIMINATI:</strong> " . count($delete_ids) . " duplicati</p>\n";
                $duplicati_processati += count($delete_ids);
            }
            echo "</div>\n";
        }
        
        if (!$dry_run && $duplicati_processati > 0) {
            $fixes_applied[] = "Eliminati $duplicati_processati duplicati timbrature";
            $logger->info("Cleanup duplicati timbrature", ['eliminati' => $duplicati_processati]);
        }
    } else {
        echo "<p style='color: green;'>✅ Nessun duplicato timbrature trovato</p>\n";
    }
    
    // CORREZIONE 5: Cleanup duplicati attività (soft delete)
    echo "<h3>5. 🧹 Cleanup Duplicati Attività</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT 
            dipendente_id,
            data_inizio,
            durata_ore,
            SUBSTRING(descrizione, 1, 50) as desc_short,
            COUNT(*) as duplicates,
            GROUP_CONCAT(id ORDER BY id) as record_ids
        FROM attivita 
        WHERE is_duplicate IS NULL OR is_duplicate = 0
        GROUP BY dipendente_id, data_inizio, durata_ore, SUBSTRING(descrizione, 1, 50)
        HAVING COUNT(*) > 1
        ORDER BY duplicates DESC
    ");
    $stmt->execute();
    $duplicati_attivita = $stmt->fetchAll();
    
    if (!empty($duplicati_attivita)) {
        $duplicati_marcati = 0;
        foreach ($duplicati_attivita as $da) {
            $ids = explode(',', $da['record_ids']);
            $keep_id = $ids[0]; // Mantieni il primo
            $mark_ids = array_slice($ids, 1); // Marca gli altri come duplicati
            
            echo "<div style='background: #fff3cd; padding: 10px; margin: 5px 0; border-left: 4px solid orange;'>\n";
            echo "<p><strong>Duplicato Attività:</strong> Dipendente {$da['dipendente_id']}, {$da['data_inizio']}, {$da['durata_ore']}h</p>\n";
            echo "<p>Descrizione: " . htmlspecialchars($da['desc_short']) . "...</p>\n";
            echo "<p>Record: {$da['record_ids']} | Mantieni: $keep_id | Marca duplicati: " . implode(',', $mark_ids) . "</p>\n";
            
            if ($dry_run) {
                echo "<p style='color: blue;'><strong>🧪 SIMULAZIONE:</strong> Verrebbero marcati " . count($mark_ids) . " come duplicati</p>\n";
            } else {
                foreach ($mark_ids as $mark_id) {
                    $stmt = $conn->prepare("UPDATE attivita SET is_duplicate = 1 WHERE id = ?");
                    $stmt->execute([$mark_id]);
                }
                echo "<p style='color: green;'><strong>✅ MARCATI:</strong> " . count($mark_ids) . " come duplicati</p>\n";
                $duplicati_marcati += count($mark_ids);
            }
            echo "</div>\n";
        }
        
        if (!$dry_run && $duplicati_marcati > 0) {
            $fixes_applied[] = "Marcati $duplicati_marcati duplicati attività";
            $logger->info("Cleanup duplicati attività", ['marcati' => $duplicati_marcati]);
        }
    } else {
        echo "<p style='color: green;'>✅ Nessun duplicato attività trovato</p>\n";
    }
    
    // COMMIT o ROLLBACK
    if (!$dry_run) {
        if (empty($errors_encountered)) {
            $conn->commit();
            echo "<div style='background: #d4edda; padding: 15px; border: 2px solid green; border-radius: 10px; margin: 20px 0;'>\n";
            echo "<h3 style='color: green;'>✅ FASE 1 COMPLETATA CON SUCCESSO!</h3>\n";
            echo "<p><strong>Transazione committata.</strong> Tutte le modifiche sono state applicate.</p>\n";
        } else {
            $conn->rollback();
            echo "<div style='background: #f8d7da; padding: 15px; border: 2px solid red; border-radius: 10px; margin: 20px 0;'>\n";
            echo "<h3 style='color: red;'>❌ ERRORI RISCONTRATI - ROLLBACK ESEGUITO</h3>\n";
            echo "<p><strong>Nessuna modifica applicata.</strong> Correggere gli errori e riprovare.</p>\n";
        }
    }
    
    // SUMMARY FINALE
    echo "<h3>📊 Summary Operazioni</h3>\n";
    
    if ($dry_run) {
        echo "<div style='background: #cce5ff; padding: 15px; border: 1px solid blue; border-radius: 5px;'>\n";
        echo "<h4 style='color: blue;'>🧪 SIMULAZIONE COMPLETATA</h4>\n";
        echo "<p>Questa era una simulazione. Nessuna modifica è stata applicata al database.</p>\n";
        echo "<p><strong>Per eseguire le correzioni reali:</strong> <a href='?execute=yes' style='background: red; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px;'>ESEGUI CORREZIONI</a></p>\n";
        echo "</div>\n";
    } else {
        if (!empty($fixes_applied)) {
            echo "<h4 style='color: green;'>✅ Correzioni Applicate (" . count($fixes_applied) . "):</h4>\n";
            echo "<ul>\n";
            foreach ($fixes_applied as $fix) {
                echo "<li>$fix</li>\n";
            }
            echo "</ul>\n";
        }
        
        if (!empty($errors_encountered)) {
            echo "<h4 style='color: red;'>❌ Errori Incontrati (" . count($errors_encountered) . "):</h4>\n";
            echo "<ul>\n";
            foreach ($errors_encountered as $error) {
                echo "<li>$error</li>\n";
            }
            echo "</ul>\n";
        }
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante esecuzione correzioni</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Rollback eseguito.</strong> Nessuna modifica applicata.</p>\n";
    echo "</div>\n";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; }
    h2, h3, h4 { color: #333; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; font-weight: bold; }
    code { background-color: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
</style>

<p>
    <a href="analyze_current_issues.php">🔍 Analisi Problemi</a> | 
    <a href="diagnose_data_master.php">📊 Diagnostica</a> | 
    <a href="index.php">🏠 Dashboard</a>
</p>