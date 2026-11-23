<?php
$max = $group ? (int)$group['max_guests'] : (int)($c['rsvp']['max_public'] ?? 4);
$att = $my ? (int)$my['attending'] : null;
$dis = $disabled ? 'disabled' : '';
?>
<form method="post" action="<?= e($action) ?>" class="rsvp-form" <?= $disabled ? 'aria-disabled="true"' : '' ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="l" value="<?= e($L) ?>">
  <?php if ($group): ?><input type="hidden" name="g" value="<?= e($group['token']) ?>"><?php endif; ?>
  <fieldset class="rsvp-choice" <?= $dis ?>>
    <legend><?= e(t('inv.rsvp_q')) ?></legend>
    <label class="pill"><input type="radio" name="attending" value="1" required <?= $att === 1 ? 'checked' : '' ?>> <span><?= e(t('inv.rsvp_yes')) ?></span></label>
    <label class="pill"><input type="radio" name="attending" value="0" <?= $att === 0 ? 'checked' : '' ?>> <span><?= e(t('inv.rsvp_no')) ?></span></label>
  </fieldset>
  <fieldset <?= $dis ?> class="rsvp-fields">
    <?php if (!$group): ?>
      <label class="ifield"><span><?= e(t('inv.rsvp_name')) ?></span><input name="name" maxlength="120" autocomplete="name" required value="<?= e($my['name'] ?? '') ?>"></label>
    <?php endif; ?>
    <?php if (!empty($c['rsvp']['ask_counts'])): ?>
      <div class="rsvp-counts" data-when-attending>
        <label class="ifield"><span><?= e(t('inv.adults')) ?></span><input type="number" name="adults" min="0" max="<?= $max ?>" value="<?= (int)($my['adults'] ?? 1) ?: 1 ?>" inputmode="numeric"></label>
        <label class="ifield"><span><?= e(t('inv.children')) ?></span><input type="number" name="children" min="0" max="<?= $max ?>" value="<?= (int)($my['children'] ?? 0) ?>" inputmode="numeric"></label>
      </div>
      <p class="muted small"><?= e(t('inv.max_guests', ['n' => $max])) ?></p>
    <?php endif; ?>
    <label class="ifield"><span><?= e(t('inv.note')) ?> <small>(<?= e(t('form.optional')) ?>)</small></span><textarea name="note" rows="3" maxlength="1000"><?= e($my['note'] ?? '') ?></textarea></label>
    <button class="ibtn ibtn-primary" type="submit"><?= e($my ? t('inv.rsvp_update') : t('inv.rsvp_send')) ?></button>
  </fieldset>
</form>
