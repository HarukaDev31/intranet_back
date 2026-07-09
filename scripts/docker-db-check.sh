#!/usr/bin/env bash
# Diagnóstico MySQL desde contenedor app (servidor con MySQL en host + socket).
# Uso: bash scripts/docker-db-check.sh

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

echo "=== .env (DB_*) en host ==="
grep -E '^DB_|^DATABASE_URL=' .env 2>/dev/null || echo "(sin .env o sin vars DB_)"

echo ""
echo "=== Socket montado en contenedor ==="
compose exec -T app sh -c 'ls -la /var/run/mysqld/mysqld.sock 2>&1 || ls -la /var/run/mysqld/ 2>&1 || echo "NO existe el socket en el contenedor"'

echo ""
echo "=== Config efectiva Laravel (después de config:clear) ==="
compose exec -T app php artisan config:clear >/dev/null
compose exec -T app php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$c = config('database.connections.mysql');
echo 'host=' . (\$c['host'] ?? '?') . PHP_EOL;
echo 'port=' . (\$c['port'] ?? '?') . PHP_EOL;
echo 'database=' . (\$c['database'] ?? '?') . PHP_EOL;
echo 'unix_socket=' . (\$c['unix_socket'] ?? '') . PHP_EOL;
echo 'url=' . (\$c['url'] ?? '') . PHP_EOL;
"

echo ""
echo "=== Prueba de conexión PDO ==="
compose exec -T app php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'OK: conexión MySQL'; } catch (Throwable \$e) { echo 'ERROR: ' . \$e->getMessage(); }"
