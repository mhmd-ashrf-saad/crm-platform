# Client guide — operator notes

The Arabic client manual for Sijilna (this Krayin fork). Clients read `ar/`. Everything in `_internal/` is for whoever builds and maintains the guide, and is never shipped to a client.

**Status:** all 17 chapters are written and marked `جاهز` in `ar/README.md`. Screenshots are pending capture: every `🖼️` line is a placeholder with a reserved `assets/NN-name.webp` path.

**Language:** the guide is Arabic only. There is no English edition and none is planned; this file is the only English artifact.

## File inventory

| Path | Audience | Purpose |
| --- | --- | --- |
| `ar/README.md` | client | The first page a client opens: what the manual is, a "من أنت؟" router by role, and the full 17-chapter table |
| `ar/01-about-sijilna.md` … `ar/17-glossary.md` | client | The 17 chapters, in reading order: orientation, login, dashboard, leads and pipeline, lead record, contacts, activities, quotes, products and inventory, mail, team and permissions, configuration, automation, web forms and import, troubleshooting, limits and provider, glossary |
| `ar/assets/` | client | Screenshots referenced as `assets/NN-name.webp`; holds only a `.gitkeep` until capture |
| `_internal/facts.md` | writers | The verified product facts digest. Single source of truth; every claim cites the file it came from |
| `_internal/conventions.md` | writers | Writing conventions: tone, header block, heading levels, UI quoting, callouts, screenshot placeholders, terminology |
| `_internal/screenshots.md` | operator | The single screenshot catalog for all 17 chapters: file name, Chapter, screen, exact URL, what must be visible, preconditions |

## How to add a chapter

1. **Read `_internal/conventions.md` in full**, then the facts digest for your subject.
2. **Confirm any string you intend to quote** against `packages/Webkul/Admin/src/Resources/lang/ar/app.php`. Quote it; do not translate it. If a string is not in the digest and not in that file, leave the claim out and write `<!-- TODO(تحقق): … -->`.
3. **Create `ar/NN-topic.md`** using the chapter name already fixed in `ar/README.md`. Do not rename the file — the index and the cross-references depend on those names.
4. **Open with the three-line header block** (لمن هذا الفصل / الهدف / الوقت المتوقع) and keep exactly one `#` heading. The expected time always carries its unit: `10 دقائق` for 3–10, `14 دقيقة` for 11 and above.
5. **Close with `## حدود هذا الجزء`** (3–5 bullets, each ending in a workaround) and an invisible `<!-- المصادر: … -->` comment listing the source files.
6. **Aim for 110–260 lines.** Cross-reference other chapters as `[راجع الفصل NN](NN-file.md)` instead of repeating them.
7. **Add your screenshots to the chapter's own section of `_internal/screenshots.md`** — one catalog now, in chapter order, in the same six-column table shape.
8. **Flip your row in `ar/README.md`** to `جاهز` and link the file.

## The screenshot convention

Every image is one line, on its own, exactly this shape:

```markdown
> 🖼️ **صورة:** `assets/NN-name.webp` — <what must be visible> (`<screen path>`)
```

- `NN` is the two-digit chapter number; `name` is lowercase Latin with hyphens.
- Place it after the paragraph or list that describes the screen, never before.
- `ar/assets/` must exist even while empty — it holds a `.gitkeep` for that reason.
- Capture at 1400 px wide (1000 px for modals), `webp` quality 85, Arabic locale confirmed first. Preconditions and sample data for each shot are in `_internal/screenshots.md`.
- Record the product version you captured against. A screenshot from an older version misleads silently; the build currently referenced is `2.2.7`.

## Regenerating and converting later

The guide is plain Markdown with relative links and relative image paths, so it needs no build step to stay readable in the repository or on any static host.

- **Screenshots to `webp`:** `magick input.png -resize 1400x -quality 85 output.webp`. Keep the source PNGs out of `ar/assets/` — only the `.webp` files belong there.
- **To a single document (client handover):** run `pandoc` over the chapters in numeric order, resolving `ar/` as the resource path, for example `pandoc -f markdown -t docx --resource-path=ar ar/README.md ar/0[1-9]-*.md ar/1[0-7]-*.md -o guide.docx`. The relative links then resolve against the same directory.
- **To PDF:** the same `pandoc` call with `--pdf-engine=weasyprint` preserves RTL better than LaTeX engines. Arabic shaping in generated PDFs must be checked by eye before shipping — this is the same risk class as the product's own quote PDF (mPDF + ArPHP), which no test suite guards.
- **Nothing here is generated.** There is no template, no include mechanism, and no script. A chapter is one file; the index is one hand-maintained table; the screenshot catalog is one hand-maintained table.

## Known gaps

- **The owner has four blanks to fill before handover** — all marked `<!-- TODO(المالك): … -->`, none of them inventable:
  - `ar/01-about-sijilna.md` — the support channel: email, phone or ticket link, plus working hours.
  - `ar/README.md` — the company name and the same support channel.
  - `ar/16-limits-and-provider.md` — two TODOs: the service table with real names and numbers, and an explicit list of what the handover does *not* include (team training, legacy data migration, post-handover configuration changes).
- **No screenshots exist yet.** Every `🖼️` path in the chapters is already in `_internal/screenshots.md` with its preconditions; `ar/assets/` is empty apart from `.gitkeep`.
- **`_internal/facts.md` records real product defects** that a chapter must state rather than work around: the settings landing page lives at `/{admin}/settings/settings`; the quote grid registers its mass-delete action twice; a throttled login is rendered as an HTTP 500; the mail compose screen reports success even when sending failed; and a quote email leaves no record in المرسل. Writers should not "fix" the wording of these — the client will meet them.
- **Some shipped labels contradict each other** (the quotes menu reads «العروض» while the screen reads «عروض الأسعار»; both `settings` and `configuration` read «الإعدادات»; the attributes *grid* labels a type «قائمة منسدلة» while the *create* screen calls it «اختيار»). The deviation table in `_internal/conventions.md` §8 and `_internal/facts.md` §4/§8 are authoritative; do not re-decide per chapter.
- **The Arabic locale is not what this checkout ships.** `.env` sets `APP_LOCALE=en`, so an operator verifying the screens must change the language setting before capturing anything.
