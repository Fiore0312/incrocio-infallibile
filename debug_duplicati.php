<?php
require_once 'config/Database.php';

echo "<h2>🔍 Analisi Duplicati e Problemi di Importazione</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // 1. Analisi indici e chiavi tabelle critiche
    echo "<h3>1. Analisi Struttura Database</h3>\n";
    
    $tables = ['dipendenti', 'attivita', 'timbrature', 'calendario'];
    foreach ($tables as $table) {
        echo "<h4>Tabella: $table</h4>\n";
        
        // Mostra indici
        $stmt = $conn->prepare("SHOW INDEX FROM $table");
        $stmt->execute();
        $indexes = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Nome Indice</th><th>Colonna</th><th>Unico</th><th>Tipo</th></tr>\n";
        foreach ($indexes as $index) {
            $unique = $index['Non_unique'] ? 'No' : 'Sì';
            echo "<tr><td>{$index['Key_name']}</td><td>{$index['Column_name']}</td><td>$unique</td><td>{$index['Index_type']}</td></tr>\n";
        }
        echo "</table>\n";
    }
    
    // 2. Analisi specifica duplicati attività
    echo "<h3>2. Analisi Dettagliata Duplicati Attività</h3>\n";
    
    // Query più specifica per trovare duplicati esatti
    $stmt = $conn->prepare("
        SELECT 
            dipendente_id,
            data_inizio,
            data_fine,
            durata_ore,
            LEFT(descrizione, 100) as descrizione_short,
            creato_da,
            COUNT(*) as duplicates,
            GROUP_CONCAT(id ORDER BY id) as ids
        FROM attivita 
        GROUP BY dipendente_id, data_inizio, data_fine, durata_ore, LEFT(descrizione, 100), creato_da
        HAVING COUNT(*) > 1
        ORDER BY duplicates DESC, dipendente_id
        LIMIT 50
    ");
    $stmt->execute();
    $activity_duplicates = $stmt->fetchAll();
    
    if (!empty($activity_duplicates)) {
        echo "<p style='color: red;'><strong>❌ Trovati " . count($activity_duplicates) . " gruppi di attività duplicate identiche!</strong></p>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; background-color: #ffe6e6;'>\n";
        echo "<tr><th>Dipendente ID</th><th>Data Inizio</th><th>Data Fine</th><th>Durata</th><th>Descrizione</th><th>Creato Da</th><th>Duplicati</th><th>IDs</th></tr>\n";
        foreach ($activity_duplicates as $dup) {
            echo "<tr>\n";
            echo "<td>{$dup['dipendente_id']}</td>\n";
            echo "<td>{$dup['data_inizio']}</td>\n";
            echo "<td>{$dup['data_fine']}</td>\n";
            echo "<td>{$dup['durata_ore']}</td>\n";
            echo "<td>" . htmlspecialchars($dup['descrizione_short']) . "</td>\n";
            echo "<td>{$dup['creato_da']}</td>\n";
            echo "<td><strong style='color: red;'>{$dup['duplicates']}</strong></td>\n";
            echo "<td><small>{$dup['ids']}</small></td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Calcola totale attività duplicate
        $total_duplicates = 0;
        foreach ($activity_duplicates as $dup) {
            $total_duplicates += ($dup['duplicates'] - 1); // -1 perché il primo non è duplicato
        }
        echo "<p><strong>Total attività duplicate da rimuovere:</strong> <span style='color: red; font-size: 1.2em;'>$total_duplicates</span></p>\n";
        
    } else {
        echo "<p style='color: green;'><strong>✅ Nessun duplicato perfetto trovato nelle attività</strong></p>\n";
    }
    
    // 3. Analisi pattern di importazione
    echo "<h3>3. Pattern di Importazione Attività</h3>\n";
    
    // Raggruppa per data di creazione per vedere se ci sono state multiple importazioni
    $stmt = $conn->prepare("
        SELECT 
            DATE(created_at) as import_date,
            HOUR(created_at) as import_hour,
            COUNT(*) as count
        FROM attivita 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at), HOUR(created_at)
        ORDER BY import_date DESC, import_hour DESC
    ");
    $stmt->execute();
    $import_patterns = $stmt->fetchAll();
    
    if (!empty($import_patterns)) {
        echo "<h4>Importazioni per Data e Ora (ultimi 30 giorni):</h4>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Data</th><th>Ora</th><th>Attività Importate</th><th>Note</th></tr>\n";
        foreach ($import_patterns as $pattern) {
            $note = '';
            if ($pattern['count'] > 1000) {
                $note = '⚠️ Import massivo';
            } elseif ($pattern['count'] > 100) {
                $note = '⚠️ Import alto';
            }
            
            echo "<tr>\n";
            echo "<td>{$pattern['import_date']}</td>\n";
            echo "<td>{$pattern['import_hour']}:00</td>\n";
            echo "<td>{$pattern['count']}</td>\n";
            echo "<td>$note</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // 4. Analisi dipendenti creati di recente
    echo "<h3>4. Dipendenti Creati di Recente</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            nome,
            cognome,
            email,
            created_at,
            CASE 
                WHEN nome IN ('Punto', 'Fiesta', 'Peugeot') THEN 'VEICOLO'
                WHEN nome IN ('Info', 'System', 'Admin', 'Test') THEN 'SISTEMA'
                WHEN cognome = '' AND LENGTH(nome) < 4 THEN 'NOME_CORTO'
                WHEN nome LIKE '%@%' OR cognome LIKE '%@%' THEN 'EMAIL'
                ELSE 'VALIDO'
            END as tipo
        FROM dipendenti 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $recent_employees = $stmt->fetchAll();
    
    if (!empty($recent_employees)) {
        echo "<h4>Dipendenti creati negli ultimi 7 giorni:</h4>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>ID</th><th>Nome</th><th>Cognome</th><th>Email</th><th>Tipo</th><th>Creato</th></tr>\n";
        foreach ($recent_employees as $emp) {
            $tipo_color = ($emp['tipo'] == 'VALIDO') ? 'green' : 'red';
            echo "<tr>\n";
            echo "<td>{$emp['id']}</td>\n";
            echo "<td>{$emp['nome']}</td>\n";
            echo "<td>{$emp['cognome']}</td>\n";
            echo "<td>{$emp['email']}</td>\n";
            echo "<td style='color: $tipo_color; font-weight: bold;'>{$emp['tipo']}</td>\n";
            echo "<td>{$emp['created_at']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p>Nessun dipendente creato negli ultimi 7 giorni</p>\n";
    }
    
    // 5. Analisi file di log del sistema (se esistono)
    echo "<h3>5. Analisi Logs Importazione</h3>\n";
    
    $log_files = [
        '/var/log/php_errors.log',
        './logs/import.log', 
        './logs/csvparser.log'
    ];
    
    $logs_found = false;
    foreach ($log_files as $log_file) {
        if (file_exists($log_file)) {
            $logs_found = true;
            echo "<h4>Log: $log_file</h4>\n";
            
            // Leggi le ultime 20 righe del log
            $lines = file($log_file);
            $recent_lines = array_slice($lines, -20);
            
            echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow-y: auto;'>\n";
            foreach ($recent_lines as $line) {
                echo htmlspecialchars($line);
            }
            echo "</pre>\n";
        }
    }
    
    if (!$logs_found) {
        echo "<p style='color: orange;'>⚠️ Nessun file di log trovato per analizzare gli errori di importazione</p>\n";
    }
    
    // 6. Summary e raccomandazioni
    echo "<h3>6. Summary e Raccomandazioni</h3>\n";
    
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
    echo "<h4>🎯 Problemi Confermati:</h4>\n";
    echo "<ul>\n";
    
    if (!empty($activity_duplicates)) {
        echo "<li><strong>Duplicati Attività:</strong> " . count($activity_duplicates) . " gruppi trovati con circa $total_duplicates record da rimuovere</li>\n";
    }
    
    $problematic_employees = 0;
    foreach ($recent_employees as $emp) {
        if ($emp['tipo'] != 'VALIDO') $problematic_employees++;
    }
    
    if ($problematic_employees > 0) {
        echo "<li><strong>Dipendenti Problematici:</strong> $problematic_employees dipendenti con nomi non validi</li>\n";
    }
    
    echo "</ul>\n";
    
    echo "<h4>🛠️ Raccomandazioni Immediate:</h4>\n";
    echo "<ol>\n";
    echo "<li><strong>Pulizia Database:</strong> Rimuovere dipendenti non validi e attività duplicate</li>\n";
    echo "<li><strong>Migliorare Validazione:</strong> Aggiungere controlli nel CsvParser</li>\n";
    echo "<li><strong>Indici Unici:</strong> Verificare/aggiungere constraint per prevenire duplicati</li>\n";
    echo "<li><strong>Logging:</strong> Implementare logging dettagliato per future importazioni</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Errore durante l'analisi:</strong> " . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>

<p><a href="debug_dipendenti.php">Debug Dipendenti</a> | <a href="diagnose_data.php">← Diagnostica</a> | <a href="index.php">Dashboard</a></p>