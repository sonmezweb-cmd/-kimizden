#!/usr/bin/env python3
"""
İkimizden — tema görselini katmanlara ayırır (geliştirme aracı; üretimde gerekmez).

Girdi: üç tema görseli (dikey, 9:16).
Çıktı:
  tools/opening-video/public/{slug}/  → Remotion açılış videosu katmanları (PNG)
  public/themes/{slug}/               → web için poster/önizleme/mühür/doku/kâğıt/ışık maskesi + theme.json

Kullanım: python3 tools/media/cut_layers.py tools/media/source/zeytin-varak.png tools/media/source/gul-muhru.png tools/media/source/sedef-kivrimi.png
"""
import json, os, sys
import numpy as np
from PIL import Image, ImageDraw, ImageFilter

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
W, H = 1080, 1920

THEMES = {
    'zeytin-varak': {
        'geo': {'flapLeft': 40.0, 'flapRight': 44.0, 'flapTipX': 50.0, 'flapTipY': 63.0, 'sealX': 49.0, 'sealY': 61.7, 'sealR': 18.8, 'pocketSide': 83.0, 'pocketTipY': 66.0},
        'colors': {'surface': '#b9cdb0', 'ink': '#2f4430', 'accent': '#a8893f', 'paper': '#f6f2e6', 'lining': '#7f9a78', 'paperEdge': '#b79a55'},
        'sheen': 'gold',
    },
    'gul-muhru': {
        'geo': {'flapLeft': 40.5, 'flapRight': 41.0, 'flapTipX': 50.0, 'flapTipY': 63.0, 'sealX': 50.0, 'sealY': 62.3, 'sealR': 17.8, 'pocketSide': 85.0, 'pocketTipY': 66.0},
        'colors': {'surface': '#5a1630', 'ink': '#4a1024', 'accent': '#c98a6b', 'paper': '#f5ede0', 'lining': '#2b0816', 'paperEdge': '#c08566'},
        'sheen': 'copper',
    },
    'sedef-kivrimi': {
        'geo': {'flapLeft': 37.0, 'flapRight': 35.5, 'flapTipX': 50.0, 'flapTipY': 61.5, 'sealX': 49.0, 'sealY': 61.5, 'sealR': 19.2, 'pocketSide': 84.0, 'pocketTipY': 66.0},
        'colors': {'surface': '#efe6ee', 'ink': '#4a3d5c', 'accent': '#a796c4', 'paper': '#fbf8f8', 'lining': '#d8cbe0', 'paperEdge': '#b9aed0'},
        'sheen': 'pearl',
    },
}


def smooth(x, a, b):
    t = np.clip((x - a) / (b - a), 0, 1)
    return t * t * (3 - 2 * t)


def blur(a, r):
    im = Image.fromarray(np.clip(a * 255, 0, 255).astype(np.uint8))
    return np.asarray(im.filter(ImageFilter.GaussianBlur(r)), np.float32) / 255


def poly_mask(pts, ss=3):
    im = Image.new('L', (W * ss, H * ss), 0)
    ImageDraw.Draw(im).polygon([(x * ss, y * ss) for x, y in pts], fill=255)
    return np.asarray(im.resize((W, H), Image.LANCZOS), np.float32) / 255


def flap_pts(g):
    return [(0, 0), (W, 0), (W, g['flapRight'] / 100 * H), (g['flapTipX'] / 100 * W, g['flapTipY'] / 100 * H), (0, g['flapLeft'] / 100 * H)]


def rgba(rgb, a):
    out = np.zeros((H, W, 4), np.uint8)
    out[..., :3] = np.clip(rgb, 0, 255).astype(np.uint8)
    out[..., 3] = np.clip(a * 255, 0, 255).astype(np.uint8)
    return Image.fromarray(out, 'RGBA')


def paper(colors, seed):
    rng = np.random.default_rng(seed)
    base = np.array(Image.new('RGB', (1, 1), colors['paper']).getpixel((0, 0)), np.float32)
    n1 = blur(rng.random((H, W)).astype(np.float32), 0.6) - 0.5
    n2 = blur(rng.random((H, W)).astype(np.float32), 2.2) - 0.5
    n3 = blur(rng.random((H // 8, W // 8)).astype(np.float32), 6)
    n3 = np.asarray(Image.fromarray((n3 * 255).astype(np.uint8)).resize((W, H), Image.BICUBIC), np.float32) / 255 - 0.5
    t = 1 + 0.05 * n1 + 0.06 * n2 + 0.035 * n3
    img = base[None, None, :] * t[..., None]
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    vign = 1 - 0.05 * (((xx - W / 2) / (W / 2)) ** 2 + ((yy - H / 2) / (H / 2)) ** 2)
    img *= vign[..., None]
    im = Image.fromarray(np.clip(img, 0, 255).astype(np.uint8))
    d = ImageDraw.Draw(im)
    edge = colors['paperEdge']
    d.rectangle([44, 44, W - 45, H - 45], outline=edge, width=3)
    d.rectangle([58, 58, W - 59, H - 59], outline=edge, width=1)
    return im


def build(slug, src):
    cfg = THEMES[slug]
    g = cfg['geo']
    img = Image.open(src).convert('RGB').resize((W, H), Image.LANCZOS)
    a = np.asarray(img, np.float32)
    lum = a @ np.array([0.3, 0.55, 0.15], np.float32)

    flap = poly_mask(flap_pts(g))
    sx, sy, sr = g['sealX'] / 100 * W, g['sealY'] / 100 * H, g['sealR'] / 100 * W
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    rr = np.sqrt((xx - sx) ** 2 + (yy - sy) ** 2)
    seal_a = smooth(rr, sr * 1.04, sr * 0.97)

    # Mühür: kendi gölgesiyle birlikte ayrı katman
    S = int(sr * 2.6)
    x0, y0 = int(sx - S / 2), int(sy - S / 2)
    seal_img = rgba(a, seal_a).crop((x0, y0, x0 + S, y0 + S))

    # Gövde: mühür alanı, çevre halkanın ortalama rengine eşitlenmiş dokuyla doldurulur
    py0 = int(g['pocketSide'] / 100 * H) + 40
    tp = a[py0:py0 + 300, int(W * 0.30):int(W * 0.30) + 300]
    tl = np.concatenate([tp, tp[:, ::-1]], 1); tl = np.concatenate([tl, tl[::-1]], 0)
    reps = (H // tl.shape[0] + 1, W // tl.shape[1] + 1, 1)
    tiled = np.tile(tl, reps)[:H, :W]
    ring = (rr > sr * 1.5) & (rr < sr * 1.8) & (flap < 0.5)
    ring_mean = a[ring].mean(0)
    tiled = tiled * (ring_mean / tiled.reshape(-1, 3).mean(0))[None, None, :]
    fill_a = smooth(rr, sr * 1.45, sr * 1.0)[..., None]
    body_rgb = a * (1 - fill_a) + tiled * fill_a
    body = rgba(body_rgb, 1 - flap)
    flap_img = rgba(a, flap)
    flap_noseal = rgba(a, flap * smooth(rr, sr * 0.97, sr * 1.01))

    # Işık maskesi: yalnız kabartma desende (tema rengine göre)
    hi = lum - blur(lum / 255, 8) * 255
    r_, g_, b_ = a[..., 0], a[..., 1], a[..., 2]
    if cfg['sheen'] == 'gold':
        crit = smooth((r_ + g_) / 2 - b_, 22, 55) * smooth(lum, 110, 185)
    elif cfg['sheen'] == 'copper':
        crit = smooth(r_ - b_, 25, 60) * smooth(lum, 110, 190)
    else:
        crit = smooth(hi, 4, 22) * smooth(lum, 200, 240)
    sheen = np.clip(crit * flap * (rr > sr * 1.05), 0, 1)
    sheen = blur(sheen, 1.2)

    # Doku: alt cepte temiz bölgeden döşenebilir yama
    py0 = int(g['pocketSide'] / 100 * H) + 40
    patch = a[py0:py0 + 300, int(W * 0.30):int(W * 0.30) + 300]
    tile = np.concatenate([patch, patch[:, ::-1]], 1)
    tile = np.concatenate([tile, tile[::-1]], 0)
    tex = Image.fromarray(np.clip(tile, 0, 255).astype(np.uint8))

    pap = paper(cfg['colors'], abs(hash(slug)) % 1000)

    # --- Remotion katmanları
    rd = os.path.join(ROOT, 'tools', 'opening-video', 'public', slug)
    os.makedirs(rd, exist_ok=True)
    img.save(os.path.join(rd, 'poster.png'))
    body.save(os.path.join(rd, 'body.png'))
    flap_noseal.save(os.path.join(rd, 'flap.png'))
    seal_img.save(os.path.join(rd, 'seal.png'))
    pap.save(os.path.join(rd, 'paper.png'))
    tex.save(os.path.join(rd, 'texture.png'))
    rgba(np.full((H, W, 3), 255, np.float32), sheen).save(os.path.join(rd, 'sheen.png'))

    # --- Web varlıkları
    wd = os.path.join(ROOT, 'public', 'themes', slug)
    os.makedirs(os.path.join(wd, 'audio'), exist_ok=True)
    img.save(os.path.join(wd, 'poster.webp'), 'WEBP', quality=86, method=6)
    img.resize((640, 1138), Image.LANCZOS).save(os.path.join(wd, 'preview.webp'), 'WEBP', quality=84, method=6)
    img.resize((360, 640), Image.LANCZOS).save(os.path.join(wd, 'poster-small.webp'), 'WEBP', quality=80, method=6)
    seal_img.save(os.path.join(wd, 'seal.webp'), 'WEBP', quality=90, method=6)
    flap_noseal.save(os.path.join(wd, 'ornament.webp'), 'WEBP', quality=84, method=6)
    body.save(os.path.join(wd, 'body.webp'), 'WEBP', quality=84, method=6)
    tex.save(os.path.join(wd, 'texture.webp'), 'WEBP', quality=82, method=6)
    pap.save(os.path.join(wd, 'paper.webp'), 'WEBP', quality=84, method=6)
    rgba(np.full((H, W, 3), 255, np.float32), sheen).resize((540, 960), Image.LANCZOS).save(os.path.join(wd, 'sheen-mask.webp'), 'WEBP', quality=80, method=6)
    # parıltı noktaları: ışık maskesinin en parlak, birbirinden uzak noktaları
    sm = blur(sheen, 3)
    pts = []
    flat = np.argsort(sm.ravel())[::-1]
    for idx in flat[:200000:50]:
        y, x = divmod(int(idx), W)
        if sm[y, x] < 0.05: break
        if all((x - px) ** 2 + (y - py) ** 2 > 140 ** 2 for px, py in pts):
            pts.append((x, y))
        if len(pts) >= 12: break
    sparkles = [[round(x / W * 100, 2), round(y / H * 100, 2)] for x, y in pts]
    meta = {'sparkles': sparkles, 'geometry': g, 'sealSize': S / W * 100, 'colors': cfg['colors'], 'sheen': cfg['sheen']}
    with open(os.path.join(wd, 'theme.json'), 'w') as f:
        json.dump(meta, f, ensure_ascii=False, indent=2)
    with open(os.path.join(rd, 'theme.json'), 'w') as f:
        json.dump(meta, f, ensure_ascii=False, indent=2)
    print(slug, 'ok')


if __name__ == '__main__':
    z, gl, sd = sys.argv[1:4]
    build('zeytin-varak', z)
    build('gul-muhru', gl)
    build('sedef-kivrimi', sd)
