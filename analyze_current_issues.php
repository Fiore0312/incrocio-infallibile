<?php
require_once 'config/Database.php';

/**
 * Analisi Dettagliata Problemi Database - Report Specifico
 * Verifica tutti i problemi segnalati dall'utente
 */

echo "<h2>🔍 Analisi Problemi Database - Report Dettagliato</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $report = [
        'andrea_bianchi' => [],
        'parsing_errors' => [],
        'missing_employees' => [],
        'present_employees' => [],
        'duplicates_attivita' => 0,
        'duplicates_timbrature' => 0,
        'database_structure' => []
    ];
    
    // 1. PROBLEMA: Andrea Bianchi da eliminare
    echo "<h3>1. 🔍 Ricerca 'Andrea Bianchi' da eliminare</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT id, nome, cognome, email, attivo, created_at,
               (SELECT COUNT(*) FROM attivita WHERE dipendente_id = d.id) as attivita_count,
               (SELECT COUNT(*) FROM timbrature WHERE dipendente_id = d.id) as timbrature_count
        FROM dipendenti d 
        WHERE nome = 'Andrea' AND cognome = 'Bianchi'
    ");
    $stmt->execute();
    $andrea_results = $stmt->fetchAll();
    
    if (!empty($andrea_results)) {
        foreach ($andrea_results as $ab) {
            $report['andrea_bianchi'][] = $ab;
            $safe_to_delete = ($ab['attivita_count'] == 0 && $ab['timbrature_count'] == 0);
            echo "<div style='background: #ffe6e6; padding: 10px; border-left: 4px solid red; margin: 5px 0;'>\n";
            echo "<p><strong>❌ TROVATO: Andrea Bianchi</strong></p>\n";
            echo "<ul>\n";
            echo "<li><strong>ID:</strong> {$ab['id']}</li>\n";
            echo "<li><strong>Email:</strong> {$ab['email']}</li>\n";
            echo "<li><strong>Attivo:</strong> " . ($ab['attivo'] ? 'Sì' : 'No') . "</li>\n";
            echo "<li><strong>Creato:</strong> {$ab['created_at']}</li>\n";
            echo "<li><strong>Attività collegate:</strong> {$ab['attivita_count']}</li>\n";
            echo "<li><strong>Timbrature collegate:</strong> {$ab['timbrature_count']}</li>\n";
            echo "<li><strong>Sicuro da eliminare:</strong> " . ($safe_to_delete ? '✅ SÌ' : '❌ NO') . "</li>\n";
            echo "</ul>\n";
            echo "</div>\n";
        }
    } else {
        echo "<p style='color: green;'>✅ Andrea Bianchi NON trovato nel database</p>\n";
    }
    
    // 2. PROBLEMA: Franco Fiorellino/Matteo Signo parsing errato
    echo "<h3>2. 🔍 Ricerca errori parsing 'Franco Fiorellino/Matteo Signo'</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT id, nome, cognome, email, attivo, created_at
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
            $report['parsing_errors'][] = $pe;
            echo "<div style='background: #ffe6e6; padding: 10px; border-left: 4px solid orange; margin: 5px 0;'>\n";
            echo "<p><strong>❌ ERRORE PARSING TROVATO</strong></p>\n";
            echo "<ul>\n";
            echo "<li><strong>ID:</strong> {$pe['id']}</li>\n";
            echo "<li><strong>Nome:</strong> '{$pe['nome']}'</li>\n";
            echo "<li><strong>Cognome:</strong> '{$pe['cognome']}'</li>\n";
            echo "<li><strong>Email:</strong> {$pe['email']}</li>\n";
            echo "<li><strong>Attivo:</strong> " . ($pe['attivo'] ? 'Sì' : 'No') . "</li>\n";
            echo "<li><strong>Creato:</strong> {$pe['created_at']}</li>\n";
            echo "</ul>\n";
            echo "<p><strong>🔧 CORREZIONE NECESSARIA:</strong> Cambiare in nome='Matteo', cognome='Signo'</p>\n";
            echo "</div>\n";
        }
    } else {
        echo "<p style='color: green;'>✅ Nessun errore di parsing con '/' trovato</p>\n";
    }
    
    // 3. VERIFICA: Lista dipendenti richiesta dall'utente
    echo "<h3>3. 🔍 Verifica Lista Dipendenti Richiesta</h3>\n";
    
    $dipendenti_richiesti = [
        ['nome' => 'Niccolò', 'cognome' => 'Ragusa'],
        ['nome' => 'Davide', 'cognome' => 'Cestone'],
        ['nome' => 'Arlind', 'cognome' => 'Hoxha'],
        ['nome' => 'Lorenzo', 'cognome' => 'Serratore'],
        ['nome' => 'Gabriele', 'cognome' => 'De Palma'],
        ['nome' => 'Franco', 'cognome' => 'Fiorellino'], // Questo deve essere presente E corretto
        ['nome' => 'Matteo', 'cognome' => 'Signo'],     // Questo potrebbe essere nel record errato
        ['nome' => 'Marco', 'cognome' => 'Birocchi'],
        ['nome' => 'Roberto', 'cognome' => 'Birocchi'],
        ['nome' => 'Alex', 'cognome' => 'Ferrario'],
        ['nome' => 'Gianluca', 'cognome' => 'Ghirindelli'],
        ['nome' => 'Matteo', 'cognome' => 'Di Salvo'],
        ['nome' => 'Cristian', 'cognome' => 'La Bella'],
        ['nome' => 'Giuseppe', 'cognome' => 'Anastasio']
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>\n";
    echo "<tr><th>Nome Cognome</th><th>Stato</th><th>ID</th><th>Email</th><th>Attivo</th><th>Azione</th></tr>\n";
    
    foreach ($dipendenti_richiesti as $dip_req) {
        $stmt = $conn->prepare("
            SELECT id, nome, cognome, email, attivo 
            FROM dipendenti 
            WHERE nome = ? AND cognome = ?
        ");
        $stmt->execute([$dip_req['nome'], $dip_req['cognome']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $report['present_employees'][] = array_merge($dip_req, $existing);
            $status_color = $existing['attivo'] ? 'green' : 'orange';
            $status_text = $existing['attivo'] ? '✅ Presente e Attivo' : '⚠️ Presente ma Inattivo';
            echo "<tr style='background-color: #e6ffe6;'>\n";
            echo "<td><strong>{$dip_req['nome']} {$dip_req['cognome']}</strong></td>\n";
            echo "<td style='color: $status_color;'>$status_text</td>\n";
            echo "<td>{$existing['id']}</td>\n";
            echo "<td>{$existing['email']}</td>\n";
            echo "<td>" . ($existing['attivo'] ? 'Sì' : 'No') . "</td>\n";
            echo "<td>Nessuna</td>\n";
            echo "</tr>\n";
        } else {
            $report['missing_employees'][] = $dip_req;
            echo "<tr style='background-color: #ffe6e6;'>\n";
            echo "<td><strong>{$dip_req['nome']} {$dip_req['cognome']}</strong></td>\n";
            echo "<td style='color: red;'>❌ MANCANTE</td>\n";
            echo "<td>-</td>\n";
            echo "<td>-</td>\n";
            echo "<td>-</td>\n";
            echo "<td><strong>DA AGGIUNGERE</strong></td>\n";
            echo "</tr>\n";
        }
    }
    echo "</table>\n";
    
    // 4. ANALISI: Duplicati nelle timbrature
    echo "<h3>4. 🔍 Analisi Duplicati Timbrature</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT 
            dipendente_id,
            data,
            ora_inizio,
            ora_fine,
            COUNT(*) as duplicates,
            GROUP_CONCAT(id) as record_ids
        FROM timbrature 
        GROUP BY dipendente_id, data, ora_inizio, ora_fine
        HAVING COUNT(*) > 1
        ORDER BY duplicates DESC
        LIMIT 10
    ");
    $stmt->execute();
    $duplicati_timbrature = $stmt->fetchAll();
    
    $report['duplicates_timbrature'] = count($duplicati_timbrature);
    
    if (!empty($duplicati_timbrature)) {
        echo "<div style='background: #fff3cd; padding: 10px; border-left: 4px solid orange; margin: 5px 0;'>\n";
        echo "<p><strong>⚠️ DUPLICATI TIMBRATURE TROVATI: " . count($duplicati_timbrature) . " gruppi</strong></p>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Dipendente ID</th><th>Data</th><th>Ora Inizio</th><th>Ora Fine</th><th>Duplicati</th><th>Record IDs</th></tr>\n";
        foreach ($duplicati_timbrature as $dt) {
            echo "<tr>\n";
            echo "<td>{$dt['dipendente_id']}</td>\n";
            echo "<td>{$dt['data']}</td>\n";
            echo "<td>{$dt['ora_inizio']}</td>\n";
            echo "<td>{$dt['ora_fine']}</td>\n";
            echo "<td><strong>{$dt['duplicates']}</strong></td>\n";
            echo "<td>{$dt['record_ids']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        echo "</div>\n";
    } else {
        echo "<p style='color: green;'>✅ Nessun duplicato timbrature trovato</p>\n";
    }
    
    // 5. ANALISI: Duplicati nelle attività
    echo "<h3>5. 🔍 Analisi Duplicati Attività</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT 
            dipendente_id,
            data_inizio,
            durata_ore,
            SUBSTRING(descrizione, 1, 50) as desc_short,
            COUNT(*) as duplicates,
            GROUP_CONCAT(id) as record_ids
        FROM attivita 
        GROUP BY dipendente_id, data_inizio, durata_ore, SUBSTRING(descrizione, 1, 50)
        HAVING COUNT(*) > 1
        ORDER BY duplicates DESC
        LIMIT 10
    ");
    $stmt->execute();
    $duplicati_attivita = $stmt->fetchAll();
    
    $report['duplicates_attivita'] = count($duplicati_attivita);
    
    if (!empty($duplicati_attivita)) {
        echo "<div style='background: #fff3cd; padding: 10px; border-left: 4px solid orange; margin: 5px 0;'>\n";
        echo "<p><strong>⚠️ DUPLICATI ATTIVITÀ TROVATI: " . count($duplicati_attivita) . " gruppi</strong></p>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Dipendente ID</th><th>Data Inizio</th><th>Durata</th><th>Descrizione</th><th>Duplicati</th><th>Record IDs</th></tr>\n";
        foreach ($duplicati_attivita as $da) {
            echo "<tr>\n";
            echo "<td>{$da['dipendente_id']}</td>\n";
            echo "<td>{$da['data_inizio']}</td>\n";
            echo "<td>{$da['durata_ore']}</td>\n";
            echo "<td>" . htmlspecialchars($da['desc_short']) . "...</td>\n";
            echo "<td><strong>{$da['duplicates']}</strong></td>\n";
            echo "<td>" . substr($da['record_ids'], 0, 50) . "...</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        echo "</div>\n";
    } else {
        echo "<p style='color: green;'>✅ Nessun duplicato attività trovato</p>\n";
    }
    
    // 6. ANALISI: Struttura tabella dipendenti e KPI
    echo "<h3>6. 🔍 Analisi Struttura Database - Perché KPI mostrano una sola colonna</h3>\n";
    
    // Descrivi struttura tabella dipendenti
    $stmt = $conn->prepare("DESCRIBE dipendenti");
    $stmt->execute();
    $dipendenti_structure = $stmt->fetchAll();
    
    echo "<h4>Struttura Tabella 'dipendenti':</h4>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Chiave</th><th>Default</th><th>Extra</th></tr>\n";
    foreach ($dipendenti_structure as $col) {
        echo "<tr>\n";
        echo "<td>{$col['Field']}</td>\n";
        echo "<td>{$col['Type']}</td>\n";
        echo "<td>{$col['Null']}</td>\n";
        echo "<td>{$col['Key']}</td>\n";
        echo "<td>{$col['Default']}</td>\n";
        echo "<td>{$col['Extra']}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Verifica come vengono calcolati i KPI
    $stmt = $conn->prepare("
        SELECT 
            d.id,
            d.nome,
            d.cognome,
            CONCAT(d.nome, ' ', d.cognome) as nome_completo,
            COUNT(k.id) as kpi_records
        FROM dipendenti d
        LEFT JOIN kpi_giornalieri k ON d.id = k.dipendente_id
        GROUP BY d.id, d.nome, d.cognome
        ORDER BY d.cognome, d.nome
        LIMIT 10
    ");
    $stmt->execute();
    $kpi_analysis = $stmt->fetchAll();
    
    echo "<h4>Analisi Collegamenti KPI-Dipendenti:</h4>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>ID</th><th>Nome</th><th>Cognome</th><th>Nome Completo</th><th>Record KPI</th></tr>\n";
    foreach ($kpi_analysis as $ka) {
        echo "<tr>\n";
        echo "<td>{$ka['id']}</td>\n";
        echo "<td>{$ka['nome']}</td>\n";
        echo "<td>{$ka['cognome']}</td>\n";
        echo "<td><strong>{$ka['nome_completo']}</strong></td>\n";
        echo "<td>{$ka['kpi_records']}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<div style='background: #e7f3ff; padding: 10px; border-left: 4px solid blue; margin: 5px 0;'>\n";
    echo "<p><strong>💡 SPIEGAZIONE KPI COLONNA UNICA:</strong></p>\n";
    echo "<p>I KPI mostrano i nomi in una sola colonna 'Dipendente' perché viene calcolata tramite <code>CONCAT(nome, ' ', cognome)</code>.</p>\n";
    echo "<p>Il database ha campi separati <code>nome</code> e <code>cognome</code>, ma nelle visualizzazioni vengono combinati per leggibilità.</p>\n";
    echo "<p>Questa è una struttura corretta: dati normalizzati nel database, visualizzazione user-friendly nell'interfaccia.</p>\n";
    echo "</div>\n";
    
    // 7. SUMMARY COMPLETO
    echo "<h3>7. 📋 SUMMARY COMPLETO PROBLEMI</h3>\n";
    
    $total_issues = count($report['andrea_bianchi']) + count($report['parsing_errors']) + count($report['missing_employees']) + ($report['duplicates_attivita'] > 0 ? 1 : 0) + ($report['duplicates_timbrature'] > 0 ? 1 : 0);
    
    if ($total_issues > 0) {
        echo "<div style='background: #fff3cd; padding: 15px; border: 2px solid orange; border-radius: 10px;'>\n";
        echo "<h4 style='color: orange;'>⚠️ PROBLEMI IDENTIFICATI ($total_issues)</h4>\n";
        
        if (!empty($report['andrea_bianchi'])) {
            echo "<p><strong>1. Andrea Bianchi da eliminare:</strong> " . count($report['andrea_bianchi']) . " record</p>\n";
        }
        
        if (!empty($report['parsing_errors'])) {
            echo "<p><strong>2. Errori parsing nomi:</strong> " . count($report['parsing_errors']) . " record</p>\n";
        }
        
        if (!empty($report['missing_employees'])) {
            echo "<p><strong>3. Dipendenti mancanti:</strong> " . count($report['missing_employees']) . " dipendenti</p>\n";
            echo "<ul>\n";
            foreach ($report['missing_employees'] as $missing) {
                echo "<li>{$missing['nome']} {$missing['cognome']}</li>\n";
            }
            echo "</ul>\n";
        }
        
        if ($report['duplicates_timbrature'] > 0) {
            echo "<p><strong>4. Duplicati timbrature:</strong> {$report['duplicates_timbrature']} gruppi</p>\n";
        }
        
        if ($report['duplicates_attivita'] > 0) {
            echo "<p><strong>5. Duplicati attività:</strong> {$report['duplicates_attivita']} gruppi</p>\n";
        }
        
        echo "<h5 style='color: red;'>🚀 AZIONI NECESSARIE:</h5>\n";
        echo "<ol>\n";
        echo "<li>Eliminare Andrea Bianchi se sicuro</li>\n";
        echo "<li>Correggere errori parsing nome/cognome</li>\n";
        echo "<li>Aggiungere dipendenti mancanti</li>\n";
        echo "<li>Cleanup duplicati timbrature</li>\n";
        echo "<li>Cleanup duplicati attività</li>\n";
        echo "</ol>\n";
        echo "</div>\n";
        
    } else {
        echo "<div style='background: #d4edda; padding: 15px; border: 2px solid green; border-radius: 10px;'>\n";
        echo "<h4 style='color: green;'>✅ NESSUN PROBLEMA CRITICO TROVATO</h4>\n";
        echo "<p>Il database appare pulito e coerente. I KPI mostrano nomi in una sola colonna per motivi di visualizzazione, non per errori strutturali.</p>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante analisi</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
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
    <a href="fix_database_issues.php">🔧 Vai alle Correzioni</a> | 
    <a href="diagnose_data.php">📊 Diagnostica Completa</a> | 
    <a href="index.php">🏠 Dashboard</a>
</p>