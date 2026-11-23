<?php use App\I18n; ?>
<h1 class="h2"><?= e(t('admin.translations')) ?></h1>
<p class="muted"><?= e(t('admin.translations_help')) ?></p>
<form class="filters" method="get">
  <select name="tl" aria-label="<?= e(t('nav.language')) ?>"><?php foreach (I18n::LANGS as $l): ?><option value="<?= $l ?>" <?= $tl === $l ? 'selected' : '' ?>><?= e(I18n::NAMES[$l]) ?></option><?php endforeach; ?></select>
  <select name="p" aria-label="<?= e(t('admin.group')) ?>"><option value=""><?= e(t('admin.all_groups')) ?></option><?php foreach ($groups as $g): ?><option value="<?= e($g) ?>" <?= $p === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?></select>
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('common.search')) ?>">
  <button class="btn btn-sm"><?= e(t('common.filter')) ?></button>
</form>
<form method="post" action="<?= e(url('/admin/translations')) ?>" class="card">
  <?= csrf_field() ?><input type="hidden" name="tl" value="<?= e($tl) ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="p" value="<?= e($p) ?>">
  <?php foreach ($keys as $k): $val = $over[$k] ?? ($base[$k] ?? ''); $long = mb_strlen($val) > 70 || str_contains($val, "\n"); ?>
    <div class="tr-row">
      <span class="tr-key"><?= e($k) ?><?= isset($over[$k]) ? ' · ' . e(t('admin.overridden')) : '' ?></span>
      <?php if ($tl !== 'tr'): ?><span class="tr-base">TR: <?= e($tr[$k] ?? '') ?></span><?php endif; ?>
      <?php if ($long): ?><textarea name="t[<?= e($k) ?>]" rows="3" lang="<?= e($tl) ?>"><?= e($val) ?></textarea>
      <?php else: ?><input name="t[<?= e($k) ?>]" value="<?= e($val) ?>" lang="<?= e($tl) ?>"><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$keys): ?><p class="muted"><?= e(t('admin.no_results')) ?></p><?php endif; ?>
  <p class="muted small"><?= e(t('admin.translations_reset')) ?></p>
  <button class="btn btn-primary"><?= e(t('common.save')) ?></button>
</form>
