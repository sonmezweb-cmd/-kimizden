<section class="section">
  <div class="wrap">
    <h1 class="h1"><?= e(t('themes.title')) ?></h1>
    <p class="lead"><?= e(t('themes.lead')) ?></p>
    <?= \App\View::partial('site/_theme_grid', ['themes' => $themes]) ?>
  </div>
</section>
