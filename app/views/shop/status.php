<section class="section">
  <div class="wrap narrow center">
    <h1 class="h2"><?= e(t('pay.status_title')) ?></h1>
    <?php if ($state === 'login'): ?>
      <p><?= e(t('pay.login_to_see')) ?></p>
      <a class="btn btn-primary" href="<?= e(url('/login')) ?>"><?= e(t('nav.login')) ?></a>
    <?php else: ?>
      <div class="pay-state pay-state-<?= e($state) ?>" id="pay-state" data-state="<?= e($state) ?>" data-poll="<?= e(url('/pay/' . $no . '/status')) ?>" role="status" aria-live="polite">
        <p class="lead"><?= e(t('pay.state.' . $state)) ?></p>
        <?php if ($state === 'verifying'): ?><p class="muted small"><?= e(t('pay.verifying_note')) ?></p><?php endif; ?>
      </div>
      <p class="cta-row center">
        <?php if (in_array($state, ['failed', 'unpaid'], true)): ?><a class="btn btn-primary" href="<?= e(url('/checkout/' . $no)) ?>"><?= e(t('pay.try_again')) ?></a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('/panel/orders/' . $no)) ?>"><?= e(t('checkout.to_order')) ?></a>
      </p>
    <?php endif; ?>
  </div>
</section>
<script src="<?= e(asset('js/pay.js')) ?>" defer></script>
