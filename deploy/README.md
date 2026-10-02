# Продакшн: https://call-yansburg.com

Сервер: VPS KVM-SSD-3-FIN VL (firstbyte), Ubuntu 24.04, IP `185.17.1.109`.
Вход только по SSH-ключу (пароль для SSH отключён), на Маке настроен короткий адрес:

```bash
ssh starter
```

## Что где работает

Всё запущено в Docker из `/opt/starter/starter-backend/deploy`:

| Сервис | Что делает |
|---|---|
| `web` | Caddy: HTTPS (сертификат Let's Encrypt получает и продлевает сам), раздаёт фронтенд, проксирует `/api`, `/docs` и `/ws` |
| `php` | RoadRunner (API), WebSocket-сервер звонков, очередь и планировщик — образ `.docker/Dockerfile_prod` |
| `mariadb`, `redis` | БД и кэш; наружу не открыты |
| `coturn` | TURN-сервер для звонков из сетей со строгим NAT: `turn.call-yansburg.com:3478` |

Код: `/opt/starter/starter-backend` и `/opt/starter/starter-frontend` — клоны репозиториев с GitHub.
Настройки и секреты: `/opt/starter/starter-backend/deploy/.env` (в git не попадает, шаблон — `.env.example`).

Открытые порты (ufw): 22, 80, 443 (tcp/udp), 3478 (tcp/udp), 49160–49200/udp (медиа через TURN).

Логины TURN временные: coturn проверяет их по общему секрету `CALL_TURN_SECRET`, а API выдаёт
каждому пользователю свой логин на `CALL_TURN_TTL` секунд. Без `CALL_TURN_SECRET` в `deploy/.env`
`docker compose` откажется запускаться. Переход со старых постоянных логинов:

```bash
ssh starter
cd /opt/starter/starter-backend/deploy
echo "CALL_TURN_SECRET=$(openssl rand -hex 32)" >> .env   # CALL_TURN_USERNAME/CREDENTIAL больше не нужны
```

Сделать это нужно до мёржа изменений: деплой пересоздаст coturn уже с новым секретом.

## Как вносить изменения

Прямой пуш в `main` закрыт: изменения попадают туда только через Pull Request с зелёными проверками.

```bash
git switch main && git pull
git switch -c feature/название      # ветка под задачу
# ...правки, make check...
git push -u origin feature/название
gh pr create --web                  # открыть Pull Request на GitHub
```

- На каждый Pull Request GitHub Actions запускает проверки (`.github/workflows/ci.yml`): в бэкенде
  php-cs-fixer, Rector, PHPStan и PHPUnit, во фронте oxlint, ESLint, Prettier, vue-tsc, Vitest и сборку.
- После мёржа в `main` срабатывает `.github/workflows/deploy.yml` и выкатывает изменения на сервер.
  Ход деплоя виден во вкладке Actions репозитория.
- Если задача затрагивает фронт и бэкенд, ветки в обоих репозиториях и два Pull Request; мёржить вместе.
  Деплой общий: скрипт подтягивает `main` обоих репозиториев, одновременные запуски идут по очереди.

## Деплой

`deploy/deploy.sh` подтягивает код, пересобирает образы, перезапускает контейнеры и применяет миграции.
GitHub Actions заходит на сервер отдельным ключом (секреты `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`),
которому в `~/.ssh/authorized_keys` разрешена только эта команда. Вручную:

```bash
ssh starter /opt/starter/starter-backend/deploy/deploy.sh
```

Или кнопкой Run workflow во вкладке Actions → Deploy.

## Частые команды

```bash
ssh starter
cd /opt/starter/starter-backend/deploy

docker compose ps                         # что запущено
docker compose logs -f php                # логи API и сервера звонков
docker compose logs -f web                # логи Caddy (в том числе выпуск сертификата)
docker compose exec php php artisan tinker
docker compose restart php                # после правки deploy/.env
```

Тестовые пользователи (`admin@`, `ivan@`, `maria@example.com`, пароль `Password123`) созданы сидером.
Пересоздать БД с нуля (все данные пропадут):

```bash
docker compose exec php php artisan migrate:fresh --seed --force
```

## Первая установка на чистый сервер

```bash
apt-get update && apt-get install -y git ufw && curl -fsSL https://get.docker.com | sh
ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443 && ufw allow 3478 && ufw allow 49160:49200/udp && ufw --force enable

mkdir -p /opt/starter && cd /opt/starter
git clone https://github.com/Nikolas-Bars/starter-backend.git
git clone https://github.com/Nikolas-Bars/starter-frontend.git

cd starter-backend/deploy
cp .env.example .env    # заполнить ACME_EMAIL, APP_KEY и пароли
docker compose up -d --build --wait
docker compose exec php php artisan migrate --seed --force
```

DNS (Cloudflare): записи `A @` и `A turn` → IP сервера в режиме «DNS only» (серое облако).
