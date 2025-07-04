<?php
require_once 'config/Database.php';
require_once 'classes/KpiCalculator.php';

echo "<h2>Diagnostica Dati Sistema</h2>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // 1. Verifica dipendenti
    echo "<h3>1. Analisi Dipendenti</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total, COUNT(CASE WHEN attivo = 1 THEN 1 END) as attivi FROM dipendenti");
    $stmt->execute();
    $dipendenti = $stmt->fetch();
    echo "<p>Dipendenti totali: <strong>{$dipendenti['total']}</strong> | Attivi: <strong>{$dipendenti['attivi']}</strong></p>";
    
    $stmt = $conn->prepare("SELECT nome, cognome, email, costo_giornaliero, attivo FROM dipendenti ORDER BY cognome LIMIT 10");
    $stmt->execute();
    $sample_dipendenti = $stmt->fetchAll();
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Nome</th><th>Cognome</th><th>Email</th><th>Costo Giorn.</th><th>Attivo</th></tr>";
    foreach ($sample_dipendenti as $dip) {
        echo "<tr><td>{$dip['nome']}</td><td>{$dip['cognome']}</td><td>{$dip['email']}</td><td>€{$dip['costo_giornaliero']}</td><td>" . ($dip['attivo'] ? 'Sì' : 'No') . "</td></tr>";
    }
    echo "</table>";
    
    // 2. Verifica timbrature
    echo "<h3>2. Analisi Timbrature</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total, MIN(data) as min_data, MAX(data) as max_data FROM timbrature");
    $stmt->execute();
    $timbrature = $stmt->fetch();
    echo "<p>Timbrature totali: <strong>{$timbrature['total']}</strong></p>";
    echo "<p>Periodo: da <strong>{$timbrature['min_data']}</strong> a <strong>{$timbrature['max_data']}</strong></p>";
    
    if ($timbrature['total'] > 0) {
        $stmt = $conn->prepare("
            SELECT d.nome, d.cognome, t.data, t.ora_inizio, t.ora_fine, t.ore_totali, c.nome as cliente_nome
            FROM timbrature t 
            JOIN dipendenti d ON t.dipendente_id = d.id 
            LEFT JOIN clienti c ON t.cliente_id = c.id 
            ORDER BY t.data DESC, d.cognome 
            LIMIT 10
        ");
        $stmt->execute();
        $sample_timbrature = $stmt->fetchAll();
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Dipendente</th><th>Data</th><th>Inizio</th><th>Fine</th><th>Ore Tot.</th><th>Cliente</th></tr>";
        foreach ($sample_timbrature as $t) {
            echo "<tr><td>{$t['nome']} {$t['cognome']}</td><td>{$t['data']}</td><td>{$t['ora_inizio']}</td><td>{$t['ora_fine']}</td><td>{$t['ore_totali']}</td><td>{$t['cliente_nome']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'><strong>PROBLEMA:</strong> Nessuna timbratura trovata!</p>";
    }
    
    // 3. Verifica attività
    echo "<h3>3. Analisi Attività</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total, COUNT(CASE WHEN fatturabile = 1 THEN 1 END) as fatturabili, SUM(durata_ore) as ore_totali FROM attivita");
    $stmt->execute();
    $attivita = $stmt->fetch();
    echo "<p>Attività totali: <strong>{$attivita['total']}</strong> | Fatturabili: <strong>{$attivita['fatturabili']}</strong> | Ore totali: <strong>" . number_format($attivita['ore_totali'], 2) . "</strong></p>";
    
    if ($attivita['total'] > 0) {
        $stmt = $conn->prepare("
            SELECT d.nome, d.cognome, a.descrizione, a.data_inizio, a.durata_ore, a.fatturabile, p.nome as progetto_nome
            FROM attivita a 
            JOIN dipendenti d ON a.dipendente_id = d.id 
            LEFT JOIN progetti p ON a.progetto_id = p.id 
            ORDER BY a.data_inizio DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $sample_attivita = $stmt->fetchAll();
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Dipendente</th><th>Descrizione</th><th>Data Inizio</th><th>Durata (h)</th><th>Fatturabile</th><th>Progetto</th></tr>";
        foreach ($sample_attivita as $a) {
            $desc = $a['descrizione'] ? substr($a['descrizione'], 0, 40) . "..." : 'N/A';
            echo "<tr><td>{$a['nome']} {$a['cognome']}</td><td>$desc</td><td>{$a['data_inizio']}</td><td>{$a['durata_ore']}</td><td>" . ($a['fatturabile'] ? 'Sì' : 'No') . "</td><td>{$a['progetto_nome']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'><strong>PROBLEMA:</strong> Nessuna attività trovata!</p>";
    }
    
    // 4. Verifica sessioni TeamViewer
    echo "<h3>4. Analisi Sessioni TeamViewer</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total, MIN(data_inizio) as min_data, MAX(data_inizio) as max_data FROM teamviewer_sessioni");
    $stmt->execute();
    $teamviewer = $stmt->fetch();
    echo "<p>Sessioni TeamViewer: <strong>{$teamviewer['total']}</strong></p>";
    if ($teamviewer['min_data']) {
        echo "<p>Periodo: da <strong>{$teamviewer['min_data']}</strong> a <strong>{$teamviewer['max_data']}</strong></p>";
    }
    
    if ($teamviewer['total'] > 0) {
        $stmt = $conn->prepare("
            SELECT d.nome, d.cognome, ts.nome_cliente as computer_name, ts.data_inizio, ts.durata_minuti, c.nome as cliente_nome
            FROM teamviewer_sessioni ts 
            JOIN dipendenti d ON ts.dipendente_id = d.id 
            LEFT JOIN clienti c ON ts.cliente_id = c.id 
            ORDER BY ts.data_inizio DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $sample_teamviewer = $stmt->fetchAll();
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Dipendente</th><th>Computer</th><th>Data Inizio</th><th>Durata (min)</th><th>Cliente</th></tr>";
        foreach ($sample_teamviewer as $tv) {
            echo "<tr><td>{$tv['nome']} {$tv['cognome']}</td><td>{$tv['computer_name']}</td><td>{$tv['data_inizio']}</td><td>{$tv['durata_minuti']}</td><td>{$tv['cliente_nome']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'><strong>PROBLEMA:</strong> Nessuna sessione TeamViewer trovata!</p>";
    }
    
    // 5. Verifica KPI calcolati
    echo "<h3>5. Analisi KPI Giornalieri</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total, MIN(data) as min_data, MAX(data) as max_data FROM kpi_giornalieri");
    $stmt->execute();
    $kpi = $stmt->fetch();
    echo "<p>KPI giornalieri calcolati: <strong>{$kpi['total']}</strong></p>";
    if ($kpi['min_data']) {
        echo "<p>Periodo: da <strong>{$kpi['min_data']}</strong> a <strong>{$kpi['max_data']}</strong></p>";
    }
    
    if ($kpi['total'] > 0) {
        $stmt = $conn->prepare("
            SELECT d.nome, d.cognome, k.data, k.ore_fatturabili, k.efficiency_rate, k.profit_loss, k.remote_sessions
            FROM kpi_giornalieri k 
            JOIN dipendenti d ON k.dipendente_id = d.id 
            ORDER BY k.data DESC, k.efficiency_rate DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $sample_kpi = $stmt->fetchAll();
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Dipendente</th><th>Data</th><th>Ore Fatt.</th><th>Efficiency %</th><th>Profit/Loss</th><th>Sessioni Remote</th></tr>";
        foreach ($sample_kpi as $k) {
            $profit_color = $k['profit_loss'] >= 0 ? 'green' : 'red';
            echo "<tr><td>{$k['nome']} {$k['cognome']}</td><td>{$k['data']}</td><td>{$k['ore_fatturabili']}</td><td>" . number_format($k['efficiency_rate'], 1) . "%</td><td style='color: $profit_color'>€" . number_format($k['profit_loss'], 2) . "</td><td>{$k['remote_sessions']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'><strong>ATTENZIONE:</strong> Nessun KPI calcolato! I KPI devono essere generati.</p>";
        echo "<p><a href='#' onclick='calculateKpis()' style='background: #007bff; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px;'>Calcola KPI Ora</a></p>";
    }
    
    // 6. Verifica configurazioni
    echo "<h3>6. Analisi Configurazioni</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM configurazioni");
    $stmt->execute();
    $config_count = $stmt->fetch();
    echo "<p>Configurazioni totali: <strong>{$config_count['total']}</strong></p>";
    
    if ($config_count['total'] > 0) {
        $stmt = $conn->prepare("SELECT chiave, valore, tipo, categoria FROM configurazioni ORDER BY categoria, chiave");
        $stmt->execute();
        $configs = $stmt->fetchAll();
        
        $grouped_configs = [];
        foreach ($configs as $conf) {
            $grouped_configs[$conf['categoria']][] = $conf;
        }
        
        foreach ($grouped_configs as $category => $conf_list) {
            echo "<h4>Categoria: " . ucfirst($category) . "</h4>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>Chiave</th><th>Valore</th><th>Tipo</th></tr>";
            foreach ($conf_list as $conf) {
                echo "<tr><td>{$conf['chiave']}</td><td>{$conf['valore']}</td><td>{$conf['tipo']}</td></tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color: red;'><strong>PROBLEMA:</strong> Nessuna configurazione trovata! Usa 'Inizializza Default' nelle impostazioni.</p>";
    }
    
    // 7. Test calcolo KPI in tempo reale
    echo "<h3>7. Test Calcolo KPI</h3>";
    try {
        $kpiCalculator = new KpiCalculator();
        $oggi = date('Y-m-d');
        $settimana_fa = date('Y-m-d', strtotime('-7 days'));
        
        echo "<p>Test periodo: da <strong>$settimana_fa</strong> a <strong>$oggi</strong></p>";
        
        $summary = $kpiCalculator->getKpiSummary(null, $settimana_fa, $oggi);
        if ($summary) {
            echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>Summary KPI Ultimi 7 Giorni:</h4>";
            echo "<p><strong>Efficiency Rate Media:</strong> " . number_format($summary['avg_efficiency_rate'], 2) . "%</p>";
            echo "<p><strong>Profit/Loss Totale:</strong> €" . number_format($summary['total_profit_loss'], 2) . "</p>";
            echo "<p><strong>Ore Fatturabili Totali:</strong> " . number_format($summary['totale_ore_fatturabili'], 2) . "</p>";
            echo "<p><strong>Giorni Lavorativi:</strong> {$summary['giorni_lavorativi']}</p>";
            echo "<p><strong>Sessioni Remote:</strong> {$summary['totale_sessioni_remote']}</p>";
            echo "</div>";
        } else {
            echo "<p style='color: red;'><strong>PROBLEMA:</strong> Impossibile calcolare summary KPI!</p>";
        }
        
        $alerts = $kpiCalculator->getAlertsCount($settimana_fa, $oggi);
        if ($alerts) {
            echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>Alert Attivi:</h4>";
            echo "<p><strong>Efficiency Warnings:</strong> {$alerts['efficiency_warnings']}</p>";
            echo "<p><strong>Efficiency Critical:</strong> {$alerts['efficiency_critical']}</p>";
            echo "<p><strong>Profit Warnings:</strong> {$alerts['profit_warnings']}</p>";
            echo "<p><strong>Ore Insufficienti:</strong> {$alerts['ore_insufficienti']}</p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>ERRORE nel calcolo KPI:</strong> " . $e->getMessage() . "</p>";
    }
    
    // 8. Raccomandazioni
    echo "<h3>8. Raccomandazioni</h3>";
    echo "<div style='background: #e7f3ff; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px;'>";
    
    $issues = [];
    if ($timbrature['total'] == 0) $issues[] = "Importare dati timbrature";
    if ($attivita['total'] == 0) $issues[] = "Importare dati attività";
    if ($teamviewer['total'] == 0) $issues[] = "Importare dati TeamViewer";
    if ($kpi['total'] == 0) $issues[] = "Calcolare KPI giornalieri";
    if ($config_count['total'] == 0) $issues[] = "Inizializzare configurazioni default";
    
    if (empty($issues)) {
        echo "<h4 style='color: green;'>✅ Sistema Completo</h4>";
        echo "<p>Tutti i dati sono presenti e i calcoli funzionano correttamente.</p>";
    } else {
        echo "<h4 style='color: orange;'>⚠️ Azioni Necessarie:</h4>";
        echo "<ul>";
        foreach ($issues as $issue) {
            echo "<li>$issue</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Errore nella diagnostica:</strong> " . $e->getMessage() . "</p>";
}
?>

<script>
function calculateKpis() {
    if (confirm('Calcolare tutti i KPI? Questo potrebbe richiedere alcuni minuti.')) {
        window.location.href = 'calculate_kpis.php';
    }
}
</script>

<p><a href="index.php">← Torna al Dashboard</a> | <a href="upload.php">Carica Dati</a> | <a href="settings.php">Configurazioni</a></p>