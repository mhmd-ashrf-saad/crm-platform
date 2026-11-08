# Sijilna admin - palette and typography directions

<!-- docs/design/rebrand-palette-directions.md - bead crm-7qu.1 -->

**Status: PROPOSAL. Nothing in this document is a decision, a recommendation or an approval.**
Three directions are offered side by side so a human can pick one by looking at it. No direction is
marked preferred, recommended or approved. The choice is
recorded on bead `crm-7qu.1` and in section 8 of this file, and only then does the token bead
(`crm-7qu.3`) get written.

| | |
| --- | --- |
| Artifacts | `docs/design/specimens/index.html` (chooser), `docs/design/specimens/register.html`, `qalam.html`, `signal.html` |
| Anchors (fixed, not up for redesign) | navy `#0B3C5D`, cream `#FAF7F1`, the existing mark and both wordmarks, Arabic-first typography |
| Scope of this brief | A document and self-contained artifacts. No application code, no file under `packages/`, no token wired into the running UI |
| Consumer | bead `crm-7qu.3` (token foundation), which can be written straight from whichever section wins |

## 0. What this document is

Three distinct directions for the admin UI, each expressed in the same six token groups - one primary
scale, one neutral scale, semantic colours, a type scale, radii, and two density levels - because that
is the only shape bead `crm-7qu.3` can ship without rewriting 211 core view files. A direction that
needed a seventh group, or a different component shape, was not offered.

Each direction is given six things: its character, its scales with hex values **and** token names, its
typefaces with licensing, its radii and elevation, its two densities, and a rendered specimen sheet
that looks like the admin rather than like a style guide.

The directions differ on purpose along axes a person can actually perceive:

| Axis | A - Register | B - Qalam | C - Signal |
| --- | --- | --- | --- |
| Is the brand navy the button? | yes, exactly | yes, and deeper | no - a livelier step is the action colour |
| Is the product paper or screen? | paper canvas, white cards | paper everywhere, hairlines, no shadow | white canvas, tinted panels, soft shadows |
| Arabic typeface | IBM Plex Sans Arabic | Noto Naskh Arabic + Noto Sans Arabic | Cairo |
| Latin typeface | IBM Plex Sans | Noto Serif + Noto Sans | Cairo (same family) |
| Corners | 3-10 px | 0-4 px | 6-18 px |
| Rows (comfortable / compact) | 52 / 38 px | 46 / 34 px | 56 / 40 px |
| Semantic loudness | quiet | very quiet (paper + rule) | loud |

## 1. Fixed input

Not negotiable inside this brief, and unchanged by all three directions:

- **The mark** (`packages/Sijilna/Brand/resources/assets/sijilna-mark.svg`) and **both wordmarks**
  (`sijilna-wordmark.svg`, `sijilna-wordmark-ar.svg` and the reversed pair). The specimen sheets inline
  those exact files - they are not redrawn, recoloured or re-spaced, and the header specimen shows the
  reversed wordmark on the action colour.
- **Navy `#0B3C5D` and cream `#FAF7F1`**, the only two colours in the brand layer today. Both scales are
  derived from them and both colours appear as real steps in those scales.
- **Arabic-first typography.** Every direction names an Arabic-capable family, renders Arabic in the
  specimen, and honours the brand floor: Arabic body text is 16 px minimum at weight 500, Arabic
  secondary text stops at `#5A6472`-equivalent darkness, and Arabic is never set smaller than its Latin
  companion.
- **Self-hosting.** The product is installed per client. No direction may depend on Google Fonts, a CDN
  or any runtime fetch, so every named family is one you ship inside the installation.

## 2. How the scales were built

Both scales are generated, not hand-picked, in **OKLCH** - a perceptual colour space where equal
lightness steps look equal. The method, so bead `crm-7qu.3` can reproduce or adjust it:

1. Take the anchor's own OKLCH coordinates. Navy `#0B3C5D` is `L 0.3430 / C 0.0762 / H 243.88`.
   Cream `#FAF7F1` is `L 0.9768 / C 0.0086 / H 84.57`.
2. Build eleven target lightnesses for the steps 50-950, and **pin the anchor at the step whose
   lightness it actually occupies** - do not move it so the ramp looks tidy.
3. Give the ramp a chroma profile (this is where the directions differ most) and a hue, then gamut-map
   each result into sRGB by reducing chroma until it fits.
4. Measure every pair with the WCAG 2.1 relative-luminance formula.

**Where navy actually lands:** Tailwind's `blue-900` is `L 0.379` and `blue-950` is `L 0.282`. Navy at
`0.343` sits between them, nearer 900. So:

- A and C pin navy at **`primary-900`** and redistribute the 700-950 range slightly to keep the steps
  even.
- B pins navy at **`primary-950`**, which pushes its whole scale a step lighter above the anchor and is
  what makes its mid blues (`primary-700/800`) usable as ink rather than as buttons.

**Where cream actually lands:** Tailwind's `gray-50` is `L 0.985`; cream is `0.977`, so cream is pinned
at **`neutral-50`** in all three directions. The consequence is honest and worth stating: *nothing in
these neutral scales is lighter than the paper.* White is therefore a separate token
(`--sijilna-surface-card`) rather than a scale step, in every direction.

## 3. The constraint every direction had to pass

The admin carries 211 view files that reference Tailwind palette utilities - 3,640 palette-class
occurrences at the time of writing, of which `gray-*` accounts for about 2,700 across 204 files. None of those files may be rewritten, so
the chosen direction has to resolve through the **existing utility names**. That fixes the token map:

| Tailwind family in the views | Occurrences | Maps to |
| --- | --- | --- |
| `gray-*` | ~2,700 (204 files) | `--sijilna-neutral-*` |
| `green-*` | 149 | `--sijilna-success-*` |
| `red-*` | 142 | `--sijilna-danger-*` |
| `slate-*`, `neutral-*` | 57 | `--sijilna-neutral-*` |
| `blue-*`, `sky-*`, `cyan-*` | 69 | `--sijilna-primary-*` (and `info-*` for `sky`/`cyan`) |
| `orange-*`, `amber-*`, `yellow-*` | 23 | `--sijilna-warning-*` |
| `rose-*`, `pink-*`, `purple-*`, `violet-*`, `lime-*` | 12 | `--sijilna-info-*` (rare; audit in bead 5) |

Two measurements that matter for bead `crm-7qu.3`:

- **Zero** palette utilities in the admin views use an alpha modifier (`bg-gray-900/50` and the like),
  so the token variables can hold plain hex values. If that changes, they must become space-separated
  RGB channels for Tailwind's `<alpha-value>` placeholder to keep working.
- Only **three** arbitrary hex classes exist in the views (`bg-[#eff6ff61]` and friends in
  `assets/css/app.css`), so the map above covers essentially the whole surface.

## 4. The three directions, one section each

Each direction is self-contained: character, scales, semantics, type, fonts, radii, density, effect
on the real screens, and shipping cost. Read A, B, C in any order.


## A. Direction A - Register (السجل)

> Institutional. The brand exactly as it was drawn, with nothing louder than the data.

The faithful direction: brand navy is pinned at primary-900 and used literally as the action colour, the canvas is the brand cream, the semantic colours are close in chroma to the two brand colours that already exist, corners are small and the type is one neutral grotesque running in both scripts. It is the direction that can be described to a client in one sentence without a mood board.

| | |
| --- | --- |
| **Who it suits** | Government, ETA e-invoicing and procurement buyers; teams moving off a spreadsheet or off another CRM; any client who judges software by how uneventful it is. It is also the cheapest to ship, because most of it already exists in the brand layer. |
| **What it costs** | Safest and therefore least distinctive: low-chroma semantics give you thin differentiation between stages in a pipeline chart, and the design will not impress anyone in a pitch. |
| **Arabic** | IBM Plex Sans Arabic |
| **Latin** | IBM Plex Sans |
| **Figures** | IBM Plex Mono |
| **Shape** | control 6 (md) - card 8 (lg) - chip 6 (md) |
| **Elevation** | Three shadows at 4-10% navy; cards still carry a 1px border. Elevation is a hint, not a signal. |

### A.1 Primary scale - derived from `#0B3C5D`

| Step | Token | Hex | Note |
| --- | --- | --- | --- |
| 50 | `--sijilna-primary-50` | `#F1F7FD` |  |
| 100 | `--sijilna-primary-100` | `#E0ECF5` |  |
| 200 | `--sijilna-primary-200` | `#CADDED` |  |
| 300 | `--sijilna-primary-300` | `#ACC7DF` |  |
| 400 | `--sijilna-primary-400` | `#87ABC8` |  |
| 500 | `--sijilna-primary-500` | `#658EB0` |  |
| 600 | `--sijilna-primary-600` | `#4A7699` |  |
| 700 | `--sijilna-primary-700` | `#326084` |  |
| 800 | `--sijilna-primary-800` | `#1F4E71` |  |
| 900 | `--sijilna-primary-900` | `#0B3C5D` | **brand navy `#0B3C5D`** - pinned here because this is where its lightness lands |
| 950 | `--sijilna-primary-950` | `#002843` |  |

### A.2 Neutral scale - derived from `#FAF7F1`

| Step | Token | Hex | Note |
| --- | --- | --- | --- |
| 50 | `--sijilna-neutral-50` | `#FAF7F1` | **brand cream `#FAF7F1`** - pinned; nothing in the scale is lighter or warmer |
| 100 | `--sijilna-neutral-100` | `#F1EDE6` |  |
| 200 | `--sijilna-neutral-200` | `#E3DDD5` |  |
| 300 | `--sijilna-neutral-300` | `#D2CAC1` |  |
| 400 | `--sijilna-neutral-400` | `#B3A9A2` |  |
| 500 | `--sijilna-neutral-500` | `#8E847F` |  |
| 600 | `--sijilna-neutral-600` | `#5A6472` | **brand secondary ink `#5A6472`** - labels and metadata |
| 700 | `--sijilna-neutral-700` | `#445162` |  |
| 800 | `--sijilna-neutral-800` | `#2A3B4E` |  |
| 900 | `--sijilna-neutral-900` | `#12263A` | **brand ink `#12263A`** - body text |
| 950 | `--sijilna-neutral-950` | `#031526` |  |

### A.3 Semantic colours

One step for text, one for background, one for border, so a status pill, an inline alert and a
toast can all be built from the same three values.

| State | Text | Background | Border | Text on background | Text on white / cream |
| --- | --- | --- | --- | --- | --- |
| Success | `#1F6956` (`--sijilna-success-text`) | `#EDF7F4` (`--sijilna-success-bg`) | `#C1DBD2` (`--sijilna-success-border`) | 5.98:1 | 6.11:1 |
| Warning | `#85581B` (`--sijilna-warning-text`) | `#FCF5E9` (`--sijilna-warning-bg`) | `#E8D1B3` (`--sijilna-warning-border`) | 5.68:1 | 5.76:1 |
| Danger | `#99393B` (`--sijilna-danger-text`) | `#FDF1F1` (`--sijilna-danger-bg`) | `#EDCCCA` (`--sijilna-danger-border`) | 6.34:1 | 6.54:1 |
| Info | `#3B5F7B` (`--sijilna-info-text`) | `#EFF5FB` (`--sijilna-info-bg`) | `#C6D7E6` (`--sijilna-info-border`) | 6.15:1 | 6.32:1 |

### A.4 Component tokens and the ratios they must clear

| Token | Value | Used for |
| --- | --- | --- |
| `--sijilna-action-bg` | `#0B3C5D` | Primary button fill - the action colour |
| `--sijilna-action-hover` | `#1F4E71` | Primary button hover |
| `--sijilna-on-action` | `#FAF7F1` | Text on the primary button |
| `--sijilna-secondary-bg` | `#FFFFFF` | Secondary button fill |
| `--sijilna-secondary-text` | `#0B3C5D` | Secondary button text |
| `--sijilna-secondary-border` | `#0B3C5D` | Secondary button border |
| `--sijilna-danger-bg` | `#99393B` | Danger button fill (delete, destructive confirm) |
| `--sijilna-on-danger` | `#FFFFFF` | Text on the danger button |
| `--sijilna-disabled-bg` | `#E3DDD5` | Disabled control fill |
| `--sijilna-disabled-text` | `#8E847F` | Disabled control text (WCAG-exempt, but measured) |
| `--sijilna-disabled-border` | `#D2CAC1` | Disabled control border |
| `--sijilna-link` | `#326084` | Links and inline actions |
| `--sijilna-link-hover` | `#0B3C5D` | Link hover |
| `--sijilna-focus-ring` | `#326084` | Focus ring (must clear 3:1 against the surface) |
| `--sijilna-surface-canvas` | `#FAF7F1` | Page background |
| `--sijilna-surface-card` | `#FFFFFF` | Card, grid and input surface |
| `--sijilna-surface-sunken` | `#F1EDE6` | Sunk panels, toolbars, disabled fills |
| `--sijilna-border-subtle` | `#E3DDD5` | Row rules inside a datagrid |
| `--sijilna-border-default` | `#D2CAC1` | Card and container borders |
| `--sijilna-border-strong` | `#B3A9A2` | Hover borders and dividers that must be seen |
| `--sijilna-border-input` | `#8E847F` | Form control border, AA non-text 3:1 |
| `--sijilna-text-primary` | `#12263A` | Body text |
| `--sijilna-text-secondary` | `#5A6472` | Labels, metadata, column heads |
| `--sijilna-text-muted` | `#8E847F` | Icon glyphs and disabled text only - never readable text |
| `--sijilna-table-head-bg` | `#F1EDE6` | Datagrid header row |
| `--sijilna-row-hover` | `#F1F7FD` | Datagrid row hover |
| `--sijilna-row-selected` | `#E0ECF5` | Datagrid row selected |
| `--sijilna-header-bar` | `#0B3C5D` | Admin header bar |
| `--sijilna-header-text` | `#FAF7F1` | Header bar text and the reversed wordmark |

| Pair | Foreground | Background | Ratio | Verdict |
| --- | --- | --- | --- | --- |
| onAction on actionBg | `#FAF7F1` | `#0B3C5D` | 10.8:1 | passes AA |
| secondaryText on secondaryBg | `#0B3C5D` | `#FFFFFF` | 11.55:1 | passes AA |
| onDanger on dangerBg | `#FFFFFF` | `#99393B` | 6.99:1 | passes AA |
| textPrimary on surfaceCanvas | `#12263A` | `#FAF7F1` | 14.4:1 | passes AA |
| textSecondary on surfaceCanvas | `#5A6472` | `#FAF7F1` | 5.61:1 | passes AA |
| textPrimary on surfaceCard | `#12263A` | `#FFFFFF` | 15.4:1 | passes AA |
| textSecondary on surfaceCard | `#5A6472` | `#FFFFFF` | 6.0:1 | passes AA |
| link on surfaceCard | `#326084` | `#FFFFFF` | 6.68:1 | passes AA |
| linkHover on surfaceCard | `#0B3C5D` | `#FFFFFF` | 11.55:1 | passes AA |
| textMuted on surfaceCard | `#8E847F` | `#FFFFFF` | 3.65:1 | below 4.5:1 by design - open problem 5.2 |
| borderInput on surfaceCard | `#8E847F` | `#FFFFFF` | 3.65:1 | passes 3:1 |
| focusRing on surfaceCard | `#326084` | `#FFFFFF` | 6.68:1 | passes 3:1 |
| headerText on headerBar | `#FAF7F1` | `#0B3C5D` | 10.8:1 | passes AA |
| textMuted on disabledBg | `#8E847F` | `#E3DDD5` | 2.7:1 | below 4.5:1 by design - open problem 5.2 |
| disabledText on disabledBg | `#8E847F` | `#E3DDD5` | 2.7:1 | WCAG-exempt (disabled control) - open problem 5.2 |

### A.5 Type scale

| Role | Latin | Arabic | Usage |
| --- | --- | --- | --- |
| Display | 28 / 36 - 600 | 30 / 44 - 600 | Login, empty states, page hero |
| H1 page title | 22 / 30 - 600 | 24 / 36 - 600 | Admin page header |
| H2 section | 18 / 26 - 600 | 20 / 32 - 600 | Section and card group titles |
| H3 card title | 16 / 24 - 600 | 17 / 28 - 600 | Card and panel headers |
| Body | 14 / 22 - 400 | 16 / 28 - 500 | Grid cells, descriptions, form values |
| Body lead | 15 / 24 - 400 | 17 / 30 - 500 | Forms and detail pages |
| Small | 13 / 20 - 400 | 15 / 26 - 500 | Metadata under a value |
| Caption | 12 / 18 - 500, +0.02em | 14 / 24 - 500 | Column labels, chips, breadcrumbs |
| Numeric | 13 / 20 - 400, mono | - | IDs and amounts; tabular by construction |

Two rules govern the Arabic column. First, Arabic is never set smaller than its Latin companion in the
same row. Second, Arabic **body** stays at the brand floor of 16 px / weight 500 or above; only metadata
and captions go below it (15 px and 14 px, both at weight 500, both non-reading text). That is why the
Caption role is 14 px in Arabic where it is 11-12 px in Latin, and why the **compact** density cannot buy
rows by shrinking Arabic type - it pays in padding instead.

### A.6 Fonts, licensing and self-hosting

| Family | Role in this direction | Licence | Licence evidence | Self-hostable? |
| --- | --- | --- | --- | --- |
| IBM Plex Sans Arabic | Arabic UI and body | SIL OFL 1.1 | Verified locally: `/usr/share/doc/fonts-ibm-plex/copyright` records `OFL-1.1`. Reserved Font Name "Plex". | Yes - ship the TTF/WOFF2 with the installation, keep the OFL text, do not rename the family. |
| IBM Plex Sans | Latin UI, headings | SIL OFL 1.1 | Verified locally: `/usr/share/doc/fonts-ibm-plex/copyright` records `OFL-1.1`. | Yes - same file set as above. |
| IBM Plex Mono | IDs and amounts | SIL OFL 1.1 | Verified locally: `/usr/share/doc/fonts-ibm-plex/copyright` records `OFL-1.1`. | Yes - two weights cover every numeric use in the admin. |

All four families used across this brief are SIL OFL 1.1. OFL permits embedding in a product,
redistribution to clients and commercial use; it requires the copyright notice and licence text to
travel with the font files, forbids selling the font on its own, and forbids distributing a modified
version under a reserved name. Nothing here needs a CDN, a Google Fonts request or a network at
runtime - the specimen sheets embed the actual TTF files as base64 for exactly this reason.

### A.7 Radii and elevation

| Token | Value | Where |
| --- | --- | --- |
| `--sijilna-radius-sm` | 3px | checkbox, tag, small chip |
| `--sijilna-radius-md` | 6px | every control - button, input, select |
| `--sijilna-radius-lg` | 8px | card, panel, dropdown |
| `--sijilna-radius-xl` | 10px | drawer, modal, sheet |
| `--sijilna-radius-pill` | 999px | avatar, status badge |

Elevation: Three shadows at 4-10% navy; cards still carry a 1px border. Elevation is a hint, not a signal.

### A.8 The two density levels

| | comfortable | compact |
| --- | --- | --- |
| Row height | 52px | 38px |
| Cell padding (block / inline) | 14px / 16px | 7px / 12px |
| Control height | 38px | 32px |
| Latin size / line-height | 14 / 22 | 13 / 20 |
| Arabic size / line-height | 16 / 28 | 16 / 28 |

### A.9 What it does to the screens that exist today

- **Datagrids** lose nothing and gain nothing: same density, same chrome, but the header row is brand cream rather than Tailwind's `gray-100`, and a selected row is `primary-100`, which is the first time selection has ever read as brand.
- **Primary buttons** become the brand navy exactly (`#0B3C5D`), so the Settings colour picker and the button finally agree. Danger stays a muted brick so a delete never out-shouts a save.
- **Forms** get a border that actually clears 3:1 (`neutral-500`), which looks heavier than today's `gray-300` and is the most visible change in the whole direction.
- **Sidebar and header** keep the navy; the palette change is felt mostly in tint, selection and semantics, not in structure.
- **Empty states and login** are the only places the cream canvas and the display size are visible together.

### A.10 What it costs to ship

Cheapest to ship: two of its four anchors already exist in `BrandingServiceProvider`, the type is one family plus a mono, the file set is the smallest after Signal and there are no new interactions. The token bead can land it without touching a single component's shape.

## B. Direction B - Qalam (قلم)

> A register that looks printed: deep ink, paper, hairlines, Naskh for Arabic headings.

The documentary direction. Navy is pinned at the deepest step and used the way ink is used: for the bar, for rules, for headings. Surfaces are paper with white sheets laid on them, corners are near-square, cards are separated by hairlines rather than shadows, and Arabic headings are set in Naskh while the Latin display is a serif. Status is carried by a coloured rule and the word itself, never by a flood of colour.

| | |
| --- | --- |
| **Who it suits** | Accounting and audit-heavy floors, distributors who print quotes, statements and delivery notes, and any client who wants the CRM to read like a ledger rather than a web app. |
| **What it costs** | Naskh is beautiful but it is not a grid typeface, so this direction needs two Arabic families (Naskh for headings, a sans for the grid) - a real maintenance and subsetting cost. Serif Latin display can read as dated to a buyer who expected SaaS. |
| **Arabic** | Noto Sans Arabic (grid) + Noto Naskh Arabic (headings) |
| **Latin** | Noto Sans (UI) + Noto Serif (display) |
| **Figures** | none - figures use tabular numerals |
| **Shape** | control 2 (md) - card 4 (xl) - badge 2 (a squared label, not a pill) |
| **Elevation** | None. Separators are 1px rules at the border tokens; nothing floats. The only depth cue is paper-on-paper. |

### B.1 Primary scale - derived from `#0B3C5D`

| Step | Token | Hex | Note |
| --- | --- | --- | --- |
| 50 | `--sijilna-primary-50` | `#F1F7FC` |  |
| 100 | `--sijilna-primary-100` | `#DFEBF6` |  |
| 200 | `--sijilna-primary-200` | `#C7DCEE` |  |
| 300 | `--sijilna-primary-300` | `#A7C6E0` |  |
| 400 | `--sijilna-primary-400` | `#84A9C9` |  |
| 500 | `--sijilna-primary-500` | `#628DB1` |  |
| 600 | `--sijilna-primary-600` | `#47769B` |  |
| 700 | `--sijilna-primary-700` | `#34648A` |  |
| 800 | `--sijilna-primary-800` | `#245478` |  |
| 900 | `--sijilna-primary-900` | `#17476A` |  |
| 950 | `--sijilna-primary-950` | `#0B3C5D` | **brand navy `#0B3C5D`** - pinned here because this is where its lightness lands |

### B.2 Neutral scale - derived from `#FAF7F1`

| Step | Token | Hex | Note |
| --- | --- | --- | --- |
| 50 | `--sijilna-neutral-50` | `#FAF7F1` | **brand cream `#FAF7F1`** - pinned; the whole ramp stays warm from here |
| 100 | `--sijilna-neutral-100` | `#F2EFE8` |  |
| 200 | `--sijilna-neutral-200` | `#E6E1D9` |  |
| 300 | `--sijilna-neutral-300` | `#D7D1C8` |  |
| 400 | `--sijilna-neutral-400` | `#BEB7AD` |  |
| 500 | `--sijilna-neutral-500` | `#9A9288` |  |
| 600 | `--sijilna-neutral-600` | `#797067` |  |
| 700 | `--sijilna-neutral-700` | `#60574E` |  |
| 800 | `--sijilna-neutral-800` | `#473E37` |  |
| 900 | `--sijilna-neutral-900` | `#312822` | warm near-black, derived from the cream rather than from a grey |
| 950 | `--sijilna-neutral-950` | `#1B140F` |  |

### B.3 Semantic colours

One step for text, one for background, one for border, so a status pill, an inline alert and a
toast can all be built from the same three values.

| State | Text | Background | Border | Text on background | Text on white / cream |
| --- | --- | --- | --- | --- | --- |
| Success | `#336449` (`--sijilna-success-text`) | `#F9F6EB` (`--sijilna-success-bg`) | `#D9D5BE` (`--sijilna-success-border`) | 6.34:1 | 6.42:1 |
| Warning | `#854F15` (`--sijilna-warning-text`) | `#FEF5E6` (`--sijilna-warning-bg`) | `#E9CFAF` (`--sijilna-warning-border`) | 6.21:1 | 6.28:1 |
| Danger | `#82363C` (`--sijilna-danger-text`) | `#FDF4ED` (`--sijilna-danger-bg`) | `#EACCC2` (`--sijilna-danger-border`) | 7.61:1 | 7.73:1 |
| Info | `#345D75` (`--sijilna-info-text`) | `#F9F6ED` (`--sijilna-info-bg`) | `#D1D7C2` (`--sijilna-info-border`) | 6.55:1 | 6.62:1 |

### B.4 Component tokens and the ratios they must clear

| Token | Value | Used for |
| --- | --- | --- |
| `--sijilna-action-bg` | `#0B3C5D` | Primary button fill - the action colour |
| `--sijilna-action-hover` | `#17476A` | Primary button hover |
| `--sijilna-on-action` | `#FAF7F1` | Text on the primary button |
| `--sijilna-secondary-bg` | `#FFFFFF` | Secondary button fill |
| `--sijilna-secondary-text` | `#0B3C5D` | Secondary button text |
| `--sijilna-secondary-border` | `#0B3C5D` | Secondary button border |
| `--sijilna-danger-bg` | `#82363C` | Danger button fill (delete, destructive confirm) |
| `--sijilna-on-danger` | `#FFFFFF` | Text on the danger button |
| `--sijilna-disabled-bg` | `#E6E1D9` | Disabled control fill |
| `--sijilna-disabled-text` | `#9A9288` | Disabled control text (WCAG-exempt, but measured) |
| `--sijilna-disabled-border` | `#D7D1C8` | Disabled control border |
| `--sijilna-link` | `#17476A` | Links and inline actions |
| `--sijilna-link-hover` | `#0B3C5D` | Link hover |
| `--sijilna-focus-ring` | `#34648A` | Focus ring (must clear 3:1 against the surface) |
| `--sijilna-surface-canvas` | `#FAF7F1` | Page background |
| `--sijilna-surface-card` | `#FFFFFF` | Card, grid and input surface |
| `--sijilna-surface-sunken` | `#F2EFE8` | Sunk panels, toolbars, disabled fills |
| `--sijilna-border-subtle` | `#E6E1D9` | Row rules inside a datagrid |
| `--sijilna-border-default` | `#D7D1C8` | Card and container borders |
| `--sijilna-border-strong` | `#BEB7AD` | Hover borders and dividers that must be seen |
| `--sijilna-border-input` | `#9A9288` | Form control border, AA non-text 3:1 |
| `--sijilna-text-primary` | `#312822` | Body text |
| `--sijilna-text-secondary` | `#797067` | Labels, metadata, column heads |
| `--sijilna-text-muted` | `#9A9288` | Icon glyphs and disabled text only - never readable text |
| `--sijilna-table-head-bg` | `#FAF7F1` | Datagrid header row |
| `--sijilna-row-hover` | `#E6E1D9` | Datagrid row hover |
| `--sijilna-row-selected` | `#D7D1C8` | Datagrid row selected |
| `--sijilna-header-bar` | `#0B3C5D` | Admin header bar |
| `--sijilna-header-text` | `#FAF7F1` | Header bar text and the reversed wordmark |

| Pair | Foreground | Background | Ratio | Verdict |
| --- | --- | --- | --- | --- |
| onAction on actionBg | `#FAF7F1` | `#0B3C5D` | 10.8:1 | passes AA |
| secondaryText on secondaryBg | `#0B3C5D` | `#FFFFFF` | 11.55:1 | passes AA |
| onDanger on dangerBg | `#FFFFFF` | `#82363C` | 8.26:1 | passes AA |
| textPrimary on surfaceCanvas | `#312822` | `#FAF7F1` | 13.48:1 | passes AA |
| textSecondary on surfaceCanvas | `#797067` | `#FAF7F1` | 4.54:1 | passes AA |
| textPrimary on surfaceCard | `#312822` | `#FFFFFF` | 14.41:1 | passes AA |
| textSecondary on surfaceCard | `#797067` | `#FFFFFF` | 4.85:1 | passes AA |
| link on surfaceCard | `#17476A` | `#FFFFFF` | 9.79:1 | passes AA |
| linkHover on surfaceCard | `#0B3C5D` | `#FFFFFF` | 11.55:1 | passes AA |
| textMuted on surfaceCard | `#9A9288` | `#FFFFFF` | 3.07:1 | below 4.5:1 by design - open problem 5.2 |
| borderInput on surfaceCard | `#9A9288` | `#FFFFFF` | 3.07:1 | passes 3:1 |
| focusRing on surfaceCard | `#34648A` | `#FFFFFF` | 6.3:1 | passes 3:1 |
| headerText on headerBar | `#FAF7F1` | `#0B3C5D` | 10.8:1 | passes AA |
| textMuted on disabledBg | `#9A9288` | `#E6E1D9` | 2.36:1 | below 4.5:1 by design - open problem 5.2 |
| disabledText on disabledBg | `#9A9288` | `#E6E1D9` | 2.36:1 | WCAG-exempt (disabled control) - open problem 5.2 |

### B.5 Type scale

| Role | Latin | Arabic | Usage |
| --- | --- | --- | --- |
| Display | 30 / 42 - 600 - Noto Serif | 32 / 52 - 700 - Noto Naskh | Login, empty states, page hero |
| H1 page title | 24 / 32 - 600 - Noto Serif | 26 / 42 - 700 - Noto Naskh | Admin page header |
| H2 section | 19 / 28 - 600 - Noto Serif | 21 / 34 - 700 - Noto Naskh | Section and card group titles |
| H3 card title | 16 / 24 - 600 - Noto Serif | 18 / 30 - 700 - Noto Naskh | Card and panel headers |
| Body | 15 / 24 - 400 - Noto Sans | 17 / 30 - 400 - Noto Sans Arabic | Grid cells, descriptions |
| Small | 13 / 20 - 400 - Noto Sans | 15 / 27 - 400 | Metadata under a value |
| Caption | 11 / 18 - 400 - uppercase +0.08em | 14 / 24 - 500 | Column labels, chips - printed table heads |
| Numeric | 14 / 22 - 400, tabular figures | - | Amounts; tabular numerals, no mono |

Two rules govern the Arabic column. First, Arabic is never set smaller than its Latin companion in the
same row. Second, Arabic **body** stays at the brand floor of 16 px / weight 500 or above; only metadata
and captions go below it (15 px and 14 px, both at weight 500, both non-reading text). That is why the
Caption role is 14 px in Arabic where it is 11-12 px in Latin, and why the **compact** density cannot buy
rows by shrinking Arabic type - it pays in padding instead.

### B.6 Fonts, licensing and self-hosting

| Family | Role in this direction | Licence | Licence evidence | Self-hostable? |
| --- | --- | --- | --- | --- |
| Noto Sans Arabic | Arabic grid text | SIL OFL 1.1 | Verified locally: `/usr/share/doc/fonts-noto-core/copyright` records `OFL-1.1`. | Yes - the safest grid face for Arabic at 15-16 px. |
| Noto Naskh Arabic | Arabic headings and display | SIL OFL 1.1 | Verified locally: `/usr/share/doc/fonts-noto-core/copyright` records `OFL-1.1`. | Yes - but it needs 17 px and above; below that Naskh strokes close up. |
| Noto Sans | Latin UI text | SIL OFL 1.1 | Verified locally: `/usr/share/doc/fonts-noto-core/copyright` records `OFL-1.1`. | Yes - widest Latin coverage in this set, largest file (about 500 KB unsubsetted per weight). |
| Noto Serif | Latin display and page headings | SIL OFL 1.1 | Verified locally: `/usr/share/doc/fonts-noto-core/copyright` records `OFL-1.1`. | Yes - display only here, so one weight is enough (about 590 KB unsubsetted). |

All four families used across this brief are SIL OFL 1.1. OFL permits embedding in a product,
redistribution to clients and commercial use; it requires the copyright notice and licence text to
travel with the font files, forbids selling the font on its own, and forbids distributing a modified
version under a reserved name. Nothing here needs a CDN, a Google Fonts request or a network at
runtime - the specimen sheets embed the actual TTF files as base64 for exactly this reason.

### B.7 Radii and elevation

| Token | Value | Where |
| --- | --- | --- |
| `--sijilna-radius-sm` | 0px | checkbox, tag, small chip |
| `--sijilna-radius-md` | 2px | every control - button, input, select |
| `--sijilna-radius-lg` | 3px | card, panel, dropdown |
| `--sijilna-radius-xl` | 4px | drawer, modal, sheet |
| `--sijilna-radius-pill` | 2px | avatar only - the badge is squared on purpose |

Elevation: None. Separators are 1px rules at the border tokens; nothing floats. The only depth cue is paper-on-paper.

### B.8 The two density levels

| | comfortable | compact |
| --- | --- | --- |
| Row height | 46px | 34px |
| Cell padding (block / inline) | 11px / 14px | 5px / 10px |
| Control height | 36px | 32px |
| Latin size / line-height | 15 / 24 | 13 / 20 |
| Arabic size / line-height | 17 / 30 | 16 / 28 |

### B.9 What it does to the screens that exist today

- **Datagrids** become the centrepiece: uppercase letterspaced Latin column heads, Arabic heads at 14 px, hairline row rules, a squared selected row carrying an ink rule on its leading edge instead of a colour flood.
- **Cards stop floating.** No shadows anywhere; a card is a white sheet on cream, separated by a 1 px rule. Print, quote and invoice screens look like the PDF they produce.
- **Headings are Naskh in Arabic and serif in Latin**, so an Arabic page header finally looks authored rather than translated. The cost is that two Arabic families have to ship.
- **Inputs** use a 1 px rule at `neutral-500` and a 2 px focus ring at `primary-800`; forms look like a printed form, which is either the whole point or the whole objection depending on the client.
- **Semantics are calm by design:** every pill shares one paper background and is distinguished by a coloured rule and its word, so a screen full of statuses does not look like a traffic light.

### B.10 What it costs to ship

Most expensive to ship: four font families (two of them Arabic), a serif display path that has to be threaded through every heading in 204 view files, and a badge that is deliberately not a pill - so the existing `rounded-full` status markup has to be handled explicitly. It is also the direction most at risk in a `2.2` merge, because serif headings will meet upstream sans headings in the same rules.

## C. Direction C - Signal (إشارة)

> Contemporary product UI: a livelier navy family, cool neutrals, soft corners, loud semantics.

The product-marketing direction. Navy is still the anchor and still pinned at its true step, but the middle of the scale is pushed to a vivid steel blue and the neutrals cool off after the paper-light steps, so the screen reads as a modern web app rather than a document. Corners are soft, the secondary button is a soft-filled chip rather than an outline, and the semantic colours carry real chroma.

| | |
| --- | --- |
| **Who it suits** | Sales floors where the CRM has to sell itself, clients comparing the product to HubSpot or Zoho, and any deployment where the UI is demoed on a projector before it is audited. |
| **What it costs** | The richest colour and therefore the highest risk of looking like every other SaaS: it leans on execution. It also drifts furthest from the current brand layer - the button is primary-800 (#004F7C), not the brand navy itself. |
| **Arabic** | Cairo |
| **Latin** | Cairo |
| **Figures** | IBM Plex Mono |
| **Shape** | control 10 (md) - card 14 (lg) - chip pill |
| **Elevation** | Two soft shadows (6-8% navy) plus a 1px border; cards and drawers lift, the page does not. |

### C.1 Primary scale - derived from `#0B3C5D`

| Step | Token | Hex | Note |
| --- | --- | --- | --- |
| 50 | `--sijilna-primary-50` | `#F0F8FF` |  |
| 100 | `--sijilna-primary-100` | `#DBEFFF` |  |
| 200 | `--sijilna-primary-200` | `#C0E1FB` |  |
| 300 | `--sijilna-primary-300` | `#9DCCF1` |  |
| 400 | `--sijilna-primary-400` | `#72AFDE` |  |
| 500 | `--sijilna-primary-500` | `#4891C8` |  |
| 600 | `--sijilna-primary-600` | `#2478B1` |  |
| 700 | `--sijilna-primary-700` | `#036299` |  |
| 800 | `--sijilna-primary-800` | `#004F7C` |  |
| 900 | `--sijilna-primary-900` | `#0B3C5D` | **brand navy `#0B3C5D`** - pinned here because this is where its lightness lands |
| 950 | `--sijilna-primary-950` | `#002843` |  |

### C.2 Neutral scale - derived from `#FAF7F1`

| Step | Token | Hex | Note |
| --- | --- | --- | --- |
| 50 | `--sijilna-neutral-50` | `#FAF7F1` | **brand cream `#FAF7F1`** - pinned; the ramp cools off as it darkens |
| 100 | `--sijilna-neutral-100` | `#F3F0EA` |  |
| 200 | `--sijilna-neutral-200` | `#E4E4DF` |  |
| 300 | `--sijilna-neutral-300` | `#D2D6D5` |  |
| 400 | `--sijilna-neutral-400` | `#B8BBBC` |  |
| 500 | `--sijilna-neutral-500` | `#91979B` |  |
| 600 | `--sijilna-neutral-600` | `#6D747C` |  |
| 700 | `--sijilna-neutral-700` | `#515962` |  |
| 800 | `--sijilna-neutral-800` | `#383F49` |  |
| 900 | `--sijilna-neutral-900` | `#1F2731` | cool ink, the far end of the cream-rooted ramp |
| 950 | `--sijilna-neutral-950` | `#0D141E` |  |

### C.3 Semantic colours

One step for text, one for background, one for border, so a status pill, an inline alert and a
toast can all be built from the same three values.

| State | Text | Background | Border | Text on background | Text on white / cream |
| --- | --- | --- | --- | --- | --- |
| Success | `#1E7C50` (`--sijilna-success-text`) | `#E9F8EF` (`--sijilna-success-bg`) | `#B4DDC3` (`--sijilna-success-border`) | 4.72:1 | 4.84:1 |
| Warning | `#976200` (`--sijilna-warning-text`) | `#FFF3DF` (`--sijilna-warning-bg`) | `#EECB9A` (`--sijilna-warning-border`) | 4.71:1 | 4.83:1 |
| Danger | `#B4343B` (`--sijilna-danger-text`) | `#FFF0EF` (`--sijilna-danger-bg`) | `#F6C2BF` (`--sijilna-danger-border`) | 5.43:1 | 5.62:1 |
| Info | `#106E9A` (`--sijilna-info-text`) | `#E9F6FF` (`--sijilna-info-bg`) | `#B3D7EE` (`--sijilna-info-border`) | 5.13:1 | 5.28:1 |

### C.4 Component tokens and the ratios they must clear

| Token | Value | Used for |
| --- | --- | --- |
| `--sijilna-action-bg` | `#004F7C` | Primary button fill - the action colour |
| `--sijilna-action-hover` | `#0B3C5D` | Primary button hover |
| `--sijilna-on-action` | `#FFFFFF` | Text on the primary button |
| `--sijilna-secondary-bg` | `#F0F8FF` | Secondary button fill |
| `--sijilna-secondary-text` | `#036299` | Secondary button text |
| `--sijilna-secondary-border` | `#DBEFFF` | Secondary button border |
| `--sijilna-danger-bg` | `#B4343B` | Danger button fill (delete, destructive confirm) |
| `--sijilna-on-danger` | `#FFFFFF` | Text on the danger button |
| `--sijilna-disabled-bg` | `#E4E4DF` | Disabled control fill |
| `--sijilna-disabled-text` | `#91979B` | Disabled control text (WCAG-exempt, but measured) |
| `--sijilna-disabled-border` | `#D2D6D5` | Disabled control border |
| `--sijilna-link` | `#036299` | Links and inline actions |
| `--sijilna-link-hover` | `#004F7C` | Link hover |
| `--sijilna-focus-ring` | `#036299` | Focus ring (must clear 3:1 against the surface) |
| `--sijilna-surface-canvas` | `#FFFFFF` | Page background |
| `--sijilna-surface-card` | `#FFFFFF` | Card, grid and input surface |
| `--sijilna-surface-sunken` | `#F3F0EA` | Sunk panels, toolbars, disabled fills |
| `--sijilna-border-subtle` | `#E4E4DF` | Row rules inside a datagrid |
| `--sijilna-border-default` | `#E4E4DF` | Card and container borders |
| `--sijilna-border-strong` | `#D2D6D5` | Hover borders and dividers that must be seen |
| `--sijilna-border-input` | `#6D747C` | Form control border, AA non-text 3:1 |
| `--sijilna-text-primary` | `#1F2731` | Body text |
| `--sijilna-text-secondary` | `#6D747C` | Labels, metadata, column heads |
| `--sijilna-text-muted` | `#91979B` | Icon glyphs and disabled text only - never readable text |
| `--sijilna-table-head-bg` | `#F3F0EA` | Datagrid header row |
| `--sijilna-row-hover` | `#F0F8FF` | Datagrid row hover |
| `--sijilna-row-selected` | `#DBEFFF` | Datagrid row selected |
| `--sijilna-header-bar` | `#0B3C5D` | Admin header bar |
| `--sijilna-header-text` | `#FFFFFF` | Header bar text and the reversed wordmark |

| Pair | Foreground | Background | Ratio | Verdict |
| --- | --- | --- | --- | --- |
| onAction on actionBg | `#FFFFFF` | `#004F7C` | 8.72:1 | passes AA |
| secondaryText on secondaryBg | `#036299` | `#F0F8FF` | 6.1:1 | passes AA |
| onDanger on dangerBg | `#FFFFFF` | `#B4343B` | 6.01:1 | passes AA |
| textPrimary on surfaceCanvas | `#1F2731` | `#FFFFFF` | 15.08:1 | passes AA |
| textSecondary on surfaceCanvas | `#6D747C` | `#FFFFFF` | 4.73:1 | passes AA |
| textPrimary on surfaceCard | `#1F2731` | `#FFFFFF` | 15.08:1 | passes AA |
| textSecondary on surfaceCard | `#6D747C` | `#FFFFFF` | 4.73:1 | passes AA |
| link on surfaceCard | `#036299` | `#FFFFFF` | 6.54:1 | passes AA |
| linkHover on surfaceCard | `#004F7C` | `#FFFFFF` | 8.72:1 | passes AA |
| textMuted on surfaceCard | `#91979B` | `#FFFFFF` | 2.96:1 | below 4.5:1 by design - open problem 5.2 |
| borderInput on surfaceCard | `#6D747C` | `#FFFFFF` | 4.73:1 | passes 3:1 |
| focusRing on surfaceCard | `#036299` | `#FFFFFF` | 6.54:1 | passes 3:1 |
| headerText on headerBar | `#FFFFFF` | `#0B3C5D` | 11.55:1 | passes AA |
| textMuted on disabledBg | `#91979B` | `#E4E4DF` | 2.32:1 | below 4.5:1 by design - open problem 5.2 |
| disabledText on disabledBg | `#91979B` | `#E4E4DF` | 2.32:1 | WCAG-exempt (disabled control) - open problem 5.2 |

### C.5 Type scale

| Role | Latin | Arabic | Usage |
| --- | --- | --- | --- |
| Display | 30 / 38 - 700 | 32 / 46 - 700 | Login, empty states, page hero |
| H1 page title | 24 / 32 - 600 | 26 / 38 - 600 | Admin page header |
| H2 section | 19 / 28 - 600 | 21 / 34 - 600 | Section and card group titles |
| H3 card title | 16 / 24 - 600 | 17 / 28 - 600 | Card and panel headers |
| Body | 15 / 24 - 400 | 17 / 30 - 400 | Grid cells, descriptions |
| Small | 14 / 22 - 400 | 16 / 26 - 400 | Metadata under a value |
| Caption | 12 / 18 - 500, +0.01em | 14 / 24 - 500 | Column labels, chips |
| Numeric | 13 / 20 - 400, mono | - | IDs and amounts |

Two rules govern the Arabic column. First, Arabic is never set smaller than its Latin companion in the
same row. Second, Arabic **body** stays at the brand floor of 16 px / weight 500 or above; only metadata
and captions go below it (15 px and 14 px, both at weight 500, both non-reading text). That is why the
Caption role is 14 px in Arabic where it is 11-12 px in Latin, and why the **compact** density cannot buy
rows by shrinking Arabic type - it pays in padding instead.

### C.6 Fonts, licensing and self-hosting

| Family | Role in this direction | Licence | Licence evidence | Self-hostable? |
| --- | --- | --- | --- | --- |
| Cairo | Arabic and Latin together - one family, both scripts | SIL OFL 1.1 | Verified locally: the font ships its own `OFL.txt` at `/home/mohamed/.local/share/fonts/Cairo/OFL.txt`. | Yes - one family covers both scripts, so the subset is the smallest of the three directions. |
| IBM Plex Mono | IDs and amounts | SIL OFL 1.1 | Verified locally: `/usr/share/doc/fonts-ibm-plex/copyright` records `OFL-1.1`. | Yes - two weights cover every numeric use in the admin. |

All four families used across this brief are SIL OFL 1.1. OFL permits embedding in a product,
redistribution to clients and commercial use; it requires the copyright notice and licence text to
travel with the font files, forbids selling the font on its own, and forbids distributing a modified
version under a reserved name. Nothing here needs a CDN, a Google Fonts request or a network at
runtime - the specimen sheets embed the actual TTF files as base64 for exactly this reason.

### C.7 Radii and elevation

| Token | Value | Where |
| --- | --- | --- |
| `--sijilna-radius-sm` | 6px | checkbox, tag, small chip |
| `--sijilna-radius-md` | 10px | every control - button, input, select |
| `--sijilna-radius-lg` | 14px | card, panel, dropdown |
| `--sijilna-radius-xl` | 18px | drawer, modal, sheet |
| `--sijilna-radius-pill` | 999px | avatar, status badge |

Elevation: Two soft shadows (6-8% navy) plus a 1px border; cards and drawers lift, the page does not.

### C.8 The two density levels

| | comfortable | compact |
| --- | --- | --- |
| Row height | 56px | 40px |
| Cell padding (block / inline) | 15px / 18px | 8px / 14px |
| Control height | 42px | 34px |
| Latin size / line-height | 15 / 24 | 14 / 22 |
| Arabic size / line-height | 17 / 30 | 16 / 28 |

### C.9 What it does to the screens that exist today

- **Datagrids** get more air (56 px comfortable rows), a tinted header, a blue row hover and a strong blue selected row - the states are unmistakable, which is what a sales floor asks for.
- **Primary buttons** move off the brand navy to `#004F7C`, a livelier step of the same family; the navy becomes the frame (`primary-900` headers, headings, focus). This is the direction's biggest brand decision.
- **Secondary buttons become soft chips** (pale `primary-50` fill, no border), which changes the look of every toolbar in the admin.
- **Cards lift** with a 1 px border plus a soft shadow, and radii go to 10-14 px, so the whole product reads as a modern web app.
- **Status pills carry real chroma.** A dashboard of four stages is instantly scannable and also the loudest screen in the product - which is a feature on a projector and a risk on a 22-inch monitor at 60 cm.

### C.10 What it costs to ship

Middle cost, highest execution risk. One family (Cairo) covers both scripts and the file set is the smallest, but the soft-filled secondary button and the larger radii change the shape of every toolbar, and the direction depends on hover, focus and shadow details being consistent across 211 views to look intentional rather than sloppy.


## 5. Honest open problems none of the directions solves

These are the things a brief that only shows pretty swatches would hide. Every direction has them.

1. **Non-text contrast on form borders.** WCAG 1.4.11 wants 3:1 for the boundary of a UI component.
   Tailwind's `gray-300` borders are about 1.6:1 and that is what the admin uses today. All three
   directions therefore carry a separate `--sijilna-border-input` (measured in each section) that is
   visibly heavier than what ships now. Adopting it is the single most noticeable change in the brief,
   and it is the one most likely to be argued about. The lighter `border-default` is kept for
   decorative dividers, where 3:1 is not required.
2. **Muted text.** The `text-muted` token is deliberately below 4.5:1 in all three directions (2.97-3.65:1).
   It is allowed for icon glyphs that duplicate a text label and for disabled controls, which WCAG
   exempts. It is **not** allowed for anything a user has to read, and the specimens only use it that way.
3. **Compact density and Arabic.** The brand floor (16 px / 500) means compact rows cannot buy space by
   shrinking Arabic type. Both densities therefore keep Arabic at 16 px and pay for compact rows out of
   padding and rules. That is a real constraint on how dense the grid can get in Arabic, and no direction
   escapes it.
4. **Font files.** The specimen sheets embed unsubsetted TTFs so they open with no network - 0.6 MB for
   Signal, 1.7 MB for Register, 2.6 MB for Qalam. Production must ship **WOFF2 subsets** (Arabic +
   Latin + digits), which lands around 15-35 KB per weight. No subsetting tool is installed on this
   machine, so that conversion is bead `crm-7qu.3`'s work, not this brief's.
5. **The IBM Plex reserved name.** Plex is OFL with a Reserved Font Name clause. Shipping subsets under
   the original family name is what Google Fonts does, but the safe reading of the OFL is: redistribute
   the files unmodified, keep the OFL text, and do not rename the family unless you rebuild it.
6. **Naskh in dense grids (direction B only).** Naskh is a calligraphic style; below about 17 px its
   strokes close up. B therefore needs a second Arabic sans for the grid, which is why it carries four
   families instead of two or three. That is a maintenance cost, not a rendering problem.

## 6. How to choose

Not a scoring matrix - three questions whose answers pick the direction for you.

1. **Who signs the cheque?** If it is a compliance, government or accounting buyer, A or B. If it is a
   sales director comparing you to HubSpot in a demo, C.
2. **Does the output leave the screen?** If quotes, statements and invoices are printed and posted, B
   (its paper-and-rule logic makes the print and the screen agree). If the product is mostly read on
   screen, A or C.
3. **How much risk do you want in the brand?** A changes the least and is nearly free. B changes the
   most and is the most distinctive. C is the most conventional of the three in the SaaS market - the
   richest colour, the least differentiated from the competition.

If the answer is "we are not sure", the question to resolve first is question 3, because it is the only
one that costs money to change later.

## 7. Verification

Everything in this brief is reproducible. Run from the repository root.

```bash
# 1. The sheets make no network request. Only svg xmlns namespaces may match.
grep -oE 'https?://[^"'"'"' )>]*' docs/design/specimens/*.html | sort -u
#   -> only the two svg namespace declarations (w3.org/2000/svg), nothing fetched

# 2. Every swatch carries a hex value and a token name.
grep -o 'class="chip-meta"' docs/design/specimens/register.html | wc -l   # 22 = 11 primary + 11 neutral

# 3. They render with the network disabled (fonts are embedded, not fetched).
google-chrome --headless=new --disable-gpu --no-sandbox --hide-scrollbars \
  --window-size=1200,6400 --host-resolver-rules="MAP * ~NOTFOUND" \
  --screenshot=/var/tmp/pi-scratch/shot.png \
  "file://$PWD/docs/design/specimens/register.html"
```

**What was actually performed, and what was not:**

- Rendered all four sheets in headless Chrome with `--host-resolver-rules="MAP * ~NOTFOUND"`, at
  1200 px wide, and read the resulting screenshots. The three full-page PNGs that command writes
  (`screenshot-register.png`, `-qalam.png`, `-signal.png`) are **not** committed to this repository -
  they are render evidence only, so a fresh clone has none of them in `docs/design/specimens/`.
  That is how the component layout, the Arabic shaping and the embedded fonts were confirmed.
- Confirmed the `grep` for `https?://` returns only the two SVG namespace declarations.
- **Not** performed: no browser other than Chrome, no real device, no dark-mode pass (dark values belong
  to the token bead `crm-7qu.3`; this brief proposes light values only), no RTL mirroring of the shell, no
  visual-regression baseline, and no test suite run - this brief touches no application code, so the
  suite has nothing to cover here.

## 8. The recorded choice

<!-- Filled in by the operator, not by an agent. Nothing is decided until this
     block carries a direction and the date. -->

| Field | Value |
| --- | --- |
| Chosen direction | _(not chosen yet)_ |
| Date | |
| Amendments requested | |
| Recorded by | |

## 9. What this document does not do

- It does not modify the mark, the wordmarks, or anything under `packages/`.
- It does not wire a token into the running UI, and it ships no CSS to the product.
- It does not decide the winner, and no direction is described as recommended.
- It does not cover dark mode, RTL mirroring, per-client white-label overrides or the visual-regression
  gate. Dark values belong to bead `crm-7qu.3`; the rest are the epic's companion beads.

**One repository caveat:** `docs/*` is gitignored except `docs/client-guide`, `docs/sales`,
`docs/ops` and `docs/legal`, so this file and its specimen directory do not enter a commit today.
Bead `crm-7qu.10` owns the `.gitignore` exception; until it lands, this brief lives on disk only, and
forcing it into a commit ahead of that decision would outrun the plan.

## 10. Appendix - the OKLCH source of every step

Every hex in this brief is derived from these numbers, so bead `crm-7qu.3` can regenerate or
nudge a step without guessing. Format: `step  L  C  H` (OKLCH; L 0-1, C unclamped before
gamut mapping, H in degrees). The pinned anchor is the step whose L, C and H are the brand colour
exactly.

**A primary** - anchor at primary-900 = brand navy

```
 50  0.9740  0.01000  243.90   -> #F1F7FD
100  0.9360  0.01800  243.90   -> #E0ECF5
200  0.8880  0.03000  243.90   -> #CADDED
300  0.8180  0.04500  243.90   -> #ACC7DF
400  0.7240  0.05800  243.90   -> #87ABC8
500  0.6300  0.06800  243.90   -> #658EB0
600  0.5480  0.07400  243.90   -> #4A7699
700  0.4740  0.07700  243.90   -> #326084
800  0.4080  0.07750  243.90   -> #1F4E71
900  0.3430  0.07622  243.90   -> #0B3C5D
950  0.2660  0.06800  243.90   -> #002843
```

**A neutral** - anchors at neutral-50 (cream), -600 (secondary ink) and -900 (ink)

```
 50  0.9768  0.00857   84.57   -> #FAF7F1
100  0.9470  0.01050   84.00   -> #F1EDE6
200  0.9000  0.01300   80.00   -> #E3DDD5
300  0.8430  0.01500   72.00   -> #D2CAC1
400  0.7420  0.01500   60.00   -> #B3A9A2
500  0.6200  0.01400   45.00   -> #8E847F
600  0.4998  0.02557  256.80   -> #5A6472
700  0.4300  0.03300  256.00   -> #445162
800  0.3450  0.04000  253.00   -> #2A3B4E
900  0.2625  0.04590  249.97   -> #12263A
950  0.1900  0.04200  248.00   -> #031526
```

**B primary** - anchor at primary-950 = brand navy

```
 50  0.9720  0.01000  246.00   -> #F1F7FC
100  0.9340  0.02000  246.00   -> #DFEBF6
200  0.8840  0.03400  245.50   -> #C7DCEE
300  0.8120  0.05000  245.00   -> #A7C6E0
400  0.7180  0.06200  244.80   -> #84A9C9
500  0.6260  0.07200  244.60   -> #628DB1
600  0.5480  0.07800  244.40   -> #47769B
700  0.4880  0.08000  244.10   -> #34648A
800  0.4300  0.07900  244.00   -> #245478
900  0.3840  0.07780  243.90   -> #17476A
950  0.3430  0.07622  243.88   -> #0B3C5D
```

**B neutral** - anchor at neutral-50 = brand cream; every step stays warm

```
 50  0.9768  0.00857   84.57   -> #FAF7F1
100  0.9520  0.01000   84.00   -> #F2EFE8
200  0.9120  0.01200   82.00   -> #E6E1D9
300  0.8640  0.01400   80.00   -> #D7D1C8
400  0.7820  0.01600   76.00   -> #BEB7AD
500  0.6640  0.01700   72.00   -> #9A9288
600  0.5520  0.01800   68.00   -> #797067
700  0.4620  0.01800   64.00   -> #60574E
800  0.3720  0.01750   60.00   -> #473E37
900  0.2860  0.01650   58.00   -> #312822
950  0.2000  0.01500   56.00   -> #1B140F
```

**C primary** - anchor at primary-900 = brand navy; primary-800 is the action colour

```
 50  0.9760  0.01600  240.00   -> #F0F8FF
100  0.9420  0.03000  241.00   -> #DBEFFF
200  0.8940  0.05000  242.00   -> #C0E1FB
300  0.8240  0.07200  242.50   -> #9DCCF1
400  0.7300  0.09400  243.00   -> #72AFDE
500  0.6340  0.11000  243.30   -> #4891C8
600  0.5500  0.11800  243.60   -> #2478B1
700  0.4780  0.11600  243.70   -> #036299
800  0.4100  0.10400  243.80   -> #004F7C
900  0.3430  0.07622  243.88   -> #0B3C5D
950  0.2680  0.06600  243.90   -> #002843
```

**C neutral** - anchor at neutral-50 = brand cream; the ramp cools as it darkens

```
 50  0.9768  0.00857   84.57   -> #FAF7F1
100  0.9560  0.00900   90.00   -> #F3F0EA
200  0.9180  0.00750  110.00   -> #E4E4DF
300  0.8720  0.00350  180.00   -> #D2D6D5
400  0.7900  0.00300  220.00   -> #B8BBBC
500  0.6720  0.01000  240.00   -> #91979B
600  0.5560  0.01500  248.00   -> #6D747C
700  0.4600  0.01800  252.00   -> #515962
800  0.3660  0.02000  254.00   -> #383F49
900  0.2700  0.02200  256.00   -> #1F2731
950  0.1900  0.02300  258.00   -> #0D141E
```

Semantic steps are stated directly in each direction section; they were picked the same way,
with the text step walked darker until it cleared 4.5:1 on both sanctioned surfaces.