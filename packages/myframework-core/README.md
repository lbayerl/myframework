# myframework/core

Dieses Package ist ein **Symfony Bundle** (Symfony **7.4 LTS**) und liefert als "Core" out-of-the-box:

- Security/Auth: Login, Registrierung, E-Mail-Verifizierung, Passwort-Reset
- UI: Twig-Templates (überschreibbar), optional AssetMapper-Assets
- Push Notifications: minishlink/web-push-bundle (VAPID via ENV)
- PWA: spomky-labs/pwa-bundle

## Pro-App Konfiguration (ENV/Secrets)

### Mail (Brevo SMTP)
Im Projekt:
- `MAILER_DSN=smtp://apikey:BREVO_API_KEY@smtp-relay.brevo.com:587`

### VAPID (Push)
VAPID Keys sind **pro App** unterschiedlich:
- `VAPID_PUBLIC_KEY=...`
- `VAPID_PRIVATE_KEY=...`
- `VAPID_SUBJECT=mailto:you@example.com`

### UI/Branding
Per ENV oder Bundle-Config (und/oder Twig-Overrides):
- `MYFRAMEWORK_APP_NAME`
- `MYFRAMEWORK_PRIMARY_COLOR`
- `MYFRAMEWORK_LOGO_PATH`

## VAPID Keys erzeugen (pro App)

VAPID Keys sind **pro App** unterschiedlich. Das Bundle bringt dafür ein Console-Command mit, das neue Keys generiert und direkt passende ENV-Zeilen ausgibt.

Beispiel:

```bash
php bin/console myframework:vapid:generate --subject="mailto:you@example.com"
```

Ausgabe (Beispiel):

```dotenv
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
VAPID_SUBJECT=mailto:you@example.com
```

Optional:
- `--format=json` für JSON-Ausgabe
- `--copy` um nur Werte (Public/Private/Subject) ohne Labels auszugeben

## Entwicklung
- Tests: `packages/myframework-core/tests`
