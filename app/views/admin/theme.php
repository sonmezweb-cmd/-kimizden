<?php
use App\I18n;
use App\Controllers\AdminController;
$t = $theme;
$id = (int)$t['id'];
$g = $t['geometry'];
$slots = [
  'poster' => ['admin.media.poster', 'admin.media.poster_help', 'image/jpeg,image/png,image/webp', $t['media']['poster']],
  'preview' => ['admin.media.preview', 'admin.media.preview_help', 'image/jpeg,image/png,image/webp', $t['media']['preview']],
  'video' => ['admin.media.video', 'admin.media.video_help', 'video/mp4', $t['media']['video_mp4']],
  'video_webm' => ['admin.media.video_webm', 'admin.media.video_webm_help', 'video/webm', $t['media']['video_webm']],
  'video_wide' => ['admin.media.video_wide', 'admin.media.video_wide_help', 'video/mp4', $t['media']['video_wide']],
  'story_mp4' => ['admin.media.story', 'admin.media.story_help', 'video/mp4', $t['media']['story_mp4']],
  'story_webm' => ['admin.media.story_webm', 'admin.media.video_webm_help', 'video/webm', $t['media']['story_webm']],
  'audio_ornament' => ['admin.media.audio_ornament', 'admin.media.audio_help', 'audio/*', $t['audio']['ornament'] ?? null],
  'audio_seal' => ['admin.media.audio_seal', 'admin.media.audio_help', 'audio/*', $t['audio']['seal'] ?? null],
  'audio_flap' => ['admin.media.audio_flap', 'admin.media.audio_help', 'audio/*', $t['audio']['flap'] ?? null],
  'audio_paper' => ['admin.media.audio_paper', 'admin.media.audio_help', 'audio/*', $t['audio']['paper'] ?? null],
  'seal' => ['admin.media.seal', 'admin.media.layer_help', 'image/png,image/webp', $t['media']['seal']],
  'texture' => ['admin.media.texture', 'admin.media.layer_help', 'image/jpeg,image/png,image/webp', $t['media']['texture']],
  'ornament' => ['admin.media.ornament', 'admin.media.layer_help', 'image/png,image/webp', $t['media']['ornament']],
  'paper' => ['admin.media.paper', 'admin.media.layer_help', 'image/jpeg,image/png,image/webp', $t['media']['paper']],
];
?>
<p class="eyebrow"><a href="<?= e(url('/admin/themes')) ?>"><?= e(t('admin.themes')) ?></a></p>
<div class="page-head"><h1 class="h2"><?= e(loc($t['name'])) ?> <span class="muted small">themes/<?= e($t['slug']) ?>/</span></h1><a class="btn btn-sm btn-ghost" href="<?= e(url('/preview/' . $t['slug'])) ?>" target="_blank" rel="noopener"><?= e(t('theme.live_preview')) ?></a></div>
<form method="post" action="<?= e(url('/admin/themes/' . $id)) ?>" class="card form" data-keep>
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <div class="grid-3">
    <label class="check"><input type="checkbox" name="is_active" value="1" <?= (int)$t['is_active'] ? 'checked' : '' ?>> <?= e(t('admin.active')) ?></label>
    <label class="field"><span><?= e(t('admin.volume')) ?> (%)</span><input type="number" name="sound_volume" min="0" max="100" value="<?= (int)$t['sound_volume'] ?>"></label>
    <label class="field"><span><?= e(t('admin.sort')) ?></span><input type="number" name="sort" value="<?= (int)$t['sort'] ?>"></label>
  </div>
  <?php foreach (I18n::LANGS as $l): ?>
    <div class="lang-block"><p class="lang-tag"><?= e(I18n::NAMES[$l]) ?></p>
      <label class="field"><span><?= e(t('admin.name')) ?></span><input name="name[<?= $l ?>]" value="<?= e($t['name'][$l] ?? '') ?>"></label>
      <label class="field"><span><?= e(t('admin.description')) ?></span><textarea name="desc[<?= $l ?>]" rows="2"><?= e($t['description'][$l] ?? '') ?></textarea></label></div>
  <?php endforeach; ?>
  <details><summary><?= e(t('admin.geometry')) ?></summary>
    <p class="muted small"><?= e(t('admin.geometry_help')) ?></p>
    <div class="grid-3"><?php foreach (['flapLeft', 'flapRight', 'flapTipX', 'flapTipY', 'sealX', 'sealY', 'sealR', 'pocketSide', 'pocketTipY'] as $k): ?>
      <label class="field"><span><?= e($k) ?> (%)</span><input name="geo[<?= $k ?>]" inputmode="decimal" value="<?= e((string)($g[$k] ?? '')) ?>"></label><?php endforeach; ?></div>
  </details>
  <details><summary><?= e(t('admin.story_stops')) ?></summary>
    <p class="muted small"><?= e(t('admin.story_stops_help')) ?></p>
    <textarea name="story_stops" rows="7" style="font-family:ui-monospace,monospace"><?php foreach ($t['story_stops'] as $st) echo e($st['type'] . ' ' . $st['t'] . ' ' . implode(',', $st['zone'])) . "\n"; ?></textarea>
  </details>
  <button class="btn btn-primary"><?= e(t('common.save')) ?></button>
</form>
<h2 class="h3"><?= e(t('admin.media_title')) ?></h2>
<p class="muted"><?= e(t('admin.media_intro')) ?></p>
<div class="order-cards">
<?php foreach ($slots as $slot => [$label, $help, $accept, $cur]): $def = AdminController::MEDIA_SLOTS[$slot]; ?>
  <section class="card">
    <h3 class="h4"><?= e(t($label)) ?> <?= $cur ? '<span class="badge badge-ok">✓</span>' : '<span class="badge">' . e(t('admin.media.none')) . '</span>' ?></h3>
    <p class="muted small"><?= e(t($help)) ?> · <?= e(t('admin.media.max', ['n' => $def['max']])) ?> · <code><?= e($def['file']) ?><?= $def['kind'] === 'audio' ? '.mp3/.m4a/.ogg' : '' ?></code></p>
    <?php if ($cur && $def['kind'] === 'audio'): ?><audio controls preload="none" src="<?= e($cur) ?>"></audio><?php endif; ?>
    <?php if ($cur && in_array($def['kind'], ['video', 'webm'], true)): ?><video controls muted preload="none" playsinline src="<?= e($cur) ?>" style="max-height:220px"></video><?php endif; ?>
    <?php if ($cur && str_starts_with($def['kind'], 'image')): ?><img src="<?= e($cur) ?>" alt="" style="max-height:160px;border-radius:8px;background:#ddd" loading="lazy"><?php endif; ?>
    <form method="post" action="<?= e(url('/admin/themes/' . $id)) ?>" enctype="multipart/form-data" class="inline-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="slot" value="<?= e($slot) ?>">
      <input type="file" name="file" accept="<?= e($accept) ?>" required aria-label="<?= e(t($label)) ?>">
      <button class="btn btn-sm"><?= e(t('admin.upload')) ?></button>
    </form>
    <?php if ($cur && $slot !== 'poster'): ?>
      <form method="post" action="<?= e(url('/admin/themes/' . $id)) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="slot" value="<?= e($slot) ?>"><button class="btn btn-xs btn-danger" data-confirm="<?= e(t('admin.media.remove_confirm')) ?>"><?= e(t('common.delete')) ?></button></form>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
