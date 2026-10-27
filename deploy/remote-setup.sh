#!/usr/bin/env bash
set -euo pipefail
APP_DIR="${APP_DIR:-$HOME/sample.beditapharma.com}"
REPO_URL="${REPO_URL:-https://github.com/justicear9/bedita-erequisition.git}"

echo "==> App dir: $APP_DIR"
mkdir -p "$APP_DIR"
cd "$APP_DIR"

if [ -d .git ]; then
  git fetch origin
  git reset --hard origin/main
else
  git clone "$REPO_URL" .
fi

composer install --no-dev --optimize-autoloader
npm ci
npm run build

if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate
fi

php artisan storage:link || true
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Done. Point document root to $APP_DIR/public"
