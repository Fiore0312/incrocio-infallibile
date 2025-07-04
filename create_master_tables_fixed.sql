-- ================================
-- MASTER REFERENCE TABLES MIGRATION - FIXED VERSION
-- Soluzione per normalizzazione dati e prevenzione duplicati
-- ================================

-- ================================
-- 1. TABELLE MASTER DI RIFERIMENTO
-- ================================

-- Master dipendenti - dati consolidati e normalizzati
CREATE TABLE IF NOT EXISTS `master_dipendenti` (
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
CREATE TABLE IF NOT EXISTS `master_veicoli` (
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
CREATE TABLE IF NOT EXISTS `master_clienti` (
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
CREATE TABLE IF NOT EXISTS `master_progetti` (
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
CREATE TABLE IF NOT EXISTS `dipendenti_aliases` (
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
CREATE TABLE IF NOT EXISTS `clienti_aliases` (
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