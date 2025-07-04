-- =====================================================
-- FASE 2: SCHEMA MASTER DATA FISSI
-- Sistema basato su dati certi e configurabili
-- =====================================================

-- 1. TABELLA MASTER DIPENDENTI FISSI
-- Lista definitiva dei 15 dipendenti certi
DROP TABLE IF EXISTS master_dipendenti_fixed;
CREATE TABLE master_dipendenti_fixed (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    cognome VARCHAR(50) NOT NULL,
    nome_completo VARCHAR(100) GENERATED ALWAYS AS (CONCAT(nome, ' ', cognome)) STORED,
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
    INDEX idx_attivo (attivo),
    FULLTEXT idx_search (nome, cognome, nome_completo)
);

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

-- 2. TABELLA MASTER AZIENDE
-- Aziende identificate dal file attività + configurabili
DROP TABLE IF EXISTS master_aziende;
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
    data_inserimento DATE DEFAULT (CURRENT_DATE),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_nome (nome),
    INDEX idx_nome_breve (nome_breve),
    INDEX idx_codice_cliente (codice_cliente),
    INDEX idx_attivo (attivo),
    FULLTEXT idx_search (nome, nome_breve, codice_cliente)
);

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

-- 3. TABELLA MASTER VEICOLI CONFIGURABILI
-- Veicoli fissi ma configurabili via UI
DROP TABLE IF EXISTS master_veicoli_config;
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
    INDEX idx_attivo (attivo),
    FULLTEXT idx_search (nome, marca, modello, targa)
);

-- Popola con veicoli base conosciuti
INSERT INTO master_veicoli_config (nome, tipo, marca, modello) VALUES
('Punto', 'Auto', 'Fiat', 'Punto'),
('Fiesta', 'Auto', 'Ford', 'Fiesta'),
('Peugeot', 'Auto', 'Peugeot', 'Generico'),
('Aurora', 'Auto', 'Generico', 'Aurora'),
('Furgone Aziendale', 'Furgone', 'Iveco', 'Daily');

-- 4. TABELLA CLIENTI AZIENDE (per associazioni dinamiche)
-- Gestisce i nomi dei dipendenti delle aziende clienti
DROP TABLE IF EXISTS clienti_aziende;
CREATE TABLE clienti_aziende (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100),
    nome_completo VARCHAR(200) GENERATED ALWAYS AS (
        CASE 
            WHEN cognome IS NOT NULL AND cognome != '' 
            THEN CONCAT(nome, ' ', cognome)
            ELSE nome
        END
    ) STORED,
    azienda_id INT,
    ruolo VARCHAR(100),
    email VARCHAR(100),
    telefono VARCHAR(20),
    reparto VARCHAR(100),
    note TEXT,
    attivo BOOLEAN DEFAULT TRUE,
    data_inserimento DATE DEFAULT (CURRENT_DATE),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (azienda_id) REFERENCES master_aziende(id) ON DELETE SET NULL,
    INDEX idx_azienda (azienda_id),
    INDEX idx_nome_completo (nome_completo),
    INDEX idx_attivo (attivo),
    FULLTEXT idx_search (nome, cognome, nome_completo)
);

-- 5. TABELLA CODA ASSOCIAZIONI (per UI dinamica)
-- Gestisce i nuovi clienti da associare alle aziende
DROP TABLE IF EXISTS association_queue;
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
    
    FOREIGN KEY (azienda_suggerita_id) REFERENCES master_aziende(id) ON DELETE SET NULL,
    FOREIGN KEY (azienda_assegnata_id) REFERENCES master_aziende(id) ON DELETE SET NULL,
    INDEX idx_stato (stato),
    INDEX idx_fonte (fonte_import),
    INDEX idx_processed (processed_at),
    FULLTEXT idx_search (nome_cliente)
);

-- 6. TABELLA PROGETTI DINAMICI
-- Progetti rilevati automaticamente ma associati ad aziende
DROP TABLE IF EXISTS master_progetti;
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
    ore_effettive INT GENERATED ALWAYS AS (
        (SELECT COALESCE(SUM(durata_ore), 0) FROM attivita WHERE progetto_id = master_progetti.id AND is_duplicate != 1)
    ) STORED,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (azienda_id) REFERENCES master_aziende(id) ON DELETE SET NULL,
    UNIQUE KEY unique_codice (codice),
    INDEX idx_azienda (azienda_id),
    INDEX idx_stato (stato),
    INDEX idx_date_range (data_inizio, data_fine),
    FULLTEXT idx_search (nome, codice, descrizione)
);

-- 7. TABELLA CONFIGURAZIONI SISTEMA
-- Gestisce configurazioni dinamiche del sistema
DROP TABLE IF EXISTS system_config;
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

-- 8. AGGIORNA TABELLA DIPENDENTI LEGACY
-- Collega alla master table per compatibilità
ALTER TABLE dipendenti ADD COLUMN IF NOT EXISTS master_dipendente_id INT;
ALTER TABLE dipendenti ADD INDEX IF NOT EXISTS idx_master_dipendente (master_dipendente_id);

-- 9. AGGIORNA ALTRE TABELLE LEGACY
-- Collega clienti alla master aziende
ALTER TABLE clienti ADD COLUMN IF NOT EXISTS master_azienda_id INT;
ALTER TABLE clienti ADD INDEX IF NOT EXISTS idx_master_azienda (master_azienda_id);

-- Collega progetti alla master progetti
ALTER TABLE progetti ADD COLUMN IF NOT EXISTS master_progetto_id INT;
ALTER TABLE progetti ADD INDEX IF NOT EXISTS idx_master_progetto (master_progetto_id);

-- 10. TRIGGER PER SINCRONIZZAZIONE AUTOMATICA
-- Trigger per mantenere sincronizzazione dipendenti legacy ↔ master

DELIMITER //
CREATE TRIGGER sync_dipendenti_to_master
AFTER INSERT ON dipendenti
FOR EACH ROW
BEGIN
    DECLARE master_id INT;
    
    -- Cerca se esiste un master dipendente corrispondente
    SELECT id INTO master_id 
    FROM master_dipendenti_fixed 
    WHERE nome = NEW.nome AND cognome = NEW.cognome 
    LIMIT 1;
    
    -- Se trovato, aggiorna il collegamento
    IF master_id IS NOT NULL THEN
        UPDATE dipendenti 
        SET master_dipendente_id = master_id 
        WHERE id = NEW.id;
    END IF;
END//

CREATE TRIGGER sync_master_dipendenti_to_legacy
AFTER UPDATE ON master_dipendenti_fixed
FOR EACH ROW
BEGIN
    -- Aggiorna tutti i dipendenti legacy collegati
    UPDATE dipendenti 
    SET 
        nome = NEW.nome,
        cognome = NEW.cognome,
        email = COALESCE(NEW.email, email),
        costo_giornaliero = NEW.costo_giornaliero,
        ruolo = NEW.ruolo,
        attivo = NEW.attivo
    WHERE master_dipendente_id = NEW.id;
END//
DELIMITER ;

-- 11. VISTE PER SEMPLIFICARE QUERY
-- Vista completa dipendenti con info master
CREATE OR REPLACE VIEW v_dipendenti_completi AS
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
CREATE OR REPLACE VIEW v_aziende_stats AS
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
CREATE OR REPLACE VIEW v_association_queue_detailed AS
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

-- 12. STORED PROCEDURES PER OPERAZIONI COMUNI
DELIMITER //

-- Procedura per aggiungere nuovo dipendente fisso
CREATE PROCEDURE AddMasterEmployee(
    IN p_nome VARCHAR(50),
    IN p_cognome VARCHAR(50),
    IN p_email VARCHAR(100),
    IN p_ruolo VARCHAR(20),
    IN p_costo DECIMAL(8,2)
)
BEGIN
    DECLARE master_id INT;
    
    -- Inserisci nel master
    INSERT INTO master_dipendenti_fixed (nome, cognome, email, ruolo, costo_giornaliero)
    VALUES (p_nome, p_cognome, p_email, p_ruolo, p_costo);
    
    SET master_id = LAST_INSERT_ID();
    
    -- Inserisci nel legacy per compatibilità
    INSERT INTO dipendenti (nome, cognome, email, ruolo, costo_giornaliero, master_dipendente_id, attivo)
    VALUES (p_nome, p_cognome, p_email, p_ruolo, p_costo, master_id, 1);
    
    SELECT master_id as new_master_id, LAST_INSERT_ID() as new_dipendente_id;
END//

-- Procedura per associare cliente ad azienda
CREATE PROCEDURE AssociateClientToCompany(
    IN p_nome_cliente VARCHAR(200),
    IN p_azienda_id INT,
    IN p_processed_by VARCHAR(100)
)
BEGIN
    DECLARE queue_id INT;
    
    -- Trova nella queue
    SELECT id INTO queue_id 
    FROM association_queue 
    WHERE nome_cliente = p_nome_cliente AND stato = 'pending'
    LIMIT 1;
    
    IF queue_id IS NOT NULL THEN
        -- Aggiorna queue
        UPDATE association_queue 
        SET 
            azienda_assegnata_id = p_azienda_id,
            stato = 'assigned',
            processed_by = p_processed_by,
            processed_at = CURRENT_TIMESTAMP
        WHERE id = queue_id;
        
        -- Inserisci in clienti_aziende
        INSERT INTO clienti_aziende (nome, azienda_id, attivo)
        VALUES (p_nome_cliente, p_azienda_id, 1);
        
        SELECT queue_id as updated_queue_id, LAST_INSERT_ID() as new_client_id;
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cliente non trovato in queue pending';
    END IF;
END//

DELIMITER ;

-- 13. INDICI PER PERFORMANCE
-- Indici aggiuntivi per query frequenti

-- Per ricerche rapide dipendenti
CREATE INDEX idx_dipendenti_nome_cognome ON dipendenti(nome, cognome);
CREATE INDEX idx_dipendenti_attivo ON dipendenti(attivo);

-- Per ricerche KPI e statistiche
CREATE INDEX idx_attivita_data_dipendente ON attivita(data_inizio, dipendente_id);
CREATE INDEX idx_timbrature_data_dipendente ON timbrature(data, dipendente_id);

-- Per associazioni e ricerche
CREATE INDEX idx_association_queue_stato_created ON association_queue(stato, created_at);

-- =====================================================
-- FINE SCHEMA MASTER DATA FISSI
-- 
-- Questo schema crea una base solida con:
-- - 15 dipendenti fissi e certi
-- - Aziende configurabili
-- - Veicoli gestibili
-- - Sistema associazioni dinamiche
-- - Sincronizzazione automatica legacy↔master
-- - Performance ottimizzate con indici e viste
-- =====================================================