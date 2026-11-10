# Deploy nativo IONOS

Dominio: `https://docusign.produceavalue.com`

- SSH: utente `deploy`, host `212.227.225.209`.
- Applicazione: `/var/www/docusign`; document root: `/var/www/docusign/public`.
- PostgreSQL: database e ruolo dedicati `docusign`, connessione locale.
- Runtime PHP isolato in `/var/www/docusign/.runtime`, senza aggiornare il PHP condiviso.
- Wrapper CLI: `/var/www/docusign/bin/php`.
- Configurazione privata: `/var/www/docusign/.env`, permessi `0600`, proprietario `deploy`.
- PHP-FPM: `/etc/docusign/fpm.conf`, socket `/run/docusign-fpm.sock`.
- Servizi: `docusign-fpm`, `docusign-worker`, `docusign-scheduler.timer`.
- Nginx: `/etc/nginx/sites-available/docusign.produceavalue.com`.
- ACME webroot: `/var/www/docusign-acme`.

I file in questa cartella riproducono le configurazioni dedicate installate. Non avviare Docker Compose su questa VPS: Nginx e gli altri siti usano già le porte pubbliche.

## Comandi di gestione

```bash
cd /var/www/docusign
bin/php artisan migrate --force
bin/php artisan optimize
bin/php artisan queue:restart
sudo systemctl restart docusign-fpm
sudo systemctl status docusign-fpm docusign-worker docusign-scheduler.timer
```

Gli aggiornamenti del runtime devono sostituire insieme i binari PHP e le estensioni nella directory isolata, verificando prima la compatibilità. Non aggiornare i PHP condivisi come effetto collaterale del deploy dell'applicazione.

## Certificato

Il certificato è stato emesso con `certbot certonly --webroot`, limitato al singolo dominio. Il file di rinnovo ha un hook dedicato `/etc/docusign/certbot-deploy-hook`, che ricarica Nginx solo quando `RENEWED_LINEAGE` coincide con questo certificato.

Per simulare soltanto questo rinnovo:

```bash
sudo certbot renew --cert-name docusign.produceavalue.com --dry-run --no-directory-hooks --no-random-sleep-on-renew
```

Non eseguire un `certbot renew --dry-run` senza `--cert-name`.

## Verifiche sugli altri siti

Il deploy conserva sul server `/etc/docusign/nginx-before.sha256`, `/etc/docusign/php-before.txt` e `/etc/docusign/http-before.txt`. Servono a confrontare configurazioni, processi PHP e risposte HTTP precedenti all'attivazione. Nginx viene ricaricato dopo `nginx -t`; i servizi PHP condivisi non vengono riavviati.

## Segreti e backup

La password sudo viene usata soltanto dal computer locale e non viene copiata sulla VPS. La password iniziale dell'amministratore viene eliminata dal `.env` di produzione dopo il seed. Le chiavi di cifratura e firma sono nuove, dedicate alla produzione.

La copia locale `.env.production`, se presente, è privata ed esclusa da Git: conservarne una copia sicura separata dalla VPS. I documenti e il database di sviluppo non vengono trasferiti.

Il backup remoto restic/rclone richiede ancora la configurazione delle credenziali Google Drive e della password restic. Il deploy web non costituisce un collaudo del backup o della PEC.
