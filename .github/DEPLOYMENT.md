# Deployment Setup für Kohlkopf App

## GitHub Secrets konfigurieren

Gehe zu https://github.com/lbayerl/myframework/settings/secrets/actions und füge folgende Secrets hinzu:

### Required Secrets

| Secret Name | Value |
|------------|-------|
| `DEPLOY_HOST` | `5.9.74.208` |
| `DEPLOY_USER` | `lugge` |
| `DEPLOY_SSH_KEY` | Inhalt von `~/.ssh/id_ecdsa` (komplett mit BEGIN/END) |

**SSH-Key kopieren:**
```powershell
# In PowerShell:
Get-Content $env:USERPROFILE\.ssh\id_ecdsa | Set-Clipboard
# Dann in GitHub Secret einfügen (Strg+V)
```

## Server-Vorbereitung

### 1. Deployment-Verzeichnis erstellen

```bash
ssh user@yourserver.com

# Verzeichnis anlegen
sudo mkdir -p /var/www/kohlkopf
sudo chown -R www-data:www-data /var/www/kohlkopf

# Backup-Verzeichnis
sudo mkdir -p /var/backups/kohlkopf
sudo chown -R www-data:www-data /var/backups/kohlkopf
```

### 2. Datenbank vorbereiten

**Hinweis:** Datenbank und Schema müssen bereits existieren. Migrations werden **nicht** automatisch ausgeführt.

### 3. .env.local erstellen

```bash
sudo nano /var/www/kohlkopf/.env.local
```

Inhalt:
```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<generiere-mit: openssl rand -hex 32>

# Datenbank (siehe copilot-instructions.md)
DATABASE_URL="mysql://symfony:password@localhost:3306/symf_kohlkopf?serverVersion=11.0&charset=utf8mb4"

# Mailer
MAILER_DSN=smtp://user:pass@smtp.example.com:587

# Branding
MYFRAMEWORK_APP_NAME="Kohlkopf"
MYFRAMEWORK_PRIMARY_COLOR=0d6efd

# VAPID Keys (auf Server generieren!)
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:your@email.com
```

### 4. VAPID Keys generieren

```bash
cd /var/www/kohlkopf

# Manuell Keys generieren (einmalig)
php bin/console myframework:vapid:generate --subject="mailto:your@email.com"

# Output in .env.local eintragen
sudo nano .env.local
```

### 5. Nginx konfigurieren

```bash
sudo nano /etc/nginx/sites-available/kohlkopf
```

Inhalt:
```nginx
server {
    listen 80;
    server_name kohlkopf.yourdomain.com;
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

### 6. SSL mit Let's Encrypt

```bash
sudo certbot --nginx -d kohlkopf.yourdomain.com
```

## Deployment-Workflow

### Automatisches Deployment

Pushe einfach auf `main`:
```bash
git push origin main
```

Die GitHub Action wird automatisch ausgelöst, wenn:
- Dateien in `apps/kohlkopf/**` geändert wurden
- Dateien in `packages/myframework-core/**` geändert wurden (Bundle-Updates)

### Manuelles Deployment

Über GitHub UI:
1. Gehe zu Actions → Deploy Kohlkopf App
2. Klicke "Run workflow"
3. Wähle Environment (production/staging)
4. Klicke "Run workflow"

### Rollback bei Fehler

Falls das Deployment fehlschlägt, wird automatisch ein Rollback durchgeführt.

Manuelles Rollback:
```bash
ssh user@yourserver.com

# Zeige verfügbare Backups
ls -la /var/backups/kohlkopf/

# Rollback zu bestimmtem Backup
sudo rm -rf /var/www/kohlkopf
sudo cp -r /var/backups/kohlkopf/20260204_143000 /var/www/kohlkopf
sudo chown -R www-data:www-data /var/www/kohlkopf
```

## Troubleshooting

### Deployment schlägt fehl

1. **SSH-Verbindung prüfen:**
   ```bash
   ssh -i ~/.ssh/kohlkopf_deploy user@yourserver.com
   ```

2. **GitHub Action Logs checken:**
   Repository → Actions → Deploy Kohlkopf App → Latest run → Logs

3. **Server-Logs prüfen:**
   ```bash
   # PHP-FPM Logs
   sudo tail -f /var/log/php8.4-fpm.log
   
   # Nginx Logs
   sudo tail -f /var/log/nginx/kohlkopf_error.log
   
   # Symfony Logs
   tail -f /var/www/kohlkopf/var/log/prod.log
   ```

### Cache-Probleme

```bash
ssh user@yourserver.com
cd /var/www/kohlkopf
sudo rm -rf var/cache/*
php bin/console cache:clear --env=prod
sudo chown -R www-data:www-data var/
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
