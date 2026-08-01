#!/usr/bin/env bash
# Configura MySQL en el HOST para exponer el socket en /opt/mysql-socket
# (ruta persistente; sobrevive systemctl restart mysql / unattended-upgrade).
#
# Uso (como root en el servidor):
#   bash scripts/setup-mysql-docker-socket.sh
#
# Después:
#   1) En .env de la app: DB_SOCKET=/opt/mysql-socket/mysqld.sock
#   2) docker compose ... up -d --force-recreate app horizon scheduler

set -euo pipefail

SOCKET_DIR="${MYSQL_SOCKET_DIR_HOST:-/opt/mysql-socket}"
CNF_SRC="$(cd "$(dirname "$0")/.." && pwd)/docker/mysql/zz-docker-socket.cnf.example"
CNF_DST="/etc/mysql/mysql.conf.d/zz-docker-socket.cnf"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Ejecutá como root: sudo bash $0" >&2
  exit 1
fi

if [[ ! -f "$CNF_SRC" ]]; then
  echo "No encontré $CNF_SRC" >&2
  exit 1
fi

echo "==> Crear $SOCKET_DIR"
mkdir -p "$SOCKET_DIR"
chown mysql:mysql "$SOCKET_DIR"
chmod 755 "$SOCKET_DIR"

echo "==> Instalar $CNF_DST"
cp "$CNF_SRC" "$CNF_DST"
# Alinear path si MYSQL_SOCKET_DIR_HOST no es el default
if [[ "$SOCKET_DIR" != "/opt/mysql-socket" ]]; then
  sed -i "s|/opt/mysql-socket|${SOCKET_DIR}|g" "$CNF_DST"
fi

echo "==> Validar config MySQL"
mysqld --validate-config 2>/dev/null || true

echo "==> Reiniciar MySQL"
systemctl restart mysql

sleep 2
if [[ -S "${SOCKET_DIR}/mysqld.sock" ]]; then
  echo "OK: socket en ${SOCKET_DIR}/mysqld.sock"
  ls -la "${SOCKET_DIR}/mysqld.sock"
else
  echo "ERROR: no apareció ${SOCKET_DIR}/mysqld.sock — revisá journalctl -u mysql -n 50" >&2
  exit 1
fi

echo ""
echo "Siguiente en la app:"
echo "  DB_SOCKET=${SOCKET_DIR}/mysqld.sock"
echo "  MYSQL_SOCKET_DIR_HOST=${SOCKET_DIR}"
echo "  docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml up -d --force-recreate app horizon scheduler"
echo "  docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec -u www-data app php artisan config:clear"
