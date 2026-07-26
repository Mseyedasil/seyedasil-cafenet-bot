#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run: sudo bash install.sh"
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

echo "==> Updating Ubuntu..."
apt-get update

echo "==> Installing base packages..."
apt-get install -y ca-certificates curl git software-properties-common apt-transport-https lsb-release unzip apache2 mariadb-server certbot python3-certbot-apache

echo "==> Adding PHP 8.2 repository..."
add-apt-repository -y ppa:ondrej/php
apt-get update

echo "==> Installing PHP 8.2..."
apt-get install -y php8.2 php8.2-cli php8.2-common php8.2-mysql php8.2-curl php8.2-mbstring php8.2-xml php8.2-fileinfo php8.2-zip

systemctl enable --now apache2
systemctl enable --now mariadb

PHP_BIN="$(command -v php8.2 || true)"
if [[ -z "$PHP_BIN" ]]; then
  echo "ERROR: PHP 8.2 installation failed."
  exit 1
fi

update-alternatives --install /usr/bin/php php "$PHP_BIN" 82
update-alternatives --set php "$PHP_BIN"

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

mkdir -p storage
chown -R www-data:www-data storage
chmod -R 750 storage

echo
echo "=============================================="
echo "Base installation completed successfully."
echo "PHP: $(php -r 'echo PHP_VERSION;')"
echo "Project: $PROJECT_DIR"
echo "=============================================="
echo
echo "Now configure the domain, database and Telegram bot."
