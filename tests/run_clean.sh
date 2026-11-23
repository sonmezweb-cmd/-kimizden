#!/usr/bin/env bash
# Temiz kurulumdan uçtan uca test: veritabanını sıfırlar, sihirbazı HTTP ile çalıştırır, testleri koşar.
# YALNIZ YEREL GELİŞTİRME. Kullanım: bash tests/run_clean.sh
set -euo pipefail
cd "$(dirname "$0")/.."
BASE=${BASE:-http://127.0.0.1:8080}
mysql -uroot -e "DROP DATABASE IF EXISTS ikimizden; CREATE DATABASE ikimizden CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f storage/config.php storage/install.lock storage/cache/i18n-*.php
rm -rf storage/uploads/photos storage/cache/img
J=$(mktemp)
curl -s -c "$J" -b "$J" "$BASE/install" > /tmp/ikz-install.html
TOKEN=$(grep -oE 'name="_csrf" value="[^"]+"' /tmp/ikz-install.html | head -1 | sed 's/.*value="//;s/"//')
curl -s -c "$J" -b "$J" -o /tmp/ikz-install2.html --data-urlencode "_csrf=$TOKEN" --data-urlencode "base_url=$BASE" \
  --data-urlencode "db_host=127.0.0.1" --data-urlencode "db_port=3306" --data-urlencode "db_name=ikimizden" \
  --data-urlencode "db_user=iki" --data-urlencode "db_pass=ikipass" --data-urlencode "admin_name=Site Yöneticisi" \
  --data-urlencode "admin_email=admin@example.test" --data-urlencode "admin_password=YonetimParola2026!" "$BASE/install"
grep -q "Kurulum tamamlandı" /tmp/ikz-install2.html && echo "Kurulum: tamam"
# Ürün kataloğu dışında hiçbir müşteri/sipariş kaydı olmamalı
echo "Kurulum sonrası kayıt sayıları (users/orders/themes/packages):"
mysql -uiki -pikipass -h127.0.0.1 ikimizden -N -e "SELECT (SELECT COUNT(*) FROM users),(SELECT COUNT(*) FROM orders),(SELECT COUNT(*) FROM themes),(SELECT COUNT(*) FROM packages)"
sed -i "s/'debug' => false,/'debug' => false,\n  'paytr_token_url' => 'http:\/\/127.0.0.1:8090\/', \/\/ YALNIZ YEREL TEST/" storage/config.php
php tests/acceptance.php "$BASE" "${1:-tests/_out/acceptance.json}"
