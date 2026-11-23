<?php
use App\Fmt;
$s = $order['snapshot'];
$editable = $order['payment_status'] === 'unpaid';
$selAddons = array_column($s['addons'] ?? [], 'id');
?>
<section class="section">
  <div class="wrap narrow">
    <p class="eyebrow"><?= e(t('panel.order')) ?> <?= e($order['public_no']) ?></p>
    <h1 class="h1"><?= e(t('checkout.title')) ?></h1>
    <div class="card summary">
      <div class="summary-theme">
        <img src="<?= e(\App\Catalog::theme((int)$order['theme_id'])['media']['poster_small']) ?>" width="72" height="128" alt="">
        <div>
          <p class="muted small"><?= e(t('checkout.theme')) ?></p>
          <h2 class="h3"><?= e(loc($s['theme']['name'])) ?></h2>
          <p><?= e(loc($s['package']['name'])) ?> · <?= e(t('pkg.revisions', ['n' => $s['revisions_total']])) ?> · <?= e(t('pkg.delivery', ['n' => $s['package']['delivery_days']])) ?></p>
        </div>
      </div>
      <table class="lines">
        <tr><td><?= e(loc($s['package']['name'])) ?></td><td class="num"><?= e(Fmt::money($s['package']['price_minor'], $s['currency'])) ?></td></tr>
        <?php foreach ($s['addons'] as $a): ?><tr><td><?= e(loc($a['name'])) ?></td><td class="num"><?= e(Fmt::money($a['price_minor'], $s['currency'])) ?></td></tr><?php endforeach; ?>
        <tr class="total"><th><?= e(t('checkout.total')) ?></th><th class="num"><?= e(Fmt::money($s['total_minor'], $s['currency'])) ?></th></tr>
      </table>
      <p class="muted small"><?= e(t('checkout.snapshot_note')) ?></p>
    </div>

    <?php if ($editable): ?>
    <details class="card">
      <summary><?= e(t('checkout.change')) ?></summary>
      <form method="post" action="<?= e(url('/checkout/' . $order['public_no'])) ?>" class="form">
        <?= csrf_field() ?>
        <?php foreach ($packages as $p): ?>
          <label class="check"><input type="radio" name="package_id" value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$order['package_id'] ? 'checked' : '' ?>> <?= e(loc($p['name'])) ?> — <?= $p['price_minor'] !== null ? e(Fmt::money((int)$p['price_minor'], $p['currency'])) : e(t('pkg.price_soon')) ?></label>
        <?php endforeach; ?>
        <?php foreach (array_filter($addons, fn($a) => $a['price_minor'] !== null) as $a): ?>
          <label class="check"><input type="checkbox" name="addons[]" value="<?= (int)$a['id'] ?>" <?= in_array((int)$a['id'], $selAddons, true) ? 'checked' : '' ?>> <?= e(loc($a['name'])) ?> — <?= e(Fmt::money((int)$a['price_minor'])) ?></label>
        <?php endforeach; ?>
        <button class="btn btn-ghost" type="submit"><?= e(t('checkout.update')) ?></button>
      </form>
    </details>
    <?php endif; ?>

    <?php if (in_array($order['payment_status'], ['paid', 'test_paid', 'review'], true)): ?>
      <div class="notice notice-ok"><?= e(t('pay.state.' . \App\Controllers\PaymentController::state($order))) ?></div>
      <a class="btn btn-primary" href="<?= e(url('/panel/orders/' . $order['public_no'])) ?>"><?= e(t('checkout.to_order')) ?></a>
    <?php elseif (!$purchasable): ?>
      <div class="notice"><?= e(t('checkout.not_priced')) ?></div>
      <a class="btn btn-ghost" href="<?= e(url('/preview/' . $s['theme']['slug'])) ?>"><?= e(t('theme.live_preview')) ?></a>
    <?php elseif (!$payEnabled): ?>
      <div class="notice"><?= e(t('checkout.pay_disabled')) ?></div>
      <a class="btn btn-ghost" href="<?= e(url('/contact')) ?>"><?= e(t('nav.contact')) ?></a>
    <?php else: ?>
      <form method="post" action="<?= e(url('/pay/' . $order['public_no'])) ?>" class="pay-form">
        <?= csrf_field() ?>
        <?php if ($payMode === 'test'): ?><p class="notice notice-info"><?= e(t('checkout.test_mode')) ?></p><?php endif; ?>
        <?php if ($lang === 'ru'): ?><p class="notice notice-info"><?= e(t('pay.lang_note')) ?></p><?php endif; ?>
        <button class="btn btn-primary btn-block" type="submit"><?= e(t('checkout.pay', ['total' => Fmt::money($s['total_minor'], $s['currency'])])) ?></button>
        <p class="muted small"><?= e(t('checkout.secure_note')) ?></p>
      </form>
    <?php endif; ?>
    <p><a href="<?= e(url('/panel/orders/' . $order['public_no'] . '/form/names')) ?>"><?= e(t('checkout.start_form')) ?></a></p>
  </div>
</section>
