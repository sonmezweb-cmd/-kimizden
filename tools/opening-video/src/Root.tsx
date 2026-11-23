import React from "react";
import { Composition } from "remotion";
import { Opening, ThemeMeta } from "./Opening";
import gul from "../public/gul-muhru/theme.json";
import zeytin from "../public/zeytin-varak/theme.json";
import sedef from "../public/sedef-kivrimi/theme.json";

const themes: Array<[string, ThemeMeta]> = [
  ["gul-muhru", gul as ThemeMeta],
  ["zeytin-varak", zeytin as ThemeMeta],
  ["sedef-kivrimi", sedef as ThemeMeta],
];

export const RemotionRoot: React.FC = () => (
  <>
    {themes.map(([slug, meta]) => (
      <Composition
        key={slug}
        id={slug}
        component={Opening}
        durationInFrames={192}
        fps={24}
        width={1080}
        height={1920}
        defaultProps={{ slug, meta }}
      />
    ))}
  </>
);
