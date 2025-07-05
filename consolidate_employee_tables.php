<?php
require_once 'config/Database.php';

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Consolidamento Tabelle Dipendenti</title>\n";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
echo "</head>\n<body>\n";

echo "<div class='container mt-4'>\n";
echo "<h2><i class='fas fa-database'></i> Consolidamento Tabelle Dipendenti</h2>\n";
echo "<p class='text-muted'>Unificazione delle tabelle dipendenti in un'unica struttura master</p>\n";

// Modalità di esecuzione
$execute_mode = isset($_GET['execute']) && $_GET['execute'] === 'yes';
$backup_mode = isset($_GET['backup']) && $_GET['backup'] === 'yes';

if (!$execute_mode && !$backup_mode) {
    echo "<div class='alert alert-info'>\n";
    echo "<h4>🔍 Modalità Analisi</h4>\n";
    echo "<p>Stai visualizzando il piano di consolidamento senza eseguire modifiche.</p>\n";
    echo "<div class='btn-group' role='group'>\n";
    echo "<a href='?backup=yes' class='btn btn-warning'><i class='fas fa-download'></i> Esegui Backup</a>\n";
    echo "<a href='?execute=yes' class='btn btn-success'><i class='fas fa-play'></i> Esegui Consolidamento</a>\n";
    echo "</div>\n";
    echo "</div>\n";
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Step 1: Analizza tabelle esistenti
    echo "<h3>🔍 Step 1: Analisi Tabelle Esistenti</h3>\n";
    
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $employee_tables = [];
    foreach ($all_tables as $table) {
        if (strpos($table, 'dipendenti') !== false) {
            $employee_tables[] = $table;
        }
    }
    
    if (empty($employee_tables)) {
        echo "<div class='alert alert-warning'>\n";
        echo "<h4>⚠️ Nessuna Tabella Dipendenti Trovata</h4>\n";
        echo "<p>Non sono state trovate tabelle contenenti 'dipendenti'. Creazione tabella master da zero.</p>\n";
        echo "</div>\n";
        
        // Crea tabella master da zero
        if ($execute_mode) {
            $create_master_sql = "
                CREATE TABLE IF NOT EXISTS `master_dipendenti_consolidated` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `nome` varchar(50) NOT NULL,
                    `cognome` varchar(50) NOT NULL,
                    `nome_completo` varchar(101) GENERATED ALWAYS AS (CONCAT(nome, ' ', cognome)) STORED,
                    `email` varchar(100) DEFAULT NULL,
                    `costo_giornaliero` decimal(8,2) DEFAULT 80.00,
                    `ruolo` enum('tecnico','manager','amministrativo') DEFAULT 'tecnico',
                    `attivo` tinyint(1) DEFAULT 1,
                    `data_assunzione` date DEFAULT NULL,
                    `fonte_origine` enum('csv','manual','teamviewer','calendar','consolidation') DEFAULT 'consolidation',
                    `note_parsing` text DEFAULT NULL,
                    `tabella_origine` varchar(50) DEFAULT NULL,
                    `id_origine` int(11) DEFAULT NULL,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `nome_cognome_unique` (`nome`, `cognome`),
                    UNIQUE KEY `nome_completo_unique` (`nome_completo`),
                    INDEX `idx_attivo` (`attivo`),
                    INDEX `idx_fonte` (`fonte_origine`),
                    INDEX `idx_tabella_origine` (`tabella_origine`),
                    FULLTEXT KEY `ft_nome_completo` (`nome_completo`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $conn->exec($create_master_sql);
            echo "<div class='alert alert-success'>\n";
            echo "<h4>✅ Tabella Master Creata</h4>\n";
            echo "<p>Tabella <strong>master_dipendenti_consolidated</strong> creata con successo.</p>\n";
            echo "</div>\n";
        }
    } else {
        echo "<div class='alert alert-info'>\n";
        echo "<h4>📊 Tabelle Dipendenti Trovate</h4>\n";
        echo "<ul>\n";
        foreach ($employee_tables as $table) {
            // Conta record
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table");
            $stmt->execute();
            $count = $stmt->fetch();
            echo "<li><strong>$table</strong> - " . number_format($count['count']) . " record</li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
        
        // Step 2: Backup se richiesto
        if ($backup_mode) {
            echo "<h3>💾 Step 2: Backup Database</h3>\n";
            
            $backup_file = 'backups/database_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backup_dir = dirname($backup_file);
            
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            
            $backup_content = "-- Database Backup - " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($employee_tables as $table) {
                $backup_content .= "-- Table: $table\n";
                $backup_content .= "DROP TABLE IF EXISTS `{$table}_backup`;\n";
                $backup_content .= "CREATE TABLE `{$table}_backup` SELECT * FROM `$table`;\n\n";
            }
            
            file_put_contents($backup_file, $backup_content);
            
            echo "<div class='alert alert-success'>\n";
            echo "<h4>✅ Backup Completato</h4>\n";
            echo "<p>Backup salvato in: <strong>$backup_file</strong></p>\n";
            echo "</div>\n";
        }
        
        // Step 3: Consolidamento
        if ($execute_mode) {
            echo "<h3>🔄 Step 3: Consolidamento Dati</h3>\n";
            
            // Crea tabella consolidata
            $create_consolidated_sql = "
                CREATE TABLE IF NOT EXISTS `master_dipendenti_consolidated` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `nome` varchar(50) NOT NULL,
                    `cognome` varchar(50) NOT NULL,
                    `nome_completo` varchar(101) GENERATED ALWAYS AS (CONCAT(nome, ' ', cognome)) STORED,
                    `email` varchar(100) DEFAULT NULL,
                    `costo_giornaliero` decimal(8,2) DEFAULT 80.00,
                    `ruolo` enum('tecnico','manager','amministrativo') DEFAULT 'tecnico',
                    `attivo` tinyint(1) DEFAULT 1,
                    `data_assunzione` date DEFAULT NULL,
                    `fonte_origine` enum('csv','manual','teamviewer','calendar','consolidation') DEFAULT 'consolidation',
                    `note_parsing` text DEFAULT NULL,
                    `tabella_origine` varchar(50) DEFAULT NULL,
                    `id_origine` int(11) DEFAULT NULL,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `nome_cognome_unique` (`nome`, `cognome`),
                    UNIQUE KEY `nome_completo_unique` (`nome_completo`),
                    INDEX `idx_attivo` (`attivo`),
                    INDEX `idx_fonte` (`fonte_origine`),
                    INDEX `idx_tabella_origine` (`tabella_origine`),
                    FULLTEXT KEY `ft_nome_completo` (`nome_completo`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $conn->exec($create_consolidated_sql);
            echo "<div class='alert alert-success'>\n";
            echo "<h4>✅ Tabella Consolidata Creata</h4>\n";
            echo "</div>\n";
            
            // Migra dati da ogni tabella
            $total_migrated = 0;
            $duplicates_found = 0;
            
            foreach ($employee_tables as $table) {
                echo "<h4>📋 Migrazione da $table</h4>\n";
                
                // Ottieni struttura tabella
                $stmt = $conn->prepare("DESCRIBE $table");
                $stmt->execute();
                $columns = $stmt->fetchAll();
                
                $has_nome = false;
                $has_cognome = false;
                $available_columns = [];
                
                foreach ($columns as $column) {
                    $field = $column['Field'];
                    $available_columns[] = $field;
                    if ($field === 'nome') $has_nome = true;
                    if ($field === 'cognome') $has_cognome = true;
                }
                
                if (!$has_nome || !$has_cognome) {
                    echo "<div class='alert alert-warning'>\n";
                    echo "<p>⚠️ Tabella $table non ha campi nome/cognome. Saltata.</p>\n";
                    echo "</div>\n";
                    continue;
                }
                
                // Migra dati
                $select_columns = [];
                $insert_columns = [];
                $mapping = [
                    'nome' => 'nome',
                    'cognome' => 'cognome',
                    'email' => 'email',
                    'costo_giornaliero' => 'costo_giornaliero',
                    'ruolo' => 'ruolo',
                    'attivo' => 'attivo',
                    'data_assunzione' => 'data_assunzione',
                    'created_at' => 'created_at',
                    'updated_at' => 'updated_at'
                ];
                
                foreach ($mapping as $target => $source) {
                    if (in_array($source, $available_columns)) {
                        $select_columns[] = $source;
                        $insert_columns[] = $target;
                    } else {
                        $select_columns[] = 'NULL';
                        $insert_columns[] = $target;
                    }
                }
                
                $select_columns[] = "'$table'";
                $insert_columns[] = 'tabella_origine';
                $select_columns[] = 'id';
                $insert_columns[] = 'id_origine';
                
                $insert_sql = "
                    INSERT IGNORE INTO master_dipendenti_consolidated 
                    (" . implode(', ', $insert_columns) . ") 
                    SELECT " . implode(', ', $select_columns) . " 
                    FROM $table
                ";
                
                $stmt = $conn->prepare($insert_sql);
                $stmt->execute();
                $affected = $stmt->rowCount();
                
                echo "<div class='alert alert-info'>\n";
                echo "<p>✅ Migrati $affected record da $table</p>\n";
                echo "</div>\n";
                
                $total_migrated += $affected;
            }
            
            echo "<div class='alert alert-success'>\n";
            echo "<h4>🎉 Consolidamento Completato</h4>\n";
            echo "<p><strong>Totale record migrati:</strong> $total_migrated</p>\n";
            echo "<p><strong>Tabella finale:</strong> master_dipendenti_consolidated</p>\n";
            echo "</div>\n";
            
            // Statistiche finali
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM master_dipendenti_consolidated");
            $stmt->execute();
            $final_count = $stmt->fetch();
            
            echo "<div class='alert alert-info'>\n";
            echo "<h4>📊 Statistiche Finali</h4>\n";
            echo "<p><strong>Record finali:</strong> " . number_format($final_count['count']) . "</p>\n";
            echo "</div>\n";
        }
    }
    
    // Step 4: Piano di post-migrazione
    echo "<h3>📋 Step 4: Piano Post-Migrazione</h3>\n";
    echo "<div class='alert alert-warning'>\n";
    echo "<h4>⚠️ Azioni Raccomandate</h4>\n";
    echo "<ol>\n";
    echo "<li>Verificare l'integrità dei dati migrati</li>\n";
    echo "<li>Aggiornare le query dell'applicazione per usare la nuova tabella</li>\n";
    echo "<li>Testare tutte le funzionalità dipendenti</li>\n";
    echo "<li>Rimuovere le tabelle legacy dopo verifica</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>\n";
    echo "<h4>❌ Errore durante consolidamento</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "</div>\n";
echo "</body>\n</html>\n";
?>