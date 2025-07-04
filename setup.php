<?php
require_once 'config/Database.php';
require_once 'config/Configuration.php';

$step = $_GET['step'] ?? 1;
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'create_database':
                $database = new Database();
                
                if ($database->databaseExists()) {
                    $message = "Database già esistente. Procedendo al passo successivo.";
                    $message_type = 'info';
                    $step = 2;
                } else {
                    $database->createDatabase();
                    $message = "Database creato con successo!";
                    $message_type = 'success';
                    $step = 2;
                }
                break;
                
            case 'initialize_config':
                $config = new Configuration();
                $config->initializeDefaults();
                
                $message = "Configurazioni inizializzate con successo!";
                $message_type = 'success';
                $step = 3;
                break;
                
            case 'test_system':
                // Test basic functionality
                $database = new Database();
                $conn = $database->getConnection();
                
                $config = new Configuration();
                $costo = $config->getCostoGiornaliero();
                
                $message = "Sistema testato con successo! Costo giornaliero: €$costo";
                $message_type = 'success';
                $step = 4;
                break;
        }
    } catch (Exception $e) {
        $message = "Errore: " . $e->getMessage();
        $message_type = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Employee Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-cog me-2"></i>Setup Employee Analytics
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Progress Bar -->
                        <div class="progress mb-4">
                            <div class="progress-bar" role="progressbar" style="width: <?= ($step / 4) * 100 ?>%">
                                Step <?= $step ?> di 4
                            </div>
                        </div>

                        <?php if ($step == 1): ?>
                            <!-- Step 1: Database Creation -->
                            <h5><i class="fas fa-database me-2"></i>Step 1: Creazione Database</h5>
                            <p>Questo step creerà il database MySQL e tutte le tabelle necessarie.</p>
                            
                            <div class="alert alert-info">
                                <strong>Prerequisiti:</strong><br>
                                • MySQL server attivo<br>
                                • Credenziali corrette in <code>config/Database.php</code><br>
                                • Permessi per creare database e tabelle
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="create_database">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-database me-2"></i>Crea Database
                                </button>
                            </form>

                        <?php elseif ($step == 2): ?>
                            <!-- Step 2: Configuration -->
                            <h5><i class="fas fa-sliders-h me-2"></i>Step 2: Configurazione Sistema</h5>
                            <p>Inizializzazione delle configurazioni di default per KPI e validazioni.</p>
                            
                            <div class="alert alert-info">
                                <strong>Configurazioni che verranno impostate:</strong><br>
                                • Costo dipendente default: €80/giorno<br>
                                • Ore lavorative giornaliere: 8<br>
                                • Tariffa oraria standard: €50<br>
                                • Soglie di alert e validazione
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="initialize_config">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-cog me-2"></i>Inizializza Configurazioni
                                </button>
                            </form>

                        <?php elseif ($step == 3): ?>
                            <!-- Step 3: System Test -->
                            <h5><i class="fas fa-check-circle me-2"></i>Step 3: Test Sistema</h5>
                            <p>Verifica che tutti i componenti funzionino correttamente.</p>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="test_system">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-play me-2"></i>Testa Sistema
                                </button>
                            </form>

                        <?php else: ?>
                            <!-- Step 4: Complete -->
                            <div class="text-center">
                                <i class="fas fa-check-circle text-success fa-5x mb-3"></i>
                                <h5 class="text-success">Setup Completato!</h5>
                                <p>Il sistema Employee Analytics è pronto per l'uso.</p>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="fas fa-upload fa-2x text-primary mb-2"></i>
                                                <h6>Carica Dati</h6>
                                                <p class="small">Inizia caricando i file CSV</p>
                                                <a href="upload.php" class="btn btn-primary btn-sm">
                                                    Vai al Upload
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="fas fa-chart-line fa-2x text-success mb-2"></i>
                                                <h6>Dashboard</h6>
                                                <p class="small">Visualizza le metriche</p>
                                                <a href="index.php" class="btn btn-success btn-sm">
                                                    Vai al Dashboard
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr class="my-4">
                                
                                <div class="alert alert-info text-start">
                                    <h6><i class="fas fa-info-circle me-2"></i>Prossimi Passi:</h6>
                                    <ol class="mb-0">
                                        <li>Carica i file CSV dalla cartella <code>file-orig-300625</code></li>
                                        <li>Verifica che i dati siano stati importati correttamente</li>
                                        <li>Controlla le anomalie rilevate automaticamente</li>
                                        <li>Configura i parametri specifici se necessario</li>
                                    </ol>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
                
                <!-- System Requirements Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-server me-2"></i>Requisiti di Sistema
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>PHP 7.4+</li>
                                    <li><i class="fas fa-check text-success me-2"></i>MySQL 5.7+</li>
                                    <li><i class="fas fa-check text-success me-2"></i>PDO Extension</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Apache/Nginx</li>
                                    <li><i class="fas fa-check text-success me-2"></i>JSON Extension</li>
                                    <li><i class="fas fa-check text-success me-2"></i>File Upload enabled</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>