<?php
session_start();
require_once 'classes/EnhancedCsvParser.php'; // Usa Enhanced al posto di CsvParser normale
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
            $parser = new EnhancedCsvParser(); // *** ENHANCED PARSER ***
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
                
                $message = "Upload completato con Enhanced Parser! $success_count file processati, $total_processed record elaborati. " . count($anomalie) . " anomalie rilevate.";
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
    <title>Enhanced Upload CSV - Employee Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-chart-line me-2"></i>Employee Analytics - Enhanced
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="upload.php">
                    <i class="fas fa-upload me-1"></i>Upload Standard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-magic me-2"></i>Enhanced Upload CSV - Con Parsing Avanzato
                        </h4>
                        <small>Gestisce nomi multipli come "Franco Fiorellino/Matteo Signo" e integrazione Master Tables</small>
                    </div>
                    <div class="card-body">
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-star me-2"></i>Funzionalità Enhanced Parser</h6>
                            <ul class="mb-0">
                                <li><strong>Parsing Multiplo:</strong> Gestisce separatori /, ,, ;, &, +, "e", "with"</li>
                                <li><strong>Master Tables:</strong> Ricerca intelligente in dipendenti e alias</li>
                                <li><strong>Anti-Veicoli:</strong> Riconosce "Punto", "Fiesta" come veicoli</li>
                                <li><strong>Auto-linking:</strong> Collega automaticamente ai master dipendenti</li>
                            </ul>
                        </div>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Upload Form -->
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="attivita" class="form-label">🎯 Attività (Test Parsing Nomi)</label>
                                    <input type="file" class="form-control" id="attivita" name="attivita" accept=".csv">
                                    <div class="form-text">Test per parsing "Franco Fiorellino/Matteo Signo"</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="calendario" class="form-label">📅 Calendario (Test Nomi Master)</label>
                                    <input type="file" class="form-control" id="calendario" name="calendario" accept=".csv">
                                    <div class="form-text">Test per ricerca in master dipendenti</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="timbrature" class="form-label">🕐 Timbrature</label>
                                    <input type="file" class="form-control" id="timbrature" name="timbrature" accept=".csv">
                                    <div class="form-text">apprilevazionepresenze-timbrature-totali-base.csv</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="richieste" class="form-label">📋 Richieste Ferie/Permessi</label>
                                    <input type="file" class="form-control" id="richieste" name="richieste" accept=".csv">
                                    <div class="form-text">apprilevazionepresenze-richieste.csv</div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-magic me-2"></i>Carica con Enhanced Parser
                                </button>
                            </div>
                        </form>
                        
                        <!-- Results Display -->
                        <?php if (!empty($upload_results)): ?>
                            <hr class="my-4">
                            <h5>🎯 Risultati Enhanced Processing</h5>
                            
                            <div class="accordion" id="resultsAccordion">
                                <?php foreach ($upload_results as $type => $result): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= ucfirst($type) ?>">
                                                <?= ucfirst($type) ?> 
                                                <?php if (isset($result['success'])): ?>
                                                    <span class="badge <?= $result['success'] ? 'bg-success' : 'bg-danger' ?> ms-2">
                                                        <?= $result['success'] ? 'ENHANCED OK' : 'ERROR' ?>
                                                    </span>
                                                    <?php if ($result['success'] && isset($result['stats'])): ?>
                                                        <span class="badge bg-info ms-1">
                                                            <?= $result['stats']['inserted'] + $result['stats']['updated'] ?> record
                                                        </span>
                                                    <?php endif; ?>
                            // Enhanced: Mostra statistiche deduplicazione
                            if (isset($parser) && method_exists($parser, 'getDeduplicationStats')) {
                                $dedup_stats = $parser->getDeduplicationStats();
                                if (!empty($dedup_stats)) {
                                    echo "<div class='mt-3'>\n";
                                    echo "<h6>🔄 Statistiche Anti-duplicazione:</h6>\n";
                                    echo "<div class='row'>\n";
                                    echo "<div class='col-md-3'><small><strong>Duplicati Rilevati:</strong> {$dedup_stats['duplicates_detected']}</small></div>\n";
                                    echo "<div class='col-md-3'><small><strong>Duplicati Merged:</strong> {$dedup_stats['duplicates_merged']}</small></div>\n";
                                    echo "<div class='col-md-3'><small><strong>Duplicati Marcati:</strong> {$dedup_stats['duplicates_marked']}</small></div>\n";
                                    echo "<div class='col-md-3'><small><strong>Inserimenti Unici:</strong> {$dedup_stats['unique_inserted']}</small></div>\n";
                                    echo "</div>\n";
                                    echo "</div>\n";
                                }
                            }
                                                <?php endif; ?>
                            // Enhanced: Mostra statistiche deduplicazione
                            if (isset($parser) && method_exists($parser, 'getDeduplicationStats')) {
                                $dedup_stats = $parser->getDeduplicationStats();
                                if (!empty($dedup_stats)) {
                                    echo "<div class='mt-3'>\n";
                                    echo "<h6>🔄 Statistiche Anti-duplicazione:</h6>\n";
                                    echo "<div class='row'>\n";
                                    echo "<div class='col-md-3'><small><strong>Duplicati Rilevati:</strong> {$dedup_stats['duplicates_detected']}</small></div>\n";
                                    echo "<div class='col-md-3'><small><strong>Duplicati Merged:</strong> {$dedup_stats['duplicates_merged']}</small></div>\n";
                                    echo "<div class='col-md-3'><small><strong>Duplicati Marcati:</strong> {$dedup_stats['duplicates_marked']}</small></div>\n";
                                    echo "<div class='col-md-3'><small><strong>Inserimenti Unici:</strong> {$dedup_stats['unique_inserted']}</small></div>\n";
                                    echo "</div>\n";
                                    echo "</div>\n";
                                }
                            }
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
                                                            <h6>⚠️ Avvisi Enhanced:</h6>
                                                            <ul class="list-unstyled">
                                                                <?php foreach ($result['warnings'] as $warning): ?>
                                                                    <li><i class="fas fa-exclamation-triangle text-warning me-2"></i><?= htmlspecialchars($warning) ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                            // Enhanced: Mostra statistiche deduplicazione
                            if (isset($parser) && method_exists($parser, 'getDeduplicationStats')) {
                                $dedup_stats = $parser->getDeduplicationStats();
                                if (!empty($dedup_stats)) {
                                    echo "<div class='mt-3'>\n";
                                    echo "<h6>🔄 Statistiche Anti-duplicazione:</h6>\n";
                                    echo "<div class='row'>\n";
                                    echo "<div class='col-md-3'><small><strong>Duplicati Rilevati:</strong> {$dedup_stats['duplicates_detected']}</small></div>\n";
                                    echo "<div class='col-md-3'><small><strong>Duplicati Merged:</strong> {$dedup_stats['duplicates_merged']}</small></div>\n";
                                    echo "<div class='col-md-3'><small><strong>Duplicati Marcati:</strong> {$dedup_stats['duplicates_marked']}</small></div>\n";
                                    echo "<div class='col-md-3'><small><strong>Inserimenti Unici:</strong> {$dedup_stats['unique_inserted']}</small></div>\n";
                                    echo "</div>\n";
                                    echo "</div>\n";
                                }
                            }
                                                    
                                                <?php else: ?>
                                                    <div class="alert alert-danger">
                                                        <strong>Errore Enhanced:</strong> <?= htmlspecialchars($result['error'] ?? 'Errore sconosciuto') ?>
                                                    </div>
                                                <?php endif; ?>
                            // Enhanced: Mostra statistiche deduplicazione
                            if (isset($parser) && method_exists($parser, 'getDeduplicationStats')) {
                                $dedup_stats = $parser->getDeduplicationStats();
                                if (!empty($dedup_stats)) {
                                    echo "<div class='mt-3'>\n";
                                    echo "<h6>🔄 Statistiche Anti-duplicazione:</h6>\n";
                                    echo "<div class='row'>\n";
                                    echo "<div class='col-md-3'><small><strong>Duplicati Rilevati:</strong> {$dedup_stats['duplicates_detected']}</small></div>\n";
                                    echo "<div class='col-md-3'><small><strong>Duplicati Merged:</strong> {$dedup_stats['duplicates_merged']}</small></div>\n";
                                    echo "<div class='col-md-3'><small><strong>Duplicati Marcati:</strong> {$dedup_stats['duplicates_marked']}</small></div>\n";
                                    echo "<div class='col-md-3'><small><strong>Inserimenti Unici:</strong> {$dedup_stats['unique_inserted']}</small></div>\n";
                                    echo "</div>\n";
                                    echo "</div>\n";
                                }
                            }
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
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Elaborazione Enhanced in corso...';
        });
    </script>
</body>
</html>