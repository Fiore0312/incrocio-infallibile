<?php
require_once 'config/Database.php';

echo "<h2>🌱 Data Seeding - Popolazione Tabelle Master</h2>\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $conn->beginTransaction();
    
    // ===================================
    // 1. SEEDING DIPENDENTI MASTER
    // ===================================
    
    echo "<h3>👥 Seeding Dipendenti Master</h3>\n";
    
    // Dipendenti noti dall'analisi dell'utente
    $known_employees = [
        ['Franco', 'Fiorellino', 'franco.fiorellino@example.com', 'tecnico', '2020-01-15'],
        ['Matteo', 'Signo', 'matteo.signo@example.com', 'tecnico', '2020-02-01'],
        ['Arlind', 'Hoxha', 'arlind.hoxha@example.com', 'tecnico', '2020-03-01'],
        ['Lorenzo', 'Serratore', 'lorenzo.serratore@example.com', 'tecnico', '2020-04-01'],
        ['Alex', 'Ferrario', 'alex.ferrario@example.com', 'tecnico', '2020-05-01'],
        ['Roberto', 'Birocchi', 'roberto.birocchi@example.com', 'manager', '2019-01-01'],
        ['Gabriele', 'De Palma', 'gabriele.depalma@example.com', 'tecnico', '2020-06-01'],
        ['Marco', 'Birocchi', 'marco.birocchi@example.com', 'tecnico', '2020-07-01'],
        ['Davide', 'Cestone', 'davide.cestone@example.com', 'tecnico', '2020-08-01'],
        ['Matteo', 'Di Salvo', 'matteo.disalvo@example.com', 'tecnico', '2020-09-01']
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO master_dipendenti 
        (nome, cognome, email, ruolo, attivo, data_assunzione, fonte_origine, note_parsing) 
        VALUES (?, ?, ?, ?, 1, ?, 'manual', 'Dipendente noto - seeding iniziale')
        ON DUPLICATE KEY UPDATE 
        email = VALUES(email),
        ruolo = VALUES(ruolo),
        data_assunzione = VALUES(data_assunzione),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Nome</th><th>Cognome</th><th>Email</th><th>Ruolo</th><th>Stato</th></tr>\n";
    
    $inserted_employees = 0;
    foreach ($known_employees as $employee) {
        try {
            $stmt->execute($employee);
            $inserted_employees++;
            echo "<tr>\n";
            echo "<td><strong>{$employee[0]}</strong></td>\n";
            echo "<td>{$employee[1]}</td>\n";
            echo "<td>{$employee[2]}</td>\n";
            echo "<td>{$employee[3]}</td>\n";
            echo "<td style='color: green;'>✅ Inserito</td>\n";
            echo "</tr>\n";
        } catch (Exception $e) {
            echo "<tr>\n";
            echo "<td><strong>{$employee[0]}</strong></td>\n";
            echo "<td>{$employee[1]}</td>\n";
            echo "<td>{$employee[2]}</td>\n";
            echo "<td>{$employee[3]}</td>\n";
            echo "<td style='color: red;'>❌ " . htmlspecialchars($e->getMessage()) . "</td>\n";
            echo "</tr>\n";
        }
    }
    echo "</table>\n";
    
    // ===================================
    // 2. SEEDING VEICOLI MASTER
    // ===================================
    
    echo "<h3>🚗 Seeding Veicoli Master</h3>\n";
    
    // Veicoli noti dall'analisi dell'utente (nomi problematici identificati)
    $known_vehicles = [
        ['Punto', 'Punto', 'Fiat', 'AB123CD', 2018, 0.35],
        ['Fiesta', 'Fiesta', 'Ford', 'EF456GH', 2019, 0.35],
        ['Panda', 'Panda', 'Fiat', 'IJ789KL', 2017, 0.30],
        ['Clio', 'Clio', 'Renault', 'MN012OP', 2020, 0.35],
        ['Corsa', 'Corsa', 'Opel', 'QR345ST', 2018, 0.35],
        ['Polo', 'Polo', 'Volkswagen', 'UV678WX', 2019, 0.35],
        ['Yaris', 'Yaris', 'Toyota', 'YZ901AB', 2020, 0.35],
        ['Ibiza', 'Ibiza', 'Seat', 'CD234EF', 2018, 0.35],
        ['Micra', 'Micra', 'Nissan', 'GH567IJ', 2017, 0.30],
        ['C3', 'C3', 'Citroen', 'KL890MN', 2019, 0.35]
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO master_veicoli 
        (nome, modello, marca, targa, anno, costo_km, attivo, note) 
        VALUES (?, ?, ?, ?, ?, ?, 1, 'Veicolo aziendale - seeding iniziale')
        ON DUPLICATE KEY UPDATE 
        modello = VALUES(modello),
        marca = VALUES(marca),
        targa = VALUES(targa),
        anno = VALUES(anno),
        costo_km = VALUES(costo_km),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Nome</th><th>Modello</th><th>Marca</th><th>Targa</th><th>Anno</th><th>Stato</th></tr>\n";
    
    $inserted_vehicles = 0;
    foreach ($known_vehicles as $vehicle) {
        try {
            $stmt->execute($vehicle);
            $inserted_vehicles++;
            echo "<tr>\n";
            echo "<td><strong>{$vehicle[0]}</strong></td>\n";
            echo "<td>{$vehicle[1]}</td>\n";
            echo "<td>{$vehicle[2]}</td>\n";
            echo "<td>{$vehicle[3]}</td>\n";
            echo "<td>{$vehicle[4]}</td>\n";
            echo "<td style='color: green;'>✅ Inserito</td>\n";
            echo "</tr>\n";
        } catch (Exception $e) {
            echo "<tr>\n";
            echo "<td><strong>{$vehicle[0]}</strong></td>\n";
            echo "<td>{$vehicle[1]}</td>\n";
            echo "<td>{$vehicle[2]}</td>\n";
            echo "<td>{$vehicle[3]}</td>\n";
            echo "<td>{$vehicle[4]}</td>\n";
            echo "<td style='color: red;'>❌ " . htmlspecialchars($e->getMessage()) . "</td>\n";
            echo "</tr>\n";
        }
    }
    echo "</table>\n";
    
    // ===================================
    // 3. SEEDING CLIENTI MASTER
    // ===================================
    
    echo "<h3>🏢 Seeding Clienti Master</h3>\n";
    
    // Clienti noti (esempi generici per iniziare)
    $known_clients = [
        ['BAIT S.r.l.', 'Via Roma 123', 'Milano', 'MI', 'BAIT001'],
        ['Gruppo Aziende', 'Via Garibaldi 45', 'Torino', 'TO', 'GRUPPO001'],
        ['TechCorp Italia', 'Via Venezia 78', 'Roma', 'RM', 'TECH001'],
        ['Innovazione S.p.A.', 'Via Dante 90', 'Napoli', 'NA', 'INNOV001'],
        ['Digital Solutions', 'Via Manzoni 12', 'Firenze', 'FI', 'DIGIT001'],
        ['Sistemi Avanzati', 'Via Verdi 34', 'Bologna', 'BO', 'SIST001'],
        ['Automazione Nord', 'Via Cavour 56', 'Venezia', 'VE', 'AUTO001'],
        ['Consulting Group', 'Via Leopardi 78', 'Genova', 'GE', 'CONS001'],
        ['Software House', 'Via Pascoli 90', 'Palermo', 'PA', 'SOFT001'],
        ['IT Services', 'Via Carducci 23', 'Bari', 'BA', 'ITSER001']
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO master_clienti 
        (nome, indirizzo, citta, provincia, codice_gestionale, fonte_origine, attivo, note) 
        VALUES (?, ?, ?, ?, ?, 'manual', 1, 'Cliente noto - seeding iniziale')
        ON DUPLICATE KEY UPDATE 
        indirizzo = VALUES(indirizzo),
        citta = VALUES(citta),
        provincia = VALUES(provincia),
        codice_gestionale = VALUES(codice_gestionale),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Nome</th><th>Città</th><th>Provincia</th><th>Codice</th><th>Stato</th></tr>\n";
    
    $inserted_clients = 0;
    foreach ($known_clients as $client) {
        try {
            $stmt->execute($client);
            $inserted_clients++;
            echo "<tr>\n";
            echo "<td><strong>{$client[0]}</strong></td>\n";
            echo "<td>{$client[2]}</td>\n";
            echo "<td>{$client[3]}</td>\n";
            echo "<td>{$client[4]}</td>\n";
            echo "<td style='color: green;'>✅ Inserito</td>\n";
            echo "</tr>\n";
        } catch (Exception $e) {
            echo "<tr>\n";
            echo "<td><strong>{$client[0]}</strong></td>\n";
            echo "<td>{$client[2]}</td>\n";
            echo "<td>{$client[3]}</td>\n";
            echo "<td>{$client[4]}</td>\n";
            echo "<td style='color: red;'>❌ " . htmlspecialchars($e->getMessage()) . "</td>\n";
            echo "</tr>\n";
        }
    }
    echo "</table>\n";
    
    // ===================================
    // 4. SEEDING ALIAS DIPENDENTI
    // ===================================
    
    echo "<h3>🔄 Seeding Alias Dipendenti</h3>\n";
    
    // Recupera gli ID dei dipendenti master per creare alias
    $stmt = $conn->prepare("SELECT id, nome, cognome FROM master_dipendenti");
    $stmt->execute();
    $master_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Crea alias comuni per i dipendenti (variazioni nei nomi)
    $employee_aliases = [];
    foreach ($master_employees as $employee) {
        $master_id = $employee['id'];
        $nome = $employee['nome'];
        $cognome = $employee['cognome'];
        
        // Alias comuni per i nomi
        $nome_variants = [$nome];
        if ($nome === 'Franco') $nome_variants[] = 'Francesco';
        if ($nome === 'Matteo') $nome_variants[] = 'Matt';
        if ($nome === 'Alex') $nome_variants[] = 'Alessandro';
        if ($nome === 'Roberto') $nome_variants[] = 'Rob';
        if ($nome === 'Gabriele') $nome_variants[] = 'Gabe';
        if ($nome === 'Marco') $nome_variants[] = 'Marc';
        if ($nome === 'Davide') $nome_variants[] = 'Dave';
        
        // Cognome abbreviato
        $cognome_variants = [$cognome];
        if (strlen($cognome) > 4) {
            $cognome_variants[] = substr($cognome, 0, 4);
        }
        
        // Crea combinazioni alias
        foreach ($nome_variants as $n) {
            foreach ($cognome_variants as $c) {
                if ($n !== $nome || $c !== $cognome) {
                    $employee_aliases[] = [$master_id, $n, $c, 'manual'];
                }
            }
        }
    }
    
    if (!empty($employee_aliases)) {
        $stmt = $conn->prepare("
            INSERT INTO dipendenti_aliases 
            (master_dipendente_id, alias_nome, alias_cognome, fonte, note) 
            VALUES (?, ?, ?, ?, 'Alias generato automaticamente - seeding iniziale')
            ON DUPLICATE KEY UPDATE 
            fonte = VALUES(fonte),
            note = VALUES(note)
        ");
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Master ID</th><th>Alias Nome</th><th>Alias Cognome</th><th>Stato</th></tr>\n";
        
        $inserted_aliases = 0;
        foreach ($employee_aliases as $alias) {
            try {
                $stmt->execute($alias);
                $inserted_aliases++;
                echo "<tr>\n";
                echo "<td>{$alias[0]}</td>\n";
                echo "<td><strong>{$alias[1]}</strong></td>\n";
                echo "<td>{$alias[2]}</td>\n";
                echo "<td style='color: green;'>✅ Inserito</td>\n";
                echo "</tr>\n";
            } catch (Exception $e) {
                echo "<tr>\n";
                echo "<td>{$alias[0]}</td>\n";
                echo "<td><strong>{$alias[1]}</strong></td>\n";
                echo "<td>{$alias[2]}</td>\n";
                echo "<td style='color: red;'>❌ " . htmlspecialchars($e->getMessage()) . "</td>\n";
                echo "</tr>\n";
            }
        }
        echo "</table>\n";
    } else {
        echo "<p>Nessun alias da generare per i dipendenti master.</p>\n";
    }
    
    // ===================================
    // 5. MIGRAZIONE DATI ESISTENTI
    // ===================================
    
    echo "<h3>🔄 Migrazione Dati Esistenti</h3>\n";
    
    // Collega dipendenti esistenti ai master dipendenti
    echo "<h4>Collegamento Dipendenti Esistenti</h4>\n";
    
    $stmt = $conn->prepare("
        UPDATE dipendenti d 
        JOIN master_dipendenti md ON (d.nome = md.nome AND d.cognome = md.cognome)
        SET d.master_dipendente_id = md.id
        WHERE d.master_dipendente_id IS NULL
    ");
    $stmt->execute();
    $linked_employees = $stmt->rowCount();
    
    echo "<p style='color: green;'>✅ Collegati $linked_employees dipendenti esistenti ai master dipendenti</p>\n";
    
    // Collega veicoli esistenti ai master veicoli
    echo "<h4>Collegamento Veicoli Esistenti</h4>\n";
    
    $stmt = $conn->prepare("
        UPDATE veicoli v 
        JOIN master_veicoli mv ON v.nome = mv.nome
        SET v.master_veicolo_id = mv.id
        WHERE v.master_veicolo_id IS NULL
    ");
    $stmt->execute();
    $linked_vehicles = $stmt->rowCount();
    
    echo "<p style='color: green;'>✅ Collegati $linked_vehicles veicoli esistenti ai master veicoli</p>\n";
    
    // Collega clienti esistenti ai master clienti
    echo "<h4>Collegamento Clienti Esistenti</h4>\n";
    
    $stmt = $conn->prepare("
        UPDATE clienti c 
        JOIN master_clienti mc ON c.nome = mc.nome
        SET c.master_cliente_id = mc.id
        WHERE c.master_cliente_id IS NULL
    ");
    $stmt->execute();
    $linked_clients = $stmt->rowCount();
    
    echo "<p style='color: green;'>✅ Collegati $linked_clients clienti esistenti ai master clienti</p>\n";
    
    $conn->commit();
    
    // ===================================
    // 6. VERIFICA FINALE
    // ===================================
    
    echo "<h3>📊 Verifica Finale Seeding</h3>\n";
    
    // Conta i record nelle tabelle master
    $tables = [
        'master_dipendenti' => 'Dipendenti Master',
        'master_veicoli' => 'Veicoli Master',
        'master_clienti' => 'Clienti Master',
        'dipendenti_aliases' => 'Alias Dipendenti'
    ];
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Tabella</th><th>Descrizione</th><th>Record</th><th>Stato</th></tr>\n";
    
    foreach ($tables as $table => $description) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM `$table`");
        $stmt->execute();
        $count = $stmt->fetch()['count'];
        
        $status = $count > 0 ? '✅ Popolata' : '⚠️ Vuota';
        $color = $count > 0 ? 'green' : 'orange';
        
        echo "<tr>\n";
        echo "<td><strong>$table</strong></td>\n";
        echo "<td>$description</td>\n";
        echo "<td>$count</td>\n";
        echo "<td style='color: $color;'>$status</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Summary finale
    echo "<h3>🎯 Summary Data Seeding</h3>\n";
    
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>\n";
    echo "<h4>✅ Data Seeding Completato con Successo!</h4>\n";
    echo "<ul>\n";
    echo "<li><strong>Dipendenti master inseriti:</strong> $inserted_employees</li>\n";
    echo "<li><strong>Veicoli master inseriti:</strong> $inserted_vehicles</li>\n";
    echo "<li><strong>Clienti master inseriti:</strong> $inserted_clients</li>\n";
    echo "<li><strong>Alias dipendenti creati:</strong> " . (isset($inserted_aliases) ? $inserted_aliases : 0) . "</li>\n";
    echo "<li><strong>Dipendenti collegati:</strong> $linked_employees</li>\n";
    echo "<li><strong>Veicoli collegati:</strong> $linked_vehicles</li>\n";
    echo "<li><strong>Clienti collegati:</strong> $linked_clients</li>\n";
    echo "</ul>\n";
    echo "<p><strong>🎯 Prossimo Step:</strong> Migliorare il CsvParser per utilizzare le tabelle master durante l'import.</p>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
    echo "<h4>❌ Errore durante il seeding</h4>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}
?>

<p>
    <a href="enhance_csv_parser.php">🔧 Potenziare CSV Parser</a> | 
    <a href="test_master_tables.php">🧪 Test Master Tables</a> | 
    <a href="index.php">Dashboard</a>
</p>