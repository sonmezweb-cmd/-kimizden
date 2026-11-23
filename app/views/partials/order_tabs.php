<?php
use App\Auth;
$no = $order['public_no'];
$tabs = [
    'overview' => ['/panel/orders/' . $no, 'tab.overview'],
    'form' => ['/panel/orders/' . $no . '/form/names', 'tab.form'],
    'photos' => ['/panel/orders/' . $no . '/photos', 'tab.photos'],
    'preview' => ['/panel/orders/' . $no . '/preview', 'tab.preview'],
    'share' => ['/panel/orders/' . $no . '/share', 'tab.share'],
    'guests' => ['/panel/orders/' . $no . '/guests', 'tab.guests'],
    'rsvps' => ['/panel/orders/' . $no . '/rsvps', 'tab.rsvps'],
];
$theme = $order['snapshot']['theme']['name'] ?? [];
?>
<div class="order-head">
  <div>
    <p class="eyebrow"><?= e(t('panel.order')) ?> <?= e($no) ?><?php if (Auth::is('admin')): ?> · <a href="<?= e(url('/admin/orders/' . $no)) ?>"><?= e(t('admin.manage')) ?></a><?php endif; ?></p>
    <h1 class="h2"><?= e(loc($theme)) ?> · <?= e(loc($order['snapshot']['package']['name'] ?? [])) ?></h1>
  </div>
  <div class="status-row">
    <?= \App\View::partial('partials/status', ['order' => $order]) ?>
  </div>
</div>
<nav class="tabs" aria-label="<?= e(t('panel.order')) ?>">
  <?php foreach ($tabs as $k => [$p, $label]): ?>
    <a href="<?= e(url($p)) ?>" <?= ($tab ?? '') === $k ? 'aria-current="page"' : '' ?>><?= e(t($label)) ?></a>
  <?php endforeach; ?>
</nav>
