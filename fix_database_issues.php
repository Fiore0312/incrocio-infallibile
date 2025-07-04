<?php
require_once 'config/Database.php';
require_once 'classes/ImportLogger.php';

/**
 * Script di Pulizia Database - Fase 1
 * Risolve i problemi specifici identificati nella diagnostica
 */

echo "<h2>🔧 Fase 1: Pulizia Database Immediata</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    $logger = new ImportLogger('database_cleanup');
    
    $issues_found = [];
    $issues_fixed = [];
    $issues_errors = [];
    
    // 1. Identifica "Andrea Bianchi" da eliminare
    echo "<h3>1. 🔍 Ricerca Andrea Bianchi</h3>\n";
    
    $stmt = $conn->prepare("SELECT id, nome, cognome, email FROM dipendenti WHERE nome = 'Andrea' AND cognome = 'Bianchi'");
    $stmt->execute();
    $andrea_bianchi = $stmt->fetchAll();
    
    if (!empty($andrea_bianchi)) {
        foreach ($andrea_bianchi as $ab) {
            echo "<p style='color: red;'>❌ Trovato: Andrea Bianchi (ID: {$ab['id']}, Email: {$ab['email']})</p>\n";
            $issues_found[] = "Andrea Bianchi ID {$ab['id']}";
        }
    } else {
        echo "<p style='color: green;'>✅ Andrea Bianchi non trovato nel database</p>\n";
    }
    
    // 2. Identifica problema "Franco Fiorellino/Matteo Signo"
    echo "<h3>2. 🔍 Ricerca problemi parsing nomi</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT id, nome, cognome, email 
        FROM dipendenti 
        WHERE cognome LIKE '%/%' 
           OR cognome LIKE '%Fiorellino/Matteo Signo%'
           OR nome LIKE '%/%'
    ");
    $stmt->execute();
    $parsing_issues = $stmt->fetchAll();
    
    if (!empty($parsing_issues)) {
        foreach ($parsing_issues as $pi) {
            echo "<p style='color: red;'>❌ Parsing errato: {$pi['nome']} {$pi['cognome']} (ID: {$pi['id']})</p>\n";
            $issues_found[] = "Parsing errato: {$pi['nome']} {$pi['cognome']} (ID: {$pi['id']})";
        }
    } else {
        echo "<p style='color: green;'>✅ Nessun problema di parsing nome/cognome trovato</p>\n";
    }
    
    // 3. Verifica dipendenti mancanti
    echo "<h3>3. 🔍 Verifica Dipendenti Mancanti</h3>\n";
    
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
    
    $dipendenti_mancanti = [];
    $dipendenti_presenti = [];
    
    foreach ($dipendenti_richiesti as $dip_req) {
        $stmt = $conn->prepare("
            SELECT id, nome, cognome, attivo 
            FROM dipendenti 
            WHERE nome = ? AND cognome = ?
        ");
        $stmt->execute([$dip_req['nome'], $dip_req['cognome']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $status = $existing['attivo'] ? 'Attivo' : 'Inattivo';
            echo "<p style='color: green;'>✅ {$dip_req['nome']} {$dip_req['cognome']} - Presente (ID: {$existing['id']}, $status)</p>\n";
            $dipendenti_presenti[] = $dip_req;
        } else {
            echo "<p style='color: red;'>❌ {$dip_req['nome']} {$dip_req['cognome']} - Mancante</p>\n";
            $dipendenti_mancanti[] = $dip_req;
            $issues_found[] = "Dipendente mancante: {$dip_req['nome']} {$dip_req['cognome']}";
        }
    }
    
    // 4. Analisi duplicati
    echo "<h3>4. 🔍 Analisi Duplicati</h3>\n";
    
    // Duplicati attività
    $stmt = $conn->prepare("
        SELECT 
            dipendente_id,
            data_inizio,
            durata_ore,
            COUNT(*) as duplicates
        FROM attivita 
        GROUP BY dipendente_id, data_inizio, durata_ore
        HAVING COUNT(*) > 1
        ORDER BY duplicates DESC
        LIMIT 10
    ");
    $stmt->execute();
    $duplicati_attivita = $stmt->fetchAll();
    
    if (!empty($duplicati_attivita)) {
        echo "<p style='color: red;'>❌ Trovati " . count($duplicati_attivita) . " gruppi di attività duplicate</p>\n";
        $issues_found[] = count($duplicati_attivita) . " gruppi attività duplicate";
    } else {
        echo "<p style='color: green;'>✅ Nessun duplicato attività trovato</p>\n";
    }
    
    // Duplicati timbrature
    $stmt = $conn->prepare("
        SELECT 
            dipendente_id,
            data,
            ora_inizio,
            COUNT(*) as duplicates
        FROM timbrature 
        GROUP BY dipendente_id, data, ora_inizio
        HAVING COUNT(*) > 1
        ORDER BY duplicates DESC
        LIMIT 10
    ");
    $stmt->execute();
    $duplicati_timbrature = $stmt->fetchAll();
    
    if (!empty($duplicati_timbrature)) {
        echo "<p style='color: red;'>❌ Trovati " . count($duplicati_timbrature) . " gruppi di timbrature duplicate</p>\n";
        $issues_found[] = count($duplicati_timbrature) . " gruppi timbrature duplicate";
    } else {
        echo "<p style='color: green;'>✅ Nessun duplicato timbrature trovato</p>\n";
    }
    
    // 5. Summary problemi trovati
    echo "<h3>5. 📋 Summary Problemi Identificati</h3>\n";
    
    if (empty($issues_found)) {
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>\n";
        echo "<h4 style='color: green;'>✅ Database Pulito</h4>\n";
        echo "<p>Non sono stati trovati problemi che richiedono correzione immediata.</p>\n";
        echo "</div>\n";
    } else {
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
        echo "<h4 style='color: orange;'>⚠️ Problemi Trovati (" . count($issues_found) . ")</h4>\n";
        echo "<ul>\n";
        foreach ($issues_found as $issue) {
            echo "<li>$issue</li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
    }
    
    // ESECUZIONE CORREZIONI (solo se richiesto)
    if (isset($_GET['execute']) && $_GET['execute'] === 'yes') {
        echo "<h3>🚀 ESECUZIONE CORREZIONI</h3>\n";
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
        echo "<p><strong>⚠️ ATTENZIONE:</strong> Modifiche database in corso!</p>\n";
        echo "</div>\n";
        
        $conn->beginTransaction();
        
        try {
            // Correzione 1: Elimina Andrea Bianchi (se sicuro)
            if (!empty($andrea_bianchi)) {
                foreach ($andrea_bianchi as $ab) {
                    // Verifica dipendenze
                    $stmt = $conn->prepare("
                        SELECT 
                            (SELECT COUNT(*) FROM attivita WHERE dipendente_id = ?) as attivita_count,
                            (SELECT COUNT(*) FROM timbrature WHERE dipendente_id = ?) as timbrature_count,
                            (SELECT COUNT(*) FROM kpi_giornalieri WHERE dipendente_id = ?) as kpi_count
                    ");
                    $stmt->execute([$ab['id'], $ab['id'], $ab['id']]);
                    $deps = $stmt->fetch();
                    
                    if ($deps['attivita_count'] == 0 && $deps['timbrature_count'] == 0 && $deps['kpi_count'] == 0) {
                        $stmt = $conn->prepare("DELETE FROM dipendenti WHERE id = ?");
                        $stmt->execute([$ab['id']]);
                        echo "<p style='color: green;'>✅ Eliminato Andrea Bianchi (ID: {$ab['id']})</p>\n";
                        $issues_fixed[] = "Eliminato Andrea Bianchi";
                        $logger->info("Eliminato dipendente invalido: Andrea Bianchi (ID: {$ab['id']})");
                    } else {
                        echo "<p style='color: orange;'>⚠️ Andrea Bianchi ha dipendenze - non eliminato</p>\n";
                        $issues_errors[] = "Andrea Bianchi ha dipendenze";
                    }
                }
            }
            
            // Correzione 2: Fix parsing errati
            if (!empty($parsing_issues)) {
                foreach ($parsing_issues as $pi) {
                    // Caso specifico: "Franco Fiorellino/Matteo Signo" -> "Matteo Signo"
                    if (strpos($pi['cognome'], 'Fiorellino/Matteo Signo') !== false) {
                        $stmt = $conn->prepare("UPDATE dipendenti SET nome = 'Matteo', cognome = 'Signo' WHERE id = ?");
                        $stmt->execute([$pi['id']]);
                        echo "<p style='color: green;'>✅ Corretto: {$pi['nome']} {$pi['cognome']} → Matteo Signo</p>\n";
                        $issues_fixed[] = "Corretto parsing: Matteo Signo";
                        $logger->info("Corretto parsing errato: ID {$pi['id']} → Matteo Signo");
                    } else {
                        echo "<p style='color: orange;'>⚠️ Parsing non standard: {$pi['nome']} {$pi['cognome']} - richiede verifica manuale</p>\n";
                        $issues_errors[] = "Parsing non standard: {$pi['nome']} {$pi['cognome']}";
                    }
                }
            }
            
            // Correzione 3: Aggiungi dipendenti mancanti
            foreach ($dipendenti_mancanti as $dip_mancante) {
                $stmt = $conn->prepare("
                    INSERT INTO dipendenti (nome, cognome, attivo, costo_giornaliero, ruolo) 
                    VALUES (?, ?, 1, 200.00, 'Tecnico')
                ");
                $stmt->execute([$dip_mancante['nome'], $dip_mancante['cognome']]);
                $new_id = $conn->lastInsertId();
                echo "<p style='color: green;'>✅ Aggiunto: {$dip_mancante['nome']} {$dip_mancante['cognome']} (ID: $new_id)</p>\n";
                $issues_fixed[] = "Aggiunto: {$dip_mancante['nome']} {$dip_mancante['cognome']}";
                $logger->info("Aggiunto dipendente mancante: {$dip_mancante['nome']} {$dip_mancante['cognome']} (ID: $new_id)");
            }
            
            // Correzione 4: Cleanup duplicati (soft delete)
            if (!empty($duplicati_attivita)) {
                $duplicati_rimossi = 0;
                foreach ($duplicati_attivita as $dup) {
                    // Mantieni solo il primo record, marca gli altri come duplicati
                    $stmt = $conn->prepare("
                        SELECT id FROM attivita 
                        WHERE dipendente_id = ? AND data_inizio = ? AND durata_ore = ?
                        ORDER BY id ASC
                    ");
                    $stmt->execute([$dup['dipendente_id'], $dup['data_inizio'], $dup['durata_ore']]);
                    $records = $stmt->fetchAll();
                    
                    // Salta il primo, marca gli altri come duplicati
                    for ($i = 1; $i < count($records); $i++) {
                        $stmt = $conn->prepare("UPDATE attivita SET is_duplicate = 1 WHERE id = ?");
                        $stmt->execute([$records[$i]['id']]);
                        $duplicati_rimossi++;
                    }
                }
                echo "<p style='color: green;'>✅ Marcati $duplicati_rimossi record attività come duplicati</p>\n";
                $issues_fixed[] = "Marcati $duplicati_rimossi attività duplicate";
            }
            
            $conn->commit();
            
            // Summary finale
            echo "<h3>📊 Summary Correzioni</h3>\n";
            echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>\n";
            echo "<h4 style='color: green;'>✅ Correzioni Completate</h4>\n";
            echo "<ul>\n";
            foreach ($issues_fixed as $fix) {
                echo "<li>$fix</li>\n";
            }
            echo "</ul>\n";
            
            if (!empty($issues_errors)) {
                echo "<h4 style='color: orange;'>⚠️ Problemi Rimanenti</h4>\n";
                echo "<ul>\n";
                foreach ($issues_errors as $error) {
                    echo "<li>$error</li>\n";
                }
                echo "</ul>\n";
            }
            
            echo "<p><strong>🎯 Fase 1 Completata!</strong> Database pulito e pronto per Fase 2.</p>\n";
            echo "</div>\n";
            
        } catch (Exception $e) {
            $conn->rollback();
            echo "<p style='color: red;'>❌ Errore durante correzioni: " . htmlspecialchars($e->getMessage()) . "</p>\n";
            throw $e;
        }
        
    } else {
        // Mostra bottone per eseguire correzioni
        if (!empty($issues_found)) {
            echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
            echo "<h4>🚀 Eseguire Correzioni</h4>\n";
            echo "<p>Sono stati identificati problemi che possono essere corretti automaticamente.</p>\n";
            echo "<p><a href='?execute=yes' onclick='return confirm(\"Sei sicuro di voler eseguire le correzioni? Questa operazione modificherà il database!\")' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>"; 
            echo "<strong>🔧 ESEGUI CORREZIONI</strong></a></p>\n";
            echo "<p><small><strong>IMPORTANTE:</strong> Assicurati di aver fatto un backup del database!</small></p>\n";
            echo "</div>\n";
        }
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante pulizia database</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h2, h3, h4 { color: #333; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>

<p>
    <a href="diagnose_data.php">📊 Diagnostica Completa</a> | 
    <a href="debug_dipendenti.php">👥 Debug Dipendenti</a> | 
    <a href="index.php">🏠 Dashboard</a>
</p>