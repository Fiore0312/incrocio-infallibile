-- Aggiunta campo validation_alerts alla tabella kpi_giornalieri
ALTER TABLE `kpi_giornalieri` 
ADD COLUMN `validation_alerts` JSON DEFAULT NULL COMMENT 'Array di alert di validazione per questo KPI'
AFTER `vehicle_usage`;

-- Aggiorna indici per performance
ALTER TABLE `kpi_giornalieri` 
ADD INDEX `idx_efficiency_validation` (`efficiency_rate`, `validation_alerts`(50));