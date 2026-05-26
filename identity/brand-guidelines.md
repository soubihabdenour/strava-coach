# Personal Coach — Brand Guidelines

> A complete system for designing in the Personal Coach brand. Read this once; reference the assets in this folder forever after.

---

## 1. Brand essence

**Name** Personal Coach
**Tagline (EN)** Train smarter, not just harder.
**Tagline (FR)** Entraîne-toi plus malin, pas seulement plus dur.
**Description** A personal coach for endurance athletes, powered by your Strava data.

**Brand attributes** — pick any three to guide a design decision:

- **Honest** — we show real numbers, not vanity metrics.
- **Practical** — every screen, every message has a next action.
- **Quiet confidence** — no shouting, no exclamation marks. The work speaks.
- **Athletic** — built by people who run, ride, swim. Lived-in language.
- **Adaptive** — the plan changes when you change.

We are *not*: hype-driven, motivational-poster-y, gamified, emoji-heavy, or chasing engagement for its own sake.

---

## 2. Logo

### Mark

The mark is four ascending bars in a rounded orange tile. It reads as **progression** — weekly volume building toward a peak. At small sizes it stays legible because the bars increase in clear, uniform steps.

| File | When to use |
| --- | --- |
| `logo/logo-mark.svg` | Default. Orange tile, white bars. App icon, favicon, anywhere on dark surfaces ≥ 24px. |
| `logo/logo-mark-white.svg` | On photography or coloured backgrounds where the tile would clash. |
| `logo/logo-mark-black.svg` | Print, monochrome documents, embossed surfaces. |

### Wordmark + lockups

| File | When to use |
| --- | --- |
| `logo/logo-wordmark.svg` | Text-only contexts (legal lines, citations). |
| `logo/logo-horizontal.svg` | Default header lockup. Mark left, two-line wordmark right. |
| `logo/logo-stacked.svg` | Square or narrow surfaces (social avatars, app stores). |

### Clear space

Always leave **at least the height of the bars' tallest unit** (≈ ⅓ of the mark) clear on all sides of the lockup. Don't crowd it with other logos or UI chrome.

### Don'ts

- Don't change the bar count, spacing, or proportions.
- Don't apply gradients, drop-shadows, outer strokes, or filters.
- Don't recolour the tile to anything other than the brand orange.
- Don't rotate, skew, or "play" with the mark.
- Don't use the wordmark without its kerning preserved (use the SVG, not your own typing).

### Minimum sizes

- Mark alone: **16 px** digital, **8 mm** print
- Horizontal lockup: **120 px** wide digital, **30 mm** print
- Stacked lockup: **80 px** wide digital, **20 mm** print

---

## 3. Colour

See `color/tokens.css`, `color/palette.json`, `color/palette.svg`.

### Hierarchy of decisions

1. **Brand orange** is reserved for primary actions, brand surfaces, and the *peak* training phase. Don't use it for decoration.
2. **Ink scale** carries every other surface and text colour. Pick from the ladder; don't pick "in between" values.
3. **Status colours** (green / amber / blue / red) only carry meaning. Green ≠ "nice"; green = "on track".
4. **Phase colours** (base/build/peak/taper) are a special tier — they read as a sequence, never use them out of context.

### Contrast

Body text is `#E6E8EC` on `#0F1115` — **15.8:1**, well above WCAG AAA. Muted text `#8A93A6` on the same — **6.4:1**, AA for body. **Never** use orange (`#FC4C02`) for body text on the dark background (poor contrast); reserve it for fills, accents, and headings ≥ 22 px.

---

## 4. Typography

See `typography/fonts.css`, `typography/README.md`, `typography/type-scale.svg`.

- **Display:** Space Grotesk (700) for hero, h1, big numbers.
- **UI:** Inter (400/500/600/700) for everything else.
- **Tabular nums always on** for pace, distance, percentages.

---

## 5. Iconography

See `icons/`. All icons are:

- **24 × 24 viewBox** with content inset 2 px on every side.
- **1.75 px stroke**, `currentColor`, round caps & joins.
- **No fills** unless the metaphor demands it (e.g. trophy).
- Set the colour via CSS `color:` — the SVG inherits.

```css
.sport-icon { width: 20px; height: 20px; color: var(--pc-text-muted); }
.sport-icon.active { color: var(--pc-brand); }
```

Emoji is acceptable in chat content (user-generated) and informally in onboarding copy, but **never** in primary navigation or in static UI labels. Use the SVG icon set there.

---

## 6. Motion

- **Loading**: 56 px spinner, 0.9 s linear rotation, orange-on-translucent backdrop. Implemented; see `views/layout_open.php`.
- **Transitions**: 150 ms ease-out for hover/press; 250 ms ease-in-out for entry; ≥ 400 ms for layout shifts.
- **Reduced motion**: respect `prefers-reduced-motion: reduce` — disable spin, replace with a static "..." indicator.

---

## 7. Surface system

| Layer | Token | Use |
| --- | --- | --- |
| Page | `--pc-bg` | The canvas. Everything sits on this. |
| Card | `--pc-surface` | Grouped content. Always rounded 12 px. |
| Inset | `--pc-palette-ink-950` | Inputs, secondary surfaces *inside* a card (e.g. the day rows on the plan view). |
| Border | `--pc-border` | 1 px dividers and outlines. Avoid borders on cards — use elevation instead. |

Cards never sit on cards more than two levels deep. If a third layer is needed, replace the parent with a section header and rely on whitespace.

---

## 8. Voice & tone

See `voice-tone.md`. Short version:

- Second person ("you"), present tense.
- French: informal "tu".
- Numbers in the body, not adjectives. "+18 %" beats "a big jump".
- No exclamation marks. No "let's", "amazing", "crush it".

---

## 9. Applying the brand to the app

To migrate the existing app to these tokens:

1. Import `color/tokens.css` and `typography/fonts.css` at the top of `views/layout_open.php` (or merge their contents into the inline `<style>`).
2. Replace the existing `:root` variables with `var(--pc-*)` equivalents (`--bg` → `--pc-bg`, `--accent` → `--pc-brand`, etc.).
3. Swap `public/favicon.svg` for `identity/logo/favicon.svg` (identical content, kept here as the source of truth).
4. Replace emoji sport icons in `views/coach.php` and `views/plan.php` with `<img>` references to `identity/icons/*.svg`.
5. Add `<meta property="og:image" content="/identity/social/og-image.png">` once the SVG has been rasterised (see §11).

---

## 10. File map

```
identity/
├── README.md
├── brand-guidelines.md          ← you are here
├── voice-tone.md
├── style-guide.html             ← open in a browser to see everything live
│
├── logo/
│   ├── logo-mark.svg            ← primary mark
│   ├── logo-mark-white.svg
│   ├── logo-mark-black.svg
│   ├── logo-horizontal.svg
│   ├── logo-stacked.svg
│   ├── logo-wordmark.svg
│   └── favicon.svg
│
├── color/
│   ├── tokens.css               ← drop into a stylesheet
│   ├── palette.json             ← machine-readable
│   └── palette.svg              ← visual reference
│
├── typography/
│   ├── fonts.css                ← @import + utility classes
│   ├── type-scale.svg
│   └── README.md
│
├── icons/
│   ├── run.svg  bike.svg  swim.svg  tri.svg
│   ├── nutrition.svg  strength.svg  rest.svg
│   ├── trend-up.svg  heart-rate.svg
│
└── social/
    ├── og-image.svg             ← 1200×630, OpenGraph / Twitter card
    └── apple-touch-icon.svg     ← 180×180, iOS home screen
```

---

## 11. Production notes

- **SVG wordmarks reference Google Fonts via `@import`**. They render correctly in any modern browser. For email, print, or contexts that don't load web fonts, **vectorize the text** (convert to paths) using Figma/Illustrator before export.
- **Rasterizing**: For platforms that require PNG (some social previews, app store icons), open the SVG in Chrome → DevTools → "Capture node screenshot", or use `rsvg-convert` (`brew install librsvg`):
  ```
  rsvg-convert -w 1200 -h 630 identity/social/og-image.svg > og-image.png
  ```
- **Versioning**: This system is v1.0.0. Bump the version in `color/palette.json` when you change a token. Don't silently change colour values — downstream callers depend on them.

---

*Designed for Personal Coach. v1.0.0.*
