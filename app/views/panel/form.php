<?php
use App\Auth;
use App\Content;
use App\Fmt;
use App\I18n;
use App\View;
$c = $content;
$no = $order['public_no'];
$action = url('/panel/orders/' . $no . '/form/' . $step);
$langs = $c['languages'];
$maxLangs = (int)($order['snapshot']['package']['max_languages'] ?? 3);
$conf = function (string $path) use ($conflicts) {
    if (!array_key_exists($path, $conflicts)) return '';
    $v = $conflicts[$path];
    $txt = is_array($v) ? t('save.conflict_complex') : (string)$v;
    return '<div class="conflict" role="alert"><strong>' . e(t('save.conflict_field')) . '</strong> <span class="conflict-val">' . e($txt === '' ? '—' : $txt) . '</span>'
        . (!is_array($v) ? ' <button type="button" class="btn btn-xs btn-ghost" data-use-theirs="' . e($path) . '" data-value="' . e($txt) . '">' . e(t('save.use_theirs')) . '</button>' : '') . '</div>';
};
$common = ['Europe/Istanbul', 'Europe/London', 'Europe/Berlin', 'Europe/Paris', 'Europe/Amsterdam', 'Europe/Rome', 'Europe/Madrid', 'Europe/Athens', 'Asia/Nicosia', 'Europe/Sofia',
    'Europe/Kyiv', 'Europe/Minsk', 'Europe/Moscow', 'Asia/Tbilisi', 'Asia/Baku', 'Asia/Yerevan', 'Asia/Almaty', 'Asia/Tashkent', 'Asia/Dubai', 'America/New_York', 'America/Chicago', 'America/Los_Angeles', 'UTC'];
$allTz = DateTimeZone::listIdentifiers();
$tzSelect = function (string $name, string $cur) use ($common, $allTz) {
    $h = '<select name="' . e($name) . '" required>';
    $h .= '<optgroup label="' . e(t('form.tz_common')) . '">';
    foreach ($common as $z) $h .= '<option value="' . e($z) . '"' . ($z === $cur ? ' selected' : '') . '>' . e(str_replace('_', ' ', $z)) . '</option>';
    $h .= '</optgroup><optgroup label="' . e(t('form.tz_all')) . '">';
    foreach ($allTz as $z) if (!in_array($z, $common, true)) $h .= '<option value="' . e($z) . '"' . ($z === $cur ? ' selected' : '') . '>' . e(str_replace('_', ' ', $z)) . '</option>';
    return $h . '</optgroup></select>';
};
?>
<?= View::partial('partials/order_tabs', get_defined_vars()) ?>

<ol class="stepper">
  <?php foreach ($steps as $i => $s): $miss = $s === 'review' ? 0 : 0; ?>
    <li><a href="<?= e(url('/panel/orders/' . $no . '/form/' . $s)) ?>" <?= $s === $step ? 'aria-current="step"' : '' ?>><span><?= $i + 1 ?></span> <?= e(t('form.step.' . $s)) ?></a></li>
  <?php endforeach; ?>
</ol>

<?php if ($conflicts): ?>
  <div class="flash flash-error" role="alert"><?= e(t('save.conflict_long')) ?></div>
<?php endif; ?>
<?php if (!$editable): ?><div class="notice"><?= e(t('form.archived')) ?></div><?php endif; ?>

<?php if ($step !== 'review'): ?>
<form method="post" action="<?= e($action) ?>" class="card form step-form" data-autosave="<?= e($action) ?>" data-keep id="step-form">
  <?= csrf_field() ?>
  <input type="hidden" name="_v" value="<?= (int)$draft['lock_version'] ?>" data-version>
  <fieldset <?= $editable ? '' : 'disabled' ?>>
  <legend class="h3"><?= e(t('form.step.' . $step)) ?></legend>

  <?php if ($step === 'names'): ?>
    <p class="muted"><?= e(t('form.names.help')) ?></p>
    <div class="grid-2">
      <label class="field"><span><?= e(t('form.names.first')) ?></span>
        <input name="names_first" maxlength="80" value="<?= e($c['names']['first']) ?>" autocomplete="off" spellcheck="false" data-name-preview="a"><?= $conf('names.first') ?></label>
      <label class="field"><span><?= e(t('form.names.second')) ?></span>
        <input name="names_second" maxlength="80" value="<?= e($c['names']['second']) ?>" autocomplete="off" spellcheck="false" data-name-preview="b"><?= $conf('names.second') ?></label>
    </div>
    <fieldset class="radios">
      <legend><?= e(t('form.names.order')) ?></legend>
      <label class="check"><input type="radio" name="names_order" value="ab" <?= $c['names']['order'] !== 'ba' ? 'checked' : '' ?>> <?= e(t('form.names.order_ab')) ?></label>
      <label class="check"><input type="radio" name="names_order" value="ba" <?= $c['names']['order'] === 'ba' ? 'checked' : '' ?>> <?= e(t('form.names.order_ba')) ?></label>
    </fieldset>
    <p class="name-preview" aria-live="polite"><span class="muted small"><?= e(t('form.names.preview')) ?>:</span> <span data-name-out><?php [$a, $b] = Content::names($c); echo e(trim($a . ' & ' . $b, ' &')); ?></span></p>
    <p class="muted small"><?= e(t('form.names.no_translate')) ?></p>

  <?php elseif ($step === 'languages'): ?>
    <p class="muted"><?= e(t('form.languages.help', ['n' => $maxLangs])) ?></p>
    <fieldset class="radios">
      <legend><?= e(t('form.languages.which')) ?></legend>
      <?php foreach (I18n::LANGS as $l): ?>
        <label class="check"><input type="checkbox" name="languages[]" value="<?= $l ?>" <?= in_array($l, $langs, true) ? 'checked' : '' ?> data-max="<?= $maxLangs ?>"> <?= e(I18n::NAMES[$l]) ?></label>
      <?php endforeach; ?>
      <?= $conf('languages') ?>
    </fieldset>
    <fieldset class="radios">
      <legend><?= e(t('form.languages.default')) ?></legend>
      <?php foreach (I18n::LANGS as $l): ?>
        <label class="check"><input type="radio" name="default_lang" value="<?= $l ?>" <?= $c['default_lang'] === $l ? 'checked' : '' ?>> <?= e(I18n::NAMES[$l]) ?></label>
      <?php endforeach; ?>
    </fieldset>
    <p class="muted small"><?= e(t('form.languages.ui_note')) ?></p>

  <?php elseif ($step === 'events'): ?>
    <p class="muted"><?= e(t('form.events.help')) ?></p>
    <?= $conf('events') ?>
    <?php if (!$c['events']): ?><p class="notice"><?= e(t('form.events.none')) ?></p><?php endif; ?>
    <?php foreach ($c['events'] as $i => $ev): ?>
      <fieldset class="event-row" id="event-<?= $i ?>">
        <legend class="h4"><?= e(t('form.events.event_n', ['n' => $i + 1])) ?> — <?= e(Content::eventTitle($ev, I18n::$lang)) ?></legend>
        <input type="hidden" name="events[<?= $i ?>][id]" value="<?= e($ev['id']) ?>">
        <div class="grid-3">
          <label class="field"><span><?= e(t('form.events.type')) ?></span>
            <select name="events[<?= $i ?>][type]"><?php foreach (Content::EVENT_TYPES as $ty): ?><option value="<?= $ty ?>" <?= $ev['type'] === $ty ? 'selected' : '' ?>><?= e(t('event.type.' . $ty)) ?></option><?php endforeach; ?></select></label>
          <label class="field"><span><?= e(t('form.events.date')) ?></span><input type="date" name="events[<?= $i ?>][date]" value="<?= e($ev['date']) ?>" required></label>
          <label class="field"><span><?= e(t('form.events.tz')) ?></span><?= $tzSelect("events[$i][tz]", $ev['tz']) ?></label>
          <label class="field"><span><?= e(t('form.events.start')) ?></span><input type="time" name="events[<?= $i ?>][start]" value="<?= e($ev['start']) ?>" required></label>
          <label class="field"><span><?= e(t('form.events.end')) ?> <small class="muted"><?= e(t('form.optional')) ?></small></span><input type="time" name="events[<?= $i ?>][end]" value="<?= e($ev['end']) ?>"></label>
          <div class="field computed">
            <span><?= e(t('form.events.local_time')) ?></span>
            <output><?= !empty($ev['start_utc']) ? e(Fmt::dateLong($ev['start_utc'], $ev['tz']) . ' · ' . Fmt::timeRange($ev['start_utc'], $ev['end_utc'], $ev['tz'])) : '—' ?></output>
          </div>
        </div>
        <label class="field"><span><?= e(t('form.events.venue')) ?></span><input name="events[<?= $i ?>][venue]" maxlength="150" value="<?= e($ev['venue']) ?>" required></label>
        <label class="field"><span><?= e(t('form.events.address')) ?></span><textarea name="events[<?= $i ?>][address]" rows="2" maxlength="400"><?= e($ev['address']) ?></textarea></label>
        <label class="field"><span><?= e(t('form.events.map_url')) ?> <small class="muted"><?= e(t('form.optional')) ?></small></span><input type="url" name="events[<?= $i ?>][map_url]" maxlength="500" value="<?= e($ev['map_url']) ?>" placeholder="https://maps.google.com/…">
          <small class="muted"><?= e(t('form.events.map_help')) ?></small></label>
        <?php foreach ($langs as $l): ?>
          <div class="lang-block" lang="<?= $l ?>">
            <p class="lang-tag"><?= e(I18n::NAMES[$l]) ?></p>
            <label class="field"><span><?= e(t('form.events.title')) ?> <small class="muted"><?= $ev['type'] === 'other' ? e(t('form.required')) : e(t('form.events.title_default', ['t' => t('event.type.' . $ev['type'], [], $l)])) ?></small></span>
              <input name="events[<?= $i ?>][title][<?= $l ?>]" maxlength="120" value="<?= e($ev['title'][$l] ?? '') ?>"></label>
            <label class="field"><span><?= e(t('form.events.desc')) ?> <small class="muted"><?= e(t('form.optional')) ?></small></span>
              <textarea name="events[<?= $i ?>][desc][<?= $l ?>]" rows="2" maxlength="600"><?= e($ev['desc'][$l] ?? '') ?></textarea></label>
          </div>
        <?php endforeach; ?>
        <?php foreach (array_diff(I18n::LANGS, $langs) as $l): ?>
          <input type="hidden" name="events[<?= $i ?>][title][<?= $l ?>]" value="<?= e($ev['title'][$l] ?? '') ?>">
          <input type="hidden" name="events[<?= $i ?>][desc][<?= $l ?>]" value="<?= e($ev['desc'][$l] ?? '') ?>">
        <?php endforeach; ?>
        <div class="row-actions">
          <?php if ($i > 0): ?><button class="btn btn-xs btn-ghost" name="_move" value="<?= $i ?>:up"><?= e(t('common.move_up')) ?></button><?php endif; ?>
          <?php if ($i < count($c['events']) - 1): ?><button class="btn btn-xs btn-ghost" name="_move" value="<?= $i ?>:down"><?= e(t('common.move_down')) ?></button><?php endif; ?>
          <label class="check danger"><input type="checkbox" name="events[<?= $i ?>][_remove]" value="1"> <?= e(t('form.events.remove')) ?></label>
        </div>
      </fieldset>
    <?php endforeach; ?>
    <?php if (count($c['events']) < Content::MAX_EVENTS): ?>
      <button class="btn btn-ghost" type="submit" name="_add_event" value="1">+ <?= e(t('form.events.add')) ?></button>
    <?php endif; ?>

  <?php elseif ($step === 'texts'): ?>
    <p class="muted"><?= e(t('form.texts.help')) ?></p>
    <?php foreach ($langs as $l): ?>
      <fieldset class="lang-block" lang="<?= $l ?>">
        <legend class="h4"><?= e(I18n::NAMES[$l]) ?><?= $l === $c['default_lang'] ? ' · ' . e(t('form.languages.default_short')) : '' ?></legend>
        <?php foreach (Content::TEXT_FIELDS as $f => $max): ?>
          <label class="field"><span><?= e(t('form.texts.' . $f)) ?> <small class="muted"><?= $f === 'letter' ? e(t('form.required')) : e(t('form.optional')) ?></small></span>
            <textarea name="texts[<?= $l ?>][<?= $f ?>]" rows="<?= $f === 'letter' ? 7 : 3 ?>" maxlength="<?= $max ?>" data-count><?= e($c['texts'][$l][$f] ?? '') ?></textarea>
            <small class="muted"><?= e(t('form.texts.' . $f . '_help')) ?></small>
            <?= $conf("texts.$l.$f") ?></label>
        <?php endforeach; ?>
      </fieldset>
    <?php endforeach; ?>

  <?php elseif ($step === 'details'): ?>
    <label class="field"><span><?= e(t('form.details.cover_event')) ?></span>
      <select name="cover_event_id">
        <?php foreach ($c['events'] as $ev): ?><option value="<?= e($ev['id']) ?>" <?= $c['cover_event_id'] === $ev['id'] ? 'selected' : '' ?>><?= e(Content::eventTitle($ev, I18n::$lang)) ?><?= $ev['date'] ? ' — ' . e($ev['date']) : '' ?></option><?php endforeach; ?>
        <?php if (!$c['events']): ?><option value=""><?= e(t('form.events.none')) ?></option><?php endif; ?>
      </select><small class="muted"><?= e(t('form.details.cover_help')) ?></small></label>
    <div class="grid-2">
      <label class="field"><span><?= e(t('form.details.contact_name')) ?> <small class="muted"><?= e(t('form.optional')) ?></small></span><input name="contact_name" maxlength="120" value="<?= e($c['contact']['name']) ?>"><?= $conf('contact.name') ?></label>
      <label class="field"><span><?= e(t('form.details.contact_phone')) ?> <small class="muted"><?= e(t('form.optional')) ?></small></span><input type="tel" name="contact_phone" maxlength="40" value="<?= e($c['contact']['phone']) ?>"></label>
    </div>
    <fieldset>
      <legend class="h4"><?= e(t('form.details.rsvp')) ?></legend>
      <input type="hidden" name="rsvp_enabled" value="0"><label class="check"><input type="checkbox" name="rsvp_enabled" value="1" <?= !empty($c['rsvp']['enabled']) ? 'checked' : '' ?>> <?= e(t('form.details.rsvp_enabled')) ?></label>
      <input type="hidden" name="rsvp_ask_counts" value="0"><label class="check"><input type="checkbox" name="rsvp_ask_counts" value="1" <?= !empty($c['rsvp']['ask_counts']) ? 'checked' : '' ?>> <?= e(t('form.details.rsvp_counts')) ?></label>
      <div class="grid-2">
        <label class="field"><span><?= e(t('form.details.rsvp_deadline')) ?> <small class="muted"><?= e(t('form.optional')) ?></small></span><input type="date" name="rsvp_deadline" value="<?= e($c['rsvp']['deadline']) ?>"></label>
        <label class="field"><span><?= e(t('form.details.rsvp_max_public')) ?></span><input type="number" name="rsvp_max_public" min="1" max="10" value="<?= (int)$c['rsvp']['max_public'] ?>"></label>
      </div>
      <p class="muted small"><?= e(t('form.details.rsvp_help')) ?></p>
    </fieldset>
  <?php endif; ?>
  </fieldset>

  <div class="form-actions">
    <button class="btn btn-ghost" type="submit"><?= e(t('common.save')) ?></button>
    <button class="btn btn-primary" type="submit" name="_next" value="1"><?= e(t('form.save_next')) ?></button>
    <span class="muted small" data-autosave-hint><?= e(t('save.autosave_hint')) ?></span>
  </div>
</form>

<?php else: /* review */ ?>
<section class="card">
  <h2 class="h3"><?= e(t('form.step.review')) ?></h2>
  <?php $total = array_sum(array_map('count', $missing)); ?>
  <?php if ($total === 0): ?>
    <p class="notice notice-ok"><?= e(t('form.review.complete')) ?></p>
  <?php else: ?>
    <p><?= e(t('form.review.missing_intro')) ?></p>
    <ul class="missing">
      <?php foreach ($missing['global'] as $k): ?><li><a href="<?= e(url('/panel/orders/' . $no . '/form/' . (str_starts_with($k, 'form.names') ? 'names' : 'events'))) ?>"><?= e(t($k)) ?></a></li><?php endforeach; ?>
      <?php foreach ($langs as $l): foreach ($missing[$l] ?? [] as $k): ?>
        <li><a href="<?= e(url('/panel/orders/' . $no . '/form/' . (str_starts_with($k, 'form.texts') ? 'texts' : 'events'))) ?>"><?= e(I18n::NAMES[$l]) ?>: <?= e(t($k)) ?></a></li>
      <?php endforeach; endforeach; ?>
    </ul>
  <?php endif; ?>
  <p class="cta-row">
    <a class="btn btn-ghost" href="<?= e(url('/panel/orders/' . $no . '/render/draft')) ?>" target="_blank" rel="noopener"><?= e(t('form.review.preview_draft')) ?></a>
    <a class="btn btn-ghost" href="<?= e(url('/panel/orders/' . $no . '/photos')) ?>"><?= e(t('tab.photos')) ?> (<?= count($c['gallery']) ?>)</a>
  </p>
  <?php if (Auth::is('customer') && $order['design_status'] === 'info_pending'): ?>
    <form method="post" action="<?= e(url('/panel/orders/' . $no . '/submit-info')) ?>">
      <?= csrf_field() ?>
      <p class="muted"><?= e(t('form.submit_help')) ?></p>
      <button class="btn btn-primary" type="submit" <?= $missing['global'] ? 'disabled' : '' ?>><?= e(t('form.submit')) ?></button>
      <?php if ($missing['global']): ?><p class="muted small"><?= e(t('form.submit_blocked')) ?></p><?php endif; ?>
    </form>
  <?php elseif ($order['info_submitted_at']): ?>
    <p class="muted"><?= e(t('form.submitted_at', ['d' => Fmt::dt($order['info_submitted_at'])])) ?></p>
  <?php endif; ?>
</section>
<?php endif; ?>
