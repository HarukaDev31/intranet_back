#!/usr/bin/env bash
# Corrige permisos storage/ para PHP-FPM (www-data) tras composer/artisan como root.
# Uso en servidor: bash scripts/docker-fix-permissions.sh

set -euo pipefail

COMPOSE_LOCAL="${COMPOSE_LOCAL:-false}"
COMPOSE_HOST_MYSQL="${COMPOSE_HOST_MYSQL:-true}"

compose() {
  local files=(-f docker-compose.yml)
  if [[ "${COMPOSE_LOCAL}" == "true" ]]; then
    files+=(-f docker-compose.local.yml)
  elif [[ "${COMPOSE_HOST_MYSQL}" == "true" ]]; then
    files+=(-f docker-compose.host-mysql.yml)
  fi
  docker compose "${files[@]}" "$@"
}

echo "[permissions] limpiar cache y corregir ownership (www-data)"
compose exec -T -u root app sh -c '
  mkdir -p storage/framework/cache/data
  mkdir -p storage/framework/sessions
  mkdir -p storage/framework/views
  mkdir -p storage/logs
  mkdir -p bootstrap/cache
  rm -rf storage/framework/cache/data/*
  chown -R www-data:www-data vendor storage bootstrap/cache
  chmod -R ug+rwx storage bootstrap/cache
'
compose exec -T -u www-data app php artisan cache:clear || true
compose exec -T -u www-data app php artisan config:clear || true

echo "[permissions] listo"
