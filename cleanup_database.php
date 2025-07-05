<?php
require_once 'config/Database.php';

echo "<h2>🧹 Pulizia Database - Rimozione Dipendenti Errati e Duplicati</h2>\n";

// Controllo di sicurezza
$confirm = isset($_GET['confirm']) ? $_GET['confirm'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($confirm !== 'yes') {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h3>⚠️ ATTENZIONE - Operazione Critica</h3>\n";
    echo "<p>Questa operazione <strong>rimuoverà permanentemente</strong> dati dal database:</p>\n";
    echo "<ul>\n";
    echo "<li>Dipendenti con nomi non validi (veicoli, sistemi, ecc.)</li>\n";
    echo "<li>Attività duplicate identiche</li>\n";
    echo "<li>Record correlati (KPI, timbrature, ecc.)</li>\n";
    echo "</ul>\n";
    echo "<p><strong>ASSICURATI DI AVER FATTO UN BACKUP DEL DATABASE PRIMA DI PROCEDERE!</strong></p>\n";
    echo "<p><a href='?confirm=yes&action=preview' style='background: orange; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>📋 Anteprima Modifiche</a></p>\n";
    echo "<p><a href='?confirm=yes&action=cleanup' style='background: red; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🧹 ESEGUI PULIZIA</a></p>\n";
    echo "</div>\n";
    echo "<p><a href='debug_dipendenti.php'>← Torna al Debug</a></p>\n";
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($action === 'preview') {
        echo "<h3>📋 Anteprima Modifiche (NESSUNA modifica sarà eseguita)</h3>\n";
        $preview_mode = true;
    } else {
        echo "<h3>🧹 Esecuzione Pulizia Database</h3>\n";
        $preview_mode = false;
        $conn->beginTransaction();
    }
    
    $changes_summary = [];
    
    // 1. Identifica dipendenti problematici
    echo "<h4>1. Analisi Dipendenti Problematici</h4>\n";
    
    $stmt = $conn->prepare("
        SELECT 
            id, nome, cognome, email,
            CASE 
                WHEN nome IN ('Punto', 'Fiesta', 'Peugeot') THEN 'VEICOLO'
                WHEN nome IN ('Info', 'System', 'Admin', 'Test', 'Aurora') THEN 'SISTEMA'
                WHEN cognome = '' AND LENGTH(nome) < 3 THEN 'NOME_CORTO'
                WHEN nome LIKE '%@%' OR cognome LIKE '%@%' THEN 'EMAIL'
                ELSE 'VALIDO'
            END as tipo
        FROM dipendenti 
        WHERE nome IN ('Punto', 'Fiesta', 'Peugeot', 'Info', 'System', 'Admin', 'Test', 'Aurora')
           OR (cognome = '' AND LENGTH(nome) < 3)
           OR nome LIKE '%@%' 
           OR cognome LIKE '%@%'
        ORDER BY tipo, nome
    ");
    $stmt->execute();
    $problematic_employees = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>ID</th><th>Nome</th><th>Cognome</th><th>Email</th><th>Tipo Problema</th><th>Azione</th></tr>\n";
    
    $employees_to_delete = [];
    foreach ($problematic_employees as $emp) {
        $tipo_color = 'red';
        echo "<tr style='background-color: #ffe6e6;'>\n";
        echo "<td>{$emp['id']}</td>\n";
        echo "<td>{$emp['nome']}</td>\n";
        echo "<td>{$emp['cognome']}</td>\n";
        echo "<td>{$emp['email']}</td>\n";
        echo "<td style='color: $tipo_color; font-weight: bold;'>{$emp['tipo']}</td>\n";
        echo "<td>DA RIMUOVERE</td>\n";
        echo "</tr>\n";
        
        $employees_to_delete[] = $emp['id'];
    }
    echo "</table>\n";
    
    $changes_summary[] = count($problematic_employees) . " dipendenti problematici da rimuovere";
    
    // 2. Analizza dipendenze per i dipendenti da rimuovere
    echo "<h4>2. Analisi Dipendenze</h4>\n";
    
    $total_dependencies = 0;
    if (!empty($employees_to_delete)) {
        $ids_placeholder = implode(',', array_fill(0, count($employees_to_delete), '?'));
        
        // Conta dipendenze
        $dependency_queries = [
            'anomalie' => "SELECT COUNT(*) as count FROM anomalie WHERE dipendente_id IN ($ids_placeholder) OR risolto_da IN ($ids_placeholder)",
            'attivita' => "SELECT COUNT(*) as count FROM attivita WHERE dipendente_id IN ($ids_placeholder)",
            'timbrature' => "SELECT COUNT(*) as count FROM timbrature WHERE dipendente_id IN ($ids_placeholder)",
            'calendario' => "SELECT COUNT(*) as count FROM calendario WHERE dipendente_id IN ($ids_placeholder)",
            'kpi_giornalieri' => "SELECT COUNT(*) as count FROM kpi_giornalieri WHERE dipendente_id IN ($ids_placeholder)",
            'teamviewer_sessioni' => "SELECT COUNT(*) as count FROM teamviewer_sessioni WHERE dipendente_id IN ($ids_placeholder)",
            'registro_auto' => "SELECT COUNT(*) as count FROM registro_auto WHERE dipendente_id IN ($ids_placeholder)"
        ];
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Tabella</th><th>Record da Rimuovere</th></tr>\n";
        
        foreach ($dependency_queries as $table => $query) {
            $stmt = $conn->prepare($query);
            
            // Gestione speciale per tabella anomalie (ha due foreign key verso dipendenti)
            if ($table === 'anomalie') {
                $stmt->execute(array_merge($employees_to_delete, $employees_to_delete));
            } else {
                $stmt->execute($employees_to_delete);
            }
            
            $count = $stmt->fetch()['count'];
            
            echo "<tr><td>$table</td><td>$count</td></tr>\n";
            $total_dependencies += $count;
            
            if ($count > 0) {
                $changes_summary[] = "$count record da rimuovere in $table";
            }
        }
        echo "</table>\n";
    }
    
    // 3. Identifica attività duplicate
    echo "<h4>3. Analisi Attività Duplicate</h4>\n";
    
    $stmt = $conn->prepare("
        SELECT 
            dipendente_id,
            data_inizio,
            data_fine,
            durata_ore,
            LEFT(COALESCE(descrizione, ''), 100) as descrizione_short,
            creato_da,
            COUNT(*) as duplicates,
            GROUP_CONCAT(id ORDER BY id) as ids,
            MIN(id) as keep_id
        FROM attivita 
        GROUP BY dipendente_id, data_inizio, data_fine, durata_ore, LEFT(COALESCE(descrizione, ''), 100), creato_da
        HAVING COUNT(*) > 1
        ORDER BY duplicates DESC
        LIMIT 100
    ");
    $stmt->execute();
    $duplicate_activities = $stmt->fetchAll();
    
    $activities_to_delete = [];
    $total_duplicate_removals = 0;
    
    if (!empty($duplicate_activities)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Dipendente</th><th>Data Inizio</th><th>Durata</th><th>Descrizione</th><th>Duplicati</th><th>Da Rimuovere</th><th>Mantiene</th></tr>\n";
        
        foreach ($duplicate_activities as $dup) {
            $ids_array = explode(',', $dup['ids']);
            $keep_id = $dup['keep_id'];
            $remove_ids = array_filter($ids_array, function($id) use ($keep_id) {
                return $id != $keep_id;
            });
            
            echo "<tr style='background-color: #fff3cd;'>\n";
            echo "<td>{$dup['dipendente_id']}</td>\n";
            echo "<td>{$dup['data_inizio']}</td>\n";
            echo "<td>{$dup['durata_ore']}</td>\n";
            echo "<td>" . htmlspecialchars($dup['descrizione_short']) . "</td>\n";
            echo "<td><strong>{$dup['duplicates']}</strong></td>\n";
            echo "<td>" . implode(', ', $remove_ids) . "</td>\n";
            echo "<td style='color: green;'><strong>$keep_id</strong></td>\n";
            echo "</tr>\n";
            
            $activities_to_delete = array_merge($activities_to_delete, $remove_ids);
            $total_duplicate_removals += count($remove_ids);
        }
        echo "</table>\n";
        
        $changes_summary[] = "$total_duplicate_removals attività duplicate da rimuovere";
    } else {
        echo "<p style='color: green;'>✅ Nessuna attività duplicata trovata</p>\n";
    }
    
    // 4. Summary delle modifiche
    echo "<h4>4. Summary Modifiche</h4>\n";
    echo "<div style='background: #e7f3ff; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px;'>\n";
    if (!empty($changes_summary)) {
        echo "<ul>\n";
        foreach ($changes_summary as $change) {
            echo "<li>$change</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p style='color: green;'>✅ Nessuna modifica necessaria - Database già pulito!</p>\n";
    }
    echo "</div>\n";
    
    // 5. Esecuzione effettiva (solo se non in preview)
    if (!$preview_mode && (!empty($employees_to_delete) || !empty($activities_to_delete))) {
        echo "<h4>5. Esecuzione Pulizia</h4>\n";
        
        $operations_completed = 0;
        
        // Rimuovi attività duplicate
        if (!empty($activities_to_delete)) {
            $chunks = array_chunk($activities_to_delete, 100); // Processa in batch di 100
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $stmt = $conn->prepare("DELETE FROM attivita WHERE id IN ($placeholders)");
                $stmt->execute($chunk);
                $operations_completed += $stmt->rowCount();
            }
            echo "<p>✅ Rimosse $operations_completed attività duplicate</p>\n";
        }
        
        // Rimuovi dipendenze dei dipendenti problematici
        if (!empty($employees_to_delete)) {
            $placeholders = implode(',', array_fill(0, count($employees_to_delete), '?'));
            
            // Rimuovi in ordine corretto per rispettare le foreign key
            $delete_order = [
                'anomalie',           // PRIMA - ha foreign key verso dipendenti
                'teamviewer_sessioni',
                'kpi_giornalieri', 
                'calendario',
                'registro_auto',
                'timbrature',
                'attivita'
            ];
            
            foreach ($delete_order as $table) {
                // Gestione speciale per tabella anomalie (ha due foreign key verso dipendenti)
                if ($table === 'anomalie') {
                    $stmt = $conn->prepare("DELETE FROM anomalie WHERE dipendente_id IN ($placeholders) OR risolto_da IN ($placeholders)");
                    $stmt->execute(array_merge($employees_to_delete, $employees_to_delete)); // Parametri duplicati per entrambi i campi
                } else {
                    $stmt = $conn->prepare("DELETE FROM $table WHERE dipendente_id IN ($placeholders)");
                    $stmt->execute($employees_to_delete);
                }
                
                $deleted = $stmt->rowCount();
                if ($deleted > 0) {
                    echo "<p>✅ Rimossi $deleted record da $table</p>\n";
                }
            }
            
            // Infine rimuovi i dipendenti
            $stmt = $conn->prepare("DELETE FROM dipendenti WHERE id IN ($placeholders)");
            $stmt->execute($employees_to_delete);
            $deleted_employees = $stmt->rowCount();
            echo "<p>✅ Rimossi $deleted_employees dipendenti problematici</p>\n";
        }
        
        $conn->commit();
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h4>🎉 Pulizia Completata con Successo!</h4>\n";
        echo "<p>Il database è stato pulito. Tutti i dipendenti non validi e le attività duplicate sono stati rimossi.</p>\n";
        echo "</div>\n";
        
    } elseif ($preview_mode) {
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h4>📋 Anteprima Completata</h4>\n";
        echo "<p>Questa è solo un'anteprima. Per eseguire la pulizia, clicca sul pulsante 'ESEGUI PULIZIA' sopra.</p>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    if (!$preview_mode && isset($conn)) {
        $conn->rollback();
    }
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante la pulizia</h4>\n";
    echo "<p><strong>Errore:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p>Nessuna modifica è stata effettuata al database.</p>\n";
    echo "</div>\n";
}
?>

<p>
    <a href="debug_dipendenti.php">Debug Dipendenti</a> | 
    <a href="debug_duplicati.php">Debug Duplicati</a> | 
    <a href="diagnose_data_master.php">← Diagnostica</a> | 
    <a href="index.php">Dashboard</a>
</p>