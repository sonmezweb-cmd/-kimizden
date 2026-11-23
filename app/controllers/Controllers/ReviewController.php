<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Audit;
use App\Auth;
use App\Catalog;
use App\DB;
use App\Invitations;
use App\Orders;
use App\View;

/** Önizleme, sürüm gönderimi, revizyon ve onay. */
final class ReviewController
{
    public function preview(string $no): void
    {
        $o = Orders::access($no);
        $inv = Invitations::forOrder((int)$o['id']);
        $draft = Invitations::draft((int)$inv['id']);
        $versions = Invitations::versions((int)$inv['id']);
        $latest = $versions[0] ?? null;
        $approved = null;
        foreach ($versions as $v) if ($v['status'] === 'approved') { $approved = $v; break; }
        $revs = DB::all('SELECT r.*, v.version_no, u.name AS author FROM revision_requests r JOIN invitation_versions v ON v.id = r.version_id JOIN users u ON u.id = r.created_by WHERE r.order_id = ? ORDER BY r.id DESC', [$o['id']]);
        $links = DB::all('SELECT * FROM preview_tokens WHERE invitation_id = ? ORDER BY id DESC LIMIT 10', [$inv['id']]);
        $what = (string)($_GET['v'] ?? ($latest ? 'v' . $latest['id'] : 'draft'));
        if (Auth::is('customer') && $what === 'draft' && $latest) $what = 'v' . $latest['id'];
        $content = $draft['content'];
        if (preg_match('/^v(\d+)$/', $what, $m)) {
            $sel = Invitations::version((int)$m[1]);
            if (!$sel || (int)$sel['invitation_id'] !== (int)$inv['id']) abort(404);
            $content = $sel['content'];
        }
        $plang = in_array($_GET['l'] ?? '', $content['languages'], true) ? $_GET['l'] : $content['default_lang'];
        View::show('panel/preview', [
            'title' => t('preview.title'), 'robots' => 'noindex', 'order' => $o, 'inv' => $inv, 'draft' => $draft, 'versions' => $versions,
            'latest' => $latest, 'approved' => $approved, 'revisions' => $revs, 'links' => $links, 'what' => $what, 'plang' => $plang,
            'pcontent' => $content, 'themes' => Catalog::themes(false), 'tab' => 'preview',
            'revUsed' => Orders::revisionsUsed((int)$o['id']), 'newToken' => $_SESSION['new_preview_token'] ?? null,
        ], 'panel');
        unset($_SESSION['new_preview_token']);
    }

    public function approve(string $no): void
    {
        $o = Orders::access($no);
        $r = Invitations::approve($o, (int)($_POST['version_id'] ?? 0), (int)Auth::id());
        if (!$r['ok']) flash('error', t($r['error']));
        else flash('success', !empty($r['published']) ? t('approve.done_published') : t('approve.done'));
        redirect(url('/panel/orders/' . $no . '/preview'));
    }

    public function revision(string $no): void
    {
        $o = Orders::access($no);
        $msg = str_in('message', 4000);
        if (mb_strlen($msg) < 3) { flash('error', t('revision.err.empty')); redirect(url('/panel/orders/' . $no . '/preview')); }
        $r = Invitations::requestRevision($o, (int)($_POST['version_id'] ?? 0), (int)Auth::id(), $msg);
        if (!$r['ok']) flash('error', t($r['error']));
        else flash('success', !empty($r['duplicate']) ? t('revision.duplicate') : t('revision.sent'));
        redirect(url('/panel/orders/' . $no . '/preview'));
    }

    public function sendVersion(string $no): void
    {
        $o = Orders::access($no);
        $r = Invitations::sendVersion($o, (int)Auth::id(), str_in('note', 3000));
        flash('success', t('version.sent', ['n' => $r['no']]));
        redirect(url('/panel/orders/' . $no . '/preview'));
    }

    public function setTheme(string $no): void
    {
        $o = Orders::access($no);
        $t = Catalog::theme((int)($_POST['theme_id'] ?? 0));
        if (!$t) abort(404);
        $inv = Invitations::forOrder((int)$o['id']);
        Invitations::setTheme((int)$inv['id'], (int)$t['id']);
        Audit::log('draft.theme', 'order', (int)$o['id'], ['theme' => $t['slug']]);
        flash('success', t('common.saved'));
        redirect(url('/panel/orders/' . $no . '/preview', null, ['v' => 'draft']));
    }

    /** Süreli, iptal edilebilir özel önizleme bağlantısı */
    public function previewLink(string $no): void
    {
        $o = Orders::access($no);
        $inv = Invitations::forOrder((int)$o['id']);
        $vid = (int)($_POST['version_id'] ?? 0);
        if ($vid && !DB::val('SELECT 1 FROM invitation_versions WHERE id = ? AND invitation_id = ?', [$vid, $inv['id']])) abort(404);
        $days = max(1, min(30, (int)($_POST['days'] ?? 7)));
        $token = random_token(24);
        DB::insert('preview_tokens', ['invitation_id' => $inv['id'], 'version_id' => $vid ?: null, 'token_hash' => hash('sha256', $token),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $days * 86400), 'created_by' => Auth::id(), 'created_at' => DB::now()]);
        $_SESSION['new_preview_token'] = url_raw('/p/' . $token);
        Audit::log('preview_link.created', 'order', (int)$o['id']);
        redirect(url('/panel/orders/' . $no . '/preview') . '#links');
    }

    public function revokeLink(string $no): void
    {
        $o = Orders::access($no);
        $inv = Invitations::forOrder((int)$o['id']);
        DB::q('UPDATE preview_tokens SET revoked_at = ? WHERE id = ? AND invitation_id = ?', [DB::now(), (int)($_POST['id'] ?? 0), $inv['id']]);
        flash('success', t('preview.link_revoked'));
        redirect(url('/panel/orders/' . $no . '/preview') . '#links');
    }
}
