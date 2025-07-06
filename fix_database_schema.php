<?php
require_once 'config/Database.php';

/**
 * Fix Database Schema - Corregge Schema Database
 * Aggiunge colonne mancanti e crea tabelle necessarie
 */

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>Correzione Schema Database</title>\n";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
echo "<link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>\n";
echo "</head>\n<body>\n";

echo "<nav class='navbar navbar-expand-lg navbar-dark bg-success'>\n";
echo "<div class='container-fluid'>\n";
echo "<a class='navbar-brand' href='index.php'>\n";
echo "<i class='fas fa-wrench me-2'></i>Correzione Schema Database\n";
echo "</a>\n";
echo "<div class='navbar-nav ms-auto'>\n";
echo "<a class='nav-link' href='index.php'><i class='fas fa-home me-1'></i>Dashboard</a>\n";
echo "<a class='nav-link' href='smart_upload_final.php'><i class='fas fa-magic me-1'></i>Smart Upload</a>\n";
echo "</div>\n";
echo "</div>\n";
echo "</nav>\n";

echo "<div class='container mt-4'>\n";
echo "<h1><i class='fas fa-database me-2'></i>Correzione Schema Database</h1>\n";
echo "<p class='text-muted'>Risolve automaticamente problemi di schema come colonne mancanti</p>\n";

$fixes_applied = [];
$errors = [];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<div class='alert alert-info'>\n";
    echo "<h5><i class='fas fa-info-circle me-2'></i>Inizio Correzione Schema</h5>\n";
    echo "<p>Verifica e correzione automatica delle tabelle...</p>\n";
    echo "</div>\n";
    
    // 1. Verifica e crea master_aziende
    $stmt = $conn->prepare("SHOW TABLES LIKE 'master_aziende'");
    $stmt->execute();
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "<div class='alert alert-warning'>\n";
        echo "<h6><i class='fas fa-exclamation-triangle me-2'></i>Creazione master_aziende</h6>\n";
        echo "<p>Tabella master_aziende non trovata. Creazione in corso...</p>\n";
        echo "</div>\n";
        
        $create_table_sql = "
        CREATE TABLE master_aziende (
          id int(11) NOT NULL AUTO_INCREMENT,
          nome varchar(255) NOT NULL,
          nome_breve varchar(50) DEFAULT NULL,
          settore varchar(100) DEFAULT NULL,
          attivo tinyint(1) DEFAULT 1,
          created_at timestamp DEFAULT CURRENT_TIMESTAMP,
          updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY nome_unique (nome),
          INDEX idx_attivo (attivo),
          INDEX idx_settore (settore)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $conn->exec($create_table_sql);
        $fixes_applied[] = "Creata tabella master_aziende";
        
        // Inserisci alcuni dati di esempio
        $insert_sql = "
        INSERT INTO master_aziende (nome, nome_breve, settore) VALUES
        ('Azienda Alpha S.r.l.', 'Alpha', 'Servizi'),
        ('Beta Solutions', 'Beta', 'Tecnologia'),
        ('Gamma Industries', 'Gamma', 'Industria'),
        ('Delta Corp', 'Delta', 'Consulenza'),
        ('Epsilon Group', 'Epsilon', 'Marketing')
        ";
        $conn->exec($insert_sql);
        $fixes_applied[] = "Inseriti 5 dati di esempio in master_aziende";
        
    } else {
        // Verifica se esiste colonna nome_breve
        $stmt = $conn->prepare("SHOW COLUMNS FROM master_aziende LIKE 'nome_breve'");
        $stmt->execute();
        $column_exists = $stmt->fetch();
        
        if (!$column_exists) {
            echo "<div class='alert alert-warning'>\n";
            echo "<h6><i class='fas fa-plus me-2'></i>Aggiunta colonna nome_breve</h6>\n";
            echo "<p>Colonna nome_breve mancante in master_aziende. Aggiunta in corso...</p>\n";
            echo "</div>\n";
            
            $alter_sql = "ALTER TABLE master_aziende ADD COLUMN nome_breve varchar(50) DEFAULT NULL AFTER nome";
            $conn->exec($alter_sql);
            $fixes_applied[] = "Aggiunta colonna 'nome_breve' a master_aziende";
            
            // Aggiorna alcune righe esistenti con nome_breve se ci sono dati
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM master_aziende");
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            
            if ($count > 0) {
                $update_sql = "
                UPDATE master_aziende 
                SET nome_breve = CASE 
                    WHEN nome LIKE '%S.r.l.%' THEN REPLACE(REPLACE(nome, ' S.r.l.', ''), ' S.R.L.', '')
                    WHEN nome LIKE '%S.p.A.%' THEN REPLACE(REPLACE(nome, ' S.p.A.', ''), ' S.P.A.', '')
                    WHEN nome LIKE '%Solutions%' THEN REPLACE(nome, ' Solutions', '')
                    WHEN nome LIKE '%Corp%' THEN REPLACE(nome, ' Corp', '')
                    WHEN nome LIKE '%Group%' THEN REPLACE(nome, ' Group', '')
                    ELSE SUBSTRING(nome, 1, 20)
                END
                WHERE nome_breve IS NULL
                ";
                $conn->exec($update_sql);
                $fixes_applied[] = "Aggiornati nomi brevi automaticamente per righe esistenti";
            }
        }
    }
    
    // 2. Verifica e crea master_dipendenti_fixed
    $stmt = $conn->prepare("SHOW TABLES LIKE 'master_dipendenti_fixed'");
    $stmt->execute();
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "<div class='alert alert-warning'>\n";
        echo "<h6><i class='fas fa-users me-2'></i>Creazione master_dipendenti_fixed</h6>\n";
        echo "<p>Tabella master_dipendenti_fixed non trovata. Creazione in corso...</p>\n";
        echo "</div>\n";
        
        $create_table_sql = "
        CREATE TABLE master_dipendenti_fixed (
          id int(11) NOT NULL AUTO_INCREMENT,
          nome varchar(50) NOT NULL,
          cognome varchar(50) NOT NULL,
          nome_completo varchar(101) GENERATED ALWAYS AS (CONCAT(nome, ' ', cognome)) STORED,
          email varchar(100) DEFAULT NULL,
          costo_giornaliero decimal(8,2) DEFAULT 80.00,
          ruolo enum('tecnico','manager','amministrativo') DEFAULT 'tecnico',
          attivo tinyint(1) DEFAULT 1,
          data_assunzione date DEFAULT NULL,
          fonte_origine enum('csv','manual','teamviewer','calendar') DEFAULT 'manual',
          note_parsing text DEFAULT NULL COMMENT 'Note su come è stato parsato il nome dal CSV',
          created_at timestamp DEFAULT CURRENT_TIMESTAMP,
          updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY nome_cognome_unique (nome, cognome),
          UNIQUE KEY nome_completo_unique (nome_completo),
          INDEX idx_attivo (attivo),
          INDEX idx_fonte (fonte_origine),
          FULLTEXT KEY ft_nome_completo (nome_completo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $conn->exec($create_table_sql);
        $fixes_applied[] = "Creata tabella master_dipendenti_fixed";
        
        // Inserisci i 15 dipendenti fissi
        $dipendenti_fissi = [
            ['Franco', 'Gasparini', 'franco@company.com', 'manager'],
            ['Mario', 'Rossi', 'mario.rossi@company.com', 'tecnico'],
            ['Giuseppe', 'Verdi', 'giuseppe.verdi@company.com', 'tecnico'],
            ['Antonio', 'Bianchi', 'antonio.bianchi@company.com', 'tecnico'],
            ['Giovanni', 'Neri', 'giovanni.neri@company.com', 'tecnico'],
            ['Francesco', 'Ferrari', 'francesco.ferrari@company.com', 'tecnico'],
            ['Alessandro', 'Romano', 'alessandro.romano@company.com', 'tecnico'],
            ['Lorenzo', 'Galli', 'lorenzo.galli@company.com', 'tecnico'],
            ['Marco', 'Conti', 'marco.conti@company.com', 'tecnico'],
            ['Andrea', 'Ricci', 'andrea.ricci@company.com', 'tecnico'],
            ['Stefano', 'Marino', 'stefano.marino@company.com', 'tecnico'],
            ['Fabio', 'Greco', 'fabio.greco@company.com', 'tecnico'],
            ['Simone', 'Bruno', 'simone.bruno@company.com', 'tecnico'],
            ['Davide', 'Gatti', 'davide.gatti@company.com', 'amministrativo'],
            ['Matteo', 'Cattaneo', 'matteo.cattaneo@company.com', 'amministrativo']
        ];
        
        $insert_stmt = $conn->prepare("
            INSERT INTO master_dipendenti_fixed (nome, cognome, email, ruolo, costo_giornaliero) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($dipendenti_fissi as $dipendente) {
            $costo = ($dipendente[3] === 'manager') ? 120.00 : 
                    (($dipendente[3] === 'amministrativo') ? 70.00 : 80.00);
            $insert_stmt->execute([
                $dipendente[0], 
                $dipendente[1], 
                $dipendente[2], 
                $dipendente[3], 
                $costo
            ]);
        }
        
        $fixes_applied[] = "Inseriti 15 dipendenti fissi in master_dipendenti_fixed";
    }
    
    // 3. Verifica e crea association_queue
    $stmt = $conn->prepare("SHOW TABLES LIKE 'association_queue'");
    $stmt->execute();
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "<div class='alert alert-warning'>\n";
        echo "<h6><i class='fas fa-link me-2'></i>Creazione association_queue</h6>\n";
        echo "<p>Tabella association_queue non trovata. Creazione in corso...</p>\n";
        echo "</div>\n";
        
        $create_table_sql = "
        CREATE TABLE association_queue (
          id int(11) NOT NULL AUTO_INCREMENT,
          tipo_associazione enum('dipendente-azienda','cliente-azienda','progetto-azienda') NOT NULL,
          entita_sorgente varchar(255) NOT NULL,
          entita_target varchar(255) NOT NULL,
          confidenza decimal(3,2) NOT NULL DEFAULT 0.50,
          stato enum('pending','approved','rejected') DEFAULT 'pending',
          note text DEFAULT NULL,
          created_at timestamp DEFAULT CURRENT_TIMESTAMP,
          updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          INDEX idx_stato (stato),
          INDEX idx_tipo (tipo_associazione),
          INDEX idx_confidenza (confidenza)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $conn->exec($create_table_sql);
        $fixes_applied[] = "Creata tabella association_queue";
    }
    
    // Risultato finale
    if (!empty($fixes_applied)) {
        echo "<div class='alert alert-success'>\n";
        echo "<h5><i class='fas fa-check-circle me-2'></i>Correzioni Completate</h5>\n";
        echo "<ul>\n";
        foreach ($fixes_applied as $fix) {
            echo "<li>$fix</li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
        
        echo "<div class='alert alert-info'>\n";
        echo "<h6><i class='fas fa-rocket me-2'></i>Prossimi Passi</h6>\n";
        echo "<p>Schema database corretto! Ora puoi:</p>\n";
        echo "<div class='d-flex gap-2 flex-wrap'>\n";
        echo "<a href='test_smart_parser.php' class='btn btn-primary btn-sm'><i class='fas fa-flask me-1'></i>Testare Smart Parser</a>\n";
        echo "<a href='smart_upload_final.php' class='btn btn-success btn-sm'><i class='fas fa-magic me-1'></i>Usare Smart Upload</a>\n";
        echo "<a href='analyze_current_issues.php' class='btn btn-info btn-sm'><i class='fas fa-search me-1'></i>Analizzare Sistema</a>\n";
        echo "</div>\n";
        echo "</div>\n";
    } else {
        echo "<div class='alert alert-success'>\n";
        echo "<h5><i class='fas fa-check-circle me-2'></i>Schema già Corretto</h5>\n";
        echo "<p>Tutte le tabelle e colonne necessarie sono già presenti.</p>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>\n";
    echo "<h5><i class='fas fa-exclamation-circle me-2'></i>Errore durante Correzione</h5>\n";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
    $errors[] = $e->getMessage();
}

echo "</div>\n"; // Close container

echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>\n";
echo "</body>\n</html>\n";
?>