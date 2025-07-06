<?php
require_once 'config/Database.php';

/**
 * Check Database Schema - Verifica Schema Database
 * Controlla la struttura delle tabelle e individua colonne mancanti
 */

echo "<!DOCTYPE html>\n<html lang='it'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>Verifica Schema Database</title>\n";
echo "<style>table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; } th { background-color: #f2f2f2; }</style>\n";
echo "</head>\n<body>\n";

echo "<h1>🔍 Verifica Schema Database</h1>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Controlla tabelle esistenti
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>📋 Tabelle Esistenti</h2>\n";
    echo "<ul>\n";
    foreach ($tables as $table) {
        echo "<li>$table</li>\n";
    }
    echo "</ul>\n";
    
    // Controlla schema master_aziende (dove manca nome_breve)
    if (in_array('master_aziende', $tables)) {
        echo "<h2>🏢 Schema master_aziende</h2>\n";
        
        $stmt = $conn->prepare("DESCRIBE master_aziende");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>\n";
        echo "<tr><th>Colonna</th><th>Tipo</th><th>Null</th><th>Default</th></tr>\n";
        
        $has_nome_breve = false;
        foreach ($columns as $column) {
            echo "<tr>\n";
            echo "<td>{$column['Field']}</td>\n";
            echo "<td>{$column['Type']}</td>\n";
            echo "<td>{$column['Null']}</td>\n";
            echo "<td>{$column['Default']}</td>\n";
            echo "</tr>\n";
            
            if ($column['Field'] === 'nome_breve') {
                $has_nome_breve = true;
            }
        }
        echo "</table>\n";
        
        if (!$has_nome_breve) {
            echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>\n";
            echo "<h3>❌ PROBLEMA TROVATO</h3>\n";
            echo "<p><strong>Colonna 'nome_breve' mancante in master_aziende</strong></p>\n";
            echo "<p>Questo causa l'errore SQLSTATE[42S22] in SmartCsvParser.php</p>\n";
            echo "</div>\n";
        } else {
            echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>\n";
            echo "<h3>✅ Schema OK</h3>\n";
            echo "<p>Colonna 'nome_breve' presente</p>\n";
            echo "</div>\n";
        }
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h3>❌ TABELLA MANCANTE</h3>\n";
        echo "<p>Tabella 'master_aziende' non trovata</p>\n";
        echo "</div>\n";
    }
    
    // Controlla master_dipendenti_fixed
    if (in_array('master_dipendenti_fixed', $tables)) {
        echo "<h2>👥 Schema master_dipendenti_fixed</h2>\n";
        
        $stmt = $conn->prepare("DESCRIBE master_dipendenti_fixed");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>\n";
        echo "<tr><th>Colonna</th><th>Tipo</th><th>Null</th><th>Default</th></tr>\n";
        
        foreach ($columns as $column) {
            echo "<tr>\n";
            echo "<td>{$column['Field']}</td>\n";
            echo "<td>{$column['Type']}</td>\n";
            echo "<td>{$column['Null']}</td>\n";
            echo "<td>{$column['Default']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h3>❌ TABELLA MANCANTE</h3>\n";
        echo "<p>Tabella 'master_dipendenti_fixed' non trovata</p>\n";
        echo "</div>\n";
    }
    
    // Soluzione proposta
    echo "<h2>🔧 Soluzioni Proposte</h2>\n";
    
    if (!in_array('master_aziende', $tables) || !$has_nome_breve) {
        echo "<div style='background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h3>💡 Soluzione per master_aziende</h3>\n";
        echo "<p>Eseguire questo comando SQL:</p>\n";
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>\n";
        
        if (!in_array('master_aziende', $tables)) {
            echo "-- Creare tabella master_aziende\n";
            echo "CREATE TABLE master_aziende (\n";
            echo "  id int(11) NOT NULL AUTO_INCREMENT,\n";
            echo "  nome varchar(255) NOT NULL,\n";
            echo "  nome_breve varchar(50) DEFAULT NULL,\n";
            echo "  settore varchar(100) DEFAULT NULL,\n";
            echo "  attivo tinyint(1) DEFAULT 1,\n";
            echo "  created_at timestamp DEFAULT CURRENT_TIMESTAMP,\n";
            echo "  updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
            echo "  PRIMARY KEY (id),\n";
            echo "  UNIQUE KEY nome_unique (nome)\n";
            echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";
        } else {
            echo "-- Aggiungere colonna nome_breve\n";
            echo "ALTER TABLE master_aziende ADD COLUMN nome_breve varchar(50) DEFAULT NULL AFTER nome;\n";
        }
        echo "</pre>\n";
        echo "</div>\n";
    }
    
    if (!in_array('master_dipendenti_fixed', $tables)) {
        echo "<div style='background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h3>💡 Soluzione per master_dipendenti_fixed</h3>\n";
        echo "<p>Eseguire questo comando SQL:</p>\n";
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>\n";
        echo "-- Creare tabella master_dipendenti_fixed\n";
        echo "CREATE TABLE master_dipendenti_fixed (\n";
        echo "  id int(11) NOT NULL AUTO_INCREMENT,\n";
        echo "  nome varchar(50) NOT NULL,\n";
        echo "  cognome varchar(50) NOT NULL,\n";
        echo "  nome_completo varchar(101) GENERATED ALWAYS AS (CONCAT(nome, ' ', cognome)) STORED,\n";
        echo "  email varchar(100) DEFAULT NULL,\n";
        echo "  costo_giornaliero decimal(8,2) DEFAULT 80.00,\n";
        echo "  ruolo enum('tecnico','manager','amministrativo') DEFAULT 'tecnico',\n";
        echo "  attivo tinyint(1) DEFAULT 1,\n";
        echo "  data_assunzione date DEFAULT NULL,\n";
        echo "  fonte_origine enum('csv','manual','teamviewer','calendar') DEFAULT 'manual',\n";
        echo "  note_parsing text DEFAULT NULL,\n";
        echo "  created_at timestamp DEFAULT CURRENT_TIMESTAMP,\n";
        echo "  updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
        echo "  PRIMARY KEY (id),\n";
        echo "  UNIQUE KEY nome_cognome_unique (nome, cognome),\n";
        echo "  UNIQUE KEY nome_completo_unique (nome_completo)\n";
        echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";
        echo "</pre>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h3>❌ ERRORE</h3>\n";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "</body>\n</html>\n";
?>