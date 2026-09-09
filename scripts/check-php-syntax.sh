#!/usr/bin/env bash
# Falla si algún PHP en app/ no parsea (p. ej. métodos duplicados case-insensitive).
set -euo pipefail

ROOT="${1:-app}"
fail=0

while IFS= read -r -d '' file; do
  if ! php -l "$file" > /dev/null 2>&1; then
    echo "ERROR de sintaxis en: $file" >&2
    php -l "$file" >&2 || true
    fail=1
  fi
done < <(find "$ROOT" -type f -name '*.php' -print0)

if [[ "$fail" -ne 0 ]]; then
  exit 1
fi

echo "OK: sintaxis PHP válida en ${ROOT}/"
