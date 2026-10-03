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

`php` читает `deploy/.env` только при создании контейнера: после правки нужен
`docker compose up -d php` (пересоздаёт контейнер), `docker compose restart php` новых значений
не увидит. `backup.sh` и `restore.sh` читают `.env` сами при каждом запуске.

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
ssh starter 'chmod 600 /opt/starter/starter-backend/deploy/secrets/firebase-credentials.json && cd /opt/starter/starter-backend/deploy && docker compose up -d php'
```

Без файла push выключены, а звонок собеседнику не в сети сразу завершается как «не в сети».

## Бэкапы

Каждый день в 03:30 (время сервера, UTC) `deploy/backup.sh` делает две копии:

1. **База и настройки.** Всё, чего нет в git, — дамп БД, `deploy/.env` и ключ Firebase — в одном
   архиве, зашифрованном [age](https://age-encryption.org). Архив кладётся в `/var/backups/starter/`
   (хранятся последние 14 дней) и приходит в Telegram.
2. **Файлы чатов** — в Cloudflare R2, зашифрованными (раздел «Файлы чатов»). В подписи к архиву
   в Telegram видно, сколько файлов в R2, или «ОШИБКА», если копия не удалась.

Архива и R2 достаточно, чтобы поднять всё на новом сервере (раздел «Переезд на новый сервер»).
Расписание — `/etc/cron.d/starter-backup` (ставится командой `./backup.sh --install`),
журнал — `/var/log/starter-backup.log`.

Настройки в `deploy/.env`:

- `BACKUP_AGE_RECIPIENT` — открытый ключ `age1…`. Им можно только зашифровать, поэтому даже со
  взломанного сервера старые бэкапы не прочитать.
- `ADMIN_TELEGRAM_BOT_TOKEN`, `ADMIN_TELEGRAM_CHAT_ID` — бот и чат, куда приходят копии (и
  предупреждение, что место для файлов чатов кончается).

Ключи на Маке (`~/.config/starter/`, у всех права `600`), копии — в менеджере паролей:

| Файл | Зачем |
|---|---|
| `backup-age-key.txt` | Секретный ключ age: без него архивы не расшифровать. На сервере его нет и быть не должно. Потерять его — потерять все бэкапы |
| `attachments-crypt.env` | Пароль шифрования файлов в R2. Есть и в `deploy/.env` (а значит, в архиве) |
| `r2.env` | Ключ доступа к бакету R2. Тоже есть в `deploy/.env` |
| `dbeaver-password.txt` | Пароль пользователя `dbeaver` (раздел «База из DBeaver») |

Восстановить с Мака (данные БД заменятся данными архива; файл — из Telegram или с сервера):

```bash
age -d -i ~/.config/starter/backup-age-key.txt ~/Downloads/starter-2026-10-03-0330.tar.gz.age \
  | ssh starter /opt/starter/starter-backend/deploy/restore.sh
```

Посмотреть, что внутри архива: `age -d -i ~/.config/starter/backup-age-key.txt файл.tar.gz.age | tar -tzv`.
Сделать бэкап вручную: `ssh starter /opt/starter/starter-backend/deploy/backup.sh`.
Сверить копию файлов в R2 с диском (ничего не меняет, в конце — `0 differences found`):
`ssh starter /opt/starter/starter-backend/deploy/backup.sh --check`.

## Файлы чатов

Фото, видео, голосовые и файлы из чатов лежат в томе `starter_attachments` (на хосте —
`docker volume inspect -f '{{ .Mountpoint }}' starter_attachments`). Один файл — до 50 МБ
(`ATTACHMENTS_MAX_FILE_BYTES`), всего — не больше 10 ГБ (`ATTACHMENTS_QUOTA_BYTES`): при 90% в
Telegram приходит предупреждение, при 100% новые файлы не принимаются. Фото, видео и голосовые
сжимает очередь `media` (процесс `media` в supervisor, ffmpeg и Imagick в образе): фото — до 2560 px
без EXIF и координат, видео — H.264 до 720p, голосовые — AAC 64 кбит/с. Отдаёт файлы Caddy по
подписанным ссылкам `/api/files/…`, подпись проверяет PHP (`/api/attachments/authorize`).

```bash
docker compose exec php php artisan attachments:usage                     # сколько занято
docker compose exec php php artisan attachments:prune --before=2026-01-01 # удалить файлы старше даты
```

Сообщения при этом остаются, пропадают только вложения. Не отправленные за сутки файлы удаляются
сами (раз в час).

Копия — в Cloudflare R2 (аккаунт `nikolasparaslovgp@gmail.com`, бесплатный тариф до 10 ГБ),
бакет `call-yansburg-files`: тот же `backup.sh` каждую ночь синхронизирует том с бакетом через
rclone. Файлы и их имена шифруются на сервере (`rclone crypt`) — в Cloudflare видна только
зашифрованная каша. Удалённые с сервера файлы ещё 30 дней лежат в корзине бакета (`trash/`), так
что случайно стёртый том не уничтожит копию следующей ночью. `restore.sh` на новом сервере
скачивает файлы обратно.

Настройки в `deploy/.env`:

- `R2_ACCOUNT_ID`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET` — ключ R2 (Cloudflare →
  Storage & databases → R2 Object Storage → API Tokens → Manage → Create Account API token,
  права Object Read & Write только на этот бакет). Копия — `~/.config/starter/r2.env` на Маке.
- `ATTACHMENTS_CRYPT_PASSWORD`, `ATTACHMENTS_CRYPT_SALT` — пароль шифрования. Сам `.env` лежит в
  зашифрованном бэкапе, поэтому пароль восстановится вместе с ним; копия — в
  `~/.config/starter/attachments-crypt.env` на Маке. Поменять пароль — значит перезалить всё
  заново: старая копия новым паролем не читается.

Без этих ключей `backup.sh` копирует только базу и пишет в подписи «файлы чатов в R2 не
копируются». Включить на сервере (ключи уходят через stdin, в истории команд их нет):

```bash
cat ~/.config/starter/r2.env ~/.config/starter/attachments-crypt.env \
  | ssh starter 'cd /opt/starter/starter-backend/deploy && cat >> .env && ./backup.sh --install && ./backup.sh'
```

## База из DBeaver

MariaDB слушает только `127.0.0.1:3306` на сервере, снаружи до неё не достучаться — подключение
идёт через SSH-туннель.

- Main: Host `127.0.0.1`, Port `3306`, Database `starter`, Username `dbeaver`, пароль — в
  `~/.config/starter/dbeaver-password.txt` на Маке (`pbcopy < ~/.config/starter/dbeaver-password.txt`).
- SSH: Host `call-yansburg.com`, Port `22`, User `root`, Authentication «Public Key»,
  ключ `~/.ssh/starter_server`.

У пользователя `dbeaver` полный доступ к базе `starter`: правки сразу видны на проде. Он живёт в
системной базе MariaDB и в бэкап не попадает — после переезда создать заново (этой же командой
меняется пароль):

```bash
ssh starter /opt/starter/starter-backend/deploy/dbeaver-user.sh < ~/.config/starter/dbeaver-password.txt
```

## Частые команды

```bash
ssh starter
cd /opt/starter/starter-backend/deploy

docker compose ps                         # что запущено
docker compose logs -f php                # логи API и сервера звонков
docker compose logs -f web                # логи Caddy (в том числе выпуск сертификата)
docker compose exec php php artisan tinker
docker compose up -d php                  # после правки deploy/.env (restart её не перечитывает)
docker compose restart php                # просто перезапустить API, сервер звонков и очереди
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
в чатах открываются старые фото, `ssh starter /opt/starter/starter-backend/deploy/backup.sh --check`
находит 0 расхождений, а `ssh starter /opt/starter/starter-backend/deploy/backup.sh` присылает бэкап
в Telegram.
Мобильное приложение ходит по домену — пересобирать его не нужно.

Без бэкапа (только код): вместо шага 4 `cp .env.example .env`, заполнить его (секреты сгенерировать
заново), положить ключ Firebase (раздел «Push-уведомления»), затем
`docker compose up -d --build --wait && docker compose exec php php artisan migrate --force && ./backup.sh --install`.
Данные пользователей и переписка в этом случае пропадут.
