/* PayTR iFrame boyutlandırma + ödeme durumu bekleme (durum yalnız sunucudaki kayıttan okunur) */
(function () {
  if (window.iFrameResize && document.getElementById('paytriframe')) window.iFrameResize({}, '#paytriframe');
  // PayTR dönüş sayfası çerçeve içinde açıldıysa üst pencereye taşı
  if (window.top !== window.self && document.getElementById('pay-state')) { try { window.top.location.href = window.location.href; } catch (e) {} }
  var el = document.getElementById('pay-state');
  if (!el || el.getAttribute('data-state') !== 'verifying') return;
  var url = el.getAttribute('data-poll');
  var n = 0;
  var poll = function () {
    if (n++ > 40) return;
    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j && j.state && j.state !== 'verifying') window.location.reload(); else setTimeout(poll, 3000); })
      .catch(function () { setTimeout(poll, 5000); });
  };
  setTimeout(poll, 2500);
})();
