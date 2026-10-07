#!/bin/bash
set -euo pipefail
cd /var/www/absensi

echo "==> maintenance mode"
php artisan down --retry=15 || true
trap 'php artisan up || true' EXIT

echo "==> pulling"
git pull origin main

echo "==> composer"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> assets"
npm ci
npm run build
rm -f public/hot

echo "==> migrations"
php artisan migrate --force

echo "==> caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> permissions"
sudo chown -R dev:www-data /var/www/absensi
sudo chmod -R 775 storage bootstrap/cache

echo "==> reload php"
sudo systemctl reload php8.4-fpm

php artisan up
trap - EXIT
echo "==> done"