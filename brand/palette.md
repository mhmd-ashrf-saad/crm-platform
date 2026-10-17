<!-- Context: brand/palette.md | Priority: high | Version: 1.0 | Updated: 2026-10-16 -->

# Sijilna Palette

Five colours, four jobs. Measured with the WCAG 2.1 relative-luminance formula
(`(L1+0.05)/(L2+0.05)`); every sanctioned text pair is listed below.

## Colours and roles

| Hex | Name | Role | Never |
| --- | ---- | ---- | ----- |
| `#0B3C5D` | Register blue | Identity, headers, table rules, invoice rules, logo ink | — |
| `#1B6E5A` | Proof green | Paid / settled state, success badges, the "recorded" tick | Body text |
| `#B07D2B` | Grounded ochre | Accents only: rules, marks, small non-text decoration | Body text, text under any size, any word |
| `#FAF7F1` | Paper | Surfaces, cards, reversed logo fill | — |
| `#12263A` | Ink | Body text | — |

Secondary text is `#5A6472` on the two light surfaces. It is a tint of ink, not a
sixth identity colour.

## Measured contrast (WCAG 2.1 AA needs 4.5:1 normal, 3:1 large/UI)

Sanctioned text pairs:

| Text on surface | Ratio | Verdict |
| --- | --- | --- |
| `#12263A` on `#FAF7F1` | 14.40:1 | AA, AAA |
| `#12263A` on `#FFFFFF` | 15.40:1 | AA, AAA |
| `#0B3C5D` on `#FAF7F1` | 10.80:1 | AA, AAA |
| `#0B3C5D` on `#FFFFFF` | 11.55:1 | AA, AAA |
| `#1B6E5A` on `#FFFFFF` | 6.13:1 | AA |
| `#1B6E5A` on `#FAF7F1` | 5.73:1 | AA |
| `#5A6472` on `#FFFFFF` | 6.00:1 | AA |
| `#5A6472` on `#FAF7F1` | 5.61:1 | AA |
| `#FAF7F1` on `#0B3C5D` | 10.80:1 | AA, AAA (reversed) |
| `#FAF7F1` on `#12263A` | 14.40:1 | AA, AAA (reversed) |
| `#FAF7F1` on `#1B6E5A` | 5.73:1 | AA (reversed badge) |
| `#FFFFFF` on `#1B6E5A` | 6.13:1 | AA (badge) |

Ochre is excluded from the text table on purpose:

| Text on surface | Ratio | Verdict |
| --- | --- | --- |
| `#B07D2B` on `#FAF7F1` | 3.38:1 | **Fails 4.5:1 — non-text only** |
| `#B07D2B` on `#FFFFFF` | 3.61:1 | **Fails 4.5:1 — non-text only** |
| `#B07D2B` on `#0B3C5D` | 3.20:1 | **Fails 4.5:1 — non-text only** |

## The contrast rule

1. Body text is `#12263A` (or `#0B3C5D` for headings) on `#FAF7F1` / `#FFFFFF` only.
2. Secondary text is `#5A6472` or darker. Nothing lighter.
3. Ochre is never text and never sits under a word. It is a rule, a mark or a
   border, and it must be at least 2 px thick in UI so it is not read as text.
4. Reversed text is `#FAF7F1` on `#0B3C5D`, `#12263A` or `#1B6E5A`.
5. Arabic body text is 16 px minimum at weight 500. Arabic glyphs carry more
   vertical detail per em than Latin, so a size that reads in Latin is under-set
   in Arabic; `#5A6472` is the floor for secondary Arabic text, never a lighter grey.

## Reproducing the numbers

```bash
python3 - <<'PY'
def lum(h):
    h = h.lstrip('#'); c = [int(h[i:i+2],16)/255 for i in (0,2,4)]
    c = [x/12.92 if x <= 0.04045 else ((x+0.055)/1.055)**2.4 for x in c]
    return 0.2126*c[0] + 0.7152*c[1] + 0.0722*c[2]
def ratio(a, b):
    la, lb = lum(a), lum(b); hi, lo = max(la, lb), min(la, lb)
    return (hi + 0.05) / (lo + 0.05)
print(round(ratio('#B07D2B', '#FAF7F1'), 2))   # 3.38
PY
```
