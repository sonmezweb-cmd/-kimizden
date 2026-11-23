<?php
use App\Auth;
use App\Settings;
use App\View;
$u = Auth::user();
$company = array_filter([
    'name' => Settings::get('company.name'), 'address' => Settings::get('company.address'),
    'phone' => Settings::get('company.phone'), 'email' => Settings::get('company.email'),
]);
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head><?= View::partial('partials/head', get_defined_vars()) ?></head>
<body class="site <?= e($bodyClass ?? '') ?>">
<a class="skip" href="#main"><?= e(t('a11y.skip')) ?></a>
<header class="site-head">
  <div class="wrap site-head-in">
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e(Settings::get('site.name', 'İkimizden')) ?> — <?= e(t('brand.home')) ?>">
      <img src="<?= e(\App\App::baseUrl()) ?>/assets/img/logo-360.webp" width="180" height="60" alt="İkimizden — dijital davetiye">
    </a>
    <nav class="site-nav" aria-label="<?= e(t('nav.main')) ?>">
      <a href="<?= e(url('/themes')) ?>" <?= is_active_path('/themes') ? 'aria-current="page"' : '' ?>><?= e(t('nav.themes')) ?></a>
      <a href="<?= e(url('/packages')) ?>" <?= is_active_path('/packages') ? 'aria-current="page"' : '' ?>><?= e(t('nav.packages')) ?></a>
      <a href="<?= e(url('/how-it-works')) ?>" <?= is_active_path('/how-it-works') ? 'aria-current="page"' : '' ?>><?= e(t('nav.how')) ?></a>
      <a href="<?= e(url('/faq')) ?>" <?= is_active_path('/faq') ? 'aria-current="page"' : '' ?>><?= e(t('nav.faq')) ?></a>
      <a href="<?= e(url('/contact')) ?>" <?= is_active_path('/contact') ? 'aria-current="page"' : '' ?>><?= e(t('nav.contact')) ?></a>
    </nav>
    <div class="site-tools">
      <?= View::partial('partials/lang') ?>
      <?php if ($u): ?>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/panel')) ?>"><?= e(t('nav.panel')) ?></a>
      <?php else: ?>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/login')) ?>"><?= e(t('nav.login')) ?></a>
      <?php endif; ?>
      <details class="menu-mobile">
        <summary aria-label="<?= e(t('nav.menu')) ?>"><?= View::partial('partials/icon', ['name' => 'menu']) ?></summary>
        <div class="menu-mobile-panel">
          <a href="<?= e(url('/themes')) ?>"><?= e(t('nav.themes')) ?></a>
          <a href="<?= e(url('/packages')) ?>"><?= e(t('nav.packages')) ?></a>
          <a href="<?= e(url('/how-it-works')) ?>"><?= e(t('nav.how')) ?></a>
          <a href="<?= e(url('/faq')) ?>"><?= e(t('nav.faq')) ?></a>
          <a href="<?= e(url('/contact')) ?>"><?= e(t('nav.contact')) ?></a>
          <?php if ($u): ?><a href="<?= e(url('/panel')) ?>"><?= e(t('nav.panel')) ?></a><?php else: ?><a href="<?= e(url('/login')) ?>"><?= e(t('nav.login')) ?></a><a href="<?= e(url('/register')) ?>"><?= e(t('nav.register')) ?></a><?php endif; ?>
        </div>
      </details>
    </div>
  </div>
</header>
<main id="main">
  <div class="wrap"><?= View::partial('partials/flash') ?></div>
  <?= $content ?>
</main>
<footer class="site-foot">
  <div class="wrap foot-grid">
    <div>
      <img src="<?= e(\App\App::baseUrl()) ?>/assets/img/logo-360.webp" width="150" height="50" alt="" loading="lazy">
      <?php if ($company): ?>
        <address class="foot-company">
          <?php if (!empty($company['name'])): ?><strong><?= e($company['name']) ?></strong><br><?php endif; ?>
          <?php if (!empty($company['address'])): ?><?= nl2br(e($company['address'])) ?><br><?php endif; ?>
          <?php if (!empty($company['phone'])): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $company['phone'])) ?>"><?= e($company['phone']) ?></a><br><?php endif; ?>
          <?php if (!empty($company['email'])): ?><a href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a><?php endif; ?>
        </address>
      <?php endif; ?>
    </div>
    <nav aria-label="<?= e(t('nav.footer')) ?>" class="foot-links">
      <a href="<?= e(url('/themes')) ?>"><?= e(t('nav.themes')) ?></a>
      <a href="<?= e(url('/packages')) ?>"><?= e(t('nav.packages')) ?></a>
      <a href="<?= e(url('/faq')) ?>"><?= e(t('nav.faq')) ?></a>
      <a href="<?= e(url('/contact')) ?>"><?= e(t('nav.contact')) ?></a>
    </nav>
    <nav aria-label="<?= e(t('nav.legal')) ?>" class="foot-links">
      <a href="<?= e(url('/legal/privacy')) ?>"><?= e(t('legal.privacy')) ?></a>
      <a href="<?= e(url('/legal/kvkk')) ?>"><?= e(t('legal.kvkk')) ?></a>
      <a href="<?= e(url('/legal/terms')) ?>"><?= e(t('legal.terms')) ?></a>
      <a href="<?= e(url('/legal/cookies')) ?>"><?= e(t('legal.cookies')) ?></a>
    </nav>
    <div><?= View::partial('partials/lang', ['cls' => 'lang-foot']) ?></div>
  </div>
</footer>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
