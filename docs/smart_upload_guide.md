# 📤 Guida Smart Upload Final

## Come Usare smart_upload_final.php

### 🎯 Cosa Fa
`smart_upload_final.php` è il sistema di caricamento intelligente che:
- Riconosce automaticamente i tipi di file CSV
- Processa i dati usando le tabelle master
- Gestisce associazioni automatiche
- Evita duplicati
- Fornisce statistiche dettagliate

### 📋 File CSV Supportati

| Tipo File | Nome File Atteso | Descrizione |
|-----------|------------------|-------------|
| **Attività** | `attivita.csv` | Ticket e attività lavorative |
| **Timbrature** | `apprilevazionepresenze-timbrature-totali-base.csv` | Orari di lavoro e timbrature |
| **Richieste** | `apprilevazionepresenze-richieste.csv` | Ferie, permessi, ROL |
| **Calendario** | `calendario.csv` | Eventi e appuntamenti |
| **Progetti** | `progetti.csv` | Gestione progetti |
| **Registro Auto** | `registro_auto.csv` | Utilizzo veicoli aziendali |
| **TeamViewer BAIT** | `teamviewer_bait.csv` | Sessioni remote BAIT |
| **TeamViewer Gruppo** | `teamviewer_gruppo.csv` | Sessioni remote Gruppo |

### 🚀 Come Utilizzarlo

#### 1. Preparazione File
- Assicurati che i file CSV abbiano i nomi corretti (vedi tabella sopra)
- I file devono essere in formato UTF-8 o Windows-1252
- Separatori supportati: `,` `;` `\t` `|` (rilevamento automatico)

#### 2. Caricamento
1. Vai su `smart_upload_final.php`
2. Clicca su **"Seleziona File CSV"**
3. Seleziona uno o più file CSV
4. Clicca **"Avvia Smart Upload"**

#### 3. Monitoraggio
Il sistema mostrerà in tempo reale:
- **Progresso**: Barra di avanzamento per ogni file
- **Statistiche**: Record processati, inseriti, aggiornati, saltati
- **Errori**: Eventuali problemi riscontrati
- **Warnings**: Avvisi su dati sospetti o duplicati

### 🎛️ Funzionalità Intelligenti

#### Riconoscimento Automatico Dipendenti
Il sistema riconosce automaticamente i dipendenti dai nomi usando:
- **Cache Master**: Database di dipendenti consolidato
- **Fuzzy Matching**: Trova corrispondenze anche con variazioni nel nome
- **Auto-Creation**: Crea automaticamente dipendenti validi se non esistono

#### Gestione Aziende/Clienti
- **Normalizzazione**: Converte nomi aziende in formato standard
- **Deduplicazione**: Evita aziende duplicate
- **Association Queue**: Mette in coda aziende da verificare manualmente

#### Validazione Avanzata
- **Nome Validation**: Esclude nomi veicoli, sistemi, email
- **Blacklist**: Filtra automaticamente nomi non validi
- **Data Validation**: Verifica e converte date in formato corretto

### 📊 Interpretare i Risultati

#### Statistiche Tipiche
```
✅ File processato: attivita.csv
📋 Record processati: 150
➕ Inseriti: 142
🔄 Aggiornati: 5
⏭️ Saltati: 3
⚠️ Warnings: 2
❌ Errori: 0
```

#### Significato Colori
- 🟢 **Verde**: Operazione completata con successo
- 🟡 **Giallo**: Warnings (dati sospetti ma elaborati)
- 🔴 **Rosso**: Errori (dati non elaborati)
- 🔵 **Blu**: Informazioni generali

### ⚠️ Warnings Comuni

| Warning | Significato | Azione |
|---------|-------------|---------|
| "Dipendente non trovato" | Nome dipendente non riconosciuto | Verrà creato automaticamente se valido |
| "Azienda aggiunta a queue" | Cliente non riconosciuto | Controlla association queue |
| "Possibile duplicato" | Record simile già esistente | Verrà saltato per sicurezza |
| "Data non valida" | Formato data non riconosciuto | Record saltato |

### 🔧 Risoluzione Problemi

#### File Non Riconosciuto
- Verifica il nome del file
- Controlla che sia un CSV valido
- Assicurati che abbia almeno un header

#### Molti Record Saltati
- Controlla i nomi dei dipendenti
- Verifica formato date
- Controlla associazione aziende

#### Performance Lenta
- Carica un file alla volta per file molto grandi
- Usa file compressi se possibile

### 📈 Prossimi Passi Dopo Upload

1. **Verifica Dati**: Vai su `master_data_console.php` per controllare i dati inseriti
2. **Association Queue**: Controlla se ci sono aziende da associare manualmente  
3. **KPI Dashboard**: Visualizza i KPI calcolati su `index.php`
4. **Validazioni**: Esegui `verify_data_integrity.php` per controlli aggiuntivi

### 💡 Consigli

- **Upload Incrementale**: Carica prima i dipendenti (attivita.csv), poi gli altri file
- **Backup**: Fai sempre backup prima di upload massivi
- **Test**: Prova con un file piccolo prima di caricare tutto
- **Monitoraggio**: Controlla i log per problemi ricorrenti