<?php use App\Fmt; use App\I18n; ?>
<h1 class="h2"><?= e(t('admin.users')) ?></h1>
<nav class="tabs"><?php foreach (['customer', 'designer', 'admin'] as $r): ?><a href="<?= e(url('/admin/users', null, ['role' => $r])) ?>" <?= $role === $r ? 'aria-current="page"' : '' ?>><?= e(t('role.' . $r . 's')) ?></a><?php endforeach; ?></nav>
<?php if ($role !== 'customer'): ?>
<details class="card"><summary><?= e(t('admin.new_user', ['role' => t('role.' . $role)])) ?></summary>
  <form method="post" action="<?= e(url('/admin/users')) ?>" class="form" style="margin-top:12px">
    <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="role" value="<?= e($role) ?>">
    <div class="grid-2">
      <label class="field"><span><?= e(t('form.your_name')) ?></span><input name="name" required maxlength="120"></label>
      <label class="field"><span><?= e(t('form.email')) ?></span><input type="email" name="email" required></label>
      <label class="field"><span><?= e(t('form.password')) ?></span><input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
      <label class="field"><span><?= e(t('account.email_lang')) ?></span><select name="locale"><?php foreach (I18n::LANGS as $l): ?><option value="<?= $l ?>"><?= e(I18n::NAMES[$l]) ?></option><?php endforeach; ?></select></label>
    </div>
    <button class="btn btn-primary btn-sm"><?= e(t('common.create')) ?></button>
  </form>
</details>
<?php endif; ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(t('form.your_name')) ?></th><th><?= e(t('form.email')) ?></th><th><?= e($role === 'designer' ? t('admin.assigned') : t('admin.orders')) ?></th><th><?= e(t('admin.last_login')) ?></th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $u): ?><tr>
    <td><?= e($u['name']) ?><?= (int)$u['is_active'] ? '' : ' <span class="badge badge-warn">' . e(t('admin.inactive')) . '</span>' ?></td><td><?= e($u['email']) ?></td>
    <td><?= (int)($role === 'designer' ? $u['assigned_count'] : $u['orders_count']) ?></td><td class="small"><?= e(Fmt::dt($u['last_login_at'])) ?></td>
    <td><form method="post" action="<?= e(url('/admin/users')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="role" value="<?= e($role) ?>"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-xs btn-ghost"><?= (int)$u['is_active'] ? e(t('admin.deactivate')) : e(t('admin.activate')) ?></button></form></td>
  </tr><?php endforeach; ?></tbody>
</table></div>
