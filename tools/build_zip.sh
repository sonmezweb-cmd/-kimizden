#!/usr/bin/env bash
# cPanel kurulum ZIP'i ve tam kaynak ZIP'i üretir: dist/
set -euo pipefail
cd "$(dirname "$0")/.."
VER=$(php -r "require 'app/bootstrap.php'; echo APP_VERSION;" 2>/dev/null || echo 1.0.0)
OUT=dist; STAGE=$(mktemp -d)
rm -rf "$OUT"; mkdir -p "$OUT"
# --- cPanel paketi
P="$STAGE/cpanel"
mkdir -p "$P/ikimizden" "$P/public_html"
cp -r app bin database vendor "$P/ikimizden/"
mkdir -p "$P/ikimizden/storage"/{logs,backups,uploads,cache,sessions}
for d in storage storage/uploads storage/logs storage/backups storage/cache storage/sessions; do printf 'Require all denied\nDeny from all\n' > "$P/ikimizden/$d/.htaccess"; done
cp -r public/. "$P/public_html/"
rm -f "$P/public_html/router.php" "$P/public_html/app-path.php"
cp README.md "$P/OKUBENI.md"; mkdir -p "$P/belgeler"; cp docs/*.md "$P/belgeler/"
find "$P" -name '.DS_Store' -delete
(cd "$P" && zip -qr "$OLDPWD/$OUT/ikimizden-cpanel-$VER.zip" .)
# --- tam kaynak (çalışma zamanı dosyaları ve node_modules hariç)
git archive --format=zip --prefix="ikimizden-kaynak-$VER/" -o "$OUT/ikimizden-kaynak-$VER.zip" HEAD
rm -rf "$STAGE"
ls -la "$OUT"
# denetim: sır, yapılandırma veya test ayarı pakette olmamalı
if unzip -l "$OUT/ikimizden-cpanel-$VER.zip" | grep -E "storage/config\.php|install\.lock|\.log$|paytr_token_url|router\.php"; then echo "UYARI: pakette olmaması gereken dosya"; exit 1; fi
echo "Paket denetimi: temiz"
