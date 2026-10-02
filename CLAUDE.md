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

Modules: User (model, factory, seeder, repository, user list), Auth (register, login, logout, me),
Call (1-on-1 video calls: call history, ICE servers, own WebSocket signaling server in `Call/WebSockets/`,
started by `php artisan call:ws-serve` under supervisor; restart it after code changes with `make ws-restart`).
Media goes peer-to-peer over WebRTC; the server only relays JSON signaling messages and records call statuses.
CallLink (personal permanent "call me" link: `GET call-link`, `POST call-link/rotate`, public
`GET call-links/{code}` and `POST call-links/{code}/join`). Joining creates a guest — a `users` row with
`guest_of_id` = link owner and a short-lived token (`calls.links.guest_token_ttl`). Guests are never deleted
(calls cascade on user delete). `User::canContact()` is the single rule: a guest and their host can call and
see each other online, nobody else. Endpoints guests must not use get the `not_guest` middleware.
User also owns the profile: `PATCH profile` (name + optional unique `username`, lowercase `[a-z0-9_]{3,32}`);
`GET users?search=` matches name, email or username (a leading `@` is ignored).
Chat (messenger, 1-on-1 for now; the schema is ready for groups: `chats` + `chat_members` + `chat_messages`).
Writes go through REST: `GET chats`, `GET chats/{id}`, `POST chats/direct {user_id}` (find or create),
`GET chats/{id}/messages?before_id=` (50 per page, oldest first, `has_more`), `POST chats/{id}/messages
{body, client_id}` (client-chosen UUID makes retries idempotent), `POST chats/{id}/read {message_id}`.
Read state is a per-member cursor `chat_members.last_read_message_id` (only moves forward); unread counts and
"read" ticks are derived from it. Guests do not use chats (`not_guest`) and cannot be chat peers.
Reactions: `PUT|DELETE chats/{id}/messages/{messageId}/reaction {emoji}` — one per user per message (a new one
replaces it), emoji from `ChatReactionEnum` (mirrored in the frontend); messages carry `reactions:
[{emoji, user_ids}]`. Folders are private to their owner: `GET|POST chat-folders`, `PATCH|DELETE
chat-folders/{id}`, `PUT|DELETE chat-folders/{id}/chats/{chatId}`, max 20; `GET chats?folder_id=` filters
the list; a folder carries `chat_ids` and `unread_chats_count`.

Realtime from the API to browsers: HTTP workers cannot see open sockets, so Actions publish events to
`App\Services\RealtimeBus` (a Redis list, `config/realtime.php`; the `array` driver keeps them in memory for
tests) after the transaction commits. The WebSocket server drains the bus every loop (`socket_select` waits at
most 0.1 s) and `MessageRouter::flushRealtime()` sends each event to every tab of its `user_ids`.
Chat events: `chat.message {message}`, `chat.read {chat_id, user_id, last_read_message_id, unread_count}`,
`chat.reaction {chat_id, message_id, reactions}`, `chat.folders` (no data, only to the owner: refetch folders).
Calls in chat: when a call reaches a final status, `MessageRouter` runs `RecordCallInChatAction`, which adds
a `type = call` message (author = caller, empty body, `call_id`; the resource exposes `call {id, status,
duration_seconds}`) to the participants' direct chat, creating it if needed. Calls with guests are not
recorded. `client_id` is a UUID v5 of the call id, so recording twice is a no-op. Answered or rejected calls
also move the callee's read cursor if they had read everything, so only missed calls show as unread.
Typing: the client sends `chat.typing {chat_id}` over the socket; the router checks membership
(`ListTypingRecipientsAction`) and forwards `chat.typing {chat_id, user_id}` to the other members directly
(not stored, not via the bus). Restart the WebSocket server after changing either.
WebSocket Origin check (`calls.websocket.allowed_origins`): site origins (`CALL_WS_ALLOWED_ORIGINS`, falling
back to `CORS_ALLOWED_ORIGINS`) plus the mobile app's `CALL_WS_APP_ORIGINS` (default `starter-mobile://app`,
sent by `starter-mobile`). No site origins means the check is off.
Push (module Push, phones only): `PUT push/devices {token}` stores an FCM token in `push_devices`, tied to the
Sanctum token it came with (`access_token_id` cascades, so logout forgets the device; a token re-registered by
another user moves to them). `App\Services\FcmClient` sends data-only FCM HTTP v1 messages with the service
account key from `push.fcm.credentials` (`FCM_CREDENTIALS`, default `storage/app/firebase-credentials.json`,
in prod `deploy/secrets/`); no key means push is off and `UserHasPushDevicesTask` is false. Tests force
`FCM_CREDENTIALS=""`; `Tests\FakeFirebase::enable()` fakes Google with a real RSA key.
Calls and push: the app sends `client.state {background}` over the socket. `call.invite` to a callee with no
foreground connection but with push devices rings anyway and queues `SendCallPushJob` (incoming: `call_id`,
`caller_name`, `decline_token`, `expires_at`); the WS loop never calls FCM itself. When the app connects it
gets the still-ringing call as `call.incoming` (`MessageRouter::connected`). Accepting, or ending a call that
was never answered, queues a second push (`call.ended`) that removes the notification. "Decline" in the
notification posts `POST calls/{id}/decline {token}` without auth (HMAC of call id + callee id with the app
key, `throttle:call-decline`); the API publishes `server.call_finished` on the RealtimeBus and the router
sends `call.ended`. The queue worker must run for push (supervisor `queue:work`).
`chat_messages.client_id` is a native UUID column in MariaDB but plain text in the SQLite tests, so a
non-UUID value passes the tests and fails in dev.

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

Docker compose: `docker-compose.yml` (services: php, mariadb, redis). Ports: API 8090, WebSocket 8091, DB 33070, Redis 6390.

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
