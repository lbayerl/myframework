# Deployment Setup für Kohlkopf App

## Voraussetzungen

Der Server benötigt folgende PHP-Extensions:
```bash
sudo apt update
sudo apt install php8.4-fpm php8.4-mysql php8.4-xml php8.4-intl php8.4-mbstring php8.4-gd
sudo systemctl restart php8.4-fpm
```

## GitHub Secrets konfigurieren

Gehe zu https://github.com/lbayerl/myframework/settings/secrets/actions und füge folgende Secrets hinzu:

### Required Secrets

| Secret Name | Value |
|------------|-------|
| `DEPLOY_HOST` | `5.9.74.208` |
| `DEPLOY_USER` | `lugge` |
| `DEPLOY_SSH_KEY` | Inhalt von `~/.ssh/id_ed25519_deploy` (ohne Passphrase!) |

**SSH-Key erstellen und kopieren:**
```powershell
# In PowerShell: Neuen SSH-Key ohne Passphrase erstellen
ssh-keygen -t ed25519 -f $env:USERPROFILE\.ssh\id_ed25519_deploy -N '""' -C "github-actions-deploy"

# Public Key auf Server hinzufügen
ssh lugge@5.9.74.208  # Mit deinem normalen Key
echo "<INHALT VON id_ed25519_deploy.pub>" >> ~/.ssh/authorized_keys
exit

# Private Key in Zwischenablage kopieren
Get-Content $env:USERPROFILE\.ssh\id_ed25519_deploy | Set-Clipboard
# Dann in GitHub Secret einfügen (Strg+V)
```

## Server-Vorbereitung

### 1. Deployment-Verzeichnis erstellen

```bash
ssh lugge@5.9.74.208

# Verzeichnis anlegen
sudo mkdir -p /var/www/kohlkopf
sudo chown -R lugge:www-data /var/www/kohlkopf
sudo chmod -R 775 /var/www/kohlkopf

# Backup-Verzeichnis (im Home-Verzeichnis)
mkdir -p ~/kohlkopf-backups
```

### 2. Datenbank vorbereiten

**Wichtig:** 
- Datenbank `symf_kohlkopf` muss bereits existieren
- Migrations **nicht** im Deployment ausführen (lokal gegen Prod-DB entwickeln)
- Schema ist bereits aktuell durch lokale Entwicklung

### 3. .env.local erstellen

```bash
nano /var/www/kohlkopf/.env.local
```

Inhalt:
```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<generiere-mit: openssl rand -hex 32>

# Datenbank (Remote MariaDB via SSH Tunnel)
DATABASE_URL="mysql://symfony:password@127.0.0.1:3306/symf_kohlkopf?serverVersion=11.0&charset=utf8mb4"

# Mailer (z.B. Brevo SMTP)
MAILER_DSN=smtp://user:pass@smtp-relay.brevo.com:587

# Branding
MYFRAMEWORK_APP_NAME="Kohlkopf"
MYFRAMEWORK_PRIMARY_COLOR=0d6efd

# VAPID Keys (generiere nach erstem Deployment!)
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:your@email.com
```

**Wichtig:** `.env.local` wird **nicht** deployed - muss einmalig manuell auf dem Server erstellt werden!

### 4. VAPID Keys generieren (nach erstem Deployment)

```bash
cd /var/www/kohlkopf
php bin/console myframework:vapid:generate --subject="mailto:your@email.com"

# Output in .env.local eintragen
nano .env.local

# Cache clearen
php bin/console cache:clear --env=prod
```

### 5. Nginx konfigurieren (falls noch nicht geschehen)

```bash
sudo nano /etc/nginx/sites-available/kohlkopf
```

Inhalt:
```nginx
server {
    listen 80;
    server_name your-domain.de www.your-domain.de;
    root /var/www/kohlkopf/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    error_log /var/log/nginx/kohlkopf_error.log;
    access_log /var/log/nginx/kohlkopf_access.log;
}
```

Aktivieren:
```bash
sudo ln -s /etc/nginx/sites-available/kohlkopf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 6. SSL mit Let's Encrypt (nach erstem Deployment)

```bash
# Apache (falls Apache verwendet wird)
sudo certbot --apache -d your-domain.de -d www.your-domain.de

# Oder Nginx
sudo certbot --nginx -d your-domain.de -d www.your-domain.de
```

## Deployment-Workflow

### Was macht das Deployment?

1. **Backup erstellen**: Aktueller Stand → `~/kohlkopf-backups/YYYYMMDD_HHMMSS`
2. **Code deployen**: TAR-Archiv hochladen und entpacken (ohne vendor, var, .env.local, legacy)
3. **Dependencies installieren**: `composer install` (holt myframework/core von GitHub)
4. **Assets kompilieren**: Importmap + Asset-Map
5. **Cache aufwärmen**: Prod-Cache generieren
6. **PHP-FPM reload**: Opcache leeren

### Automatisches Deployment

Pushe auf `main` - Workflow startet automatisch bei Änderungen in:
- `apps/kohlkopf/**`
- `packages/myframework-core/**`

```bash
git add .
git commit -m "feat: Add new feature"
git push
```

### Manuelles Deployment

1. https://github.com/lbayerl/myframework/actions
2. **Deploy Kohlkopf App** → **Run workflow**
3. Branch: `main`, Environment: `production`
4. **Run workflow**

### Rollback

Manuelles Rollback zu vorherigem Backup:
```bash
ssh lugge@5.9.74.208

# Verfügbare Backups anzeigen
ls -la ~/kohlkopf-backups/

# Rollback zu bestimmtem Backup
cd /var/www
sudo rm -rf kohlkopf
sudo cp -r ~/kohlkopf-backups/20260204_143000 kohlkopf
sudo chown -R lugge:www-data kohlkopf
sudo systemctl reload php8.4-fpm
```

## Troubleshooting

### Deployment schlägt fehl

1. **SSH-Verbindung testen:**
   ```bash
   ssh -i ~/.ssh/id_ed25519_deploy lugge@5.9.74.208 "echo 'Connection OK'"
   ```

2. **GitHub Action Logs:**
   https://github.com/lbayerl/myframework/actions

3. **Server-Logs prüfen:**
   ```bash
   ssh lugge@5.9.74.208
   
   # PHP-FPM Logs
   sudo tail -f /var/log/php8.4-fpm.log
   
   # Apache/Nginx Logs
   sudo tail -f /var/log/apache2/error.log
   # oder
   sudo tail -f /var/log/nginx/error.log
   
   # Symfony Logs
   tail -f /var/www/kohlkopf/var/log/prod.log
   ```

### Cache-Probleme

```bash
ssh lugge@5.9.74.208
cd /var/www/kohlkopf
rm -rf var/cache/*
php bin/console cache:clear --env=prod
chmod -R 775 var/
```

### Composer-Probleme (Path Repository)

Falls Deployment mit "Source path not found" fehlschlägt:
```bash
ssh lugge@5.9.74.208
cd /var/www/kohlkopf
rm -f composer.lock
composer config --unset repositories.0
composer install --no-dev
```

### Asset-Probleme

```bash
cd /var/www/kohlkopf
php bin/console importmap:install --env=prod
php bin/console asset-map:compile --env=prod
```

## Performance-Optimierung (Optional)

### OPcache aktivieren

```bash
sudo nano /etc/php/8.4/fpm/conf.d/10-opcache.ini
```

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=0
```

### Realpath Cache erhöhen

```bash
sudo nano /etc/php/8.4/fpm/php.ini
```

```ini
realpath_cache_size=4096k
realpath_cache_ttl=600
```

Dann PHP-FPM neu starten:
```bash
sudo systemctl restart php8.4-fpm
```

## Monitoring (Optional)

### Healthcheck-Endpoint

Erstelle `apps/kohlkopf/public/health.php`:
```php
<?php
http_response_code(200);
echo json_encode(['status' => 'ok', 'timestamp' => time()]);
```

Dann in Monitoring-Tool (UptimeRobot, etc.):
- URL: `https://kohlkopf.yourdomain.com/health.php`
- Check-Intervall: 5 Minuten
