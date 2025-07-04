-- ================================
-- EMPLOYEE ANALYTICS DATABASE SCHEMA
-- Ottimizzato per KPI e validazioni specifiche
-- ================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Database creation
CREATE DATABASE IF NOT EXISTS `employee_analytics` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `employee_analytics`;

-- ================================
-- TABELLE PRINCIPALI
-- ================================

-- Dipendenti con configurazione costi
CREATE TABLE `dipendenti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `cognome` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `costo_giornaliero` decimal(8,2) DEFAULT 80.00,
  `ruolo` enum('tecnico','manager','amministrativo') DEFAULT 'tecnico',
  `attivo` tinyint(1) DEFAULT 1,
  `data_assunzione` date DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome_cognome` (`nome`,`cognome`),
  INDEX `idx_attivo` (`attivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clienti
CREATE TABLE `clienti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `indirizzo` varchar(200) DEFAULT NULL,
  `citta` varchar(50) DEFAULT NULL,
  `provincia` varchar(2) DEFAULT NULL,
  `codice_gestionale` varchar(20) DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome_unique` (`nome`),
  INDEX `idx_attivo` (`attivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progetti
CREATE TABLE `progetti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codice` varchar(20) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `stato` enum('attivo','sospeso','completato','cancellato') DEFAULT 'attivo',
  `priorita` enum('bassa','media','alta','critica') DEFAULT 'media',
  `cliente_id` int(11) DEFAULT NULL,
  `capo_progetto_id` int(11) DEFAULT NULL,
  `data_inizio` date DEFAULT NULL,
  `data_scadenza` date DEFAULT NULL,
  `budget_ore` decimal(8,2) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codice_unique` (`codice`),
  KEY `fk_progetti_cliente` (`cliente_id`),
  KEY `fk_progetti_capo` (`capo_progetto_id`),
  INDEX `idx_stato` (`stato`),
  INDEX `idx_priorita` (`priorita`),
  CONSTRAINT `fk_progetti_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clienti` (`id`),
  CONSTRAINT `fk_progetti_capo` FOREIGN KEY (`capo_progetto_id`) REFERENCES `dipendenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Veicoli
CREATE TABLE `veicoli` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(30) NOT NULL,
  `targa` varchar(10) DEFAULT NULL,
  `modello` varchar(50) DEFAULT NULL,
  `costo_km` decimal(5,2) DEFAULT 0.35,
  `attivo` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome_unique` (`nome`),
  INDEX `idx_attivo` (`attivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================
-- TABELLE DATI OPERATIVI
-- ================================

-- Timbrature (da apprilevazionepresenze-timbrature-totali-base.csv)
CREATE TABLE `timbrature` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dipendente_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `data` date NOT NULL,
  `ora_inizio` time NOT NULL,
  `ora_fine` time NOT NULL,
  `ore_totali` decimal(5,2) NOT NULL,
  `ore_arrotondate` decimal(5,2) NOT NULL,
  `ore_nette_pause` decimal(5,2) NOT NULL,
  `pausa_minuti` int(11) DEFAULT 0,
  `indirizzo_start` varchar(200) DEFAULT NULL,
  `citta_start` varchar(50) DEFAULT NULL,
  `provincia_start` varchar(2) DEFAULT NULL,
  `indirizzo_end` varchar(200) DEFAULT NULL,
  `citta_end` varchar(50) DEFAULT NULL,
  `provincia_end` varchar(2) DEFAULT NULL,
  `descrizione_attivita` text DEFAULT NULL,
  `stato_timbratura` varchar(20) DEFAULT NULL,
  `timbratura_id_originale` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_timbrature_dipendente` (`dipendente_id`),
  KEY `fk_timbrature_cliente` (`cliente_id`),
  INDEX `idx_data` (`data`),
  INDEX `idx_dipendente_data` (`dipendente_id`, `data`),
  CONSTRAINT `fk_timbrature_dipendente` FOREIGN KEY (`dipendente_id`) REFERENCES `dipendenti` (`id`),
  CONSTRAINT `fk_timbrature_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clienti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attività/Ticket (da attivita.csv)
CREATE TABLE `attivita` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dipendente_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `progetto_id` int(11) DEFAULT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `data_inizio` datetime NOT NULL,
  `data_fine` datetime NOT NULL,
  `durata_ore` decimal(5,2) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `riferimento_progetto` varchar(50) DEFAULT NULL,
  `creato_da` varchar(100) DEFAULT NULL,
  `fatturabile` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_attivita_dipendente` (`dipendente_id`),
  KEY `fk_attivita_cliente` (`cliente_id`),
  KEY `fk_attivita_progetto` (`progetto_id`),
  INDEX `idx_data_inizio` (`data_inizio`),
  INDEX `idx_ticket` (`ticket_id`),
  INDEX `idx_dipendente_data` (`dipendente_id`, `data_inizio`),
  INDEX `idx_fatturabile` (`fatturabile`),
  CONSTRAINT `fk_attivita_dipendente` FOREIGN KEY (`dipendente_id`) REFERENCES `dipendenti` (`id`),
  CONSTRAINT `fk_attivita_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clienti` (`id`),
  CONSTRAINT `fk_attivita_progetto` FOREIGN KEY (`progetto_id`) REFERENCES `progetti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Richieste ferie/permessi (da apprilevazionepresenze-richieste.csv)
CREATE TABLE `richieste_assenze` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dipendente_id` int(11) NOT NULL,
  `tipo` enum('ferie','permessi','malattia','rol','ex_festivita') NOT NULL,
  `data_richiesta` datetime NOT NULL,
  `data_inizio` datetime NOT NULL,
  `data_fine` datetime NOT NULL,
  `stato` enum('approvata','rifiutata','in_attesa','annullata') DEFAULT 'in_attesa',
  `note` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_richieste_dipendente` (`dipendente_id`),
  INDEX `idx_data_inizio` (`data_inizio`),
  INDEX `idx_stato` (`stato`),
  INDEX `idx_tipo` (`tipo`),
  CONSTRAINT `fk_richieste_dipendente` FOREIGN KEY (`dipendente_id`) REFERENCES `dipendenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Calendario (da calendario.csv)
CREATE TABLE `calendario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dipendente_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `titolo` varchar(200) NOT NULL,
  `data_inizio` datetime NOT NULL,
  `data_fine` datetime NOT NULL,
  `location` varchar(200) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `priorita` int(11) DEFAULT 5,
  `categoria` varchar(50) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_calendario_dipendente` (`dipendente_id`),
  KEY `fk_calendario_cliente` (`cliente_id`),
  INDEX `idx_data_inizio` (`data_inizio`),
  INDEX `idx_dipendente_data` (`dipendente_id`, `data_inizio`),
  CONSTRAINT `fk_calendario_dipendente` FOREIGN KEY (`dipendente_id`) REFERENCES `dipendenti` (`id`),
  CONSTRAINT `fk_calendario_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clienti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro auto (da registro_auto.csv)
CREATE TABLE `registro_auto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dipendente_id` int(11) NOT NULL,
  `veicolo_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `data` date NOT NULL,
  `ora_presa` datetime DEFAULT NULL,
  `ora_riconsegna` datetime DEFAULT NULL,
  `km_partenza` int(11) DEFAULT NULL,
  `km_arrivo` int(11) DEFAULT NULL,
  `km_totali` int(11) DEFAULT NULL,
  `costo_stimato` decimal(8,2) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_registro_dipendente` (`dipendente_id`),
  KEY `fk_registro_veicolo` (`veicolo_id`),
  KEY `fk_registro_cliente` (`cliente_id`),
  INDEX `idx_data` (`data`),
  INDEX `idx_dipendente_data` (`dipendente_id`, `data`),
  CONSTRAINT `fk_registro_dipendente` FOREIGN KEY (`dipendente_id`) REFERENCES `dipendenti` (`id`),
  CONSTRAINT `fk_registro_veicolo` FOREIGN KEY (`veicolo_id`) REFERENCES `veicoli` (`id`),
  CONSTRAINT `fk_registro_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clienti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessioni TeamViewer (da teamviewer_bait.csv e teamviewer_gruppo.csv)
CREATE TABLE `teamviewer_sessioni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dipendente_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `nome_cliente` varchar(100) DEFAULT NULL,
  `email_cliente` varchar(100) DEFAULT NULL,
  `codice_sessione` varchar(50) DEFAULT NULL,
  `tipo_sessione` varchar(50) DEFAULT NULL,
  `gruppo` varchar(100) DEFAULT NULL,
  `data_inizio` datetime NOT NULL,
  `data_fine` datetime NOT NULL,
  `durata_minuti` int(11) NOT NULL,
  `tariffa` decimal(8,2) DEFAULT 0.00,
  `modalita_calcolo` varchar(20) DEFAULT 'fattura',
  `descrizione` text DEFAULT NULL,
  `note` text DEFAULT NULL,
  `classificazione` varchar(50) DEFAULT NULL,
  `fatturabile` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_teamviewer_dipendente` (`dipendente_id`),
  KEY `fk_teamviewer_cliente` (`cliente_id`),
  INDEX `idx_data_inizio` (`data_inizio`),
  INDEX `idx_dipendente_data` (`dipendente_id`, `data_inizio`),
  INDEX `idx_fatturabile` (`fatturabile`),
  CONSTRAINT `fk_teamviewer_dipendente` FOREIGN KEY (`dipendente_id`) REFERENCES `dipendenti` (`id`),
  CONSTRAINT `fk_teamviewer_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clienti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================
-- TABELLE ANALYTICS E VALIDAZIONI
-- ================================

-- KPI giornalieri calcolati
CREATE TABLE `kpi_giornalieri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dipendente_id` int(11) NOT NULL,
  `data` date NOT NULL,
  `ore_timbrature` decimal(5,2) DEFAULT 0.00,
  `ore_attivita` decimal(5,2) DEFAULT 0.00,
  `ore_calendario` decimal(5,2) DEFAULT 0.00,
  `ore_fatturabili` decimal(5,2) DEFAULT 0.00,
  `efficiency_rate` decimal(5,2) DEFAULT 0.00,
  `costo_giornaliero` decimal(8,2) DEFAULT 0.00,
  `ricavo_stimato` decimal(8,2) DEFAULT 0.00,
  `profit_loss` decimal(8,2) DEFAULT 0.00,
  `remote_sessions` int(11) DEFAULT 0,
  `onsite_hours` decimal(5,2) DEFAULT 0.00,
  `travel_hours` decimal(5,2) DEFAULT 0.00,
  `vehicle_usage` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dipendente_data` (`dipendente_id`, `data`),
  KEY `fk_kpi_dipendente` (`dipendente_id`),
  INDEX `idx_data` (`data`),
  INDEX `idx_efficiency` (`efficiency_rate`),
  INDEX `idx_profit_loss` (`profit_loss`),
  CONSTRAINT `fk_kpi_dipendente` FOREIGN KEY (`dipendente_id`) REFERENCES `dipendenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anomalie e alert system
CREATE TABLE `anomalie` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dipendente_id` int(11) NOT NULL,
  `data` date NOT NULL,
  `tipo_anomalia` enum('ore_eccessive','sovrapposizioni','rapportini_mancanti','trasferte_incongruenti','ore_insufficienti','sessioni_orphan') NOT NULL,
  `severita` enum('bassa','media','alta','critica') DEFAULT 'media',
  `descrizione` text NOT NULL,
  `dettagli_json` json DEFAULT NULL,
  `risolto` tinyint(1) DEFAULT 0,
  `note_risoluzione` text DEFAULT NULL,
  `risolto_da` int(11) DEFAULT NULL,
  `risolto_il` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_anomalie_dipendente` (`dipendente_id`),
  KEY `fk_anomalie_risolto_da` (`risolto_da`),
  INDEX `idx_data` (`data`),
  INDEX `idx_tipo_anomalia` (`tipo_anomalia`),
  INDEX `idx_severita` (`severita`),
  INDEX `idx_risolto` (`risolto`),
  CONSTRAINT `fk_anomalie_dipendente` FOREIGN KEY (`dipendente_id`) REFERENCES `dipendenti` (`id`),
  CONSTRAINT `fk_anomalie_risolto_da` FOREIGN KEY (`risolto_da`) REFERENCES `dipendenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurazioni sistema
CREATE TABLE `configurazioni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chiave` varchar(100) NOT NULL,
  `valore` text NOT NULL,
  `tipo` enum('string','integer','float','boolean','json') DEFAULT 'string',
  `descrizione` text DEFAULT NULL,
  `categoria` varchar(50) DEFAULT 'generale',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chiave_unique` (`chiave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================
-- VISTE OTTIMIZZATE PER ANALYTICS
-- ================================

-- Vista daily performance per dipendente
CREATE VIEW `v_daily_performance` AS
SELECT 
    d.id as dipendente_id,
    d.nome,
    d.cognome,
    k.data,
    k.ore_timbrature,
    k.ore_fatturabili,
    k.efficiency_rate,
    k.profit_loss,
    k.costo_giornaliero,
    k.ricavo_stimato,
    CASE 
        WHEN k.ore_fatturabili < 7 AND ra.id IS NULL THEN 'ALERT'
        WHEN k.efficiency_rate < 70 THEN 'WARNING'
        ELSE 'OK'
    END as status_flag,
    COUNT(a.id) as anomalie_count
FROM dipendenti d
LEFT JOIN kpi_giornalieri k ON d.id = k.dipendente_id
LEFT JOIN richieste_assenze ra ON d.id = ra.dipendente_id 
    AND DATE(ra.data_inizio) <= k.data 
    AND DATE(ra.data_fine) >= k.data 
    AND ra.stato = 'approvata'
LEFT JOIN anomalie a ON d.id = a.dipendente_id 
    AND a.data = k.data 
    AND a.risolto = 0
WHERE d.attivo = 1
GROUP BY d.id, k.data;

-- Vista correlation score
CREATE VIEW `v_correlation_metrics` AS
SELECT 
    dipendente_id,
    data,
    ore_timbrature,
    ore_attivita,
    ore_calendario,
    ABS(ore_timbrature - ore_attivita) as gap_timbrature_attivita,
    ABS(ore_timbrature - ore_calendario) as gap_timbrature_calendario,
    CASE 
        WHEN ABS(ore_timbrature - ore_attivita) <= 1 AND ABS(ore_timbrature - ore_calendario) <= 1 THEN 100
        WHEN ABS(ore_timbrature - ore_attivita) <= 2 OR ABS(ore_timbrature - ore_calendario) <= 2 THEN 75
        WHEN ABS(ore_timbrature - ore_attivita) <= 3 OR ABS(ore_timbrature - ore_calendario) <= 3 THEN 50
        ELSE 25
    END as correlation_score
FROM kpi_giornalieri
WHERE ore_timbrature > 0;

-- ================================
-- DATI INIZIALI DI CONFIGURAZIONE
-- ================================

-- Inserimento configurazioni base
INSERT INTO `configurazioni` (`chiave`, `valore`, `tipo`, `descrizione`, `categoria`) VALUES
('costo_dipendente_default', '80.00', 'float', 'Costo giornaliero default per dipendente', 'costi'),
('ore_lavorative_giornaliere', '8', 'integer', 'Ore lavorative standard per giornata', 'parametri'),
('tolleranza_ore_max', '1.0', 'float', 'Tolleranza massima in ore per validazioni', 'validazioni'),
('tariffa_oraria_standard', '50.00', 'float', 'Tariffa oraria standard per fatturazione', 'ricavi'),
('alert_ore_minime', '7', 'integer', 'Soglia minima ore per alert', 'alert'),
('vehicle_cost_per_km', '0.35', 'float', 'Costo per km veicolo aziendale', 'costi');

-- Inserimento veicoli base
INSERT INTO `veicoli` (`nome`, `modello`, `costo_km`, `attivo`) VALUES
('Punto', 'Fiat Punto', 0.35, 1),
('Fiesta', 'Ford Fiesta', 0.35, 1),
('Peugeot', 'Peugeot 208', 0.35, 1);

COMMIT;