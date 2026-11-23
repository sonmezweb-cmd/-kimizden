<?php
use App\App;
use App\Content;
use App\I18n;
$col = $theme['colors'];
[$na, $nb] = $names;
$ogTitle = trim($na . ' & ' . $nb, ' &') ?: loc($theme['name']);
$ogDesc = $cover ? \App\Fmt::dateCover($cover['start_utc'], $cover['tz'], $glang) : '';
$ogImg = $coverPhoto['l'] ?? $theme['media']['preview'];
$noindex = $mode !== 'showcase';
?><!doctype html>
<html lang="<?= e($glang) ?>" class="inv-html">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($ogTitle) ?><?= $mode === 'showcase' ? ' · ' . e(t('showcase.label')) : '' ?></title>
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if (!empty($canonical)): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
<?php /* Paylaşım önizlemesi: yalnız canlı sürümün herkese açık bilgileri; grup adı/özel bağlantı yok */ ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<?php if ($ogDesc): ?><meta property="og:description" content="<?= e($ogDesc) ?>"><?php endif; ?>
<meta property="og:image" content="<?= e($ogImg) ?>">
<?php if (!empty($canonical)): ?><meta property="og:url" content="<?= e($canonical) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e($col['surface'] ?? '#5a1630') ?>">
<link rel="icon" href="<?= e(App::baseUrl()) ?>/favicon.ico" sizes="any">
<link rel="preload" as="image" href="<?= e($theme['media']['poster']) ?>" fetchpriority="high">
<link rel="stylesheet" href="<?= e(asset('css/invite.css')) ?>">
<script src="<?= e(asset('js/boot.js')) ?>"></script>
</head>
<body class="inv theme-<?= e($theme['slug']) ?> mode-<?= e($mode) ?>" style="--t-surface: <?= e($col['surface'] ?? '#5a1630') ?>; --t-ink: <?= e($col['ink'] ?? '#3a1426') ?>; --t-accent: <?= e($col['accent'] ?? '#b87459') ?>; --t-paper: <?= e($col['paper'] ?? '#f6efe4') ?>; --t-lining: <?= e($col['lining'] ?? '#2b0816') ?>; --t-edge: <?= e($col['paperEdge'] ?? ($col['accent'] ?? '#b87459')) ?>; --paper-img: url('<?= e($theme['media']['paper'] ?? '') ?>');">
<?= $content ?>
<script src="<?= e(asset('js/envelope.js')) ?>" defer></script>
<script src="<?= e(asset('js/invite.js')) ?>" defer></script>
</body>
</html>
