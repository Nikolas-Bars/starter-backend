#!/usr/bin/env bash
# Создаёт пользователя БД dbeaver (полный доступ к базе приложения) или меняет ему пароль.
# Пароль читается из stdin, чтобы не попасть в историю команд и список процессов:
#   ssh starter /opt/starter/starter-backend/deploy/dbeaver-user.sh < ~/.config/starter/dbeaver-password.txt
set -euo pipefail

cd "$(dirname "$0")"

read -r pw
docker compose exec -T -e PW="$pw" mariadb sh -c '
MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot <<SQL
CREATE USER IF NOT EXISTS "dbeaver"@"%" IDENTIFIED BY "$PW";
ALTER USER "dbeaver"@"%" IDENTIFIED BY "$PW";
GRANT ALL PRIVILEGES ON \`$MARIADB_DATABASE\`.* TO "dbeaver"@"%";
SQL
'
echo "Пользователь dbeaver готов"
