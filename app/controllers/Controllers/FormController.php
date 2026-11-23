<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Audit;
use App\Auth;
use App\Content;
use App\DB;
use App\I18n;
use App\Invitations;
use App\Mailer;
use App\Media;
use App\Orders;
use App\View;

/**
 * Adım adım bilgi formu. Her adım veritabanına kaydedilir; alan düzeyinde iyimser kilit
 * aynı taslağı açan müşteri ve tasarımcının birbirini sessizce ezmesini önler.
 */
final class FormController
{
    public const STEPS = ['names', 'languages', 'events', 'texts', 'details', 'review'];

    private function ctx(string $no): array
    {
        $o = Orders::access($no);
        $inv = Invitations::forOrder((int)$o['id']);
        return [$o, $inv];
    }

    private function canEdit(array $o): bool
    {
        if ($o['publish_status'] === 'archived') return Auth::is('admin');
        return true;
    }

    public function step(string $no, string $step, array $extra = []): void
    {
        if (!in_array($step, self::STEPS, true)) abort(404);
        [$o, $inv] = $this->ctx($no);
        $draft = Invitations::draft((int)$inv['id']);
        $content = $extra['content'] ?? $draft['content'];
        View::show('panel/form', array_merge([
            'title' => t('form.title') . ' — ' . t('form.step.' . $step), 'robots' => 'noindex', 'order' => $o, 'inv' => $inv,
            'draft' => $draft, 'content' => $content, 'step' => $step, 'steps' => self::STEPS, 'tab' => 'form',
            'missing' => Content::missing($draft['content']), 'conflicts' => [], 'editable' => $this->canEdit($o),
            'media' => Media::forOrder((int)$o['id']),
        ], $extra), 'panel', $extra['code'] ?? 200);
    }

    /** POST'tan bu adımın yamasını kurar. */
    private function patchFrom(string $step, array $o): array
    {
        $P = $_POST;
        $p = [];
        switch ($step) {
            case 'names':
                $p['names.first'] = (string)($P['names_first'] ?? '');
                $p['names.second'] = (string)($P['names_second'] ?? '');
                $p['names.order'] = (string)($P['names_order'] ?? 'ab');
                break;
            case 'languages':
                $max = (int)($o['snapshot']['package']['max_languages'] ?? 3);
                $langs = array_values(array_intersect(I18n::LANGS, (array)($P['languages'] ?? [])));
                if (count($langs) > $max) $langs = array_slice($langs, 0, $max);
                $p['languages'] = $langs ?: ['tr'];
                $p['default_lang'] = (string)($P['default_lang'] ?? $p['languages'][0]);
                break;
            case 'events':
                $rows = [];
                foreach ((array)($P['events'] ?? []) as $e) {
                    if (!is_array($e) || !empty($e['_remove'])) continue;
                    $rows[] = $e;
                }
                if (!empty($P['_add_event']) && count($rows) < Content::MAX_EVENTS) {
                    $rows[] = ['type' => 'wedding', 'tz' => $rows ? end($rows)['tz'] ?? 'Europe/Istanbul' : 'Europe/Istanbul', 'date' => $rows ? end($rows)['date'] ?? '' : ''];
                }
                if (isset($P['_move']) && preg_match('/^(\d+):(up|down)$/', (string)$P['_move'], $m)) {
                    $i = (int)$m[1]; $j = $m[2] === 'up' ? $i - 1 : $i + 1;
                    if (isset($rows[$i], $rows[$j])) { [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]]; }
                }
                $p['events'] = $rows;
                break;
            case 'texts':
                $c = Invitations::draft((int)Invitations::forOrder((int)$o['id'])['id'])['content'];
                foreach ($c['languages'] as $l) {
                    foreach (array_keys(Content::TEXT_FIELDS) as $f) {
                        if (isset($P['texts'][$l][$f])) $p["texts.$l.$f"] = (string)$P['texts'][$l][$f];
                    }
                }
                break;
            case 'details':
                $p['cover_event_id'] = (string)($P['cover_event_id'] ?? '');
                $p['contact.name'] = (string)($P['contact_name'] ?? '');
                $p['contact.phone'] = (string)($P['contact_phone'] ?? '');
                $p['rsvp.enabled'] = !empty($P['rsvp_enabled']);
                $p['rsvp.deadline'] = (string)($P['rsvp_deadline'] ?? '');
                $p['rsvp.ask_counts'] = !empty($P['rsvp_ask_counts']);
                $p['rsvp.max_public'] = (int)($P['rsvp_max_public'] ?? 4);
                break;
        }
        // Otomatik kayıt yalnız değişen alanları gönderir: başkasının alanı boşuna çakışmaz
        if (isset($P['_dirty']) && is_array($P['_dirty'])) {
            $want = [];
            foreach ($P['_dirty'] as $name) $want[] = self::fieldPath((string)$name);
            $p = array_filter($p, fn($path) => in_array($path, $want, true), ARRAY_FILTER_USE_KEY);
        }
        return $p;
    }

    public static function fieldPath(string $name): string
    {
        if (str_starts_with($name, 'events')) return 'events';
        if (preg_match('/^texts\[(tr|en|ru)\]\[(\w+)\]$/', $name, $m)) return "texts.$m[1].$m[2]";
        return match (rtrim($name, '[]')) {
            'names_first' => 'names.first', 'names_second' => 'names.second', 'names_order' => 'names.order',
            'languages' => 'languages', 'default_lang' => 'default_lang', 'cover_event_id' => 'cover_event_id',
            'contact_name' => 'contact.name', 'contact_phone' => 'contact.phone', 'rsvp_enabled' => 'rsvp.enabled',
            'rsvp_deadline' => 'rsvp.deadline', 'rsvp_ask_counts' => 'rsvp.ask_counts', 'rsvp_max_public' => 'rsvp.max_public',
            default => '',
        };
    }

    public function save(string $no, string $step): void
    {
        if (!in_array($step, self::STEPS, true)) abort(404);
        [$o, $inv] = $this->ctx($no);
        if (!$this->canEdit($o)) abort(403, t('form.archived'));
        $base = (int)($_POST['_v'] ?? 0);
        $patch = $this->patchFrom($step, $o);
        $res = Invitations::savePatch((int)$inv['id'], $patch, $base, Auth::id());
        if ($res['applied']) Audit::log('draft.saved', 'order', (int)$o['id'], ['step' => $step, 'fields' => $res['applied']]);

        if (wants_json()) {
            json_out([
                'ok' => $res['ok'], 'version' => $res['lock_version'], 'applied' => $res['applied'],
                'conflicts' => array_keys($res['conflicts']), 'others' => array_keys($res['others']),
                'message' => $res['ok'] ? t('save.saved') : t('save.conflict'),
            ], $res['ok'] ? 200 : 409);
        }
        if (!$res['ok']) {
            // Kullanıcının girdisi kaybolmaz: form, girdiler ve sunucudaki değerler birlikte gösterilir
            $content = $res['content'];
            foreach ($res['conflicts'] as $path => $_) Content::setPath($content, $path, Content::sanitize($path, $patch[$path]));
            $this->step($no, $step, ['content' => $content, 'conflicts' => $res['conflicts'], 'code' => 409,
                'draft' => array_merge(Invitations::draft((int)$inv['id']), [])]);
            return;
        }
        flash('success', t('save.saved'));
        $next = $step;
        if (!empty($_POST['_next'])) {
            $i = array_search($step, self::STEPS, true);
            $next = self::STEPS[min($i + 1, count(self::STEPS) - 1)];
        }
        redirect(url('/panel/orders/' . $no . '/form/' . $next) . ($step === 'events' && !empty($_POST['_add_event']) ? '#event-' . (count($res['content']['events']) - 1) : ''));
    }

    public function submitInfo(string $no): void
    {
        [$o, $inv] = $this->ctx($no);
        if (!Auth::is('customer')) abort(403);
        if ($o['design_status'] !== 'info_pending') redirect(url('/panel/orders/' . $no));
        DB::update('orders', ['design_status' => 'in_design', 'info_submitted_at' => DB::now(), 'updated_at' => DB::now()], 'id = ? AND design_status = ?', [$o['id'], 'info_pending']);
        Audit::log('info.submitted', 'order', (int)$o['id']);
        Mailer::queueAdmins('info_submitted', ['order' => $no], 'info:' . $o['id']);
        if ($o['designer_id']) {
            $d = DB::one('SELECT email, locale FROM users WHERE id = ?', [$o['designer_id']]);
            if ($d) Mailer::queueTemplate($d['email'], $d['locale'], 'info_submitted', ['order' => $no], 'info-d:' . $o['id']);
        }
        flash('success', t('form.submitted'));
        redirect(url('/panel/orders/' . $no));
    }

    // ------------------------------------------------------------ fotoğraflar

    public function photos(string $no): void
    {
        [$o, $inv] = $this->ctx($no);
        $draft = Invitations::draft((int)$inv['id']);
        View::show('panel/photos', ['title' => t('photos.title'), 'robots' => 'noindex', 'order' => $o, 'inv' => $inv, 'draft' => $draft,
            'content' => $draft['content'], 'media' => Media::forOrder((int)$o['id']), 'tab' => 'photos', 'editable' => $this->canEdit($o)], 'panel');
    }

    public function photoUpload(string $no): void
    {
        [$o, $inv] = $this->ctx($no);
        if (!$this->canEdit($o)) abort(403);
        $files = $_FILES['photos'] ?? null;
        $results = [];
        if ($files && is_array($files['name'])) {
            $count = (int)DB::val('SELECT COUNT(*) FROM media WHERE order_id = ? AND deleted_at IS NULL', [$o['id']]);
            foreach ($files['name'] as $i => $name) {
                if ($count >= 40) { $results[] = ['name' => $name, 'ok' => false, 'error' => t('upload.err.limit')]; continue; }
                $f = ['name' => $name, 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                $r = Media::storePhoto((int)$o['id'], $f, Auth::id());
                $results[] = ['name' => $name, 'ok' => $r['ok'], 'error' => $r['ok'] ? null : t($r['error']), 'key' => $r['media']['key'] ?? null];
                if ($r['ok']) {
                    $count++;
                    // yeni fotoğraf galeriye eklenir; kapak yoksa ilk fotoğraf kapak olur
                    $d = Invitations::draft((int)$inv['id']);
                    $gal = $d['content']['gallery'];
                    $gal[] = $r['media']['key'];
                    $patch = ['gallery' => $gal];
                    if (!$d['content']['cover_photo']) $patch['cover_photo'] = $r['media']['key'];
                    Invitations::savePatch((int)$inv['id'], $patch, (int)$d['lock_version'], Auth::id(), $this->mediaCheck((int)$o['id']));
                }
            }
        }
        if (wants_json()) json_out(['ok' => !in_array(false, array_column($results, 'ok'), true), 'results' => $results]);
        foreach ($results as $r) flash($r['ok'] ? 'success' : 'error', $r['ok'] ? t('upload.ok', ['name' => $r['name']]) : $r['name'] . ': ' . $r['error']);
        if (!$results) flash('error', t('upload.err.failed'));
        redirect(url('/panel/orders/' . $no . '/photos'));
    }

    private function mediaCheck(int $orderId): callable
    {
        return function (string $path, mixed $v) use ($orderId) {
            $valid = array_column(DB::all('SELECT mkey FROM media WHERE order_id = ? AND deleted_at IS NULL', [$orderId]), 'mkey');
            if ($path === 'cover_photo') return in_array($v, $valid, true) ? $v : null;
            return array_values(array_filter((array)$v, fn($k) => in_array($k, $valid, true)));
        };
    }

    public function photoUpdate(string $no): void
    {
        [$o, $inv] = $this->ctx($no);
        if (!$this->canEdit($o)) abort(403);
        $d = Invitations::draft((int)$inv['id']);
        $c = $d['content'];
        $key = (string)($_POST['key'] ?? '');
        $action = (string)($_POST['action'] ?? '');
        $m = DB::one('SELECT * FROM media WHERE order_id = ? AND mkey = ? AND deleted_at IS NULL', [$o['id'], $key]);
        if (!$m && $action !== 'order') abort(404);
        $gal = $c['gallery'];
        $patch = [];
        switch ($action) {
            case 'cover': $patch['cover_photo'] = $key; break;
            case 'uncover': $patch['cover_photo'] = null; break;
            case 'toggle':
                $patch['gallery'] = in_array($key, $gal, true) ? array_values(array_diff($gal, [$key])) : array_merge($gal, [$key]);
                break;
            case 'up': case 'down':
                $i = array_search($key, $gal, true);
                if ($i !== false) {
                    $j = $action === 'up' ? $i - 1 : $i + 1;
                    if (isset($gal[$j])) { [$gal[$i], $gal[$j]] = [$gal[$j], $gal[$i]]; $patch['gallery'] = $gal; }
                }
                break;
            case 'order':
                $patch['gallery'] = array_map('strval', (array)($_POST['keys'] ?? []));
                break;
            case 'delete':
                DB::update('media', ['deleted_at' => DB::now()], 'id = ?', [$m['id']]);
                $patch['gallery'] = array_values(array_diff($gal, [$key]));
                if ($c['cover_photo'] === $key) $patch['cover_photo'] = null;
                break;
        }
        $res = $patch ? Invitations::savePatch((int)$inv['id'], $patch, (int)$d['lock_version'], Auth::id(), $this->mediaCheck((int)$o['id'])) : ['ok' => true];
        if (wants_json()) json_out(['ok' => $res['ok']]);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? t('save.saved') : t('save.conflict'));
        redirect(url('/panel/orders/' . $no . '/photos'));
    }
}
