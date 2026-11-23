# Üçüncü Taraf Bileşenler ve Lisanslar

| Bileşen | Kullanım | Lisans | Konum |
|---|---|---|---|
| PHPMailer 6.x | SMTP e-posta gönderimi | LGPL-2.1 | `vendor/phpmailer/phpmailer/LICENSE` |
| chillerlan/php-qrcode 5.x | QR kod (SVG/PNG) | MIT veya Apache-2.0 (çift lisans) | `vendor/chillerlan/php-qrcode/LICENSE` |
| chillerlan/php-settings-container | php-qrcode bağımlılığı | MIT | `vendor/chillerlan/php-settings-container/LICENSE` |
| Inter (değişken) | Arayüz yazı tipi (Latin, Kiril) | SIL OFL 1.1 | `public/assets/fonts/LICENSE-inter.txt` |
| Cormorant Garamond | Başlık yazı tipi (Latin, Kiril) | SIL OFL 1.1 | `public/assets/fonts/LICENSE-cormorantgaramond.txt` |
| Great Vibes | Davetiye isim yazısı (Latin, Kiril) | SIL OFL 1.1 | `public/assets/fonts/LICENSE-greatvibes.txt` |
| Marck Script | Yedek el yazısı (Kiril) | SIL OFL 1.1 | `public/assets/fonts/LICENSE-marckscript.txt` |
| PayTR iframeResizer | Ödeme çerçevesi boyutlandırma | PayTR tarafından sunulur (www.paytr.com) | harici |
| Remotion 4.x | Açılış videolarının üretimi (yalnız geliştirme aracı, üretimde gerekmez) | Remotion Lisansı — 3 kişiye kadar ekipler için ücretsiz; daha büyük şirketler için şirket lisansı gerekir | `tools/opening-video/` |

Uygulama kodu (PHP, CSS, JS) dış kütüphane kullanmadan yazılmıştır. Yazı tipleri, Google Fonts deposundan alınıp Latin, Latin-Ext ve Kiril alt kümeleriyle WOFF2'ye dönüştürülmüştür; harici yazı tipi isteği yapılmaz.
