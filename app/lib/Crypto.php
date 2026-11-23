<?php
declare(strict_types=1);

namespace App;

/**
 * Sır şifreleme: libsodium secretbox. Anahtar storage/config.php içinde (veritabanında değil).
 */
final class Crypto
{
    private static function key(): string
    {
        $k = App::$config['app_key'] ?? '';
        $raw = base64_decode($k, true);
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('Uygulama şifreleme anahtarı eksik veya geçersiz.');
        }
        return $raw;
    }

    public static function newKey(): string
    {
        return base64_encode(sodium_crypto_secretbox_keygen());
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(string $enc): ?string
    {
        if (!str_starts_with($enc, 'v1:')) return null;
        $raw = base64_decode(substr($enc, 3), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1) return null;
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $out = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, self::key());
        return $out === false ? null : $out;
    }
}
