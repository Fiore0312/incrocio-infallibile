-- ================================
-- FIX DATABASE CONSTRAINTS
-- Aggiunge indici unici per prevenire duplicati
-- ================================

-- 1. Aggiungi indice unico per prevenire attività duplicate
-- Considera duplicata un'attività con stesso dipendente, stesse date e stessa durata
ALTER TABLE attivita 
ADD UNIQUE INDEX `idx_unique_activity` (
    `dipendente_id`, 
    `data_inizio`, 
    `data_fine`, 
    `durata_ore`
);

-- 2. Aggiungi indice unico per prevenire timbrature duplicate  
-- Considera duplicata una timbratura con stesso dipendente e stessa data
ALTER TABLE timbrature
ADD UNIQUE INDEX `idx_unique_timesheet` (
    `dipendente_id`,
    `data`,
    `ora_inizio`,
    `ora_fine`
);

-- 3. Aggiungi indice unico per prevenire eventi calendario duplicati
-- Considera duplicato un evento con stesso dipendente, stesso titolo e stessa data inizio
ALTER TABLE calendario
ADD UNIQUE INDEX `idx_unique_calendar` (
    `dipendente_id`,
    `data_inizio`,
    `titolo`(100)  -- Usa solo primi 100 caratteri del titolo
);

-- 4. Aggiungi constraint per validation data logica
-- Assicura che data_fine >= data_inizio nelle attività
ALTER TABLE attivita 
ADD CONSTRAINT `chk_activity_dates` 
CHECK (`data_fine` >= `data_inizio`);

-- 5. Aggiungi constraint per validation ore positive
-- Assicura che durata_ore sia positiva
ALTER TABLE attivita 
ADD CONSTRAINT `chk_activity_duration` 
CHECK (`durata_ore` > 0);

-- 6. Aggiungi constraint per validation ore timbrature
-- Assicura che ore_totali sia positiva
ALTER TABLE timbrature 
ADD CONSTRAINT `chk_timesheet_hours` 
CHECK (`ore_totali` > 0);

-- 7. Miglioramento campo validation_alerts in kpi_giornalieri (se non esiste)
-- Aggiungi il campo se non è già presente dalle correzioni precedenti
ALTER TABLE kpi_giornalieri 
ADD COLUMN IF NOT EXISTS `validation_alerts` JSON DEFAULT NULL 
COMMENT 'Alert di validazione per questo KPI';

-- 8. Indice per performance su validation_alerts
CREATE INDEX IF NOT EXISTS `idx_validation_alerts` 
ON kpi_giornalieri ((CAST(validation_alerts AS CHAR(255))));

-- ================================
-- COMMENTI E NOTE
-- ================================

/*
Questo script aggiunge constraint e indici per:

1. PREVENIRE DUPLICATI:
   - Attività identiche (stesso dipendente, date, durata)
   - Timbrature identiche (stesso dipendente, data, orari)
   - Eventi calendario identici (stesso dipendente, data, titolo)

2. VALIDARE DATI:
   - Date logiche (fine >= inizio)
   - Ore positive (> 0)
   - Constraint di integrità

3. MIGLIORARE PERFORMANCE:
   - Indici ottimizzati per query comuni
   - Indici per validation_alerts

ATTENZIONE:
- Eseguire DOPO aver pulito i duplicati esistenti
- Testare su backup prima della produzione
- Monitorare performance dopo l'applicazione

Per applicare:
mysql -u username -p database_name < fix_database_constraints.sql
*/