# Ricerca Definizione Tabella master_dipendenti_fixed

## Problema Principale: Trovare la definizione esatta della tabella master_dipendenti_fixed

### Situazione Attuale
Il user richiede di trovare la definizione esatta della tabella `master_dipendenti_fixed` e comprendere le differenze schema tra:
- `dipendenti` (tabella legacy)
- `master_dipendenti` (prima versione master)
- `master_dipendenti_fixed` (versione corretta master)

## Piano di Ricerca e Analisi

### **FASE 1: Ricerca Definizione Schema**
- [x] ✅ **Cercare CREATE TABLE statements** - Cercato nei file SQL
- [x] ✅ **Analizzare file PHP setup** - Analizzati setup_master_schema.php e simple_setup_mariadb.php
- [x] ✅ **Verificare file SQL esistenti** - Analizzati create_master_tables.sql e create_master_tables_fixed.sql
- [ ] **Controllare database live** - Usare DESCRIBE per vedere schema attuale
- [ ] **Analizzare log di setup** - Verificare script di creazione effettivi

### **FASE 2: Mappatura Schema Differences**
- [ ] **Confrontare structure dipendenti** - Schema tabella legacy
- [ ] **Confrontare structure master_dipendenti** - Schema prima versione master
- [ ] **Confrontare structure master_dipendenti_fixed** - Schema versione corretta
- [ ] **Documentare differenze** - Colonne, tipi, constraint, indici

### **FASE 3: Verifica Implementazione**
- [ ] **Verificare quale tabella è in uso** - Controllare database attuale
- [ ] **Analizzare query nei file PHP** - Vedere quale schema utilizza il codice
- [ ] **Identificare migration path** - Piano per standardizzazione

## Risultati Parziali Trovati

### **File Analizzati**
1. **create_master_tables.sql** - Contiene `master_dipendenti` (NON _fixed)
2. **create_master_tables_fixed.sql** - Contiene `master_dipendenti` (NON _fixed)
3. **setup_master_schema.php** - Riferisce a file SQL esterno `create_fixed_master_schema_mariadb.sql` (NON TROVATO)
4. **simple_setup_mariadb.php** - Riferisce a file SQL esterno `create_master_schema_simple.sql` (NON TROVATO)

### **Schema Trovato per master_dipendenti**
```sql
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
```

### **File SQL Mancanti**
- [ ] ❌ **create_fixed_master_schema_mariadb.sql** - File referenziato ma non trovato
- [ ] ❌ **create_master_schema_simple.sql** - File referenziato ma non trovato

## Prossimi Passi

### **1. Verificare Database Live**
```bash
# Controllare se la tabella esiste
mysql -u root -p -e "DESCRIBE master_dipendenti_fixed;" employee_analytics

# Vedere tutte le tabelle dipendenti-related
mysql -u root -p -e "SHOW TABLES LIKE '%dipendenti%';" employee_analytics
```

### **2. Analizzare Setup Scripts**
- [ ] Eseguire `database_structure_analysis.php` per vedere schema attuale
- [ ] Controllare log di setup per vedere cosa è stato creato
- [ ] Verificare quale script ha effettivamente creato le tabelle

### **3. Trovare Schema Embedded**
- [ ] Cercare CREATE TABLE statements embedded nei file PHP
- [ ] Controllare se il schema è hardcoded nei setup scripts
- [ ] Analizzare tutti i file che contengono riferimenti alla tabella

### **4. Documentare Differenze**
Una volta trovati tutti e tre gli schemi:
- [ ] Creare tabella comparativa delle colonne
- [ ] Documentare differenze nei tipi di dati
- [ ] Identificare constraint e indici diversi
- [ ] Spiegare le motivazioni delle modifiche

## Status File Ricerca

### **File Contenenti Riferimenti a master_dipendenti_fixed**
- ✅ `simple_setup_mariadb.php` - Queries di verifica
- ✅ `quick_setup_mariadb.php` - Queries di verifica
- ✅ `setup_master_schema.php` - Queries di verifica
- ✅ `master_data_console_backup_20250704_220623.php` - Insert/Update queries
- ✅ `problem_resolution_report.php` - Conteggi
- ✅ `database_structure_analysis.php` - Analisi struttura
- ✅ `smart_upload_final.php` - Conteggi
- ✅ `classes/SmartCsvParser.php` - Usage

### **File SQL da Creare/Trovare**
- [ ] **create_fixed_master_schema_mariadb.sql** - Schema principale
- [ ] **create_master_schema_simple.sql** - Schema semplificato

## Raccomandazione Immediata

**APPROCCIO: Analisi Database Live + Reverse Engineering**

1. **Connettere al database** e usare DESCRIBE per vedere schema attuale
2. **Analizzare file PHP** che usano la tabella per capire colonne utilizzate
3. **Ricostruire schema** basato sull'uso effettivo
4. **Documentare differenze** tra le tre versioni

Se la tabella non esiste nel database, allora il problema è che gli script di setup non l'hanno creata correttamente e dobbiamo trovare/ricreare la definizione corretta.

---

*Analisi iniziale completata - Ready per esecuzione verifica database live*