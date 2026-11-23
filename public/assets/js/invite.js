/*! İkimizden — davetiye sayfası: geri sayım, görünürken belirme, harita, RSVP */
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Görünür olunca yumuşak belirme
  var items = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (ents) {
      ents.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    Array.prototype.forEach.call(items, function (el) { el.classList.add('pre'); io.observe(el); });
  }

  // Geri sayım (mekânın UTC zamanına göre; misafirin saat diliminden bağımsız)
  var cd = document.querySelector('[data-countdown]');
  if (cd) {
    var target = Date.parse(cd.getAttribute('data-countdown'));
    var cells = {};
    ['d', 'h', 'm', 's'].forEach(function (k) { cells[k] = cd.querySelector('[data-cd="' + k + '"]'); });
    var fmt = new Intl.NumberFormat(document.documentElement.lang || 'tr');
    var tick = function () {
      var diff = Math.max(0, target - Date.now());
      if (diff <= 0) {
        cd.querySelector('.cd-grid').hidden = true;
        cd.querySelector('.cd-done').hidden = false;
        return;
      }
      var s = Math.floor(diff / 1000);
      var v = { d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: s % 60 };
      for (var k in v) if (cells[k]) {
        var t = k === 'd' ? fmt.format(v[k]) : String(v[k]).padStart(2, '0');
        if (cells[k].textContent !== t) { cells[k].textContent = t; if (!reduce) { cells[k].classList.remove('tick'); void cells[k].offsetWidth; cells[k].classList.add('tick'); } }
      }
      setTimeout(tick, 1000 - (Date.now() % 1000));
    };
    tick();
  }

  // Harita: dokununca yüklenir (gizlilik ve hız)
  Array.prototype.forEach.call(document.querySelectorAll('[data-map-show]'), function (b) {
    b.addEventListener('click', function () {
      var card = b.closest('.map-card');
      if (!card || card.querySelector('iframe')) return;
      var f = document.createElement('iframe');
      f.src = card.getAttribute('data-map');
      f.loading = 'lazy';
      f.title = b.textContent;
      f.referrerPolicy = 'no-referrer-when-downgrade';
      f.className = 'map-frame';
      card.appendChild(f);
      b.remove();
    });
  });

  // RSVP: gelemiyorum seçilince kişi sayıları gizlenir
  Array.prototype.forEach.call(document.querySelectorAll('.rsvp-form'), function (form) {
    var counts = form.querySelector('[data-when-attending]');
    var sync = function () {
      var yes = form.querySelector('input[name="attending"][value="1"]');
      if (counts) counts.hidden = !(yes && yes.checked);
    };
    form.addEventListener('change', sync);
    sync();
    if (form.getAttribute('aria-disabled') === 'true') form.addEventListener('submit', function (e) { e.preventDefault(); });
  });
})();
