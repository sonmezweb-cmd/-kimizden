/*!
 * İkimizden — hikâye videosu (zarftan sonraki resimli kaydırmalı sahne).
 * Kaydırma → video zamanı; her durakta video DURUR ve müşteri bilgisi okunurken sayfa kaydırılmaya devam eder.
 * Tek seek, en güncel hedefe yetişme; video bölüme yaklaşınca yüklenir. Yoksa bölüm gizlenir.
 */
(function () {
  'use strict';
  var clamp = function (v, a, b) { return v < a ? a : v > b ? b : v; };
  var MOVE = 0.10;  // video saniyesi başına ekran yüksekliği
  var HOLD = 0.70;  // her durakta okuma payı (ekran yüksekliği)
  var LAST_HOLD = 0.45;

  function pick(cfg) {
    var v = document.createElement('video');
    if (!v.canPlayType) return null;
    if (cfg.mp4 && v.canPlayType('video/mp4; codecs="avc1.640028"')) return cfg.mp4;
    if (cfg.webm && v.canPlayType('video/webm; codecs="vp9"')) return cfg.webm;
    if (cfg.mp4 && v.canPlayType('video/mp4')) return cfg.mp4;
    return null;
  }

  function init() {
    var sec = document.getElementById('story');
    var dataEl = document.getElementById('story-data');
    if (!sec || !dataEl) return;
    var cfg; try { cfg = JSON.parse(dataEl.textContent); } catch (e) { return; }
    var mq = window.matchMedia;
    var reduce = mq && mq('(prefers-reduced-motion: reduce)').matches;
    var conn = navigator.connection || {};
    var lowData = !!conn.saveData || /(^|-)2g$/.test(conn.effectiveType || '') || /[?&]novideo=1/.test(location.search);
    var src = pick(cfg);
    if (reduce || lowData || !src || !cfg.stops || !cfg.stops.length) return; // bölüm gizli kalır, bilgiler normal akışta

    sec.hidden = false;
    document.body.classList.add('has-story');
    var sticky = sec.querySelector('.story-sticky');
    var stage = sec.querySelector('.story-stage');
    var media = document.getElementById('story-media');
    var stops = Array.prototype.slice.call(sec.querySelectorAll('.story-stop'));
    var video = null, duration = 24, seeking = false, pending = null, ready = false;
    var segs = [], total = 0, vh = window.innerHeight;

    // Kaydırma payı: hareket parçaları + durak bekleme parçaları
    var buildSegments = function () {
      segs = []; total = 0;
      var t = 0;
      cfg.stops.forEach(function (st, i) {
        var tt = clamp(st.t, t, duration);
        if (tt > t) { segs.push({ kind: 'move', a: t, b: tt, len: (tt - t) * MOVE }); total += (tt - t) * MOVE; }
        var h = i === cfg.stops.length - 1 ? LAST_HOLD : HOLD;
        segs.push({ kind: 'hold', t: tt, i: i, len: h }); total += h;
        t = tt;
      });
      if (t < duration && cfg.stops[cfg.stops.length - 1].t < duration - 0.3) { segs.push({ kind: 'move', a: t, b: duration, len: (duration - t) * MOVE }); total += (duration - t) * MOVE; }
      sec.style.height = Math.round((1 + total) * vh) + 'px';
    };

    // Boş alanları video görüntüsünün gerçek konumuna yerleştir (telefonda tam ekran, geniş ekranda ortalı)
    var layout = function () {
      vh = window.innerHeight;
      var W = stage.clientWidth, H = stage.clientHeight, vw = 9, vhh = 16;
      var portrait = W / H <= 0.75;
      var s = portrait ? Math.max(W / vw, H / vhh) : Math.min(W / vw, H / vhh);
      var rw = vw * s, rh = vhh * s, rx = (W - rw) / 2, ry = (H - rh) / 2;
      stage.classList.toggle('story-contain', !portrait);
      media.style.left = rx + 'px'; media.style.top = ry + 'px'; media.style.width = rw + 'px'; media.style.height = rh + 'px';
      stops.forEach(function (el, i) {
        var z = cfg.stops[i].zone;
        var x1 = Math.max(rx + rw * z[0] / 100, 10), x2 = Math.min(rx + rw * z[2] / 100, W - 10);
        el.style.left = x1 + 'px'; el.style.top = (ry + rh * z[1] / 100) + 'px';
        el.style.width = (x2 - x1) + 'px'; el.style.height = (rh * (z[3] - z[1]) / 100) + 'px';
        fit(el);
      });
      buildSegments();
    };

    // Metin alanına sığdır: yazı küçülür (okunabilir alt sınır), kesilmez
    var fit = function (el) {
      var box = el.querySelector('.story-box');
      box.style.fontSize = '';
      var size = parseFloat(getComputedStyle(box).fontSize), guard = 30;
      while (box.scrollHeight > el.clientHeight + 1 && size > 11 && guard-- > 0) { size -= 0.5; box.style.fontSize = size + 'px'; }
      el.classList.toggle('story-scroll', box.scrollHeight > el.clientHeight + 1);
    };

    var seekTime = function (t) { seeking = true; try { video.currentTime = t; } catch (e) { seeking = false; } };
    var seekTo = function (t) {
      if (!ready) return;
      t = clamp(t, 0, duration - 0.04);
      if (seeking) { pending = t; return; }
      if (Math.abs(video.currentTime - t) < 1 / 60) return;
      seekTime(t);
    };

    var load = function () {
      if (video) return;
      video = document.createElement('video');
      video.muted = true; video.defaultMuted = true; video.playsInline = true;
      video.setAttribute('muted', ''); video.setAttribute('playsinline', ''); video.setAttribute('aria-hidden', 'true');
      video.preload = 'auto'; video.disablePictureInPicture = true;
      video.src = src;
      video.addEventListener('loadedmetadata', function () {
        if (isFinite(video.duration) && video.duration > 1) duration = video.duration;
        ready = true;
        buildSegments();
        var pr = video.play();
        var go = function () { video.pause(); compute(); };
        if (pr && pr.then) pr.then(go).catch(go); else go();
      });
      video.addEventListener('seeked', function () {
        seeking = false;
        stage.classList.add('story-on');
        if (pending != null && Math.abs(pending - video.currentTime) > 1 / 48) { var t = pending; pending = null; seekTime(t); } else pending = null;
      });
      video.addEventListener('error', function () { // yüklenemezse bölümü kaldır
        sec.hidden = true; sec.style.height = ''; document.body.classList.remove('has-story');
      });
      media.appendChild(video);
    };

    var lastActive = -1;
    var compute = function () {
      ticking = false;
      var rect = sec.getBoundingClientRect();
      // misafir zarfı açmaya başlayınca (ilk kaydırma) indirmeye başla; sayfa açılışında indirme yok
      if (!video && (window.scrollY > 20 || rect.top < vh * 1.2)) load();
      var px = clamp(-rect.top, 0, Math.max(1, sec.offsetHeight - sticky.offsetHeight));
      var u = px / vh, t = 0, active = -1, f = 0;
      for (var k = 0; k < segs.length; k++) {
        var sg = segs[k];
        if (u <= sg.len || k === segs.length - 1) {
          var r = sg.len > 0 ? clamp(u / sg.len, 0, 1) : 1;
          if (sg.kind === 'move') t = sg.a + (sg.b - sg.a) * r; else { t = sg.t; active = sg.i; f = r; }
          break;
        }
        u -= sg.len;
      }
      seekTo(t);
      stops.forEach(function (el, i) {
        var o = 0;
        if (i === active) {
          var last = i === stops.length - 1;
          o = f < 0.15 ? f / 0.15 : (last || f < 0.85 ? 1 : (1 - f) / 0.15);
        }
        el.style.opacity = o.toFixed(3);
        el.style.transform = 'translateY(' + ((1 - o) * 10).toFixed(1) + 'px)';
        var on = o > 0.5;
        el.classList.toggle('is-on', on);
        el.setAttribute('aria-hidden', on ? 'false' : 'true');
        Array.prototype.forEach.call(el.querySelectorAll('a'), function (a) { a.tabIndex = on ? 0 : -1; });
      });
      if (active !== lastActive) { lastActive = active; sec.setAttribute('data-stop', String(active)); }
    };
    var ticking = false;
    var onScroll = function () { if (!ticking) { ticking = true; requestAnimationFrame(compute); } };
    window.addEventListener('scroll', onScroll, { passive: true });
    var rt = null;
    window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(function () { layout(); compute(); }, 120); });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { layout(); compute(); });
    layout();
    compute();
    window.__ikzStory = { segments: function () { return segs; }, video: function () { return video; }, duration: function () { return duration; } };
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
