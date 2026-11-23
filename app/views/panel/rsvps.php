<?php
use App\Fmt;
use App\View;
$no = $order['public_no'];
$s = $stats;
?>
<?= View::partial('partials/order_tabs', get_defined_vars()) ?>
<section class="card">
  <div class="page-head"><h2 class="h3"><?= e(t('rsvp.report')) ?></h2><a class="btn btn-sm btn-ghost" href="<?= e(url('/panel/orders/' . $no . '/rsvps.csv')) ?>"><?= e(t('rsvp.csv')) ?></a></div>
  <h3 class="h4"><?= e(t('rsvp.groups_section')) ?></h3>
  <div class="stats">
    <div class="stat"><span><?= e(t('rsvp.invited')) ?></span><strong><?= Fmt::number($s['invited']) ?></strong></div>
    <div class="stat"><span><?= e(t('rsvp.responded')) ?></span><strong><?= Fmt::number($s['responded']) ?></strong></div>
    <div class="stat stat-ok"><span><?= e(t('rsvp.people_coming')) ?></span><strong><?= Fmt::number($s['people']) ?></strong><small><?= e(t('rsvp.adults_children', ['a' => $s['adults'], 'c' => $s['children']])) ?></small></div>
    <div class="stat stat-warn"><span><?= e(t('rsvp.st.not_coming')) ?></span><strong><?= Fmt::number($s['not_coming']) ?></strong></div>
    <div class="stat"><span><?= e(t('rsvp.st.no_answer')) ?></span><strong><?= Fmt::number($s['no_answer']) ?></strong></div>
  </div>
  <h3 class="h4"><?= e(t('rsvp.public_section')) ?></h3>
  <p class="muted small"><?= e(t('rsvp.public_help')) ?></p>
  <div class="stats">
    <div class="stat"><span><?= e(t('rsvp.public_count')) ?></span><strong><?= Fmt::number($s['public_count']) ?></strong></div>
    <div class="stat stat-ok"><span><?= e(t('rsvp.people_coming')) ?></span><strong><?= Fmt::number($s['public_people']) ?></strong></div>
    <div class="stat stat-warn"><span><?= e(t('rsvp.st.not_coming')) ?></span><strong><?= Fmt::number($s['public_not']) ?></strong></div>
  </div>
  <p class="lead"><?= e(t('rsvp.total_people', ['n' => Fmt::number($s['total_people'])])) ?></p>
</section>
<section class="card">
  <h3 class="h4"><?= e(t('rsvp.details')) ?></h3>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th><?= e(t('csv.source')) ?></th><th><?= e(t('csv.name')) ?></th><th><?= e(t('csv.status')) ?></th><th><?= e(t('csv.adults')) ?></th><th><?= e(t('csv.children')) ?></th><th><?= e(t('csv.note')) ?></th><th><?= e(t('csv.updated')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($groups as $g): ?>
      <tr><td><?= e(t('csv.group')) ?></td><td><?= e($g['name']) ?></td>
        <td><?= $g['attending'] === null ? e(t('rsvp.st.no_answer')) : ((int)$g['attending'] ? e(t('rsvp.st.coming')) : e(t('rsvp.st.not_coming'))) ?></td>
        <td><?= (int)$g['adults'] ?></td><td><?= (int)$g['children'] ?></td><td><?= nl2br(e((string)$g['note'])) ?></td><td><?= e(Fmt::dt($g['updated_at'])) ?></td></tr>
    <?php endforeach; ?>
    <?php foreach ($public as $p): ?>
      <tr><td><?= e(t('csv.public')) ?></td><td><?= e($p['name']) ?></td><td><?= (int)$p['attending'] ? e(t('rsvp.st.coming')) : e(t('rsvp.st.not_coming')) ?></td>
        <td><?= (int)$p['adults'] ?></td><td><?= (int)$p['children'] ?></td><td><?= nl2br(e((string)$p['note'])) ?></td><td><?= e(Fmt::dt($p['updated_at'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
