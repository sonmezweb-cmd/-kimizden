<section class="section">
  <div class="wrap narrow">
    <h1 class="h1"><?= e(t('how.title')) ?></h1>
    <p class="lead"><?= e(t('how.lead')) ?></p>
    <?= \App\View::partial('site/_steps') ?>
    <div class="cta-row"><a class="btn btn-primary" href="<?= e(url('/themes')) ?>"><?= e(t('home.cta_themes')) ?></a></div>
  </div>
</section>
