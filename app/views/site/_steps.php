<ol class="steps">
  <?php foreach (['choose', 'pay', 'info', 'draft', 'approve', 'share'] as $i => $s): ?>
    <li>
      <span class="step-no"><?= $i + 1 ?></span>
      <h3 class="h4"><?= e(t('how.' . $s . '.title')) ?></h3>
      <p><?= e(t('how.' . $s . '.text')) ?></p>
    </li>
  <?php endforeach; ?>
</ol>
