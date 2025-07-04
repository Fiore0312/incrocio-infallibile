<?php
require_once 'config/Database.php';

echo "<h2>🔍 Debug Investigazione Dipendenti</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // 1. Lista completa dipendenti con analisi
    echo "<h3>1. Analisi Completa Dipendenti</h3>\n";
    $stmt = $conn->prepare("
        SELECT 
            id, 
            nome, 
            cognome, 
            email, 
            costo_giornaliero, 
            ruolo, 
            attivo,
            created_at
        FROM dipendenti 
        ORDER BY created_at DESC, nome
    ");
    $stmt->execute();
    $all_dipendenti = $stmt->fetchAll();
    
    echo "<p><strong>Totale dipendenti nel database:</strong> " . count($all_dipendenti) . "</p>\n";
    
    // Raggruppa per tipologia
    $valid_employees = [];
    $suspicious_employees = [];
    $vehicle_names = ['Punto', 'Fiesta', 'Peugeot'];
    $system_names = ['Info', 'System', 'Admin', 'Test'];
    
    foreach ($all_dipendenti as $dip) {
        if (in_array($dip['nome'], $vehicle_names) || in_array($dip['cognome'], $vehicle_names)) {
            $suspicious_employees[] = array_merge($dip, ['reason' => 'Nome veicolo']);
        } elseif (in_array($dip['nome'], $system_names) || in_array($dip['cognome'], $system_names)) {
            $suspicious_employees[] = array_merge($dip, ['reason' => 'Nome sistema']);
        } elseif (empty($dip['cognome']) && strlen($dip['nome']) < 3) {
            $suspicious_employees[] = array_merge($dip, ['reason' => 'Nome troppo corto']);
        } elseif (strpos($dip['nome'], '@') !== false || strpos($dip['cognome'], '@') !== false) {
            $suspicious_employees[] = array_merge($dip, ['reason' => 'Email come nome']);
        } else {
            $valid_employees[] = $dip;
        }
    }
    
    // Report dipendenti validi
    echo "<h4>✅ Dipendenti Validi (" . count($valid_employees) . ")</h4>\n";
    if (!empty($valid_employees)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>ID</th><th>Nome</th><th>Cognome</th><th>Email</th><th>Ruolo</th><th>Attivo</th><th>Creato</th></tr>\n";
        foreach ($valid_employees as $dip) {
            $attivo_flag = $dip['attivo'] ? '✅' : '❌';
            echo "<tr>\n";
            echo "<td>{$dip['id']}</td>\n";
            echo "<td>{$dip['nome']}</td>\n";
            echo "<td>{$dip['cognome']}</td>\n";
            echo "<td>{$dip['email']}</td>\n";
            echo "<td>{$dip['ruolo']}</td>\n";
            echo "<td>$attivo_flag</td>\n";
            echo "<td>{$dip['created_at']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // Report dipendenti sospetti
    echo "<h4>❌ Dipendenti Sospetti (" . count($suspicious_employees) . ")</h4>\n";
    if (!empty($suspicious_employees)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; background-color: #ffe6e6;'>\n";
        echo "<tr><th>ID</th><th>Nome</th><th>Cognome</th><th>Email</th><th>Motivo</th><th>Creato</th><th>Azioni</th></tr>\n";
        foreach ($suspicious_employees as $dip) {
            echo "<tr>\n";
            echo "<td>{$dip['id']}</td>\n";
            echo "<td><strong>{$dip['nome']}</strong></td>\n";
            echo "<td><strong>{$dip['cognome']}</strong></td>\n";
            echo "<td>{$dip['email']}</td>\n";
            echo "<td style='color: red;'>{$dip['reason']}</td>\n";
            echo "<td>{$dip['created_at']}</td>\n";
            echo "<td>
                <button onclick='checkDependencies({$dip['id']})' style='background: orange; color: white; border: none; padding: 5px;'>
                    Verifica Dipendenze
                </button>
            </td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // 2. Verifica veicoli nella tabella veicoli
    echo "<h3>2. Verifica Veicoli Registrati</h3>\n";
    $stmt = $conn->prepare("SELECT id, nome, modello, attivo FROM veicoli ORDER BY nome");
    $stmt->execute();
    $veicoli = $stmt->fetchAll();
    
    echo "<p><strong>Veicoli registrati:</strong> " . count($veicoli) . "</p>\n";
    if (!empty($veicoli)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>ID</th><th>Nome</th><th>Modello</th><th>Attivo</th></tr>\n";
        foreach ($veicoli as $v) {
            $attivo_flag = $v['attivo'] ? '✅' : '❌';
            echo "<tr><td>{$v['id']}</td><td>{$v['nome']}</td><td>{$v['modello']}</td><td>$attivo_flag</td></tr>\n";
        }
        echo "</table>\n";
    }
    
    // 3. Analisi dipendenze (attività, timbrature, ecc.)
    echo "<h3>3. Analisi Dipendenze per Dipendenti Sospetti</h3>\n";
    if (!empty($suspicious_employees)) {
        foreach ($suspicious_employees as $dip) {
            echo "<h4>Dipendente: {$dip['nome']} {$dip['cognome']} (ID: {$dip['id']})</h4>\n";
            
            // Controlla attività
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM attivita WHERE dipendente_id = :id");
            $stmt->execute([':id' => $dip['id']]);
            $attivita_count = $stmt->fetch()['count'];
            
            // Controlla timbrature
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM timbrature WHERE dipendente_id = :id");
            $stmt->execute([':id' => $dip['id']]);
            $timbrature_count = $stmt->fetch()['count'];
            
            // Controlla calendario
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM calendario WHERE dipendente_id = :id");
            $stmt->execute([':id' => $dip['id']]);
            $calendario_count = $stmt->fetch()['count'];
            
            // Controlla KPI
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM kpi_giornalieri WHERE dipendente_id = :id");
            $stmt->execute([':id' => $dip['id']]);
            $kpi_count = $stmt->fetch()['count'];
            
            echo "<div style='background: #f8f9fa; padding: 10px; margin: 5px 0; border-left: 4px solid red;'>\n";
            echo "<p><strong>Dipendenze:</strong></p>\n";
            echo "<ul>\n";
            echo "<li>Attività: <strong>$attivita_count</strong></li>\n";
            echo "<li>Timbrature: <strong>$timbrature_count</strong></li>\n";
            echo "<li>Calendario: <strong>$calendario_count</strong></li>\n";
            echo "<li>KPI: <strong>$kpi_count</strong></li>\n";
            echo "</ul>\n";
            
            $total_deps = $attivita_count + $timbrature_count + $calendario_count + $kpi_count;
            if ($total_deps == 0) {
                echo "<p style='color: green;'><strong>✅ SICURO DA RIMUOVERE</strong> - Nessuna dipendenza trovata</p>\n";
            } else {
                echo "<p style='color: orange;'><strong>⚠️ ATTENZIONE</strong> - Ha $total_deps record collegati</p>\n";
            }
            echo "</div>\n";
        }
    }
    
    // 4. Analisi duplicati attività
    echo "<h3>4. Analisi Duplicati Attività</h3>\n";
    $stmt = $conn->prepare("
        SELECT 
            dipendente_id,
            data_inizio,
            durata_ore,
            descrizione,
            COUNT(*) as duplicates
        FROM attivita 
        GROUP BY dipendente_id, data_inizio, durata_ore, SUBSTRING(descrizione, 1, 50)
        HAVING COUNT(*) > 1
        ORDER BY duplicates DESC
        LIMIT 20
    ");
    $stmt->execute();
    $duplicates = $stmt->fetchAll();
    
    if (!empty($duplicates)) {
        echo "<p style='color: red;'><strong>❌ Trovati " . count($duplicates) . " gruppi di attività duplicate!</strong></p>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; background-color: #ffe6e6;'>\n";
        echo "<tr><th>Dipendente ID</th><th>Data Inizio</th><th>Durata</th><th>Descrizione</th><th>Duplicati</th></tr>\n";
        foreach ($duplicates as $dup) {
            echo "<tr>\n";
            echo "<td>{$dup['dipendente_id']}</td>\n";
            echo "<td>{$dup['data_inizio']}</td>\n";
            echo "<td>{$dup['durata_ore']}</td>\n";
            echo "<td>" . htmlspecialchars(substr($dup['descrizione'], 0, 50)) . "...</td>\n";
            echo "<td><strong>{$dup['duplicates']}</strong></td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p style='color: green;'><strong>✅ Nessun duplicato trovato nelle attività</strong></p>\n";
    }
    
    // 5. Statistiche di import
    echo "<h3>5. Statistiche di Importazione</h3>\n";
    
    // Conta attività per data di creazione
    $stmt = $conn->prepare("
        SELECT 
            DATE(created_at) as import_date,
            COUNT(*) as count
        FROM attivita 
        GROUP BY DATE(created_at)
        ORDER BY import_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $import_stats = $stmt->fetchAll();
    
    if (!empty($import_stats)) {
        echo "<h4>Attività per Data di Importazione:</h4>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Data Import</th><th>Numero Attività</th></tr>\n";
        foreach ($import_stats as $stat) {
            echo "<tr><td>{$stat['import_date']}</td><td>{$stat['count']}</td></tr>\n";
        }
        echo "</table>\n";
    }
    
    // Summary finale
    echo "<h3>6. Summary Problemi</h3>\n";
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
    echo "<h4>🎯 Problemi Identificati:</h4>\n";
    echo "<ul>\n";
    
    if (!empty($suspicious_employees)) {
        echo "<li><strong>" . count($suspicious_employees) . " dipendenti sospetti</strong> da verificare/rimuovere</li>\n";
    }
    
    if (!empty($duplicates)) {
        echo "<li><strong>" . count($duplicates) . " gruppi di attività duplicate</strong> da pulire</li>\n";
    }
    
    echo "<li><strong>Dipendenti totali:</strong> " . count($all_dipendenti) . " (di cui " . count($valid_employees) . " validi)</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Errore durante la debug:</strong> " . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>

<script>
function checkDependencies(dipendente_id) {
    alert('Controllo dipendenze per dipendente ID: ' + dipendente_id);
    // Questa funzione potrebbe essere espansa per fare chiamate AJAX
}
</script>

<p><a href="diagnose_data.php">← Torna alla Diagnostica</a> | <a href="index.php">Dashboard</a></p>