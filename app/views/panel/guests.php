<?php
use App\View;
$no = $order['public_no'];
$base = url_raw('/i/' . $inv['slug'] . '/g/');
?>
<?= View::partial('partials/order_tabs', get_defined_vars()) ?>
<?php if (!$allowed): ?>
  <div class="card empty"><p><?= e(t('guests.not_in_package')) ?></p></div>
<?php else: ?>
<section class="card">
  <h2 class="h3"><?= e(t('guests.title')) ?></h2>
  <p class="muted"><?= e(t('guests.help')) ?></p>
  <form method="post" action="<?= e(url('/panel/orders/' . $no . '/guests')) ?>" class="form" data-keep>
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <label class="field"><span><?= e(t('guests.names')) ?></span><textarea name="names" rows="4" placeholder="<?= e(t('guests.names_ph')) ?>" required></textarea>
      <small class="muted"><?= e(t('guests.names_help')) ?></small></label>
    <label class="field inline-field"><span><?= e(t('guests.max')) ?></span><input type="number" name="max_guests" min="1" max="20" value="2"></label>
    <button class="btn btn-primary" type="submit"><?= e(t('guests.add')) ?></button>
  </form>
</section>
<section class="card">
  <h2 class="h4"><?= e(t('guests.list')) ?> (<?= count($groups) ?>)</h2>
  <?php if ($order['publish_status'] !== 'published'): ?><p class="notice"><?= e(t('guests.links_after_publish')) ?></p><?php endif; ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th><?= e(t('guests.group')) ?></th><th><?= e(t('guests.max')) ?></th><th><?= e(t('status.title')) ?></th><th><?= e(t('guests.private_link')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($groups as $g): $link = $base . $g['token']; ?>
      <tr>
        <td>
          <form method="post" action="<?= e(url('/panel/orders/' . $no . '/guests')) ?>" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
            <input name="name" value="<?= e($g['name']) ?>" maxlength="180" aria-label="<?= e(t('guests.group')) ?>">
            <input type="number" name="max_guests" value="<?= (int)$g['max_guests'] ?>" min="1" max="20" class="num-sm" aria-label="<?= e(t('guests.max')) ?>">
            <button class="btn btn-xs btn-ghost"><?= e(t('common.save')) ?></button>
          </form>
        </td>
        <td><?= (int)$g['max_guests'] ?></td>
        <td><?php if ($g['attending'] === null): ?><span class="badge"><?= e(t('rsvp.st.no_answer')) ?></span><?php elseif ((int)$g['attending']): ?><span class="badge badge-ok"><?= e(t('rsvp.st.coming')) ?> · <?= (int)$g['adults'] + (int)$g['children'] ?></span><?php else: ?><span class="badge badge-warn"><?= e(t('rsvp.st.not_coming')) ?></span><?php endif; ?></td>
        <td><div class="copy-row sm"><input readonly value="<?= e($link) ?>" aria-label="<?= e(t('guests.private_link')) ?>"><button type="button" class="btn btn-xs" data-copy="<?= e($link) ?>"><?= e(t('common.copy')) ?></button>
          <a class="btn btn-xs btn-ghost" href="https://wa.me/?text=<?= e(rawurlencode($link)) ?>" target="_blank" rel="noopener">WhatsApp</a></div></td>
        <td><form method="post" action="<?= e(url('/panel/orders/' . $no . '/guests')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="btn btn-xs btn-danger" data-confirm="<?= e(t('guests.delete_confirm')) ?>"><?= e(t('common.delete')) ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php endif; ?>
