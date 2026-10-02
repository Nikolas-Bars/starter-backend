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

## Push-уведомления

Входящие звонки на телефон при закрытом приложении идут через Firebase Cloud Messaging. Ключ сервисного
аккаунта (Firebase → «Настройки проекта» → «Сервисные аккаунты» → «Создать закрытый ключ») — секрет,
в git его нет. Он лежит на сервере в `deploy/secrets/` и монтируется в контейнер только для чтения:

```bash
scp firebase-credentials.json starter:/opt/starter/starter-backend/deploy/secrets/
ssh starter 'chmod 600 /opt/starter/starter-backend/deploy/secrets/firebase-credentials.json && cd /opt/starter/starter-backend/deploy && docker compose restart php'
```

Без файла push выключены, а звонок собеседнику не в сети сразу завершается как «не в сети».

## Бэкапы

Каждый день в 03:30 (время сервера, UTC) `deploy/backup.sh` собирает всё, чего нет в git, — дамп БД,
`deploy/.env` и ключ Firebase — в один архив, шифрует его [age](https://age-encryption.org), кладёт
в `/var/backups/starter/` (хранятся последние 14 дней) и присылает копию в Telegram. Этого архива
достаточно, чтобы поднять всё на новом сервере (раздел «Переезд на новый сервер»).
Расписание — `/etc/cron.d/starter-backup` (ставится командой `./backup.sh --install`),
журнал — `/var/log/starter-backup.log`.

Настройки в `deploy/.env`:

- `BACKUP_AGE_RECIPIENT` — открытый ключ `age1…`. Им можно только зашифровать, поэтому даже со
  взломанного сервера старые бэкапы не прочитать.
- `BACKUP_TELEGRAM_BOT_TOKEN`, `BACKUP_TELEGRAM_CHAT_ID` — бот и чат, куда приходят копии.

Секретный ключ лежит на Маке в `~/.config/starter/backup-age-key.txt`, копия — в менеджере паролей.
На сервере его нет и быть не должно. Потерять его — потерять все бэкапы.

Восстановить с Мака (данные БД заменятся данными архива; файл — из Telegram или с сервера):

```bash
age -d -i ~/.config/starter/backup-age-key.txt ~/Downloads/starter-2026-10-03-0330.tar.gz.age \
  | ssh starter /opt/starter/starter-backend/deploy/restore.sh
```

Посмотреть, что внутри архива: `age -d -i ~/.config/starter/backup-age-key.txt файл.tar.gz.age | tar -tzv`.
Сделать бэкап вручную: `ssh starter /opt/starter/starter-backend/deploy/backup.sh`.

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

Моковых пользователей на проде нет: сидер в `APP_ENV=production` их не создаёт. Аккаунты заводятся
через регистрацию на сайте.

Пересоздать БД с нуля (все данные пропадут, сначала сделайте бэкап — раздел «Бэкапы»):

```bash
docker compose exec php php artisan migrate:fresh --force
```

## Переезд на новый сервер

Если сервер умер или переезжаем к другому хостингу. Нужны: новый VPS на Ubuntu 24.04 (от 2 ГБ памяти)
с root-паролем, последний бэкап из Telegram и секретный ключ age. Около часа.

**1. Мак: доступ по ключу.** `НОВЫЙ_IP` — адрес нового сервера.

```bash
ssh-copy-id -i ~/.ssh/starter_server root@НОВЫЙ_IP      # спросит root-пароль от хостинга, в последний раз
sed -i '' 's/HostName .*/HostName НОВЫЙ_IP/' ~/.ssh/config   # Host starter → новый адрес
ssh starter 'echo ok'
```

**2. Сервер: защита, Docker, код.** Вход по паролю выключается, ключ деплоя GitHub Actions может
выполнить только `deploy.sh`.

```bash
ssh starter
printf 'PasswordAuthentication no\nKbdInteractiveAuthentication no\n' > /etc/ssh/sshd_config.d/00-keys-only.conf
systemctl reload ssh
apt-get update && apt-get install -y git ufw age && curl -fsSL https://get.docker.com | sh
ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443 && ufw allow 3478 && ufw allow 49160:49200/udp && ufw --force enable

mkdir -p /opt/starter && cd /opt/starter
git clone https://github.com/Nikolas-Bars/starter-backend.git
git clone https://github.com/Nikolas-Bars/starter-frontend.git
exit
```

```bash
# с Мака: ключ, которым заходит GitHub Actions
echo "command=\"/opt/starter/starter-backend/deploy/deploy.sh\",no-port-forwarding,no-X11-forwarding,no-agent-forwarding,no-pty $(cat ~/.ssh/starter_github_deploy.pub)" \
  | ssh starter 'cat >> ~/.ssh/authorized_keys'
```

**3. DNS.** В Cloudflare записи `A @` и `A turn` → `НОВЫЙ_IP`, режим «DNS only» (серое облако).
Сертификат HTTPS Caddy получит сам, как только домен начнёт указывать на новый сервер.

**4. Мак: восстановить всё из бэкапа.** `restore.sh` возьмёт из архива `deploy/.env` (с новым
`PUBLIC_IP`) и ключ Firebase, поднимет контейнеры, зальёт БД и включит ежедневный бэкап.

```bash
age -d -i ~/.config/starter/backup-age-key.txt ~/Downloads/starter-2026-10-03-0330.tar.gz.age \
  | ssh starter /opt/starter/starter-backend/deploy/restore.sh
```

**5. GitHub: отпечаток нового сервера** — иначе деплой откажется к нему подключаться.

```bash
for repo in starter-backend starter-frontend; do
  ssh-keyscan -t ed25519 call-yansburg.com 2>/dev/null | gh secret set DEPLOY_KNOWN_HOSTS -R Nikolas-Bars/$repo
done
gh workflow run deploy.yml -R Nikolas-Bars/starter-backend   # проверить деплой
```

**6. Проверить:** открывается https://call-yansburg.com, вход, звонок между двумя устройствами,
`ssh starter /opt/starter/starter-backend/deploy/backup.sh` присылает бэкап в Telegram.
Мобильное приложение ходит по домену — пересобирать его не нужно.

Без бэкапа (только код): вместо шага 4 `cp .env.example .env`, заполнить его (секреты сгенерировать
заново), положить ключ Firebase (раздел «Push-уведомления»), затем
`docker compose up -d --build --wait && docker compose exec php php artisan migrate --force && ./backup.sh --install`.
Данные пользователей и переписка в этом случае пропадут.
