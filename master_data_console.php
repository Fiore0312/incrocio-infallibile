<?php
session_start();
require_once 'config/Database.php';
require_once 'classes/ImportLogger.php';

/**
 * Master Data Management Console - Versione Legacy Semplificata
 * UI per gestire dipendenti, clienti e veicoli usando schema legacy
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
                        INSERT INTO dipendenti (nome, cognome, email, ruolo, costo_giornaliero) 
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
                    
                case 'add_client':
                    $stmt = $conn->prepare("
                        INSERT INTO clienti (nome, indirizzo, citta, provincia, codice_gestionale) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['nome'],
                        $_POST['indirizzo'],
                        $_POST['citta'],
                        $_POST['provincia'],
                        $_POST['codice_gestionale']
                    ]);
                    $message = "Cliente aggiunto con successo";
                    $message_type = 'success';
                    break;
                    
                case 'add_vehicle':
                    $stmt = $conn->prepare("
                        INSERT INTO veicoli (nome, targa, modello, costo_km) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['nome'],
                        $_POST['targa'],
                        $_POST['modello'],
                        $_POST['costo_km']
                    ]);
                    $message = "Veicolo aggiunto con successo";
                    $message_type = 'success';
                    break;
                    
                case 'delete_employee':
                    $stmt = $conn->prepare("DELETE FROM dipendenti WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $message = "Dipendente eliminato";
                    $message_type = 'success';
                    break;
                    
                case 'delete_client':
                    $stmt = $conn->prepare("DELETE FROM clienti WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $message = "Cliente eliminato";
                    $message_type = 'success';
                    break;
                    
                case 'delete_vehicle':
                    $stmt = $conn->prepare("DELETE FROM veicoli WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $message = "Veicolo eliminato";
                    $message_type = 'success';
                    break;
            }
        } catch (Exception $e) {
            $message = "Errore: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
    
    // Get counts for tabs
    $stmt = $conn->query("SELECT COUNT(*) as count FROM dipendenti WHERE attivo = 1");
    $dipendenti_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM clienti WHERE attivo = 1");
    $clienti_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM veicoli WHERE attivo = 1");
    $veicoli_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Show message if any
    if ($message) {
        echo "<div class='container mt-3'>";
        echo "<div class='alert alert-{$message_type} alert-dismissible fade show' role='alert'>";
        echo htmlspecialchars($message);
        echo "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>";
        echo "</div>";
        echo "</div>";
    }
    
    echo "<div class='container-fluid mt-4'>";
    echo "<div class='row'>";
    echo "<div class='col-12'>";
    
    // Tabs
    echo "<ul class='nav nav-tabs' id='masterTabs' role='tablist'>";
    echo "<li class='nav-item' role='presentation'>";
    echo "<button class='nav-link active' id='dipendenti-tab' data-bs-toggle='tab' data-bs-target='#dipendenti' type='button'>";
    echo "<i class='fas fa-users me-2'></i>Dipendenti ({$dipendenti_count})";
    echo "</button></li>";
    echo "<li class='nav-item' role='presentation'>";
    echo "<button class='nav-link' id='clienti-tab' data-bs-toggle='tab' data-bs-target='#clienti' type='button'>";
    echo "<i class='fas fa-building me-2'></i>Clienti ({$clienti_count})";
    echo "</button></li>";
    echo "<li class='nav-item' role='presentation'>";
    echo "<button class='nav-link' id='veicoli-tab' data-bs-toggle='tab' data-bs-target='#veicoli' type='button'>";
    echo "<i class='fas fa-car me-2'></i>Veicoli ({$veicoli_count})";
    echo "</button></li>";
    echo "</ul>";
    
    echo "<div class='tab-content' id='masterTabsContent'>";
    
    // DIPENDENTI TAB
    echo "<div class='tab-pane fade show active' id='dipendenti'>";
    echo "<div class='row mt-3'>";
    echo "<div class='col-md-6'>";
    echo "<div class='card'>";
    echo "<div class='card-header'><i class='fas fa-user-plus me-2'></i>Aggiungi Dipendente</div>";
    echo "<div class='card-body'>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='action' value='add_employee'>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Nome</label>";
    echo "<input type='text' class='form-control' name='nome' required>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Cognome</label>";
    echo "<input type='text' class='form-control' name='cognome' required>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Email</label>";
    echo "<input type='email' class='form-control' name='email'>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Ruolo</label>";
    echo "<select class='form-control' name='ruolo'>";
    echo "<option value='tecnico'>Tecnico</option>";
    echo "<option value='manager'>Manager</option>";
    echo "<option value='amministrativo'>Amministrativo</option>";
    echo "</select>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Costo Giornaliero (€)</label>";
    echo "<input type='number' step='0.01' class='form-control' name='costo_giornaliero' value='200.00'>";
    echo "</div>";
    echo "<button type='submit' class='btn btn-primary'><i class='fas fa-save me-2'></i>Salva</button>";
    echo "</form>";
    echo "</div></div></div>";
    
    echo "<div class='col-md-6'>";
    echo "<div class='card'>";
    echo "<div class='card-header'><i class='fas fa-list me-2'></i>Lista Dipendenti</div>";
    echo "<div class='card-body'>";
    $stmt = $conn->query("SELECT * FROM dipendenti ORDER BY cognome, nome");
    $dipendenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($dipendenti)) {
        echo "<p class='text-muted'>Nessun dipendente trovato. Inizia aggiungendo i 14 dipendenti.</p>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-sm'>";
        echo "<thead><tr><th>Nome</th><th>Ruolo</th><th>Costo €</th><th>Azioni</th></tr></thead>";
        echo "<tbody>";
        foreach ($dipendenti as $dip) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($dip['nome'] . ' ' . $dip['cognome']) . "</td>";
            echo "<td>" . htmlspecialchars($dip['ruolo']) . "</td>";
            echo "<td>€" . number_format($dip['costo_giornaliero'], 2) . "</td>";
            echo "<td>";
            echo "<form method='POST' style='display:inline;'>";
            echo "<input type='hidden' name='action' value='delete_employee'>";
            echo "<input type='hidden' name='id' value='" . $dip['id'] . "'>";
            echo "<button type='submit' class='btn btn-sm btn-outline-danger' onclick='return confirm(\"Sicuro?\");'>";
            echo "<i class='fas fa-trash'></i></button>";
            echo "</form>";
            echo "</td></tr>";
        }
        echo "</tbody></table>";
        echo "</div>";
    }
    echo "</div></div></div></div></div>";
    
    // CLIENTI TAB
    echo "<div class='tab-pane fade' id='clienti'>";
    echo "<div class='row mt-3'>";
    echo "<div class='col-md-6'>";
    echo "<div class='card'>";
    echo "<div class='card-header'><i class='fas fa-building-user me-2'></i>Aggiungi Cliente</div>";
    echo "<div class='card-body'>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='action' value='add_client'>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Nome</label>";
    echo "<input type='text' class='form-control' name='nome' required>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Indirizzo</label>";
    echo "<input type='text' class='form-control' name='indirizzo'>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Città</label>";
    echo "<input type='text' class='form-control' name='citta'>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Provincia</label>";
    echo "<input type='text' class='form-control' name='provincia' maxlength='2'>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Codice Gestionale</label>";
    echo "<input type='text' class='form-control' name='codice_gestionale'>";
    echo "</div>";
    echo "<button type='submit' class='btn btn-primary'><i class='fas fa-save me-2'></i>Salva</button>";
    echo "</form>";
    echo "</div></div></div>";
    
    echo "<div class='col-md-6'>";
    echo "<div class='card'>";
    echo "<div class='card-header'><i class='fas fa-list me-2'></i>Lista Clienti</div>";
    echo "<div class='card-body'>";
    $stmt = $conn->query("SELECT * FROM clienti ORDER BY nome");
    $clienti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($clienti)) {
        echo "<p class='text-muted'>Nessun cliente trovato.</p>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-sm'>";
        echo "<thead><tr><th>Nome</th><th>Città</th><th>Azioni</th></tr></thead>";
        echo "<tbody>";
        foreach ($clienti as $cli) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($cli['nome']) . "</td>";
            echo "<td>" . htmlspecialchars($cli['citta'] ?? '') . "</td>";
            echo "<td>";
            echo "<form method='POST' style='display:inline;'>";
            echo "<input type='hidden' name='action' value='delete_client'>";
            echo "<input type='hidden' name='id' value='" . $cli['id'] . "'>";
            echo "<button type='submit' class='btn btn-sm btn-outline-danger' onclick='return confirm(\"Sicuro?\");'>";
            echo "<i class='fas fa-trash'></i></button>";
            echo "</form>";
            echo "</td></tr>";
        }
        echo "</tbody></table>";
        echo "</div>";
    }
    echo "</div></div></div></div></div>";
    
    // VEICOLI TAB
    echo "<div class='tab-pane fade' id='veicoli'>";
    echo "<div class='row mt-3'>";
    echo "<div class='col-md-6'>";
    echo "<div class='card'>";
    echo "<div class='card-header'><i class='fas fa-car-side me-2'></i>Aggiungi Veicolo</div>";
    echo "<div class='card-body'>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='action' value='add_vehicle'>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Nome</label>";
    echo "<input type='text' class='form-control' name='nome' required>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Targa</label>";
    echo "<input type='text' class='form-control' name='targa'>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Modello</label>";
    echo "<input type='text' class='form-control' name='modello'>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<label class='form-label'>Costo per KM (€)</label>";
    echo "<input type='number' step='0.01' class='form-control' name='costo_km' value='0.35'>";
    echo "</div>";
    echo "<button type='submit' class='btn btn-primary'><i class='fas fa-save me-2'></i>Salva</button>";
    echo "</form>";
    echo "</div></div></div>";
    
    echo "<div class='col-md-6'>";
    echo "<div class='card'>";
    echo "<div class='card-header'><i class='fas fa-list me-2'></i>Lista Veicoli</div>";
    echo "<div class='card-body'>";
    $stmt = $conn->query("SELECT * FROM veicoli ORDER BY nome");
    $veicoli = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($veicoli)) {
        echo "<p class='text-muted'>Nessun veicolo trovato.</p>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-sm'>";
        echo "<thead><tr><th>Nome</th><th>Modello</th><th>Costo/KM</th><th>Azioni</th></tr></thead>";
        echo "<tbody>";
        foreach ($veicoli as $veic) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($veic['nome']) . "</td>";
            echo "<td>" . htmlspecialchars($veic['modello'] ?? '') . "</td>";
            echo "<td>€" . number_format($veic['costo_km'], 2) . "</td>";
            echo "<td>";
            echo "<form method='POST' style='display:inline;'>";
            echo "<input type='hidden' name='action' value='delete_vehicle'>";
            echo "<input type='hidden' name='id' value='" . $veic['id'] . "'>";
            echo "<button type='submit' class='btn btn-sm btn-outline-danger' onclick='return confirm(\"Sicuro?\");'>";
            echo "<i class='fas fa-trash'></i></button>";
            echo "</form>";
            echo "</td></tr>";
        }
        echo "</tbody></table>";
        echo "</div>";
    }
    echo "</div></div></div></div></div>";
    
    echo "</div>"; // End tab-content
    echo "</div></div></div>"; // End container
    
} catch (Exception $e) {
    echo "<div class='container mt-4'>";
    echo "<div class='alert alert-danger'>";
    echo "<h4>Errore Master Data Console</h4>";
    echo "<p>Errore: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    echo "</div>";
}

echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>";
echo "</body></html>";
?>