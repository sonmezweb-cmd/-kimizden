<?php
use App\Auth;
use App\Fmt;
use App\View;
$s = $order['snapshot'];
$c = $draft['content'];
$isCustomer = Auth::is('customer');
$missingCount = array_sum(array_map('count', $missing));
?>
<?= View::partial('partials/order_tabs', get_defined_vars()) ?>
<div class="grid-main">
  <section class="card">
    <h2 class="h3"><?= e(t('panel.next_step')) ?></h2>
    <?php if ($next['url']): ?><p><a class="btn btn-primary" href="<?= e($next['url']) ?>"><?= e(t($next['key'])) ?></a></p>
    <?php else: ?><p class="lead"><?= e(t($next['key'])) ?></p><?php endif; ?>
    <?php if ($isCustomer && $order['design_status'] === 'info_pending'): ?>
      <p class="muted"><?= e(t('form.submit_help')) ?></p>
    <?php endif; ?>
    <dl class="facts">
      <dt><?= e(t('panel.missing')) ?></dt>
      <dd><?= $missingCount ? e(t('panel.missing_n', ['n' => $missingCount])) . ' — <a href="' . e(url('/panel/orders/' . $order['public_no'] . '/form/review')) . '">' . e(t('panel.see_missing')) . '</a>' : e(t('panel.none_missing')) ?></dd>
      <dt><?= e(t('panel.latest_draft')) ?></dt>
      <dd><?= $latest ? e(t('preview.version_label', ['n' => $latest['version_no']])) . ' · ' . e(t('vstatus.' . $latest['status'])) . ' · ' . e(Fmt::dt($latest['created_at'])) : '—' ?></dd>
      <?php if ($latest && $latest['designer_note']): ?><dt><?= e(t('panel.designer_note')) ?></dt><dd class="note"><?= nl2br(e($latest['designer_note'])) ?></dd><?php endif; ?>
      <dt><?= e(t('panel.approved_version')) ?></dt>
      <dd><?= $approved ? e(t('preview.version_label', ['n' => $approved['version_no']])) . ' · ' . e(Fmt::dt($approved['approved_at'])) : '—' ?></dd>
      <dt><?= e(t('pkg.revisions_label')) ?></dt>
      <dd><?= e(t('designer.rev_left', ['n' => max(0, (int)$order['revisions_allowed'] - count($revisions)), 'total' => (int)$order['revisions_allowed']])) ?></dd>
      <?php if ($order['publish_status'] === 'published'): ?>
        <dt><?= e(t('share.link')) ?></dt><dd><a href="<?= e(url_raw('/i/' . $inv['slug'])) ?>" target="_blank" rel="noopener"><?= e(url_raw('/i/' . $inv['slug'])) ?></a></dd>
      <?php endif; ?>
    </dl>
  </section>
  <aside class="card">
    <h2 class="h4"><?= e(t('panel.package_scope')) ?></h2>
    <p><strong><?= e(loc($s['package']['name'])) ?></strong> · <?= e(Fmt::money($s['total_minor'], $s['currency'])) ?></p>
    <ul class="ticks small">
      <?php foreach (($s['package']['scope'][$lang] ?? $s['package']['scope']['tr'] ?? []) as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
      <?php foreach ($s['addons'] as $a): ?><li><?= e(loc($a['name'])) ?></li><?php endforeach; ?>
    </ul>
    <p class="muted small"><?= e(t('pkg.delivery', ['n' => $s['package']['delivery_days']])) ?> · <?= e(t('pkg.max_langs', ['n' => $s['package']['max_languages']])) ?></p>
    <?php if (in_array($order['payment_status'], ['unpaid', 'awaiting'], true) && $isCustomer): ?>
      <a class="btn btn-primary btn-block" href="<?= e(url('/checkout/' . $order['public_no'])) ?>"><?= e(t('next.pay')) ?></a>
    <?php endif; ?>
  </aside>
</div>
<?php if ($revisions): ?>
<section class="card">
  <h2 class="h4"><?= e(t('revision.history')) ?></h2>
  <ul class="timeline">
    <?php foreach ($revisions as $r): ?>
      <li><strong><?= e(t('preview.version_label', ['n' => $r['version_no']])) ?></strong> · <?= e(Fmt::dt($r['created_at'])) ?><br><?= nl2br(e($r['message'])) ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
