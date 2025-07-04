<?php
session_start();
require_once 'classes/CsvParser.php';
require_once 'classes/KpiCalculator.php';
require_once 'classes/ValidationEngine.php';

$message = '';
$message_type = '';
$upload_results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        
        if (!$database->databaseExists()) {
            throw new Exception("Database non trovato. Eseguire prima il setup.");
        }
        $upload_dir = 'uploads/' . date('Y-m-d_H-i-s') . '/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $uploaded_files = [];
        
        foreach ($_FILES as $key => $file) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $filename = basename($file['name']);
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $uploaded_files[] = $target_path;
                }
            }
        }
        
        if (!empty($uploaded_files)) {
            $parser = new CsvParser();
            $upload_results = $parser->processAllFiles($upload_dir);
            
            $success_count = 0;
            $total_processed = 0;
            
            foreach ($upload_results as $type => $result) {
                if (isset($result['success']) && $result['success']) {
                    $success_count++;
                    $total_processed += $result['stats']['inserted'] + $result['stats']['updated'];
                }
            }
            
            if ($success_count > 0) {
                $kpiCalculator = new KpiCalculator();
                $kpiCalculator->calculateAllKpis();
                
                $validationEngine = new ValidationEngine();
                $anomalie = $validationEngine->validateAllData();
                
                $message = "Upload completato con successo! $success_count file processati, $total_processed record elaborati. " . count($anomalie) . " anomalie rilevate.";
                $message_type = 'success';
            } else {
                $message = "Errore durante l'elaborazione dei file. Verificare il formato dei CSV.";
                $message_type = 'danger';
            }
        } else {
            $message = "Errore durante l'upload dei file.";
            $message_type = 'danger';
        }
        
    } catch (Exception $e) {
        $message = "Errore: " . $e->getMessage();
        $message_type = 'danger';
        error_log($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload CSV - Employee Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-chart-line me-2"></i>Employee Analytics
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-upload me-2"></i>Carica File CSV
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        // Quick check if system is ready
                        try {
                            $database = new Database();
                            if (!$database->databaseExists()) {
                                echo '<div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Setup richiesto:</strong> Il database non è ancora configurato.
                                    <a href="setup.php" class="btn btn-warning btn-sm ms-2">Vai al Setup</a>
                                </div>';
                            }
                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Errore sistema:</strong> ' . htmlspecialchars($e->getMessage()) . '
                            </div>';
                        }
                        ?>

                        <!-- Upload Form -->
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="timbrature" class="form-label">Timbrature</label>
                                    <input type="file" class="form-control" id="timbrature" name="timbrature" accept=".csv">
                                    <div class="form-text">apprilevazionepresenze-timbrature-totali-base.csv</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="richieste" class="form-label">Richieste Ferie/Permessi</label>
                                    <input type="file" class="form-control" id="richieste" name="richieste" accept=".csv">
                                    <div class="form-text">apprilevazionepresenze-richieste.csv</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="attivita" class="form-label">Attività</label>
                                    <input type="file" class="form-control" id="attivita" name="attivita" accept=".csv">
                                    <div class="form-text">attivita.csv</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="calendario" class="form-label">Calendario</label>
                                    <input type="file" class="form-control" id="calendario" name="calendario" accept=".csv">
                                    <div class="form-text">calendario.csv</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="progetti" class="form-label">Progetti</label>
                                    <input type="file" class="form-control" id="progetti" name="progetti" accept=".csv">
                                    <div class="form-text">progetti.csv</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="registro_auto" class="form-label">Registro Auto</label>
                                    <input type="file" class="form-control" id="registro_auto" name="registro_auto" accept=".csv">
                                    <div class="form-text">registro_auto.csv</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="teamviewer_bait" class="form-label">TeamViewer BAIT</label>
                                    <input type="file" class="form-control" id="teamviewer_bait" name="teamviewer_bait" accept=".csv">
                                    <div class="form-text">teamviewer_bait.csv</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="teamviewer_gruppo" class="form-label">TeamViewer Gruppo</label>
                                    <input type="file" class="form-control" id="teamviewer_gruppo" name="teamviewer_gruppo" accept=".csv">
                                    <div class="form-text">teamviewer_gruppo.csv</div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-upload me-2"></i>Carica e Elabora CSV
                                </button>
                            </div>
                        </form>
                        
                        <!-- Batch Upload -->
                        <hr class="my-4">
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Upload Rapido</h6>
                            <p class="mb-2">Puoi anche copiare tutti i file CSV nella cartella:</p>
                            <code><?= realpath('.') ?>/file-orig-300625/</code>
                            <p class="mt-2 mb-0">
                                <a href="?batch_process=1" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-magic me-1"></i>Elabora File dalla Cartella
                                </a>
                            </p>
                        </div>

                        <!-- Results Display -->
                        <?php if (!empty($upload_results)): ?>
                            <hr class="my-4">
                            <h5>Risultati Elaborazione</h5>
                            
                            <div class="accordion" id="resultsAccordion">
                                <?php foreach ($upload_results as $type => $result): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= ucfirst($type) ?>">
                                                <?= ucfirst($type) ?> 
                                                <?php if (isset($result['success'])): ?>
                                                    <span class="badge <?= $result['success'] ? 'bg-success' : 'bg-danger' ?> ms-2">
                                                        <?= $result['success'] ? 'OK' : 'ERROR' ?>
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
                                                            <h6>Avvisi:</h6>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.getElementById('uploadForm').addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Elaborazione in corso...';
        });
    </script>
</body>
</html>

<?php
if (isset($_GET['batch_process'])) {
    try {
        $database = new Database();
        
        if (!$database->databaseExists()) {
            echo "<script>alert('Database non trovato. Eseguire prima il setup.'); window.location.href = 'setup.php';</script>";
            exit;
        }
        
        $parser = new CsvParser();
        $results = $parser->processAllFiles('file-orig-300625');
        
        $kpiCalculator = new KpiCalculator();
        $kpiCalculator->calculateAllKpis();
        
        $validationEngine = new ValidationEngine();
        $anomalie = $validationEngine->validateAllData();
        
        echo "<script>
            alert('Batch processing completato! " . count($anomalie) . " anomalie rilevate.');
            window.location.href = 'index.php';
        </script>";
        
    } catch (Exception $e) {
        echo "<script>alert('Errore batch processing: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>