<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Audit;
use App\Auth;
use App\Catalog;
use App\DB;
use App\I18n;
use App\Invitations;
use App\Mailer;
use App\Migrator;
use App\Orders;
use App\Payments;
use App\PayTR;
use App\Settings;
use App\View;

final class AdminController
{
    private function show(string $tpl, array $data): void
    {
        View::show('admin/' . $tpl, $data + ['robots' => 'noindex'], 'panel');
    }

    public function dashboard(): void
    {
        $c = fn(string $sql, array $p = []) => (int)DB::val($sql, $p);
        $this->show('dashboard', ['title' => t('admin.dashboard'), 'stats' => [
            'orders' => $c('SELECT COUNT(*) FROM orders'),
            'paid' => $c("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid'"),
            'review' => $c('SELECT COUNT(*) FROM orders WHERE needs_review = 1'),
            'unassigned' => $c("SELECT COUNT(*) FROM orders WHERE designer_id IS NULL AND payment_status IN ('paid','test_paid')"),
            'awaiting_approval' => $c("SELECT COUNT(*) FROM orders WHERE design_status = 'awaiting_approval'"),
            'published' => $c("SELECT COUNT(*) FROM orders WHERE publish_status = 'published'"),
            'mail_failed' => $c("SELECT COUNT(*) FROM email_queue WHERE status = 'failed'"),
            'mail_pending' => $c("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'"),
            'messages' => $c('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'),
            'unpriced' => $c('SELECT COUNT(*) FROM packages WHERE is_on_sale = 1 AND price_minor IS NULL'),
        ], 'paytr' => ['enabled' => PayTR::enabled(), 'configured' => PayTR::configured(), 'mode' => PayTR::mode()],
           'smtp' => Mailer::configured(), 'pending' => Migrator::pending()]);
    }

    // ------------------------------------------------------------ siparişler

    public function orders(): void
    {
        $where = ['1=1']; $p = [];
        $f = ['q' => trim((string)($_GET['q'] ?? '')), 'pay' => (string)($_GET['pay'] ?? ''), 'design' => (string)($_GET['design'] ?? ''), 'pub' => (string)($_GET['pub'] ?? '')];
        if ($f['q'] !== '') { $where[] = '(o.public_no LIKE ? OR u.email LIKE ? OR u.name LIKE ?)'; $q = '%' . $f['q'] . '%'; array_push($p, $q, $q, $q); }
        if ($f['pay'] !== '') { $where[] = 'o.payment_status = ?'; $p[] = $f['pay']; }
        if ($f['design'] !== '') { $where[] = 'o.design_status = ?'; $p[] = $f['design']; }
        if ($f['pub'] !== '') { $where[] = 'o.publish_status = ?'; $p[] = $f['pub']; }
        if (!empty($_GET['review'])) $where[] = 'o.needs_review = 1';
        $rows = DB::all('SELECT o.*, u.email, u.name AS customer, d.name AS designer FROM orders o JOIN users u ON u.id = o.user_id LEFT JOIN users d ON d.id = o.designer_id WHERE ' . implode(' AND ', $where) . ' ORDER BY o.id DESC LIMIT 300', $p);
        $this->show('orders', ['title' => t('admin.orders'), 'rows' => $rows, 'f' => $f]);
    }

    public function order(string $no): void
    {
        $o = Orders::find($no) ?? abort(404);
        $inv = Invitations::forOrder((int)$o['id']);
        $this->show('order', [
            'title' => t('panel.order') . ' ' . $no, 'order' => $o, 'inv' => $inv,
            'attempts' => DB::all('SELECT * FROM payment_attempts WHERE order_id = ? ORDER BY id DESC', [$o['id']]),
            'payments' => DB::all('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$o['id']]),
            'callbacks' => DB::all('SELECT c.* FROM payment_callbacks c JOIN payment_attempts a ON a.merchant_oid = c.merchant_oid WHERE a.order_id = ? ORDER BY c.id DESC LIMIT 50', [$o['id']]),
            'versions' => Invitations::versions((int)$inv['id']),
            'designers' => DB::all("SELECT id, name, email FROM users WHERE role = 'designer' AND is_active = 1 ORDER BY name"),
            'audit' => DB::all("SELECT a.*, u.name AS who FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity = 'order' AND a.entity_id = ? ORDER BY a.id DESC LIMIT 100", [$o['id']]),
            'revUsed' => Orders::revisionsUsed((int)$o['id']),
        ]);
    }

    public function orderAction(string $no): void
    {
        $o = Orders::find($no) ?? abort(404);
        $a = (string)($_POST['action'] ?? '');
        $admin = (int)Auth::id();
        switch ($a) {
            case 'assign':
                $did = (int)($_POST['designer_id'] ?? 0);
                $d = $did ? DB::one("SELECT * FROM users WHERE id = ? AND role = 'designer'", [$did]) : null;
                DB::update('orders', ['designer_id' => $d ? $did : null, 'updated_at' => DB::now()], 'id = ?', [$o['id']]);
                Audit::log('order.assigned', 'order', (int)$o['id'], ['designer' => $d['email'] ?? null]);
                if ($d) Mailer::queueTemplate($d['email'], $d['locale'], 'info_submitted', ['order' => $no], 'assign:' . $o['id'] . ':' . $did);
                break;
            case 'manual_paid':
                if (in_array($o['payment_status'], ['paid'], true)) { flash('error', t('pay.err.already')); break; }
                Payments::markManual($o, $admin, str_in('note', 400));
                break;
            case 'grant_right':
                DB::update('orders', ['publish_right' => 1, 'updated_at' => DB::now()], 'id = ?', [$o['id']]);
                Audit::log('order.publish_right_granted', 'order', (int)$o['id'], ['note' => str_in('note', 400)]);
                break;
            case 'revoke_right':
                DB::update('orders', ['publish_right' => 0, 'updated_at' => DB::now()], 'id = ?', [$o['id']]);
                Audit::log('order.publish_right_revoked', 'order', (int)$o['id']);
                break;
            case 'clear_review':
                $paidLive = (int)DB::val("SELECT COUNT(*) FROM payments WHERE order_id = ? AND mode IN ('live','manual')", [$o['id']]);
                DB::update('orders', ['needs_review' => 0, 'payment_status' => $paidLive ? 'paid' : ($o['payment_status'] === 'review' ? 'test_paid' : $o['payment_status']),
                    'publish_right' => $paidLive ? 1 : (int)$o['publish_right'], 'paid_at' => $o['paid_at'] ?? DB::now(), 'updated_at' => DB::now()], 'id = ?', [$o['id']]);
                Audit::log('order.review_cleared', 'order', (int)$o['id'], ['note' => str_in('note', 400)]);
                break;
            case 'publish':
                $inv = Invitations::forOrder((int)$o['id']);
                $vid = (int)DB::val("SELECT id FROM invitation_versions WHERE invitation_id = ? AND status = 'approved' ORDER BY version_no DESC LIMIT 1", [$inv['id']]);
                $r = $vid ? Invitations::publish($o, $vid, $admin, true) : ['ok' => false, 'error' => 'publish.err.not_approved'];
                if (!$r['ok']) { flash('error', t($r['error'])); redirect(url('/admin/orders/' . $no)); }
                break;
            case 'unpublish':
                Invitations::unpublish($o, false);
                break;
            case 'archive':
                Invitations::unpublish($o, true);
                break;
            default:
                abort(400);
        }
        flash('success', t('common.saved'));
        redirect(url('/admin/orders/' . $no));
    }

    // ------------------------------------------------------------ kullanıcılar

    public function users(): void
    {
        $role = in_array($_GET['role'] ?? '', ['customer', 'designer', 'admin'], true) ? $_GET['role'] : 'customer';
        $rows = DB::all('SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders_count, (SELECT COUNT(*) FROM orders o WHERE o.designer_id = u.id) AS assigned_count FROM users u WHERE role = ? ORDER BY u.id DESC LIMIT 500', [$role]);
        $this->show('users', ['title' => t('admin.users'), 'rows' => $rows, 'role' => $role]);
    }

    public function usersSave(): void
    {
        $a = (string)($_POST['action'] ?? '');
        if ($a === 'create') {
            $email = mb_strtolower(str_in('email', 190));
            $role = in_array($_POST['role'] ?? '', ['designer', 'admin'], true) ? $_POST['role'] : 'designer';
            $pw = (string)($_POST['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !Auth::passwordOk($pw) || str_in('name', 120) === '') { flash('error', t('v.check_fields')); redirect(url('/admin/users', null, ['role' => $role])); }
            if (DB::val('SELECT 1 FROM users WHERE email = ?', [$email])) { flash('error', t('auth.email_taken')); redirect(url('/admin/users', null, ['role' => $role])); }
            $id = DB::insert('users', ['email' => $email, 'password_hash' => Auth::hash($pw), 'name' => str_in('name', 120), 'role' => $role,
                'locale' => I18n::valid($_POST['locale'] ?? '') ? $_POST['locale'] : 'tr', 'is_active' => 1, 'created_at' => DB::now(), 'updated_at' => DB::now()]);
            Audit::log('user.created', 'user', $id, ['role' => $role]);
            redirect(url('/admin/users', null, ['role' => $role]));
        }
        if ($a === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === Auth::id()) { flash('error', t('admin.cannot_self')); redirect(url('/admin/users')); }
            DB::q('UPDATE users SET is_active = 1 - is_active, updated_at = ? WHERE id = ?', [DB::now(), $id]);
            Audit::log('user.toggled', 'user', $id);
        }
        flash('success', t('common.saved'));
        redirect(url('/admin/users', null, ['role' => (string)($_POST['role'] ?? 'customer')]));
    }

    // ------------------------------------------------------------ paketler

    public function packages(): void
    {
        $this->show('packages', ['title' => t('admin.packages'), 'packages' => Catalog::packages(), 'addons' => Catalog::addons(false)]);
    }

    public static function parseMoney(string $s): ?int
    {
        $s = trim(str_replace([' ', '₺'], '', $s));
        if ($s === '') return null;
        // "1.250,50" veya "1250.50"
        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d{1,2})?$/', $s)) $s = str_replace(['.', ','], ['', '.'], $s);
        else $s = str_replace(',', '.', $s);
        if (!is_numeric($s) || (float)$s < 0) return null;
        return (int)round((float)$s * 100);
    }

    public function packagesSave(): void
    {
        foreach ((array)($_POST['pkg'] ?? []) as $id => $p) {
            $scope = [];
            $name = [];
            foreach (I18n::LANGS as $l) {
                $name[$l] = mb_substr(trim((string)($p['name'][$l] ?? '')), 0, 80);
                $scope[$l] = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)($p['scope'][$l] ?? '')) ?: [])));
            }
            DB::update('packages', [
                'name_json' => jenc($name), 'scope_json' => jenc($scope),
                'price_minor' => self::parseMoney((string)($p['price'] ?? '')),
                'currency' => in_array($p['currency'] ?? 'TRY', ['TRY', 'EUR', 'USD'], true) ? $p['currency'] : 'TRY',
                'revisions' => max(0, min(50, (int)($p['revisions'] ?? 0))), 'delivery_days' => max(1, min(90, (int)($p['delivery_days'] ?? 5))),
                'max_languages' => max(1, min(3, (int)($p['max_languages'] ?? 1))), 'guest_groups' => !empty($p['guest_groups']) ? 1 : 0,
                'is_on_sale' => !empty($p['is_on_sale']) ? 1 : 0, 'sort' => (int)($p['sort'] ?? 0), 'updated_at' => DB::now(),
            ], 'id = ?', [(int)$id]);
        }
        foreach ((array)($_POST['addon'] ?? []) as $id => $p) {
            $name = []; $desc = [];
            foreach (I18n::LANGS as $l) { $name[$l] = mb_substr(trim((string)($p['name'][$l] ?? '')), 0, 80); $desc[$l] = mb_substr(trim((string)($p['desc'][$l] ?? '')), 0, 300); }
            DB::update('addons', ['name_json' => jenc($name), 'description_json' => jenc($desc), 'price_minor' => self::parseMoney((string)($p['price'] ?? '')),
                'extra_revisions' => max(0, min(10, (int)($p['extra_revisions'] ?? 0))), 'is_active' => !empty($p['is_active']) ? 1 : 0, 'updated_at' => DB::now()], 'id = ?', [(int)$id]);
        }
        Audit::log('catalog.packages_saved');
        flash('success', t('admin.packages_saved'));
        redirect(url('/admin/packages'));
    }

    // ------------------------------------------------------------ temalar ve medya

    public function themes(): void
    {
        $this->show('themes', ['title' => t('admin.themes'), 'themes' => Catalog::themes(false)]);
    }

    public function theme(string $id): void
    {
        $t = Catalog::theme((int)$id) ?? abort(404);
        $this->show('theme', ['title' => loc($t['name']), 'theme' => $t]);
    }

    public const MEDIA_SLOTS = [
        'poster' => ['file' => 'poster.webp', 'kind' => 'image', 'max' => 12],
        'preview' => ['file' => 'preview.webp', 'kind' => 'image', 'max' => 12],
        'video' => ['file' => 'opening.mp4', 'kind' => 'video', 'max' => 60],
        'video_webm' => ['file' => 'opening.webm', 'kind' => 'webm', 'max' => 60],
        'video_wide' => ['file' => 'opening-wide.mp4', 'kind' => 'video', 'max' => 80],
        'texture' => ['file' => 'texture.webp', 'kind' => 'image', 'max' => 8],
        'ornament' => ['file' => 'ornament.webp', 'kind' => 'image_alpha', 'max' => 8],
        'seal' => ['file' => 'seal.webp', 'kind' => 'image_alpha', 'max' => 4],
        'paper' => ['file' => 'paper.webp', 'kind' => 'image', 'max' => 8],
        'audio_ornament' => ['file' => 'audio/ornament', 'kind' => 'audio', 'max' => 3],
        'audio_seal' => ['file' => 'audio/seal', 'kind' => 'audio', 'max' => 3],
        'audio_flap' => ['file' => 'audio/flap', 'kind' => 'audio', 'max' => 3],
        'audio_paper' => ['file' => 'audio/paper', 'kind' => 'audio', 'max' => 3],
    ];

    public function themeSave(string $id): void
    {
        $t = Catalog::theme((int)$id) ?? abort(404);
        $dir = App::publicPath('themes/' . $t['slug']);
        $a = (string)($_POST['action'] ?? 'save');
        if ($a === 'save') {
            $name = []; $desc = [];
            foreach (I18n::LANGS as $l) { $name[$l] = mb_substr(trim((string)($_POST['name'][$l] ?? '')), 0, 80); $desc[$l] = mb_substr(trim((string)($_POST['desc'][$l] ?? '')), 0, 600); }
            $cfg = $t['config'];
            $geo = [];
            foreach (['flapLeft', 'flapRight', 'flapTipX', 'flapTipY', 'sealX', 'sealY', 'sealR', 'pocketSide', 'pocketTipY'] as $g) {
                if (isset($_POST['geo'][$g]) && is_numeric($_POST['geo'][$g])) $geo[$g] = max(0, min(100, (float)$_POST['geo'][$g]));
            }
            if ($geo) $cfg['geometry'] = $geo;
            DB::update('themes', ['name_json' => jenc($name), 'description_json' => jenc($desc), 'config_json' => jenc($cfg),
                'sound_volume' => max(0, min(100, (int)($_POST['sound_volume'] ?? 60))), 'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                'sort' => (int)($_POST['sort'] ?? 0), 'updated_at' => DB::now()], 'id = ?', [$t['id']]);
            Audit::log('theme.saved', 'theme', (int)$t['id']);
            flash('success', t('common.saved'));
        } elseif ($a === 'upload') {
            $slot = (string)($_POST['slot'] ?? '');
            $def = self::MEDIA_SLOTS[$slot] ?? abort(400);
            $f = $_FILES['file'] ?? null;
            $err = $this->storeThemeFile($dir, $def, $f);
            if ($err) flash('error', t($err));
            else {
                DB::q('UPDATE themes SET media_rev = media_rev + 1, updated_at = ? WHERE id = ?', [DB::now(), $t['id']]);
                Audit::log('theme.media_uploaded', 'theme', (int)$t['id'], ['slot' => $slot]);
                flash('success', t('admin.media_uploaded'));
            }
        } elseif ($a === 'remove') {
            $slot = (string)($_POST['slot'] ?? '');
            $def = self::MEDIA_SLOTS[$slot] ?? abort(400);
            if ($slot === 'poster') { flash('error', t('admin.poster_required')); redirect(url('/admin/themes/' . $id)); }
            foreach (glob($dir . '/' . $def['file'] . ($def['kind'] === 'audio' ? '.*' : '')) ?: [] as $file) @unlink($file);
            DB::q('UPDATE themes SET media_rev = media_rev + 1 WHERE id = ?', [$t['id']]);
            Audit::log('theme.media_removed', 'theme', (int)$t['id'], ['slot' => $slot]);
            flash('success', t('common.deleted'));
        }
        redirect(url('/admin/themes/' . $id));
    }

    private function storeThemeFile(string $dir, array $def, ?array $f): ?string
    {
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) return 'upload.err.failed';
        if ($f['size'] > $def['max'] * 1024 * 1024) return 'upload.err.size';
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
        @mkdir($dir . '/audio', 0775, true);
        switch ($def['kind']) {
            case 'image': case 'image_alpha':
                if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) return 'upload.err.type';
                $img = match ($mime) { 'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']), 'image/png' => @imagecreatefrompng($f['tmp_name']), default => @imagecreatefromwebp($f['tmp_name']) };
                if (!$img) return 'upload.err.type';
                imagepalettetotruecolor($img);
                imagealphablending($img, false);
                imagesavealpha($img, true);
                $tmp = $dir . '/.' . bin2hex(random_bytes(4));
                imagewebp($img, $tmp, 84);
                rename($tmp, $dir . '/' . $def['file']);
                if ($def['file'] === 'poster.webp') {
                    $w = imagesx($img); $h = imagesy($img);
                    $s = imagecreatetruecolor(360, (int)round(360 * $h / max(1, $w)));
                    imagecopyresampled($s, $img, 0, 0, 0, 0, imagesx($s), imagesy($s), $w, $h);
                    imagewebp($s, $dir . '/poster-small.webp', 78);
                }
                return null;
            case 'video':
                if ($mime !== 'video/mp4' || $ext !== 'mp4') return 'upload.err.type';
                move_uploaded_file($f['tmp_name'], $dir . '/' . $def['file']);
                return null;
            case 'webm':
                if ($mime !== 'video/webm' || $ext !== 'webm') return 'upload.err.type';
                move_uploaded_file($f['tmp_name'], $dir . '/' . $def['file']);
                return null;
            case 'audio':
                $map = ['audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'm4a', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'video/mp4' => 'm4a'];
                if (!isset($map[$mime]) || !in_array($ext, ['mp3', 'm4a', 'ogg', 'wav'], true)) return 'upload.err.type';
                foreach (glob($dir . '/' . $def['file'] . '.*') ?: [] as $old) @unlink($old);
                move_uploaded_file($f['tmp_name'], $dir . '/' . $def['file'] . '.' . $map[$mime]);
                return null;
        }
        return 'upload.err.type';
    }

    // ------------------------------------------------------------ çeviriler

    public function translations(): void
    {
        $lang = I18n::valid($_GET['tl'] ?? null) ? $_GET['tl'] : 'tr';
        $q = trim((string)($_GET['q'] ?? ''));
        $prefix = (string)($_GET['p'] ?? '');
        $base = I18n::base($lang);
        $tr = I18n::base('tr');
        $over = [];
        foreach (DB::all('SELECT k, v FROM translations WHERE lang = ?', [$lang]) as $r) $over[$r['k']] = $r['v'];
        $keys = array_keys($tr);
        $groups = array_values(array_unique(array_map(fn($k) => explode('.', $k)[0], $keys)));
        $keys = array_filter($keys, function ($k) use ($q, $prefix, $base, $over) {
            if ($prefix !== '' && !str_starts_with($k, $prefix . '.')) return false;
            if ($q === '') return true;
            return stripos($k, $q) !== false || stripos($base[$k] ?? '', $q) !== false || stripos($over[$k] ?? '', $q) !== false;
        });
        $this->show('translations', ['title' => t('admin.translations'), 'tl' => $lang, 'q' => $q, 'p' => $prefix, 'groups' => $groups,
            'keys' => array_slice(array_values($keys), 0, 200), 'base' => $base, 'tr' => $tr, 'over' => $over]);
    }

    public function translationsSave(): void
    {
        $lang = I18n::valid($_POST['tl'] ?? null) ? $_POST['tl'] : 'tr';
        $base = I18n::base($lang);
        foreach ((array)($_POST['t'] ?? []) as $k => $v) {
            if (!isset(I18n::base('tr')[$k])) continue;
            $v = str_replace("\r\n", "\n", trim((string)$v));
            if ($v === '' || $v === ($base[$k] ?? null)) DB::q('DELETE FROM translations WHERE lang = ? AND k = ?', [$lang, $k]);
            else DB::q('INSERT INTO translations (lang, k, v, updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = VALUES(updated_at)', [$lang, $k, mb_substr($v, 0, 5000), DB::now()]);
        }
        I18n::clearCache();
        Audit::log('translations.saved', null, null, ['lang' => $lang]);
        flash('success', t('common.saved'));
        redirect(url('/admin/translations', null, ['tl' => $lang, 'q' => (string)($_POST['q'] ?? ''), 'p' => (string)($_POST['p'] ?? '')]));
    }

    // ------------------------------------------------------------ e-postalar

    public function emails(): void
    {
        $st = in_array($_GET['st'] ?? '', ['pending', 'sent', 'failed'], true) ? $_GET['st'] : null;
        $rows = $st ? DB::all('SELECT * FROM email_queue WHERE status = ? ORDER BY id DESC LIMIT 200', [$st]) : DB::all('SELECT * FROM email_queue ORDER BY id DESC LIMIT 200');
        $this->show('emails', ['title' => t('admin.emails'), 'rows' => $rows, 'st' => $st, 'smtp' => Mailer::configured()]);
    }

    public function emailsAction(): void
    {
        $a = (string)($_POST['action'] ?? '');
        if ($a === 'retry') {
            $id = (int)($_POST['id'] ?? 0);
            DB::q("UPDATE email_queue SET status = 'pending', attempts = 0 WHERE id = ? AND status <> 'sent'", [$id]);
            $r = Mailer::processQueue(1, $id);
            flash($r['sent'] ? 'success' : 'error', $r['sent'] ? t('mail.sent_ok') : t('mail.sent_fail'));
        } elseif ($a === 'process') {
            DB::q("UPDATE email_queue SET status = 'pending', attempts = 0 WHERE status = 'failed'");
            $r = Mailer::processQueue(50);
            flash('info', t('mail.processed', ['sent' => $r['sent'], 'failed' => $r['failed']]));
        }
        redirect(url('/admin/emails'));
    }

    // ------------------------------------------------------------ ayarlar

    public const GENERAL_KEYS = ['site.name', 'company.name', 'company.address', 'company.phone', 'company.email', 'company.whatsapp', 'company.tax', 'company.mersis', 'company.hours', 'mail.admin_notify', 'social.instagram'];

    public function settings(string $section): void
    {
        if (!in_array($section, ['general', 'payments', 'smtp', 'legal'], true)) abort(404);
        $data = ['title' => t('admin.settings.' . $section), 'section' => $section];
        if ($section === 'legal') {
            $pages = [];
            foreach (DB::all('SELECT * FROM pages') as $p) $pages[$p['k']][$p['lang']] = $p;
            $data['pages'] = $pages;
        }
        if ($section === 'payments') {
            $data['paytr'] = [
                'merchant_id' => (string)Settings::get('paytr.merchant_id', ''),
                'has_key' => Settings::hasSecret('paytr.merchant_key'), 'has_salt' => Settings::hasSecret('paytr.merchant_salt'),
                'enabled' => Settings::get('paytr.enabled') === '1', 'mode' => PayTR::mode(),
                'no_installment' => Settings::get('paytr.no_installment', '1') === '1', 'max_installment' => (int)Settings::get('paytr.max_installment', '0'),
                'callback' => PayTR::callbackUrl(), 'last_test' => Settings::get('paytr.last_test_callback_at'), 'last_live' => Settings::get('paytr.last_live_callback_at'),
                'recent' => DB::all('SELECT merchant_oid, status, mode, hash_valid, outcome, created_at FROM payment_callbacks ORDER BY id DESC LIMIT 15'),
            ];
        }
        if ($section === 'smtp') $data['has_pass'] = Settings::hasSecret('smtp.pass');
        $this->show('settings', $data);
    }

    public function settingsSave(string $section): void
    {
        switch ($section) {
            case 'general':
                foreach (self::GENERAL_KEYS as $k) Settings::set($k, mb_substr(trim((string)($_POST[str_replace('.', '_', $k)] ?? '')), 0, 500));
                break;
            case 'payments':
                $mid = trim((string)($_POST['merchant_id'] ?? ''));
                if ($mid !== '' && !preg_match('/^\d{1,20}$/', $mid)) { flash('error', t('paytr.err.merchant_id')); redirect(url('/admin/settings/payments')); }
                Settings::set('paytr.merchant_id', $mid);
                // boş bırakılan sır alanı mevcut değeri korur; kayıtlı sır tarayıcıya gönderilmez
                foreach (['merchant_key', 'merchant_salt'] as $s) {
                    $v = trim((string)($_POST[$s] ?? ''));
                    if ($v !== '') Settings::set('paytr.' . $s, $v, true);
                    if (!empty($_POST['clear_' . $s])) Settings::set('paytr.' . $s, null, true);
                }
                $enable = !empty($_POST['enabled']);
                if ($enable && !PayTR::configured()) { $enable = false; flash('error', t('paytr.err.incomplete')); }
                Settings::set('paytr.enabled', $enable ? '1' : '0');
                Settings::set('paytr.mode', ($_POST['mode'] ?? 'test') === 'live' ? 'live' : 'test');
                Settings::set('paytr.no_installment', !empty($_POST['installments']) ? '0' : '1');
                Settings::set('paytr.max_installment', (string)max(0, min(12, (int)($_POST['max_installment'] ?? 0))));
                Audit::log('settings.paytr_saved', null, null, ['enabled' => $enable, 'mode' => Settings::get('paytr.mode')]); // sır yok
                break;
            case 'smtp':
                foreach (['host', 'port', 'encryption', 'user', 'from_email', 'from_name'] as $k) Settings::set('smtp.' . $k, mb_substr(trim((string)($_POST[$k] ?? '')), 0, 190));
                if (trim((string)($_POST['pass'] ?? '')) !== '') Settings::set('smtp.pass', trim((string)$_POST['pass']), true);
                if (!empty($_POST['clear_pass'])) Settings::set('smtp.pass', null, true);
                if (!empty($_POST['test_to'])) {
                    $to = (string)$_POST['test_to'];
                    if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                        $err = Mailer::send($to, t('mail.test.subject', ['site' => Settings::get('site.name', 'İkimizden')]), t('mail.test.body'));
                        flash($err ? 'error' : 'success', $err ? t('mail.test_failed') . ' ' . $err : t('mail.test_ok'));
                        redirect(url('/admin/settings/smtp'));
                    }
                }
                break;
            case 'legal':
                foreach ((array)($_POST['page'] ?? []) as $k => $langs) {
                    if (!in_array($k, ['privacy', 'terms', 'kvkk', 'cookies'], true)) continue;
                    foreach ((array)$langs as $l => $p) {
                        if (!I18n::valid($l)) continue;
                        $title = mb_substr(trim((string)($p['title'] ?? '')), 0, 180);
                        $body = trim((string)($p['body'] ?? ''));
                        if ($title === '' && $body === '') { DB::q('DELETE FROM pages WHERE k = ? AND lang = ?', [$k, $l]); continue; }
                        DB::q('INSERT INTO pages (k, lang, title, body, updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body), updated_at = VALUES(updated_at)',
                            [$k, $l, $title ?: t('legal.' . $k, [], $l), $body, DB::now()]);
                    }
                }
                break;
            default:
                abort(404);
        }
        flash('success', t('common.saved'));
        redirect(url('/admin/settings/' . $section));
    }

    // ------------------------------------------------------------ diğer

    public function messages(): void
    {
        $rows = DB::all('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 200');
        DB::q('UPDATE contact_messages SET is_read = 1 WHERE is_read = 0');
        $this->show('messages', ['title' => t('admin.messages'), 'rows' => $rows]);
    }

    public function audit(): void
    {
        $rows = DB::all('SELECT a.*, u.name AS who, u.email FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 300');
        $this->show('audit', ['title' => t('admin.audit'), 'rows' => $rows]);
    }

    public function system(): void
    {
        $backups = [];
        foreach (glob(App::storagePath('backups') . '/*.sql.gz') ?: [] as $f) $backups[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
        rsort($backups);
        $this->show('system', ['title' => t('admin.system'), 'pending' => Migrator::pending(), 'checks' => InstallController::checks(), 'backups' => $backups,
            'applied' => DB::all('SELECT * FROM migrations ORDER BY name')]);
    }

    public function systemAction(): void
    {
        $a = (string)($_POST['action'] ?? '');
        if ($a === 'migrate') {
            $done = Migrator::run();
            Audit::log('system.migrated', null, null, ['files' => $done]);
            flash('success', t('admin.migrated', ['n' => count($done)]));
        } elseif ($a === 'backup') {
            $file = \App\Backup::create();
            Audit::log('system.backup', null, null, ['file' => basename($file)]);
            flash('success', t('admin.backup_done', ['file' => basename($file)]));
        } elseif ($a === 'download') {
            $name = basename((string)($_POST['file'] ?? ''));
            $path = App::storagePath('backups/' . $name);
            if (!preg_match('/^[\w\-\.]+\.sql\.gz$/', $name) || !is_file($path)) abort(404);
            header('Content-Type: application/gzip');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } elseif ($a === 'clear_cache') {
            I18n::clearCache();
            foreach (glob(App::storagePath('cache/img') . '/*') ?: [] as $f) @unlink($f);
            flash('success', t('common.done'));
        }
        redirect(url('/admin/system'));
    }
}
