<?php
use App\Settings;
$errors = $errors ?? [];
$old = $old ?? [];
$email = Settings::get('company.email');
$phone = Settings::get('company.phone');
$wa = Settings::get('company.whatsapp');
$hours = Settings::get('company.hours');
?>
<section class="section">
  <div class="wrap narrow">
    <h1 class="h1"><?= e(t('contact.title')) ?></h1>
    <?php if ($email || $phone || $wa): ?>
      <ul class="contact-list">
        <?php if ($email): ?><li><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
        <?php if ($phone): ?><li><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
        <?php if ($wa): ?><li><a href="https://wa.me/<?= e(preg_replace('/\D/', '', $wa)) ?>" rel="noopener">WhatsApp</a></li><?php endif; ?>
        <?php if ($hours): ?><li class="muted"><?= e($hours) ?></li><?php endif; ?>
      </ul>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/contact')) ?>" class="card form" data-keep novalidate>
      <?= csrf_field() ?>
      <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
      <label class="field"><span><?= e(t('form.your_name')) ?></span><input name="name" required maxlength="120" value="<?= e($old['name'] ?? '') ?>" autocomplete="name">
        <?php if (isset($errors['name'])): ?><small class="err"><?= e($errors['name']) ?></small><?php endif; ?></label>
      <label class="field"><span><?= e(t('form.email')) ?></span><input type="email" name="email" required maxlength="190" value="<?= e($old['email'] ?? '') ?>" autocomplete="email">
        <?php if (isset($errors['email'])): ?><small class="err"><?= e($errors['email']) ?></small><?php endif; ?></label>
      <label class="field"><span><?= e(t('contact.message')) ?></span><textarea name="message" rows="6" required maxlength="4000"><?= e($old['msg'] ?? '') ?></textarea>
        <?php if (isset($errors['message'])): ?><small class="err"><?= e($errors['message']) ?></small><?php endif; ?></label>
      <button class="btn btn-primary" type="submit"><?= e(t('contact.send')) ?></button>
    </form>
  </div>
</section>
