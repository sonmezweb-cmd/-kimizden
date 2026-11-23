#!/usr/bin/env python3
"""
İkimizden — tema medya üreticisi (yalnız geliştirme aracı, üretimde gerekmez).

Çıktılar (public/themes/{slug}/):
  poster.webp       1080x1920 ilk kare / video yoksa açılışın kaynağı
  preview.webp      640px katalog görseli
  texture.webp      kenarlara uzatılan malzeme dokusu (masaüstü uyarlaması)
  ornament.webp     kapak kabartması (şeffaf)
  seal.webp         mühür (şeffaf)
  paper.webp        iç kâğıt
  sheen-mask.webp   ışık geçişinin yalnız kabartma desende görünmesi için maske
  theme.json        geometri (poster koordinatlarında yüzde)

Kullanım: python3 tools/media/build_media.py <gul_video.mp4> <gul_still.png>
"""
import json, math, os, subprocess, sys, tempfile
import numpy as np
from PIL import Image, ImageDraw, ImageFilter

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
OUT = os.path.join(ROOT, 'public', 'themes')
W, H = 1080, 1920

# Ortak zarf geometrisi (poster yüzdeleri) — Gül Mührü görselinden ölçüldü,
# üretilen iki tema da aynı kesimle çizilir.
GEOM = {
    'flapLeft': 40.5, 'flapRight': 41.0, 'flapTipX': 50.0, 'flapTipY': 63.0,
    'sealX': 50.0, 'sealY': 62.3, 'sealR': 17.6,      # sealR: genişliğin yüzdesi
    'pocketSide': 85.0, 'pocketTipY': 66.0,
}


def save_webp(img, path, q=82, lossless=False):
    img.save(path, 'WEBP', quality=q, method=6, lossless=lossless)


def smooth(x, a, b):
    t = np.clip((x - a) / (b - a), 0, 1)
    return t * t * (3 - 2 * t)


def blur(arr, r):
    im = Image.fromarray(np.clip(arr * 255, 0, 255).astype(np.uint8)) if arr.dtype != np.uint8 else Image.fromarray(arr)
    return np.asarray(im.filter(ImageFilter.GaussianBlur(r)), dtype=np.float32) / 255.0


def blurf(arr, r):
    """float dizi için yüksek hassasiyetli bulanıklık (16 bit)"""
    # üç geçişli kutu bulanıklığı ≈ gauss
    a = arr.astype(np.float32)
    if r <= 0:
        return a.copy()
    k = max(1, int(round(r * 0.9)))
    for _ in range(3):
        for ax in (0, 1):
            pad = [(0, 0), (0, 0)]
            pad[ax] = (k + 1, k)
            p = np.pad(a, pad, mode='edge')
            c = np.cumsum(p, axis=ax, dtype=np.float64)
            if ax == 0:
                a = ((c[2 * k + 1:] - c[:-2 * k - 1]) / (2 * k + 1)).astype(np.float32)
            else:
                a = ((c[:, 2 * k + 1:] - c[:, :-2 * k - 1]) / (2 * k + 1)).astype(np.float32)
    return a


def noise(rng, h, w, r):
    n = rng.standard_normal((h, w)).astype(np.float32)
    n = blurf(n, r)
    n = (n - n.mean()) / (n.std() + 1e-6)
    return n


def polygon_mask(points, w=W, h=H, ss=2):
    im = Image.new('L', (w * ss, h * ss), 0)
    ImageDraw.Draw(im).polygon([(x * ss, y * ss) for x, y in points], fill=255)
    return np.asarray(im.resize((w, h), Image.LANCZOS), dtype=np.float32) / 255.0


def geom_px():
    g = GEOM
    return {
        'flap': [(0, 0), (W, 0), (W, g['flapRight'] / 100 * H), (g['flapTipX'] / 100 * W, g['flapTipY'] / 100 * H), (0, g['flapLeft'] / 100 * H)],
        'pocket': [(0, g['pocketSide'] / 100 * H), (W / 2, g['pocketTipY'] / 100 * H), (W, g['pocketSide'] / 100 * H), (W, H), (0, H)],
        'seal': (g['sealX'] / 100 * W, g['sealY'] / 100 * H, g['sealR'] / 100 * W),
    }


def shade(height, strength=6.0, light=(-0.55, -0.65, 0.55)):
    gy, gx = np.gradient(height)
    nx, ny, nz = -gx * strength, -gy * strength, np.ones_like(height)
    ln = np.sqrt(nx * nx + ny * ny + nz * nz)
    nx, ny, nz = nx / ln, ny / ln, nz / ln
    l = np.array(light, dtype=np.float32); l /= np.linalg.norm(l)
    diff = np.clip(nx * l[0] + ny * l[1] + nz * l[2], 0, 1)
    # Blinn-Phong yarım vektör (bakış z yönünde)
    hv = l + np.array([0, 0, 1.0]); hv /= np.linalg.norm(hv)
    spec = np.clip(nx * hv[0] + ny * hv[1] + nz * hv[2], 0, 1)
    return diff, spec, (nx, ny, nz)


def composite(base, color, alpha):
    a = alpha[..., None]
    return base * (1 - a) + color * a


# ---------------------------------------------------------------- malzemeler

def velvet(rng, h, w, c_dark, c_light):
    fine = noise(rng, h, w, 0.6)
    mid = noise(rng, h, w, 3.0)
    low = noise(rng, h, w, 60.0)
    streak = blurf(rng.standard_normal((h, w)).astype(np.float32), 0.8)
    streak = np.asarray(Image.fromarray(((streak - streak.min()) / (np.ptp(streak) + 1e-6) * 255).astype(np.uint8)).filter(ImageFilter.BoxBlur(0)), dtype=np.float32) / 255
    t = 0.5 + 0.09 * fine + 0.07 * mid + 0.12 * low + 0.04 * (streak - 0.5)
    t = np.clip(t, 0, 1)[..., None]
    return np.asarray(c_dark, np.float32) * (1 - t) + np.asarray(c_light, np.float32) * t


def paper_tex(rng, h, w, base, fiber=0.025, tint=None):
    fine = noise(rng, h, w, 0.5)
    fib = noise(rng, h, w, 1.5)
    low = noise(rng, h, w, 120)
    t = 1 + fiber * fine + fiber * 0.8 * fib + 0.012 * low
    img = np.asarray(base, np.float32)[None, None, :] * t[..., None]
    if tint is not None:
        img = img + tint
    return np.clip(img, 0, 255)


# ---------------------------------------------------------------- çizim yardımcıları

def bezier(p0, p1, p2, p3, n=200):
    t = np.linspace(0, 1, n)[:, None]
    return ((1 - t) ** 3) * p0 + 3 * ((1 - t) ** 2) * t * p1 + 3 * (1 - t) * t * t * p2 + t ** 3 * p3


def draw_stroke(hmap_img, pts, width, ss):
    d = ImageDraw.Draw(hmap_img)
    pts = [(float(x) * ss, float(y) * ss) for x, y in pts]
    d.line(pts, fill=255, width=int(width * ss), joint='curve')
    r = width * ss / 2
    for x, y in (pts[0], pts[-1]):
        d.ellipse([x - r, y - r, x + r, y + r], fill=255)


def leaf(hmap, vein, cx, cy, ang, L, Wd, Hh=1.0):
    """yaprak: (cx,cy) sap noktası, ang yönü"""
    ca, sa = math.cos(ang), math.sin(ang)
    x0, x1 = int(max(0, cx - L - Wd)), int(min(hmap.shape[1], cx + L + Wd))
    y0, y1 = int(max(0, cy - L - Wd)), int(min(hmap.shape[0], cy + L + Wd))
    if x1 <= x0 or y1 <= y0:
        return
    yy, xx = np.mgrid[y0:y1, x0:x1].astype(np.float32)
    dx, dy = xx - cx, yy - cy
    u = (dx * ca + dy * sa) / L
    v = (-dx * sa + dy * ca)
    inside_u = (u > 0) & (u < 1)
    half = Wd * np.power(np.clip(np.sin(np.pi * np.clip(u, 0, 1)), 0, 1), 0.75) * (1 - 0.25 * u)
    vv = np.where(half > 0.5, v / np.maximum(half, 1e-3), 9)
    inside = inside_u & (np.abs(vv) < 1)
    dome = np.sqrt(np.clip(1 - vv * vv, 0, 1)) * Hh
    rib = np.exp(-(v / (Wd * 0.07)) ** 2) * 0.18 * Hh
    side = (np.abs(np.sin((u * 7.0 - np.abs(vv) * 1.2) * np.pi)) ** 18) * 0.06 * Hh
    hh = np.where(inside, 0.35 * Hh + dome * 0.65 - side + rib * 0.0, 0)
    # orta damar: hafif oluk + ince sırt
    groove = np.where(inside, np.exp(-(v / (Wd * 0.05)) ** 2) * 0.12 * Hh, 0)
    hh = hh - groove
    sub = hmap[y0:y1, x0:x1]
    np.maximum(sub, hh, out=sub)
    vm = np.where(inside, np.exp(-(v / (Wd * 0.06)) ** 2) * (1 - u) ** 0.3, 0)
    vm += np.where(inside, (np.abs(np.sin((u * 7.0 - np.abs(vv) * 1.2) * np.pi)) ** 24) * 0.7 * (np.abs(vv) < 0.85), 0)
    vs = vein[y0:y1, x0:x1]
    np.maximum(vs, np.clip(vm, 0, 1), out=vs)


def ellipse_dome(hmap, cx, cy, rx, ry, ang, Hh=1.0):
    x0, x1 = int(cx - rx - ry - 2), int(cx + rx + ry + 2)
    y0, y1 = int(cy - rx - ry - 2), int(cy + rx + ry + 2)
    yy, xx = np.mgrid[y0:y1, x0:x1].astype(np.float32)
    ca, sa = math.cos(ang), math.sin(ang)
    dx, dy = xx - cx, yy - cy
    u = (dx * ca + dy * sa) / rx
    v = (-dx * sa + dy * ca) / ry
    r2 = u * u + v * v
    hh = np.where(r2 < 1, np.sqrt(np.clip(1 - r2, 0, 1)) * Hh, 0)
    sub = hmap[y0:y1, x0:x1]
    np.maximum(sub, hh, out=sub)


def wax_seal(rng, R, wax_dark, wax_light, motif, size=None):
    """mühür: şeffaf RGBA dizi (size x size)"""
    S = size or int(R * 2.5)
    c = S / 2
    yy, xx = np.mgrid[0:S, 0:S].astype(np.float32)
    dx, dy = xx - c, yy - c
    r = np.sqrt(dx * dx + dy * dy)
    th = np.arctan2(dy, dx)
    ph = rng.uniform(0, 6.28, 3)
    edge = R * (1 + 0.045 * np.sin(7 * th + ph[0]) + 0.025 * np.sin(11 * th + ph[1]) + 0.015 * np.sin(19 * th + ph[2]))
    rn = r / edge
    body = np.clip(1 - rn, 0, 1)
    h = np.where(rn < 1, np.power(np.clip(1 - rn ** 6, 0, 1), 0.5) * 0.7, 0)
    # iç baskı alanı
    ri = R * 0.70
    rim = smooth(r, ri - 6, ri + 4)
    h = h - (1 - rim) * 0.32 + np.exp(-((r - ri) / 5.0) ** 2) * 0.12
    mot = np.zeros((S, S), np.float32)
    motif(mot, c, c, ri)
    h = h + mot * 0.22 * (r < ri)
    h = blurf(h, 1.2) * 60
    diff, spec, _ = shade(h, strength=0.09)
    col = np.asarray(wax_dark, np.float32) * (1 - diff[..., None]) + np.asarray(wax_light, np.float32) * diff[..., None]
    col = col * (0.62 + 0.5 * diff[..., None]) + (spec[..., None] ** 40) * 120
    alpha = smooth(rn, 1.0, 0.975)
    # yumuşak gölge
    sh = blurf((rn < 1.03).astype(np.float32), R * 0.08) * 0.55
    rgba = np.zeros((S, S, 4), np.float32)
    rgba[..., :3] = np.clip(col, 0, 255) * alpha[..., None]
    a = alpha + sh * (1 - alpha)
    rgba[..., :3] = np.where(a[..., None] > 0, rgba[..., :3] / np.maximum(a[..., None], 1e-4), 0)
    rgba[..., 3] = np.clip(a, 0, 1) * 255
    return rgba


# ---------------------------------------------------------------- ortak zarf katmanları

def envelope_shading(img, accent_hi, accent_lo, side_dark=0.92, bottom_light=1.04):
    g = geom_px()
    flap = polygon_mask(g['flap'])
    pocket = polygon_mask(g['pocket'])
    sides = np.clip(1 - flap - pocket, 0, 1)
    out = img.copy()
    out *= (1 * flap + side_dark * sides + bottom_light * pocket)[..., None]
    # kapak altında gölge
    shadow = blurf(flap, 14) - flap
    shadow = np.clip(shadow, 0, 1)
    out *= (1 - shadow * 0.9)[..., None]
    # kenar ışığı (kapak kenarı ve cep çizgileri)
    for m, amt, col in ((flap, 0.45, accent_hi), (pocket, 0.35, accent_hi)):
        e = np.abs(blurf(m, 1.2) - m)
        e = np.clip(e * 4, 0, 1)
        out = composite(out, np.asarray(col, np.float32), e * amt)
    return np.clip(out, 0, 255)


def ornament_layers(img_rgb, ornament_rgba, seal_rgba):
    """poster = taban + kabartma + mühür"""
    out = img_rgb.copy()
    a = ornament_rgba[..., 3:4] / 255
    out = out * (1 - a) + ornament_rgba[..., :3] * a
    g = geom_px()
    sx, sy, sr = g['seal']
    S = seal_rgba.shape[0]
    x0, y0 = int(sx - S / 2), int(sy - S / 2)
    sub = out[y0:y0 + S, x0:x0 + S]
    sa = seal_rgba[..., 3:4] / 255
    out[y0:y0 + S, x0:x0 + S] = sub * (1 - sa) + seal_rgba[..., :3] * sa
    return out


def to_img(a):
    return Image.fromarray(np.clip(a, 0, 255).astype(np.uint8))


def write_common(slug, poster, ornament_rgba, seal_rgba, texture, paper, sheen, colors, extra=None):
    d = os.path.join(OUT, slug)
    os.makedirs(os.path.join(d, 'audio'), exist_ok=True)
    p = to_img(poster)
    save_webp(p, os.path.join(d, 'poster.webp'), q=84)
    save_webp(p.resize((640, 1138), Image.LANCZOS), os.path.join(d, 'preview.webp'), q=82)
    save_webp(p.resize((360, 640), Image.LANCZOS), os.path.join(d, 'poster-small.webp'), q=78)
    Image.fromarray(np.clip(ornament_rgba, 0, 255).astype(np.uint8), 'RGBA').save(os.path.join(d, 'ornament.webp'), 'WEBP', quality=84, method=6)
    Image.fromarray(np.clip(seal_rgba, 0, 255).astype(np.uint8), 'RGBA').save(os.path.join(d, 'seal.webp'), 'WEBP', quality=88, method=6)
    save_webp(to_img(texture), os.path.join(d, 'texture.webp'), q=80)
    save_webp(to_img(paper), os.path.join(d, 'paper.webp'), q=82)
    m = np.zeros((H, W, 4), np.uint8)
    m[..., :3] = 255
    m[..., 3] = np.clip(sheen * 255, 0, 255).astype(np.uint8)
    Image.fromarray(m, 'RGBA').resize((540, 960), Image.LANCZOS).save(os.path.join(d, 'sheen-mask.webp'), 'WEBP', quality=80, method=6)
    sx, sy, sr = geom_px()['seal']
    meta = {'geometry': GEOM, 'sealSize': seal_rgba.shape[0] / W * 100, 'colors': colors}
    if extra:
        meta.update(extra)
    with open(os.path.join(d, 'theme.json'), 'w') as f:
        json.dump(meta, f, ensure_ascii=False, indent=2)


# ================================================================ GÜL MÜHRÜ (gerçek görsel)

def build_gul(video, still):
    tmp = tempfile.mkdtemp()
    f0 = os.path.join(tmp, 'f0.png'); fe = os.path.join(tmp, 'fe.png')
    subprocess.run(['ffmpeg', '-v', 'error', '-y', '-ss', '0', '-i', video, '-frames:v', '1', f0], check=True)
    subprocess.run(['ffmpeg', '-v', 'error', '-y', '-ss', '7.8', '-i', video, '-frames:v', '1', fe], check=True)
    poster = np.asarray(Image.open(f0).convert('RGB').resize((W, H), Image.LANCZOS), np.float32)
    g = geom_px()
    flap = polygon_mask(g['flap'])
    sx, sy, sr = g['seal']
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    rr = np.sqrt((xx - sx) ** 2 + (yy - sy) ** 2)
    lum = poster @ np.array([0.3, 0.55, 0.15], np.float32)
    redness = poster[..., 0] - poster[..., 2]
    orn_a = smooth(lum, 78, 122) * smooth(redness, 20, 45) * blurf(flap, 2) * smooth(rr, sr * 1.02, sr * 1.12)
    orn_a = np.clip(orn_a * 1.15, 0, 1)
    ornament = np.zeros((H, W, 4), np.float32)
    ornament[..., :3] = poster
    ornament[..., 3] = orn_a * 255
    S = int(sr * 2.5)
    x0, y0 = int(sx - S / 2), int(sy - S / 2)
    seal = np.zeros((S, S, 4), np.float32)
    seal[..., :3] = poster[y0:y0 + S, x0:x0 + S]
    sy2, sx2 = np.mgrid[0:S, 0:S].astype(np.float32)
    sd = np.sqrt((sx2 - S / 2) ** 2 + (sy2 - S / 2) ** 2)
    seal[..., 3] = smooth(sd, sr * 1.08, sr * 0.98) * 255
    # doku: alt kapak içinden temiz kadife bölgesi, aynalanarak döşenebilir
    patch = poster[1540:1900, 360:720]
    tile = np.concatenate([patch, patch[:, ::-1]], axis=1)
    tile = np.concatenate([tile, tile[::-1]], axis=0)
    paper = np.asarray(Image.open(fe).convert('RGB').resize((W, H), Image.LANCZOS), np.float32)
    sheen = orn_a * smooth(lum, 95, 170)
    write_common('gul-muhru', poster, ornament, seal, tile, paper, sheen,
                 {'surface': '#5a1630', 'ink': '#4a1024', 'accent': '#c98a6b', 'paper': '#f4ecdf', 'lining': '#2b0816'},
                 {'video': True})
    # katalog görseli: kullanıcının verdiği gerçek görsel
    st = Image.open(still).convert('RGB').resize((640, 1138), Image.LANCZOS)
    save_webp(st, os.path.join(OUT, 'gul-muhru', 'preview.webp'), q=84)
    # kaydırmaya uygun video: sık anahtar kare, sessiz, hızlı başlangıç
    dst = os.path.join(OUT, 'gul-muhru', 'opening.mp4')
    subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', video, '-an', '-c:v', 'libx264', '-profile:v', 'high',
                    '-pix_fmt', 'yuv420p', '-preset', 'slow', '-crf', '27', '-g', '6', '-keyint_min', '6',
                    '-sc_threshold', '0', '-bf', '0', '-r', '24', '-vf', 'scale=1080:1920',
                    '-movflags', '+faststart', dst], check=True)


# ================================================================ ZEYTİN VARAK

def build_zeytin():
    rng = np.random.default_rng(7)
    base = velvet(rng, H, W, (30, 52, 38), (78, 104, 74))
    g = geom_px()
    flap = polygon_mask(g['flap'])
    hm = np.zeros((H, W), np.float32)
    vein = np.zeros((H, W), np.float32)
    ss = 1
    stems = Image.new('L', (W, H), 0)
    sx, sy, sr = g['seal']
    # ana dal: mühürden yukarı S kıvrımı
    main = bezier(np.array([sx - 10, sy - sr * 0.6]), np.array([sx - 260, sy - 420]), np.array([sx + 280, sy - 620]), np.array([sx - 40, 120]))
    draw_stroke(stems, main, 15, ss)
    branches = []
    for t0, side, ln in ((0.18, -1, 300), (0.34, 1, 330), (0.5, -1, 280), (0.66, 1, 250), (0.8, -1, 200)):
        i = int(t0 * (len(main) - 1))
        p = main[i]
        tang = main[min(i + 5, len(main) - 1)] - main[max(i - 5, 0)]
        a = math.atan2(tang[1], tang[0]) + side * 0.9
        end = p + ln * np.array([math.cos(a), math.sin(a)])
        ctrl = p + ln * 0.5 * np.array([math.cos(a - side * 0.35), math.sin(a - side * 0.35)])
        b = bezier(p, ctrl, (ctrl + end) / 2 + side * np.array([0, -30]), end, 80)
        draw_stroke(stems, b, 8, ss)
        branches.append(b)
    sh = np.asarray(stems.filter(ImageFilter.GaussianBlur(3.5)), np.float32) / 255
    hm = np.maximum(hm, np.clip(sh * 1.6, 0, 1) * 0.75)
    vein = np.maximum(vein, smooth(sh, 0.55, 0.9) * 0.8)

    def leaves_on(curve, step, L, Wd, start=0.06):
        n = len(curve)
        k = 0
        for i in range(int(start * n), n - 2, step):
            p = curve[i]
            tang = curve[min(i + 3, n - 1)] - curve[max(i - 3, 0)]
            a = math.atan2(tang[1], tang[0])
            side = 1 if k % 2 == 0 else -1
            la = a + side * rng.uniform(0.55, 0.85)
            leaf(hm, vein, p[0], p[1], la, L * rng.uniform(0.85, 1.1), Wd * rng.uniform(0.85, 1.1))
            k += 1
        # uç yaprağı
        p = curve[-1]; tang = curve[-1] - curve[-4]
        leaf(hm, vein, p[0], p[1], math.atan2(tang[1], tang[0]), L * 1.05, Wd)

    leaves_on(main, 16, 120, 24, 0.1)
    for b in branches:
        leaves_on(b, 14, 100, 20, 0.15)
    # zeytin taneleri
    for b in branches[:4]:
        mid = b[len(b) // 2]
        for j in range(3):
            ellipse_dome(hm, mid[0] + rng.uniform(-40, 40), mid[1] + rng.uniform(10, 60), 17, 24, rng.uniform(0, 3.1), 1.0)
    hm = blurf(hm, 1.4) * flap * smooth(np.sqrt((np.mgrid[0:H, 0:W][1] - sx) ** 2 + (np.mgrid[0:H, 0:W][0] - sy) ** 2), sr * 0.95, sr * 1.05)
    diff, spec, _ = shade(hm * 40, strength=0.12)
    gold_d, gold_l = np.array([92, 70, 30], np.float32), np.array([214, 182, 110], np.float32)
    col = gold_d * (1 - diff[..., None]) + gold_l * diff[..., None]
    col = col * (0.6 + 0.55 * diff[..., None]) + (spec[..., None] ** 30) * 90
    alpha = smooth(hm, 0.02, 0.12)
    ornament = np.zeros((H, W, 4), np.float32)
    ornament[..., :3] = np.clip(col, 0, 255)
    ornament[..., 3] = alpha * 255
    # kabartma gölgesi tabana
    shadow = np.clip(blurf(alpha, 5), 0, 1)
    shadow = np.roll(np.roll(shadow, 6, 0), 5, 1)
    base_s = base * (1 - shadow * 0.35)[..., None]
    env = envelope_shading(base_s, (150, 170, 120), (20, 30, 22))

    def olive_motif(m, cx, cy, ri):
        tmp = np.zeros_like(m); vt = np.zeros_like(m)
        st = Image.new('L', (m.shape[1], m.shape[0]), 0)
        c = bezier(np.array([cx - ri * 0.5, cy + ri * 0.55]), np.array([cx - ri * 0.1, cy + ri * 0.1]), np.array([cx + ri * 0.1, cy - ri * 0.2]), np.array([cx + ri * 0.45, cy - ri * 0.55]), 60)
        draw_stroke(st, c, 5, 1)
        tmp = np.maximum(tmp, np.asarray(st.filter(ImageFilter.GaussianBlur(1.5)), np.float32) / 255)
        for i, t in enumerate(np.linspace(0.15, 0.9, 6)):
            p = c[int(t * 59)]; tg = c[min(int(t * 59) + 2, 59)] - c[max(int(t * 59) - 2, 0)]
            a = math.atan2(tg[1], tg[0]) + (0.7 if i % 2 else -0.7)
            leaf(tmp, vt, p[0], p[1], a, ri * 0.32, ri * 0.08)
        ellipse_dome(tmp, cx + ri * 0.1, cy + ri * 0.28, ri * 0.09, ri * 0.12, 0.4)
        np.maximum(m, tmp, out=m)

    seal = wax_seal(np.random.default_rng(3), sr, (60, 48, 22), (176, 140, 70), olive_motif)
    poster = ornament_layers(env, ornament, seal)
    tex = velvet(np.random.default_rng(11), 720, 720, (30, 52, 38), (78, 104, 74))
    paper = paper_tex(np.random.default_rng(5), H, W, (243, 238, 222))
    pi = to_img(paper)
    d = ImageDraw.Draw(pi)
    d.rectangle([40, 40, W - 41, H - 41], outline=(176, 146, 84), width=3)
    d.rectangle([52, 52, W - 53, H - 53], outline=(196, 170, 112), width=1)
    paper = np.asarray(pi, np.float32)
    sheen = np.clip(vein * alpha, 0, 1)
    write_common('zeytin-varak', poster, ornament, seal, tex, paper, sheen,
                 {'surface': '#3a5a3e', 'ink': '#24382a', 'accent': '#b8964f', 'paper': '#f3eede', 'lining': '#16261a'})


# ================================================================ SEDEF KIVRIMI

def build_sedef():
    rng = np.random.default_rng(21)
    base = paper_tex(rng, H, W, (241, 237, 233), fiber=0.009)
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    irid = np.stack([np.sin(xx / 380 + yy / 610) * 4, np.sin(xx / 450 + 1.7 + yy / 520) * 3, np.sin(xx / 400 + 3.4 + yy / 700) * 5], -1)
    base = base + irid
    g = geom_px()
    flap = polygon_mask(g['flap'])
    sx, sy, sr = g['seal']
    hm = np.zeros((H, W), np.float32)
    ridge = np.zeros((H, W), np.float32)
    # büyük deniz kabuğu: menteşe aşağıda, yelpaze yukarı
    hx, hy = sx, sy - sr * 1.05
    dx, dy = xx - hx, hy - yy
    r = np.sqrt(dx * dx + dy * dy)
    th = np.arctan2(dx, dy)  # 0 yukarı
    span = 0.92
    N = 23
    phase = (th / span) * (N / 2) * np.pi
    R = 560 + 20 * np.cos(phase)
    inside = (np.abs(th) < span) & (r < R)
    rn = np.clip(r / R, 0, 1)
    ribs = 0.5 + 0.5 * np.cos(phase)
    growth = 0.5 + 0.5 * np.cos(rn * 2 * np.pi * 9)
    prof = np.sqrt(np.clip(np.sin(np.pi * (0.15 + 0.85 * rn)), 0, 1))
    edge = smooth(np.abs(th), span, span - 0.05) * smooth(rn, 1.0, 0.97) * smooth(rn, 0.04, 0.14)
    shell = np.where(inside, (0.35 + 0.5 * ribs ** 1.4 + 0.05 * growth) * prof * edge, 0)
    hm = np.maximum(hm, shell)
    ribidx = np.round(((th / span) * (N / 2) + N) )
    sel = np.isin(ribidx % N, [N - 8, N - 4, 4, 8, 0])
    ridge = np.maximum(ridge, np.where(inside & sel, (ribs ** 10) * smooth(rn, 0.25, 0.55) * smooth(rn, 0.98, 0.85), 0))
    # kulakçıklar
    for s_ in (-1, 1):
        pts = [(hx - s_ * 10, hy + 6), (hx + s_ * 95, hy - 55), (hx + s_ * 85, hy + 8)]
        m = polygon_mask(pts)
        hm = np.maximum(hm, blurf(m, 2.5) * 0.4)
    # ince dalga kıvrımları (kör kabartma)
    curls = Image.new('L', (W, H), 0)
    for s_ in (-1, 1):
        for k, (r0, y0, wd) in enumerate(((120, 760, 4), (86, 1010, 3))):
            cx = W / 2 + s_ * (400 + 20 * k)
            t = np.linspace(0, 3.6 * np.pi, 320)
            rr = r0 * np.exp(-t / 7.0)
            draw_stroke(curls, list(zip(cx + s_ * rr * np.cos(t), y0 + rr * np.sin(t))), wd, 1)
        b = bezier(np.array([W / 2 + s_ * 380, 760 - 120]), np.array([W / 2 + s_ * 520, 560]), np.array([W / 2 + s_ * 480, 260]), np.array([W / 2 + s_ * 300, 150]), 160)
        draw_stroke(curls, b, 3, 1)
    ch = np.asarray(curls.filter(ImageFilter.GaussianBlur(2)), np.float32) / 255
    hm = np.maximum(hm, np.clip(ch * 1.4, 0, 1) * 0.45)
    ridge = np.maximum(ridge, smooth(ch, 0.6, 0.95) * 0.25)
    # inci dizisi kabuk kenarında
    for t in np.linspace(-0.82, 0.82, 13):
        ellipse_dome(hm, hx + math.sin(t) * 612, hy - math.cos(t) * 612, 9, 9, 0, 0.7)
    hm = hm * blurf(flap, 3) * smooth(np.sqrt((xx - sx) ** 2 + (yy - sy) ** 2), sr * 0.95, sr * 1.05)
    hm = blurf(hm, 1.2)
    diff, spec, (nx, ny, nz) = shade(hm * 36, strength=0.12, light=(-0.4, -0.75, 0.6))
    pearl = np.array([246, 243, 240], np.float32); lil = np.array([170, 152, 200], np.float32); sil = np.array([255, 255, 255], np.float32)
    k = (diff - 0.55)[..., None]
    col = pearl + np.where(k < 0, k * 2.2 * (pearl - lil), k * 1.6 * (sil - pearl + 20))
    hue = (0.5 + 0.5 * np.sin(nx * 4 + ny * 3))[..., None]
    col = col + (spec[..., None] ** 28) * (np.array([30, 26, 50], np.float32) * hue + np.array([40, 42, 46], np.float32) * (1 - hue))
    alpha = smooth(hm, 0.02, 0.1)
    ornament = np.zeros((H, W, 4), np.float32)
    ornament[..., :3] = np.clip(col, 0, 255)
    ornament[..., 3] = alpha * 255
    shadow = np.clip(blurf(alpha, 6), 0, 1)
    shadow = np.roll(np.roll(shadow, 7, 0), 5, 1)
    base_s = base * (1 - shadow * 0.06)[..., None]
    env = envelope_shading(base_s, (255, 255, 255), (180, 170, 190), side_dark=0.965, bottom_light=1.01)

    def shell_motif(m, cx, cy, ri):
        yy2, xx2 = np.mgrid[0:m.shape[0], 0:m.shape[1]].astype(np.float32)
        hx2, hy2 = cx, cy + ri * 0.55
        dx2, dy2 = xx2 - hx2, hy2 - yy2
        r2 = np.sqrt(dx2 * dx2 + dy2 * dy2); th2 = np.arctan2(dx2, dy2)
        R2 = ri * 0.95
        ins = (np.abs(th2) < 0.95) & (r2 < R2)
        rib2 = 0.5 + 0.5 * np.cos(th2 / 0.95 * 9 * np.pi / 2)
        np.maximum(m, np.where(ins, (0.5 + 0.5 * rib2) * smooth(r2 / R2, 1, 0.9) * smooth(r2 / R2, 0.05, 0.2), 0), out=m)

    seal = wax_seal(np.random.default_rng(9), sr, (110, 98, 140), (236, 232, 244), shell_motif)
    poster = ornament_layers(env, ornament, seal)
    tex = paper_tex(np.random.default_rng(31), 720, 720, (241, 237, 233), fiber=0.009)
    paper = paper_tex(np.random.default_rng(6), H, W, (250, 248, 246), fiber=0.015)
    pi = to_img(paper)
    d = ImageDraw.Draw(pi)
    d.rectangle([40, 40, W - 41, H - 41], outline=(176, 166, 196), width=3)
    d.rectangle([52, 52, W - 53, H - 53], outline=(200, 202, 210), width=1)
    paper = np.asarray(pi, np.float32)
    sheen = np.clip(ridge * alpha, 0, 1)
    write_common('sedef-kivrimi', poster, ornament, seal, tex, paper, sheen,
                 {'surface': '#efeae6', 'ink': '#3e3550', 'accent': '#a99cc4', 'paper': '#faf8f6', 'lining': '#d9d2e2'})


if __name__ == '__main__':
    which = sys.argv[3:] or ['gul', 'zeytin', 'sedef']
    if 'gul' in which:
        build_gul(sys.argv[1], sys.argv[2])
    if 'zeytin' in which:
        build_zeytin()
    if 'sedef' in which:
        build_sedef()
    print('ok')
