<?php $errors = $errors ?? []; $old = $old ?? []; ?>
<section class="section">
  <div class="wrap auth">
    <form method="post" action="<?= e(url('/register')) ?>" class="card form" data-keep novalidate>
      <h1 class="h2"><?= e(t('auth.register')) ?></h1>
      <?= csrf_field() ?>
      <label class="field"><span><?= e(t('form.your_name')) ?></span><input name="name" required maxlength="120" autocomplete="name" value="<?= e($old['name'] ?? '') ?>">
        <?php if (isset($errors['name'])): ?><small class="err"><?= e($errors['name']) ?></small><?php endif; ?></label>
      <label class="field"><span><?= e(t('form.email')) ?></span><input type="email" name="email" required autocomplete="email" value="<?= e($old['email'] ?? '') ?>">
        <?php if (isset($errors['email'])): ?><small class="err"><?= e($errors['email']) ?></small><?php endif; ?></label>
      <label class="field"><span><?= e(t('form.phone')) ?> <small class="muted"><?= e(t('form.optional')) ?></small></span><input type="tel" name="phone" autocomplete="tel" value="<?= e($old['phone'] ?? '') ?>"></label>
      <label class="field"><span><?= e(t('form.password')) ?></span><input type="password" name="password" required minlength="10" autocomplete="new-password" data-nokeep>
        <small class="muted"><?= e(t('v.password_len')) ?></small>
        <?php if (isset($errors['password'])): ?><small class="err"><?= e($errors['password']) ?></small><?php endif; ?></label>
      <label class="check"><input type="checkbox" name="accept" value="1" required> <span><?= strtr(e(t('auth.accept')), ['{privacy}' => '<a href="' . e(url('/legal/privacy')) . '" target="_blank">' . e(t('legal.privacy')) . '</a>', '{terms}' => '<a href="' . e(url('/legal/terms')) . '" target="_blank">' . e(t('legal.terms')) . '</a>']) ?></span></label>
      <?php if (isset($errors['accept'])): ?><small class="err"><?= e($errors['accept']) ?></small><?php endif; ?>
      <button class="btn btn-primary btn-block" type="submit"><?= e(t('auth.register')) ?></button>
      <p class="small"><a href="<?= e(url('/login')) ?>"><?= e(t('auth.have_account')) ?></a></p>
    </form>
  </div>
</section>
