#!/usr/bin/env bash
# Бэкап всего, что нельзя взять из git: дамп БД, deploy/.env и ключ Firebase в одном архиве,
# зашифрованном age. Кладётся в /var/backups/starter (хранятся последние KEEP_DAYS дней) и уходит
# в Telegram, если в deploy/.env заданы ADMIN_TELEGRAM_BOT_TOKEN и ADMIN_TELEGRAM_CHAT_ID.
# Файлы чатов в архив не входят (до 10 ГБ): они зашифрованными копируются в Cloudflare R2 (r2.sh).
# Восстановление, в том числе на новом сервере, — deploy/restore.sh.
#   ./backup.sh            — сделать бэкап сейчас
#   ./backup.sh --install  — поставить age и rclone и делать бэкап каждый день в 03:30 по времени сервера (cron)
#   ./backup.sh --check    — сверить копию файлов чатов в R2 с диском, ничего не меняя
set -euo pipefail

cd "$(dirname "$0")"

BACKUP_DIR=/var/backups/starter
KEEP_DAYS=14

if [[ "${1:-}" == "--install" ]]; then
    command -v age > /dev/null || apt-get install -y age
    command -v rclone > /dev/null || apt-get install -y rclone
    echo "30 3 * * * root $(pwd)/backup.sh >> /var/log/starter-backup.log 2>&1" > /etc/cron.d/starter-backup
    chmod 644 /etc/cron.d/starter-backup
    echo "Ежедневный бэкап включён: /etc/cron.d/starter-backup"
    exit 0
fi

# deploy/.env — это .env Laravel, а не bash-скрипт: значения читаем по одному, не через source
env_value() {
    grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/' || true
}

# shellcheck source=r2.sh
source ./r2.sh

if [[ "${1:-}" == "--check" ]]; then
    if ! r2_enabled; then
        echo "Ключей R2 в deploy/.env нет (deploy/README.md, «Файлы чатов»)" >&2
        exit 1
    fi
    r2_configure
    exec rclone cryptcheck "$(attachments_dir)" "$R2_CURRENT"
fi

recipient=$(env_value BACKUP_AGE_RECIPIENT)
if [[ -z "$recipient" ]]; then
    echo "Задайте BACKUP_AGE_RECIPIENT в deploy/.env (открытый ключ age, deploy/README.md, «Бэкапы»)" >&2
    exit 1
fi

umask 077
mkdir -p "$BACKUP_DIR"

file="$BACKUP_DIR/starter-$(date +%F-%H%M).tar.gz.age"
work=$(mktemp -d)
trap 'rm -rf "$work" "$file.tmp"' EXIT

# Пароль берётся из окружения контейнера и не попадает в аргументы команды
docker compose exec -T mariadb sh -c \
    'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb-dump -uroot --single-transaction --routines --triggers "$MARIADB_DATABASE"' \
    > "$work/database.sql"
cp .env "$work/env"
if [[ -f secrets/firebase-credentials.json ]]; then
    cp secrets/firebase-credentials.json "$work/"
fi

tar -C "$work" -czf - . | age -r "$recipient" > "$file.tmp"
mv "$file.tmp" "$file"

find "$BACKUP_DIR" -name 'starter-*.age' -mtime +"$KEEP_DAYS" -delete
echo "$(date '+%F %T') $file $(du -h "$file" | cut -f1)"

# Файлы чатов: R2 повторяет том attachments. Удалённые с сервера файлы ещё R2_TRASH_DAYS дней
# лежат в корзине — случайно стёртый том не уничтожит копию при следующем бэкапе
files_status="файлы чатов в R2 не копируются (нет ключей R2 в deploy/.env)"
if r2_enabled; then
    r2_configure
    today=$(date +%F)
    if rclone sync "$(attachments_dir)" "$R2_CURRENT" --backup-dir "$R2_TRASH/$today" --transfers 4 --stats-one-line --stats 0 -q; then
        old=$(date -d "-$R2_TRASH_DAYS days" +%F)
        for day in $(rclone lsf "$R2_TRASH" --dirs-only 2> /dev/null | tr -d /); do
            if [[ "$day" < "$old" ]]; then
                rclone purge "$R2_TRASH/$day" -q
            fi
        done
        files_status="файлы чатов в R2: $(rclone size "$R2_CURRENT" --json | grep -o '"bytes":[0-9]*' | cut -d: -f2 | numfmt --to=iec) скопировано"
    else
        files_status="ОШИБКА: файлы чатов в R2 не скопировались, см. /var/log/starter-backup.log"
    fi
    echo "$(date '+%F %T') $files_status"
fi

# Старые имена BACKUP_TELEGRAM_* — пока на сервере не обновлён deploy/.env
token=$(env_value ADMIN_TELEGRAM_BOT_TOKEN)
token=${token:-$(env_value BACKUP_TELEGRAM_BOT_TOKEN)}
chat_id=$(env_value ADMIN_TELEGRAM_CHAT_ID)
chat_id=${chat_id:-$(env_value BACKUP_TELEGRAM_CHAT_ID)}
if [[ -n "$token" && -n "$chat_id" ]]; then
    # Адрес с токеном передаётся через stdin, чтобы токен не был виден в списке процессов.
    # Telegram принимает от бота файлы до 50 МБ
    curl -fsS -o /dev/null -K - \
        -F "chat_id=$chat_id" \
        -F "document=@$file" \
        -F "caption=Бэкап call-yansburg.com $(date '+%F %H:%M') UTC, $files_status" \
        <<< "url = \"https://api.telegram.org/bot$token/sendDocument\""
    echo "$(date '+%F %T') отправлен в Telegram"
fi
