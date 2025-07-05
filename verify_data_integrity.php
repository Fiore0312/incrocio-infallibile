<?php
require_once 'config/Database.php';
require_once 'classes/ImportLogger.php';

echo "<h2>🔍 Verifica Integrità Dati Sistema</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    $logger = new ImportLogger('data_verification');
    
    $logger->info("Avvio verifica integrità dati");
    
    $issues_found = [];
    $total_issues = 0;
    
    // 1. Verifica dipendenti con nomi non validi
    echo "<h3>1. Verifica Dipendenti Non Validi</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT 
            id, nome, cognome, email,
            CASE 
                WHEN nome IN ('Punto', 'Fiesta', 'Peugeot') THEN 'VEICOLO'
                WHEN nome IN ('Info', 'System', 'Admin', 'Test', 'Aurora') THEN 'SISTEMA'
                WHEN cognome = '' AND LENGTH(nome) < 3 THEN 'NOME_CORTO'
                WHEN nome LIKE '%@%' OR cognome LIKE '%@%' THEN 'EMAIL'
                WHEN LENGTH(nome) > 50 OR LENGTH(cognome) > 50 THEN 'TROPPO_LUNGO'
                WHEN nome REGEXP '^[0-9]+$' OR cognome REGEXP '^[0-9]+$' THEN 'SOLO_NUMERI'
                ELSE 'VALIDO'
            END as issue_type
        FROM dipendenti 
        HAVING issue_type != 'VALIDO'
        ORDER BY issue_type, nome
    ");
    $stmt->execute();
    $invalid_employees = $stmt->fetchAll();
    
    if (!empty($invalid_employees)) {
        $issues_found['dipendenti_non_validi'] = count($invalid_employees);
        $total_issues += count($invalid_employees);
        
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h4>❌ Trovati " . count($invalid_employees) . " dipendenti con nomi non validi</h4>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>ID</th><th>Nome</th><th>Cognome</th><th>Problema</th></tr>\n";
        foreach ($invalid_employees as $emp) {
            echo "<tr><td>{$emp['id']}</td><td>{$emp['nome']}</td><td>{$emp['cognome']}</td><td>{$emp['issue_type']}</td></tr>\n";
        }
        echo "</table>\n";
        echo "</div>\n";
        
        $logger->error("Dipendenti non validi trovati", ['count' => count($invalid_employees)]);
    } else {
        echo "<p style='color: green;'>✅ Tutti i dipendenti hanno nomi validi</p>\n";
        $logger->info("Verifica dipendenti: OK");
    }
    
    // 2. Verifica attività duplicate
    echo "<h3>2. Verifica Attività Duplicate</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT 
            dipendente_id,
            data_inizio,
            durata_ore,
            COUNT(*) as duplicates
        FROM attivita 
        GROUP BY dipendente_id, data_inizio, durata_ore, LEFT(COALESCE(descrizione, ''), 50)
        HAVING COUNT(*) > 1
        ORDER BY duplicates DESC
        LIMIT 20
    ");
    $stmt->execute();
    $duplicate_activities = $stmt->fetchAll();
    
    if (!empty($duplicate_activities)) {
        $total_duplicates = array_sum(array_column($duplicate_activities, 'duplicates'));
        $issues_found['attivita_duplicate'] = count($duplicate_activities);
        $total_issues += count($duplicate_activities);
        
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h4>⚠️ Trovati " . count($duplicate_activities) . " gruppi di attività duplicate (total: $total_duplicates)</h4>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Dipendente ID</th><th>Data Inizio</th><th>Durata</th><th>Duplicati</th></tr>\n";
        foreach ($duplicate_activities as $dup) {
            echo "<tr><td>{$dup['dipendente_id']}</td><td>{$dup['data_inizio']}</td><td>{$dup['durata_ore']}</td><td>{$dup['duplicates']}</td></tr>\n";
        }
        echo "</table>\n";
        echo "</div>\n";
        
        $logger->warning("Attività duplicate trovate", ['groups' => count($duplicate_activities), 'total' => $total_duplicates]);
    } else {
        echo "<p style='color: green;'>✅ Nessuna attività duplicata trovata</p>\n";
        $logger->info("Verifica attività duplicate: OK");
    }
    
    // 3. Verifica coerenza dati temporali
    echo "<h3>3. Verifica Coerenza Dati Temporali</h3>\n";
    
    // Attività con date inconsistenti
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM attivita 
        WHERE data_fine < data_inizio OR durata_ore <= 0
    ");
    $stmt->execute();
    $temporal_issues = $stmt->fetch()['count'];
    
    if ($temporal_issues > 0) {
        $issues_found['dati_temporali'] = $temporal_issues;
        $total_issues += $temporal_issues;
        
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h4>❌ Trovate $temporal_issues attività con date/durata inconsistenti</h4>\n";
        echo "</div>\n";
        
        $logger->error("Dati temporali inconsistenti", ['count' => $temporal_issues]);
    } else {
        echo "<p style='color: green;'>✅ Tutti i dati temporali sono coerenti</p>\n";
        $logger->info("Verifica dati temporali: OK");
    }
    
    // 4. Verifica foreign key orfane
    echo "<h3>4. Verifica Riferimenti Orfani</h3>\n";
    
    $orphan_checks = [
        'attivita_dipendenti' => "
            SELECT COUNT(*) as count 
            FROM attivita a 
            LEFT JOIN dipendenti d ON a.dipendente_id = d.id 
            WHERE d.id IS NULL
        ",
        'timbrature_dipendenti' => "
            SELECT COUNT(*) as count 
            FROM timbrature t 
            LEFT JOIN dipendenti d ON t.dipendente_id = d.id 
            WHERE d.id IS NULL
        ",
        'attivita_clienti' => "
            SELECT COUNT(*) as count 
            FROM attivita a 
            LEFT JOIN clienti c ON a.cliente_id = c.id 
            WHERE a.cliente_id IS NOT NULL AND c.id IS NULL
        "
    ];
    
    $orphan_found = false;
    foreach ($orphan_checks as $check_name => $query) {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            $orphan_found = true;
            $issues_found[$check_name] = $count;
            $total_issues += $count;
            echo "<p style='color: red;'>❌ $check_name: $count record orfani</p>\n";
            $logger->warning("Riferimenti orfani trovati", ['check' => $check_name, 'count' => $count]);
        } else {
            echo "<p style='color: green;'>✅ $check_name: OK</p>\n";
        }
    }
    
    if (!$orphan_found) {
        $logger->info("Verifica riferimenti orfani: OK");
    }
    
    // 5. Verifica indici e constraint
    echo "<h3>5. Verifica Struttura Database</h3>\n";
    
    // Controlla se esistono gli indici unici che dovrebbero prevenire duplicati
    $constraint_checks = [
        'attivita_unique' => "SHOW INDEX FROM attivita WHERE Key_name = 'idx_unique_activity'",
        'timbrature_unique' => "SHOW INDEX FROM timbrature WHERE Key_name = 'idx_unique_timesheet'",
        'calendario_unique' => "SHOW INDEX FROM calendario WHERE Key_name = 'idx_unique_calendar'"
    ];
    
    $missing_constraints = [];
    foreach ($constraint_checks as $constraint_name => $query) {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if (!$result) {
            $missing_constraints[] = $constraint_name;
            echo "<p style='color: orange;'>⚠️ Indice unico mancante: $constraint_name</p>\n";
        } else {
            echo "<p style='color: green;'>✅ Indice unico presente: $constraint_name</p>\n";
        }
    }
    
    if (!empty($missing_constraints)) {
        $issues_found['indici_mancanti'] = count($missing_constraints);
        $logger->warning("Indici unici mancanti", ['missing' => $missing_constraints]);
    } else {
        $logger->info("Verifica indici unici: OK");
    }
    
    // 6. Verifica logs sistema
    echo "<h3>6. Verifica Logs Sistema</h3>\n";
    
    $log_stats = ImportLogger::getLogStats('csvparser');
    if ($log_stats) {
        echo "<div style='background: #e7f3ff; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h4>📊 Statistiche Log Oggi:</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>Dimensione file:</strong> " . round($log_stats['file_size'] / 1024, 2) . " KB</li>\n";
        echo "<li><strong>Righe totali:</strong> {$log_stats['total_lines']}</li>\n";
        echo "<li><strong>Errori:</strong> {$log_stats['error_count']}</li>\n";
        echo "<li><strong>Warning:</strong> {$log_stats['warning_count']}</li>\n";
        echo "<li><strong>Info:</strong> {$log_stats['info_count']}</li>\n";
        echo "<li><strong>Sessioni:</strong> {$log_stats['sessions']}</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        
        if ($log_stats['error_count'] > 0) {
            echo "<p style='color: red;'>❌ Ci sono {$log_stats['error_count']} errori nei log di oggi</p>\n";
            $issues_found['errori_log'] = $log_stats['error_count'];
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Nessun log trovato per oggi</p>\n";
    }
    
    // 7. Summary finale
    echo "<h3>7. Summary Verifica Integrità</h3>\n";
    
    if ($total_issues == 0) {
        echo "<div style='background: #d4edda; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h4>🎉 Database Integro!</h4>\n";
        echo "<p>La verifica non ha trovato problemi di integrità significativi.</p>\n";
        echo "</div>\n";
        $logger->info("Verifica integrità completata: TUTTO OK");
    } else {
        echo "<div style='background: #f8d7da; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h4>⚠️ Problemi di Integrità Trovati: $total_issues</h4>\n";
        echo "<ul>\n";
        foreach ($issues_found as $issue_type => $count) {
            echo "<li><strong>$issue_type:</strong> $count</li>\n";
        }
        echo "</ul>\n";
        echo "<h5>Azioni Raccomandate:</h5>\n";
        echo "<ol>\n";
        if (isset($issues_found['dipendenti_non_validi'])) {
            echo "<li>Utilizzare lo script di pulizia per rimuovere dipendenti non validi</li>\n";
        }
        if (isset($issues_found['attivita_duplicate'])) {
            echo "<li>Eseguire la pulizia attività duplicate</li>\n";
        }
        if (isset($issues_found['indici_mancanti'])) {
            echo "<li>Applicare gli script di constraint del database</li>\n";
        }
        echo "<li>Consultare i log per dettagli sugli errori</li>\n";
        echo "</ol>\n";
        echo "</div>\n";
        
        $logger->warning("Verifica integrità completata con problemi", ['total_issues' => $total_issues, 'details' => $issues_found]);
    }
    
    // 8. Link azioni
    echo "<h3>8. Azioni Disponibili</h3>\n";
    echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px;'>\n";
    echo "<p><strong>Strumenti di Risoluzione:</strong></p>\n";
    echo "<p>\n";
    echo "<a href='cleanup_database.php' style='background: #dc3545; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🧹 Pulizia Database</a>\n";
    echo "<a href='debug_dipendenti.php' style='background: #fd7e14; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🔍 Debug Dipendenti</a>\n";
    echo "<a href='debug_duplicati.php' style='background: #ffc107; color: black; padding: 8px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>📋 Debug Duplicati</a>\n";
    echo "</p>\n";
    echo "<p><strong>Script SQL:</strong></p>\n";
    echo "<p>Per applicare i constraint: <code>mysql database < fix_database_constraints.sql</code></p>\n";
    echo "</div>\n";
    
    $logger->close();
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante la verifica</h4>\n";
    echo "<p><strong>Errore:</strong> " . $e->getMessage() . "</p>\n";
    echo "</div>\n";
    
    if (isset($logger)) {
        $logger->error("Errore durante verifica integrità", ['error' => $e->getMessage()]);
        $logger->close();
    }
}
?>

<p>
    <a href="diagnose_data_master.php">← Diagnostica Base</a> | 
    <a href="index.php">Dashboard</a> |
    <a href="logs/">📊 View Logs</a>
</p>