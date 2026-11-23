<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;
use App\I18n;
use App\Orders;
use App\Payments;
use App\PayTR;
use App\View;

final class PaymentController
{
    /** PayTR bildirim uç noktası: oturum/giriş gerektirmez, yalnız düz metin. */
    public function callback(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'POST only';
            return;
        }
        [$body] = Payments::handleCallback($_POST);
        echo $body;
    }

    public function start(string $no): void
    {
        $o = Orders::access($no);
        $r = Payments::start($o, I18n::$lang);
        if (!$r['ok']) {
            flash('error', t($r['error']));
            redirect(url('/checkout/' . $no));
        }
        $_SESSION['pay_token'][$no] = $r['token'];
        redirect(url('/pay/' . $no));
    }

    public function frame(string $no): void
    {
        $o = Orders::access($no);
        $token = $_SESSION['pay_token'][$no] ?? null;
        if (!$token || $o['payment_status'] !== 'awaiting') redirect(url('/checkout/' . $no));
        View::show('shop/pay', ['title' => t('pay.title'), 'robots' => 'noindex', 'order' => $o, 'iframe' => PayTR::IFRAME_URL . rawurlencode($token), 'resizer' => PayTR::RESIZER_URL]);
    }

    /**
     * Başarı/başarısızlık dönüşü. Bu sayfayı açmak siparişi ödenmiş yapmaz;
     * durum yalnız veritabanındaki (bildirimle güncellenen) kayıttan okunur.
     */
    public function back(string $no): void
    {
        $o = Orders::find($no);
        if (!$o) abort(404);
        $u = \App\Auth::user();
        $own = $u && ((int)$u['id'] === (int)$o['user_id'] || $u['role'] === 'admin');
        View::show('shop/status', ['title' => t('pay.status_title'), 'robots' => 'noindex', 'order' => $own ? $o : null, 'no' => $no, 'returned' => (string)($_GET['r'] ?? ''), 'state' => $own ? self::state($o) : 'login']);
    }

    public static function state(array $o): string
    {
        return match ($o['payment_status']) {
            'paid' => 'paid',
            'test_paid' => 'test_paid',
            'review' => 'review',
            'awaiting' => 'verifying',
            default => (DB::val("SELECT status FROM payment_attempts WHERE order_id = ? ORDER BY id DESC LIMIT 1", [$o['id']]) === 'failed') ? 'failed' : 'unpaid',
        };
    }

    public function status(string $no): void
    {
        $o = Orders::access($no);
        json_out(['ok' => true, 'state' => self::state($o)]);
    }
}
