<!-- Context: guides/arabic-terminology.md | Priority: critical | Version: 1.1 | Updated: 2026-11-04 -->

# Arabic terminology — the one glossary

**Read this before changing any Arabic string, seeder value, view label or client-guide sentence.**
Sijilna is Arabic-first and Egypt-first. Every Arabic word a user sees is either on this page or is a
bug. Nothing gets renamed until the operator approves a row — approval is per row, this table is the
review surface.

Beads origin: `crm-mox.1`. Application of these decisions is `crm-mox.3`, not this file's job.

---

## Operator decisions — 2026-11-04

The review table below was taken to the operator row by row on **2026-11-04** and is **approved as
written: 26 of 26 rows, none rejected.** The five decisions that came out of that review are final for
the apply lane (`crm-mox.3`). They are recorded here, in this file's own voice, so no later agent
reopens them; §6 keeps each one's evidence trail.

1. **All 26 rows approved, including rows 1 and 2.** `lead` → **الفرصة** and `pipeline` →
   **مسار المبيعات** therefore *approve* the overturn of `brand/copy-deck.md` §3, which had committed
   the rebrand to `عميل محتمل` and a `قناة`-family pipeline name. §3 describes itself as reusing the
   shipped Arabic, so it recorded what shipped rather than deciding it: **the deck and the client guide
   get rewritten to match this table**, not the other way round. *Apply lane:* nothing in §3 blocks a
   row here.
2. **Deal owner = `مندوب المبيعات`** (resolves OQ-5). It replaces the seeded `مالك المبيعات` (8 `I`)
   and beats both runners-up: `المسؤول` is AccFlex's own field label and reads neutrally for a manager
   who never calls, but it is vaguer than the label the grid already shows — and in this tree
   `المسؤول` already means *administrator* (4 `A`); `مالك الفرصة` is the precise CRM term, but `مالك`
   reads as legal ownership to an Egyptian reader and it ships `0` times. *Apply lane:* the seeded
   owner attribute and the grid column become the same word.
3. **`customer`: reserve `عميل`, keep the gap** (resolves OQ-1). No existing string may be renamed to
   `عميل`, and no person, organization or deal may be labelled with it; `جهات الاتصال` stays the
   section. The word is held for a real customer record, and beads `crm-ytp` inherits that constraint —
   the invoicing epic must not arrive under a second name for it.
4. **`dashboard` → `لوحة المعلومات`**, overruling the "keep as shipped" recommendation. It replaces
   `لوحة القيادة` — **3 occurrences in `A`** (`A`:2115 widget, `A`:2217 page title, `A`:2362 menu) plus
   **36 mentions** across `docs/` and `brand/`. `لوحة التحكم` is untouched and keeps its different
   referent: the admin panel as a whole, never this page. *Origin:* an **operator decision on Egyptian
   usage**, not a research finding — no external source settled this row (§9.4), and none is claimed
   for it.
5. **`warehouse` → `المخزن` / pl. `المخازن`**, overruling the "keep as shipped" recommendation. It
   replaces `المستودع` — **14 occurrences in `A`**, 5 of them the plural `المستودعات`, plus **33
   mentions** across `docs/` and `brand/`. The inventory card keeps `المخزون`, so `المخزن` / `المخزون`
   now read as one family on one screen. *Origin:* an **operator decision on Egyptian usage**, not a
   research finding (§9.4).

OQ-4 (`مسار المبيعات` versus AccFlex's `المسار البيعي`) was settled at the same review: **`مسار
المبيعات` stays**, as recommended. §6 now holds five resolved entries (OQ-1 to OQ-5) and the three
questions these decisions genuinely do not reach (OQ-6 to OQ-8).

---

## Read this first — the 26 rows that change what ships

Everything else in §4 confirms the shipped Arabic. **These 26 change it.** All 26 are approved as of
2026-11-04, and each one keeps its evidence in the row's `source` and `reason` cells so the approval
can be re-checked rather than re-argued.

| # | concept | ships today | approved | why it must change (one line) |
| --- | --- | --- | --- | --- |
| 1 | `lead` (the deal) | `عميل محتمل` / `العملاء المحتملون` | **الفرصة / الفرص** | The market definition of `الفرصة البيعية` is exactly this record, and `عميل محتمل` is the *unqualified contact* — which is a different thing and is already a stage name here. |
| 2 | `pipeline` | `خط الأنابيب` / `الأنابيب` / `قنوات البيع` / `قناة المبيعات` | **مسار المبيعات / مسارات المبيعات** | Four names for one concept, and two of them (`قناة`) collide with the *channel* concept the `source` field already carries. |
| 3 | `rotten lead` | `متعفن` / `الفاسد` | **مهمل** | `فاسد` reads as "corrupt" in Egyptian Arabic; the product already ships the right phrase in the mail screen. |
| 4 | `rotten days` field | `أيام التعفن` / `مؤشر التعفن` | **أيام الإهمال / مؤشر الإهمال** | Same word as the state, so a settings field and a kanban badge agree. |
| 5 | `owner` | `مالك المبيعات` (seeded attribute) | **مندوب المبيعات** | `مالك` reads as a legal owner; `مندوب المبيعات` is the grid column users already see. |
| 6 | `stage: won` | `مُنتصر` / `فاز` / `ربحت` | **مكتسب** | Five forms ship for two states; the dashboard and `PDF` already say `المكتسب`. |
| 7 | `stage: lost` | `خسر` / `خسرت` | **مفقود** | Same, and it pairs with `مكتسب`. |
| 8 | `won value` | `قيمة الربح` | **قيمة الإغلاق** | `ربح` is *profit* — a different number from the deal's value. |
| 9 | `organization` | `المنظمة` / `المنظمات` | **المؤسسة / المؤسسات** | `منظمة` reads as an NGO; the screens already say `المؤسسة`. |
| 10 | `contact person` | `شخص الاتصال` | **جهة الاتصال** | The product already says `جهة الاتصال` for the same object in five places. |
| 11 | `quote` (one document) | `الاقتباسات` | **عرض سعر** | `اقتباس` means a *citation*, not a priced document — and the product uses it that way elsewhere. |
| 12 | `quotes` (section) | `العروض` | **عروض الأسعار** | The menu and the screen title for the same list must read the same. |
| 13 | `sub total` | `الإجمالي الفرعي` (installer) | **المجموع الفرعي** | The other five occurrences in the tree already say `المجموع الفرعي`. |
| 14 | activity's deal field | `القيادة` | **الفرصة** | `القيادة` means "leadership" — a literal rendering of the English word. |
| 15 | `configuration` menu | `الإعدادات` (same label as the other menu item) | **التهيئة** | Two different screens render the identical label; the ACL already picked `التهيئة`. |
| 16 | data scope field | `عرض الصلاحية` | **نطاق البيانات** | `عرض الصلاحية` parses as "the viewing of the permission"; the admin error strings already say `نطاق بياناتك`. |
| 17 | `attribute` | `السمات` | **الخصائص** | `سمة` reads as a personal trait; the working screens already say `الخصائص`. |
| 18 | `tag` | `العلامات` / `العلامة` | **الوسوم / الوسم** | `علامة` collides with the brand mark; the shipped majority is already the وسم family — 16 `A` against 11 `A` for `العلامات` + `العلامة` (5 + 6, counted once each). |
| 19 | `event` (automation) | `الفعاليات` | **الأحداث** | `فعالية` is an event you *attend*, not a trigger; `حدث` is already shipped 20 times. |
| 20 | `data transfer` | `نقل البيانات` | **استيراد البيانات** | Every string inside that screen already says `استيراد`. |
| 21 | `inbox` folder | `الوارد` | **البريد الوارد** | A bare adjective with no noun, beside the menu's `البريد الوارد`. |
| 22 | `sent` folder | `تم الإرسال` | **المرسل** | A past-tense sentence used as a folder name. |
| 23 | `trash` action | `تم النقل إلى سلة المهملات` | **نقل إلى سلة المهملات** | Same class of error; a control label is a verb phrase. |
| 24 | `Existing Business` seed | `عمل موجود` | **عمل قائم** | `موجود` means "present/available", not "existing". |
| 25 | `dashboard` | `لوحة القيادة` — **3 `A`** (`A`:2115 widget, `A`:2217 page title, `A`:2362 menu), plus **36 mentions** across `docs/` and `brand/` | **لوحة المعلومات** | `لوحة القيادة` is a cockpit calque; an Egyptian manager who has opened Excel or Power BI has already read `لوحة المعلومات`. **Operator decision on Egyptian usage, 2026-11-04 — no external source settles this row, and none is cited for it.** `لوحة التحكم` is untouched: it means the admin panel. |
| 26 | `warehouse` | `المستودع` / `المستودعات` — **14 `A`** family occurrences, 5 of them the plural, plus **33 mentions** across `docs/` and `brand/` | **المخزن / المخازن** | The Egyptian word is `المخزن`, and `المخزون` (inventory) already ships beside the Gulf form on the same screen. **Operator decision on Egyptian usage, 2026-11-04 — no external source settles this row, and none is cited for it.** |

Four of these contradict `brand/copy-deck.md` §3, which committed the rebrand to `عميل محتمل`, a
`قناة`-family pipeline name, `لوحة القيادة` and `المستودعات` (rows 1, 2, 25 and 26). §3 says in its own
words that the Arabic "reuses the terminology already in" the Admin `ar` file — it is a record of what
shipped, not an independent decision — so the deck is rewritten to match this table rather than the
table bending to the deck. Rows 25 and 26 had no external source at all: they rest on the operator's
read of Egyptian usage (§9.4) and that is the whole of their authority.

---

## 0. How to read the table

File aliases used in every "rejected" cell, with the measured occurrence count in that file:

| Alias | File |
| ----- | ---- |
| `A`   | `packages/Webkul/Admin/src/Resources/lang/ar/app.php` |
| `I`   | `packages/Webkul/Installer/src/Resources/lang/ar/app.php` |
| `V`   | `lang/ar/validation.php` |
| `W`   | `packages/Webkul/WebForm/src/Resources/lang/ar/app.php` |
| `G`   | `packages/Webkul/GoogleContact/src/Resources/lang/ar/app.php` |

Counts in the table are **occurrences** (`grep -o -F`), measured 2026-11-04 against the working tree,
not line counts and not file counts. Reproduce any cell with:

```bash
grep -o -F 'المصطلح' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l
```

A count of `0` means: measured and absent. `—` means: the concept does not appear in the shipped
Arabic at all (usually a seeded-data value the DB currently holds in English).

**Confidence**
- **high** — a shipped majority plus at least one external source agree, and the Egyptian/Gulf position is not contested.
- **medium** — sources agree but the shipped product contradicts itself, so the answer is a choice between coherent options.
- **low** — no source settled it; the row is a proposal, and §6 records what would settle it.

---

## 1. Register rule, per surface

One register per surface, not one register for the product. Sijilna sells self-hosted CRM to Egyptian
SMBs whose users are sales reps and an accountant, not translators. Stiff MSA in a button reads as a
government form; full Egyptian colloquial in a contract PDF reads as unserious.

| Surface | Register | Test | Example |
| ------- | -------- | ---- | ------- |
| Buttons, labels, column headers, tabs, menu items | **Modern MSA, short** | Would it fit on a 20-character menu item and still be a noun? | `حفظ`, `المصدر`, `المرحلة`, `جهات الاتصال` |
| Empty states, success/error messages, validation | **Modern MSA, full sentence, no dialect markers** | Does it survive being copy-pasted into a support ticket? | `لم يتم العثور على عروض لهذه الفرصة.` |
| Domain nouns (the things the business is about) | **Egyptian business usage** — the word an Egyptian salesperson or accountant says out loud | Say the sentence to a Cairo sales manager; if they correct you, it is wrong | `عرض سعر`, `مندوب المبيعات`, `عميل مهمل` |
| Documents and tax artifacts (quote, invoice, tax number) | **Egyptian regulatory vocabulary** — the authority's own word, even when it is stiffer than the UI | Does the word match what the accountant files with ETA? | `فاتورة`, `عرض سعر`, `إشعار دائن` |
| Protocol, format and identifier tokens | **Kept in Latin script** | Is it spelled in Latin in every other Arabic product in the market? | `IMAP`, `SMTP`, `PDF`, `SKU`, `CSV`, `API` |
| Seeded data (pipeline stages, sources, types) | **Domain nouns**, because they are rows a client will read and occasionally edit | Same test as domain nouns | `متابعة`, `مكتسب`, `عمل قائم` |
| Console output, `.env`, log lines, code comments | **English, unchanged** | — | — |

Two consequences worth stating because they get argued about:

- **A domain noun beats a literal translation, even when the literal is more accurate.** `عميل مهمل`
  ("a neglected customer") is what the sales manager says; `عميل فاسد` is a literal rendering of
  `rotten lead` and reads as "a corrupt customer". Ship the domain noun.
- **Chrome does not adopt domain slang.** A button labelled `يلا` is not Egyptian-friendly, it is
  unshippable. The dialect lives in nouns, not in verbs.

---

## 2. Numeral rule

**Western digits (0–9) everywhere. No Arabic-Indic digits (`٠١٢…`) anywhere in the product.**

This is already the shipped state and it must not drift — measured with an **explicit character class**, because
`grep '[٠-٩]'` is a multibyte byte range and silently matches Latin digits too:

```bash
grep -cP '[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]' \
  packages/Webkul/Admin/src/Resources/lang/ar/app.php        # 0
grep -cP '[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]' \
  packages/Webkul/Installer/src/Resources/lang/ar/app.php    # 0
```

Shipped examples that fix the rule: `آخر 6 أشهر` (A:301), `آخر 3 أشهر` (A:302), `آخر 30 يومًا`,
`3 أشهر` / `9 أشهر` (A:2140–2141), `110px X 110px` (A:98), `الاحتمالية (%)` (A:1247).

Rules that follow from it:

1. Counts, dates, percentages, money, durations: Western digits, Latin `%`, Arabic word for the unit
   after the number — `آخر 30 يومًا`, not `آخر ٣٠ يومًا` and not `30 days`.
2. Placeholders (`:days`, `:count`, `:version`, `:week`) stay exactly as written; the digit arrives
   from PHP and is Western.
3. Arabic grammar still applies to the counted noun: `:days يومًا`, `:days أيام` per the number.
4. Long identifiers the user typed (IBAN, phone, tax registration number, SKU) are never localised.
5. A numeral inside an exported `PDF` uses the same Western digits as the screen. No exceptions for
   "officialness" — ETA's own e-invoice documents use Western digits.

**The one numeral disagreement in the tree today** is typographic, not numeric: `1-month` is rendered
`شهر واحد` while `3-months` is `3 أشهر`. That is correct Arabic (one takes a word, three takes a
digit) and is not a contradiction. Do not "fix" it.

---

## 3. Kept in Latin script

These are written in Latin in the Arabic UI, in the client guide and in support replies. They are not
untranslated words — they are tokens that Arabic-market products leave in Latin.

| Token | Why it stays Latin | Note |
| ----- | ------------------ | ---- |
| `IMAP`, `SMTP`, `POP3` | Mail protocol names. Never transliterated in an Arabic admin panel. | `إعدادات IMAP` is already shipped and correct. |
| `PDF` | Format name on a button (`تصدير PDF`) and on the quote attachment. | Never `بي دي إف`. |
| `CSV` | Export/import format. | |
| `SKU` | The product code. Must match the string printed on the warehouse label and in the `PDF`. | The column header is Arabic (`رمز المنتج`), the value and the token are Latin — that pairing is right. |
| `API`, `URL`, `JSON`, `HTTP`, `HTTPS` | Technical surfaces only (webhook endpoint, import errors). | `عنوان URL` is shipped and correct. |
| `base64` | Appears inside a technical error string (A:1869). | Left as-is; it is a protocol token. |
| `ID` | *Not* kept — see §5 row 8. Use `المعرف`. | The one Latin token this glossary removes. |
| `Google`, `Sijilna`, `سجلنا` | Brand names. `Sijilna` is written in Latin where the wordmark appears, `سجلنا` where the text runs in Arabic prose. | Both are approved; do not "correct" one into the other. |
| `webhook` → **`ويب هوك`** | Deliberately *not* Latin. Arabic technical teams in Egypt say "ويب هوك", and the shipped string is that transliteration 17 times in `A`. | Kept as an Arabic-transliterated domain term, not a Latin token. |
| `Kanban` | *Not* a product word. The screen is `العملاء المحتملون` / `الفرص` with two view icons and no label. Do not introduce `كانبان` into the UI. | The client guide may use it to describe the view, the UI may not. |

Rule: a token stays Latin **only if** the Arabic rendering would be unrecognisable or would not match
what the user reads elsewhere (protocol spec, printed label, tax filing). Everything else is Arabic.

---

## 4. The glossary

Ordered by surface, then by the entity hierarchy. `overturns shipped term?` is the column the operator
should read first: `yes` means the shipped Arabic changes, `no` means it is confirmed as-is.

### 4.1 Pipeline entities

| concept | approved Arabic | rejected renderings present in the product | source | reason | register | EG vs Gulf | confidence | overturns shipped term? |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `lead` (the tracked deal: value, stage, probability, expected close date, products, quotes, won/lost) | **الفرصة** — pl. **الفرص** | `عميل محتمل` (12 `A` + 1 `I`), `العملاء المحتملون` (5 `A`), `القيادة` (2 of the 5 `A` hits, lines 1707/1735 — the other 3 are `لوحة القيادة`), `قائمة العملاء الخاصة بك` / `إنشاء عميل` (kanban empty state, `A`:1993–1994). **`الفرص` as a plural does not ship at all** (`الفرص` matches only inside `الفرصة`). | **Settled by an Egyptian ERP's own Arabic docs** — AccFlex: «الفرصة البيعية (Opportunity) هي صفقة قيد التنفيذ … تقع في مرحلة واحدة من مسار بيعي واحد», with fields المرحلة / القيمة / تاريخ الإغلاق المتوقع / المسؤول. Naffes CRM defines it the same way («صفقة مفتوحة في المسار لها قيمة وموعد إغلاق متوقع») and explicitly separates it from العميل المحتمل. The product already uses `الفرصة: :title`, `حول الفرصة`, `...لهذه الفرصة` on the record screen (7×, `A`:2003–2051), so the tree is already half-converted. | The record carries stage + value + probability + expected close date + products + quotes, which is the market definition of **فرصة**, not of **عميل محتمل** (which the same sources define as an *unqualified contact*). Keeping `عميل محتمل` also collides with the seeded stage that is *also* named `عميل محتمل`. | domain | same | **high** | **yes** — was `عميل محتمل` / `العملاء المحتملون` |
| `prospect` (seed pipeline stage 3) | **عميل محتمل** | — | Shipped seed (`I`:96). AccFlex and Naffes both define **العميل المحتمل** as the pre-qualification stage of an interest signal; Egyptian sales usage matches. | Keeping it here is what makes the `lead` overturn above coherent: the stage means "prospect", the record means "opportunity". Two different objects, two different words. | domain | same | high | no — kept, and now unambiguous |
| `opportunity` (as a separate entity) | **does not exist as its own entity** — the approved `lead` record *is* the opportunity | — | Product has one table (`leads`), no `opportunities`. | Do not introduce an opportunity entity in Arabic that has no English counterpart in the code. One word, one record. | domain | same | high | n/a |
| `pipeline` | **مسار المبيعات** — pl. **مسارات المبيعات**; short form in a picker: **المسار** | `خط الأنابيب` (12 `A`: 993, 1027, 1225–1278) + `خطوط الأنابيب` (1 `A`:1223), `الأنابيب` (15 `A` in total — 13 of those are inside the two `خط…` forms just listed, the 2 bare ones are at `A`:2408–2409), `قنوات البيع` (2 `A`: 28, 2354), `قناة المبيعات` (3 `I`:88, default pipeline name), `المسارات` (1 `A`: 1952) | **Settled by AccFlex**, which names the pipeline **المسار البيعي** throughout its Arabic UI and docs («يقع في مرحلة واحدة من مسار بيعي واحد», «لوحة المسار البيعي»), and by Naffes («صفقة مفتوحة في المسار»). `خط أنابيب` is a literal calque of *pipeline* (a physical pipe); `قناة بيع` is a real, different marketing concept (direct vs reseller channel) that the `source` field already carries. `كل المسارات` is already shipped in the pipeline picker (`A`:1952). | One concept, one word, no collision with "channel", and the market reference already says `مسار`. | domain | **Gulf-divergent** — Gulf products favour `خط أنابيب المبيعات`; Egypt code-switches to "البايبلاين" verbally and uses `مسار` when it needs an Arabic noun | **high** | **yes** — was `خط الأنابيب` / `الأنابيب` / `قنوات البيع` / `قناة المبيعات` |
| `stage` | **المرحلة** — pl. **المراحل** | — | Shipped and consistent (`A`:8, `I`). | The word Egyptian sales teams use for a pipeline column. | domain | same | high | no |
| `probability` | **الاحتمالية (%)** | — | Shipped (`A`:1247). | Kept because it is short and precise; `نسبة الإغلاق` is longer and no clearer on a label. | chrome | same | medium | no |
| `rotten lead` / stale deal | **عميل مهمل** — and the age of neglect: **مهمل منذ :days يومًا** | `عميل محتمل متعفن` (1 `A`:1890), `العميل الفاسد منذ :days أيام` (1 `A`, kanban), `متعفن` (1), `الفاسد` (1) | The product itself already ships the right phrase elsewhere: `العميل المحتمل مهمل لمدة :days يومًا` (mail, `A`:1822). Egyptian sales usage: a lead nobody followed up is **مهمل**. | `متعفن`/`فاسد` are literal renderings of *rotten*; `فاسد` in Egyptian Arabic reads as "corrupt" and is actively wrong for a customer. | domain | same | high | **yes** — was `متعفن` / `الفاسد` |
| `rotten days` (the threshold field) | **أيام الإهمال** | `أيام التعفن` (3 `A`: 1238, 1246, 1266), `مؤشر التعفن` (1 `A`:1933) | Same as above. | Same noun as the state, so a settings field and a kanban badge use one word. | domain | same | high | **yes** — was `أيام التعفن` / `مؤشر التعفن` |
| `owner` / `sales owner` (the user responsible for a deal) | **مندوب المبيعات** | `مالك المبيعات` (8 `I`: 10, 18, 29, 30, 37, 38, 58, 63); considered and rejected at the 2026-11-04 review: `المسؤول` and `مالك الفرصة` (neither names this field — `مالك الفرصة` ships `0` times anywhere, and the 4 `A` + 2 `I` hits on `المسؤول` all mean *administrator*) | Shipped majority in the working UI is `مندوب المبيعات` (5 `A`: 406, 433, 1882, 1909, 1934). Egyptian sales usage: the person who owns a deal is **المندوب**. **Decided by the operator on 2026-11-04; OQ-5 in §6 is resolved.** | `مالك` is the CRM-import "owner" that reads as a *legal* owner to an Egyptian reader; `مندوب المبيعات` is what the team calls him, and it is already the grid column. `المسؤول` is AccFlex's own label and works for a boss who never calls, but it is vaguer about the role and already carries the *administrator* sense in this tree; `مالك الفرصة` is the precise CRM term but reads as legal ownership and would be a brand-new word. | domain | same | high | **yes** — was `مالك المبيعات` in the seeded attribute labels |
| `stage: won` | **مكتسب** | `مُنتصر` (1 `I`:98), `فاز` (2 `A`: 1249, 1281), `ربحت` (2 `A`, kanban `stages.won` + record view) | The product's own dashboard card and `PDF` report use **المكتسب** (3 `A`) and **الإيرادات المكتسبة** — the most-read surface in the product already says it. | A state label must be an adjective, not a verb: `ربحت`/`فاز` are past-tense verbs whose gender/number agreement flips with the subject (`الصفقة ربحت` vs `العميل فاز`), and they are already inconsistent with each other. | domain | same | high | **yes** — was `مُنتصر` (seed) / `فاز` (editor) / `ربحت` (kanban) |
| `stage: lost` | **مفقود** | `خسرت` (2 `A`), `خسر` (2 `A` — the other 2 of the 4 `A` hits are inside `خسرت`) | Same evidence pair; `مفقود` is already shipped as the seed stage and in the `PDF` report. | Same verb/adjective argument, and it pairs with `مكتسب`. | domain | same | high | **yes** — was `خسرت` / `خسر` |
| `won revenue` / `lost revenue` | **الإيرادات المكتسبة** / **الإيرادات المفقودة** | — | Shipped dashboard card (`A`, `revenue.won-revenue` / `lost-revenue`). | Already coherent with the approved stage adjectives; changing it would reintroduce the split this glossary removes. | domain | same | high | no |
| `won value` (value the deal closed at) | **قيمة الإغلاق** | `قيمة الربح` (2 `A`, kanban `stages.won-value` + record view) | Egyptian accounting usage: `ربح` is *profit*, a different number from deal value. | A won deal's value is not its profit; using `ربح` tells the user the wrong number. | domain | same | high | **yes** — was `قيمة الربح` |
| `lost reason` | **سبب الخسارة** | — | Shipped. | The correct Egyptian business phrase; `سبب الضياع` would be wrong. | domain | same | high | no |

### 4.2 People and organizations

| concept | approved Arabic | rejected renderings present in the product | source | reason | register | EG vs Gulf | confidence | overturns shipped term? |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `contacts` (the umbrella section: persons + organizations) | **جهات الاتصال** | — | Shipped 8× `A`; menu, ACL and screens agree. | The standard Arabic for a contacts module. | domain | same | high | no |
| `person` | **الشخص** — pl. **الأشخاص** | — | Shipped 19× `A`. | Standard, and it matches the entity name. | domain | same | high | no |
| `organization` | **المؤسسة** — pl. **المؤسسات** | `المنظمة` (1 `A`:1990 + 2 `I`:28, 31), `المنظمات` (2 `A`: 19, 1438) | Screens use `المؤسسة`/`المؤسسات` (9 + 4 `A`: 524, 533, 609–629); ACL and the seeded attribute label use `المنظمات`. Egyptian business usage: `المؤسسة` for a company you sell to. | `منظمة` in Arabic reads as an *NGO/international organization*, not a customer company — a live misreading in a CRM. | domain | same | high | **yes** — was `المنظمة` / `المنظمات` in the ACL and seeded attribute |
| `contact person` (the person attached to a deal) | **جهة الاتصال** | `شخص الاتصال` (7 `A`: 1888, 1905, 1932, 1969, 1970, 1979, 1980) | The product already uses `جهة الاتصال` for the same object in 5 places (`A`: 709, 1370, 1823, 1825, 2054). Regional Arabic (Microsoft, Odoo) uses `جهة اتصال` for *contact*. | One object, one name; and `جهة` is the register-independent word that also works for a company contact, which `شخص` does not. | domain | same | medium | **yes** — was `شخص الاتصال` |
| `sales person` (grid column) | **مندوب المبيعات** | — | Shipped 5× `A`; Egyptian usage. | Same word as the approved `owner` row, so the column and the attribute stop disagreeing. | domain | same | high | no |
| `customer` / `account` (master record) | **عميل** — **reserved; no such entity exists yet** (§6 OQ-1, resolved 2026-11-04) | — | AccFlex separates **عميل محتمل** → **الفرصة البيعية** → **عميل**, and creates the `عميل` record when a deal is won. Naffes' funnel ends the same way (`عميل: اشترى فعلًا`). Sijilna has no such record: Krayin models a *deal* (`leads`) pointing at a person/organization. | Recorded as a gap rather than invented, because labelling something that is not a customer record `عميل` would be worse than the gap — but the word itself is settled and **reserved**: no existing string may be renamed to `عميل`, and `crm-ytp` must bring the real record under exactly this name. | domain | same | — | n/a |

### 4.3 Documents, products, inventory

| concept | approved Arabic | rejected renderings present in the product | source | reason | register | EG vs Gulf | confidence | overturns shipped term? |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `quote` (the sales document, one of them) | **عرض سعر** | `الاقتباسات` (3 `A`: 171, 2008 = the quote tab and component; 1593 = *quotation marks*, a different sense) | Egyptian/regional market usage: AccFlex titles the screen **عرض سعر - Sales Quotation** and Wafeq files the same document under *عروض أسعار*; the accountant and ETA-adjacent tooling say **عرض سعر**. | `اقتباس` in Arabic means a *citation/quotation mark* — the product already uses it that way in a validation message about curly quotes (`A`:1593). Using it for a priced document to a customer is a mistranslation. | domain (document) | same | high | **yes** — was `الاقتباسات` in the record-screen tab and the component label |
| `quotes` (the section / menu item / ACL) | **عروض الأسعار** | `العروض` (3 `A`: 2125, 2268, 2365) | Shipped majority: the screen title, the ACL and the `PDF` title are all `عروض الأسعار` (8 `A`). | A menu item and a screen title for the same list must read the same, or the user cannot tell they are in the same place. | chrome | same | high | **yes** — was `العروض` in the menu and dashboard metric |
| `invoice` | **فاتورة** | `0` — no invoice entity ships yet | ETA e-invoicing vocabulary (the word the tax authority uses and the word on the document). | `crm-ytp` puts ETA e-invoicing on the roadmap; the product must not pick a different word now and contradict the tax filing later. | domain (document) | same | high | n/a — reserved |
| `credit note` | **إشعار دائن** | `0` — not shipped | ETA e-invoicing: a correction/return is an independent **إشعار دائن** linked to the original invoice. | Reserved so the roadmap does not invent `مذكرة دائنة` (the Gulf/MS rendering) and break the tax vocabulary. | domain (document) | Gulf-divergent — Gulf tools say `مذكرة دائنة`; Egypt's ETA says `إشعار دائن` | high | n/a — reserved |
| `tax registration number` | **الرقم الضريبي** | `0` — not shipped | Egyptian usage; `الرقم الضريبي` is what appears on the tax card and on ETA filings. | Reserved for `crm-ytp`; do not let a future form say `رقم التسجيل الضريبي` on one screen and `الرقم الضريبي` on the next. | domain (document) | same | high | n/a — reserved |
| `product` | **المنتج** — pl. **المنتجات** | — | Shipped consistently. | Universal. | domain | same | high | no |
| `SKU` | **`SKU`** in Latin; the column header is **رمز المنتج** | `ID` used where `المعرف` belongs (see row 8 in §5) | Convention in every Arabic commerce product; the code must match the printed label. | See §3. | protocol | same | high | no |
| `warehouse` | **المخزن** — pl. **المخازن**; inventory card **المخزون** | `المستودع` / `المستودعات` — 14 `A` occurrences of the family, 5 of them the plural, plus 33 mentions across `docs/` and `brand/` | **Operator decision on Egyptian usage, 2026-11-04** (OQ-3 in §6 is resolved). No external source settles `المستودع` versus `المخزن` — Egyptian vendor pages use both (§9.4) — so this row is a decision on record, not a research finding. | `المخزون` (inventory) is already shipped for the parent card, so `المستودع` puts the Gulf form next to the Egyptian root on the same screen; `المخزن` makes the two one family, and it is the word the client's warehouse keeper says. | domain | **Gulf-divergent, now resolved Egypt-side** — Gulf products say `مستودع`, Egypt says `مخزن` | medium — **decided** | **yes** — was `المستودع` / `المستودعات` |
| `quantity`, `price`, `amount`, `total` | **الكمية**, **السعر**, **المبلغ**, **الإجمالي** | — | Shipped consistently in quotes, products and the `PDF`. | Standard line-item vocabulary. | chrome | same | high | no |
| `sub total` / `grand total` | **المجموع الفرعي** / **المجموع الكلي** | — | Shipped (`A` quotes + `I`:60 `الإجمالي الفرعي`) | `I` has one divergent string (`الإجمالي الفرعي`); it should be `المجموع الفرعي`. | domain (document) | same | high | **yes** — the installer's `sub-total` label |
| `discount` / `tax` / `adjustment` | **الخصم** / **الضريبة** / **التعديل** | — | Shipped consistently. | Standard. | domain (document) | same | high | no |
| `billing address` / `shipping address` | **عنوان الفوترة** / **عنوان الشحن** | — | Shipped (`A` quote `PDF`). | Standard. | domain (document) | same | high | no |

### 4.4 Activities

| concept | approved Arabic | rejected renderings present in the product | source | reason | register | EG vs Gulf | confidence | overturns shipped term? |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `activity` | **النشاط** — pl. **الأنشطة** | — | Shipped and consistent (`A`). | The standard CRM word in Arabic, and what an Egyptian rep logs. | domain | same | high | no |
| activity types: call/meeting/lunch/note/file/email/change-log | **مكالمة**, **اجتماع**, **غداء**, **ملاحظة**, **ملف**, **بريد إلكتروني**, **سجل التغيير** | — | Shipped (`A` activities + contacts). | Already consistent; only `غداء` is odd in a CRM but it is upstream's type list, not a translation error. | domain | same | high | no |
| the deal an activity belongs to (`lead` field) | **الفرصة** | `القيادة` (2 `A`: 1707, 1735) | Same evidence as the `lead` row: `القيادة` is a literal rendering of *lead* as in "leadership". | `القيادة` is not a CRM word in any dialect; it means "leadership". The worst single literal in the product. | domain | same | high | **yes** — was `القيادة` |
| `participants` | **المشاركون** | — | Shipped. | Standard. | chrome | same | high | no |

### 4.5 Settings and administration

| concept | approved Arabic | rejected renderings present in the product | source | reason | register | EG vs Gulf | confidence | overturns shipped term? |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `settings` (the card page: users, leads, inventory, automation, other) | **الإعدادات** | — | Shipped 15× `A`, including the menu fallback `layouts.setting` (`A`:2375). | The word an Egyptian admin looks for. | chrome | same | high | no |
| `configuration` (the settings tree: general, email) | **التهيئة** | The configuration root renders the **same label** `الإعدادات` as the card page — this is the exact defect `docs/client-guide/ar/17-glossary.md` documents as «البندان متطابقان نصاً في القائمة، وهما شاشتان مختلفتان تماماً» | `التهيئة` is already the ACL name (4 `A`:31, plus the global-search tab). | Two different screens with the identical label is a navigation defect, not a terminology preference; one of them must move, and the ACL already picked which. | chrome | same | high | **yes** — the configuration menu label was `الإعدادات`, identical to the other one |
| `role` | **الدور** — pl. **الأدوار** | — | Shipped 11× `A`. | Standard. | domain | same | high | no |
| `group` | **المجموعة** — pl. **المجموعات** | — | Shipped. | Standard. | domain | same | high | no |
| `permission type` / data scope (`view_permission`: global / group / individual) | **نطاق البيانات** — values **عام**, **المجموعة**, **فردي** | `عرض الصلاحية` (1 `A`:1213) | The product already uses `نطاق بياناتك` in two admin error strings (`A`:2498–2499), and `نطاق البيانات` is the phrase Egyptian admins use for row-level scope. | `عرض الصلاحية` parses as "the viewing of the permission" — it names the ACL, not the scope. | domain | same | high | **yes** — was `عرض الصلاحية` |
| `attribute` (custom field) | **الخاصية** — pl. **الخصائص** | `السمات` (4 `A`: ACL `attributes`, web-form `attributes`, `add-attribute-btn`) | Screens/automation use `الخصائص` (2 `A`); ACL and web-form use `السمات`. Egyptian users of Arabic SaaS meet `حقل مخصص` most, and `الخاصية` for the definition. | `سمة` in Arabic means a *trait of a person*; on a form it reads as psychology, not as a field definition. | domain | same | medium | **yes** — was `السمات` in the ACL and web-form labels |
| `tag` | **الوسم** — pl. **الوسوم** | `العلامات` (5 `A`: 1780 mail, 1912, 1938, 2432, 2433) + `العلامة` (6 `A`: 1833, 1834, 1887, 2068, 2069, 2165) | Shipped majority is already `الوسوم`/`الوسم` — **16 `A`** (11 `الوسم` + 5 `الوسوم`) against **11 `A`** for `العلامات` + `العلامة` (5 + 6, counted once each; `العلامات` and `العلامة` are different words, so the pair cannot overlap, and one of the six is `لون العلامة التجارية` at `A`:2165 — the brand mark itself). | `علامة` collides with the brand mark (`العلامة التجارية`) and with a check mark; `وسم` is the standard Arabic for a tag in every Arabic product. | domain | same | high | **yes** — was `العلامات` / `العلامة` in mail, the lead grid and the tag tool |
| `workflow` | **سير العمل** | — | Shipped 13× `A`. | Standard. | domain | same | high | no |
| `event` (automation trigger) | **الحدث** — pl. **الأحداث** | `الفعاليات` (2 `A`: 2422–2423) | `حدث` is already shipped 20× as the trigger word. | `فعالية` means "an event you attend"; using it for a trigger is the same class of literal as `القيادة`. | domain | same | high | **yes** — was `الفعاليات` |
| `campaign` | **الحملة** — pl. **الحملات** | — | Shipped 6× `A`. | Standard marketing Arabic. | domain | same | high | no |
| `email template` | **قالب البريد الإلكتروني** | — | Shipped. | Standard. | domain | same | high | no |
| `webhook` | **ويب هوك** — pl. **الويب هوك** | `الويب هوكس` as a plural (mixed Arabic article + English plural) | Shipped 17× `A`. | Egyptian developers say "ويب هوك"; the Arabic-transliterated form is what the UI already uses and what a support reply will use. Fix only the plural construction if it is touched. | domain | same | medium | no |
| `data transfer` / `import` | **استيراد البيانات** for the section, **استيراد** for the action, **الاستيرادات** for the history list | `نقل البيانات` (3 `A`:44 and two configuration labels) | The screen's own strings are all `استيراد` (10+ `A`: 1561, 1611, 1632, 1636, 1642, 1666, 1667). | `نقل البيانات` ("moving data") is vaguer and disagrees with every string inside the screen it opens. | chrome | same | high | **yes** — was `نقل البيانات` for the section label |
| `web form` | **نموذج الويب** | — | Shipped (`A`, `W`). It is also the seeded lead source `نموذج ويب`. | Consistent. Note the two spellings `نموذج الويب` (settings) and `نموذج ويب` (source value) — both correct, different grammar (definite label vs list value). Do not unify. | domain | same | high | no |
| `source` | **المصدر** — pl. **المصادر** | — | Shipped 13× `A`. | Standard. | domain | same | high | no |
| `type` | **النوع** — pl. **الأنواع** | — | Shipped 20× `A`. | Standard. | domain | same | high | no |
| `dashboard` | **لوحة المعلومات** | `لوحة القيادة` (3 `A`: 2115 widget, 2217 page title, 2362 menu; plus 36 mentions across `docs/` and `brand/`) — and note that `لوحة التحكم` (8 `A`) is **not** a rendering of this concept | **Operator decision on Egyptian usage, 2026-11-04** (OQ-2 in §6 is resolved). `لوحة المعلومات` is the Microsoft / Power BI / Excel rendering — the form an Egyptian manager who has opened any analytics tool has already read. No external source lists Arabic dashboard terms (§9.4), so the row rests on the operator's read of Egyptian usage and on nothing else. | `لوحة القيادة` is a cockpit calque: it puts a steering wheel on a report page. `لوحة التحكم` stays reserved for the *admin panel as a whole* ("واجهة لوحة التحكم"), a different referent, so it is neither this page nor a duplicate label for it. | chrome | **Egypt-divergent** — Gulf products keep `لوحة القيادة`; Egyptian readers meet `لوحة المعلومات` in Excel / Power BI | medium — **decided** | **yes** — was `لوحة القيادة` |
| `quick add` | **إضافة سريعة** | — | Shipped. | Standard. | chrome | same | high | no |
| `notifications` | **الإشعارات** | — | Shipped. | Standard. | chrome | same | high | no |
| `global search` | **البحث الشامل** | — | Shipped. | Standard. | chrome | same | high | no |
| `my account` | **حسابي** | — | Shipped. | Standard. | chrome | same | high | no |
| `dark mode` | **الوضع الليلي** | — | Shipped. | Standard. | chrome | same | high | no |

### 4.6 Mail

| concept | approved Arabic | rejected renderings present in the product | source | reason | register | EG vs Gulf | confidence | overturns shipped term? |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `inbox` | **البريد الوارد** | `الوارد` (1 of the 5 `A` hits, the bare folder label at `A`:1758; the other 4 are inside `البريد الوارد`) | `البريد الوارد` is the menu item and the datagrid action (`A`); regional Arabic mail clients use `البريد الوارد`. | The bare `الوارد` reads as an adjective with no noun. | chrome | same | high | **yes** — was `الوارد` as the folder label |
| `outbox` | **الصادر** | — | Shipped. | Correct Arabic for outgoing-in-progress. | chrome | same | high | no |
| `sent` (folder) | **المرسل** | `تم الإرسال` (1 `A`:1761) | The menu already says `المرسل` (`A` layouts), the inner string says `تم الإرسال`. | A folder is a noun; `تم الإرسال` is a past-tense sentence used as a folder name. | chrome | same | high | **yes** — was `تم الإرسال` |
| `drafts` | **المسودات** | `مسودة` (2 `A`, singular, used as the mail folder label at `A`:1757 and again in the compose dialog) | Shipped majority `المسودات` (3 `A`). | Folder names are plural. | chrome | same | high | no |
| `trash` | **سلة المهملات** | `المهملات` (1 of 4 `A` hits, the mail folder label at `A`:1761 — the other 3 are inside `سلة المهملات`), `تم النقل إلى سلة المهملات` (1 `A`:1785, an action label written as a past-tense sentence) | Shipped majority `سلة المهملات` (`A`:2129 settings, `A`:2374 menu). | Both the folder and the action currently exist; the folder should read `سلة المهملات` and the action should read `نقل إلى سلة المهملات`. | chrome | same | high | **yes** — the action label `تم النقل إلى سلة المهملات` |
| `routed mail` (`المرسل` vs `الصادر` distinction) | **الصادر** = queued, **المرسل** = actually left the system | — | Both shipped; the client guide already documents the difference. | Keep the distinction; it is real and the guide explains it. | chrome | same | high | no |

### 4.7 Fixture-level defects the client guide already names

These are not terminology choices; they are Arabic errors in shipped strings. `crm-mox.3` fixes them
by name.

| what ships | where | problem | approved replacement |
| --- | --- | --- | --- |
| `لا توجد أشخاص متاحة للفترة المختارة` | dashboard `top-persons.empty-info` (`A`) | Gender disagreement: `أشخاص` is masculine plural, `متاحة` is feminine singular. | `لا توجد أشخاص متاحون للفترة المختارة` |
| `Leads can not be deleted.` | `leads.update-failed` (`A`:1861) | A full English sentence left inside the Arabic file — and it is also the wrong message for `update-failed`. | `تعذر تحديث العميل المحتمل` → after the `lead` overturn: `تعذر تحديث الفرصة.` |
| `العميل الفاسد منذ :days أيام` | kanban card | Literal rendering of *rotten*; covered by the rotten-lead row above. | `مهمل منذ :days يومًا` (with the correct number agreement arm) |
| `تم النقل إلى سلة المهملات` | mail datagrid action | Past-tense sentence where a verb phrase belongs on a control. | `نقل إلى سلة المهملات` |
| `تم الإرسال` | mail folder label | Past-tense sentence where a noun belongs. | `المرسل` |
| `أضف طلب المنتج لهذا العرض.` | quotes (`A`:453, 482) | "طلب المنتج" is not a thing in this product; a quote line is a product line. | `أضف منتجًا إلى هذا العرض.` |

---

## 5. Seeded data — what a migration will write

These values live in the **database** today as English, because the installer ran with `en`:

```text
lead_pipeline_stages:  Follow Up, Lost, Negotiation, New, Prospect, Won
lead_sources:          Email, Web, Web Form, Phone, Direct
lead_types:            New Business, Existing Business
lead_pipelines:        Default Pipeline
```

Verified against the live database on 2026-11-04, so this is the actual state, not the seed file's
intent. The Arabic is therefore written by a migration (`crm-mox.3`), not by editing a seeder.

### 5.1 Pipeline stages

| English (DB) | approved Arabic | rejected renderings in the product | reason |
| --- | --- | --- | --- |
| `New` | **جديد** | — | Shipped seed; short; correct. |
| `Follow Up` | **متابعة** | — | Shipped seed; `متابعة` is exactly what an Egyptian rep says he will do next. |
| `Prospect` | **عميل محتمل** | — | Approved as the *stage*, which is only unambiguous because the record itself moved to `الفرصة` (§4.1). |
| `Negotiation` | **مفاوضات** | — | Shipped seed; `مفاوضات` is the standard business noun. `تفاوض` is the verbal noun and reads less like a stage. |
| `Won` | **مكتسب** | `مُنتصر` (seed `I`:98), `فاز` (editor), `ربحت` (kanban) | **One word chosen out of five shipped forms.** See §5.2. |
| `Lost` | **مفقود** | `خسر` (editor), `خسرت` (kanban) | Same. |
| `Default Pipeline` (pipeline name) | **مسار المبيعات الافتراضي** | `قناة المبيعات الافتراضية` (seed `I`:88) | Follows the `pipeline` row: `مسار`, not `قناة` (channel). |

### 5.2 The won/lost conflict, resolved

Five forms ship today for two states:

| surface | current text |
| --- | --- |
| seeded stage name (`I`:94, `I`:98) | `مفقود` / `مُنتصر` |
| pipeline editor stage flags (`A`:1249–1250, 1281–1282) | `فاز` / `خسر` |
| kanban card + record view (`A` `stages`) | `ربحت` / `خسرت` |
| dashboard revenue card | `الإيرادات المكتسبة` / `الإيرادات المفقودة` |
| dashboard/`PDF` report and lead counters | `المكتسب` / `المفقود` |

**Decision: `مكتسب` / `مفقود` everywhere.**

> **External alternative on record:** AccFlex, the Egyptian ERP whose Arabic docs settled most of §4, uses **فوز** / **خسارة** for the win/loss transition (`تسجيل فرصة بيعية كفوز`, `تسجيل … كخسارة`, `تاريخ الفوز`, `سبب الخسارة`). It was rejected as the *stage label* for one reason: `فوز` is a verbal noun naming an event, not a state, so it does not sit in a column header beside `جديد` / `متابعة` / `عميل محتمل` / `مفاوضات`. `سبب الخسارة` is kept as-is, which is AccFlex's own phrase — so the two products agree on the loss vocabulary and differ only on the stage adjective.

Why this one and not the others:

1. **It is already on the highest-authority surface.** The dashboard revenue card and the exported
   `PDF` report are what a manager and a client read; they say `المكتسبة`/`المفقودة`. The stage names
   are the thing that should bend to the report, not the reverse.
2. **A stage label is an adjective describing the deal; a verb has to agree with its subject.**
   `ربحت` and `خسرت` are feminine past-tense verbs — they only parse if you silently supply a feminine
   noun, and they become wrong the moment the entity is called anything masculine. `فاز`/`خسر` are the
   masculine forms of the same verb. So the product today ships **both genders of the same verb** for
   the same two states, on two screens. That is the defect in one sentence.
3. **`مكتسب`/`مفقود` is the participle pair**, so it agrees with nothing and reads identically under
   any reformulation of the entity name. That is what makes it survive the `lead` → `الفرصة` change.
4. **The two stage flags in the pipeline editor** need one more word to be readable as setting names:
   `مرحلة مكتسبة` / `مرحلة مفقودة`.

### 5.3 Lead sources

| English (DB) | approved Arabic | reason |
| --- | --- | --- |
| `Email` | **البريد الإلكتروني** | Shipped seed; the word an Egyptian user writes on a form. |
| `Web` | **ويب** | Shipped seed; kept. In a source dropdown the context is unambiguous and `الموقع الإلكتروني` is 8 characters longer for no gain. |
| `Web Form` | **نموذج ويب** | Shipped seed; kept, and it matches the settings screen's `نماذج الويب`. |
| `Phone` | **الهاتف** | Shipped seed; kept. |
| `Direct` | **مباشر** | Shipped seed; kept. `مباشر` is what "walk-in / direct contact" is called. |

No source value is overturned. Three of the five are single words that a user reads in a dropdown
next to a stage name; the bar for churning them is higher than for a screen title.

### 5.4 Lead types

| English (DB) | approved Arabic | rejected | reason |
| --- | --- | --- | --- |
| `New Business` | **عمل جديد** | — | Shipped seed; correct and short. |
| `Existing Business` | **عمل قائم** | `عمل موجود` (seed `I`:113) | `موجود` means "present / available", not "existing". `عمل قائم` is the pair to `عمل جديد` and is what an Egyptian account manager says about a repeat customer. |

---

## 6. Questions: five resolved, three still open

Rows the research did not settle. Each one names the options and the evidence that would decide it.
OQ-1 to OQ-5 went to the operator on **2026-11-04** and are recorded below as resolved — what was
asked, what was decided, why, and the constraint each answer leaves behind. Read the resolved entry
before reopening one: a decision on record beats a second opinion. OQ-6 to OQ-8 are still open; do not
guess them in code, ask the operator.

### 6.1 Resolved — operator decisions, 2026-11-04

**OQ-1 — Is there a `customer` / `عميل` master record, and if so what is it called?**
The product models a *deal* (`leads`) pointing at a person and an organization. There is no customer
record, no customer number, no customer account. An Egyptian accountant's mental model is
**عميل** first and the deal second; he will look for `العملاء` in the menu and find `جهات الاتصال`.
Options: (a) leave the gap and keep `جهات الاتصال`; (b) rename organizations to `العملاء` on the
customer-facing screens only — rejected for now, because an organization in this product can be a
supplier; (c) add a real customer entity in a later epic.
*What would settle it:* a decision on whether Sijilna's roadmap converges on an invoicing/customer
module (ETA integration in `crm-ytp` implies it does). If it does, `عميل` must be reserved now so the
module does not arrive under a second name.

**Decided 2026-11-04 — keep the gap and reserve `عميل`.** Option (a): `جهات الاتصال` stays the section
and no `العملاء` entry is invented for it. Option (b) stays rejected for the reason it was rejected —
an organization in this product can be a supplier, so renaming it `العملاء` would name the wrong
record — and (c) is now a scheduled epic rather than a possibility, because `crm-ytp` converges on
invoicing.
**Constraint this leaves for later lanes:** `عميل` is a **reserved word**. No existing string may be
renamed to `عميل`, and no person, organization or deal may be labelled with it. When the customer
record arrives it arrives under exactly this name — a second name for it later would be the same
defect this glossary exists to prevent.

**OQ-2 — `لوحة القيادة` versus `لوحة المعلومات`.**
`لوحة القيادة` is shipped and the 17-chapter client guide is built on it. `لوحة المعلومات` is the
Microsoft / Power BI / Excel rendering and is what an Egyptian manager who has used any analytics tool
has seen. `لوحة التحكم` is already taken in this product for the admin panel as a whole.
Options: keep `لوحة القيادة` (zero churn, cockpit calque); move to `لوحة المعلومات` (modern MSA,
matches the analytics tools the client already uses). *What would settle it:* the operator's read of
whether `لوحة القيادة` sounds like a cockpit to an Egyptian sales manager. Recommended: keep, revisit
only if a client complains.

**Decided 2026-11-04 — move to `لوحة المعلومات`,** overruling that "keep" recommendation. The
operator's read of Egyptian usage settled it: `لوحة القيادة` is a cockpit calque, and `لوحة المعلومات`
is the form an Egyptian manager has already read in Excel and Power BI. No external source lists
Arabic dashboard terms (§9.4), so this is a **decision on record, not a research finding — do not cite
one for it.** Cost accepted: 3 occurrences in `A` plus 36 mentions across `docs/` and `brand/`,
including the 17-chapter client guide, which is rewritten in the apply lane. `لوحة التحكم` is
unaffected and still means the admin panel as a whole.

**OQ-3 — `المستودع` versus `المخزن` for warehouse.**
19 shipped strings say `المستودعات`. An Egyptian finance team says `المخزن` at least as often, and
`المستودع` leans Gulf/formal — while `المخزون` (inventory) is already shipped for the parent card, so
the product already puts the Egyptian root next to the Gulf one. Options: keep `المستودعات` (no
churn, Gulf-leaning); move to `المخازن` (Egypt-tight, 19-string churn, and `المخزون`/`المخزن` then
read as the same family). *What would settle it:* what the client's warehouse keeper says. Recommended:
keep for now, and treat it as the first candidate in a later Egypt-tightening pass.

**Decided 2026-11-04 — move to `المخزن` / pl. `المخازن`,** overruling that "keep for now"
recommendation. The client's warehouse keeper says `مخزن`, and `المخزون` (inventory) already ships, so
the change turns two roots on one screen into one family. No external source settles the pair (§9.4),
so this too is a **decision on record, not a research finding.** Cost re-measured on 2026-11-04 before
approving it: 14 occurrences of the `المستودع` family in `A`, 5 of them the plural `المستودعات`, plus
33 mentions across `docs/` and `brand/`. The "19 strings" figure this question was opened with is
superseded by that measurement.

**OQ-4 — `مسار المبيعات` versus AccFlex's `المسار البيعي`.**
The word `مسار` is settled: AccFlex and Naffes both use it. What is not settled is the *genitive*:
`مسار المبيعات` ("sales path") versus `المسار البيعي` ("the selling path"), which is AccFlex's exact
form. The shipped `قناة المبيعات` already uses the `X المبيعات` shape and `مسار المبيعات` reads more
naturally in the plural menu (`مسارات المبيعات`), while `المسار البيعي` is the form a client who has
used AccFlex will recognise.
*What would settle it:* whether the first client has used AccFlex. Recommended: `مسار المبيعات`; the
churn cost is identical either way, so this is a one-line change if the operator prefers the market
exact form.

**Decided 2026-11-04 — keep `مسار المبيعات`,** as recommended. The churn cost is identical either way
and the plural menu (`مسارات المبيعات`) reads more naturally in this genitive. AccFlex's `المسار
البيعي` stays on record as the market-exact alternative, so if the first client turns out to be an
AccFlex user this remains a one-line change.

**OQ-5 — `مندوب المبيعات` assumes the deal owner is a rep.**
In a 3-person Egyptian SMB the owner is often the owner of the company, or a manager who does not
make calls. `مالك الفرصة` is the precise CRM term (opportunity owner) and is what the seeded attribute
says 8 times. `مندوب المبيعات` is what people say, but it may mislabel the field for the boss.
Options: `مندوب المبيعات` (spoken, may be wrong for managers); `المسؤول` (**the word AccFlex's own
Arabic UI uses** for this field — neutral, works for a boss, vaguer about the role); `مالك الفرصة`
(precise, reads legal to an Egyptian). *What would settle it:* who actually opens deals in the first
client's instance. Recommended: `مندوب المبيعات`, because it is already the grid column label users
see most — with `المسؤول` as the strongest market-backed runner-up.

**Decided 2026-11-04 — `مندوب المبيعات`,** as recommended. Both runners-up are recorded as rejected
rather than dropped, with the reason each lost: `المسؤول` is AccFlex's own field label and reads
neutrally for a boss who never calls, but it is vaguer about the role than the label the grid already
shows — and in this tree `المسؤول` already means *administrator* (4 `A`, e.g. `حساب المسؤول الرئيسي`),
which makes it actively ambiguous as a field name; `مالك الفرصة` is the precise CRM term, but `مالك`
reads as legal ownership to an Egyptian reader and it ships `0` times, so it would be a new word
rather than a decision.

### 6.2 Still open — OQ-6 to OQ-8

Neither the research nor the decisions above reach these three: one needs a rendering check, one a
policy call, one a cleanup.

**OQ-6 — `عرض سعر` inside a record is a document; the record-screen tab is a *list* of quotes.**
The approved pair is `عرض سعر` (one) / `عروض الأسعار` (the section). The tab on the deal record lists
the quotes attached to that deal, so it should read `عروض الأسعار` too — but a tab label that long may
not fit the tab strip. *What would settle it:* the rendered tab width in `ar`. Recommended: try
`عروض الأسعار`; if it wraps, use `العروض` **only** there and record the exception in this file.

**OQ-7 — Arabic-Indic digits for the printed `PDF`.**
The rule is Western digits everywhere, and ETA's own documents use Western digits, so this is only
open if the operator wants the exported quote `PDF` to look "official" in a different way. No evidence
supports changing it. Listed so that no one "improves" the `PDF` later without a decision on record.

**OQ-8 — the other nine locales carry Arabic-looking or untranslated leftovers.**
`packages/Webkul/Admin/src/Resources/lang/fa/app.php` contains `مفقود`, and `leads.update-failed`
ships as a full English sentence in the **Arabic** file. Locale hygiene is outside this glossary;
recorded so that a `fa` string is never mistaken for an `ar` one during the rename pass.

---

## 7. How to add a term

1. **Find the English key** in the package's `en` file. The concept list is built from the product,
   never from a dictionary.
2. **Search this file first.** If the concept is here, use the approved Arabic exactly — no synonyms,
   no shorter variant, no "better" word for one screen.
3. **If it is not here**, decide in this order and stop at the first hit:
   a. the same concept already shipped in Arabic somewhere in the tree → use that, and add the row;
   b. an Egyptian-market product or an ETA document uses a word for it → use that, cite it;
   c. neither → write the row with `confidence: low` and put it in §6 with the options. Do not ship a
      guessed domain noun.
4. **Register check:** is this chrome or a domain noun? Chrome gets short MSA, a domain noun gets the
   word an Egyptian user says.
5. **Numeral check:** digits Western, units in Arabic, placeholders untouched.
6. **Grammar check:** number agreement (`يومًا` / `أيام`), gender agreement, and no past-tense sentence
   used as a label or a button.
7. **Add the row** with the measured `grep` count of every rejected rendering, so the next agent can
   verify it rather than trust it.
8. **Update `docs/client-guide/ar/17-glossary.md`** in the same change — the guide's promise is that
   every screen word maps to a row here. A glossary row the guide does not know about is a support
   ticket waiting to happen.
9. **Never leave two Arabic names for one concept** unless this file says why.

---

## 8. Verification commands

```bash
# 1. Every rejected rendering in §4 is measurable:
grep -o -F 'متعفن'  packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l   # 1
grep -o -F 'القيادة' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l  # 5, of which only 2 are the lead field
grep -o -F 'الاقتباسات' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l  # 3 = 2 (quotes tab) + 1 (quotation marks)
grep -o -F 'مالك المبيعات' packages/Webkul/Installer/src/Resources/lang/ar/app.php | wc -l  # 8

# 2. Numerals: no Arabic-Indic digits anywhere in the Arabic files.
#    Use an explicit PCRE class — `[٠-٩]` is a multibyte byte range and matches Latin digits too.
grep -rnP '[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]' \
  packages/Webkul/*/src/Resources/lang/ar/app.php lang/ar/ ; # exit=1 means none found (pass)

# 3. Seeded data currently holds English (so a migration, not a seeder edit, is required):
docker exec crm-platform-mysql mysql -uroot -p"$DB_ROOT_PASSWORD" -N -B -e \
  "select name from krayin.lead_pipeline_stages; select name from krayin.lead_sources; select name from krayin.lead_types;"

# 4. Encoding sanity — the document itself must be valid UTF-8 with no mojibake:
file -i .agents/context/guides/arabic-terminology.md          # text/plain; charset=utf-8
iconv -f UTF-8 -t UTF-8 .agents/context/guides/arabic-terminology.md >/dev/null && echo 'valid UTF-8'
# and no stray bidi control characters (LRM/RLM/embedding):
grep -cP '[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]' \
  .agents/context/guides/arabic-terminology.md               # must be 0
```

## 9. Sources consulted, and what each one actually settled

Recorded so the next agent can re-check a row instead of re-running the research. The `source` column
in §4 cites the *decisive* one; this section is the full set.

### 9.1 Egyptian / regional products with real Arabic UI (kind a)

| Source | What it settled | Notes |
| ------ | --------------- | ----- |
| **AccFlex** CRM docs — `عميل محتمل`, `فرصة بيعية`, `عرض سعر` — `docs.accflex.com` | **The decisive source for the whole entity layer.** Confirms `عميل محتمل` → `الفرصة البيعية` → `عميل` as three distinct records; names the pipeline **المسار البيعي** with the deal sitting in **مرحلة** of it; titles the quote screen **عرض سعر**; labels the deal owner **المسؤول**; uses **النشاط/الأنشطة** and **سبب الخسارة** exactly as shipped. | An Egyptian ERP sold into Egypt and the Gulf, so it is both an Egyptian-market witness and an Arabic-UI witness. Its opportunity fields (المرحلة، القيمة، تاريخ الإغلاق المتوقع، المسؤول) map one-for-one onto Sijilna's `leads` table. |
| **Naffes CRM** glossary — `naffes.net/glossary/lead`, `/guides/sales-pipeline-management` | Confirms the same split in definition form: lead = an unchecked interest signal; **فرصة بيعية** = «صفقة مفتوحة في المسار لها قيمة وموعد إغلاق متوقع»; the funnel ends at **عميل** = «اشترى فعلًا». Also uses `المسار` and `المندوب`. | Its worked example is Riyadh-based, so treat it as Gulf-leaning wherever Egypt and the Gulf divide. |
| **Daftra** (`docs.daftra.com`, Arabic user manual) | Settled the **customer** vocabulary and confirmed `عميل` as the master record (`قائمة العملاء`, `إضافة عميل جديد`). Did **not** settle lead/opportunity: the manual does not define the `عميل محتمل` / `فرصة` boundary. | This is why the lead row leans on AccFlex and Naffes rather than Daftra. |
| **Wafeq** help centre (Arabic) | Settled the **quote** document name: `عرض سعر`, filed under *عروض أسعار وفواتير مبدئية*, convertible to an invoice. | Two independent market products saying `عرض سعر` is why that row is `high` confidence. |
| **Odoo Arabic** / **Odoo Ninja** glossary | Confirms the `عرض سعر` → `أمر بيع` document chain, i.e. a quote is a distinct document from an invoice in regional Arabic. | Cross-check on the document chain only, not on CRM entities. |
| **Bitrix24 Arabic** | Confirmed that a pipeline is presented as kanban columns of stages and that multiple pipelines are a first-class concept. | Its own stage labels are not standardised, so it settled shape, not words. |

### 9.2 Egyptian regulatory vocabulary (kind b)

| Source | What it settled | Notes |
| ------ | --------------- | ----- |
| **ETA** e-invoicing material — `eta.gov.eg`, `invoicing.eta.gov.eg`, e-Invoicing Solution FAQs | Settled **فاتورة** for invoice and **إشعار دائن** for credit note, and that an approved e-invoice is never edited or deleted — corrections are separate credit notes linked to it. | Reserved for `crm-ytp`. The Gulf/MS rendering `مذكرة دائنة` was rejected on this evidence. |
| Egyptian tax-adjacent accounting material (Qoyod, mini-tax, commentary on Unified Tax Procedures Law No. 206/2020) | Corroborates the credit-note rule and the `إشعار دائن` wording. | Vendor blogs plus one law-office article, not a primary ETA page — recorded as corroboration, not as the decisive source. |

### 9.3 Egyptian professional usage and the product itself (kind c)

| Source | What it settled | Notes |
| ------ | --------------- | ----- |
| AccFlex + Naffes Arabic product docs (above) | The spoken-vs-written mapping for `عميل محتمل` / `فرصة` / `صفقة` / `المندوب` / `المسؤول`, and the stage vocabulary of a real Arabic pipeline board. | Written for users, not translators — exactly the register this glossary targets. |
| The product's own shipped strings | The decisive source for `مكتسب`/`مفقود` (dashboard card + `PDF` report), for `مهمل` (`العميل المحتمل مهمل لمدة :days يومًا`), for `جهة الاتصال` (5 × `A`), for `الوسوم` (16 × `A`: 11 `الوسم` + 5 `الوسوم`) and for `نطاق البيانات` (`A`:2498–2499). | Where the product already says the right thing on its most-read surface, that *is* the evidence — it needs making consistent, not re-deciding. |
| `docs/client-guide/ar/17-glossary.md` | The defect list this document must resolve by name: every "two names for one concept" row there is a row here, and every bad literal it calls out is either overturned or explicitly kept above. | Written before this research; it documents the ambiguity, it does not decide it. |
| `brand/copy-deck.md` §0 and §3 | The 12 operator decisions of 2026-10-17, and the Arabic term set the rebrand committed to (`عميل محتمل`, `جهات الاتصال`, `العرض`, `الأنشطة`, `المستودعات`, `الأدوار`, `لوحة القيادة`, `الإعدادات`). | §3 says the Arabic "reuses the terminology already in" the Admin `ar` file — so it is a *record of what shipped*, not an independent decision. That is why this glossary may overturn rows in it: rows 1, 2, 25 and 26 (`الفرصة`, `مسار المبيعات`, `لوحة المعلومات`, `المخزن`) contradict §3, were approved at the 2026-11-04 review, and §3 is rewritten to match this file. |

### 9.4 What did **not** settle anything, and why

- **Foodics** — no Arabic CRM glossary surfaced; its public material is product marketing, not terminology.
- **Zoho CRM Arabic** — searches returned rate-limit errors and no usable page. Not used.
- **Microsoft Arabic style guide** — Microsoft publishes style guides per language but no Arabic *dashboard* term list surfaced; `لوحة المعلومات` is inferred from the Power BI / Excel Arabic UI. **The operator decided the row on 2026-11-04 (`لوحة المعلومات`), so the missing source no longer holds it open — it is a decision on record, not a citation.**
- **`المستودع` vs `المخزن`** — Egyptian accounting vendor pages use the two interchangeably, so no source settles it. **The operator decided the row on 2026-11-04 (`المخزن` / `المخازن`); the absence of a source is why it is labelled a decision rather than a finding.**

---

## Related

- Application lane → beads `crm-mox.3` (applies these rows to lang files, seeded data and the guide)
- Client-facing ambiguity as shipped → [docs/client-guide/ar/17-glossary.md](../../../docs/client-guide/ar/17-glossary.md)
- Where Arabic may be edited at all → [rebrand-boundary.md](rebrand-boundary.md)
- Context map → [navigation.md](navigation.md)
