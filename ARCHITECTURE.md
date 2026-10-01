# Архитектурный гайд

Проект построен на Laravel с модульной структурой и паттерном **Action/Task**. Каждый модуль
инкапсулирует свою бизнес-логику. Правила ниже не пожелания: большинство из них проверяет PHPStan
(`tests/PHPStanRules/`), и `make check` не пройдёт при нарушении.

## Структура модуля (`app/Modules/{Module}/`)

```
app/Modules/{Module}/
├── Actions/          # Бизнес-сценарии: XxxAction, метод run()
├── Tasks/            # Атомарные операции: XxxTask, метод run()
├── Repositories/     # Единственный слой, который ходит в БД: XxxRepository
├── DTO/              # spatie/laravel-data: XxxDTO
├── Models/           # Eloquent-модели
├── Http/
│   ├── Controllers/  # Один контроллер = один эндпоинт (__invoke)
│   ├── Requests/     # Валидация: XxxRequest
│   └── Resources/    # Ответ: XxxResource
├── Exceptions/       # XxxException extends ApiException
├── Database/
│   ├── Migrations/   # Подключаются провайдером модуля автоматически
│   ├── Factories/    # XxxFactory для модели Models/Xxx (связываются по имени)
│   └── Seeders/
├── Providers/        # XxxServiceProvider extends LoadModuleProvider — регистрируется сам
└── Tests/
    ├── Feature/      # Тест на каждый контроллер: {Controller}Test.php
    └── Unit/
```

Имена модулей — в единственном числе (`User`, `Auth`, `Order`). Папки — во множественном, кроме
`DTO`, `Http`, `Database`.

## Поток данных

```
Request (валидация) → Controller → DTO → Action::run() → Task::run() → Repository → Model
                                                                                       ↓
                                       JSON ← ApiResponder ← Resource ←────────────────┘
```

- **Controller** — тонкий: берёт DTO из Request, вызывает **один** Action, отдаёт
  `ApiResponder::ok|created(Resource::make(...), Translator::get('messages...'))`.
  Маршрут задаётся атрибутом: `#[Post('auth/login', middleware: 'throttle:login')]`. Префикс `api`
  и группа middleware `api` добавляются автоматически (`config/route-attributes.php`).
- **Action** — сценарий целиком: оркеструет Tasks, бросает бизнес-исключения, открывает
  `DB::transaction`, если шагов несколько. Action не вызывает другой Action (для переиспользования —
  `Actions/SubActions/XxxSubAction`). Предпочтительно работает через Tasks.
- **Task** — одна атомарная операция (`CreateUserTask`, `FindUserByEmailTask`). Не вызывает Actions,
  в БД ходит через Repository.
- **Repository** — `extends BaseRepository<Model>`, единственное место с запросами к БД
  (Eloquent / Query Builder / `DB`; `DB::transaction` разрешён везде). Вызывается из Tasks, Actions
  и других репозиториев — но не из контроллеров, запросов и ресурсов.

## Ошибки и ответы

- Формат всегда один: `{ status, message, data, errors }` — через `App\Services\ApiResponder`.
- Бизнес-ошибка — класс в `Exceptions/`, наследник `App\Exceptions\ApiException`:
  `$errorMessage` — ключ перевода `exceptions.{module}.{reason}`, `$statusCode` — HTTP-код.
  Рендерится сама, в лог не пишется. Каждое исключение описывает свой
  `@OA\Response(response="{Module}.{Class}")`, контроллер ссылается на него.
- Валидация (422), 401, 404, 429 оборачиваются в тот же формат в `bootstrap/app.php`.

## Локализация

Всё, что читает клиент, переводится: `lang/{ru,en}/`.

- `exceptions.php` — тексты исключений;
- `messages.php` — сообщения успешных ответов;
- `fields.php` — названия полей, **сгруппированы по формам** (`fields.register.email`); в Request:
  `attributes(): Translator::group('fields.register')`;
- `validation.php` — сообщения правил валидации.

Ключи берутся через `App\Services\Translator::get()` — он гарантирует строку, а не `mixed`.
Язык запроса выбирает middleware `SetLocale`: `X-Locale`, затем `Accept-Language`, иначе `ru`.

## Обязательные правила кода

- В каждом файле `declare(strict_types=1);`.
- Все классы `final` (кроме абстрактных базовых).
- Контроллер содержит только `__invoke`.
- На каждый контроллер есть feature-тест.
- Классы в папке слоя носят его суффикс и наследуют его базу: `Actions/*Action extends BaseAction`,
  `Tasks/*Task extends BaseTask`, `DTO/*DTO extends Data`, `Exceptions/*Exception extends ApiException`.
- Переменные в camelCase, неиспользуемых `use` нет, `dd()`/`dump()`/`var_dump()` запрещены.
- Нет запросов к БД и `in_array` внутри циклов.
- Пароли — только через cast `hashed` / `Hash`, никогда не логируются и не отдаются в Resource.

## Как добавить модуль

1. `app/Modules/Order/Providers/OrderServiceProvider.php` — `final class ... extends LoadModuleProvider`
   (провайдер и миграции модуля подключатся автоматически).
2. Модель, миграция в `Database/Migrations`, фабрика `Database/Factories/OrderFactory`.
3. `OrderRepository` → Tasks → Action → Request/DTO → Controller с атрибутом маршрута → Resource.
4. Переводы в `lang/ru` и `lang/en`, OpenAPI-аннотации на контроллере, запросе и ресурсе.
5. Feature-тест на каждый контроллер, затем `make check` и `make swagger`.
