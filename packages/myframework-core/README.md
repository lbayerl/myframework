# myframework/core

Dieses Package ist ein **Symfony Bundle** (Symfony **7.4 LTS**) und liefert als "Core" out-of-the-box:

- Security/Auth: Login, Registrierung, E-Mail-Verifizierung, Passwort-Reset
- UI: Twig-Templates (überschreibbar), optional AssetMapper-Assets
- Push Notifications: minishlink/web-push-bundle (VAPID via ENV)
- PWA: spomky-labs/pwa-bundle
- Logging: symfony/monolog-bundle (PSR-3)

## Installation

```bash
composer require myframework/core
```

## Setup in der App

### 1. Bundles registrieren

In `config/bundles.php`:

```php
<?php

return [
    // ... andere Bundles
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    SpomkyLabs\PwaBundle\SpomkyLabsPwaBundle::class => ['all' => true],
    Minishlink\Bundle\WebPushBundle\MinishlinkWebPushBundle::class => ['all' => true],
    MyFramework\Core\MyFrameworkCoreBundle::class => ['all' => true],
];
```

### 2. Monolog konfigurieren

Erstelle `config/packages/monolog.yaml`:

```yaml
monolog:
    channels:
        - deprecation

when@dev:
    monolog:
        handlers:
            main:
                type: stream
                path: "%kernel.logs_dir%/%kernel.environment%.log"
                level: debug
                channels: ["!event"]
            console:
                type: console
                process_psr_3_messages: false
                channels: ["!event", "!doctrine", "!console"]

when@prod:
    monolog:
        handlers:
            main:
                type: fingers_crossed
                action_level: error
                handler: nested
                excluded_http_codes: [404, 405]
                buffer_size: 50
            nested:
                type: stream
                path: php://stderr
                level: debug
                formatter: monolog.formatter.json
            console:
                type: console
                process_psr_3_messages: false
                channels: ["!event", "!doctrine"]
```

### 3. Routing importieren

In `config/routes.yaml`:

```yaml
myframework_core:
    resource: '../vendor/myframework/core/resources/config/routing/auth.yaml'

myframework_notifications:
    resource: '../vendor/myframework/core/resources/config/routing/notifications.yaml'
```

### 4. Doctrine Entities mappen

In `config/packages/doctrine.yaml`:

```yaml
doctrine:
    orm:
        mappings:
            MyFrameworkCore:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/vendor/myframework/core/src/Entity'
                prefix: 'MyFramework\Core\Entity'
                alias: MyFrameworkCore
```

### 5. Security konfigurieren

In `config/packages/security.yaml`:

```yaml
security:
    providers:
        app_user_provider:
            entity:
                class: MyFramework\Core\Entity\User
                property: email
    firewalls:
        main:
            lazy: true
            provider: app_user_provider
            form_login:
                login_path: myframework_auth_login
                check_path: myframework_auth_login
            logout:
                path: myframework_auth_logout
```

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
