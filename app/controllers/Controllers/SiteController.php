<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Catalog;
use App\DB;
use App\I18n;
use App\RateLimit;
use App\Settings;
use App\View;

final class SiteController
{
    private function seo(string $titleKey, string $descKey, array $extra = []): array
    {
        return array_merge(['title' => t($titleKey), 'description' => t($descKey), 'hreflang' => true], $extra);
    }

    public function home(): void
    {
        $themes = Catalog::themes();
        View::show('site/home', $this->seo('seo.home.title', 'seo.home.desc', ['themes' => $themes, 'packages' => Catalog::packages(true), 'bodyClass' => 'is-home', 'extraCss' => ['css/envelope.css']]));
    }

    public function themes(): void
    {
        View::show('site/themes', $this->seo('seo.themes.title', 'seo.themes.desc', ['themes' => Catalog::themes()]));
    }

    public function theme(string $slug): void
    {
        $t = Catalog::themeBySlug($slug);
        if (!$t || !(int)$t['is_active']) abort(404, t('error.not_found'));
        View::show('site/theme', [
            'title' => loc($t['name']) . ' — ' . t('brand.tagline'), 'description' => loc($t['description']), 'hreflang' => true,
            'theme' => $t, 'packages' => Catalog::packages(true), 'addons' => Catalog::addons(),
            'ogImage' => $t['media']['preview'],
        ]);
    }

    public function packages(): void
    {
        View::show('site/packages', $this->seo('seo.packages.title', 'seo.packages.desc', ['packages' => Catalog::packages(true), 'addons' => Catalog::addons(), 'themes' => Catalog::themes()]));
    }

    public function how(): void
    {
        View::show('site/how', $this->seo('seo.how.title', 'seo.how.desc'));
    }

    public function faq(): void
    {
        View::show('site/faq', $this->seo('seo.faq.title', 'seo.faq.desc'));
    }

    public function contact(): void
    {
        View::show('site/contact', $this->seo('seo.contact.title', 'seo.contact.desc'));
    }

    public function contactSubmit(): void
    {
        $name = str_in('name', 120);
        $email = str_in('email', 190);
        $msg = str_in('message', 4000);
        $errors = [];
        if ($name === '') $errors['name'] = t('v.required');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = t('v.email');
        if (mb_strlen($msg) < 5) $errors['message'] = t('v.required');
        if (!empty($_POST['website'])) $errors['message'] = t('error.generic'); // bal küpü
        if (!$errors && !RateLimit::hit('contact:' . client_ip(), 5, 60)) $errors['message'] = t('auth.too_many');
        if ($errors) {
            View::show('site/contact', $this->seo('seo.contact.title', 'seo.contact.desc', ['errors' => $errors, 'old' => compact('name', 'email', 'msg')]), 'site', 422);
            return;
        }
        DB::insert('contact_messages', ['name' => $name, 'email' => $email, 'message' => $msg, 'lang' => I18n::$lang, 'ip' => client_ip(), 'created_at' => DB::now()]);
        flash('success', t('contact.sent'));
        redirect(url('/contact'));
    }

    public function legal(string $key): void
    {
        if (!in_array($key, ['privacy', 'terms', 'kvkk', 'cookies'], true)) abort(404);
        $p = DB::one('SELECT * FROM pages WHERE k = ? AND lang = ?', [$key, I18n::$lang]) ?? DB::one('SELECT * FROM pages WHERE k = ? AND lang = ?', [$key, 'tr']);
        View::show('site/legal', ['title' => $p['title'] ?? t('legal.' . $key), 'page' => $p, 'key' => $key, 'hreflang' => true]);
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $paths = ['/', '/themes', '/packages', '/how-it-works', '/faq', '/contact'];
        foreach (Catalog::themes() as $t) $paths[] = '/themes/' . $t['slug'];
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($paths as $p) {
            foreach (I18n::LANGS as $l) {
                echo '<url><loc>' . e(url($p, $l)) . '</loc>';
                foreach (I18n::LANGS as $l2) echo '<xhtml:link rel="alternate" hreflang="' . $l2 . '" href="' . e(url($p, $l2)) . '"/>';
                echo '<xhtml:link rel="alternate" hreflang="x-default" href="' . e(url($p, 'tr')) . '"/>';
                echo "</url>\n";
            }
        }
        echo '</urlset>';
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $b = \App\App::basePath();
        echo "User-agent: *\n";
        foreach (['/panel', '/admin', '/designer', '/checkout', '/pay', '/i/', '/p/', '/m/', '/install', '/login', '/register', '/forgot', '/reset'] as $p) {
            echo "Disallow: $b$p\n";
            foreach (['en', 'ru'] as $l) echo "Disallow: $b/$l$p\n";
        }
        echo 'Sitemap: ' . url_raw('/sitemap.xml') . "\n";
    }
}
