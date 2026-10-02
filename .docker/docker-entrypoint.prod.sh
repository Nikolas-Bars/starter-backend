#!/bin/sh
set -e

cd /var/www/html

# Настройки приходят из переменных окружения контейнера, поэтому кэш собирается при старте, а не при сборке
php artisan config:cache
php artisan event:cache
php artisan l5-swagger:generate || echo "Swagger: документация не сгенерирована, API работает без неё" >&2

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
