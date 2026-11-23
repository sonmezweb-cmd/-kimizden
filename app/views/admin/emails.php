<?php use App\Fmt; ?>
<h1 class="h2"><?= e(t('admin.emails')) ?></h1>
<?php if (!$smtp): ?><div class="notice"><?= e(t('smtp.not_configured')) ?> <a href="<?= e(url('/admin/settings/smtp')) ?>"><?= e(t('admin.settings.smtp')) ?></a></div><?php endif; ?>
<p class="muted"><?= e(t('admin.emails_help')) ?> <a href="<?= e(url('/admin/translations', null, ['p' => 'mail'])) ?>"><?= e(t('admin.edit_templates')) ?></a></p>
<div class="filters">
  <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/emails')) ?>"><?= e(t('admin.all')) ?></a>
  <?php foreach (['pending', 'sent', 'failed'] as $s): ?><a class="btn btn-sm <?= $st === $s ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('/admin/emails', null, ['st' => $s])) ?>"><?= e(t('mailst.' . $s)) ?></a><?php endforeach; ?>
  <form method="post" action="<?= e(url('/admin/emails')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="process"><button class="btn btn-sm"><?= e(t('admin.process_queue')) ?></button></form>
</div>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(t('admin.to')) ?></th><th><?= e(t('admin.subject')) ?></th><th><?= e(t('status.title')) ?></th><th><?= e(t('admin.attempts')) ?></th><th><?= e(t('admin.created')) ?></th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?><tr>
    <td><?= e($r['to_email']) ?><br><span class="muted small"><?= e($r['template']) ?> · <?= strtoupper(e($r['lang'])) ?></span></td>
    <td><?= e($r['subject']) ?></td>
    <td><span class="badge <?= $r['status'] === 'sent' ? 'badge-ok' : ($r['status'] === 'failed' ? 'badge-warn' : '') ?>"><?= e(t('mailst.' . $r['status'])) ?></span><?= $r['last_error'] ? '<br><small class="err">' . e($r['last_error']) . '</small>' : '' ?></td>
    <td><?= (int)$r['attempts'] ?></td><td class="small"><?= e(Fmt::dt($r['created_at'])) ?></td>
    <td><?php if ($r['status'] !== 'sent'): ?><form method="post" action="<?= e(url('/admin/emails')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="retry"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-xs"><?= e(t('common.retry')) ?></button></form><?php endif; ?></td>
  </tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted">—</td></tr><?php endif; ?></tbody>
</table></div>
