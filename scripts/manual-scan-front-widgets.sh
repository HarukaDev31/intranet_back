#!/usr/bin/env bash
# Regenera config/manual_usuario_page_widgets.php desde el código Vue del front.
# Uso (desde la raíz del back):
#   bash scripts/manual-scan-front-widgets.sh
#   bash scripts/manual-scan-front-widgets.sh --only=pages/curso
#   FRONT_PATH=/path/probusiness_intranetv3 bash scripts/manual-scan-front-widgets.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

FRONT="${FRONT_PATH:-${MANUAL_USUARIO_FRONT_PATH:-}}"
if [[ -z "$FRONT" ]]; then
  if [[ -d "$ROOT/../probusiness_intranetv3/pages" ]]; then
    FRONT="$(cd "$ROOT/../probusiness_intranetv3" && pwd)"
  elif [[ -d "$ROOT/probusiness_intranetv3/pages" ]]; then
    FRONT="$(cd "$ROOT/probusiness_intranetv3" && pwd)"
  fi
fi

if [[ -z "$FRONT" || ! -d "$FRONT/pages" ]]; then
  echo "No se encontró el front Vue (pages/)." >&2
  echo "Define FRONT_PATH o MANUAL_USUARIO_FRONT_PATH, o clona probusiness_intranetv3 como hermano del back." >&2
  exit 1
fi

ONLY_ARGS=()
WRITE_ARGS=(--write --no-backup)
for arg in "$@"; do
  case "$arg" in
    --dry-run) WRITE_ARGS=() ;;
    --backup) WRITE_ARGS=(--write) ;;
    *) ONLY_ARGS+=("$arg") ;;
  esac
done

echo "Front: $FRONT"
php artisan manual:scan-front-widgets --front="$FRONT" "${WRITE_ARGS[@]}" "${ONLY_ARGS[@]}"
php artisan config:clear
echo "Listo. Commitéa config/manual_usuario_page_widgets.php si cambió."
