<div class="theme-grid">
  <?php foreach ($themes as $t): ?>
    <article class="theme-card">
      <a href="<?= e(url('/themes/' . $t['slug'])) ?>" class="theme-card-img">
        <img src="<?= e($t['media']['preview']) ?>" alt="<?= e(loc($t['name'])) ?>" width="640" height="1138" loading="lazy" decoding="async">
      </a>
      <div class="theme-card-body">
        <h3 class="h3"><a href="<?= e(url('/themes/' . $t['slug'])) ?>"><?= e(loc($t['name'])) ?></a></h3>
        <p><?= e(loc($t['description'])) ?></p>
        <div class="cta-row">
          <a class="btn btn-primary btn-sm" href="<?= e(url('/preview/' . $t['slug'])) ?>"><?= e(t('theme.live_preview')) ?></a>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('/themes/' . $t['slug'])) ?>"><?= e(t('theme.details')) ?></a>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
</div>
