<?php
require_once 'config/Database.php';

echo "<h2>🔗 Integrazione Enhanced CSV Parser</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // 1. Backup del CsvParser originale
    echo "<h3>📦 Backup CsvParser Originale</h3>\n";
    
    $original_file = 'classes/CsvParser.php';
    $backup_file = 'classes/CsvParser_backup_' . date('Y-m-d_H-i-s') . '.php';
    
    if (file_exists($original_file)) {
        if (copy($original_file, $backup_file)) {
            echo "<p style='color: green;'>✅ Backup creato: $backup_file</p>\n";
        } else {
            throw new Exception("Impossibile creare backup del CsvParser originale");
        }
    } else {
        echo "<p style='color: orange;'>⚠️ File CsvParser originale non trovato</p>\n";
    }
    
    // 2. Sostituzione temporanea per test
    echo "<h3>🔄 Configurazione Test Enhanced Parser</h3>\n";
    
    // Crea un file di test che usa EnhancedCsvParser
    $test_upload_content = '<?php
session_start();
require_once \'classes/EnhancedCsvParser.php\'; // Usa Enhanced al posto di CsvParser normale
require_once \'classes/KpiCalculator.php\';
require_once \'classes/ValidationEngine.php\';

$message = \'\';
$message_type = \'\';
$upload_results = [];

if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {
    try {
        $database = new Database();
        
        if (!$database->databaseExists()) {
            throw new Exception("Database non trovato. Eseguire prima il setup.");
        }
        $upload_dir = \'uploads/\' . date(\'Y-m-d_H-i-s\') . \'/\';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $uploaded_files = [];
        
        foreach ($_FILES as $key => $file) {
            if ($file[\'error\'] === UPLOAD_ERR_OK) {
                $filename = basename($file[\'name\']);
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file[\'tmp_name\'], $target_path)) {
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
                if (isset($result[\'success\']) && $result[\'success\']) {
                    $success_count++;
                    $total_processed += $result[\'stats\'][\'inserted\'] + $result[\'stats\'][\'updated\'];
                }
            }
            
            if ($success_count > 0) {
                $kpiCalculator = new KpiCalculator();
                $kpiCalculator->calculateAllKpis();
                
                $validationEngine = new ValidationEngine();
                $anomalie = $validationEngine->validateAllData();
                
                $message = "Upload completato con Enhanced Parser! $success_count file processati, $total_processed record elaborati. " . count($anomalie) . " anomalie rilevate.";
                $message_type = \'success\';
            } else {
                $message = "Errore durante l\'elaborazione dei file. Verificare il formato dei CSV.";
                $message_type = \'danger\';
            }
        } else {
            $message = "Errore durante l\'upload dei file.";
            $message_type = \'danger\';
        }
        
    } catch (Exception $e) {
        $message = "Errore: " . $e->getMessage();
        $message_type = \'danger\';
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
                                                <?php if (isset($result[\'success\'])): ?>
                                                    <span class="badge <?= $result[\'success\'] ? \'bg-success\' : \'bg-danger\' ?> ms-2">
                                                        <?= $result[\'success\'] ? \'ENHANCED OK\' : \'ERROR\' ?>
                                                    </span>
                                                    <?php if ($result[\'success\'] && isset($result[\'stats\'])): ?>
                                                        <span class="badge bg-info ms-1">
                                                            <?= $result[\'stats\'][\'inserted\'] + $result[\'stats\'][\'updated\'] ?> record
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </button>
                                        </h2>
                                        <div id="collapse<?= ucfirst($type) ?>" class="accordion-collapse collapse">
                                            <div class="accordion-body">
                                                <?php if (isset($result[\'success\']) && $result[\'success\']): ?>
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <strong>Processati:</strong> <?= $result[\'stats\'][\'processed\'] ?>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <strong>Inseriti:</strong> <?= $result[\'stats\'][\'inserted\'] ?>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <strong>Aggiornati:</strong> <?= $result[\'stats\'][\'updated\'] ?>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <strong>Saltati:</strong> <?= $result[\'stats\'][\'skipped\'] ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($result[\'warnings\'])): ?>
                                                        <div class="mt-3">
                                                            <h6>⚠️ Avvisi Enhanced:</h6>
                                                            <ul class="list-unstyled">
                                                                <?php foreach ($result[\'warnings\'] as $warning): ?>
                                                                    <li><i class="fas fa-exclamation-triangle text-warning me-2"></i><?= htmlspecialchars($warning) ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                <?php else: ?>
                                                    <div class="alert alert-danger">
                                                        <strong>Errore Enhanced:</strong> <?= htmlspecialchars($result[\'error\'] ?? \'Errore sconosciuto\') ?>
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
        document.getElementById(\'uploadForm\').addEventListener(\'submit\', function() {
            const button = this.querySelector(\'button[type="submit"]\');
            button.disabled = true;
            button.innerHTML = \'<i class="fas fa-spinner fa-spin me-2"></i>Elaborazione Enhanced in corso...\';
        });
    </script>
</body>
</html>';
    
    file_put_contents('enhanced_upload.php', $test_upload_content);
    
    echo "<p style='color: green;'>✅ Creato enhanced_upload.php per test</p>\n";
    
    // 3. Analisi differenze
    echo "<h3>📊 Analisi Funzionalità Enhanced</h3>\n";
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Funzionalità</th><th>CsvParser Standard</th><th>EnhancedCsvParser</th><th>Beneficio</th></tr>\n";
    
    $features = [
        [
            'Parsing Nomi Multipli',
            '❌ Non supportato',
            '✅ Gestisce /, ,, ;, &, +, "e", "with"',
            'Risolve "Franco Fiorellino/Matteo Signo"'
        ],
        [
            'Integrazione Master Tables',
            '❌ Cerca solo in dipendenti legacy',
            '✅ Ricerca in master + alias + fuzzy',
            'Trova dipendenti anche con varianti nome'
        ],
        [
            'Validazione Veicoli',
            '✅ Blacklist statica',
            '✅ Blacklist + master_veicoli',
            'Evita "Punto", "Fiesta" come dipendenti'
        ],
        [
            'Auto-linking',
            '❌ Crea sempre nuovi dipendenti',
            '✅ Collega a master esistenti',
            'Mantiene normalizzazione dati'
        ],
        [
            'Cache Performance',
            '❌ Query a ogni ricerca',
            '✅ Cache in memoria',
            'Velocità import migliorata'
        ],
        [
            'Alias Automatici',
            '❌ Non gestiti',
            '✅ Crea alias per varianti',
            'Traccia tutte le varianti nomi trovate'
        ],
        [
            'Logging Avanzato',
            '✅ Log base',
            '✅ Log dettagliato con context',
            'Debug e troubleshooting migliorati'
        ]
    ];
    
    foreach ($features as $feature) {
        echo "<tr>\n";
        echo "<td><strong>{$feature[0]}</strong></td>\n";
        echo "<td>{$feature[1]}</td>\n";
        echo "<td>{$feature[2]}</td>\n";
        echo "<td><small>{$feature[3]}</small></td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // 4. Test rapido integrazione
    echo "<h3>🧪 Test Rapido Integrazione</h3>\n";
    
    try {
        require_once 'classes/EnhancedCsvParser.php';
        $enhanced_parser = new EnhancedCsvParser();
        echo "<p style='color: green;'>✅ EnhancedCsvParser caricato con successo</p>\n";
        
        // Test metodo core
        $reflection = new ReflectionClass($enhanced_parser);
        $method = $reflection->getMethod('getDipendenteByFullName');
        $method->setAccessible(true);
        
        // Test case semplice
        $test_result = $method->invoke($enhanced_parser, 'Franco Fiorellino');
        if ($test_result) {
            echo "<p style='color: green;'>✅ Test parsing 'Franco Fiorellino': ID $test_result</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ Test parsing 'Franco Fiorellino': non trovato</p>\n";
        }
        
        // Test case multiplo
        $test_result = $method->invoke($enhanced_parser, 'Franco Fiorellino/Matteo Signo');
        if ($test_result) {
            echo "<p style='color: green;'>✅ Test parsing multiplo 'Franco Fiorellino/Matteo Signo': ID $test_result</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ Test parsing multiplo: non riuscito</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Errore test integrazione: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    // 5. Istruzioni finali
    echo "<h3>📋 Istruzioni per Test Completo</h3>\n";
    
    echo "<div style='background: #e8f5e8; padding: 15px; border: 1px solid #a3d977; border-radius: 5px;'>\n";
    echo "<h4>✅ Enhanced Parser Integrato con Successo!</h4>\n";
    echo "<p><strong>🎯 Per testare:</strong></p>\n";
    echo "<ol>\n";
    echo "<li><a href='enhanced_upload.php' target='_blank'><strong>Vai a Enhanced Upload</strong></a> per testare il parser potenziato</li>\n";
    echo "<li>Carica file CSV con nomi problematici come 'Franco Fiorellino/Matteo Signo'</li>\n";
    echo "<li>Verifica che i nomi vengano parsati correttamente</li>\n";
    echo "<li>Controlla che i veicoli non vengano creati come dipendenti</li>\n";
    echo "<li>Se tutto funziona, sostituire definitivamente il CsvParser originale</li>\n";
    echo "</ol>\n";
    echo "<p><strong>🔄 Per rollback:</strong> Il backup è salvato in <code>$backup_file</code></p>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante l'integrazione</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}
?>

<p>
    <a href="enhanced_upload.php">🚀 Test Enhanced Upload</a> | 
    <a href="upload.php">📤 Upload Standard</a> | 
    <a href="index.php">Dashboard</a>
</p>