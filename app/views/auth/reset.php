<section class="section">
  <div class="wrap auth">
    <div class="card form">
      <h1 class="h2"><?= e(t('auth.reset')) ?></h1>
      <?php if (!$valid): ?>
        <p><?= e(t('auth.reset_invalid')) ?></p><p><a href="<?= e(url('/forgot')) ?>"><?= e(t('auth.forgot')) ?></a></p>
      <?php else: ?>
      <form method="post" action="<?= e(url('/reset/' . $token)) ?>">
        <?= csrf_field() ?>
        <label class="field"><span><?= e(t('auth.new_password')) ?></span><input type="password" name="password" required minlength="10" autocomplete="new-password">
          <?php if (isset($errors['password'])): ?><small class="err"><?= e($errors['password']) ?></small><?php endif; ?></label>
        <button class="btn btn-primary btn-block" type="submit"><?= e(t('auth.reset')) ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</section>
