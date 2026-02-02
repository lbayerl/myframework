# Smoke App (`apps/smoke`)

Diese App ist eine **Symfony 7.4 LTS** Referenz-/Test-Anwendung, um das lokale Bundle **`myframework/core`** realistisch zu betreiben.

Sie ist bewusst nicht „minimal“, sondern deckt typische Integrationspunkte ab:
- Routing-Import aus dem Bundle
- Security (form_login) mit User-Entity aus dem Bundle
- Doctrine ORM Mapping für Entities aus dem Bundle
- Mailer (lokal via Mailpit)
- PWA + WebPush Bundles

## Lokales Bundle einbinden

In `composer.json` wird das Bundle über ein Path-Repository eingebunden:
- `../../packages/myframework-core` mit `symlink=true`

Damit wirken Änderungen im Bundle-Verzeichnis live in dieser App.

## Infrastruktur (Docker Compose)

- `compose.yaml` stellt Postgres bereit.
- `compose.override.yaml` ergänzt:
  - Port-Mapping für Postgres (`5432`)
  - Mailpit (`1025` SMTP, `8025` Web UI)

Die Compose-Files sind so geschrieben, dass Ports „publiziert“ werden, aber standardmäßig ohne feste Host-Ports (Docker wählt freie Ports). Das ist für parallele Setups praktisch.

## Wichtige Konfigurationen in der App

### Routing
`config/routes.yaml` importiert Bundle-Routen direkt aus `vendor/`:
- `../vendor/myframework/core/resources/config/routing/auth.yaml`

Das setzt eine klassische `vendor/` Struktur voraus.

### Doctrine
`config/packages/doctrine.yaml` mappt zusätzlich:
- `MyFrameworkCore` Entities aus `%kernel.project_dir%/vendor/myframework/core/src`

### Security
`config/packages/security.yaml` verwendet:
- `MyFramework\\Core\\Security\\Entity\\User` als User-Provider
- form_login Routen aus dem Bundle (`myframework_auth_login`, …)

## Tests

- PHPUnit-Konfiguration: `phpunit.dist.xml`
- Besonderheit: `failOnWarning`, `failOnNotice`, `failOnDeprecation` sind aktiviert → die App dient auch als „Frühwarnsystem“ für Deprecations.

## Auffälligkeiten / Grenzen

- PHP-Versionen: App erlaubt `>=8.2`, Bundle fordert `^8.3`. Ideal ist daher PHP 8.3+.
- Einige Pfade (Routing/Doctrine) referenzieren `vendor/myframework/core/...` direkt. Das ist ok für dieses Setup, aber bewusst nicht „vendor-dir-agnostisch“.

## Nützliche Kommandos

Die genauen Kommandos hängen von deinem lokalen Setup ab (PHP, Composer, Symfony CLI, Docker). Typischerweise:

```powershell
# im apps/smoke Verzeichnis
composer install

# Infrastruktur
docker compose up -d

# Migrations/Schema
php bin/console doctrine:migrations:migrate

# App starten (eine von beiden Varianten)
php -S localhost:8000 -t public
# oder
php bin/console server:run

# Tests
php vendor/bin/phpunit
```
