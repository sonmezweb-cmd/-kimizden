<section class="section">
  <div class="wrap narrow prose">
    <h1 class="h1"><?= e($page['title'] ?? t('legal.' . $key)) ?></h1>
    <?php if ($page && trim($page['body']) !== ''): ?>
      <?= nl_paras($page['body']) ?>
      <p class="muted small"><?= e(t('legal.updated')) ?>: <?= e(\App\Fmt::dt($page['updated_at'])) ?></p>
    <?php else: ?>
      <p class="muted"><?= e(t('legal.not_published')) ?></p>
    <?php endif; ?>
  </div>
</section>
