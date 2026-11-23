#!/usr/bin/env python3
"""
İkimizden — prosedürel ses efektleri (geliştirme aracı).
Her tema için dört kısa efekt üretir: ornament, seal, flap, paper (.mp3, 44.1 kHz mono).
Yönetici panelinden kendi dosyalarınızı yükleyerek bunları değiştirebilirsiniz.
"""
import os, subprocess, tempfile, wave
import numpy as np

SR = 44100
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))


def env(n, a, d, curve=3.0):
    t = np.arange(n) / SR
    att = np.clip(t / max(a, 1e-4), 0, 1)
    dec = np.exp(-np.clip(t - a, 0, None) * curve / max(d, 1e-4))
    return att * dec


def bandnoise(rng, n, lo, hi):
    x = rng.standard_normal(n)
    X = np.fft.rfft(x)
    f = np.fft.rfftfreq(n, 1 / SR)
    m = ((f >= lo) & (f <= hi)).astype(float)
    m = np.convolve(m, np.ones(40) / 40, mode='same')
    y = np.fft.irfft(X * m, n)
    return y / (np.abs(y).max() + 1e-9)


def sweep_noise(rng, n, f0, f1, q=0.35):
    """zamanla kayan bant: kısa bloklar halinde"""
    out = np.zeros(n)
    blk = 2048
    for i in range(0, n, blk // 2):
        seg = min(blk, n - i)
        if seg < 64:
            break
        t = i / n
        fc = f0 + (f1 - f0) * t
        b = bandnoise(rng, seg, fc * (1 - q), fc * (1 + q)) * np.hanning(seg)
        out[i:i + seg] += b
    return out / (np.abs(out).max() + 1e-9)


def crackle(rng, n, count, lo=1500, hi=9000, spread=0.08):
    out = np.zeros(n)
    for _ in range(count):
        p = int(rng.uniform(0, spread) * SR)
        L = int(rng.uniform(0.003, 0.012) * SR)
        if p + L >= n:
            continue
        b = bandnoise(rng, L, lo, hi) * np.exp(-np.linspace(0, 6, L)) * rng.uniform(0.4, 1)
        out[p:p + L] += b
    return out


def chime(n, freqs, decay):
    t = np.arange(n) / SR
    y = np.zeros(n)
    for i, f in enumerate(freqs):
        y += np.sin(2 * np.pi * f * t) * np.exp(-t * decay * (1 + i * 0.6)) / (1 + i)
    return y


def norm(y, peak=0.7):
    y = y - np.mean(y)
    fade = min(len(y), int(0.01 * SR))
    y[-fade:] *= np.linspace(1, 0, fade)
    return y / (np.abs(y).max() + 1e-9) * peak


def write(path, y):
    tmp = tempfile.mktemp(suffix='.wav')
    with wave.open(tmp, 'wb') as w:
        w.setnchannels(1); w.setsampwidth(2); w.setframerate(SR)
        w.writeframes((np.clip(y, -1, 1) * 32767).astype(np.int16).tobytes())
    subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', tmp, '-c:a', 'libmp3lame', '-b:a', '96k', path], check=True)
    os.remove(tmp)


def build(slug, spec):
    d = os.path.join(ROOT, 'public', 'themes', slug, 'audio')
    os.makedirs(d, exist_ok=True)
    for name, fn in spec.items():
        write(os.path.join(d, name + '.mp3'), fn())


def gul():
    r = np.random.default_rng(1)
    return {
        # saten fısıltısı
        'ornament': lambda: norm(sweep_noise(r, int(0.9 * SR), 2500, 5200, 0.5) * env(int(0.9 * SR), 0.35, 0.5, 4), 0.45),
        # sıcak balmumu çıtı
        'seal': lambda: norm(crackle(r, int(0.35 * SR), 9, 800, 5000, 0.07) + 0.3 * bandnoise(r, int(0.35 * SR), 150, 600) * env(int(0.35 * SR), 0.002, 0.08, 6), 0.75),
        # kalın kâğıt menteşesi
        'flap': lambda: norm(sweep_noise(r, int(0.8 * SR), 600, 1600, 0.6) * env(int(0.8 * SR), 0.12, 0.55, 3) + crackle(r, int(0.8 * SR), 6, 1200, 4000, 0.6) * 0.3, 0.6),
        # pamuklu kâğıt kayması
        'paper': lambda: norm(sweep_noise(r, int(1.1 * SR), 1800, 3800, 0.6) * env(int(1.1 * SR), 0.3, 0.7, 3), 0.5),
    }


def zeytin():
    r = np.random.default_rng(2)
    return {
        # hafif yaprak hışırtısı
        'ornament': lambda: norm((crackle(r, int(0.9 * SR), 40, 3000, 11000, 0.8) * 0.6 + sweep_noise(r, int(0.9 * SR), 4000, 6000, 0.4) * 0.4) * env(int(0.9 * SR), 0.3, 0.5, 3), 0.4),
        # yumuşak mühür sesi
        'seal': lambda: norm(bandnoise(r, int(0.3 * SR), 120, 900) * env(int(0.3 * SR), 0.004, 0.12, 5) + crackle(r, int(0.3 * SR), 3, 700, 2500, 0.03) * 0.5, 0.7),
        # dokulu kâğıt kıvrımı
        'flap': lambda: norm(sweep_noise(r, int(0.7 * SR), 900, 2200, 0.7) * env(int(0.7 * SR), 0.08, 0.5, 3) + crackle(r, int(0.7 * SR), 14, 1500, 6000, 0.5) * 0.45, 0.6),
        # kısa kâğıt kayması
        'paper': lambda: norm(sweep_noise(r, int(0.6 * SR), 2200, 3600, 0.5) * env(int(0.6 * SR), 0.15, 0.4, 3), 0.5),
    }


def sedef():
    r = np.random.default_rng(3)
    n = int(1.4 * SR)
    return {
        # kısa ve ince cam tınısı
        'ornament': lambda: norm(chime(n, [2093, 3136, 4186, 5274], 3.2) * env(n, 0.004, 1.2, 2) + 0.02 * bandnoise(r, n, 5000, 9000) * env(n, 0.002, 0.2, 5), 0.35),
        # zarif mühür ayrılması
        'seal': lambda: norm(crackle(r, int(0.25 * SR), 4, 2000, 8000, 0.04) + 0.4 * chime(int(0.25 * SR), [1568, 2350], 14), 0.55),
        # ince kabartmalı kâğıt
        'flap': lambda: norm(sweep_noise(r, int(0.6 * SR), 1800, 3200, 0.5) * env(int(0.6 * SR), 0.1, 0.45, 3), 0.5),
        # parşömen kayması
        'paper': lambda: norm((sweep_noise(r, int(0.9 * SR), 1400, 2800, 0.6) + crackle(r, int(0.9 * SR), 10, 2500, 7000, 0.7) * 0.25) * env(int(0.9 * SR), 0.25, 0.6, 3), 0.5),
    }


if __name__ == '__main__':
    build('gul-muhru', gul())
    build('zeytin-varak', zeytin())
    build('sedef-kivrimi', sedef())
    print('ok')
