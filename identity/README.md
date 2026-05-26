# Personal Coach — Brand Identity

A complete visual identity for the Personal Coach app. Use this as the single source of truth for the logo, colour, typography, iconography, voice, and visual language.

## Quick start

| Want to… | Open |
| --- | --- |
| See everything at a glance | **[`style-guide.html`](style-guide.html)** — open in a browser |
| Read the full rules | [`brand-guidelines.md`](brand-guidelines.md) |
| Learn how the brand speaks | [`voice-tone.md`](voice-tone.md) |
| Use the logo somewhere | [`logo/`](logo/) — pick the lockup that fits |
| Drop tokens into a stylesheet | [`color/tokens.css`](color/tokens.css) + [`typography/fonts.css`](typography/fonts.css) |
| Use an icon | [`icons/`](icons/) — 24×24 SVGs, `currentColor` |
| Make a social share | [`social/og-image.svg`](social/og-image.svg) |

## Directory map

```
identity/
├── README.md                     ← you are here
├── brand-guidelines.md           ← full guidelines doc
├── voice-tone.md                 ← how the brand speaks
├── style-guide.html              ← live visual brand book
│
├── logo/                         ← 7 SVGs (mark, wordmark, 3 lockups, 2 mono, favicon)
├── color/                        ← tokens.css, palette.json, palette.svg
├── typography/                   ← fonts.css, type-scale.svg, README
├── icons/                        ← 9 stroke icons (24×24)
└── social/                       ← OG image, apple-touch-icon
```

## Two-minute summary

- **Name:** Personal Coach
- **Tagline:** *Train smarter, not just harder.*
- **Primary colour:** Coach Orange `#FC4C02`
- **Display font:** Space Grotesk (700)
- **UI font:** Inter (400/500/600/700)
- **Mark:** Four ascending bars in a rounded orange tile
- **Voice:** Direct, specific, calm. No hype, no exclamation marks.

## Applying to the app

The Personal Coach PHP app already uses ~90% of these tokens informally. To formalise:

1. Copy `color/tokens.css` and `typography/fonts.css` contents into `views/layout_open.php`'s `<style>` block.
2. Replace `--accent` with `--pc-brand`, `--bg` with `--pc-bg`, etc. across all CSS.
3. Replace `public/favicon.svg` with `identity/logo/favicon.svg` (same content; this is the canonical version).
4. Swap emoji sport icons in `views/coach.php` and `views/plan.php` for `<img src="/identity/icons/...svg">`.

The brand can be adopted incrementally — the tokens are designed to drop in without breaking the existing visual rhythm.

---

*v1.0.0 · Designed for Personal Coach.*
