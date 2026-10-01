# Starter API

## Project Overview

Laravel 12 REST API starter: registration, token login, mock users. PHP 8.3, RoadRunner HTTP server,
MariaDB 11.4, Redis 7, Laravel Sanctum (Bearer tokens). Detailed rules: `ARCHITECTURE.md`.

## Architecture

Modular architecture under `app/Modules/`. Each module is self-contained:

```
app/Modules/{ModuleName}/
├── Actions/          # Business scenario orchestrators, run()
├── Tasks/            # Atomic operations, run()
├── Repositories/     # The only layer that touches the database
├── DTO/              # Data Transfer Objects (spatie/laravel-data)
├── Models/           # Eloquent models
├── Http/
│   ├── Controllers/  # Single-action (__invoke) controllers
│   ├── Requests/     # Form request validation
│   └── Resources/    # JSON API resources
├── Exceptions/       # ApiException subclasses
├── Database/         # Module migrations, factories, seeders
├── Providers/        # Module provider (extends LoadModuleProvider), auto-registered
└── Tests/            # Unit and Feature tests
```

Modules: User (model, factory, seeder, repository), Auth (register, login, logout, me).

Call chain: Controller → Action → Task → Repository. Controllers call exactly one Action; Actions never
call other Actions (use SubActions); Tasks never call Actions; Repositories are used only from
Actions/Tasks/Repositories, and only Repositories query the DB. `DB::transaction` belongs in Actions.

## Key Commands (via Docker)

```bash
make start              # First-time full setup: .env, build, up, migrate + seed, swagger
make up / make down     # Start/stop containers
make rebuild            # Rebuild image (after composer.json / Dockerfile changes)
make shell              # Enter PHP container bash
make test               # PHPUnit
make lint               # php-cs-fixer
make phpstan            # PHPStan (level 10)
make rector             # Rector (dry-run)
make check              # lint-check + rector + phpstan + tests
make refresh            # Fresh migrations + seeds
```

Docker compose: `docker-compose.yml` (services: php, mariadb, redis). Ports: API 8090, DB 33070, Redis 6390.

## Code Quality Standards

- **PHPStan level 10** with larastan and phpstan-strict-rules
- **php-cs-fixer** for formatting, **Rector** for automated refactoring
- Custom PHPStan rules in `tests/PHPStanRules/`:
  - `RequireStrictTypesRule` — all files must have `declare(strict_types=1)`
  - `RequireFinalClassRule` — classes must be `final`
  - `ControllerInvokeOnlyRule` — controllers must use single `__invoke` method
  - `ControllerHasFeatureTestRule` — every controller has `Tests/Feature/{Controller}Test.php`
  - `ArchitectureRule`, `ActionUsageRule`, `RepositoryUsageRule`, `DbAccessOnlyInRepositoryRule` — layer boundaries
  - `ModuleNamingConventionRule`, `RequireParentClassRule` — folder suffixes and base classes
  - `NoDebugFunctionsRule` — no dd(), dump(), var_dump()
  - `VariableCamelCaseRule`, `UnusedUseRule`
  - `DbQueryInLoopRule` / `InArrayInLoopRule` — performance rules

## Conventions

- All classes must be `final`; all files must have `declare(strict_types=1)`
- Controllers are single-action (invokable `__invoke` only)
- Routes defined via `spatie/laravel-route-attributes` (attributes on controllers, `api` prefix added by config)
- DTOs use `spatie/laravel-data`
- Auth via Laravel Sanctum personal access tokens (Bearer). Passwords use the `hashed` cast (bcrypt)
- API documentation via L5-Swagger (OpenAPI annotations on controllers, requests, resources, exceptions)
- Responses always go through `App\Services\ApiResponder`: `{status, message, data, errors}`
- **Everything the client reads is localized**: exception texts in `lang/{locale}/exceptions.php`
  (`$errorMessage` stores the key), success messages in `messages.php`, field names in `fields.php`
  grouped per form (`attributes()` returns `Translator::group('fields.{form}')`). Resolve keys via
  `App\Services\Translator` (always returns a string)
- Locale per request via `SetLocale` middleware: `X-Locale`, then `Accept-Language`, fallback `ru`

## Testing

- PHPUnit with SQLite in-memory database, `BCRYPT_ROUNDS=4`
- Tests live in `app/Modules/{Module}/Tests/{Unit,Feature}/`, base class `Tests\TestCase` (RefreshDatabase)
- Run: `make test`

## Database

- MariaDB 11.4 (dev credentials: user/user, root/root, database: starter)
- Migrations in `database/migrations/` (framework tables) and `app/Modules/*/Database/Migrations/`
- Seeders: `DatabaseSeeder` → `UserSeeder` (password `Password123` for every mock user)
