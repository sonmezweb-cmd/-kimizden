<section class="section">
  <div class="wrap narrow">
    <h1 class="h1"><?= e(t('faq.title')) ?></h1>
    <div class="faq">
      <?php for ($i = 1; $i <= 10; $i++): ?>
        <details>
          <summary><?= e(t('faq.q' . $i)) ?></summary>
          <div><?= nl_paras(t('faq.a' . $i)) ?></div>
        </details>
      <?php endfor; ?>
    </div>
  </div>
</section>
