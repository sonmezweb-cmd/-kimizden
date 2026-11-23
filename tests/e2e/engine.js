// İkimizden — tarayıcı senaryoları F ve I (Playwright + yerleşik Chromium).
// Kullanım: node tests/e2e/engine.js http://127.0.0.1:8080 tests/_out/e2e.json tests/_out/shots
const { chromium, devices } = require('playwright');
const fs = require('fs');
const base = process.argv[2] || 'http://127.0.0.1:8080';
const outJson = process.argv[3] || 'tests/_out/e2e.json';
const shots = process.argv[4] || 'tests/_out/shots';
fs.mkdirSync(shots, { recursive: true });
const results = [];
let scen = '';
const check = (name, ok, detail = '') => { results.push({ scenario: scen, check: name, result: ok ? 'Geçti' : 'Kaldı', detail: ok ? '' : String(detail) }); console.log((ok ? '  ✓ ' : '  ✗ ') + name + (ok ? '' : ' — ' + detail)); };
const URL = '/preview/gul-muhru';
const progress = (p) => p.evaluate(() => parseFloat(document.getElementById('env').style.getPropertyValue('--p') || '0'));
const scrollFrac = async (p, f, wait = 500) => { await p.evaluate((f) => { const e = document.getElementById('env'); window.scrollTo(0, (e.offsetHeight - innerHeight) * f); }, f); await p.waitForTimeout(wait); };

(async () => {
  const b = await chromium.launch();
  const page = async (opts = {}, url = URL) => {
    const ctx = await b.newContext(opts);
    const p = await ctx.newPage();
    p.errs = []; p.bad = [];
    p.on('pageerror', e => p.errs.push(e.message));
    p.on('response', r => { if (r.status() >= 400) p.bad.push(r.status() + ' ' + r.url()); });
    await p.goto(base + url, { waitUntil: 'networkidle' });
    return p;
  };

  scen = 'F. Video ve ses dayanıklılığı';
  let p = await page({ viewport: { width: 390, height: 844 } });
  check('Video modunda açılış (dikey)', await p.evaluate(() => document.getElementById('env').getAttribute('data-mode')) === 'video');
  await scrollFrac(p, 0.5, 900);
  const t1 = await p.evaluate(() => document.querySelector('.env-video').currentTime);
  check('İleri kaydırma videoyu ilerletir (%50 ≈ 4 sn)', Math.abs(t1 - 4) < 0.3, t1);
  await scrollFrac(p, 0.2, 900);
  const t2 = await p.evaluate(() => document.querySelector('.env-video').currentTime);
  check('Geri kaydırma geri sarar (%20 ≈ 1,6 sn)', Math.abs(t2 - 1.6) < 0.3, t2);
  // hızlı kaydırma: art arda 30 hedef, en güncele yetişir
  await p.evaluate(async () => { const e = document.getElementById('env'); const H = e.offsetHeight - innerHeight; for (let i = 0; i < 30; i++) { window.scrollTo(0, H * Math.random()); await new Promise(r => requestAnimationFrame(r)); } window.scrollTo(0, H * 0.75); });
  await p.waitForTimeout(1200);
  const t3 = await p.evaluate(() => document.querySelector('.env-video').currentTime);
  check('Hızlı kaydırmada seek birikmez, son hedefe yetişir (%75 ≈ 6 sn)', Math.abs(t3 - 6) < 0.3, t3);
  check('Video sessiz ve satır içi', await p.evaluate(() => { const v = document.querySelector('.env-video'); return v.muted && v.playsInline; }));
  await scrollFrac(p, 0.85, 600);
  const o85 = await p.evaluate(() => +getComputedStyle(document.getElementById('env-text')).opacity);
  await scrollFrac(p, 0.5, 400);
  const o50 = await p.evaluate(() => +getComputedStyle(document.getElementById('env-text')).opacity);
  check('İsim/tarih %80–90 arasında belirir (%50’de gizli)', o85 > 0.3 && o50 < 0.05, `${o85}/${o50}`);
  await scrollFrac(p, 1, 600);
  const box = await p.evaluate(() => { const r = document.getElementById('env-text').getBoundingClientRect(); return { x1: r.left / innerWidth, x2: r.right / innerWidth, y1: r.top / innerHeight, y2: r.bottom / innerHeight }; });
  check('Metin alanı x %12–88, y %28–68 içinde', box.x1 >= 0.115 && box.x2 <= 0.885 && box.y1 >= 0.275 && box.y2 <= 0.685, JSON.stringify(box));
  check('Ses dosyaları "Sesi aç" olmadan indirilmez', !(await p.evaluate(() => performance.getEntriesByType('resource').some(e => /\/audio\//.test(e.name)))));
  check('Hata/404 yok', p.errs.length === 0 && p.bad.length === 0, p.errs.concat(p.bad).join(' | '));
  await p.context().close();

  // ses eşikleri: sahte ses bağlamıyla sayım
  p = await page({ viewport: { width: 390, height: 844 } });
  await p.evaluate(() => {
    const env = window.__ikzEnv; window.__plays = [];
    env.sounds.enabled = true; env.sounds.ctx = { createBufferSource: () => ({ connect: (g) => g, start: () => {} }), createGain: () => ({ gain: {}, connect: () => ({}) }), destination: {} };
    ['ornament', 'seal', 'flap', 'paper'].forEach(k => env.sounds.buffers[k] = { k });
    const orig = env.sounds.cross.bind(env.sounds);
    const proto = Object.getPrototypeOf(env.sounds);
    env.sounds.ctx.createBufferSource = () => ({ set buffer(b) { window.__plays.push(b.k); }, connect: (g) => g, start: () => {} });
  });
  for (let f = 0; f <= 1.0001; f += 0.02) await scrollFrac(p, f, 220);
  let plays = await p.evaluate(() => window.__plays.slice());
  check('İleri geçişte dört ses sırayla birer kez (%14, %30, %38, %61)', plays.join(',') === 'ornament,seal,flap,paper', plays.join(','));
  for (let f = 1; f >= 0; f -= 0.05) await scrollFrac(p, f, 60);
  for (let f = 0; f <= 1; f += 0.05) await scrollFrac(p, f, 200);
  await scrollFrac(p, 0.31, 200); await scrollFrac(p, 0.29, 200); await scrollFrac(p, 0.31, 200);
  plays = await p.evaluate(() => window.__plays.slice());
  check('Geri kaydırma ve eşik üzerinde küçük hareketler sesi tekrarlamaz', plays.length === 4, plays.join(','));
  await p.context().close();
  p = await page({ viewport: { width: 390, height: 844 } });
  await p.evaluate(() => { const env = window.__ikzEnv; window.__plays = []; env.sounds.enabled = true; env.sounds.ctx = { createBufferSource: () => ({ set buffer(b) { window.__plays.push(b.k); }, connect: (g) => g, start: () => {} }), createGain: () => ({ gain: {}, connect: () => ({}) }), destination: {} }; ['ornament', 'seal', 'flap', 'paper'].forEach(k => env.sounds.buffers[k] = { k }); });
  await p.click('#env-skip');
  await p.waitForTimeout(500);
  plays = await p.evaluate(() => window.__plays.slice());
  check('"Animasyonu geç" ile sona atlamada dört ses arka arkaya çalmaz', plays.length === 0, plays.join(','));
  check('Animasyonu geç → ilerleme 1 ve odak başlıkta', (await progress(p)) > 0.99 && await p.evaluate(() => document.activeElement && document.activeElement.id === 'inv-title'));
  await p.context().close();

  // Sesi aç: gerçek dosyalar 200 döner
  p = await page({ viewport: { width: 390, height: 844 } });
  await p.click('#env-sound');
  await p.waitForTimeout(1500);
  check('"Sesi aç" dört ses dosyasını yükler (404 yok)', p.bad.length === 0 && (await p.evaluate(() => performance.getEntriesByType('resource').filter(e => /\/audio\//.test(e.name)).length)) === 4, p.bad.join(' '));
  check('Ses düğmesi durumu (aria-pressed)', await p.getAttribute('#env-sound', 'aria-pressed') === 'true');
  await p.context().close();

  // mühre basma
  p = await page({ viewport: { width: 390, height: 844 } });
  const sealBox = await p.locator('#env-seal').boundingBox();
  const sc = await p.evaluate(() => { const g = window.__ikzEnv.G; return { x: g.seal[0], y: g.seal[1] }; });
  check('Mühür düğmesi videodaki mühürle aynı yerde', sealBox && Math.abs(sealBox.x + sealBox.width / 2 - sc.x) < 2 && Math.abs(sealBox.y + sealBox.height / 2 - sc.y) < 2, JSON.stringify(sealBox));
  await p.click('#env-seal');
  await p.waitForTimeout(150);
  check('Mühre basınca basma efekti', await p.evaluate(() => document.getElementById('env-seal').classList.contains('pressed')));
  await p.waitForTimeout(4200);
  check('Basma sonrası açılış oynar (ilerleme 1)', (await progress(p)) > 0.99, await progress(p));
  await p.screenshot({ path: `${shots}/after-seal.png` });
  await p.context().close();

  // klavye
  p = await page({ viewport: { width: 390, height: 844 } });
  await p.focus('[data-env-open]');
  await p.keyboard.press('Enter');
  await p.waitForTimeout(4000);
  check('"Kaydırarak aç" klavyeyle (Enter) çalışır', (await progress(p)) > 0.99);
  await p.context().close();

  // video yok / yüklenemedi / düşük veri
  p = await page({ viewport: { width: 390, height: 844 } }, URL + '?novideo=1');
  check('Video yok/düşük veri: poster maskeli CSS açılış', await p.evaluate(() => document.getElementById('env').getAttribute('data-mode')) === 'poster');
  await scrollFrac(p, 0.5, 400);
  const ang = await p.evaluate(() => document.querySelector('.L-flapwrap').style.transform);
  check('CSS açılışta kapak döner ve mühür kapakla birlikte', /rotateX\((\d+)/.test(ang) && +ang.match(/rotateX\(([\d.]+)/)[1] > 60, ang);
  await scrollFrac(p, 1, 500);
  await p.screenshot({ path: `${shots}/novideo-end.png` });
  check('CSS açılışta 404 yok', p.bad.length === 0, p.bad.join(' '));
  await p.context().close();
  {
    const ctx = await b.newContext({ viewport: { width: 390, height: 844 } });
    const p2 = await ctx.newPage();
    await p2.route(/opening\.(mp4|webm)/, r => r.abort());
    await p2.goto(base + URL, { waitUntil: 'load' });
    await p2.waitForTimeout(1500);
    check('Video yüklenemezse CSS sürüme geçer', await p2.evaluate(() => document.getElementById('env').getAttribute('data-mode')) === 'poster');
    await ctx.close();
  }
  p = await page({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
  check('Hareket azaltma: hafif sürüm (220svh yok)', await p.evaluate(() => document.getElementById('env').getAttribute('data-mode') === 'lite' && document.getElementById('env').offsetHeight <= innerHeight + 2));
  await p.click('[data-env-open]');
  await p.waitForTimeout(300);
  check('Hafif sürümde "Aç" isimleri gösterir', await p.evaluate(() => +getComputedStyle(document.getElementById('env-text')).opacity) > 0.9);
  await p.context().close();
  p = await page({ viewport: { width: 390, height: 844 }, javaScriptEnabled: false });
  const nojs = await p.evaluate(() => ({ title: document.querySelector('h1').innerText, date: !!document.querySelector('.env-date'), rsvp: !!document.getElementById('rsvp'), addr: !!document.querySelector('.event-address'), vis: getComputedStyle(document.getElementById('env-text')).opacity }));
  check('JavaScript kapalıyken isim, tarih, adres ve RSVP erişilebilir', nojs.title.includes('İpek') && nojs.date && nojs.rsvp && nojs.addr && +nojs.vis === 1, JSON.stringify(nojs));
  await p.screenshot({ path: `${shots}/nojs.png`, fullPage: false });
  await p.context().close();

  scen = 'I. Ekran genişlikleri ve cihazlar';
  const pages = ['/', '/themes', '/themes/sedef-kivrimi', '/packages', '/faq', '/contact', '/login', '/register', '/ru/', '/preview/zeytin-varak', '/en/preview/sedef-kivrimi'];
  for (const w of [320, 390, 768, 1440]) {
    let overflow = [];
    for (const u of pages) {
      const q = await page({ viewport: { width: w, height: w > 1000 ? 900 : 800 } }, u);
      const ov = await q.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      if (ov > 1) overflow.push(u + ':' + ov);
      if (u === '/' || u === '/preview/zeytin-varak') await q.screenshot({ path: `${shots}/w${w}${u.replace(/\W+/g, '_')}.png` });
      await q.context().close();
    }
    check(`${w}px: yatay taşma yok (${pages.length} sayfa)`, overflow.length === 0, overflow.join(', '));
  }
  // masaüstü: dikey video esnetilmez
  p = await page({ viewport: { width: 1440, height: 900 } });
  check('Masaüstü (yatay): dikey video esnetilmez → kenarlara uzanan CSS zarf', await p.evaluate(() => document.getElementById('env').getAttribute('data-mode')) === 'composed');
  await p.context().close();
  // mobil yatay
  p = await page({ ...devices['Pixel 7 landscape'] });
  await scrollFrac(p, 1, 600);
  const fits = await p.evaluate(() => { const t = document.getElementById('env-text'); return t.scrollHeight <= t.clientHeight + 1 || document.getElementById('env').classList.contains('names-flow'); });
  check('Mobil yatay ekranda isimler kesilmez', fits);
  await p.screenshot({ path: `${shots}/landscape.png` });
  await p.context().close();
  p = await page({ ...devices['iPhone 13'] });
  check('iPhone 13 emülasyonu (WebKit değil, Chromium) açılış modu video', await p.evaluate(() => document.getElementById('env').getAttribute('data-mode')) === 'video');
  await p.context().close();

  await b.close();
  fs.writeFileSync(outJson, JSON.stringify(results, null, 2));
  const pass = results.filter(r => r.result === 'Geçti').length;
  console.log(`\nSonuç: ${pass} / ${results.length} geçti`);
  process.exit(pass === results.length ? 0 : 1);
})();
