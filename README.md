# Employee Analytics Dashboard

Sistema completo per l'analisi delle performance dei dipendenti con calcolo KPI automatizzato, detection anomalie e dashboard real-time.

## Caratteristiche Principali

### 📊 KPI Automatizzati
- **Daily P&L**: Calcolo profitto/perdita giornaliero (€80 costo vs ricavi fatturabili)
- **Efficiency Rate**: (ore fatturabili / 8h) * 100
- **Alert Count**: Anomalie e discrepanze rilevate automaticamente
- **Correlation Score**: Correlazione tra timbrature, attività e calendario
- **Client Profitability**: Analisi redditività per cliente
- **Vehicle Efficiency**: Efficienza utilizzo veicoli aziendali
- **Remote vs Onsite Ratio**: Rapporto lavoro remoto/on-site
- **Monthly Trends**: Andamento performance mensile

### 🔍 Validazioni Automatiche
- **Controllo ore**: Timbrature vs attività con tolleranza ±1h
- **Detection sovrapposizioni**: Timbrature multiple stesso tecnico
- **Validazione trasferte**: Congruenza cliente vs uso auto
- **Correlazione sessioni remote**: TeamViewer vs ticket corrispondenti
- **Alert ore insufficienti**: <7h fatturabili senza assenze giustificate
- **Rapportini mancanti**: Timbrature senza attività registrate

### 📈 Dashboard Real-time
- KPI cards aggiornate automaticamente
- Grafici interattivi performance
- Top performers ranking
- Alert system con priorità
- Responsive design (mobile-friendly)

## Struttura File CSV Supportati

### 1. Timbrature (`apprilevazionepresenze-timbrature-totali-base.csv`)
```csv
dipendente nome;dipendente cognome;cliente nome;ora inizio;ora fine;ore;...
```

### 2. Richieste Ferie (`apprilevazionepresenze-richieste.csv`)
```csv
Data della richiesta;Dipendente;Tipo;Data inizio;Data fine;Stato;Note
```

### 3. Attività (`attivita.csv`)
```csv
Contratto;Id Ticket;Iniziata il;Conclusa il;Azienda;Descrizione;Durata;Creato da
```

### 4. Calendario (`calendario.csv`)
```csv
SUMMARY;DTSTART;DTEND;ATTENDEE;LOCATION;PRIORITY
```

### 5. Progetti (`progetti.csv`)
```csv
Codice Progetto;Stato;Priorità;Azienda Assegnataria;Nome;Capo Progetto
```

### 6. Registro Auto (`registro_auto.csv`)
```csv
Dipendente;Data;Auto;Presa Data e Ora;Riconsegna Data e Ora;Cliente
```

### 7. TeamViewer (`teamviewer_bait.csv`, `teamviewer_gruppo.csv`)
```csv
Utente;Nome;Tipo di sessione;Gruppo;Inizio;Fine;Durata
```

## Installazione

### 1. Requisiti di Sistema
- **PHP**: 7.4+ con PDO, JSON extensions
- **MySQL**: 5.7+ o MariaDB 10.2+
- **Web Server**: Apache/Nginx
- **Browser**: Chrome/Firefox/Safari (moderni)

### 2. Setup Rapido
```bash
# 1. Clona/copia i file nel web server
cp -r employee-analytics/ /var/www/html/

# 2. Configura database
nano config/Database.php

# 3. Esegui setup automatico
http://localhost/employee-analytics/setup.php

# 4. Carica file CSV
http://localhost/employee-analytics/upload.php
```

### 3. Configurazione Database
Modifica `config/Database.php`:
```php
private $host = 'localhost';
private $db_name = 'employee_analytics';
private $username = 'root';
private $password = 'your_password';
```

## Utilizzo

### 1. Setup Iniziale
1. Vai a `setup.php` per inizializzare il sistema
2. Il setup creerà automaticamente database e configurazioni
3. Verifica che tutti i test passino

### 2. Caricamento Dati
1. Vai a `upload.php`
2. Carica i file CSV (supporta caricamento multiplo)
3. Il sistema processerà automaticamente tutti i file
4. Verifica eventuali errori o warning

### 3. Monitoraggio
1. Dashboard principale mostra KPI real-time
2. Le anomalie vengono rilevate automaticamente
3. Gli alert vengono aggiornati ogni 2 minuti
4. I grafici si aggiornano automaticamente

## API Endpoints

### Performance Data
```
GET /api/performance_data.php?period=7&dipendente_id=1
```

### KPI Summary
```
GET /api/kpi_summary.php
```

### Alerts Count
```
GET /api/alerts_count.php
```

## Configurazioni Avanzate

### Soglie KPI (modificabili via database)
```sql
UPDATE configurazioni SET valore = '75.00' WHERE chiave = 'costo_dipendente_default';
UPDATE configurazioni SET valore = '60.00' WHERE chiave = 'tariffa_oraria_standard';
UPDATE configurazioni SET valore = '6' WHERE chiave = 'alert_ore_minime';
```

### Personalizzazione Alert
- **Efficiency Warning**: <70%
- **Efficiency Critical**: <50%
- **Profit Warning**: <-€20
- **Ore Insufficienti**: <7h senza assenze

## Manutenzione

### Backup Automatico
Il sistema crea backup automatici in:
- `backups/` - Configurazioni
- `uploads/` - File CSV storici

### Log di Sistema
I log vengono salvati in:
- `logs/errors.log` - Errori sistema
- `logs/uploads.log` - Storico upload
- `logs/validations.log` - Log validazioni

### Ottimizzazione Performance
```sql
-- Indicizzazione automatica ottimizzata
-- Pulizia dati vecchi (>1 anno)
DELETE FROM timbrature WHERE data < DATE_SUB(CURDATE(), INTERVAL 1 YEAR);
```

## Troubleshooting

### Problemi Comuni

#### Upload File Fallisce
- Verifica permessi cartella `uploads/`
- Controlla `upload_max_filesize` in php.ini
- Verifica encoding file CSV (UTF-8)

#### KPI Non Calcolati
- Controlla connessione database
- Verifica presenza dati timbrature
- Esegui `classes/KpiCalculator->recalculateAllKpis(true)`

#### Dashboard Vuoto
- Verifica che ci siano dati negli ultimi 7 giorni
- Controlla API endpoints (`/api/*.php`)
- Verifica permessi file JavaScript

### Debug Mode
Abilita debug aggiungendo in `config/Database.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Sicurezza

### Raccomandazioni
- Cambia credenziali database default
- Limita accesso alla cartella `config/`
- Usa HTTPS in produzione
- Aggiorna regolarmente PHP/MySQL

### Validazione Input
- Tutti i CSV vengono sanitizzati automaticamente
- Protezione SQL injection tramite prepared statements
- Validazione encoding UTF-8

## Supporto

### File di Log
- Errori: `logs/error.log`
- Sistema: Browser DevTools Console
- Database: MySQL error log

### Performance Monitoring
- Tempo risposta API: <500ms
- Caricamento dashboard: <2s
- Processing CSV: ~1000 record/sec

---

**Versione**: 1.0.0  
**Autore**: Employee Analytics System  
**Licenza**: Proprietaria