<?php
declare(strict_types=1);

namespace App;

final class Payments
{
    public static function newOid(int $orderId): string
    {
        // PayTR: benzersiz, yalnız alfanümerik
        return 'IKZ' . $orderId . 'T' . time() . strtoupper(substr(random_token(6), 0, 5));
    }

    /**
     * Ödeme denemesi başlatır. Sepet sunucuda yeniden hesaplanır; deneme tutarı kuruş.
     * @return array{ok:bool, error?:string, attempt?:array, token?:string}
     */
    public static function start(array $order, string $lang): array
    {
        if (!PayTR::enabled()) return ['ok' => false, 'error' => 'pay.err.disabled'];
        if (in_array($order['payment_status'], ['paid', 'test_paid', 'review'], true)) return ['ok' => false, 'error' => 'pay.err.already'];
        // fiyat yeniden hesap: paket/ek hizmet kopyası güncel katalogdan
        $addonIds = array_column($order['snapshot']['addons'] ?? [], 'id');
        $p = Orders::price((int)$order['theme_id'], (int)$order['package_id'], $addonIds);
        if (!$p['ok'] || empty($p['purchasable']) || $p['total'] === null || $p['total'] <= 0) return ['ok' => false, 'error' => 'pay.err.no_price'];
        $mode = PayTR::mode();
        $attempt = DB::tx(function () use ($order, $p, $mode) {
            $o = DB::one('SELECT payment_status FROM orders WHERE id = ? FOR UPDATE', [$order['id']]);
            if (in_array($o['payment_status'], ['paid', 'test_paid', 'review'], true)) return null;
            DB::update('orders', [
                'snapshot_json' => jenc($p['snapshot']), 'total_minor' => $p['total'], 'currency' => $p['currency'],
                'revisions_allowed' => $p['snapshot']['revisions_total'], 'payment_status' => 'awaiting', 'updated_at' => DB::now(),
            ], 'id = ?', [$order['id']]);
            $a = [
                'order_id' => $order['id'], 'merchant_oid' => self::newOid((int)$order['id']),
                'merchant_id' => (string)Settings::get('paytr.merchant_id'), 'mode' => $mode,
                'amount_minor' => $p['total'], 'currency' => $p['currency'], 'status' => 'created',
                'created_at' => DB::now(), 'updated_at' => DB::now(),
            ];
            $a['id'] = DB::insert('payment_attempts', $a);
            return $a;
        });
        if (!$attempt) return ['ok' => false, 'error' => 'pay.err.already'];
        $order = Orders::findById((int)$order['id']);
        $r = PayTR::requestToken($order, $attempt, $lang);
        if (!$r['ok']) {
            DB::update('payment_attempts', ['status' => 'token_failed', 'error_text' => mb_substr((string)($r['reason'] ?? $r['error']), 0, 490), 'updated_at' => DB::now()], 'id = ?', [$attempt['id']]);
            // sepet korunur: sipariş yeniden ödemeye açık
            DB::q("UPDATE orders SET payment_status = 'unpaid' WHERE id = ? AND payment_status = 'awaiting'", [$order['id']]);
            return ['ok' => false, 'error' => $r['error'] === 'unreachable' ? 'pay.err.unreachable' : 'pay.err.rejected'];
        }
        DB::update('payment_attempts', ['status' => 'awaiting', 'token' => $r['token'], 'updated_at' => DB::now()], 'id = ?', [$attempt['id']]);
        Audit::log('payment.started', 'order', (int)$order['id'], ['oid' => $attempt['merchant_oid'], 'mode' => $mode]);
        return ['ok' => true, 'attempt' => $attempt, 'token' => $r['token']];
    }

    /**
     * PayTR bildirimini işler. Dönüş: [httpBody, outcome]. Yalnız güvenle işlenmiş geçerli bildirimde "OK".
     */
    public static function handleCallback(array $post): array
    {
        $oid = (string)($post['merchant_oid'] ?? '');
        $status = (string)($post['status'] ?? '');
        $total = (string)($post['total_amount'] ?? '');
        $hash = (string)($post['hash'] ?? '');
        $safePayload = array_intersect_key($post, array_flip(['merchant_oid', 'status', 'total_amount', 'payment_amount', 'payment_type', 'currency', 'test_mode', 'failed_reason_code', 'failed_reason_msg', 'installment_count']));

        $log = function (bool $valid, string $outcome, ?string $mode = null) use ($oid, $status, $safePayload) {
            try {
                DB::insert('payment_callbacks', ['merchant_oid' => mb_substr($oid, 0, 64), 'status' => mb_substr($status, 0, 20), 'mode' => $mode, 'hash_valid' => $valid ? 1 : 0, 'outcome' => $outcome, 'payload_json' => jenc($safePayload), 'ip' => client_ip(), 'created_at' => DB::now()]);
            } catch (\Throwable $e) {
                Log::error('Bildirim günlüğü yazılamadı: ' . $e->getMessage());
            }
        };

        if (!PayTR::configured() || $oid === '' || $hash === '' || !in_array($status, ['success', 'failed'], true) || !ctype_digit($total)) {
            $log(false, 'malformed');
            return ['PAYTR notification failed: bad request', 'malformed'];
        }
        $key = (string)Settings::secret('paytr.merchant_key');
        $salt = (string)Settings::secret('paytr.merchant_salt');
        $expected = PayTR::callbackHash($oid, $status, $total, $key, $salt);
        if (!hash_equals($expected, $hash)) {
            $log(false, 'bad_hash');
            return ['PAYTR notification failed: bad hash', 'bad_hash'];
        }
        $cbMode = ((string)($post['test_mode'] ?? '0')) === '1' ? 'test' : 'live';

        try {
            $outcome = DB::tx(function () use ($oid, $status, $total, $post, $cbMode) {
                $a = DB::one('SELECT * FROM payment_attempts WHERE merchant_oid = ? FOR UPDATE', [$oid]);
                if (!$a) return 'unknown_oid';
                if ($a['merchant_id'] !== (string)Settings::get('paytr.merchant_id')) return 'store_mismatch';
                DB::q('UPDATE payment_attempts SET callback_count = callback_count + 1, updated_at = ? WHERE id = ?', [DB::now(), $a['id']]);
                if (in_array($a['status'], ['success', 'failed'], true)) return 'duplicate'; // daha önce güvenle işlendi
                if ($a['mode'] !== $cbMode) return 'mode_mismatch';
                $order = DB::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$a['order_id']]);
                $now = DB::now();
                if ($status !== 'success') {
                    DB::update('payment_attempts', ['status' => 'failed', 'callback_status' => 'failed', 'processed_at' => $now,
                        'error_text' => mb_substr((string)($post['failed_reason_code'] ?? '') . ' ' . (string)($post['failed_reason_msg'] ?? ''), 0, 490)], 'id = ?', [$a['id']]);
                    if ($order['payment_status'] === 'awaiting') {
                        $other = (int)DB::val("SELECT COUNT(*) FROM payment_attempts WHERE order_id = ? AND status = 'awaiting' AND id <> ?", [$order['id'], $a['id']]);
                        if (!$other) DB::update('orders', ['payment_status' => 'unpaid', 'updated_at' => $now], 'id = ?', [$order['id']]);
                    }
                    return 'failed_recorded';
                }
                // başarı: ana tutar ile taksit farkı içerebilen tahsilat ayrı değerlendirilir
                $paymentAmount = isset($post['payment_amount']) && ctype_digit((string)$post['payment_amount']) ? (int)$post['payment_amount'] : null;
                $collected = (int)$total;
                $amountOk = ($paymentAmount === null ? $collected >= (int)$a['amount_minor'] : $paymentAmount === (int)$a['amount_minor']) && $collected >= (int)$a['amount_minor'];
                DB::update('payment_attempts', ['status' => 'success', 'callback_status' => 'success', 'collected_minor' => $collected, 'processed_at' => $now], 'id = ?', [$a['id']]);
                DB::insert('payments', [
                    'order_id' => $order['id'], 'attempt_id' => $a['id'], 'mode' => $a['mode'],
                    'amount_minor' => $paymentAmount ?? (int)$a['amount_minor'], 'collected_minor' => $collected, 'currency' => $a['currency'],
                    'payment_type' => mb_substr((string)($post['payment_type'] ?? ''), 0, 30), 'created_at' => $now,
                ]);
                $paidCount = (int)DB::val('SELECT COUNT(*) FROM payments WHERE order_id = ? AND mode <> ?', [$order['id'], 'test']);
                $review = false; $reason = null;
                if (!$amountOk) { $review = true; $reason = 'amount_mismatch'; }
                if ($a['mode'] === 'live' && (in_array($order['payment_status'], ['paid', 'review'], true) || $paidCount > 1)) { $review = true; $reason = $reason ?? 'double_charge'; }
                if ($a['mode'] === 'test' && $order['payment_status'] === 'test_paid') { $review = true; $reason = $reason ?? 'double_charge_test'; }
                $upd = ['updated_at' => $now];
                if ($a['mode'] === 'test' && in_array($order['payment_status'], ['paid', 'review'], true)) {
                    // canlı ödenmiş siparişte geç gelen test tahsilatı durumu düşürmez; yalnız incelemeye
                    $upd['needs_review'] = 1;
                    $review = true; $reason = 'test_after_live';
                } elseif ($review) {
                    $upd['payment_status'] = 'review';
                    $upd['needs_review'] = 1;
                } else {
                    $upd['payment_status'] = $a['mode'] === 'live' ? 'paid' : 'test_paid';
                    $upd['paid_at'] = $now;
                    // Test tahsilatı ücretli yayın hakkı açmaz
                    if ($a['mode'] === 'live') $upd['publish_right'] = 1;
                }
                DB::update('orders', $upd, 'id = ?', [$order['id']]);
                Settings::set($a['mode'] === 'live' ? 'paytr.last_live_callback_at' : 'paytr.last_test_callback_at', $now);
                // e-postalar aynı işlemde kuyruğa; tekrar bildirimde benzersiz anahtar ikinciyi engeller
                $u = DB::one('SELECT email, name, locale FROM users WHERE id = ?', [$order['user_id']]);
                if (!$review) {
                    $lang = I18n::valid($u['locale']) ? $u['locale'] : 'tr';
                    DB::insert('email_queue', [
                        'dedupe_key' => 'paid:' . $order['id'] . ':' . $a['id'], 'to_email' => $u['email'], 'lang' => $lang, 'template' => 'paid',
                        'subject' => t('mail.paid.subject', ['order' => $order['public_no']], $lang),
                        'body_text' => t('mail.paid.body', ['name' => $u['name'], 'order' => $order['public_no'], 'link' => url('/panel/orders/' . $order['public_no'], $lang)], $lang) . ($a['mode'] === 'test' ? "\n\n[TEST]" : ''),
                        'created_at' => $now,
                    ]);
                } else {
                    $admin = Settings::get('mail.admin_notify') ?: DB::val("SELECT email FROM users WHERE role='admin' AND is_active=1 ORDER BY id LIMIT 1");
                    if ($admin) DB::insert('email_queue', [
                        'dedupe_key' => 'review:' . $a['id'], 'to_email' => $admin, 'lang' => 'tr', 'template' => 'admin_review',
                        'subject' => t('mail.admin_review.subject', ['order' => $order['public_no']], 'tr'),
                        'body_text' => t('mail.admin_review.body', ['order' => $order['public_no'], 'reason' => (string)$reason], 'tr'),
                        'created_at' => $now,
                    ]);
                }
                DB::insert('audit_log', ['user_id' => null, 'action' => $review ? 'payment.review' : 'payment.success', 'entity' => 'order', 'entity_id' => $order['id'],
                    'details_json' => jenc(['oid' => $oid, 'mode' => $a['mode'], 'collected' => $collected, 'reason' => $reason]), 'ip' => client_ip(), 'created_at' => $now]);
                return $review ? 'success_review' : 'success';
            });
        } catch (\Throwable $e) {
            Log::error('Ödeme bildirimi işlenemedi: ' . Log::scrub($e->getMessage()));
            $log(true, 'db_error', $cbMode);
            return ['PAYTR notification failed: temporary error', 'db_error']; // kayıt başarısız: OK dönme, PayTR tekrar dener
        }
        $log(true, $outcome, $cbMode);
        App::onShutdown(fn() => Mailer::processQueue(5));
        if ($outcome === 'mode_mismatch' || $outcome === 'store_mismatch') {
            return ['PAYTR notification failed: ' . $outcome, $outcome];
        }
        // unknown_oid: imza geçerli; inceleme için günlüğe alındı
        return ['OK', $outcome];
    }

    /** Yönetici: havale vb. çevrim dışı ödeme kaydı */
    public static function markManual(array $order, int $adminId, string $note): void
    {
        DB::tx(function () use ($order, $adminId, $note) {
            DB::insert('payments', ['order_id' => $order['id'], 'attempt_id' => null, 'mode' => 'manual', 'amount_minor' => $order['total_minor'],
                'collected_minor' => $order['total_minor'], 'currency' => $order['currency'], 'note' => mb_substr($note, 0, 490), 'created_by' => $adminId, 'created_at' => DB::now()]);
            DB::update('orders', ['payment_status' => 'paid', 'publish_right' => 1, 'paid_at' => DB::now(), 'updated_at' => DB::now()], 'id = ?', [$order['id']]);
        });
        Audit::log('payment.manual', 'order', (int)$order['id'], ['note' => $note]);
    }
}
