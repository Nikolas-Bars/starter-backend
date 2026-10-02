#!/usr/bin/env bash
# Бэкап БД: сжатый и зашифрованный (age) дамп в /var/backups/starter, хранятся последние KEEP_DAYS дней.
# Копия уходит в Telegram, если в deploy/.env заданы BACKUP_TELEGRAM_BOT_TOKEN и BACKUP_TELEGRAM_CHAT_ID.
#   ./backup.sh            — сделать бэкап сейчас
#   ./backup.sh --install  — поставить age и делать бэкап каждый день в 03:30 по времени сервера (cron)
set -euo pipefail

cd "$(dirname "$0")"

BACKUP_DIR=/var/backups/starter
KEEP_DAYS=14

if [[ "${1:-}" == "--install" ]]; then
    command -v age > /dev/null || apt-get install -y age
    echo "30 3 * * * root $(pwd)/backup.sh >> /var/log/starter-backup.log 2>&1" > /etc/cron.d/starter-backup
    chmod 644 /etc/cron.d/starter-backup
    echo "Ежедневный бэкап включён: /etc/cron.d/starter-backup"
    exit 0
fi

# deploy/.env — это .env Laravel, а не bash-скрипт: значения читаем по одному, не через source
env_value() {
    grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/' || true
}

recipient=$(env_value BACKUP_AGE_RECIPIENT)
if [[ -z "$recipient" ]]; then
    echo "Задайте BACKUP_AGE_RECIPIENT в deploy/.env (открытый ключ age, deploy/README.md, «Бэкапы»)" >&2
    exit 1
fi

umask 077
mkdir -p "$BACKUP_DIR"

file="$BACKUP_DIR/starter-$(date +%F-%H%M).sql.gz.age"
trap 'rm -f "$file.tmp"' EXIT

# Пароль берётся из окружения контейнера и не попадает в аргументы команды
docker compose exec -T mariadb sh -c \
    'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb-dump -uroot --single-transaction --routines --triggers "$MARIADB_DATABASE"' \
    | gzip | age -r "$recipient" > "$file.tmp"
mv "$file.tmp" "$file"

find "$BACKUP_DIR" -name 'starter-*.sql.gz*' -mtime +"$KEEP_DAYS" -delete
echo "$(date '+%F %T') $file $(du -h "$file" | cut -f1)"

token=$(env_value BACKUP_TELEGRAM_BOT_TOKEN)
chat_id=$(env_value BACKUP_TELEGRAM_CHAT_ID)
if [[ -n "$token" && -n "$chat_id" ]]; then
    # Адрес с токеном передаётся через stdin, чтобы токен не был виден в списке процессов.
    # Telegram принимает от бота файлы до 50 МБ
    curl -fsS -o /dev/null -K - \
        -F "chat_id=$chat_id" \
        -F "document=@$file" \
        -F "caption=Бэкап call-yansburg.com $(date '+%F %H:%M') UTC" \
        <<< "url = \"https://api.telegram.org/bot$token/sendDocument\""
    echo "$(date '+%F %T') отправлен в Telegram"
fi
