<section class="section">
  <div class="wrap narrow">
    <h1 class="h2"><?= e(t('pay.title')) ?></h1>
    <p class="muted"><?= e(t('pay.frame_note')) ?></p>
    <?php if ($lang === 'ru'): ?><p class="notice notice-info"><?= e(t('pay.lang_note')) ?></p><?php endif; ?>
    <div class="pay-frame">
      <iframe src="<?= e($iframe) ?>" id="paytriframe" title="PayTR" frameborder="0" scrolling="no" style="width:100%;min-height:600px"></iframe>
    </div>
    <p class="small"><a href="<?= e(url('/pay/' . $order['public_no'] . '/return')) ?>"><?= e(t('pay.check_status')) ?></a></p>
  </div>
</section>
<script src="<?= e($resizer) ?>"></script>
<script src="<?= e(asset('js/pay.js')) ?>"></script>
