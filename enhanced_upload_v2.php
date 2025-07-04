<?php
session_start();
require_once 'config/Database.php';
require_once 'classes/EnhancedCsvParser.php';
require_once 'classes/UploadManager.php';
require_once 'classes/KpiCalculator.php';
require_once 'classes/ValidationEngine.php';

$message = '';
$message_type = '';
$upload_results = [];
$upload_manager = new UploadManager();

// Gestione azioni
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'remove_file':
                $filename = $_POST['filename'] ?? '';
                if ($upload_manager->removeSessionFile($filename)) {
                    $message = "File $filename rimosso dalla sessione";
                    $message_type = 'success';
                } else {
                    $message = "Impossibile rimuovere file $filename";
                    $message_type = 'danger';
                }
                break;
                
            case 'upload_files':
                // Processo upload normale
                break;
        }
    }
    
    // Upload e processing file
    if (!isset($_POST['action']) || $_POST['action'] === 'upload_files') {
        try {
            $database = new Database();
            
            if (!$database->databaseExists()) {
                throw new Exception("Database non trovato. Eseguire prima il setup.");
            }
            
            // Processa upload con UploadManager
            $upload_result = $upload_manager->processFileUploads($_FILES);
            
            if ($upload_result['success'] && !empty($upload_result['uploaded_files'])) {
                // Usa Enhanced Parser per elaborazione
                $parser = new EnhancedCsvParser();
                $processing_results = $parser->processAllFiles($upload_result['session_directory']);
                
                $success_count = 0;
                $total_processed = 0;
                
                foreach ($processing_results as $type => $result) {
                    if (isset($result['success']) && $result['success']) {
                        $success_count++;
                        $total_processed += $result['stats']['inserted'] + $result['stats']['updated'];
                    }
                }
                
                // Calcola KPI e validazioni
                if ($success_count > 0) {
                    $kpiCalculator = new KpiCalculator();
                    $kpiCalculator->calculateAllKpis();
                    
                    $validationEngine = new ValidationEngine();
                    $anomalie = $validationEngine->validateAllData();
                    
                    // Ottieni statistiche deduplicazione
                    $dedup_stats = $parser->getDeduplicationStats();
                    $dedup_info = '';
                    if ($dedup_stats) {
                        $dedup_info = " Duplicati rilevati: {$dedup_stats['duplicates_detected']}, marcati: {$dedup_stats['duplicates_marked']}.";
                    }
                    
                    $message = "Upload Enhanced completato! $success_count file processati, $total_processed record elaborati.$dedup_info " . count($anomalie) . " anomalie rilevate.";
                    $message_type = 'success';
                    
                    $upload_results = $processing_results;
                } else {
                    $message = "Errore durante l'elaborazione dei file. Verificare il formato dei CSV.";
                    $message_type = 'danger';
                }
            } elseif (!empty($upload_result['errors'])) {
                $message = "Errori upload: " . implode(', ', $upload_result['errors']);
                $message_type = 'danger';
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
}

// Recupera informazioni sessione esistente
$session_info = $upload_manager->getSessionInfo();
$session_files = $upload_manager->listSessionFiles();
$upload_stats = $upload_manager->getUploadStats();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Upload v2 - Employee Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .file-preview { max-height: 200px; overflow-y: auto; }
        .session-info { background: #f8f9fa; border-left: 4px solid #007bff; }
        .upload-progress { display: none; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-cloud-upload-alt me-2"></i>Employee Analytics - Enhanced Upload v2
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="enhanced_upload.php">
                    <i class="fas fa-upload me-1"></i>Upload v1
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Session Info -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card session-info">
                    <div class="card-body">
                        <h6><i class="fas fa-info-circle me-2"></i>Informazioni Sessione</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <small><strong>Session ID:</strong> <?= htmlspecialchars($upload_stats['session_id']) ?></small>
                            </div>
                            <div class="col-md-3">
                                <small><strong>File in Sessione:</strong> <?= $upload_stats['files_in_session'] ?></small>
                            </div>
                            <div class="col-md-3">
                                <small><strong>Dimensione:</strong> <?= $upload_stats['session_size_mb'] ?> MB</small>
                            </div>
                            <div class="col-md-3">
                                <small><strong>Directory:</strong> <code><?= basename($upload_stats['session_directory']) ?></code></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Upload Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Enhanced Upload v2 - Con Session Management
                        </h4>
                        <small class="text-muted">Sistema di upload avanzato con gestione sessioni, preview e anti-duplicazione</small>
                    </div>
                    <div class="card-body">
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-star me-2"></i>Funzionalità Enhanced Upload v2</h6>
                            <ul class="mb-0 small">
                                <li><strong>Session Management:</strong> File persistenti tra reload pagina</li>
                                <li><strong>File Preview:</strong> Anteprima header e prime righe CSV</li>
                                <li><strong>Progress Tracking:</strong> Monitoraggio upload e processing</li>
                                <li><strong>Anti-duplicazione:</strong> Sistema avanzato prevenzione duplicati</li>
                                <li><strong>Cleanup Automatico:</strong> Rimozione file vecchi > 7 giorni</li>
                            </ul>
                        </div>
                        
                        <!-- Upload Form -->
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="action" value="upload_files">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="attivita" class="form-label">🎯 Attività (Test Parsing)</label>
                                    <input type="file" class="form-control" id="attivita" name="attivita" accept=".csv">
                                    <div class="form-text">Test "Franco Fiorellino/Matteo Signo" e anti-duplicazione</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="calendario" class="form-label">📅 Calendario</label>
                                    <input type="file" class="form-control" id="calendario" name="calendario" accept=".csv">
                                    <div class="form-text">Test ricerca master dipendenti</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="timbrature" class="form-label">🕐 Timbrature</label>
                                    <input type="file" class="form-control" id="timbrature" name="timbrature" accept=".csv">
                                    <div class="form-text">apprilevazionepresenze-timbrature-totali-base.csv</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="richieste" class="form-label">📋 Richieste</label>
                                    <input type="file" class="form-control" id="richieste" name="richieste" accept=".csv">
                                    <div class="form-text">apprilevazionepresenze-richieste.csv</div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-cloud-upload-alt me-2"></i>Carica e Elabora con Enhanced v2
                                </button>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="upload-progress">
                                <div class="progress mb-2">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Upload in corso...</small>
                            </div>
                        </form>
                        
                        <!-- Processing Results -->
                        <?php if (!empty($upload_results)): ?>
                            <hr class="my-4">
                            <h5>🎯 Risultati Enhanced Processing v2</h5>
                            
                            <div class="accordion" id="resultsAccordion">
                                <?php foreach ($upload_results as $type => $result): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= ucfirst($type) ?>">
                                                <?= ucfirst($type) ?> 
                                                <?php if (isset($result['success'])): ?>
                                                    <span class="badge <?= $result['success'] ? 'bg-success' : 'bg-danger' ?> ms-2">
                                                        <?= $result['success'] ? 'v2 OK' : 'ERROR' ?>
                                                    </span>
                                                    <?php if ($result['success'] && isset($result['stats'])): ?>
                                                        <span class="badge bg-info ms-1">
                                                            <?= $result['stats']['inserted'] + $result['stats']['updated'] ?> record
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </button>
                                        </h2>
                                        <div id="collapse<?= ucfirst($type) ?>" class="accordion-collapse collapse">
                                            <div class="accordion-body">
                                                <?php if (isset($result['success']) && $result['success']): ?>
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <strong>Processati:</strong> <?= $result['stats']['processed'] ?>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <strong>Inseriti:</strong> <?= $result['stats']['inserted'] ?>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <strong>Aggiornati:</strong> <?= $result['stats']['updated'] ?>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <strong>Saltati:</strong> <?= $result['stats']['skipped'] ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($result['warnings'])): ?>
                                                        <div class="mt-3">
                                                            <h6>⚠️ Avvisi:</h6>
                                                            <ul class="list-unstyled">
                                                                <?php foreach ($result['warnings'] as $warning): ?>
                                                                    <li><i class="fas fa-exclamation-triangle text-warning me-2"></i><?= htmlspecialchars($warning) ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                <?php else: ?>
                                                    <div class="alert alert-danger">
                                                        <strong>Errore:</strong> <?= htmlspecialchars($result['error'] ?? 'Errore sconosciuto') ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
            
            <!-- Session Files Sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-files me-2"></i>File in Sessione (<?= count($session_files) ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        
                        <?php if (empty($session_files)): ?>
                            <p class="text-muted text-center">
                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                Nessun file caricato in questa sessione
                            </p>
                        <?php else: ?>
                            <?php foreach ($session_files as $file): ?>
                                <div class="card mb-2">
                                    <div class="card-body p-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <i class="fas fa-file-csv me-1"></i>
                                                    <?= htmlspecialchars($file['filename']) ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?= round($file['size'] / 1024, 1) ?> KB - 
                                                    <?= date('H:i:s', $file['modified']) ?>
                                                </small>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="showFilePreview('<?= addslashes($file['filename']) ?>')">
                                                            <i class="fas fa-eye me-2"></i>Preview
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="remove_file">
                                                            <input type="hidden" name="filename" value="<?= htmlspecialchars($file['filename']) ?>">
                                                            <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Rimuovere file?')">
                                                                <i class="fas fa-trash me-2"></i>Rimuovi
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <!-- File Analysis -->
                                        <?php if (isset($file['analysis']) && !isset($file['analysis']['error'])): ?>
                                            <div class="mt-2">
                                                <small class="d-block">
                                                    <strong>Righe:</strong> <?= $file['analysis']['total_rows'] ?> |
                                                    <strong>Colonne:</strong> <?= $file['analysis']['columns_count'] ?> |
                                                    <strong>Sep:</strong> "<?= htmlspecialchars($file['analysis']['separator']) ?>"
                                                </small>
                                                
                                                <!-- Preview toggle -->
                                                <div class="collapse" id="preview<?= md5($file['filename']) ?>">
                                                    <div class="file-preview mt-2 p-2 bg-light border rounded">
                                                        <small>
                                                            <strong>Header:</strong><br>
                                                            <?= htmlspecialchars(implode(', ', array_slice($file['analysis']['header'], 0, 5))) ?>
                                                            <?= count($file['analysis']['header']) > 5 ? '...' : '' ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <button class="btn btn-sm btn-outline-info mt-1" type="button" data-bs-toggle="collapse" data-bs-target="#preview<?= md5($file['filename']) ?>">
                                                    <i class="fas fa-eye"></i> Preview
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                        
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-tools me-2"></i>Azioni Rapide
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="cleanup_duplicates.php" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-broom me-2"></i>Cleanup Duplicati
                            </a>
                            <a href="test_deduplication_engine.php" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-microscope me-2"></i>Test Deduplication
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-chart-bar me-2"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced upload con progress tracking
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            const progressDiv = document.querySelector('.upload-progress');
            const progressBar = progressDiv.querySelector('.progress-bar');
            
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Elaborazione v2 in corso...';
            progressDiv.style.display = 'block';
            
            // Simula progress
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress > 90) progress = 90;
                progressBar.style.width = progress + '%';
            }, 500);
            
            // Stop simulation after form submission
            setTimeout(() => clearInterval(interval), 1000);
        });
        
        function showFilePreview(filename) {
            // Toggle preview for specific file
            const previewId = 'preview' + btoa(filename).replace(/[^a-zA-Z0-9]/g, '');
            const element = document.getElementById(previewId);
            if (element) {
                new bootstrap.Collapse(element, { toggle: true });
            }
        }
    </script>
</body>
</html>