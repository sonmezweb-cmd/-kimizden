<section class="section">
  <div class="wrap narrow center">
    <p class="eyebrow"><?= (int)$code ?></p>
    <h1 class="h1"><?= e(match ((int)$code) { 404 => t('error.not_found'), 403 => t('error.forbidden'), 419 => t('error.csrf'), 405 => t('error.method'), 429 => t('auth.too_many'), default => t('error.generic') }) ?></h1>
    <?php if (!empty($msg) && !in_array($msg, [t('error.not_found'), t('error.forbidden'), t('error.csrf')], true)): ?><p class="lead"><?= e($msg) ?></p><?php endif; ?>
    <p><a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= e(t('error.home')) ?></a></p>
  </div>
</section>
