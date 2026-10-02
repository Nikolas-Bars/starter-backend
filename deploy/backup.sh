#!/usr/bin/env bash
# Бэкап БД: сжатый дамп в /var/backups/starter, хранятся последние KEEP_DAYS дней.
#   ./backup.sh            — сделать бэкап сейчас
#   ./backup.sh --install  — делать его каждый день в 03:30 по времени сервера (cron)
set -euo pipefail

cd "$(dirname "$0")"

BACKUP_DIR=/var/backups/starter
KEEP_DAYS=14

if [[ "${1:-}" == "--install" ]]; then
    echo "30 3 * * * root $(pwd)/backup.sh >> /var/log/starter-backup.log 2>&1" > /etc/cron.d/starter-backup
    chmod 644 /etc/cron.d/starter-backup
    echo "Ежедневный бэкап включён: /etc/cron.d/starter-backup"
    exit 0
fi

umask 077
mkdir -p "$BACKUP_DIR"

file="$BACKUP_DIR/starter-$(date +%F-%H%M).sql.gz"
trap 'rm -f "$file.tmp"' EXIT

# Пароль берётся из окружения контейнера и не попадает в аргументы команды
docker compose exec -T mariadb sh -c \
    'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb-dump -uroot --single-transaction --routines --triggers "$MARIADB_DATABASE"' \
    | gzip > "$file.tmp"
mv "$file.tmp" "$file"

find "$BACKUP_DIR" -name 'starter-*.sql.gz' -mtime +"$KEEP_DAYS" -delete
echo "$(date '+%F %T') $file $(du -h "$file" | cut -f1)"
