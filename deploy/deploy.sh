#!/usr/bin/env bash
# Обновление на сервере: свежий код обоих репозиториев → пересборка → миграции.
set -euo pipefail

cd "$(dirname "$0")"

# Мёрж во фронт и бэкенд одновременно запускает два деплоя — выполняем их по очереди
exec 9> /tmp/starter-deploy.lock
flock 9

git -C .. pull --ff-only
git -C ../../starter-frontend pull --ff-only

docker compose up -d --build --wait --remove-orphans
# Caddyfile подключён томом: правку в нём контейнер сам не заметит
docker compose exec -T web caddy reload --config /etc/caddy/Caddyfile
docker compose exec -T php php artisan migrate --force

docker image prune -f > /dev/null
docker compose ps
