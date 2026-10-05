# Kolyansburg API

Бэкенд семейного мессенджера Kolyansburg — https://call-yansburg.com. Клиенты: веб
([starter-frontend](../starter-frontend)) и Android-приложение ([starter-mobile](../starter-mobile)).

Что умеет:

- регистрация с обязательным ником `@username`, вход по Bearer-токену, профиль с аватаркой, поиск людей по нику;
- чаты 1:1 с папками, реакциями, «прочитано» и «печатает…», удаление своих сообщений у всех, пересылка;
- фото, видео, голосовые и файлы в чатах: до 50 МБ на файл, сжатие на сервере, всего до 10 ГБ;
- видеозвонки 1:1 (WebRTC) со своим сервером сигнализации, TURN и историей звонков;
- личная ссылка для звонка и вход гостем;
- push о входящих звонках на телефон (Firebase).

Модульная архитектура Controller → Action → Task → Repository и строгие проверки (PHPStan level 10 +
архитектурные правила).

Стек: PHP 8.3, Laravel 12, RoadRunner, MariaDB 11.4, Redis 7, Laravel Sanctum, spatie/laravel-data,
spatie/laravel-route-attributes, L5-Swagger, ffmpeg и Imagick (сжатие вложений).

## Быстрый старт (macOS)

Нужен только Docker Desktop — PHP и Composer на машине не требуются.

```bash
make start
```

Команда создаёт `.env` с новым `APP_KEY`, собирает образ, поднимает контейнеры, ждёт healthcheck,
пересоздаёт БД с моковыми пользователями и генерирует Swagger.

| Что       | Адрес                                     |
|-----------|-------------------------------------------|
| API       | http://localhost:8090/api                 |
| Swagger   | http://localhost:8090/api/documentation   |
| Healthcheck | http://localhost:8090/up                |
| Звонки (WebSocket) | ws://localhost:8091?ticket=<билет из POST /api/calls/ws-ticket> |
| MariaDB   | localhost:33070 (starter / user / user)   |
| Redis     | localhost:6390                            |

Порты меняются в `.env`: `APP_PORT`, `WS_PORT`, `DB_FORWARD_PORT`, `REDIS_FORWARD_PORT`.

### Моковые пользователи

Пароль у всех — `Password123`.

- `admin@example.com` — Администратор
- `ivan@example.com` — Иван Петров
- `maria@example.com` — Мария Смирнова
- ещё 10 случайных

Пересоздать БД и пользователей: `make refresh`.

## API

Все ответы в одном формате:

```json
{ "status": "success|error", "message": "текст", "data": {}, "errors": { "field": ["..."] } }
```

Полный список с параметрами и примерами — в Swagger (http://localhost:8090/api/documentation).
Основные группы:

| Пути | Что |
|------|-----|
| `auth/register`, `auth/login`, `auth/me`, `auth/logout` | Регистрация и вход, токен на устройство |
| `profile`, `profile/avatar`, `users?search=` | Свой профиль, ник и аватарка, поиск только по `@нику` |
| `chats`, `chats/direct`, `chats/{id}/messages`, `chats/{id}/read`, `…/reaction`, `…/messages/forward` | Чаты, сообщения (отправка, удаление, пересылка), «прочитано», реакции |
| `chat-folders` | Личные папки чатов |
| `attachments`, `files/{path}` | Загрузка вложения и выдача файла по подписанной ссылке |
| `calls`, `calls/ice-servers`, `calls/ws-ticket`, `calls/{id}/decline` | История звонков, STUN/TURN, билет для сокета, «Отклонить» из уведомления |
| `call-link`, `call-links/{code}` | Своя ссылка для звонка, вход гостем по чужой |
| `push/devices` | FCM-токен телефона |

События в реальном времени (новые и удалённые сообщения, «прочитано», реакции, готовые вложения, звонки)
приходят по WebSocket — `ws://localhost:8091`, в проде `wss://call-yansburg.com/ws`.

```bash
curl -X POST http://localhost:8090/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"Password123"}'

curl http://localhost:8090/api/auth/me -H 'Authorization: Bearer <access_token>'
```

Язык ответов: заголовок `X-Locale` или `Accept-Language` (`ru` по умолчанию, есть `en`).

## Файлы в чатах

1. Клиент загружает файл: `POST /api/attachments` (multipart: `file`, необязательные `name` — имя
   у отправителя, `voice` — голосовое, `as_file` — отправить фото или видео без сжатия) и получает
   вложение со `status`.
2. Отправляет сообщение с ним: `POST /api/chats/{id}/messages {client_id, body?, attachment_ids}` —
   до 10 вложений, текст необязателен.
3. Фото, видео и голосовые сжимает очередь `media` (`ProcessChatAttachmentJob`): пока идёт сжатие,
   у вложения `status: processing`, по готовности участникам чата приходит событие
   `chat.attachment`. Битый файл получает `failed`, сообщение остаётся.

Фото — до 2560 px и превью 480 px, без EXIF и координат (JPEG; PNG и GIF остаются в своём формате); видео — H.264 до 720p с кадром-превью;
голосовые — AAC 64 кбит/с с волной громкости. Остальное хранится как есть. Не отправленные за сутки
загрузки удаляются раз в час (`attachments:prune`). Сколько занято: `make artisan cmd='attachments:usage'`.

Ссылки на файлы относительные (`/api/files/…?expires=…&signature=…`), подписаны ключом приложения и
живут до конца следующих суток. Локально файл отдаёт PHP, в проде — Caddy после проверки подписи
(`deploy/Caddyfile`). Хранение на сервере, лимиты и копия в R2 — [deploy/README.md](deploy/README.md).

## Безопасность

- Пароли хэшируются bcrypt (cast `hashed` у модели, `BCRYPT_ROUNDS=12`), в API не отдаются.
- Требования к паролю: от 8 символов, заглавные и строчные буквы, цифры, подтверждение.
- Токены Sanctum: в БД хранится только SHA-256 токена, срок жизни — `SANCTUM_TOKEN_EXPIRATION`
  (по умолчанию 7 дней), истёкшие удаляются планировщиком раз в сутки.
- Вход не выдаёт, существует ли email: одинаковая ошибка и одинаковое время ответа
  (проверка против фиктивного хэша, если пользователь не найден).
- Лимиты: вход — 5 попыток в минуту на пару email + IP, регистрация — 10 в минуту на IP.
- CORS открыт только для `CORS_ALLOWED_ORIGINS` (по умолчанию фронт `http://localhost:5190`).
- Сервер звонков принимает не токен, а одноразовый билет на 30 секунд (`POST /api/calls/ws-ticket`):
  адрес сокета попадает в логи прокси, и токен там оседать не должен.
- Логины TURN временные и у каждого пользователя свои, если задан `CALL_TURN_SECRET`.
- Файлы чатов открываются только по подписанной ссылке, которую API выдаёт участникам чата; из фото
  удаляются EXIF и координаты. Загрузка — 30 файлов в минуту на пользователя.

## Команды

`make help` — полный список. Основные:

```bash
make up / make down   # запуск / остановка
make logs             # логи RoadRunner, очереди и планировщика
make shell            # bash в PHP-контейнере
make test             # PHPUnit (SQLite in-memory)
make phpstan          # PHPStan level 10 + архитектурные правила
make lint             # php-cs-fixer
make check            # lint-check + rector + phpstan + test — перед каждым коммитом
make composer cmd='require vendor/package'
make artisan cmd='route:list'
```

## Домен

- Домен: `call-yansburg.com`.
- Регистратор: [Cloudflare](https://dash.cloudflare.com), аккаунт `nikolasparaslovgp@gmail.com`.
- Куплен 1 октября 2026 года на год, оплачен до 1 октября 2027 года. Автопродление выключено:
  включается в Cloudflare → Domain Registration → Manage Domains.
- DNS тоже в Cloudflare. Записи `A @` и `A turn` указывают на IP сервера в режиме «DNS only»
  (серое облако): через прокси Cloudflare TURN не работает.
- Сервер, деплой и обновление — [deploy/README.md](deploy/README.md).

## Архитектура

Правила и порядок добавления модуля — в [ARCHITECTURE.md](ARCHITECTURE.md).
