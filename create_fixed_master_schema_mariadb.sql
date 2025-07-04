-- =====================================================
-- FASE 2: SCHEMA MASTER DATA FISSI - MARIADB COMPATIBLE
-- Sistema basato su dati certi e configurabili
-- Versione compatibile con MariaDB/MySQL 5.7+
-- =====================================================

-- Disabilita temporaneamente i controlli foreign key per permettere DROP sicuri
SET FOREIGN_KEY_CHECKS = 0;

-- Elimina tabelle nell'ordine corretto per evitare conflitti foreign key
-- Prima elimina le tabelle dipendenti, poi quelle referenziate
DROP TABLE IF EXISTS clienti_aziende;
DROP TABLE IF EXISTS association_queue;
DROP TABLE IF EXISTS master_progetti;
DROP TABLE IF EXISTS master_aziende;
DROP TABLE IF EXISTS master_dipendenti_fixed;
DROP TABLE IF EXISTS master_veicoli_config;
DROP TABLE IF EXISTS system_config;

-- Riabilita i controlli foreign key
SET FOREIGN_KEY_CHECKS = 1;

-- 1. TABELLA MASTER DIPENDENTI FISSI
-- Lista definitiva dei 15 dipendenti certi
CREATE TABLE master_dipendenti_fixed (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    cognome VARCHAR(50) NOT NULL,
    nome_completo VARCHAR(100) AS (CONCAT(nome, ' ', cognome)) PERSISTENT,
    email VARCHAR(100),
    ruolo ENUM('Tecnico', 'Manager', 'Admin', 'Responsabile') DEFAULT 'Tecnico',
    costo_giornaliero DECIMAL(8,2) DEFAULT 200.00,
    telefono VARCHAR(20),
    data_assunzione DATE,
    attivo BOOLEAN DEFAULT TRUE,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_nome_cognome (nome, cognome),
    INDEX idx_nome_completo (nome_completo),
    INDEX idx_attivo (attivo)
);

-- 2. TABELLA MASTER AZIENDE
-- Aziende identificate dal file attività + configurabili
CREATE TABLE master_aziende (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(200) NOT NULL,
    nome_breve VARCHAR(100),
    codice_cliente VARCHAR(50),
    indirizzo TEXT,
    citta VARCHAR(100),
    provincia VARCHAR(2),
    cap VARCHAR(10),
    telefono VARCHAR(20),
    email VARCHAR(100),
    pec VARCHAR(100),
    sito_web VARCHAR(200),
    codice_fiscale VARCHAR(16),
    partita_iva VARCHAR(11),
    settore VARCHAR(100),
    note TEXT,
    attivo BOOLEAN DEFAULT TRUE,
    data_inserimento DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_nome (nome),
    INDEX idx_nome_breve (nome_breve),
    INDEX idx_codice_cliente (codice_cliente),
    INDEX idx_attivo (attivo)
);

-- 3. TABELLA MASTER VEICOLI CONFIGURABILI
-- Veicoli fissi ma configurabili via UI
CREATE TABLE master_veicoli_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('Auto', 'Furgone', 'Camion', 'Moto', 'Altro') DEFAULT 'Auto',
    marca VARCHAR(50),
    modello VARCHAR(50),
    targa VARCHAR(20),
    anno_immatricolazione YEAR,
    colore VARCHAR(30),
    alimentazione ENUM('Benzina', 'Diesel', 'Elettrica', 'Ibrida', 'GPL', 'Metano') DEFAULT 'Benzina',
    km_attuali INT DEFAULT 0,
    costo_km DECIMAL(6,3) DEFAULT 0.350,
    note TEXT,
    attivo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_nome (nome),
    INDEX idx_tipo (tipo),
    INDEX idx_targa (targa),
    INDEX idx_attivo (attivo)
);

-- 4. TABELLA CLIENTI AZIENDE (per associazioni dinamiche)
-- Gestisce i nomi dei dipendenti delle aziende clienti
CREATE TABLE clienti_aziende (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100),
    nome_completo VARCHAR(200) AS (
        CASE 
            WHEN cognome IS NOT NULL AND cognome != '' 
            THEN CONCAT(nome, ' ', cognome)
            ELSE nome
        END
    ) PERSISTENT,
    azienda_id INT,
    ruolo VARCHAR(100),
    email VARCHAR(100),
    telefono VARCHAR(20),
    reparto VARCHAR(100),
    note TEXT,
    attivo BOOLEAN DEFAULT TRUE,
    data_inserimento DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_azienda (azienda_id),
    INDEX idx_nome_completo (nome_completo),
    INDEX idx_attivo (attivo)
);

-- 5. TABELLA CODA ASSOCIAZIONI (per UI dinamica)
-- Gestisce i nuovi clienti da associare alle aziende
CREATE TABLE association_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome_cliente VARCHAR(200) NOT NULL,
    fonte_import VARCHAR(50), -- 'teamviewer', 'attivita', 'manual'
    azienda_suggerita_id INT,
    azienda_assegnata_id INT,
    stato ENUM('pending', 'assigned', 'rejected', 'ignored') DEFAULT 'pending',
    confidenza_match DECIMAL(3,2), -- 0.00-1.00 per matching automatico
    note_admin TEXT,
    processed_by VARCHAR(100),
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_azienda_suggerita (azienda_suggerita_id),
    INDEX idx_azienda_assegnata (azienda_assegnata_id),
    INDEX idx_stato (stato),
    INDEX idx_fonte (fonte_import),
    INDEX idx_processed (processed_at)
);

-- 6. TABELLA PROGETTI DINAMICI
-- Progetti rilevati automaticamente ma associati ad aziende
CREATE TABLE master_progetti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(200) NOT NULL,
    codice VARCHAR(100),
    descrizione TEXT,
    azienda_id INT,
    data_inizio DATE,
    data_fine DATE,
    stato ENUM('attivo', 'completato', 'sospeso', 'annullato') DEFAULT 'attivo',
    budget DECIMAL(10,2),
    ore_stimate INT,
    ore_effettive INT DEFAULT 0, -- Sarà aggiornato via trigger o stored procedure
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_azienda (azienda_id),
    UNIQUE KEY unique_codice (codice),
    INDEX idx_stato (stato),
    INDEX idx_date_range (data_inizio, data_fine)
);

-- 7. TABELLA CONFIGURAZIONI SISTEMA
-- Gestisce configurazioni dinamiche del sistema
CREATE TABLE system_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    categoria VARCHAR(50) NOT NULL,
    chiave VARCHAR(100) NOT NULL,
    valore TEXT,
    tipo ENUM('string', 'int', 'float', 'boolean', 'json', 'date') DEFAULT 'string',
    descrizione TEXT,
    validazione VARCHAR(200), -- regex o range per validazione
    modificabile BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_categoria_chiave (categoria, chiave),
    INDEX idx_categoria (categoria),
    INDEX idx_modificabile (modificabile)
);

-- =====================================================
-- INSERIMENTO DATI BASE
-- =====================================================

-- Popola con i 15 dipendenti certi forniti dall'utente
INSERT INTO master_dipendenti_fixed (nome, cognome, ruolo, costo_giornaliero) VALUES
('Niccolò', 'Ragusa', 'Tecnico', 200.00),
('Davide', 'Cestone', 'Tecnico', 200.00),
('Arlind', 'Hoxha', 'Tecnico', 200.00),
('Lorenzo', 'Serratore', 'Tecnico', 200.00),
('Gabriele', 'De Palma', 'Tecnico', 200.00),
('Franco', 'Fiorellino', 'Tecnico', 200.00),
('Matteo', 'Signo', 'Tecnico', 200.00),
('Marco', 'Birocchi', 'Tecnico', 200.00),
('Roberto', 'Birocchi', 'Tecnico', 200.00),
('Alex', 'Ferrario', 'Tecnico', 200.00),
('Gianluca', 'Ghirindelli', 'Tecnico', 200.00),
('Matteo', 'Di Salvo', 'Tecnico', 200.00),
('Cristian', 'La Bella', 'Tecnico', 200.00),
('Giuseppe', 'Anastasio', 'Tecnico', 200.00);

-- Popola con aziende dal file attività (base conosciuta)
INSERT INTO master_aziende (nome, nome_breve, settore) VALUES
('ITX ITALIA SRL', 'ITX', 'Tecnologia'),
('WITTMANN BATTENFELD ITALIA S.r.l.', 'WITTMANN', 'Manifatturiero'),
('BAIT Service S.r.l.', 'BAIT', 'Servizi IT'),
('AMROP - G&P CONSULTANTS SRL', 'AMROP', 'Consulenza'),
('ELECTRALINE 3PMARK SPA', 'ELECTRALINE', 'Elettronica'),
('ASPEX ARL', 'ASPEX', 'Servizi'),
('SWITZERLAND CHEESE MARKETING ITALIA SRL', 'SCM ITALIA', 'Food & Beverage'),
('MAX MODA SPA', 'MAX MODA', 'Moda'),
('ISOTERMA SRL', 'ISOTERMA', 'Costruzioni');

-- Popola con veicoli base conosciuti
INSERT INTO master_veicoli_config (nome, tipo, marca, modello) VALUES
('Punto', 'Auto', 'Fiat', 'Punto'),
('Fiesta', 'Auto', 'Ford', 'Fiesta'),
('Peugeot', 'Auto', 'Peugeot', 'Generico'),
('Aurora', 'Auto', 'Generico', 'Aurora'),
('Furgone Aziendale', 'Furgone', 'Iveco', 'Daily');

-- Popola configurazioni base
INSERT INTO system_config (categoria, chiave, valore, tipo, descrizione) VALUES
('generale', 'ore_lavoro_giorno', '8', 'int', 'Ore lavorative standard per giorno'),
('generale', 'giorni_lavoro_settimana', '5', 'int', 'Giorni lavorativi per settimana (Lun-Ven)'),
('generale', 'costo_giornaliero_default', '200.00', 'float', 'Costo giornaliero default per nuovi dipendenti'),
('generale', 'azienda_principale', 'ITX ITALIA SRL', 'string', 'Nome azienda principale'),
('import', 'auto_associate_clients', 'true', 'boolean', 'Associazione automatica clienti-aziende'),
('import', 'confidence_threshold', '0.80', 'float', 'Soglia confidenza per associazioni automatiche'),
('import', 'duplicate_time_window', '3', 'int', 'Finestra temporale (minuti) per rilevamento duplicati'),
('kpi', 'efficiency_warning_threshold', '70', 'int', 'Soglia % efficiency per warning'),
('kpi', 'efficiency_critical_threshold', '50', 'int', 'Soglia % efficiency per critical alert');

-- =====================================================
-- AGGIORNAMENTI TABELLE LEGACY
-- =====================================================

-- Verifica ed aggiunge colonne alle tabelle esistenti se non presenti
SET @query1 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE table_schema = DATABASE() AND table_name = 'dipendenti' AND column_name = 'master_dipendente_id') = 0,
    'ALTER TABLE dipendenti ADD COLUMN master_dipendente_id INT', 
    'SELECT "master_dipendente_id già presente in dipendenti" as status');
PREPARE stmt1 FROM @query1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @query2 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE table_schema = DATABASE() AND table_name = 'clienti' AND column_name = 'master_azienda_id') = 0,
    'ALTER TABLE clienti ADD COLUMN master_azienda_id INT', 
    'SELECT "master_azienda_id già presente in clienti" as status');
PREPARE stmt2 FROM @query2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @query3 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE table_schema = DATABASE() AND table_name = 'progetti' AND column_name = 'master_progetto_id') = 0,
    'ALTER TABLE progetti ADD COLUMN master_progetto_id INT', 
    'SELECT "master_progetto_id già presente in progetti" as status');
PREPARE stmt3 FROM @query3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

SET @query4 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE table_schema = DATABASE() AND table_name = 'attivita' AND column_name = 'master_azienda_id') = 0,
    'ALTER TABLE attivita ADD COLUMN master_azienda_id INT', 
    'SELECT "master_azienda_id già presente in attivita" as status');
PREPARE stmt4 FROM @query4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;

-- Aggiunge foreign keys se le tabelle referenziate esistono
SET @add_fk1 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE table_schema = DATABASE() AND table_name = 'master_aziende') > 0,
    'ALTER TABLE clienti_aziende ADD CONSTRAINT fk_clienti_aziende_master 
     FOREIGN KEY (azienda_id) REFERENCES master_aziende(id) ON DELETE SET NULL', 
    'SELECT "Tabella master_aziende non trovata" as status');
PREPARE fk_stmt1 FROM @add_fk1;
EXECUTE fk_stmt1;
DEALLOCATE PREPARE fk_stmt1;

SET @add_fk2 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE table_schema = DATABASE() AND table_name = 'master_aziende') > 0,
    'ALTER TABLE association_queue ADD CONSTRAINT fk_association_suggested 
     FOREIGN KEY (azienda_suggerita_id) REFERENCES master_aziende(id) ON DELETE SET NULL', 
    'SELECT "Tabella master_aziende non trovata" as status');
PREPARE fk_stmt2 FROM @add_fk2;
EXECUTE fk_stmt2;
DEALLOCATE PREPARE fk_stmt2;

SET @add_fk3 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE table_schema = DATABASE() AND table_name = 'master_aziende') > 0,
    'ALTER TABLE association_queue ADD CONSTRAINT fk_association_assigned 
     FOREIGN KEY (azienda_assegnata_id) REFERENCES master_aziende(id) ON DELETE SET NULL', 
    'SELECT "Tabella master_aziende non trovata" as status');
PREPARE fk_stmt3 FROM @add_fk3;
EXECUTE fk_stmt3;
DEALLOCATE PREPARE fk_stmt3;

SET @add_fk4 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE table_schema = DATABASE() AND table_name = 'master_aziende') > 0,
    'ALTER TABLE master_progetti ADD CONSTRAINT fk_progetti_azienda 
     FOREIGN KEY (azienda_id) REFERENCES master_aziende(id) ON DELETE SET NULL', 
    'SELECT "Tabella master_aziende non trovata" as status');
PREPARE fk_stmt4 FROM @add_fk4;
EXECUTE fk_stmt4;
DEALLOCATE PREPARE fk_stmt4;

-- =====================================================
-- INDICI PER PERFORMANCE
-- =====================================================

-- Indici aggiuntivi per query frequenti (con controllo esistenza)
SET @idx1 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'dipendenti' AND index_name = 'idx_dipendenti_nome_cognome') = 0,
    'CREATE INDEX idx_dipendenti_nome_cognome ON dipendenti(nome, cognome)', 
    'SELECT "idx_dipendenti_nome_cognome già esistente" as status');
PREPARE idx_stmt1 FROM @idx1;
EXECUTE idx_stmt1;
DEALLOCATE PREPARE idx_stmt1;

SET @idx2 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'dipendenti' AND index_name = 'idx_dipendenti_attivo') = 0,
    'CREATE INDEX idx_dipendenti_attivo ON dipendenti(attivo)', 
    'SELECT "idx_dipendenti_attivo già esistente" as status');
PREPARE idx_stmt2 FROM @idx2;
EXECUTE idx_stmt2;
DEALLOCATE PREPARE idx_stmt2;

SET @idx3 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'dipendenti' AND index_name = 'idx_master_dipendente') = 0,
    'CREATE INDEX idx_master_dipendente ON dipendenti(master_dipendente_id)', 
    'SELECT "idx_master_dipendente già esistente" as status');
PREPARE idx_stmt3 FROM @idx3;
EXECUTE idx_stmt3;
DEALLOCATE PREPARE idx_stmt3;

-- Indici per performance KPI e statistiche se le tabelle esistono
SET @idx4 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE table_schema = DATABASE() AND table_name = 'attivita') > 0 AND
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'attivita' AND index_name = 'idx_attivita_data_dipendente') = 0,
    'CREATE INDEX idx_attivita_data_dipendente ON attivita(data_inizio, dipendente_id)', 
    'SELECT "Tabella attivita non trovata o indice già esistente" as status');
PREPARE idx_stmt4 FROM @idx4;
EXECUTE idx_stmt4;
DEALLOCATE PREPARE idx_stmt4;

SET @idx5 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE table_schema = DATABASE() AND table_name = 'timbrature') > 0 AND
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'timbrature' AND index_name = 'idx_timbrature_data_dipendente') = 0,
    'CREATE INDEX idx_timbrature_data_dipendente ON timbrature(data, dipendente_id)', 
    'SELECT "Tabella timbrature non trovata o indice già esistente" as status');
PREPARE idx_stmt5 FROM @idx5;
EXECUTE idx_stmt5;
DEALLOCATE PREPARE idx_stmt5;

-- Indici per association queue
CREATE INDEX idx_association_queue_stato_created ON association_queue(stato, created_at);

-- =====================================================
-- VISTE PER SEMPLIFICARE QUERY
-- =====================================================

-- Vista completa dipendenti con info master
DROP VIEW IF EXISTS v_dipendenti_completi;
CREATE VIEW v_dipendenti_completi AS
SELECT 
    d.id as dipendente_id,
    d.nome,
    d.cognome,
    CONCAT(d.nome, ' ', d.cognome) as nome_completo,
    d.email,
    d.ruolo,
    d.costo_giornaliero,
    d.attivo as dipendente_attivo,
    mdf.id as master_id,
    mdf.nome as master_nome,
    mdf.cognome as master_cognome,
    mdf.nome_completo as master_nome_completo,
    mdf.attivo as master_attivo,
    CASE 
        WHEN mdf.id IS NOT NULL THEN 'MASTER'
        ELSE 'LEGACY_ONLY'
    END as source_type
FROM dipendenti d
LEFT JOIN master_dipendenti_fixed mdf ON d.master_dipendente_id = mdf.id
ORDER BY d.cognome, d.nome;

-- Vista aziende con conteggi
DROP VIEW IF EXISTS v_aziende_stats;
CREATE VIEW v_aziende_stats AS
SELECT 
    ma.id,
    ma.nome,
    ma.nome_breve,
    ma.settore,
    ma.attivo,
    COUNT(DISTINCT ca.id) as clienti_count,
    COUNT(DISTINCT mp.id) as progetti_count,
    ma.created_at
FROM master_aziende ma
LEFT JOIN clienti_aziende ca ON ma.id = ca.azienda_id AND ca.attivo = 1
LEFT JOIN master_progetti mp ON ma.id = mp.azienda_id
GROUP BY ma.id, ma.nome, ma.nome_breve, ma.settore, ma.attivo, ma.created_at
ORDER BY ma.nome;

-- Vista queue associazioni con suggerimenti
DROP VIEW IF EXISTS v_association_queue_detailed;
CREATE VIEW v_association_queue_detailed AS
SELECT 
    aq.id,
    aq.nome_cliente,
    aq.fonte_import,
    aq.stato,
    aq.confidenza_match,
    mas.nome as azienda_suggerita,
    maa.nome as azienda_assegnata,
    aq.created_at,
    aq.processed_at,
    aq.note_admin
FROM association_queue aq
LEFT JOIN master_aziende mas ON aq.azienda_suggerita_id = mas.id
LEFT JOIN master_aziende maa ON aq.azienda_assegnata_id = maa.id
ORDER BY 
    CASE aq.stato 
        WHEN 'pending' THEN 1 
        WHEN 'assigned' THEN 2 
        WHEN 'rejected' THEN 3 
        WHEN 'ignored' THEN 4 
    END,
    aq.confidenza_match DESC,
    aq.created_at ASC;

-- =====================================================
-- FINE SCHEMA MASTER DATA FISSI - MARIADB COMPATIBLE
-- 
-- Questo schema crea una base solida con:
-- - 15 dipendenti fissi e certi
-- - Aziende configurabili 
-- - Veicoli gestibili
-- - Sistema associazioni dinamiche
-- - Compatibilità completa MariaDB/MySQL 5.7+
-- - Gestione errori e controlli esistenza
-- - Performance ottimizzate con indici e viste
-- =====================================================