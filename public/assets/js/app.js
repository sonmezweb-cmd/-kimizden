/*! İkimizden — panel ve vitrin etkileşimleri */
(function () {
  'use strict';
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = function () { var i = document.querySelector('input[name="_csrf"]'); return i ? i.value : ''; };
  var stateEl = document.getElementById('save-state');
  var L = (document.documentElement.lang || 'tr');
  var MSG = {
    tr: { saving: 'Kaydediliyor…', saved: 'Kaydedildi', failed: 'Kaydedilemedi', retry: 'Yeniden dene', offline: 'Bağlantı yok — değişiklikler bu cihazda tutuluyor', conflict: 'Başka biri bu alanı değiştirdi. Sayfayı yenileyip karşılaştırın.', others: 'Başka bir kullanıcı bazı alanları güncelledi. Görmek için sayfayı yenileyin.', copied: 'Kopyalandı', uploading: 'Yükleniyor…', upfail: 'Yükleme başarısız', restored: 'Kaydedilmemiş girişleriniz geri yüklendi.', max: 'Paketiniz en fazla {n} dil içerir.' },
    en: { saving: 'Saving…', saved: 'Saved', failed: 'Could not save', retry: 'Retry', offline: 'Offline — changes are kept on this device', conflict: 'Someone else changed this field. Reload to compare.', others: 'Another user updated some fields. Reload to see them.', copied: 'Copied', uploading: 'Uploading…', upfail: 'Upload failed', restored: 'Your unsaved entries were restored.', max: 'Your package includes up to {n} languages.' },
    ru: { saving: 'Сохранение…', saved: 'Сохранено', failed: 'Не удалось сохранить', retry: 'Повторить', offline: 'Нет соединения — изменения хранятся на этом устройстве', conflict: 'Это поле изменил другой пользователь. Обновите страницу для сравнения.', others: 'Другой пользователь обновил некоторые поля. Обновите страницу.', copied: 'Скопировано', uploading: 'Загрузка…', upfail: 'Ошибка загрузки', restored: 'Несохранённые данные восстановлены.', max: 'Ваш пакет включает не более {n} языков.' }
  }[L] || {};

  var setState = function (cls, text, retryFn) {
    if (!stateEl) return;
    stateEl.className = 'save-state ' + cls;
    stateEl.textContent = text;
    if (retryFn) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'btn btn-xs'; b.textContent = MSG.retry;
      b.addEventListener('click', retryFn);
      stateEl.appendChild(document.createTextNode(' '));
      stateEl.appendChild(b);
    }
  };

  // Dil değişince form adımı ve girilmiş alanlar korunur (yerel kopya)
  var keyFor = function (form) {
    var path = location.pathname.replace(/^(.*?)\/(en|ru|tr)(\/|$)/, '$1$3');
    return 'ikz-form:' + path + ':' + (form.id || form.getAttribute('action') || '');
  };
  var snapshot = function (form) {
    var data = {};
    $$('input, textarea, select', form).forEach(function (el) {
      if (!el.name || el.type === 'password' || el.type === 'file' || el.type === 'hidden' || el.hasAttribute('data-nokeep')) return;
      if (el.type === 'checkbox' || el.type === 'radio') { data[el.name + '::' + el.value] = el.checked; }
      else data[el.name] = el.value;
    });
    return data;
  };
  var save = function (form) { try { sessionStorage.setItem(keyFor(form), JSON.stringify(snapshot(form))); } catch (e) {} };
  var restore = function (form) {
    var raw; try { raw = sessionStorage.getItem(keyFor(form)); } catch (e) {}
    if (!raw) return false;
    var data; try { data = JSON.parse(raw); } catch (e) { return false; }
    var changed = false;
    $$('input, textarea, select', form).forEach(function (el) {
      if (!el.name || el.type === 'password' || el.type === 'file' || el.type === 'hidden') return;
      if (el.type === 'checkbox' || el.type === 'radio') { var k = el.name + '::' + el.value; if (k in data && el.checked !== data[k]) { el.checked = data[k]; changed = true; markDirty(el); } }
      else if (el.name in data && el.value !== data[el.name]) { el.value = data[el.name]; changed = true; markDirty(el); }
    });
    return changed;
  };
  var clearSaved = function (form) { try { sessionStorage.removeItem(keyFor(form)); } catch (e) {} };
  var dirty = new Set();
  var markDirty = function (el) { if (el && el.name) dirty.add(el.name); };

  $$('form[data-keep]').forEach(function (form) {
    if (restore(form)) setState('info', MSG.restored);
    form.addEventListener('input', function (e) { markDirty(e.target); save(form); });
    form.addEventListener('change', function (e) { markDirty(e.target); save(form); });
    form.addEventListener('submit', function () { clearSaved(form); });
  });
  $$('[data-lang-switch]').forEach(function (a) {
    a.addEventListener('click', function () { $$('form[data-keep]').forEach(save); });
  });

  // Otomatik kayıt (yalnız değişen alanlar; alan düzeyinde çakışma denetimi sunucuda)
  $$('form[data-autosave]').forEach(function (form) {
    var url = form.getAttribute('data-autosave');
    var vEl = form.querySelector('[data-version]');
    var timer = null, inflight = false, queued = false, blocked = false;
    var send = function () {
      if (blocked) return;
      if (!dirty.size) return;
      if (inflight) { queued = true; return; }
      if (!navigator.onLine) { setState('warn', MSG.offline); return; }
      inflight = true;
      var fd = new FormData(form);
      fd.delete('_next'); fd.delete('_add_event'); fd.delete('_move');
      var names = Array.from(dirty);
      names.forEach(function (n) { fd.append('_dirty[]', n); });
      dirty.clear();
      setState('busy', MSG.saving);
      fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.json().then(function (j) { return { status: r.status, j: j }; }); })
        .then(function (res) {
          inflight = false;
          if (res.j && res.j.version && vEl) vEl.value = res.j.version;
          if (res.status === 409) { blocked = true; setState('error', MSG.conflict); return; }
          if (res.status >= 400) throw new Error('http');
          clearSaved(form);
          setState('ok', (res.j.others && res.j.others.length) ? MSG.saved + ' · ' + MSG.others : MSG.saved);
          if (queued) { queued = false; send(); }
        })
        .catch(function () {
          inflight = false;
          names.forEach(function (n) { dirty.add(n); });
          setState('error', MSG.failed, send);
        });
    };
    form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(send, 1400); });
    form.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(send, 600); });
    window.addEventListener('online', send);
    window.addEventListener('beforeunload', function (e) { if (dirty.size && !form.__submitting) { save(form); } });
    form.addEventListener('submit', function () { form.__submitting = true; });
  });

  // Çakışmada "onların değerini kullan"
  $$('[data-use-theirs]').forEach(function (b) {
    b.addEventListener('click', function () {
      var path = b.getAttribute('data-use-theirs');
      var name = { 'names.first': 'names_first', 'names.second': 'names_second', 'contact.name': 'contact_name' }[path];
      if (!name) { var m = path.match(/^texts\.(\w+)\.(\w+)$/); if (m) name = 'texts[' + m[1] + '][' + m[2] + ']'; }
      var el = name && document.querySelector('[name="' + name + '"]');
      if (el) { el.value = b.getAttribute('data-value'); markDirty(el); el.dispatchEvent(new Event('input', { bubbles: true })); }
    });
  });

  // İsim önizlemesi
  var a = document.querySelector('[data-name-preview="a"]'), bb = document.querySelector('[data-name-preview="b"]'), out = document.querySelector('[data-name-out]');
  if (a && bb && out) {
    var upd = function () {
      var ba = document.querySelector('input[name="names_order"][value="ba"]');
      var x = a.value.trim(), y = bb.value.trim();
      if (ba && ba.checked) { var t = x; x = y; y = t; }
      out.textContent = x && y ? x + ' & ' + y : (x || y);
    };
    document.addEventListener('input', upd); document.addEventListener('change', upd);
  }

  // Dil sınırı
  $$('input[name="languages[]"][data-max]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var max = +cb.getAttribute('data-max');
      var on = $$('input[name="languages[]"]:checked');
      if (on.length > max) { cb.checked = false; alert(MSG.max.replace('{n}', max)); }
    });
  });

  // Kopyala
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.getAttribute('data-copy');
      var done = function () { var o = b.innerHTML; b.textContent = MSG.copied; setTimeout(function () { b.innerHTML = o; }, 1600); };
      if (navigator.clipboard) navigator.clipboard.writeText(v).then(done, done);
      else { var i = document.createElement('textarea'); i.value = v; document.body.appendChild(i); i.select(); try { document.execCommand('copy'); } catch (e) {} i.remove(); done(); }
    });
  });

  // Onay iletişimi ve çift gönderim önleme
  $$('[data-confirm]').forEach(function (b) {
    b.addEventListener('click', function (e) { if (!confirm(b.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  $$('form[data-once]').forEach(function (f) {
    f.addEventListener('submit', function () { setTimeout(function () { $$('button[type="submit"], button:not([type])', f).forEach(function (b) { b.disabled = true; }); }, 0); });
  });

  // Fotoğraf yükleme: dosya başına durum, başarısız olanı yeniden dene
  $$('form[data-upload]').forEach(function (form) {
    var input = form.querySelector('input[type="file"]');
    var list = form.querySelector('[data-upload-list]');
    form.addEventListener('submit', function (e) {
      if (!window.fetch || !input.files.length) return;
      e.preventDefault();
      var files = Array.from(input.files);
      var pending = files.length;
      var finish = function () { if (--pending <= 0) setTimeout(function () { location.reload(); }, 600); };
      files.forEach(function (file) {
        var row = document.createElement('div');
        row.className = 'up-row';
        row.textContent = file.name + ' — ' + MSG.uploading;
        list.appendChild(row);
        var go = function () {
          var fd = new FormData();
          fd.append('_csrf', csrf());
          fd.append('photos[]', file);
          fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
              var r0 = j.results && j.results[0];
              if (r0 && r0.ok) { row.textContent = file.name + ' ✓'; row.className = 'up-row ok'; finish(); }
              else throw new Error((r0 && r0.error) || MSG.upfail);
            })
            .catch(function (err) {
              row.className = 'up-row err';
              row.textContent = file.name + ' — ' + (err.message || MSG.upfail) + ' ';
              var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-xs'; b.textContent = MSG.retry;
              b.addEventListener('click', function () { row.textContent = file.name + ' — ' + MSG.uploading; row.className = 'up-row'; go(); });
              row.appendChild(b);
            });
        };
        go();
      });
    });
  });
})();
