<!-- Context: guides/arabic-naming-inventory.md | Priority: critical | Version: 1.1 | Updated: 2026-11-04 -->

# Arabic naming inventory — one concept, every name the product currently uses

**What this is.** A map, not a decision. Every Arabic string the product ships for a
given business concept, every translation key that renders it, and the class of the
disagreement. It proposes no terms — the glossary and the replacement terms are a
separate deliverable.

**What it changes.** Nothing. This file is the only write. No product string, no
seeder, no `lang` file and no client-guide chapter was touched.

**Beads task:** `crm-mox.2` (parent epic `crm-mox`; blocks `crm-mox.3`).
**Produced:** 2026-11-04.
**Corrected:** 2026-11-04 — six mis-classifications that the reconciliation against the glossary measured
are fixed **in place, and each one states its correction rather than deleting the finding**; §13 lists
all six with the command that re-measures each number. Three headline counts moved as a result
(`consistent` 30 → 31, `conflicting` 25 → 24, `data_transfer` names 3 → 2) and every stale total in §3 and
§5 was recomputed, not left behind.

---

## 1. Corpus

| source | role | Arabic-bearing keys |
| --- | --- | --- |
| `packages/Webkul/Admin/src/Resources/lang/ar/app.php` | every admin screen, datagrid, form, toast, mail and PDF string | 1,903 |
| `packages/Webkul/Installer/src/Resources/lang/ar/app.php` | install-time seed values (pipeline, stages, sources, types, roles) plus the installer wizard | 203 |
| `lang/ar/validation.php` | Laravel validation messages | 89 |
| `packages/Webkul/WebForm/src/Resources/lang/ar/app.php` | web-form package | 8 |
| `packages/Webkul/GoogleContact/src/Resources/lang/ar/app.php` | Google Contacts package | 1 |
| `packages/Webkul/Installer/src/Database/Seeders/**` | the code that writes Arabic into the database | 0 literals of its own |
| `docs/client-guide/ar/*.md` | 17 chapters + `README.md` | term occurrences only |

**Seeded data has no Arabic of its own.** Every seeder resolves its value through
`trans()`. `Lead/PipelineSeeder.php` calls
`trans('installer::app.seeders.lead.pipeline.pipeline-stages.won', [], $defaultLocale)`
and the installer passes the install-time locale, so the *only* place a seeded
Arabic name lives is
`packages/Webkul/Installer/src/Resources/lang/ar/app.php`. Changing the seed means
changing that file (and re-seeding, for existing installs). `Lead/SourceSeeder.php`
and `Lead/TypeSeeder.php` work the same way.

```text
$ grep -rlP '[\x{0600}-\x{06FF}]' packages/Webkul/Installer/src/Database/Seeders/
(no output)
```

**Hardcoded Arabic outside the lang files: one occurrence, and it is a code comment,
not a product string.**

```text
$ grep -rlP '[\x{0600}-\x{06FF}]' --include='*.blade.php' --include='*.vue' \
    --include='*.php' packages/ | grep -v vendor | grep -v node_modules \
    | grep -v '/Resources/lang/'
packages/Webkul/Admin/src/Resources/views/activities/index.blade.php
$ grep -nP '[\x{0600}-\x{06FF}]' packages/Webkul/Admin/src/Resources/views/activities/index.blade.php
1175:             * one-letter form, so the heading row read as fragments (`ا`, `حدث`) instead of day
```

---

## 2. Method

The corpus is parsed by script, never by reading 2,539 lines into context. The whole
extraction lives in one file so the next agent can re-run it instead of trusting this
page:

```text
php scripts/arabic-naming-inventory.php . > /var/tmp/pi-scratch/<runId>-arabic-inventory/inventory.json
```

The script is reproduced verbatim in [Appendix B](#appendix-b--the-extraction-script)
because this deliverable was allowed exactly one write, so the script itself is not a
second file in the repository. Copy it out and run it against any checkout.

**Steps it performs**

1. `require`s each lang file and flattens the nested array to `ns:dotted.key = value`.
   Keys are counted, not lines.
2. Keeps values containing at least one Arabic letter (`U+0600–U+06FF`) as the
   **Arabic set**; counts the rest as **Latin-only**. A Latin-only value inside an
   `ar` file is either a proper noun / protocol (`IMAP`, `CSV`, `PHP`) or an
   untranslated product string — section 8 separates the two.
3. Assigns every key to one **or more** concepts from a key-pattern table. A key may
   serve two concepts: `leads.index.datagrid.sales-person` renders the Leads screen
   *and* names the Sales-owner concept, so it lands in both. Key→concept assignments
   therefore exceed the key count.
4. For each concept, scans **only that concept's own keys** for the concept's Arabic
   surface forms, taken from the synonym map in section 9. Restricting the scan to the
   concept's keys is what stops `النوع` inside `نوع الكيان` (attribute) from being
   counted as a Lead-type name.
5. Classifies every hit as a **naming slot** or **prose**. A naming slot is: an ACL
   label, a sidebar label, a screen title, an entity-type label, a seeded value, or any
   value of at most six words with no sentence terminator. A sentence such as
   «تم حذف العميل المحتمل بنجاح.» mentions the concept but does not name it, so it is
   reported as prose and never votes.
6. Collapses surface forms into **lexemes**. One lexeme is one word. شخص / الشخص /
   أشخاص / الأشخاص are a single name in four inflections; الفرصة is a second name;
   القيادة a third. The number of *names* per concept is the lexeme count — counting
   inflections instead would report a conflict wherever Arabic marks definiteness.
7. Counts the same surface forms inside `docs/client-guide/ar/*.md`.

**What "measured" means here.** Every number below was produced by that script against
this checkout. Nothing is estimated. Where a count depends on a judgement — the
suspect-literal classification, and the decision that two inflections are one name —
the rule is stated, so it can be re-derived or disputed.

---

## 3. Counts

| measure | value |
| --- | --- |
| Arabic-bearing keys across the five lang files | **2,204** |
| Latin-only values in the same files | **42** non-empty (43 with the one key whose value is empty, `installer:installer.index.webkul`) |
| Concepts identified | **55** |
| Key→concept assignments (a key can serve two concepts) | **2,475** |
| Naming-slot hits (label-shaped occurrences that were classified) | **555** |
| Concepts with exactly **1** name — `consistent` | **31** |
| Concepts with exactly **2** names — `conflicting` | **14** |
| Concepts with **3 or more** names — `conflicting` | **10** |
| Client-guide chapters scanned | **18** (17 chapters + `README.md`) |
| Distinct Arabic surface forms flagged `suspect-literal` | **44** |

**55 = 31 + 14 + 10.** 24 of 55 concepts — **44%** — carry more than one name today.

*Recomputed 2026-11-04, and the arithmetic stated so it can be checked rather than trusted:* the first
pass reported `55 = 30 + 13 + 12` and "25 of 55 — 45%". Three corrections in §13 move one concept each
into a different bucket — `activity_type` and `email_settings` are not conflicts at all (§13 items 1 and
3) and `contact_person` is one (§13 item 4) — so `consistent` gains two and loses one (**30 + 2 − 1 =
31**), and `conflicting` loses two and gains one (**25 − 2 + 1 = 24**), of which **14** carry two names
and **10** carry three or more. 24 ÷ 55 = 43.6% → **44%**.

### The 15 worst

Ordered by number of names, then by how many keys are affected.

| # | concept | names | keys | the names, in full |
| --- | --- | --- | --- | --- |
| 1 | `http_terminology` | 6 | 13 | نقطة النهاية · نقطة نهاية الرابط · حمولة · الرؤوس · رأس · الجسم · الطريقة · إرسال · تحديث |
| 2 | `lead` | 5 | 180 | عميل محتمل · العملاء المحتملون · العميل / العملاء · الفرصة · القيادة |
| 3 | `quote` | 5 | 143 | عرض السعر · عروض الأسعار · العروض / عروض · عرض · الاقتباس |
| 4 | `webhook` | 3 | 68 | الويب هوك · ويب هوك · Webhook |
| 5 | `sales_owner` | 3 | 14 | مالك المبيعات · مندوب المبيعات · مسؤول المبيعات |
| 6 | `password_reset` | 3 | 13 | نسيت كلمة المرور · استعادة كلمة المرور · إعادة تعيين كلمة المرور |
| 7 | `pipeline_stage_won` | 3 | 10 | مُنتصر · ربحت / فاز · المكتسب |
| 8 | `rotten` | 3 | 8 | أيام التعفن / التعفن · متعفن / الفاسد · مهمل |
| 9 | `mail_trash` | 3 | 4 | سلة المهملات · المهملات · المحذوفات |
| 10 | `address` | 3 | 4 | عنوان الشحن · عنوان الفوترة · عنوان الفواتير |
| 11 | `attribute` | 2 | 196 | السمة · الخاصية |
| 12 | `data_transfer` | 2 | 120 | نقل البيانات · الاستيراد (التصدير is the sibling action, not a third name — §13 item 2) |
| 13 | `configuration` | 2 | 98 | التهيئة · التكوين |
| 14 | `dashboard` | 2 | 81 | لوحة القيادة · لوحة التحكم (5 dashboard keys only — §13 item 5) |
| 15 | `web_form` | 2 | 78 | نموذج الويب · نماذج الويب (both correct — the glossary protects this concept) |

Runners-up: `pipeline` (2 names / 64 keys), `tag` (2 / 44), `email_template` (2 / 31),
`organization` (2 / 29), `event` (2 / 28), `contact_person` (2 / 11), `pipeline_stage_lost` (2 / 10),
`mail_inbox` (2 / 4), `mail_sent` (2 / 4).

*Recomputed 2026-11-04.* The first pass put `data_transfer` at #4 and `activity_type` at #6 on the
strength of the corrections now undone: `data_transfer` drops to two names (120 keys still rank it 12th,
because the tie-break is key count) and `activity_type` leaves the table entirely — its three singulars
are three different values, not three names for one (§13 item 1). `web_form` comes in at 15th only
because 78 keys break the tie among the two-name concepts; the glossary protects it as shipped as
correct, so its rank is not a work order.

### The 31 concepts that already agree

`lead_value`, `lead_source`, `lead_type`, `pipeline_stage`, `probability`, `person`,
`contacts`, `product`, `activity`, `activity_type`, `warehouse`, `inventory`, `role`,
`group`, `user`, `workflow`, `automation`, `campaign`, `email_settings`, `settings`, `mail`,
`mail_outbox`, `mail_draft`, `menu_item`, `mega_search`, `notification`, `quick_add`,
`record_id`, `sku`, `account`, `login`.

*Recomputed 2026-11-04:* `activity_type` and `email_settings` join this list (neither is a conflict —
§13 items 1 and 3) and `contact_person` leaves it (two lexemes, not one — §13 item 4), so the list is
**30 + 2 − 1 = 31**.

"Consistent" here means **one name**, not "one spelling". البريد / البريد الإلكتروني,
المستخدم / المستخدمون / المستخدمين, شخص / أشخاص — these are inflections of one word.
Two of them are still worth a note:

- `user` and `contacts` ship both `المستخدمون` (nominative) and `المستخدمين`
  (accusative/genitive) as static labels: `acl.users` = المستخدمون but
  `layouts.users` = المستخدمين, and the same split exists for `العملاء المحتملون` /
  `العملاء المحتملين`. Both are grammatically defensible in context and neither is a
  second name, but a glossary has to pick one form for each slot or the guardrail will
  flag it forever.
- `record_id` shows one Arabic name (`المعرف`) but the leads grid ships the Latin
  `ID` — see section 8.

---

## 4. The three layers that name the same thing

Each concept below has a sidebar label, a screen title and an ACL label, and the three
disagree often enough to be worth seeing side by side. **23 concepts carry all three
layers; only 11 agree across them.**

Sidebar label = `admin::app.layouts.*` (what `packages/Webkul/Admin/src/Config/menu.php`
renders) or a package `menu.title`. Screen title = `*.index.title`. ACL label =
`admin::app.acl.*`, which is what you see when you build a Role.

| concept | sidebar label | screen title | ACL label | verdict |
| --- | --- | --- | --- | --- |
| `lead` | العملاء المحتملين | العملاء المحتملون | العملاء المحتملون | **split 2 ways** |
| `lead_source` | المصادر | المصادر | المصادر | **agree** |
| `pipeline` | الأنابيب | خطوط الأنابيب | قنوات البيع | **split 3 ways** |
| `person` | الأشخاص | الأشخاص | الأشخاص | **agree** |
| `organization` | المؤسسات | المؤسسات | المنظمات | **split 2 ways** |
| `contacts` | جهات الاتصال | الأشخاص | جهات الاتصال | **split 2 ways** |
| `quote` | العروض | عروض الأسعار | عروض الأسعار | **split 2 ways** |
| `product` | المنتجات | المنتجات | المنتجات | **agree** |
| `activity` | الأنشطة | الأنشطة | الأنشطة | **agree** |
| `warehouse` | المستودعات | المستودعات | المستودعات | **agree** |
| `tag` | العلامات | الوسوم | الوسوم | **split 2 ways** |
| `attribute` | الخصائص | السمات | السمات | **split 2 ways** |
| `role` | الأدوار | الأدوار | الأدوار | **agree** |
| `group` | المجموعات | المجموعات | المجموعات | **agree** |
| `user` | المستخدم | المستخدمون | المستخدمون | **split 2 ways** |
| `email_template` | قوالب البريد الإلكتروني | قوالب البريد الإلكتروني | قوالب البريد الإلكتروني | **agree** |
| `workflow` | سير العمل | سير العمل | سير العمل | **agree** |
| `campaign` | الحملات | الحملات | الحملات | **agree** |
| `event` | الفعاليات | الأحداث | حدث | **split 3 ways** |
| `webhook` | الويب هوك | الويب هوكس | ويب هوك | **split 3 ways** |
| `web_form` | نماذج الويب | نماذج الويب | نماذج الويب | **agree** |
| `data_transfer` | نقل البيانات | الاستيرادات | نقل البيانات | **split 2 ways** |
| `configuration` | الإعدادات | التكوين | التهيئة | **split 3 ways** |

Concepts with all three layers present: 23 — agreeing 11, split 2 ways 8, split 3 ways 4.


### Reading the table

- `pipeline` is the cleanest three-way split in the product: **الأنابيب** in the
  sidebar, **خطوط الأنابيب** on the screen, **قنوات البيع** in the ACL tree, and a
  fourth name — **قناة المبيعات** — seeded into the database.
- `configuration` collapses two different screens onto one sidebar word: the settings
  card page and the configuration tree both sit under **الإعدادات**, while the tree's
  own screen title is **التكوين** and its ACL label is **التهيئة**.
- `user` differs only by case ending (المستخدم vs المستخدمون) but ships as two static
  labels in two different files' worth of context.
- `contacts` has no screen title of its own; the table shows `الأشخاص` because that is
  the first `*.index.title` its key set contains. Treat that row as "no title layer".
- `event` names the same object **الفعاليات** in the sidebar, **الأحداث** on the
  screen and **حدث** in the ACL tree — three different words, not three inflections.
- `webhook` is the worst offender per string: **الويب هوك** (sidebar), **الويب هوكس**
  (screen title, with a Latin plural `s` welded onto the Arabic word), **ويب هوك**
  (ACL), plus bare `Webhook` and `webhook` in workflow actions.

---

## 5. Classes

Every concept in this inventory is marked with exactly one of two classes, and any
surface form may *additionally* be marked `suspect-literal`. The two axes are
independent: a string can be both, and often is.

| class | meaning | concepts |
| --- | --- | --- |
| `consistent` | the concept has exactly **one** name; all its surface forms are inflections of that one word | **31** |
| `conflicting` | the concept has **two or more** names — genuinely different Arabic words, not inflections | **24** |
| `suspect-literal` | a *string-level* flag, applied on top: a transliteration of the English term, or a word-for-word rendering whose Arabic sense does not match the English concept | **44 strings** |

`consistent` does **not** mean "correct". المؤسسة and المنظمة are two names, but
المخزون and الإعدادات are single names that may still be the wrong single name. This
inventory does not judge fitness; it only reports agreement.

### Both at once

**12 of the 24 `conflicting` concepts also carry `suspect-literal` strings**, and one
`consistent` concept does too:

| concept | conflicting because | and suspect-literal because |
| --- | --- | --- |
| `lead` | عميل محتمل / العملاء المحتملون / الفرصة / القيادة | القيادة is `leadership` |
| `pipeline` | قناة المبيعات / خط الأنابيب | both are calques — a sales pipeline is neither a channel nor a pipe |
| `pipeline_stage_won` | مُنتصر / ربحت / فاز / المكتسب | مُنتصر = `victorious`, المكتسب = `acquired`, ربحت / فاز are verb forms |
| `pipeline_stage_lost` | مفقود / خسرت / خسر / المفقود | مفقود = `a missing object`, خسرت / خسر are verb forms |
| `rotten` | التعفن / متعفن / الفاسد / مهمل | متعفن / الفاسد / التعفن all render `rotten` literally |
| `quote` | عرض السعر / عروض الأسعار / العروض / الاقتباسات | الاقتباسات = `citations` |
| `dashboard` | لوحة القيادة / لوحة التحكم (the latter in 5 keys; 2 more keys use it for the admin panel — §13 item 5) | لوحة القيادة is a calque of `dashboard` |
| `webhook` | الويب هوك / ويب هوك / Webhook | a transliteration of `webhook`; `الويب هوكس` welds a Latin `s` onto it |
| `email_template` | قالب البريد / نموذج البريد | نموذج = `form/model`, a word-for-word rendering of `template` |
| `mail_trash` | سلة المهملات / المهملات / المحذوفات | المحذوفات = `the deleted things` |
| `mail_sent` | المرسل / تم الإرسال | تم الإرسال is a past-tense sentence used as a folder name |
| `http_terminology` | nine strings for six terms | every one is a word-for-word technical rendering |
| `login` — `consistent`, not conflicting | — | بيانات الاعتماد = literal `credential data` for `credentials` |

The 12 `conflicting` concepts with **no** suspect-literal string are `address`, `attribute`,
`configuration`, `contact_person`, `data_transfer`, `event`, `mail_inbox`, `organization`,
`password_reset`, `sales_owner`, `tag` and `web_form`.
Those are genuine synonym disagreements — two valid Arabic words for one thing — rather
than bad translation, and a glossary has to choose between them without any of them
being defensibly "wrong".

*Recomputed 2026-11-04:* the first pass listed 13, counting `activity_type` and `email_settings`, neither
of which is a synonym disagreement at all (§13 items 1 and 3), and missing `contact_person`, which is
(§13 item 4) — so 13 − 2 + 1 = **12**, and 24 − 12 = 12 conflicting concepts do carry a suspect-literal
string.

---

## 6. The full suspect-literal list

**Criterion, applied string by string.** A form is suspect-literal when either

1. it is a **transliteration** of the English term (`ويب هوك` for `webhook`,
   `هوكس` for `hooks`), or
2. it is a **word-for-word rendering** of an English term whose Arabic sense is a
   different thing — a sales `pipeline` is not a pipe, a `lead` is not leadership, an
   HTTP `payload` is not cargo.

Deliberately **not** counted as suspect-literal, because the Arabic is idiomatic and
means what the English means: العميل المحتمل (`probable customer`), المؤسسة / المنظمة,
الوسم / العلامة, السمة / الخاصية, الفعالية / الحدث, التهيئة / التكوين,
مندوب المبيعات / مالك المبيعات, المرحلة, الاحتمالية, المخزون.

**44 forms:**

| suspect form | why | concept | keys |
| --- | --- | --- | --- |
| Webhook | left untranslated | `webhook` | `admin:settings.workflows.helpers.add-webhook` |
| webhook | left untranslated inside an Arabic sentence | `webhook` | `admin:settings.workflows.create.no-webhook-found`<br>`admin:settings.workflows.create.create-webhook` |
| أيام التعفن | `days of rottenness`; word-for-word for `rotten days` | `rotten` | `admin:settings.pipelines.index.datagrid.rotten-days`<br>`admin:settings.pipelines.create.rotten-days`<br>`admin:settings.pipelines.edit.rotten-days` |
| إرسال | `send`; used as the label for the HTTP verb POST — **decided 2026-11-04 → Latin `POST`** (glossary decision 8a) | `http_terminology` | `admin:settings.webhooks.create.post`<br>`admin:settings.webhooks.edit.post` |
| الأنابيب | word-for-word `the pipes` | `pipeline` | `admin:layouts.pipelines`<br>`admin:layouts.pipelines-info` |
| الاقتباس / اقتباس | `citation`; word-for-word for `quote` | `quote` | `admin:settings.data-transfer.validation.errors.wrong-quotes` |
| الاقتباسات | `citations`; used for `quote` | `quote` | `admin:components.activities.index.quotes`<br>`admin:leads.view.tabs.quotes` |
| التعفن | `rottenness` | `rotten` | `admin:leads.index.kanban.toolbar.card-settings.rotten-indicator` |
| الجسم | literal `body`; word-for-word for request `body` | `http_terminology` | `admin:settings.webhooks.create.body` |
| الرؤوس | literal `heads`; word-for-word for HTTP `headers` | `http_terminology` | `admin:settings.webhooks.create.headers` |
| الفاسد | `the spoiled/rotten one`; word-for-word for `rotten` | `rotten` | `admin:leads.index.kanban.rotten-days` |
| القيادة | `leadership`; word-for-word for `lead` | `lead` | `admin:activities.index.datagrid.lead`<br>`admin:activities.edit.lead` |
| المحذوفات | `the deleted things`; word-for-word for `trash` — **decided 2026-11-04 → `سلة المهملات`** (glossary §4.6) | `mail_trash` | `admin:acl.trash` |
| المسارات | `paths`; calque for `pipeline` | `pipeline` | `admin:leads.index.view-switcher.all-pipelines` |
| المفقود | `the missing object` | `pipeline_stage_lost` | `admin:dashboard.index.pdf.lost` |
| المفقودة | `the missing one`; word-for-word for `lost` | `pipeline_stage_lost` | `admin:dashboard.index.revenue.lost-revenue` |
| المفقودين | `the missing ones`; word-for-word for `lost` | `pipeline_stage_lost` | `admin:dashboard.index.total-leads.lost` |
| المكتسب | `acquired`; word-for-word for `won` | `pipeline_stage_won` | `admin:dashboard.index.pdf.won` |
| المكتسبة | `acquired`; word-for-word for `won` | `pipeline_stage_won` | `admin:dashboard.index.revenue.won-revenue` |
| المكتسبين | `the acquired ones`; word-for-word for `won` | `pipeline_stage_won` | `admin:dashboard.index.total-leads.won` |
| الويب هوك | transliteration of `webhook` + Arabic article | `webhook` | `admin:settings.webhooks.create.save-btn`<br>`admin:settings.webhooks.edit.title`<br>`admin:settings.webhooks.edit.edit-btn`<br>`admin:settings.webhooks.edit.save-btn`<br>`admin:layouts.webhooks`<br>`admin:layouts.webhooks-info` |
| الويب هوكس | transliteration + Latin plural `s` inside an Arabic word | `webhook` | `admin:settings.webhooks.index.title`<br>`admin:settings.webhooks.create.info`<br>`admin:settings.webhooks.edit.info` |
| بيانات الاعتماد | literal `credential data`; word-for-word for `credentials` | `login` | `admin:users.login-error` |
| تحديث | `update`; used as the label for the HTTP verb PUT — **decided 2026-11-04 → Latin `PUT`** (glossary decision 8a) | `http_terminology` | `admin:settings.webhooks.create.put`<br>`admin:settings.webhooks.edit.put` |
| تم الإرسال | a past-tense sentence used as a folder name | `mail_sent` | `admin:mail.index.sent` |
| حمولة | literal `cargo/load`; word-for-word for `payload` | `http_terminology` | `admin:settings.webhooks.create.add-new-payload` |
| خسر | `he lost`; verb form used as a stage name | `pipeline_stage_lost` | `admin:settings.pipelines.create.lost-stage`<br>`admin:settings.pipelines.edit.lost-stage` |
| خسرت | `you lost`; verb form used as a stage name | `pipeline_stage_lost` | `admin:leads.index.kanban.stages.lost`<br>`admin:leads.view.stages.lost` |
| خط أنابيب | word-for-word `a pipe` | `pipeline` | `admin:settings.pipelines.index.create-btn`<br>`admin:settings.pipelines.create.title` |
| خط الأنابيب | word-for-word `pipe`; a sales pipeline is not a pipe | `pipeline` | `admin:settings.webforms.create.pipeline`<br>`admin:settings.webforms.edit.pipeline`<br>`admin:settings.pipelines.create.save-btn`<br>`admin:settings.pipelines.edit.title`<br>`admin:settings.pipelines.edit.save-btn` |
| خطوط الأنابيب | word-for-word `pipes` | `pipeline` | `admin:settings.pipelines.index.title` |
| رأس | literal `head`; word-for-word for HTTP `header` | `http_terminology` | `admin:settings.webhooks.create.add-new-header` |
| ربحت | `you won`; verb form used as a stage name | `pipeline_stage_won` | `admin:leads.index.kanban.stages.won`<br>`admin:leads.view.stages.won` |
| فاز | `he won`; verb form used as a stage name | `pipeline_stage_won` | `admin:settings.pipelines.create.won-stage`<br>`admin:settings.pipelines.edit.won-stage` |
| قناة المبيعات | `sales channel`, a calque for pipeline | `pipeline` | `installer:seeders.attributes.leads.pipeline`<br>`installer:seeders.lead.pipeline.default` |
| قنوات البيع | `sales channels`, a calque for pipeline | `pipeline` | `admin:acl.pipelines` |
| لوحة القيادة | `leadership panel`; calque of `dashboard` | `dashboard` | `admin:configuration.index.general.settings.menu.dashboard`<br>`admin:dashboard.index.title`<br>`admin:layouts.dashboard` |
| متعفن | `rotting`; word-for-word for `rotten` | `rotten` | `admin:leads.index.datagrid.rotten-lead` |
| مفقود | `missing object`; word-for-word for `lost` | `pipeline_stage_lost` | `installer:seeders.lead.pipeline.pipeline-stages.lost` |
| مُنتصر | `victorious`; word-for-word for `won` | `pipeline_stage_won` | `installer:seeders.lead.pipeline.pipeline-stages.won` |
| نقطة النهاية | literal `end point`; word-for-word for `endpoint` — **decided 2026-11-04 → `عنوان URL`** (glossary decision 8b) | `http_terminology` | `admin:settings.webhooks.index.datagrid.end-point` |
| نقطة نهاية الرابط | literal `link end point` — **decided 2026-11-04 → `عنوان URL`** (glossary decision 8b) | `http_terminology` | `admin:settings.webhooks.create.url-endpoint`<br>`admin:settings.webhooks.edit.url-endpoint` |
| نموذج البريد الإلكتروني | `form/model`; word-for-word for `template` — **decided 2026-11-04 → `قالب البريد الإلكتروني`** (glossary §4.5) | `email_template` | `admin:settings.marketing.campaigns.index.create.email-template` |
| ويب هوك | transliteration of `webhook` | `webhook` | `admin:acl.webhook`<br>`admin:settings.webhooks.index.create-btn`<br>`admin:settings.webhooks.create.title` |

---

## 7. The concept table

One block per concept. `Class` on each row is the concept's class plus
`+ suspect-literal` when that particular surface form is flagged in section 6.
`keys` are the translation keys whose value renders that form in a naming slot; prose
occurrences are listed separately under `Prose-only`.

An Arabic form can appear under two concepts. `جهة الاتصال` is in `contacts` and in
`contact_person`; `النوع` is in `lead_type` and is reused by five other concepts. That
is intentional — the duplication *is* the finding, and section 9 names each case.

### `lead` — Lead / opportunity record

**Class:** `conflicting` · **names:** 5 · **surface forms:** 8 · **keys scanned:** 180

| surface form | class | keys that render it |
| --- | --- | --- |
| العملاء | `conflicting` | `admin:leads.index.kanban.empty-list` |
| العملاء المحتملون | `conflicting` | `admin:acl.leads`<br>`admin:settings.webforms.create.leads`<br>`admin:settings.webforms.edit.leads`<br>`admin:settings.data-transfer.importers.leads.title`<br>`admin:leads.index.title` |
| العملاء المحتملين | `conflicting` | `admin:components.layouts.header.mega-search.tabs.leads`<br>`admin:settings.attributes.index.datagrid.entity-types.leads`<br>`admin:configuration.index.general.settings.menu.leads`<br>`admin:layouts.leads`<br>`admin:layouts.lead-info` |
| العميل | `conflicting` | `admin:leads.index.kanban.rotten-days` |
| العميل المحتمل | `conflicting` | `admin:notifications.titles.lead-updated`<br>`admin:mail.view.lead-details`<br>`admin:leads.edit.title` |
| الفرصة | `conflicting` | `admin:leads.view.title`<br>`admin:leads.view.attributes.title` |
| القيادة | `conflicting + suspect-literal` | `admin:activities.index.datagrid.lead`<br>`admin:activities.edit.lead` |
| عميل محتمل | `conflicting` | `admin:acl.lead`<br>`admin:notifications.titles.lead-created`<br>`admin:leads.index.create-btn`<br>`admin:leads.index.datagrid.rotten-lead`<br>`admin:leads.index.upload.create-lead`<br>`admin:leads.create.title`<br>`admin:layouts.lead`<br>`installer:seeders.lead.pipeline.pipeline-stages.prospect` |

Also used in 18 of 18 client-guide chapters.

### `lead_value` — Lead value (deal amount)

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 5

| surface form | class | keys that render it |
| --- | --- | --- |
| القيمة التقديرية للعميل المحتمل | `consistent` | `admin:leads.index.datagrid.lead-value`<br>`admin:leads.index.kanban.columns.lead-value`<br>`admin:leads.index.kanban.toolbar.card-settings.estimated-lead-value`<br>`installer:seeders.attributes.leads.lead-value` |

Also used in 3 of 18 client-guide chapters.

### `lead_source` — Lead source

**Class:** `consistent` · **names:** 1 · **surface forms:** 3 · **keys scanned:** 33

| surface form | class | keys that render it |
| --- | --- | --- |
| المصادر | `consistent` | `admin:acl.sources`<br>`admin:settings.sources.index.title`<br>`admin:dashboard.index.pdf.revenue-by-sources`<br>`admin:dashboard.index.revenue-by-sources.title`<br>`admin:layouts.sources`<br>`admin:layouts.sources-info` |
| المصدر | `consistent` | `admin:settings.sources.index.create.save-btn`<br>`admin:settings.sources.index.edit.title`<br>`admin:leads.index.datagrid.source`<br>`admin:leads.index.kanban.columns.source`<br>`admin:leads.index.kanban.toolbar.card-settings.source`<br>`admin:dashboard.index.pdf.source`<br>`installer:seeders.attributes.leads.source`<br>`installer:seeders.attributes.leads.lead-source-id` |
| مصدر | `consistent` | `admin:settings.sources.index.create-btn`<br>`admin:settings.sources.index.create.title` |

Also used in 17 of 18 client-guide chapters.

### `lead_type` — Lead type

**Class:** `consistent` · **names:** 1 · **surface forms:** 4 · **keys scanned:** 23

| surface form | class | keys that render it |
| --- | --- | --- |
| الأنواع | `consistent` | `admin:acl.types`<br>`admin:layouts.types`<br>`admin:layouts.types-info` |
| النوع | `consistent` | `admin:settings.workflows.create.type`<br>`admin:settings.workflows.edit.type`<br>`admin:settings.marketing.campaigns.index.create.type`<br>`admin:settings.attributes.index.datagrid.type`<br>`admin:settings.attributes.create.type`<br>`admin:settings.attributes.edit.type`<br>`admin:settings.data-transfer.imports.create.type`<br>`admin:settings.data-transfer.imports.edit.type`<br>… +6 more |
| نوع | `consistent` | `admin:activities.edit.type` |
| نوع العميل المحتمل | `consistent` | `admin:leads.index.datagrid.lead-type`<br>`admin:leads.index.kanban.columns.lead-type`<br>`admin:leads.index.kanban.toolbar.card-settings.lead-type` |

Also used in 12 of 18 client-guide chapters.

### `pipeline` — Pipeline

**Class:** `conflicting` · **names:** 2 · **surface forms:** 6 · **keys scanned:** 64

| surface form | class | keys that render it |
| --- | --- | --- |
| الأنابيب | `conflicting + suspect-literal` | `admin:layouts.pipelines`<br>`admin:layouts.pipelines-info` |
| خط أنابيب | `conflicting + suspect-literal` | `admin:settings.pipelines.index.create-btn`<br>`admin:settings.pipelines.create.title` |
| خط الأنابيب | `conflicting + suspect-literal` | `admin:settings.webforms.create.pipeline`<br>`admin:settings.webforms.edit.pipeline`<br>`admin:settings.pipelines.create.save-btn`<br>`admin:settings.pipelines.edit.title`<br>`admin:settings.pipelines.edit.save-btn` |
| خطوط الأنابيب | `conflicting + suspect-literal` | `admin:settings.pipelines.index.title` |
| قناة المبيعات | `conflicting + suspect-literal` | `installer:seeders.attributes.leads.pipeline`<br>`installer:seeders.lead.pipeline.default` |
| قنوات البيع | `conflicting + suspect-literal` | `admin:acl.pipelines` |

Also used in 12 of 18 client-guide chapters.

### `pipeline_stage` — Pipeline stage

**Class:** `consistent` · **names:** 1 · **surface forms:** 3 · **keys scanned:** 19

| surface form | class | keys that render it |
| --- | --- | --- |
| المراحل | `consistent` | `admin:settings.pipelines.create.stages`<br>`admin:settings.pipelines.edit.stages` |
| المرحلة | `consistent` | `admin:settings.pipelines.create.delete-stage`<br>`admin:settings.pipelines.create.stage-delete-success`<br>`admin:settings.pipelines.edit.delete-stage`<br>`admin:settings.pipelines.edit.stage-delete-success`<br>`admin:leads.index.datagrid.stage`<br>`admin:dashboard.index.pdf.stage`<br>`installer:seeders.attributes.leads.stage` |
| مرحلة | `consistent` | `admin:settings.pipelines.create.stage-btn`<br>`admin:settings.pipelines.edit.stage-btn` |

Also used in 14 of 18 client-guide chapters.

### `pipeline_stage_won` — Pipeline stage: won

**Class:** `conflicting` · **names:** 3 · **surface forms:** 6 · **keys scanned:** 10

| surface form | class | keys that render it |
| --- | --- | --- |
| المكتسب | `conflicting + suspect-literal` | `admin:dashboard.index.pdf.won` |
| المكتسبة | `conflicting + suspect-literal` | `admin:dashboard.index.revenue.won-revenue` |
| المكتسبين | `conflicting + suspect-literal` | `admin:dashboard.index.total-leads.won` |
| ربحت | `conflicting + suspect-literal` | `admin:leads.index.kanban.stages.won`<br>`admin:leads.view.stages.won` |
| فاز | `conflicting + suspect-literal` | `admin:settings.pipelines.create.won-stage`<br>`admin:settings.pipelines.edit.won-stage` |
| مُنتصر | `conflicting + suspect-literal` | `installer:seeders.lead.pipeline.pipeline-stages.won` |

Also used in 8 of 18 client-guide chapters.

### `pipeline_stage_lost` — Pipeline stage: lost

**Class:** `conflicting` · **names:** 2 · **surface forms:** 6 · **keys scanned:** 10

| surface form | class | keys that render it |
| --- | --- | --- |
| المفقود | `conflicting + suspect-literal` | `admin:dashboard.index.pdf.lost` |
| المفقودة | `conflicting + suspect-literal` | `admin:dashboard.index.revenue.lost-revenue` |
| المفقودين | `conflicting + suspect-literal` | `admin:dashboard.index.total-leads.lost` |
| خسر | `conflicting + suspect-literal` | `admin:settings.pipelines.create.lost-stage`<br>`admin:settings.pipelines.edit.lost-stage` |
| خسرت | `conflicting + suspect-literal` | `admin:leads.index.kanban.stages.lost`<br>`admin:leads.view.stages.lost` |
| مفقود | `conflicting + suspect-literal` | `installer:seeders.lead.pipeline.pipeline-stages.lost` |

Also used in 9 of 18 client-guide chapters.

### `rotten` — Rotten / stale-lead indicator

**Class:** `conflicting` · **names:** 3 · **surface forms:** 5 · **keys scanned:** 8

| surface form | class | keys that render it |
| --- | --- | --- |
| أيام التعفن | `conflicting + suspect-literal` | `admin:settings.pipelines.index.datagrid.rotten-days`<br>`admin:settings.pipelines.create.rotten-days`<br>`admin:settings.pipelines.edit.rotten-days` |
| التعفن | `conflicting + suspect-literal` | `admin:leads.index.kanban.toolbar.card-settings.rotten-indicator` |
| الفاسد | `conflicting + suspect-literal` | `admin:leads.index.kanban.rotten-days` |
| متعفن | `conflicting + suspect-literal` | `admin:leads.index.datagrid.rotten-lead` |
| مهمل | `conflicting` | `admin:mail.view.rotten-days` |

Also used in 5 of 18 client-guide chapters.

### `probability` — Stage probability

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 2

| surface form | class | keys that render it |
| --- | --- | --- |
| الاحتمالية | `consistent` | `admin:settings.pipelines.create.probability`<br>`admin:settings.pipelines.edit.probability` |

Also used in 3 of 18 client-guide chapters.

### `person` — Person (contact record)

**Class:** `consistent` · **names:** 1 · **surface forms:** 3 · **keys scanned:** 112

| surface form | class | keys that render it |
| --- | --- | --- |
| الأشخاص | `consistent` | `admin:acl.persons`<br>`admin:components.activities.actions.activity.participants.persons`<br>`admin:components.layouts.header.mega-search.tabs.persons`<br>`admin:contacts.persons.index.title`<br>`admin:contacts.organizations.index.datagrid.persons-count`<br>`admin:settings.attributes.index.datagrid.entity-types.persons`<br>`admin:settings.data-transfer.importers.persons.title`<br>`admin:activities.edit.persons`<br>… +3 more |
| الشخص | `consistent` | `admin:quotes.index.datagrid.person`<br>`admin:contacts.persons.view.about-person`<br>`admin:contacts.persons.create.save-btn`<br>`admin:contacts.persons.edit.title`<br>`admin:contacts.persons.edit.save-btn`<br>`admin:dashboard.index.pdf.person` |
| شخص | `consistent` | `admin:quotes.index.pdf.person`<br>`admin:contacts.persons.index.create-btn`<br>`admin:contacts.persons.create.title`<br>`admin:settings.webforms.create.person`<br>`admin:settings.webforms.edit.person`<br>`admin:layouts.person`<br>`installer:seeders.attributes.quotes.person`<br>`installer:seeders.attributes.quotes.person-id` |

Prose-only, never a label: «أشخاص»

Also used in 17 of 18 client-guide chapters.

### `organization` — Organization

**Class:** `conflicting` · **names:** 2 · **surface forms:** 5 · **keys scanned:** 29

| surface form | class | keys that render it |
| --- | --- | --- |
| المؤسسات | `conflicting` | `admin:contacts.organizations.index.title`<br>`admin:configuration.index.general.settings.menu.organizations`<br>`admin:layouts.organizations` |
| المؤسسة | `conflicting` | `admin:contacts.persons.index.datagrid.organization-name`<br>`admin:contacts.persons.view.about-organization`<br>`admin:contacts.organizations.create.save-btn`<br>`admin:contacts.organizations.edit.title`<br>`admin:contacts.organizations.edit.save-btn` |
| المنظمات | `conflicting` | `admin:acl.organizations`<br>`admin:settings.attributes.index.datagrid.entity-types.organizations` |
| المنظمة | `conflicting` | `admin:leads.common.contact.organization`<br>`installer:seeders.attributes.persons.organization`<br>`installer:seeders.attributes.persons.organization-id` |
| مؤسسة | `conflicting` | `admin:contacts.organizations.index.create-btn`<br>`admin:contacts.organizations.create.title`<br>`admin:layouts.organization` |

Also used in 10 of 18 client-guide chapters.

### `contacts` — Contacts (persons + organizations)

**Class:** `consistent` · **names:** 1 · **surface forms:** 2 · **keys scanned:** 108

| surface form | class | keys that render it |
| --- | --- | --- |
| جهات اتصال | `consistent` | `admin:settings.google-contacts.index.title`<br>`admin:export.google-contacts` |
| جهات الاتصال | `consistent` | `admin:acl.contacts`<br>`admin:configuration.index.general.settings.menu.contacts`<br>`admin:layouts.contacts` |

Also used in 13 of 18 client-guide chapters.

### `contact_person` — Contact person (linked person on a record)

**Class:** `conflicting` · **names:** 2 · **surface forms:** 3 · **keys scanned:** 11

**Corrected 2026-11-04 (§13 item 4):** this concept was filed `consistent` on the strength of one name
("شخص الاتصال / جهة الاتصال") written with a slash. It is **not** one name. Measured with
`grep -o -F` over `A`: **`شخص الاتصال` 7 occurrences on 7 keys** and **`جهة الاتصال` 5 occurrences on
5 keys** — two different lexemes (`شخص` = person, `جهة` = party/body), not two inflections of one word,
and the glossary **overturns** `شخص الاتصال` in favour of `جهة الاتصال` (its §4.2 row). A concept whose
wrong name the glossary removes cannot be `consistent`; that class was hiding a rename.

| surface form | class | keys that render it |
| --- | --- | --- |
| جهة اتصال | `conflicting` | `admin:mail.view.link-to-contact` |
| جهة الاتصال | `conflicting` | `admin:settings.warehouses.index.datagrid.contact-name`<br>`admin:mail.view.linked-contact`<br>`admin:notifications.titles.person-updated`<br>`admin:mail.view.save-contact`<br>`admin:leads.view.persons.change-title` |
| شخص الاتصال | `conflicting` | `admin:leads.index.datagrid.contact-person`<br>`admin:leads.index.kanban.columns.contact-person`<br>`admin:leads.index.kanban.toolbar.card-settings.contact-person`<br>`admin:leads.create.contact-person`<br>`admin:leads.edit.contact-person` |

Note `شخص الاتصال` also ships inside two prose sentences (`leads.create.contact-info` and
`leads.edit.contact-info`, both `معلومات عن شخص الاتصال`), which do not vote as names but do change with
the rename; and `جهة الاتصال` is shared with the `contacts` concept (section 9) — the two key sets are in
`mail`/`warehouses`/`notifications` versus `acl.contacts`, so a rename has to stay key-scoped.

Also used in 9 of 18 client-guide chapters.

### `quote` — Quote

**Class:** `conflicting` · **names:** 5 · **surface forms:** 7 · **keys scanned:** 143

| surface form | class | keys that render it |
| --- | --- | --- |
| الاقتباسات | `conflicting + suspect-literal` | `admin:components.activities.index.quotes`<br>`admin:leads.view.tabs.quotes` |
| العروض | `conflicting` | `admin:configuration.index.general.settings.menu.quotes`<br>`admin:layouts.quotes` |
| عرض | `conflicting` | `admin:leads.view.quotes.add-btn`<br>`admin:layouts.quote` |
| عرض السعر | `conflicting` | `admin:quotes.index.pdf.quote-id`<br>`admin:quotes.index.pdf.title`<br>`admin:quotes.create.save-btn`<br>`admin:quotes.create.quote-info`<br>`admin:quotes.create.quote-items`<br>`admin:quotes.create.quote-name`<br>`admin:quotes.edit.title`<br>`admin:quotes.edit.save-btn`<br>… +4 more |
| عرض سعر | `conflicting` | `admin:quotes.index.create-btn`<br>`admin:quotes.create.title`<br>`admin:notifications.titles.quote-created`<br>`admin:leads.view.quotes.mail-subject` |
| عروض | `conflicting` | `admin:leads.view.quotes.empty-title` |
| عروض الأسعار | `conflicting` | `admin:acl.quotes`<br>`admin:components.layouts.header.mega-search.tabs.quotes`<br>`admin:quotes.index.title`<br>`admin:contacts.persons.view.activities.index.quotes`<br>`admin:settings.attributes.index.datagrid.entity-types.quotes` |

Also used in 18 of 18 client-guide chapters.

### `product` — Product

**Class:** `consistent` · **names:** 1 · **surface forms:** 4 · **keys scanned:** 84

| surface form | class | keys that render it |
| --- | --- | --- |
| المنتج | `consistent` | `admin:quotes.index.pdf.product-name`<br>`admin:quotes.create.product-name`<br>`admin:quotes.edit.product-name`<br>`admin:products.index.datagrid.sku`<br>`admin:products.view.sku`<br>`admin:products.view.attributes.about-product`<br>`admin:notifications.titles.product-updated`<br>`admin:mail.view.product`<br>… +3 more |
| المنتجات | `consistent` | `admin:acl.products`<br>`admin:components.layouts.header.mega-search.tabs.products`<br>`admin:products.index.title`<br>`admin:products.create.save-btn`<br>`admin:products.edit.title`<br>`admin:products.edit.save-btn`<br>`admin:settings.warehouses.index.datagrid.products`<br>`admin:settings.attributes.index.datagrid.entity-types.products`<br>… +8 more |
| منتج | `consistent` | `admin:products.index.create-btn`<br>`admin:notifications.titles.product-created`<br>`admin:leads.view.products.add-product`<br>`admin:layouts.product` |
| منتجات | `consistent` | `admin:products.create.title`<br>`admin:leads.view.products.empty-title` |

Also used in 18 of 18 client-guide chapters.

### `activity` — Activity

**Class:** `consistent` · **names:** 1 · **surface forms:** 4 · **keys scanned:** 177

| surface form | class | keys that render it |
| --- | --- | --- |
| أنشطة | `consistent` | `admin:components.activities.index.empty-placeholders.all.title`<br>`admin:components.activities.index.empty-placeholders.planned.title` |
| الأنشطة | `consistent` | `admin:acl.activities`<br>`admin:activities.index.title`<br>`admin:configuration.index.general.settings.menu.activities`<br>`admin:layouts.activities` |
| النشاط | `consistent` | `admin:components.activities.actions.activity.save-btn`<br>`admin:contacts.persons.view.activities.actions.activity.save-btn`<br>`admin:activities.edit.title`<br>`admin:activities.edit.save-btn`<br>`admin:activities.edit.type` |
| نشاط | `consistent` | `admin:components.activities.actions.activity.btn`<br>`admin:components.activities.actions.activity.title`<br>`admin:contacts.persons.view.activities.actions.activity.btn`<br>`admin:contacts.persons.view.activities.actions.activity.title` |

Also used in 13 of 18 client-guide chapters.

### `activity_type` — Activity type (call / meeting / lunch)

**Class:** `consistent` · **names:** 1 per type (three types) · **surface forms:** 7 · **keys scanned:** 20

**Corrected 2026-11-04 (§13 item 1):** this concept was filed `conflicting` with **3 names**, and that
was wrong. `مكالمة` (call), `اجتماع` (meeting) and `غداء` (lunch) are **three different types**, not three
names for one concept: each is a legitimate value of the `type` field, each ships in its own key set
(`….actions.activity.call` vs `.meeting` vs `…index.calls` vs `.meetings` vs `.lunches`), and the glossary
**approves all three singulars as shipped**. Counting values as rival names invented a conflict. The one
real defect on this screen is a **grammar** defect, not a naming one — the broken plural
`الغداءات` / `غداءات`, measured at **3 occurrences in 2 keys**
(`components.activities.index.empty-placeholders.lunches.title` and `.description`), which needs
`أنشطة غداء` instead. The class therefore moves to `consistent` and the plural is recorded separately
(section 11.8) — **the glossary has no row for it yet**, so it is a naming fix with no decision behind it,
not one of the four defects the operator approved.

| surface form | class | keys that render it |
| --- | --- | --- |
| اجتماع | `consistent` | `admin:components.activities.actions.activity.meeting`<br>`admin:contacts.persons.view.activities.actions.activity.meeting`<br>`admin:activities.index.datagrid.meeting`<br>`admin:activities.edit.meeting` |
| اجتماعات | `consistent` | `admin:components.activities.index.empty-placeholders.meetings.title` |
| الاجتماعات | `consistent` | `admin:components.activities.index.meetings`<br>`admin:contacts.persons.view.activities.index.meetings` |
| الغداء | `consistent` | `admin:components.activities.index.lunches`<br>`admin:contacts.persons.view.activities.index.lunches` |
| المكالمات | `consistent` | `admin:components.activities.index.calls`<br>`admin:contacts.persons.view.activities.index.calls` |
| مكالمات | `consistent` | `admin:components.activities.index.empty-placeholders.calls.title` |
| مكالمة | `consistent` | `admin:components.activities.actions.activity.call`<br>`admin:contacts.persons.view.activities.actions.activity.call`<br>`admin:activities.index.datagrid.call`<br>`admin:activities.edit.call` |

Not names, and not clean: «غداءات» / «الغداءات» — a broken plural inside 2 prose keys
(`…empty-placeholders.lunches.title` / `.description`), and the glossaries count it as a string-level
**grammar** defect (§11.8), never as a fourth name.

Also used in 11 of 18 client-guide chapters.

### `warehouse` — Warehouse

**Class:** `consistent` · **names:** 1 · **surface forms:** 3 · **keys scanned:** 50

| surface form | class | keys that render it |
| --- | --- | --- |
| المستودع | `consistent` | `admin:products.view.inventory.warehouse`<br>`admin:settings.warehouses.create.save-btn`<br>`admin:settings.warehouses.edit.title`<br>`admin:settings.warehouses.edit.save-btn` |
| المستودعات | `consistent` | `admin:acl.warehouses`<br>`admin:settings.warehouses.index.title`<br>`admin:settings.attributes.index.datagrid.entity-types.warehouses`<br>`admin:layouts.warehouses`<br>`admin:layouts.warehouses-info` |
| مستودع | `consistent` | `admin:settings.warehouses.index.create-btn`<br>`admin:settings.warehouses.create.title`<br>`admin:layouts.warehouse` |

Also used in 4 of 18 client-guide chapters.

### `inventory` — Inventory / stock

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 17

| surface form | class | keys that render it |
| --- | --- | --- |
| المخزون | `consistent` | `admin:acl.inventory`<br>`admin:products.index.datagrid.in-stock`<br>`admin:products.view.inventories`<br>`admin:products.view.inventory.in-stock`<br>`admin:layouts.inventory`<br>`admin:layouts.inventory-info` |

Also used in 8 of 18 client-guide chapters.

### `tag` — Tag

**Class:** `conflicting` · **names:** 2 · **surface forms:** 6 · **keys scanned:** 44

| surface form | class | keys that render it |
| --- | --- | --- |
| العلامات | `conflicting` | `admin:mail.index.datagrid.tags`<br>`admin:leads.index.kanban.columns.tags`<br>`admin:leads.index.kanban.toolbar.card-settings.tags`<br>`admin:layouts.tags`<br>`admin:layouts.tags-info` |
| العلامة | `conflicting` | `admin:leads.index.datagrid.tag-name` |
| الوسم | `conflicting` | `admin:components.tags.index.save-btn`<br>`admin:contacts.persons.index.datagrid.tag-name`<br>`admin:products.index.datagrid.tag-name`<br>`admin:settings.tags.index.create.save-btn`<br>`admin:settings.tags.index.edit.title` |
| الوسوم | `conflicting` | `admin:acl.tags`<br>`admin:components.tags.index.title`<br>`admin:components.tags.index.added-tags`<br>`admin:components.tags.index.placeholder`<br>`admin:settings.tags.index.title` |
| علامة | `conflicting` | `admin:settings.workflows.helpers.add-tag` |
| وسم | `conflicting` | `admin:settings.tags.index.create-btn`<br>`admin:settings.tags.index.create.title` |

Also used in 13 of 18 client-guide chapters.

### `attribute` — Attribute (custom field)

**Class:** `conflicting` · **names:** 2 · **surface forms:** 6 · **keys scanned:** 196

| surface form | class | keys that render it |
| --- | --- | --- |
| الخاصية | `conflicting` | `admin:settings.attributes.edit.option-deleted` |
| الخصائص | `conflicting` | `admin:layouts.attributes`<br>`admin:layouts.attributes-info` |
| السمات | `conflicting` | `admin:acl.attributes`<br>`admin:settings.webforms.create.attributes`<br>`admin:settings.webforms.edit.attributes`<br>`admin:settings.attributes.index.title` |
| السمة | `conflicting` | `admin:settings.webforms.create.attribute-label-color`<br>`admin:settings.webforms.edit.attribute-label-color`<br>`admin:settings.attributes.create.save-btn`<br>`admin:settings.attributes.create.add-attribute-options`<br>`admin:settings.attributes.edit.add-attribute-options`<br>`admin:settings.attributes.edit.save-btn`<br>`admin:settings.attributes.edit.title` |
| خاصية | `conflicting` | `admin:layouts.attribute` |
| سمة | `conflicting` | `admin:settings.webforms.create.add-attribute-btn`<br>`admin:settings.webforms.edit.add-attribute-btn`<br>`admin:settings.attributes.index.create-btn`<br>`admin:settings.attributes.create.title` |

Prose-only, never a label: «سمات»

Also used in 8 of 18 client-guide chapters.

### `role` — Role

**Class:** `consistent` · **names:** 1 · **surface forms:** 3 · **keys scanned:** 49

| surface form | class | keys that render it |
| --- | --- | --- |
| الأدوار | `consistent` | `admin:acl.roles`<br>`admin:settings.roles.index.create-btn`<br>`admin:settings.roles.index.title`<br>`admin:layouts.roles`<br>`admin:layouts.roles-info` |
| الدور | `consistent` | `admin:settings.roles.create.save-btn`<br>`admin:settings.roles.edit.save-btn`<br>`admin:settings.roles.edit.title`<br>`admin:settings.users.index.create.role` |
| دور | `consistent` | `admin:settings.roles.create.title`<br>`admin:layouts.role`<br>`installer:seeders.user.role.administrator-role` |

Also used in 10 of 18 client-guide chapters.

### `group` — Group

**Class:** `consistent` · **names:** 1 · **surface forms:** 3 · **keys scanned:** 22

| surface form | class | keys that render it |
| --- | --- | --- |
| المجموعات | `consistent` | `admin:acl.groups`<br>`admin:settings.groups.index.title`<br>`admin:layouts.groups`<br>`admin:layouts.groups-info` |
| المجموعة | `consistent` | `admin:settings.groups.index.edit.title`<br>`admin:settings.groups.index.create.save-btn`<br>`admin:settings.users.index.datagrid.group` |
| مجموعة | `consistent` | `admin:settings.groups.index.create-btn`<br>`admin:settings.groups.index.create.title`<br>`admin:settings.users.index.create.group` |

Also used in 6 of 18 client-guide chapters.

### `user` — User

**Class:** `consistent` · **names:** 1 · **surface forms:** 4 · **keys scanned:** 103

| surface form | class | keys that render it |
| --- | --- | --- |
| المستخدم | `consistent` | `admin:acl.user`<br>`admin:settings.users.index.create.save-btn`<br>`admin:settings.users.index.edit.title`<br>`admin:help.index.resources.user-docs.title`<br>`admin:layouts.user` |
| المستخدمون | `consistent` | `admin:acl.users`<br>`admin:components.activities.actions.activity.participants.users`<br>`admin:settings.tags.index.datagrid.users`<br>`admin:settings.users.index.title`<br>`admin:settings.users.index.datagrid.users`<br>`admin:activities.edit.users` |
| المستخدمين | `consistent` | `admin:layouts.users`<br>`admin:layouts.users-info` |
| مستخدم | `consistent` | `admin:settings.users.index.create-btn`<br>`admin:settings.users.index.create.title` |

Also used in 16 of 18 client-guide chapters.

### `sales_owner` — Sales owner

**Class:** `conflicting` · **names:** 3 · **surface forms:** 3 · **keys scanned:** 14

| surface form | class | keys that render it |
| --- | --- | --- |
| مالك المبيعات | `conflicting` | `installer:seeders.attributes.leads.sales-owner`<br>`installer:seeders.attributes.leads.user-id`<br>`installer:seeders.attributes.persons.sales-owner`<br>`installer:seeders.attributes.persons.user-id`<br>`installer:seeders.attributes.organizations.sales-owner`<br>`installer:seeders.attributes.organizations.user-id`<br>`installer:seeders.attributes.quotes.sales-owner`<br>`installer:seeders.attributes.quotes.user-id` |
| مسؤول المبيعات | `conflicting` | `admin:settings.workflows.helpers.send-email-to-sales-owner` |
| مندوب المبيعات | `conflicting` | `admin:quotes.index.datagrid.sales-person`<br>`admin:quotes.index.pdf.sales-person`<br>`admin:leads.index.datagrid.sales-person`<br>`admin:leads.index.kanban.columns.sales-person`<br>`admin:leads.index.kanban.toolbar.card-settings.sales-person` |

Also used in 8 of 18 client-guide chapters.

### `email_template` — Email template

**Class:** `conflicting` · **names:** 2 · **surface forms:** 3 · **keys scanned:** 31

| surface form | class | keys that render it |
| --- | --- | --- |
| قالب البريد الإلكتروني | `conflicting` | `admin:settings.email-template.create.save-btn`<br>`admin:settings.email-template.create.email-template`<br>`admin:settings.email-template.edit.title`<br>`admin:settings.email-template.edit.save-btn`<br>`admin:settings.email-template.edit.email-template` |
| قوالب البريد الإلكتروني | `conflicting` | `admin:acl.email-templates`<br>`admin:settings.email-template.index.title`<br>`admin:layouts.email-templates`<br>`admin:layouts.email-templates-info` |
| نموذج البريد الإلكتروني | `conflicting + suspect-literal` | `admin:settings.marketing.campaigns.index.create.email-template` |

Also used in 3 of 18 client-guide chapters.

### `email_settings` — Email / IMAP-SMTP settings

**Class:** `consistent` · **names:** 1 · **surface forms:** 4 · **keys scanned:** 12

**Corrected 2026-11-04 (§13 item 3):** this concept was filed `conflicting` with 2 names, treating
`إعدادات البريد الإلكتروني` and the `IMAP` family as rivals. They are not rivals: `إعدادات البريد
الإلكتروني` is the **parent section title** (`configuration.index.email.title`) and `إعدادات IMAP` /
`حساب IMAP` are its **subsection titles** two levels down (`…email.imap.title`,
`…email.imap.account.title`), with `IMAP` alone appearing only inside field labels (`اسم مستخدم IMAP`,
`كلمة مرور IMAP`). One section name, several subsection names, in a deliberate hierarchy — no conflict,
and no rename owed. The concept joins the 31 that agree.

| surface form | class | keys that render it |
| --- | --- | --- |
| IMAP | `consistent` (subsection, not a rival) | `admin:configuration.index.email.imap.account.username`<br>`admin:configuration.index.email.imap.account.password` |
| إعدادات IMAP | `consistent` (subsection, not a rival) | `admin:configuration.index.email.imap.title` |
| إعدادات البريد الإلكتروني | `consistent` (the one section name) | `admin:configuration.index.email.title` |
| حساب IMAP | `consistent` (subsection, not a rival) | `admin:configuration.index.email.imap.account.title` |

Also used in 4 of 18 client-guide chapters.

### `workflow` — Workflow

**Class:** `consistent` · **names:** 1 · **surface forms:** 2 · **keys scanned:** 92

| surface form | class | keys that render it |
| --- | --- | --- |
| سير العمل | `consistent` | `admin:acl.workflows`<br>`admin:settings.workflows.index.title`<br>`admin:settings.workflows.create.save-btn`<br>`admin:settings.workflows.edit.title`<br>`admin:settings.workflows.edit.save-btn`<br>`admin:layouts.workflows`<br>`admin:layouts.workflows-info` |
| سير عمل | `consistent` | `admin:settings.workflows.index.create-btn`<br>`admin:settings.workflows.create.title` |

Also used in 4 of 18 client-guide chapters.

### `automation` — Automation

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 2

| surface form | class | keys that render it |
| --- | --- | --- |
| الأتمتة | `consistent` | `admin:acl.automation`<br>`admin:layouts.automation` |

Also used in 9 of 18 client-guide chapters.

### `campaign` — Campaign

**Class:** `consistent` · **names:** 1 · **surface forms:** 3 · **keys scanned:** 26

| surface form | class | keys that render it |
| --- | --- | --- |
| الحملات | `consistent` | `admin:acl.campaigns`<br>`admin:settings.marketing.campaigns.index.title`<br>`admin:layouts.campaigns`<br>`admin:layouts.campaigns-info` |
| الحملة | `consistent` | `admin:settings.marketing.campaigns.index.edit.title` |
| حملة | `consistent` | `admin:settings.marketing.campaigns.index.create-btn`<br>`admin:settings.marketing.campaigns.index.create.title` |

Also used in 7 of 18 client-guide chapters.

### `event` — Event

**Class:** `conflicting` · **names:** 2 · **surface forms:** 4 · **keys scanned:** 28

| surface form | class | keys that render it |
| --- | --- | --- |
| الأحداث | `conflicting` | `admin:settings.marketing.events.index.title`<br>`admin:settings.marketing.events.index.mass-delete-success` |
| الحدث | `conflicting` | `admin:settings.workflows.create.event`<br>`admin:settings.workflows.edit.event`<br>`admin:settings.marketing.events.index.create.save-btn`<br>`admin:settings.marketing.events.index.edit.title`<br>`admin:settings.marketing.campaigns.index.create.event` |
| الفعاليات | `conflicting` | `admin:layouts.events`<br>`admin:layouts.events-info` |
| حدث | `conflicting` | `admin:acl.event`<br>`admin:settings.marketing.events.index.create-btn`<br>`admin:settings.marketing.events.index.create.title` |

Also used in 13 of 18 client-guide chapters.

### `webhook` — Webhook

**Class:** `conflicting` · **names:** 3 · **surface forms:** 4 · **keys scanned:** 68

| surface form | class | keys that render it |
| --- | --- | --- |
| Webhook | `conflicting + suspect-literal` | `admin:settings.workflows.helpers.add-webhook` |
| الويب هوك | `conflicting + suspect-literal` | `admin:settings.webhooks.create.save-btn`<br>`admin:settings.webhooks.edit.title`<br>`admin:settings.webhooks.edit.edit-btn`<br>`admin:settings.webhooks.edit.save-btn`<br>`admin:layouts.webhooks`<br>`admin:layouts.webhooks-info` |
| الويب هوكس | `conflicting + suspect-literal` | `admin:settings.webhooks.index.title`<br>`admin:settings.webhooks.create.info`<br>`admin:settings.webhooks.edit.info` |
| ويب هوك | `conflicting + suspect-literal` | `admin:acl.webhook`<br>`admin:settings.webhooks.index.create-btn`<br>`admin:settings.webhooks.create.title` |

Prose-only, never a label: «webhook»

Also used in 5 of 18 client-guide chapters.

### `web_form` — Web form

**Class:** `conflicting` · **names:** 2 · **surface forms:** 3 · **keys scanned:** 78

> **Protected — zero edits.** The glossary's operator decision 7 (2026-11-04) keeps every one of these
> keys as shipped: `نموذج الويب` is a definite label, `نماذج الويب` is the plural a list screen needs, and
> `نموذج ويب` is the seeded lead-source *value* (`installer:seeders.lead.source.web-form`), which is a
> list value rather than a label. The inventory still reports the conflict because reporting is its job;
> **the glossary decides, and here it decides to leave the concept alone.** Nothing under
> `settings.webforms.*`, `webform:*` or the web-form ACL changes in the apply lane.

| surface form | class | keys that render it |
| --- | --- | --- |
| نماذج الويب | `conflicting` | `admin:settings.webforms.index.title`<br>`webform:acl.title`<br>`webform:menu.title` |
| نموذج الويب | `conflicting` | `admin:settings.webforms.create.customize-webform`<br>`admin:settings.webforms.create.save-btn`<br>`admin:settings.webforms.edit.customize-webform`<br>`admin:settings.webforms.edit.save-btn` |
| نموذج ويب | `conflicting` | `admin:settings.webforms.index.create-btn` |

Also used in 6 of 18 client-guide chapters.

### `data_transfer` — Data transfer / import / export

**Class:** `conflicting` · **names:** 2 · **surface forms:** 5 · **keys scanned:** 120

**Corrected 2026-11-04 (§13 item 2):** this concept was filed with **3 names**, counting `تصدير`
(export) as a competing name for import. It is not one. Export is the **sibling action** the same screen
offers, shipped in 3 keys (`admin:dashboard.index.export-pdf`, `admin:export.export`,
`admin:export.no-records`) — all three are **correct as shipped and must not change**. The conflict is
only `نقل البيانات` (section/ACL label) versus the `استيراد` / `الاستيراد` / `الاستيرادات` family the
screen's own strings use, which is what the glossary overturns. Counting export inflated the concept from
2 names to 3 and put it at #4 in the worst-fifteen table; it now ranks 12th on key count.

| surface form | class | keys that render it |
| --- | --- | --- |
| استيراد | `conflicting` | `admin:acl.import`<br>`admin:settings.data-transfer.imports.create.title`<br>`admin:settings.data-transfer.imports.index.button-title`<br>`admin:settings.data-transfer.imports.import.title` |
| الاستيراد | `conflicting` | `admin:settings.data-transfer.imports.create.save-btn`<br>`admin:settings.data-transfer.imports.edit.save-btn`<br>`admin:settings.data-transfer.imports.edit.title`<br>`admin:settings.data-transfer.imports.import.importing-info`<br>`admin:settings.data-transfer.imports.not-valid` |
| الاستيرادات | `conflicting` | `admin:acl.imports`<br>`admin:settings.data-transfer.imports.index.title` |
| تصدير | **not a name** — sibling action, correct as shipped | `admin:dashboard.index.export-pdf`<br>`admin:export.export`<br>`admin:export.no-records` |
| نقل البيانات | `conflicting` | `admin:acl.data-transfer`<br>`admin:layouts.data_transfer`<br>`admin:layouts.data_transfer_info` |

Prose-only, never a label: «التصدير»

Also used in 10 of 18 client-guide chapters.

### `dashboard` — Dashboard

**Class:** `conflicting` · **names:** 2 · **surface forms:** 2 · **keys scanned:** 81

**Corrected 2026-11-04 (§13 item 5):** the `لوحة التحكم` row below counted **3 keys** while section 10
counted **7 keys** for the same string — the two figures disagreed and neither was the right one.
Measured: `grep -o -F 'لوحة التحكم'` over `A` returns **8 occurrences on 7 keys**, and only **5** of them
render this concept. The other **2** mean the **admin panel as a whole** and do not belong to this concept
at all: `configuration.index.general.general.locale-settings.title-info` (`A`:2091) and
`configuration.index.general.settings.footer.show-info` (`A`:2106). `لوحة التحكم` is therefore a
**homograph** (section 9), the name count stays 2, and the glossary's decision splits it per key: the 5
dashboard keys become `لوحة المعلومات`, the 2 admin-panel keys stay untouched.

| surface form | class | keys that render it |
| --- | --- | --- |
| لوحة التحكم | `conflicting` (homograph: 5 keys here, 2 keys for the admin panel elsewhere) | `admin:configuration.index.general.settings.dashboard.title`<br>`admin:configuration.index.general.settings.dashboard.info`<br>`admin:configuration.index.general.settings.dashboard.date-range-info`<br>`admin:dashboard.index.pdf.title`<br>`admin:errors.dashboard` |
| لوحة القيادة | `conflicting + suspect-literal` | `admin:configuration.index.general.settings.menu.dashboard`<br>`admin:dashboard.index.title`<br>`admin:layouts.dashboard` |

Excluded from this concept on measurement: the 2 admin-panel keys named above. Do not add them back, and
do not sweep the string — an applier that replaces `لوحة التحكم` everywhere edits the admin panel's own
chrome.

Also used in 9 of 18 client-guide chapters.

### `settings` — Settings (card page)

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 2

| surface form | class | keys that render it |
| --- | --- | --- |
| الإعدادات | `consistent` | `admin:acl.settings`<br>`admin:layouts.settings` |

Also used in 17 of 18 client-guide chapters.

### `configuration` — Configuration (settings tree)

**Class:** `conflicting` · **names:** 2 · **surface forms:** 3 · **keys scanned:** 98

| surface form | class | keys that render it |
| --- | --- | --- |
| التكوين | `conflicting` | `admin:configuration.index.save-btn`<br>`admin:configuration.index.title` |
| التهيئة | `conflicting` | `admin:acl.configuration`<br>`admin:configuration.index.general.settings.menu.configuration` |
| تكوين | `conflicting` | `admin:configuration.index.general.info` |

Also used in 6 of 18 client-guide chapters.

### `mail` — Mail module

**Class:** `consistent` · **names:** 1 · **surface forms:** 2 · **keys scanned:** 102

| surface form | class | keys that render it |
| --- | --- | --- |
| البريد | `consistent` | `admin:acl.mail`<br>`admin:mail.index.btn`<br>`admin:mail.index.datagrid.move-to-inbox`<br>`admin:mail.view.link-mail`<br>`admin:leads.view.quotes.mail`<br>`admin:configuration.index.general.settings.menu.mail`<br>`admin:layouts.mail.title`<br>`admin:layouts.mail.inbox` |
| البريد الإلكتروني | `consistent` | `admin:components.activities.actions.mail.enter-emails`<br>`admin:mail.index.mail.enter-emails`<br>`admin:mail.view.enter-mails` |

Also used in 16 of 18 client-guide chapters.

### `mail_inbox` — Mail folder: inbox

**Class:** `conflicting` · **names:** 2 · **surface forms:** 2 · **keys scanned:** 4

| surface form | class | keys that render it |
| --- | --- | --- |
| البريد الوارد | `conflicting` | `admin:configuration.index.general.settings.menu.inbox`<br>`admin:layouts.mail.inbox` |
| الوارد | `conflicting` | `admin:acl.inbox`<br>`admin:mail.index.inbox` |

Also used in 10 of 18 client-guide chapters.

### `mail_sent` — Mail folder: sent

**Class:** `conflicting` · **names:** 2 · **surface forms:** 2 · **keys scanned:** 4

| surface form | class | keys that render it |
| --- | --- | --- |
| المرسل | `conflicting` | `admin:acl.sent`<br>`admin:configuration.index.general.settings.menu.sent`<br>`admin:layouts.mail.sent` |
| تم الإرسال | `conflicting + suspect-literal` | `admin:mail.index.sent` |

Also used in 7 of 18 client-guide chapters.

### `mail_outbox` — Mail folder: outbox

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 4

| surface form | class | keys that render it |
| --- | --- | --- |
| الصادر | `consistent` | `admin:acl.outbox`<br>`admin:mail.index.outbox`<br>`admin:configuration.index.general.settings.menu.outbox`<br>`admin:layouts.mail.outbox` |

Also used in 11 of 18 client-guide chapters.

### `mail_draft` — Mail folder: drafts

**Class:** `consistent` · **names:** 1 · **surface forms:** 2 · **keys scanned:** 5

| surface form | class | keys that render it |
| --- | --- | --- |
| المسودات | `consistent` | `admin:acl.draft`<br>`admin:configuration.index.general.settings.menu.draft`<br>`admin:layouts.mail.draft` |
| مسودة | `consistent` | `admin:mail.index.draft`<br>`admin:mail.index.mail.draft` |

Also used in 6 of 18 client-guide chapters.

### `mail_trash` — Mail folder: trash

**Class:** `conflicting` · **names:** 3 · **surface forms:** 3 · **keys scanned:** 4

| surface form | class | keys that render it |
| --- | --- | --- |
| المحذوفات | `conflicting + suspect-literal` | `admin:acl.trash` |
| المهملات | `conflicting` | `admin:mail.index.trash` |
| سلة المهملات | `conflicting` | `admin:configuration.index.general.settings.menu.trash`<br>`admin:layouts.mail.trash` |

Also used in 5 of 18 client-guide chapters.

### `menu_item` — Sidebar menu entry label

**Class:** `consistent` · **names:** 1 · **surface forms:** 2 · **keys scanned:** 21

| surface form | class | keys that render it |
| --- | --- | --- |
| إعدادات عناصر القائمة | `consistent` | `admin:configuration.index.general.settings.menu.title` |
| عناصر القائمة | `consistent` | `admin:configuration.index.general.settings.menu-color.title` |

Also used in 4 of 18 client-guide chapters.

### `mega_search` — Global (mega) search

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 19

| surface form | class | keys that render it |
| --- | --- | --- |
| البحث الشامل | `consistent` | `admin:components.layouts.header.mega-search.title` |

Also used in 3 of 18 client-guide chapters.

### `notification` — Notification

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 34

| surface form | class | keys that render it |
| --- | --- | --- |
| الإشعارات | `consistent` | `admin:notifications.title`<br>`admin:configuration.index.general.settings.notifications.title`<br>`admin:configuration.index.general.settings.notifications.enabled` |

Prose-only, never a label: «إشعارات», «الإشعار», «إشعار»

Also used in 5 of 18 client-guide chapters.

### `quick_add` — Quick add

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 9

| surface form | class | keys that render it |
| --- | --- | --- |
| إضافة سريعة | `consistent` | `admin:acl.quick_add`<br>`admin:settings.attributes.index.datagrid.quick-add`<br>`admin:settings.attributes.create.quick_add`<br>`admin:settings.attributes.edit.quick_add`<br>`admin:layouts.quick-add.title` |

Also used in 4 of 18 client-guide chapters.

### `address` — Billing / shipping address

**Class:** `conflicting` · **names:** 3 · **surface forms:** 3 · **keys scanned:** 4

| surface form | class | keys that render it |
| --- | --- | --- |
| عنوان الشحن | `conflicting` | `admin:quotes.index.pdf.shipping-address`<br>`installer:seeders.attributes.quotes.shipping-address` |
| عنوان الفواتير | `conflicting` | `installer:seeders.attributes.quotes.billing-address` |
| عنوان الفوترة | `conflicting` | `admin:quotes.index.pdf.billing-address` |

Also used in 2 of 18 client-guide chapters.

### `record_id` — Record identifier column

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 22

| surface form | class | keys that render it |
| --- | --- | --- |
| المعرف | `consistent` | `admin:contacts.persons.index.datagrid.id`<br>`admin:contacts.organizations.index.datagrid.id`<br>`admin:products.index.datagrid.id`<br>`admin:settings.groups.index.datagrid.id`<br>`admin:settings.roles.index.datagrid.id`<br>`admin:settings.types.index.datagrid.id`<br>`admin:settings.sources.index.datagrid.id`<br>`admin:settings.workflows.index.datagrid.id`<br>… +13 more |

Also used in 6 of 18 client-guide chapters.

### `sku` — SKU / product code

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 3

| surface form | class | keys that render it |
| --- | --- | --- |
| رمز المنتج | `consistent` | `admin:quotes.index.pdf.sku`<br>`admin:products.index.datagrid.sku`<br>`admin:products.view.sku` |

Also used in 4 of 18 client-guide chapters.

### `account` — My account

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 37

| surface form | class | keys that render it |
| --- | --- | --- |
| حسابي | `consistent` | `admin:account.edit.title`<br>`admin:layouts.my-account`<br>`admin:user.account.my_account` |

Also used in 6 of 18 client-guide chapters.

### `login` — Sign-in screen

**Class:** `consistent` · **names:** 1 · **surface forms:** 1 · **keys scanned:** 6

| surface form | class | keys that render it |
| --- | --- | --- |
| تسجيل الدخول | `consistent` | `admin:users.login.submit-btn`<br>`admin:users.login.title` |

Also used in 1 of 18 client-guide chapters.

### `password_reset` — Password reset / forgot-password flow

**Class:** `conflicting` · **names:** 3 · **surface forms:** 3 · **keys scanned:** 13

| surface form | class | keys that render it |
| --- | --- | --- |
| إعادة تعيين كلمة المرور | `conflicting` | `admin:users.reset-password.submit-btn`<br>`admin:users.reset-password.title` |
| استعادة كلمة المرور | `conflicting` | `admin:users.forget-password.create.title` |
| نسيت كلمة المرور | `conflicting` | `admin:users.forget-password.create.page-title` |

Also used in 3 of 18 client-guide chapters.

### `http_terminology` — HTTP vocabulary used by the webhook screens

**Class:** `conflicting` · **names:** 6 · **surface forms:** 9 · **keys scanned:** 13

| surface form | class | keys that render it |
| --- | --- | --- |
| إرسال | `conflicting` | `admin:settings.webhooks.create.post` |
| الجسم | `conflicting` | `admin:settings.webhooks.create.body` |
| الرؤوس | `conflicting` | `admin:settings.webhooks.create.headers` |
| الطريقة | `conflicting` | `admin:settings.webhooks.create.method` |
| تحديث | `conflicting` | `admin:settings.webhooks.create.put` |
| حمولة | `conflicting` | `admin:settings.webhooks.create.add-new-payload` |
| رأس | `conflicting` | `admin:settings.webhooks.create.add-new-header` |
| نقطة النهاية | `conflicting` | `admin:settings.webhooks.index.datagrid.end-point` |
| نقطة نهاية الرابط | `conflicting` | `admin:settings.webhooks.create.url-endpoint` |

Also used in 16 of 18 client-guide chapters.

---

## 8. Latin-only strings: the 42 values in the Arabic files that contain no Arabic

The count is **42 non-empty** values; a 43rd key is Latin-only because its value is empty
(`installer:installer.index.webkul`), which is why a raw count of keys with no Arabic letter returns 43.
Reproduce both:

```text
# flatten the five lang files to `ns:key<TAB>value` lines the way Appendix B does, concatenate the
# results into one file, then:
$ grep -vcP '[\x{0600}-\x{06FF}]' all-values.tsv                   # 43 — keys with no Arabic letter
$ grep -vP '[\x{0600}-\x{06FF}]' all-values.tsv | grep -cP '\t\S'  # 42 — the same, with a value
```

**Four of these are genuine defects, and all four were decided by the operator on 2026-11-04** (glossary
§4.7.1); the rest are correct as they stand.

| key | value | verdict |
| --- | --- | --- |
| `admin:leads.update-failed` | `Leads can not be deleted.` | **defect — decided** → `لا يمكن حذف الفرص.` (uses the approved record term `الفرصة` / `الفرص`) |
| `admin:leads.index.datagrid.id` | `ID` | **defect — decided** → `المعرف`; every other grid already says it |
| `admin:leads.index.kanban.columns.id` | `ID` | **defect — decided** → `المعرف`; same |
| `admin:settings.webhooks.create.json` | `Json` | **defect — decided** → `JSON`, as `installer:installer.index.server-requirements.json` already is |
| `admin:settings.webhooks.edit.json` | `Json` | **defect — decided** → `JSON`; the `edit` twin of the row above, missing from the first pass |
| `installer:seeders.attributes.products.sku` | `SKU` | correct — stays Latin by design |
| `admin:export.csv` / `.xls` / `.xlsx` | `CSV` / `XLS` / `XLSX` | correct — formats |
| `admin:settings.webhooks.*.x-www-form-urlencoded` | `x-www-form-urlencoded` | correct — media type |
| `admin:components.media.*.allowed-types` | `png, jpeg, jpg` / `mp4, webm, mkv` | correct — extensions |
| `admin:contacts.persons.view.title` | `:name` | correct — placeholder only |
| `admin:mail.view.subject` | `:subject` | correct — placeholder only |
| `admin:configuration.index.magic-ai.settings.models.*` (6) | model names | correct — proper nouns |
| `installer:installer.index.environment-configuration.*` (4) | MySQL / MariaDB / PgSQL / SQLSRV | correct — product names |
| `installer:installer.index.server-requirements.*` (13, the whole extension checklist) | cType, cURL, dom, GD, intl, JSON, mbstring, openssl, pcre, pdo, PHP, tokenizer, XML | correct — extension names |
| `installer:installer.index.create-administrator.email-address` | `admin@example.com` | correct — placeholder |
| `installer:installer.index.environment-configuration.default-url-link` | `https://localhost` | correct — placeholder |
| `googlecontact:acl.title` / `menu.title` | `Google Contacts` | correct — proper noun, and the only two Arabic-file keys this package ships |

The `ID` pair matters for the guardrail: the leads screen is the *only* grid that
renders a Latin column header, and `المعرف` is what all the other grids use, so
`record_id` looks `consistent` in the Arabic corpus while the screen is not.

---

## 9. One Arabic word, two concepts (homographs)

These are not conflicts — they are the reason a naive "replace this substring
everywhere" sweep would corrupt unrelated screens. Each row is a string that this
inventory found doing two jobs.

| string | job 1 | job 2 |
| --- | --- | --- |
| عرض | `acl.view` — the **view** action | `layouts.quote` — a **quote** |
| مسار | `settings.data-transfer.imports.*.images-directory` — a filesystem **path** | `leads.index.view-switcher.create-new-pipeline` — a **pipeline** |
| مسار | `mail.invalid-route` — an invalid mail **route** | same as above |
| مفقود | `leads.file.missing-api-key` — a **missing** API key | `installer:seeders.lead.pipeline.pipeline-stages.lost` — a **lost** deal |
| علامة | `components.activities.index.mark-as-done` — **mark** as done | `acl.tags` — a **tag** |
| حدث | `components.datagrid.toolbar.filter.delete-error`, `errors.500.description` — **something happened** | `acl.event` — a marketing **event** |
| مستخدم | `installer:...database-username` — a database **user** | `acl.user` — a CRM **user** |
| شخص | `account.edit.profile-image` — **your** photo | `acl.persons` — a **person** record |
| النوع | the Leads **type** | shared by `entity-type`, `condition-type`, `option-type`, `lookup-type`, `permission-type` — five other concepts |
| لوحة التحكم | the **dashboard page** — 5 keys (`dashboard.index.pdf.title`, the three `configuration…settings.dashboard.*` labels, `errors.dashboard`) | the **admin panel as a whole** — 2 keys (`configuration.index.general.general.locale-settings.title-info`, `configuration.index.general.settings.footer.show-info`) |
| جهة الاتصال | the **Contacts** module (`acl.contacts`) | the **linked contact** of a single lead (`leads.view.persons.change-title`) |

The `النوع` and `جهة الاتصال` rows are the two that a glossary has to resolve
explicitly: `النوع` cannot be renamed wholesale without splitting the six concepts it
currently serves, and `جهة الاتصال` is used for both the module and the lead's link,
so renaming one renames the other unless the key sets are separated first.

`لوحة التحكم` was added to this table on 2026-11-04 (§13 item 5) because it is the one row where a
*decided* rename would corrupt an unrelated screen: the glossary moves the dashboard page to
`لوحة المعلومات`, and a find-and-replace over `لوحة التحكم` would silently relabel the admin panel's own
locale and footer help text. Measured split: 8 occurrences on 7 keys = 5 dashboard + 2 admin panel.

---

## 10. Audit against the client guide

`docs/client-guide/ar/17-glossary.md` already documents several of these conflicts.
Checked mechanically against this inventory:

**The guide is right about** the lead / القيادة pair, the pipeline three-way split
(الأنابيب / خطوط الأنابيب / قنوات البيع / كل المسارات), the won-and-lost trio
(مُنتصر ومفقود as seeded, ربحت وخسرت on the kanban, الإيرادات المكتسبة والمفقودة on the
dashboard card, فاز وخسر in the pipeline editor), الأنابيب versus قنوات البيع,
الصادر versus المرسل, the rotten family (أيام التعفن / العميل الفاسد / عميل محتمل متعفن
/ العميل المحتمل مهمل), and `Leads can not be deleted.` — every one of those strings
exists at the key the guide implies.

Two guide quotes were re-verified against the source:

```text
$ grep -n 'rotten-days' packages/Webkul/Admin/src/Resources/lang/ar/app.php
  mail.view.rotten-days        => 'العميل المحتمل مهمل لمدة :days يومًا',
  leads.index.kanban.rotten-days => 'العميل الفاسد منذ :days أيام',
$ grep -n 'top-persons.empty-info' .../ar/app.php
  dashboard.index.top-persons.empty-info => 'لا توجد أشخاص متاحة للفترة المختارة'
```

**The guide misses** these conflicting name sets — a mechanical check of chapter 17 for
each string returns zero hits:

| string the guide never mentions | where it is real |
| --- | --- |
| المحذوفات | `acl.trash` — a third name for the trash folder |
| لوحة التحكم | `dashboard.index.pdf.title`, the 3 `configuration.index.general.settings.dashboard.*` keys, `configuration.index.general.general.locale-settings.title-info`, `configuration.index.general.settings.footer.show-info`, `errors.dashboard` — 7 keys in total, of which **5 render the dashboard** and **2 mean the admin panel** (§13 item 5) |
| الاقتباسات | `components.activities.index.quotes`, `leads.view.tabs.quotes` |
| نموذج البريد الإلكتروني | `settings.marketing.campaigns.index.create.email-template` |
| الويب هوكس | `settings.webhooks.index.title` — the guide's webhook row lists no alternative name at all, though four exist |
| مسؤول المبيعات | `settings.workflows.helpers.send-email-to-sales-owner` |
| عنوان الفواتير | `installer:seeders.attributes.quotes.billing-address` |
| إعادة تعيين / استعادة كلمة المرور | the whole password-reset flow ships three titles; the guide never mentions the flow |

Two whole clusters are absent from the guide from beginning to end: the
`http_terminology` cluster (نقطة النهاية, حمولة, الرؤوس, الجسم, الطريقة) and the
`password_reset` cluster. Both are `conflicting` here, and both were **settled by the operator on
2026-11-04** — the glossary now carries a row for each (§4.5's three HTTP rows, §4.8's password-flow row),
so the guide chapters get them in the apply lane instead of staying silent about them.

The guide's own framing — "three layers name the same thing" — is confirmed by the
data, but the inventory finds **23** concepts with all three layers and **12** of them
disagreeing, not the three the guide illustrates.

---

## 11. What I could not classify, and why

Stated plainly, because the guardrail task will run into each of these.

1. **`النوع` is unresolvable by key alone.** It is the Leads *type* concept in
   `settings.types.*` and `installer:seeders.lead.type.*`, but the same word names
   `entity-type`, `condition-type`, `option-type`, `lookup-type` and `permission-type`.
   The key path separates them; the string does not. Any rename has to be
   key-path-scoped, never string-scoped.

2. **`جهة الاتصال` serves two concepts** — the Contacts module and a single lead's
   linked person. `contact_person` and `contacts` both own it. I left it in both rather
   than guess which one a glossary would keep. **Settled 2026-11-04:** the glossary keeps `جهة الاتصال`
   for the linked person (its §4.2 `contact person` row overturns `شخص الاتصال`) and keeps `جهات الاتصال`
   for the section, so the same word legitimately names both — the key sets are separate, and
   `person-updated`, `save-contact` and `change-title` belong to the record, not to the module.

3. **`العميل` and `العملاء` (bare, without محتمل) are ambiguous — and the first pass got their key set
   wrong.** Corrected 2026-11-04 (§13 item 6): the two `contacts.persons.index.*-delete-warning` keys do
   **not** ship a bare `العميل`. Measured, they read `…لأنهم مرتبطون بعملاء محتملين.` (`A`:512–513) — the
   *lead* term, not the customer term. The keys that really carry a bare word are three, and they are not
   interchangeable: `leads.index.kanban.empty-list` (`A`:1901 `قائمة العملاء الخاصة بك فارغة`) and
   `settings.sources.index.delete-failed-associated-leads` (`A`:830 `…يرجى فصل أو تحديث هؤلاء العملاء قبل
   الحذف`) carry bare **`العملاء`**, while `emails.common.user.forget-password.subject` (`A`:2475
   `إعادة تعيين كلمة مرور العميل`) carries bare **`العميل`** — and that one is a defect the operator has
   already decided, because it labels a CRM **user** as a customer (glossary §4.7.1). I counted all three
   as `lead` surface forms because the keys are lead keys, and flagged them here. One further occurrence
   of `العملاء` is not a record name at all: `layouts.inventory-info` (`A`:2444) says `إدارة علاقات العملاء
   (CRM)`, the CRM product noun.

4. **`منظمة` / `مؤسسة` cannot be ordered.** Both are idiomatic Arabic for
   "organization". Two names is a fact; which one is right is a glossary decision and
   not something extraction can settle.

5. **The `ID` versus `المعرف` conflict is invisible to the Arabic corpus** because `ID`
   contains no Arabic letter, so the seven-line `leads` grid header defect shows up in
   section 8 rather than in the concept table. `record_id` reads `consistent` there.

6. **`http_terminology` severity is a judgement — and it has been made.** I called إرسال and تحديث
   suspect-literal for HTTP `POST` and `PUT` because the labels hide the verb a webhook author is
   choosing, and a reviewer could reasonably have called them acceptable localisation. **Settled
   2026-11-04 in the direction this finding pointed:** both become the Latin `POST` / `PUT` (glossary
   decision 8a), the endpoint field becomes `عنوان URL` (8b), and `حمولة` / `الرؤوس` / `رأس` / `الجسم` /
   `الطريقة` keep their shipped values with a glossary row of their own (8c) — so the cluster is no longer
   an open question, and the strings stay listed here for the record.

7. **Whether the pipeline is one concept or two is arguable.** `pipeline` and
   `pipeline_stage` are separate concepts here because they have separate ACL, sidebar
   and title slots. If a glossary treats "the pipeline and its stages" as one screen,
   the counts merge (`pipeline` 2 names + `pipeline_stage` 1 name).

8. **`activity_type`'s broken plural is a grammar defect, not a naming conflict — corrected 2026-11-04
   (§13 item 1).** الغداء (lunch) takes `الغداءات` / `غداءات`, which is not a normal Arabic plural, and
   that is the only defect on this screen. The three types are three different values, so they are not
   rival names for one concept and the class is `consistent`, not `conflicting`; this item previously
   said otherwise. The plural ships in **2 keys** — `components.activities.index.empty-placeholders.lunches.title`
   (`A`:199) and `.description` (`A`:200), 3 occurrences between them — and the fix needs no new invented
   plural: `أنشطة غداء` / `أنشطة الغداء`, matching how the `calls` and `meetings` blocks read. That
   suggestion is **not decided** — the glossary carries no row for it — so the apply lane takes it from
   here or asks, and must not invent a different plural.

9. **Prose was excluded by rule, so some mentions never appear as names.** For example
   `leads.file.*` is a block of eleven AI-upload error sentences that mention
   العميل المحتمل; they are in the key lists but contribute no rendering. If the apply
   task rewrites sentences too, it must read the full key list, not just `naming_keys`
   in the appendix.

---

## 12. Synonym map

The map the extraction uses, reproduced. A concept may own more than one name (the `conflicting` rows) and a name may be owned by more than one concept (see section 9). The `flagged` column repeats the section-6 classification per surface form so the two sections can be diffed.

| concept | name (lexeme) | surface forms in the corpus | flagged |
| --- | --- | --- | --- |
| `lead` (conflicting) | عميل محتمل | العميل المحتمل · عميل محتمل | — |
|  | العملاء المحتملون | العملاء المحتملين المفتوحين · العملاء المحتملين · العملاء المحتملون · عملاء محتملين | — |
|  | العميل / العملاء (bare) | العميل · العملاء | — |
|  | الفرصة (opportunity) | الفرصة · الفرص · فرصة | — |
|  | القيادة (leadership) | القيادة | suspect-literal: القيادة |
| `lead_value` (consistent) | القيمة التقديرية للعميل المحتمل | القيمة التقديرية للعميل المحتمل | — |
|  | قيمة الربح | قيمة الربح | — |
| `lead_source` (consistent) | المصدر / المصادر | المصادر · المصدر · مصادر · مصدر | — |
| `lead_type` (consistent) | النوع / الأنواع | نوع العميل المحتمل · الأنواع · النوع · أنواع · نوع | — |
| `pipeline` (conflicting) | قناة المبيعات / قنوات البيع | قنوات البيع · قناة المبيعات | suspect-literal: قنوات البيع · قناة المبيعات |
|  | خط الأنابيب (literal pipe) | خطوط الأنابيب · خط الأنابيب · خط أنابيب · الأنابيب · أنابيب | suspect-literal: خطوط الأنابيب · خط الأنابيب · خط أنابيب · الأنابيب |
|  | المسار (path) | المسارات · مسار | suspect-literal: المسارات · مسار |
| `pipeline_stage` (consistent) | المرحلة / المراحل | المراحل · المرحلة · مراحل · مرحلة | — |
| `pipeline_stage_won` (conflicting) | مُنتصر (seeded) | مُنتصر · منتصر | suspect-literal: مُنتصر |
|  | ربحت / فاز (buttons) | ربحت · فاز | suspect-literal: ربحت · فاز |
|  | المكتسب (dashboard) | المكتسبة · المكتسبين · المكتسب | suspect-literal: المكتسبة · المكتسبين · المكتسب |
| `pipeline_stage_lost` (conflicting) | مفقود (seeded) | المفقودة · المفقودين · المفقود · مفقود | suspect-literal: المفقودة · المفقودين · المفقود · مفقود |
|  | خسرت / خسر (buttons) | خسرت · خسر | suspect-literal: خسرت · خسر |
| `rotten` (conflicting) | التعفن / متعفن | أيام التعفن · التعفن · متعفن | suspect-literal: أيام التعفن · التعفن · متعفن |
|  | الفاسد (literal rotten) | الفاسد · فاسد | suspect-literal: الفاسد |
|  | مهمل (neglected) | مهمل | — |
| `probability` (consistent) | الاحتمالية | الاحتمالية | — |
| `person` (consistent) | شخص / أشخاص | الأشخاص · أشخاص · الشخص · شخص | — |
| `organization` (conflicting) | المؤسسة / المؤسسات | المؤسسات · مؤسسات · المؤسسة · مؤسسة | — |
|  | المنظمة / المنظمات | المنظمات · منظمات · المنظمة · منظمة | — |
| `contacts` (consistent) | جهات الاتصال | جهات الاتصال · جهة الاتصال · جهات اتصال · جهة اتصال | — |
| `contact_person` (**conflicting**; §13 item 4) | شخص الاتصال | شخص الاتصال | — |
|  | جهة الاتصال | جهة الاتصال · جهة اتصال | — |
| `quote` (conflicting) | عرض السعر | عرض السعر · عرض سعر | — |
|  | عروض الأسعار (quotation) | عروض الأسعار | — |
|  | العروض / عروض | العروض · عروض | — |
|  | عرض (bare; homograph: view) | عرض | — |
|  | الاقتباس (citation) | الاقتباسات · الاقتباس · اقتباس | suspect-literal: الاقتباسات · الاقتباس · اقتباس |
| `product` (consistent) | المنتج / المنتجات | المنتجات · منتجات · المنتج · منتج | — |
| `activity` (consistent) | النشاط / الأنشطة | الأنشطة · أنشطة · النشاط · نشاط | — |
| `activity_type` (**consistent** — three values, one name each; §13 item 1) | مكالمة / مكالمات | المكالمات · مكالمات · مكالمة | — |
|  | اجتماع / اجتماعات | الاجتماعات · اجتماعات · اجتماع | — |
|  | غداء | الغداء · غداء  (**not** a name: `الغداءات` · `غداءات`) | — (grammar defect, §11.8) |
| `warehouse` (consistent) | المستودع / المستودعات | المستودعات · مستودعات · المستودع · مستودع | — |
| `inventory` (consistent) | المخزون | المخزون · مخزون | — |
| `tag` (conflicting) | الوسم / الوسوم | الوسوم · وسوم · الوسم · وسم | — |
|  | العلامة / العلامات | العلامات · العلامة · علامة | — |
| `attribute` (conflicting) | السمة / السمات | السمات · سمات · السمة · سمة | — |
|  | الخاصية / الخصائص | الخصائص · الخاصية · خاصية | — |
| `role` (consistent) | الدور / الأدوار | الأدوار · أدوار · الدور · دور | — |
| `group` (consistent) | المجموعة / المجموعات | المجموعات · مجموعات · المجموعة · مجموعة | — |
| `user` (consistent) | المستخدم / المستخدمون | المستخدمون · المستخدمين · مستخدمون · المستخدم · مستخدم | — |
| `sales_owner` (conflicting) | مالك المبيعات | مالك المبيعات | — |
|  | مندوب المبيعات | مندوب المبيعات | — |
|  | مسؤول المبيعات | مسؤول المبيعات | — |
| `email_template` (conflicting) | قالب البريد الإلكتروني | قوالب البريد الإلكتروني · قالب البريد الإلكتروني · قوالب البريد · قالب البريد | — |
|  | نموذج البريد الإلكتروني | نموذج البريد الإلكتروني | suspect-literal: نموذج البريد الإلكتروني |
| `email_settings` (**consistent**; §13 item 3) | إعدادات البريد الإلكتروني | إعدادات البريد الإلكتروني | — |
|  | IMAP (subsection titles, not a rival name) | إعدادات IMAP · حساب IMAP · IMAP | — |
|  | SMTP (protocol token) | SMTP | — |
| `workflow` (consistent) | سير العمل | سير العمل · سير عمل | — |
| `automation` (consistent) | الأتمتة | الأتمتة · أتمتة | — |
| `campaign` (consistent) | الحملة / الحملات | الحملات · حملات · الحملة · حملة | — |
| `event` (conflicting) | الحدث / الأحداث | الأحداث · الحدث · حدث | — |
|  | الفعالية / الفعاليات | الفعاليات · فعاليات · الفعالية · فعالية | — |
| `webhook` (conflicting) | الويب هوك | الويب هوكس · الويب هوك | suspect-literal: الويب هوكس · الويب هوك |
|  | ويب هوك | ويب هوك | suspect-literal: ويب هوك |
|  | Webhook (left in English) | Webhooks · Webhook · webhooks · webhook | suspect-literal: Webhook · webhook |
| `web_form` (conflicting — **protected as shipped by the glossary; no edit**) | نموذج الويب | نموذج الويب · نموذج ويب | — |
|  | نماذج الويب | نماذج الويب · نماذج ويب | — |
| `data_transfer` (conflicting) | نقل البيانات | نقل البيانات | — |
|  | الاستيراد / استيراد | الاستيرادات · الاستيراد · استيراد | — |
|  | ~~التصدير / تصدير~~ **not a name** (§13 item 2) | التصدير · تصدير | — sibling action, correct as shipped |
| `dashboard` (conflicting) | لوحة القيادة | لوحة القيادة | suspect-literal: لوحة القيادة |
|  | لوحة التحكم (5 dashboard keys only; 2 more keys mean the admin panel — §13 item 5 and section 9) | لوحة التحكم | — |
|  | لوحة المعلومات (approved, ships 0×) | لوحة المعلومات | — |
| `settings` (consistent) | الإعدادات | الإعدادات · إعدادات | — |
| `configuration` (conflicting) | التهيئة | التهيئة | — |
|  | التكوين | التكوينات · التكوين · تكوينات · تكوين | — |
| `mail` (consistent) | البريد | البريد الإلكتروني · البريد | — |
| `mail_inbox` (conflicting) | البريد الوارد | البريد الوارد · صندوق الوارد | — |
|  | الوارد | الوارد | — |
| `mail_sent` (conflicting) | المرسل | المرسل | — |
|  | تم الإرسال (past tense used as folder name) | تم الإرسال · المرسلة | suspect-literal: تم الإرسال |
| `mail_outbox` (consistent) | الصادر | الصادر · الصادرة | — |
| `mail_draft` (consistent) | المسودات | المسودات · المسودة · مسودة | — (per key: folder label → المسودات, compose dialog stays مسودة — glossary decision 10) |
| `mail_trash` (conflicting) | سلة المهملات | سلة المهملات | — |
|  | المهملات | المهملات | — |
|  | المحذوفات | المحذوفات | suspect-literal: المحذوفات |
| `menu_item` (consistent) | عناصر القائمة | عناصر القائمة · بند القائمة · إعدادات عناصر القائمة | — |
| `mega_search` (consistent) | البحث الشامل | البحث الشامل · بحث شامل · البحث العام | — |
| `notification` (consistent) | الإشعارات | الإشعارات · إشعارات · الإشعار · إشعار | — |
| `quick_add` (consistent) | إضافة سريعة | إضافة سريعة | — |
| `address` (conflicting) | عنوان الشحن | عنوان الشحن | — |
|  | عنوان الفوترة (invoicing) | عنوان الفوترة · الفوترة | — |
|  | عنوان الفواتير (invoices) | عنوان الفواتير · الفواتير | — |
| `record_id` (consistent) | المعرف | المعرف | — |
|  | ID (left in English) | ID | — |
| `sku` (consistent) | رمز المنتج | رمز المنتج | — |
|  | SKU | SKU | — |
| `account` (consistent) | حسابي | حسابي | — |
| `login` (consistent) | تسجيل الدخول | تسجيل الدخول | — |
| `password_reset` (conflicting — **settled 2026-11-04**, glossary decision 9) | إعادة تعيين كلمة المرور | إعادة تعيين كلمة المرور | — step two, stays |
|  | استعادة كلمة المرور | استعادة كلمة المرور | — step one, and the tab title joins it |
|  | نسيت كلمة المرور | نسيت كلمة المرور | — the login link only, not a screen title |
| `http_terminology` (conflicting — **settled 2026-11-04**, glossary decision 8) | نقطة النهاية | نقطة نهاية الرابط · نقطة النهاية | suspect-literal: نقطة نهاية الرابط · نقطة النهاية — resolved to `عنوان URL` in 3 keys |
|  | حمولة | حمولة | suspect-literal: حمولة — **kept as shipped** |
|  | الرؤوس | الرؤوس · رأس | suspect-literal: الرؤوس · رأس — **kept as shipped** |
|  | الجسم | الجسم | suspect-literal: الجسم — **kept as shipped** |
|  | الطريقة | الطريقة | suspect-literal: الطريقة — **kept as shipped** |
|  | إرسال / تحديث (POST / PUT) | إرسال · تحديث | suspect-literal: إرسال · تحديث — resolved to Latin `POST` / `PUT` in 4 keys |

---

## 13. Corrections applied 2026-11-04

The reconciliation against [arabic-terminology.md](arabic-terminology.md) measured six classifications in
this inventory that were wrong, and wrong in the direction that matters: an applier trusting them would
have edited the wrong keys. Each one is **corrected in place above, with the correction stated** rather
than deleted, and each keeps its original finding visible so the next agent can see what changed and why.
Every number below is re-measurable with the command quoted beside it.

| # | concept | was | now | why it was wrong |
| --- | --- | --- | --- | --- |
| 1 | `activity_type` | `conflicting`, 3 names / 20 keys | **`consistent`**, 1 name per type | مكالمة / اجتماع / غداء are three **different types**, not three names for one concept; each ships in its own key set and the glossary approves all three singulars. The only defect is the broken plural `الغداءات` / `غداءات`, a grammar error in 2 prose keys — string-level, not a naming conflict. |
| 2 | `data_transfer` | `conflicting`, 3 names / 120 keys | **`conflicting`, 2 names / 120 keys** | `تصدير` (export) is the sibling action, not a competing name for import. Its 3 keys are **correct as shipped** and must not change. |
| 3 | `email_settings` | `conflicting`, 2 names / 12 keys | **`consistent`**, 1 name | A parent section title (`إعدادات البريد الإلكتروني`) and its IMAP subsection titles are a deliberate hierarchy, not rivals. |
| 4 | `contact_person` | `consistent`, 1 name / 11 keys | **`conflicting`, 2 names / 11 keys** | `شخص الاتصال` (**7 `A`** keys) and `جهة الاتصال` (**5 `A`** keys) are two different lexemes, and the glossary overturns one of them — a concept carrying a rename cannot be `consistent`. |
| 5 | `dashboard` | `لوحة التحكم` = 3 keys here, 7 keys in section 10 | **5 keys here, 2 excluded** | Measured: 8 occurrences on **7 keys**, of which **5** render the dashboard and **2** mean the admin panel as a whole. `لوحة التحكم` is a homograph (section 9), and the glossary splits it per key. |
| 6 | section 11.3 | "the two `contacts.persons.index.*-delete-warning` strings ship a bare `العميل`" | **they ship `مرتبطون بعملاء محتملين`** (`A`:512–513) | The bare word is not there. The keys that really carry it are `leads.index.kanban.empty-list` (`A`:1901, bare **`العملاء`**), `settings.sources.index.delete-failed-associated-leads` (`A`:830, bare **`العملاء`**) and `emails.common.user.forget-password.subject` (`A`:2475, bare **`العميل`** — the one the operator has already decided). |

### The commands that re-measure each correction

```bash
A=packages/Webkul/Admin/src/Resources/lang/ar/app.php

# 1 — the broken plural is prose in exactly 2 keys (title + description of the lunches block)
grep -n 'الغداءات\|غداءات' $A                    # A:199 title, A:200 description
grep -o -F 'غداءات' $A | wc -l                    # 3 occurrences (الغداءات counted inside غداءات)

# 2 — export is a separate action, shipped correctly in 3 keys
grep -n 'تصدير' $A                                # export-pdf, export.export, export.no-records are labels

# 3 — one section title, two subsection titles, no rival name
grep -n 'إعدادات البريد الإلكتروني\|إعدادات IMAP\|حساب IMAP' $A

# 4 — two lexemes, not two inflections
grep -o -F 'شخص الاتصال' $A | wc -l               # 7
grep -o -F 'جهة الاتصال' $A | wc -l               # 5

# 5 — 8 occurrences on 7 keys, split 5 dashboard + 2 admin panel
grep -o -F 'لوحة التحكم' $A | wc -l               # 8
grep -n 'لوحة التحكم' $A                          # 7 lines: 2091, 2106 (admin panel) + 2132, 2133, 2135×2, 2234, 2492 (dashboard)

# 6 — the delete-warning wording that was mis-quoted
grep -n 'delete-warning' $A                       # A:512–513 both say مرتبطون بعملاء محتملين
grep -n 'قائمة العملاء الخاصة بك فارغة' $A         # A:1901
grep -n 'كلمة مرور العميل' $A                      # A:2475
```

### What these corrections do **not** change

- The **55 concepts** and the **2,204 Arabic-bearing keys** are untouched: no concept was added, removed
  or re-scoped.
- The **44 suspect-literal strings** in section 6 are untouched. Item 1's broken plural is not a
  suspect-literal (it is not a transliteration or a word-for-word rendering), which is why it is recorded
  as a grammar defect instead of being counted there.
- `web_form` **stays `conflicting` here** even though the glossary protects it as shipped: reporting the
  disagreement is this file's job, deciding it is the glossary's, and the glossary's decision is
  **zero edits** (its operator decision 7).
- The glossary's own decisions are not restated here. They live in
  [arabic-terminology.md](arabic-terminology.md) §operator decisions 6–10, §4.5, §4.6, §4.8 and §4.7.1.

---

## Appendix A — machine-readable map

Shape is stable: `concept -> {english, class, n_names, names, strings, suspect,
naming_keys}`. `naming_keys` holds only the keys whose **value must change** for a
rename (naming slots); the full scanned key list is regenerable from Appendix B. That
keeps the block usable by the apply and guardrail tasks without carrying 2,475
assignments.

```json
{
    "lead": {
        "english": "Lead / opportunity record",
        "class": "conflicting",
        "n_names": 5,
        "names": [
            "العملاء المحتملون",
            "عميل محتمل",
            "القيادة (leadership)",
            "العميل / العملاء (bare)",
            "الفرصة (opportunity)"
        ],
        "strings": [
            "العملاء",
            "العملاء المحتملون",
            "العملاء المحتملين",
            "العميل",
            "العميل المحتمل",
            "الفرصة",
            "القيادة",
            "عميل محتمل"
        ],
        "suspect": [
            "القيادة"
        ],
        "naming_keys": [
            "admin:acl.lead",
            "admin:acl.leads",
            "admin:activities.edit.lead",
            "admin:activities.index.datagrid.lead",
            "admin:components.layouts.header.mega-search.tabs.leads",
            "admin:configuration.index.general.settings.menu.leads",
            "admin:layouts.lead",
            "admin:layouts.lead-info",
            "admin:layouts.leads",
            "admin:leads.create.title",
            "admin:leads.edit.title",
            "admin:leads.index.create-btn",
            "admin:leads.index.datagrid.rotten-lead",
            "admin:leads.index.kanban.empty-list",
            "admin:leads.index.kanban.rotten-days",
            "admin:leads.index.title",
            "admin:leads.index.upload.create-lead",
            "admin:leads.view.attributes.title",
            "admin:leads.view.title",
            "admin:mail.view.lead-details",
            "admin:notifications.titles.lead-created",
            "admin:notifications.titles.lead-updated",
            "admin:settings.attributes.index.datagrid.entity-types.leads",
            "admin:settings.data-transfer.importers.leads.title",
            "admin:settings.webforms.create.leads",
            "admin:settings.webforms.edit.leads",
            "installer:seeders.lead.pipeline.pipeline-stages.prospect"
        ]
    },
    "lead_value": {
        "english": "Lead value (deal amount)",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "القيمة التقديرية للعميل المحتمل"
        ],
        "strings": [
            "القيمة التقديرية للعميل المحتمل"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:leads.index.datagrid.lead-value",
            "admin:leads.index.kanban.columns.lead-value",
            "admin:leads.index.kanban.toolbar.card-settings.estimated-lead-value",
            "installer:seeders.attributes.leads.lead-value"
        ]
    },
    "lead_source": {
        "english": "Lead source",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "المصدر / المصادر"
        ],
        "strings": [
            "المصادر",
            "المصدر",
            "مصدر"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.sources",
            "admin:dashboard.index.pdf.revenue-by-sources",
            "admin:dashboard.index.pdf.source",
            "admin:dashboard.index.revenue-by-sources.title",
            "admin:layouts.sources",
            "admin:layouts.sources-info",
            "admin:leads.index.datagrid.source",
            "admin:leads.index.kanban.columns.source",
            "admin:leads.index.kanban.toolbar.card-settings.source",
            "admin:settings.sources.index.create-btn",
            "admin:settings.sources.index.create.save-btn",
            "admin:settings.sources.index.create.title",
            "admin:settings.sources.index.edit.title",
            "admin:settings.sources.index.title",
            "installer:seeders.attributes.leads.lead-source-id",
            "installer:seeders.attributes.leads.source"
        ]
    },
    "lead_type": {
        "english": "Lead type",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "النوع / الأنواع"
        ],
        "strings": [
            "الأنواع",
            "النوع",
            "نوع",
            "نوع العميل المحتمل"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.types",
            "admin:activities.edit.type",
            "admin:activities.index.datagrid.type",
            "admin:dashboard.index.pdf.type",
            "admin:layouts.types",
            "admin:layouts.types-info",
            "admin:leads.index.datagrid.lead-type",
            "admin:leads.index.kanban.columns.lead-type",
            "admin:leads.index.kanban.toolbar.card-settings.lead-type",
            "admin:settings.attributes.create.type",
            "admin:settings.attributes.edit.type",
            "admin:settings.attributes.index.datagrid.type",
            "admin:settings.data-transfer.imports.create.type",
            "admin:settings.data-transfer.imports.edit.type",
            "admin:settings.data-transfer.imports.index.datagrid.type",
            "admin:settings.marketing.campaigns.index.create.type",
            "admin:settings.workflows.create.type",
            "admin:settings.workflows.edit.type",
            "installer:seeders.attributes.leads.lead-type-id",
            "installer:seeders.attributes.leads.type",
            "installer:seeders.email.type"
        ]
    },
    "pipeline": {
        "english": "Pipeline",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "قناة المبيعات / قنوات البيع",
            "خط الأنابيب (literal pipe)"
        ],
        "strings": [
            "الأنابيب",
            "خط أنابيب",
            "خط الأنابيب",
            "خطوط الأنابيب",
            "قناة المبيعات",
            "قنوات البيع"
        ],
        "suspect": [
            "الأنابيب",
            "خط أنابيب",
            "خط الأنابيب",
            "خطوط الأنابيب",
            "قناة المبيعات",
            "قنوات البيع"
        ],
        "naming_keys": [
            "admin:acl.pipelines",
            "admin:layouts.pipelines",
            "admin:layouts.pipelines-info",
            "admin:settings.pipelines.create.save-btn",
            "admin:settings.pipelines.create.title",
            "admin:settings.pipelines.edit.save-btn",
            "admin:settings.pipelines.edit.title",
            "admin:settings.pipelines.index.create-btn",
            "admin:settings.pipelines.index.title",
            "admin:settings.webforms.create.pipeline",
            "admin:settings.webforms.edit.pipeline",
            "installer:seeders.attributes.leads.pipeline",
            "installer:seeders.lead.pipeline.default"
        ]
    },
    "pipeline_stage": {
        "english": "Pipeline stage",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "المرحلة / المراحل"
        ],
        "strings": [
            "المراحل",
            "المرحلة",
            "مرحلة"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:dashboard.index.pdf.stage",
            "admin:leads.index.datagrid.stage",
            "admin:settings.pipelines.create.delete-stage",
            "admin:settings.pipelines.create.stage-btn",
            "admin:settings.pipelines.create.stage-delete-success",
            "admin:settings.pipelines.create.stages",
            "admin:settings.pipelines.edit.delete-stage",
            "admin:settings.pipelines.edit.stage-btn",
            "admin:settings.pipelines.edit.stage-delete-success",
            "admin:settings.pipelines.edit.stages",
            "installer:seeders.attributes.leads.stage"
        ]
    },
    "pipeline_stage_won": {
        "english": "Pipeline stage: won",
        "class": "conflicting",
        "n_names": 3,
        "names": [
            "ربحت / فاز (buttons)",
            "المكتسب (dashboard)",
            "مُنتصر (seeded)"
        ],
        "strings": [
            "المكتسب",
            "المكتسبة",
            "المكتسبين",
            "ربحت",
            "فاز",
            "مُنتصر"
        ],
        "suspect": [
            "المكتسب",
            "المكتسبة",
            "المكتسبين",
            "ربحت",
            "فاز",
            "مُنتصر"
        ],
        "naming_keys": [
            "admin:dashboard.index.pdf.won",
            "admin:dashboard.index.revenue.won-revenue",
            "admin:dashboard.index.total-leads.won",
            "admin:leads.index.kanban.stages.won",
            "admin:leads.view.stages.won",
            "admin:settings.pipelines.create.won-stage",
            "admin:settings.pipelines.edit.won-stage",
            "installer:seeders.lead.pipeline.pipeline-stages.won"
        ]
    },
    "pipeline_stage_lost": {
        "english": "Pipeline stage: lost",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "خسرت / خسر (buttons)",
            "مفقود (seeded)"
        ],
        "strings": [
            "المفقود",
            "المفقودة",
            "المفقودين",
            "خسر",
            "خسرت",
            "مفقود"
        ],
        "suspect": [
            "المفقود",
            "المفقودة",
            "المفقودين",
            "خسر",
            "خسرت",
            "مفقود"
        ],
        "naming_keys": [
            "admin:dashboard.index.pdf.lost",
            "admin:dashboard.index.revenue.lost-revenue",
            "admin:dashboard.index.total-leads.lost",
            "admin:leads.index.kanban.stages.lost",
            "admin:leads.view.stages.lost",
            "admin:settings.pipelines.create.lost-stage",
            "admin:settings.pipelines.edit.lost-stage",
            "installer:seeders.lead.pipeline.pipeline-stages.lost"
        ]
    },
    "rotten": {
        "english": "Rotten / stale-lead indicator",
        "class": "conflicting",
        "n_names": 3,
        "names": [
            "التعفن / متعفن",
            "مهمل (neglected)",
            "الفاسد (literal rotten)"
        ],
        "strings": [
            "أيام التعفن",
            "التعفن",
            "الفاسد",
            "متعفن",
            "مهمل"
        ],
        "suspect": [
            "أيام التعفن",
            "التعفن",
            "الفاسد",
            "متعفن"
        ],
        "naming_keys": [
            "admin:leads.index.datagrid.rotten-lead",
            "admin:leads.index.kanban.rotten-days",
            "admin:leads.index.kanban.toolbar.card-settings.rotten-indicator",
            "admin:mail.view.rotten-days",
            "admin:settings.pipelines.create.rotten-days",
            "admin:settings.pipelines.edit.rotten-days",
            "admin:settings.pipelines.index.datagrid.rotten-days"
        ]
    },
    "probability": {
        "english": "Stage probability",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "الاحتمالية"
        ],
        "strings": [
            "الاحتمالية"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:settings.pipelines.create.probability",
            "admin:settings.pipelines.edit.probability"
        ]
    },
    "person": {
        "english": "Person (contact record)",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "شخص / أشخاص"
        ],
        "strings": [
            "الأشخاص",
            "الشخص",
            "شخص"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.persons",
            "admin:activities.edit.persons",
            "admin:components.activities.actions.activity.participants.persons",
            "admin:components.layouts.header.mega-search.tabs.persons",
            "admin:configuration.index.general.settings.menu.persons",
            "admin:contacts.organizations.index.datagrid.persons-count",
            "admin:contacts.persons.create.save-btn",
            "admin:contacts.persons.create.title",
            "admin:contacts.persons.edit.save-btn",
            "admin:contacts.persons.edit.title",
            "admin:contacts.persons.index.create-btn",
            "admin:contacts.persons.index.title",
            "admin:contacts.persons.view.about-person",
            "admin:dashboard.index.pdf.person",
            "admin:layouts.person",
            "admin:layouts.persons",
            "admin:leads.view.persons.title",
            "admin:quotes.index.datagrid.person",
            "admin:quotes.index.pdf.person",
            "admin:settings.attributes.index.datagrid.entity-types.persons",
            "admin:settings.data-transfer.importers.persons.title",
            "admin:settings.webforms.create.person",
            "admin:settings.webforms.edit.person",
            "installer:seeders.attributes.quotes.person",
            "installer:seeders.attributes.quotes.person-id"
        ]
    },
    "organization": {
        "english": "Organization",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "المنظمة / المنظمات",
            "المؤسسة / المؤسسات"
        ],
        "strings": [
            "المؤسسات",
            "المؤسسة",
            "المنظمات",
            "المنظمة",
            "مؤسسة"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.organizations",
            "admin:configuration.index.general.settings.menu.organizations",
            "admin:contacts.organizations.create.save-btn",
            "admin:contacts.organizations.create.title",
            "admin:contacts.organizations.edit.save-btn",
            "admin:contacts.organizations.edit.title",
            "admin:contacts.organizations.index.create-btn",
            "admin:contacts.organizations.index.title",
            "admin:contacts.persons.index.datagrid.organization-name",
            "admin:contacts.persons.view.about-organization",
            "admin:layouts.organization",
            "admin:layouts.organizations",
            "admin:leads.common.contact.organization",
            "admin:settings.attributes.index.datagrid.entity-types.organizations",
            "installer:seeders.attributes.persons.organization",
            "installer:seeders.attributes.persons.organization-id"
        ]
    },
    "contacts": {
        "english": "Contacts (persons + organizations)",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "جهات الاتصال"
        ],
        "strings": [
            "جهات اتصال",
            "جهات الاتصال"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.contacts",
            "admin:configuration.index.general.settings.menu.contacts",
            "admin:export.google-contacts",
            "admin:layouts.contacts",
            "admin:settings.google-contacts.index.title"
        ]
    },
    "contact_person": {
        "english": "Contact person (linked person on a record)",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "شخص الاتصال",
            "جهة الاتصال"
        ],
        "strings": [
            "جهة اتصال",
            "جهة الاتصال",
            "شخص الاتصال"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:leads.create.contact-info",
            "admin:leads.create.contact-person",
            "admin:leads.edit.contact-info",
            "admin:leads.edit.contact-person",
            "admin:leads.index.datagrid.contact-person",
            "admin:leads.index.kanban.columns.contact-person",
            "admin:leads.index.kanban.toolbar.card-settings.contact-person"
        ]
    },
    "quote": {
        "english": "Quote",
        "class": "conflicting",
        "n_names": 5,
        "names": [
            "عروض الأسعار (quotation)",
            "الاقتباس (citation)",
            "عرض السعر",
            "العروض / عروض",
            "عرض (bare; homograph: view)"
        ],
        "strings": [
            "الاقتباسات",
            "العروض",
            "عرض",
            "عرض السعر",
            "عرض سعر",
            "عروض",
            "عروض الأسعار"
        ],
        "suspect": [
            "الاقتباسات"
        ],
        "naming_keys": [
            "admin:acl.quotes",
            "admin:components.activities.index.quotes",
            "admin:components.layouts.header.mega-search.tabs.quotes",
            "admin:configuration.index.general.settings.menu.quotes",
            "admin:contacts.persons.view.activities.index.quotes",
            "admin:layouts.quote",
            "admin:layouts.quotes",
            "admin:leads.view.quotes.add-btn",
            "admin:leads.view.quotes.empty-title",
            "admin:leads.view.quotes.mail-subject",
            "admin:leads.view.tabs.quotes",
            "admin:notifications.titles.quote-created",
            "admin:notifications.titles.quote-updated",
            "admin:quotes.create.quote-info",
            "admin:quotes.create.quote-items",
            "admin:quotes.create.quote-name",
            "admin:quotes.create.save-btn",
            "admin:quotes.create.title",
            "admin:quotes.edit.quote-info",
            "admin:quotes.edit.quote-items",
            "admin:quotes.edit.quote-name",
            "admin:quotes.edit.save-btn",
            "admin:quotes.edit.title",
            "admin:quotes.index.create-btn",
            "admin:quotes.index.pdf.quote-id",
            "admin:quotes.index.pdf.title",
            "admin:quotes.index.title",
            "admin:settings.attributes.index.datagrid.entity-types.quotes"
        ]
    },
    "product": {
        "english": "Product",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "المنتج / المنتجات"
        ],
        "strings": [
            "المنتج",
            "المنتجات",
            "منتج",
            "منتجات"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.products",
            "admin:components.layouts.header.mega-search.tabs.products",
            "admin:configuration.index.general.settings.menu.products",
            "admin:dashboard.index.pdf.product",
            "admin:layouts.product",
            "admin:layouts.products",
            "admin:leads.common.products.product-name",
            "admin:leads.create.products",
            "admin:leads.create.products-info",
            "admin:leads.edit.products",
            "admin:leads.edit.products-info",
            "admin:leads.view.products.add-product",
            "admin:leads.view.products.empty-title",
            "admin:leads.view.products.product-name",
            "admin:leads.view.tabs.products",
            "admin:mail.view.product",
            "admin:notifications.titles.product-created",
            "admin:notifications.titles.product-updated",
            "admin:products.create.save-btn",
            "admin:products.create.title",
            "admin:products.edit.save-btn",
            "admin:products.edit.title",
            "admin:products.index.create-btn",
            "admin:products.index.datagrid.sku",
            "admin:products.index.title",
            "admin:products.view.attributes.about-product",
            "admin:products.view.sku",
            "admin:quotes.create.product-name",
            "admin:quotes.edit.product-name",
            "admin:quotes.index.pdf.product-name",
            "admin:settings.attributes.index.datagrid.entity-types.products",
            "admin:settings.data-transfer.importers.products.title",
            "admin:settings.warehouses.index.datagrid.products"
        ]
    },
    "activity": {
        "english": "Activity",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "النشاط / الأنشطة"
        ],
        "strings": [
            "أنشطة",
            "الأنشطة",
            "النشاط",
            "نشاط"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.activities",
            "admin:activities.edit.save-btn",
            "admin:activities.edit.title",
            "admin:activities.edit.type",
            "admin:activities.index.title",
            "admin:components.activities.actions.activity.btn",
            "admin:components.activities.actions.activity.save-btn",
            "admin:components.activities.actions.activity.title",
            "admin:components.activities.index.empty-placeholders.all.title",
            "admin:components.activities.index.empty-placeholders.planned.title",
            "admin:configuration.index.general.settings.menu.activities",
            "admin:contacts.persons.view.activities.actions.activity.btn",
            "admin:contacts.persons.view.activities.actions.activity.save-btn",
            "admin:contacts.persons.view.activities.actions.activity.title",
            "admin:layouts.activities"
        ]
    },
    "activity_type": {
        "english": "Activity type (call / meeting / lunch)",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "مكالمة / اجتماع / غداء — three values, one name each (corrected 2026-11-04)"
        ],
        "strings": [
            "اجتماع",
            "اجتماعات",
            "الاجتماعات",
            "الغداء",
            "الغداءات",
            "المكالمات",
            "غداءات",
            "مكالمات",
            "مكالمة"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:activities.edit.call",
            "admin:activities.edit.meeting",
            "admin:activities.index.datagrid.call",
            "admin:activities.index.datagrid.meeting",
            "admin:components.activities.actions.activity.call",
            "admin:components.activities.actions.activity.meeting",
            "admin:components.activities.index.calls",
            "admin:components.activities.index.empty-placeholders.calls.title",
            "admin:components.activities.index.empty-placeholders.lunches.description",
            "admin:components.activities.index.empty-placeholders.lunches.title",
            "admin:components.activities.index.empty-placeholders.meetings.title",
            "admin:components.activities.index.lunches",
            "admin:components.activities.index.meetings",
            "admin:contacts.persons.view.activities.actions.activity.call",
            "admin:contacts.persons.view.activities.actions.activity.meeting",
            "admin:contacts.persons.view.activities.index.calls",
            "admin:contacts.persons.view.activities.index.lunches",
            "admin:contacts.persons.view.activities.index.meetings"
        ]
    },
    "warehouse": {
        "english": "Warehouse",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "المستودع / المستودعات"
        ],
        "strings": [
            "المستودع",
            "المستودعات",
            "مستودع"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.warehouses",
            "admin:layouts.warehouse",
            "admin:layouts.warehouses",
            "admin:layouts.warehouses-info",
            "admin:products.view.inventory.warehouse",
            "admin:settings.attributes.index.datagrid.entity-types.warehouses",
            "admin:settings.warehouses.create.save-btn",
            "admin:settings.warehouses.create.title",
            "admin:settings.warehouses.edit.save-btn",
            "admin:settings.warehouses.edit.title",
            "admin:settings.warehouses.index.create-btn",
            "admin:settings.warehouses.index.title"
        ]
    },
    "inventory": {
        "english": "Inventory / stock",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "المخزون"
        ],
        "strings": [
            "المخزون"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.inventory",
            "admin:layouts.inventory",
            "admin:layouts.inventory-info",
            "admin:products.index.datagrid.in-stock",
            "admin:products.view.inventories",
            "admin:products.view.inventory.in-stock"
        ]
    },
    "tag": {
        "english": "Tag",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "الوسم / الوسوم",
            "العلامة / العلامات"
        ],
        "strings": [
            "العلامات",
            "العلامة",
            "الوسم",
            "الوسوم",
            "علامة",
            "وسم"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.tags",
            "admin:components.tags.index.added-tags",
            "admin:components.tags.index.placeholder",
            "admin:components.tags.index.save-btn",
            "admin:components.tags.index.title",
            "admin:contacts.persons.index.datagrid.tag-name",
            "admin:layouts.tags",
            "admin:layouts.tags-info",
            "admin:leads.index.datagrid.tag-name",
            "admin:leads.index.kanban.columns.tags",
            "admin:leads.index.kanban.toolbar.card-settings.tags",
            "admin:mail.index.datagrid.tags",
            "admin:products.index.datagrid.tag-name",
            "admin:settings.tags.index.create-btn",
            "admin:settings.tags.index.create.save-btn",
            "admin:settings.tags.index.create.title",
            "admin:settings.tags.index.edit.title",
            "admin:settings.tags.index.title",
            "admin:settings.workflows.helpers.add-tag"
        ]
    },
    "attribute": {
        "english": "Attribute (custom field)",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "السمة / السمات",
            "الخاصية / الخصائص"
        ],
        "strings": [
            "الخاصية",
            "الخصائص",
            "السمات",
            "السمة",
            "خاصية",
            "سمة"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.attributes",
            "admin:layouts.attribute",
            "admin:layouts.attributes",
            "admin:layouts.attributes-info",
            "admin:settings.attributes.create.add-attribute-options",
            "admin:settings.attributes.create.save-btn",
            "admin:settings.attributes.create.title",
            "admin:settings.attributes.edit.add-attribute-options",
            "admin:settings.attributes.edit.option-deleted",
            "admin:settings.attributes.edit.save-btn",
            "admin:settings.attributes.edit.title",
            "admin:settings.attributes.index.create-btn",
            "admin:settings.attributes.index.title",
            "admin:settings.webforms.create.add-attribute-btn",
            "admin:settings.webforms.create.attribute-label-color",
            "admin:settings.webforms.create.attributes",
            "admin:settings.webforms.edit.add-attribute-btn",
            "admin:settings.webforms.edit.attribute-label-color",
            "admin:settings.webforms.edit.attributes"
        ]
    },
    "role": {
        "english": "Role",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "الدور / الأدوار"
        ],
        "strings": [
            "الأدوار",
            "الدور",
            "دور"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.roles",
            "admin:layouts.role",
            "admin:layouts.roles",
            "admin:layouts.roles-info",
            "admin:settings.roles.create.save-btn",
            "admin:settings.roles.create.title",
            "admin:settings.roles.edit.save-btn",
            "admin:settings.roles.edit.title",
            "admin:settings.roles.index.create-btn",
            "admin:settings.roles.index.title",
            "admin:settings.users.index.create.role",
            "installer:seeders.user.role.administrator-role"
        ]
    },
    "group": {
        "english": "Group",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "المجموعة / المجموعات"
        ],
        "strings": [
            "المجموعات",
            "المجموعة",
            "مجموعة"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.groups",
            "admin:layouts.groups",
            "admin:layouts.groups-info",
            "admin:settings.groups.index.create-btn",
            "admin:settings.groups.index.create.save-btn",
            "admin:settings.groups.index.create.title",
            "admin:settings.groups.index.edit.title",
            "admin:settings.groups.index.title",
            "admin:settings.users.index.create.group",
            "admin:settings.users.index.datagrid.group"
        ]
    },
    "user": {
        "english": "User",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "المستخدم / المستخدمون"
        ],
        "strings": [
            "المستخدم",
            "المستخدمون",
            "المستخدمين",
            "مستخدم"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.user",
            "admin:acl.users",
            "admin:activities.edit.users",
            "admin:components.activities.actions.activity.participants.users",
            "admin:help.index.resources.user-docs.title",
            "admin:layouts.user",
            "admin:layouts.users",
            "admin:layouts.users-info",
            "admin:settings.tags.index.datagrid.users",
            "admin:settings.users.index.create-btn",
            "admin:settings.users.index.create.save-btn",
            "admin:settings.users.index.create.title",
            "admin:settings.users.index.datagrid.users",
            "admin:settings.users.index.edit.title",
            "admin:settings.users.index.title"
        ]
    },
    "sales_owner": {
        "english": "Sales owner",
        "class": "conflicting",
        "n_names": 3,
        "names": [
            "مندوب المبيعات",
            "مسؤول المبيعات",
            "مالك المبيعات"
        ],
        "strings": [
            "مالك المبيعات",
            "مسؤول المبيعات",
            "مندوب المبيعات"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:leads.index.datagrid.sales-person",
            "admin:leads.index.kanban.columns.sales-person",
            "admin:leads.index.kanban.toolbar.card-settings.sales-person",
            "admin:quotes.index.datagrid.sales-person",
            "admin:quotes.index.pdf.sales-person",
            "admin:settings.workflows.helpers.send-email-to-sales-owner",
            "installer:seeders.attributes.leads.sales-owner",
            "installer:seeders.attributes.leads.user-id",
            "installer:seeders.attributes.organizations.sales-owner",
            "installer:seeders.attributes.organizations.user-id",
            "installer:seeders.attributes.persons.sales-owner",
            "installer:seeders.attributes.persons.user-id",
            "installer:seeders.attributes.quotes.sales-owner",
            "installer:seeders.attributes.quotes.user-id"
        ]
    },
    "email_template": {
        "english": "Email template",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "قالب البريد الإلكتروني",
            "نموذج البريد الإلكتروني"
        ],
        "strings": [
            "قالب البريد الإلكتروني",
            "قوالب البريد الإلكتروني",
            "نموذج البريد الإلكتروني"
        ],
        "suspect": [
            "نموذج البريد الإلكتروني"
        ],
        "naming_keys": [
            "admin:acl.email-templates",
            "admin:layouts.email-templates",
            "admin:layouts.email-templates-info",
            "admin:settings.email-template.create.email-template",
            "admin:settings.email-template.create.save-btn",
            "admin:settings.email-template.edit.email-template",
            "admin:settings.email-template.edit.save-btn",
            "admin:settings.email-template.edit.title",
            "admin:settings.email-template.index.title",
            "admin:settings.marketing.campaigns.index.create.email-template"
        ]
    },
    "email_settings": {
        "english": "Email / IMAP-SMTP settings",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "إعدادات البريد الإلكتروني (the section; إعدادات IMAP / حساب IMAP are subsection titles — corrected 2026-11-04)"
        ],
        "strings": [
            "IMAP",
            "إعدادات IMAP",
            "إعدادات البريد الإلكتروني",
            "حساب IMAP"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:configuration.index.email.imap.account.password",
            "admin:configuration.index.email.imap.account.title",
            "admin:configuration.index.email.imap.account.username",
            "admin:configuration.index.email.imap.title",
            "admin:configuration.index.email.title"
        ]
    },
    "workflow": {
        "english": "Workflow",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "سير العمل"
        ],
        "strings": [
            "سير العمل",
            "سير عمل"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.workflows",
            "admin:layouts.workflows",
            "admin:layouts.workflows-info",
            "admin:settings.workflows.create.save-btn",
            "admin:settings.workflows.create.title",
            "admin:settings.workflows.edit.save-btn",
            "admin:settings.workflows.edit.title",
            "admin:settings.workflows.index.create-btn",
            "admin:settings.workflows.index.title"
        ]
    },
    "automation": {
        "english": "Automation",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "الأتمتة"
        ],
        "strings": [
            "الأتمتة"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.automation",
            "admin:layouts.automation"
        ]
    },
    "campaign": {
        "english": "Campaign",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "الحملة / الحملات"
        ],
        "strings": [
            "الحملات",
            "الحملة",
            "حملة"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.campaigns",
            "admin:layouts.campaigns",
            "admin:layouts.campaigns-info",
            "admin:settings.marketing.campaigns.index.create-btn",
            "admin:settings.marketing.campaigns.index.create.title",
            "admin:settings.marketing.campaigns.index.edit.title",
            "admin:settings.marketing.campaigns.index.title"
        ]
    },
    "event": {
        "english": "Event",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "الحدث / الأحداث",
            "الفعالية / الفعاليات"
        ],
        "strings": [
            "الأحداث",
            "الحدث",
            "الفعاليات",
            "حدث"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.event",
            "admin:layouts.events",
            "admin:layouts.events-info",
            "admin:settings.marketing.campaigns.index.create.event",
            "admin:settings.marketing.events.index.create-btn",
            "admin:settings.marketing.events.index.create.save-btn",
            "admin:settings.marketing.events.index.create.title",
            "admin:settings.marketing.events.index.edit.title",
            "admin:settings.marketing.events.index.mass-delete-success",
            "admin:settings.marketing.events.index.title",
            "admin:settings.workflows.create.event",
            "admin:settings.workflows.edit.event"
        ]
    },
    "webhook": {
        "english": "Webhook",
        "class": "conflicting",
        "n_names": 3,
        "names": [
            "ويب هوك",
            "Webhook (left in English)",
            "الويب هوك"
        ],
        "strings": [
            "Webhook",
            "الويب هوك",
            "الويب هوكس",
            "ويب هوك"
        ],
        "suspect": [
            "Webhook",
            "الويب هوك",
            "الويب هوكس",
            "ويب هوك"
        ],
        "naming_keys": [
            "admin:acl.webhook",
            "admin:layouts.webhooks",
            "admin:layouts.webhooks-info",
            "admin:settings.webhooks.create.info",
            "admin:settings.webhooks.create.save-btn",
            "admin:settings.webhooks.create.title",
            "admin:settings.webhooks.edit.edit-btn",
            "admin:settings.webhooks.edit.info",
            "admin:settings.webhooks.edit.save-btn",
            "admin:settings.webhooks.edit.title",
            "admin:settings.webhooks.index.create-btn",
            "admin:settings.webhooks.index.title",
            "admin:settings.workflows.helpers.add-webhook"
        ]
    },
    "web_form": {
        "english": "Web form",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "نماذج الويب",
            "نموذج الويب"
        ],
        "strings": [
            "نماذج الويب",
            "نموذج الويب",
            "نموذج ويب"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:settings.webforms.create.customize-webform",
            "admin:settings.webforms.create.save-btn",
            "admin:settings.webforms.edit.customize-webform",
            "admin:settings.webforms.edit.save-btn",
            "admin:settings.webforms.index.create-btn",
            "admin:settings.webforms.index.title",
            "webform:acl.title",
            "webform:menu.title"
        ]
    },
    "data_transfer": {
        "english": "Data transfer / import / export",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "نقل البيانات",
            "الاستيراد / استيراد"
        ],
        "strings": [
            "استيراد",
            "الاستيراد",
            "الاستيرادات",
            "تصدير",
            "نقل البيانات"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.data-transfer",
            "admin:acl.import",
            "admin:acl.imports",
            "admin:layouts.data_transfer",
            "admin:layouts.data_transfer_info",
            "admin:settings.data-transfer.imports.create.save-btn",
            "admin:settings.data-transfer.imports.create.title",
            "admin:settings.data-transfer.imports.edit.save-btn",
            "admin:settings.data-transfer.imports.edit.title",
            "admin:settings.data-transfer.imports.import.importing-info",
            "admin:settings.data-transfer.imports.import.title",
            "admin:settings.data-transfer.imports.index.button-title",
            "admin:settings.data-transfer.imports.index.title",
            "admin:settings.data-transfer.imports.not-valid"
        ]
    },
    "dashboard": {
        "english": "Dashboard",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "لوحة القيادة",
            "لوحة التحكم (this page, 5 keys only; the 2 keys that mean the admin panel are excluded — corrected 2026-11-04)"
        ],
        "strings": [
            "لوحة التحكم",
            "لوحة القيادة"
        ],
        "suspect": [
            "لوحة القيادة"
        ],
        "naming_keys": [
            "admin:configuration.index.general.settings.dashboard.date-range-info",
            "admin:configuration.index.general.settings.dashboard.info",
            "admin:configuration.index.general.settings.dashboard.title",
            "admin:configuration.index.general.settings.menu.dashboard",
            "admin:dashboard.index.pdf.title",
            "admin:dashboard.index.title",
            "admin:errors.dashboard",
            "admin:layouts.dashboard"
        ]
    },
    "settings": {
        "english": "Settings (card page)",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "الإعدادات"
        ],
        "strings": [
            "الإعدادات"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.settings",
            "admin:layouts.settings"
        ]
    },
    "configuration": {
        "english": "Configuration (settings tree)",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "التهيئة",
            "التكوين"
        ],
        "strings": [
            "التكوين",
            "التهيئة",
            "تكوين"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.configuration",
            "admin:configuration.index.general.info",
            "admin:configuration.index.general.settings.menu.configuration",
            "admin:configuration.index.save-btn",
            "admin:configuration.index.title"
        ]
    },
    "mail": {
        "english": "Mail module",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "البريد"
        ],
        "strings": [
            "البريد",
            "البريد الإلكتروني"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.mail",
            "admin:components.activities.actions.mail.enter-emails",
            "admin:configuration.index.general.settings.menu.mail",
            "admin:layouts.mail.inbox",
            "admin:layouts.mail.title",
            "admin:leads.view.quotes.mail",
            "admin:mail.index.btn",
            "admin:mail.index.datagrid.move-to-inbox",
            "admin:mail.index.mail.enter-emails",
            "admin:mail.view.enter-mails",
            "admin:mail.view.link-mail"
        ]
    },
    "mail_inbox": {
        "english": "Mail folder: inbox",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "الوارد",
            "البريد الوارد"
        ],
        "strings": [
            "البريد الوارد",
            "الوارد"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.inbox",
            "admin:configuration.index.general.settings.menu.inbox",
            "admin:layouts.mail.inbox",
            "admin:mail.index.inbox"
        ]
    },
    "mail_sent": {
        "english": "Mail folder: sent",
        "class": "conflicting",
        "n_names": 2,
        "names": [
            "المرسل",
            "تم الإرسال (past tense used as folder name)"
        ],
        "strings": [
            "المرسل",
            "تم الإرسال"
        ],
        "suspect": [
            "تم الإرسال"
        ],
        "naming_keys": [
            "admin:acl.sent",
            "admin:configuration.index.general.settings.menu.sent",
            "admin:layouts.mail.sent",
            "admin:mail.index.sent"
        ]
    },
    "mail_outbox": {
        "english": "Mail folder: outbox",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "الصادر"
        ],
        "strings": [
            "الصادر"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.outbox",
            "admin:configuration.index.general.settings.menu.outbox",
            "admin:layouts.mail.outbox",
            "admin:mail.index.outbox"
        ]
    },
    "mail_draft": {
        "english": "Mail folder: drafts",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "المسودات"
        ],
        "strings": [
            "المسودات",
            "مسودة"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.draft",
            "admin:configuration.index.general.settings.menu.draft",
            "admin:layouts.mail.draft",
            "admin:mail.index.draft",
            "admin:mail.index.mail.draft"
        ]
    },
    "mail_trash": {
        "english": "Mail folder: trash",
        "class": "conflicting",
        "n_names": 3,
        "names": [
            "المحذوفات",
            "المهملات",
            "سلة المهملات"
        ],
        "strings": [
            "المحذوفات",
            "المهملات",
            "سلة المهملات"
        ],
        "suspect": [
            "المحذوفات"
        ],
        "naming_keys": [
            "admin:acl.trash",
            "admin:configuration.index.general.settings.menu.trash",
            "admin:layouts.mail.trash",
            "admin:mail.index.trash"
        ]
    },
    "menu_item": {
        "english": "Sidebar menu entry label",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "عناصر القائمة"
        ],
        "strings": [
            "إعدادات عناصر القائمة",
            "عناصر القائمة"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:configuration.index.general.settings.menu-color.title",
            "admin:configuration.index.general.settings.menu.title"
        ]
    },
    "mega_search": {
        "english": "Global (mega) search",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "البحث الشامل"
        ],
        "strings": [
            "البحث الشامل"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:components.layouts.header.mega-search.title"
        ]
    },
    "notification": {
        "english": "Notification",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "الإشعارات"
        ],
        "strings": [
            "الإشعارات"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:configuration.index.general.settings.notifications.enabled",
            "admin:configuration.index.general.settings.notifications.title",
            "admin:notifications.title"
        ]
    },
    "quick_add": {
        "english": "Quick add",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "إضافة سريعة"
        ],
        "strings": [
            "إضافة سريعة"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:acl.quick_add",
            "admin:layouts.quick-add.title",
            "admin:settings.attributes.create.quick_add",
            "admin:settings.attributes.edit.quick_add",
            "admin:settings.attributes.index.datagrid.quick-add"
        ]
    },
    "address": {
        "english": "Billing / shipping address",
        "class": "conflicting",
        "n_names": 3,
        "names": [
            "عنوان الفوترة (invoicing)",
            "عنوان الشحن",
            "عنوان الفواتير (invoices)"
        ],
        "strings": [
            "عنوان الشحن",
            "عنوان الفواتير",
            "عنوان الفوترة"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:quotes.index.pdf.billing-address",
            "admin:quotes.index.pdf.shipping-address",
            "installer:seeders.attributes.quotes.billing-address",
            "installer:seeders.attributes.quotes.shipping-address"
        ]
    },
    "record_id": {
        "english": "Record identifier column",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "المعرف"
        ],
        "strings": [
            "المعرف"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:activities.index.datagrid.id",
            "admin:contacts.organizations.index.datagrid.id",
            "admin:contacts.persons.index.datagrid.id",
            "admin:leads.view.quotes.id",
            "admin:mail.index.datagrid.id",
            "admin:products.index.datagrid.id",
            "admin:settings.attributes.index.datagrid.id",
            "admin:settings.email-template.index.datagrid.id",
            "admin:settings.groups.index.datagrid.id",
            "admin:settings.marketing.campaigns.index.datagrid.id",
            "admin:settings.marketing.events.index.datagrid.id",
            "admin:settings.pipelines.index.datagrid.id",
            "admin:settings.roles.index.datagrid.id",
            "admin:settings.sources.index.datagrid.id",
            "admin:settings.tags.index.datagrid.id",
            "admin:settings.types.index.datagrid.id",
            "admin:settings.users.index.datagrid.id",
            "admin:settings.warehouses.index.datagrid.id",
            "admin:settings.webforms.index.datagrid.id",
            "admin:settings.webhooks.index.datagrid.id",
            "admin:settings.workflows.index.datagrid.id"
        ]
    },
    "sku": {
        "english": "SKU / product code",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "رمز المنتج"
        ],
        "strings": [
            "رمز المنتج"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:products.index.datagrid.sku",
            "admin:products.view.sku",
            "admin:quotes.index.pdf.sku"
        ]
    },
    "account": {
        "english": "My account",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "حسابي"
        ],
        "strings": [
            "حسابي"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:account.edit.title",
            "admin:layouts.my-account",
            "admin:user.account.my_account"
        ]
    },
    "login": {
        "english": "Sign-in screen",
        "class": "consistent",
        "n_names": 1,
        "names": [
            "تسجيل الدخول"
        ],
        "strings": [
            "تسجيل الدخول"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:users.login.submit-btn",
            "admin:users.login.title"
        ]
    },
    "password_reset": {
        "english": "Password reset / forgot-password flow",
        "class": "conflicting",
        "n_names": 3,
        "names": [
            "نسيت كلمة المرور",
            "استعادة كلمة المرور",
            "إعادة تعيين كلمة المرور"
        ],
        "strings": [
            "إعادة تعيين كلمة المرور",
            "استعادة كلمة المرور",
            "نسيت كلمة المرور"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:users.forget-password.create.page-title",
            "admin:users.forget-password.create.title",
            "admin:users.reset-password.submit-btn",
            "admin:users.reset-password.title"
        ]
    },
    "http_terminology": {
        "english": "HTTP vocabulary used by the webhook screens",
        "class": "conflicting",
        "n_names": 6,
        "names": [
            "نقطة النهاية",
            "الطريقة",
            "إرسال / تحديث (POST / PUT)",
            "الرؤوس",
            "الجسم",
            "حمولة"
        ],
        "strings": [
            "إرسال",
            "الجسم",
            "الرؤوس",
            "الطريقة",
            "تحديث",
            "حمولة",
            "رأس",
            "نقطة النهاية",
            "نقطة نهاية الرابط"
        ],
        "suspect": [],
        "naming_keys": [
            "admin:settings.webhooks.create.add-new-header",
            "admin:settings.webhooks.create.add-new-payload",
            "admin:settings.webhooks.create.body",
            "admin:settings.webhooks.create.headers",
            "admin:settings.webhooks.create.method",
            "admin:settings.webhooks.create.post",
            "admin:settings.webhooks.create.put",
            "admin:settings.webhooks.create.url-endpoint",
            "admin:settings.webhooks.index.datagrid.end-point"
        ]
    }
}
```

---

## Appendix B — the extraction script

One file, read-only against the checkout, no dependencies beyond the PHP the project
already requires. Save it anywhere and run it with the repository root as the argument.

```php
<?php
/**
 * arabic-naming-inventory.php — one re-runnable file that produces the whole
 * Arabic naming inventory for Sijilna.
 *
 *   php arabic-naming-inventory.php /path/to/crm-platform > inventory.json
 *
 * INPUTS (all read-only)
 *   packages/Webkul/Admin/src/Resources/lang/ar/app.php
 *   packages/Webkul/Installer/src/Resources/lang/ar/app.php          (seeded values)
 *   packages/Webkul/WebForm/src/Resources/lang/ar/app.php
 *   packages/Webkul/GoogleContact/src/Resources/lang/ar/app.php
 *   lang/ar/validation.php
 *   docs/client-guide/ar/*.md                                        (term occurrences)
 *
 * STEPS
 *   1. Flatten each lang file to  ns:dotted.key = value. Keep values that
 *      contain an Arabic letter  (ARABIC_SET) and, separately, count values that
 *      contain none  (LATIN_ONLY — untranslated or proper nouns).
 *   2. Assign every key to one or more concepts (CONCEPTS). A key can serve two
 *      concepts: "sales-person" is a SalesOwner column rendered on the Leads
 *      screen, so it belongs to both.
 *   3. Per concept, scan only that concept's own keys for the concept's Arabic
 *      surface forms (SYNONYMS), grouped into LEXEMES. One lexeme = one word =
 *      one name. شخص / الشخص / أشخاص / الأشخاص are one lexeme, not four.
 *   4. Classify every hit as NAMING SLOT (acl label, sidebar label, screen
 *      title, create button, entity-type label, seeded value, or a short label
 *      with no sentence terminator) or PROSE. Prose is reported, never votes.
 *   5. Extract the three naming layers per concept: the ACL label, the sidebar
 *      label and the screen title, straight out of that concept's key list.
 *   6. Count the same surface forms in docs/client-guide/ar/*.md.
 *
 * OUTPUT (stdout, JSON)
 *   summary            measured totals
 *   latin_only         values in the ar files that hold no Arabic letter
 *   concepts           concept -> english, class, n_renderings, renderings
 *                      (term -> locations), lexeme_forms, three_layer,
 *                      guide_files, keys
 */

$root = rtrim($argv[1] ?? getcwd(), '/');

$FILES = [
    'admin'         => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
    'installer'     => 'packages/Webkul/Installer/src/Resources/lang/ar/app.php',
    'webform'       => 'packages/Webkul/WebForm/src/Resources/lang/ar/app.php',
    'googlecontact' => 'packages/Webkul/GoogleContact/src/Resources/lang/ar/app.php',
    'validation'    => 'lang/ar/validation.php',
];

// concept => [English label, [key match patterns], [key veto patterns]]
$CONCEPTS = [
    'lead'                => ['Lead / opportunity record', ['(^|\.)leads?(-[a-z-]+)?(\.|$)'], ['lead-value|lead-type|lead-source|lead-pipeline|sales-person|\.file\.']],
    'lead_value'          => ['Lead value (deal amount)', ['lead-value|estimated-lead-value'], []],
    'lead_source'         => ['Lead source', ['(^|\.)(lead-)?sources?(\.|$|-)', 'revenue-by-sources'], []],
    'lead_type'           => ['Lead type', ['(^|\.)types?(\.|$|-)', 'lead-type'], ['entity-type|condition-type|option-type|lookup-type|permission-type|\.types\.|webhooks?\.']],
    'pipeline'            => ['Pipeline', ['(^|\.)pipelines?(\.|$|-)'], []],
    'pipeline_stage'      => ['Pipeline stage', ['(^|\.)stages?(\.|$|-)', 'add-stage', 'delete-stage', 'stage-btn'], ['won|lost']],
    'pipeline_stage_won'  => ['Pipeline stage: won', ['(^|\.)won(-[a-z]+)?$'], []],
    'pipeline_stage_lost' => ['Pipeline stage: lost', ['(^|\.)lost(-[a-z]+)?$'], []],
    'rotten'              => ['Rotten / stale-lead indicator', ['rotten'], []],
    'probability'         => ['Stage probability', ['probabilit'], []],
    'person'              => ['Person (contact record)', ['(^|\.)persons?(\.|$|-)', 'person-created', 'person-updated', 'about-person'], ['contact-person|sales-person|no-person']],
    'organization'        => ['Organization', ['(^|\.)organizations?(\.|$|-)', 'about-organization', 'organization-name'], []],
    'contacts'            => ['Contacts (persons + organizations)', ['\Aacl\.contacts$', '\Alayouts\.contacts$', 'menu\.contacts$', '(^|\.)contacts\.', 'google-contact'], []],
    'contact_person'      => ['Contact person (linked person on a record)', ['contact-person', 'attach-contact', 'link-to-contact', 'linked-contact', 'change-contact', 'contact-name', 'no-person'], []],
    'quote'               => ['Quote', ['(^|\.)quotes?(\.|$|-)'], []],
    'product'             => ['Product', ['(^|\.)products?(\.|$|-)'], []],
    'activity'            => ['Activity', ['(^|\.)activities(\.|$|-)'], []],
    'activity_type'       => ['Activity type (call / meeting / lunch)', ['(^|\.)(calls?|meetings?|lunches?)(\.|$|-)'], []],
    'warehouse'           => ['Warehouse', ['(^|\.)warehouses?(\.|$|-)'], []],
    'inventory'           => ['Inventory / stock', ['inventory(\.|$|-)?', 'inventories', 'in-stock', 'on-hand', 'allocated'], []],
    'tag'                 => ['Tag', ['(^|\.)tags?(\.|$|-)', 'tag-name', 'add-tag'], []],
    'attribute'           => ['Attribute (custom field)', ['(^|\.)attributes?(\.|$|-)', 'attribute-[a-z]', 'add-attribute'], []],
    'role'                => ['Role', ['(^|\.)roles?(\.|$|-)', 'administrator-role'], []],
    'group'               => ['Group', ['(^|\.)groups?(\.|$|-)'], []],
    'user'                => ['User', ['(^|\.)users?(\.|$|-)'], ['users?-id']],
    'sales_owner'         => ['Sales owner', ['sales-(owner|person)', 'users?-id$', '(\A|\.)owner$'], []],
    'email_template'      => ['Email template', ['email-templates?'], []],
    'email_settings'      => ['Email / IMAP-SMTP settings', ['configuration\.index\.email', 'email-settings', 'imap', 'smtp', 'mailer'], []],
    'workflow'            => ['Workflow', ['(^|\.)workflows?(\.|$|-)'], []],
    'automation'          => ['Automation', ['(^|\.)automation(\.|$)'], []],
    'campaign'            => ['Campaign', ['(^|\.)campaigns?(\.|$|-)'], []],
    'event'               => ['Event', ['(^|\.)events?(\.|$|-)'], []],
    'webhook'             => ['Webhook', ['(^|\.)webhooks?(\.|$|-)', 'add-webhook', 'create-webhook', 'no-webhook-found'], []],
    'web_form'            => ['Web form', ['(^|\.)webforms?(\.|$|-)'], []],
    'data_transfer'       => ['Data transfer / import / export', ['data-transfer|data_transfer', '(^|\.)imports?(\.|$|-)', '\Aexport\.', 'export-', '(^|\.)importing'], []],
    'dashboard'           => ['Dashboard', ['(^|\.)dashboard(\.|$)'], []],
    'settings'            => ['Settings (card page)', ['\Aacl\.settings$', '\Alayouts\.settings$', '\Asettings\.index'], []],
    'configuration'       => ['Configuration (settings tree)', ['(^|\.)configuration(\.|$)'], []],
    'mail'                => ['Mail module', ['(^|\.)mail(\.|$|-)'], []],
    'mail_inbox'          => ['Mail folder: inbox', ['(^|\.)inbox$'], []],
    'mail_sent'           => ['Mail folder: sent', ['(^|\.)sent$'], []],
    'mail_outbox'         => ['Mail folder: outbox', ['(^|\.)outbox$'], []],
    'mail_draft'          => ['Mail folder: drafts', ['(^|\.)(drafts?|draft)$'], []],
    'mail_trash'          => ['Mail folder: trash', ['(^|\.)trash$'], []],
    'menu_item'           => ['Sidebar menu entry label', ['menu-item', 'menu_color', 'menu-color', 'settings\.menu\.'], []],
    'mega_search'         => ['Global (mega) search', ['mega-search', 'global-search'], []],
    'notification'        => ['Notification', ['notifications?(\.|$|-)', 'notification-'], []],
    'quick_add'           => ['Quick add', ['quick_add', 'quick-add'], []],
    'address'             => ['Billing / shipping address', ['billing-address', 'shipping-address'], []],
    'record_id'           => ['Record identifier column', ['(^|\.)id$', 'datagrid\.id'], []],
    'sku'                 => ['SKU / product code', ['(^|\.)sku$', 'product-code'], []],
    'account'             => ['My account', ['(^|\.)account(\.|$)', 'my-account'], []],
    'login'               => ['Sign-in screen', ['\Ausers\.login'], []],
    'password_reset'      => ['Password reset / forgot-password flow', ['\Ausers\.(forget|reset)-password'], []],
    'http_terminology'    => ['HTTP vocabulary used by the webhook screens', ['webhooks?\.(index\.(datagrid\.end-point|invalid-endpoint)|create\.(url-and-parameters|method|post|put|url-endpoint|parameters|headers|add-new-header|body|add-new-payload|key-and-value))'], []],
];

// concept => lexeme (one word/name) => [surface forms observed for it]
$SYNONYMS = [
    'lead' => [
        'عميل محتمل'                  => ['العميل المحتمل', 'عميل محتمل'],
        'العملاء المحتملون'           => ['العملاء المحتملين المفتوحين', 'العملاء المحتملين', 'العملاء المحتملون', 'عملاء محتملين'],
        'العميل / العملاء (bare)'     => ['العميل', 'العملاء'],
        'الفرصة (opportunity)'        => ['الفرصة', 'الفرص', 'فرصة'],
        'القيادة (leadership)'        => ['القيادة'],
    ],
    'lead_value' => ['القيمة التقديرية للعميل المحتمل' => ['القيمة التقديرية للعميل المحتمل'], 'قيمة الربح' => ['قيمة الربح']],
    'lead_source' => ['المصدر / المصادر' => ['المصادر', 'المصدر', 'مصادر', 'مصدر']],
    'lead_type' => ['النوع / الأنواع' => ['نوع العميل المحتمل', 'الأنواع', 'النوع', 'أنواع', 'نوع']],
    'pipeline' => [
        'قناة المبيعات / قنوات البيع' => ['قنوات البيع', 'قناة المبيعات'],
        'خط الأنابيب (literal pipe)'  => ['خطوط الأنابيب', 'خط الأنابيب', 'خط أنابيب', 'الأنابيب', 'أنابيب'],
        'المسار (path)'               => ['المسارات', 'مسار'],
    ],
    'pipeline_stage' => ['المرحلة / المراحل' => ['المراحل', 'المرحلة', 'مراحل', 'مرحلة']],
    'pipeline_stage_won' => [
        'مُنتصر (seeded)'      => ['مُنتصر', 'منتصر'],
        'ربحت / فاز (buttons)' => ['ربحت', 'فاز'],
        'المكتسب (dashboard)'  => ['المكتسبة', 'المكتسبين', 'المكتسب'],
    ],
    'pipeline_stage_lost' => [
        'مفقود (seeded)'       => ['المفقودة', 'المفقودين', 'المفقود', 'مفقود'],
        'خسرت / خسر (buttons)' => ['خسرت', 'خسر'],
    ],
    'rotten' => [
        'التعفن / متعفن'          => ['أيام التعفن', 'التعفن', 'متعفن'],
        'الفاسد (literal rotten)' => ['الفاسد', 'فاسد'],
        'مهمل (neglected)'        => ['مهمل'],
    ],
    'probability' => ['الاحتمالية' => ['الاحتمالية']],
    'person' => ['شخص / أشخاص' => ['الأشخاص', 'أشخاص', 'الشخص', 'شخص']],
    'organization' => [
        'المؤسسة / المؤسسات' => ['المؤسسات', 'مؤسسات', 'المؤسسة', 'مؤسسة'],
        'المنظمة / المنظمات' => ['المنظمات', 'منظمات', 'المنظمة', 'منظمة'],
    ],
    'contacts' => ['جهات الاتصال' => ['جهات الاتصال', 'جهة الاتصال', 'جهات اتصال', 'جهة اتصال']],
    'contact_person' => ['شخص الاتصال / جهة الاتصال' => ['شخص الاتصال', 'جهة الاتصال', 'جهة اتصال']],
    'quote' => [
        'عرض السعر'                   => ['عرض السعر', 'عرض سعر'],
        'عروض الأسعار (quotation)'    => ['عروض الأسعار'],
        'العروض / عروض'               => ['العروض', 'عروض'],
        'عرض (bare; homograph: view)' => ['عرض'],
        'الاقتباس (citation)'         => ['الاقتباسات', 'الاقتباس', 'اقتباس'],
    ],
    'product' => ['المنتج / المنتجات' => ['المنتجات', 'منتجات', 'المنتج', 'منتج']],
    'activity' => ['النشاط / الأنشطة' => ['الأنشطة', 'أنشطة', 'النشاط', 'نشاط']],
    'activity_type' => [
        'مكالمة / مكالمات'   => ['المكالمات', 'مكالمات', 'مكالمة'],
        'اجتماع / اجتماعات'  => ['الاجتماعات', 'اجتماعات', 'اجتماع'],
        'غداء / الغداءات'    => ['الغداءات', 'الغداء', 'غداء'],
    ],
    'warehouse' => ['المستودع / المستودعات' => ['المستودعات', 'مستودعات', 'المستودع', 'مستودع']],
    'inventory' => ['المخزون' => ['المخزون', 'مخزون']],
    'tag' => [
        'الوسم / الوسوم'      => ['الوسوم', 'وسوم', 'الوسم', 'وسم'],
        'العلامة / العلامات'  => ['العلامات', 'العلامة', 'علامة'],
    ],
    'attribute' => [
        'السمة / السمات'       => ['السمات', 'سمات', 'السمة', 'سمة'],
        'الخاصية / الخصائص'    => ['الخصائص', 'الخاصية', 'خاصية'],
    ],
    'role' => ['الدور / الأدوار' => ['الأدوار', 'أدوار', 'الدور', 'دور']],
    'group' => ['المجموعة / المجموعات' => ['المجموعات', 'مجموعات', 'المجموعة', 'مجموعة']],
    'user' => ['المستخدم / المستخدمون' => ['المستخدمون', 'المستخدمين', 'مستخدمون', 'المستخدم', 'مستخدم']],
    'sales_owner' => [
        'مالك المبيعات'   => ['مالك المبيعات'],
        'مندوب المبيعات'  => ['مندوب المبيعات'],
        'مسؤول المبيعات'  => ['مسؤول المبيعات'],
    ],
    'email_template' => [
        'قالب البريد الإلكتروني'  => ['قوالب البريد الإلكتروني', 'قالب البريد الإلكتروني', 'قوالب البريد', 'قالب البريد'],
        'نموذج البريد الإلكتروني' => ['نموذج البريد الإلكتروني'],
    ],
    'email_settings' => [
        'إعدادات البريد الإلكتروني' => ['إعدادات البريد الإلكتروني'],
        'IMAP'                      => ['إعدادات IMAP', 'حساب IMAP', 'IMAP'],
        'SMTP'                      => ['SMTP'],
    ],
    'workflow' => ['سير العمل' => ['سير العمل', 'سير عمل']],
    'automation' => ['الأتمتة' => ['الأتمتة', 'أتمتة']],
    'campaign' => ['الحملة / الحملات' => ['الحملات', 'حملات', 'الحملة', 'حملة']],
    'event' => [
        'الحدث / الأحداث'          => ['الأحداث', 'الحدث', 'حدث'],
        'الفعالية / الفعاليات'     => ['الفعاليات', 'فعاليات', 'الفعالية', 'فعالية'],
    ],
    'webhook' => [
        'الويب هوك'                  => ['الويب هوكس', 'الويب هوك'],
        'ويب هوك'                    => ['ويب هوك'],
        'Webhook (left in English)'  => ['Webhooks', 'Webhook', 'webhooks', 'webhook'],
    ],
    'web_form' => [
        'نموذج الويب'   => ['نموذج الويب', 'نموذج ويب'],
        'نماذج الويب'   => ['نماذج الويب', 'نماذج ويب'],
    ],
    'data_transfer' => [
        'نقل البيانات'        => ['نقل البيانات'],
        'الاستيراد / استيراد' => ['الاستيرادات', 'الاستيراد', 'استيراد'],
        'التصدير / تصدير'     => ['التصدير', 'تصدير'],
    ],
    'dashboard' => [
        'لوحة القيادة'                => ['لوحة القيادة'],
        'لوحة التحكم (control panel)' => ['لوحة التحكم'],
        'لوحة المعلومات'              => ['لوحة المعلومات'],
    ],
    'settings' => ['الإعدادات' => ['الإعدادات', 'إعدادات']],
    'configuration' => [
        'التهيئة'   => ['التهيئة'],
        'التكوين'   => ['التكوينات', 'التكوين', 'تكوينات', 'تكوين'],
    ],
    'mail' => ['البريد' => ['البريد الإلكتروني', 'البريد']],
    'mail_inbox' => [
        'البريد الوارد' => ['البريد الوارد', 'صندوق الوارد'],
        'الوارد'        => ['الوارد'],
    ],
    'mail_sent' => [
        'المرسل'                                    => ['المرسل'],
        'تم الإرسال (past tense used as folder name)' => ['تم الإرسال', 'المرسلة'],
    ],
    'mail_outbox' => ['الصادر' => ['الصادر', 'الصادرة']],
    'mail_draft' => ['المسودات' => ['المسودات', 'المسودة', 'مسودة']],
    'mail_trash' => [
        'سلة المهملات' => ['سلة المهملات'],
        'المهملات'     => ['المهملات'],
        'المحذوفات'    => ['المحذوفات'],
    ],
    'menu_item' => ['عناصر القائمة' => ['عناصر القائمة', 'بند القائمة', 'إعدادات عناصر القائمة']],
    'mega_search' => ['البحث الشامل' => ['البحث الشامل', 'بحث شامل', 'البحث العام']],
    'notification' => ['الإشعارات' => ['الإشعارات', 'إشعارات', 'الإشعار', 'إشعار']],
    'quick_add' => ['إضافة سريعة' => ['إضافة سريعة']],
    'address' => [
        'عنوان الشحن'                   => ['عنوان الشحن'],
        'عنوان الفوترة (invoicing)'     => ['عنوان الفوترة', 'الفوترة'],
        'عنوان الفواتير (invoices)'     => ['عنوان الفواتير', 'الفواتير'],
    ],
    'record_id' => ['المعرف' => ['المعرف'], 'ID (left in English)' => ['ID']],
    'sku' => ['رمز المنتج' => ['رمز المنتج'], 'SKU' => ['SKU']],
    'account' => ['حسابي' => ['حسابي']],
    'login' => ['تسجيل الدخول' => ['تسجيل الدخول']],
    'password_reset' => [
        'إعادة تعيين كلمة المرور' => ['إعادة تعيين كلمة المرور'],
        'استعادة كلمة المرور'     => ['استعادة كلمة المرور'],
        'نسيت كلمة المرور'        => ['نسيت كلمة المرور'],
    ],
    'http_terminology' => [
        'نقطة النهاية'        => ['نقطة نهاية الرابط', 'نقطة النهاية'],
        'حمولة'               => ['حمولة'],
        'الرؤوس'              => ['الرؤوس', 'رأس'],
        'الجسم'               => ['الجسم'],
        'الطريقة'             => ['الطريقة'],
        'إرسال / تحديث (POST / PUT)' => ['إرسال', 'تحديث'],
    ],
];

// ---- 1. load ----------------------------------------------------------------
$rows = [];        // Arabic-bearing
$latin = [];       // no Arabic letter
foreach ($FILES as $ns => $rel) {
    $path = $root . '/' . $rel;
    if (! is_file($path)) {
        fwrite(STDERR, "MISSING $rel\n");
        continue;
    }
    $data = require $path;
    $flat = [];
    $walk = function ($node, $prefix) use (&$walk, &$flat) {
        foreach ($node as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            is_array($v) ? $walk($v, $key) : $flat[$key] = (string) $v;
        }
    };
    $walk($data, '');
    foreach ($flat as $key => $val) {
        if (trim($val) === '') {
            continue;
        }
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $val)) {
            $rows[] = ['ns' => $ns, 'key' => $key, 'val' => $val];
        } else {
            $latin[] = $ns . ':' . $key . ' = ' . $val;
        }
    }
}

// ---- 2. assign --------------------------------------------------------------
$assigned = [];
// packages whose whole file belongs to one concept
$NS_CONCEPT = ['webform' => 'web_form', 'googlecontact' => 'contacts'];
foreach ($rows as $r) {
    if (isset($NS_CONCEPT[$r['ns']])) {
        $assigned[$NS_CONCEPT[$r['ns']]][$r['ns'] . ':' . $r['key']] = $r;
    }
    foreach ($CONCEPTS as $id => [$label, $pats, $vetos]) {
        foreach ($vetos as $v) {
            if (preg_match('#' . $v . '#', $r['key'])) {
                continue 2;
            }
        }
        foreach ($pats as $p) {
            if (preg_match('#' . $p . '#', $r['key'])) {
                $assigned[$id][$r['ns'] . ':' . $r['key']] = $r;
                break;
            }
        }
    }
}

// ---- 3. naming slot ---------------------------------------------------------
function is_naming_slot(string $key, string $val): bool
{
    if (preg_match('#(^|\.)(acl|layouts)\.|\.title$|\.create-btn$|entity-types\.#', $key)) {
        return true;
    }
    if (preg_match('#seeders\.|^installer:installer\.index\.installation#', $key)) {
        return true;
    }

    return ! preg_match('/[.؟!:]$/u', $val)
        && count(preg_split('/\s+/u', trim($val))) <= 6;
}

// ---- 4. guide corpus --------------------------------------------------------
$guide = [];
foreach (glob($root . '/docs/client-guide/ar/*.md') as $f) {
    $guide[basename($f)] = file_get_contents($f);
}

// ---- 5. per concept ---------------------------------------------------------
$out = [];
$slotHits = 0;
foreach ($CONCEPTS as $id => [$label, $pats, $vetos]) {
    if (! isset($assigned[$id])) {
        continue;
    }
    $lex = $SYNONYMS[$id] ?? [];
    $all = [];
    $formToLexeme = [];
    foreach ($lex as $l => $forms) {
        foreach ($forms as $f) {
            $all[] = $f;
            $formToLexeme[$f] = $l;
        }
    }
    usort($all, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));
    $re = $all ? '/' . implode('|', array_map(fn ($t) => preg_quote($t, '/'), $all)) . '(?![\p{Arabic}])/u' : null;

    $renderings = [];
    $lexemes = [];
    $prose = [];
    $keys = [];
    $slotKeys = [];
    $three = ['acl' => null, 'sidebar' => null, 'title' => null];

    foreach ($assigned[$id] as $loc => $r) {
        $keys[] = $loc;

        // three-layer capture, package-agnostic
        if (preg_match('#(^|:)acl\.#', $loc)) {
            $three['acl'] ??= [$loc, $r['val']];
        } elseif (preg_match('#:layouts\.[a-z_.-]+$#', $loc) && ! preg_match('#-info$#', $loc)) {
            $three['sidebar'] ??= [$loc, $r['val']];
        } elseif (preg_match('#:menu\.title$#', $loc)) {
            $three['sidebar'] ??= [$loc, $r['val']];
        } elseif (preg_match('#\.index\.title$#', $loc)) {
            $three['title'] ??= [$loc, $r['val']];
        }

        if (! $re || ! preg_match_all($re, $r['val'], $m)) {
            continue;
        }
        $slot = is_naming_slot($r['key'], $r['val']);
        if ($slot) {
            $slotHits++;
            $slotKeys[] = $loc;
        }
        foreach (array_unique($m[0]) as $t) {
            if ($slot) {
                $renderings[$t][] = $loc;
                $lexemes[$formToLexeme[$t] ?? $t] = true;
            } else {
                $prose[$t][] = $loc;
            }
        }
    }
    sort($keys);
    sort($slotKeys);
    ksort($renderings);

    $guideFiles = [];
    if ($re) {
        foreach ($guide as $name => $text) {
            if (preg_match($re, $text)) {
                $guideFiles[] = $name;
            }
        }
    }

    $n = count($lexemes);
    $out[$id] = [
        'concept'          => $id,
        'english'          => $label,
        'class'            => $n <= 1 ? 'consistent' : 'conflicting',
        'n_renderings'     => $n,
        'lexeme_forms'     => array_keys($lexemes),
        'renderings'       => array_map(fn ($l) => array_values(array_unique($l)), $renderings),
        'prose_only_forms' => array_keys(array_diff_key($prose, $renderings)),
        'slot_keys'        => $slotKeys,
        'three_layer'      => $three,
        'guide_chapters'   => $guideFiles,
        'keys'             => $keys,
    ];
}

// ---- 6. summary -------------------------------------------------------------
$b = [1 => 0, 2 => 0, 3 => 0];
foreach ($out as $c) {
    $b[$c['n_renderings'] === 1 ? 1 : ($c['n_renderings'] === 2 ? 2 : 3)]++;
}
$summary = [
    'lang_files'                => count($FILES),
    'arabic_keys_total'         => count($rows),
    'latin_only_keys_total'     => count($latin),
    'concepts'                  => count($out),
    'key_concept_assignments'   => array_sum(array_map(fn ($c) => count($c['keys']), $out)),
    'naming_slot_hits'          => $slotHits,
    'concepts_1_rendering'      => $b[1],
    'concepts_2_renderings'     => $b[2],
    'concepts_3plus_renderings' => $b[3],
    'guide_chapters'            => count($guide),
];

echo json_encode([
    'summary'    => $summary,
    'latin_only' => $latin,
    'concepts'   => $out,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";

fwrite(STDERR, json_encode($summary, JSON_UNESCAPED_UNICODE) . "\n");
```

**Reproduce the numbers in this document:**

```text
php arabic-naming-inventory.php /mnt/Projects/Freelance/crm-platform > inventory.json
```

Expected on this checkout:

```json
{"lang_files":5,"arabic_keys_total":2204,"latin_only_keys_total":42,"concepts":55,"key_concept_assignments":2475,"naming_slot_hits":555,"concepts_1_rendering":30,"concepts_2_renderings":13,"concepts_3plus_renderings":12,"guide_chapters":18}
```

The synonym map and the concept patterns are the only judgement in the script. Change
them and the counts change; everything else is mechanical.

---

## Appendix C — scratch data

Extracted dumps for this run lived in
`/var/tmp/pi-scratch/arabic-inventory/` — never `/tmp`, which on this machine is a
bounded 2 GiB tmpfs. Nothing there is required to read this document; `inventory.json`
is reproduced in Appendix A and the script in Appendix B. The directory was removed
after the document was written.
