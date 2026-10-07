# Kolyansburg API

## Project Overview

Backend of Kolyansburg, a family messenger (https://call-yansburg.com; clients `../starter-frontend` and
`../starter-mobile`): chats with folders, reactions and attachments, 1:1 WebRTC calls, call links with guests,
call push. Laravel 12, PHP 8.3, RoadRunner HTTP server, MariaDB 11.4, Redis 7, Laravel Sanctum (Bearer
tokens), ffmpeg + Imagick for media. The user-facing name comes from `APP_NAME`. Detailed rules:
`ARCHITECTURE.md`; production: `deploy/README.md`.

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
User also owns the profile: `PATCH profile` (name + optional unique `username`, lowercase `[a-z0-9_]{3,32}`;
registration requires it, same rule); `POST|DELETE profile/avatar` (multipart `avatar`: `MediaProcessor::avatar`
crops to a 512 px JPEG on the `attachments` disk under `avatars/`; users carry a signed `avatar_url` or null,
served like attachments — `AuthorizeChatFileAction` lets any signed-in user see an avatar);
`GET users?search=` matches only a substring of the username (`INSTR`, so `_` is literal; a leading `@` is ignored) — never name or email.
Chat (messenger, 1-on-1 for now; the schema is ready for groups: `chats` + `chat_members` + `chat_messages`).
Writes go through REST: `GET chats`, `GET chats/{id}`, `POST chats/direct {user_id}` (find or create),
`GET chats/{id}/messages?before_id=` (50 per page, oldest first, `has_more`), `POST chats/{id}/messages
{body, client_id}` (client-chosen UUID makes retries idempotent), `POST chats/{id}/read {message_id}`.
Read state is a per-member cursor `chat_members.last_read_message_id` (only moves forward); unread counts and
"read" ticks are derived from it. Guests do not use chats (`not_guest`) and cannot be chat peers.
Reactions: `PUT|DELETE chats/{id}/messages/{messageId}/reaction {emoji}` — one per user per message (a new one
replaces it), emoji from `ChatReactionEnum` (mirrored in the frontend); messages carry `reactions:
[{emoji, user_ids}]`. `DELETE chats/{id}/messages/{messageId}` deletes for everyone — only the author, never
a call message — with its attachment files; the chat's last message falls back to the previous one.
`PATCH chats/{id}/messages/{messageId} {body}` edits the author's own text message (not a forward, not a call)
until it is answered — any later message or call from another member makes it 409; sets `edited_at` and
sends `chat.message_updated {chat_id, message}` (the same text changes nothing and sends no event).
`POST chats/{id}/messages/forward {message_id, client_id}` copies a message the user can see (text and ready
attachments as new files) into chat `{id}`; it carries `forwarded_from {user_id, name}` of the original author
(forwarding a forward keeps it) and goes out as a normal `chat.message`. Folders are private to their owner: `GET|POST chat-folders`, `PATCH|DELETE
chat-folders/{id}`, `PUT|DELETE chat-folders/{id}/chats/{chatId}`, max 20; `GET chats?folder_id=` filters
the list; a folder carries `chat_ids` and `unread_chats_count`.
Attachments (`config/attachments.php`): `POST attachments` (multipart `file`, optional `name` — phones send
the real file name because pickers hand over UUID cache files, `voice`, `as_file`; `throttle:chat-attachment`)
stores the upload on the `attachments` disk and returns a `ChatAttachmentResource` without a message;
`POST chats/{id}/messages {attachment_ids}` (max 10, body optional then) attaches the sender's own unsent
uploads. Images, videos and voice get `status = processing` and `ProcessChatAttachmentJob` on the `media`
queue (`MediaProcessor`: Imagick strips EXIF/GPS and auto-rotates, ffmpeg makes H.264 ≤1280 px / AAC, voice
gets a waveform); the original is deleted, then `chat.attachment {chat_id, message_id, attachment}` goes to
the members over the RealtimeBus. Broken media becomes `failed`, never a queue crash. Resource URLs are relative
`/api/files/{path}?expires&signature` (`FileUrlSigner`, HMAC with the app key, valid until the end of the next
UTC day). In prod Caddy serves `/api/files/*` itself after `forward_auth` to `GET attachments/authorize`;
locally `ServeChatFileController` streams the file. A quota (`ATTACHMENTS_QUOTA_BYTES`, 10 GB) rejects uploads
with 507; at 90% `NotifyChatStorageUsageTask` pings the admin Telegram (`services.telegram`, re-armed below
85%). `attachments:prune` (hourly) drops uploads never sent within `orphan_ttl_hours`; `attachments:prune
--before=DATE` frees space; `attachments:usage` prints usage. Pruning deletes the rows and files but keeps
messages: one left with no body and no attachments shows «Файл удалён» in the clients. Nightly encrypted copy
to R2: `deploy/backup.sh`.

Realtime from the API to browsers: HTTP workers cannot see open sockets, so Actions publish events to
`App\Services\RealtimeBus` (a Redis list, `config/realtime.php`; the `array` driver keeps them in memory for
tests) after the transaction commits. The WebSocket server drains the bus every loop (`socket_select` waits at
most 0.1 s) and `MessageRouter::flushRealtime()` sends each event to every tab of its `user_ids`.
Chat events: `chat.message {message}`, `chat.read {chat_id, user_id, last_read_message_id, unread_count}`,
`chat.reaction {chat_id, message_id, reactions}`, `chat.attachment {chat_id, message_id, attachment}` (media
finished processing), `chat.message_deleted {chat_id, message_id, user_id, last_changed, last_message}`
(`last_message` is the new chat preview when `last_changed`), `chat.folders` (no data, only to the owner: refetch folders).
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
- Locale per request via `SetLocale` middleware: `X-Locale`, then `Accept-Language`, fallback `ru`.
  Languages: `app.supported_locales` (`ru`, `vi`, `en`); a new one is a code there plus `lang/{code}/` —
  `LocaleFilesTest` fails while it lacks any key of `lang/ru`. Each user has `users.locale` (interface
  language; incoming chat messages will be translated into it): set at registration / guest join from
  the request locale, changed by `PUT profile/locale {locale}` (guests too; the reply is already in the
  new language), exposed as `locale` in `UserResource`
- Secrets of external services (LLM keys for translation, `config/translation.php`) are read only through
  `App\Services\SecretReader` at the moment of use: `{key}_file` (prod: `deploy/secrets/*`, mounted
  read-only) wins over the plain `{key}` env value (local dev). It returns a `Secret` that masks itself
  in dumps/JSON and refuses to serialize — never pass the revealed string into jobs, logs, exceptions or
  resources, and never put a key into a URL. Provider base URLs are fixed in config, not env. Every log
  channel taps `RedactSecretsLogTap` (masks `sk-…`, Bearer and `x-api-key` values). Tests force all keys empty

## Testing

- PHPUnit with SQLite in-memory database, `BCRYPT_ROUNDS=4`
- Tests live in `app/Modules/{Module}/Tests/{Unit,Feature}/`, base class `Tests\TestCase` (RefreshDatabase)
- Run: `make test`

## Database

- MariaDB 11.4 (dev credentials: user/user, root/root, database: starter)
- Migrations in `database/migrations/` (framework tables) and `app/Modules/*/Database/Migrations/`
- Seeders: `DatabaseSeeder` → `UserSeeder` (password `Password123` for every mock user)
