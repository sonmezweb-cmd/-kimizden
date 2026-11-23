<?php
use App\Content;
use App\Fmt;
use App\I18n;
use App\Ics;
use App\View;
$c = $content;
$L = $glang;
[$na, $nb] = $names;
$text = fn(string $f) => Content::text($c, $L, $f);
$font = $theme['config']['font'] ?? 'greatvibes';
$slugQ = !empty($slug) ? $slug : null;
$inviteBase = $slugQ ? url_raw('/i/' . $slugQ) : null;
$langLinks = count($c['languages']) > 1;
$demo = $mode === 'showcase';
$preview = $mode === 'preview';
$ic = fn(string $n) => View::partial('partials/icon', ['name' => $n]);
$phone = trim((string)($c['contact']['phone'] ?? ''));
$phoneDigits = preg_replace('/\D/', '', $phone);
$engineJson = jenc($engine + ['strings' => [
    'hint' => t('inv.scroll_hint'), 'open' => t('inv.open'), 'skip' => t('inv.skip'),
    'soundOn' => t('inv.sound_on'), 'soundOff' => t('inv.sound_off'),
]]);
?>
<?php if ($demo || $preview): ?>
  <div class="inv-banner" role="note">
    <?= $demo ? e(t('showcase.banner')) : e($previewLabel ?? t('preview.title')) ?>
    <?php if ($demo && !empty($backUrl)): ?> · <a href="<?= e($backUrl) ?>"><?= e(t('showcase.back')) ?></a><?php endif; ?>
  </div>
<?php endif; ?>

<section class="env" id="env" aria-label="<?= e(t('inv.envelope')) ?>">
  <script type="application/json" id="env-data"><?= str_replace('</', '<\/', $engineJson) ?></script>
  <div class="env-sticky">
    <div class="env-stage" id="env-stage">
      <img class="env-poster" src="<?= e($theme['media']['poster']) ?>" alt="" width="1080" height="1920" fetchpriority="high" decoding="async">
      <div class="env-layers" id="env-layers" aria-hidden="true"></div>
      <div class="env-fill" aria-hidden="true"></div>
      <button type="button" class="env-seal" id="env-seal" aria-label="<?= e(t('inv.open_seal')) ?>">
        <img src="<?= e($theme['media']['seal']) ?>" alt="" draggable="false">
        <span class="env-seal-ring" aria-hidden="true"></span>
      </button>
      <div class="env-text" id="env-text">
        <h1 class="env-names font-<?= e($font) ?>" id="inv-title">
          <span class="n1"><?= e($na) ?></span>
          <?php if ($na !== '' && $nb !== ''): ?><span class="amp" aria-hidden="true">&amp;</span><span class="sr-only"> <?= e(t('inv.and')) ?> </span><?php endif; ?>
          <span class="n2"><?= e($nb) ?></span>
        </h1>
        <?php if ($cover): ?><p class="env-date"><time datetime="<?= e($cover['start_utc']) ?>"><?= e(Fmt::dateCover($cover['start_utc'], $cover['tz'], $L)) ?></time></p><?php endif; ?>
      </div>
      <div class="env-hint" id="env-hint">
        <button type="button" class="env-hint-btn" data-env-open><?= e(t('inv.scroll_hint')) ?> <?= $ic('down') ?></button>
      </div>
      <div class="env-controls">
        <?php if ($engine['audio']): ?>
          <button type="button" class="env-ctl" id="env-sound" aria-pressed="false" hidden><?= $ic('sound') ?><span><?= e(t('inv.sound_on')) ?></span></button>
        <?php endif; ?>
        <button type="button" class="env-ctl" id="env-skip" hidden><?= $ic('skip') ?><span><?= e(t('inv.skip')) ?></span></button>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($story)): ?><?= View::partial('invite/_story', ['story' => $story, 'c' => $c, 'L' => $L, 'na' => $na, 'nb' => $nb, 'font' => $font, 'cover' => $cover, 'events' => $events]) ?><?php endif; ?>

<main class="letter" id="letter">
  <div class="letter-paper">
    <div id="names-flow-slot"></div>
    <?php if ($langLinks && !$preview): ?>
      <nav class="inv-langs" aria-label="<?= e(t('nav.language')) ?>">
        <?php foreach ($c['languages'] as $l): ?>
          <a href="?l=<?= $l ?>" lang="<?= $l ?>" hreflang="<?= $l ?>" <?= $l === $L ? 'aria-current="true"' : '' ?>><?= e(I18n::NAMES[$l]) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <?php if (!empty($group)): ?>
      <p class="greeting reveal"><?= e(t('inv.dear', ['name' => $group['name']])) ?></p>
    <?php endif; ?>

    <?php if ($text('intro') !== ''): ?><p class="intro reveal"><?= nl2br(e($text('intro'))) ?></p><?php endif; ?>
    <?php if ($text('letter') !== ''): ?>
      <div class="letter-body reveal"><?= nl_paras($text('letter')) ?></div>
    <?php endif; ?>

    <?php if ($cover && !empty($cover['start_utc'])): ?>
      <section class="countdown reveal" aria-label="<?= e(t('inv.countdown')) ?>" data-countdown="<?= e($cover['start_utc']) ?>">
        <p class="kicker"><?= e(t('inv.countdown')) ?></p>
        <div class="cd-grid" aria-live="off">
          <?php foreach (['d' => 'inv.days', 'h' => 'inv.hours', 'm' => 'inv.minutes', 's' => 'inv.seconds'] as $u => $k): ?>
            <div class="cd-cell"><strong data-cd="<?= $u ?>">–</strong><span><?= e(t($k)) ?></span></div>
          <?php endforeach; ?>
        </div>
        <p class="cd-done" hidden><?= e(t('inv.countdown_done')) ?></p>
      </section>
    <?php endif; ?>

    <?php if ($text('quote') !== ''): ?>
      <figure class="quote reveal">
        <span class="quote-mark" aria-hidden="true">“</span>
        <blockquote><?= nl2br(e($text('quote'))) ?></blockquote>
        <?php if ($text('quote_by') !== ''): ?><figcaption>— <?= e($text('quote_by')) ?></figcaption><?php endif; ?>
      </figure>
    <?php endif; ?>

    <?php if ($coverPhoto): ?>
      <figure class="cover-photo reveal"><img src="<?= e($coverPhoto['m']) ?>" srcset="<?= e($coverPhoto['m']) ?> 1080w, <?= e($coverPhoto['l']) ?> 1800w" sizes="(max-width: 700px) 92vw, 640px" alt="<?= e(trim($na . ' & ' . $nb, ' &')) ?>" loading="lazy" decoding="async"></figure>
    <?php endif; ?>

    <?php if ($events): ?>
      <section class="events" aria-labelledby="ev-h">
        <h2 class="sec-title reveal" id="ev-h"><?= e(t('inv.program')) ?></h2>
        <?php foreach ($events as $e): $title = Content::eventTitle($e, $L); $loc = trim($e['venue'] . ', ' . $e['address'], ', '); $dir = Ics::directionsUrl($e); ?>
          <article class="event reveal" id="ev-<?= e($e['id']) ?>">
            <p class="event-type"><?= e($title) ?></p>
            <p class="event-date"><time datetime="<?= e($e['start_utc']) ?>"><?= e(Fmt::dateLong($e['start_utc'], $e['tz'], $L)) ?></time></p>
            <p class="event-time"><?= e(Fmt::timeRange($e['start_utc'], $e['end_utc'], $e['tz'], $L)) ?> <small class="tz"><?= e(t('inv.local_time', ['tz' => Fmt::tzLabel($e['tz'], $e['start_utc'], $L)])) ?></small></p>
            <?php if ($e['venue'] !== ''): ?><h3 class="event-venue"><?= e($e['venue']) ?></h3><?php endif; ?>
            <?php if ($e['address'] !== ''): ?><address class="event-address"><?= nl2br(e($e['address'])) ?></address><?php endif; ?>
            <?php if (trim($e['desc'][$L] ?? '') !== ''): ?><p class="event-desc"><?= nl2br(e($e['desc'][$L])) ?></p><?php endif; ?>
            <?php if ($loc !== ''): ?>
              <div class="map-card" data-map="<?= e('https://www.google.com/maps?q=' . rawurlencode($loc) . '&output=embed') ?>">
                <a class="map-open" href="<?= e($dir) ?>" target="_blank" rel="noopener">
                  <span class="map-pin"><?= $ic('pin') ?></span>
                  <span><strong><?= e($e['venue'] ?: t('inv.location')) ?></strong><small><?= e(t('inv.tap_map')) ?></small></span>
                </a>
                <button type="button" class="map-show" data-map-show><?= e(t('inv.show_map')) ?></button>
              </div>
            <?php endif; ?>
            <div class="event-actions">
              <?php if ($loc !== ''): ?><a class="ibtn" href="<?= e($dir) ?>" target="_blank" rel="noopener"><?= $ic('pin') ?> <?= e(t('inv.directions')) ?></a><?php endif; ?>
              <?php if ($slugQ && $mode !== 'preview'): ?>
                <a class="ibtn" href="<?= e(url_raw('/i/' . $slugQ . '/ics/' . $e['id'], ['l' => $L])) ?>"><?= $ic('cal') ?> <?= e(t('inv.add_calendar')) ?></a>
              <?php endif; ?>
              <a class="ibtn ibtn-ghost" href="<?= e(Ics::googleUrl($e, $title . ' — ' . trim($na . ' & ' . $nb, ' &'), $loc, $inviteBase ?? '')) ?>" target="_blank" rel="noopener">Google <?= e(t('inv.calendar')) ?></a>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if ($gallery): ?>
      <section class="gallery" aria-labelledby="gal-h">
        <h2 class="sec-title reveal" id="gal-h"><?= e(t('inv.gallery')) ?></h2>
        <div class="gal-grid">
          <?php foreach ($gallery as $i => $g): ?>
            <a class="gal-item reveal" href="<?= e($g['l']) ?>" target="_blank" rel="noopener"><img src="<?= e($g['s']) ?>" srcset="<?= e($g['s']) ?> 480w, <?= e($g['m']) ?> 1080w" sizes="(max-width: 700px) 46vw, 300px" alt="<?= e(t('inv.photo_n', ['n' => $i + 1])) ?>" loading="lazy" decoding="async"></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php foreach (['dress' => 'inv.dress', 'transport' => 'inv.transport', 'stay' => 'inv.stay', 'notes' => 'inv.notes'] as $f => $k): if ($text($f) === '') continue; ?>
      <section class="info reveal"><h2 class="sec-title"><?= e(t($k)) ?></h2><?= nl_paras($text($f)) ?></section>
    <?php endforeach; ?>

    <?php if ($phone !== '' || trim($c['contact']['name'] ?? '') !== ''): ?>
      <section class="contact reveal" aria-labelledby="ct-h">
        <h2 class="sec-title" id="ct-h"><?= e(t('inv.contact')) ?></h2>
        <?php if (trim($c['contact']['name'] ?? '') !== ''): ?><p><?= e($c['contact']['name']) ?></p><?php endif; ?>
        <?php if ($phone !== ''): ?>
          <div class="contact-actions">
            <a class="ibtn" href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= $ic('user') ?> <?= e(t('inv.call')) ?></a>
            <a class="ibtn ibtn-wa" href="https://wa.me/<?= e($phoneDigits) ?>" target="_blank" rel="noopener"><?= $ic('whatsapp') ?> WhatsApp</a>
          </div>
          <p class="muted"><?= e($phone) ?></p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if (!empty($c['rsvp']['enabled'])): ?>
      <section class="rsvp reveal" id="rsvp" aria-labelledby="rsvp-h">
        <h2 class="sec-title" id="rsvp-h"><?= e(t('inv.rsvp')) ?></h2>
        <?php foreach (flashes() as $fl): ?><p class="rsvp-flash rsvp-<?= e($fl['type']) ?>" role="status"><?= e($fl['msg']) ?></p><?php endforeach; ?>
        <?php if (!empty($c['rsvp']['deadline'])): ?>
          <p class="muted"><?= e(t('inv.rsvp_by', ['d' => Fmt::dateCover($c['rsvp']['deadline'] . 'T12:00:00Z', 'UTC', $L)])) ?></p>
        <?php endif; ?>
        <?php if ($deadlinePassed): ?>
          <p><?= e(t('rsvp.deadline_passed')) ?></p>
        <?php elseif ($demo || $preview): ?>
          <p class="muted"><?= e(t('inv.rsvp_preview')) ?></p>
          <?= View::partial('invite/_rsvp_form', ['c' => $c, 'L' => $L, 'group' => $group ?? null, 'my' => null, 'action' => '#', 'disabled' => true]) ?>
        <?php else: ?>
          <?php if (!empty($myRsvp)): ?>
            <p class="rsvp-current"><?= e((int)$myRsvp['attending'] ? t('inv.rsvp_current_yes', ['n' => (int)$myRsvp['adults'] + (int)$myRsvp['children']]) : t('inv.rsvp_current_no')) ?> <?= e(t('inv.rsvp_can_change')) ?></p>
          <?php endif; ?>
          <?= View::partial('invite/_rsvp_form', ['c' => $c, 'L' => $L, 'group' => $group ?? null, 'my' => $myRsvp ?? null, 'action' => url_raw('/i/' . $slugQ . '/rsvp'), 'disabled' => false]) ?>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <footer class="inv-foot">
      <span class="inv-foot-names font-<?= e($font) ?>"><?= e($na) ?><?= $na !== '' && $nb !== '' ? ' & ' : '' ?><?= e($nb) ?></span>
      <a href="<?= e(url('/', $L)) ?>" class="inv-brand"><img src="<?= e(\App\App::baseUrl()) ?>/assets/img/logo-360.webp" width="120" height="40" alt="İkimizden — dijital davetiye" loading="lazy"></a>
    </footer>
  </div>
</main>
