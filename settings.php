<?php
session_start();
require_once 'config/Database.php';
require_once 'config/Configuration.php';

$success_message = '';
$error_message = '';
$config = null;

try {
    $database = new Database();
    
    if (!$database->databaseExists()) {
        $error_message = "Database non trovato. È necessario eseguire il setup iniziale.";
        $setup_required = true;
    } else {
        $config = new Configuration();
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'save_settings') {
                $updates = 0;
                $errors = [];
                
                // Process all configuration updates
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'config_') === 0) {
                        $config_key = substr($key, 7); // Remove 'config_' prefix
                        $type = $_POST['type_' . $config_key] ?? 'string';
                        $description = $_POST['desc_' . $config_key] ?? '';
                        $category = $_POST['cat_' . $config_key] ?? 'generale';
                        
                        // Validate value based on type
                        $validated_value = $value;
                        switch ($type) {
                            case 'integer':
                                if (!is_numeric($value) || (int)$value != $value) {
                                    $errors[] = "Valore non valido per $config_key: deve essere un numero intero";
                                    continue 2;
                                }
                                $validated_value = (int)$value;
                                break;
                            case 'float':
                                if (!is_numeric($value)) {
                                    $errors[] = "Valore non valido per $config_key: deve essere un numero";
                                    continue 2;
                                }
                                $validated_value = (float)$value;
                                break;
                            case 'boolean':
                                $validated_value = $value ? 1 : 0;
                                break;
                        }
                        
                        if ($config->set($config_key, $validated_value, $type, $description, $category)) {
                            $updates++;
                        } else {
                            $errors[] = "Errore nel salvare $config_key";
                        }
                    }
                }
                
                if (empty($errors)) {
                    $success_message = "Configurazioni salvate con successo! ($updates aggiornamenti)";
                } else {
                    $error_message = "Alcuni errori durante il salvataggio: " . implode(', ', $errors);
                }
            }
            
            if ($action === 'initialize_defaults') {
                if ($config->initializeDefaults()) {
                    $success_message = "Configurazioni default inizializzate con successo!";
                } else {
                    $error_message = "Errore nell'inizializzazione delle configurazioni default";
                }
            }
            
            if ($action === 'backup_config') {
                $filename = $config->backup();
                if ($filename) {
                    $success_message = "Backup creato con successo: $filename";
                } else {
                    $error_message = "Errore nella creazione del backup";
                }
            }
        }
        
        // Get all configurations grouped by category
        $all_configs = [];
        $stmt = $database->getConnection()->prepare("SELECT * FROM configurazioni ORDER BY categoria, chiave");
        $stmt->execute();
        $configs = $stmt->fetchAll();
        
        foreach ($configs as $conf) {
            $all_configs[$conf['categoria']][] = $conf;
        }
    }
    
} catch (Exception $e) {
    $error_message = "Errore di sistema: " . $e->getMessage();
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurazioni - Employee Analytics Dashboard</title>
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
                <a class="nav-link" href="upload.php">
                    <i class="fas fa-upload me-1"></i>Carica CSV
                </a>
                <a class="nav-link" href="master_data_console.php">
                    <i class="fas fa-database me-1"></i>Master Data
                </a>
                <a class="nav-link active" href="settings.php">
                    <i class="fas fa-cog me-1"></i>Configurazioni
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($setup_required)): ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>Database non configurato
                <hr>
                <p class="mb-3">È necessario eseguire il setup iniziale del database prima di poter utilizzare le configurazioni.</p>
                <a href="setup.php" class="btn btn-warning">
                    <i class="fas fa-cog me-2"></i>Avvia Setup
                </a>
            </div>
        <?php else: ?>
            
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-cog me-2"></i>Configurazioni Sistema
                            </h5>
                        </div>
                        <div class="card-body">
                            
                            <!-- Action buttons -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="btn-group" role="group">
                                        <form method="post" style="display: inline-block;">
                                            <input type="hidden" name="action" value="initialize_defaults">
                                            <button type="submit" class="btn btn-outline-primary" onclick="return confirm('Inizializzare le configurazioni default? Questo non sovrascriverà quelle esistenti.')">
                                                <i class="fas fa-magic me-1"></i>Inizializza Default
                                            </button>
                                        </form>
                                        <form method="post" style="display: inline-block;">
                                            <input type="hidden" name="action" value="backup_config">
                                            <button type="submit" class="btn btn-outline-success">
                                                <i class="fas fa-download me-1"></i>Crea Backup
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($all_configs)): ?>
                                <form method="post" id="settingsForm">
                                    <input type="hidden" name="action" value="save_settings">
                                    
                                    <?php foreach ($all_configs as $category => $configs): ?>
                                        <div class="row mb-4">
                                            <div class="col-12">
                                                <h6 class="text-uppercase text-muted mb-3">
                                                    <i class="fas fa-folder me-1"></i><?= ucfirst(htmlspecialchars($category)) ?>
                                                </h6>
                                                
                                                <div class="table-responsive">
                                                    <table class="table table-bordered">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th style="width: 25%;">Chiave</th>
                                                                <th style="width: 20%;">Valore</th>
                                                                <th style="width: 10%;">Tipo</th>
                                                                <th style="width: 45%;">Descrizione</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($configs as $conf): ?>
                                                                <tr>
                                                                    <td>
                                                                        <strong><?= htmlspecialchars($conf['chiave']) ?></strong>
                                                                        <input type="hidden" name="type_<?= $conf['chiave'] ?>" value="<?= $conf['tipo'] ?>">
                                                                        <input type="hidden" name="desc_<?= $conf['chiave'] ?>" value="<?= htmlspecialchars($conf['descrizione']) ?>">
                                                                        <input type="hidden" name="cat_<?= $conf['chiave'] ?>" value="<?= htmlspecialchars($conf['categoria']) ?>">
                                                                    </td>
                                                                    <td>
                                                                        <?php if ($conf['tipo'] === 'boolean'): ?>
                                                                            <select name="config_<?= $conf['chiave'] ?>" class="form-select form-select-sm">
                                                                                <option value="0" <?= $conf['valore'] == '0' ? 'selected' : '' ?>>No</option>
                                                                                <option value="1" <?= $conf['valore'] == '1' ? 'selected' : '' ?>>Sì</option>
                                                                            </select>
                                                                        <?php elseif ($conf['tipo'] === 'float'): ?>
                                                                            <?php 
                                                                            $is_monetary = (strpos($conf['chiave'], 'costo') !== false || 
                                                                                          strpos($conf['chiave'], 'tariffa') !== false || 
                                                                                          strpos($conf['chiave'], 'profit') !== false);
                                                                            $formatted_value = $is_monetary ? number_format((float)$conf['valore'], 2, '.', '') : $conf['valore'];
                                                                            ?>
                                                                            <input type="number" name="config_<?= $conf['chiave'] ?>" 
                                                                                   value="<?= htmlspecialchars($formatted_value) ?>" 
                                                                                   class="form-control form-control-sm"
                                                                                   step="<?= $is_monetary ? '0.01' : '0.1' ?>"
                                                                                   min="<?= $is_monetary && strpos($conf['chiave'], 'profit') !== false ? '' : '0' ?>">
                                                                        <?php elseif ($conf['tipo'] === 'integer'): ?>
                                                                            <input type="number" name="config_<?= $conf['chiave'] ?>" 
                                                                                   value="<?= htmlspecialchars($conf['valore']) ?>" 
                                                                                   class="form-control form-control-sm"
                                                                                   step="1"
                                                                                   min="0">
                                                                        <?php else: ?>
                                                                            <input type="text" name="config_<?= $conf['chiave'] ?>" 
                                                                                   value="<?= htmlspecialchars($conf['valore']) ?>" 
                                                                                   class="form-control form-control-sm">
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <small class="text-muted"><?= htmlspecialchars($conf['tipo']) ?></small>
                                                                    </td>
                                                                    <td>
                                                                        <small><?= htmlspecialchars($conf['descrizione']) ?></small>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="row">
                                        <div class="col-12 text-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Salva Configurazioni
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-info-circle text-muted fa-3x mb-3"></i>
                                    <h5 class="text-muted">Nessuna configurazione trovata</h5>
                                    <p class="text-muted">Clicca su "Inizializza Default" per creare le configurazioni base del sistema</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Additional Info Card -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2"></i>Informazioni Configurazioni
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <h6 class="text-primary">Tipologie di Configurazioni</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-circle text-success me-2"></i><strong>Costi:</strong> Parametri economici</li>
                                        <li><i class="fas fa-circle text-info me-2"></i><strong>KPI:</strong> Soglie indicatori performance</li>
                                        <li><i class="fas fa-circle text-warning me-2"></i><strong>Parametri:</strong> Valori operativi</li>
                                        <li><i class="fas fa-circle text-danger me-2"></i><strong>Alert:</strong> Configurazioni notifiche</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="text-primary">Tipi di Dati</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-hashtag me-2"></i><strong>integer:</strong> Numeri interi</li>
                                        <li><i class="fas fa-calculator me-2"></i><strong>float:</strong> Numeri decimali</li>
                                        <li><i class="fas fa-check-square me-2"></i><strong>boolean:</strong> Vero/Falso</li>
                                        <li><i class="fas fa-quote-left me-2"></i><strong>string:</strong> Testo</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="text-primary">Funzionalità</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-save me-2"></i>Salvataggio in tempo reale</li>
                                        <li><i class="fas fa-download me-2"></i>Backup automatico</li>
                                        <li><i class="fas fa-shield-alt me-2"></i>Validazione input</li>
                                        <li><i class="fas fa-history me-2"></i>Log delle modifiche</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('settingsForm')?.addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input[type="text"], input[type="number"]');
            let hasError = false;
            
            inputs.forEach(input => {
                const value = input.value.trim();
                const type = input.getAttribute('type');
                
                // Validate based on input type
                if (type === 'number') {
                    if (value === '' || isNaN(value)) {
                        input.classList.add('is-invalid');
                        hasError = true;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                } else {
                    // Text validation with pattern if exists
                    const pattern = input.getAttribute('pattern');
                    if (pattern && !new RegExp(pattern).test(value)) {
                        input.classList.add('is-invalid');
                        hasError = true;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                }
            });
            
            if (hasError) {
                e.preventDefault();
                alert('Alcuni valori non sono validi. Controlla i campi evidenziati.');
            }
        });
        
        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
    </script>
</body>
</html>