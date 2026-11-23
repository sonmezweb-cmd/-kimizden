<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Catalog;
use App\I18n;
use App\Orders;
use App\PayTR;
use App\View;

final class ShopController
{
    /** Tema detayından "Satın al": seçim oturumda tutulur, giriş sonrası sipariş açılır. */
    public function start(): void
    {
        $sel = [
            'theme' => (int)($_POST['theme_id'] ?? 0),
            'package' => (int)($_POST['package_id'] ?? 0),
            'addons' => array_map('intval', (array)($_POST['addons'] ?? [])),
        ];
        $_SESSION['cart'] = $sel;
        if (!Auth::user()) {
            $_SESSION['after_login'] = \App\App::basePath() . (I18n::$lang !== 'tr' ? '/' . I18n::$lang : '') . '/order/new';
            flash('info', t('cart.login_first'));
            redirect(url('/register'));
        }
        redirect(url('/order/new'));
    }

    public function newOrder(): void
    {
        $u = Auth::user();
        if ($u['role'] !== 'customer') { flash('error', t('cart.customers_only')); redirect(url('/themes')); }
        $sel = $_SESSION['cart'] ?? null;
        if (!$sel) redirect(url('/themes'));
        $r = Orders::create((int)$u['id'], $sel['theme'], $sel['package'], $sel['addons'], I18n::$lang);
        unset($_SESSION['cart']);
        if (!$r['ok']) { flash('error', t($r['error'])); redirect(url('/themes')); }
        $o = Orders::findById($r['id']);
        redirect(url('/checkout/' . $o['public_no']));
    }

    public function checkout(string $no): void
    {
        $o = Orders::access($no);
        $pkg = Catalog::package((int)$o['package_id']);
        View::show('shop/checkout', [
            'title' => t('checkout.title'), 'robots' => 'noindex', 'order' => $o,
            'packages' => Catalog::packages(true), 'addons' => Catalog::addons(),
            'purchasable' => $pkg && Catalog::purchasable($pkg) && $o['snapshot']['total_minor'] !== null,
            'payEnabled' => PayTR::enabled(), 'payMode' => PayTR::mode(),
        ]);
    }

    public function updateCart(string $no): void
    {
        $o = Orders::access($no);
        $r = Orders::reprice($o, (int)($_POST['package_id'] ?? 0), array_map('intval', (array)($_POST['addons'] ?? [])));
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? t('cart.updated') : t($r['error']));
        redirect(url('/checkout/' . $no));
    }
}
