<?php
use App\I18n;
use App\Settings;
$siteName = Settings::get('site.name', 'İkimizden');
$pageTitle = isset($title) && $title !== '' ? $title . ' · ' . $siteName : $siteName . ' — ' . t('brand.tagline');
$path = current_path();
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<?php if (!empty($description)): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
<?php if (!empty($robots)): ?><meta name="robots" content="<?= e($robots) ?>"><?php endif; ?>
<?php if (!empty($hreflang)): ?>
<link rel="canonical" href="<?= e(url($path)) ?>">
<?php foreach (I18n::LANGS as $l): ?><link rel="alternate" hreflang="<?= $l ?>" href="<?= e(url($path, $l)) ?>">
<?php endforeach; ?><link rel="alternate" hreflang="x-default" href="<?= e(url($path, 'tr')) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($title ?? $siteName) ?>">
<?php if (!empty($description)): ?><meta property="og:description" content="<?= e($description) ?>"><?php endif; ?>
<meta property="og:url" content="<?= e(url($path)) ?>">
<meta property="og:locale" content="<?= e(I18n::locale()) ?>">
<meta property="og:image" content="<?= e($ogImage ?? (\App\App::baseUrl() . '/assets/img/logo-720.png')) ?>">
<?php endif; ?>
<meta name="theme-color" content="#fbf6ee">
<link rel="icon" href="<?= e(\App\App::baseUrl()) ?>/favicon.ico" sizes="any">
<link rel="icon" type="image/png" href="<?= e(\App\App::baseUrl()) ?>/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="<?= e(\App\App::baseUrl()) ?>/apple-touch-icon.png">
<link rel="preload" href="<?= e(asset('fonts/Inter-var.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/boot.js')) ?>"></script>
