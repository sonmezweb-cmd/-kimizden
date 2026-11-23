<!doctype html>
<html lang="<?= e($lang ?? 'tr') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title><?= e($title ?? 'İkimizden') ?></title>
<link rel="icon" href="<?= e(\App\App::baseUrl()) ?>/favicon.ico">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="bare">
<main id="main" class="bare-main"><?= $content ?></main>
</body>
</html>
