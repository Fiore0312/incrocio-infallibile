<?php
session_start();
require_once 'config/Database.php';
require_once 'config/Configuration.php';
require_once 'classes/KpiCalculator.php';

$force_recalculate = $_GET['force'] ?? false;
$ajax = $_GET['ajax'] ?? false;

if ($ajax) {
    header('Content-Type: application/json');
}

try {
    $database = new Database();
    
    if (!$database->databaseExists()) {
        throw new Exception("Database non trovato");
    }
    
    $config = new Configuration();
    $kpiCalculator = new KpiCalculator();
    
    if ($ajax) {
        // AJAX call for progress updates
        $action = $_GET['action'] ?? '';
        
        if ($action === 'get_progress') {
            $conn = $database->getConnection();
            
            // Get total days to process
            $stmt = $conn->prepare("
                SELECT 
                    MIN(data) as min_data, 
                    MAX(data) as max_data,
                    DATEDIFF(MAX(data), MIN(data)) + 1 as total_days
                FROM timbrature
            ");
            $stmt->execute();
            $period = $stmt->fetch();
            
            // Get current KPI count
            $stmt = $conn->prepare("SELECT COUNT(*) as current_kpis FROM kpi_giornalieri");
            $stmt->execute();
            $current = $stmt->fetch();
            
            // Get active employees count
            $stmt = $conn->prepare("SELECT COUNT(*) as active_employees FROM dipendenti WHERE attivo = 1");
            $stmt->execute();
            $employees = $stmt->fetch();
            
            $expected_total = $period['total_days'] * $employees['active_employees'];
            $progress = $expected_total > 0 ? round(($current['current_kpis'] / $expected_total) * 100, 1) : 0;
            
            echo json_encode([
                'success' => true,
                'progress' => $progress,
                'current_kpis' => $current['current_kpis'],
                'expected_total' => $expected_total,
                'period' => $period,
                'employees' => $employees['active_employees']
            ]);
            exit;
        }
        
        if ($action === 'start_calculation') {
            // Start the calculation process
            $result = $kpiCalculator->recalculateAllKpis($force_recalculate);
            
            echo json_encode([
                'success' => true,
                'message' => 'Calcolo completato',
                'calculated' => count($result)
            ]);
            exit;
        }
    }
    
    // Normal page load
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $force = $_POST['force'] ?? false;
        $start_calculation = true;
    }
    
} catch (Exception $e) {
    if ($ajax) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calcolo KPI - Employee Analytics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/dashboard.css" rel="stylesheet">
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
                <a class="nav-link" href="master_data_console.php">
                    <i class="fas fa-database me-1"></i>Master Data
                </a>
                <a class="nav-link" href="diagnose_data_master.php">
                    <i class="fas fa-stethoscope me-1"></i>Diagnostica
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calculator me-2"></i>Calcolo KPI Giornalieri
                        </h5>
                    </div>
                    <div class="card-body">
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div id="status-info" class="mb-4">
                            <h6>Stato Attuale del Sistema:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Informazioni Database</h6>
                                            <p class="mb-1"><strong>KPI Calcolati:</strong> <span id="current-kpis">Caricamento...</span></p>
                                            <p class="mb-1"><strong>KPI Totali Attesi:</strong> <span id="expected-kpis">Caricamento...</span></p>
                                            <p class="mb-1"><strong>Dipendenti Attivi:</strong> <span id="active-employees">Caricamento...</span></p>
                                            <p class="mb-0"><strong>Periodo Dati:</strong> <span id="data-period">Caricamento...</span></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Progresso Calcolo</h6>
                                            <div class="progress mb-2">
                                                <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                                            </div>
                                            <p class="mb-0"><span id="progress-text">Pronto per iniziare</span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="calculation-controls">
                            <div class="alert alert-info" role="alert">
                                <h6><i class="fas fa-info-circle me-2"></i>Informazioni sul Calcolo KPI</h6>
                                <p class="mb-2">Questo processo calcolerà tutti i KPI giornalieri per ogni dipendente nel periodo disponibile nei dati.</p>
                                <ul class="mb-2">
                                    <li><strong>Efficiency Rate:</strong> Percentuale ore fatturabili su ore lavorative standard (8h)</li>
                                    <li><strong>Profit/Loss:</strong> Ricavo stimato - Costo giornaliero dipendente</li>
                                    <li><strong>Ore Fatturabili:</strong> Somma ore attività fatturabili per giorno</li>
                                    <li><strong>Sessioni Remote:</strong> Conteggio sessioni TeamViewer per giorno</li>
                                </ul>
                                <p class="mb-0"><strong>Tempo stimato:</strong> 1-3 minuti per l'elaborazione completa.</p>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                                <button id="start-calculation" class="btn btn-primary btn-lg">
                                    <i class="fas fa-play me-2"></i>Avvia Calcolo KPI
                                </button>
                                <button id="force-calculation" class="btn btn-warning btn-lg">
                                    <i class="fas fa-redo me-2"></i>Ricalcola Tutto (Force)
                                </button>
                            </div>
                        </div>
                        
                        <div id="calculation-status" class="mt-4" style="display: none;">
                            <div class="alert alert-primary" role="alert">
                                <h6><i class="fas fa-cog fa-spin me-2"></i>Calcolo in Corso...</h6>
                                <p class="mb-0">Non chiudere questa pagina durante il calcolo.</p>
                            </div>
                        </div>
                        
                        <div id="calculation-complete" class="mt-4" style="display: none;">
                            <div class="alert alert-success" role="alert">
                                <h6><i class="fas fa-check-circle me-2"></i>Calcolo Completato!</h6>
                                <p class="mb-2">Tutti i KPI sono stati calcolati con successo.</p>
                                <div class="d-grid gap-2 d-md-flex">
                                    <a href="index.php" class="btn btn-success">
                                        <i class="fas fa-chart-line me-2"></i>Vai al Dashboard
                                    </a>
                                    <a href="diagnose_data_master.php" class="btn btn-info">
                                        <i class="fas fa-stethoscope me-2"></i>Verifica Dati
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div id="calculation-error" class="mt-4" style="display: none;">
                            <div class="alert alert-danger" role="alert">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Errore nel Calcolo</h6>
                                <p class="mb-2" id="error-message">Si è verificato un errore durante il calcolo.</p>
                                <button onclick="location.reload()" class="btn btn-outline-danger">
                                    <i class="fas fa-refresh me-2"></i>Riprova
                                </button>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let calculationInProgress = false;
        let progressInterval;
        
        // Load initial status
        loadStatus();
        
        // Event listeners
        document.getElementById('start-calculation').addEventListener('click', () => startCalculation(false));
        document.getElementById('force-calculation').addEventListener('click', () => {
            if (confirm('Ricalcolare tutti i KPI? Questo cancellerà i dati esistenti e richiederà più tempo.')) {
                startCalculation(true);
            }
        });
        
        function loadStatus() {
            fetch('calculate_kpis.php?ajax=1&action=get_progress')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('current-kpis').textContent = data.current_kpis;
                        document.getElementById('expected-kpis').textContent = data.expected_total;
                        document.getElementById('active-employees').textContent = data.employees;
                        
                        if (data.period.min_data && data.period.max_data) {
                            document.getElementById('data-period').textContent = 
                                `${data.period.min_data} → ${data.period.max_data} (${data.period.total_days} giorni)`;
                        } else {
                            document.getElementById('data-period').textContent = 'Nessun dato disponibile';
                        }
                        
                        updateProgress(data.progress);
                        
                        // If already complete, show success
                        if (data.progress >= 100) {
                            showCompletionStatus();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading status:', error);
                });
        }
        
        function startCalculation(force = false) {
            if (calculationInProgress) return;
            
            calculationInProgress = true;
            document.getElementById('calculation-controls').style.display = 'none';
            document.getElementById('calculation-status').style.display = 'block';
            document.getElementById('calculation-complete').style.display = 'none';
            document.getElementById('calculation-error').style.display = 'none';
            
            // Start progress monitoring
            progressInterval = setInterval(loadStatus, 2000);
            
            // Start calculation
            const url = `calculate_kpis.php?ajax=1&action=start_calculation${force ? '&force=1' : ''}`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    clearInterval(progressInterval);
                    calculationInProgress = false;
                    
                    if (data.success) {
                        showCompletionStatus();
                        loadStatus(); // Final status update
                    } else {
                        showErrorStatus(data.error || 'Errore sconosciuto');
                    }
                })
                .catch(error => {
                    clearInterval(progressInterval);
                    calculationInProgress = false;
                    showErrorStatus('Errore di connessione: ' + error.message);
                });
        }
        
        function updateProgress(progress) {
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            
            progressBar.style.width = progress + '%';
            progressBar.textContent = progress + '%';
            
            if (progress < 100) {
                progressText.textContent = `Calcolo in corso... ${progress}%`;
                progressBar.className = 'progress-bar bg-primary';
            } else {
                progressText.textContent = 'Calcolo completato!';
                progressBar.className = 'progress-bar bg-success';
            }
        }
        
        function showCompletionStatus() {
            document.getElementById('calculation-status').style.display = 'none';
            document.getElementById('calculation-complete').style.display = 'block';
            document.getElementById('calculation-controls').style.display = 'block';
        }
        
        function showErrorStatus(message) {
            document.getElementById('calculation-status').style.display = 'none';
            document.getElementById('calculation-error').style.display = 'block';
            document.getElementById('error-message').textContent = message;
            document.getElementById('calculation-controls').style.display = 'block';
        }
    </script>
</body>
</html>