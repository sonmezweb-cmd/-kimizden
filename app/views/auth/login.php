<section class="section">
  <div class="wrap auth">
    <form method="post" action="<?= e(url('/login')) ?>" class="card form" novalidate>
      <h1 class="h2"><?= e(t('auth.login')) ?></h1>
      <?= csrf_field() ?>
      <label class="field"><span><?= e(t('form.email')) ?></span><input type="email" name="email" required autocomplete="email" value="<?= e($email ?? '') ?>"></label>
      <label class="field"><span><?= e(t('form.password')) ?></span><input type="password" name="password" required autocomplete="current-password"></label>
      <button class="btn btn-primary btn-block" type="submit"><?= e(t('auth.login')) ?></button>
      <p class="small"><a href="<?= e(url('/forgot')) ?>"><?= e(t('auth.forgot')) ?></a> · <a href="<?= e(url('/register')) ?>"><?= e(t('auth.no_account')) ?></a></p>
    </form>
  </div>
</section>
