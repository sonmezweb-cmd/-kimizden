<?php
// Ödeme, tasarım ve yayın durumları birbirinden bağımsız gösterilir
$p = $order['payment_status'];
$d = $order['design_status'];
$u = $order['publish_status'];
$tone = fn(string $s) => match ($s) {
    'paid', 'approved', 'published' => 'ok',
    'review', 'revision_requested', 'archived' => 'warn',
    'test_paid', 'awaiting', 'awaiting_approval', 'in_design' => 'info',
    default => 'muted',
};
?>
<span class="badge badge-<?= $tone($p) ?>" title="<?= e(t('status.payment')) ?>"><?= e(t('status.payment')) ?>: <?= e(t('status.pay.' . $p)) ?></span>
<span class="badge badge-<?= $tone($d) ?>" title="<?= e(t('status.design')) ?>"><?= e(t('status.design')) ?>: <?= e(t('status.design.' . $d)) ?></span>
<span class="badge badge-<?= $tone($u) ?>" title="<?= e(t('status.publish')) ?>"><?= e(t('status.publish')) ?>: <?= e(t('status.pub.' . $u)) ?></span>
