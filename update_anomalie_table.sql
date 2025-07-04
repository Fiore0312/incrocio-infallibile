-- Aggiorna enum tipo_anomalia per supportare i nuovi tipi di validazione
ALTER TABLE `anomalie` 
MODIFY COLUMN `tipo_anomalia` enum(
    'ore_eccessive',
    'sovrapposizioni',
    'rapportini_mancanti',
    'trasferte_incongruenti',
    'ore_insufficienti',
    'sessioni_orphan',
    'FATTURABILI_EXCEED_TIMBRATURE',
    'TIMBRATURE_ATTIVITA_MISMATCH',
    'MISSING_TIMESHEET',
    'HIGH_EFFICIENCY',
    'BILLABLE_WITHOUT_TIMESHEET'
) NOT NULL;