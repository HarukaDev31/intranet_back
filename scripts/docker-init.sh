#!/usr/bin/env bash
set -euo pipefail

if [[ ! -f .env ]]; then
  cp .env.docker.example .env
  echo "Creado .env desde .env.docker.example — revísalo antes de continuar."
fi

docker compose -f docker-compose.yml -f docker-compose.local.yml up -d --build
docker compose -f docker-compose.yml -f docker-compose.local.yml exec -T -u root app git config --global --add safe.directory /var/www/html || true
docker compose -f docker-compose.yml -f docker-compose.local.yml exec -T -u root app composer install --no-interaction --prefer-dist
docker compose -f docker-compose.yml -f docker-compose.local.yml exec -T -u root app chown -R www-data:www-data vendor storage bootstrap/cache
docker compose -f docker-compose.yml -f docker-compose.local.yml exec -T -u root app chmod -R ug+rwx storage bootstrap/cache
docker compose -f docker-compose.yml -f docker-compose.local.yml exec -T app php artisan key:generate --force || true
docker compose -f docker-compose.yml -f docker-compose.local.yml exec -T app php artisan migrate --force || true

echo ""
echo "API: http://localhost:${APP_PORT:-8080}"
