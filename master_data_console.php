<?php
session_start();
require_once 'config/Database.php';
require_once 'classes/ImportLogger.php';

/**
 * Master Data Management Console - Fase 4
 * UI per gestire dipendenti fissi, aziende, veicoli e associazioni
 */

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Master Data Console - Employee Analytics</title>\n";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
echo "<link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>\n";
echo "<style>\n";
echo ".card-header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; }\n";
echo ".master-item { transition: all 0.3s ease; }\n";
echo ".master-item:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }\n";
echo ".queue-item { border-left: 4px solid #ffc107; }\n";
echo ".queue-item.pending { border-left-color: #dc3545; }\n";
echo ".queue-item.assigned { border-left-color: #198754; }\n";
echo ".status-badge { font-size: 0.8em; }\n";
echo "</style>\n";
echo "</head>\n<body>\n";

// Navigation
echo "<nav class='navbar navbar-expand-lg navbar-dark bg-primary'>\n";
echo "<div class='container-fluid'>\n";
echo "<a class='navbar-brand' href='index.php'>\n";
echo "<i class='fas fa-database me-2'></i>Master Data Console\n";
echo "</a>\n";
echo "<div class='navbar-nav ms-auto'>\n";
echo "<a class='nav-link' href='index.php'><i class='fas fa-home me-1'></i>Dashboard</a>\n";
echo "<a class='nav-link' href='test_smart_parser.php'><i class='fas fa-flask me-1'></i>Test Parser</a>\n";
echo "</div>\n";
echo "</div>\n";
echo "</nav>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    $logger = new ImportLogger('master_console');
    
    // Gestione azioni POST
    $message = '';
    $message_type = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'add_employee':
                    $stmt = $conn->prepare("
                        INSERT INTO master_dipendenti_fixed (nome, cognome, email, ruolo, costo_giornaliero) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['nome'],
                        $_POST['cognome'],
                        $_POST['email'],
                        $_POST['ruolo'],
                        $_POST['costo_giornaliero']
                    ]);
                    $message = "Dipendente aggiunto con successo";
                    $message_type = 'success';
                    break;
                    
                case 'add_company':
                    $stmt = $conn->prepare("
                        INSERT INTO master_aziende (nome, nome_breve, settore, email, telefono) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['nome'],
                        $_POST['nome_breve'],
                        $_POST['settore'],
                        $_POST['email'],
                        $_POST['telefono']
                    ]);
                    $message = "Azienda aggiunta con successo";
                    $message_type = 'success';
                    break;
                    
                case 'add_vehicle':
                    $stmt = $conn->prepare("
                        INSERT INTO master_veicoli_config (nome, tipo, marca, modello, targa, costo_km) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['nome'],
                        $_POST['tipo'],
                        $_POST['marca'],
                        $_POST['modello'],
                        $_POST['targa'],
                        $_POST['costo_km']
                    ]);
                    $message = "Veicolo aggiunto con successo";
                    $message_type = 'success';
                    break;
                    
                case 'associate_client':
                    $stmt = $conn->prepare("CALL AssociateClientToCompany(?, ?, ?)");
                    $stmt->execute([
                        $_POST['nome_cliente'],
                        $_POST['azienda_id'],
                        'Admin Console'
                    ]);
                    $message = "Cliente associato con successo";
                    $message_type = 'success';
                    break;
                    
                case 'add_config':
                    $stmt = $conn->prepare("
                        INSERT INTO system_config (categoria, chiave, valore, tipo, descrizione) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['categoria'],
                        $_POST['chiave'],
                        $_POST['valore'],
                        $_POST['tipo'],
                        $_POST['descrizione']
                    ]);
                    $message = "Configurazione aggiunta con successo";
                    $message_type = 'success';
                    break;
                    
                case 'update_config':
                    $stmt = $conn->prepare("UPDATE system_config SET valore = ? WHERE id = ? AND modificabile = 1");
                    $stmt->execute([$_POST['valore'], $_POST['id']]);
                    $message = "Configurazione aggiornata con successo";
                    $message_type = 'success';
                    break;
                    
                case 'edit_employee':
                    $updates = [];
                    $params = [];
                    
                    if (!empty($_POST['nome'])) {
                        $updates[] = "nome = ?";
                        $params[] = $_POST['nome'];
                    }
                    if (!empty($_POST['cognome'])) {
                        $updates[] = "cognome = ?";
                        $params[] = $_POST['cognome'];
                    }
                    if (!empty($_POST['email'])) {
                        $updates[] = "email = ?";
                        $params[] = $_POST['email'];
                    }
                    if (!empty($_POST['ruolo'])) {
                        $updates[] = "ruolo = ?";
                        $params[] = $_POST['ruolo'];
                    }
                    if (!empty($_POST['costo_giornaliero'])) {
                        $updates[] = "costo_giornaliero = ?";
                        $params[] = $_POST['costo_giornaliero'];
                    }
                    
                    if (!empty($updates)) {
                        $params[] = $_POST['id'];
                        $sql = "UPDATE master_dipendenti_fixed SET " . implode(", ", $updates) . " WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        $message = "Dipendente modificato con successo";
                        $message_type = 'success';
                    }
                    break;
                    
                case 'edit_company':
                    $updates = [];
                    $params = [];
                    
                    if (!empty($_POST['nome'])) {
                        $updates[] = "nome = ?";
                        $params[] = $_POST['nome'];
                    }
                    if (!empty($_POST['nome_breve'])) {
                        $updates[] = "nome_breve = ?";
                        $params[] = $_POST['nome_breve'];
                    }
                    if (!empty($_POST['settore'])) {
                        $updates[] = "settore = ?";
                        $params[] = $_POST['settore'];
                    }
                    if (!empty($_POST['email'])) {
                        $updates[] = "email = ?";
                        $params[] = $_POST['email'];
                    }
                    if (!empty($_POST['telefono'])) {
                        $updates[] = "telefono = ?";
                        $params[] = $_POST['telefono'];
                    }
                    
                    if (!empty($updates)) {
                        $params[] = $_POST['id'];
                        $sql = "UPDATE master_aziende SET " . implode(", ", $updates) . " WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        $message = "Azienda modificata con successo";
                        $message_type = 'success';
                    }
                    break;
                    
                case 'edit_vehicle':
                    $updates = [];
                    $params = [];
                    
                    if (!empty($_POST['nome'])) {
                        $updates[] = "nome = ?";
                        $params[] = $_POST['nome'];
                    }
                    if (!empty($_POST['tipo'])) {
                        $updates[] = "tipo = ?";
                        $params[] = $_POST['tipo'];
                    }
                    if (!empty($_POST['marca'])) {
                        $updates[] = "marca = ?";
                        $params[] = $_POST['marca'];
                    }
                    if (!empty($_POST['modello'])) {
                        $updates[] = "modello = ?";
                        $params[] = $_POST['modello'];
                    }
                    if (!empty($_POST['targa'])) {
                        $updates[] = "targa = ?";
                        $params[] = $_POST['targa'];
                    }
                    if (!empty($_POST['costo_km'])) {
                        $updates[] = "costo_km = ?";
                        $params[] = $_POST['costo_km'];
                    }
                    
                    if (!empty($updates)) {
                        $params[] = $_POST['id'];
                        $sql = "UPDATE master_veicoli_config SET " . implode(", ", $updates) . " WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        $message = "Veicolo modificato con successo";
                        $message_type = 'success';
                    }
                    break;
                    
                case 'reject_association':
                    $stmt = $conn->prepare("UPDATE association_queue SET stato = 'rejected' WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $message = "Associazione rifiutata con successo";
                    $message_type = 'success';
                    break;
                    
                case 'toggle_status':
                    $table = $_POST['table'];
                    $id = $_POST['id'];
                    $current_status = $_POST['current_status'];
                    $new_status = $current_status ? 0 : 1;
                    
                    $allowed_tables = ['master_dipendenti_fixed', 'master_aziende', 'master_veicoli_config'];
                    if (in_array($table, $allowed_tables)) {
                        $stmt = $conn->prepare("UPDATE $table SET attivo = ? WHERE id = ?");
                        $stmt->execute([$new_status, $id]);
                        $message = "Stato aggiornato con successo";
                        $message_type = 'success';
                    }
                    break;
            }
        } catch (Exception $e) {
            $message = "Errore: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
    
    echo "<div class='container-fluid mt-4'>\n";
    
    // Message display
    if ($message) {
        echo "<div class='alert alert-$message_type alert-dismissible fade show'>\n";
        echo "$message\n";
        echo "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>\n";
        echo "</div>\n";
    }
    
    // Tab navigation
    echo "<ul class='nav nav-tabs' id='masterTabs' role='tablist'>\n";
    echo "<li class='nav-item' role='presentation'>\n";
    echo "<button class='nav-link active' id='employees-tab' data-bs-toggle='tab' data-bs-target='#employees' type='button'>\n";
    echo "<i class='fas fa-users me-2'></i>Dipendenti Fissi (15)\n";
    echo "</button>\n";
    echo "</li>\n";
    echo "<li class='nav-item' role='presentation'>\n";
    echo "<button class='nav-link' id='companies-tab' data-bs-toggle='tab' data-bs-target='#companies' type='button'>\n";
    echo "<i class='fas fa-building me-2'></i>Aziende\n";
    echo "</button>\n";
    echo "</li>\n";
    echo "<li class='nav-item' role='presentation'>\n";
    echo "<button class='nav-link' id='vehicles-tab' data-bs-toggle='tab' data-bs-target='#vehicles' type='button'>\n";
    echo "<i class='fas fa-car me-2'></i>Veicoli\n";
    echo "</button>\n";
    echo "</li>\n";
    echo "<li class='nav-item' role='presentation'>\n";
    echo "<button class='nav-link' id='associations-tab' data-bs-toggle='tab' data-bs-target='#associations' type='button'>\n";
    echo "<i class='fas fa-link me-2'></i>Associazioni\n";
    echo "<span class='badge bg-warning ms-1' id='queueCount'>0</span>\n";
    echo "</button>\n";
    echo "</li>\n";
    echo "<li class='nav-item' role='presentation'>\n";
    echo "<button class='nav-link' id='config-tab' data-bs-toggle='tab' data-bs-target='#config' type='button'>\n";
    echo "<i class='fas fa-cog me-2'></i>Configurazioni\n";
    echo "</button>\n";
    echo "</li>\n";
    echo "</ul>\n";
    
    echo "<div class='tab-content' id='masterTabContent'>\n";
    
    // TAB 1: DIPENDENTI FISSI
    echo "<div class='tab-pane fade show active' id='employees' role='tabpanel'>\n";
    echo "<div class='row mt-3'>\n";
    
    // Lista dipendenti esistenti
    echo "<div class='col-lg-8'>\n";
    echo "<div class='card'>\n";
    echo "<div class='card-header'>\n";
    echo "<h5 class='mb-0'><i class='fas fa-users me-2'></i>Dipendenti Master (Lista Fissa)</h5>\n";
    echo "</div>\n";
    echo "<div class='card-body'>\n";
    
    $stmt = $conn->prepare("
        SELECT id, nome, cognome, nome_completo, email, ruolo, costo_giornaliero, attivo,
               (SELECT COUNT(*) FROM dipendenti WHERE master_dipendente_id = mdf.id) as legacy_links
        FROM master_dipendenti_fixed mdf 
        ORDER BY cognome, nome
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll();
    
    if (empty($employees)) {
        echo "<div class='alert alert-warning'>\n";
        echo "<i class='fas fa-exclamation-triangle me-2'></i>Nessun dipendente master trovato.\n";
        echo "<a href='setup_master_schema.php' class='btn btn-sm btn-primary ms-2'>Setup Schema</a>\n";
        echo "</div>\n";
    } else {
        echo "<div class='row'>\n";
        foreach ($employees as $emp) {
            $status_class = $emp['attivo'] ? 'border-success' : 'border-secondary';
            $status_icon = $emp['attivo'] ? 'fa-check text-success' : 'fa-times text-secondary';
            
            echo "<div class='col-md-6 col-lg-4 mb-3'>\n";
            echo "<div class='card master-item $status_class'>\n";
            echo "<div class='card-body p-3'>\n";
            echo "<div class='d-flex justify-content-between align-items-start'>\n";
            echo "<div>\n";
            echo "<h6 class='card-title mb-1'>{$emp['nome']} {$emp['cognome']}</h6>\n";
            echo "<small class='text-muted'>{$emp['ruolo']} • €{$emp['costo_giornaliero']}/giorno</small>\n";
            if ($emp['email']) {
                echo "<br><small><i class='fas fa-envelope me-1'></i>{$emp['email']}</small>\n";
            }
            echo "<br><small><i class='fas fa-link me-1'></i>{$emp['legacy_links']} collegamento/i legacy</small>\n";
            echo "</div>\n";
            echo "<div class='dropdown'>\n";
            echo "<button class='btn btn-sm btn-outline-secondary dropdown-toggle' data-bs-toggle='dropdown'>\n";
            echo "<i class='fas $status_icon'></i>\n";
            echo "</button>\n";
            echo "<ul class='dropdown-menu'>\n";
            echo "<li><a class='dropdown-item' href='#' onclick='editEmployee({$emp['id']})'>\n";
            echo "<i class='fas fa-edit me-2'></i>Modifica\n";
            echo "</a></li>\n";
            echo "<li><a class='dropdown-item' href='#' onclick='toggleStatus(\"master_dipendenti_fixed\", {$emp['id']}, {$emp['attivo']})'>\n";
            echo "<i class='fas fa-toggle-" . ($emp['attivo'] ? 'off' : 'on') . " me-2'></i>" . ($emp['attivo'] ? 'Disattiva' : 'Attiva') . "\n";
            echo "</a></li>\n";
            echo "</ul>\n";
            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n";
        }
        echo "</div>\n";
        
        echo "<div class='alert alert-info mt-3'>\n";
        echo "<i class='fas fa-info-circle me-2'></i>\n";
        echo "<strong>Lista Fissa:</strong> Questi sono i 15 dipendenti certi della tua organizzazione. ";
        echo "Puoi modificare i dettagli ma la lista è progettata per essere stabile.\n";
        echo "</div>\n";
    }
    
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    // Form aggiungi dipendente
    echo "<div class='col-lg-4'>\n";
    echo "<div class='card'>\n";
    echo "<div class='card-header'>\n";
    echo "<h6 class='mb-0'><i class='fas fa-plus me-2'></i>Aggiungi Dipendente</h6>\n";
    echo "</div>\n";
    echo "<div class='card-body'>\n";
    echo "<form method='POST'>\n";
    echo "<input type='hidden' name='action' value='add_employee'>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Nome</label>\n";
    echo "<input type='text' name='nome' class='form-control' required>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Cognome</label>\n";
    echo "<input type='text' name='cognome' class='form-control' required>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Email</label>\n";
    echo "<input type='email' name='email' class='form-control'>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Ruolo</label>\n";
    echo "<select name='ruolo' class='form-select'>\n";
    echo "<option value='Tecnico'>Tecnico</option>\n";
    echo "<option value='Manager'>Manager</option>\n";
    echo "<option value='Responsabile'>Responsabile</option>\n";
    echo "<option value='Admin'>Admin</option>\n";
    echo "</select>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Costo Giornaliero (€)</label>\n";
    echo "<input type='number' name='costo_giornaliero' class='form-control' value='200.00' step='0.01'>\n";
    echo "</div>\n";
    echo "<button type='submit' class='btn btn-primary w-100'>\n";
    echo "<i class='fas fa-plus me-2'></i>Aggiungi\n";
    echo "</button>\n";
    echo "</form>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    echo "</div>\n";
    echo "</div>\n";
    
    // TAB 2: AZIENDE
    echo "<div class='tab-pane fade' id='companies' role='tabpanel'>\n";
    echo "<div class='row mt-3'>\n";
    
    echo "<div class='col-lg-8'>\n";
    echo "<div class='card'>\n";
    echo "<div class='card-header'>\n";
    echo "<h5 class='mb-0'><i class='fas fa-building me-2'></i>Aziende Master</h5>\n";
    echo "</div>\n";
    echo "<div class='card-body'>\n";
    
    $stmt = $conn->prepare("
        SELECT ma.*, 
               COUNT(DISTINCT ca.id) as clienti_count,
               COUNT(DISTINCT mp.id) as progetti_count
        FROM master_aziende ma
        LEFT JOIN clienti_aziende ca ON ma.id = ca.azienda_id AND ca.attivo = 1
        LEFT JOIN master_progetti mp ON ma.id = mp.azienda_id
        GROUP BY ma.id
        ORDER BY ma.nome
    ");
    $stmt->execute();
    $companies = $stmt->fetchAll();
    
    echo "<div class='table-responsive'>\n";
    echo "<table class='table table-hover'>\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th>Azienda</th>\n";
    echo "<th>Settore</th>\n";
    echo "<th>Clienti</th>\n";
    echo "<th>Progetti</th>\n";
    echo "<th>Stato</th>\n";
    echo "<th>Azioni</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    
    foreach ($companies as $comp) {
        $status_badge = $comp['attivo'] ? 'bg-success' : 'bg-secondary';
        $status_text = $comp['attivo'] ? 'Attiva' : 'Inattiva';
        
        echo "<tr>\n";
        echo "<td>\n";
        echo "<strong>{$comp['nome']}</strong>\n";
        if ($comp['nome_breve']) {
            echo "<br><small class='text-muted'>({$comp['nome_breve']})</small>\n";
        }
        echo "</td>\n";
        echo "<td>{$comp['settore']}</td>\n";
        echo "<td><span class='badge bg-info'>{$comp['clienti_count']}</span></td>\n";
        echo "<td><span class='badge bg-primary'>{$comp['progetti_count']}</span></td>\n";
        echo "<td><span class='badge $status_badge'>$status_text</span></td>\n";
        echo "<td>\n";
        echo "<div class='btn-group btn-group-sm'>\n";
        echo "<button class='btn btn-outline-primary' onclick='editCompany({$comp['id']})'>\n";
        echo "<i class='fas fa-edit'></i>\n";
        echo "</button>\n";
        echo "<button class='btn btn-outline-secondary' onclick='toggleStatus(\"master_aziende\", {$comp['id']}, {$comp['attivo']})'>\n";
        echo "<i class='fas fa-toggle-" . ($comp['attivo'] ? 'off' : 'on') . "'></i>\n";
        echo "</button>\n";
        echo "</div>\n";
        echo "</td>\n";
        echo "</tr>\n";
    }
    
    echo "</tbody>\n";
    echo "</table>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    // Form aggiungi azienda
    echo "<div class='col-lg-4'>\n";
    echo "<div class='card'>\n";
    echo "<div class='card-header'>\n";
    echo "<h6 class='mb-0'><i class='fas fa-plus me-2'></i>Aggiungi Azienda</h6>\n";
    echo "</div>\n";
    echo "<div class='card-body'>\n";
    echo "<form method='POST'>\n";
    echo "<input type='hidden' name='action' value='add_company'>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Nome Azienda</label>\n";
    echo "<input type='text' name='nome' class='form-control' required>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Nome Breve</label>\n";
    echo "<input type='text' name='nome_breve' class='form-control'>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Settore</label>\n";
    echo "<select name='settore' class='form-select'>\n";
    echo "<option value='Tecnologia'>Tecnologia</option>\n";
    echo "<option value='Manifatturiero'>Manifatturiero</option>\n";
    echo "<option value='Servizi IT'>Servizi IT</option>\n";
    echo "<option value='Consulenza'>Consulenza</option>\n";
    echo "<option value='Altro'>Altro</option>\n";
    echo "</select>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Email</label>\n";
    echo "<input type='email' name='email' class='form-control'>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Telefono</label>\n";
    echo "<input type='text' name='telefono' class='form-control'>\n";
    echo "</div>\n";
    echo "<button type='submit' class='btn btn-primary w-100'>\n";
    echo "<i class='fas fa-plus me-2'></i>Aggiungi\n";
    echo "</button>\n";
    echo "</form>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    echo "</div>\n";
    echo "</div>\n";
    
    // TAB 3: ASSOCIAZIONI (queue)
    echo "<div class='tab-pane fade' id='associations' role='tabpanel'>\n";
    echo "<div class='row mt-3'>\n";
    echo "<div class='col-12'>\n";
    echo "<div class='card'>\n";
    echo "<div class='card-header'>\n";
    echo "<h5 class='mb-0'><i class='fas fa-link me-2'></i>Coda Associazioni Cliente-Azienda</h5>\n";
    echo "</div>\n";
    echo "<div class='card-body'>\n";
    
    $stmt = $conn->prepare("
        SELECT aq.*, 
               mas.nome as azienda_suggerita_nome,
               maa.nome as azienda_assegnata_nome
        FROM association_queue aq
        LEFT JOIN master_aziende mas ON aq.azienda_suggerita_id = mas.id
        LEFT JOIN master_aziende maa ON aq.azienda_assegnata_id = maa.id
        WHERE aq.stato = 'pending'
        ORDER BY aq.confidenza_match DESC, aq.created_at ASC
    ");
    $stmt->execute();
    $queue_items = $stmt->fetchAll();
    
    if (empty($queue_items)) {
        echo "<div class='alert alert-info'>\n";
        echo "<i class='fas fa-info-circle me-2'></i>Nessuna associazione in attesa.\n";
        echo "Le nuove associazioni appariranno qui quando verranno rilevati nuovi clienti durante l'import.\n";
        echo "</div>\n";
    } else {
        foreach ($queue_items as $item) {
            $confidence_class = $item['confidenza_match'] >= 0.8 ? 'success' : ($item['confidenza_match'] >= 0.5 ? 'warning' : 'danger');
            
            echo "<div class='card queue-item pending mb-3'>\n";
            echo "<div class='card-body'>\n";
            echo "<div class='row align-items-center'>\n";
            echo "<div class='col-md-4'>\n";
            echo "<h6 class='mb-1'>{$item['nome_cliente']}</h6>\n";
            echo "<small class='text-muted'>Fonte: {$item['fonte_import']}</small>\n";
            if ($item['azienda_suggerita_nome']) {
                echo "<br><small><i class='fas fa-lightbulb me-1'></i>Suggerito: {$item['azienda_suggerita_nome']}</small>\n";
            }
            echo "</div>\n";
            echo "<div class='col-md-3'>\n";
            echo "<span class='badge bg-$confidence_class'>Confidenza: " . round($item['confidenza_match'] * 100) . "%</span>\n";
            echo "<br><small class='text-muted'>" . date('d/m/Y H:i', strtotime($item['created_at'])) . "</small>\n";
            echo "</div>\n";
            echo "<div class='col-md-5'>\n";
            echo "<form method='POST' class='d-flex gap-2'>\n";
            echo "<input type='hidden' name='action' value='associate_client'>\n";
            echo "<input type='hidden' name='nome_cliente' value='{$item['nome_cliente']}'>\n";
            echo "<select name='azienda_id' class='form-select form-select-sm' required>\n";
            echo "<option value=''>Seleziona azienda...</option>\n";
            
            foreach ($companies as $comp) {
                if ($comp['attivo']) {
                    $selected = ($comp['id'] == $item['azienda_suggerita_id']) ? 'selected' : '';
                    echo "<option value='{$comp['id']}' $selected>{$comp['nome']}</option>\n";
                }
            }
            
            echo "</select>\n";
            echo "<button type='submit' class='btn btn-sm btn-success'>\n";
            echo "<i class='fas fa-check'></i>\n";
            echo "</button>\n";
            echo "<button type='button' class='btn btn-sm btn-secondary' onclick='rejectAssociation({$item['id']})'>\n";
            echo "<i class='fas fa-times'></i>\n";
            echo "</button>\n";
            echo "</form>\n";
            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n";
        }
    }
    
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    // TAB 4: VEICOLI
    echo "<div class='tab-pane fade' id='vehicles' role='tabpanel'>\n";
    echo "<div class='row'>\n";
    echo "<div class='col-md-8'>\n";
    echo "<h5><i class='fas fa-car me-2'></i>Veicoli Configurati</h5>\n";
    
    // Recupera veicoli
    $stmt = $conn->prepare("SELECT * FROM master_veicoli_config ORDER BY nome");
    $stmt->execute();
    $vehicles = $stmt->fetchAll();
    
    if (empty($vehicles)) {
        echo "<div class='alert alert-warning'>\n";
        echo "<i class='fas fa-exclamation-triangle me-2'></i>Nessun veicolo configurato.\n";
        echo "</div>\n";
    } else {
        echo "<div class='row'>\n";
        foreach ($vehicles as $vehicle) {
            $status_class = $vehicle['attivo'] ? 'border-success' : 'border-secondary';
            $status_icon = $vehicle['attivo'] ? 'fa-check text-success' : 'fa-times text-secondary';
            
            echo "<div class='col-md-6 col-lg-4 mb-3'>\n";
            echo "<div class='card master-item $status_class'>\n";
            echo "<div class='card-body p-3'>\n";
            echo "<div class='d-flex justify-content-between align-items-start'>\n";
            echo "<div>\n";
            echo "<h6 class='card-title mb-1'>{$vehicle['nome']}</h6>\n";
            echo "<small class='text-muted'>{$vehicle['tipo']} • {$vehicle['marca']} {$vehicle['modello']}</small>\n";
            if ($vehicle['targa']) {
                echo "<br><small><i class='fas fa-id-card me-1'></i>{$vehicle['targa']}</small>\n";
            }
            echo "<br><small><i class='fas fa-euro-sign me-1'></i>€{$vehicle['costo_km']}/km</small>\n";
            echo "</div>\n";
            echo "<div class='dropdown'>\n";
            echo "<button class='btn btn-sm btn-outline-secondary dropdown-toggle' data-bs-toggle='dropdown'>\n";
            echo "<i class='fas $status_icon'></i>\n";
            echo "</button>\n";
            echo "<ul class='dropdown-menu'>\n";
            echo "<li><a class='dropdown-item' href='#' onclick='editVehicle({$vehicle['id']})'>\n";
            echo "<i class='fas fa-edit me-2'></i>Modifica\n";
            echo "</a></li>\n";
            echo "<li><a class='dropdown-item' href='#' onclick='toggleStatus(\"master_veicoli_config\", {$vehicle['id']}, {$vehicle['attivo']})'>\n";
            echo "<i class='fas fa-toggle-" . ($vehicle['attivo'] ? 'off' : 'on') . " me-2'></i>" . ($vehicle['attivo'] ? 'Disattiva' : 'Attiva') . "\n";
            echo "</a></li>\n";
            echo "</ul>\n";
            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n";
        }
        echo "</div>\n";
    }
    
    echo "</div>\n";
    echo "<div class='col-md-4'>\n";
    echo "<div class='card'>\n";
    echo "<div class='card-header'>\n";
    echo "<h6><i class='fas fa-plus me-2'></i>Aggiungi Veicolo</h6>\n";
    echo "</div>\n";
    echo "<div class='card-body'>\n";
    echo "<form method='POST' action='{$_SERVER['PHP_SELF']}'>\n";
    echo "<input type='hidden' name='action' value='add_vehicle'>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Nome</label>\n";
    echo "<input type='text' name='nome' class='form-control' required>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Tipo</label>\n";
    echo "<select name='tipo' class='form-select'>\n";
    echo "<option value='Auto'>Auto</option>\n";
    echo "<option value='Furgone'>Furgone</option>\n";
    echo "<option value='Camion'>Camion</option>\n";
    echo "<option value='Moto'>Moto</option>\n";
    echo "<option value='Altro'>Altro</option>\n";
    echo "</select>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Marca</label>\n";
    echo "<input type='text' name='marca' class='form-control'>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Modello</label>\n";
    echo "<input type='text' name='modello' class='form-control'>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Targa</label>\n";
    echo "<input type='text' name='targa' class='form-control'>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Costo/km (€)</label>\n";
    echo "<input type='number' name='costo_km' class='form-control' step='0.001' value='0.350'>\n";
    echo "</div>\n";
    echo "<button type='submit' class='btn btn-primary btn-sm w-100'>\n";
    echo "<i class='fas fa-save me-2'></i>Aggiungi Veicolo\n";
    echo "</button>\n";
    echo "</form>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    // TAB 5: CONFIGURAZIONI
    echo "<div class='tab-pane fade' id='config' role='tabpanel'>\n";
    echo "<div class='row'>\n";
    echo "<div class='col-md-8'>\n";
    echo "<h5><i class='fas fa-cog me-2'></i>Configurazioni Sistema</h5>\n";
    
    // Recupera configurazioni raggruppate per categoria
    $stmt = $conn->prepare("SELECT * FROM system_config ORDER BY categoria, chiave");
    $stmt->execute();
    $configs = $stmt->fetchAll();
    
    if (empty($configs)) {
        echo "<div class='alert alert-warning'>\n";
        echo "<i class='fas fa-exclamation-triangle me-2'></i>Nessuna configurazione trovata.\n";
        echo "</div>\n";
    } else {
        // Raggruppa per categoria
        $grouped_configs = [];
        foreach ($configs as $config) {
            $grouped_configs[$config['categoria']][] = $config;
        }
        
        foreach ($grouped_configs as $categoria => $categoria_configs) {
            echo "<div class='card mb-3'>\n";
            echo "<div class='card-header'>\n";
            echo "<h6><i class='fas fa-folder me-2'></i>" . ucfirst($categoria) . "</h6>\n";
            echo "</div>\n";
            echo "<div class='card-body'>\n";
            echo "<div class='table-responsive'>\n";
            echo "<table class='table table-sm'>\n";
            echo "<thead>\n";
            echo "<tr><th>Chiave</th><th>Valore</th><th>Tipo</th><th>Descrizione</th><th>Azioni</th></tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";
            
            foreach ($categoria_configs as $config) {
                $readonly = !$config['modificabile'] ? 'readonly' : '';
                $badge_class = $config['modificabile'] ? 'bg-success' : 'bg-secondary';
                
                echo "<tr>\n";
                echo "<td><code>{$config['chiave']}</code></td>\n";
                echo "<td>\n";
                
                if ($config['tipo'] === 'boolean') {
                    $checked = ($config['valore'] === '1' || $config['valore'] === 'true') ? 'checked' : '';
                    echo "<div class='form-check form-switch'>\n";
                    echo "<input type='checkbox' class='form-check-input' $checked $readonly onclick='updateConfig({$config['id']}, this.checked)'>\n";
                    echo "</div>\n";
                } else {
                    echo "<input type='text' class='form-control form-control-sm' value='" . htmlspecialchars($config['valore']) . "' $readonly onchange='updateConfig({$config['id']}, this.value)'>\n";
                }
                
                echo "</td>\n";
                echo "<td><span class='badge $badge_class'>{$config['tipo']}</span></td>\n";
                echo "<td><small class='text-muted'>{$config['descrizione']}</small></td>\n";
                echo "<td>\n";
                if ($config['modificabile']) {
                    echo "<button class='btn btn-sm btn-outline-primary' onclick='editConfig({$config['id']})'>\n";
                    echo "<i class='fas fa-edit'></i>\n";
                    echo "</button>\n";
                }
                echo "</td>\n";
                echo "</tr>\n";
            }
            
            echo "</tbody>\n";
            echo "</table>\n";
            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n";
        }
    }
    
    echo "</div>\n";
    echo "<div class='col-md-4'>\n";
    echo "<div class='card'>\n";
    echo "<div class='card-header'>\n";
    echo "<h6><i class='fas fa-plus me-2'></i>Aggiungi Configurazione</h6>\n";
    echo "</div>\n";
    echo "<div class='card-body'>\n";
    echo "<form method='POST' action='{$_SERVER['PHP_SELF']}'>\n";
    echo "<input type='hidden' name='action' value='add_config'>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Categoria</label>\n";
    echo "<select name='categoria' class='form-select'>\n";
    echo "<option value='sistema'>Sistema</option>\n";
    echo "<option value='ui'>Interfaccia</option>\n";
    echo "<option value='notifiche'>Notifiche</option>\n";
    echo "<option value='performance'>Performance</option>\n";
    echo "<option value='sicurezza'>Sicurezza</option>\n";
    echo "</select>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Chiave</label>\n";
    echo "<input type='text' name='chiave' class='form-control' required placeholder='nome_configurazione'>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Valore</label>\n";
    echo "<input type='text' name='valore' class='form-control' required>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Tipo</label>\n";
    echo "<select name='tipo' class='form-select'>\n";
    echo "<option value='string'>String</option>\n";
    echo "<option value='int'>Integer</option>\n";
    echo "<option value='float'>Float</option>\n";
    echo "<option value='boolean'>Boolean</option>\n";
    echo "<option value='json'>JSON</option>\n";
    echo "<option value='date'>Date</option>\n";
    echo "</select>\n";
    echo "</div>\n";
    echo "<div class='mb-3'>\n";
    echo "<label class='form-label'>Descrizione</label>\n";
    echo "<textarea name='descrizione' class='form-control' rows='2' placeholder='Descrizione della configurazione'></textarea>\n";
    echo "</div>\n";
    echo "<button type='submit' class='btn btn-primary btn-sm w-100'>\n";
    echo "<i class='fas fa-save me-2'></i>Aggiungi Configurazione\n";
    echo "</button>\n";
    echo "</form>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    echo "</div>\n"; // Close tab-content
    echo "</div>\n"; // Close container
    
} catch (Exception $e) {
    echo "<div class='container mt-4'>\n";
    echo "<div class='alert alert-danger'>\n";
    echo "<h4>❌ Errore Master Data Console</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
    echo "</div>\n";
}

// JavaScript
echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>\n";
echo "<script>\n";

// Funzioni JavaScript per gestione
echo "function toggleStatus(table, id, currentStatus) {\n";
echo "    if (confirm('Cambiare stato elemento?')) {\n";
echo "        const form = document.createElement('form');\n";
echo "        form.method = 'POST';\n";
echo "        form.innerHTML = `\n";
echo "            <input type='hidden' name='action' value='toggle_status'>\n";
echo "            <input type='hidden' name='table' value='\${table}'>\n";
echo "            <input type='hidden' name='id' value='\${id}'>\n";
echo "            <input type='hidden' name='current_status' value='\${currentStatus}'>\n";
echo "        `;\n";
echo "        document.body.appendChild(form);\n";
echo "        form.submit();\n";
echo "    }\n";
echo "}\n";

echo "function editEmployee(id) {\n";
echo "    // Implementazione semplice: prompt per ora\n";
echo "    const nome = prompt('Nuovo nome (lascia vuoto per non modificare):');\n";
echo "    const cognome = prompt('Nuovo cognome (lascia vuoto per non modificare):');\n";
echo "    const email = prompt('Nuova email (lascia vuoto per non modificare):');\n";
echo "    const ruolo = prompt('Nuovo ruolo (lascia vuoto per non modificare):');\n";
echo "    const costo = prompt('Nuovo costo giornaliero (lascia vuoto per non modificare):');\n";
echo "    \n";
echo "    if (nome || cognome || email || ruolo || costo) {\n";
echo "        const form = document.createElement('form');\n";
echo "        form.method = 'POST';\n";
echo "        form.style.display = 'none';\n";
echo "        form.innerHTML = `\n";
echo "            <input type='hidden' name='action' value='edit_employee'>\n";
echo "            <input type='hidden' name='id' value='\${id}'>\n";
echo "            <input type='hidden' name='nome' value='\${nome || \"\"}'>\n";
echo "            <input type='hidden' name='cognome' value='\${cognome || \"\"}'>\n";
echo "            <input type='hidden' name='email' value='\${email || \"\"}'>\n";
echo "            <input type='hidden' name='ruolo' value='\${ruolo || \"\"}'>\n";
echo "            <input type='hidden' name='costo_giornaliero' value='\${costo || \"\"}'>\n";
echo "        `;\n";
echo "        document.body.appendChild(form);\n";
echo "        form.submit();\n";
echo "    }\n";
echo "}\n";

echo "function editCompany(id) {\n";
echo "    // Implementazione semplice: prompt per ora\n";
echo "    const nome = prompt('Nuovo nome azienda (lascia vuoto per non modificare):');\n";
echo "    const nome_breve = prompt('Nuovo nome breve (lascia vuoto per non modificare):');\n";
echo "    const settore = prompt('Nuovo settore (lascia vuoto per non modificare):');\n";
echo "    const email = prompt('Nuova email (lascia vuoto per non modificare):');\n";
echo "    const telefono = prompt('Nuovo telefono (lascia vuoto per non modificare):');\n";
echo "    \n";
echo "    if (nome || nome_breve || settore || email || telefono) {\n";
echo "        const form = document.createElement('form');\n";
echo "        form.method = 'POST';\n";
echo "        form.style.display = 'none';\n";
echo "        form.innerHTML = `\n";
echo "            <input type='hidden' name='action' value='edit_company'>\n";
echo "            <input type='hidden' name='id' value='\${id}'>\n";
echo "            <input type='hidden' name='nome' value='\${nome || \"\"}'>\n";
echo "            <input type='hidden' name='nome_breve' value='\${nome_breve || \"\"}'>\n";
echo "            <input type='hidden' name='settore' value='\${settore || \"\"}'>\n";
echo "            <input type='hidden' name='email' value='\${email || \"\"}'>\n";
echo "            <input type='hidden' name='telefono' value='\${telefono || \"\"}'>\n";
echo "        `;\n";
echo "        document.body.appendChild(form);\n";
echo "        form.submit();\n";
echo "    }\n";
echo "}\n";

echo "function rejectAssociation(id) {\n";
echo "    if (confirm('Sei sicuro di voler rifiutare questa associazione?')) {\n";
echo "        const form = document.createElement('form');\n";
echo "        form.method = 'POST';\n";
echo "        form.style.display = 'none';\n";
echo "        form.innerHTML = `\n";
echo "            <input type='hidden' name='action' value='reject_association'>\n";
echo "            <input type='hidden' name='id' value='\${id}'>\n";
echo "        `;\n";
echo "        document.body.appendChild(form);\n";
echo "        form.submit();\n";
echo "    }\n";
echo "}\n";

echo "function editVehicle(id) {\n";
echo "    // Implementazione semplice: prompt per ora\n";
echo "    const nome = prompt('Nuovo nome veicolo (lascia vuoto per non modificare):');\n";
echo "    const tipo = prompt('Nuovo tipo (Auto/Furgone/Camion/Moto/Altro, lascia vuoto per non modificare):');\n";
echo "    const marca = prompt('Nuova marca (lascia vuoto per non modificare):');\n";
echo "    const modello = prompt('Nuovo modello (lascia vuoto per non modificare):');\n";
echo "    const targa = prompt('Nuova targa (lascia vuoto per non modificare):');\n";
echo "    const costo_km = prompt('Nuovo costo/km (lascia vuoto per non modificare):');\n";
echo "    \n";
echo "    if (nome || tipo || marca || modello || targa || costo_km) {\n";
echo "        const form = document.createElement('form');\n";
echo "        form.method = 'POST';\n";
echo "        form.style.display = 'none';\n";
echo "        form.innerHTML = `\n";
echo "            <input type='hidden' name='action' value='edit_vehicle'>\n";
echo "            <input type='hidden' name='id' value='\${id}'>\n";
echo "            <input type='hidden' name='nome' value='\${nome || \"\"}'>\n";
echo "            <input type='hidden' name='tipo' value='\${tipo || \"\"}'>\n";
echo "            <input type='hidden' name='marca' value='\${marca || \"\"}'>\n";
echo "            <input type='hidden' name='modello' value='\${modello || \"\"}'>\n";
echo "            <input type='hidden' name='targa' value='\${targa || \"\"}'>\n";
echo "            <input type='hidden' name='costo_km' value='\${costo_km || \"\"}'>\n";
echo "        `;\n";
echo "        document.body.appendChild(form);\n";
echo "        form.submit();\n";
echo "    }\n";
echo "}\n";

echo "function editConfig(id) {\n";
echo "    const valore = prompt('Nuovo valore configurazione:');\n";
echo "    \n";
echo "    if (valore !== null) {\n";
echo "        const form = document.createElement('form');\n";
echo "        form.method = 'POST';\n";
echo "        form.style.display = 'none';\n";
echo "        form.innerHTML = `\n";
echo "            <input type='hidden' name='action' value='update_config'>\n";
echo "            <input type='hidden' name='id' value='\${id}'>\n";
echo "            <input type='hidden' name='valore' value='\${valore}'>\n";
echo "        `;\n";
echo "        document.body.appendChild(form);\n";
echo "        form.submit();\n";
echo "    }\n";
echo "}\n";

echo "function updateConfig(id, valore) {\n";
echo "    const form = document.createElement('form');\n";
echo "    form.method = 'POST';\n";
echo "    form.style.display = 'none';\n";
echo "    form.innerHTML = `\n";
echo "        <input type='hidden' name='action' value='update_config'>\n";
echo "        <input type='hidden' name='id' value='\${id}'>\n";
echo "        <input type='hidden' name='valore' value='\${valore}'>\n";
echo "    `;\n";
echo "    document.body.appendChild(form);\n";
echo "    form.submit();\n";
echo "}\n";

// Update queue count
echo "document.addEventListener('DOMContentLoaded', function() {\n";
echo "    const queueItems = document.querySelectorAll('.queue-item.pending');\n";
echo "    document.getElementById('queueCount').textContent = queueItems.length;\n";
echo "});\n";

echo "</script>\n";
echo "</body>\n</html>\n";
?>