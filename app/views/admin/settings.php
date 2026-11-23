<?php
use App\Fmt;
use App\I18n;
use App\Settings;
use App\Controllers\AdminController;
?>
<h1 class="h2"><?= e(t('admin.settings.' . $section)) ?></h1>
<nav class="tabs"><?php foreach (['general', 'payments', 'smtp', 'legal'] as $s): ?><a href="<?= e(url('/admin/settings/' . $s)) ?>" <?= $section === $s ? 'aria-current="page"' : '' ?>><?= e(t('admin.settings.' . $s)) ?></a><?php endforeach; ?></nav>

<?php if ($section === 'general'): ?>
<form method="post" action="<?= e(url('/admin/settings/general')) ?>" class="card form" data-keep>
  <?= csrf_field() ?>
  <p class="muted"><?= e(t('settings.general_help')) ?></p>
  <div class="grid-2">
  <?php foreach (AdminController::GENERAL_KEYS as $k): $n = str_replace('.', '_', $k); ?>
    <label class="field"><span><?= e(t('settings.' . $k)) ?></span>
      <?php if ($k === 'company.address'): ?><textarea name="<?= $n ?>" rows="3"><?= e((string)Settings::get($k, '')) ?></textarea>
      <?php else: ?><input name="<?= $n ?>" value="<?= e((string)Settings::get($k, '')) ?>"><?php endif; ?></label>
  <?php endforeach; ?>
  </div>
  <button class="btn btn-primary"><?= e(t('common.save')) ?></button>
</form>

<?php elseif ($section === 'payments'): $p = $paytr; ?>
<div class="card notice-info">
  <h2 class="h4"><?= e(t('paytr.callback_title')) ?></h2>
  <p><?= e(t('paytr.callback_help')) ?></p>
  <div class="copy-row"><input readonly value="<?= e($p['callback']) ?>" aria-label="<?= e(t('paytr.callback_title')) ?>"><button type="button" class="btn btn-sm" data-copy="<?= e($p['callback']) ?>"><?= e(t('common.copy')) ?></button></div>
  <?php if (!str_starts_with($p['callback'], 'https://')): ?><p class="err small"><?= e(t('paytr.https_warning')) ?></p><?php endif; ?>
</div>
<form method="post" action="<?= e(url('/admin/settings/payments')) ?>" class="card form" autocomplete="off">
  <?= csrf_field() ?>
  <h2 class="h4">PayTR iFrame API</h2>
  <label class="field"><span><?= e(t('paytr.merchant_id')) ?> · merchant_id</span><input name="merchant_id" inputmode="numeric" value="<?= e($p['merchant_id']) ?>" autocomplete="off"></label>
  <label class="field"><span><?= e(t('paytr.merchant_key')) ?> · merchant_key</span>
    <input type="password" name="merchant_key" value="" autocomplete="new-password" placeholder="<?= $p['has_key'] ? e(t('paytr.saved_keep')) : '' ?>">
    <small class="secret-state"><?= $p['has_key'] ? '✓ ' . e(t('paytr.secret_saved')) : '— ' . e(t('paytr.secret_missing')) ?></small>
    <?php if ($p['has_key']): ?><label class="check small"><input type="checkbox" name="clear_merchant_key" value="1"> <?= e(t('paytr.clear')) ?></label><?php endif; ?></label>
  <label class="field"><span><?= e(t('paytr.merchant_salt')) ?> · merchant_salt</span>
    <input type="password" name="merchant_salt" value="" autocomplete="new-password" placeholder="<?= $p['has_salt'] ? e(t('paytr.saved_keep')) : '' ?>">
    <small class="secret-state"><?= $p['has_salt'] ? '✓ ' . e(t('paytr.secret_saved')) : '— ' . e(t('paytr.secret_missing')) ?></small>
    <?php if ($p['has_salt']): ?><label class="check small"><input type="checkbox" name="clear_merchant_salt" value="1"> <?= e(t('paytr.clear')) ?></label><?php endif; ?></label>
  <label class="check"><input type="checkbox" name="enabled" value="1" <?= $p['enabled'] ? 'checked' : '' ?>> <?= e(t('paytr.enable')) ?></label>
  <fieldset class="radios"><legend><?= e(t('paytr.mode')) ?></legend>
    <label class="check"><input type="radio" name="mode" value="test" <?= $p['mode'] === 'test' ? 'checked' : '' ?>> <?= e(t('paytr.mode.test')) ?></label>
    <label class="check"><input type="radio" name="mode" value="live" <?= $p['mode'] === 'live' ? 'checked' : '' ?>> <?= e(t('paytr.mode.live')) ?></label></fieldset>
  <label class="check"><input type="checkbox" name="installments" value="1" <?= !$p['no_installment'] ? 'checked' : '' ?>> <?= e(t('paytr.installments')) ?></label>
  <label class="field inline-field"><span><?= e(t('paytr.max_installment')) ?></span><input type="number" name="max_installment" min="0" max="12" value="<?= (int)$p['max_installment'] ?>"><small class="muted"><?= e(t('paytr.max_installment_help')) ?></small></label>
  <p class="muted small"><?= e(t('paytr.secrets_note')) ?></p>
  <button class="btn btn-primary"><?= e(t('common.save')) ?></button>
</form>
<section class="card">
  <h2 class="h4"><?= e(t('paytr.verification')) ?></h2>
  <p class="muted small"><?= e(t('paytr.verification_help')) ?></p>
  <dl class="kv">
    <dt><?= e(t('paytr.last_test')) ?></dt><dd><?= $p['last_test'] ? e(Fmt::dt($p['last_test'])) : e(t('paytr.never')) ?></dd>
    <dt><?= e(t('paytr.last_live')) ?></dt><dd><?= $p['last_live'] ? e(Fmt::dt($p['last_live'])) : e(t('paytr.never')) ?></dd>
  </dl>
  <?php if ($p['recent']): ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>oid</th><th>status</th><th><?= e(t('paytr.mode')) ?></th><th><?= e(t('admin.signature')) ?></th><th><?= e(t('admin.outcome')) ?></th><th><?= e(t('admin.created')) ?></th></tr></thead>
  <tbody><?php foreach ($p['recent'] as $r): ?><tr><td class="small"><code><?= e((string)$r['merchant_oid']) ?></code></td><td><?= e((string)$r['status']) ?></td><td><?= e((string)$r['mode']) ?></td><td><?= (int)$r['hash_valid'] ? '✓' : '✗' ?></td><td><?= e($r['outcome']) ?></td><td class="small"><?= e(Fmt::dt($r['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</section>

<?php elseif ($section === 'smtp'): ?>
<form method="post" action="<?= e(url('/admin/settings/smtp')) ?>" class="card form" autocomplete="off">
  <?= csrf_field() ?>
  <div class="grid-2">
    <label class="field"><span><?= e(t('smtp.host')) ?></span><input name="host" value="<?= e((string)Settings::get('smtp.host', '')) ?>" placeholder="mail.alanadiniz.com"></label>
    <label class="field"><span><?= e(t('smtp.port')) ?></span><input name="port" inputmode="numeric" value="<?= e((string)Settings::get('smtp.port', '587')) ?>"></label>
    <label class="field"><span><?= e(t('smtp.encryption')) ?></span><select name="encryption"><?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => t('smtp.none')] as $k => $v): ?><option value="<?= $k ?>" <?= Settings::get('smtp.encryption', 'tls') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span><?= e(t('smtp.user')) ?></span><input name="user" value="<?= e((string)Settings::get('smtp.user', '')) ?>" autocomplete="off"></label>
    <label class="field"><span><?= e(t('smtp.pass')) ?></span><input type="password" name="pass" autocomplete="new-password" placeholder="<?= $has_pass ? e(t('paytr.saved_keep')) : '' ?>"><small class="secret-state"><?= $has_pass ? '✓ ' . e(t('paytr.secret_saved')) : '' ?></small></label>
    <label class="field"><span><?= e(t('smtp.from_email')) ?></span><input type="email" name="from_email" value="<?= e((string)Settings::get('smtp.from_email', '')) ?>"></label>
    <label class="field"><span><?= e(t('smtp.from_name')) ?></span><input name="from_name" value="<?= e((string)Settings::get('smtp.from_name', 'İkimizden')) ?>"></label>
  </div>
  <label class="field"><span><?= e(t('smtp.test_to')) ?></span><input type="email" name="test_to" placeholder="<?= e(t('smtp.test_ph')) ?>"></label>
  <button class="btn btn-primary"><?= e(t('smtp.save_test')) ?></button>
</form>

<?php else: /* legal */ ?>
<p class="muted"><?= e(t('settings.legal_help')) ?></p>
<form method="post" action="<?= e(url('/admin/settings/legal')) ?>" class="form" data-keep>
  <?= csrf_field() ?>
  <?php foreach (['privacy', 'kvkk', 'terms', 'cookies'] as $k): ?>
    <details class="card" <?= $k === 'privacy' ? 'open' : '' ?>><summary class="h4"><?= e(t('legal.' . $k)) ?></summary>
      <?php foreach (I18n::LANGS as $l): $pg = $pages[$k][$l] ?? null; ?>
        <div class="lang-block"><p class="lang-tag"><?= e(I18n::NAMES[$l]) ?></p>
          <label class="field"><span><?= e(t('admin.title')) ?></span><input name="page[<?= $k ?>][<?= $l ?>][title]" value="<?= e($pg['title'] ?? '') ?>" placeholder="<?= e(t('legal.' . $k, [], $l)) ?>"></label>
          <label class="field"><span><?= e(t('admin.body')) ?></span><textarea name="page[<?= $k ?>][<?= $l ?>][body]" rows="8"><?= e($pg['body'] ?? '') ?></textarea></label></div>
      <?php endforeach; ?>
    </details>
  <?php endforeach; ?>
  <button class="btn btn-primary"><?= e(t('common.save')) ?></button>
</form>
<?php endif; ?>
