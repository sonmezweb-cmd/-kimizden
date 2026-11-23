import React from "react";
import {
  AbsoluteFill,
  Easing,
  Img,
  interpolate,
  staticFile,
  useCurrentFrame,
  useVideoConfig,
} from "remotion";

/*
 * İkimizden — zarf açılış videosu (sessiz, kaydırmayla oynatılır).
 * Zaman çizelgesi (8 sn, 24 fps):
 *  0,0–1,0  kapalı zarf, hafif parıltılar
 *  1,0–2,4  kabartma desen boyunca tek ince ışık geçişi
 *  2,4–4,8  mühür ayrılır, kapağın üzerinde kalır, kapakla birlikte açılır
 *  4,8–6,4  boş iç kâğıt yükselir ve ekranı doldurur
 *  6,4–8,0  kâğıt sabit (isim ve tarih web sayfasında gerçek HTML olarak eklenir)
 */

export type Geo = {
  flapLeft: number; flapRight: number; flapTipX: number; flapTipY: number;
  sealX: number; sealY: number; sealR: number; pocketSide: number; pocketTipY: number;
};
export type ThemeMeta = {
  geometry: Geo;
  sealSize: number;
  sparkles: number[][];
  sheen: "copper" | "gold" | "pearl";
  colors: Record<string, string>;
};
export type OpeningProps = { slug: string; meta: ThemeMeta };

const clamp = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;
const SMOOTH = Easing.bezier(0.45, 0, 0.2, 1);
const OUT = Easing.bezier(0.16, 1, 0.3, 1);

const SHEEN: Record<string, string> = {
  copper: "linear-gradient(115deg, transparent 38%, rgba(255,214,186,0.0) 42%, rgba(255,206,170,0.95) 50%, rgba(255,240,225,1) 52%, rgba(255,196,160,0.9) 54%, transparent 62%)",
  gold: "linear-gradient(115deg, transparent 38%, rgba(255,236,170,0.0) 42%, rgba(255,226,140,0.95) 50%, rgba(255,250,215,1) 52%, rgba(240,205,120,0.9) 54%, transparent 62%)",
  pearl: "linear-gradient(115deg, transparent 38%, rgba(214,196,255,0.0) 42%, rgba(205,188,255,0.9) 48%, rgba(255,255,255,1) 51%, rgba(208,226,240,0.9) 54%, transparent 62%)",
};

const Sparkle: React.FC<{ x: number; y: number; delay: number; size: number; color: string }> = ({ x, y, delay, size, color }) => {
  const frame = useCurrentFrame();
  const t = frame - delay;
  const o = interpolate(t, [0, 7, 16], [0, 1, 0], clamp);
  return (
    <div
      style={{
        position: "absolute", left: `${x}%`, top: `${y}%`, width: size, height: size,
        marginLeft: -size / 2, marginTop: -size / 2, opacity: o,
        scale: interpolate(t, [0, 7, 16], [0.3, 1, 0.5], clamp),
        rotate: `${interpolate(t, [0, 16], [0, 45], clamp)}deg`,
        background: `radial-gradient(circle, #fff 0 6%, ${color} 14%, transparent 40%), conic-gradient(from 0deg, transparent 0 20deg, rgba(255,255,255,.9) 22deg 23deg, transparent 25deg 110deg, rgba(255,255,255,.9) 112deg 113deg, transparent 115deg 200deg, rgba(255,255,255,.9) 202deg 203deg, transparent 205deg 290deg, rgba(255,255,255,.9) 292deg 293deg, transparent 295deg)`,
        borderRadius: "50%",
        mixBlendMode: "screen",
        filter: "blur(0.3px)",
      }}
    />
  );
};

export const Opening: React.FC<OpeningProps> = ({ slug, meta }) => {
  const frame = useCurrentFrame();
  const { fps, width: W, height: H } = useVideoConfig();
  const s = (sec: number) => sec * fps;
  const g = meta.geometry;
  const c = meta.colors;
  const f = (file: string) => staticFile(`${slug}/${file}`);

  // mühür: 2,4–3,1 sn ayrılır (büyür, gölgesi derinleşir)
  const sealLift = interpolate(frame, [s(2.4), s(3.1)], [0, 1], { ...clamp, easing: OUT });
  // kapak: 3,0–4,8 sn menteşe etrafında 0 → 180°
  const flapAngle = interpolate(frame, [s(3.0), s(4.7)], [0, 180], { ...clamp, easing: SMOOTH });
  // kâğıt: 4,8–5,7 yükselir; 5,5–6,4 ekranı doldurur, zarf aşağı süzülür
  const rise = interpolate(frame, [s(4.7), s(5.7)], [0, 1], { ...clamp, easing: OUT });
  const fill = interpolate(frame, [s(5.5), s(6.4)], [0, 1], { ...clamp, easing: SMOOTH });

  const sealPx = (meta.sealSize / 100) * W;
  const sealCx = (g.sealX / 100) * W;
  const sealCy = (g.sealY / 100) * H;
  const flapPoly = `polygon(0% 0%, 100% 0%, 100% ${g.flapRight}%, ${g.flapTipX}% ${g.flapTipY}%, 0% ${g.flapLeft}%)`;

  // kâğıdın ara konumu (zarfın içinde) → tam ekran
  const paperW0 = 0.8, paperH0 = 0.62;
  const y0 = interpolate(rise, [0, 1], [0.72, 0.26]); // üst kenar (oran)
  const pw = interpolate(fill, [0, 1], [paperW0, 1]);
  const ph = interpolate(fill, [0, 1], [paperH0, 1]);
  const py = interpolate(fill, [0, 1], [y0, 0]);
  const paperRadius = interpolate(fill, [0, 1], [10, 0]);

  return (
    <AbsoluteFill style={{ backgroundColor: c.lining, overflow: "hidden" }}>
      {/* zarfın içi: aynı doku, gölgede */}
      <AbsoluteFill style={{ backgroundImage: `url(${f("texture.png")})`, backgroundSize: "600px 600px", filter: "brightness(0.85)" }} />
      <AbsoluteFill style={{ backgroundColor: c.lining, opacity: 0.55, mixBlendMode: "multiply" }} />
      <AbsoluteFill
        style={{
          background: `radial-gradient(120% 70% at 50% 40%, rgba(0,0,0,0.0) 0%, rgba(0,0,0,0.3) 100%)`,
        }}
      />

      {/* iç kâğıt (zarfın arkasından yükselir) */}
      <div
        style={{
          position: "absolute",
          left: `${((1 - pw) / 2) * 100}%`, top: `${py * 100}%`,
          width: `${pw * 100}%`, height: `${ph * 100}%`,
          borderRadius: paperRadius, overflow: "hidden",
          boxShadow: `0 ${interpolate(fill, [0, 1], [18, 0])}px ${interpolate(fill, [0, 1], [50, 0])}px rgba(0,0,0,${interpolate(fill, [0, 1], [0.35, 0])})`,
          opacity: interpolate(frame, [s(4.6), s(4.75)], [0, 1], clamp),
        }}
      >
        <Img src={f("paper.png")} style={{ width: "100%", height: "100%", objectFit: "cover" }} />
        {/* kâğıt üzerinde yumuşak ışık */}
        <AbsoluteFill
          style={{
            background: "linear-gradient(160deg, rgba(255,255,255,0.35) 0%, rgba(255,255,255,0) 45%, rgba(0,0,0,0.04) 100%)",
            opacity: interpolate(fill, [0, 1], [1, 0.4]),
          }}
        />
      </div>

      {/* zarf gövdesi: kâğıt dolarken aşağı süzülür */}
      <AbsoluteFill
        style={{
          translate: `0px ${interpolate(fill, [0, 1], [0, H * 0.55])}px`,
          opacity: interpolate(fill, [0.2, 0.9], [1, 0], clamp),
        }}
      >
        <Img src={f("body.png")} style={{ width: "100%", height: "100%" }} />
        {/* mühür izi */}
        <div
          style={{
            position: "absolute", left: sealCx - sealPx * 0.42, top: sealCy - sealPx * 0.42,
            width: sealPx * 0.84, height: sealPx * 0.84, borderRadius: "50%",
            background: "radial-gradient(circle, rgba(0,0,0,0.07) 0%, rgba(0,0,0,0.03) 50%, transparent 70%)",
            opacity: interpolate(frame, [s(3.0), s(3.6)], [0, 1], clamp),
          }}
        />
      </AbsoluteFill>

      {/* kapak + mühür: menteşe (üst kenar) etrafında 3B dönüş */}
      <AbsoluteFill>
        <AbsoluteFill
          style={{
            transformOrigin: "50% 0%",
            // perspektif öğenin kendi dönüşümünde: kapak izleyiciye doğru kalkar
            transform: `perspective(2200px) rotateX(${flapAngle}deg)`,
            opacity: interpolate(fill, [0, 0.4], [1, 0], clamp),
          }}
        >
          {/* ön yüz */}
          <AbsoluteFill style={{ opacity: flapAngle < 90 ? 1 : 0 }}>
            <Img src={f("flap.png")} style={{ width: "100%", height: "100%" }} />
            {/* kabartma boyunca tek ışık geçişi (1,0–2,4 sn) */}
            <AbsoluteFill
              style={{
                backgroundImage: SHEEN[meta.sheen],
                backgroundSize: "300% 300%",
                backgroundPosition: `${interpolate(frame, [s(1.0), s(2.4)], [100, 0], { ...clamp, easing: Easing.bezier(0.4, 0, 0.3, 1) })}% ${interpolate(frame, [s(1.0), s(2.4)], [100, 0], clamp)}%`,
                WebkitMaskImage: `url(${f("sheen.png")})`,
                WebkitMaskSize: "100% 100%",
                maskImage: `url(${f("sheen.png")})`,
                maskSize: "100% 100%",
                mixBlendMode: meta.sheen === "pearl" ? "soft-light" : "screen",
                opacity: interpolate(frame, [s(0.95), s(1.15), s(2.25), s(2.45)], [0, 1, 1, 0], clamp),
              }}
            />
            {/* kapak kenarında ince gölge (derinlik) */}
            <AbsoluteFill style={{ clipPath: flapPoly, boxShadow: "inset 0 -2px 0 rgba(255,255,255,0.12)" }} />
          </AbsoluteFill>
          {/* arka yüz: kapağın içi */}
          <AbsoluteFill
            style={{
              clipPath: flapPoly,
              background: `linear-gradient(180deg, ${c.lining} 0%, ${c.surface} 100%)`,
              opacity: flapAngle >= 90 ? 1 : 0,
            }}
          />
          {/* mühür: kapağın üzerinde kalır, kapakla birlikte açılır */}
          <div
            style={{
              position: "absolute", left: sealCx - sealPx / 2, top: sealCy - sealPx / 2, width: sealPx, height: sealPx,
              scale: interpolate(sealLift, [0, 1], [1, 1.07]),
              translate: `0px ${interpolate(sealLift, [0, 1], [0, -10])}px`,
              filter: `drop-shadow(0 ${interpolate(sealLift, [0, 1], [3, 22])}px ${interpolate(sealLift, [0, 1], [5, 26])}px rgba(0,0,0,${interpolate(sealLift, [0, 1], [0.25, 0.42])}))`,
              opacity: flapAngle < 92 ? 1 : 0,
            }}
          >
            <Img src={f("seal.png")} style={{ width: "100%", height: "100%" }} />
            {/* mühür yüzeyinde parlak çizgi */}
            <div
              style={{
                position: "absolute", inset: "18%", borderRadius: "50%",
                background: "linear-gradient(120deg, transparent 35%, rgba(255,255,255,0.55) 50%, transparent 65%)",
                backgroundSize: "250% 250%",
                backgroundPosition: `${interpolate(frame, [s(2.5), s(3.2)], [100, 0], clamp)}% 50%`,
                mixBlendMode: "soft-light",
                opacity: interpolate(frame, [s(2.45), s(2.6), s(3.1), s(3.25)], [0, 1, 1, 0], clamp),
              }}
            />
          </div>
        </AbsoluteFill>
      </AbsoluteFill>

      {/* kapalı zarfta parıltılar (0–2,4 sn) */}
      <AbsoluteFill style={{ pointerEvents: "none" }}>
        {meta.sparkles.slice(0, 9).map(([x, y], i) => (
          <Sparkle key={i} x={x} y={y} delay={4 + i * 5} size={46 + (i % 3) * 14} color={meta.sheen === "pearl" ? "rgba(220,205,255,.8)" : "rgba(255,220,170,.8)"} />
        ))}
      </AbsoluteFill>

      {/* genel yumuşak ışık süzülmesi */}
      <AbsoluteFill
        style={{
          background: "radial-gradient(80% 50% at 30% 20%, rgba(255,255,255,0.10), transparent 70%)",
          opacity: interpolate(frame, [0, s(2.4), s(4.8)], [0.6, 1, 0], clamp),
          mixBlendMode: "soft-light",
        }}
      />
    </AbsoluteFill>
  );
};
