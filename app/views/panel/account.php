<?php use App\Auth; use App\I18n; $u = Auth::user(); ?>
<h1 class="h2"><?= e(t('panel.account')) ?></h1>
<form method="post" action="<?= e(url('/panel/account')) ?>" class="card form narrow-form" data-keep>
  <?= csrf_field() ?>
  <label class="field"><span><?= e(t('form.your_name')) ?></span><input name="name" value="<?= e($u['name']) ?>" maxlength="120" required></label>
  <label class="field"><span><?= e(t('form.email')) ?></span><input value="<?= e($u['email']) ?>" disabled></label>
  <label class="field"><span><?= e(t('form.phone')) ?></span><input type="tel" name="phone" value="<?= e($u['phone'] ?? '') ?>"></label>
  <label class="field"><span><?= e(t('account.email_lang')) ?></span>
    <select name="locale"><?php foreach (I18n::LANGS as $l): ?><option value="<?= $l ?>" <?= $u['locale'] === $l ? 'selected' : '' ?>><?= e(I18n::NAMES[$l]) ?></option><?php endforeach; ?></select></label>
  <fieldset>
    <legend class="h4"><?= e(t('account.change_password')) ?></legend>
    <label class="field"><span><?= e(t('account.current_password')) ?></span><input type="password" name="current_password" autocomplete="current-password" data-nokeep></label>
    <label class="field"><span><?= e(t('auth.new_password')) ?></span><input type="password" name="new_password" minlength="10" autocomplete="new-password" data-nokeep></label>
  </fieldset>
  <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
</form>
