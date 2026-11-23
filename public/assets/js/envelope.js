/*!
 * İkimizden — kaydırmalı zarf motoru (bağımlılıksız).
 * - 220svh bölüm + görünür ekran boyunda yapışkan sahne; ilerleme gerçekten kaydırılabilen mesafeden.
 * - Video: sessiz, kaydırma ileri → ileri sarar, geri → geri sarar; tek seek, en güncel hedefe yetişir.
 * - Video yoksa / yüklenemezse / düşük veri: posterden tema maskeleriyle CSS açılışı.
 * - Masaüstü (yatay): dikey zarf esnetilmez; yatay video varsa o, yoksa kenarlara uzanan CSS zarf.
 * - Hareket azaltma: hafif sürüm (soluk geçiş, "Aç" düğmesi).
 * - Sesler ayrı katmanda, yalnız "Sesi aç" ile; her eşik bir açılışta bir kez.
 */
(function () {
  'use strict';

  var clamp = function (v, a, b) { return v < a ? a : v > b ? b : v; };
  var seg = function (p, a, b) { return clamp((p - a) / (b - a), 0, 1); };
  var ease = function (t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; };
  var easeOut = function (t) { return 1 - Math.pow(1 - t, 3); };

  // Spesifikasyon çizelgesi (ilerleme oranı)
  var T = { closedEnd: 0.125, sheenEnd: 0.30, flapEnd: 0.60, paperEnd: 0.80, textEnd: 0.90 };

  /* ---------------------------------------------------------------- geometri */

  // Posterin "cover" ile sahneye yerleşimi
  function coverRect(W, H, pw, ph) {
    var s = Math.max(W / pw, H / ph);
    return { s: s, w: pw * s, h: ph * s, x: (W - pw * s) / 2, y: (H - ph * s) / 2 };
  }

  function buildGeometry(geo, W, H, composed) {
    var g = geo || {};
    var r = composed ? { s: H / 1920, w: H * 0.5625, h: H, x: (W - H * 0.5625) / 2, y: 0 } : coverRect(W, H, 1080, 1920);
    var P = function (xp, yp) { return [r.x + (xp / 100) * r.w, r.y + (yp / 100) * r.h]; };
    var tip = P(g.flapTipX || 50, g.flapTipY || 63);
    var left = P(0, g.flapLeft || 40), right = P(100, g.flapRight || 41);
    var seal = P(g.sealX || 50, g.sealY || 62);
    var sealR = ((g.sealR || 17.6) / 100) * r.w;
    var extend = function (a, b, x) { var k = (b[1] - a[1]) / (b[0] - a[0]); return [x, a[1] + k * (x - a[0])]; };
    var L, R;
    if (composed) { // yatay ekran: kapak kenarları ekran kenarına aynı yükseklikte uzanır
      L = [0, H * (g.flapLeft || 40) / 100]; R = [W, H * (g.flapRight || 41) / 100];
    } else {
      L = left[0] > 0 ? extend(tip, left, 0) : left;
      R = right[0] < W ? extend(tip, right, W) : right;
    }
    var top = Math.min(0, r.y);
    return {
      r: r, tip: tip, L: L, R: R, seal: seal, sealR: sealR, top: top,
      flap: [[0, top], [W, top], R, tip, L],
      pocketTipY: r.y + ((g.pocketTipY || 66) / 100) * r.h,
      pocketSide: r.y + ((g.pocketSide || 85) / 100) * r.h
    };
  }

  var poly = function (pts) { return 'polygon(' + pts.map(function (p) { return p[0].toFixed(1) + 'px ' + p[1].toFixed(1) + 'px'; }).join(',') + ')'; };

  /* ---------------------------------------------------------------- ses */

  function Sounds(urls, volume) {
    this.urls = urls || {};
    this.volume = volume == null ? 0.6 : volume;
    this.enabled = false;
    this.buffers = {};
    this.fired = {};
    this.lastAt = 0;
    this.ctx = null;
  }
  Sounds.prototype.has = function () { return Object.keys(this.urls).length > 0; };
  Sounds.prototype.enable = function () {
    var self = this;
    if (!this.has()) return Promise.resolve(false);
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return Promise.resolve(false);
    this.ctx = this.ctx || new AC();
    this.enabled = true;
    var jobs = Object.keys(this.urls).map(function (k) {
      if (self.buffers[k]) return Promise.resolve();
      return fetch(self.urls[k], { credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error('ses yok'); return r.arrayBuffer(); })
        .then(function (b) { return new Promise(function (res, rej) { self.ctx.decodeAudioData(b, res, rej); }); })
        .then(function (buf) { self.buffers[k] = buf; })
        .catch(function () { /* dosya yoksa sessiz */ });
    });
    return this.ctx.resume().then(function () { return Promise.all(jobs); }).then(function () { return true; });
  };
  Sounds.prototype.disable = function () { this.enabled = false; if (this.ctx) this.ctx.suspend(); };
  // Eşik: bir açılışta bir kez; art arda (<180 ms) gelen eşikler çalınmaz (sona atlama)
  Sounds.prototype.cross = function (name) {
    if (this.fired[name]) return;
    this.fired[name] = true;
    var now = performance.now();
    var tooFast = now - this.lastAt < 180;
    this.lastAt = now;
    if (!this.enabled || tooFast || document.hidden) return;
    var buf = this.buffers[name];
    if (!buf || !this.ctx) return;
    var src = this.ctx.createBufferSource();
    var gain = this.ctx.createGain();
    gain.gain.value = this.volume;
    src.buffer = buf;
    src.connect(gain).connect(this.ctx.destination);
    src.start();
  };
  Sounds.prototype.markAll = function () { var self = this; Object.keys(this.urls).forEach(function (k) { self.fired[k] = true; }); };

  /* ---------------------------------------------------------------- motor */

  function Envelope(root, cfg, opts) {
    this.root = root;
    this.cfg = cfg;
    this.opts = opts || {};
    this.card = !!this.opts.card;
    this.stage = root.querySelector('.env-stage') || root;
    this.layers = this.stage.querySelector('.env-layers');
    this.text = this.stage.querySelector('.env-text');
    this.hint = this.stage.querySelector('.env-hint');
    this.sealBtn = this.stage.querySelector('.env-seal');
    this.p = 0;
    this.drawn = -1;
    this.mode = null;
    this.video = null;
    this.seeking = false;
    this.pending = null;
    this.sounds = new Sounds(cfg.audio, cfg.volume);
    this.cues = cfg.cues || { ornament: 0.14, seal: 0.30, flap: 0.38, paper: 0.61 };
    this.prevP = 0;
  }

  // Tarayıcının çözebildiği video: H.264 MP4 (Safari/Chrome) ya da VP9 WebM (H.264 olmayan Chromium/Firefox)
  function pickVideo(cfg) {
    var v = document.createElement('video');
    if (!v.canPlayType) return null;
    var mp4 = cfg.videoMp4 || (cfg.video && /\.mp4/.test(cfg.video) ? cfg.video : null);
    var webm = cfg.videoWebm || null;
    if (mp4 && v.canPlayType('video/mp4; codecs="avc1.640028"')) return mp4;
    if (webm && v.canPlayType('video/webm; codecs="vp9"')) return webm;
    if (mp4 && v.canPlayType('video/mp4')) return mp4;
    return null;
  }

  Envelope.prototype.decideMode = function () {
    var mq = window.matchMedia;
    var reduce = mq && mq('(prefers-reduced-motion: reduce)').matches;
    var conn = navigator.connection || {};
    var lowData = !!conn.saveData || /(^|-)2g$/.test(conn.effectiveType || '');
    var W = this.stage.clientWidth, H = this.stage.clientHeight;
    var wide = W > H * 1.05;
    if (reduce && !this.card) return 'lite';
    if (wide) return this.cfg.videoWide && !lowData ? 'video-wide' : 'composed';
    if (!this.videoFailed && !lowData && pickVideo(this.cfg)) return 'video';
    return 'poster';
  };

  Envelope.prototype.init = function () {
    var self = this;
    this.root.classList.add('env-ready');
    this.setup(this.decideMode());
    var resizeT = null;
    window.addEventListener('resize', function () {
      clearTimeout(resizeT);
      resizeT = setTimeout(function () {
        var m = self.decideMode();
        if (m !== self.mode) self.setup(m); else self.layout();
        self.fitNames();
        self.render(true);
      }, 120);
    });
    this.fitNames();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { self.fitNames(); });
  };

  Envelope.prototype.setup = function (mode) {
    var self = this;
    this.mode = mode;
    this.root.setAttribute('data-mode', mode);
    this.layers.innerHTML = '';
    this.video = null;
    this.el = {};
    this.layout();
    if (mode === 'lite') { this.render(true); return; }
    if (mode === 'video' || mode === 'video-wide') {
      var v = document.createElement('video');
      v.className = 'env-video';
      v.muted = true; v.defaultMuted = true; v.playsInline = true;
      v.setAttribute('muted', ''); v.setAttribute('playsinline', ''); v.setAttribute('webkit-playsinline', '');
      v.preload = 'auto';
      v.disablePictureInPicture = true;
      v.setAttribute('aria-hidden', 'true');
      v.poster = this.cfg.poster;
      v.src = mode === 'video-wide' ? this.cfg.videoWide : pickVideo(this.cfg);
      v.addEventListener('loadedmetadata', function () {
        // iOS: ilk kareyi çözmek için sessiz oynat-durdur
        var pr = v.play();
        if (pr && pr.then) pr.then(function () { v.pause(); self.seekTo(self.p); }).catch(function () { self.seekTo(self.p); });
        else self.seekTo(self.p);
      });
      v.addEventListener('seeked', function () {
        self.seeking = false;
        self.root.classList.add('video-on');
        if (self.pending != null && Math.abs(self.pending - v.currentTime) > 1 / 48) {
          var t = self.pending; self.pending = null; self.seekTime(t);
        } else self.pending = null;
      });
      v.addEventListener('error', function () { self.videoFailed = true; self.setup('poster'); self.render(true); });
      this.layers.appendChild(v);
      this.video = v;
      // yükleme takılırsa (8 sn) CSS sürüme geç
      setTimeout(function () { if (self.video === v && v.readyState < 1) { self.videoFailed = true; self.setup(self.decideMode()); self.render(true); } }, 8000);
      this.render(true);
      return;
    }
    // CSS açılış katmanları
    var mk = function (cls) { var d = document.createElement('div'); d.className = 'L ' + cls; self.layers.appendChild(d); return d; };
    var composed = mode === 'composed';
    this.el.interior = mk('L-interior');
    this.el.paper = mk('L-paper');
    this.el.body = mk('L-body');
    this.el.flapWrap = mk('L-flapwrap');
    this.el.flap = document.createElement('div'); this.el.flap.className = 'L L-flap'; this.el.flapWrap.appendChild(this.el.flap);
    this.el.sheen = document.createElement('div'); this.el.sheen.className = 'L L-sheen sheen-' + (this.cfg.sheen || 'copper'); this.el.flapWrap.appendChild(this.el.sheen);
    this.el.seal = document.createElement('div'); this.el.seal.className = 'L-seal'; this.el.flapWrap.appendChild(this.el.seal);
    if (composed) {
      this.el.lines = mk('L-lines');
      this.el.lines.innerHTML = '<svg width="100%" height="100%" preserveAspectRatio="none" aria-hidden="true"><path class="fold" d=""/><path class="fold2" d=""/></svg>';
    }
    this.layout();
    this.render(true);
  };

  Envelope.prototype.layout = function () {
    var W = this.stage.clientWidth, H = this.stage.clientHeight;
    this.W = W; this.H = H;
    var composed = this.mode === 'composed';
    var G = buildGeometry(this.cfg.geometry, W, H, composed);
    this.G = G;
    var c = this.cfg;
    // mühür düğmesi: videodaki/posterdeki mühürle aynı yerde
    if (this.sealBtn) {
      var size = (this.cfg.sealSize || 44) / 100 * G.r.w;
      var st = this.sealBtn.style;
      st.width = st.height = size + 'px';
      st.left = (G.seal[0] - size / 2) + 'px';
      st.top = (G.seal[1] - size / 2) + 'px';
    }
    if (!this.el || !this.el.body) return;
    var bgPoster = 'url("' + c.poster + '")';
    var posBg = function (el) {
      el.style.backgroundImage = bgPoster;
      el.style.backgroundSize = G.r.w + 'px ' + G.r.h + 'px';
      el.style.backgroundPosition = G.r.x + 'px ' + G.r.y + 'px';
      el.style.backgroundRepeat = 'no-repeat';
    };
    var flapPoly = poly(G.flap);
    var bodyPoly = poly([G.L, G.tip, G.R, [W, H], [0, H]]);
    this.el.interior.style.backgroundImage = c.texture ? 'url("' + c.texture + '")' : '';
    if (composed) {
      // doku ekran kenarlarına uzar; kapak ortadaki kabartma görseliyle
      var tex = 'url("' + c.texture + '")';
      this.el.body.style.backgroundImage = 'url("' + c.poster + '"), ' + tex;
      this.el.body.style.backgroundSize = G.r.w + 'px ' + G.r.h + 'px, 600px 600px';
      this.el.body.style.backgroundPosition = G.r.x + 'px ' + G.r.y + 'px, 0 0';
      this.el.body.style.backgroundRepeat = 'no-repeat, repeat';
      this.el.body.style.webkitMaskImage = this.el.body.style.maskImage = '';
      this.el.flap.style.backgroundImage = 'url("' + c.ornament + '"), ' + tex;
      this.el.flap.style.backgroundSize = G.r.w + 'px ' + G.r.h + 'px, 600px 600px';
      this.el.flap.style.backgroundPosition = G.r.x + 'px ' + G.r.y + 'px, 0 0';
      this.el.flap.style.backgroundRepeat = 'no-repeat, repeat';
      var d = 'M0,' + G.L[1] + ' L' + G.tip[0] + ',' + G.tip[1] + ' L' + W + ',' + G.R[1];
      var py = G.pocketSide, pt = G.pocketTipY;
      var d2 = 'M0,' + (py + (H - py) * 0.4) + ' L' + G.tip[0] + ',' + pt + ' L' + W + ',' + (py + (H - py) * 0.4);
      var paths = this.el.lines.querySelectorAll('path');
      paths[0].setAttribute('d', d); paths[1].setAttribute('d', d2);
      this.el.lines.firstChild.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    } else {
      posBg(this.el.body);
      posBg(this.el.flap);
    }
    this.el.body.style.clipPath = this.el.body.style.webkitClipPath = bodyPoly;
    this.el.flap.style.clipPath = this.el.flap.style.webkitClipPath = flapPoly;
    // ışık: yalnız kabartma desende
    if (c.sheenMask) {
      var m = 'url("' + c.sheenMask + '")';
      this.el.sheen.style.webkitMaskImage = this.el.sheen.style.maskImage = m;
      this.el.sheen.style.webkitMaskSize = this.el.sheen.style.maskSize = G.r.w + 'px ' + G.r.h + 'px';
      this.el.sheen.style.webkitMaskPosition = this.el.sheen.style.maskPosition = G.r.x + 'px ' + G.r.y + 'px';
      this.el.sheen.style.webkitMaskRepeat = this.el.sheen.style.maskRepeat = 'no-repeat';
    }
    var ss = (this.cfg.sealSize || 44) / 100 * G.r.w;
    var se = this.el.seal.style;
    se.width = se.height = ss + 'px';
    se.left = (G.seal[0] - ss / 2) + 'px';
    se.top = (G.seal[1] - ss / 2) + 'px';
    se.backgroundImage = 'url("' + c.seal + '")';
    this.el.paper.style.backgroundImage = c.paper ? 'url("' + c.paper + '")' : '';
  };

  /* ---------------------------------------------------------------- video zamanı */

  Envelope.prototype.seekTime = function (t) {
    var v = this.video;
    if (!v) return;
    this.seeking = true;
    try { if (v.fastSeek && Math.abs(v.currentTime - t) > 1.5) v.fastSeek(t); else v.currentTime = t; }
    catch (e) { this.seeking = false; }
  };
  Envelope.prototype.seekTo = function (p) {
    var v = this.video;
    if (!v || !v.duration || !isFinite(v.duration)) return;
    var t = clamp(p * v.duration, 0, v.duration - 0.04);
    if (this.seeking) { this.pending = t; return; } // biriktirme yok: yalnız en güncel hedef
    if (Math.abs(v.currentTime - t) < 1 / 60) return;
    this.seekTime(t);
  };

  /* ---------------------------------------------------------------- çizim */

  Envelope.prototype.setProgress = function (p) {
    this.p = clamp(p, 0, 1);
    this.render(false);
  };

  Envelope.prototype.render = function (force) {
    var p = this.p;
    if (!force && Math.abs(p - this.drawn) < 0.0005) return;
    this.drawn = p;
    var root = this.root;
    root.style.setProperty('--p', p.toFixed(4));

    // sesler: yalnız ileri geçişte; geri sarma tetiklemez
    if (p > this.prevP) {
      for (var k in this.cues) if (this.prevP < this.cues[k] && p >= this.cues[k]) this.sounds.cross(k);
    }
    this.prevP = Math.max(this.prevP, p);

    // ipucu ve mühür düğmesi
    var hintO = 1 - seg(p, 0.02, T.closedEnd);
    if (this.hint) { this.hint.style.opacity = hintO.toFixed(3); this.hint.style.visibility = hintO < 0.02 ? 'hidden' : 'visible'; }
    if (this.sealBtn) {
      var so = 1 - seg(p, 0.005, 0.04);
      this.sealBtn.style.opacity = so.toFixed(3);
      this.sealBtn.style.visibility = so < 0.02 ? 'hidden' : 'visible';
    }
    // metin: %80–90 belirir, sonra sabit
    var tp = seg(p, T.paperEnd, T.textEnd);
    if (this.text && !root.classList.contains('names-flow')) {
      this.text.style.opacity = this.mode === 'lite' ? (p > 0.5 ? 1 : 0) : easeOut(tp).toFixed(3);
      this.text.style.transform = 'translateY(' + ((1 - easeOut(tp)) * 14).toFixed(1) + 'px)';
    }
    root.classList.toggle('is-open', p >= T.paperEnd);
    root.classList.toggle('is-done', p >= 0.999);

    if (this.mode === 'video' || this.mode === 'video-wide') { this.seekTo(p); return; }
    if (this.mode === 'lite' || !this.el || !this.el.body) return;

    var G = this.G, W = this.W, H = this.H;
    // 1) ışık geçişi 12,5–30
    var sh = seg(p, T.closedEnd, T.sheenEnd);
    this.el.sheen.style.opacity = (sh > 0 && sh < 1 ? Math.sin(sh * Math.PI) : 0).toFixed(3);
    this.el.sheen.style.backgroundPosition = (110 - sh * 120).toFixed(1) + '% ' + (110 - sh * 120).toFixed(1) + '%';
    // 2) mühür ayrılır 30–38, kapak açılır 36–60
    var lift = easeOut(seg(p, T.sheenEnd, 0.38));
    var fl = ease(seg(p, 0.36, T.flapEnd));
    var ang = fl * 180;
    this.el.seal.style.transform = 'translateY(' + (-lift * 10).toFixed(1) + 'px) scale(' + (1 + lift * 0.07).toFixed(3) + ')';
    this.el.seal.style.filter = 'drop-shadow(0 ' + (3 + lift * 18).toFixed(1) + 'px ' + (5 + lift * 20).toFixed(1) + 'px rgba(0,0,0,' + (0.25 + lift * 0.17).toFixed(2) + '))';
    this.el.seal.style.opacity = ang < 92 ? 1 : 0;
    var hingeY = G.top;
    this.el.flapWrap.style.transformOrigin = '50% ' + hingeY + 'px';
    this.el.flapWrap.style.transform = 'perspective(' + Math.round(H * 1.15) + 'px) rotateX(' + ang.toFixed(2) + 'deg)';
    this.el.flapWrap.classList.toggle('is-back', ang >= 90);
    // 3) kâğıt 60–80: zarfın içinden yükselir, sonra ekranı doldurur; gövde aşağı süzülür
    var rise = easeOut(seg(p, 0.58, 0.70));
    var fill = ease(seg(p, 0.68, T.paperEnd));
    var pw0 = Math.min(G.r.w * 0.8, W * 0.9), ph0 = H * 0.6;
    var top0 = G.pocketTipY + (G.tip[1] - G.pocketTipY) * 0 + H * 0.06 - rise * (H * 0.46);
    var x = (W - pw0) / 2 * (1 - fill), y = top0 * (1 - fill), w = pw0 + (W - pw0) * fill, h = ph0 + (H - ph0) * fill;
    var ps = this.el.paper.style;
    ps.opacity = p > 0.56 ? 1 : 0;
    ps.transform = 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px)';
    ps.width = w.toFixed(1) + 'px'; ps.height = h.toFixed(1) + 'px';
    ps.borderRadius = ((1 - fill) * 8).toFixed(1) + 'px';
    this.el.body.style.transform = 'translateY(' + (fill * H * 0.55).toFixed(1) + 'px)';
    this.el.body.style.opacity = (1 - seg(fill, 0.2, 0.9)).toFixed(3);
    this.el.flapWrap.style.opacity = (1 - seg(fill, 0, 0.4)).toFixed(3);
    if (this.el.lines) this.el.lines.style.opacity = (1 - seg(p, 0.4, 0.6)).toFixed(3);
  };

  /* ---------------------------------------------------------------- isimlerin sığdırılması */

  Envelope.prototype.fitNames = function () {
    var h1 = this.text && this.text.querySelector('.env-names');
    if (!h1) return;
    var box = this.text;
    if (this.root.classList.contains('names-flow')) return;
    h1.style.fontSize = '';
    var size = parseFloat(getComputedStyle(h1).fontSize);
    var min = 28; // okunabilir alt sınır
    var guard = 40;
    var fits = function () { return h1.scrollWidth <= box.clientWidth + 1 && box.scrollHeight <= box.clientHeight + 1; };
    while (!fits() && size > min && guard-- > 0) { size -= 2; h1.style.fontSize = size + 'px'; }
    if (!fits()) {
      // yine sığmıyorsa başlık normal belge akışına taşınır (tek h1, iki kez okunmaz)
      var slot = document.getElementById('names-flow-slot');
      if (slot && !this.opts.card) {
        h1.style.fontSize = '';
        this.root.classList.add('names-flow');
        slot.appendChild(this.text);
        this.text.style.opacity = 1; this.text.style.transform = 'none';
      }
    }
  };

  /* ---------------------------------------------------------------- sürücüler */

  // Davetiye sayfası: kaydırma
  function ScrollDriver(env) {
    var root = env.root;
    var sticky = root.querySelector('.env-sticky');
    var ticking = false;
    var compute = function () {
      ticking = false;
      if (env.mode === 'lite') return;
      var rect = root.getBoundingClientRect();
      var scrollable = root.offsetHeight - sticky.offsetHeight;
      var p = scrollable > 0 ? clamp(-rect.top / scrollable, 0, 1) : 1;
      env.setProgress(p);
    };
    var onScroll = function () { if (!ticking) { ticking = true; requestAnimationFrame(compute); } };
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    document.addEventListener('visibilitychange', function () { if (document.hidden && env.sounds.ctx) env.sounds.ctx.suspend(); else if (env.sounds.enabled && env.sounds.ctx) env.sounds.ctx.resume(); });
    compute();
    // kaydırmayı yumuşak animasyonla bölüm sonuna taşı (Aç / mühür)
    this.animateTo = function (target, ms, done) {
      var start = window.scrollY, rect = root.getBoundingClientRect();
      var end = window.scrollY + rect.top + (root.offsetHeight - sticky.offsetHeight) * target;
      var t0 = performance.now();
      var cancel = false;
      var stop = function () { cancel = true; };
      window.addEventListener('wheel', stop, { passive: true, once: true });
      window.addEventListener('touchstart', stop, { passive: true, once: true });
      var step = function (now) {
        if (cancel) return;
        var k = clamp((now - t0) / ms, 0, 1);
        var e = k < 0.5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2;
        window.scrollTo(0, start + (end - start) * e);
        if (k < 1) requestAnimationFrame(step); else if (done) done();
      };
      requestAnimationFrame(step);
    };
    this.jumpTo = function (target) {
      var rect = root.getBoundingClientRect();
      window.scrollTo(0, window.scrollY + rect.top + (root.offsetHeight - sticky.offsetHeight) * target);
      compute();
    };
  }

  // Vitrin kartı: zaman
  function TimeDriver(env) {
    var raf = null;
    this.play = function (ms, done) {
      cancelAnimationFrame(raf);
      var t0 = performance.now();
      var from = env.p;
      var step = function (now) {
        var k = clamp((now - t0) / ms, 0, 1);
        env.setProgress(from + (1 - from) * k);
        if (k < 1) raf = requestAnimationFrame(step); else if (done) done();
      };
      raf = requestAnimationFrame(step);
    };
    this.reset = function () { cancelAnimationFrame(raf); env.prevP = 0; env.setProgress(0); };
  }

  /* ---------------------------------------------------------------- davetiye sayfası */

  function pressSeal(btn, then) {
    if (!btn) { then(); return; }
    btn.classList.remove('pressed');
    void btn.offsetWidth;
    btn.classList.add('pressed');
    if (navigator.vibrate) { try { navigator.vibrate(12); } catch (e) {} }
    setTimeout(then, 420);
  }

  function initInvite() {
    var root = document.getElementById('env');
    var dataEl = document.getElementById('env-data');
    if (!root || !dataEl) return;
    var cfg;
    try { cfg = JSON.parse(dataEl.textContent); } catch (e) { return; }
    var stage = root.querySelector('.env-stage');
    var poster = stage.querySelector('.env-poster');
    cfg.texture = cfg.texture || (poster && poster.getAttribute('data-texture'));
    var env = new Envelope(root, cfg);
    env.init();
    var driver = new ScrollDriver(env);
    var letter = document.getElementById('letter');
    var focusLetter = function () {
      var h = document.getElementById('inv-title');
      if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
    };

    var open = function () {
      if (env.mode === 'lite') { root.classList.add('lite-open'); env.p = 1; env.render(true); focusLetter(); return; }
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      driver.animateTo(1, reduce ? 10 : 3200, focusLetter);
    };
    // mühüre basınca: basma efekti, ardından açılış
    var seal = document.getElementById('env-seal');
    if (seal) seal.addEventListener('click', function () { pressSeal(seal, open); });
    Array.prototype.forEach.call(root.querySelectorAll('[data-env-open]'), function (b) { b.addEventListener('click', open); });

    var skip = document.getElementById('env-skip');
    if (skip) {
      skip.hidden = false;
      skip.addEventListener('click', function () {
        env.sounds.markAll(); // sona atlarken sesler arka arkaya çalmaz
        if (env.mode === 'lite') { root.classList.add('lite-open'); env.p = 1; env.render(true); }
        else driver.jumpTo(1);
        if (letter) letter.scrollIntoView({ block: 'start' });
        focusLetter();
      });
    }
    var snd = document.getElementById('env-sound');
    if (snd && env.sounds.has()) {
      snd.hidden = false;
      var label = snd.querySelector('span');
      snd.addEventListener('click', function () {
        if (env.sounds.enabled) {
          env.sounds.disable();
          snd.setAttribute('aria-pressed', 'false');
          label.textContent = cfg.strings.soundOn;
          snd.classList.remove('on');
        } else {
          env.sounds.enable().then(function (ok) {
            if (!ok) { snd.hidden = true; return; }
            snd.setAttribute('aria-pressed', 'true');
            label.textContent = cfg.strings.soundOff;
            snd.classList.add('on');
          });
        }
      });
    }
  }

  /* ---------------------------------------------------------------- vitrin kartı */

  function initOpener() {
    var wrap = document.getElementById('opener');
    if (!wrap) return;
    var cards;
    try { cards = JSON.parse(wrap.getAttribute('data-cards')); } catch (e) { return; }
    var card = wrap.querySelector('.opener-card');
    var stage = card.querySelector('[data-env-card]');
    var posterImg = stage.querySelector('.env-poster');
    var btn = wrap.querySelector('[data-opener-open]');
    var full = wrap.querySelector('[data-opener-full]');
    var cur = cards[0];
    var env = null, drv = null;
    var make = function (c) {
      cur = c;
      var layers = stage.querySelector('.env-layers');
      if (!layers) { layers = document.createElement('div'); layers.className = 'env-layers'; stage.insertBefore(layers, posterImg.nextSibling); }
      env = new Envelope(stage, {
        poster: c.poster, video: null, geometry: c.geometry, sealSize: c.sealSize, texture: c.texture,
        ornament: c.ornament, seal: c.seal, paper: c.paper, sheenMask: c.sheen, sheen: c.sheenKind || 'copper', audio: {}
      }, { card: true });
      env.root = stage;
      env.stage = stage;
      env.setup('poster');
      drv = new TimeDriver(env);
      env.setProgress(0);
    };
    make(cur);
    var playing = false;
    btn.addEventListener('click', function () {
      if (playing) return;
      if (env.p >= 0.99) { drv.reset(); }
      playing = true;
      btn.disabled = true;
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      drv.play(reduce ? 200 : 3600, function () { playing = false; btn.disabled = false; });
    });
    Array.prototype.forEach.call(wrap.querySelectorAll('[data-opener-theme]'), function (b) {
      b.addEventListener('click', function () {
        var slug = b.getAttribute('data-opener-theme');
        var c = cards.filter(function (x) { return x.slug === slug; })[0];
        if (!c) return;
        Array.prototype.forEach.call(wrap.querySelectorAll('[data-opener-theme]'), function (o) { o.classList.toggle('is-on', o === b); o.setAttribute('aria-pressed', o === b ? 'true' : 'false'); });
        posterImg.src = c.poster;
        card.setAttribute('data-theme', slug);
        card.style.setProperty('--t-ink', (c.colors && c.colors.ink) || '#4a1024');
        var names = stage.querySelector('.env-names');
        if (names) names.className = 'env-names font-' + (c.font || 'greatvibes');
        if (full) full.href = c.preview;
        make(c);
      });
    });
    window.addEventListener('resize', function () { if (env) { env.layout(); env.render(true); } });
  }

  window.IkzEnvelope = Envelope;
  var start = function () { initInvite(); initOpener(); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
