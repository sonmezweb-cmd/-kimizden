<?php use App\Fmt; use App\View; ?>
<h1 class="h2"><?= e(t('admin.orders')) ?></h1>
<form class="filters" method="get">
  <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('admin.search_orders')) ?>" aria-label="<?= e(t('admin.search_orders')) ?>">
  <select name="pay" aria-label="<?= e(t('status.payment')) ?>"><option value=""><?= e(t('status.payment')) ?></option><?php foreach (['unpaid', 'awaiting', 'paid', 'test_paid', 'review'] as $s): ?><option value="<?= $s ?>" <?= $f['pay'] === $s ? 'selected' : '' ?>><?= e(t('status.pay.' . $s)) ?></option><?php endforeach; ?></select>
  <select name="design" aria-label="<?= e(t('status.design')) ?>"><option value=""><?= e(t('status.design')) ?></option><?php foreach (['info_pending', 'in_design', 'awaiting_approval', 'revision_requested', 'approved'] as $s): ?><option value="<?= $s ?>" <?= $f['design'] === $s ? 'selected' : '' ?>><?= e(t('status.design.' . $s)) ?></option><?php endforeach; ?></select>
  <select name="pub" aria-label="<?= e(t('status.publish')) ?>"><option value=""><?= e(t('status.publish')) ?></option><?php foreach (['unpublished', 'published', 'archived'] as $s): ?><option value="<?= $s ?>" <?= $f['pub'] === $s ? 'selected' : '' ?>><?= e(t('status.pub.' . $s)) ?></option><?php endforeach; ?></select>
  <button class="btn btn-sm" type="submit"><?= e(t('common.filter')) ?></button>
</form>
<div class="table-wrap">
<table class="table">
  <thead><tr><th><?= e(t('panel.order')) ?></th><th><?= e(t('admin.customer')) ?></th><th><?= e(t('checkout.total')) ?></th><th><?= e(t('status.title')) ?></th><th><?= e(t('admin.designer')) ?></th><th><?= e(t('admin.created')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $o): ?>
    <tr>
      <td><a href="<?= e(url('/admin/orders/' . $o['public_no'])) ?>"><strong><?= e($o['public_no']) ?></strong></a><?= (int)$o['needs_review'] ? ' <span class="badge badge-warn">' . e(t('admin.needs_review')) . '</span>' : '' ?></td>
      <td><?= e($o['customer']) ?><br><span class="muted small"><?= e($o['email']) ?></span></td>
      <td><?= e(Fmt::money((int)$o['total_minor'], $o['currency'])) ?></td>
      <td><?= View::partial('partials/status', ['order' => $o]) ?></td>
      <td><?= e($o['designer'] ?? '—') ?></td>
      <td class="small"><?= e(Fmt::dt($o['created_at'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted"><?= e(t('admin.no_results')) ?></td></tr><?php endif; ?>
  </tbody>
</table>
</div>
