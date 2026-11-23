<div class="bare-card">
  <img src="<?= e(\App\App::baseUrl()) ?>/assets/img/logo-360.webp" width="180" height="60" alt="İkimizden">
  <h1 class="h2"><?= e(!empty($expired) ? t('inv.link_expired') : t('inv.unavailable')) ?></h1>
  <p class="muted"><?= e(t('inv.unavailable_text')) ?></p>
</div>
