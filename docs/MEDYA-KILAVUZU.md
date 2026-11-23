# Tema Medyası Kılavuzu

Her temanın medyası herkese açık `themes/{tema}/` klasöründedir ve **bütün müşteriler için ortaktır**. İsimler, tarih, adres ve müşterinin yazdığı metinler hiçbir zaman görsele veya videoya gömülmez; veritabanından okunur ve sayfada **gerçek HTML metni** olarak görselin üzerine yazılır. Metin değiştiğinde yeni medya üretmek gerekmez.

## 1. Dosya düzeni

```
themes/{tema}/
├── poster.webp           ZORUNLU — ilk kare (1080×1920, 9:16). Videonun ilk karesiyle aynı olmalı.
├── preview.webp          katalog kartı görseli (yoksa poster kullanılır)
├── opening.mp4           açılış videosu — 8 sn, 9:16, 1080×1920, 24 fps, SESSİZ (H.264)
├── opening.webm          aynı videonun VP9 kopyası (isteğe bağlı; H.264 çözemeyen tarayıcılar için)
├── opening-wide.mp4      isteğe bağlı yatay video (masaüstü)
├── audio/ornament.*      desen ışığı sesi  (%14)
├── audio/seal.*          mühür sesi        (%30)
├── audio/flap.*          kapak sesi        (%38)
├── audio/paper.*         kâğıt sesi        (%61)
├── seal.webp             mühür katmanı (şeffaf) — mühür düğmesi ve CSS açılışı
├── body.webp             zarf gövdesi (kapak ve mühür çıkarılmış, şeffaf)
├── ornament.webp         kapak kabartması (mühür çıkarılmış, şeffaf)
├── texture.webp          kenarlara uzatılan malzeme dokusu (masaüstü)
├── paper.webp            iç kâğıt dokusu (mektup bölümü ve CSS açılışı)
├── sheen-mask.webp       ışık geçişinin yalnız kabartma desende görünmesi için maske
└── theme.json            geometri (poster yüzdeleri), renkler, parıltı noktaları
```

Tüm dosyalar **Yönetim → Temalar ve medya → (tema)** ekranından yüklenebilir, değiştirilebilir veya kaldırılabilir. Poster kaldırılamaz, yalnız değiştirilebilir. Ses seviyesi ve aktif/pasif durumu aynı ekrandadır. Yüklemelerde gerçek dosya tipi ve boyutu kontrol edilir; görseller sunucuda WebP'ye dönüştürülür; `themes/` klasöründe kod çalıştırma `.htaccess` ile kapalıdır.

## 2. Ne yoksa ne olur?

| Durum | Davetiyenin davranışı |
|---|---|
| Video var (dikey ekran) | Kaydırma video zamanına bağlanır: ileri → ileri sarar, geri → geri sarar. |
| Video yok / yüklenemedi / düşük veri modu | Poster, tema geometrisine göre maskelenir: kapak ve mühür birlikte açılır, alttan iç kâğıt çıkar (CSS). |
| Masaüstü (yatay ekran) | Dikey video **esnetilmez**. `opening-wide.mp4` varsa o; yoksa doku, kapak ve kat çizgileri ekran kenarlarına uzanan CSS zarf. |
| Hareket azaltma tercihi | Hafif sürüm: zarf görseli + "Aç" ile soluk geçiş. |
| Ses dosyası yok | Sessiz çalışır; "Sesi aç" düğmesi hiç gösterilmez, 404 isteği yapılmaz. |
| JavaScript kapalı | Poster + altında isim, tarih, program, adres ve katılım formu normal akışta. |

## 3. Video sözleşmesi ve zaman çizelgesi

Kaydırma ilerlemesi (%) ile video zamanı aynı çizelgeye bağlıdır:

| İlerleme | Zaman | Görüntü |
|---|---|---|
| %0–12,5 | 0,0–1,0 sn | Kapalı zarf, hafif parıltılar. Ekranda yerelleştirilmiş "Kaydırarak aç". |
| %12,5–30 | 1,0–2,4 sn | Yalnız kabartma desen boyunca **tek, ince** ışık geçişi. |
| %30–60 | 2,4–4,8 sn | Mühür alttaki kâğıttan ayrılır, kapağın üzerinde kalır, kapakla birlikte açılır. |
| %60–80 | 4,8–6,4 sn | **Boş** iç kâğıt yükselir ve ekranı doldurur. |
| %80–90 | 6,4–7,2 sn | (Videoda değil) İsimler ve tarih web sayfasında HTML olarak belirir. |
| %90–100 | 7,2–8,0 sn | Kâğıt sabit. |

Metin, son ekranın x %12–88 ve y %28–68 alanına yerleşir; videonun son karelerinde bu alan **boş kâğıt** olmalıdır.

**Teknik:** MP4/H.264, 1080×1920, 24 fps, 8,0 sn, ses yok, `+faststart`, kaydırmanın akıcı olması için **sık anahtar kare** (6 karede bir). Örnek dönüştürme:

```bash
ffmpeg -i kaynak.mp4 -an -c:v libx264 -profile:v high -pix_fmt yuv420p -crf 24 -g 6 -keyint_min 6 \
  -sc_threshold 0 -bf 0 -r 24 -vf scale=1080:1920 -movflags +faststart opening.mp4
ffmpeg -i kaynak.mp4 -an -c:v libvpx-vp9 -b:v 0 -crf 36 -g 6 -row-mt 1 -pix_fmt yuv420p opening.webm
```

Tanıtım için sesli video kullanabilirsiniz; ancak davetiyede kaydırmayla oynatılan video **sessizdir**. Sesler ayrı katmandan çalınır.

## 4. Sesler

| Dosya | Eşik | Gül Mührü | Zeytin Varak | Sedef Kıvrımı |
|---|---|---|---|---|
| `ornament` | %14 | saten fısıltısı | hafif yaprak hışırtısı | kısa, ince cam tınısı |
| `seal` | %30 | sıcak balmumu çıtı | yumuşak mühür sesi | zarif mühür ayrılması |
| `flap` | %38 | kalın kâğıt menteşesi | dokulu kâğıt kıvrımı | ince kabartmalı kâğıt |
| `paper` | %61 | pamuklu kâğıt kayması | kısa kâğıt kayması | parşömen kayması |

Kurulumla gelen sesler prosedürel olarak üretilmiştir (`tools/media/build_audio.py`). Kendi seslerinizi kısa (0,3–1,4 sn) MP3/M4A/OGG olarak yükleyin. Sesler yalnız misafir **Sesi aç**'a dokunursa çalar; her eşik bir açılışta bir kez; geri kaydırma, sekmeye dönme veya eşik üzerinde küçük hareketler tekrar çaldırmaz; sona atlandığında dördü arka arkaya çalmaz.

## 5. Yeni poster yüklediğinizde

CSS açılışı (video olmadığında) posteri tema geometrisine göre keser. Farklı kompozisyonlu bir poster yüklediyseniz **Zarf geometrisi** alanlarını güncelleyin (poster yüzdeleri): kapağın sol/sağ kenar yüksekliği, kapak ucu, mühür merkezi ve yarıçapı, cep çizgileri. Katman dosyalarını (`body`, `ornament`, `seal`, `texture`, `paper`, `sheen-mask`) en kolay şekilde geliştirme aracıyla üretebilirsiniz:

```bash
python3 tools/media/cut_layers.py zeytin.png gul.png sedef.png   # katmanlar + theme.json + Remotion katmanları
```

## 6. Açılış videolarını yeniden üretmek (Remotion)

Kurulumla gelen üç açılış videosu, verdiğiniz üç tema görselinden katmanlara ayrılarak **Remotion** ile yerelde üretilmiştir (harici API yok). Kaynak: `tools/opening-video/`.

```bash
python3 tools/media/cut_layers.py <zeytin> <gul> <sedef>      # katmanları üret
cd tools/opening-video && npm install
npx remotion studio                                            # önizleme
npx remotion render gul-muhru out/gul-muhru.mp4 --codec=h264 --crf=16
# ardından 3. bölümdeki ffmpeg komutlarıyla kaydırmaya uygun hale getirin
```

Kompozisyon `src/Opening.tsx` dosyasındadır: ışık geçişi, mühür ayrılması, 3B kapak dönüşü, kâğıdın yükselmesi ve parıltılar `useCurrentFrame()` ile zaman çizelgesine bağlıdır.

## 7. Video üretim promptları (isteğe bağlı)

Bir yapay zekâ video aracıyla kendi açılış videonuzu üretmek isterseniz aşağıdaki promptları kullanın. **Başlangıç karesi olarak tema görselini** (poster) verin. Ortak kurallar her prompta eklidir. Üretimden sonra 3. bölümdeki dönüştürmeyi uygulayın ve videonun ilk karesini `poster.webp` olarak yükleyin.

### Ortak kurallar (her promptun sonuna ekleyin)

```
Vertical 9:16, 1080x1920, 24 fps, exactly 8.0 seconds, NO audio, NO text, NO letters, NO numbers,
NO names, NO watermark. Locked-off top-down camera, no camera shake, no zoom, no cuts.
The envelope fills the entire frame edge to edge at all times until the card takes over — no table,
no background, no hands, no phone mockup, no frame or border around the envelope.
Timeline: 0.0–1.0s completely still closed envelope with faint sparkles; 1.0–2.4s ONE single thin
streak of light travels diagonally across ONLY the embossed ornament (nothing else glows);
2.4–4.8s the wax seal lifts off the lower paper while staying attached to the top flap, then the
flap opens upward toward the camera together with the seal; 4.8–6.4s a BLANK cream card rises out
of the envelope and smoothly scales up until it fills the whole frame; 6.4–8.0s the blank card is
perfectly still, evenly lit, completely empty in the central area (reserved for text added later).
Photorealistic, macro paper texture, soft diffused studio light, shallow depth of field, luxury
wedding stationery.
```

### Gül Mührü

```
Start from the provided image: a deep plum/burgundy velvet envelope seen from above, its top flap
covered with an engraved copper-foil rose bouquet with leaves and swirling vines, a dark burgundy
wax seal with an embossed leaf motif at the flap tip. The light streak is a warm copper-gold
glint running along the engraved rose petals and vines only, like light catching metal foil.
The velvet pile shimmers subtly as the flap moves. Inside of the envelope is a darker plum lining.
The rising card is thick cotton cream paper with a thin rose-copper border line.
[Ortak kurallar]
```

### Zeytin Varak

```
Start from the provided image: a soft sage-green textured cotton-paper envelope seen from above,
the top flap carries a raised, hand-sculpted olive tree with gnarled trunk, slender leaves, small
green olives and tiny gilded blossoms; an ivory wax seal pressed with an olive sprig sits at the
flap tip. The light streak is an antique-gold reflection travelling along the leaf veins and
gilded blossoms only. The paper fibres catch light gently as the flap folds. Inside lining is a
deeper olive green. The rising card is warm ivory paper with a fine antique-gold border.
[Ortak kurallar]
```

### Sedef Kıvrımı

```
Start from the provided image: a pearl-white iridescent envelope seen from above, the top flap is a
deeply embossed scallop-shell fan with flowing ribs; a lustrous mother-of-pearl wax seal at the
flap tip. The light streak is a delicate lilac-and-silver iridescent reflection sliding over a
few of the raised shell ridges only, like nacre turning in the light. Subtle pearlescent colour
shifts (lilac, silver, pale pink) as the flap rotates. Inside lining is a soft lavender-grey.
The rising card is bright pearl-white paper with a thin silver-lilac border.
[Ortak kurallar]
```

**Kontrol listesi:** son 1,6 saniyede kâğıt **tamamen boş ve sabit** mi? İlk kare posterle aynı mı? Zarf her karede ekranın dört köşesini dolduruyor mu? Video tam 8 sn mi? Sesi kaldırıldı mı?

## 8. Lisanslar

Tema görselleri İkimizden'e aittir (müşteri tarafından sağlanmıştır). Yazı tipleri SIL Open Font License 1.1 kapsamındadır (`public/assets/fonts/LICENSE-*.txt`). Ayrıntı: `LISANSLAR.md`.
