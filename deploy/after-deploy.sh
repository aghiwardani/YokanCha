#!/usr/bin/env bash
#
# Runs on the VPS (as the deploy user) right after GitHub Actions has uploaded
# a release to /var/www/yokancha: prepares storage, migrates the database,
# rebuilds Laravel's caches and reloads PHP-FPM.
set -euo pipefail

APP_DIR=/var/www/yokancha
cd "$APP_DIR"

if [[ ! -f .env ]]; then
    echo "File .env belum ada di ${APP_DIR}. Buat dulu (lihat deploy/README.md)." >&2
    exit 1
fi

# storage/ is not uploaded, so its folders are created here the first time.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chgrp -R www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# First deploy only: give the app its encryption key.
if ! grep -qE '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Pick up the new code (opcache).
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
sudo /usr/bin/systemctl reload "php${PHP_VERSION}-fpm"

echo "Deploy selesai: $(date '+%Y-%m-%d %H:%M:%S')"
