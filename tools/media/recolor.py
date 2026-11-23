#!/usr/bin/env python3
"""Hikâye videosunu tema rengine çevirir: yalnız renkli çizgi/lekelerin tonu döner, kâğıt korunur.
Kullanım: python3 tools/media/recolor.py girdi.mp4 çıktı.mp4 <hedef_ton_derece> <doygunluk_çarpanı>
"""
import subprocess, sys, numpy as np

def recolor(rgb, hue_deg, sat_mul):
    a = rgb.astype(np.float32) / 255
    mx = a.max(-1); mn = a.min(-1); d = mx - mn
    s = np.where(mx > 0, d / np.maximum(mx, 1e-6), 0)
    r, g, b = a[..., 0], a[..., 1], a[..., 2]
    h = np.zeros_like(mx)
    m = d > 1e-6
    rc = np.where(m & (mx == r), ((g - b) / np.maximum(d, 1e-6)) % 6, 0)
    gc = np.where(m & (mx == g) & (mx != r), (b - r) / np.maximum(d, 1e-6) + 2, 0)
    bc = np.where(m & (mx == b) & (mx != r) & (mx != g), (r - g) / np.maximum(d, 1e-6) + 4, 0)
    h = (rc + gc + bc) * 60
    # kâğıt (düşük doygunluk) korunur; renkli piksellerde ton hedefe kayar
    w = np.clip((s - 0.08) / 0.10, 0, 1)
    pinkish = (h >= 285) | (h <= 38)          # gül/bakır tonları; sıcak ışıklar (sarı) ve kâğıt korunur
    h2 = np.where((w > 0) & pinkish, hue_deg, h)
    w = np.where(pinkish, w, 0)
    s2 = np.clip(s * (1 + (sat_mul - 1) * w), 0, 1)
    v = mx
    c = v * s2; x = c * (1 - np.abs((h2 / 60) % 2 - 1)); m0 = v - c
    k = (h2 // 60).astype(int) % 6
    R = np.choose(k, [c, x, 0 * c, 0 * c, x, c]); G = np.choose(k, [x, c, c, x, 0 * c, 0 * c]); B = np.choose(k, [0 * c, 0 * c, x, c, c, x])
    return (np.stack([R + m0, G + m0, B + m0], -1) * 255).clip(0, 255).astype(np.uint8)

if __name__ == '__main__':
    src, dst, hue, sat = sys.argv[1], sys.argv[2], float(sys.argv[3]), float(sys.argv[4])
    W, H = map(int, subprocess.run(['ffprobe', '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height', '-of', 'csv=p=0', src], capture_output=True, text=True).stdout.strip().split(','))
    if src.endswith('.png'):
        from PIL import Image
        Image.fromarray(recolor(np.asarray(Image.open(src).convert('RGB')), hue, sat)).save(dst); sys.exit()
    dec = subprocess.Popen(['ffmpeg', '-v', 'error', '-i', src, '-an', '-f', 'rawvideo', '-pix_fmt', 'rgb24', '-'], stdout=subprocess.PIPE)
    enc = subprocess.Popen(['ffmpeg', '-v', 'error', '-y', '-f', 'rawvideo', '-pix_fmt', 'rgb24', '-s', f'{W}x{H}', '-r', '24', '-i', '-',
                            '-c:v', 'libx264', '-crf', '14', '-pix_fmt', 'yuv420p', dst], stdin=subprocess.PIPE)
    n = W * H * 3
    while True:
        buf = dec.stdout.read(n)
        if len(buf) < n: break
        enc.stdin.write(recolor(np.frombuffer(buf, np.uint8).reshape(H, W, 3), hue, sat).tobytes())
    enc.stdin.close(); enc.wait()
