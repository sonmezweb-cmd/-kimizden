<div class="bare-card wide">
  <img src="<?= e(\App\App::baseUrl()) ?>/assets/img/logo-360.webp" width="180" height="60" alt="İkimizden">
  <h1 class="h2">Kurulum sihirbazı</h1>
  <p class="muted">Gereksinimler kontrol edilir, veritabanı tabloları oluşturulur, ilk yönetici ve şifreleme anahtarı üretilir. Kurulum bitince sihirbaz kilitlenir.</p>
  <h2 class="h4">Sunucu kontrolleri</h2>
  <ul class="checks">
    <?php foreach ($checks as [$label, $ok]): ?>
      <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✗' ?> <?= e($label) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($errors): ?><div class="flash flash-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" class="form" autocomplete="off">
    <?= csrf_field() ?>
    <fieldset><legend class="h4">Site adresi</legend>
      <label class="field"><span>Temel URL (alt dizin dahil, sonda / olmadan)</span><input name="base_url" required value="<?= e($old['base_url'] ?? '') ?>" placeholder="https://alanadiniz.com/ikimizden"></label>
      <label class="check"><input type="checkbox" name="trust_proxy" value="1" <?= !empty($old['trust_proxy']) ? 'checked' : '' ?>> Sunucu Cloudflare/vekil arkasında (X-Forwarded-For güvenilir)</label>
    </fieldset>
    <fieldset><legend class="h4">MySQL / MariaDB</legend>
      <div class="grid-2">
        <label class="field"><span>Sunucu</span><input name="db_host" required value="<?= e($old['db_host'] ?? 'localhost') ?>"></label>
        <label class="field"><span>Port</span><input name="db_port" inputmode="numeric" value="<?= e($old['db_port'] ?? '3306') ?>"></label>
        <label class="field"><span>Veritabanı adı</span><input name="db_name" required value="<?= e($old['db_name'] ?? '') ?>"></label>
        <label class="field"><span>Kullanıcı</span><input name="db_user" required value="<?= e($old['db_user'] ?? '') ?>"></label>
      </div>
      <label class="field"><span>Parola</span><input type="password" name="db_pass"></label>
    </fieldset>
    <fieldset><legend class="h4">İlk yönetici</legend>
      <label class="field"><span>Ad Soyad</span><input name="admin_name" required value="<?= e($old['admin_name'] ?? '') ?>"></label>
      <label class="field"><span>E-posta</span><input type="email" name="admin_email" required value="<?= e($old['admin_email'] ?? '') ?>"></label>
      <label class="field"><span>Parola (en az 10 karakter)</span><input type="password" name="admin_password" required minlength="10"></label>
    </fieldset>
    <button class="btn btn-primary" type="submit">Kurulumu tamamla</button>
  </form>
</div>
