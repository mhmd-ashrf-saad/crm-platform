<!-- Context: operations/brand.md | Priority: high | Version: 1.3 | Updated: 2026-10-17 -->

# Sijilna Brand

The locked product identity for this fork, and the assets that carry it. The
operator approved the name, the domain, the palette and the tagline (below).
Assets live in `brand/`; the config layer that applies them is recorded below.

## Key points

- **Name:** Sijilna (سجلنا) — "our register"; it also reads as "we recorded".
  The `نا` suffix is the ownership claim: the client owns the register.
- **Assets are outlines, not fonts.** `brand/*.svg` contain paths only, so no
  font has to be installed anywhere they are used.
- **Ochre is never text.** `#B07D2B` measures 3.38:1 on paper — decoration only.
- **Arabic is set larger and heavier than Latin** (16 px minimum, weight 500);
  Arabic carries more vertical detail per glyph, so a size that reads in Latin
  is under-set in Arabic.
- **The tagline is decided.** "Every deal, on the record." / كل صفقة لها سجل.
  is approved; its placement is the login screen and the mail chrome only.

## Name and domain

| Thing | Value |
| --- | --- |
| Product name | Sijilna |
| Arabic | سجلنا |
| Meaning | "our register"; also "we recorded" |
| Primary domain | `sijilna.com` (verified unregistered at approval time) |
| Planned domain | `sijilna.eg` — document-gated, never hard-coded |
| Wordmark without `.eg` | The lockups contain no domain text, so the `.eg` gate cannot break them |

## Tagline — approved

Option 1 is **approved**, and options 2 and 3 are **rejected** — kept only as
rejected options, not as candidates.

| # | English | Arabic | Status |
| --- | --- | --- | --- |
| 1 | Every deal, on the record. | كل صفقة لها سجل. | **APPROVED** |
| 2 | Your register. Your rules. | سجلك. قواعدك. | **REJECTED** — ownership angle, less explanatory |
| 3 | The CRM you own. | نظام إدارة عملاء تملكه. | **REJECTED** — weakest as a line |

**Why #1 won:** it explains the name (سجل = record/register) and it matches the
ETA e-invoicing promise that every transaction is a recorded entry, in both
languages and without a literal translation.

**Placement (decided):** the login screen and the transactional mail chrome
**only**. Nothing in the admin's working screens, and the footer credit is not
its home. Recorded with the other settled rebrand items in
[brand/copy-deck.md](../../../brand/copy-deck.md) §0 (decisions 1 and 11).

## Palette

| Hex | Name | Role |
| --- | --- | --- |
| `#0B3C5D` | Register blue | Identity, headers, table and invoice rules, logo ink |
| `#1B6E5A` | Proof green | Paid / settled state, success badges |
| `#B07D2B` | Grounded ochre | Accents, rules, non-text marks only |
| `#FAF7F1` | Paper | Surfaces, reversed logo fill |
| `#12263A` | Ink | Body text |
| `#5A6472` | Secondary ink | Secondary text only (a tint of ink, not a sixth colour) |

**Contrast rule:** body text is `#12263A` or `#0B3C5D` on `#FAF7F1` / `#FFFFFF`
only; secondary text is `#5A6472` or darker; ochre never sits under a word.
Every sanctioned pair and its measured ratio is in
[brand/palette.md](../../../brand/palette.md) — do not restate ratios elsewhere.
Floor for Arabic body text: 16 px at weight 500.

## Logo assets

| File | What it is |
| --- | --- |
| `brand/sijilna-mark.svg` | The mark: the Arabic seen (س) of سجلنا as a register — three ruled entries on a bound baseline, tail descending as the spine |
| `brand/sijilna-mark-favicon.svg` | The 16 px cut: entry humps narrowed to 24 units with 18-unit gaps so they stay separate when rasterised |
| `brand/sijilna-wordmark.svg` | Primary Latin lockup, mark left (600×256) |
| `brand/sijilna-wordmark-ar.svg` | Arabic lockup, mark **right** (560×256) — RTL reading order |
| `brand/sijilna-app-icon.svg` | Paper mark on an ink tile, full bleed, for the touch icon |
| `brand/png/` | Renders: favicon 16/32/48, touch 180, mark 512/1024, both wordmarks at 1024 and at 100 px width, and `sijilna-on-paper.png` (mark and both lockups on `#FAF7F1`) |
| `brand/sijilna-test-sheet.{html,png}` | The proof sheet (size ladder, 16 px pixel test, one-colour, reversed, paper, contexts) |
| `brand/palette.md` | Palette, roles, measured contrast table |

Clear space: one hump width of the mark, minimum, on every side. Minimum mark
size 16 px; minimum lockup width 100 px.

## Applied — the brand config layer

`BrandingServiceProvider` (`app/Providers/`) applies the palette and the raster
marks as **config defaults**, no core edit: `SystemConfig::getDefaultConfig()`
**drops the first segment** of a field code, so the keys read are
`settings.menu_color.brand_color` = `#0B3C5D`,
`general.admin_logo.logo_image` = `sijilna/sijilna-wordmark-1024.png` and
`general.admin_logo.favicon_image` = `sijilna/sijilna-favicon-48.png`; the
unshifted `general.general.…` form is a silent no-op. Both rasters are published
to the `public` disk idempotently by content, wrapped so a missing storage dir
degrades rather than breaking boot, and `php artisan sijilna:brand` seeds
`core_config` rows, so the values are visible and editable under **Settings**.

The palette key is the application point: a second client's colours are a config
change, not a code edit (crm-941, ruled satisfied in substance 2026-10-17). The
dashboard PDF needs both halves — `DashboardController::getLogoDataUri()` returns
null unless the path is set **and** the file exists on the `public` disk — and it
is a raster, never an SVG (the Settings field validates `bmp/jpeg/jpg/png/webp`).
**Verification gap:** the rendered PDF has no suite guard (it needs a seeded admin,
so it was checked out-of-band); `tests/Feature/RebrandRegressionTest.php` guards
the mechanism, not the PDF itself. Two deliberate non-changes:
`general.design.admin_logo.favicon` stays unset (read by one view, which already
falls back to the package's crisp SVG), and `Admin/src/Config/core_config.php`
stays unedited.

## Reproducing the assets and the proof

Exports use the logo-design skill's dependency-free renderer (Inkscape 1.4.3;
`rsvg-convert` is not required). Use its "contain" fit for square favicons: a
bare `inkscape --export-width=16` keeps the 187:131 aspect and writes 16x10.

```bash
R=~/.agents/skills/logo-design/scripts/render_png.py
for s in 16 32 48; do
  python3 $R brand/sijilna-mark-favicon.svg -o brand/png/sijilna-favicon-$s.png \
    --size $s --backend inkscape; done
python3 $R brand/sijilna-app-icon.svg -o brand/png/sijilna-touch-icon-180.png --size 180 --backend inkscape
python3 $R brand/sijilna-mark.svg -o brand/png/sijilna-mark-512.png --size 512 --backend inkscape
python3 $R brand/sijilna-mark.svg -o brand/png/sijilna-mark-1024.png --size 1024 --backend inkscape
python3 $R brand/sijilna-wordmark.svg -o brand/png/sijilna-wordmark-1024.png --width 1024 --height 437 --padding 0.02 --bg '#FAF7F1' --backend inkscape
python3 $R brand/sijilna-wordmark-ar.svg -o brand/png/sijilna-wordmark-ar-1024.png --width 1024 --height 468 --padding 0.02 --bg '#FAF7F1' --backend inkscape
python3 $R brand/sijilna-wordmark.svg brand/sijilna-wordmark-ar.svg -o x.png --width 100 --height 44 --padding 0 --out-dir brand/png --backend inkscape
mv brand/png/sijilna-wordmark.png brand/png/sijilna-wordmark-100w.png
mv brand/png/sijilna-wordmark-ar.png brand/png/sijilna-wordmark-ar-100w.png

python3 $R brand/sijilna-test-sheet.html -o brand/sijilna-test-sheet.png --width 1400 --height 5200
```

Both the proof sheet's HTML and the paper-surface composite (no seam, because
both 1024 wordmark masters carry `--bg '#FAF7F1'`) are one-off renders:

```bash
python3 ~/.agents/skills/logo-design/scripts/preview_sheet.py \
  brand/sijilna-mark.svg brand/sijilna-mark-favicon.svg \
  brand/sijilna-wordmark.svg brand/sijilna-wordmark-ar.svg \
  --name Sijilna --brand-color '#0B3C5D' -o brand/sijilna-test-sheet.html

magick -size 1400x620 xc:'#FAF7F1' \
  \( brand/png/sijilna-mark-512.png -resize 220x220 \) -geometry +70+90 -composite \
  \( brand/png/sijilna-wordmark-1024.png -resize 640x \) -geometry +360+60 -composite \
  \( brand/png/sijilna-wordmark-ar-1024.png -resize 640x \) -geometry +360+360 -composite \
  brand/png/sijilna-on-paper.png
```

What the sheet and renders proved: the mark and the Latin wordmark read at 100 px
width; the 16 px cut keeps three separate entries (pixel dump above); one-colour,
reversed and paper treatments hold; no mirror or 180° rotation produces an
unintended reading.

## Arabic typography and licence

- Arabic wordmark font: **IBM Plex Sans Arabic**, SemiBold, shaped and then
  converted to outlines — the shipped files contain no font and no `<text>`.
- Latin wordmark font: **IBM Plex Sans**, SemiBold, likewise outlined. The two
  are a designed pair, so the lockups share one voice across scripts.
- Licence: **SIL Open Font License 1.1**, with a Reserved Font Name clause for
  "Plex". Because only outlines are shipped, no OFL redistribution obligation
  attaches to these assets and no font is redistributed. If the wordmarks are
  ever regenerated from live text, that generator needs OFL-compliant handling —
  see `THIRD-PARTY-NOTICES.md`.

## The Arabic risk — what was checked

The mark is a *drawn* seen, not live text, so joining cannot break. Specific
checks, each with its result:

1. **Letter joining** — the artwork contains no text nodes at all, so no joining
   can be lost. The Arabic wordmark's shaping was done by the font's own
   HarfBuzz shaper before outlining, so `سجلنا` is the joined form. Verified by
   rendering `brand/png/sijilna-wordmark-ar-1024.png` and reading it: seen
   three-tooth, jeem with its dot below, lam-alef, noon with its dot above.
2. **Does it read as another word?** The three humps on a bound baseline with a
   descending tail are a geometric seen; no letter of the name is altered and no
   diacritic is added, so the mark cannot be read as a different word. The mark
   is *not* the whole name — the wordmark always accompanies it.
3. **No mark where it changes a letter form** — nothing is placed over or beside
   a letterform to modify it. The apparent dot in the wordmark is IBM Plex's own
   jeem and noon dots, untouched.
4. **Gaps do not close at small size** — at 16 px the primary mark's 12-unit
   gaps rasterise to ~1 px and partly merge, so the favicon cut widens them to
   18 units (~1.5 px). Pixel dump of `brand/png/sijilna-favicon-16.png`, hump
   row: `##..#..##` — three separate ink runs. The primary mark is therefore
   specified for ≥24 px, the cut for 16 px.
5. **Mirror and 180° rotation** — checked on the test sheet; neither reads as a
   different Arabic letter and neither produces an unintended shape.
6. **`نا` ownership suffix** — carried as the mark's descending tail and the
   bound spine of the baseline, not as separated letterforms, so it cannot be
   read as two unjoined letters.

## Related

- Palette detail and ratios → [brand/palette.md](../../../brand/palette.md)
- Rebrand scope and what must stay upstream → [../guides/rebrand-boundary.md](../guides/rebrand-boundary.md)
- The keys, the PDF dependency and its guard → the *Applied* section above
