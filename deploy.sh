#!/usr/bin/env bash
#
# Deploy InvoiceHub ke VPS produksi.
#
#   ./deploy.sh            # backend + frontend
#   ./deploy.sh backend
#   ./deploy.sh frontend
#
# Dua hal yang WAJIB dikecualikan dari rsync, keduanya pernah menjatuhkan produksi:
#   bootstrap/cache -> berisi daftar paket dev (laravel/pail) yang tidak ada di server
#   storage         -> menimpa izin folder sehingga www-data kehilangan akses tulis
set -euo pipefail

HOST="dedymadura@103.197.191.52"
API_DIR="/var/www/invoicehub-api"
WEB_DIR="/var/www/invoicehub-web"
FRONTEND_SRC="/Users/mm/INVOICEHUB"
TARGET="${1:-all}"

deploy_backend() {
  echo "==> Mengirim backend"
  rsync -az --delete \
    --exclude '.git' --exclude 'vendor' --exclude 'node_modules' --exclude '.env' \
    --exclude '.idea' --exclude '.DS_Store' --exclude '.phpunit.result.cache' \
    --exclude 'storage' --exclude 'bootstrap/cache' --exclude 'deploy.sh' \
    ./ "$HOST:$API_DIR/"

  echo "==> Menyiapkan aplikasi"
  ssh "$HOST" "bash -se" <<REMOTE
set -euo pipefail
cd "$API_DIR"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
php artisan migrate --force
php artisan package:discover --quiet
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
sudo systemctl restart invoicehub-queue
REMOTE
}

deploy_frontend() {
  echo "==> Membangun frontend (di lokal, agar RAM server tidak terbebani)"
  ( cd "$FRONTEND_SRC" && npm run build )
  echo "==> Mengirim frontend"
  rsync -az --delete "$FRONTEND_SRC/dist/" "$HOST:$WEB_DIR/"
}

case "$TARGET" in
  backend)  deploy_backend ;;
  frontend) deploy_frontend ;;
  all)      deploy_backend; deploy_frontend ;;
  *) echo "Pemakaian: ./deploy.sh [all|backend|frontend]"; exit 1 ;;
esac

echo "==> Memeriksa hasil"
curl -fsS -o /dev/null -w "  beranda: %{http_code}\n" -H "Host: invoicehub.my.id" http://103.197.191.52/
curl -fsS -o /dev/null -w "  api    : %{http_code}\n" -H "Host: invoicehub.my.id" http://103.197.191.52/api/v1/subscriptions/plans
echo "Selesai."
