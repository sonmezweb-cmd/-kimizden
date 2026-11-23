# İkimizden — Kurulum ve Güncelleme Kılavuzu (cPanel)

Bu kılavuz, İkimizden dijital davetiye platformunu paylaşımlı bir cPanel hostinge kurmayı ve sonradan güncellemeyi anlatır. Node.js, SSH, Docker, Redis veya sürekli çalışan bir işlem **gerekmez**.

## 1. Sunucu gereksinimleri

| Gereksinim | Değer |
|---|---|
| PHP | 8.2 veya üzeri (8.3 önerilir) |
| PHP eklentileri | pdo_mysql, mbstring, intl, sodium, gd (WebP destekli), fileinfo, curl, json, openssl |
| Veritabanı | MySQL 8 veya MariaDB 10.4+ (InnoDB, utf8mb4) |
| Web sunucusu | Apache veya LiteSpeed, `mod_rewrite` açık |
| HTTPS | Canlı ödeme için zorunlu (PayTR bildirim adresi HTTPS olmalı) |

cPanel → **MultiPHP Manager** ile PHP sürümünü, **Select PHP Version → Extensions** ile eklentileri açın. Kurulum sihirbazı bunların hepsini kontrol eder ve eksik olanı kırmızıyla gösterir.

Önerilen PHP ayarları (cPanel → MultiPHP INI Editor): `upload_max_filesize = 64M`, `post_max_size = 70M`, `memory_limit = 256M`, `max_execution_time = 60`. Tema videosu yüklerken bu sınırlar yeterli olmalıdır.

## 2. ZIP içeriği

```
ikimizden-cpanel-1.0.0.zip
├── ikimizden/            ← uygulama: web kökünün DIŞINA
│   ├── app/              PHP kaynak kodu, şablonlar, dil dosyaları
│   ├── bin/              migrate.php, cron.php (isteğe bağlı komut satırı)
│   ├── database/         migration SQL dosyaları
│   ├── storage/          yapılandırma, loglar, yedekler, özel yüklemeler (yazılabilir)
│   └── vendor/           PHP bağımlılıkları (PHPMailer, php-qrcode) — hazır
└── public_html/          ← web kökü içeriği (index.php, .htaccess, assets/, themes/)
```

Yapılandırma (`storage/config.php`), loglar, yedekler ve müşteri fotoğrafları **web kökü dışında** kalır. Web kökünde yalnız `index.php`, statik dosyalar ve herkese açık tema medyası bulunur.

## 3. Yerleşim örnekleri

### A) Ana alan adında (https://ikimizden.com)

```
/home/KULLANICI/
├── ikimizden/                 ← ZIP'teki ikimizden/ klasörü
└── public_html/               ← ZIP'teki public_html/ içeriği buraya
    ├── index.php
    ├── .htaccess
    ├── app-path.php           ← (yalnız gerekirse, aşağıya bakın)
    ├── assets/
    └── themes/
```

`public_html/index.php` uygulama klasörünü **kendiliğinden bulur**: `public_html/` ile aynı seviyedeki `ikimizden/` (yerleşim A) ve iki üst seviyedeki `ikimizden/` (yerleşim B) otomatik tanınır. Uygulamayı başka bir yere ya da başka bir adla koyarsanız `public_html/app-path.php` dosyasıyla yolunu bildirin:

```php
<?php return '/home/KULLANICI/baska-klasor';
```

`.htaccess` bu dosyanın dışarıdan okunmasını engeller.

### B) Alt dizinde (https://alanadi.com/ikimizden)

```
/home/KULLANICI/
├── ikimizden-app/             ← ZIP'teki ikimizden/ klasörü (adı serbest)
└── public_html/
    └── ikimizden/             ← ZIP'teki public_html/ içeriği buraya
        ├── index.php
        ├── .htaccess
        └── app-path.php       ← <?php return '/home/KULLANICI/ikimizden-app';
                                  (klasör adı "ikimizden" ve public_html ile kardeşse gerekmez)
```

`.htaccess` alt dizinde değişiklik gerektirmeden çalışır. Bağlantılar, statik dosyalar, ödeme dönüşleri, PayTR bildirim adresi ve QR bağlantıları kurulumda girdiğiniz **temel URL**'den üretilir (ör. `https://alanadi.com/ikimizden`).

### C) Alt alan adı (https://davetiye.alanadi.com)

cPanel → Domains bölümünde alt alan adının belge kökünü (ör. `/home/KULLANICI/davetiye_public`) gösterin; ZIP'teki `public_html/` içeriğini oraya, `ikimizden/` klasörünü belge kökünün dışına koyun ve `app-path.php` ile yolunu bildirin.

## 4. Adım adım kurulum

1. **Veritabanı:** cPanel → *MySQL Database Wizard* ile boş bir veritabanı ve kullanıcı oluşturun, kullanıcıya veritabanında **ALL PRIVILEGES** verin.
2. **Dosyalar:** cPanel → *File Manager* ile ZIP'i `/home/KULLANICI/` altına yükleyip açın, klasörleri yukarıdaki yerleşimlerden birine göre taşıyın.
3. **İzinler:** `ikimizden/storage/` ve altındaki klasörler ile `public_html/themes/` yazılabilir olmalı (genellikle 755 klasör / 644 dosya yeterlidir; sihirbaz kontrol eder).
4. **Sihirbaz:** Tarayıcıda sitenizi açın; otomatik olarak `/install` sayfasına yönlendirilirsiniz.
   - Sunucu kontrolleri tamamen yeşil olmalı.
   - **Temel URL**: sitenin tam adresi, alt dizin dahil, sonda `/` olmadan (ör. `https://alanadi.com/ikimizden`).
   - Veritabanı bilgileri ve **ilk yönetici** (ad, e-posta, en az 10 karakterli parola).
5. **Kurulumu tamamla**: sihirbaz tabloları oluşturur, üç temayı ve üç paketi ekler, **uygulama şifreleme anahtarını** üretip `storage/config.php`'ye yazar, ilk yöneticiyi oluşturur ve `storage/install.lock` ile kendini **kilitler**. Kilitten sonra `/install` adresi 404 döner.

> **Önemli:** `storage/config.php` içindeki `app_key`, PayTR ve SMTP sırlarını şifreler. Bu anahtar veritabanında tutulmaz. Dosyayı yedekleyin; kaybederseniz PayTR/SMTP sırlarını yeniden girmeniz gerekir. Dosyayı kimseyle paylaşmayın.

Kurulum veritabanına **demo müşteri veya test siparişi eklemez**. Yalnız gerçek katalog gelir: Gül Mührü, Zeytin Varak, Sedef Kıvrımı temaları ve Standart, Özel, Atölye paketleri (fiyatsız).

## 5. Kurulumdan sonra

1. `/login` ile yönetici olarak giriş yapın.
2. **Paketler ve fiyatlar**: fiyatları girin (fiyatı boş paketin önizlemesi çalışır, ödeme başlatılamaz).
3. **Ayarlar → Şirket ve iletişim**: şirket unvanı, adres, telefon, e-posta, vergi bilgileri. Boş alanlar sitede gösterilmez; hiçbir bilgi uydurulmaz.
4. **Ayarlar → Yasal metinler**: gizlilik, KVKK, mesafeli satış ve çerez metinlerini hukuk danışmanınızla hazırlayıp her dil için girin.
5. **Ayarlar → E-posta (SMTP)**: sunucu bilgilerini girin ve test e-postası gönderin.
6. **Ayarlar → Ödemeler · PayTR**: bkz. `PAYTR-YONETICI.md`.
7. **Kullanıcılar → Tasarımcılar**: tasarımcı hesaplarını oluşturun.

### İsteğe bağlı cron (e-posta kuyruğu)

E-postalar ilgili işlemden hemen sonra gönderilir; SMTP geçici olarak hata verirse kuyrukta kalır ve **Yönetim → E-postalar** ekranından yeniden denenebilir. Otomatik yeniden deneme için cPanel → *Cron Jobs*:

```
*/10 * * * * /usr/local/bin/php /home/KULLANICI/ikimizden/bin/cron.php >/dev/null 2>&1
```

## 6. Güncelleme

1. **Yedek alın:** Yönetim → Sistem → *Şimdi yedekle* (veritabanı `storage/backups/` altına `.sql.gz` olarak yazılır, indirilebilir) ve `storage/config.php` dosyasını bilgisayarınıza indirin.
2. Yeni ZIP'teki `ikimizden/app`, `ikimizden/database`, `ikimizden/vendor`, `ikimizden/bin` klasörlerini mevcutların üzerine yazın. **`storage/` klasörünü değiştirmeyin.**
3. `public_html/index.php`, `.htaccess` ve `assets/` klasörünü güncelleyin. `themes/` klasöründe yönetimden yüklediğiniz medya varsa **üzerine yazmayın**; yalnız yeni temaları ekleyin.
4. Yönetim → Sistem ekranında bekleyen veritabanı güncellemeleri görünür: *Güncellemeleri uygula*. (SSH varsa: `php bin/migrate.php`.)
5. Yönetim → Sistem → *Önbelleği temizle*.

## 7. Sorun giderme

| Belirti | Çözüm |
|---|---|
| Tüm sayfalar 404 | `.htaccess` yüklenmemiş veya `mod_rewrite` kapalı. Gizli dosyaları göster ayarıyla kontrol edin. |
| Beyaz sayfa / 500 | `ikimizden/storage/logs/` altındaki log dosyasına bakın. PHP sürümü ve eklentileri sihirbazdaki listeyle karşılaştırın. |
| "Uygulama şifreleme anahtarı eksik" | `storage/config.php` bozulmuş veya yanlış kopyalanmış; yedekten geri yükleyin. |
| Video kaydırmada takılıyor | Sunucunun video dosyaları için `Range` isteklerini desteklediğini kontrol edin (Apache/LiteSpeed varsayılan olarak destekler). |
| PayTR ödeme sonrası "doğrulanıyor"da kalıyor | Bildirim adresi PayTR panelinde tanımlı değil veya HTTPS değil. `PAYTR-YONETICI.md`. |

## 8. Yerel geliştirme (isteğe bağlı)

```bash
php -S 127.0.0.1:8080 -t public public/router.php   # router.php video için bayt aralığı desteği ekler
bash tests/run_clean.sh                              # temiz kurulum + uçtan uca testler
node tests/e2e/engine.js http://127.0.0.1:8080       # tarayıcı senaryoları (Playwright)
```
