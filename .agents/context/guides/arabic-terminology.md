<!-- Context: guides/arabic-terminology.md | Priority: critical | Version: 1.5 | Updated: 2026-11-04 -->

# Arabic terminology — the one glossary

**Read this before changing any Arabic string, seeder value, view label or client-guide sentence.**
Sijilna is Arabic-first and Egypt-first. Every Arabic word a user sees is either on this page or is a
bug. Nothing gets renamed until the operator approves a row — approval is per row, this table is the
review surface.

Beads origin: `crm-mox.1`. Application of these decisions is `crm-mox.3`, not this file's job.

---

## Operator decisions — 2026-11-04

The review table below was taken to the operator row by row on **2026-11-04** and is **approved as
written: 26 of 26 rows, none rejected.** The decisions that came out of that review are final for the
apply lane (`crm-mox.3`): five from the row-by-row pass (items 1–5 below) and five more from a second
pass run against the shipped tree, which closed the five holes the reconciliation had found (items
6–10, in the subsection at the end of this block). They are recorded here, in this file's own voice, so
no later agent reopens them; §6 keeps each one's evidence trail.

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
   **36 mentions** across `docs/` and `brand/`. `لوحة التحكم` keeps its different referent — the admin
   panel as a whole — in the **2 keys** where it means that, and moves to `لوحة المعلومات` in the other
   **5**, where it renders this page (measured and split in decision 6 below). *Origin:* an **operator
   decision on Egyptian usage**, not a research finding — no external source settled this row (§9.4),
   and none is claimed for it.
5. **`warehouse` → `المخزن` / pl. `المخازن`**, overruling the "keep as shipped" recommendation. It
   replaces `المستودع` — **14 occurrences in `A`**, 5 of them the plural `المستودعات`, plus **33
   mentions** across `docs/` and `brand/`. The inventory card keeps `المخزون`, so `المخزن` / `المخزون`
   now read as one family on one screen. *Origin:* an **operator decision on Egyptian usage**, not a
   research finding (§9.4).

OQ-4 (`مسار المبيعات` versus AccFlex's `المسار البيعي`) was settled at the same review: **`مسار
المبيعات` stays**, as recommended. §6 now holds six resolved entries (OQ-1 to OQ-5, plus OQ-8, which
§4.7.1 answered) and the two questions these decisions genuinely do not reach (OQ-6 and OQ-7).

### Second review, 2026-11-04 — the five holes, closed

Taking the table back to the operator a second time, against the **shipped tree** rather than against the
research, closed the five questions the first pass left open. Each is a decision on record exactly like
items 1–5, and the apply lane executes it as written; they are numbered 6–10 because they continue that list.

#### Decision 6 — `dashboard` — split the concept; do not blanket-replace.
The approved `لوحة المعلومات` covers the **5 keys that render the dashboard page**: `dashboard.index.pdf.title` (`A`:2234 `تقرير لوحة التحكم`),
`configuration.index.general.settings.dashboard.title` (`A`:2132), `…settings.dashboard.info`
(`A`:2133), `…settings.dashboard.date-range-info` (`A`:2135 — **two** occurrences inside that one
value), and `errors.dashboard` (`A`:2492). The **2 keys that mean the admin panel keep `لوحة التحكم`
untouched**: `configuration.index.general.general.locale-settings.title-info` (`A`:2091) and
`configuration.index.general.settings.footer.show-info` (`A`:2106). Measured:
`grep -o -F 'لوحة التحكم' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l` = **8**, on
**7 keys** — 5 dashboard + 2 admin panel. *Apply lane:* key by key, never by string; `لوحة التحكم` is a
homograph (see §4.5 and the inventory's §9).

#### Decision 7 — `web_form` — protected as shipped: zero edits, and this is a recorded do-not-unify.
78 keys. Its three renderings are all correct and must **not** be unified: the definite label `نموذج الويب` (settings
— 4 naming slots, 10 occurrences in `A`:963–1031) and the plural list screen `نماذج الويب` (3 keys:
`settings.webforms.index.title`, `webform:acl.title`, `webform:menu.title`); the third, `نموذج ويب`, is
the **seeded lead source value** (`installer:seeders.lead.source.web-form`), which is a list value, not
a label. *Apply lane:* no key under `settings.webforms.*`, `acl.*` or `menu.*` for this concept changes.
*Conflict of authority, resolved here:* the inventory classes `web_form` `conflicting` (two lexemes,
2 names / 78 keys); this glossary protects it as shipped. **The glossary wins** — see §4.5's row.

#### Decision 8 — `http_terminology` — settled, four decisions in one.
(a) The HTTP method labels stay **Latin `POST` and `PUT`**: `settings.webhooks.create.post` (`A`:974) and `settings.webhooks.create.put` (`A`:975),
with their `edit` twins (`A`:1001, `A`:1002) — those four keys change from `إرسال` and `تحديث` to
`POST` and `PUT`. This is the **first Latin token used as a control label in this UI**, and the
exception is recorded in §3. (b) The duplicate endpoint field resolves to **`عنوان URL`** under §3's
existing rule: `webhooks.index.datagrid.end-point` (`A`:968, `نقطة النهاية`),
`webhooks.create.url-endpoint` (`A`:976) and its `edit` twin (`A`:1003) (both `نقطة نهاية الرابط`).
(c) The remaining HTTP labels keep their shipped values and now have a row of their own in §4.5 so they
are no longer unsettled: `حمولة` payload, `الرؤوس` / `رأس` headers, `الجسم` body, `الطريقة` method.
(d) Why (a) cannot be done in Arabic: `تحديث` already means *update* in **60 `A` keys** (61 occurrences
of the `تحديث` family, counting `التحديث`) and `إرسال` already means *send* in **22 `A` keys**, so
neither can be reused as a method label without breaking the screens that use it as a verb.

#### Decision 9 — `password_reset` — settled.
`users.forget-password.create.page-title` (`A`:67) moves from `نسيت كلمة المرور` to **`استعادة كلمة
المرور`**, matching the screen heading it sits above (`users.forget-password.create.title`, `A`:71),
under the rule that a tab title and its heading must read the same. `users.reset-password.title` and
`.submit-btn` (`A`:80) stay **`إعادة تعيين كلمة المرور`** — they are genuinely the second step, where a
new password is typed. `users.login.forget-password-link` (`A`:58 `نسيت كلمة المرور؟`) stays as
shipped: it is the question a user clicks, not a screen name. The flow had no glossary row and no
client-guide chapter; §4.8 adds the row.

#### Decision 10 — `mail_draft` — the self-contradiction resolved by splitting the keys.
`mail.index.draft` (`A`:1757, the **folder label** in the mail sidebar) **has moved to `المسودات`**
(applied 2026-11-04, v1.4), on the row's own reason that folder names are plural (`المسودات` shipped 3×
`A` before it and ships **4×** now: `acl.draft`, `…menu.draft`, `layouts.mail.draft`, and this key).
`mail.index.mail.draft` (`A`:1773, the **compose dialog** that saves one draft) **stays `مسودة`**: that
screen is one draft, not a folder. *Applied, measured:*
`grep -n "'draft' =>" packages/Webkul/Admin/src/Resources/lang/ar/app.php` → `A`:11, `A`:1757, `A`:2116
and `A`:2371 all read `المسودات`, and `A`:1773 is the only `مسودة` left (`grep -o -F 'مسودة'` = 1). Exactly
one of those two keys changed, which is why the row can no longer say `no` while naming the shipped
label. The four fixture
defects approved in the same pass — the English sentence at `leads.update-failed`, the two bare `ID`
headers, `Json`, and the customer-labelled password-reset subject — are recorded in §4.7.1, which is
the table the apply lane reads for string-level fixes.

---

## Read this first — the 26 rows that change what ships

> **Evidence note (v1.4, 2026-11-04) — the shipped counts in these tables are pre-apply history.** The
> "ships today", "shipped count" and "current text" evidence in the 26 rows below, in every §4 table and
> in §5.2 describes the tree **before** the apply lane (`crm-mox.3`) landed on **2026-11-04**. What the
> guardrail enforces **today** is each row's `rejected renderings` column, not the sentence around it;
> where a shipped count and the tree disagree, **the tree is truth**. A rejected rendering that now
> measures `0` means the row was applied — it does not mean the cell was wrong — and a `ships today`
> cell that still names a rejected rendering is history, not a claim about the checkout in front of you.
> Do not relabel the count cells one by one; re-measure the one you are about to quote:
>
> ```bash
> grep -o -F 'المصطلح' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l   # occurrences of one form
> grep -n  -F 'المصطلح' packages/Webkul/Admin/src/Resources/lang/ar/app.php           # …and where they are
> grep -oP 'الفرص(?!\p{Arabic})' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l  # a whole word, not a prefix
> php scripts/check-arabic-glossary.php --locale                                     # what still drifts
> ```

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
| 25 | `dashboard` | `لوحة القيادة` — **3 `A`** (`A`:2115 widget, `A`:2217 page title, `A`:2362 menu), plus **36 mentions** across `docs/` and `brand/` | **لوحة المعلومات** | `لوحة القيادة` is a cockpit calque; an Egyptian manager who has opened Excel or Power BI has already read `لوحة المعلومات`. **Operator decision on Egyptian usage, 2026-11-04 — no external source settles this row, and none is cited for it.** `لوحة التحكم` is **not** uniformly out of scope: measured, it renders this page in **5 keys** and the admin panel in **2**, so this row is applied key by key, never by string (operator decision 6, §4.5). |
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

**One written form per word — shadda included.** The rule the drift guardrail enforces is *one written
form of one word across a file*, not one form per key. `تعذّر` is the form this product uses for the
verb "failed to", and it is currently the only such form in `A`:
`grep -o -F 'تعذّر' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l` = **1**
(`leads.update-failed`, `A`:1861), against `grep -o -P 'تعذر' … | wc -l` = **2** shipped without the
shadda (`settings.google-contacts.index.connect-failed`, `A`:954, and
`leads.view.quotes.mail-send-failed`, `A`:2034). Those two older keys are being unified to the shadda
form in the same apply lane, so this record matches the tree once that lands; a key that keeps or
reintroduces the bare spelling is a defect to report, not a variant to preserve.

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
| `ID` | *Not* kept. Use `المعرف` — the rule is this table's own: Latin is for protocol and format tokens, and a column header is neither. **Every grid now ships `المعرف`, the two lead-grid keys included** (`A`:1881 `leads.index.datagrid.id`, `A`:1906 `leads.index.kanban.columns.id` — this cell used to say they were the only two that did not); re-measured after the apply, `grep -c 'المعرف' packages/Webkul/Admin/src/Resources/lang/ar/app.php` = **24 lines**, **23** of them `'id' => 'المعرف'` keys, and bare `ID` as a value measures **0**. | The one Latin token this glossary removes. (An earlier draft of this cell pointed at "§5 row 8"; §5 is the seeded-data section and has no row 8. The rule lives here, and §4.7 carries the two keys.) |
| `POST`, `PUT` | **Kept Latin — and this is the first Latin token used as a *control label* in this UI.** The webhook method buttons are `settings.webhooks.{create,edit}.{post,put}`. | Recorded exception, operator decision 2026-11-04 (decision 8). The rule above allows Latin for tokens, not for labels; a method label is the one case where the token *is* the label, and `إرسال` / `تحديث` cannot be used because they already mean *send* (22 `A` keys) and *update* (60 `A` keys) on other screens. |
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
| `lead` (the tracked deal: value, stage, probability, expected close date, products, quotes, won/lost) | **الفرصة** — pl. **الفرص** | `عميل محتمل` (12 `A` + 1 `I`), `العملاء المحتملون` (5 `A`), `القيادة` (2 of the 5 `A` hits, lines 1707/1735 — the other 3 are `لوحة القيادة`), `قائمة العملاء الخاصة بك` / `إنشاء عميل` (kanban empty state, `A`:1993–1994). **`الفرص` as a plural was absent when this row was written and ships now — 27 occurrences on 27 `A` keys**, measured after the apply: `grep -oP 'الفرص(?!\p{Arabic})' packages/Webkul/Admin/src/Resources/lang/ar/app.php \| wc -l` = **27** (whole word only, so the 28 hits on `الفرصة` are excluded) — `acl.leads`, `layouts.leads` (`A`:6), `leads.index.title` (`A`:1878), `dashboard.index.total-leads.title`, `dashboard.index.over-all.total-leads`. | **Settled by an Egyptian ERP's own Arabic docs** — AccFlex: «الفرصة البيعية (Opportunity) هي صفقة قيد التنفيذ … تقع في مرحلة واحدة من مسار بيعي واحد», with fields المرحلة / القيمة / تاريخ الإغلاق المتوقع / المسؤول. Naffes CRM defines it the same way («صفقة مفتوحة في المسار لها قيمة وموعد إغلاق متوقع») and explicitly separates it from العميل المحتمل. The product already uses `الفرصة: :title`, `حول الفرصة`, `...لهذه الفرصة` on the record screen (7×, `A`:2003–2051), so the tree is already half-converted. | The record carries stage + value + probability + expected close date + products + quotes, which is the market definition of **فرصة**, not of **عميل محتمل** (which the same sources define as an *unqualified contact*). Keeping `عميل محتمل` also collides with the seeded stage that is *also* named `عميل محتمل`. | domain | same | **high** | **yes** — was `عميل محتمل` / `العملاء المحتملون` |
| `lead` — **the word in prose** (client guide, sales docs, brand copy). **A documents rule as much as a UI rule:** the product's own record term and the client-facing prose must be the same word, so a doc may not use a name no screen shows. | **الفرصة / الفرص** — the word the UI ships | `الصفقة` / `صفقة` / `الصفقات` **in documents**: **156 occurrences across the Arabic docs at the 2026-11-04 review, and `0` in every locale file** (`grep -rn 'صفق' packages/Webkul/*/src/Resources/lang/ar/ lang/ar/` → no match), so it was never a UI rendering — only a second name invented for the prose. Re-measured after the docs purge, same day: **0**. | **Operator decision, 2026-11-04: `الصفقة` is purged.** No source is cited and none is needed — this is the operator's call that one word names the deal everywhere a client reads it, not a research finding. | It is a **second name for the concept on the surface a client reads most**, and the product never shows it: a client who reads `الصفقة` in the guide and then looks at the screen sees `الفرصة`, which is the exact class of defect this file exists to prevent. `صفقة` was also the word AccFlex and Naffes use when *defining* `الفرصة` (§4.1 `lead` source cell), which is how it leaked into prose. | domain (document) | same | high — **decided** | **yes** — the documents move from `الصفقة` to `الفرصة` |
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

> **`الصفقة` in prose — the documents rule, and its two legal survivors.** The row above is enforced by
> `scripts/check-arabic-glossary.php` (the `deal, in documents (the صفقة family)` rule, scope `docs`) over
> `docs/client-guide/**`, `docs/sales/**` and `brand/**`; the rule matches the **family**, not one written
> form, because the word also ships with proclitics (`بالصفقة`, `للصفقة`, `والصفقة`) and in the construct
> form (`صفقته`). Two senses keep it, and each is an exemption with its own recorded `why`:
> (1) **a glossary's own record row**, which names a rejected term by quoting it — the client guide's
> glossary uses «…» for that; (2) **a change-log line** that records the old name, and **a verbatim client
> quotation** inside «…» (the discovery script prints the rep's own colloquial sentences that way). The
> prose *around* a quotation is not exempt: a line that says `انقل الصفقة لمفاوضات` next to a quoted
> sentence is still a violation.
>
> **History — the brand tagline used to be a third survivor, and stopped being one on 2026-11-04.** The
> rule shipped with an exemption for the approved tagline, which carried `الصفقة` because it was approved
> before this decision existed ([`brand.md`](../operations/brand.md) records that approval); the exemption
> was reported as "the one place the purged word survives with approval". Carrying the tagline with the
> terminology decision, the operator moved it to **كل فرصة لها سجل.** — same line, `الفرصة` in place of
> `الصفقة`, English unchanged (`Every deal, on the record.` is not in this decision's scope). The
> exemption was therefore deleted rather than reworded: the guardrail now *enforces* the tagline instead
> of excusing it, in both directions — any document that quotes the old wording fails the documents rule,
> and `app/Providers/BrandingServiceProvider.php` is asserted by value (`BRAND_VALUES` in the script,
> `sijilna.tagline.ar`), so reverting the shipped string fails the check even though the provider is
> neither a locale file nor a prose surface. The earlier wording was approved and is now superseded: it
> is kept in the record as history, and may not be reinstated without a new operator decision.

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
| `SKU` | **`SKU`** in Latin; the column header is **رمز المنتج** | `ID` used where `المعرف` belongs (§3's own kept-in-Latin rule) | Convention in every Arabic commerce product; the code must match the printed label. | See §3. | protocol | same | high | no |
| `warehouse` | **المخزن** — pl. **المخازن**; inventory card **المخزون** | `المستودع` / `المستودعات` — 14 `A` occurrences of the family, 5 of them the plural, plus 33 mentions across `docs/` and `brand/` | **Operator decision on Egyptian usage, 2026-11-04** (OQ-3 in §6 is resolved). No external source settles `المستودع` versus `المخزن` — Egyptian vendor pages use both (§9.4) — so this row is a decision on record, not a research finding. | `المخزون` (inventory) is already shipped for the parent card, so `المستودع` puts the Gulf form next to the Egyptian root on the same screen; `المخزن` makes the two one family, and it is the word the client's warehouse keeper says. | domain | **Gulf-divergent, now resolved Egypt-side** — Gulf products say `مستودع`, Egypt says `مخزن` | medium — **decided** | **yes** — was `المستودع` / `المستودعات` |
| `quantity`, `price`, `amount`, `total` | **الكمية**, **السعر**, **المبلغ**, **الإجمالي** | — | Shipped consistently in quotes, products and the `PDF`. | Standard line-item vocabulary. | chrome | same | high | no |
| `sub total` / `grand total` | **المجموع الفرعي** / **المجموع الكلي** | — | Shipped (`A` quotes + `I`:60 `الإجمالي الفرعي`) | `I` has one divergent string (`الإجمالي الفرعي`); it should be `المجموع الفرعي`. | domain (document) | same | high | **yes** — the installer's `sub-total` label |
| `discount` / `tax` / `adjustment` | **الخصم** / **الضريبة** / **التعديل** | — | Shipped consistently. | Standard. | domain (document) | same | high | no |
| `billing address` / `shipping address` | **عنوان الفوترة** / **عنوان الشحن** | `عنوان الفواتير` — **1 `I`**, the seeded quote attribute label (`installer:seeders.attributes.quotes.billing-address`), naming the same attribute the quote `PDF` calls `عنوان الفوترة` | Shipped (`A` quote `PDF`). | The seed is the defect: the `PDF` and the seeded attribute label disagree on the same field, and `الفواتير` is the *invoices* plural while this product has no invoice entity yet — §4.3's `invoice` row reserves `فاتورة` for `crm-ytp`. | domain (document) | same | high | **yes** — the installer seed's `billing-address` value |

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
| `configuration` (the settings tree: general, email) | **التهيئة** | Two shipped families. **(a)** *Was* the **same label** `الإعدادات` on the configuration root and the card page — the exact defect `docs/client-guide/ar/17-glossary.md` documents as «البندان متطابقان نصاً في القائمة، وهما شاشتان مختلفتان تماماً». **Applied 2026-11-04 (v1.4):** `layouts.configuration` (`A`:2438) now reads `التهيئة`, while the Settings menu keeps `الإعدادات` (`layouts.settings`, `A`:2385) — so the two menu items no longer read the same and this half of the row is closed. **(b)** `التكوين` — **4 `A` keys**, measured with `grep -o -F 'التكوين'` = 4 occurrences (`التكوينات` 1 of them): `configuration.index.title` (`A`:2082 `التكوين`), `…index.save-btn` (`A`:2077 `حفظ التكوين`), `…index.save-success` (`A`:2078 `تم حفظ التكوين بنجاح.`), and the global-search tab `components.layouts.header.mega-search.explore-all-configurations` (`A`:360 `استكشاف جميع التكوينات`); plus `configuration.index.general.info` (`A`:2085 `تكوين عام`). | `التهيئة` is already the ACL name — 4 `A` occurrences: `acl.configuration` (`A`:31), the global-search tab `…mega-search.tabs.configurations` (`A`:247), the settings-tree menu label (`A`:1617), plus one help sentence (`A`:1775) where `والتهيئة` means "setup", which is the same word doing a different job. | Two different screens with the identical label is a navigation defect, not a terminology preference; one of them must move, and the ACL already picked which. | chrome | same | high | **yes** — the configuration menu label was `الإعدادات`, identical to the other one, and the screen title was `التكوين` (both closed on 2026-11-04: `A`:2438 and the title/save keys now read the `التهيئة` family) |
| `role` | **الدور** — pl. **الأدوار** | — | Shipped 11× `A`. | Standard. | domain | same | high | no |
| `group` | **المجموعة** — pl. **المجموعات** | — | Shipped. | Standard. | domain | same | high | no |
| `permission type` / data scope (`view_permission`: global / group / individual) | **نطاق البيانات** — values **عام**, **المجموعة**, **فردي** | `عرض الصلاحية` (1 `A`:1213) | The product already uses `نطاق بياناتك` in two admin error strings (`A`:2498–2499), and `نطاق البيانات` is the phrase Egyptian admins use for row-level scope. | `عرض الصلاحية` parses as "the viewing of the permission" — it names the ACL, not the scope. | domain | same | high | **yes** — was `عرض الصلاحية` |
| `attribute` (custom field) | **الخاصية** — pl. **الخصائص** | **Two rejected forms, not one — the singular was the larger share and this cell used to leave it unrecorded.** (a) `السمات` — **4** occurrences at `HEAD`, **0** now: `git grep -c -F 'السمات' HEAD -- packages/Webkul/Admin/src/Resources/lang/ar/app.php` = 4 (ACL `attributes`, the web-form `create` and `edit` `attributes`, and the attribute-index title), and the same command without `HEAD --` on the working tree = 0. (b) `السمة` and bare `سمات` — **11** and **7** occurrences at `HEAD`, **0** of either now, measured the same way (`git grep -c -F 'السمة' HEAD -- …` = 11, `git grep -c -F 'سمات' HEAD -- …` = 7); 4 of the 7 `سمات` are the `السمات` in (a), so **3 are bare `سمات`**. The web-form screen shipped `زر إضافة سمة` and `لون تسمية السمة` (both `create` and `edit`) and `أضف سمات مخصصة إلى النموذج.`, and the attribute index shipped its own sentences — `تم إنشاء السمة بنجاح.`, `تم تحديث السمة بنجاح.`, `تم حذف السمة بنجاح.`, `لا يمكن حذف السمة.`, `حفظ السمة`, `إضافة خيارات السمة`, `تعديل السمة`, `لا يمكن حذف سمات النظام.` — and the three web-form keys that carried the singular now ship `زر إضافة خاصية`, `لون تسمية الخاصية` and `أضف خصائص مخصصة إلى النموذج.` | Screens/automation use `الخصائص` — **6 `A`** counted now (`grep -o -F 'الخصائص' packages/Webkul/Admin/src/Resources/lang/ar/app.php \| wc -l` = 6: the ACL, the web-form `create` and `edit` `attributes`, the attribute-index title, the settings menu and its `attributes-info`), against **12** for the singular `الخاصية`; this cell said "2 `A`" before the apply; ACL and web-form use the rejected `السمات`, which is the part that changes. Egyptian users of Arabic SaaS meet `حقل مخصص` most, and `الخاصية` for the definition. | `سمة` in Arabic means a *trait of a person*; on a form it reads as psychology, not as a field definition — and the defect is the same in the singular as in the plural, which is why both are rejected here. | domain | same | medium | **yes** — was `السمات` in the ACL and web-form labels, **and `السمة` / `سمات` in the web-form screen and the attribute index** |
| `tag` | **الوسم** — pl. **الوسوم** | `العلامات` (5 `A`: 1780 mail, 1912, 1938, 2432, 2433) + `العلامة` (6 `A`: 1833, 1834, 1887, 2068, 2069, 2165) | Shipped majority is already `الوسوم`/`الوسم` — **16 `A`** (11 `الوسم` + 5 `الوسوم`) against **11 `A`** for `العلامات` + `العلامة` (5 + 6, counted once each; `العلامات` and `العلامة` are different words, so the pair cannot overlap, and one of the six is `لون العلامة التجارية` at `A`:2165 — the brand mark itself). | `علامة` collides with the brand mark (`العلامة التجارية`) and with a check mark; `وسم` is the standard Arabic for a tag in every Arabic product. | domain | same | high | **yes** — was `العلامات` / `العلامة` in mail, the lead grid and the tag tool |
| `workflow` | **سير العمل** | — | Shipped 13× `A`. | Standard. | domain | same | high | no |
| `event` (automation trigger) | **الحدث** — pl. **الأحداث** | `الفعاليات` (2 `A`: 2422–2423) | `حدث` is already shipped 20× as the trigger word. | `فعالية` means "an event you attend"; using it for a trigger is the same class of literal as `القيادة`. | domain | same | high | **yes** — was `الفعاليات` |
| `campaign` | **الحملة** — pl. **الحملات** | — | Shipped 6× `A`. | Standard marketing Arabic. | domain | same | high | no |
| `email template` | **قالب البريد الإلكتروني** | `نموذج البريد الإلكتروني` — **1 `A` key**, `settings.marketing.campaigns.index.create.email-template` (`A`:842). The other 2 names the inventory counts for this concept (`قالب البريد`, `قوالب البريد`) are the same lexeme shortened in the same key family (`settings.email-template.*`), not a third name. | Shipped **9× `A`** singular (`قالب البريد الإلكتروني`) and **4×** plural (`قوالب البريد الإلكتروني`), across `settings.email-template.*`, the ACL and the sidebar. | `نموذج` means a *form* or a *model*, not a template — in this product `نموذج` already means the web form (`نماذج الويب`), so the campaign screen's dropdown offers a word that names a different object. | domain | same | high | **yes** — was `نموذج البريد الإلكتروني` in the campaign form |
| `webhook` | **ويب هوك** — pl. **الويب هوك** | `الويب هوكس` as a plural (mixed Arabic article + English plural) | Shipped 17× `A`. | Egyptian developers say "ويب هوك"; the Arabic-transliterated form is what the UI already uses and what a support reply will use. Fix only the plural construction if it is touched. | domain | same | medium | no |
| `HTTP method label` (webhook POST/PUT) | **`POST`** / **`PUT`** — kept in Latin | `إرسال` (`settings.webhooks.create.post` `A`:974, `…edit.post` `A`:1001) and `تحديث` (`…create.put` `A`:975, `…edit.put` `A`:1002) | **Operator decision, 2026-11-04** (decision 8a). The label names the protocol verb the webhook author is choosing; this screen already keeps `HTTP`, `HTTPS` and `URL` in Latin (§3), and every Arabic webhook UI in the market leaves the method in Latin. | `إرسال` already means *send* in **22 `A` keys** and `تحديث` already means *update* in **60 `A` keys** (61 with `التحديث`), so neither can label a method without reading as an action button. **This is the first Latin token used as a control label in this UI** — the exception is recorded in §3. | protocol | same | high — **decided** | **yes** — was `إرسال` / `تحديث` |
| `webhook endpoint` | **عنوان URL** | `نقطة النهاية` (`settings.webhooks.index.datagrid.end-point` `A`:968), `نقطة نهاية الرابط` (`…create.url-endpoint` `A`:976, `…edit.url-endpoint` `A`:1003) | §3's existing rule: **`عنوان URL` is shipped and correct.** The webhook screen's own error string already reads `يجب أن يكون عنوان URL لنقطة نهاية الويب هوك عنوان HTTP أو HTTPS عامًا` (`A`:962). | Three names for one field, and the field is a URL: `نقطة النهاية` is a word-for-word *endpoint*, and `نقطة نهاية الرابط` is the same literal with `الرابط` welded on. The error sentence at `A`:962 is prose, not a naming slot, and keeps its wording. | protocol | same | high | **yes** — those three keys |
| `payload` / `headers` / `body` / `method` (webhook) | **`حمولة`**, **`الرؤوس`** (sing. **`رأس`**), **`الجسم`**, **`الطريقة`** | — nothing rejected: these **keep their shipped values** | **Operator decision, 2026-11-04** (decision 8c), taken to close the inventory's `http_terminology` cluster: the strings ship (`A`:980–986 plus the `edit` twins) and were unsettled rather than wrong. | They are word-for-word technical renderings (`حمولة` is *cargo*), but only a developer reads them, on the webhook form, and no competing Arabic rendering ships anywhere in the tree. The row exists so the cluster stops being an open question, **not** because a change is owed. | protocol | same | medium — **decided, no change** | no |
| `data transfer` / `import` | **استيراد البيانات** for the section, **استيراد** for the action, **الاستيرادات** for the history list | `نقل البيانات` (3 `A`:44 and two configuration labels) | The screen's own strings are all `استيراد` (10+ `A`: 1561, 1611, 1632, 1636, 1642, 1666, 1667). | `نقل البيانات` ("moving data") is vaguer and disagrees with every string inside the screen it opens. **`تصدير` (export) is the sibling action and is correct as shipped** — it is not a competing name for import, so the 3 keys that carry it (`dashboard.index.export-pdf`, `export.export`, `export.no-records`) do **not** change (inventory §7 correction, 2026-11-04). | chrome | same | high | **yes** — was `نقل البيانات` for the section label |
| `web form` | **نموذج الويب** | — **nothing is rejected here: this concept is protected as shipped (operator decision 7, 2026-11-04).** The three renderings are `نموذج الويب` (settings — 4 naming slots, 10 occurrences `A`:963–1031), `نموذج ويب` (the seeded lead source value, `installer:seeders.lead.source.web-form`) and `نماذج الويب` (the list title and the package menu, 3 keys). | Shipped (`A`, `W`), and it is also the seeded lead source `نموذج ويب`. | **Do not unify.** Definite label, list value and plural list screen are three grammars of one word, all correct: `نموذج الويب` is a definite noun label, `نماذج الويب` is the plural a list screen needs, and `نموذج ويب` is an indefinite list *value* sitting in a dropdown beside `البريد الإلكتروني` and `مباشر`. Merging them would break either the label or the seeded data. The inventory classes `web_form` `conflicting` (2 names / 78 keys); **the glossary protects it, and the glossary wins** — an applier must leave every one of these keys alone. | domain | same | high | **no — and no key may be touched** |
| `source` | **المصدر** — pl. **المصادر** | — | Shipped 13× `A`. | Standard. | domain | same | high | no |
| `type` | **النوع** — pl. **الأنواع** | — | Shipped 20× `A`. | Standard. | domain | same | high | no |
| `dashboard` | **لوحة المعلومات** | `لوحة القيادة` (3 `A`: 2115 widget, 2217 page title, 2362 menu; plus 36 mentions across `docs/` and `brand/`). `لوحة التحكم` is **a homograph, not a rival term**: measured, `grep -o -F 'لوحة التحكم'` = **8 occurrences on 7 keys** — it renders *this page* in **5** of them (`dashboard.index.pdf.title` `A`:2234, `…settings.dashboard.title` `A`:2132, `.info` `A`:2133, `.date-range-info` `A`:2135 ×2 inside the value, `errors.dashboard` `A`:2492) and the *admin panel* in the other **2** (`…locale-settings.title-info` `A`:2091, `…footer.show-info` `A`:2106). | **Operator decision on Egyptian usage, 2026-11-04** (OQ-2 in §6 is resolved). `لوحة المعلومات` is the Microsoft / Power BI / Excel rendering — the form an Egyptian manager who has opened any analytics tool has already read. No external source lists Arabic dashboard terms (§9.4), so the row rests on the operator's read of Egyptian usage and on nothing else. | `لوحة القيادة` is a cockpit calque: it puts a steering wheel on a report page. The 5 dashboard keys become `لوحة المعلومات`; the 2 admin-panel keys keep `لوحة التحكم` untouched, because that string means the admin panel *as a whole* ("واجهة لوحة التحكم") and the same 5-key/2-key split is what makes a string sweep unsafe here (operator decision 6). | chrome | **Egypt-divergent** — Gulf products keep `لوحة القيادة`; Egyptian readers meet `لوحة المعلومات` in Excel / Power BI | medium — **decided** | **yes** — was `لوحة القيادة`, and `لوحة التحكم` in exactly 5 keys |
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
| `drafts` | **المسودات** | `مسودة` in the **folder label** only: `mail.index.draft` (`A`:1757). The second `مسودة` — `mail.index.mail.draft` (`A`:1773, the compose dialog that saves *one* draft) — is **not** rejected and does not change (operator decision 10, 2026-11-04). | Shipped majority `المسودات` (3 `A`: `acl.draft`, `…settings.menu.draft`, `layouts.mail.draft`). | Folder names are plural, and the sidebar already ships `المسودات` in two other places — but a compose dialog is one draft, so the split is by key, not by word. | chrome | same | high | **yes** — the single folder label `mail.index.draft` |
| `trash` | **سلة المهملات** | `المهملات` (1 of 4 `A` hits, the mail folder label at `A`:1761 — the other 3 are inside `سلة المهملات`), `تم النقل إلى سلة المهملات` (1 `A`:1785, an action label written as a past-tense sentence), and `المحذوفات` — **1 `A` key**, the ACL label `acl.trash` (`A`:10), which is a *third* name for this folder in a screenshot a client sees when building a role | Shipped majority `سلة المهملات` (3 `A`:1785, 2129 settings, 2374 menu). | Both the folder and the action currently exist; the folder should read `سلة المهملات` and the action should read `نقل إلى سلة المهملات`. `المحذوفات` ("the deleted things") is a word-for-word rendering of *trash* sitting in the ACL tree, which is the one place a role builder reads it beside its siblings `البريد الوارد` and `المرسل`. | chrome | same | high | **yes** — the action label `تم النقل إلى سلة المهملات` and the ACL label `المحذوفات` |
| `routed mail` (`المرسل` vs `الصادر` distinction) | **الصادر** = queued, **المرسل** = actually left the system | — | Both shipped; the client guide already documents the difference. | Keep the distinction; it is real and the guide explains it. | chrome | same | high | no |

### 4.7 Fixture-level defects the client guide already names

These are not terminology choices; they are Arabic errors in shipped strings. The first block is the set the client guide already names; the second is the four defects the operator approved in the second 2026-11-04 pass (§4.7.1), recorded here so the apply lane executes them from one table; the third is the one defect that surfaced while applying those four and was decided then (§4.7.2).

| what ships | where | problem | approved replacement |
| --- | --- | --- | --- |
| `لا توجد أشخاص متاحة للفترة المختارة` | dashboard `top-persons.empty-info` (`A`) | Gender disagreement: `أشخاص` is masculine plural, `متاحة` is feminine singular. | `لا توجد أشخاص متاحون للفترة المختارة` |
| `العميل الفاسد منذ :days أيام` | kanban card | Literal rendering of *rotten*; covered by the rotten-lead row above. | `مهمل منذ :days يومًا` (with the correct number agreement arm) |
| `تم النقل إلى سلة المهملات` | mail datagrid action | Past-tense sentence where a verb phrase belongs on a control. | `نقل إلى سلة المهملات` |
| `تم الإرسال` | mail folder label | Past-tense sentence where a noun belongs. | `المرسل` |
| `أضف طلب المنتج لهذا العرض.` | quotes (`A`:453, 482) | "طلب المنتج" is not a thing in this product; a quote line is a product line. | `أضف منتجًا إلى هذا العرض.` |

### 4.7.1 The four defects approved 2026-11-04

Decided, not proposed. Each is a single key (or a two-key pair), so none of them needs a sweep.

| what ships | where | problem | approved replacement |
| --- | --- | --- | --- |
| `Leads can not be deleted.` → `لا يمكن حذف الفرص.` | `leads.update-failed` — English `packages/Webkul/Admin/src/Resources/lang/en/app.php`:1863, Arabic (`A`):1861 | The English value was an upstream copy-paste of the **delete**-failure sentence (`leads.destroy-failed`, `en`:1865) sitting under an **`update-failed`** key, in the message a user sees when an edit fails. Fixing only the Arabic, as first decided, left a correct translation honouring a promise the key never makes. | **Both sides were fixed, not only the Arabic.** English source `Leads can not be deleted.` → **`Lead can not be updated.`**; Arabic → **`تعذّر تحديث الفرصة.`** Verified in the tree with `grep -n "'update-failed'" packages/Webkul/Admin/src/Resources/lang/en/app.php packages/Webkul/Admin/src/Resources/lang/ar/app.php` → `en`:1863 `Lead can not be updated.`, `A`:1861 `تعذّر تحديث الفرصة.` **The now-superseded first decision, taken in the first 2026-11-04 pass: translate the sentence it actually carries.** That pass shipped `Leads can not be deleted.` → **`لا يمكن حذف الفرص.`**, using the approved record term **`الفرصة` / `الفرص`** (row 1 of the 26), **not** `العملاء المحتملين`, on the reasoning that the value is what changes and the sentence under the key is what the user reads — i.e. translate the bug faithfully rather than invent an update message. **A later pass on the same day, 2026-11-04, superseded it:** the operator reversed the decision — the key is `update-failed`, and translating the delete sentence faithfully left the Arabic promising something the key never does, so the English was corrected too. Read the shipped sibling family in the `en` file rather than from memory — it now reads `Lead created successfully.` / `Leads updated successfully.` / `Lead can not be updated.` (this key) / `Lead deleted successfully.` / `Lead can not be deleted.` |
| `ID` | `leads.index.datagrid.id` (`A`:1881) and `leads.index.kanban.columns.id` (`A`:1906) | Bare Latin `ID` as a column header, in what **was** the only grid in the product that did not ship `المعرف` — measured at review time, **21 other keys** shipped `المعرف` exactly (20 `*.datagrid.id` headers plus `leads.view.quotes.id`), and these two were the only ones that did not. **Applied 2026-11-04 (v1.4):** both keys now read `المعرف`; re-measured, `A` ships **23** `'id' => 'المعرف'` keys (24 occurrences — the 24th is the `id-not-found` message at `A`:1580) and **zero** bare `ID` values. | **`المعرف`**, per §3's own kept-in-Latin rule (the dangling "see §5 row 8" pointer that used to live in that rule is fixed). |
| `Json` | `settings.webhooks.create.json` (`A`:1325) and `settings.webhooks.edit.json` (`A`:1354) | Case defect in a Latin token the UI keeps on purpose; the installer's server-requirements checklist already ships `JSON` (`installer:installer.index.server-requirements.json`). | **`JSON`** |
| `إعادة تعيين كلمة مرور العميل` | `emails.common.user.forget-password.subject` (`A`:2475) | Labels the CRM **user** as a customer: this is the mail subject sent to a *user* who forgot their password, and `عميل` is **reserved** for the customer record that does not exist yet (§4.2 `customer` row, §6 OQ-1). | **`إعادة تعيين كلمة مرور المستخدم`** — required by the reserved-word rule, not by style. |

### 4.7.2 Decided during the apply phase — the import header error

One defect the first two passes never reached; applying §4.7.1 surfaced it. Same class as the block
above — a wrong noun in a shipped string, not a terminology preference — so it is recorded here.

| what ships | where | problem | approved replacement |
| --- | --- | --- | --- |
| `الرأس يحتوي على سمة غير صالحة: "%s".` | `settings.data-transfer.validation.errors.invalid-attribute` (`A`:1591) | `سمة` means a *personal trait* — the same wrong noun §4.5's `attribute` row rejects — and it was naming a column of an imported file. **Verified from the code, not assumed:** `AbstractImporter::validateData()` (`packages/Webkul/DataTransfer/src/Helpers/Importers/AbstractImporter.php`:222–241) iterates `$this->getSource()->getColumnNames()` — the CSV **header** row — and raises this code at `:239–240` when a header column name is not in `getValidColumnNames()`; `AbstractImporter::addErrors()` (`:455–461`) joins the offending names with `implode('", "', $errors)`; and `Error::getErrorMessage()` (`packages/Webkul/DataTransfer/src/Helpers/Error.php`:120–142) runs `sprintf($message, $columnName)` at `:134`. So the placeholder is **always a column name** (or several, joined) — never an attribute's label. | **`اسم العمود "%s" غير صالح.`** — the noun is `العمود` because the column is what the user has to fix, and `اسم` names the thing in the header, which is what the placeholder carries. The sibling key `column-name-invalid` (`A`:1588, `أسماء الأعمدة غير صالحة: "%s".`) is a **different code path** — the header name failed the `^[a-z][a-z0-9_]*$` shape check — stays **plural**, and was **not touched**. The English value of this key, `Header contains invalid attribute(s): "%s".` (`en`:1593), was also **not touched** — an attribute *is* what the header declares, so the English is correct and only the Arabic noun was wrong; do not "align" the two. |

### 4.8 Sign-in and password reset

This surface had **no glossary row at all** before 2026-11-04, and the inventory records that the flow
appears in **no client-guide chapter** either — which is how it shipped three titles for a two-step flow
without anyone noticing.

| concept | approved Arabic | rejected renderings present in the product | source | reason | register | EG vs Gulf | confidence | overturns shipped term? |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `sign-in` screen | **تسجيل الدخول** | — (the button and the title agree: `users.login.submit-btn` and `users.login.title`, `A`:60–61) | Shipped. | The standard Arabic for the screen. The credential error on that screen (`users.login-error`, `A`:54 `بيانات الاعتماد`) is a word-for-word *credential data* that the inventory flags `suspect-literal`; it is flagged here, **not decided**, and it is not part of this row's change. | chrome | same | high | no |
| `password reset` / forgot-password flow | **استعادة كلمة المرور** for step one, **إعادة تعيين كلمة المرور** for step two | `نسيت كلمة المرور` as a **title**: `users.forget-password.create.page-title` (`A`:67) | **Operator decision, 2026-11-04** (decision 9). The rule it applies is already in this file (§1, and the `quotes` row): a tab title and the heading under it must read the same — and that heading is `استعادة كلمة المرور` (`users.forget-password.create.title`, `A`:71). | The flow has two real steps, so two names are correct here: step one asks for the address that resets the password (`استعادة كلمة المرور`), step two is where the new password is typed (`إعادة تعيين كلمة المرور`, `users.reset-password.title` and `.submit-btn`, `A`:80). The link that opens the flow, `users.login.forget-password-link` (`A`:58 `نسيت كلمة المرور؟`), **stays as shipped** — it is the question a user clicks, not a screen name. | chrome | same | high — **decided** | **yes** — the tab title `users.forget-password.create.page-title` moves from `نسيت كلمة المرور` to `استعادة كلمة المرور` |

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

## 6. Questions: six resolved, two still open

Rows the research did not settle. Each one names the options and the evidence that would decide it.
OQ-1 to OQ-5 went to the operator on **2026-11-04** and are recorded below as resolved — what was
asked, what was decided, why, and the constraint each answer leaves behind. Read the resolved entry
before reopening one: a decision on record beats a second opinion. OQ-6 and OQ-7 are still open; do not
guess them in code, ask the operator. OQ-8 was closed on 2026-11-04 by the fixture fix in §4.7.1 and is
kept below as history.

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
has seen. `لوحة التحكم` is already taken in this product for the admin panel as a whole — in 2 of its 7
keys; the other 5 use it for the dashboard itself, which is the split decision 6 records.
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
**split by key, not replaced** (decision 6): measured, `grep -o -F 'لوحة التحكم'` finds **8 occurrences
on 7 keys** — 5 render this page and move to `لوحة المعلومات`, 2 mean the admin panel as a whole and
keep `لوحة التحكم`. The string is therefore a homograph and no sweep on it is safe.

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

### 6.2 OQ-6 and OQ-7 still open; OQ-8 closed 2026-11-04

Neither the research nor the decisions above reach OQ-6 and OQ-7: one needs a rendering check, one a
policy call. OQ-8 is kept below because its question was real when it was asked and its answer lives
somewhere else — a resolved question is history worth keeping, not a row to delete.

**OQ-6 — `عرض سعر` inside a record is a document; the record-screen tab is a *list* of quotes.**
The approved pair is `عرض سعر` (one) / `عروض الأسعار` (the section). The tab on the deal record lists
the quotes attached to that deal, so it should read `عروض الأسعار` too — but a tab label that long may
not fit the tab strip. *What would settle it:* the rendered tab width in `ar`. Recommended: try
`عروض الأسعار`; if it wraps, use `العروض` **only** there and record the exception in this file.

**OQ-7 — Arabic-Indic digits for the printed `PDF`.**
The rule is Western digits everywhere, and ETA's own documents use Western digits, so this is only
open if the operator wants the exported quote `PDF` to look "official" in a different way. No evidence
supports changing it. Listed so that no one "improves" the `PDF` later without a decision on record.

**OQ-8 — the other nine locales carry Arabic-looking or untranslated leftovers.** *(The question as it
was first written, kept verbatim as history:)*
`packages/Webkul/Admin/src/Resources/lang/fa/app.php` contains `مفقود`, and `leads.update-failed`
ships as a full English sentence in the **Arabic** file. Locale hygiene is outside this glossary;
recorded so that a `fa` string is never mistaken for an `ar` one during the rename pass.

**Resolved 2026-11-04 (v1.4) — the Arabic half is closed by §4.7.1; the `fa` half stands, still unowned.**
The claim that `leads.update-failed` ships as an English sentence in the Arabic file is **no longer true**:
the apply lane corrected both sides of that key, because the defect was the key promising an update while
the sentence promised a delete. Measured:
`grep -n "'update-failed'" packages/Webkul/Admin/src/Resources/lang/{ar,en}/app.php` → `ar`:1861
`تعذّر تحديث الفرصة.`, `en`:1863 `Lead can not be updated.` So the question no longer speaks about the
Arabic locale and has nothing left to decide there. Its other half — a `مفقود` string sitting in
`packages/Webkul/Admin/src/Resources/lang/fa/app.php` — was never about this glossary, is not read by
`scripts/check-arabic-glossary.php` (the `fa` file is not one of the five `ar` files), and remains open
where it belongs: locale hygiene across the other nine locales.

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

# 1b. The second-review decisions of 2026-11-04, each measurable on its own:
grep -o -F 'لوحة التحكم' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l   # 8 occurrences, on 7 keys
#     5 dashboard keys + 2 admin-panel keys — the split is in operator decision 6
grep -n 'لوحة التحكم' packages/Webkul/Admin/src/Resources/lang/ar/app.php
#     2091 locale-settings.title-info · 2106 footer.show-info            → admin panel, keep
grep -o -F 'نموذج الويب' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l    # 10 — protected, no edit
grep -o -F 'التكوين' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l       # 4 → all become التهيئة
grep -o -F 'نقطة نهاية الرابط' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l  # 2 → عنوان URL
grep -o -F 'إرسال' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l          # 22 keys — why POST cannot reuse it
grep -o -F 'تحديث' packages/Webkul/Admin/src/Resources/lang/ar/app.php | wc -l          # 60 keys — why PUT cannot reuse it

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
