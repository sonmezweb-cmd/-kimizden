<?php
use App\Content;
use App\View;
$no = $order['public_no'];
$names = $live ? Content::names($live['content']) : null;
$waText = $live ? t('share.wa_text', ['names' => trim($names[0] . ' & ' . $names[1]), 'link' => $publicUrl], $live['content']['default_lang']) : '';
?>
<?= View::partial('partials/order_tabs', get_defined_vars()) ?>
<?php if ($order['publish_status'] !== 'published'): ?>
  <div class="card empty"><p><?= e(t('share.not_published')) ?></p><p class="muted small"><?= e(t('share.link_reserved')) ?> <code><?= e($publicUrl) ?></code></p></div>
<?php else: ?>
<div class="grid-main">
  <section class="card">
    <h2 class="h3"><?= e(t('share.title')) ?></h2>
    <div class="copy-row">
      <input readonly value="<?= e($publicUrl) ?>" aria-label="<?= e(t('share.link')) ?>">
      <button type="button" class="btn btn-sm" data-copy="<?= e($publicUrl) ?>"><?= View::partial('partials/icon', ['name' => 'copy']) ?> <?= e(t('common.copy')) ?></button>
    </div>
    <p class="cta-row">
      <a class="btn btn-primary" href="https://wa.me/?text=<?= e(rawurlencode($waText)) ?>" target="_blank" rel="noopener"><?= View::partial('partials/icon', ['name' => 'whatsapp']) ?> <?= e(t('share.whatsapp')) ?></a>
      <a class="btn btn-ghost" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= View::partial('partials/icon', ['name' => 'eye']) ?> <?= e(t('share.open')) ?></a>
    </p>
    <p class="muted small"><?= e(t('share.langs')) ?>
      <?php foreach ($live['content']['languages'] as $l): ?><a href="<?= e($publicUrl . '?l=' . $l) ?>" target="_blank" rel="noopener"><?= strtoupper($l) ?></a> <?php endforeach; ?></p>
    <p class="muted small"><?= e(t('share.preview_meta')) ?></p>
  </section>
  <aside class="card center">
    <h2 class="h4"><?= e(t('share.qr')) ?></h2>
    <img src="<?= e(url('/panel/orders/' . $no . '/qr.svg')) ?>" alt="<?= e(t('share.qr_alt')) ?>" width="220" height="220" class="qr">
    <p class="cta-row center">
      <a class="btn btn-sm btn-ghost" href="<?= e(url('/panel/orders/' . $no . '/qr.png', null, ['dl' => 1])) ?>">PNG</a>
      <a class="btn btn-sm btn-ghost" href="<?= e(url('/panel/orders/' . $no . '/qr.svg', null, ['dl' => 1])) ?>">SVG</a>
    </p>
  </aside>
</div>
<?php endif; ?>
