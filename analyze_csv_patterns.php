<?php
require_once 'config/Database.php';

echo "<h2>🔍 Analisi Pattern CSV - Separatori Nomi e Duplicati</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // 1. Analisi pattern nomi nei file CSV caricati
    echo "<h3>1. Analisi Pattern Nomi Dipendenti dal Database</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT 
            nome, 
            cognome, 
            CONCAT(nome, ' ', cognome) as nome_completo,
            created_at,
            CASE 
                WHEN nome LIKE '%/%' OR cognome LIKE '%/%' THEN 'SEPARATORE_SLASH'
                WHEN nome LIKE '%,%' OR cognome LIKE '%,%' THEN 'SEPARATORE_VIRGOLA'
                WHEN nome LIKE '%;%' OR cognome LIKE '%;%' THEN 'SEPARATORE_PUNTO_VIRGOLA'
                WHEN LENGTH(nome) = 0 OR LENGTH(cognome) = 0 THEN 'CAMPO_VUOTO'
                WHEN nome REGEXP '[0-9]' OR cognome REGEXP '[0-9]' THEN 'CONTIENE_NUMERI'
                ELSE 'NORMALE'
            END as pattern_type
        FROM dipendenti 
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $dipendenti_patterns = $stmt->fetchAll();
    
    // Raggruppa per tipo di pattern
    $pattern_groups = [];
    foreach ($dipendenti_patterns as $dip) {
        $pattern_groups[$dip['pattern_type']][] = $dip;
    }
    
    foreach ($pattern_groups as $pattern_type => $dipendenti) {
        echo "<h4>Pattern: $pattern_type (" . count($dipendenti) . " dipendenti)</h4>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Nome</th><th>Cognome</th><th>Nome Completo</th><th>Creato</th></tr>\n";
        
        foreach (array_slice($dipendenti, 0, 10) as $dip) {
            $style = '';
            if ($pattern_type !== 'NORMALE') {
                $style = 'background-color: #ffe6e6;';
            }
            echo "<tr style='$style'>\n";
            echo "<td>{$dip['nome']}</td>\n";
            echo "<td>{$dip['cognome']}</td>\n";
            echo "<td>{$dip['nome_completo']}</td>\n";
            echo "<td>{$dip['created_at']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        if (count($dipendenti) > 10) {
            echo "<p><em>... e altri " . (count($dipendenti) - 10) . " dipendenti con lo stesso pattern</em></p>\n";
        }
    }
    
    // 2. Ricerca nomi con separatori specifici
    echo "<h3>2. Analisi Nomi Composti con Separatori</h3>\n";
    
    $separators = ['/', ',', ';', '-', '+', '&'];
    $found_separators = [];
    
    foreach ($separators as $sep) {
        $stmt = $conn->prepare("
            SELECT nome, cognome, CONCAT(nome, ' ', cognome) as nome_completo
            FROM dipendenti 
            WHERE nome LIKE ? OR cognome LIKE ?
            LIMIT 20
        ");
        $pattern = "%$sep%";
        $stmt->execute([$pattern, $pattern]);
        $results = $stmt->fetchAll();
        
        if (!empty($results)) {
            $found_separators[$sep] = $results;
        }
    }
    
    if (!empty($found_separators)) {
        foreach ($found_separators as $separator => $dipendenti) {
            echo "<h4>Separatore: '$separator' (" . count($dipendenti) . " dipendenti)</h4>\n";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
            echo "<tr><th>Nome</th><th>Cognome</th><th>Nome Completo</th><th>Possibili Split</th></tr>\n";
            
            foreach ($dipendenti as $dip) {
                $nome_split = '';
                if (strpos($dip['nome'], $separator) !== false) {
                    $parts = explode($separator, $dip['nome']);
                    $nome_split = "Nome: [" . implode('] [', $parts) . "]";
                }
                if (strpos($dip['cognome'], $separator) !== false) {
                    $parts = explode($separator, $dip['cognome']);
                    $nome_split .= " Cognome: [" . implode('] [', $parts) . "]";
                }
                
                echo "<tr style='background-color: #fff3cd;'>\n";
                echo "<td>{$dip['nome']}</td>\n";
                echo "<td>{$dip['cognome']}</td>\n";
                echo "<td>{$dip['nome_completo']}</td>\n";
                echo "<td><small>$nome_split</small></td>\n";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    } else {
        echo "<p style='color: green;'>✅ Nessun separatore problematico trovato nei nomi dipendenti</p>\n";
    }
    
    // 3. Analisi duplicazioni negli import
    echo "<h3>3. Analisi Duplicazioni Massive Import</h3>\n";
    
    // Conta importazioni per data
    $stmt = $conn->prepare("
        SELECT 
            DATE(created_at) as import_date,
            HOUR(created_at) as import_hour,
            COUNT(*) as dipendenti_creati
        FROM dipendenti 
        GROUP BY DATE(created_at), HOUR(created_at)
        ORDER BY import_date DESC, import_hour DESC
        LIMIT 20
    ");
    $stmt->execute();
    $import_stats = $stmt->fetchAll();
    
    echo "<h4>Cronologia Creazione Dipendenti:</h4>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Data</th><th>Ora</th><th>Dipendenti Creati</th><th>Note</th></tr>\n";
    
    foreach ($import_stats as $stat) {
        $note = '';
        $style = '';
        if ($stat['dipendenti_creati'] > 20) {
            $note = '⚠️ Import massivo';
            $style = 'background-color: #ffe6e6;';
        } elseif ($stat['dipendenti_creati'] > 10) {
            $note = '⚠️ Import elevato';
            $style = 'background-color: #fff3cd;';
        }
        
        echo "<tr style='$style'>\n";
        echo "<td>{$stat['import_date']}</td>\n";
        echo "<td>{$stat['import_hour']}:00</td>\n";
        echo "<td>{$stat['dipendenti_creati']}</td>\n";
        echo "<td>$note</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // 4. Analisi attività duplicate
    echo "<h3>4. Analisi Attività Duplicate</h3>\n";
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_attivita,
            COUNT(DISTINCT CONCAT(dipendente_id, '-', data_inizio, '-', durata_ore)) as unique_attivita,
            COUNT(*) - COUNT(DISTINCT CONCAT(dipendente_id, '-', data_inizio, '-', durata_ore)) as duplicate_count
        FROM attivita
    ");
    $stmt->execute();
    $activity_stats = $stmt->fetch();
    
    echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin: 10px 0;'>\n";
    echo "<h4>📊 Statistiche Attività:</h4>\n";
    echo "<p><strong>Totale attività:</strong> {$activity_stats['total_attivita']}</p>\n";
    echo "<p><strong>Attività uniche:</strong> {$activity_stats['unique_attivita']}</p>\n";
    echo "<p><strong>Attività duplicate:</strong> <span style='color: red; font-size: 1.2em;'>{$activity_stats['duplicate_count']}</span></p>\n";
    
    if ($activity_stats['duplicate_count'] > 0) {
        $duplication_factor = round($activity_stats['total_attivita'] / $activity_stats['unique_attivita'], 2);
        echo "<p><strong>Fattore duplicazione:</strong> <span style='color: red; font-weight: bold;'>{$duplication_factor}x</span></p>\n";
    }
    echo "</div>\n";
    
    // 5. Analisi file CSV originali (se esistono)
    echo "<h3>5. Analisi File CSV Disponibili</h3>\n";
    
    $csv_directories = ['file-orig-300625/', 'uploads/'];
    $csv_files_found = [];
    
    foreach ($csv_directories as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*.csv');
            foreach ($files as $file) {
                $csv_files_found[] = [
                    'path' => $file,
                    'size' => filesize($file),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    'lines' => count(file($file))
                ];
            }
        }
    }
    
    if (!empty($csv_files_found)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>File</th><th>Dimensione</th><th>Righe</th><th>Modificato</th><th>Azioni</th></tr>\n";
        
        foreach ($csv_files_found as $file) {
            $size_kb = round($file['size'] / 1024, 2);
            echo "<tr>\n";
            echo "<td>" . basename($file['path']) . "</td>\n";
            echo "<td>{$size_kb} KB</td>\n";
            echo "<td>{$file['lines']}</td>\n";
            echo "<td>{$file['modified']}</td>\n";
            echo "<td><button onclick='analyzeFile(\"{$file['path']}\")' style='background: #007bff; color: white; border: none; padding: 5px;'>Analizza</button></td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // 6. Dipendenti mancanti menzionati dall'utente
    echo "<h3>6. Verifica Dipendenti Mancanti</h3>\n";
    
    $expected_employees = [
        'Franco Fiorellino',
        'Matteo Signo', 
        'Arlind Hoxha',
        'Lorenzo Serratore',
        'Alex Ferrario',
        'Roberto Birocchi',
        'Gabriele De Palma',
        'Marco Birocchi',
        'Davide Cestone',
        'Matteo Di Salvo'
    ];
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Nome Atteso</th><th>Presente nel DB</th><th>Varianti Trovate</th></tr>\n";
    
    foreach ($expected_employees as $expected) {
        $parts = explode(' ', $expected);
        $nome = $parts[0];
        $cognome = isset($parts[1]) ? $parts[1] : '';
        
        // Cerca exact match
        $stmt = $conn->prepare("SELECT id, nome, cognome FROM dipendenti WHERE nome = ? AND cognome = ?");
        $stmt->execute([$nome, $cognome]);
        $exact_match = $stmt->fetch();
        
        // Cerca varianti
        $stmt = $conn->prepare("SELECT id, nome, cognome FROM dipendenti WHERE nome LIKE ? OR cognome LIKE ? LIMIT 5");
        $stmt->execute(["%$nome%", "%$cognome%"]);
        $variants = $stmt->fetchAll();
        
        $status = $exact_match ? '✅ Trovato' : '❌ Mancante';
        $status_color = $exact_match ? 'green' : 'red';
        
        $variants_str = '';
        if (!empty($variants)) {
            $variants_list = [];
            foreach ($variants as $v) {
                $variants_list[] = "{$v['nome']} {$v['cognome']}";
            }
            $variants_str = implode('<br>', $variants_list);
        }
        
        echo "<tr>\n";
        echo "<td><strong>$expected</strong></td>\n";
        echo "<td style='color: $status_color;'>$status</td>\n";
        echo "<td><small>$variants_str</small></td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Summary e raccomandazioni
    echo "<h3>7. Summary Analisi e Raccomandazioni</h3>\n";
    
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
    echo "<h4>🎯 Problemi Confermati:</h4>\n";
    echo "<ul>\n";
    
    $issues_found = 0;
    if ($activity_stats['duplicate_count'] > 0) {
        echo "<li><strong>Duplicazione Attività:</strong> {$activity_stats['duplicate_count']} duplicati trovati</li>\n";
        $issues_found++;
    }
    
    if (!empty($found_separators)) {
        echo "<li><strong>Nomi con Separatori:</strong> " . count($found_separators) . " tipi di separatori trovati</li>\n";
        $issues_found++;
    }
    
    // Conta dipendenti mancanti
    $missing_count = 0;
    foreach ($expected_employees as $expected) {
        $parts = explode(' ', $expected);
        $stmt = $conn->prepare("SELECT id FROM dipendenti WHERE nome = ? AND cognome = ?");
        $stmt->execute([$parts[0], isset($parts[1]) ? $parts[1] : '']);
        if (!$stmt->fetch()) $missing_count++;
    }
    
    if ($missing_count > 0) {
        echo "<li><strong>Dipendenti Mancanti:</strong> $missing_count dipendenti attesi non trovati</li>\n";
        $issues_found++;
    }
    
    if ($issues_found == 0) {
        echo "<li style='color: green;'>✅ Nessun problema critico rilevato dall'analisi automatica</li>\n";
    }
    
    echo "</ul>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante l'analisi</h4>\n";
    echo "<p><strong>Errore:</strong> " . $e->getMessage() . "</p>\n";
    echo "</div>\n";
}
?>

<script>
function analyzeFile(filepath) {
    alert('Analisi file: ' + filepath + '\nQuesto aprirebbe un tool per analizzare il contenuto del CSV');
    // Qui potremmo implementare un'analisi più dettagliata del file
}
</script>

<p>
    <a href="debug_dipendenti.php">🔍 Debug Dipendenti</a> | 
    <a href="verify_data_integrity.php">📊 Verifica Integrità</a> | 
    <a href="index.php">Dashboard</a>
</p>