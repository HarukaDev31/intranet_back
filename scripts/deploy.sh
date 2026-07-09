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
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"
COMPOSE_LOCAL="${COMPOSE_LOCAL:-false}"
COMPOSE_HOST_MYSQL="${COMPOSE_HOST_MYSQL:-true}"
# auto = solo si cambió Dockerfile/compose/docker/* | true/false fuerzan
DOCKER_REBUILD="${DOCKER_REBUILD:-auto}"
# --pull solo si DOCKER_BUILD_PULL=true (lento; usar 1x/semana o tras cambio base image)
DOCKER_BUILD_PULL="${DOCKER_BUILD_PULL:-false}"
COMPOSER_INSTALL="${COMPOSER_INSTALL:-auto}"

log() {
  echo "[deploy] $*"
}

state_dir() {
  mkdir -p "${DEPLOY_PATH}/.deploy"
}

docker_context_hash() {
  {
    cat Dockerfile docker-compose.yml docker-compose.host-mysql.yml docker-compose.local.yml 2>/dev/null || true
    if [[ -d docker ]]; then
      find docker -type f -print0 2>/dev/null | sort -z | xargs -0 cat 2>/dev/null || true
    fi
  } | sha256sum | awk '{print $1}'
}

needs_docker_build() {
  case "${DOCKER_REBUILD}" in
    true|1|yes) return 0 ;;
    false|0|no) return 1 ;;
  esac
  if [[ -n "${CHANGED_FILES:-}" ]] && echo "${CHANGED_FILES}" | grep -qE '^(Dockerfile|docker-compose|docker/)'; then
    return 0
  fi
  local hash prev
  hash="$(docker_context_hash)"
  prev="$(cat "${DEPLOY_PATH}/.deploy/docker-context.sha" 2>/dev/null || echo "")"
  [[ "${hash}" != "${prev}" ]]
}

record_docker_context_hash() {
  state_dir
  docker_context_hash > "${DEPLOY_PATH}/.deploy/docker-context.sha"
}

needs_composer_install() {
  [[ "${COMPOSER_CLEAN:-false}" == "true" ]] && return 0
  case "${COMPOSER_INSTALL}" in
    true|1|yes) return 0 ;;
    false|0|no) return 1 ;;
  esac
  if [[ ! -d vendor ]] || [[ ! -f vendor/autoload.php ]]; then
    return 0
  fi
  if [[ -n "${CHANGED_FILES:-}" ]] && echo "${CHANGED_FILES}" | grep -qE '^composer\.(json|lock)$'; then
    return 0
  fi
  local cur prev
  cur="$(sha256sum composer.lock | awk '{print $1}')"
  prev="$(cat "${DEPLOY_PATH}/.deploy/composer.lock.sha" 2>/dev/null || echo "")"
  [[ "${cur}" != "${prev}" ]]
}

record_composer_lock_hash() {
  state_dir
  sha256sum composer.lock | awk '{print $1}' > "${DEPLOY_PATH}/.deploy/composer.lock.sha"
}

needs_migrate() {
  [[ "${RUN_MIGRATIONS}" != "true" ]] && return 1
  [[ -z "${CHANGED_FILES:-}" ]] && return 1
  echo "${CHANGED_FILES}" | grep -qE '^database/migrations/'
}

needs_worker_restart() {
  [[ -z "${CHANGED_FILES:-}" ]] && return 1
  echo "${CHANGED_FILES}" | grep -qE '^(app/|config/|routes/|database/migrations/|composer\.(json|lock)|docker-compose|Dockerfile|bootstrap/)'
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

compose_files_array() {
  COMPOSE_FILES=(-f docker-compose.yml)
  if [[ "${COMPOSE_LOCAL}" == "true" ]]; then
    COMPOSE_FILES+=(-f docker-compose.local.yml)
  elif [[ "${COMPOSE_HOST_MYSQL}" == "true" ]]; then
    COMPOSE_FILES+=(-f docker-compose.host-mysql.yml)
  fi
}

# Un solo stack por path: si .env cambió COMPOSE_PROJECT_NAME, baja el proyecto anterior.
down_stale_compose_projects() {
  compose_files_array
  local active legacy
  active="$(docker compose "${COMPOSE_FILES[@]}" config --format '{{.name}}' 2>/dev/null || echo "intranet_back")"

  for legacy in intranet_back intranet_prod; do
    [[ "${legacy}" == "${active}" ]] && continue
    if docker compose -p "${legacy}" "${COMPOSE_FILES[@]}" ps -q 2>/dev/null | grep -q .; then
      log "Bajando stack Docker obsoleto: ${legacy} (proyecto activo: ${active})"
      docker compose -p "${legacy}" "${COMPOSE_FILES[@]}" down || true
    fi
  done
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
    COMPOSER_INSTALL="${COMPOSER_INSTALL:-auto}" \
    DOCKER_REBUILD="${DOCKER_REBUILD:-auto}" \
    DOCKER_BUILD_PULL="${DOCKER_BUILD_PULL:-false}" \
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
git config --global --add safe.directory '*' 2>/dev/null || true

log "Fetch origin/${GIT_BRANCH}"
OLD_HEAD="$(git rev-parse HEAD)"
git -c safe.directory="${DEPLOY_PATH}" fetch origin "${GIT_BRANCH}"
git -c safe.directory="${DEPLOY_PATH}" reset --hard "origin/${GIT_BRANCH}"
NEW_HEAD="$(git rev-parse HEAD)"

if [[ "${OLD_HEAD}" != "${NEW_HEAD}" ]]; then
  CHANGED_FILES="$(git diff --name-only "${OLD_HEAD}" "${NEW_HEAD}")"
  log "Commits nuevos: ${OLD_HEAD:0:7} → ${NEW_HEAD:0:7}"
else
  CHANGED_FILES=""
  log "Sin commits nuevos (re-deploy del mismo HEAD)"
fi

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
  if needs_docker_build; then
    log "Docker build (contexto Docker cambió o DOCKER_REBUILD=true)"
    if [[ "${DOCKER_BUILD_PULL}" == "true" ]]; then
      compose build --pull
    else
      compose build
    fi
    record_docker_context_hash
  else
    log "Omitiendo docker build (sin cambios en Dockerfile/compose/docker/*)"
  fi
  down_stale_compose_projects
  compose up -d

  compose exec -T -u root app git config --global --add safe.directory /var/www/html || true

  if needs_composer_install; then
    log "Composer install (composer.lock cambió, vendor ausente o COMPOSER_CLEAN)"
    if [[ "${COMPOSER_CLEAN:-false}" == "true" ]]; then
      log "COMPOSER_CLEAN=true — borrar vendor/ e instalar desde cero (upgrades Laravel)"
      compose exec -T -u root app rm -rf vendor
    fi
    compose exec -T -u root app composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    record_composer_lock_hash
  else
    log "Omitiendo composer install (composer.lock sin cambios)"
  fi

  ensure_env_secrets
  fix_app_permissions

  log "Limpiar config cache (evita DB_* obsoletos de un deploy anterior)"
  compose exec -T -u www-data app php artisan config:clear

  if needs_migrate; then
    compose exec -T -u www-data app php artisan migrate --force
  else
    log "Omitiendo migrate (sin migraciones nuevas en este deploy)"
  fi

  compose exec -T -u www-data app php artisan config:cache
  compose exec -T -u www-data app php artisan route:clear
  compose exec -T -u www-data app php artisan view:clear

  if needs_worker_restart; then
    compose exec -T -u www-data app php artisan horizon:terminate || true
    fix_app_permissions
    compose restart horizon scheduler websockets || true
  else
    log "Omitiendo restart workers (solo cambios menores)"
    fix_app_permissions
  fi
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
