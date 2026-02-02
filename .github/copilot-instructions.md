# MyFramework Development Guide

## Architecture Overview

This is a **Symfony Bundle monorepo** with two distinct parts:
- `packages/myframework-core/`: Reusable Symfony Bundle (`myframework/core`) providing auth, PWA, and push notifications
- `apps/smoke/`: Symfony 7.4 LTS integration test app that exercises the bundle in a realistic environment

**Critical**: The bundle is linked via Composer **path repository** with `symlink=true` in the smoke app. Changes in `packages/myframework-core/src/` are **immediately active** in the smoke app's `vendor/myframework/core/` without `composer update`.

## Key Conventions

### Namespace & Naming
- Bundle namespace: `MyFramework\Core\` (PSR-4 mapped from `packages/myframework-core/src/`)
- Composer package name: `myframework/core`
- Configuration key: `my_framework_core` (snake_case in YAML)
- Entity table prefix: `mf_` (e.g., `mf_user`, `mf_password_reset_token`)

### Strict PHP Standards
- PHP 8.3+ required for bundle (`packages/myframework-core/composer.json`)
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

## Development Workflows

### Working on Bundle Features
1. Make changes in `packages/myframework-core/src/`
2. Changes are **instantly available** in smoke app due to symlink
3. Test integration: `cd apps/smoke && php bin/console [command]`
4. Run bundle unit tests: `cd packages/myframework-core && vendor/bin/phpunit`
5. Run smoke app tests: `cd apps/smoke && php vendor/bin/phpunit`

### Testing Strategy
- **Bundle tests** (`packages/myframework-core/tests/`): Minimal unit tests for bundle logic
- **Smoke app tests** (`apps/smoke/tests/`): Integration tests with strict PHPUnit config:
  - `failOnWarning="true"`, `failOnNotice="true"`, `failOnDeprecation="true"`
  - Acts as early warning system for Symfony deprecations

### Running the Smoke App
```powershell
cd apps/smoke

# Start infrastructure (Postgres + optional Mailpit)
docker compose up -d

# Install dependencies (if needed)
composer install

# Run migrations
php bin/console doctrine:migrations:migrate

# Start development server (choose one)
php -S localhost:8000 -t public
# OR
symfony server:start
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

## Setting Up a New App

When creating a new app based on this bundle:

### 1. Install the Bundle
```bash
composer require myframework/core
```

### 2. Configure PWA (Progressive Web App)

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

### 3. Provide App Icon

Create `public/icon.svg` (simple example):
```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
  <rect width="512" height="512" rx="64" fill="#0d6efd"/>
  <text x="50%" y="50%" text-anchor="middle" dy=".35em" fill="white" font-family="system-ui" font-size="200" font-weight="bold">A</text>
</svg>
```

### 4. Environment Variables

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
```bash
php bin/console doctrine:migrations:migrate
```

### Generating VAPID Keys (for Web Push)
```bash
php bin/console myframework:vapid:generate --subject="mailto:your@email.com"
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
- Check if `vendor/myframework/core` is a symlink: `ls -la apps/smoke/vendor/myframework/`
- Clear cache: `php bin/console cache:clear`
- Verify routing: `php bin/console debug:router | grep myframework`
- Check container: `php bin/console debug:container MyFramework`

## Known Quirks

1. **Different symlink behavior**: Root workspace uses `symlink=false`, smoke app uses `symlink=true`. Work in smoke app for live development.

2. **PHP version mismatch potential**: Smoke app allows `>=8.2`, but bundle requires `^8.3`. Always use PHP 8.3+ to avoid runtime surprises.

3. **Vendor path assumptions**: Some configs reference `vendor/myframework/core/...` directly, which assumes standard Composer vendor directory structure.

4. **Version constraints are loose**: Bundle has `branch-alias dev-main: 0.1.x-dev`. Apps should pin to specific versions once releases exist.

5. **No explicit bundle config file**: Apps don't need `config/packages/my_framework_core.yaml` - all config has ENV defaults. Only create if overriding defaults.
