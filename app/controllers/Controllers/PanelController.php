<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\DB;
use App\I18n;
use App\Invitations;
use App\Orders;
use App\View;

final class PanelController
{
    public function dashboard(): void
    {
        $u = Auth::user();
        if ($u['role'] === 'admin') redirect(url('/admin'));
        if ($u['role'] === 'designer') redirect(url('/designer'));
        $orders = DB::all('SELECT public_no FROM orders WHERE user_id = ? ORDER BY id DESC', [$u['id']]);
        $cards = [];
        foreach ($orders as $r) {
            $o = Orders::find($r['public_no']);
            $inv = Invitations::forOrder((int)$o['id']);
            $latest = $inv ? Invitations::latestVersion((int)$inv['id']) : null;
            $missing = $inv ? \App\Content::missing(Invitations::draft((int)$inv['id'])['content']) : [];
            $cards[] = ['order' => $o, 'inv' => $inv, 'latest' => $latest, 'next' => Orders::nextAction($o, $inv, $latest),
                'missing' => array_sum(array_map('count', $missing))];
        }
        View::show('panel/dashboard', ['title' => t('panel.my_orders'), 'robots' => 'noindex', 'cards' => $cards], 'panel');
    }

    public function designer(): void
    {
        $u = Auth::user();
        $rows = $u['role'] === 'admin'
            ? DB::all('SELECT public_no FROM orders WHERE designer_id IS NOT NULL ORDER BY updated_at DESC')
            : DB::all('SELECT public_no FROM orders WHERE designer_id = ? ORDER BY updated_at DESC', [$u['id']]);
        $orders = array_map(fn($r) => Orders::find($r['public_no']), $rows);
        View::show('panel/designer', ['title' => t('designer.title'), 'robots' => 'noindex', 'orders' => $orders], 'panel');
    }

    public function order(string $no): void
    {
        $o = Orders::access($no);
        $inv = Invitations::forOrder((int)$o['id']);
        $draft = Invitations::draft((int)$inv['id']);
        $latest = Invitations::latestVersion((int)$inv['id']);
        $approved = DB::one("SELECT * FROM invitation_versions WHERE invitation_id = ? AND status = 'approved' ORDER BY version_no DESC LIMIT 1", [$inv['id']]);
        $revs = DB::all('SELECT r.*, v.version_no FROM revision_requests r JOIN invitation_versions v ON v.id = r.version_id WHERE r.order_id = ? ORDER BY r.id DESC', [$o['id']]);
        View::show('panel/order', [
            'title' => t('panel.order') . ' ' . $o['public_no'], 'robots' => 'noindex', 'order' => $o, 'inv' => $inv, 'draft' => $draft,
            'latest' => $latest, 'approved' => $approved, 'revisions' => $revs, 'missing' => \App\Content::missing($draft['content']),
            'next' => Orders::nextAction($o, $inv, $latest), 'tab' => 'overview',
        ], 'panel');
    }

    public function account(): void
    {
        View::show('panel/account', ['title' => t('panel.account'), 'robots' => 'noindex'], 'panel');
    }

    public function accountSave(): void
    {
        $u = Auth::user();
        $name = str_in('name', 120);
        $phone = str_in('phone', 40);
        $locale = I18n::valid($_POST['locale'] ?? null) ? $_POST['locale'] : $u['locale'];
        $upd = ['name' => $name ?: $u['name'], 'phone' => $phone ?: null, 'locale' => $locale, 'updated_at' => DB::now()];
        $new = (string)($_POST['new_password'] ?? '');
        if ($new !== '') {
            if (!password_verify((string)($_POST['current_password'] ?? ''), $u['password_hash'])) { flash('error', t('auth.invalid_current')); redirect(url('/panel/account')); }
            if (!\App\Auth::passwordOk($new)) { flash('error', t('v.password_len')); redirect(url('/panel/account')); }
            $upd['password_hash'] = Auth::hash($new);
        }
        DB::update('users', $upd, 'id = ?', [$u['id']]);
        if (isset($upd['password_hash'])) Auth::login(DB::one('SELECT * FROM users WHERE id = ?', [$u['id']]));
        flash('success', t('common.saved'));
        redirect(url('/panel/account', $locale));
    }
}
