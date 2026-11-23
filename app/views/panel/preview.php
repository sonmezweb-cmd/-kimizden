<?php
use App\Auth;
use App\Fmt;
use App\I18n;
use App\View;
$no = $order['public_no'];
$isCustomer = Auth::is('customer');
$isStaff = Auth::is('designer', 'admin');
$left = max(0, (int)$order['revisions_allowed'] - $revUsed);
$sel = null;
foreach ($versions as $v) if ('v' . $v['id'] === $what) $sel = $v;
?>
<?= View::partial('partials/order_tabs', get_defined_vars()) ?>
<div class="preview-layout">
  <section class="preview-frame-wrap">
    <div class="preview-bar">
      <div class="seg" role="group" aria-label="<?= e(t('preview.which')) ?>">
        <?php if ($isStaff): ?><a href="<?= e(url('/panel/orders/' . $no . '/preview', null, ['v' => 'draft', 'l' => $plang])) ?>" <?= $what === 'draft' ? 'aria-current="true"' : '' ?>><?= e(t('preview.draft_label')) ?></a><?php endif; ?>
        <?php foreach (array_slice($versions, 0, 6) as $v): ?>
          <a href="<?= e(url('/panel/orders/' . $no . '/preview', null, ['v' => 'v' . $v['id'], 'l' => $plang])) ?>" <?= $what === 'v' . $v['id'] ? 'aria-current="true"' : '' ?>>v<?= (int)$v['version_no'] ?></a>
        <?php endforeach; ?>
      </div>
      <div class="seg" role="group" aria-label="<?= e(t('preview.content_lang')) ?>">
        <?php foreach ($pcontent['languages'] as $l): ?>
          <a href="<?= e(url('/panel/orders/' . $no . '/preview', null, ['v' => $what, 'l' => $l])) ?>" <?= $plang === $l ? 'aria-current="true"' : '' ?>><?= strtoupper($l) ?></a>
        <?php endforeach; ?>
      </div>
      <a class="btn btn-xs btn-ghost" href="<?= e(url('/panel/orders/' . $no . '/render/' . $what, null, ['l' => $plang])) ?>" target="_blank" rel="noopener"><?= e(t('preview.open_full')) ?></a>
    </div>
    <?php if (!$versions && $isCustomer): ?>
      <div class="empty card"><p><?= e(t('preview.no_version_customer')) ?></p>
      <a class="btn btn-ghost" href="<?= e(url('/panel/orders/' . $no . '/render/draft')) ?>" target="_blank" rel="noopener"><?= e(t('form.review.preview_draft')) ?></a></div>
    <?php else: ?>
      <div class="phone-frame"><iframe src="<?= e(url('/panel/orders/' . $no . '/render/' . $what, null, ['l' => $plang])) ?>" title="<?= e(t('preview.title')) ?>" loading="lazy"></iframe></div>
    <?php endif; ?>
  </section>

  <aside class="preview-side">
    <?php if ($sel): ?>
      <section class="card">
        <h2 class="h4"><?= e(t('preview.version_label', ['n' => $sel['version_no']])) ?> · <span class="badge"><?= e(t('vstatus.' . $sel['status'])) ?></span></h2>
        <p class="muted small"><?= e(Fmt::dt($sel['created_at'])) ?><?= $sel['creator'] ? ' · ' . e($sel['creator']) : '' ?></p>
        <?php if ($sel['designer_note']): ?><div class="note"><strong><?= e(t('panel.designer_note')) ?>:</strong><br><?= nl2br(e($sel['designer_note'])) ?></div><?php endif; ?>
        <?php if ($sel['status'] === 'approved'): ?><p class="notice notice-ok"><?= e(t('preview.you_approved', ['n' => $sel['version_no'], 'd' => Fmt::dt($sel['approved_at'])])) ?></p><?php endif; ?>
      </section>
    <?php endif; ?>
    <?php if ($approved && (!$sel || $approved['id'] !== $sel['id'])): ?>
      <p class="notice"><?= e(t('preview.approved_is', ['n' => $approved['version_no']])) ?></p>
    <?php endif; ?>

    <?php if ($isCustomer && $sel && $sel['status'] === 'sent' && $latest && $sel['id'] === $latest['id']): ?>
      <section class="card">
        <h2 class="h4"><?= e(t('approve.title')) ?></h2>
        <p class="muted small"><?= e(t('approve.help', ['n' => $sel['version_no']])) ?></p>
        <form method="post" action="<?= e(url('/panel/orders/' . $no . '/approve')) ?>" data-once>
          <?= csrf_field() ?><input type="hidden" name="version_id" value="<?= (int)$sel['id'] ?>">
          <button class="btn btn-primary btn-block" type="submit" data-confirm="<?= e(t('approve.confirm', ['n' => $sel['version_no']])) ?>"><?= e(t('approve.button', ['n' => $sel['version_no']])) ?></button>
        </form>
      </section>
      <section class="card">
        <h2 class="h4"><?= e(t('revision.title')) ?></h2>
        <p class="muted small"><?= e(t('designer.rev_left', ['n' => $left, 'total' => (int)$order['revisions_allowed']])) ?></p>
        <?php if ($left > 0): ?>
        <form method="post" action="<?= e(url('/panel/orders/' . $no . '/revision')) ?>" data-once data-keep class="form">
          <?= csrf_field() ?><input type="hidden" name="version_id" value="<?= (int)$sel['id'] ?>">
          <label class="field"><span><?= e(t('revision.what')) ?></span><textarea name="message" rows="5" required maxlength="4000"></textarea></label>
          <button class="btn btn-ghost btn-block" type="submit"><?= e(t('revision.send')) ?></button>
        </form>
        <?php else: ?><p><?= e(t('revision.err.none_left')) ?></p><?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($isStaff): ?>
      <section class="card">
        <h2 class="h4"><?= e(t('version.send_title')) ?></h2>
        <p class="muted small"><?= e(t('designer.rev_left', ['n' => $left, 'total' => (int)$order['revisions_allowed']])) ?> · <?= e(loc($order['snapshot']['package']['name'])) ?></p>
        <ul class="ticks small"><?php foreach (($order['snapshot']['package']['scope'][$lang] ?? []) as $line): ?><li><?= e($line) ?></li><?php endforeach; ?></ul>
        <form method="post" action="<?= e(url('/panel/orders/' . $no . '/send-version')) ?>" class="form" data-once>
          <?= csrf_field() ?>
          <label class="field"><span><?= e(t('version.note')) ?></span><textarea name="note" rows="3" maxlength="3000"></textarea></label>
          <button class="btn btn-primary btn-block" type="submit"><?= e(t('version.send')) ?></button>
          <p class="muted small"><?= e(t('version.send_help')) ?></p>
        </form>
      </section>
      <section class="card">
        <h2 class="h4"><?= e(t('version.theme')) ?></h2>
        <form method="post" action="<?= e(url('/panel/orders/' . $no . '/theme')) ?>" class="form">
          <?= csrf_field() ?>
          <select name="theme_id"><?php foreach ($themes as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$t['id'] === (int)$draft['theme_id'] ? 'selected' : '' ?>><?= e(loc($t['name'])) ?></option><?php endforeach; ?></select>
          <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('common.save')) ?></button>
        </form>
      </section>
    <?php endif; ?>

    <?php if ($revisions): ?>
      <section class="card">
        <h2 class="h4"><?= e(t('revision.history')) ?></h2>
        <ul class="timeline"><?php foreach ($revisions as $r): ?><li><strong>v<?= (int)$r['version_no'] ?></strong> · <?= e(Fmt::dt($r['created_at'])) ?> · <?= e($r['author']) ?><br><?= nl2br(e($r['message'])) ?></li><?php endforeach; ?></ul>
      </section>
    <?php endif; ?>

    <section class="card" id="links">
      <h2 class="h4"><?= e(t('preview.links_title')) ?></h2>
      <p class="muted small"><?= e(t('preview.links_help')) ?></p>
      <?php if ($newToken): ?><div class="notice notice-ok copy-row"><input readonly value="<?= e($newToken) ?>" aria-label="<?= e(t('preview.links_title')) ?>"><button type="button" class="btn btn-xs" data-copy="<?= e($newToken) ?>"><?= e(t('common.copy')) ?></button></div><?php endif; ?>
      <form method="post" action="<?= e(url('/panel/orders/' . $no . '/preview-link')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <select name="version_id"><?php if ($isStaff): ?><option value="0"><?= e(t('preview.draft_label')) ?></option><?php endif; ?><?php foreach ($versions as $v): ?><option value="<?= (int)$v['id'] ?>">v<?= (int)$v['version_no'] ?></option><?php endforeach; ?></select>
        <select name="days"><?php foreach ([1, 3, 7, 14, 30] as $d): ?><option value="<?= $d ?>" <?= $d === 7 ? 'selected' : '' ?>><?= e(t('preview.days', ['n' => $d])) ?></option><?php endforeach; ?></select>
        <button class="btn btn-xs btn-ghost" type="submit" <?= (!$versions && !$isStaff) ? 'disabled' : '' ?>><?= e(t('preview.create_link')) ?></button>
      </form>
      <?php if ($links): ?>
        <ul class="link-list small">
          <?php foreach ($links as $l): $active = !$l['revoked_at'] && $l['expires_at'] > \App\DB::now(); ?>
            <li><?= $l['version_id'] ? 'v' . e((string)(array_column($versions, 'version_no', 'id')[$l['version_id']] ?? '?')) : e(t('preview.draft_label')) ?> · <?= $active ? e(t('preview.until', ['d' => Fmt::dt($l['expires_at'])])) : '<span class="muted">' . e($l['revoked_at'] ? t('preview.revoked') : t('preview.expired')) . '</span>' ?>
              <?php if ($active): ?><form method="post" action="<?= e(url('/panel/orders/' . $no . '/preview-link/revoke')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-xs btn-danger"><?= e(t('preview.revoke')) ?></button></form><?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</div>
