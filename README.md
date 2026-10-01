# Starter API

Заготовка REST API на Laravel 12: регистрация, вход по Bearer-токену, моковые пользователи,
модульная архитектура Controller → Action → Task → Repository и строгие проверки (PHPStan level 10 +
архитектурные правила).

Стек: PHP 8.3, Laravel 12, RoadRunner, MariaDB 11.4, Redis 7, Laravel Sanctum, spatie/laravel-data,
spatie/laravel-route-attributes, L5-Swagger.

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
| MariaDB   | localhost:33070 (starter / user / user)   |
| Redis     | localhost:6390                            |

Порты меняются в `.env`: `APP_PORT`, `DB_FORWARD_PORT`, `REDIS_FORWARD_PORT`.

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

| Метод | Путь                 | Доступ    | Описание |
|-------|----------------------|-----------|----------|
| POST  | `/api/auth/register` | гость     | Регистрация, сразу возвращает токен (201) |
| POST  | `/api/auth/login`    | гость     | Вход, возвращает токен |
| GET   | `/api/auth/me`       | по токену | Текущий пользователь |
| POST  | `/api/auth/logout`   | по токену | Отзывает токен текущего устройства |

```bash
curl -X POST http://localhost:8090/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"Password123"}'

curl http://localhost:8090/api/auth/me -H 'Authorization: Bearer <access_token>'
```

Язык ответов: заголовок `X-Locale` или `Accept-Language` (`ru` по умолчанию, есть `en`).

## Безопасность

- Пароли хэшируются bcrypt (cast `hashed` у модели, `BCRYPT_ROUNDS=12`), в API не отдаются.
- Требования к паролю: от 8 символов, заглавные и строчные буквы, цифры, подтверждение.
- Токены Sanctum: в БД хранится только SHA-256 токена, срок жизни — `SANCTUM_TOKEN_EXPIRATION`
  (по умолчанию 7 дней), истёкшие удаляются планировщиком раз в сутки.
- Вход не выдаёт, существует ли email: одинаковая ошибка и одинаковое время ответа
  (проверка против фиктивного хэша, если пользователь не найден).
- Лимиты: вход — 5 попыток в минуту на пару email + IP, регистрация — 10 в минуту на IP.
- CORS открыт только для `CORS_ALLOWED_ORIGINS` (по умолчанию фронт `http://localhost:5190`).

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

## Архитектура

Правила и порядок добавления модуля — в [ARCHITECTURE.md](ARCHITECTURE.md).
