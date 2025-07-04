-- ================================
-- MASTER REFERENCE TABLES MIGRATION
-- Soluzione per normalizzazione dati e prevenzione duplicati
-- ================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ================================
-- 1. TABELLE MASTER DI RIFERIMENTO
-- ================================

-- Master dipendenti - dati consolidati e normalizzati
CREATE TABLE `master_dipendenti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `cognome` varchar(50) NOT NULL,
  `nome_completo` varchar(101) GENERATED ALWAYS AS (CONCAT(nome, ' ', cognome)) STORED,
  `email` varchar(100) DEFAULT NULL,
  `costo_giornaliero` decimal(8,2) DEFAULT 80.00,
  `ruolo` enum('tecnico','manager','amministrativo') DEFAULT 'tecnico',
  `attivo` tinyint(1) DEFAULT 1,
  `data_assunzione` date DEFAULT NULL,
  `fonte_origine` enum('csv','manual','teamviewer','calendar') DEFAULT 'manual',
  `note_parsing` text DEFAULT NULL COMMENT 'Note su come è stato parsato il nome dal CSV',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome_cognome_unique` (`nome`, `cognome`),
  UNIQUE KEY `nome_completo_unique` (`nome_completo`),
  INDEX `idx_attivo` (`attivo`),
  INDEX `idx_fonte` (`fonte_origine`),
  FULLTEXT KEY `ft_nome_completo` (`nome_completo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Master veicoli - consolidamento auto aziendali
CREATE TABLE `master_veicoli` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(30) NOT NULL,
  `modello` varchar(50) DEFAULT NULL,
  `marca` varchar(30) DEFAULT NULL,
  `targa` varchar(10) DEFAULT NULL,
  `anno` year DEFAULT NULL,
  `costo_km` decimal(5,2) DEFAULT 0.35,
  `attivo` tinyint(1) DEFAULT 1,
  `note` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome_unique` (`nome`),
  UNIQUE KEY `targa_unique` (`targa`),
  INDEX `idx_attivo` (`attivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Master clienti - consolidamento aziende clienti
CREATE TABLE `master_clienti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `nome_normalized` varchar(100) GENERATED ALWAYS AS (UPPER(TRIM(nome))) STORED,
  `indirizzo` varchar(200) DEFAULT NULL,
  `citta` varchar(50) DEFAULT NULL,
  `provincia` varchar(2) DEFAULT NULL,
  `codice_gestionale` varchar(20) DEFAULT NULL,
  `fonte_origine` enum('csv','teamviewer','manual','calendar') DEFAULT 'manual',
  `computer_names` json DEFAULT NULL COMMENT 'Nomi computer da TeamViewer',
  `attivo` tinyint(1) DEFAULT 1,
  `note` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome_unique` (`nome`),
  UNIQUE KEY `nome_normalized_unique` (`nome_normalized`),
  INDEX `idx_attivo` (`attivo`),
  INDEX `idx_fonte` (`fonte_origine`),
  FULLTEXT KEY `ft_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Master progetti - consolidamento progetti aziendali  
CREATE TABLE `master_progetti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codice` varchar(20) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `capo_progetto_id` int(11) DEFAULT NULL,
  `stato` enum('attivo','sospeso','completato','cancellato') DEFAULT 'attivo',
  `priorita` enum('bassa','media','alta','critica') DEFAULT 'media',
  `data_inizio` date DEFAULT NULL,
  `data_scadenza` date DEFAULT NULL,
  `budget_ore` decimal(8,2) DEFAULT NULL,
  `fonte_origine` enum('csv','manual') DEFAULT 'manual',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codice_unique` (`codice`),
  KEY `fk_master_progetti_cliente` (`cliente_id`),
  KEY `fk_master_progetti_capo` (`capo_progetto_id`),
  INDEX `idx_stato` (`stato`),
  INDEX `idx_priorita` (`priorita`),
  CONSTRAINT `fk_master_progetti_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `master_clienti` (`id`),
  CONSTRAINT `fk_master_progetti_capo` FOREIGN KEY (`capo_progetto_id`) REFERENCES `master_dipendenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================
-- 2. TABELLE DI MAPPING/ALIASING
-- ================================

-- Mapping per gestire alias e varianti nomi dipendenti
CREATE TABLE `dipendenti_aliases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_dipendente_id` int(11) NOT NULL,
  `alias_nome` varchar(100) NOT NULL,
  `alias_cognome` varchar(100) DEFAULT '',
  `alias_completo` varchar(201) GENERATED ALWAYS AS (CONCAT(alias_nome, ' ', alias_cognome)) STORED,
  `fonte` enum('csv','teamviewer','calendar','manual') NOT NULL,
  `file_origine` varchar(200) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_aliases_master` (`master_dipendente_id`),
  UNIQUE KEY `alias_unique` (`alias_nome`, `alias_cognome`),
  INDEX `idx_fonte` (`fonte`),
  FULLTEXT KEY `ft_alias_completo` (`alias_completo`),
  CONSTRAINT `fk_aliases_master` FOREIGN KEY (`master_dipendente_id`) REFERENCES `master_dipendenti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mapping per clienti (alias aziende)
CREATE TABLE `clienti_aliases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_cliente_id` int(11) NOT NULL,
  `alias_nome` varchar(100) NOT NULL,
  `fonte` enum('csv','teamviewer','calendar','manual') NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_clienti_aliases_master` (`master_cliente_id`),
  UNIQUE KEY `alias_nome_unique` (`alias_nome`),
  INDEX `idx_fonte` (`fonte`),
  CONSTRAINT `fk_clienti_aliases_master` FOREIGN KEY (`master_cliente_id`) REFERENCES `master_clienti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================
-- 3. AGGIORNAMENTO TABELLE ESISTENTI
-- ================================

-- Aggiungi foreign key alle tabelle esistenti per referenziare master tables
ALTER TABLE `dipendenti` 
ADD COLUMN `master_dipendente_id` int(11) DEFAULT NULL,
ADD KEY `fk_dipendenti_master` (`master_dipendente_id`),
ADD CONSTRAINT `fk_dipendenti_master` FOREIGN KEY (`master_dipendente_id`) REFERENCES `master_dipendenti` (`id`);

ALTER TABLE `clienti` 
ADD COLUMN `master_cliente_id` int(11) DEFAULT NULL,
ADD KEY `fk_clienti_master` (`master_cliente_id`),
ADD CONSTRAINT `fk_clienti_master` FOREIGN KEY (`master_cliente_id`) REFERENCES `master_clienti` (`id`);

ALTER TABLE `veicoli` 
ADD COLUMN `master_veicolo_id` int(11) DEFAULT NULL,
ADD KEY `fk_veicoli_master` (`master_veicolo_id`),
ADD CONSTRAINT `fk_veicoli_master` FOREIGN KEY (`master_veicolo_id`) REFERENCES `master_veicoli` (`id`);

ALTER TABLE `progetti` 
ADD COLUMN `master_progetto_id` int(11) DEFAULT NULL,
ADD KEY `fk_progetti_master` (`master_progetto_id`),
ADD CONSTRAINT `fk_progetti_master` FOREIGN KEY (`master_progetto_id`) REFERENCES `master_progetti` (`id`);

-- ================================
-- 4. INDICI PER PERFORMANCE RICERCA
-- ================================

-- Indici per ricerca fuzzy e performance
CREATE INDEX `idx_dipendenti_nome_fuzzy` ON `master_dipendenti` (`nome`(10), `cognome`(10));
CREATE INDEX `idx_clienti_nome_fuzzy` ON `master_clienti` (`nome`(20));
CREATE INDEX `idx_veicoli_nome_fuzzy` ON `master_veicoli` (`nome`(10));

-- ================================
-- 5. VISTE PER COMPATIBILITÀ
-- ================================

-- Vista per mantenere compatibilità con query esistenti
CREATE VIEW `v_dipendenti_unified` AS
SELECT 
    d.id as dipendente_id,
    COALESCE(md.nome, d.nome) as nome,
    COALESCE(md.cognome, d.cognome) as cognome,
    COALESCE(md.nome_completo, CONCAT(d.nome, ' ', d.cognome)) as nome_completo,
    COALESCE(md.email, d.email) as email,
    COALESCE(md.costo_giornaliero, d.costo_giornaliero) as costo_giornaliero,
    COALESCE(md.ruolo, d.ruolo) as ruolo,
    COALESCE(md.attivo, d.attivo) as attivo,
    md.id as master_id,
    md.fonte_origine,
    d.created_at
FROM dipendenti d
LEFT JOIN master_dipendenti md ON d.master_dipendente_id = md.id;

-- Vista per clienti unified
CREATE VIEW `v_clienti_unified` AS
SELECT 
    c.id as cliente_id,
    COALESCE(mc.nome, c.nome) as nome,
    COALESCE(mc.indirizzo, c.indirizzo) as indirizzo,
    COALESCE(mc.citta, c.citta) as citta,
    COALESCE(mc.provincia, c.provincia) as provincia,
    COALESCE(mc.attivo, c.attivo) as attivo,
    mc.id as master_id,
    mc.fonte_origine,
    c.created_at
FROM clienti c
LEFT JOIN master_clienti mc ON c.master_cliente_id = mc.id;

-- ================================
-- 6. TRIGGER PER MANTENERE SINCRONIZZAZIONE
-- ================================

DELIMITER $$

-- Trigger per auto-sync dipendenti → master
CREATE TRIGGER `sync_dipendenti_to_master`
AFTER INSERT ON `dipendenti`
FOR EACH ROW
BEGIN
    DECLARE master_id INT DEFAULT NULL;
    
    -- Cerca se esiste già un master dipendente
    SELECT id INTO master_id 
    FROM master_dipendenti 
    WHERE nome = NEW.nome AND cognome = NEW.cognome 
    LIMIT 1;
    
    -- Se non esiste, crealo
    IF master_id IS NULL THEN
        INSERT INTO master_dipendenti (nome, cognome, email, costo_giornaliero, ruolo, attivo, fonte_origine)
        VALUES (NEW.nome, NEW.cognome, NEW.email, NEW.costo_giornaliero, NEW.ruolo, NEW.attivo, 'csv');
        SET master_id = LAST_INSERT_ID();
    END IF;
    
    -- Aggiorna il reference
    UPDATE dipendenti SET master_dipendente_id = master_id WHERE id = NEW.id;
END$$

-- Trigger per auto-sync clienti → master
CREATE TRIGGER `sync_clienti_to_master`
AFTER INSERT ON `clienti`
FOR EACH ROW
BEGIN
    DECLARE master_id INT DEFAULT NULL;
    
    -- Cerca se esiste già un master cliente
    SELECT id INTO master_id 
    FROM master_clienti 
    WHERE nome = NEW.nome 
    LIMIT 1;
    
    -- Se non esiste, crealo
    IF master_id IS NULL THEN
        INSERT INTO master_clienti (nome, indirizzo, citta, provincia, attivo, fonte_origine)
        VALUES (NEW.nome, NEW.indirizzo, NEW.citta, NEW.provincia, NEW.attivo, 'csv');
        SET master_id = LAST_INSERT_ID();
    END IF;
    
    -- Aggiorna il reference
    UPDATE clienti SET master_cliente_id = master_id WHERE id = NEW.id;
END$$

DELIMITER ;

-- ================================
-- COMMENTI E DOCUMENTAZIONE
-- ================================

/*
UTILIZZO DELLE MASTER TABLES:

1. NORMALIZZAZIONE DATI:
   - master_dipendenti: Fonte unica verità per dipendenti
   - master_clienti: Fonte unica verità per clienti
   - master_veicoli: Fonte unica verità per veicoli
   - master_progetti: Fonte unica verità per progetti

2. GESTIONE ALIAS:
   - dipendenti_aliases: Varianti nomi (Franco/Matteo → Franco, Matteo)
   - clienti_aliases: Varianti nomi aziende

3. ANTI-DUPLICAZIONE:
   - UNIQUE constraints su nome+cognome
   - Trigger automatici per sync
   - Foreign key per integrità

4. RICERCA MIGLIORATA:
   - FULLTEXT indices per ricerca fuzzy
   - Generated columns per performance
   - Viste unified per compatibilità

5. TRACCIABILITÀ:
   - fonte_origine per sapere da dove viene il dato
   - note_parsing per debugging
   - created_at/updated_at per audit

BENEFICI:
- ✅ Elimina duplicati alla fonte
- ✅ Permette parsing intelligente nomi composti
- ✅ Mantiene compatibilità con codice esistente
- ✅ Migliora performance ricerche
- ✅ Facilita manutenzione dati
*/

COMMIT;