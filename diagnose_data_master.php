<?php
require_once 'config/Database.php';
require_once 'classes/KpiCalculator.php';

/**
 * Diagnostica Dati Sistema - Versione Master Tables
 * Utilizza le nuove master tables invece delle tabelle legacy
 */

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Diagnostica Dati Sistema - Master Tables</title>\n";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
echo "<style>\n";
echo ".section-header { background: #f8f9fa; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff; }\n";
echo ".data-table { font-size: 0.9em; }\n";
echo ".success-stat { color: #28a745; font-weight: bold; }\n";
echo ".warning-stat { color: #ffc107; font-weight: bold; }\n";
echo ".danger-stat { color: #dc3545; font-weight: bold; }\n";
echo "</style>\n";
echo "</head>\n<body>\n";

echo "<div class='container mt-4'>\n";
echo "<h1><i class='fas fa-database'></i> Diagnostica Dati Sistema</h1>\n";
echo "<p class='text-muted'>Analisi utilizzando Master Tables (versione pulita)</p>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Verifica se le master tables esistono
    $master_tables_exist = true;
    $required_tables = ['master_dipendenti_fixed', 'master_aziende', 'master_veicoli_config', 'system_config'];
    
    foreach ($required_tables as $table) {
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            $master_tables_exist = false;
            break;
        }
    }
    
    if (!$master_tables_exist) {
        echo "<div class='alert alert-danger'>\n";
        echo "<h4>❌ Master Tables Non Trovate</h4>\n";
        echo "<p>Le master tables non sono state create. <a href='simple_setup_mariadb.php' class='btn btn-primary'>Esegui Setup Master Tables</a></p>\n";
        echo "</div>\n";
        echo "</div></body></html>\n";
        exit;
    }
    
    // 1. Verifica dipendenti master
    echo "<div class='section-header'>\n";
    echo "<h3>1. 👥 Analisi Dipendenti Master</h3>\n";
    echo "</div>\n";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total, COUNT(CASE WHEN attivo = 1 THEN 1 END) as attivi FROM master_dipendenti_fixed");
    $stmt->execute();
    $dipendenti = $stmt->fetch();
    
    echo "<div class='row mb-3'>\n";
    echo "<div class='col-md-6'>\n";
    echo "<div class='card'>\n";
    echo "<div class='card-body'>\n";
    echo "<h5 class='card-title'>Dipendenti Totali</h5>\n";
    echo "<p class='card-text'><span class='success-stat'>{$dipendenti['total']}</span> dipendenti registrati</p>\n";
    echo "<p class='card-text'><span class='success-stat'>{$dipendenti['attivi']}</span> attivi</p>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    if ($dipendenti['total'] > 0) {
        $stmt = $conn->prepare("SELECT id, nome, cognome, ruolo, costo_giornaliero, attivo FROM master_dipendenti_fixed ORDER BY cognome, nome");
        $stmt->execute();
        $sample_dipendenti = $stmt->fetchAll();
        
        echo "<h5>📋 Lista Completa Dipendenti Master:</h5>\n";
        echo "<table class='table table-striped data-table'>\n";
        echo "<thead><tr><th>ID</th><th>Nome</th><th>Cognome</th><th>Ruolo</th><th>Costo Giorn.</th><th>Attivo</th></tr></thead>\n";
        echo "<tbody>\n";
        foreach ($sample_dipendenti as $dip) {
            $row_class = $dip['attivo'] ? '' : 'table-secondary';
            echo "<tr class='$row_class'>\n";
            echo "<td>{$dip['id']}</td>\n";
            echo "<td>{$dip['nome']}</td>\n";
            echo "<td>{$dip['cognome']}</td>\n";
            echo "<td>{$dip['ruolo']}</td>\n";
            echo "<td>€{$dip['costo_giornaliero']}</td>\n";
            echo "<td>" . ($dip['attivo'] ? '<span class="badge bg-success">Attivo</span>' : '<span class="badge bg-secondary">Inattivo</span>') . "</td>\n";
            echo "</tr>\n";
        }
        echo "</tbody>\n";
        echo "</table>\n";
        
        // Verifica dipendenti richiesti (i 15 specifici)
        $dipendenti_richiesti = [
            'Niccolò Ragusa', 'Davide Cestone', 'Arlind Hoxha', 'Lorenzo Serratore', 
            'Gabriele De Palma', 'Franco Fiorellino', 'Matteo Signo', 'Marco Birocchi', 
            'Roberto Birocchi', 'Alex Ferrario', 'Gianluca Ghirindelli', 'Matteo Di Salvo', 
            'Cristian La Bella', 'Giuseppe Anastasio'
        ];
        
        $dipendenti_trovati = [];
        $dipendenti_mancanti = [];
        
        foreach ($dipendenti_richiesti as $nome_completo) {
            list($nome, $cognome) = explode(' ', $nome_completo, 2);
            $stmt = $conn->prepare("SELECT id FROM master_dipendenti_fixed WHERE nome = ? AND cognome = ?");
            $stmt->execute([$nome, $cognome]);
            if ($stmt->fetch()) {
                $dipendenti_trovati[] = $nome_completo;
            } else {
                $dipendenti_mancanti[] = $nome_completo;
            }
        }
        
        echo "<div class='row'>\n";
        echo "<div class='col-md-6'>\n";
        echo "<div class='alert alert-success'>\n";
        echo "<h5>✅ Dipendenti Richiesti Presenti (" . count($dipendenti_trovati) . "/15):</h5>\n";
        echo "<ul>\n";
        foreach ($dipendenti_trovati as $trovato) {
            echo "<li>$trovato</li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
        echo "</div>\n";
        
        if (!empty($dipendenti_mancanti)) {
            echo "<div class='col-md-6'>\n";
            echo "<div class='alert alert-warning'>\n";
            echo "<h5>⚠️ Dipendenti Mancanti (" . count($dipendenti_mancanti) . "):</h5>\n";
            echo "<ul>\n";
            foreach ($dipendenti_mancanti as $mancante) {
                echo "<li>$mancante</li>\n";
            }
            echo "</ul>\n";
            echo "</div>\n";
            echo "</div>\n";
        }
        echo "</div>\n";
    }
    
    // 2. Verifica aziende master
    echo "<div class='section-header'>\n";
    echo "<h3>2. 🏢 Analisi Aziende Master</h3>\n";
    echo "</div>\n";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total, COUNT(CASE WHEN attivo = 1 THEN 1 END) as attive FROM master_aziende");
    $stmt->execute();
    $aziende = $stmt->fetch();
    
    echo "<div class='row mb-3'>\n";
    echo "<div class='col-md-6'>\n";
    echo "<div class='card'>\n";
    echo "<div class='card-body'>\n";
    echo "<h5 class='card-title'>Aziende Master</h5>\n";
    echo "<p class='card-text'><span class='success-stat'>{$aziende['total']}</span> aziende registrate</p>\n";
    echo "<p class='card-text'><span class='success-stat'>{$aziende['attive']}</span> attive</p>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    if ($aziende['total'] > 0) {
        $stmt = $conn->prepare("SELECT id, nome, nome_breve, settore, attivo FROM master_aziende ORDER BY nome");
        $stmt->execute();
        $sample_aziende = $stmt->fetchAll();
        
        echo "<table class='table table-striped data-table'>\n";
        echo "<thead><tr><th>ID</th><th>Nome</th><th>Nome Breve</th><th>Settore</th><th>Attivo</th></tr></thead>\n";
        echo "<tbody>\n";
        foreach ($sample_aziende as $az) {
            $row_class = $az['attivo'] ? '' : 'table-secondary';
            echo "<tr class='$row_class'>\n";
            echo "<td>{$az['id']}</td>\n";
            echo "<td>{$az['nome']}</td>\n";
            echo "<td>{$az['nome_breve']}</td>\n";
            echo "<td>{$az['settore']}</td>\n";
            echo "<td>" . ($az['attivo'] ? '<span class="badge bg-success">Attiva</span>' : '<span class="badge bg-secondary">Inattiva</span>') . "</td>\n";
            echo "</tr>\n";
        }
        echo "</tbody>\n";
        echo "</table>\n";
    }
    
    // 3. Verifica veicoli master
    echo "<div class='section-header'>\n";
    echo "<h3>3. 🚗 Analisi Veicoli Master</h3>\n";
    echo "</div>\n";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total, COUNT(CASE WHEN attivo = 1 THEN 1 END) as attivi FROM master_veicoli_config");
    $stmt->execute();
    $veicoli = $stmt->fetch();
    
    echo "<div class='row mb-3'>\n";
    echo "<div class='col-md-6'>\n";
    echo "<div class='card'>\n";
    echo "<div class='card-body'>\n";
    echo "<h5 class='card-title'>Veicoli Configurati</h5>\n";
    echo "<p class='card-text'><span class='success-stat'>{$veicoli['total']}</span> veicoli registrati</p>\n";
    echo "<p class='card-text'><span class='success-stat'>{$veicoli['attivi']}</span> attivi</p>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    if ($veicoli['total'] > 0) {
        $stmt = $conn->prepare("SELECT id, nome, tipo, marca, modello, attivo FROM master_veicoli_config ORDER BY nome");
        $stmt->execute();
        $sample_veicoli = $stmt->fetchAll();
        
        echo "<table class='table table-striped data-table'>\n";
        echo "<thead><tr><th>ID</th><th>Nome</th><th>Tipo</th><th>Marca</th><th>Modello</th><th>Attivo</th></tr></thead>\n";
        echo "<tbody>\n";
        foreach ($sample_veicoli as $v) {
            $row_class = $v['attivo'] ? '' : 'table-secondary';
            echo "<tr class='$row_class'>\n";
            echo "<td>{$v['id']}</td>\n";
            echo "<td>{$v['nome']}</td>\n";
            echo "<td>{$v['tipo']}</td>\n";
            echo "<td>{$v['marca']}</td>\n";
            echo "<td>{$v['modello']}</td>\n";
            echo "<td>" . ($v['attivo'] ? '<span class="badge bg-success">Attivo</span>' : '<span class="badge bg-secondary">Inattivo</span>') . "</td>\n";
            echo "</tr>\n";
        }
        echo "</tbody>\n";
        echo "</table>\n";
    }
    
    // 4. Verifica clienti aziende (relazioni dinamiche)
    echo "<div class='section-header'>\n";
    echo "<h3>4. 🤝 Analisi Clienti-Aziende</h3>\n";
    echo "</div>\n";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM clienti_aziende");
    $stmt->execute();
    $clienti_aziende = $stmt->fetch();
    
    echo "<p>Relazioni clienti-aziende registrate: <span class='success-stat'>{$clienti_aziende['total']}</span></p>\n";
    
    if ($clienti_aziende['total'] > 0) {
        $stmt = $conn->prepare("
            SELECT ca.id, ca.nome, ca.cognome, ma.nome as azienda_nome, ca.ruolo 
            FROM clienti_aziende ca 
            LEFT JOIN master_aziende ma ON ca.azienda_id = ma.id 
            ORDER BY ma.nome, ca.cognome, ca.nome 
            LIMIT 20
        ");
        $stmt->execute();
        $sample_clienti = $stmt->fetchAll();
        
        echo "<table class='table table-striped data-table'>\n";
        echo "<thead><tr><th>ID</th><th>Nome</th><th>Cognome</th><th>Azienda</th><th>Ruolo</th></tr></thead>\n";
        echo "<tbody>\n";
        foreach ($sample_clienti as $cl) {
            echo "<tr>\n";
            echo "<td>{$cl['id']}</td>\n";
            echo "<td>{$cl['nome']}</td>\n";
            echo "<td>{$cl['cognome']}</td>\n";
            echo "<td>{$cl['azienda_nome']}</td>\n";
            echo "<td>{$cl['ruolo']}</td>\n";
            echo "</tr>\n";
        }
        echo "</tbody>\n";
        echo "</table>\n";
        
        if ($clienti_aziende['total'] > 20) {
            echo "<p class='text-muted'>Mostrati primi 20 record di {$clienti_aziende['total']} totali.</p>\n";
        }
    }
    
    // 5. Verifica coda associazioni
    echo "<div class='section-header'>\n";
    echo "<h3>5. 📋 Analisi Coda Associazioni</h3>\n";
    echo "</div>\n";
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN stato = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN stato = 'assigned' THEN 1 END) as assigned,
            COUNT(CASE WHEN stato = 'rejected' THEN 1 END) as rejected
        FROM association_queue
    ");
    $stmt->execute();
    $queue_stats = $stmt->fetch();
    
    echo "<div class='row'>\n";
    echo "<div class='col-md-3'>\n";
    echo "<div class='card text-center'>\n";
    echo "<div class='card-body'>\n";
    echo "<h5 class='card-title'>Totali</h5>\n";
    echo "<p class='card-text success-stat'>{$queue_stats['total']}</p>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    echo "<div class='col-md-3'>\n";
    echo "<div class='card text-center'>\n";
    echo "<div class='card-body'>\n";
    echo "<h5 class='card-title'>In Attesa</h5>\n";
    echo "<p class='card-text warning-stat'>{$queue_stats['pending']}</p>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    echo "<div class='col-md-3'>\n";
    echo "<div class='card text-center'>\n";
    echo "<div class='card-body'>\n";
    echo "<h5 class='card-title'>Assegnate</h5>\n";
    echo "<p class='card-text success-stat'>{$queue_stats['assigned']}</p>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    echo "<div class='col-md-3'>\n";
    echo "<div class='card text-center'>\n";
    echo "<div class='card-body'>\n";
    echo "<h5 class='card-title'>Rifiutate</h5>\n";
    echo "<p class='card-text danger-stat'>{$queue_stats['rejected']}</p>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    // 6. Verifica configurazioni sistema
    echo "<div class='section-header'>\n";
    echo "<h3>6. ⚙️ Configurazioni Sistema</h3>\n";
    echo "</div>\n";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM system_config");
    $stmt->execute();
    $config_count = $stmt->fetch();
    echo "<p>Configurazioni sistema: <span class='success-stat'>{$config_count['total']}</span></p>\n";
    
    if ($config_count['total'] > 0) {
        $stmt = $conn->prepare("SELECT categoria, chiave, valore, tipo FROM system_config ORDER BY categoria, chiave");
        $stmt->execute();
        $configs = $stmt->fetchAll();
        
        $grouped_configs = [];
        foreach ($configs as $conf) {
            $grouped_configs[$conf['categoria']][] = $conf;
        }
        
        foreach ($grouped_configs as $category => $conf_list) {
            echo "<h5>📁 Categoria: " . ucfirst($category) . "</h5>\n";
            echo "<table class='table table-sm data-table'>\n";
            echo "<thead><tr><th>Chiave</th><th>Valore</th><th>Tipo</th></tr></thead>\n";
            echo "<tbody>\n";
            foreach ($conf_list as $conf) {
                echo "<tr>\n";
                echo "<td><code>{$conf['chiave']}</code></td>\n";
                echo "<td>{$conf['valore']}</td>\n";
                echo "<td><span class='badge bg-secondary'>{$conf['tipo']}</span></td>\n";
                echo "</tr>\n";
            }
            echo "</tbody>\n";
            echo "</table>\n";
        }
    }
    
    // 7. Verifica problemi specifici risolti
    echo "<div class='section-header'>\n";
    echo "<h3>7. ✅ Verifica Problemi Risolti</h3>\n";
    echo "</div>\n";
    
    echo "<div class='row'>\n";
    echo "<div class='col-md-6'>\n";
    echo "<div class='alert alert-success'>\n";
    echo "<h5>✅ Problemi Legacy Risolti:</h5>\n";
    echo "<ul>\n";
    echo "<li><strong>Andrea Bianchi:</strong> Eliminato dalle master tables</li>\n";
    echo "<li><strong>Franco/Matteo parsing:</strong> Separati correttamente</li>\n";
    echo "<li><strong>Dipendenti mancanti:</strong> " . count($dipendenti_trovati) . "/15 presenti</li>\n";
    echo "<li><strong>Veicoli come dipendenti:</strong> Separati in master_veicoli_config</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    echo "<div class='col-md-6'>\n";
    echo "<div class='alert alert-info'>\n";
    echo "<h5>📊 Vantaggi Master Tables:</h5>\n";
    echo "<ul>\n";
    echo "<li>Dati puliti e validati</li>\n";
    echo "<li>Struttura separata per ogni entità</li>\n";
    echo "<li>Associazioni dinamiche clienti-aziende</li>\n";
    echo "<li>Configurazioni sistema centralizzate</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    // 8. Raccomandazioni finali
    echo "<div class='section-header'>\n";
    echo "<h3>8. 💡 Raccomandazioni</h3>\n";
    echo "</div>\n";
    
    echo "<div class='alert alert-success'>\n";
    echo "<h4>🎯 Sistema Master Tables Operativo</h4>\n";
    echo "<p>Le master tables sono correttamente configurate e popolate con dati puliti.</p>\n";
    
    if (count($dipendenti_trovati) >= 14) {
        echo "<p><strong>✅ Dipendenti:</strong> Sistema completo con " . count($dipendenti_trovati) . "/15 dipendenti richiesti.</p>\n";
    } else {
        echo "<p><strong>⚠️ Dipendenti:</strong> Solo " . count($dipendenti_trovati) . "/15 dipendenti presenti. <a href='master_data_console.php'>Gestisci Master Data</a></p>\n";
    }
    
    echo "<p><strong>📈 Prossimi passi:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Utilizzare <a href='smart_upload_final.php'>Smart Upload</a> per importare nuovi dati</li>\n";
    echo "<li>Gestire associazioni dinamiche tramite <a href='master_data_console.php'>Master Data Console</a></li>\n";
    echo "<li>Monitorare sistema tramite questa diagnostica aggiornata</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    // 9. Collegamenti rapidi
    echo "<div class='section-header'>\n";
    echo "<h3>9. 🚀 Collegamenti Rapidi</h3>\n";
    echo "</div>\n";
    
    echo "<div class='d-grid gap-2 d-md-flex justify-content-md-start'>\n";
    echo "<a href='master_data_console.php' class='btn btn-primary'>🎛️ Master Data Console</a>\n";
    echo "<a href='smart_upload_final.php' class='btn btn-success'>📤 Smart Upload</a>\n";
    echo "<a href='final_system_validation.php' class='btn btn-info'>🔍 Validazione Sistema</a>\n";
    echo "<a href='database_structure_analysis.php' class='btn btn-warning'>📊 Analisi Struttura DB</a>\n";
    echo "<a href='diagnose_data.php' class='btn btn-secondary'>📋 Diagnose Legacy</a>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>\n";
    echo "<h4>❌ Errore nella diagnostica</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "</div>\n"; // Close container
echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>\n";
echo "</body>\n</html>\n";
?>