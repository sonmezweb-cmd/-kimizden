<?php
declare(strict_types=1);

namespace App;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['uid'] ?? null;
            if ($id) {
                $u = DB::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id]);
                // parola değişince eski oturumlar geçersiz
                if ($u && hash_equals($_SESSION['upw'] ?? '', substr(hash('sha256', $u['password_hash']), 0, 16))) {
                    self::$user = $u;
                } else {
                    unset($_SESSION['uid'], $_SESSION['upw']);
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::user() ? (int)self::user()['id'] : null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function is(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function login(array $u): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['upw'] = substr(hash('sha256', $u['password_hash']), 0, 16);
        unset($_SESSION['_csrf']);
        self::$user = $u;
        self::$loaded = true;
        DB::update('users', ['last_login_at' => DB::now()], 'id = ?', [$u['id']]);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$user = null;
    }

    /** @param string[]|null $roles */
    public static function require(?array $roles = null): array
    {
        $u = self::user();
        if (!$u) {
            if (wants_json()) json_out(['ok' => false, 'error' => t('auth.login_required')], 401);
            $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
            redirect(url('/login'));
        }
        if ($roles && !in_array($u['role'], $roles, true)) {
            abort(403, t('error.forbidden'));
        }
        return $u;
    }

    public static function hash(string $pw): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        return password_hash($pw, $algo);
    }

    public static function passwordOk(string $pw): bool
    {
        return mb_strlen($pw) >= 10;
    }
}
