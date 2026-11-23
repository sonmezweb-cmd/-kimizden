<?php use App\Fmt; ?>
<h1 class="h2"><?= e(t('admin.system')) ?></h1>
<div class="grid-main">
  <section class="card">
    <h2 class="h4"><?= e(t('admin.updates')) ?></h2>
    <p><?= e(t('admin.version')) ?>: <strong><?= e(APP_VERSION) ?></strong> · PHP <?= e(PHP_VERSION) ?></p>
    <?php if ($pending): ?>
      <p><?= e(t('admin.pending_migrations', ['n' => count($pending)])) ?>: <code><?= e(implode(', ', $pending)) ?></code></p>
      <form method="post" action="<?= e(url('/admin/system')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="migrate"><button class="btn btn-primary btn-sm" data-confirm="<?= e(t('admin.migrate_confirm')) ?>"><?= e(t('admin.run_migrations')) ?></button></form>
    <?php else: ?><p class="muted"><?= e(t('admin.db_current')) ?></p><?php endif; ?>
    <details><summary class="small"><?= e(t('admin.applied_migrations')) ?></summary><ul class="small"><?php foreach ($applied as $m): ?><li><?= e($m['name']) ?> · <?= e(Fmt::dt($m['applied_at'])) ?></li><?php endforeach; ?></ul></details>
  </section>
  <section class="card">
    <h2 class="h4"><?= e(t('admin.backups')) ?></h2>
    <p class="muted small"><?= e(t('admin.backups_help')) ?></p>
    <form method="post" action="<?= e(url('/admin/system')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="backup"><button class="btn btn-sm"><?= e(t('admin.backup_now')) ?></button></form>
    <ul class="link-list small"><?php foreach ($backups as $b): ?><li><form method="post" action="<?= e(url('/admin/system')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="download"><input type="hidden" name="file" value="<?= e($b['name']) ?>"><button class="btn btn-xs btn-ghost"><?= e($b['name']) ?></button></form> · <?= e(Fmt::number(round($b['size'] / 1024))) ?> KB</li><?php endforeach; ?></ul>
  </section>
</div>
<section class="card">
  <h2 class="h4"><?= e(t('admin.server_checks')) ?></h2>
  <ul class="checks"><?php foreach ($checks as [$label, $ok]): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✗' ?> <?= e($label) ?></li><?php endforeach; ?></ul>
  <form method="post" action="<?= e(url('/admin/system')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="clear_cache"><button class="btn btn-sm btn-ghost"><?= e(t('admin.clear_cache')) ?></button></form>
</section>
