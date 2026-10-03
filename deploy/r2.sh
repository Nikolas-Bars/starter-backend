# Подключается из backup.sh и restore.sh: копия файлов чатов в Cloudflare R2 через rclone.
# Файлы шифруются на сервере (rclone crypt) — в R2 лежат только зашифрованные данные и имена.
# Ключи — в deploy/.env (deploy/README.md, «Файлы чатов в R2»), а сам .env — в зашифрованном бэкапе,
# поэтому на новом сервере файлы достаются тем же паролем.
# Нужна функция env_value из вызывающего скрипта.

R2_CURRENT=files:current
R2_TRASH=files:trash
R2_TRASH_DAYS=30

r2_enabled() {
    [[ -n "$(env_value R2_ACCESS_KEY_ID)" && -n "$(env_value ATTACHMENTS_CRYPT_PASSWORD)" ]]
}

# Конфиг rclone целиком в переменных окружения: файла с ключами на диске нет
r2_configure() {
    command -v rclone > /dev/null || apt-get install -y rclone
    export RCLONE_CONFIG_R2_TYPE=s3
    export RCLONE_CONFIG_R2_PROVIDER=Cloudflare
    export RCLONE_CONFIG_R2_ENDPOINT="https://$(env_value R2_ACCOUNT_ID).r2.cloudflarestorage.com"
    export RCLONE_CONFIG_R2_ACCESS_KEY_ID="$(env_value R2_ACCESS_KEY_ID)"
    export RCLONE_CONFIG_R2_SECRET_ACCESS_KEY="$(env_value R2_SECRET_ACCESS_KEY)"
    # Ключ ограничен одним бакетом и не может проверять список бакетов
    export RCLONE_CONFIG_R2_NO_CHECK_BUCKET=true
    export RCLONE_CONFIG_FILES_TYPE=crypt
    export RCLONE_CONFIG_FILES_REMOTE="r2:$(env_value R2_BUCKET)"
    RCLONE_CONFIG_FILES_PASSWORD="$(rclone obscure "$(env_value ATTACHMENTS_CRYPT_PASSWORD)")"
    RCLONE_CONFIG_FILES_PASSWORD2="$(rclone obscure "$(env_value ATTACHMENTS_CRYPT_SALT)")"
    export RCLONE_CONFIG_FILES_PASSWORD RCLONE_CONFIG_FILES_PASSWORD2
}

# Каталог тома attachments на хосте (проект docker compose называется starter)
attachments_dir() {
    docker volume inspect -f '{{ .Mountpoint }}' starter_attachments
}
