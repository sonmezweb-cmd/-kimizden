<?php
use App\Fmt;
use App\View;
$no = $order['public_no'];
$s = $order['snapshot'];
?>
<p class="eyebrow"><a href="<?= e(url('/admin/orders')) ?>"><?= e(t('admin.orders')) ?></a></p>
<div class="page-head"><h1 class="h2"><?= e($no) ?></h1><a class="btn btn-sm btn-ghost" href="<?= e(url('/panel/orders/' . $no)) ?>"><?= e(t('admin.open_workspace')) ?></a></div>
<div class="status-row"><?= View::partial('partials/status', ['order' => $order]) ?>
  <?= (int)$order['publish_right'] ? '<span class="badge badge-ok">' . e(t('admin.publish_right')) . '</span>' : '<span class="badge">' . e(t('admin.no_publish_right')) . '</span>' ?>
  <?= (int)$order['needs_review'] ? '<span class="badge badge-warn">' . e(t('admin.needs_review')) . '</span>' : '' ?></div>
<div class="grid-main" style="margin-top:16px">
  <section class="card">
    <h2 class="h4"><?= e(t('admin.order_info')) ?></h2>
    <dl class="kv">
      <dt><?= e(t('admin.customer')) ?></dt><dd><?= e($order['user_name']) ?> · <?= e($order['user_email']) ?><?= $order['user_phone'] ? ' · ' . e($order['user_phone']) : '' ?></dd>
      <dt><?= e(t('checkout.theme')) ?></dt><dd><?= e(loc($s['theme']['name'])) ?></dd>
      <dt><?= e(t('admin.package_snapshot')) ?></dt><dd><?= e(loc($s['package']['name'])) ?> · <?= e(Fmt::money($s['package']['price_minor'], $s['currency'])) ?> · <?= e(t('pkg.revisions', ['n' => $s['revisions_total']])) ?></dd>
      <?php foreach ($s['addons'] as $a): ?><dt><?= e(t('theme.addons')) ?></dt><dd><?= e(loc($a['name'])) ?> · <?= e(Fmt::money($a['price_minor'], $s['currency'])) ?></dd><?php endforeach; ?>
      <dt><?= e(t('checkout.total')) ?></dt><dd><strong><?= e(Fmt::money((int)$order['total_minor'], $order['currency'])) ?></strong> <span class="muted small">(<?= e(t('admin.snapshot_at', ['d' => $s['priced_at'] ?? ''])) ?>)</span></dd>
      <dt><?= e(t('pkg.revisions_label')) ?></dt><dd><?= e(t('designer.rev_left', ['n' => max(0, (int)$order['revisions_allowed'] - $revUsed), 'total' => (int)$order['revisions_allowed']])) ?></dd>
      <dt><?= e(t('share.link')) ?></dt><dd><a href="<?= e(url_raw('/i/' . $inv['slug'])) ?>" target="_blank" rel="noopener"><?= e(url_raw('/i/' . $inv['slug'])) ?></a></dd>
      <dt><?= e(t('admin.created')) ?></dt><dd><?= e(Fmt::dt($order['created_at'])) ?></dd>
      <dt><?= e(t('status.pay.paid')) ?></dt><dd><?= e(Fmt::dt($order['paid_at'])) ?></dd>
    </dl>
  </section>
  <aside class="card">
    <h2 class="h4"><?= e(t('admin.actions')) ?></h2>
    <form method="post" action="<?= e(url('/admin/orders/' . $no)) ?>" class="inline-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="assign">
      <select name="designer_id" aria-label="<?= e(t('admin.designer')) ?>"><option value="0">— <?= e(t('admin.designer')) ?> —</option><?php foreach ($designers as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (int)$order['designer_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
      <button class="btn btn-sm"><?= e(t('admin.assign')) ?></button>
    </form>
    <hr>
    <?php foreach ([
        ['publish', 'admin.publish_latest', 'btn-primary', null],
        ['unpublish', 'admin.unpublish', 'btn-ghost', 'admin.unpublish_confirm'],
        ['archive', 'admin.archive', 'btn-danger', 'admin.archive_confirm'],
      ] as [$a, $k, $cls, $conf]): ?>
      <form method="post" action="<?= e(url('/admin/orders/' . $no)) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><button class="btn btn-sm <?= $cls ?>" <?= $conf ? 'data-confirm="' . e(t($conf)) . '"' : '' ?>><?= e(t($k)) ?></button></form>
    <?php endforeach; ?>
    <hr>
    <form method="post" action="<?= e(url('/admin/orders/' . $no)) ?>" class="form">
      <?= csrf_field() ?>
      <label class="field"><span><?= e(t('admin.note')) ?></span><input name="note" maxlength="400" placeholder="<?= e(t('admin.note_ph')) ?>"></label>
      <div class="inline-form">
        <button class="btn btn-xs" name="action" value="manual_paid" data-confirm="<?= e(t('admin.manual_paid_confirm')) ?>"><?= e(t('admin.manual_paid')) ?></button>
        <?php if ((int)$order['publish_right']): ?><button class="btn btn-xs btn-danger" name="action" value="revoke_right"><?= e(t('admin.revoke_right')) ?></button>
        <?php else: ?><button class="btn btn-xs" name="action" value="grant_right" data-confirm="<?= e(t('admin.grant_right_confirm')) ?>"><?= e(t('admin.grant_right')) ?></button><?php endif; ?>
        <?php if ((int)$order['needs_review']): ?><button class="btn btn-xs" name="action" value="clear_review"><?= e(t('admin.clear_review')) ?></button><?php endif; ?>
      </div>
    </form>
  </aside>
</div>
<section class="card">
  <h2 class="h4"><?= e(t('admin.payment_attempts')) ?></h2>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>merchant_oid</th><th><?= e(t('paytr.mode')) ?></th><th><?= e(t('checkout.total')) ?></th><th><?= e(t('admin.collected')) ?></th><th><?= e(t('status.title')) ?></th><th><?= e(t('admin.callbacks')) ?></th><th><?= e(t('admin.created')) ?></th></tr></thead>
    <tbody><?php foreach ($attempts as $a): ?><tr><td class="small"><code><?= e($a['merchant_oid']) ?></code></td><td><?= e(t('paytr.mode.' . $a['mode'])) ?></td><td><?= e(Fmt::money((int)$a['amount_minor'], $a['currency'])) ?></td><td><?= $a['collected_minor'] !== null ? e(Fmt::money((int)$a['collected_minor'], $a['currency'])) : '—' ?></td><td><?= e($a['status']) ?><?= $a['error_text'] ? '<br><small class="muted">' . e($a['error_text']) . '</small>' : '' ?></td><td><?= (int)$a['callback_count'] ?></td><td class="small"><?= e(Fmt::dt($a['created_at'])) ?></td></tr><?php endforeach; ?>
    <?php if (!$attempts): ?><tr><td colspan="7" class="muted">—</td></tr><?php endif; ?></tbody>
  </table></div>
  <h3 class="h4"><?= e(t('admin.payments')) ?></h3>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th><?= e(t('paytr.mode')) ?></th><th><?= e(t('checkout.total')) ?></th><th><?= e(t('admin.collected')) ?></th><th><?= e(t('admin.note')) ?></th><th><?= e(t('admin.created')) ?></th></tr></thead>
    <tbody><?php foreach ($payments as $p): ?><tr><td><?= (int)$p['id'] ?></td><td><?= e($p['mode']) ?></td><td><?= e(Fmt::money((int)$p['amount_minor'], $p['currency'])) ?></td><td><?= e(Fmt::money((int)$p['collected_minor'], $p['currency'])) ?></td><td><?= e((string)$p['note']) ?></td><td class="small"><?= e(Fmt::dt($p['created_at'])) ?></td></tr><?php endforeach; ?>
    <?php if (!$payments): ?><tr><td colspan="6" class="muted">—</td></tr><?php endif; ?></tbody>
  </table></div>
  <?php if ($callbacks): ?>
  <h3 class="h4"><?= e(t('admin.callbacks')) ?></h3>
  <div class="table-wrap"><table class="table"><thead><tr><th>oid</th><th>status</th><th><?= e(t('paytr.mode')) ?></th><th><?= e(t('admin.signature')) ?></th><th><?= e(t('admin.outcome')) ?></th><th><?= e(t('admin.created')) ?></th></tr></thead>
    <tbody><?php foreach ($callbacks as $cb): ?><tr><td class="small"><code><?= e((string)$cb['merchant_oid']) ?></code></td><td><?= e((string)$cb['status']) ?></td><td><?= e((string)$cb['mode']) ?></td><td><?= (int)$cb['hash_valid'] ? '✓' : '✗' ?></td><td><?= e($cb['outcome']) ?></td><td class="small"><?= e(Fmt::dt($cb['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</section>
<section class="card">
  <h2 class="h4"><?= e(t('admin.versions')) ?></h2>
  <ul class="timeline"><?php foreach ($versions as $v): ?><li><strong>v<?= (int)$v['version_no'] ?></strong> · <?= e(t('vstatus.' . $v['status'])) ?> · <?= e(Fmt::dt($v['created_at'])) ?><?= (int)$inv['live_version_id'] === (int)$v['id'] ? ' · <span class="badge badge-ok">' . e(t('status.pub.published')) . '</span>' : '' ?></li><?php endforeach; ?>
  <?php if (!$versions): ?><li class="muted">—</li><?php endif; ?></ul>
</section>
<section class="card">
  <h2 class="h4"><?= e(t('admin.audit')) ?></h2>
  <ul class="timeline small"><?php foreach ($audit as $a): ?><li><?= e(Fmt::dt($a['created_at'])) ?> · <strong><?= e($a['action']) ?></strong> · <?= e($a['who'] ?? t('admin.system_actor')) ?><?php if ($a['details_json']): ?> · <code><?= e($a['details_json']) ?></code><?php endif; ?></li><?php endforeach; ?></ul>
</section>
