# Typography

Two free, open-source families. Both are on Google Fonts and load fast (Inter is one of the most-cached web fonts in existence).

## Families

| Role | Family | Why |
| --- | --- | --- |
| Display + brand voice | **Space Grotesk** (500, 600, 700) | Geometric, slightly compressed, has personality without shouting. Pairs naturally with athletic / performance brands. Use for hero headlines, the wordmark, week numbers, big stat values. |
| UI + body | **Inter** (400, 500, 600, 700) | Workhorse neutral sans. Excellent on screens at any size, tabular numerals built in. Use for everything else — buttons, labels, body copy, form fields, nav. |
| Numeric data | **JetBrains Mono** (500) | Optional. Use only when columns of numbers need to line up perfectly (pace tables, lap splits). |

## System fallback stack

If web fonts fail to load, the stack falls through to the user's system UI font. The brand still feels coherent because both Inter and Space Grotesk are visually close to modern system sans (San Francisco / Segoe UI).

```
font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
```

## Type scale

| Token | Size | Use |
| --- | --- | --- |
| `--pc-size-display` | 56px | Marketing hero only |
| `--pc-size-3xl`     | 36px | Page H1 |
| `--pc-size-2xl`     | 28px | Big stat value |
| `--pc-size-xl`      | 22px | Section H2 |
| `--pc-size-lg`      | 18px | Card title |
| `--pc-size-md`      | 16px | Body, form fields |
| `--pc-size-base`    | 15px | Default body on dense screens |
| `--pc-size-sm`      | 13px | Meta, captions |
| `--pc-size-xs`      | 12px | Eyebrows, all-caps labels |

Ratio: ~1.25 (major third) between adjacent sizes. Skip steps for clear hierarchy.

## Rules

- **One weight contrast per surface.** Don't mix bold + semibold + medium in the same paragraph.
- **Tracking shrinks as size grows.** Display sizes use `-0.02em`; small all-caps labels use `+0.12em`.
- **Tabular numerals on**. All numeric data (km, pace, %) uses `font-variant-numeric: tabular-nums` so figures don't jiggle when they change.
- **Sentence case in UI**, **all caps reserved for eyebrows + the wordmark**.
- **Body line-height 1.5** for readability; **headings 1.15–1.3** for tightness.

## License

Both fonts are SIL Open Font License — free for commercial use, embedding, and bundling.
