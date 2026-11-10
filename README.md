# Produce a Value Sign

Portale B2B self-hosted per raccogliere una **firma elettronica semplice via OTP email** e generare un dossier probatorio verificabile. Non è una firma digitale, avanzata o qualificata e non deve essere presentata come equivalente automatico alla firma autografa.

## Funzioni incluse

- singolo account amministratore con password, TOTP e recovery code;
- caricamento di PDF statici, hash SHA-256 e archiviazione cifrata con libsodium;
- inviti revocabili con token casuale, sessione pulita e scadenza configurabile;
- consensi espliciti, dichiarazione dei poteri aziendali e OTP email con limiti anti-abuso;
- firma cliente, controfirma Produce a Value e stati immutabili del flusso;
- audit append-only concatenato, certificato PDF, QR, manifest canonico firmato Ed25519 e ZIP esportabile;
- verifica pubblica senza esposizione di dati personali;
- invio email/PEC, importazione ricevute PEC, reminder, scadenze, retention e legal hold;
- Docker Compose con Laravel 13, PHP 8.5, PostgreSQL 18.4, Caddy e backup restic/rclone.

## Avvio locale

Requisiti: PHP 8.3+, Composer e SQLite.

```bash
cp .env.example .env
# Per lo sviluppo sostituire DB_CONNECTION con sqlite e APP_URL con http://localhost:8000
touch database/database.sqlite
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Impostare `ADMIN_EMAIL` e `ADMIN_PASSWORD` prima del seed. Al primo accesso viene richiesta la configurazione TOTP.

## Deploy sulla VPS IONOS

1. Verificare almeno 2 vCPU, 2 GB RAM, 20 GB liberi, Ubuntu/Debian aggiornato e porte 80/443 aperte.
2. Creare il record DNS `A`/`AAAA` di `sign.produceavalue.com` verso la VPS.
3. Installare Docker Engine e il plugin Compose seguendo la documentazione Docker per il sistema operativo della VPS.
4. Copiare `.env.example` in `.env`, impostare `APP_ENV=production`, `APP_DEBUG=false`, credenziali PostgreSQL robuste, SMTP IONOS, dati agenzia e email amministratore.
5. Generare i segreti senza inserirli nella shell history condivisa:

```bash
docker compose build app
docker compose run --rm app php artisan key:generate --show
docker compose run --rm app php artisan evidence:key:generate
```

   Copiare i quattro valori risultanti in `.env` e conservare una copia delle chiavi Evidence fuori dalla VPS. Perdere `EVIDENCE_VAULT_KEY` rende irrecuperabili i documenti.

6. Avviare e inizializzare:

```bash
docker compose up -d db app
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose up -d
docker compose exec app php artisan config:cache
```

   Dopo il seed rimuovere `ADMIN_PASSWORD` da `.env`: la password è già memorizzata come hash nel database. Mantenerla nel file causerebbe anche un reset involontario a ogni nuovo seed.

7. Accedere a `https://sign.produceavalue.com`, attivare TOTP e salvare i recovery code.
8. Pubblicare il fingerprint Ed25519, ottenibile dal manifest di un fascicolo pilota, sul sito aziendale o in un record DNS TXT.

## SMTP e PEC

Le email ordinarie usano le variabili `MAIL_*`. La PEC usa `PEC_SMTP_*` per l’invio e `PEC_IMAP_*` per importare le ricevute. Il comando schedulato `pec:import-receipts` conserva gli EML cifrati e li collega tramite `X-Riferimento-Message-ID`.

Prima della produzione effettuare una prova PEC completa tra la casella Produce a Value e una PEC controllata. Provider diversi possono usare intestazioni o cartelle differenti: verificare e adattare il parser con EML reali anonimizzati.

## Backup cifrato su Google Drive

Configurare rclone sul proprio computer, copiare il solo `rclone.conf` in `ops/rclone/rclone.conf` sulla VPS con permessi `600`, quindi impostare `RESTIC_REPOSITORY` e `RESTIC_PASSWORD`.

```bash
docker compose --profile ops run --rm backup
```

Programmare il comando ogni notte tramite cron di root. Il job esegue `pg_dump`, salva database e volume documenti, applica 14 snapshot giornalieri, 8 settimanali e 12 mensili e controlla un campione dei dati. Ogni trimestre ripristinare database e storage in un ambiente isolato e confrontare gli hash dei fascicoli.

La password restic, la chiave vault, la chiave Ed25519 e `APP_KEY` devono essere conservate anche offline o in un password manager separato dalla VPS e da Google Drive.

## Verifiche

```bash
php artisan test
vendor/bin/pint --test
php artisan route:list
php artisan schedule:list
php artisan evidence:purge-expired --dry-run
```

Il test end-to-end copre upload, invito, sessione firmatario, OTP, doppia firma, generazione fascicolo e verifica Ed25519.

## Checklist obbligatoria prima dell’uso reale

- revisione di un avvocato su contratti, clausole specificamente approvate, dichiarazione dei poteri e categorie ammesse;
- completamento dell’informativa privacy con dati societari, responsabili, contatti e trasferimenti effettivi;
- prova di restore, email, PEC, scadenze e annullamento;
- pilot con un contratto interno o a basso rischio;
- procedura per usare firma qualificata/notarile quando la forma legale richiesta lo impone;
- monitoraggio di aggiornamenti PHP, Laravel, PostgreSQL, immagini Docker e dipendenze Composer.

## Limiti noti

- L’OTP viene inviato alla stessa casella usata per l’invito: prova il controllo della casella ma non è un secondo fattore indipendente né verifica da solo identità e poteri societari.
- Gli indirizzi IP e i log sono dati personali e sono conservati nel dossier per la finalità dichiarata.
- L’importazione PEC richiede una ricevuta contenente il riferimento al Message-ID originale; va collaudata con il provider scelto.
- Non usare il sistema per atti che richiedono firma avanzata/qualificata, autenticazione o altra forma speciale.
