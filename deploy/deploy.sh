#!/usr/bin/env bash
# Обновление на сервере: свежий код обоих репозиториев → пересборка → миграции.
set -euo pipefail

cd "$(dirname "$0")"

git -C .. pull --ff-only
git -C ../../starter-frontend pull --ff-only

docker compose up -d --build --wait --remove-orphans
docker compose exec -T php php artisan migrate --force

docker image prune -f > /dev/null
docker compose ps
