<?php
/*
 * Hikâye videosu: zarftan sonra kaydırmalı resimli sahne. Video her duraklamada durur;
 * müşteri bilgileri videonun boş alanlarına gerçek HTML olarak yerleşir.
 * JS, video desteği yoksa veya hareket azaltma açıksa bu bölüm gizlenir; bilgiler aşağıda normal akışta zaten vardır.
 */
use App\Content;
use App\Fmt;
use App\Ics;
use App\View;
$ic = fn(string $n) => View::partial('partials/icon', ['name' => $n]);
$cfg = ['mp4' => $story['mp4'], 'webm' => $story['webm'], 'stops' => array_map(fn($s) => ['t' => $s['t'], 'zone' => $s['zone']], $story['stops'])];
$phone = trim((string)($c['contact']['phone'] ?? ''));
?>
<section class="story" id="story" aria-label="<?= e(t('inv.story')) ?>" hidden>
  <script type="application/json" id="story-data"><?= str_replace('</', '<\/', jenc($cfg)) ?></script>
  <div class="story-sticky">
    <div class="story-stage" id="story-stage">
      <div class="story-media" id="story-media"></div>
      <?php foreach ($story['stops'] as $i => $st): ?>
        <div class="story-stop story-<?= e($st['type']) ?>" data-stop="<?= $i ?>" aria-hidden="true">
          <div class="story-box">
          <?php switch ($st['type']):
            case 'names': ?>
              <?php if (Content::text($c, $L, 'intro') !== ''): ?><p class="s-kicker"><?= e(Content::text($c, $L, 'intro')) ?></p><?php endif; ?>
              <p class="s-names font-<?= e($font) ?>"><?= e($na) ?><?php if ($na !== '' && $nb !== ''): ?> <span class="amp">&amp;</span> <?php endif; ?><?= e($nb) ?></p>
              <?php if ($cover): ?><p class="s-date"><?= e(Fmt::dateLong($cover['start_utc'], $cover['tz'], $L)) ?></p><?php endif; ?>
              <?php break;
            case 'countdown': ?>
              <p class="s-kicker"><?= e(t('inv.countdown')) ?></p>
              <div class="s-cd" data-countdown="<?= e($cover['start_utc']) ?>">
                <div class="cd-grid">
                  <?php foreach (['d' => 'inv.days', 'h' => 'inv.hours', 'm' => 'inv.minutes', 's' => 'inv.seconds'] as $u => $k): ?>
                    <div class="cd-cell"><strong data-cd="<?= $u ?>">–</strong><span><?= e(t($k)) ?></span></div>
                  <?php endforeach; ?>
                </div>
                <p class="cd-done" hidden><?= e(t('inv.countdown_done')) ?></p>
              </div>
              <?php break;
            case 'event1': case 'event2': $e = $events[$st['type'] === 'event1' ? 0 : 1]; ?>
              <p class="s-kicker"><?= e(Content::eventTitle($e, $L)) ?></p>
              <p class="s-evdate"><?= e(Fmt::dateLong($e['start_utc'], $e['tz'], $L)) ?></p>
              <p class="s-evtime"><?= e(Fmt::timeRange($e['start_utc'], $e['end_utc'], $e['tz'], $L)) ?></p>
              <?php if ($e['venue'] !== ''): ?><p class="s-venue"><?= e($e['venue']) ?></p><?php endif; ?>
              <?php break;
            case 'quote': ?>
              <p class="s-quote"><span aria-hidden="true">“</span><?= nl2br(e(Content::text($c, $L, 'quote'))) ?><span aria-hidden="true">”</span></p>
              <?php if (Content::text($c, $L, 'quote_by') !== ''): ?><p class="s-by">— <?= e(Content::text($c, $L, 'quote_by')) ?></p><?php endif; ?>
              <?php break;
            case 'letter': ?>
              <div class="s-letter"><?= nl_paras(Content::text($c, $L, 'letter')) ?></div>
              <?php break;
            case 'map': ?>
              <p class="s-kicker"><?= e(t('inv.see_you')) ?></p>
              <?php foreach (array_slice(array_filter($events, fn($e) => trim($e['venue'] . $e['address']) !== ''), 0, 2) as $e): ?>
                <a class="s-place" href="<?= e(Ics::directionsUrl($e)) ?>" target="_blank" rel="noopener" tabindex="-1">
                  <span class="map-pin"><?= $ic('pin') ?></span>
                  <span><strong><?= e($e['venue'] ?: Content::eventTitle($e, $L)) ?></strong><small><?= e(Content::eventTitle($e, $L)) ?> · <?= e(t('inv.directions')) ?></small></span>
                </a>
              <?php endforeach; ?>
              <div class="s-actions">
                <?php if ($phone !== ''): ?>
                  <a class="ibtn ibtn-ghost" href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>" tabindex="-1"><?= $ic('user') ?> <?= e(t('inv.call')) ?></a>
                  <a class="ibtn ibtn-wa" href="https://wa.me/<?= e(preg_replace('/\D/', '', $phone)) ?>" target="_blank" rel="noopener" tabindex="-1"><?= $ic('whatsapp') ?> WhatsApp</a>
                <?php endif; ?>
                <?php if (!empty($c['rsvp']['enabled'])): ?><a class="ibtn ibtn-primary-sm" href="#rsvp" tabindex="-1"><?= e(t('inv.rsvp_cta')) ?></a><?php endif; ?>
              </div>
              <?php break;
          endswitch; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
