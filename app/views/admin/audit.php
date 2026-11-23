<?php use App\Fmt; ?>
<h1 class="h2"><?= e(t('admin.audit')) ?></h1>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(t('admin.created')) ?></th><th><?= e(t('admin.actor')) ?></th><th><?= e(t('admin.action')) ?></th><th><?= e(t('admin.entity')) ?></th><th><?= e(t('admin.details')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?><tr>
    <td class="small"><?= e(Fmt::dt($r['created_at'])) ?></td><td><?= e($r['who'] ?? t('admin.system_actor')) ?></td><td><code><?= e($r['action']) ?></code></td>
    <td><?php if ($r['entity'] === 'order' && $r['entity_id']): $no = \App\DB::val('SELECT public_no FROM orders WHERE id = ?', [$r['entity_id']]); ?><a href="<?= e(url('/admin/orders/' . $no)) ?>"><?= e((string)$no) ?></a><?php else: ?><?= e(trim(($r['entity'] ?? '') . ' ' . ($r['entity_id'] ?? ''))) ?><?php endif; ?></td>
    <td class="small"><?= $r['details_json'] ? '<code>' . e($r['details_json']) . '</code>' : '' ?></td>
  </tr><?php endforeach; ?></tbody>
</table></div>
