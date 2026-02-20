# MyFramework Development Guide

## Architecture Overview

This is a **Symfony Bundle monorepo** with three distinct parts:
- `packages/myframework-core/`: Reusable Symfony Bundle (`myframework/core`) providing auth, PWA, and push notifications
- `apps/smoke/`: Symfony 7.4 LTS integration test app with strict PHPUnit config for catching deprecations
- `apps/kohlkopf/`: Real-world production app demonstrating framework usage

**Critical**: The bundle is linked via Composer **path repository** with `symlink=true` in both apps. Changes in `packages/myframework-core/src/` are **immediately active** in `vendor/myframework/core/` without `composer update`.

## Key Conventions

### Namespace & Naming
- Bundle namespace: `MyFramework\Core\` (PSR-4 mapped from `packages/myframework-core/src/`)
- Composer package name: `myframework/core`
- Configuration key: `my_framework_core` (snake_case in YAML)
- Entity table prefix: `mf_` (e.g., `mf_user`, `mf_password_reset_token`)

### Strict PHP Standards
- PHP 8.4+ required for bundle (`packages/myframework-core/composer.json`)
- Apps may support earlier versions (smoke: 8.3+, kohlkopf: 8.4+) but always develop with latest
- All files must have `declare(strict_types=1);` after opening tag
- Use `final class` for all concrete classes unless explicitly designed for inheritance
- Type hints required for all parameters and return types (no mixed/no docblock-only types)

### Configuration via ENV
Bundle reads config from environment variables with intelligent defaults:
```php
// In Configuration.php, values are resolved from ENV with fallbacks:
'vapid_public_key' => '%env(resolve:VAPID_PUBLIC_KEY)%'
'app_name' => '%env(default:My App:MYFRAMEWORK_APP_NAME)%'
'primary_color' => '%env(default:0d6efd:MYFRAMEWORK_PRIMARY_COLOR)%'
```

**Important**: Default values in `env(default:...)` syntax cannot contain `:` characters.

Apps using the bundle configure via:
1. Environment variables (`.env`, `.env.local`)
2. Optional YAML override: `config/packages/my_framework_core.yaml`

## Local Development Environment (Windows)

### PHP Executable
Always use the explicit PHP 8.5 binary — **never** rely on `php` from PATH, as the version there is not compatible:
```powershell
L:\php\php85\php.exe bin/console [command]
L:\php\php85\php.exe vendor/bin/phpunit
```

### No Pipe Symbols in Terminal Commands
Do **not** use `|` (pipe) in terminal/CLI commands. Pipe output breaks the agent's ability to read terminal results, causing the agent to get stuck. Use alternatives:
- Instead of `php bin/console debug:router | grep myframework`, run `php bin/console debug:router` and read the full output.
- Instead of `Get-Process | Where-Object {...}`, store in a variable first.
- Split chained pipe commands into separate sequential calls.

## Development Workflows

### Working on Bundle Features
1. Make changes in `packages/myframework-core/src/`
2. Changes are **instantly available** in both apps due to symlink
3. Test in smoke app: `cd apps/smoke` then `L:\php\php85\php.exe bin/console [command]`
4. Test in real app: `cd apps/kohlkopf` then `L:\php\php85\php.exe bin/console [command]`
5. Run bundle unit tests: `cd packages/myframework-core` then `L:\php\php85\php.exe vendor/bin/phpunit`
6. Run smoke app tests: `cd apps/smoke` then `L:\php\php85\php.exe vendor/bin/phpunit`

### Testing Strategy
- **Bundle tests** (`packages/myframework-core/tests/`): Minimal unit tests for bundle logic
- **Smoke app tests** (`apps/smoke/tests/`): Integration tests with strict PHPUnit config:
  - `failOnWarning="true"`, `failOnNotice="true"`, `failOnDeprecation="true"`
  - Acts as early warning system for Symfony deprecations

### Running the Smoke App
```powershell
cd apps/smoke

# Install dependencies (if needed)
composer install

# Run migrations
L:\php\php85\php.exe bin/console doctrine:migrations:migrate

# Start development server
L:\php\php85\php.exe -S localhost:8000 -t public
```

### Running the Kohlkopf App
```powershell
cd apps/kohlkopf

# Run migrations
L:\php\php85\php.exe bin/console doctrine:migrations:migrate

# Start development server
L:\php\php85\php.exe -S localhost:8000 -t public
```

## Bundle Integration Patterns

### How Apps Consume the Bundle

1. **Routing**: Import auth and notification routes in app's `config/routes.yaml`:
```yaml
myframework_core:
    resource: '../vendor/myframework/core/resources/config/routing/auth.yaml'

myframework_notifications:
    resource: '../vendor/myframework/core/resources/config/routing/notifications.yaml'
```

2. **Doctrine Entities**: Map bundle entities in `config/packages/doctrine.yaml`:
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

3. **Security**: Reference bundle's User entity in `config/packages/security.yaml`:
```yaml
security:
    providers:
        app_user_provider:
            entity:
                class: MyFramework\Core\Entity\User
                property: email
    firewalls:
        main:
            form_login:
                login_path: myframework_auth_login
                check_path: myframework_auth_login
            logout:
                path: myframework_auth_logout
```

### Bundle Structure
- `src/Command/`: Console commands (e.g., `GenerateVapidKeysCommand`)
- `src/DependencyInjection/`: Bundle configuration (`Configuration.php`, `MyFrameworkCoreExtension.php`)
- `src/Entity/`: Doctrine entities (`User`, `EmailVerificationToken`, `PasswordResetToken`, `PushSubscription`)
- `src/Security/`: Auth controllers, forms, services, repositories
- `src/Push/`: Web Push services, repositories, controller
- `src/UI/`: Branding service for customization
- `src/Twig/`: Twig extensions (`PushExtension` for VAPID key access)
- `resources/config/`: Service definitions and routing (auth, notifications)
- `resources/views/`: Twig templates (namespace `@MyFrameworkCore`)
  - `base.html.twig`: Root template with PWA integration
  - `base_auth.html.twig`: Centered layout for auth pages
  - `base_home.html.twig`: Authenticated layout with navigation
  - `security/`: Login, register, password reset, email verification templates
  - `notifications/`: Push notification management UI

## Kohlkopf App Architecture

The `apps/kohlkopf/` app is a real-world production concert management PWA built on top of the bundle. It uses **MariaDB** (accessed via SSH tunnel on port 3307 locally) and Symfony 7.4 LTS.

### Domain Model
- **Concert**: Core entity. Tracks artist/band (`title`), date (`whenAt`), venue (`whereText`), status (`ConcertStatus` enum), and optional artist enrichment data from external APIs (`mbid`, `genres`, `wikipediaUrl`, `artistDescription`, `artistImage`).
- **ConcertAttendee**: Links a `Concert` to a `User` (from bundle). Tracks attendance/RSVP state.
- **Guest**: Non-registered attendee, linked to a `Concert`. Can be converted to a full `User`.
- **Ticket**: Physical or digital ticket entity linked to a `Concert` and `User`.
- **Payment**: Payment record linked to a `Ticket`.

### Directory Structure
- `src/Entity/`: `Concert`, `ConcertAttendee`, `Guest`, `Payment`, `Ticket`
- `src/Controller/`: `ConcertController`, `AttendeeController`, `GuestController`, `TicketController`, `ProfileController`, `LandingController`, `LegalController`
- `src/Service/`: `ArtistEnrichmentService` (MusicBrainz + Wikipedia lookup), `ConcertWarningService`, `GuestConversionService`
- `src/Command/`: `FetchMissingImagesCommand`, `TestAttendeeQueryCommand`, `TestWikipediaCommand`
- `src/Enum/`: `ConcertStatus` and other domain enums
- `src/Form/`, `src/Repository/`, `src/EventSubscriber/`

### External API Integration
`ArtistEnrichmentService` uses `MusicBrainzClient` and `WikipediaClient` to auto-populate artist data on concerts. The enrichment commands can be run via:
```powershell
cd apps/kohlkopf
L:\php\php85\php.exe bin/console app:enrich-concerts
L:\php\php85\php.exe bin/console app:fetch-missing-images
```

### Doctrine Migrations in Kohlkopf

**Known issue**: `doctrine:migrations:diff` always reports a "metadata out of sync" error in this app. This appears to be caused by the dual entity mapping (App entities + MyFrameworkCore bundle entities) combined with the `underscore_number_aware` naming strategy and the UUID custom ID generator. The diff command cannot be trusted to produce correct output.

**Established approach — always write migrations manually:**
1. Write the migration SQL by hand as an `ALTER TABLE` statement.
2. Create a new file in `apps/kohlkopf/migrations/` following the naming convention `Version{YYYYMMDDHHmmss}.php` (e.g. `Version20260219120000.php`).
3. Extend `AbstractMigration`, implement `up()` and `down()`, and use `$this->addSql('...')`.
4. Run: `L:\php\php85\php.exe bin/console doctrine:migrations:migrate`
5. **Never run** `doctrine:migrations:diff` or `doctrine:schema:update` in kohlkopf.

## Setting Up a New App

When creating a new app based on this bundle:

### 1. Install the Bundle
```bash
composer require myframework/core
```

### 2. Register Bundles

In `config/bundles.php`, ensure these bundles are registered:
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

### 3. Configure Monolog (Logging)

Create `config/packages/monolog.yaml`:
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

**Note:** The bundle includes `symfony/monolog-bundle` as dependency and uses `LoggerInterface` for WebPush logging. Apps must provide the configuration.

### 4. Configure PWA (Progressive Web App)

Create `config/packages/pwa.yaml`:
```yaml
pwa:
    asset_compiler: false

    manifest:
        enabled: true
        public_url: '/site.webmanifest'
        name: '%env(default:My App:MYFRAMEWORK_APP_NAME)%'
        short_name: '%env(default:App:MYFRAMEWORK_APP_NAME)%'
        description: 'Your app description'
        theme_color: '#%env(default:0d6efd:MYFRAMEWORK_PRIMARY_COLOR)%'
        background_color: '#ffffff'
        display: 'standalone'
        start_url: '/'
        scope: '/'
        icons:
            - src: '/icon.svg'
              sizes: [192, 512]
              purpose: 'any maskable'

    serviceworker:
        enabled: true
        dest: '/sw.js'
        scope: '/'
        workbox:
            enabled: true
            cache_manifest: true
```

### 5. Provide App Icon

Create `public/icon.svg` (simple example):
```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
  <rect width="512" height="512" rx="64" fill="#0d6efd"/>
  <text x="50%" y="50%" text-anchor="middle" dy=".35em" fill="white" font-family="system-ui" font-size="200" font-weight="bold">A</text>
</svg>
```

### 6. Environment Variables

Set in `.env.local`:
```env
# Branding (used by bundle and PWA)
MYFRAMEWORK_APP_NAME="Your App Name"
MYFRAMEWORK_PRIMARY_COLOR=0d6efd

# Database
DATABASE_URL="mysql://symfony:password@127.0.0.1:3307/symf_yourapp?serverVersion=11.0&charset=utf8mb4"

# Mailer
MAILER_DSN=smtp://user:password@smtp-relay.brevo.com:587

# VAPID (Web Push)
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
VAPID_SUBJECT=mailto:your@email.com
```

## Common Tasks

### Database Setup (Recommended)

For apps using this bundle, use **separate database schemas** on a shared remote MariaDB via SSH tunnel:

1. **Start SSH tunnel** (keep running):
```bash
ssh -i ~/.ssh/id_rsa -L 3307:localhost:3306 user@yourserver.com -N
```

2. **Create schema** on remote DB (Doctrine can't create the database itself):
```sql
CREATE DATABASE symf_yourapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. **Grant permissions** (pattern allows all `symf_*` schemas, only needed once):
```sql
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER,
      CREATE TEMPORARY TABLES, LOCK TABLES, CREATE VIEW, SHOW VIEW,
      EVENT, TRIGGER
ON `symf\_%`.* TO 'symfony'@'localhost';
```

4. **Configure DATABASE_URL** in `.env.local`:
```env
DATABASE_URL="mysql://symfony:password@127.0.0.1:3307/symf_yourapp?serverVersion=11.0&charset=utf8mb4"
#                                              ^^^^              ^^^^^^^^^^^
#                                          Local tunnel port   Schema name
```

5. **Run migrations** to create tables:
```powershell
L:\php\php85\php.exe bin/console doctrine:migrations:migrate
```

### Generating VAPID Keys (for Web Push)
```powershell
L:\php\php85\php.exe bin/console myframework:vapid:generate --subject="mailto:your@email.com"
# Add output to .env.local
```

### Using Push Notifications
The bundle provides a complete push notification system:

**User Flow:**
1. Navigate to `/notifications` (protected route, requires login)
2. Click "Enable Notifications" to subscribe
3. Browser prompts for permission
4. Use "Send Test Notification" to verify

**Developer API:**
```php
// Inject PushService in your controllers/services
public function __construct(
    private readonly PushService $pushService
) {}

// Send to specific user
$this->pushService->sendToUser(
    $user,
    'Welcome!',
    'Thanks for enabling notifications',
    $this->generateUrl('home')
);

// Send to all subscribers
$this->pushService->sendToAll(
    'Announcement',
    'New features available!'
);
```

**How it works:**
- Subscriptions stored in `mf_push_subscription` table (auto-cleanup on expired endpoints)
- VAPID keys configured via ENV (per-app)
- Service Worker handles push events and notification clicks
- Template includes inline JavaScript for subscription management

### Using Mobile UX Components

The framework provides mobile-first UI components and touch gesture controllers for building modern PWAs.

#### Toast Notifications

**Backend (Flash Messages → Toasts):**
```php
// In any controller
$this->addFlash('success', 'Erfolgreich gespeichert!');
$this->addFlash('error', 'Fehler beim Löschen');
$this->addFlash('warning', 'Bitte überprüfen');
$this->addFlash('info', 'Profil aktualisiert');
```

Toasts automatically:
- Appear at bottom center (mobile-optimized)
- Auto-dismiss after 3 seconds
- Show color-coded icons (✓ success, ✗ error, ⚠ warning, ℹ info)
- Are stackable and swipeable

**JavaScript API (for client-side toasts):**
```javascript
// Show toast without page reload
showToast('Erfolgreich gespeichert!', 'success');
showToast('Fehler aufgetreten', 'error', 5000); // Custom delay

// After Turbo/Fetch request
fetch('/api/save', {method: 'POST'})
    .then(() => showToast('Gespeichert!'));
```

**Toasts are automatically included** in `@MyFrameworkCore/base.html.twig` - nothing to configure!

#### Skeleton Loading States

Use skeleton screens for perceived performance while data loads:

```twig
{# Import skeleton macros #}
{% import '@MyFrameworkCore/components/skeleton.html.twig' as skeleton %}

{# Include CSS (only needed once per page) #}
{{ skeleton.styles() }}

{# Render skeletons #}
{{ skeleton.card(3) }}           {# 3 card skeletons #}
{{ skeleton.list(5) }}            {# 5 list items #}
{{ skeleton.text(3, '70%') }}     {# 3 text lines, last one 70% width #}
{{ skeleton.table(5, 4) }}        {# Table: 5 rows, 4 columns #}
{{ skeleton.form(3) }}            {# Form with 3 fields #}
{{ skeleton.avatar('48px') }}     {# Avatar circle #}
{{ skeleton.image('16/9') }}      {# Image with aspect ratio #}

{# Custom skeleton box #}
{{ skeleton.box('100%', '200px') }}

{# Usage in Turbo Frames #}
<turbo-frame id="content" src="/load-data">
    {{ skeleton.card(2) }}
</turbo-frame>
```

#### Touch Gestures

The framework provides Stimulus controllers for mobile gestures.

**Swipe Detection:**
```html
<div data-controller="myframework--swipeable"
     data-action="swipeleft->handleDelete swiperight->handleArchive"
     data-myframework--swipeable-threshold-value="50">
    Swipe me!
</div>

<script>
// In your Stimulus controller or inline
function handleDelete() {
    showToast('Gelöscht!', 'success');
}
</script>
```

**Long Press:**
```html
<button data-controller="myframework--longpress"
        data-action="longpress->showContextMenu"
        data-myframework--longpress-duration-value="500">
    Long press for menu
</button>
```

**Pull to Refresh:**
```html
<div data-controller="myframework--pullrefresh"
     data-action="refresh->reloadData"
     data-myframework--pullrefresh-threshold-value="80">
    <!-- Your scrollable content -->
</div>

<script>
async function reloadData(event) {
    await fetch('/api/refresh');
    showToast('Aktualisiert!', 'success');
    // Refresh UI...
}
</script>
```

**Installing Stimulus Controllers in your app:**

The framework provides controllers as standalone JavaScript files. Copy them to your app's `assets/controllers/` directory:

```bash
# From your app directory
cp vendor/myframework/core/resources/assets/controllers/* assets/controllers/myframework/
```

Then register them in `assets/stimulus_bootstrap.js`:
```javascript
import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

// Register MyFramework Mobile UX Controllers
import ToastController from './controllers/myframework/toast_controller.js';
import SwipeableController from './controllers/myframework/swipeable_controller.js';
import LongpressController from './controllers/myframework/longpress_controller.js';
import PullrefreshController from './controllers/myframework/pullrefresh_controller.js';

app.register('myframework--toast', ToastController);
app.register('myframework--swipeable', SwipeableController);
app.register('myframework--longpress', LongpressController);
app.register('myframework--pullrefresh', PullrefreshController);
```

**Note:** Don't add them to `controllers.json` - that's only for external NPM packages.

### Adding New Bundle Features
1. Create classes in `packages/myframework-core/src/`
2. Services auto-register via `services.yaml` (autowire + autoconfigure enabled)
3. For configuration: Add to `Configuration.php` tree, read in `MyFrameworkCoreExtension.php`
4. Templates go in `resources/views/` and are accessible via `@MyFrameworkCore` namespace

### Debugging Integration Issues
- Check if `vendor/myframework/core` is a symlink: `Get-Item apps/smoke/vendor/myframework/core`
- Clear cache: `L:\php\php85\php.exe bin/console cache:clear`
- Verify routing: `L:\php\php85\php.exe bin/console debug:router` (read full output, do not pipe)
- Check container: `L:\php\php85\php.exe bin/console debug:container MyFramework`

## Known Quirks

1. **Symlink behavior**: Both smoke and kohlkopf apps use `symlink=true` for live development. Changes in the bundle are immediately available without `composer update`.

2. **PHP version differences**: Bundle requires PHP 8.4+, smoke app allows 8.3+. Always use PHP 8.4+ to avoid compatibility issues.

3. **Vendor path assumptions**: Some configs reference `vendor/myframework/core/...` directly, which assumes standard Composer vendor directory structure.

4. **Version constraints are loose**: Bundle has `branch-alias dev-main: 0.1.x-dev`. Apps should pin to specific versions once releases exist.

5. **No explicit bundle config file**: Apps don't need `config/packages/my_framework_core.yaml` - all config has ENV defaults. Only create if overriding defaults.

6. **Doctrine migrations diff broken in kohlkopf**: `doctrine:migrations:diff` always produces a misleading "metadata out of sync" error in the kohlkopf app. Do not use it. Always write migrations manually — see the "Doctrine Migrations in Kohlkopf" section above.

7. **PHP executable on Windows**: The `php` from PATH is not the right version. Always use `L:\php\php85\php.exe` explicitly.

8. **No pipes in terminal commands**: Using `|` in terminal commands breaks agent output parsing. Run commands without pipes and read the full output.
