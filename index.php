<?php
session_start();
require_once 'config/Database.php';
require_once 'config/Configuration.php';
require_once 'classes/KpiCalculator.php';
require_once 'classes/ValidationEngine.php';

try {
    $database = new Database();
    
    if (!$database->databaseExists()) {
        $error_message = "Database non trovato. È necessario eseguire il setup iniziale.";
        $setup_required = true;
    } else {
        $config = new Configuration();
        $kpiCalculator = new KpiCalculator();
        
        $today = date('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        
        $summary = $kpiCalculator->getKpiSummary(null, $week_ago, $today);
        $alerts = $kpiCalculator->getAlertsCount($week_ago, $today);
        $topPerformers = $kpiCalculator->getTopPerformers($week_ago, $today, 5);
    }
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'non trovato') !== false || strpos($e->getMessage(), 'Unknown database') !== false) {
        $error_message = "Database non trovato. È necessario eseguire il setup iniziale.";
        $setup_required = true;
    } else {
        $error_message = "Errore di connessione al database: " . $e->getMessage();
    }
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Analytics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/dashboard.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-chart-line me-2"></i>Employee Analytics
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="upload.php">
                    <i class="fas fa-upload me-1"></i>Carica CSV
                </a>
                <a class="nav-link" href="calculate_kpis.php">
                    <i class="fas fa-calculator me-1"></i>Calcola KPI
                </a>
                <a class="nav-link" href="master_data_console.php">
                    <i class="fas fa-database me-1"></i>Master Data
                </a>
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog me-1"></i>Configurazioni
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-<?= isset($setup_required) ? 'warning' : 'danger' ?>" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= $error_message ?>
                <hr>
                <?php if (isset($setup_required)): ?>
                    <p class="mb-3">
                        <strong>Setup automatico disponibile:</strong><br>
                        Il sistema può creare automaticamente il database e configurare tutti i parametri necessari.
                    </p>
                    <a href="setup.php" class="btn btn-warning">
                        <i class="fas fa-cog me-2"></i>Avvia Setup Automatico
                    </a>
                <?php else: ?>
                    <p class="mb-0">
                        <strong>Verifica configurazione:</strong><br>
                        1. Controllare le credenziali in <code>config/Database.php</code><br>
                        2. Verificare che MySQL sia in esecuzione<br>
                        3. Eseguire il setup tramite <code>setup.php</code>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            
            <!-- KPI Cards Row -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-header d-flex justify-content-between">
                            <span>Efficiency Rate Media</span>
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="card-body">
                            <h4 class="card-title"><?= number_format($summary['avg_efficiency_rate'] ?? 0, 1) ?>%</h4>
                            <p class="card-text">Ultimi 7 giorni</p>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card text-white <?= ($summary['total_profit_loss'] ?? 0) >= 0 ? 'bg-success' : 'bg-danger' ?> mb-3">
                        <div class="card-header d-flex justify-content-between">
                            <span>Profit/Loss Totale</span>
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <div class="card-body">
                            <h4 class="card-title">€<?= number_format($summary['total_profit_loss'] ?? 0, 2) ?></h4>
                            <p class="card-text">Ultimi 7 giorni</p>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card text-white bg-info mb-3">
                        <div class="card-header d-flex justify-content-between">
                            <span>Ore Fatturabili</span>
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="card-body">
                            <h4 class="card-title"><?= number_format($summary['totale_ore_fatturabili'] ?? 0, 1) ?>h</h4>
                            <p class="card-text">Ultimi 7 giorni</p>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card text-white bg-warning mb-3">
                        <div class="card-header d-flex justify-content-between">
                            <span>Alert Attivi</span>
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="card-body">
                            <h4 class="card-title"><?= array_sum($alerts ?? [0,0,0,0]) ?></h4>
                            <p class="card-text">Da verificare</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-area me-2"></i>Andamento Performance Settimanale
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="performanceChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-exclamation-circle me-2"></i>Alert Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert-item d-flex justify-content-between align-items-center mb-2">
                                <span>Efficiency Warnings</span>
                                <span class="badge bg-warning"><?= $alerts['efficiency_warnings'] ?? 0 ?></span>
                            </div>
                            <div class="alert-item d-flex justify-content-between align-items-center mb-2">
                                <span>Efficiency Critical</span>
                                <span class="badge bg-danger"><?= $alerts['efficiency_critical'] ?? 0 ?></span>
                            </div>
                            <div class="alert-item d-flex justify-content-between align-items-center mb-2">
                                <span>Profit Warnings</span>
                                <span class="badge bg-warning"><?= $alerts['profit_warnings'] ?? 0 ?></span>
                            </div>
                            <div class="alert-item d-flex justify-content-between align-items-center">
                                <span>Ore Insufficienti</span>
                                <span class="badge bg-danger"><?= $alerts['ore_insufficienti'] ?? 0 ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performers Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-trophy me-2"></i>Top Performers (Ultimi 7 giorni)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($topPerformers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Dipendente</th>
                                                <th>Efficiency Rate</th>
                                                <th>Profit/Loss</th>
                                                <th>Ore Fatturabili</th>
                                                <th>Giorni Lavorativi</th>
                                                <th>Sessioni Remote</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topPerformers as $performer): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($performer['nome'] . ' ' . $performer['cognome']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= $performer['avg_efficiency_rate'] >= 80 ? 'bg-success' : ($performer['avg_efficiency_rate'] >= 60 ? 'bg-warning' : 'bg-danger') ?>">
                                                            <?= number_format($performer['avg_efficiency_rate'], 1) ?>%
                                                        </span>
                                                    </td>
                                                    <td class="<?= $performer['profit_loss_totale'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                        €<?= number_format($performer['profit_loss_totale'], 2) ?>
                                                    </td>
                                                    <td><?= number_format($performer['ore_fatturabili_totali'], 1) ?>h</td>
                                                    <td><?= $performer['giorni_lavorativi'] ?></td>
                                                    <td><?= $performer['sessioni_remote_totali'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-info-circle text-muted fa-3x mb-3"></i>
                                    <h5 class="text-muted">Nessun dato disponibile</h5>
                                    <p class="text-muted">Carica i file CSV per iniziare a visualizzare le metriche</p>
                                    <a href="upload.php" class="btn btn-primary">
                                        <i class="fas fa-upload me-2"></i>Carica Dati
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/dashboard.js"></script>
</body>
</html>