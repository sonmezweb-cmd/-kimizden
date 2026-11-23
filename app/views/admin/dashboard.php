<?php use App\Fmt; ?>
<h1 class="h2"><?= e(t('admin.dashboard')) ?></h1>
<?php if ($pending): ?><div class="notice notice-info"><?= e(t('admin.pending_migrations', ['n' => count($pending)])) ?> <a href="<?= e(url('/admin/system')) ?>"><?= e(t('admin.system')) ?></a></div><?php endif; ?>
<?php if ($stats['unpriced']): ?><div class="notice"><?= e(t('admin.unpriced', ['n' => $stats['unpriced']])) ?> <a href="<?= e(url('/admin/packages')) ?>"><?= e(t('admin.packages')) ?></a></div><?php endif; ?>
<div class="stats">
  <a class="stat" href="<?= e(url('/admin/orders')) ?>"><span><?= e(t('admin.orders')) ?></span><strong><?= Fmt::number($stats['orders']) ?></strong></a>
  <a class="stat stat-ok" href="<?= e(url('/admin/orders', null, ['pay' => 'paid'])) ?>"><span><?= e(t('status.pay.paid')) ?></span><strong><?= Fmt::number($stats['paid']) ?></strong></a>
  <a class="stat <?= $stats['review'] ? 'stat-warn' : '' ?>" href="<?= e(url('/admin/orders', null, ['review' => 1])) ?>"><span><?= e(t('admin.needs_review')) ?></span><strong><?= Fmt::number($stats['review']) ?></strong></a>
  <a class="stat <?= $stats['unassigned'] ? 'stat-warn' : '' ?>" href="<?= e(url('/admin/orders')) ?>"><span><?= e(t('admin.unassigned')) ?></span><strong><?= Fmt::number($stats['unassigned']) ?></strong></a>
  <a class="stat" href="<?= e(url('/admin/orders', null, ['design' => 'awaiting_approval'])) ?>"><span><?= e(t('status.design.awaiting_approval')) ?></span><strong><?= Fmt::number($stats['awaiting_approval']) ?></strong></a>
  <a class="stat" href="<?= e(url('/admin/orders', null, ['pub' => 'published'])) ?>"><span><?= e(t('status.pub.published')) ?></span><strong><?= Fmt::number($stats['published']) ?></strong></a>
  <a class="stat <?= $stats['mail_failed'] ? 'stat-warn' : '' ?>" href="<?= e(url('/admin/emails')) ?>"><span><?= e(t('admin.mail_failed')) ?></span><strong><?= Fmt::number($stats['mail_failed']) ?></strong></a>
  <a class="stat" href="<?= e(url('/admin/messages')) ?>"><span><?= e(t('admin.messages')) ?></span><strong><?= Fmt::number($stats['messages']) ?></strong></a>
</div>
<div class="grid-main">
  <section class="card">
    <h2 class="h4"><?= e(t('admin.settings.payments')) ?></h2>
    <p><?= $paytr['enabled'] ? '<span class="badge badge-ok">' . e(t('paytr.enabled')) . '</span>' : '<span class="badge">' . e(t('paytr.disabled')) . '</span>' ?>
       <span class="badge badge-info"><?= e(t('paytr.mode.' . $paytr['mode'])) ?></span>
       <?= $paytr['configured'] ? '' : '<span class="badge badge-warn">' . e(t('paytr.not_configured')) . '</span>' ?></p>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/settings/payments')) ?>"><?= e(t('admin.settings.payments')) ?></a>
  </section>
  <section class="card">
    <h2 class="h4">SMTP</h2>
    <p><?= $smtp ? '<span class="badge badge-ok">' . e(t('smtp.configured')) . '</span>' : '<span class="badge badge-warn">' . e(t('smtp.not_configured')) . '</span>' ?></p>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/settings/smtp')) ?>"><?= e(t('admin.settings.smtp')) ?></a>
  </section>
</div>
