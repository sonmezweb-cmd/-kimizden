// Görsel kontrol: node tests/e2e/shots.js <base> <outdir>
const { chromium } = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8080';
const out = process.argv[3] || '/tmp';
(async () => {
  const b = await chromium.launch();
  const shot = async (name, url, vp, fn) => {
    const p = await b.newPage({ viewport: vp, deviceScaleFactor: 1 });
    const errs = [];
    p.on('pageerror', e => errs.push(e.message));
    p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
    await p.goto(base + url, { waitUntil: 'networkidle' });
    if (fn) await fn(p);
    await p.screenshot({ path: `${out}/${name}.png` });
    if (errs.length) console.log(name, 'ERRORS', errs);
    await p.close();
  };
  const scrollTo = (frac) => async (p) => {
    await p.evaluate((f) => { const e = document.getElementById('env'); const s = e.querySelector('.env-sticky'); window.scrollTo(0, (e.offsetHeight - s.offsetHeight) * f); }, frac);
    await p.waitForTimeout(900);
  };
  await shot('home-390', '/', { width: 390, height: 844 });
  await shot('home-1440', '/', { width: 1440, height: 900 });
  for (const f of [0, 0.2, 0.45, 0.7, 0.85, 1]) await shot('inv-gul-' + f, '/preview/gul-muhru', { width: 390, height: 844 }, scrollTo(f));
  await shot('inv-gul-letter', '/preview/gul-muhru', { width: 390, height: 844 }, async (p) => { await p.evaluate(() => window.scrollTo(0, document.getElementById('letter').offsetTop + 600)); await p.waitForTimeout(1200); });
  await shot('inv-zeytin-1440', '/preview/zeytin-varak', { width: 1440, height: 900 }, scrollTo(0.45));
  await b.close();
})();
