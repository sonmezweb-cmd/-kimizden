<?php use App\View; ?>
<h1 class="h2"><?= e(t('designer.title')) ?></h1>
<?php if (!$orders): ?><div class="empty card"><p><?= e(t('designer.none')) ?></p></div><?php endif; ?>
<div class="table-wrap">
<table class="table">
  <thead><tr><th><?= e(t('panel.order')) ?></th><th><?= e(t('checkout.theme')) ?></th><th><?= e(t('pkg.revisions_label')) ?></th><th><?= e(t('status.title')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($orders as $o): $used = \App\Orders::revisionsUsed((int)$o['id']); ?>
    <tr>
      <td><strong><?= e($o['public_no']) ?></strong><br><span class="muted small"><?= e($o['user_name']) ?></span></td>
      <td><?= e(loc($o['snapshot']['theme']['name'])) ?><br><span class="muted small"><?= e(loc($o['snapshot']['package']['name'])) ?></span></td>
      <td><?= e(t('designer.rev_left', ['n' => max(0, (int)$o['revisions_allowed'] - $used), 'total' => (int)$o['revisions_allowed']])) ?></td>
      <td><?= View::partial('partials/status', ['order' => $o]) ?></td>
      <td><a class="btn btn-sm btn-primary" href="<?= e(url('/panel/orders/' . $o['public_no'])) ?>"><?= e(t('designer.open')) ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
