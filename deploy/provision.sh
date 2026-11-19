#!/usr/bin/env bash
#
# One-time setup of a fresh Ubuntu VPS (22.04, 24.04 or 26.04) for YokanCha:
# Nginx + PHP 8.4+ FPM + MySQL 8 + Composer + Certbot, a firewall, fail2ban,
# a swap file, a `deploy` user for GitHub Actions and the app directory.
#
# Run on the VPS as a sudo user:
#   sudo bash provision.sh
#
# Safe to run again: steps that are already done are skipped.
set -euo pipefail

APP_DIR=/var/www/yokancha
DEPLOY_USER=deploy
DB_NAME=yokancha
DB_USER=yokancha
TIMEZONE=Asia/Jakarta
# PHP version is picked below: the newest 8.4+ the system offers.

if [[ $EUID -ne 0 ]]; then
    echo "Jalankan dengan sudo: sudo bash provision.sh" >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive
step() { echo; echo "==> $*"; }

CODENAME="$(. /etc/os-release && echo "${VERSION_CODENAME}")"

# The ondrej/php PPA only serves older Ubuntu releases (jammy, noble). A copy
# left on newer releases (e.g. from an earlier run) breaks every apt update,
# so it is removed before anything else.
if [[ "$CODENAME" != "jammy" && "$CODENAME" != "noble" ]]; then
    rm -f /etc/apt/sources.list.d/ondrej-ubuntu-php*
fi

step "Zona waktu ${TIMEZONE} dan update sistem (Ubuntu ${CODENAME})"
timedatectl set-timezone "$TIMEZONE"
apt-get update -y
apt-get upgrade -y
apt-get install -y software-properties-common ca-certificates curl git unzip ufw fail2ban

step "Swap 2 GB (cadangan memori untuk composer)"
if ! swapon --show | grep -q /swapfile; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

step "Firewall: hanya SSH, HTTP, HTTPS"
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
systemctl enable --now fail2ban

step "PHP 8.4 atau lebih baru"
# Newer Ubuntu (26.04 "resolute") ships PHP 8.4+ itself; the ondrej PPA is
# only added on older releases that need it.
pick_php() {
    for v in 8.5 8.4; do
        if apt-cache show "php${v}-fpm" >/dev/null 2>&1; then echo "$v"; return; fi
    done
}
PHP="$(pick_php)"
if [[ -z "$PHP" && ( "$CODENAME" == "jammy" || "$CODENAME" == "noble" ) ]]; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
    PHP="$(pick_php)"
fi
if [[ -z "$PHP" ]]; then
    echo "PHP 8.4+ tidak tersedia untuk Ubuntu ${CODENAME}." >&2
    exit 1
fi
echo "Memakai PHP ${PHP}"
apt-get install -y \
    "php${PHP}-fpm" "php${PHP}-cli" "php${PHP}-mysql" "php${PHP}-mbstring" "php${PHP}-xml" \
    "php${PHP}-curl" "php${PHP}-zip" "php${PHP}-bcmath" "php${PHP}-intl" "php${PHP}-gd"
sed -i "s|^;\?date.timezone =.*|date.timezone = ${TIMEZONE}|" "/etc/php/${PHP}/fpm/php.ini" "/etc/php/${PHP}/cli/php.ini"
sed -i 's|^upload_max_filesize =.*|upload_max_filesize = 10M|; s|^post_max_size =.*|post_max_size = 12M|' "/etc/php/${PHP}/fpm/php.ini"
systemctl enable --now "php${PHP}-fpm"

step "Composer"
if ! command -v composer >/dev/null; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm /tmp/composer-setup.php
fi

step "Nginx dan Certbot"
# The stock "default" site listens on [::]:80, which fails on VPSes with IPv6
# turned off; it's replaced by the YokanCha site below anyway.
apt-get install -y nginx certbot python3-certbot-nginx || true
rm -f /etc/nginx/sites-enabled/default

step "MySQL 8, database ${DB_NAME} dan user ${DB_USER}"
apt-get install -y mysql-server
systemctl enable --now mysql
CREDENTIALS_FILE="/root/yokancha-db.txt"
if [[ ! -f "$CREDENTIALS_FILE" ]]; then
    DB_PASSWORD="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    printf 'DB_DATABASE=%s\nDB_USERNAME=%s\nDB_PASSWORD=%s\n' "$DB_NAME" "$DB_USER" "$DB_PASSWORD" > "$CREDENTIALS_FILE"
    chmod 600 "$CREDENTIALS_FILE"
fi

step "User ${DEPLOY_USER} untuk GitHub Actions"
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" "$DEPLOY_USER"
fi
usermod -aG www-data "$DEPLOY_USER"
install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/${DEPLOY_USER}/.ssh"
touch "/home/${DEPLOY_USER}/.ssh/authorized_keys"
chown "$DEPLOY_USER:$DEPLOY_USER" "/home/${DEPLOY_USER}/.ssh/authorized_keys"
chmod 600 "/home/${DEPLOY_USER}/.ssh/authorized_keys"
# deploy may reload PHP-FPM after a release, nothing else as root.
echo "${DEPLOY_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl reload php${PHP}-fpm" > "/etc/sudoers.d/${DEPLOY_USER}"
chmod 440 "/etc/sudoers.d/${DEPLOY_USER}"

step "Folder aplikasi ${APP_DIR}"
install -d -m 2775 -o "$DEPLOY_USER" -g www-data "$APP_DIR"

step "Nginx site untuk YokanCha"
# Listen on IPv6 only when the VPS has it.
LISTEN_V6=""
if [[ -f /proc/net/if_inet6 ]]; then
    LISTEN_V6="listen [::]:80;"
fi
cat > /etc/nginx/sites-available/yokancha <<NGINX
server {
    listen 80;
    ${LISTEN_V6}
    server_name _;

    root ${APP_DIR}/public;
    index index.php;
    charset utf-8;
    client_max_body_size 12M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~* \.(?:css|js|webp|png|jpe?g|svg|woff2?)$ {
        expires 30d;
        access_log off;
        try_files \$uri =404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php${PHP}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/yokancha /etc/nginx/sites-enabled/yokancha
rm -f /etc/nginx/sites-enabled/default
nginx -t
# Finish the nginx package setup if its first start failed, then (re)start.
dpkg --configure -a
systemctl enable nginx
systemctl restart nginx

echo
echo "============================================================"
echo " Selesai. Versi terpasang:"
php -v | head -1
mysql --version
nginx -v 2>&1
composer --version 2>/dev/null | head -1
echo
echo " Data login database (simpan, dipakai di .env):"
cat "$CREDENTIALS_FILE"
echo "============================================================"
