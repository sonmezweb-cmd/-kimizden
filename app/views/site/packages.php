<?php use App\Fmt; ?>
<section class="section">
  <div class="wrap">
    <h1 class="h1"><?= e(t('packages.title')) ?></h1>
    <p class="lead"><?= e(t('packages.lead')) ?></p>
    <div class="pkg-grid">
      <?php foreach ($packages as $p): ?>
        <article class="pkg card">
          <h2 class="h3"><?= e(loc($p['name'])) ?></h2>
          <p class="pkg-price"><?= $p['price_minor'] !== null ? e(Fmt::money((int)$p['price_minor'], $p['currency'])) : e(t('pkg.price_soon')) ?></p>
          <ul class="ticks">
            <?php foreach (($p['scope'][$lang] ?? $p['scope']['tr'] ?? []) as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
          </ul>
          <dl class="pkg-facts">
            <dt><?= e(t('pkg.revisions_label')) ?></dt><dd><?= (int)$p['revisions'] ?></dd>
            <dt><?= e(t('pkg.delivery_label')) ?></dt><dd><?= e(t('pkg.days', ['n' => $p['delivery_days']])) ?></dd>
            <dt><?= e(t('pkg.languages_label')) ?></dt><dd><?= (int)$p['max_languages'] ?></dd>
            <dt><?= e(t('pkg.groups_label')) ?></dt><dd><?= (int)$p['guest_groups'] ? e(t('common.yes')) : e(t('common.no')) ?></dd>
          </dl>
          <a class="btn btn-primary btn-block" href="<?= e(url('/themes')) ?>"><?= e(t('packages.choose_theme')) ?></a>
        </article>
      <?php endforeach; ?>
    </div>
    <?php $priced = array_filter($addons, fn($a) => $a['price_minor'] !== null); if ($priced): ?>
      <h2 class="h3"><?= e(t('theme.addons')) ?></h2>
      <ul class="addon-list">
        <?php foreach ($priced as $a): ?><li><strong><?= e(loc($a['name'])) ?></strong> — <?= e(Fmt::money((int)$a['price_minor'])) ?><br><span class="muted"><?= e(loc($a['description'])) ?></span></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p class="muted small"><?= e(t('packages.currency_note')) ?></p>
  </div>
</section>
