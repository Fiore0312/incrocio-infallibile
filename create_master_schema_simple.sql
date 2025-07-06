-- ================================
-- MASTER SCHEMA SIMPLE - MariaDB Compatible
-- Schema semplificato per setup rapido
-- ================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Database creation (se non esiste)
CREATE DATABASE IF NOT EXISTS `employee_analytics` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `employee_analytics`;

-- ================================
-- TABELLE MASTER DIPENDENTI CONSOLIDATE
-- ================================

-- Master dipendenti - tabella principale consolidata
CREATE TABLE IF NOT EXISTS `master_dipendenti_fixed` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Master aziende - clienti consolidati
CREATE TABLE IF NOT EXISTS `master_aziende` (
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

-- Master veicoli configurazione
CREATE TABLE IF NOT EXISTS `master_veicoli_config` (
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

-- ================================
-- TABELLE DI MAPPING/ASSOCIAZIONE
-- ================================

-- Queue associazioni per clienti non riconosciuti
CREATE TABLE IF NOT EXISTS `association_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(200) NOT NULL,
  `source_file` varchar(100) NOT NULL,
  `source_type` enum('teamviewer','calendar','timbrature','attivita') NOT NULL,
  `suggested_master_id` int(11) DEFAULT NULL,
  `confidence_score` decimal(5,2) DEFAULT 0.00,
  `status` enum('pending','processed','rejected') DEFAULT 'pending',
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_association_suggested` (`suggested_master_id`),
  KEY `fk_association_processed_by` (`processed_by`),
  INDEX `idx_status` (`status`),
  INDEX `idx_source_type` (`source_type`),
  CONSTRAINT `fk_association_suggested` FOREIGN KEY (`suggested_master_id`) REFERENCES `master_aziende` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_association_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `master_dipendenti_fixed` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clienti e aziende - mapping per legacy compatibility
CREATE TABLE IF NOT EXISTS `clienti_aziende` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_azienda_id` int(11) NOT NULL,
  `legacy_name` varchar(100) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_clienti_master` (`master_azienda_id`),
  UNIQUE KEY `legacy_name_unique` (`legacy_name`),
  INDEX `idx_is_primary` (`is_primary`),
  CONSTRAINT `fk_clienti_master` FOREIGN KEY (`master_azienda_id`) REFERENCES `master_aziende` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================
-- CONFIGURAZIONI SISTEMA
-- ================================

-- System config per il setup master
CREATE TABLE IF NOT EXISTS `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `config_type` enum('string','integer','float','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `is_system` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key_unique` (`config_key`),
  INDEX `idx_category` (`category`),
  INDEX `idx_is_system` (`is_system`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================
-- DATI INIZIALI
-- ================================

-- Configurazioni base del sistema
INSERT IGNORE INTO `system_config` (`config_key`, `config_value`, `config_type`, `description`, `category`, `is_system`) VALUES
('master_schema_version', '2.0', 'string', 'Versione schema master data', 'system', 1),
('employee_validation_enabled', '1', 'boolean', 'Abilita validazione automatica nomi dipendenti', 'import', 1),
('auto_create_employees', '1', 'boolean', 'Creazione automatica dipendenti da CSV', 'import', 1),
('auto_create_companies', '1', 'boolean', 'Creazione automatica aziende da CSV', 'import', 1),
('default_employee_cost', '80.00', 'float', 'Costo giornaliero default dipendente', 'costs', 0),
('default_vehicle_cost_km', '0.35', 'float', 'Costo per km default veicoli', 'costs', 0),
('duplicate_threshold', '0.80', 'float', 'Soglia similarità per duplicati', 'validation', 0),
('max_association_suggestions', '5', 'integer', 'Massimo suggerimenti associazione', 'import', 0);

-- Veicoli aziendali base
INSERT IGNORE INTO `master_veicoli_config` (`nome`, `modello`, `marca`, `costo_km`, `attivo`) VALUES
('Punto', 'Punto', 'Fiat', 0.35, 1),
('Fiesta', 'Fiesta', 'Ford', 0.35, 1),
('Peugeot', '208', 'Peugeot', 0.35, 1);

-- Dipendenti fissi (se non esistono già)
INSERT IGNORE INTO `master_dipendenti_fixed` (`nome`, `cognome`, `ruolo`, `costo_giornaliero`, `fonte_origine`, `attivo`) VALUES
('Niccolò', 'Ragusa', 'tecnico', 200.00, 'manual', 1),
('Davide', 'Cestone', 'tecnico', 200.00, 'manual', 1),
('Arlind', 'Hoxha', 'tecnico', 200.00, 'manual', 1),
('Lorenzo', 'Serratore', 'tecnico', 200.00, 'manual', 1),
('Gabriele', 'De Palma', 'tecnico', 200.00, 'manual', 1),
('Franco', 'Fiorellino', 'manager', 250.00, 'manual', 1),
('Matteo', 'Signo', 'tecnico', 200.00, 'manual', 1),
('Marco', 'Birocchi', 'tecnico', 200.00, 'manual', 1),
('Roberto', 'Birocchi', 'manager', 250.00, 'manual', 1),
('Alex', 'Ferrario', 'tecnico', 200.00, 'manual', 1),
('Gianluca', 'Ghirindelli', 'tecnico', 200.00, 'manual', 1),
('Matteo', 'Di Salvo', 'tecnico', 200.00, 'manual', 1),
('Cristian', 'La Bella', 'tecnico', 200.00, 'manual', 1),
('Giuseppe', 'Anastasio', 'tecnico', 200.00, 'manual', 1);

-- Aziende principali (esempi)
INSERT IGNORE INTO `master_aziende` (`nome`, `fonte_origine`, `attivo`) VALUES
('BAIT Service S.r.l.', 'manual', 1),
('Electraline CBB', 'manual', 1),
('Wittmann', 'manual', 1),
('ISOTERMA', 'manual', 1),
('MAXMODA', 'manual', 1),
('Garibaldina', 'manual', 1),
('AMROP', 'manual', 1),
('Silanos', 'manual', 1);

COMMIT;

-- ================================
-- VISTE PER COMPATIBILITÀ LEGACY
-- ================================

-- Vista dipendenti per compatibilità
CREATE OR REPLACE VIEW `dipendenti` AS
SELECT 
    id,
    nome,
    cognome,
    email,
    costo_giornaliero,
    ruolo,
    attivo,
    data_assunzione,
    created_at,
    updated_at
FROM `master_dipendenti_fixed`
WHERE attivo = 1;

-- Vista clienti per compatibilità  
CREATE OR REPLACE VIEW `clienti` AS
SELECT 
    id,
    nome,
    indirizzo,
    citta,
    provincia,
    codice_gestionale,
    attivo,
    created_at,
    updated_at
FROM `master_aziende`
WHERE attivo = 1;

-- Vista veicoli per compatibilità
CREATE OR REPLACE VIEW `veicoli` AS
SELECT 
    id,
    nome,
    targa,
    modello,
    costo_km,
    attivo,
    created_at
FROM `master_veicoli_config`
WHERE attivo = 1;