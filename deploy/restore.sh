#!/usr/bin/env bash
# Восстановление из архива deploy/backup.sh — на этом же сервере или на новом, чистом (deploy/README.md,
# «Переезд на новый сервер»). Архив расшифровывается на Маке, секретный ключ на сервер не попадает:
#   age -d -i ~/.config/starter/backup-age-key.txt starter-2026-10-03-0330.tar.gz.age \
#     | ssh starter /opt/starter/starter-backend/deploy/restore.sh
# Данные БД заменяются данными из архива. deploy/.env и ключ Firebase берутся из архива, только если
# на сервере их ещё нет.
set -euo pipefail

cd "$(dirname "$0")"

umask 077
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

tar -C "$work" -xzf -
if [[ ! -f "$work/database.sql" ]]; then
    echo "В архиве нет database.sql — это не бэкап deploy/backup.sh" >&2
    exit 1
fi

if [[ ! -f .env ]]; then
    cp "$work/env" .env
    # Адрес, с которого сервер ходит в интернет, — его внешний IP (coturn сообщает его собеседникам)
    ip=$(ip -4 route get 1.1.1.1 | awk '{ for (i = 1; i < NF; i++) if ($i == "src") print $(i + 1) }')
    sed -i "s/^PUBLIC_IP=.*/PUBLIC_IP=$ip/" .env
    echo "deploy/.env восстановлен из архива, PUBLIC_IP=$ip"
fi

if [[ -f "$work/firebase-credentials.json" && ! -f secrets/firebase-credentials.json ]]; then
    # Владелец — www-data контейнера (uid 33): от него работают очередь и сервер звонков
    install -d -m 700 -o 33 -g 33 secrets
    install -m 600 -o 33 -g 33 "$work/firebase-credentials.json" secrets/
    echo "Ключ Firebase восстановлен из архива"
fi

docker compose up -d --build --wait
docker compose exec -T mariadb sh -c \
    'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb -uroot "$MARIADB_DATABASE"' \
    < "$work/database.sql"
docker compose exec -T php php artisan migrate --force
# Сервер звонков и очередь держат состояние в памяти — перезапуск после смены БД
docker compose restart php

./backup.sh --install
echo "Восстановлено. Проверьте DNS (deploy/README.md, «Переезд на новый сервер»)."
