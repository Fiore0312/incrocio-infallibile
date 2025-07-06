<?php
session_start();
require_once 'config/Database.php';

/**
 * Analisi Problemi Attuali Sistema
 * Identifica e risolve problemi collegamento vecchio/nuovo sistema
 */

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Analisi Problemi Attuali - Employee Analytics</title>\n";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
echo "<link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>\n";
echo "<style>\n";
echo ".analysis-card { margin: 15px 0; transition: all 0.3s ease; }\n";
echo ".analysis-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }\n";
echo ".problem-found { border-left: 4px solid #dc3545; }\n";
echo ".solution-ready { border-left: 4px solid #28a745; }\n";
echo ".warning-section { border-left: 4px solid #ffc107; }\n";
echo ".info-section { border-left: 4px solid #17a2b8; }\n";
echo ".status-badge { font-size: 0.8em; }\n";
echo ".fix-actions { margin-top: 15px; }\n";
echo "</style>\n";
echo "</head>\n<body>\n";

// Navigation
echo "<nav class='navbar navbar-expand-lg navbar-dark bg-info'>\n";
echo "<div class='container-fluid'>\n";
echo "<a class='navbar-brand' href='index.php'>\n";
echo "<i class='fas fa-search me-2'></i>Analisi Problemi Sistema\n";
echo "</a>\n";
echo "<div class='navbar-nav ms-auto'>\n";
echo "<a class='nav-link' href='index.php'><i class='fas fa-home me-1'></i>Dashboard</a>\n";
echo "<a class='nav-link' href='smart_upload_final.php'><i class='fas fa-magic me-1'></i>Smart Upload</a>\n";
echo "<a class='nav-link' href='master_data_console.php'><i class='fas fa-database me-1'></i>Master Data</a>\n";
echo "</div>\n";
echo "</div>\n";
echo "</nav>\n";

echo "<div class='container mt-4'>\n";

// Header
echo "<div class='row mb-4'>\n";
echo "<div class='col-12'>\n";
echo "<div class='card analysis-card info-section'>\n";
echo "<div class='card-body'>\n";
echo "<h2><i class='fas fa-search me-2'></i>Analisi Problemi Sistema</h2>\n";
echo "<p class='mb-0'>Diagnosi automatica dei problemi di collegamento tra vecchio e nuovo sistema</p>\n";
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";

$issues = [];
$solutions = [];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // 1. VERIFICA STATO DATABASE
    echo "<div class='row mb-4'>\n";
    echo "<div class='col-12'>\n";
    echo "<div class='card analysis-card'>\n";
    echo "<div class='card-header bg-primary text-white'>\n";
    echo "<h5 class='mb-0'><i class='fas fa-database me-2'></i>Stato Database</h5>\n";
    echo "</div>\n";
    echo "<div class='card-body'>\n";
    
    // Verifica tabelle esistenti
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_tables = [
        'master_dipendenti_fixed' => 'Dipendenti Master',
        'master_aziende' => 'Aziende Master',
        'association_queue' => 'Coda Associazioni'
    ];
    
    $missing_tables = [];
    $existing_tables = [];
    
    foreach ($required_tables as $table => $description) {
        if (in_array($table, $tables)) {
            $existing_tables[$table] = $description;
        } else {
            $missing_tables[$table] = $description;
        }
    }
    
    if (!empty($existing_tables)) {
        echo "<div class='alert alert-success'>\n";
        echo "<h6><i class='fas fa-check-circle me-2'></i>Tabelle Master Trovate</h6>\n";
        echo "<ul class='mb-0'>\n";
        foreach ($existing_tables as $table => $desc) {
            echo "<li><strong>$table</strong> - $desc</li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
    }
    
    if (!empty($missing_tables)) {
        echo "<div class='alert alert-danger'>\n";
        echo "<h6><i class='fas fa-exclamation-triangle me-2'></i>Tabelle Mancanti</h6>\n";
        echo "<ul class='mb-0'>\n";
        foreach ($missing_tables as $table => $desc) {
            echo "<li><strong>$table</strong> - $desc</li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
        
        $issues[] = "Tabelle master mancanti: " . implode(', ', array_keys($missing_tables));
        $solutions[] = "Eseguire setup schema master con smart_upload_final.php";
    }
    
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    // 2. VERIFICA DATI MASTER
    echo "<div class='row mb-4'>\n";
    echo "<div class='col-12'>\n";
    echo "<div class='card analysis-card'>\n";
    echo "<div class='card-header bg-success text-white'>\n";
    echo "<h5 class='mb-0'><i class='fas fa-users me-2'></i>Dati Master</h5>\n";
    echo "</div>\n";
    echo "<div class='card-body'>\n";
    
    $master_data_status = [];
    
    // Conta dipendenti
    if (in_array('master_dipendenti_fixed', $tables)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM master_dipendenti_fixed WHERE attivo = 1");
        $stmt->execute();
        $employees_count = $stmt->fetch()['count'];
        $master_data_status['dipendenti'] = $employees_count;
    } else {
        $master_data_status['dipendenti'] = 0;
    }
    
    // Conta aziende
    if (in_array('master_aziende', $tables)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM master_aziende WHERE attivo = 1");
        $stmt->execute();
        $companies_count = $stmt->fetch()['count'];
        $master_data_status['aziende'] = $companies_count;
    } else {
        $master_data_status['aziende'] = 0;
    }
    
    // Conta associazioni pending
    if (in_array('association_queue', $tables)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM association_queue WHERE stato = 'pending'");
        $stmt->execute();
        $pending_count = $stmt->fetch()['count'];
        $master_data_status['associazioni_pending'] = $pending_count;
    } else {
        $master_data_status['associazioni_pending'] = 0;
    }
    
    echo "<div class='row'>\n";
    foreach ($master_data_status as $type => $count) {
        $color = 'info';
        $icon = 'info-circle';
        $status = 'OK';
        
        if ($type === 'dipendenti') {
            if ($count >= 10) {
                $color = 'success';
                $icon = 'check-circle';
                $status = 'Pronto';
            } elseif ($count > 0) {
                $color = 'warning';
                $icon = 'exclamation-triangle';
                $status = 'Parziale';
            } else {
                $color = 'danger';
                $icon = 'times-circle';
                $status = 'Mancante';
                $issues[] = "Dipendenti master non configurati";
                $solutions[] = "Usare 'Setup Dipendenti' in smart_upload_final.php";
            }
        } elseif ($type === 'aziende') {
            if ($count >= 5) {
                $color = 'success';
                $icon = 'check-circle';
                $status = 'Pronto';
            } elseif ($count > 0) {
                $color = 'warning';
                $icon = 'exclamation-triangle';
                $status = 'Parziale';
            } else {
                $color = 'danger';
                $icon = 'times-circle';
                $status = 'Mancante';
                $issues[] = "Aziende master non configurate";
                $solutions[] = "Usare 'Setup Aziende' in smart_upload_final.php";
            }
        } elseif ($type === 'associazioni_pending') {
            if ($count == 0) {
                $color = 'success';
                $icon = 'check-circle';
                $status = 'Pulito';
            } else {
                $color = 'warning';
                $icon = 'exclamation-triangle';
                $status = 'Da Gestire';
                $issues[] = "$count associazioni in coda";
                $solutions[] = "Gestire associazioni in master_data_console.php";
            }
        }
        
        echo "<div class='col-md-4'>\n";
        echo "<div class='card mb-3'>\n";
        echo "<div class='card-body text-center'>\n";
        echo "<i class='fas fa-$icon text-$color fa-2x mb-2'></i>\n";
        echo "<h5>" . ucfirst(str_replace('_', ' ', $type)) . "</h5>\n";
        echo "<span class='badge bg-$color fs-6'>$count</span>\n";
        echo "<div class='mt-2'><small class='text-muted'>$status</small></div>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
    }
    echo "</div>\n";
    
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    // 3. VERIFICA COLLEGAMENTI SISTEMA
    echo "<div class='row mb-4'>\n";
    echo "<div class='col-12'>\n";
    echo "<div class='card analysis-card'>\n";
    echo "<div class='card-header bg-warning text-dark'>\n";
    echo "<h5 class='mb-0'><i class='fas fa-link me-2'></i>Collegamenti Sistema</h5>\n";
    echo "</div>\n";
    echo "<div class='card-body'>\n";
    
    // Verifica file mancanti
    $required_files = [
        'smart_upload_final.php' => 'Sistema Smart Upload',
        'master_data_console.php' => 'Console Master Data',
        'test_smart_parser.php' => 'Test Parser'
    ];
    
    $missing_files = [];
    $existing_files = [];
    
    foreach ($required_files as $file => $description) {
        if (file_exists($file)) {
            $existing_files[$file] = $description;
        } else {
            $missing_files[$file] = $description;
        }
    }
    
    if (!empty($existing_files)) {
        echo "<div class='alert alert-success'>\n";
        echo "<h6><i class='fas fa-check-circle me-2'></i>File Sistema Trovati</h6>\n";
        echo "<ul class='mb-0'>\n";
        foreach ($existing_files as $file => $desc) {
            echo "<li><strong>$file</strong> - $desc</li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
    }
    
    if (!empty($missing_files)) {
        echo "<div class='alert alert-danger'>\n";
        echo "<h6><i class='fas fa-exclamation-triangle me-2'></i>File Mancanti</h6>\n";
        echo "<ul class='mb-0'>\n";
        foreach ($missing_files as $file => $desc) {
            echo "<li><strong>$file</strong> - $desc</li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
        
        $issues[] = "File sistema mancanti: " . implode(', ', array_keys($missing_files));
        $solutions[] = "Ripristinare file dal backup o ricreare";
    }
    
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    // 4. TIPI FILE SUPPORTATI
    echo "<div class='row mb-4'>\n";
    echo "<div class='col-12'>\n";
    echo "<div class='card analysis-card'>\n";
    echo "<div class='card-header bg-info text-white'>\n";
    echo "<h5 class='mb-0'><i class='fas fa-file-csv me-2'></i>Tipi File Supportati</h5>\n";
    echo "</div>\n";
    echo "<div class='card-body'>\n";
    
    $current_file_types = ['attivita', 'calendario', 'timbrature', 'teamviewer'];
    $missing_file_types = ['permessi', 'progetti'];
    
    echo "<div class='row'>\n";
    echo "<div class='col-md-6'>\n";
    echo "<h6 class='text-success'><i class='fas fa-check me-2'></i>Supportati (4/6)</h6>\n";
    echo "<ul>\n";
    foreach ($current_file_types as $type) {
        echo "<li class='text-success'>$type</li>\n";
    }
    echo "</ul>\n";
    echo "</div>\n";
    echo "<div class='col-md-6'>\n";
    echo "<h6 class='text-warning'><i class='fas fa-exclamation-triangle me-2'></i>Mancanti (2/6)</h6>\n";
    echo "<ul>\n";
    foreach ($missing_file_types as $type) {
        echo "<li class='text-warning'>$type</li>\n";
    }
    echo "</ul>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    if (!empty($missing_file_types)) {
        $issues[] = "Tipi file mancanti: " . implode(', ', $missing_file_types);
        $solutions[] = "Estendere smart_upload_final.php per supportare permessi e progetti";
    }
    
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>\n";
    echo "<h5><i class='fas fa-exclamation-circle me-2'></i>Errore Analisi</h5>\n";
    echo "<p>Errore durante l'analisi: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
    
    $issues[] = "Errore connessione database: " . $e->getMessage();
    $solutions[] = "Verificare configurazione database in config/Database.php";
}

// 5. RIEPILOGO PROBLEMI E SOLUZIONI
echo "<div class='row mb-4'>\n";
echo "<div class='col-12'>\n";
echo "<div class='card analysis-card'>\n";
echo "<div class='card-header bg-dark text-white'>\n";
echo "<h5 class='mb-0'><i class='fas fa-clipboard-list me-2'></i>Riepilogo Problemi e Soluzioni</h5>\n";
echo "</div>\n";
echo "<div class='card-body'>\n";

if (!empty($issues)) {
    echo "<div class='alert alert-danger'>\n";
    echo "<h6><i class='fas fa-exclamation-triangle me-2'></i>Problemi Rilevati (" . count($issues) . ")</h6>\n";
    echo "<ol>\n";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>\n";
    }
    echo "</ol>\n";
    echo "</div>\n";
}

if (!empty($solutions)) {
    echo "<div class='alert alert-info'>\n";
    echo "<h6><i class='fas fa-lightbulb me-2'></i>Soluzioni Consigliate (" . count($solutions) . ")</h6>\n";
    echo "<ol>\n";
    foreach ($solutions as $solution) {
        echo "<li>$solution</li>\n";
    }
    echo "</ol>\n";
    echo "</div>\n";
}

if (empty($issues)) {
    echo "<div class='alert alert-success'>\n";
    echo "<h6><i class='fas fa-check-circle me-2'></i>Sistema Funzionante</h6>\n";
    echo "<p class='mb-0'>Nessun problema critico rilevato. Il sistema è pronto per l'uso.</p>\n";
    echo "</div>\n";
}

echo "</div>\n";
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";

// 6. AZIONI RAPIDE
echo "<div class='row mb-4'>\n";
echo "<div class='col-12'>\n";
echo "<div class='card analysis-card'>\n";
echo "<div class='card-header bg-success text-white'>\n";
echo "<h5 class='mb-0'><i class='fas fa-tools me-2'></i>Azioni Rapide</h5>\n";
echo "</div>\n";
echo "<div class='card-body'>\n";
echo "<div class='row'>\n";

echo "<div class='col-md-3 mb-3'>\n";
echo "<div class='d-grid'>\n";
echo "<a href='smart_upload_final.php' class='btn btn-primary'>\n";
echo "<i class='fas fa-magic me-2'></i>Smart Upload\n";
echo "</a>\n";
echo "</div>\n";
echo "</div>\n";

echo "<div class='col-md-3 mb-3'>\n";
echo "<div class='d-grid'>\n";
echo "<a href='master_data_console.php' class='btn btn-info'>\n";
echo "<i class='fas fa-database me-2'></i>Master Data\n";
echo "</a>\n";
echo "</div>\n";
echo "</div>\n";

echo "<div class='col-md-3 mb-3'>\n";
echo "<div class='d-grid'>\n";
echo "<a href='database_structure_analysis.php' class='btn btn-warning'>\n";
echo "<i class='fas fa-search me-2'></i>Struttura DB\n";
echo "</a>\n";
echo "</div>\n";
echo "</div>\n";

echo "<div class='col-md-3 mb-3'>\n";
echo "<div class='d-grid'>\n";
echo "<a href='index.php' class='btn btn-secondary'>\n";
echo "<i class='fas fa-home me-2'></i>Dashboard\n";
echo "</a>\n";
echo "</div>\n";
echo "</div>\n";

echo "</div>\n";
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";

echo "</div>\n"; // Close container

echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>\n";
echo "</body>\n</html>\n";
?>