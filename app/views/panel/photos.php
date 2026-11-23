<?php
use App\Media;
use App\View;
$c = $content;
$no = $order['public_no'];
$byKey = [];
foreach ($media as $m) $byKey[$m['mkey']] = $m;
$ordered = array_values(array_filter($c['gallery'], fn($k) => isset($byKey[$k])));
$others = array_values(array_filter(array_keys($byKey), fn($k) => !in_array($k, $ordered, true)));
?>
<?= View::partial('partials/order_tabs', get_defined_vars()) ?>
<section class="card">
  <h2 class="h3"><?= e(t('photos.title')) ?></h2>
  <p class="muted"><?= e(t('photos.help')) ?></p>
  <?php if ($editable): ?>
  <form method="post" action="<?= e(url('/panel/orders/' . $no . '/photos')) ?>" enctype="multipart/form-data" class="upload" data-upload>
    <?= csrf_field() ?>
    <label class="upload-drop">
      <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
      <span><?= e(t('photos.choose')) ?></span>
      <small class="muted"><?= e(t('photos.limits')) ?></small>
    </label>
    <div class="upload-list" data-upload-list aria-live="polite"></div>
    <button class="btn btn-primary" type="submit"><?= e(t('photos.upload')) ?></button>
  </form>
  <?php endif; ?>
</section>

<section class="card">
  <h3 class="h4"><?= e(t('photos.gallery')) ?> (<?= count($ordered) ?>)</h3>
  <?php if (!$ordered && !$others): ?><p class="muted"><?= e(t('photos.none')) ?></p><?php endif; ?>
  <ul class="photo-grid" data-sortable="<?= e(url('/panel/orders/' . $no . '/photos/update')) ?>">
    <?php foreach (array_merge($ordered, $others) as $i => $k): $m = $byKey[$k]; $inGal = in_array($k, $ordered, true); $isCover = $c['cover_photo'] === $k; ?>
      <li class="photo <?= $inGal ? '' : 'is-out' ?>" data-key="<?= e($k) ?>">
        <img src="<?= e(Media::url($k, 's')) ?>" alt="<?= e($m['original_name'] ?? '') ?>" width="240" height="240" loading="lazy">
        <div class="photo-tags">
          <?php if ($isCover): ?><span class="badge badge-ok"><?= e(t('photos.cover')) ?></span><?php endif; ?>
          <?php if (!$inGal): ?><span class="badge"><?= e(t('photos.not_in_gallery')) ?></span><?php endif; ?>
        </div>
        <?php if ($editable): ?>
        <form method="post" action="<?= e(url('/panel/orders/' . $no . '/photos/update')) ?>" class="photo-actions">
          <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($k) ?>">
          <?php if (!$isCover): ?><button class="btn btn-xs btn-ghost" name="action" value="cover"><?= e(t('photos.make_cover')) ?></button><?php else: ?><button class="btn btn-xs btn-ghost" name="action" value="uncover"><?= e(t('photos.remove_cover')) ?></button><?php endif; ?>
          <button class="btn btn-xs btn-ghost" name="action" value="toggle"><?= $inGal ? e(t('photos.hide')) : e(t('photos.show')) ?></button>
          <?php if ($inGal && $i > 0): ?><button class="btn btn-xs btn-ghost" name="action" value="up" aria-label="<?= e(t('common.move_up')) ?>">↑</button><?php endif; ?>
          <?php if ($inGal && $i < count($ordered) - 1): ?><button class="btn btn-xs btn-ghost" name="action" value="down" aria-label="<?= e(t('common.move_down')) ?>">↓</button><?php endif; ?>
          <button class="btn btn-xs btn-danger" name="action" value="delete" data-confirm="<?= e(t('photos.delete_confirm')) ?>"><?= e(t('common.delete')) ?></button>
        </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
