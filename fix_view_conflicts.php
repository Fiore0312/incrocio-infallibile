<?php
require_once 'config/Database.php';

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Fix View Conflicts</title>\n";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
echo "</head>\n<body>\n";

echo "<div class='container mt-4'>\n";
echo "<h2><i class='fas fa-eye'></i> Fix View Conflicts</h2>\n";
echo "<p class='text-muted'>Risoluzione conflitti tra tabelle e viste</p>\n";

$execute_mode = isset($_GET['execute']) && $_GET['execute'] === 'yes';

if (!$execute_mode) {
    echo "<div class='alert alert-warning'>\n";
    echo "<h4>⚠️ Modalità Analisi</h4>\n";
    echo "<p>Questa operazione analizzerà i conflitti tra tabelle e viste senza modificare il database.</p>\n";
    echo "<a href='?execute=yes' class='btn btn-success'><i class='fas fa-play'></i> Esegui Correzioni Sicure</a>\n";
    echo "</div>\n";
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h3>🔍 Analisi Situazione Attuale</h3>\n";
    
    $problematic_tables = ['dipendenti', 'clienti', 'veicoli'];
    $analysis = [];
    
    foreach ($problematic_tables as $table) {
        // Verifica se è una tabella
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND TABLE_TYPE = 'BASE TABLE'");
        $stmt->execute([$table]);
        $is_table = $stmt->fetch()['count'] > 0;
        
        // Verifica se è una vista
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $is_view = $stmt->fetch()['count'] > 0;
        
        // Conta record se è tabella
        $record_count = 0;
        if ($is_table) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table");
            $stmt->execute();
            $record_count = $stmt->fetch()['count'];
        }
        
        $analysis[$table] = [
            'is_table' => $is_table,
            'is_view' => $is_view,
            'record_count' => $record_count,
            'status' => $is_table && $is_view ? 'CONFLICT' : ($is_table ? 'TABLE' : ($is_view ? 'VIEW' : 'MISSING'))
        ];
    }
    
    echo "<div class='table-responsive'>\n";
    echo "<table class='table table-striped'>\n";
    echo "<thead class='table-dark'>\n";
    echo "<tr><th>Nome</th><th>È Tabella</th><th>È Vista</th><th>Record</th><th>Status</th></tr>\n";
    echo "</thead>\n<tbody>\n";
    
    foreach ($analysis as $name => $info) {
        $status_class = $info['status'] === 'CONFLICT' ? 'table-danger' : ($info['status'] === 'TABLE' ? 'table-success' : 'table-warning');
        echo "<tr class='$status_class'>\n";
        echo "<td><strong>$name</strong></td>\n";
        echo "<td>" . ($info['is_table'] ? '✅' : '❌') . "</td>\n";
        echo "<td>" . ($info['is_view'] ? '✅' : '❌') . "</td>\n";
        echo "<td>" . number_format($info['record_count']) . "</td>\n";
        echo "<td><span class='badge bg-" . ($info['status'] === 'CONFLICT' ? 'danger' : ($info['status'] === 'TABLE' ? 'success' : 'warning')) . "'>{$info['status']}</span></td>\n";
        echo "</tr>\n";
    }
    
    echo "</tbody></table>\n";
    echo "</div>\n";
    
    // Verifica esistenza tabelle master
    echo "<h3>📋 Verifica Tabelle Master</h3>\n";
    
    $master_tables = ['master_dipendenti_fixed', 'master_aziende', 'master_veicoli_config'];
    $master_analysis = [];
    
    foreach ($master_tables as $table) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $exists = $stmt->fetch()['count'] > 0;
        
        $record_count = 0;
        if ($exists) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table");
            $stmt->execute();
            $record_count = $stmt->fetch()['count'];
        }
        
        $master_analysis[$table] = [
            'exists' => $exists,
            'record_count' => $record_count
        ];
    }
    
    echo "<div class='table-responsive'>\n";
    echo "<table class='table table-striped'>\n";
    echo "<thead class='table-dark'>\n";
    echo "<tr><th>Tabella Master</th><th>Esiste</th><th>Record</th></tr>\n";
    echo "</thead>\n<tbody>\n";
    
    foreach ($master_analysis as $name => $info) {
        $status_class = $info['exists'] ? 'table-success' : 'table-danger';
        echo "<tr class='$status_class'>\n";
        echo "<td><strong>$name</strong></td>\n";
        echo "<td>" . ($info['exists'] ? '✅' : '❌') . "</td>\n";
        echo "<td>" . number_format($info['record_count']) . "</td>\n";
        echo "</tr>\n";
    }
    
    echo "</tbody></table>\n";
    echo "</div>\n";
    
    if ($execute_mode) {
        echo "<h3>🔧 Esecuzione Correzioni Sicure</h3>\n";
        
        $corrections = 0;
        
        // Strategia: Rinomina le tabelle legacy e lascia che le viste prendano il loro posto
        foreach ($problematic_tables as $table) {
            if ($analysis[$table]['is_table'] && $analysis[$table]['record_count'] > 0) {
                try {
                    // Rinomina tabella legacy
                    $backup_name = $table . '_legacy_backup';
                    $conn->exec("RENAME TABLE $table TO $backup_name");
                    
                    echo "<div class='alert alert-success'>\n";
                    echo "<p>✅ Tabella <strong>$table</strong> rinominata in <strong>$backup_name</strong> (conservati {$analysis[$table]['record_count']} record)</p>\n";
                    echo "</div>\n";
                    
                    $corrections++;
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>\n";
                    echo "<p>❌ Errore rinominando $table: " . htmlspecialchars($e->getMessage()) . "</p>\n";
                    echo "</div>\n";
                }
            }
        }
        
        // Ora crea le viste se le tabelle master esistono
        if ($master_analysis['master_dipendenti_fixed']['exists']) {
            try {
                $conn->exec("
                    CREATE OR REPLACE VIEW `dipendenti` AS
                    SELECT 
                        id, nome, cognome, email, costo_giornaliero, ruolo, attivo, 
                        data_assunzione, created_at, updated_at
                    FROM `master_dipendenti_fixed`
                    WHERE attivo = 1
                ");
                
                echo "<div class='alert alert-success'>\n";
                echo "<p>✅ Vista <strong>dipendenti</strong> creata con successo</p>\n";
                echo "</div>\n";
                
                $corrections++;
                
            } catch (Exception $e) {
                echo "<div class='alert alert-danger'>\n";
                echo "<p>❌ Errore creando vista dipendenti: " . htmlspecialchars($e->getMessage()) . "</p>\n";
                echo "</div>\n";
            }
        }
        
        if ($master_analysis['master_aziende']['exists']) {
            try {
                $conn->exec("
                    CREATE OR REPLACE VIEW `clienti` AS
                    SELECT 
                        id, nome, indirizzo, citta, provincia, codice_gestionale, attivo,
                        created_at, updated_at
                    FROM `master_aziende`
                    WHERE attivo = 1
                ");
                
                echo "<div class='alert alert-success'>\n";
                echo "<p>✅ Vista <strong>clienti</strong> creata con successo</p>\n";
                echo "</div>\n";
                
                $corrections++;
                
            } catch (Exception $e) {
                echo "<div class='alert alert-danger'>\n";
                echo "<p>❌ Errore creando vista clienti: " . htmlspecialchars($e->getMessage()) . "</p>\n";
                echo "</div>\n";
            }
        }
        
        if ($master_analysis['master_veicoli_config']['exists']) {
            try {
                $conn->exec("
                    CREATE OR REPLACE VIEW `veicoli` AS
                    SELECT 
                        id, nome, targa, modello, costo_km, attivo, created_at
                    FROM `master_veicoli_config`
                    WHERE attivo = 1
                ");
                
                echo "<div class='alert alert-success'>\n";
                echo "<p>✅ Vista <strong>veicoli</strong> creata con successo</p>\n";
                echo "</div>\n";
                
                $corrections++;
                
            } catch (Exception $e) {
                echo "<div class='alert alert-danger'>\n";
                echo "<p>❌ Errore creando vista veicoli: " . htmlspecialchars($e->getMessage()) . "</p>\n";
                echo "</div>\n";
            }
        }
        
        echo "<div class='alert alert-success'>\n";
        echo "<h4>🎉 Correzioni Completate</h4>\n";
        echo "<p><strong>Operazioni eseguite:</strong> $corrections</p>\n";
        echo "<p>Le tabelle legacy sono state conservate con suffisso _legacy_backup</p>\n";
        echo "<p>Le viste ora puntano alle tabelle master</p>\n";
        echo "</div>\n";
        
    } else {
        echo "<h3>💡 Piano di Correzione Sicuro</h3>\n";
        echo "<div class='alert alert-info'>\n";
        echo "<h4>Strategia Raccomandata</h4>\n";
        echo "<ol>\n";
        echo "<li><strong>Backup Sicuro:</strong> Rinomina tabelle legacy in *_legacy_backup</li>\n";
        echo "<li><strong>Crea Viste:</strong> Crea viste che puntano alle tabelle master</li>\n";
        echo "<li><strong>Conserva Dati:</strong> Nessun dato viene perso</li>\n";
        echo "<li><strong>Compatibilità:</strong> Il codice esistente continua a funzionare</li>\n";
        echo "</ol>\n";
        echo "<p><strong>Sicuro:</strong> Nessun dato viene eliminato, solo rinominato.</p>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>\n";
    echo "<h4>❌ Errore</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "</div>\n";
echo "</body>\n</html>\n";
?>