<?php
use App\Auth;
use App\View;
$u = Auth::user();
$role = $u['role'] ?? 'customer';
$ic = fn(string $n) => View::partial('partials/icon', ['name' => $n]);
if ($role === 'admin') {
    $nav = [
        ['/admin', 'admin.dashboard', 'home', true],
        ['/admin/orders', 'admin.orders', 'orders', true],
        ['/designer', 'designer.title', 'brush', false],
        ['/admin/users', 'admin.users', 'users', false],
        ['/admin/packages', 'admin.packages', 'package', true],
        ['/admin/themes', 'admin.themes', 'theme', true],
        ['/admin/translations', 'admin.translations', 'globe', false],
        ['/admin/emails', 'admin.emails', 'mail', false],
        ['/admin/settings/general', 'admin.settings', 'settings', true],
        ['/admin/messages', 'admin.messages', 'chat', false],
        ['/admin/audit', 'admin.audit', 'log', false],
        ['/admin/system', 'admin.system', 'tool', false],
    ];
} elseif ($role === 'designer') {
    $nav = [['/designer', 'designer.title', 'brush', true], ['/panel/account', 'panel.account', 'user', true]];
} else {
    $nav = [['/panel', 'panel.my_orders', 'orders', true], ['/themes', 'nav.themes', 'theme', true], ['/panel/account', 'panel.account', 'user', true]];
}
$active = function (string $p) {
    if ($p === '/admin' || $p === '/panel') return current_path() === $p;
    if ($p === '/admin/settings/general') return str_starts_with(current_path(), '/admin/settings');
    return is_active_path($p);
};
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head><?= View::partial('partials/head', get_defined_vars()) ?></head>
<body class="app role-<?= e($role) ?>">
<a class="skip" href="#main"><?= e(t('a11y.skip')) ?></a>
<header class="app-top">
  <a class="brand" href="<?= e(url($role === 'admin' ? '/admin' : ($role === 'designer' ? '/designer' : '/panel'))) ?>">
    <img src="<?= e(\App\App::baseUrl()) ?>/assets/img/logo-360.webp" width="150" height="50" alt="İkimizden — dijital davetiye">
  </a>
  <div class="app-top-tools">
    <span class="save-state" id="save-state" role="status" aria-live="polite"></span>
    <?= View::partial('partials/lang') ?>
    <span class="who" title="<?= e($u['email'] ?? '') ?>"><?= e($u['name'] ?? '') ?> · <?= e(t('role.' . $role)) ?></span>
    <form method="post" action="<?= e(url('/logout')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit"><?= $ic('logout') ?><span class="hide-sm"><?= e(t('nav.logout')) ?></span></button></form>
  </div>
</header>
<div class="app-body">
  <nav class="app-side" aria-label="<?= e(t('nav.panel')) ?>">
    <?php foreach ($nav as [$p, $k, $i]): ?>
      <a href="<?= e(url($p)) ?>" <?= $active($p) ? 'aria-current="page"' : '' ?>><?= $ic($i) ?><span><?= e(t($k)) ?></span></a>
    <?php endforeach; ?>
    <?php if ($role === 'admin'): ?>
      <div class="side-sub">
        <a href="<?= e(url('/admin/settings/payments')) ?>" <?= current_path() === '/admin/settings/payments' ? 'aria-current="page"' : '' ?>><?= e(t('admin.settings.payments')) ?></a>
        <a href="<?= e(url('/admin/settings/smtp')) ?>" <?= current_path() === '/admin/settings/smtp' ? 'aria-current="page"' : '' ?>><?= e(t('admin.settings.smtp')) ?></a>
        <a href="<?= e(url('/admin/settings/legal')) ?>" <?= current_path() === '/admin/settings/legal' ? 'aria-current="page"' : '' ?>><?= e(t('admin.settings.legal')) ?></a>
      </div>
    <?php endif; ?>
  </nav>
  <main id="main" class="app-main">
    <?= View::partial('partials/flash') ?>
    <?= $content ?>
  </main>
</div>
<nav class="app-bottom" aria-label="<?= e(t('nav.panel')) ?>">
  <?php foreach (array_filter($nav, fn($n) => $n[3]) as [$p, $k, $i]): ?>
    <a href="<?= e(url($p)) ?>" <?= $active($p) ? 'aria-current="page"' : '' ?>><?= $ic($i) ?><span><?= e(t($k)) ?></span></a>
  <?php endforeach; ?>
  <?php if ($role === 'admin'): ?>
    <details class="bottom-more">
      <summary><?= $ic('menu') ?><span><?= e(t('nav.more')) ?></span></summary>
      <div class="bottom-more-panel">
        <?php foreach ($nav as [$p, $k, $i, $main]): if ($main) continue; ?>
          <a href="<?= e(url($p)) ?>"><?= $ic($i) ?> <?= e(t($k)) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url('/admin/settings/payments')) ?>"><?= $ic('settings') ?> <?= e(t('admin.settings.payments')) ?></a>
        <a href="<?= e(url('/admin/settings/smtp')) ?>"><?= $ic('mail') ?> <?= e(t('admin.settings.smtp')) ?></a>
        <a href="<?= e(url('/admin/settings/legal')) ?>"><?= $ic('log') ?> <?= e(t('admin.settings.legal')) ?></a>
      </div>
    </details>
  <?php endif; ?>
</nav>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
