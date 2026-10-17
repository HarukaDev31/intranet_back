#!/usr/bin/env bash
# PHPStan solo sobre los PHP de app/ que cambian en el PR o en el push del deploy.
# No analiza el resto del proyecto. Si no hay archivos, termina bien.
set -euo pipefail

BASE="${PHPSTAN_BASE:-}"
MODE="${PHPSTAN_MODE:-range}"

if [[ -z "$BASE" || "$BASE" =~ ^0+$ ]]; then
  if git rev-parse --verify --quiet HEAD^ >/dev/null; then
    BASE="$(git rev-parse HEAD^)"
    MODE="range"
    echo "Sin SHA base del evento. Se compara contra el commit anterior."
  else
    echo "No hay base de comparación. PHPStan omitido."
    exit 0
  fi
fi

if ! git cat-file -e "${BASE}^{commit}" 2>/dev/null; then
  echo "No se encontró el commit base ${BASE}. PHPStan omitido."
  exit 0
fi

if [[ "$MODE" == "pr" ]]; then
  range=("${BASE}...HEAD")
else
  range=("$BASE" "HEAD")
fi

files=()
while IFS= read -r path; do
  [[ "$path" == *.php ]] || continue
  [[ -f "$path" ]] || continue
  files+=("$path")
done < <(git diff --name-only --diff-filter=ACMR "${range[@]}" -- app)

if [[ ${#files[@]} -eq 0 ]]; then
  echo "Sin archivos PHP de app/ en el diff. PHPStan omitido."
  exit 0
fi

echo "PHPStan sobre ${#files[@]} archivo(s) (${MODE} ${BASE}..HEAD):"
printf ' - %s\n' "${files[@]}"

exec vendor/bin/phpstan analyse --memory-limit=2G --no-progress "${files[@]}"
