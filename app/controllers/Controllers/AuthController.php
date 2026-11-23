<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\DB;
use App\I18n;
use App\Mailer;
use App\RateLimit;
use App\View;

final class AuthController
{
    private function home(array $u): string
    {
        $after = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        if (is_string($after) && str_starts_with($after, '/') && !str_starts_with($after, '//')) {
            return \App\App::baseUrl() . substr($after, strlen(\App\App::basePath()));
        }
        return match ($u['role']) {
            'admin' => url('/admin'),
            'designer' => url('/designer'),
            default => url('/panel'),
        };
    }

    public function loginForm(): void
    {
        if ($u = Auth::user()) redirect($this->home($u));
        View::show('auth/login', ['title' => t('auth.login'), 'robots' => 'noindex']);
    }

    public function login(): void
    {
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $pw = (string)($_POST['password'] ?? '');
        $bucket = 'login:' . $email . '|' . client_ip();
        $ipBucket = 'login-ip:' . client_ip();
        if (RateLimit::blocked($bucket, 5, 15) || RateLimit::blocked($ipBucket, 30, 15)) {
            flash('error', t('auth.too_many'));
            View::show('auth/login', ['title' => t('auth.login'), 'email' => $email, 'robots' => 'noindex'], 'site', 429);
            return;
        }
        $u = DB::one('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);
        if (!$u || !password_verify($pw, $u['password_hash'])) {
            RateLimit::hit($bucket, 1000, 15);
            RateLimit::hit($ipBucket, 1000, 15);
            flash('error', t('auth.invalid'));
            View::show('auth/login', ['title' => t('auth.login'), 'email' => $email, 'robots' => 'noindex'], 'site', 422);
            return;
        }
        if (password_needs_rehash($u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
            $u['password_hash'] = Auth::hash($pw);
            DB::update('users', ['password_hash' => $u['password_hash']], 'id = ?', [$u['id']]);
        }
        RateLimit::clear($bucket);
        Auth::login($u);
        redirect($this->home($u));
    }

    public function registerForm(): void
    {
        if (Auth::user()) redirect(url('/panel'));
        View::show('auth/register', ['title' => t('auth.register'), 'robots' => 'noindex']);
    }

    public function register(): void
    {
        $name = str_in('name', 120);
        $email = mb_strtolower(str_in('email', 190));
        $phone = str_in('phone', 40);
        $pw = (string)($_POST['password'] ?? '');
        $errors = [];
        if ($name === '') $errors['name'] = t('v.required');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = t('v.email');
        if (!Auth::passwordOk($pw)) $errors['password'] = t('v.password_len');
        if (empty($_POST['accept'])) $errors['accept'] = t('v.accept');
        if (!RateLimit::hit('register:' . client_ip(), 10, 60)) $errors['email'] = t('auth.too_many');
        if (!$errors && DB::val('SELECT 1 FROM users WHERE email = ?', [$email])) $errors['email'] = t('auth.email_taken');
        if ($errors) {
            View::show('auth/register', ['title' => t('auth.register'), 'errors' => $errors, 'old' => compact('name', 'email', 'phone'), 'robots' => 'noindex'], 'site', 422);
            return;
        }
        $now = DB::now();
        $id = DB::insert('users', ['email' => $email, 'password_hash' => Auth::hash($pw), 'name' => $name, 'phone' => $phone ?: null,
            'role' => 'customer', 'locale' => I18n::$lang, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        Auth::login(DB::one('SELECT * FROM users WHERE id = ?', [$id]));
        redirect($this->home(Auth::user()));
    }

    public function logout(): void
    {
        Auth::logout();
        redirect(url('/'));
    }

    public function forgotForm(): void
    {
        View::show('auth/forgot', ['title' => t('auth.forgot'), 'robots' => 'noindex']);
    }

    public function forgot(): void
    {
        $email = mb_strtolower(str_in('email', 190));
        if (RateLimit::hit('forgot:' . client_ip(), 8, 60)) {
            $u = DB::one('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);
            if ($u) {
                $token = random_token(24);
                DB::insert('password_resets', ['user_id' => $u['id'], 'token_hash' => hash('sha256', $token), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600), 'created_at' => DB::now()]);
                Mailer::queueTemplate($u['email'], $u['locale'], 'reset', ['name' => $u['name'], 'link' => url('/reset/' . $token, $u['locale'])], 'reset:' . hash('sha256', $token));
            }
        }
        // hesabın varlığını sızdırma
        flash('success', t('auth.reset_sent'));
        redirect(url('/forgot'));
    }

    private function validToken(string $token): ?array
    {
        return DB::one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?', [hash('sha256', $token), DB::now()]);
    }

    public function resetForm(string $token): void
    {
        $ok = (bool)$this->validToken($token);
        View::show('auth/reset', ['title' => t('auth.reset'), 'token' => $token, 'valid' => $ok, 'robots' => 'noindex']);
    }

    public function reset(string $token): void
    {
        $row = $this->validToken($token);
        $pw = (string)($_POST['password'] ?? '');
        if (!$row) { flash('error', t('auth.reset_invalid')); redirect(url('/forgot')); }
        if (!Auth::passwordOk($pw)) {
            View::show('auth/reset', ['title' => t('auth.reset'), 'token' => $token, 'valid' => true, 'errors' => ['password' => t('v.password_len')], 'robots' => 'noindex'], 'site', 422);
            return;
        }
        DB::tx(function () use ($row, $pw) {
            DB::update('users', ['password_hash' => Auth::hash($pw), 'updated_at' => DB::now()], 'id = ?', [$row['user_id']]);
            DB::update('password_resets', ['used_at' => DB::now()], 'id = ?', [$row['id']]);
        });
        flash('success', t('auth.reset_done'));
        redirect(url('/login'));
    }
}
