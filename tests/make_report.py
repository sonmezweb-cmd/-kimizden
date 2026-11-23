#!/usr/bin/env python3
"""tests/_out/*.json → docs/TEST-RAPORU.md"""
import json, os, datetime
root = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
rows = []
for f in ['acceptance.json', 'e2e.json']:
    p = os.path.join(root, 'tests', '_out', f)
    if os.path.exists(p):
        rows += json.load(open(p, encoding='utf-8'))
external = [
    ('B/G. Ödeme', 'Gerçek PayTR mağazasıyla test modunda token alma ve iFrame’de test kartıyla ödeme', 'PayTR mağaza bilgileri ve canlı alan adı verilmedi; www.paytr.com bu ortamın ağından erişilemez. Token isteği yerel taklit sunucuyla, imza resmi formülle doğrulandı.'),
    ('B/G. Ödeme', 'PayTR’nin gerçek bildirimi (callback) ve “Son doğrulanmış test/canlı bildirim” tarihinin dolması', 'Bildirim, PayTR’nin imza formülüyle üretilen isteklerle test edildi; gerçek PayTR sunucusundan gelmedi.'),
    ('B. Ödeme', 'Canlı modda gerçek kartla tahsilat', 'Mağaza bilgileri ve canlı alan adı gerekli.'),
    ('B. Ödeme', 'PayTR resmi dokümanının (iframe-api-1/2-adim) güncel hâliyle alan/imza sırası karşılaştırması ve RU ödeme dili desteği', 'dev.paytr.com bu ortamdan açılamadı (ağ politikası). Alanlar ve imza sırası PayTR’nin bilinen PHP örneğiyle ve açık kaynak bir entegrasyonla karşılaştırıldı; RU için “en” kullanılıyor.'),
    ('E-posta', 'SMTP ile gerçek e-posta teslimi (ödeme, taslak, revizyon, onay, yayın, parola sıfırlama)', 'SMTP hesabı verilmedi. E-postaların doğru dilde, tek sefer (dedupe) kuyruğa alındığı test edildi; gönderim denenmedi.'),
    ('Kurulum', 'Gerçek cPanel hostingde ZIP kurulumu, HTTPS ve Apache/LiteSpeed .htaccess davranışı', 'Hosting erişimi yok. Kurulum PHP 8.3 + MariaDB 10.11 ile yerel sunucuda, ana dizin ve /ikimizden alt dizininde test edildi.'),
    ('I. Cihazlar', 'Fiziksel iPhone’da iOS Safari (video kaydırma, güvenli alan, sticky, ses)', 'Fiziksel cihaz yok. iPhone 13 ölçüleri yalnız Chromium emülasyonuyla denendi; WebKit/Safari çalıştırılmadı.'),
    ('I. Cihazlar', 'Fiziksel Android telefonda Chrome', 'Fiziksel cihaz yok; Pixel 7 yatay emülasyonu Chromium ile denendi.'),
    ('Paylaşım', 'WhatsApp’ta gerçek bağlantı önizlemesi (Open Graph görsel/başlık)', 'Herkese açık alan adı gerekli; meta etiketleri sayfada doğrulandı.'),
]
by = {}
for r in rows:
    by.setdefault(r['scenario'], []).append(r)
ok = sum(1 for r in rows if r['result'] == 'Geçti')
bad = sum(1 for r in rows if r['result'] != 'Geçti')
L = []
L.append('# Test Raporu — İkimizden 1.0.0\n')
L.append('Tarih: %s (UTC)  \n' % datetime.datetime.now(datetime.UTC).strftime('%Y-%m-%d %H:%M'))
L.append('Ortam: PHP 8.3.6, MariaDB 10.11.14, PHP geliştirme sunucusu (`public/router.php`), Playwright + Chromium (başsız). Kurulum her çalıştırmada **boş veritabanından** sihirbaz HTTP üzerinden çalıştırılarak yapıldı (`tests/run_clean.sh`).\n')
L.append('Komutlar: `bash tests/run_clean.sh` (A–E, G, H — HTTP üzerinden) · `node tests/e2e/engine.js` (F, I — tarayıcı).\n')
L.append('\n## Özet\n')
L.append('| Geçti | Kaldı | Harici erişim bekliyor |\n|---|---|---|\n| %d | %d | %d |\n' % (ok, bad, len(external)))
L.append('\nAçıklama: **Harici erişim bekliyor** satırları bu ortamda yapılamayan ve tamamlanmış gösterilmeyen doğrulamalardır (gerçek PayTR mağazası, alan adı, SMTP hesabı, fiziksel cihaz).\n')
L.append('\n## Senaryolar\n')
for sc, items in by.items():
    L.append('\n### %s\n\n| Kontrol | Geçti | Kaldı | Harici erişim bekliyor |\n|---|:---:|:---:|:---:|\n' % sc)
    for r in items:
        L.append('| %s | %s | %s |  |\n' % (r['check'].replace('|', '\\|').replace('<', '&lt;'), '✓' if r['result'] == 'Geçti' else '', '✗ ' + r.get('detail', '') if r['result'] != 'Geçti' else ''))
L.append('\n## Harici erişim bekleyen doğrulamalar\n\n| Alan | Kontrol | Geçti | Kaldı | Harici erişim bekliyor |\n|---|---|:---:|:---:|---|\n')
for a, c, d in external:
    L.append('| %s | %s |  |  | ⏳ %s |\n' % (a, c, d))
L.append('\n## Notlar\n')
L.append('- Ödeme testlerinde `storage/config.php` içine yalnız yerel test için `paytr_token_url` (taklit sunucu `tests/mock_paytr.php`) eklendi. Üretim ZIP’inde bu ayar yoktur; uygulama `https://www.paytr.com/odeme/api/get-token` adresini kullanır.\n')
L.append('- Bildirim testleri PayTR’nin resmi imza formülüyle (`base64(hmac_sha256(merchant_oid + salt + status + total_amount, key))`) üretilen isteklerle yapıldı.\n')
L.append('- Görsel kontroller (320/390/768/1440 px, mobil yatay, JS kapalı, video yok) ekran görüntüleriyle de incelendi; `tests/_out/shots/`.\n')
L.append('- Tarayıcı testleri yalnız Chromium motorunda çalıştı; Safari/WebKit ve Firefox çalıştırılmadı.\n')
open(os.path.join(root, 'docs', 'TEST-RAPORU.md'), 'w', encoding='utf-8').write(''.join(L))
print('rapor:', ok, 'geçti,', bad, 'kaldı,', len(external), 'harici')
