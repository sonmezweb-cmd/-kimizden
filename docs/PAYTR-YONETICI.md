# PayTR — Yönetici Kılavuzu

Bu kısa kılavuz, PayTR mağaza bilgilerinizi **nereye gireceğinizi** ve **bildirim adresini nereye ekleyeceğinizi** anlatır. Kod düzenlemeniz gerekmez.

## 1. Bilgileri girin

**Yönetim → Ayarlar → Ödemeler · PayTR**

| Alan | PayTR panelindeki karşılığı |
|---|---|
| Mağaza numarası · `merchant_id` | PayTR Mağaza Paneli → Destek & Kurulum → Entegrasyon Bilgileri |
| Mağaza anahtarı · `merchant_key` | aynı ekran |
| Mağaza gizli anahtarı · `merchant_salt` | aynı ekran |

- **Kaydet** dediğinizde anahtar ve gizli anahtar **şifrelenerek** saklanır. Şifreleme anahtarı veritabanında değil, sunucudaki `storage/config.php` dosyasındadır.
- Kayıtlı bir sır **tarayıcıya geri gönderilmez**; alan boş görünür ve altında "Kayıtlı" yazar. Değiştirmek için yeni değeri yazın; **boş bırakırsanız mevcut değer korunur**.
- Sırlar loglarda ve hata mesajlarında yer almaz.

## 2. Bildirim adresini PayTR'ye tanımlayın

Aynı ekranın üstünde **Bildirim (callback) adresi** kutusu vardır (ör. `https://alanadiniz.com/paytr/callback`).

> **Bu bildirim adresini kopyalayıp PayTR Mağaza Panelinizde tanımlayın.**

PayTR Mağaza Paneli → **Destek & Kurulum → Ayarlar → Bildirim URL** alanına yapıştırıp kaydedin. Adres **HTTPS** olmalıdır; site adresiniz HTTPS değilse ekranda uyarı görürsünüz.

## 3. Test → Canlı

1. **Mod: Test** ve **Çevrim içi ödemeyi etkinleştir** işaretleyip kaydedin.
2. Sitede bir müşteri hesabıyla küçük bir sipariş verin, PayTR test kartıyla ödeyin.
3. Ekrandaki **Son doğrulanmış test bildirimi** tarihinin dolduğunu görün. Bu tarih yalnız PayTR'den gelen ve **imzası doğrulanan gerçek bildirimle** güncellenir. Anahtarları kaydetmek tek başına bu tarihi değiştirmez.
4. Test ödemesi siparişi **"Test ödemesi"** durumuna alır ve **ücretli yayın hakkı açmaz**.
5. Her şey yolundaysa **Mod: Canlı** seçip kaydedin. İlk gerçek ödemeden sonra **Son doğrulanmış canlı bildirim** tarihi dolar.

## 4. Nasıl çalışır (özet)

- Sepet ve toplam **sunucuda yeniden hesaplanır**; tutar tam sayı kuruş olarak gönderilir (ör. 2.750,50 ₺ → `275050`). Para birimi varsayılan TRY'dir (PayTR'ye `TL` olarak gider); site dili değişse de tahsilat para birimi değişmez.
- Her ödeme denemesinin benzersiz, yalnız harf ve rakamdan oluşan `merchant_oid` değeri vardır.
- Kart bilgileri PayTR iFrame içinde girilir; sitenize ulaşmaz.
- Site dili Rusça iken PayTR formu İngilizce açılır ve bu müşteriye Rusça açıklanır. (PayTR iFrame dil parametresi `tr`/`en` olarak kullanılmıştır; PayTR Rusça form desteği eklerse yalnız `PayTR::lang()` güncellenir.)
- Müşterinin başarı sayfasını açması siparişi **ödenmiş yapmaz**; durum yalnız bildirimle değişir. Bildirim gecikirse müşteri "Ödemeniz doğrulanıyor" görür ve sayfa kendiliğinden güncellenir.
- Bildirim: imza resmi yöntemle doğrulanır, doğru denemeye/mağazaya/moda eşlenir, veritabanı işleminde kaydedilir. Aynı bildirim 5 kez gelse de **tek ödeme, tek e-posta** oluşur ve her seferinde `OK` döner. Geçersiz imzaya `OK` dönülmez. Kayıt başarısızsa `OK` dönülmez (PayTR yeniden dener). SMTP hatası ödeme kaydını bozmaz.
- Sipariş ana tutarı (`payment_amount`) ile taksit farkı içerebilen tahsilat toplamı (`total_amount`) ayrı saklanır. Tutar uyuşmazsa ya da aynı siparişe ikinci gerçek tahsilat gelirse ikisi de kaydedilir ve sipariş **"İnceleme gerekli"** olarak işaretlenir (Yönetim → Siparişler).
- PayTR'ye geçici olarak ulaşılamazsa müşterinin sepeti korunur, tekrar deneyebilir.

## 5. İnceleme gereken siparişler

Yönetim → Siparişler → **İnceleme gerekli** filtresi. Sipariş ekranında ödeme denemeleri, tahsilatlar ve PayTR bildirim günlüğü (imza sonucu dahil) görünür. Gerekirse PayTR panelinden iade yapın, ardından *İncelemeyi kapat*.

## 6. Çevrim dışı ödeme

Havale/EFT ile ödeme alırsanız sipariş ekranında **Çevrim dışı ödeme kaydet** (not zorunlu değil ama önerilir). Bu işlem yayın hakkı açar ve işlem geçmişine yazılır.
