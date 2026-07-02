#!/usr/bin/env bash
# Despliegue con Docker Compose (por defecto). PHP/Composer/Artisan corren dentro del contenedor.
#
# Servidor EC2: SSH como ubuntu (clave .pem), deploy.sh re-ejecuta con sudo → root.
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

# GitHub Actions / SSH como ubuntu: re-ejecutar deploy como root (equivale a sudo su).
if [ "${DEPLOY_USE_SUDO:-auto}" != "false" ] && [ "$(id -u)" -ne 0 ] && command -v sudo >/dev/null 2>&1; then
  log "Usuario $(whoami) — re-ejecutando deploy con sudo"
  exec sudo -E env \
    DEPLOY_PATH="${DEPLOY_PATH}" \
    GIT_BRANCH="${GIT_BRANCH}" \
    DEPLOY_MODE="${DEPLOY_MODE}" \
    RUN_MIGRATIONS="${RUN_MIGRATIONS}" \
    COMPOSE_LOCAL="${COMPOSE_LOCAL}" \
    COMPOSE_HOST_MYSQL="${COMPOSE_HOST_MYSQL}" \
    COMPOSER_CLEAN="${COMPOSER_CLEAN:-false}" \
    PHP_FPM_SERVICE="${PHP_FPM_SERVICE}" \
    DEPLOY_USE_SUDO=false \
    bash "$0"
fi

if [[ ! -d .git ]]; then
  echo "ERROR: ${DEPLOY_PATH} no es un repositorio git" >&2
  exit 1
fi

# Repo suele ser de ubuntu; tras sudo somos root → Git 2.35+ bloquea "dubious ownership"
git config --global --add safe.directory "${DEPLOY_PATH}" 2>/dev/null || true

log "Fetch origin/${GIT_BRANCH}"
git fetch origin "${GIT_BRANCH}"
git reset --hard "origin/${GIT_BRANCH}"

fix_app_permissions() {
  compose exec -T -u root app sh -c '
    mkdir -p storage/framework/cache/data
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p storage/logs
    mkdir -p bootstrap/cache
    chown -R www-data:www-data vendor storage bootstrap/cache
    chmod -R ug+rwx storage bootstrap/cache
  '
}

ensure_env_secrets() {
  if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    log "Generar APP_KEY (.env lo escribe root)"
    compose exec -T -u root app php artisan key:generate --force
  fi
  if ! grep -q '^JWT_SECRET=' .env 2>/dev/null || grep -q '^JWT_SECRET=$' .env 2>/dev/null; then
    log "Generar JWT_SECRET (.env lo escribe root)"
    compose exec -T -u root app php artisan jwt:secret --force
  fi
}

if [[ "${DEPLOY_MODE}" == "docker" ]]; then
  log "Docker Compose build + up"
  compose build --pull
  compose up -d

  log "Composer install (dentro del contenedor, como root)"
  compose exec -T -u root app git config --global --add safe.directory /var/www/html || true
  if [[ "${COMPOSER_CLEAN:-false}" == "true" ]]; then
    log "COMPOSER_CLEAN=true — borrar vendor/ e instalar desde cero (upgrades Laravel)"
    compose exec -T -u root app rm -rf vendor
  fi
  compose exec -T -u root app composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
  compose exec -T -u root app composer dump-autoload --optimize --no-interaction
  ensure_env_secrets
  fix_app_permissions

  log "Limpiar config cache (evita DB_* obsoletos de un deploy anterior)"
  compose exec -T -u www-data app php artisan config:clear

  if [[ "${RUN_MIGRATIONS}" == "true" ]]; then
    compose exec -T -u www-data app php artisan migrate --force
  fi

  compose exec -T -u www-data app php artisan config:cache
  # No usar route:cache: web.php y varios módulos usan closures (rompe con 500).
  compose exec -T -u www-data app php artisan route:clear
  compose exec -T -u www-data app php artisan view:clear
  compose exec -T -u www-data app php artisan horizon:terminate || true
  fix_app_permissions
  compose restart horizon scheduler websockets || true
else
  log "Composer install (host — requiere PHP instalado en el servidor)"
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

  if [[ "${RUN_MIGRATIONS}" == "true" ]]; then
    php artisan migrate --force
  fi

  php artisan config:cache
  php artisan route:clear
  php artisan view:cache
  php artisan horizon:terminate || true

  if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload "${PHP_FPM_SERVICE}" || true
  fi
fi

log "Deploy completado (${DEPLOY_MODE}, rama ${GIT_BRANCH})"
