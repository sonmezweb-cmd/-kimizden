<?php use App\Fmt; ?>
<h1 class="h2"><?= e(t('admin.messages')) ?></h1>
<?php foreach ($rows as $m): ?>
  <article class="card"><p class="muted small"><?= e(Fmt::dt($m['created_at'])) ?> · <?= strtoupper(e($m['lang'])) ?><?= (int)$m['is_read'] ? '' : ' · <span class="badge badge-info">' . e(t('admin.new')) . '</span>' ?></p>
    <h2 class="h4"><?= e($m['name']) ?> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></h2>
    <p><?= nl2br(e($m['message'])) ?></p></article>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="card empty"><?= e(t('admin.no_results')) ?></div><?php endif; ?>
