<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\Catalog;
use App\Content;
use App\DB;
use App\I18n;
use App\Ics;
use App\Invitations;
use App\InviteView;
use App\Media;
use App\Orders;
use App\RateLimit;
use App\View;

/** Misafir davetiyesi, RSVP, takvim, medya, önizleme. */
final class InviteController
{
    private function render(array $data, int $code = 200): void
    {
        I18n::$lang = $data['glang']; // misafir arayüz dili = davetiye içerik dili
        View::$shared['lang'] = $data['glang'];
        if ($data['mode'] !== 'live' && $data['mode'] !== 'showcase') header('X-Robots-Tag: noindex, nofollow');
        if ($data['mode'] === 'live' || $data['mode'] === 'group') header('X-Robots-Tag: noindex, nofollow');
        View::show('invite/show', $data, 'invite', $code);
    }

    public function showcase(string $slug): void
    {
        $t = Catalog::themeBySlug($slug);
        if (!$t || (!(int)$t['is_active'] && !Auth::is('admin'))) abort(404);
        $lang = I18n::$lang;
        $c = InviteView::showcaseContent($lang);
        $this->render(InviteView::build($c, $t, $lang, 'showcase', ['backUrl' => url('/themes/' . $slug)]));
    }

    public function show(string $slug): void
    {
        $live = Invitations::live($slug);
        if (!$live) { http_response_code(404); I18n::$lang = 'tr'; View::show('invite/unavailable', [], 'bare', 404); return; }
        $theme = Catalog::theme($live['theme_id']);
        $lang = InviteView::guestLang($live['content']);
        $this->render(InviteView::build($live['content'], $theme, $lang, 'live', [
            'slug' => $slug, 'canonical' => url_raw('/i/' . $slug), 'myRsvp' => $this->publicRsvp((int)$live['inv']['id'], $slug),
        ]));
    }

    public function group(string $slug, string $token): void
    {
        $live = Invitations::live($slug);
        $g = $live ? DB::one('SELECT * FROM guest_groups WHERE token = ? AND invitation_id = ?', [$token, $live['inv']['id']]) : null;
        if (!$live || !$g) { http_response_code(404); View::show('invite/unavailable', [], 'bare', 404); return; }
        $theme = Catalog::theme($live['theme_id']);
        $lang = InviteView::guestLang($live['content']);
        $rsvp = DB::one('SELECT * FROM rsvps WHERE group_id = ?', [$g['id']]);
        $this->render(InviteView::build($live['content'], $theme, $lang, 'group', [
            'slug' => $slug, 'group' => $g, 'myRsvp' => $rsvp, 'canonical' => url_raw('/i/' . $slug),
        ]));
    }

    private function publicRsvp(int $invId, string $slug): ?array
    {
        $tok = $_COOKIE['rsvp_' . $slug] ?? '';
        if (!is_string($tok) || strlen($tok) < 20) return null;
        return DB::one('SELECT * FROM rsvps WHERE edit_token_hash = ? AND invitation_id = ? AND group_id IS NULL', [hash('sha256', $tok), $invId]);
    }

    public function rsvp(string $slug): void
    {
        $live = Invitations::live($slug);
        if (!$live) abort(404);
        $c = $live['content'];
        $lang = in_array($_POST['l'] ?? '', $c['languages'], true) ? $_POST['l'] : $c['default_lang'];
        I18n::$lang = $lang;
        $back = url_raw('/i/' . $slug, ['l' => $lang]);
        $groupToken = (string)($_POST['g'] ?? '');
        if ($groupToken !== '') $back = url_raw('/i/' . $slug . '/g/' . $groupToken, ['l' => $lang]);
        $back .= '#rsvp';

        if (empty($c['rsvp']['enabled'])) { flash('error', t('rsvp.closed')); redirect($back); }
        if (InviteView::deadlinePassed($c)) { flash('error', t('rsvp.deadline_passed')); redirect($back); } // sunucu tarafında kesin kontrol
        if (!RateLimit::hit('rsvp:' . client_ip(), 40, 60)) { flash('error', t('auth.too_many')); redirect($back); }

        $attending = ($_POST['attending'] ?? '') === '1';
        $adults = $attending ? max(0, min(20, (int)($_POST['adults'] ?? 1))) : 0;
        $children = $attending ? max(0, min(20, (int)($_POST['children'] ?? 0))) : 0;
        if (empty($c['rsvp']['ask_counts']) && $attending) { $adults = max(1, $adults); }
        if ($attending && $adults + $children < 1) $adults = 1;
        $note = mb_substr(str_in('note', 1000), 0, 1000);
        $name = str_in('name', 120);
        $now = DB::now();
        $invId = (int)$live['inv']['id'];

        if (!isset($_POST['attending']) || !in_array($_POST['attending'], ['0', '1'], true)) { flash('error', t('rsvp.choose')); redirect($back); }

        if ($groupToken !== '') {
            $g = DB::one('SELECT * FROM guest_groups WHERE token = ? AND invitation_id = ?', [$groupToken, $invId]);
            if (!$g) abort(404);
            if ($adults + $children > (int)$g['max_guests']) { flash('error', t('rsvp.too_many', ['n' => $g['max_guests']])); redirect($back); }
            // aynı aile bağlantısı: önceki yanıt güncellenir (benzersiz group_id)
            DB::q('INSERT INTO rsvps (invitation_id, group_id, name, attending, adults, children, note, lang, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE attending = VALUES(attending), adults = VALUES(adults), children = VALUES(children), note = VALUES(note), lang = VALUES(lang), name = VALUES(name), updated_at = VALUES(updated_at)',
                [$invId, $g['id'], $name !== '' ? $name : $g['name'], $attending ? 1 : 0, $adults, $children, $note ?: null, $lang, $now, $now]);
        } else {
            if ($name === '') { flash('error', t('rsvp.name_required')); redirect($back); }
            $max = (int)($c['rsvp']['max_public'] ?? 4);
            if ($adults + $children > $max) { flash('error', t('rsvp.too_many', ['n' => $max])); redirect($back); }
            $existing = $this->publicRsvp($invId, $slug);
            if ($existing) {
                DB::update('rsvps', ['name' => $name, 'attending' => $attending ? 1 : 0, 'adults' => $adults, 'children' => $children, 'note' => $note ?: null, 'lang' => $lang, 'updated_at' => $now], 'id = ?', [$existing['id']]);
            } else {
                $tok = random_token(24);
                DB::insert('rsvps', ['invitation_id' => $invId, 'group_id' => null, 'edit_token_hash' => hash('sha256', $tok), 'name' => $name,
                    'attending' => $attending ? 1 : 0, 'adults' => $adults, 'children' => $children, 'note' => $note ?: null, 'lang' => $lang, 'created_at' => $now, 'updated_at' => $now]);
                setcookie('rsvp_' . $slug, $tok, ['expires' => time() + 86400 * 400, 'path' => App::basePath() . '/i/' . $slug, 'secure' => App::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
            }
        }
        flash('success', $attending ? t('rsvp.thanks_yes') : t('rsvp.thanks_no'));
        redirect($back);
    }

    public function ics(string $slug, string $eventId): void
    {
        $live = Invitations::live($slug);
        if (!$live) abort(404);
        $c = $live['content'];
        $lang = InviteView::guestLang($c);
        foreach ($c['events'] as $e) {
            if ($e['id'] !== $eventId || empty($e['start_utc'])) continue;
            [$a, $b] = Content::names($c);
            $title = Content::eventTitle($e, $lang) . ' — ' . $a . ' & ' . $b;
            $loc = trim($e['venue'] . ', ' . $e['address'], ', ');
            $ics = Ics::event($e, $title, $loc, trim(($e['desc'][$lang] ?? '') . "\n" . url_raw('/i/' . $slug)), $slug . '-' . $e['id'] . '@ikimizden', url_raw('/i/' . $slug));
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="davetiye-' . $e['id'] . '.ics"');
            header('X-Robots-Tag: noindex');
            echo $ics;
            return;
        }
        abort(404);
    }

    /** Panel içi önizleme (iframe): taslak, belirli sürüm veya canlı */
    public function panelRender(string $no, string $what): void
    {
        $o = Orders::access($no);
        $inv = Invitations::forOrder((int)$o['id']);
        if ($what === 'draft') {
            $d = Invitations::draft((int)$inv['id']);
            $content = $d['content']; $themeId = (int)$d['theme_id'];
        } elseif (preg_match('/^v(\d+)$/', $what, $m)) {
            $v = Invitations::version((int)$m[1]);
            if (!$v || (int)$v['invitation_id'] !== (int)$inv['id']) abort(404);
            $content = $v['content']; $themeId = (int)$v['theme_id'];
        } else abort(404);
        $_SESSION['media_ok'][(int)$o['id']] = true;
        $lang = InviteView::guestLang($content);
        $this->render(InviteView::build($content, Catalog::theme($themeId), $lang, 'preview', ['previewLabel' => $what === 'draft' ? t('preview.draft_label') : t('preview.version_label', ['n' => $v['version_no'] ?? ''])]));
    }

    public function previewToken(string $token): void
    {
        $pt = DB::one('SELECT * FROM preview_tokens WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > ?', [hash('sha256', $token), DB::now()]);
        if (!$pt) { http_response_code(404); View::show('invite/unavailable', ['expired' => true], 'bare', 404); return; }
        $inv = DB::one('SELECT * FROM invitations WHERE id = ?', [$pt['invitation_id']]);
        if ($pt['version_id']) {
            $v = Invitations::version((int)$pt['version_id']);
            $content = $v['content']; $themeId = (int)$v['theme_id'];
            $label = t('preview.version_label', ['n' => $v['version_no']]);
        } else {
            $d = Invitations::draft((int)$inv['id']);
            $content = $d['content']; $themeId = (int)$d['theme_id'];
            $label = t('preview.draft_label');
        }
        $_SESSION['media_ok'][(int)$inv['order_id']] = true;
        $lang = InviteView::guestLang($content);
        $this->render(InviteView::build($content, Catalog::theme($themeId), $lang, 'preview', ['previewLabel' => $label, 'tokenBase' => url_raw('/p/' . $token)]));
    }

    /** Özel medya: sahip/atanmış tasarımcı/yönetici, önizleme oturumu veya canlı sürümde kullanılan fotoğraf */
    public function media(string $key, string $size): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $key) || !isset(Media::SIZES[$size])) abort(404);
        $m = DB::one('SELECT * FROM media WHERE mkey = ?', [$key]);
        if (!$m) abort(404);
        $public = false;
        $allowed = !empty($_SESSION['media_ok'][(int)$m['order_id']]);
        if (!$allowed && ($u = Auth::user())) {
            $o = DB::one('SELECT user_id, designer_id FROM orders WHERE id = ?', [$m['order_id']]);
            $allowed = $u['role'] === 'admin' || (int)$o['user_id'] === (int)$u['id'] || ($u['role'] === 'designer' && (int)$o['designer_id'] === (int)$u['id']);
        }
        if (!$allowed) {
            $row = DB::one("SELECT v.content_json FROM invitations i JOIN orders o ON o.id = i.order_id JOIN invitation_versions v ON v.id = i.live_version_id WHERE i.order_id = ? AND o.publish_status = 'published'", [$m['order_id']]);
            if ($row && in_array($key, Content::mediaKeys(jdec($row['content_json'])), true)) { $allowed = true; $public = true; }
        }
        if (!$allowed || ($m['deleted_at'] && !$public)) abort(404);
        $path = Media::sized($m, $size);
        $etag = '"' . $key . $size . filemtime($path) . '"';
        header('Content-Type: image/jpeg');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: ' . ($public ? 'public, max-age=86400' : 'private, max-age=600'));
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); return; }
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }
}
