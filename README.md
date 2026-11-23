# İkimizden — Dijital Davetiye Platformu

Çiftlerin davetiye satın aldığı, bilgilerini gönderdiği, tasarımcının taslak hazırladığı, çiftin onayladığı ve misafirlerin telefonda **tam ekran zarfı kaydırarak ya da mühre dokunarak** açtığı dijital davetiye satış ve yönetim platformu.

- **PHP 8.2+**, PDO, MySQL 8 / MariaDB 10.4+ (InnoDB, utf8mb4); sunucu tarafında HTML, etkileşimler için bağımlılıksız CSS/JS.
- **cPanel uyumlu:** Node.js, SSH, Docker, Redis veya sürekli çalışan işlem gerekmez. Ana alan adında ve `/ikimizden` gibi alt dizinde çalışır.
- **Üç dil:** Vitrin, müşteri paneli, tasarımcı paneli, yönetim paneli ve misafir davetiyesi TR / EN / RU. Panel dili ile davetiye içeriğinin dili ayrıdır.
- **Üç tema:** Gül Mührü, Zeytin Varak, Sedef Kıvrımı. Her biri poster, Remotion ile üretilmiş 8 saniyelik kaydırmalı açılış videosu (MP4 + WebM), dört ses efekti ve video olmadan çalışan CSS açılışı içerir.
- **PayTR iFrame API** entegrasyonu; mağaza bilgileri yönetim panelinden girilir, şifreli saklanır.

## Belgeler

| Belge | İçerik |
|---|---|
| [docs/KURULUM.md](docs/KURULUM.md) | cPanel kurulum ve güncelleme kılavuzu, public_html yerleşim örnekleri |
| [docs/PAYTR-YONETICI.md](docs/PAYTR-YONETICI.md) | PayTR bilgilerini nereye gireceğiniz, bildirim adresini nereye ekleyeceğiniz |
| [docs/MEDYA-KILAVUZU.md](docs/MEDYA-KILAVUZU.md) | Poster, video ve dört sesin değiştirilmesi; Remotion ile video üretimi; video promptları |
| [docs/TEST-RAPORU.md](docs/TEST-RAPORU.md) | Geçti / Kaldı / Harici erişim bekliyor sütunlarıyla test raporu |
| [docs/LISANSLAR.md](docs/LISANSLAR.md) | Üçüncü taraf bileşenler ve lisansları |

## Klasör yapısı

```
app/            PHP: lib/ (çekirdek ve servisler), controllers/, views/, lang/ (tr, en, ru)
bin/            migrate.php, cron.php (isteğe bağlı komut satırı)
database/       migrations/*.sql
public/         web kökü: index.php, .htaccess, assets/ (css, js, fonts, img), themes/ (tema medyası)
storage/        config.php (kurulumda oluşur), logs/, backups/, uploads/ (özel), cache/ — web kökü dışında
vendor/         PHPMailer, php-qrcode (hazır)
tools/          geliştirme araçları: media/ (katman kesme, ses), opening-video/ (Remotion), i18n/ (dil dosyaları üretimi), build_zip.sh
tests/          acceptance.php (HTTP uçtan uca), e2e/engine.js (Playwright), run_clean.sh, mock_paytr.php
```

## Yerel geliştirme

```bash
php -S 127.0.0.1:8080 -t public public/router.php     # tarayıcıda açın → kurulum sihirbazı
python3 tools/i18n/build.py                            # dil dosyalarını üret + eksik anahtar denetimi
bash tests/run_clean.sh                                # temiz kurulum + A–H kabul testleri (taklit PayTR: php -S 127.0.0.1:8090 tests/mock_paytr.php)
node tests/e2e/engine.js http://127.0.0.1:8080         # F ve I tarayıcı senaryoları
bash tools/build_zip.sh                                # dist/ altında cPanel ZIP ve kaynak ZIP
```
