<section class="section">
  <div class="wrap auth">
    <form method="post" action="<?= e(url('/forgot')) ?>" class="card form">
      <h1 class="h2"><?= e(t('auth.forgot')) ?></h1>
      <p class="muted"><?= e(t('auth.forgot_text')) ?></p>
      <?= csrf_field() ?>
      <label class="field"><span><?= e(t('form.email')) ?></span><input type="email" name="email" required autocomplete="email"></label>
      <button class="btn btn-primary btn-block" type="submit"><?= e(t('auth.send_link')) ?></button>
    </form>
  </div>
</section>
