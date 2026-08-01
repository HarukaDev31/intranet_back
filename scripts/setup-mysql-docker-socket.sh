#!/usr/bin/env bash
# Configura MySQL en el HOST para exponer el socket en /opt/mysql-socket
# (ruta persistente; sobrevive systemctl restart mysql / unattended-upgrade).
#
# En Ubuntu, AppArmor bloquea sockets fuera de /run/mysqld y /var/lib/mysql.
# Este script agrega una regla local en /etc/apparmor.d/local/usr.sbin.mysqld.
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
AA_LOCAL="/etc/apparmor.d/local/usr.sbin.mysqld"
AA_PROFILE="/etc/apparmor.d/usr.sbin.mysqld"

rollback() {
  echo "==> Rollback: quitar $CNF_DST y arrancar MySQL con config anterior" >&2
  rm -f "$CNF_DST"
  systemctl reset-failed mysql 2>/dev/null || true
  systemctl start mysql || true
}

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Ejecutá como root: sudo bash $0" >&2
  exit 1
fi

if [[ ! -f "$CNF_SRC" ]]; then
  echo "No encontré $CNF_SRC" >&2
  exit 1
fi

trap 'rollback' ERR

echo "==> Crear $SOCKET_DIR"
mkdir -p "$SOCKET_DIR"
chown mysql:mysql "$SOCKET_DIR"
chmod 755 "$SOCKET_DIR"

# AppArmor: permitir r/w/k en el dir del socket (Ubuntu)
if [[ -d /etc/apparmor.d/local ]]; then
  echo "==> AppArmor local: $AA_LOCAL"
  mkdir -p /etc/apparmor.d/local
  touch "$AA_LOCAL"
  if ! grep -qF "${SOCKET_DIR}/" "$AA_LOCAL" 2>/dev/null; then
    {
      echo ""
      echo "# intranet_back docker host MySQL socket (scripts/setup-mysql-docker-socket.sh)"
      echo "${SOCKET_DIR}/ rw,"
      echo "${SOCKET_DIR}/** rwk,"
    } >> "$AA_LOCAL"
  fi
  if [[ -f "$AA_PROFILE" ]]; then
    apparmor_parser -r "$AA_PROFILE" || true
  fi
  # Por si el perfil se llama distinto en algunas builds
  if command -v aa-status >/dev/null 2>&1; then
    aa-status 2>/dev/null | grep -q mysqld && echo "    AppArmor mysqld recargado" || true
  fi
else
  echo "==> Sin AppArmor local dir — omitiendo (revisá si MySQL falla al crear .lock)"
fi

echo "==> Instalar $CNF_DST"
cp "$CNF_SRC" "$CNF_DST"
if [[ "$SOCKET_DIR" != "/opt/mysql-socket" ]]; then
  sed -i "s|/opt/mysql-socket|${SOCKET_DIR}|g" "$CNF_DST"
fi

echo "==> Reiniciar MySQL"
systemctl reset-failed mysql 2>/dev/null || true
systemctl restart mysql

sleep 2
if [[ -S "${SOCKET_DIR}/mysqld.sock" ]]; then
  trap - ERR
  echo "OK: socket en ${SOCKET_DIR}/mysqld.sock"
  ls -la "${SOCKET_DIR}/mysqld.sock"
else
  echo "ERROR: no apareció ${SOCKET_DIR}/mysqld.sock" >&2
  exit 1
fi

echo ""
echo "Siguiente en la app:"
echo "  DB_SOCKET=${SOCKET_DIR}/mysqld.sock"
echo "  MYSQL_SOCKET_DIR_HOST=${SOCKET_DIR}"
echo "  docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml up -d --force-recreate app horizon scheduler"
echo "  docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec -u www-data app php artisan config:clear"
