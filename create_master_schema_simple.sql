-- =====================================================
-- SCHEMA MASTER DATA ULTRA-SEMPLIFICATO
-- Versione pulita senza prepared statements o complessità
-- Solo CREATE TABLE e INSERT essenziali
-- =====================================================

-- Disabilita foreign key checks per operazioni sicure
SET FOREIGN_KEY_CHECKS = 0;

-- Elimina tabelle esistenti in ordine sicuro
DROP TABLE IF EXISTS clienti_aziende;
DROP TABLE IF EXISTS association_queue;
DROP TABLE IF EXISTS master_progetti;
DROP TABLE IF EXISTS master_aziende;
DROP TABLE IF EXISTS master_dipendenti_fixed;
DROP TABLE IF EXISTS master_veicoli_config;
DROP TABLE IF EXISTS system_config;

-- Riabilita foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- TABELLE MASTER DATA
-- =====================================================

-- 1. DIPENDENTI FISSI (15 dipendenti certi)
CREATE TABLE master_dipendenti_fixed (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    cognome VARCHAR(50) NOT NULL,
    nome_completo VARCHAR(100) AS (CONCAT(nome, ' ', cognome)) STORED,
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

-- 2. AZIENDE MASTER
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_nome (nome),
    INDEX idx_nome_breve (nome_breve),
    INDEX idx_codice_cliente (codice_cliente),
    INDEX idx_attivo (attivo)
);

-- 3. VEICOLI CONFIGURABILI
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

-- 4. CLIENTI AZIENDE (associazioni dinamiche)
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
    ) STORED,
    azienda_id INT,
    ruolo VARCHAR(100),
    email VARCHAR(100),
    telefono VARCHAR(20),
    reparto VARCHAR(100),
    note TEXT,
    attivo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_azienda (azienda_id),
    INDEX idx_nome_completo (nome_completo),
    INDEX idx_attivo (attivo),
    FOREIGN KEY (azienda_id) REFERENCES master_aziende(id) ON DELETE SET NULL
);

-- 5. CODA ASSOCIAZIONI (per UI dinamica)
CREATE TABLE association_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome_cliente VARCHAR(200) NOT NULL,
    fonte_import VARCHAR(50),
    azienda_suggerita_id INT,
    azienda_assegnata_id INT,
    stato ENUM('pending', 'assigned', 'rejected', 'ignored') DEFAULT 'pending',
    confidenza_match DECIMAL(3,2),
    note_admin TEXT,
    processed_by VARCHAR(100),
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_azienda_suggerita (azienda_suggerita_id),
    INDEX idx_azienda_assegnata (azienda_assegnata_id),
    INDEX idx_stato (stato),
    INDEX idx_fonte (fonte_import),
    INDEX idx_processed (processed_at),
    FOREIGN KEY (azienda_suggerita_id) REFERENCES master_aziende(id) ON DELETE SET NULL,
    FOREIGN KEY (azienda_assegnata_id) REFERENCES master_aziende(id) ON DELETE SET NULL
);

-- 6. PROGETTI DINAMICI
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
    ore_effettive INT DEFAULT 0,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_azienda (azienda_id),
    UNIQUE KEY unique_codice (codice),
    INDEX idx_stato (stato),
    INDEX idx_date_range (data_inizio, data_fine),
    FOREIGN KEY (azienda_id) REFERENCES master_aziende(id) ON DELETE SET NULL
);

-- 7. CONFIGURAZIONI SISTEMA
CREATE TABLE system_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    categoria VARCHAR(50) NOT NULL,
    chiave VARCHAR(100) NOT NULL,
    valore TEXT,
    tipo ENUM('string', 'int', 'float', 'boolean', 'json', 'date') DEFAULT 'string',
    descrizione TEXT,
    validazione VARCHAR(200),
    modificabile BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_categoria_chiave (categoria, chiave),
    INDEX idx_categoria (categoria),
    INDEX idx_modificabile (modificabile)
);

-- =====================================================
-- INSERIMENTO DATI ESSENZIALI
-- =====================================================

-- Inserisci i 15 dipendenti fissi richiesti
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

-- Inserisci aziende base dal file attività
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

-- Inserisci veicoli base
INSERT INTO master_veicoli_config (nome, tipo, marca, modello) VALUES
('Punto', 'Auto', 'Fiat', 'Punto'),
('Fiesta', 'Auto', 'Ford', 'Fiesta'),
('Peugeot', 'Auto', 'Peugeot', 'Generico'),
('Aurora', 'Auto', 'Generico', 'Aurora'),
('Furgone Aziendale', 'Furgone', 'Iveco', 'Daily');

-- Inserisci configurazioni base del sistema
INSERT INTO system_config (categoria, chiave, valore, tipo, descrizione) VALUES
('generale', 'ore_lavoro_giorno', '8', 'int', 'Ore lavorative standard per giorno'),
('generale', 'giorni_lavoro_settimana', '5', 'int', 'Giorni lavorativi per settimana'),
('generale', 'costo_giornaliero_default', '200.00', 'float', 'Costo giornaliero default'),
('generale', 'azienda_principale', 'ITX ITALIA SRL', 'string', 'Nome azienda principale'),
('import', 'auto_associate_clients', 'true', 'boolean', 'Associazione automatica clienti-aziende'),
('import', 'confidence_threshold', '0.80', 'float', 'Soglia confidenza associazioni automatiche'),
('import', 'duplicate_time_window', '3', 'int', 'Finestra temporale rilevamento duplicati'),
('kpi', 'efficiency_warning_threshold', '70', 'int', 'Soglia % efficiency per warning'),
('kpi', 'efficiency_critical_threshold', '50', 'int', 'Soglia % efficiency per critical alert');

-- =====================================================
-- SCHEMA MASTER DATA COMPLETATO
-- Versione ultra-semplificata e testata per MariaDB
-- =====================================================