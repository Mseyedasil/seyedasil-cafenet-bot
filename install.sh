#!/usr/bin/env bash
set -euo pipefail

if [[ $EUID -ne 0 ]]; then echo "با sudo اجرا کنید."; exit 1; fi

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_DIR="/etc/seyedasil-bot"
CONFIG_FILE="$CONFIG_DIR/config.php"
PUBLIC_DIR="$APP_DIR/public"
STORAGE_DIR="$APP_DIR/storage"

read -rp "دامنه (مثلاً bot.example.ir): " DOMAIN
read -rp "توکن ربات تلگرام: " BOT_TOKEN
read -rp "نام کاربری مدیر [admin]: " ADMIN_USER
ADMIN_USER=${ADMIN_USER:-admin}
read -rsp "رمز مدیر پنل: " ADMIN_PASS
echo
read -rp "شماره کارت برای شارژ کیف پول: " CARD_NUMBER
read -rp "نام صاحب کارت: " CARD_OWNER

[[ -n "$DOMAIN" && -n "$BOT_TOKEN" && -n "$ADMIN_PASS" ]] || { echo "دامنه، توکن و رمز مدیر الزامی است."; exit 1; }

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y apache2 mysql-server php8.2 php8.2-cli php8.2-mysql php8.2-curl php8.2-mbstring php8.2-xml php8.2-fileinfo unzip curl

mkdir -p "$CONFIG_DIR" "$STORAGE_DIR/uploads" "$STORAGE_DIR/results" "$STORAGE_DIR/payment_proofs"
chmod 750 "$STORAGE_DIR" "$STORAGE_DIR/uploads" "$STORAGE_DIR/results" "$STORAGE_DIR/payment_proofs"

DB_NAME="seyedasil_bot"
DB_USER="seyedasil"
DB_PASS="$(php -r 'echo bin2hex(random_bytes(20));')"
APP_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
ADMIN_HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$ADMIN_PASS")"

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

mysql "$DB_NAME" < "$APP_DIR/database/schema.sql"

cat > "$CONFIG_FILE" <<PHP
<?php
declare(strict_types=1);
return [
  'app_key' => '$APP_KEY',
  'bot_token' => '$BOT_TOKEN',
  'admin_username' => '$ADMIN_USER',
  'admin_password_hash' => '$ADMIN_HASH',
  'db' => [
    'host'=>'127.0.0.1','port'=>3306,'name'=>'$DB_NAME','user'=>'$DB_USER','pass'=>'$DB_PASS','charset'=>'utf8mb4'
  ],
  'site' => ['domain'=>'$DOMAIN','base_url'=>'https://$DOMAIN'],
  'payment' => ['card_number'=>'$CARD_NUMBER','card_owner'=>'$CARD_OWNER'],
  'upload' => ['max_mb'=>20]
];
PHP
chmod 640 "$CONFIG_FILE"
chown root:www-data "$CONFIG_FILE"

chown -R www-data:www-data "$STORAGE_DIR"
find "$STORAGE_DIR" -type d -exec chmod 750 {} \;
find "$STORAGE_DIR" -type f -exec chmod 640 {} \;

cat > /etc/apache2/sites-available/seyedasil-bot.conf <<APACHE
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $PUBLIC_DIR
    <Directory $PUBLIC_DIR>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/seyedasil-bot-error.log
    CustomLog \${APACHE_LOG_DIR}/seyedasil-bot-access.log combined
</VirtualHost>
APACHE

a2enmod rewrite headers
a2ensite seyedasil-bot.conf
a2dissite 000-default.conf || true
systemctl enable apache2 mysql
systemctl restart apache2

read -rp "SSL رایگان Let's Encrypt نصب شود؟ [Y/n]: " SSL
if [[ "${SSL,,}" != "n" ]]; then
  apt-get install -y certbot python3-certbot-apache
  certbot --apache -d "$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email --redirect || true
fi

WEBHOOK_URL="https://$DOMAIN/bot.php"
curl -fsS -X POST "https://api.telegram.org/bot$BOT_TOKEN/setWebhook" \
  -d "url=$WEBHOOK_URL" \
  -d "secret_token=$APP_KEY" || true

echo
echo "=========================================="
echo "نصب کامل شد."
echo "پنل: https://$DOMAIN/admin/login.php"
echo "Webhook: $WEBHOOK_URL"
echo "=========================================="
