<?php use App\I18n; $money = fn($m) => $m === null ? '' : number_format($m / 100, 2, ',', '.'); ?>
<h1 class="h2"><?= e(t('admin.packages')) ?></h1>
<p class="muted"><?= e(t('admin.packages_help')) ?></p>
<form method="post" action="<?= e(url('/admin/packages')) ?>" class="form" data-keep>
  <?= csrf_field() ?>
  <?php foreach ($packages as $p): $id = (int)$p['id']; ?>
    <fieldset class="card">
      <legend class="h3"><?= e(loc($p['name'])) ?> <span class="muted small">(<?= e($p['code']) ?>)</span></legend>
      <div class="grid-3">
        <label class="field"><span><?= e(t('admin.price')) ?></span><input name="pkg[<?= $id ?>][price]" inputmode="decimal" value="<?= e($money($p['price_minor'])) ?>" placeholder="<?= e(t('admin.price_ph')) ?>"></label>
        <label class="field"><span><?= e(t('admin.currency')) ?></span><select name="pkg[<?= $id ?>][currency]"><?php foreach (['TRY', 'EUR', 'USD'] as $cur): ?><option <?= $p['currency'] === $cur ? 'selected' : '' ?>><?= $cur ?></option><?php endforeach; ?></select></label>
        <label class="field"><span><?= e(t('pkg.revisions_label')) ?></span><input type="number" min="0" max="50" name="pkg[<?= $id ?>][revisions]" value="<?= (int)$p['revisions'] ?>"></label>
        <label class="field"><span><?= e(t('pkg.delivery_label')) ?></span><input type="number" min="1" max="90" name="pkg[<?= $id ?>][delivery_days]" value="<?= (int)$p['delivery_days'] ?>"></label>
        <label class="field"><span><?= e(t('pkg.languages_label')) ?></span><input type="number" min="1" max="3" name="pkg[<?= $id ?>][max_languages]" value="<?= (int)$p['max_languages'] ?>"></label>
        <label class="field"><span><?= e(t('admin.sort')) ?></span><input type="number" name="pkg[<?= $id ?>][sort]" value="<?= (int)$p['sort'] ?>"></label>
      </div>
      <label class="check"><input type="checkbox" name="pkg[<?= $id ?>][guest_groups]" value="1" <?= (int)$p['guest_groups'] ? 'checked' : '' ?>> <?= e(t('pkg.groups_label')) ?></label>
      <label class="check"><input type="checkbox" name="pkg[<?= $id ?>][is_on_sale]" value="1" <?= (int)$p['is_on_sale'] ? 'checked' : '' ?>> <?= e(t('admin.on_sale')) ?></label>
      <?php foreach (I18n::LANGS as $l): ?>
        <div class="lang-block"><p class="lang-tag"><?= e(I18n::NAMES[$l]) ?></p>
          <label class="field"><span><?= e(t('admin.name')) ?></span><input name="pkg[<?= $id ?>][name][<?= $l ?>]" value="<?= e($p['name'][$l] ?? '') ?>" maxlength="80"></label>
          <label class="field"><span><?= e(t('admin.scope')) ?></span><textarea name="pkg[<?= $id ?>][scope][<?= $l ?>]" rows="4"><?= e(implode("\n", $p['scope'][$l] ?? [])) ?></textarea></label>
        </div>
      <?php endforeach; ?>
    </fieldset>
  <?php endforeach; ?>
  <h2 class="h3"><?= e(t('theme.addons')) ?></h2>
  <?php foreach ($addons as $a): $id = (int)$a['id']; ?>
    <fieldset class="card">
      <legend class="h4"><?= e(loc($a['name'])) ?></legend>
      <div class="grid-3">
        <label class="field"><span><?= e(t('admin.price')) ?> (TRY)</span><input name="addon[<?= $id ?>][price]" inputmode="decimal" value="<?= e($money($a['price_minor'])) ?>" placeholder="<?= e(t('admin.price_ph')) ?>"></label>
        <label class="field"><span><?= e(t('admin.extra_revisions')) ?></span><input type="number" min="0" max="10" name="addon[<?= $id ?>][extra_revisions]" value="<?= (int)$a['extra_revisions'] ?>"></label>
        <label class="check"><input type="checkbox" name="addon[<?= $id ?>][is_active]" value="1" <?= (int)$a['is_active'] ? 'checked' : '' ?>> <?= e(t('admin.on_sale')) ?></label>
      </div>
      <?php foreach (I18n::LANGS as $l): ?>
        <div class="grid-2 lang-block"><label class="field"><span><?= e(t('admin.name')) ?> · <?= strtoupper($l) ?></span><input name="addon[<?= $id ?>][name][<?= $l ?>]" value="<?= e($a['name'][$l] ?? '') ?>"></label>
          <label class="field"><span><?= e(t('admin.description')) ?> · <?= strtoupper($l) ?></span><input name="addon[<?= $id ?>][desc][<?= $l ?>]" value="<?= e($a['description'][$l] ?? '') ?>"></label></div>
      <?php endforeach; ?>
    </fieldset>
  <?php endforeach; ?>
  <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
</form>
