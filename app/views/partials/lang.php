<?php
use App\I18n;
$q = $_GET;
unset($q['lang']);
$cls = $cls ?? '';
?>
<nav class="lang <?= e($cls) ?>" aria-label="<?= e(t('nav.language')) ?>">
  <?php foreach (I18n::LANGS as $l): ?>
    <a href="<?= e(url(current_path(), $l, $q)) ?>" hreflang="<?= $l ?>" lang="<?= $l ?>" data-lang-switch="<?= $l ?>"
       <?= $l === I18n::$lang ? 'aria-current="true" class="is-on"' : '' ?> title="<?= e(I18n::NAMES[$l]) ?>"><?= strtoupper($l) ?></a>
  <?php endforeach; ?>
</nav>
