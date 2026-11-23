<?php use App\View; ?>
<div class="page-head">
  <h1 class="h2"><?= e(t('panel.my_orders')) ?></h1>
  <a class="btn btn-primary btn-sm" href="<?= e(url('/themes')) ?>"><?= e(t('panel.new_order')) ?></a>
</div>
<?php if (!$cards): ?>
  <div class="empty card">
    <p><?= e(t('panel.no_orders')) ?></p>
    <a class="btn btn-primary" href="<?= e(url('/themes')) ?>"><?= e(t('home.cta_themes')) ?></a>
  </div>
<?php endif; ?>
<div class="order-cards">
<?php foreach ($cards as $c): $o = $c['order']; $s = $o['snapshot']; ?>
  <article class="card order-card">
    <header>
      <p class="eyebrow"><?= e($o['public_no']) ?></p>
      <h2 class="h3"><a href="<?= e(url('/panel/orders/' . $o['public_no'])) ?>"><?= e(loc($s['theme']['name'])) ?> · <?= e(loc($s['package']['name'])) ?></a></h2>
    </header>
    <div class="status-row"><?= View::partial('partials/status', ['order' => $o]) ?></div>
    <dl class="facts">
      <dt><?= e(t('panel.missing')) ?></dt><dd><?= $c['missing'] ? e(t('panel.missing_n', ['n' => $c['missing']])) : e(t('panel.none_missing')) ?></dd>
      <dt><?= e(t('panel.latest_draft')) ?></dt><dd><?= $c['latest'] ? e(t('preview.version_label', ['n' => $c['latest']['version_no']])) . ' · ' . e(t('vstatus.' . $c['latest']['status'])) : '—' ?></dd>
      <?php if ($c['latest'] && $c['latest']['designer_note']): ?><dt><?= e(t('panel.designer_note')) ?></dt><dd class="note"><?= nl2br(e($c['latest']['designer_note'])) ?></dd><?php endif; ?>
    </dl>
    <div class="next">
      <span class="muted small"><?= e(t('panel.next_step')) ?></span>
      <?php if ($c['next']['url']): ?>
        <a class="btn btn-primary btn-sm" href="<?= e($c['next']['url']) ?>"><?= e(t($c['next']['key'])) ?></a>
      <?php else: ?>
        <strong><?= e(t($c['next']['key'])) ?></strong>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>
