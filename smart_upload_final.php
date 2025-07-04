<?php
session_start();
require_once 'config/Database.php';
require_once 'classes/SmartCsvParser.php';
require_once 'classes/KpiCalculator.php';
require_once 'classes/ValidationEngine.php';

/**
 * Smart Upload Final - Sistema Completo
 * Integra tutto: dipendenti fissi, aziende configurabili, associazioni dinamiche
 */

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Smart Upload Final - Employee Analytics</title>\n";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
echo "<link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>\n";
echo "<style>\n";
echo ".upload-zone { border: 2px dashed #dee2e6; border-radius: 10px; padding: 40px; text-align: center; transition: all 0.3s; }\n";
echo ".upload-zone:hover { border-color: #007bff; background-color: #f8f9fa; }\n";
echo ".upload-zone.dragover { border-color: #28a745; background-color: #d4edda; }\n";
echo ".smart-feature { background: linear-gradient(135deg, #007bff, #28a745); color: white; border-radius: 10px; }\n";
echo ".result-card { transition: all 0.3s ease; }\n";
echo ".result-card:hover { transform: translateY(-2px); }\n";
echo ".progress-container { display: none; }\n";
echo "</style>\n";
echo "</head>\n<body>\n";

// Navigation
echo "<nav class='navbar navbar-expand-lg navbar-dark bg-success'>\n";
echo "<div class='container-fluid'>\n";
echo "<a class='navbar-brand' href='index.php'>\n";
echo "<i class='fas fa-magic me-2'></i>Smart Upload Final\n";
echo "</a>\n";
echo "<div class='navbar-nav ms-auto'>\n";
echo "<a class='nav-link' href='index.php'><i class='fas fa-home me-1'></i>Dashboard</a>\n";
echo "<a class='nav-link' href='master_data_console.php'><i class='fas fa-database me-1'></i>Master Data</a>\n";
echo "<a class='nav-link' href='test_smart_parser.php'><i class='fas fa-flask me-1'></i>Test</a>\n";
echo "</div>\n";
echo "</div>\n";
echo "</nav>\n";

$message = '';
$message_type = '';
$upload_results = [];
$smart_stats = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        
        if (!$database->databaseExists()) {
            throw new Exception("Database non trovato. Eseguire prima il setup.");
        }
        
        // Crea directory upload temporanea
        $upload_dir = 'uploads/smart_' . date('Y-m-d_H-i-s') . '/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $uploaded_files = [];
        
        // Processa file upload
        foreach ($_FILES as $key => $file) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $filename = basename($file['name']);
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $uploaded_files[] = [
                        'field' => $key,
                        'filename' => $filename,
                        'path' => $target_path
                    ];
                }
            }
        }
        
        if (!empty($uploaded_files)) {
            // Usa Smart Parser per elaborazione
            $smart_parser = new SmartCsvParser();
            $processing_results = [];
            
            foreach ($uploaded_files as $file_info) {
                $result = $smart_parser->processFile($file_info['path'], $file_info['field']);
                $processing_results[$file_info['field']] = array_merge($result, [
                    'filename' => $file_info['filename']
                ]);
            }
            
            $success_count = 0;
            $total_processed = 0;
            $total_associations = 0;
            
            foreach ($processing_results as $type => $result) {
                if (isset($result['success']) && $result['success']) {
                    $success_count++;
                    if (isset($result['stats'])) {
                        $total_processed += $result['stats']['inserted'] + $result['stats']['updated'];
                        $total_associations += $result['stats']['associations_created'] ?? 0;
                    }
                }
            }
            
            // Calcola KPI se successo
            if ($success_count > 0) {
                $kpiCalculator = new KpiCalculator();
                $kpiCalculator->calculateAllKpis();
                
                $validationEngine = new ValidationEngine();
                $anomalie = $validationEngine->validateAllData();
                
                $message = "🎯 Smart Upload completato! $success_count file processati, $total_processed record elaborati";
                if ($total_associations > 0) {
                    $message .= ", $total_associations nuove associazioni create";
                }
                $message .= ". " . count($anomalie) . " anomalie rilevate.";
                $message_type = 'success';
                
                $upload_results = $processing_results;
                $smart_stats = $smart_parser->getStats();
            } else {
                $message = "Errore durante l'elaborazione Smart. Verificare i file CSV.";
                $message_type = 'danger';
            }
        } else {
            $message = "Nessun file caricato.";
            $message_type = 'warning';
        }
        
    } catch (Exception $e) {
        $message = "Errore: " . $e->getMessage();
        $message_type = 'danger';
        error_log($e->getMessage());
    }
}

// Verifica prerequisiti sistema
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Conta dipendenti master
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM master_dipendenti_fixed WHERE attivo = 1");
    $stmt->execute();
    $employees_count = $stmt->fetch()['count'];
    
    // Conta aziende
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM master_aziende WHERE attivo = 1");
    $stmt->execute();
    $companies_count = $stmt->fetch()['count'];
    
    // Conta associazioni pending
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM association_queue WHERE stato = 'pending'");
    $stmt->execute();
    $pending_associations = $stmt->fetch()['count'];
    
    $system_ready = ($employees_count >= 10 && $companies_count >= 5);
    
} catch (Exception $e) {
    $system_ready = false;
    $employees_count = 0;
    $companies_count = 0;
    $pending_associations = 0;
}

echo "<div class='container mt-4'>\n";

// System Status Header
echo "<div class='row mb-4'>\n";
echo "<div class='col-12'>\n";
echo "<div class='card smart-feature'>\n";
echo "<div class='card-body'>\n";
echo "<div class='row align-items-center'>\n";
echo "<div class='col-md-8'>\n";
echo "<h3 class='mb-2'><i class='fas fa-magic me-2'></i>Smart Upload System</h3>\n";
echo "<p class='mb-0'>Sistema intelligente con dipendenti fissi, aziende configurabili e associazioni dinamiche</p>\n";
echo "</div>\n";
echo "<div class='col-md-4 text-end'>\n";

if ($system_ready) {
    echo "<span class='badge bg-light text-dark fs-6'><i class='fas fa-check-circle text-success me-1'></i>Sistema Pronto</span>\n";
} else {
    echo "<span class='badge bg-warning text-dark fs-6'><i class='fas fa-exclamation-triangle me-1'></i>Setup Richiesto</span>\n";
}

echo "<br><small class='text-light'>Dipendenti: $employees_count | Aziende: $companies_count</small>\n";

if ($pending_associations > 0) {
    echo "<br><small><span class='badge bg-warning'>$pending_associations associazioni da verificare</span></small>\n";
}

echo "</div>\n";
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";

// Message display
if ($message) {
    echo "<div class='alert alert-$message_type alert-dismissible fade show'>\n";
    echo "$message\n";
    echo "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>\n";
    echo "</div>\n";
}

// Sistema non pronto warning
if (!$system_ready) {
    echo "<div class='alert alert-warning'>\n";
    echo "<h5><i class='fas fa-exclamation-triangle me-2'></i>Setup Sistema Richiesto</h5>\n";
    echo "<p>Prima di utilizzare Smart Upload, completa il setup:</p>\n";
    echo "<div class='d-flex gap-2 flex-wrap'>\n";
    if ($employees_count < 10) {
        echo "<a href='setup_master_schema.php' class='btn btn-warning btn-sm'><i class='fas fa-users me-1'></i>Setup Dipendenti ($employees_count/15)</a>\n";
    }
    if ($companies_count < 5) {
        echo "<a href='master_data_console.php' class='btn btn-warning btn-sm'><i class='fas fa-building me-1'></i>Setup Aziende ($companies_count)</a>\n";
    }
    echo "<a href='analyze_current_issues.php' class='btn btn-info btn-sm'><i class='fas fa-search me-1'></i>Verifica Database</a>\n";
    echo "</div>\n";
    echo "</div>\n";
}

echo "<div class='row'>\n";

// Upload Form
echo "<div class='col-lg-8'>\n";
echo "<div class='card'>\n";
echo "<div class='card-header bg-success text-white'>\n";
echo "<h5 class='mb-0'><i class='fas fa-cloud-upload-alt me-2'></i>Smart Upload - Sistema Completo</h5>\n";
echo "</div>\n";
echo "<div class='card-body'>\n";

// Smart Features Info
echo "<div class='alert alert-info'>\n";
echo "<h6><i class='fas fa-brain me-2'></i>Funzionalità Smart System</h6>\n";
echo "<div class='row'>\n";
echo "<div class='col-md-6'>\n";
echo "<ul class='mb-0 small'>\n";
echo "<li><strong>Dipendenti Fissi:</strong> Riconosce automaticamente i 15 dipendenti master</li>\n";
echo "<li><strong>Parsing Intelligente:</strong> Gestisce \"Franco/Matteo\" → 2 dipendenti</li>\n";
echo "<li><strong>Anti-Veicoli:</strong> Blocca \"Punto\", \"Fiesta\" come dipendenti</li>\n";
echo "</ul>\n";
echo "</div>\n";
echo "<div class='col-md-6'>\n";
echo "<ul class='mb-0 small'>\n";
echo "<li><strong>Aziende Smart:</strong> Associazione automatica con confidenza</li>\n";
echo "<li><strong>Progetti Dinamici:</strong> Auto-creazione progetti dal codice</li>\n";
echo "<li><strong>Anti-Duplicazione:</strong> Finestra temporale intelligente</li>\n";
echo "</ul>\n";
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";

// Upload Form
echo "<form method='POST' enctype='multipart/form-data' id='smartUploadForm'>\n";

echo "<div class='upload-zone mb-4' id='uploadZone'>\n";
echo "<i class='fas fa-cloud-upload-alt fa-3x text-muted mb-3'></i>\n";
echo "<h5>Trascina file CSV qui o clicca per selezionare</h5>\n";
echo "<p class='text-muted'>Supporta: attività, timbrature, calendario, teamviewer</p>\n";
echo "</div>\n";

echo "<div class='row'>\n";
echo "<div class='col-md-6 mb-3'>\n";
echo "<label for='attivita' class='form-label'><i class='fas fa-tasks me-1'></i>Attività</label>\n";
echo "<input type='file' class='form-control' id='attivita' name='attivita' accept='.csv'>\n";
echo "<div class='form-text'>Test nomi complessi e associazioni aziende</div>\n";
echo "</div>\n";

echo "<div class='col-md-6 mb-3'>\n";
echo "<label for='calendario' class='form-label'><i class='fas fa-calendar me-1'></i>Calendario</label>\n";
echo "<input type='file' class='form-control' id='calendario' name='calendario' accept='.csv'>\n";
echo "<div class='form-text'>Riconoscimento dipendenti fissi</div>\n";
echo "</div>\n";

echo "<div class='col-md-6 mb-3'>\n";
echo "<label for='timbrature' class='form-label'><i class='fas fa-clock me-1'></i>Timbrature</label>\n";
echo "<input type='file' class='form-control' id='timbrature' name='timbrature' accept='.csv'>\n";
echo "<div class='form-text'>apprilevazionepresenze-timbrature-totali-base.csv</div>\n";
echo "</div>\n";

echo "<div class='col-md-6 mb-3'>\n";
echo "<label for='teamviewer' class='form-label'><i class='fas fa-desktop me-1'></i>TeamViewer</label>\n";
echo "<input type='file' class='form-control' id='teamviewer' name='teamviewer' accept='.csv'>\n";
echo "<div class='form-text'>Auto-associazione clienti ad aziende</div>\n";
echo "</div>\n";
echo "</div>\n";

echo "<div class='d-grid gap-2'>\n";
echo "<button type='submit' class='btn btn-success btn-lg' id='uploadBtn'>\n";
echo "<i class='fas fa-magic me-2'></i>Avvia Smart Upload\n";
echo "</button>\n";
echo "</div>\n";

// Progress Bar
echo "<div class='progress-container mt-3'>\n";
echo "<div class='progress'>\n";
echo "<div class='progress-bar progress-bar-striped progress-bar-animated' role='progressbar' style='width: 0%'></div>\n";
echo "</div>\n";
echo "<small class='text-muted'>Elaborazione smart in corso...</small>\n";
echo "</div>\n";

echo "</form>\n";
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";

// Sidebar with System Info
echo "<div class='col-lg-4'>\n";

// System Status
echo "<div class='card mb-3'>\n";
echo "<div class='card-header'>\n";
echo "<h6 class='mb-0'><i class='fas fa-info-circle me-2'></i>System Status</h6>\n";
echo "</div>\n";
echo "<div class='card-body'>\n";

$status_items = [
    ['label' => 'Dipendenti Master', 'count' => $employees_count, 'target' => 15, 'icon' => 'users'],
    ['label' => 'Aziende Configurate', 'count' => $companies_count, 'target' => 5, 'icon' => 'building'],
    ['label' => 'Associazioni Pending', 'count' => $pending_associations, 'target' => 0, 'icon' => 'link', 'reverse' => true]
];

foreach ($status_items as $item) {
    $percentage = $item['target'] > 0 ? min(100, ($item['count'] / $item['target']) * 100) : 0;
    if (isset($item['reverse']) && $item['reverse']) {
        $color = $item['count'] == 0 ? 'success' : 'warning';
    } else {
        $color = $percentage >= 80 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');
    }
    
    echo "<div class='d-flex justify-content-between align-items-center mb-2'>\n";
    echo "<div>\n";
    echo "<i class='fas fa-{$item['icon']} me-2'></i>{$item['label']}\n";
    echo "</div>\n";
    echo "<span class='badge bg-$color'>{$item['count']}</span>\n";
    echo "</div>\n";
}

echo "</div>\n";
echo "</div>\n";

// Quick Actions
echo "<div class='card mb-3'>\n";
echo "<div class='card-header'>\n";
echo "<h6 class='mb-0'><i class='fas fa-bolt me-2'></i>Azioni Rapide</h6>\n";
echo "</div>\n";
echo "<div class='card-body'>\n";
echo "<div class='d-grid gap-2'>\n";
echo "<a href='master_data_console.php' class='btn btn-outline-primary btn-sm'>\n";
echo "<i class='fas fa-database me-2'></i>Gestisci Master Data\n";
echo "</a>\n";
echo "<a href='test_smart_parser.php' class='btn btn-outline-info btn-sm'>\n";
echo "<i class='fas fa-flask me-2'></i>Test Smart Parser\n";
echo "</a>\n";
if ($pending_associations > 0) {
    echo "<a href='master_data_console.php#associations' class='btn btn-outline-warning btn-sm'>\n";
    echo "<i class='fas fa-link me-2'></i>Gestisci Associazioni ($pending_associations)\n";
    echo "</a>\n";
}
echo "<a href='analyze_current_issues.php' class='btn btn-outline-secondary btn-sm'>\n";
echo "<i class='fas fa-search me-2'></i>Analizza Database\n";
echo "</a>\n";
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";

// Last Upload Stats
if (!empty($smart_stats)) {
    echo "<div class='card'>\n";
    echo "<div class='card-header'>\n";
    echo "<h6 class='mb-0'><i class='fas fa-chart-bar me-2'></i>Statistiche Ultimo Upload</h6>\n";
    echo "</div>\n";
    echo "<div class='card-body'>\n";
    
    $stats_display = [
        ['label' => 'Record Processati', 'value' => $smart_stats['processed'] ?? 0, 'icon' => 'file-alt'],
        ['label' => 'Record Inseriti', 'value' => $smart_stats['inserted'] ?? 0, 'icon' => 'plus'],
        ['label' => 'Duplicati Rilevati', 'value' => $smart_stats['duplicates_detected'] ?? 0, 'icon' => 'copy'],
        ['label' => 'Associazioni Create', 'value' => $smart_stats['associations_created'] ?? 0, 'icon' => 'link']
    ];
    
    foreach ($stats_display as $stat) {
        echo "<div class='d-flex justify-content-between align-items-center mb-2'>\n";
        echo "<div>\n";
        echo "<i class='fas fa-{$stat['icon']} me-2'></i>{$stat['label']}\n";
        echo "</div>\n";
        echo "<span class='badge bg-info'>{$stat['value']}</span>\n";
        echo "</div>\n";
    }
    
    echo "</div>\n";
    echo "</div>\n";
}

echo "</div>\n"; // Close col-lg-4
echo "</div>\n"; // Close row

// Results Display
if (!empty($upload_results)) {
    echo "<div class='row mt-4'>\n";
    echo "<div class='col-12'>\n";
    echo "<h4><i class='fas fa-chart-line me-2'></i>Risultati Smart Upload</h4>\n";
    
    echo "<div class='row'>\n";
    foreach ($upload_results as $type => $result) {
        echo "<div class='col-md-6 col-lg-4 mb-3'>\n";
        echo "<div class='card result-card'>\n";
        
        $header_class = (isset($result['success']) && $result['success']) ? 'bg-success' : 'bg-danger';
        $icon = isset($result['success']) && $result['success'] ? 'check' : 'times';
        
        echo "<div class='card-header $header_class text-white'>\n";
        echo "<div class='d-flex justify-content-between align-items-center'>\n";
        echo "<div>\n";
        echo "<i class='fas fa-$icon me-2'></i>" . ucfirst($type) . "\n";
        echo "</div>\n";
        echo "<small>{$result['filename']}</small>\n";
        echo "</div>\n";
        echo "</div>\n";
        
        echo "<div class='card-body'>\n";
        if (isset($result['success']) && $result['success']) {
            $stats = $result['stats'] ?? [];
            echo "<div class='row text-center'>\n";
            echo "<div class='col-6'>\n";
            echo "<div class='h5 mb-0 text-success'>" . ($stats['inserted'] + $stats['updated']) . "</div>\n";
            echo "<small class='text-muted'>Record</small>\n";
            echo "</div>\n";
            echo "<div class='col-6'>\n";
            echo "<div class='h5 mb-0 text-warning'>" . ($stats['duplicates_detected'] ?? 0) . "</div>\n";
            echo "<small class='text-muted'>Duplicati</small>\n";
            echo "</div>\n";
            echo "</div>\n";
            
            if (!empty($result['warnings'])) {
                echo "<hr>\n";
                echo "<small class='text-muted'><i class='fas fa-exclamation-triangle me-1'></i>" . count($result['warnings']) . " avvisi</small>\n";
            }
        } else {
            echo "<div class='alert alert-danger mb-0'>\n";
            echo "<small>" . htmlspecialchars($result['error'] ?? 'Errore sconosciuto') . "</small>\n";
            echo "</div>\n";
        }
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
    }
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
}

echo "</div>\n"; // Close container

// JavaScript
echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>\n";
echo "<script>\n";

// Form submit handling
echo "document.getElementById('smartUploadForm').addEventListener('submit', function(e) {\n";
echo "    const button = document.getElementById('uploadBtn');\n";
echo "    const progressContainer = document.querySelector('.progress-container');\n";
echo "    const progressBar = progressContainer.querySelector('.progress-bar');\n";
echo "    \n";
echo "    button.disabled = true;\n";
echo "    button.innerHTML = '<i class=\"fas fa-spinner fa-spin me-2\"></i>Smart Processing...';\n";
echo "    progressContainer.style.display = 'block';\n";
echo "    \n";
echo "    // Simula progress\n";
echo "    let progress = 0;\n";
echo "    const interval = setInterval(() => {\n";
echo "        progress += Math.random() * 15;\n";
echo "        if (progress > 90) progress = 90;\n";
echo "        progressBar.style.width = progress + '%';\n";
echo "    }, 300);\n";
echo "    \n";
echo "    setTimeout(() => clearInterval(interval), 1000);\n";
echo "});\n";

// Drag & Drop
echo "const uploadZone = document.getElementById('uploadZone');\n";
echo "const fileInputs = document.querySelectorAll('input[type=\"file\"]');\n";

echo "uploadZone.addEventListener('dragover', (e) => {\n";
echo "    e.preventDefault();\n";
echo "    uploadZone.classList.add('dragover');\n";
echo "});\n";

echo "uploadZone.addEventListener('dragleave', () => {\n";
echo "    uploadZone.classList.remove('dragover');\n";
echo "});\n";

echo "uploadZone.addEventListener('drop', (e) => {\n";
echo "    e.preventDefault();\n";
echo "    uploadZone.classList.remove('dragover');\n";
echo "    \n";
echo "    const files = e.dataTransfer.files;\n";
echo "    if (files.length > 0) {\n";
echo "        // Assegna primo file al primo input disponibile\n";
echo "        for (let input of fileInputs) {\n";
echo "            if (!input.files.length) {\n";
echo "                input.files = files;\n";
echo "                break;\n";
echo "            }\n";
echo "        }\n";
echo "    }\n";
echo "});\n";

echo "uploadZone.addEventListener('click', () => {\n";
echo "    fileInputs[0].click();\n";
echo "});\n";

echo "</script>\n";
echo "</body>\n</html>\n";
?>