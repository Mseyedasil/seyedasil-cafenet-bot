#!/usr/bin/env bash
set -euo pipefail
if [[ $EUID -ne 0 ]]; then echo "با sudo اجرا کنید."; exit 1; fi
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f /etc/seyedasil-bot/config.php ]]; then
  echo "Config خارج از GitHub نگهداری می‌شود."
fi
mysql_cmd="$(command -v mysql || true)"
if [[ -z "$mysql_cmd" ]]; then echo "MySQL پیدا نشد."; exit 1; fi
source /dev/null 2>/dev/null || true
mysql_root_db="$(grep -oP "'name'=>'\K[^']+" /etc/seyedasil-bot/config.php | head -1 || true)"
if [[ -n "$mysql_root_db" ]]; then
  mysql "$mysql_root_db" < "$APP_DIR/database/schema.sql"
fi
chown -R www-data:www-data "$APP_DIR/storage" 2>/dev/null || true
systemctl reload apache2
echo "Update complete."
