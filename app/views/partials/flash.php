<?php $fl = flashes(); if ($fl): ?>
<div class="flashes" role="status" aria-live="polite">
  <?php foreach ($fl as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
