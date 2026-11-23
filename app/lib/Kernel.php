<?php
declare(strict_types=1);

namespace App;

final class Kernel
{
    public static function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = Router::resolvePath();

        self::securityHeaders($path);

        if (!App::$installed) {
            Session::start();
            if (!str_starts_with($path, '/install')) redirect(App::baseUrl() . '/install');
            $r = new Router();
            $r->get('/install', 'Controllers\InstallController@show', ['no_csrf' => true]);
            $r->post('/install', 'Controllers\InstallController@submit');
            $r->dispatch($method, $path);
            return;
        }

        // PayTR bildirimi: oturum ve CSRF yok, kendi imza doğrulaması var
        if ($path === '/paytr/callback') {
            (new Controllers\PaymentController())->callback();
            App::runShutdown();
            return;
        }

        Session::start();
        I18n::detect();
        View::$shared = ['lang' => I18n::$lang];

        $r = new Router();
        self::routes($r);
        try {
            $r->dispatch($method, $path);
        } finally {
            App::runShutdown();
        }
    }

    private static function securityHeaders(string $path): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        $frame = str_starts_with($path, '/panel/orders/') ? "'self'" : (str_starts_with($path, '/pay/') ? "'self' https://www.paytr.com" : "'none'");
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' https://www.paytr.com; frame-src 'self' https://www.paytr.com https://www.google.com https://maps.google.com; connect-src 'self'; form-action 'self' https://www.paytr.com; frame-ancestors $frame; base-uri 'self'; object-src 'none'");
        if (App::isHttps()) header('Strict-Transport-Security: max-age=31536000');
    }

    public static function routes(Router $r): void
    {
        // Vitrin
        $r->get('/', 'Controllers\SiteController@home');
        $r->get('/themes', 'Controllers\SiteController@themes');
        $r->get('/themes/{slug}', 'Controllers\SiteController@theme');
        $r->get('/preview/{slug}', 'Controllers\InviteController@showcase');
        $r->get('/packages', 'Controllers\SiteController@packages');
        $r->get('/how-it-works', 'Controllers\SiteController@how');
        $r->get('/faq', 'Controllers\SiteController@faq');
        $r->get('/contact', 'Controllers\SiteController@contact');
        $r->post('/contact', 'Controllers\SiteController@contactSubmit');
        $r->get('/legal/{key}', 'Controllers\SiteController@legal');
        $r->get('/sitemap.xml', 'Controllers\SiteController@sitemap');
        $r->get('/robots.txt', 'Controllers\SiteController@robots');

        // Hesap
        $r->get('/login', 'Controllers\AuthController@loginForm');
        $r->post('/login', 'Controllers\AuthController@login');
        $r->get('/register', 'Controllers\AuthController@registerForm');
        $r->post('/register', 'Controllers\AuthController@register');
        $r->post('/logout', 'Controllers\AuthController@logout');
        $r->get('/forgot', 'Controllers\AuthController@forgotForm');
        $r->post('/forgot', 'Controllers\AuthController@forgot');
        $r->get('/reset/{token}', 'Controllers\AuthController@resetForm');
        $r->post('/reset/{token}', 'Controllers\AuthController@reset');

        // Sipariş ve ödeme
        $r->post('/order/start', 'Controllers\ShopController@start');
        $r->get('/order/new', 'Controllers\ShopController@newOrder', ['auth' => true]);
        $r->get('/checkout/{no}', 'Controllers\ShopController@checkout', ['auth' => ['customer', 'admin']]);
        $r->post('/checkout/{no}', 'Controllers\ShopController@updateCart', ['auth' => ['customer', 'admin']]);
        $r->post('/pay/{no}', 'Controllers\PaymentController@start', ['auth' => ['customer']]);
        $r->get('/pay/{no}', 'Controllers\PaymentController@frame', ['auth' => ['customer']]);
        $r->get('/pay/{no}/return', 'Controllers\PaymentController@back');
        $r->get('/pay/{no}/status', 'Controllers\PaymentController@status', ['auth' => true]);

        // Panel (müşteri, atanmış tasarımcı, yönetici — sahiplik her istekte doğrulanır)
        $r->get('/panel', 'Controllers\PanelController@dashboard', ['auth' => true]);
        $r->get('/panel/account', 'Controllers\PanelController@account', ['auth' => true]);
        $r->post('/panel/account', 'Controllers\PanelController@accountSave', ['auth' => true]);
        $r->get('/panel/orders/{no}', 'Controllers\PanelController@order', ['auth' => true]);
        $r->get('/panel/orders/{no}/form/{step}', 'Controllers\FormController@step', ['auth' => true]);
        $r->post('/panel/orders/{no}/form/{step}', 'Controllers\FormController@save', ['auth' => true]);
        $r->post('/panel/orders/{no}/submit-info', 'Controllers\FormController@submitInfo', ['auth' => true]);
        $r->get('/panel/orders/{no}/photos', 'Controllers\FormController@photos', ['auth' => true]);
        $r->post('/panel/orders/{no}/photos', 'Controllers\FormController@photoUpload', ['auth' => true]);
        $r->post('/panel/orders/{no}/photos/update', 'Controllers\FormController@photoUpdate', ['auth' => true]);
        $r->get('/panel/orders/{no}/preview', 'Controllers\ReviewController@preview', ['auth' => true]);
        $r->get('/panel/orders/{no}/render/{what}', 'Controllers\InviteController@panelRender', ['auth' => true]);
        $r->post('/panel/orders/{no}/approve', 'Controllers\ReviewController@approve', ['auth' => ['customer']]);
        $r->post('/panel/orders/{no}/revision', 'Controllers\ReviewController@revision', ['auth' => ['customer']]);
        $r->post('/panel/orders/{no}/send-version', 'Controllers\ReviewController@sendVersion', ['auth' => ['designer', 'admin']]);
        $r->post('/panel/orders/{no}/theme', 'Controllers\ReviewController@setTheme', ['auth' => ['designer', 'admin']]);
        $r->post('/panel/orders/{no}/preview-link', 'Controllers\ReviewController@previewLink', ['auth' => true]);
        $r->post('/panel/orders/{no}/preview-link/revoke', 'Controllers\ReviewController@revokeLink', ['auth' => true]);
        $r->get('/panel/orders/{no}/share', 'Controllers\GuestsController@share', ['auth' => true]);
        $r->get('/panel/orders/{no}/qr.{fmt}', 'Controllers\GuestsController@qr', ['auth' => true]);
        $r->get('/panel/orders/{no}/guests', 'Controllers\GuestsController@guests', ['auth' => true]);
        $r->post('/panel/orders/{no}/guests', 'Controllers\GuestsController@guestsSave', ['auth' => true]);
        $r->get('/panel/orders/{no}/rsvps', 'Controllers\GuestsController@report', ['auth' => true]);
        $r->get('/panel/orders/{no}/rsvps.csv', 'Controllers\GuestsController@csv', ['auth' => true]);

        // Tasarımcı
        $r->get('/designer', 'Controllers\PanelController@designer', ['auth' => ['designer', 'admin']]);

        // Yönetim
        $r->get('/admin', 'Controllers\AdminController@dashboard', ['auth' => ['admin']]);
        $r->get('/admin/orders', 'Controllers\AdminController@orders', ['auth' => ['admin']]);
        $r->get('/admin/orders/{no}', 'Controllers\AdminController@order', ['auth' => ['admin']]);
        $r->post('/admin/orders/{no}', 'Controllers\AdminController@orderAction', ['auth' => ['admin']]);
        $r->get('/admin/users', 'Controllers\AdminController@users', ['auth' => ['admin']]);
        $r->post('/admin/users', 'Controllers\AdminController@usersSave', ['auth' => ['admin']]);
        $r->get('/admin/packages', 'Controllers\AdminController@packages', ['auth' => ['admin']]);
        $r->post('/admin/packages', 'Controllers\AdminController@packagesSave', ['auth' => ['admin']]);
        $r->get('/admin/themes', 'Controllers\AdminController@themes', ['auth' => ['admin']]);
        $r->get('/admin/themes/{id}', 'Controllers\AdminController@theme', ['auth' => ['admin']]);
        $r->post('/admin/themes/{id}', 'Controllers\AdminController@themeSave', ['auth' => ['admin']]);
        $r->get('/admin/translations', 'Controllers\AdminController@translations', ['auth' => ['admin']]);
        $r->post('/admin/translations', 'Controllers\AdminController@translationsSave', ['auth' => ['admin']]);
        $r->get('/admin/emails', 'Controllers\AdminController@emails', ['auth' => ['admin']]);
        $r->post('/admin/emails', 'Controllers\AdminController@emailsAction', ['auth' => ['admin']]);
        $r->get('/admin/settings/{section}', 'Controllers\AdminController@settings', ['auth' => ['admin']]);
        $r->post('/admin/settings/{section}', 'Controllers\AdminController@settingsSave', ['auth' => ['admin']]);
        $r->get('/admin/messages', 'Controllers\AdminController@messages', ['auth' => ['admin']]);
        $r->get('/admin/audit', 'Controllers\AdminController@audit', ['auth' => ['admin']]);
        $r->get('/admin/system', 'Controllers\AdminController@system', ['auth' => ['admin']]);
        $r->post('/admin/system', 'Controllers\AdminController@systemAction', ['auth' => ['admin']]);

        // Misafir
        $r->get('/i/{slug}', 'Controllers\InviteController@show');
        $r->get('/i/{slug}/g/{token}', 'Controllers\InviteController@group');
        $r->post('/i/{slug}/rsvp', 'Controllers\InviteController@rsvp');
        $r->get('/i/{slug}/ics/{event}', 'Controllers\InviteController@ics');
        $r->get('/p/{token}', 'Controllers\InviteController@previewToken');
        $r->get('/m/{key}/{size}', 'Controllers\InviteController@media');
    }
}
