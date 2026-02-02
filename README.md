# MyFramework – Symfony PWA Bundle Monorepo

A modern **Symfony 7.4 Bundle** for rapidly building **mobile-first Progressive Web Apps (PWAs)** with batteries included.

This is a **monorepo** containing:
- **`packages/myframework-core/`** - The reusable Symfony Bundle (`myframework/core`)
- **`apps/smoke/`** - Integration test app (Symfony 7.4 LTS) serving as living documentation

## 🚀 Features

### Core Authentication System
- ✅ User registration & login
- ✅ Email verification
- ✅ Password reset workflow
- ✅ Secure password hashing (bcrypt/argon2)
- ✅ CSRF protection

### Progressive Web App (PWA)
- ✅ Service Worker integration
- ✅ Web App Manifest
- ✅ Installable on mobile & desktop
- ✅ Offline-capable architecture

### Push Notifications
- ✅ Web Push API integration (VAPID)
- ✅ Browser notification support
- ✅ Subscription management
- ✅ Send to individual users or broadcast

### Mobile UX Components
- ✅ **Toast Notifications** - Auto-dismissing feedback messages
- ✅ **Skeleton Screens** - Loading state placeholders
- ✅ **Touch Gestures** - Swipe, Long-Press, Pull-to-Refresh (Stimulus Controllers)
- ✅ Responsive mobile-first templates

### UI & Branding
- ✅ Customizable branding (app name, colors, logo)
- ✅ Bootstrap 5 integration
- ✅ Ready-to-use templates (login, register, home)
- ✅ Environment-based configuration

## 📦 Installation (for New Apps)

```bash
composer require myframework/core
```

See [`.github/copilot-instructions.md`](.github/copilot-instructions.md) for detailed setup instructions.

## 🏗️ Repository Structure

- **`packages/myframework-core/`**  
  The reusable Symfony Bundle. All framework features are developed here.

- **`apps/smoke/`**  
  Symfony 7.4 LTS integration test app. Demonstrates all framework features.

- **Root `composer.json`**  
  Workspace helper to make the package locally installable.

## 🛠️ Local Development (Smoke App)

The smoke app is the fastest way to test the bundle end-to-end.

### Requirements

- PHP 8.3+
- Composer 2.x
- PostgreSQL (or MySQL/MariaDB)

### Setup

```bash
cd apps/smoke

# Install dependencies
composer install

# Configure environment
cp .env .env.local
# Edit .env.local with your database, mailer, and VAPID settings

# Start database (Docker)
docker compose up -d

# Run migrations
php bin/console doctrine:migrations:migrate

# Start development server
php -S localhost:8000 -t public
# OR
symfony server:start
```

Visit `http://localhost:8000` to see the framework in action!

## 🔗 How the Bundle is Linked Locally

The bundle is integrated via Composer **path repository** with `symlink=true` in the smoke app:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../../packages/myframework-core",
            "options": { "symlink": true }
        }
    ]
}
```

**This means:** Changes in `packages/myframework-core/src/` are **immediately active** in `apps/smoke/vendor/myframework/core/` without `composer update`.

## 🧪 Testing

### Bundle Unit Tests
```bash
cd packages/myframework-core
vendor/bin/phpunit
```

### Integration Tests (Smoke App)
```bash
cd apps/smoke
php vendor/bin/phpunit
```

The smoke app uses strict PHPUnit config (`failOnWarning`, `failOnDeprecation`) to catch Symfony deprecations early.

## 📚 Documentation

Comprehensive development guide: [`.github/copilot-instructions.md`](.github/copilot-instructions.md)

Topics covered:
- Bundle architecture & conventions
- Development workflows
- Configuration patterns
- Database setup
- Push notifications
- Mobile UX components
- Deployment strategies

## 📄 License

MIT License - see [LICENSE](LICENSE) file for details.

Free to use for personal and commercial projects!

## ✨ Built With

- [Symfony 7.4](https://symfony.com/)
- [Bootstrap 5](https://getbootstrap.com/)
- [Stimulus](https://stimulus.hotwired.dev/)
- [Turbo](https://turbo.hotwired.dev/)
- [Web Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API)
