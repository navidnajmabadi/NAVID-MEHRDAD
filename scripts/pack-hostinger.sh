#!/usr/bin/env bash
# Emergency zip only — normal deploy is SSH git pull on PetFinder (see README).
# Prefer merge to PetFinder + docs/HOSTINGER_INSTALL.md over this zip.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/dist/sipaw-hostinger.zip"
mkdir -p "$ROOT/dist"
rm -f "$OUT"
cd "$ROOT"
zip -9r "$OUT" \
  .htaccess \
  index.php signup.php login.php logout.php dashboard.php pet.php pet-form.php tag.php \
  manifest.webmanifest sw.js robots.txt config.example.php README.md \
  api lib assets sql docs uploads/pets/.htaccess uploads/pets/.gitkeep \
  -x "*.git*" -x "dist/*" -x "config.local.php" -x "config.php" -x "uploads/pets/*.*"
echo "Wrote $OUT"
unzip -l "$OUT" | head -60
