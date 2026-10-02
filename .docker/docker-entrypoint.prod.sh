#!/bin/sh
set -e

cd /var/www/html

# Настройки приходят из переменных окружения контейнера, поэтому кэш собирается при старте, а не при сборке
php artisan config:cache
php artisan event:cache
php artisan l5-swagger:generate

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
