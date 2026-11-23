<h1 class="h2"><?= e(t('admin.themes')) ?></h1>
<div class="theme-grid">
<?php foreach ($themes as $t): ?>
  <article class="theme-card">
    <a class="theme-card-img" href="<?= e(url('/admin/themes/' . $t['id'])) ?>"><img src="<?= e($t['media']['preview']) ?>" alt="" loading="lazy" width="640" height="1138"></a>
    <div class="theme-card-body">
      <h2 class="h3"><?= e(loc($t['name'])) ?> <?= (int)$t['is_active'] ? '<span class="badge badge-ok">' . e(t('admin.active')) . '</span>' : '<span class="badge">' . e(t('admin.inactive')) . '</span>' ?></h2>
      <p class="small"><?= $t['media']['video'] ? '✓ ' . e(t('admin.media.video')) : '— ' . e(t('admin.media.video')) ?> · <?= count($t['audio']) ?>/4 <?= e(t('admin.media.sounds')) ?></p>
      <div class="cta-row"><a class="btn btn-sm btn-primary" href="<?= e(url('/admin/themes/' . $t['id'])) ?>"><?= e(t('common.edit')) ?></a><a class="btn btn-sm btn-ghost" href="<?= e(url('/preview/' . $t['slug'])) ?>" target="_blank" rel="noopener"><?= e(t('theme.live_preview')) ?></a></div>
    </div>
  </article>
<?php endforeach; ?>
</div>
