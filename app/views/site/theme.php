<?php
use App\Catalog;
use App\Fmt;
?>
<section class="section">
  <div class="wrap theme-detail">
    <div class="theme-detail-media">
      <img src="<?= e($theme['media']['preview']) ?>" alt="<?= e(loc($theme['name'])) ?>" width="640" height="1138">
      <a class="btn btn-primary btn-block" href="<?= e(url('/preview/' . $theme['slug'])) ?>"><?= e(t('theme.live_preview')) ?></a>
    </div>
    <div>
      <p class="eyebrow"><a href="<?= e(url('/themes')) ?>"><?= e(t('nav.themes')) ?></a></p>
      <h1 class="h1"><?= e(loc($theme['name'])) ?></h1>
      <p class="lead"><?= e(loc($theme['description'])) ?></p>
      <ul class="ticks">
        <li><?= e(t('theme.feat.scroll')) ?></li>
        <li><?= e(t('theme.feat.text')) ?></li>
        <li><?= e(t('theme.feat.langs')) ?></li>
        <li><?= e(t('theme.feat.rsvp')) ?></li>
      </ul>

      <form method="post" action="<?= e(url('/order/start')) ?>" class="card order-form" data-keep>
        <?= csrf_field() ?>
        <input type="hidden" name="theme_id" value="<?= (int)$theme['id'] ?>">
        <fieldset>
          <legend class="h3"><?= e(t('theme.choose_package')) ?></legend>
          <div class="pkg-choices">
          <?php $firstSel = false; foreach ($packages as $p): $buy = Catalog::purchasable($p); ?>
            <label class="pkg-choice">
              <input type="radio" name="package_id" value="<?= (int)$p['id'] ?>" <?= !$firstSel ? 'checked' : '' ?> required>
              <?php $firstSel = true; ?>
              <span class="pkg-choice-body">
                <strong><?= e(loc($p['name'])) ?></strong>
                <span class="price"><?= $p['price_minor'] !== null ? e(Fmt::money((int)$p['price_minor'], $p['currency'])) : e(t('pkg.price_soon')) ?></span>
                <span class="muted"><?= e(t('pkg.revisions', ['n' => $p['revisions']])) ?> · <?= e(t('pkg.delivery', ['n' => $p['delivery_days']])) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
          </div>
        </fieldset>
        <?php $priced = array_filter($addons, fn($a) => $a['price_minor'] !== null); if ($priced): ?>
        <fieldset>
          <legend class="h4"><?= e(t('theme.addons')) ?></legend>
          <?php foreach ($priced as $a): ?>
            <label class="check"><input type="checkbox" name="addons[]" value="<?= (int)$a['id'] ?>"> <?= e(loc($a['name'])) ?> — <?= e(Fmt::money((int)$a['price_minor'])) ?><br><small class="muted"><?= e(loc($a['description'])) ?></small></label>
          <?php endforeach; ?>
        </fieldset>
        <?php endif; ?>
        <button class="btn btn-primary btn-block" type="submit"><?= e(t('theme.continue')) ?></button>
        <p class="muted small"><?= e(t('theme.continue_note')) ?></p>
      </form>
    </div>
  </div>
</section>
