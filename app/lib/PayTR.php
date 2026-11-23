<?php
declare(strict_types=1);

namespace App;

/**
 * PayTR iFrame API.
 * 1. adım: sunucudan get-token isteği; imza:
 *    base64(hmac_sha256(merchant_id.user_ip.merchant_oid.email.payment_amount.user_basket.no_installment.max_installment.currency.test_mode . merchant_salt, merchant_key))
 * 2. adım: bildirim (callback) imzası:
 *    base64(hmac_sha256(merchant_oid . merchant_salt . status . total_amount, merchant_key))
 * Mağaza bilgileri yönetim panelinden girilir, şifreli saklanır.
 */
final class PayTR
{
    public const TOKEN_URL = 'https://www.paytr.com/odeme/api/get-token';
    public const IFRAME_URL = 'https://www.paytr.com/odeme/guvenli/';
    public const RESIZER_URL = 'https://www.paytr.com/js/iframeResizer.min.js';

    public static function enabled(): bool
    {
        return Settings::get('paytr.enabled') === '1' && self::configured();
    }

    public static function configured(): bool
    {
        return (string)Settings::get('paytr.merchant_id', '') !== '' && Settings::hasSecret('paytr.merchant_key') && Settings::hasSecret('paytr.merchant_salt');
    }

    public static function mode(): string
    {
        return Settings::get('paytr.mode', 'test') === 'live' ? 'live' : 'test';
    }

    public static function callbackUrl(): string
    {
        return url_raw('/paytr/callback');
    }

    /** PayTR'nin para birimi kodu (TL, USD, EUR, GBP, RUB). */
    public static function currencyCode(string $iso): string
    {
        return $iso === 'TRY' ? 'TL' : $iso;
    }

    /**
     * Ödeme sayfası dili. PayTR iFrame dil parametresi resmi dokümanda 'tr' ve 'en' olarak tanımlıdır;
     * site Rusça iken ödeme formu İngilizce açılır ve bu kullanıcıya Rusça açıklanır.
     */
    public static function lang(string $siteLang): string
    {
        return $siteLang === 'tr' ? 'tr' : 'en';
    }

    public static function tokenHash(array $f, string $key, string $salt): string
    {
        $str = $f['merchant_id'] . $f['user_ip'] . $f['merchant_oid'] . $f['email'] . $f['payment_amount'] . $f['user_basket']
            . $f['no_installment'] . $f['max_installment'] . $f['currency'] . $f['test_mode'];
        return base64_encode(hash_hmac('sha256', $str . $salt, $key, true));
    }

    public static function callbackHash(string $oid, string $status, string $total, string $key, string $salt): string
    {
        return base64_encode(hash_hmac('sha256', $oid . $salt . $status . $total, $key, true));
    }

    /**
     * Token ister. @return array{ok:bool, token?:string, error?:string}
     */
    public static function requestToken(array $order, array $attempt, string $siteLang): array
    {
        $key = (string)Settings::secret('paytr.merchant_key');
        $salt = (string)Settings::secret('paytr.merchant_salt');
        $snap = $order['snapshot'];
        $basket = [[mb_substr(loc($snap['package']['name'], 'tr') . ' — ' . loc($snap['theme']['name'], 'tr'), 0, 100), number_format($attempt['amount_minor'] / 100, 2, '.', ''), 1]];
        $noInst = Settings::get('paytr.no_installment', '1') === '1' ? '1' : '0';
        $maxInst = (string)max(0, min(12, (int)Settings::get('paytr.max_installment', '0')));
        $f = [
            'merchant_id' => (string)Settings::get('paytr.merchant_id'),
            'user_ip' => client_ip(),
            'merchant_oid' => $attempt['merchant_oid'],
            'email' => $order['user_email'],
            'payment_amount' => (string)$attempt['amount_minor'],
            'user_basket' => base64_encode(json_encode($basket, JSON_UNESCAPED_UNICODE)),
            'no_installment' => $noInst,
            'max_installment' => $maxInst,
            'currency' => self::currencyCode($attempt['currency']),
            'test_mode' => $attempt['mode'] === 'test' ? '1' : '0',
        ];
        $post = $f + [
            'paytr_token' => self::tokenHash($f, $key, $salt),
            'debug_on' => $attempt['mode'] === 'test' ? '1' : '0',
            'user_name' => mb_substr($order['user_name'], 0, 60),
            'user_address' => mb_substr((string)Settings::get('paytr.default_address', 'Türkiye'), 0, 400),
            'user_phone' => mb_substr($order['user_phone'] ?: '0000000000', 0, 20),
            'merchant_ok_url' => url('/pay/' . $order['public_no'] . '/return', $siteLang, ['r' => 'ok']),
            'merchant_fail_url' => url('/pay/' . $order['public_no'] . '/return', $siteLang, ['r' => 'fail']),
            'timeout_limit' => '30',
            'lang' => self::lang($siteLang),
        ];
        $endpoint = App::$config['paytr_token_url'] ?? self::TOKEN_URL; // yalnız yerel test için değiştirilebilir
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_FRESH_CONNECT => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            Log::error('PayTR token isteği bağlantı hatası: ' . $cerr);
            return ['ok' => false, 'error' => 'unreachable'];
        }
        $j = json_decode((string)$raw, true);
        if (($j['status'] ?? '') === 'success' && !empty($j['token'])) {
            return ['ok' => true, 'token' => (string)$j['token']];
        }
        $reason = Log::scrub((string)($j['reason'] ?? mb_substr((string)$raw, 0, 200)));
        Log::error('PayTR token reddedildi: ' . $reason);
        return ['ok' => false, 'error' => 'rejected', 'reason' => $reason];
    }
}
