<?php
declare(strict_types=1);
/*
 * İkimizden — uçtan uca kabul testleri (A–H). Kurulu bir yerel sitede HTTP üzerinden çalışır.
 * Kullanım: php tests/acceptance.php http://127.0.0.1:8080 [rapor.json]
 * Ön koşul: kurulum tamam, admin@example.test / YonetimParola2026!, storage/config.php içinde
 *   'paytr_token_url' => 'http://127.0.0.1:8090/' (tests/mock_paytr.php).
 * Gerçek PayTR, alan adı ve SMTP ile yapılan doğrulama DEĞİLDİR.
 */
require __DIR__ . '/../app/bootstrap.php';

use App\DB;

$BASE = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$REPORT = $argv[2] ?? null;
$results = [];
$current = '';
function scenario(string $s): void { global $current; $current = $s; echo "\n== $s ==\n"; }
function check(string $name, bool $ok, string $detail = ''): bool {
    global $results, $current;
    $results[] = ['scenario' => $current, 'check' => $name, 'result' => $ok ? 'Geçti' : 'Kaldı', 'detail' => $detail];
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . ($detail && !$ok ? " — $detail" : '') . "\n";
    return $ok;
}

final class Client
{
    public string $jar;
    public int $code = 0;
    public string $body = '';
    public array $headers = [];
    public function __construct(public string $base) { $this->jar = tempnam(sys_get_temp_dir(), 'ikzjar'); }
    public function req(string $method, string $path, array $data = [], array $hdr = [], array $files = []): self
    {
        $url = str_starts_with($path, 'http') ? $path : $this->base . $path;
        $ch = curl_init($url);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $hdr, CURLOPT_TIMEOUT => 30];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($files) {
                $flat = [];
                $walk = function ($v, $k) use (&$walk, &$flat) { if (is_array($v)) { foreach ($v as $kk => $vv) $walk($vv, $k . '[' . $kk . ']'); } else $flat[$k] = $v; };
                foreach ($data as $k => $v) $walk($v, $k);
                foreach ($files as $k => $f) $flat[$k] = $f;
                $opts[CURLOPT_POSTFIELDS] = $flat;
            } else {
                $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
            }
        }
        curl_setopt_array($ch, $opts);
        $raw = (string)curl_exec($ch);
        $this->code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $this->headers = [];
        foreach (explode("\r\n", substr($raw, 0, $hs)) as $l) if (str_contains($l, ':')) { [$k, $v] = explode(':', $l, 2); $this->headers[strtolower(trim($k))] = trim($v); }
        $this->body = substr($raw, $hs);
        return $this;
    }
    public function get(string $p, array $h = []): self { return $this->req('GET', $p, [], $h); }
    public function csrf(string $path = '/'): string
    {
        $this->get($path);
        preg_match('/name="_csrf" value="([^"]+)"/', $this->body, $m);
        return $m[1] ?? '';
    }
    public function post(string $p, array $d, ?string $csrfFrom = null, array $files = [], array $hdr = []): self
    {
        $d['_csrf'] = $this->csrf($csrfFrom ?? $p);
        return $this->req('POST', $p, $d, $hdr, $files);
    }
    public function location(): string { return $this->headers['location'] ?? ''; }
    public function login(string $email, string $pw): self { return $this->post('/login', ['email' => $email, 'password' => $pw], '/login'); }
}

$sfx = substr(bin2hex(random_bytes(3)), 0, 6);
$ADMIN = ['admin@example.test', 'YonetimParola2026!'];
$admin = new Client($BASE);
$KEY = 'TESTkey' . $sfx . 'abcdef';
$SALT = 'TESTsalt' . $sfx . 'ghijkl';
$MID = '123456';

// ---------------------------------------------------------------- A
scenario('A. Kurulum → yönetici → fiyat → tema → katalog önizleme');
check('Kurulum sihirbazı kilitli (/install 404)', $admin->get('/install')->code === 404, (string)$admin->code);
$admin->login(...$ADMIN);
check('Yönetici girişi /admin yönlendirmesi', str_ends_with($admin->location(), '/admin'), $admin->location());
$pk = DB::all('SELECT id, code FROM packages');
$post = ['pkg' => []];
foreach ($pk as $p) $post['pkg'][$p['id']] = ['price' => ['standart' => '1.500,00', 'ozel' => '2.750,50', 'atolye' => '4.900'][$p['code']], 'currency' => 'TRY', 'revisions' => ['standart' => 1, 'ozel' => 2, 'atolye' => 6][$p['code']], 'delivery_days' => 5, 'max_languages' => $p['code'] === 'standart' ? 1 : 3, 'guest_groups' => $p['code'] === 'standart' ? '' : '1', 'is_on_sale' => '1', 'sort' => 1, 'name' => ['tr' => ucfirst($p['code']), 'en' => ucfirst($p['code']), 'ru' => ucfirst($p['code'])], 'scope' => ['tr' => "Madde 1\nMadde 2", 'en' => "Item 1\nItem 2", 'ru' => "Пункт 1\nПункт 2"]];
$admin->post('/admin/packages', $post, '/admin/packages');
$ozelPrice = (int)DB::val("SELECT price_minor FROM packages WHERE code = 'ozel'");
check('Paket fiyatı kuruş olarak kaydedildi (2.750,50 → 275050)', $ozelPrice === 275050, (string)$ozelPrice);
$theme = DB::one("SELECT * FROM themes WHERE slug = 'zeytin-varak'");
$admin->post('/admin/themes/' . $theme['id'], ['action' => 'save', 'is_active' => '1', 'sound_volume' => 55, 'sort' => 2,
    'name' => ['tr' => 'Zeytin Varak', 'en' => 'Olive Leaf', 'ru' => 'Оливковая ветвь'], 'desc' => json_decode($theme['description_json'], true)], '/admin/themes/' . $theme['id']);
check('Tema kaydı (aktif, ses %55)', (int)DB::val('SELECT sound_volume FROM themes WHERE id = ?', [$theme['id']]) === 55);
$pub = new Client($BASE);
$pub->get('/themes/zeytin-varak');
check('Katalog tema sayfasında fiyat görünür', str_contains($pub->body, '2.750,50') || str_contains($pub->body, '2.750,5'), 'fiyat yok');
$pub->get('/preview/zeytin-varak');
check('Canlı önizleme motoru açılır (zarf + gerçek HTML isim)', $pub->code === 200 && str_contains($pub->body, 'id="env"') && str_contains($pub->body, 'İpek'));
check('Katalog ilk açılışta video indirmez (sayfada <video> yok)', !str_contains($pub->get('/themes')->body, '<video'));
$pub->get('/en/preview/zeytin-varak');
check('EN önizlemede İngilizce örnek çift', str_contains($pub->body, 'Elizabeth'));

// PayTR ayarları (sahte mağaza; yerel taklit uç nokta)
$admin->post('/admin/settings/payments', ['merchant_id' => $MID, 'merchant_key' => $KEY, 'merchant_salt' => $SALT, 'enabled' => '1', 'mode' => 'live', 'max_installment' => 0], '/admin/settings/payments');
$raw = (string)DB::val("SELECT v FROM settings WHERE k = 'paytr.merchant_key'");
check('PayTR anahtarı şifreli saklanır (DB’de düz metin yok)', str_starts_with($raw, 'v1:') && !str_contains($raw, $KEY));
$admin->get('/admin/settings/payments');
check('Kayıtlı sır tarayıcıya gönderilmez', !str_contains($admin->body, $KEY) && !str_contains($admin->body, $SALT));
check('Bildirim adresi panelde gösterilir', str_contains($admin->body, '/paytr/callback'));
$admin->post('/admin/settings/payments', ['merchant_id' => $MID, 'merchant_key' => '', 'merchant_salt' => '', 'enabled' => '1', 'mode' => 'live'], '/admin/settings/payments');
check('Boş bırakılan sır alanı mevcut değeri korur', \App\Crypto::decrypt((string)DB::val("SELECT v FROM settings WHERE k = 'paytr.merchant_key'")) === $KEY);
$logs = implode('', array_map('file_get_contents', glob(\App\App::storagePath('logs') . '/*.log') ?: []));
check('Sırlar loglarda yok', !str_contains($logs, $KEY) && !str_contains($logs, $SALT));

// ---------------------------------------------------------------- B
scenario('B. Hesap → paket → ödeme → doğrulanmış bildirim → form → taslak → revizyon → onay → yayın');
$cust = new Client($BASE);
$email = "cift$sfx@example.test";
$cust->post('/register', ['name' => 'İpek Yılmaz', 'email' => $email, 'phone' => '+90 555 000 0000', 'password' => 'CiftParola2026!', 'accept' => '1'], '/register');
check('Müşteri hesabı oluşturuldu', (bool)DB::val('SELECT 1 FROM users WHERE email = ?', [$email]));
$ozel = (int)DB::val("SELECT id FROM packages WHERE code = 'ozel'");
$cust->post('/order/start', ['theme_id' => $theme['id'], 'package_id' => $ozel], '/themes/zeytin-varak');
$cust->get('/order/new');
$loc = $cust->location();
preg_match('#/checkout/([A-Z0-9]+)#', $loc, $m);
$NO = $m[1] ?? '';
check('Sipariş oluşturuldu ve özete yönlendirildi', $NO !== '', $loc);
$order = DB::one('SELECT * FROM orders WHERE public_no = ?', [$NO]);
$snap = json_decode($order['snapshot_json'], true);
check('Sipariş anlık kopyası (paket, fiyat, revizyon, para birimi)', $snap['package']['price_minor'] === 275050 && $snap['currency'] === 'TRY' && $snap['revisions_total'] === 2);
// fiyat değişikliği eski siparişi etkilemez
DB::q("UPDATE packages SET price_minor = 999900 WHERE code = 'atolye'");
check('Başka paketin fiyat değişikliği siparişi etkilemez', (int)DB::val('SELECT total_minor FROM orders WHERE id = ?', [$order['id']]) === 275050);
$cust->get('/ru/checkout/' . $NO);
check('RU özet sayfası ve PayTR dil açıklaması', $cust->code === 200 && str_contains($cust->body, 'на английском'));
$cust->post('/pay/' . $NO, [], '/checkout/' . $NO);
check('Ödeme başlatıldı → iFrame sayfası', str_contains($cust->location(), '/pay/' . $NO), $cust->location() . ' ' . DB::val('SELECT error_text FROM payment_attempts WHERE order_id = ? ORDER BY id DESC', [$order['id']]));
$mock = json_decode((string)@file_get_contents(sys_get_temp_dir() . '/mock_paytr_last.json'), true) ?: [];
check('Token isteği: kuruş tutar, TL, test_mode=0, imza alanları', ($mock['payment_amount'] ?? '') === '275050' && ($mock['currency'] ?? '') === 'TL' && ($mock['test_mode'] ?? '') === '0');
$hashStr = $mock['merchant_id'] . $mock['user_ip'] . $mock['merchant_oid'] . $mock['email'] . $mock['payment_amount'] . $mock['user_basket'] . $mock['no_installment'] . $mock['max_installment'] . $mock['currency'] . $mock['test_mode'];
check('paytr_token resmi sıra ile doğru', hash_equals(base64_encode(hash_hmac('sha256', $hashStr . $SALT, $KEY, true)), $mock['paytr_token'] ?? ''));
$cust->get('/pay/' . $NO);
check('iFrame sayfası PayTR güvenli adresini gösterir', str_contains($cust->body, 'https://www.paytr.com/odeme/guvenli/'));
$OID = (string)DB::val('SELECT merchant_oid FROM payment_attempts WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$order['id']]);
// başarı dönüş sayfasını doğrudan açmak ödenmiş yapmaz
$cust->get('/pay/' . $NO . '/return?r=ok');
check('Başarı dönüş sayfası siparişi ödenmiş yapmaz ("doğrulanıyor")', DB::val('SELECT payment_status FROM orders WHERE id = ?', [$order['id']]) === 'awaiting' && str_contains($cust->body, 'doğrulanıyor'));
$cb = fn(string $oid, string $status, string $total, string $test = '0', ?string $payAmt = null, ?string $hash = null) => (new Client($BASE))->req('POST', '/paytr/callback', array_filter([
    'merchant_oid' => $oid, 'status' => $status, 'total_amount' => $total, 'payment_amount' => $payAmt ?? $total, 'currency' => 'TL', 'test_mode' => $test, 'payment_type' => 'card',
    'hash' => $hash ?? base64_encode(hash_hmac('sha256', $oid . $SALT . $status . $total, $KEY, true)),
], fn($v) => $v !== null));
$r = $cb($OID, 'success', '280000', '0', '275050'); // taksit farkı içeren tahsilat
check('Geçerli bildirim → düz metin OK', $r->body === 'OK', $r->body);
$o = DB::one('SELECT * FROM orders WHERE id = ?', [$order['id']]);
check('Sipariş ödendi + yayın hakkı (canlı)', $o['payment_status'] === 'paid' && (int)$o['publish_right'] === 1);
check('Taksit farkı ayrı kaydedildi (ana 275050 / tahsil 280000)', (bool)DB::val('SELECT 1 FROM payments WHERE order_id = ? AND amount_minor = 275050 AND collected_minor = 280000', [$order['id']]));
check('Son doğrulanmış canlı bildirim tarihi güncellendi', (bool)DB::val("SELECT v FROM settings WHERE k = 'paytr.last_live_callback_at'"));
check('Ödendi ≠ çift onayladı ≠ yayında (bağımsız durumlar)', $o['design_status'] === 'info_pending' && $o['publish_status'] === 'unpublished');

// form adımları
$custForm = function (string $step, array $data) use ($cust, $NO) {
    $cust->get('/panel/orders/' . $NO . '/form/' . $step);
    preg_match('/name="_v" value="(\d+)"/', $cust->body, $m);
    return $cust->post('/panel/orders/' . $NO . '/form/' . $step, $data + ['_v' => $m[1] ?? 0], '/panel/orders/' . $NO . '/form/' . $step);
};
$custForm('names', ['names_first' => 'İpek', 'names_second' => 'Çağrı', 'names_order' => 'ab']);
$custForm('languages', ['languages' => ['tr', 'en', 'ru'], 'default_lang' => 'tr']);
$custForm('events', ['events' => [
    ['type' => 'civil', 'date' => '2027-06-12', 'start' => '17:30', 'end' => '18:30', 'tz' => 'Europe/Istanbul', 'venue' => 'Kalamış Nikâh Salonu', 'address' => 'Fenerbahçe, Kadıköy, İstanbul', 'map_url' => '', 'title' => ['tr' => '', 'en' => '', 'ru' => ''], 'desc' => ['tr' => '', 'en' => '', 'ru' => '']],
    ['type' => 'dinner', 'date' => '2027-06-12', 'start' => '20:00', 'end' => '01:30', 'tz' => 'Europe/Moscow', 'venue' => 'Усадьба Кусково', 'address' => 'Москва, ул. Юности, 2', 'map_url' => 'https://maps.google.com/?q=Kuskovo', 'title' => ['tr' => '', 'en' => '', 'ru' => ''], 'desc' => ['tr' => '', 'en' => '', 'ru' => '']],
]]);
$custForm('texts', ['texts' => [
    'tr' => ['intro' => 'Birlikte yeni bir yola', 'letter' => "Sevgili dostlarımız,\n\nBu mutluluğu sizinle paylaşmak istiyoruz.\nSevgiyle.", 'quote' => '', 'quote_by' => '', 'dress' => '', 'transport' => 'Servis 19:00’da kalkar.', 'stay' => '', 'notes' => ''],
    'en' => ['intro' => '', 'letter' => "Dear friends,\n\nWe would love to celebrate with you.", 'quote' => '', 'quote_by' => '', 'dress' => '', 'transport' => '', 'stay' => '', 'notes' => ''],
    'ru' => ['intro' => '', 'letter' => "Дорогие друзья!\n\nБудем рады видеть вас.", 'quote' => '', 'quote_by' => '', 'dress' => '', 'transport' => '', 'stay' => '', 'notes' => ''],
]]);
$custForm('details', ['cover_event_id' => '', 'contact_name' => 'İpek', 'contact_phone' => '+90 555 111 2233', 'rsvp_enabled' => '1', 'rsvp_ask_counts' => '1', 'rsvp_deadline' => '2027-05-31', 'rsvp_max_public' => 4]);
$inv = DB::one('SELECT * FROM invitations WHERE order_id = ?', [$order['id']]);
$draft = json_decode((string)DB::val('SELECT content_json FROM invitation_drafts WHERE invitation_id = ?', [$inv['id']]), true);
check('Her form adımı veritabanına kaydedildi', $draft['names']['first'] === 'İpek' && count($draft['events']) === 2 && $draft['texts']['ru']['letter'] !== '' && $draft['contact']['phone'] !== '');
check('Etkinlik zamanı UTC + IANA dilimi ayrı saklanır', $draft['events'][0]['start_utc'] === '2027-06-12T14:30:00Z' && $draft['events'][0]['tz'] === 'Europe/Istanbul');
check('Gece yarısını geçen etkinlik ertesi güne taşar', $draft['events'][1]['end_utc'] === '2027-06-12T22:30:00Z');
check('Paragraf ve satır sonları korunur', str_contains($draft['texts']['tr']['letter'], "\n\n") && str_contains($draft['texts']['tr']['letter'], "\nSevgiyle"));
// fotoğraf
$img = imagecreatetruecolor(800, 600); imagefill($img, 0, 0, imagecolorallocate($img, 200, 120, 140));
$tmp = tempnam(sys_get_temp_dir(), 'ph') . '.jpg'; imagejpeg($img, $tmp, 90);
$cust->post('/panel/orders/' . $NO . '/photos', [], '/panel/orders/' . $NO . '/photos', ['photos[0]' => new CURLFile($tmp, 'image/jpeg', 'kapak.jpg')]);
$mkey = (string)DB::val('SELECT mkey FROM media WHERE order_id = ?', [$order['id']]);
check('Fotoğraf yüklendi ve web kökü dışında saklandı', $mkey !== '' && is_file(\App\App::storagePath('uploads/' . DB::val('SELECT path FROM media WHERE mkey = ?', [$mkey]))));
$php = tempnam(sys_get_temp_dir(), 'ev') . '.jpg'; file_put_contents($php, "<?php echo 'x'; ?>");
$cust->post('/panel/orders/' . $NO . '/photos', [], '/panel/orders/' . $NO . '/photos', ['photos[0]' => new CURLFile($php, 'image/jpeg', 'kotu.php.jpg')]);
check('Sahte görsel (PHP içerik) reddedildi', (int)DB::val('SELECT COUNT(*) FROM media WHERE order_id = ?', [$order['id']]) === 1);
$cust->post('/panel/orders/' . $NO . '/submit-info', [], '/panel/orders/' . $NO . '/form/review');
check('Bilgiler tasarımcıya gönderildi', DB::val('SELECT design_status FROM orders WHERE id = ?', [$order['id']]) === 'in_design');
// tasarımcı
$demail = "tasarim$sfx@example.test";
$admin->post('/admin/users', ['action' => 'create', 'role' => 'designer', 'name' => 'Tasarımcı Deniz', 'email' => $demail, 'password' => 'TasarimParola2026!', 'locale' => 'tr'], '/admin/users?role=designer');
$did = (int)DB::val('SELECT id FROM users WHERE email = ?', [$demail]);
$admin->post('/admin/orders/' . $NO, ['action' => 'assign', 'designer_id' => $did], '/admin/orders/' . $NO);
$des = new Client($BASE);
$des->login($demail, 'TasarimParola2026!');
$des->get('/designer');
check('Tasarımcı yalnız atanmış siparişi görür', str_contains($des->body, $NO));
$des->get('/panel/orders/' . $NO . '/preview');
check('Tasarımcı paket kapsamı ve kalan revizyonu görür', str_contains($des->body, '2 / 2') || str_contains($des->body, '2 revizyon'));
$des->post('/panel/orders/' . $NO . '/send-version', ['note' => 'İlk taslak hazır.'], '/panel/orders/' . $NO . '/preview');
$v1 = DB::one('SELECT * FROM invitation_versions WHERE invitation_id = ? ORDER BY version_no DESC LIMIT 1', [$inv['id']]);
check('Sürüm 1 onaya gönderildi', $v1 && (int)$v1['version_no'] === 1 && DB::val('SELECT design_status FROM orders WHERE id = ?', [$order['id']]) === 'awaiting_approval');
$cust->post('/panel/orders/' . $NO . '/revision', ['version_id' => $v1['id'], 'message' => 'Yazı rengini biraz koyulaştıralım.'], '/panel/orders/' . $NO . '/preview');
$cust->post('/panel/orders/' . $NO . '/revision', ['version_id' => $v1['id'], 'message' => 'Yazı rengini biraz koyulaştıralım.'], '/panel/orders/' . $NO . '/preview');
check('Aynı revizyon iki kez gönderilince hak bir kez düşer', (int)DB::val('SELECT COUNT(*) FROM revision_requests WHERE order_id = ?', [$order['id']]) === 1);
$des->post('/panel/orders/' . $NO . '/send-version', ['note' => 'Düzeltildi.'], '/panel/orders/' . $NO . '/preview');
$v2 = DB::one('SELECT * FROM invitation_versions WHERE invitation_id = ? ORDER BY version_no DESC LIMIT 1', [$inv['id']]);
$cust->post('/panel/orders/' . $NO . '/approve', ['version_id' => $v1['id']], '/panel/orders/' . $NO . '/preview');
check('Eski sürüm onaylanamaz', DB::val('SELECT status FROM invitation_versions WHERE id = ?', [$v1['id']]) !== 'approved');
$cust->post('/panel/orders/' . $NO . '/approve', ['version_id' => $v2['id']], '/panel/orders/' . $NO . '/preview');
$o = DB::one('SELECT * FROM orders WHERE id = ?', [$order['id']]);
check('Sürüm 2 onaylandı ve yayımlandı', $o['design_status'] === 'approved' && $o['publish_status'] === 'published');
$SLUG = $inv['slug'];
$guest = new Client($BASE);
$guest->get('/i/' . $SLUG);
check('Kalıcı bağlantı yayında (200, noindex)', $guest->code === 200 && str_contains($guest->headers['x-robots-tag'] ?? '', 'noindex'));
check('E-postalar kuyrukta (ödeme, taslak, yayın)', (int)DB::val("SELECT COUNT(*) FROM email_queue WHERE dedupe_key LIKE ? OR dedupe_key IN (?, ?, ?)", ['paid:' . $order['id'] . ':%', 'draft_ready:' . $v1['id'], 'draft_ready:' . $v2['id'], 'published:' . $v2['id']]) === 4);

// ---------------------------------------------------------------- C
scenario('C. Misafir: zarf → isim/tarih → ikinci etkinlik konumu → katılım → panelde toplam');
check('İsimler gerçek HTML başlıkta', (bool)preg_match('#<h1[^>]*>.*İpek.*Çağrı.*</h1>#s', $guest->body));
check('Kapak tarihi yerelleştirilmiş', str_contains($guest->body, '12 Haziran 2027'));
check('İkinci etkinlik kendi harita bağlantısını taşır', str_contains($guest->body, 'https://maps.google.com/?q=Kuskovo'));
check('Mekân yerel saatiyle (Moskova 20:00 – 01:30 +1 gün)', str_contains($guest->body, '20:00 – 01:30') && str_contains($guest->body, '+1'));
check('Geri sayım, Ara ve WhatsApp düğmeleri', str_contains($guest->body, 'data-countdown') && str_contains($guest->body, 'tel:+905551112233') && str_contains($guest->body, 'wa.me/905551112233'));
check('Boş isteğe bağlı bölümler gizli (konaklama yok)', !str_contains($guest->body, 'Konaklama'));
check('Takvime ekle (ICS) bağlantısı', str_contains($guest->body, '/ics/'));
$ics = (new Client($BASE))->get('/i/' . $SLUG . '/ics/' . $draft['events'][1]['id']);
check('ICS: TZID=Europe/Moscow, başlangıç 20:00, bitiş ertesi gün 01:30', str_contains($ics->body, 'DTSTART;TZID=Europe/Moscow:20270612T200000') && str_contains($ics->body, 'DTEND;TZID=Europe/Moscow:20270613T013000') && str_contains($ics->body, 'BEGIN:VTIMEZONE'));
$guest->post('/i/' . $SLUG . '/rsvp', ['l' => 'tr', 'attending' => '1', 'adults' => 2, 'children' => 1, 'name' => 'Ayşe Demir', 'note' => 'Sabırsızlanıyoruz'], '/i/' . $SLUG);
$guest->post('/i/' . $SLUG . '/rsvp', ['l' => 'tr', 'attending' => '1', 'adults' => 2, 'children' => 0, 'name' => 'Ayşe Demir', 'note' => 'Düzeltme'], '/i/' . $SLUG);
check('Aynı tarayıcıdan düzeltme önceki genel yanıtı günceller', (int)DB::val('SELECT COUNT(*) FROM rsvps WHERE invitation_id = ? AND group_id IS NULL', [$inv['id']]) === 1);
$cust->get('/panel/orders/' . $NO . '/rsvps');
check('Çift panelinde toplam doğru (2 kişi)', str_contains($cust->body, 'Toplam katılacak kişi: 2'));
$guest->get('/i/' . $SLUG . '?l=ru');
check('RU davetiye: Kiril isimler korunur, arayüz Rusça', str_contains($guest->body, 'lang="ru"') && str_contains($guest->body, 'Программа'));

// ---------------------------------------------------------------- D
scenario('D. İsim değişikliği → taslakta yeni isim → canlı korunur → onay sonrası aynı URL güncellenir');
$custForm('names', ['names_first' => 'İpek Su', 'names_second' => 'Çağrı', 'names_order' => 'ab']);
$cust->get('/panel/orders/' . $NO . '/render/draft');
check('Yeni isim taslak önizlemede', str_contains($cust->body, 'İpek Su'));
$g = (new Client($BASE))->get('/i/' . $SLUG);
check('Canlı sürüm eski isimle korunur', !str_contains($g->body, 'İpek Su'));
$des->post('/panel/orders/' . $NO . '/send-version', ['note' => 'İsim güncellendi'], '/panel/orders/' . $NO . '/preview');
$v3 = DB::one('SELECT * FROM invitation_versions WHERE invitation_id = ? ORDER BY version_no DESC LIMIT 1', [$inv['id']]);
check('Yeni sürüm otomatik onaylı değil', $v3['status'] === 'sent' && !str_contains((new Client($BASE))->get('/i/' . $SLUG)->body, 'İpek Su'));
$cust->post('/panel/orders/' . $NO . '/approve', ['version_id' => $v3['id']], '/panel/orders/' . $NO . '/preview');
$g = (new Client($BASE))->get('/i/' . $SLUG);
check('Onaydan sonra aynı URL yeni isimle', $g->code === 200 && str_contains($g->body, 'İpek Su'));

// ---------------------------------------------------------------- E
scenario('E. Diller, uzun Kiril isimler, saat dilimleri, eksik alanlar');
foreach (['tr' => 'Siparişlerim', 'en' => 'My orders', 'ru' => 'Мои заказы'] as $l => $txt) {
    $cust->get(($l === 'tr' ? '' : '/' . $l) . '/panel');
    check("Panel $l", str_contains($cust->body, $txt));
}
foreach (['en' => 'Orders', 'ru' => 'Заказы'] as $l => $txt) check("Yönetim paneli $l", str_contains($admin->get('/' . $l . '/admin')->body, $txt));
check('Tasarımcı paneli RU', str_contains($des->get('/ru/designer')->body, 'Назначенные заказы'));
$custForm('names', ['names_first' => 'Анастасия-Александра Константиновна', 'names_second' => 'Александр Владимирович', 'names_order' => 'ba']);
$cust->get('/panel/orders/' . $NO . '/render/draft?l=ru');
check('Uzun Kiril isimler özgün sırayla (ikinci & birinci)', (bool)preg_match('#Александр Владимирович.*Анастасия-Александра Константиновна#s', $cust->body));
$pubC = new Client($BASE);
foreach (['tr' => 'İpek', 'en' => 'Elizabeth', 'ru' => 'Анастасия'] as $l => $n) check("Vitrin önizleme $l ($n)", str_contains($pubC->get(($l === 'tr' ? '' : '/' . $l) . '/preview/gul-muhru')->body, $n));
check('Para biçimi dile göre (EN)', str_contains($pubC->get('/en/packages')->body, 'TRY') || str_contains($pubC->body, '₺'));
// dil değişiminde aynı adres
check('Dil bağlantısı aynı sayfada kalır', str_contains($cust->get('/panel/orders/' . $NO . '/form/events')->body, '/ru/panel/orders/' . $NO . '/form/events'));

// ---------------------------------------------------------------- G
scenario('G. Bildirim güvenliği: tekrar, geçersiz imza, tutar, dönüş sayfası, test/canlı');
$before = (int)DB::val('SELECT COUNT(*) FROM payments WHERE order_id = ?', [$order['id']]);
$mailsBefore = (int)DB::val('SELECT COUNT(*) FROM email_queue');
$oks = 0;
for ($i = 0; $i < 5; $i++) if ($cb($OID, 'success', '280000', '0', '275050')->body === 'OK') $oks++;
check('Aynı bildirim 5 kez → her biri OK', $oks === 5);
check('… tek ödeme, ek e-posta yok', (int)DB::val('SELECT COUNT(*) FROM payments WHERE order_id = ?', [$order['id']]) === $before && (int)DB::val('SELECT COUNT(*) FROM email_queue') === $mailsBefore);
check('Geçersiz imza reddedilir (OK değil)', $cb($OID, 'success', '280000', '0', '275050', base64_encode('sahte'))->body !== 'OK');
// ikinci sipariş: uyuşmayan tutar ve test modu
$cust2 = $cust;
$mk = function (string $mode) use ($admin, $cust, $theme, $ozel, $MID) {
    $admin->post('/admin/settings/payments', ['merchant_id' => $MID, 'merchant_key' => '', 'merchant_salt' => '', 'enabled' => '1', 'mode' => $mode], '/admin/settings/payments');
    $cust->post('/order/start', ['theme_id' => $theme['id'], 'package_id' => $ozel], '/themes/zeytin-varak');
    $cust->get('/order/new');
    preg_match('#/checkout/([A-Z0-9]+)#', $cust->location(), $m);
    $no = $m[1];
    $cust->post('/pay/' . $no, [], '/checkout/' . $no);
    return [$no, (string)DB::val('SELECT a.merchant_oid FROM payment_attempts a JOIN orders o ON o.id = a.order_id WHERE o.public_no = ? ORDER BY a.id DESC LIMIT 1', [$no])];
};
[$no2, $oid2] = $mk('live');
$r = $cb($oid2, 'success', '100', '0', '100');
check('Uyuşmayan tutar: kaydedilir, OK, incelemeye alınır, yayın hakkı yok', $r->body === 'OK' && DB::val('SELECT payment_status FROM orders WHERE public_no = ?', [$no2]) === 'review' && (int)DB::val('SELECT publish_right FROM orders WHERE public_no = ?', [$no2]) === 0);
[$no3, $oid3] = $mk('test');
check('Test modunda test_mode=1 gönderildi', (json_decode((string)file_get_contents(sys_get_temp_dir() . '/mock_paytr_last.json'), true)['test_mode'] ?? '') === '1');
$r = $cb($oid3, 'success', '275050', '1');
$o3 = DB::one('SELECT * FROM orders WHERE public_no = ?', [$no3]);
check('Test tahsilatı: test_paid, yayın hakkı açmaz', $r->body === 'OK' && $o3['payment_status'] === 'test_paid' && (int)$o3['publish_right'] === 0);
[$no5, $oid5] = $mk('live');
$r5 = $cb($oid5, 'success', '275050', '1');
check('Canlı denemeye test_mode=1 bildirimi uyuşmaz (OK değil, ödenmez)', $r5->body !== 'OK' && DB::val('SELECT payment_status FROM orders WHERE public_no = ?', [$no5]) === 'awaiting');
[$no4, $oid4] = $mk('live');
$r = $cb($oid4, 'failed', '275050', '0');
check('Başarısız bildirim: sipariş ödenmemiş, sepet korunur', $r->body === 'OK' && DB::val('SELECT payment_status FROM orders WHERE public_no = ?', [$no4]) === 'unpaid');
// aynı sipariş için ikinci gerçek tahsilat
$cust->post('/pay/' . $no4, [], '/checkout/' . $no4);
$oid4b = (string)DB::val('SELECT a.merchant_oid FROM payment_attempts a JOIN orders o ON o.id = a.order_id WHERE o.public_no = ? ORDER BY a.id DESC LIMIT 1', [$no4]);
$cb($oid4b, 'success', '275050', '0');
$o4 = DB::one('SELECT * FROM orders WHERE public_no = ?', [$no4]);
check('İlk başarılı tahsilat → ödendi', $o4['payment_status'] === 'paid');
// ilk (başarısız sayılan) denemeyi zorla açık bırakılmış gibi yeni bir deneme ile çift tahsilat
DB::q("INSERT INTO payment_attempts (order_id, merchant_oid, merchant_id, mode, amount_minor, currency, status, created_at, updated_at) VALUES (?, ?, ?, 'live', 275050, 'TRY', 'awaiting', NOW(), NOW())", [$o4['id'], 'IKZDUP' . $sfx, $MID]);
$cb('IKZDUP' . $sfx, 'success', '275050', '0');
check('Aynı siparişe iki gerçek tahsilat: ikisi de kaydedilir, incelemeye açılır', (int)DB::val('SELECT COUNT(*) FROM payments WHERE order_id = ?', [$o4['id']]) === 2 && (int)DB::val('SELECT needs_review FROM orders WHERE id = ?', [$o4['id']]) === 1);
check('Bilinmeyen merchant_oid geçerli imzayla günlüğe alınır', $cb('IKZYOK' . $sfx, 'success', '100', '0')->body === 'OK' && (bool)DB::val("SELECT 1 FROM payment_callbacks WHERE merchant_oid = ? AND outcome = 'unknown_oid'", ['IKZYOK' . $sfx]));
check('Bildirim uç noktası GET kabul etmez', (new Client($BASE))->get('/paytr/callback')->code === 405);

// ---------------------------------------------------------------- H
scenario('H. Yetki, eşzamanlı düzenleme, aile yanıtı');
$other = new Client($BASE);
$other->post('/register', ['name' => 'Başka Kullanıcı', 'email' => "baska$sfx@example.test", 'password' => 'BaskaParola2026!', 'accept' => '1'], '/register');
check('Başka müşteri siparişe erişemez (404)', $other->get('/panel/orders/' . $NO)->code === 404);
check('Başka müşteri formu kaydedemez', $other->post('/panel/orders/' . $NO . '/form/names', ['names_first' => 'X', '_v' => 1], '/panel')->code === 404);
$img2 = imagecreatetruecolor(640, 480); imagefill($img2, 0, 0, imagecolorallocate($img2, 90, 140, 120)); $tmp2 = tempnam(sys_get_temp_dir(), 'ph') . '.jpg'; imagejpeg($img2, $tmp2, 90);
$cust->post('/panel/orders/' . $NO . '/photos', [], '/panel/orders/' . $NO . '/photos', ['photos[0]' => new CURLFile($tmp2, 'image/jpeg', 'taslak.jpg')]);
$mkey2 = (string)DB::val('SELECT mkey FROM media WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$order['id']]);
check('Başka müşteri taslaktaki özel fotoğrafı göremez (404)', $mkey2 !== $mkey && $other->get('/m/' . $mkey2 . '/s')->code === 404);
check('Sahibi kendi fotoğrafını görür', $cust->get('/m/' . $mkey2 . '/s')->code === 200);
check('Canlı sürümdeki kapak fotoğrafı misafire açık', (new Client($BASE))->get('/m/' . $mkey . '/m')->code === 200);
check('Müşteri yönetim paneline giremez (403)', $cust->get('/admin')->code === 403);
check('Tasarımcı atanmamış siparişe erişemez', $des->get('/panel/orders/' . $no2)->code === 404);
check('CSRF jetonu olmadan POST reddedilir (419)', $cust->req('POST', '/panel/orders/' . $NO . '/form/names', ['names_first' => 'X'])->code === 419);
// eşzamanlı düzenleme
$cust->get('/panel/orders/' . $NO . '/form/names'); preg_match('/name="_v" value="(\d+)"/', $cust->body, $m); $vC = $m[1];
$des->get('/panel/orders/' . $NO . '/form/names'); preg_match('/name="_v" value="(\d+)"/', $des->body, $m); $vD = $m[1];
$des->post('/panel/orders/' . $NO . '/form/names', ['names_first' => 'Tasarımcı Yazdı', 'names_second' => 'Çağrı', 'names_order' => 'ab', '_v' => $vD], '/panel/orders/' . $NO . '/form/names');
$r = $cust->post('/panel/orders/' . $NO . '/form/names', ['names_first' => 'Müşteri Yazdı', 'names_second' => 'Çağrı', 'names_order' => 'ab', '_v' => $vC], '/panel/orders/' . $NO . '/form/names');
$now = json_decode((string)DB::val('SELECT content_json FROM invitation_drafts WHERE invitation_id = ?', [$inv['id']]), true);
check('Eşzamanlı düzenleme sessizce ezilmez (409 + çakışma gösterilir)', $r->code === 409 && $now['names']['first'] === 'Tasarımcı Yazdı' && str_contains($r->body, 'Müşteri Yazdı'));
$cust->get('/panel/orders/' . $NO . '/form/names'); preg_match('/name="_v" value="(\d+)"/', $cust->body, $m); $vC = $m[1];
$des->post('/panel/orders/' . $NO . '/form/names', ['_dirty' => ['names_first'], 'names_first' => 'Tasarımcı Yazdı', 'names_second' => 'eski', 'names_order' => 'ab', '_v' => $vC], '/panel/orders/' . $NO . '/form/names', [], ['Accept: application/json', 'X-Requested-With: fetch']);
$r = $cust->post('/panel/orders/' . $NO . '/form/names', ['_dirty' => ['names_second'], 'names_first' => 'eski', 'names_second' => 'Çağrı Can', 'names_order' => 'ab', '_v' => $vC], '/panel/orders/' . $NO . '/form/names', [], ['Accept: application/json', 'X-Requested-With: fetch']);
$now = json_decode((string)DB::val('SELECT content_json FROM invitation_drafts WHERE invitation_id = ?', [$inv['id']]), true);
check('Otomatik kayıt yalnız değişen alanı yazar; başkasının alanı korunur', $r->code === 200 && $now['names']['second'] === 'Çağrı Can' && $now['names']['first'] === 'Tasarımcı Yazdı');
// aile bağlantısı
$cust->post('/panel/orders/' . $NO . '/guests', ['action' => 'add', 'names' => "Yılmaz Ailesi; 4\nСемья Ивановых; 3", 'max_guests' => 2], '/panel/orders/' . $NO . '/guests');
$grp = DB::one('SELECT * FROM guest_groups WHERE invitation_id = ? ORDER BY id LIMIT 1', [$inv['id']]);
check('Aile grubu ve tahmin edilemeyen özel bağlantı', $grp && strlen($grp['token']) === 24 && (int)$grp['max_guests'] === 4);
$fam = new Client($BASE);
$fam->get('/i/' . $SLUG . '/g/' . $grp['token']);
check('Özel bağlantıda aile adı görünür, paylaşım önizlemesinde görünmez', str_contains($fam->body, 'Yılmaz Ailesi') && !preg_match('/og:[a-z]+" content="[^"]*Yılmaz Ailesi/', $fam->body));
check('Genel sayfada aile adı görünmez', !str_contains((new Client($BASE))->get('/i/' . $SLUG)->body, 'Yılmaz Ailesi'));
$fam->post('/i/' . $SLUG . '/rsvp', ['l' => 'tr', 'g' => $grp['token'], 'attending' => '1', 'adults' => 3, 'children' => 1], '/i/' . $SLUG . '/g/' . $grp['token']);
$fam->post('/i/' . $SLUG . '/rsvp', ['l' => 'tr', 'g' => $grp['token'], 'attending' => '1', 'adults' => 2, 'children' => 1], '/i/' . $SLUG . '/g/' . $grp['token']);
$fam->post('/i/' . $SLUG . '/rsvp', ['l' => 'tr', 'g' => $grp['token'], 'attending' => '1', 'adults' => 5, 'children' => 1], '/i/' . $SLUG . '/g/' . $grp['token']);
check('Aynı aile yanıtı toplamı şişirmez (tek kayıt, son geçerli: 3 kişi)', (int)DB::val('SELECT COUNT(*) FROM rsvps WHERE group_id = ?', [$grp['id']]) === 1 && (int)DB::val('SELECT adults + children FROM rsvps WHERE group_id = ?', [$grp['id']]) === 3);
$cust->get('/panel/orders/' . $NO . '/rsvps');
check('Panelde davet edilen / yanıtlayan / yanıt vermeyen ayrı', str_contains($cust->body, 'Toplam katılacak kişi: 5'));
$csv = $cust->get('/panel/orders/' . $NO . '/rsvps.csv');
check('CSV UTF-8 (BOM) ve Kiril aile adı', str_starts_with($csv->body, "\xEF\xBB\xBF") && str_contains($csv->body, 'Семья Ивановых'));
// son tarih
$cur = json_decode((string)DB::val('SELECT content_json FROM invitation_versions WHERE id = (SELECT live_version_id FROM invitations WHERE id = ?)', [$inv['id']]), true);
$cur['rsvp']['deadline'] = '2020-01-01';
DB::q('UPDATE invitation_versions SET content_json = ? WHERE id = (SELECT live_version_id FROM invitations WHERE id = ?)', [json_encode($cur, JSON_UNESCAPED_UNICODE), $inv['id']]);
$n0 = (int)DB::val('SELECT COUNT(*) FROM rsvps WHERE invitation_id = ?', [$inv['id']]);
(new Client($BASE))->post('/i/' . $SLUG . '/rsvp', ['l' => 'tr', 'attending' => '1', 'adults' => 1, 'name' => 'Geç Kalan'], '/i/' . $SLUG);
check('Son tarihten sonra sunucu yeni yanıt kabul etmez', (int)DB::val('SELECT COUNT(*) FROM rsvps WHERE invitation_id = ?', [$inv['id']]) === $n0);
// yayından kaldırma / arşiv
$admin->post('/admin/orders/' . $NO, ['action' => 'archive'], '/admin/orders/' . $NO);
check('Arşivlenen davetiye misafire kapanır', (new Client($BASE))->get('/i/' . $SLUG)->code === 404);
// önizleme bağlantısı
$cust->post('/panel/orders/' . $NO . '/preview-link', ['version_id' => $v3['id'], 'days' => 1], '/panel/orders/' . $NO . '/preview');
$cust->get('/panel/orders/' . $NO . '/preview');
preg_match('#value="(http[^"]+/p/[A-Za-z0-9]+)"#', $cust->body, $m);
$plink = $m[1] ?? '';
$pv = (new Client($BASE))->get($plink);
check('Süreli önizleme bağlantısı çalışır ve noindex', $pv->code === 200 && str_contains($pv->headers['x-robots-tag'] ?? '', 'noindex'));
$pid = (int)DB::val('SELECT id FROM preview_tokens WHERE invitation_id = ? ORDER BY id DESC LIMIT 1', [$inv['id']]);
$cust->post('/panel/orders/' . $NO . '/preview-link/revoke', ['id' => $pid], '/panel/orders/' . $NO . '/preview');
check('İptal edilen önizleme bağlantısı kapanır', (new Client($BASE))->get($plink)->code === 404);
// giriş denemesi sınırı
$bf = new Client($BASE);
for ($i = 0; $i < 6; $i++) $bf->login($email, 'yanlis-parola-' . $i);
check('Giriş denemesi sınırı (6. deneme 429)', $bf->code === 429);

$pass = count(array_filter($results, fn($r) => $r['result'] === 'Geçti'));
echo "\nSonuç: $pass / " . count($results) . " geçti\n";
if ($REPORT) file_put_contents($REPORT, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
exit($pass === count($results) ? 0 : 1);
