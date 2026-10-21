#!/usr/bin/env bash
# Обновление на сервере: свежий код обоих репозиториев → пересборка → миграции.
set -euo pipefail

cd "$(dirname "$0")"

# Мёрж во фронт и бэкенд одновременно запускает два деплоя — выполняем их по очереди
exec 9> /tmp/starter-deploy.lock
flock 9

git -C .. pull --ff-only
git -C ../../starter-frontend pull --ff-only

# Без служебных подписей сборки (provenance): с ними у каждой сборки новый ID образа, даже если
# ничего не менялось, — и compose пересоздавал бы php и web при каждом деплое
export BUILDX_NO_DEFAULT_ATTESTATIONS=1
docker compose up -d --build --wait --remove-orphans
# Caddyfile подключён в контейнер одиночным файлом: git pull заменяет файл новым, а запущенный
# контейнер видит прежний (до перезапуска). Поэтому новый конфиг передаётся Caddy напрямую —
# reload без остановки; ошибка в конфиге — reload отказывает, работает прежний
docker compose exec -T web sh -c 'cat > /tmp/Caddyfile' < Caddyfile
docker compose exec -T web caddy reload --config /tmp/Caddyfile --adapter caddyfile
docker compose exec -T php php artisan migrate --force

docker image prune -f > /dev/null
docker compose ps
