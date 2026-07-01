#!/usr/bin/env bash
# Despliegue con Docker Compose (por defecto). PHP/Composer/Artisan corren dentro del contenedor.
#
# Servidor QA:
#   DEPLOY_PATH=/var/www/html/intranet_back_qa GIT_BRANCH=qa bash scripts/deploy.sh
#
# Modo legacy (sin Docker): DEPLOY_MODE=classic bash scripts/deploy.sh

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:?Define DEPLOY_PATH}"
GIT_BRANCH="${GIT_BRANCH:-main}"
DEPLOY_MODE="${DEPLOY_MODE:-docker}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.2-fpm}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"
COMPOSE_LOCAL="${COMPOSE_LOCAL:-false}"
COMPOSE_HOST_MYSQL="${COMPOSE_HOST_MYSQL:-true}"

log() {
  echo "[deploy] $*"
}

compose() {
  local files=(-f docker-compose.yml)
  if [[ "${COMPOSE_LOCAL}" == "true" ]]; then
    files+=(-f docker-compose.local.yml)
  elif [[ "${COMPOSE_HOST_MYSQL}" == "true" ]]; then
    files+=(-f docker-compose.host-mysql.yml)
  fi
  docker compose "${files[@]}" "$@"
}

cd "${DEPLOY_PATH}"

if [[ ! -d .git ]]; then
  echo "ERROR: ${DEPLOY_PATH} no es un repositorio git" >&2
  exit 1
fi

log "Fetch origin/${GIT_BRANCH}"
git fetch origin "${GIT_BRANCH}"
git reset --hard "origin/${GIT_BRANCH}"

fix_app_permissions() {
  compose exec -T -u root app chown -R www-data:www-data \
    /var/www/html/vendor \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache
  compose exec -T -u root app chmod -R ug+rwx \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache
}

if [[ "${DEPLOY_MODE}" == "docker" ]]; then
  log "Docker Compose build + up"
  compose build --pull
  compose up -d

  log "Composer install (dentro del contenedor, como root)"
  compose exec -T -u root app git config --global --add safe.directory /var/www/html || true
  compose exec -T -u root app composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
  fix_app_permissions

  if [[ "${RUN_MIGRATIONS}" == "true" ]]; then
    compose exec -T app php artisan migrate --force
  fi

  compose exec -T app php artisan config:cache
  compose exec -T app php artisan route:cache
  compose exec -T app php artisan view:cache
  compose exec -T app php artisan horizon:terminate || true
  compose restart horizon scheduler || true
else
  log "Composer install (host — requiere PHP instalado en el servidor)"
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

  if [[ "${RUN_MIGRATIONS}" == "true" ]]; then
    php artisan migrate --force
  fi

  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan horizon:terminate || true

  if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload "${PHP_FPM_SERVICE}" || true
  fi
fi

log "Deploy completado (${DEPLOY_MODE}, rama ${GIT_BRANCH})"
