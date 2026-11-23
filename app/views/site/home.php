<?php
use App\Fmt;
use App\View;
$first = $themes[0] ?? null;
$cards = [];
foreach ($themes as $t) {
    $cards[] = ['slug' => $t['slug'], 'name' => loc($t['name']), 'poster' => $t['media']['poster'], 'video' => $t['media']['video'],
        'geometry' => $t['geometry'], 'sealSize' => $t['seal_size'], 'texture' => $t['media']['texture'], 'ornament' => $t['media']['ornament'], 'body' => $t['media']['body'],
        'seal' => $t['media']['seal'], 'paper' => $t['media']['paper'], 'sheen' => $t['media']['sheen'], 'sheenKind' => $t['config']['sheen'] ?? 'copper', 'colors' => $t['colors'],
        'font' => $t['config']['font'] ?? 'greatvibes', 'preview' => url('/preview/' . $t['slug'])];
}
$demoNames = ['tr' => ['İpek', 'Çağrı'], 'en' => ['Elizabeth', 'Alexander'], 'ru' => ['Анастасия', 'Александр']][$lang];
?>
<section class="hero">
  <div class="wrap hero-grid">
    <div class="hero-copy">
      <p class="eyebrow"><?= e(t('home.eyebrow')) ?></p>
      <h1 class="display"><?= e(t('home.title')) ?></h1>
      <p class="lead"><?= e(t('home.lead')) ?></p>
      <div class="cta-row">
        <a class="btn btn-primary" href="<?= e(url('/themes')) ?>"><?= e(t('home.cta_themes')) ?></a>
        <a class="btn btn-ghost" href="<?= e(url('/how-it-works')) ?>"><?= e(t('nav.how')) ?></a>
      </div>
    </div>
    <?php if ($first): ?>
    <div class="opener" id="opener" data-cards="<?= e(jenc($cards)) ?>">
      <div class="opener-card" data-theme="<?= e($first['slug']) ?>" style="--t-ink: <?= e($first['colors']['ink'] ?? '#4a1024') ?>">
        <div class="env-stage opener-stage" data-env-card>
          <img class="env-poster" src="<?= e($first['media']['poster']) ?>" alt="<?= e(t('home.opener_alt', ['theme' => loc($first['name'])])) ?>" width="540" height="960" fetchpriority="high">
          <div class="env-text" aria-live="polite">
            <p class="env-names font-<?= e($first['config']['font'] ?? 'greatvibes') ?>"><span class="n1"><?= e($demoNames[0]) ?></span> <span class="amp">&amp;</span> <span class="n2"><?= e($demoNames[1]) ?></span></p>
            <p class="env-date"><?= e(t('home.sample')) ?></p>
          </div>
        </div>
        <div class="opener-actions">
          <button type="button" class="btn btn-primary" data-opener-open><?= e(t('home.open')) ?></button>
          <a class="btn btn-ghost" href="<?= e(url('/preview/' . $first['slug'])) ?>" data-opener-full><?= e(t('home.full_preview')) ?></a>
        </div>
      </div>
      <div class="opener-themes" role="group" aria-label="<?= e(t('nav.themes')) ?>">
        <?php foreach ($themes as $i => $t): ?>
          <button type="button" class="chip <?= $i === 0 ? 'is-on' : '' ?>" data-opener-theme="<?= e($t['slug']) ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
            <img src="<?= e($t['media']['poster_small']) ?>" alt="" width="28" height="50" loading="lazy"> <?= e(loc($t['name'])) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <h2 class="h2"><?= e(t('home.collection')) ?></h2>
      <a href="<?= e(url('/themes')) ?>"><?= e(t('home.all_themes')) ?></a>
    </div>
    <?= View::partial('site/_theme_grid', ['themes' => $themes]) ?>
  </div>
</section>

<section class="section section-alt">
  <div class="wrap">
    <h2 class="h2"><?= e(t('how.title')) ?></h2>
    <?= View::partial('site/_steps') ?>
  </div>
</section>

<?php if ($packages): ?>
<section class="section">
  <div class="wrap">
    <div class="section-head">
      <h2 class="h2"><?= e(t('nav.packages')) ?></h2>
      <a href="<?= e(url('/packages')) ?>"><?= e(t('home.compare')) ?></a>
    </div>
    <div class="pkg-mini">
      <?php foreach ($packages as $p): ?>
        <a class="pkg-mini-item" href="<?= e(url('/packages')) ?>">
          <strong><?= e(loc($p['name'])) ?></strong>
          <span><?= $p['price_minor'] !== null ? e(Fmt::money((int)$p['price_minor'], $p['currency'])) : e(t('pkg.price_soon')) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<script src="<?= e(asset('js/envelope.js')) ?>" defer></script>
