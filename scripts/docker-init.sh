#!/usr/bin/env bash
set -euo pipefail

if [[ ! -f .env ]]; then
  cp .env.docker.example .env
  echo "Creado .env desde .env.docker.example — revísalo antes de continuar."
fi

docker compose -f docker-compose.yml -f docker-compose.local.yml up -d --build
docker compose -f docker-compose.yml -f docker-compose.local.yml exec -T app composer install --no-interaction --prefer-dist
docker compose -f docker-compose.yml -f docker-compose.local.yml exec -T app php artisan key:generate --force || true
docker compose -f docker-compose.yml -f docker-compose.local.yml exec -T app php artisan migrate --force || true

echo ""
echo "API: http://localhost:${APP_PORT:-8080}"
