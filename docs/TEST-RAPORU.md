# Test Raporu — İkimizden 1.0.0
Tarih: 2026-11-23 12:38 (UTC)  
Ortam: PHP 8.3.6, MariaDB 10.11.14, PHP geliştirme sunucusu (`public/router.php`), Playwright + Chromium (başsız). Kurulum her çalıştırmada **boş veritabanından** sihirbaz HTTP üzerinden çalıştırılarak yapıldı (`tests/run_clean.sh`).
Komutlar: `bash tests/run_clean.sh` (A–E, G, H — HTTP üzerinden) · `node tests/e2e/engine.js` (F, I — tarayıcı).

## Özet
| Geçti | Kaldı | Harici erişim bekliyor |
|---|---|---|
| 151 | 0 | 9 |

Açıklama: **Harici erişim bekliyor** satırları bu ortamda yapılamayan ve tamamlanmış gösterilmeyen doğrulamalardır (gerçek PayTR mağazası, alan adı, SMTP hesabı, fiziksel cihaz).

## Senaryolar

### A. Kurulum → yönetici → fiyat → tema → katalog önizleme

| Kontrol | Geçti | Kaldı | Harici erişim bekliyor |
|---|:---:|:---:|:---:|
| Kurulum sihirbazı kilitli (/install 404) | ✓ |  |  |
| Yönetici girişi /admin yönlendirmesi | ✓ |  |  |
| Paket fiyatı kuruş olarak kaydedildi (2.750,50 → 275050) | ✓ |  |  |
| Tema kaydı (aktif, ses %55) | ✓ |  |  |
| Katalog tema sayfasında fiyat görünür | ✓ |  |  |
| Canlı önizleme motoru açılır (zarf + gerçek HTML isim) | ✓ |  |  |
| Katalog ilk açılışta video indirmez (sayfada &lt;video> yok) | ✓ |  |  |
| EN önizlemede İngilizce örnek çift | ✓ |  |  |
| PayTR anahtarı şifreli saklanır (DB’de düz metin yok) | ✓ |  |  |
| Kayıtlı sır tarayıcıya gönderilmez | ✓ |  |  |
| Bildirim adresi panelde gösterilir | ✓ |  |  |
| Boş bırakılan sır alanı mevcut değeri korur | ✓ |  |  |
| Sırlar loglarda yok | ✓ |  |  |

### B. Hesap → paket → ödeme → doğrulanmış bildirim → form → taslak → revizyon → onay → yayın

| Kontrol | Geçti | Kaldı | Harici erişim bekliyor |
|---|:---:|:---:|:---:|
| Müşteri hesabı oluşturuldu | ✓ |  |  |
| Sipariş oluşturuldu ve özete yönlendirildi | ✓ |  |  |
| Sipariş anlık kopyası (paket, fiyat, revizyon, para birimi) | ✓ |  |  |
| Başka paketin fiyat değişikliği siparişi etkilemez | ✓ |  |  |
| RU özet sayfası ve PayTR dil açıklaması | ✓ |  |  |
| Ödeme başlatıldı → iFrame sayfası | ✓ |  |  |
| Token isteği: kuruş tutar, TL, test_mode=0, imza alanları | ✓ |  |  |
| paytr_token resmi sıra ile doğru | ✓ |  |  |
| iFrame sayfası PayTR güvenli adresini gösterir | ✓ |  |  |
| Başarı dönüş sayfası siparişi ödenmiş yapmaz ("doğrulanıyor") | ✓ |  |  |
| Geçerli bildirim → düz metin OK | ✓ |  |  |
| Sipariş ödendi + yayın hakkı (canlı) | ✓ |  |  |
| Taksit farkı ayrı kaydedildi (ana 275050 / tahsil 280000) | ✓ |  |  |
| Son doğrulanmış canlı bildirim tarihi güncellendi | ✓ |  |  |
| Ödendi ≠ çift onayladı ≠ yayında (bağımsız durumlar) | ✓ |  |  |
| Her form adımı veritabanına kaydedildi | ✓ |  |  |
| Etkinlik zamanı UTC + IANA dilimi ayrı saklanır | ✓ |  |  |
| Gece yarısını geçen etkinlik ertesi güne taşar | ✓ |  |  |
| Paragraf ve satır sonları korunur | ✓ |  |  |
| Fotoğraf yüklendi ve web kökü dışında saklandı | ✓ |  |  |
| Sahte görsel (PHP içerik) reddedildi | ✓ |  |  |
| Bilgiler tasarımcıya gönderildi | ✓ |  |  |
| Tasarımcı yalnız atanmış siparişi görür | ✓ |  |  |
| Tasarımcı paket kapsamı ve kalan revizyonu görür | ✓ |  |  |
| Sürüm 1 onaya gönderildi | ✓ |  |  |
| Aynı revizyon iki kez gönderilince hak bir kez düşer | ✓ |  |  |
| Eski sürüm onaylanamaz | ✓ |  |  |
| Sürüm 2 onaylandı ve yayımlandı | ✓ |  |  |
| Kalıcı bağlantı yayında (200, noindex) | ✓ |  |  |
| E-postalar kuyrukta (ödeme, taslak, yayın) | ✓ |  |  |

### C. Misafir: zarf → isim/tarih → ikinci etkinlik konumu → katılım → panelde toplam

| Kontrol | Geçti | Kaldı | Harici erişim bekliyor |
|---|:---:|:---:|:---:|
| İsimler gerçek HTML başlıkta | ✓ |  |  |
| Kapak tarihi yerelleştirilmiş | ✓ |  |  |
| İkinci etkinlik kendi harita bağlantısını taşır | ✓ |  |  |
| Mekân yerel saatiyle (Moskova 20:00 – 01:30 +1 gün) | ✓ |  |  |
| Geri sayım, Ara ve WhatsApp düğmeleri | ✓ |  |  |
| Boş isteğe bağlı bölümler gizli (konaklama yok) | ✓ |  |  |
| Takvime ekle (ICS) bağlantısı | ✓ |  |  |
| ICS: TZID=Europe/Moscow, başlangıç 20:00, bitiş ertesi gün 01:30 | ✓ |  |  |
| Aynı tarayıcıdan düzeltme önceki genel yanıtı günceller | ✓ |  |  |
| Çift panelinde toplam doğru (2 kişi) | ✓ |  |  |
| RU davetiye: Kiril isimler korunur, arayüz Rusça | ✓ |  |  |

### D. İsim değişikliği → taslakta yeni isim → canlı korunur → onay sonrası aynı URL güncellenir

| Kontrol | Geçti | Kaldı | Harici erişim bekliyor |
|---|:---:|:---:|:---:|
| Yeni isim taslak önizlemede | ✓ |  |  |
| Canlı sürüm eski isimle korunur | ✓ |  |  |
| Yeni sürüm otomatik onaylı değil | ✓ |  |  |
| Onaydan sonra aynı URL yeni isimle | ✓ |  |  |

### E. Diller, uzun Kiril isimler, saat dilimleri, eksik alanlar

| Kontrol | Geçti | Kaldı | Harici erişim bekliyor |
|---|:---:|:---:|:---:|
| Panel tr | ✓ |  |  |
| Panel en | ✓ |  |  |
| Panel ru | ✓ |  |  |
| Yönetim paneli en | ✓ |  |  |
| Yönetim paneli ru | ✓ |  |  |
| Tasarımcı paneli RU | ✓ |  |  |
| Uzun Kiril isimler özgün sırayla (ikinci & birinci) | ✓ |  |  |
| Vitrin önizleme tr (İpek) | ✓ |  |  |
| Vitrin önizleme en (Elizabeth) | ✓ |  |  |
| Vitrin önizleme ru (Анастасия) | ✓ |  |  |
| Para biçimi dile göre (EN) | ✓ |  |  |
| Dil bağlantısı aynı sayfada kalır | ✓ |  |  |

### G. Bildirim güvenliği: tekrar, geçersiz imza, tutar, dönüş sayfası, test/canlı

| Kontrol | Geçti | Kaldı | Harici erişim bekliyor |
|---|:---:|:---:|:---:|
| Aynı bildirim 5 kez → her biri OK | ✓ |  |  |
| … tek ödeme, ek e-posta yok | ✓ |  |  |
| Geçersiz imza reddedilir (OK değil) | ✓ |  |  |
| Uyuşmayan tutar: kaydedilir, OK, incelemeye alınır, yayın hakkı yok | ✓ |  |  |
| Test modunda test_mode=1 gönderildi | ✓ |  |  |
| Test tahsilatı: test_paid, yayın hakkı açmaz | ✓ |  |  |
| Canlı denemeye test_mode=1 bildirimi uyuşmaz (OK değil, ödenmez) | ✓ |  |  |
| Başarısız bildirim: sipariş ödenmemiş, sepet korunur | ✓ |  |  |
| İlk başarılı tahsilat → ödendi | ✓ |  |  |
| Aynı siparişe iki gerçek tahsilat: ikisi de kaydedilir, incelemeye açılır | ✓ |  |  |
| Bilinmeyen merchant_oid geçerli imzayla günlüğe alınır | ✓ |  |  |
| Bildirim uç noktası GET kabul etmez | ✓ |  |  |

### H. Yetki, eşzamanlı düzenleme, aile yanıtı

| Kontrol | Geçti | Kaldı | Harici erişim bekliyor |
|---|:---:|:---:|:---:|
| Başka müşteri siparişe erişemez (404) | ✓ |  |  |
| Başka müşteri formu kaydedemez | ✓ |  |  |
| Başka müşteri taslaktaki özel fotoğrafı göremez (404) | ✓ |  |  |
| Sahibi kendi fotoğrafını görür | ✓ |  |  |
| Canlı sürümdeki kapak fotoğrafı misafire açık | ✓ |  |  |
| Müşteri yönetim paneline giremez (403) | ✓ |  |  |
| Tasarımcı atanmamış siparişe erişemez | ✓ |  |  |
| CSRF jetonu olmadan POST reddedilir (419) | ✓ |  |  |
| Eşzamanlı düzenleme sessizce ezilmez (409 + çakışma gösterilir) | ✓ |  |  |
| Otomatik kayıt yalnız değişen alanı yazar; başkasının alanı korunur | ✓ |  |  |
| Aile grubu ve tahmin edilemeyen özel bağlantı | ✓ |  |  |
| Özel bağlantıda aile adı görünür, paylaşım önizlemesinde görünmez | ✓ |  |  |
| Genel sayfada aile adı görünmez | ✓ |  |  |
| Aynı aile yanıtı toplamı şişirmez (tek kayıt, son geçerli: 3 kişi) | ✓ |  |  |
| Panelde davet edilen / yanıtlayan / yanıt vermeyen ayrı | ✓ |  |  |
| CSV UTF-8 (BOM) ve Kiril aile adı | ✓ |  |  |
| Son tarihten sonra sunucu yeni yanıt kabul etmez | ✓ |  |  |
| Arşivlenen davetiye misafire kapanır | ✓ |  |  |
| Süreli önizleme bağlantısı çalışır ve noindex | ✓ |  |  |
| İptal edilen önizleme bağlantısı kapanır | ✓ |  |  |
| Giriş denemesi sınırı (6. deneme 429) | ✓ |  |  |

### F. Video ve ses dayanıklılığı

| Kontrol | Geçti | Kaldı | Harici erişim bekliyor |
|---|:---:|:---:|:---:|
| Video modunda açılış (dikey) | ✓ |  |  |
| İleri kaydırma videoyu ilerletir (%50 ≈ 4 sn) | ✓ |  |  |
| Geri kaydırma geri sarar (%20 ≈ 1,6 sn) | ✓ |  |  |
| Hızlı kaydırmada seek birikmez, son hedefe yetişir (%75 ≈ 6 sn) | ✓ |  |  |
| Video sessiz ve satır içi | ✓ |  |  |
| İsim/tarih %80–90 arasında belirir (%50’de gizli) | ✓ |  |  |
| Metin alanı x %12–88, y %28–68 içinde | ✓ |  |  |
| Ses dosyaları "Sesi aç" olmadan indirilmez | ✓ |  |  |
| Hata/404 yok | ✓ |  |  |
| İleri geçişte dört ses sırayla birer kez (%14, %30, %38, %61) | ✓ |  |  |
| Geri kaydırma ve eşik üzerinde küçük hareketler sesi tekrarlamaz | ✓ |  |  |
| "Animasyonu geç" ile sona atlamada dört ses arka arkaya çalmaz | ✓ |  |  |
| Animasyonu geç → ilerleme 1 ve odak başlıkta | ✓ |  |  |
| "Sesi aç" dört ses dosyasını yükler (404 yok) | ✓ |  |  |
| Ses düğmesi durumu (aria-pressed) | ✓ |  |  |
| Mühür düğmesi videodaki mühürle aynı yerde | ✓ |  |  |
| Mühre basınca basma efekti | ✓ |  |  |
| Basma sonrası açılış oynar (ilerleme 1) | ✓ |  |  |
| "Kaydırarak aç" klavyeyle (Enter) çalışır | ✓ |  |  |
| Video yok/düşük veri: poster maskeli CSS açılış | ✓ |  |  |
| CSS açılışta kapak döner ve mühür kapakla birlikte | ✓ |  |  |
| CSS açılışta 404 yok | ✓ |  |  |
| Video yüklenemezse CSS sürüme geçer | ✓ |  |  |
| Hareket azaltma: hafif sürüm (220svh yok) | ✓ |  |  |
| Hafif sürümde "Aç" isimleri gösterir | ✓ |  |  |
| JavaScript kapalıyken isim, tarih, adres ve RSVP erişilebilir | ✓ |  |  |

### F2. Hikâye videosu (resimli, duraklı kaydırma)

| Kontrol | Geçti | Kaldı | Harici erişim bekliyor |
|---|:---:|:---:|:---:|
| Hikâye bölümü açık ve kaydırma payı ayarlandı | ✓ |  |  |
| Hikâye videosu bölüme yaklaşmadan indirilmez | ✓ |  |  |
| Örnek içerikte altı durak (isim, geri sayım, nikâh, söz, yemek, konum) | ✓ |  |  |
| Durakta video durur (kaydırma sürerken zaman sabit ≈10,9 sn) | ✓ |  |  |
| Durak bilgisi görünür (nikâh saati HTML) | ✓ |  |  |
| Aynı anda yalnız bir durak bilgisi | ✓ |  |  |
| Geri kaydırmada önceki durağa döner (≈3,6 sn, isimler) | ✓ |  |  |
| Son durakta yol tarifi ve katılım düğmesi tıklanabilir | ✓ |  |  |
| Durak metinleri boş alana sığar (kesilmez) | ✓ |  |  |
| Hikâye etkinken mektupta geri sayım ve söz tekrar edilmez | ✓ |  |  |
| Hikâye: hata/404 yok | ✓ |  |  |
| Hareket azaltmada hikâye gizli, bilgiler normal akışta (geri sayım görünür) | ✓ |  |  |
| Video yok/düşük veride hikâye gizli | ✓ |  |  |
| Hikâye videosu yüklenemezse bölüm kaldırılır | ✓ |  |  |
| Masaüstünde hikâye videosu esnetilmez (ortalı, 9:16) | ✓ |  |  |

### I. Ekran genişlikleri ve cihazlar

| Kontrol | Geçti | Kaldı | Harici erişim bekliyor |
|---|:---:|:---:|:---:|
| 320px: yatay taşma yok (11 sayfa) | ✓ |  |  |
| 390px: yatay taşma yok (11 sayfa) | ✓ |  |  |
| 768px: yatay taşma yok (11 sayfa) | ✓ |  |  |
| 1440px: yatay taşma yok (11 sayfa) | ✓ |  |  |
| Masaüstü (yatay): dikey video esnetilmez → kenarlara uzanan CSS zarf | ✓ |  |  |
| Mobil yatay ekranda isimler kesilmez | ✓ |  |  |
| iPhone 13 emülasyonu (WebKit değil, Chromium) açılış modu video | ✓ |  |  |

## Harici erişim bekleyen doğrulamalar

| Alan | Kontrol | Geçti | Kaldı | Harici erişim bekliyor |
|---|---|:---:|:---:|---|
| B/G. Ödeme | Gerçek PayTR mağazasıyla test modunda token alma ve iFrame’de test kartıyla ödeme |  |  | ⏳ PayTR mağaza bilgileri ve canlı alan adı verilmedi; www.paytr.com bu ortamın ağından erişilemez. Token isteği yerel taklit sunucuyla, imza resmi formülle doğrulandı. |
| B/G. Ödeme | PayTR’nin gerçek bildirimi (callback) ve “Son doğrulanmış test/canlı bildirim” tarihinin dolması |  |  | ⏳ Bildirim, PayTR’nin imza formülüyle üretilen isteklerle test edildi; gerçek PayTR sunucusundan gelmedi. |
| B. Ödeme | Canlı modda gerçek kartla tahsilat |  |  | ⏳ Mağaza bilgileri ve canlı alan adı gerekli. |
| B. Ödeme | PayTR resmi dokümanının (iframe-api-1/2-adim) güncel hâliyle alan/imza sırası karşılaştırması ve RU ödeme dili desteği |  |  | ⏳ dev.paytr.com bu ortamdan açılamadı (ağ politikası). Alanlar ve imza sırası PayTR’nin bilinen PHP örneğiyle ve açık kaynak bir entegrasyonla karşılaştırıldı; RU için “en” kullanılıyor. |
| E-posta | SMTP ile gerçek e-posta teslimi (ödeme, taslak, revizyon, onay, yayın, parola sıfırlama) |  |  | ⏳ SMTP hesabı verilmedi. E-postaların doğru dilde, tek sefer (dedupe) kuyruğa alındığı test edildi; gönderim denenmedi. |
| Kurulum | Gerçek cPanel hostingde ZIP kurulumu, HTTPS ve Apache/LiteSpeed .htaccess davranışı |  |  | ⏳ Hosting erişimi yok. Kurulum PHP 8.3 + MariaDB 10.11 ile yerel sunucuda, ana dizin ve /ikimizden alt dizininde test edildi. |
| I. Cihazlar | Fiziksel iPhone’da iOS Safari (video kaydırma, güvenli alan, sticky, ses) |  |  | ⏳ Fiziksel cihaz yok. iPhone 13 ölçüleri yalnız Chromium emülasyonuyla denendi; WebKit/Safari çalıştırılmadı. |
| I. Cihazlar | Fiziksel Android telefonda Chrome |  |  | ⏳ Fiziksel cihaz yok; Pixel 7 yatay emülasyonu Chromium ile denendi. |
| Paylaşım | WhatsApp’ta gerçek bağlantı önizlemesi (Open Graph görsel/başlık) |  |  | ⏳ Herkese açık alan adı gerekli; meta etiketleri sayfada doğrulandı. |

## Notlar
- Ödeme testlerinde `storage/config.php` içine yalnız yerel test için `paytr_token_url` (taklit sunucu `tests/mock_paytr.php`) eklendi. Üretim ZIP’inde bu ayar yoktur; uygulama `https://www.paytr.com/odeme/api/get-token` adresini kullanır.
- Bildirim testleri PayTR’nin resmi imza formülüyle (`base64(hmac_sha256(merchant_oid + salt + status + total_amount, key))`) üretilen isteklerle yapıldı.
- Görsel kontroller (320/390/768/1440 px, mobil yatay, JS kapalı, video yok) ekran görüntüleriyle de incelendi; `tests/_out/shots/`.
- Tarayıcı testleri yalnız Chromium motorunda çalıştı; Safari/WebKit ve Firefox çalıştırılmadı.
