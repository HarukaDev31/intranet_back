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

echo "[permissions] chown www-data en storage, bootstrap/cache y vendor"
compose exec -T -u root app chown -R www-data:www-data \
  /var/www/html/vendor \
  /var/www/html/storage \
  /var/www/html/bootstrap/cache

compose exec -T -u root app chmod -R ug+rwx \
  /var/www/html/storage \
  /var/www/html/bootstrap/cache

echo "[permissions] listo"
