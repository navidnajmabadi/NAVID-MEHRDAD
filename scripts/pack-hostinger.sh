#!/usr/bin/env bash
# Pack the static files that belong in ringringpaw.nakilab.com public_html.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/dist/ringringpaw-hostinger.zip"
mkdir -p "$ROOT/dist"
rm -f "$OUT"
cd "$ROOT"
zip -9 "$OUT" \
  index.html register.html pet.html dashboard.html admin.html \
  app.js style.css .htaccess robots.txt
echo "Wrote $OUT"
unzip -l "$OUT"
