<?php

declare(strict_types=1);

/**
 * scripts/check-arabic-glossary.php — fail when an Arabic string drifts off the approved glossary.
 *
 * The specification is .agents/context/guides/arabic-terminology.md. Every rule below carries the
 * row it comes from, so a violation can be traced back to a decision instead of to a taste.
 *
 * Standalone on purpose: no Composer autoload, no Laravel bootstrap, no extension beyond core PHP
 * (`tokenizer` is used to recover line numbers). `php scripts/check-arabic-glossary.php` must work on
 * a bare checkout, and it must work in CI-less environments, because the operator decided this runs
 * as a `make` target only (`.github/workflows/` is deliberately out of scope).
 *
 * ---------------------------------------------------------------------------------------------
 * How the approved vocabulary is encoded — and why not by parsing the markdown
 * ---------------------------------------------------------------------------------------------
 * The glossary's §4 tables are prose-plus-markdown with measured counts inside cells ("`متعفن` (1
 * `A`:1890)"), so a parser over them would be guessing at cell structure and would break the first
 * time a cell is reworded. Instead each concept is a hand-written entry in RULES below with the row
 * it comes from (`§4.1 lead`, `§4.5 dashboard (operator decision 6)`, …). The mapping is therefore
 * traceable one entry at a time: `grep -n '§4.1 rotten lead'` in this file shows the rule, and
 * `grep -n 'rotten lead' .agents/context/guides/arabic-terminology.md` shows the decision behind it.
 * The risk of a hand-written table is silent drift from the document, so the invariant checks below
 * (value-presence of every key rule, ar/en key parity, PHP-parse) fail loudly rather than pass
 * quietly when the tree moves out from under the table.
 *
 * ---------------------------------------------------------------------------------------------
 * Exemption format
 * ---------------------------------------------------------------------------------------------
 * A rejected rendering is rejected *as a rendering of that concept*, not as a word: `لوحة التحكم` is
 * the admin panel in 2 keys and the dashboard in 5, `الاقتباسات` means quotation marks in one string,
 * `عميل محتمل` is the approved prospect stage. Those senses are recorded in EXEMPTIONS as:
 *
 *     'file'    => exact repo-relative path, or a glob (fnmatch)
 *     'match'   => a substring that must appear in the line (markdown) or in "<key.path> <line>"
 *                  (locale PHP files, so a key path or a value fragment both work)
 *     'concept' => the concept the exemption silences, or '*' for all
 *     'why'     => the glossary row that makes this sense legitimate
 *
 * Lines are matched by text rather than by number on purpose: a line number rots on the next edit,
 * a key path or a sentence fragment does not.
 *
 * A `rejected` token may also carry a `regex` (match a family instead of one written form) or
 * `unless_quoted` (a verbatim quotation in «…» keeps the word it quotes). Both are used only by the
 * documents rule for the صفقة family, where the proclitics (بالصفقة، للصفقة، وصفقة) and the construct
 * form (صفقته) mean a fixed list of surface forms would miss half the family.
 *
 * Usage:
 *   php scripts/check-arabic-glossary.php              # locale files + docs
 *   php scripts/check-arabic-glossary.php --locale     # locale files only
 *   php scripts/check-arabic-glossary.php --docs       # markdown only
 *
 * Exit code: 0 clean, 1 violations (or a structural failure such as a locale file that no longer
 * parses as a PHP array).
 */
$root = dirname(__DIR__);

// -------------------------------------------------------------------------------------------------
// Files under check
// -------------------------------------------------------------------------------------------------

/** The `ar` locale files. Aliases are the glossary's own (§0 "file aliases"). */
const LOCALE_FILES = [
    'A' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
    'I' => 'packages/Webkul/Installer/src/Resources/lang/ar/app.php',
    'W' => 'packages/Webkul/WebForm/src/Resources/lang/ar/app.php',
    'G' => 'packages/Webkul/GoogleContact/src/Resources/lang/ar/app.php',
    'V' => 'lang/ar/validation.php',
];

/** The only pair the glossary measures for parity; compared to each other, never to a hard number. */
const PARITY_AR = 'packages/Webkul/Admin/src/Resources/lang/ar/app.php';
const PARITY_EN = 'packages/Webkul/Admin/src/Resources/lang/en/app.php';

/** Prose surfaces (§ the task's "Docs" scope). The last two globs are being rewritten elsewhere. */
const DOC_GLOBS = [
    'docs/client-guide/*.md',
    'docs/client-guide/ar/*.md',
    'docs/client-guide/_internal/*.md',
    'brand/*.md',
    'docs/sales/*.md',
];

// -------------------------------------------------------------------------------------------------
// The glossary, as rules
// -------------------------------------------------------------------------------------------------

/**
 * One entry per concept. `rejected` lists the renderings the glossary rejects **for that concept**;
 * `unless_prefixed` / `unless_followed` encode a homograph that a plain substring search would
 * misfire on (`القيادة` inside `لوحة القيادة`, `الوارد` inside `البريد الوارد`).
 *
 * `scope`: 'locale' (the ar PHP files), 'docs' (the markdown surfaces) or 'both'.
 *
 * `files` (optional): an allowlist of repo-relative paths the rule applies to. Absent means every file
 * the scope covers, which is what every rule relies on today. It was added on 2026-11-04 v1.7 for one
 * rule that was only sound on the Admin `ar` file while the *same* rejected rendering still shipped
 * legally in the Installer's seeded attribute label — a string another lane owned. Narrowing to the
 * file that was actually fixed kept the rule enforcing a real invariant instead of demanding an edit
 * that lane could not make. The Installer's seeded label and the pending migration have since moved,
 * so that rule is back to full `locale` scope and no entry names `files`: the mechanism stays because
 * it is cheap and a future narrowing should not have to re-add the runner support, but anything that
 * uses it is a narrowing, never a widening — an entry that names `files` checks a subset of its scope.
 */
const RULES = [
    [
        'concept' => 'lead',
        'approved' => 'الفرصة / الفرص',
        'row' => '§4.1 lead — 26-row table row 1',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'عميل محتمل'],
            ['text' => 'العملاء المحتملون'],
            ['text' => 'قائمة العملاء الخاصة بك'],
            ['text' => 'إنشاء عميل'],
        ],
    ],
    [
        'concept' => 'lead (the deal field on an activity)',
        'approved' => 'الفرصة',
        'row' => '§4.1 lead + §4.4 activity deal field — 26-row table row 14',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'القيادة', 'unless_prefixed' => ['لوحة ']],
        ],
    ],
    [
        // The documents rule for the same row: the word clients read in the guide and the sales docs,
        // not the word any key ships. Operator decision of 2026-11-04 — `الصفقة` is purged, because it
        // is a second name for the concept on the surface a client reads most and the product never
        // shows it. Scoped to the docs on purpose: no locale file ever shipped it (`0` in all five).
        // The glossary that records the rejection (`.agents/context/guides/arabic-terminology.md`) is
        // not in DOC_GLOBS, so the record cannot violate itself.
        'concept' => 'deal, in documents (the صفقة family)',
        'approved' => 'الفرصة / الفرص',
        'row' => '§4.1 lead — the documents rule (operator decision, 2026-11-04)',
        'scope' => 'docs',
        'rejected' => [
            [
                'text' => 'صفق',
                'regex' => '/(?<![\p{Arabic}])[\p{Arabic}]*صفق[\p{Arabic}]*(?![\p{Arabic}])/u',
                'unless_quoted' => true,
            ],
        ],
    ],
    [
        'concept' => 'pipeline',
        'approved' => 'مسار المبيعات / مسارات المبيعات',
        'row' => '§4.1 pipeline — 26-row table row 2',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'خط الأنابيب'],
            ['text' => 'خطوط الأنابيب'],
            ['text' => 'الأنابيب', 'unless_prefixed' => ['خط ', 'خطوط ']],
            ['text' => 'قنوات البيع'],
            ['text' => 'قناة المبيعات'],
        ],
    ],
    [
        // Two slots, two approved forms (§4.7.5): the *label* is the short state adjective `مهمل`, and
        // the noun phrase `عميل مهمل` belongs in a sentence. The violation message names both, so a later
        // lane reading it is not told to replace a column header with the phrase.
        'concept' => 'rotten lead',
        'approved' => 'مهمل (عنوان العمود) / عميل مهمل (داخل جملة)',
        'row' => '§4.1 rotten lead — 26-row table row 3 / §4.7.5 (label vs sentence, two slots)',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'متعفن'],
            ['text' => 'الفاسد'],
        ],
    ],
    [
        'concept' => 'rotten days',
        'approved' => 'أيام الإهمال / مؤشر الإهمال',
        'row' => '§4.1 rotten days — 26-row table row 4',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'أيام التعفن'],
            ['text' => 'مؤشر التعفن'],
        ],
    ],
    [
        'concept' => 'owner',
        'approved' => 'مندوب المبيعات',
        'row' => '§4.1 owner / §6.1 OQ-5 — 26-row table row 5',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'مالك المبيعات'],
        ],
    ],
    [
        'concept' => 'stage: won',
        'approved' => 'مكتسب',
        'row' => '§4.1 stage: won / §5.2 — 26-row table row 6',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'مُنتصر'],
            ['text' => 'منتصر'],
            ['text' => 'فاز'],
            ['text' => 'ربحت'],
        ],
    ],
    [
        'concept' => 'stage: lost',
        'approved' => 'مفقود',
        'row' => '§4.1 stage: lost / §5.2 — 26-row table row 7',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'خسرت'],
            ['text' => 'خسر'],
        ],
    ],
    [
        'concept' => 'won value',
        'approved' => 'قيمة الإغلاق',
        'row' => '§4.1 won value — 26-row table row 8',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'قيمة الربح'],
        ],
    ],
    [
        'concept' => 'organization',
        'approved' => 'المؤسسة / المؤسسات',
        'row' => '§4.2 organization — 26-row table row 9',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'المنظمة'],
            ['text' => 'المنظمات'],
        ],
    ],
    [
        'concept' => 'contact person',
        'approved' => 'جهة الاتصال',
        'row' => '§4.2 contact person — 26-row table row 10',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'شخص الاتصال'],
        ],
    ],
    [
        'concept' => 'quote (one document)',
        'approved' => 'عرض سعر',
        'row' => '§4.3 quote — 26-row table row 11',
        'scope' => 'both',
        // `الاقتباسات المستقيمة` is the quotation-mark sense the glossary itself keeps (§4.3's own
        // source cell cites A:1593 as "quotation marks, a different sense").
        'rejected' => [
            ['text' => 'الاقتباسات', 'unless_followed' => [' المستقيمة', ' الملتوية']],
        ],
    ],
    [
        'concept' => 'quotes (the section)',
        'approved' => 'عروض الأسعار',
        'row' => '§4.3 quotes — 26-row table row 12',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'العروض'],
        ],
    ],
    [
        'concept' => 'sub total',
        'approved' => 'المجموع الفرعي',
        'row' => '§4.3 sub total — 26-row table row 13',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'الإجمالي الفرعي'],
        ],
    ],
    [
        'concept' => 'configuration',
        'approved' => 'التهيئة',
        'row' => '§4.5 configuration — 26-row table row 15 (the row rejects the bare noun and its verbal forms too)',
        'scope' => 'both',
        // The definite forms are their own tokens because Arabic boundaries hide them from the bare
        // one: in `التكوين` the `تكوين` occurrence is preceded by `ل`, an Arabic letter, so the
        // whole-word check below skips it — which is also why `تكوين` can never fire inside it.
        // The proclitic forms are separate tokens for the same reason: `بتكوين`'s `تكوين` is preceded
        // by `ب`, so only the written form can be matched. Each definite form's bare twin is listed
        // beside it, so rejecting `التكوينات` cannot leave `تكوينات` legal. `unless_quoted` is the
        // script's usual verbatim-quotation guard: the client guide reproduces the old screen string
        // inside «…», and prose around a quotation is still checked.
        'rejected' => [
            ['text' => 'التكوين'],
            ['text' => 'التكوينات'],
            ['text' => 'تكوين', 'unless_quoted' => true],
            ['text' => 'تكوينات', 'unless_quoted' => true],
            ['text' => 'بتكوين', 'unless_quoted' => true],
            ['text' => 'لتكوين', 'unless_quoted' => true],
        ],
    ],
    [
        'concept' => 'permission type / data scope',
        'approved' => 'نطاق البيانات',
        'row' => '§4.5 permission type — 26-row table row 16',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'عرض الصلاحية'],
        ],
    ],
    [
        'concept' => 'attribute',
        'approved' => 'الخاصية / الخصائص',
        'row' => '§4.5 attribute — 26-row table row 17 (the row rejects the singular and the bare plural too)',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'السمات'],
            ['text' => 'السمة'],
            ['text' => 'سمات'],
        ],
    ],
    [
        'concept' => 'tag',
        'approved' => 'الوسم / الوسوم',
        'row' => '§4.5 tag — 26-row table row 18',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'العلامات'],
            ['text' => 'العلامة'],
        ],
    ],
    [
        'concept' => 'event (automation trigger)',
        'approved' => 'الحدث / الأحداث',
        'row' => '§4.5 event — 26-row table row 19',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'الفعاليات'],
        ],
    ],
    [
        'concept' => 'data transfer',
        'approved' => 'استيراد البيانات',
        'row' => '§4.5 data transfer — 26-row table row 20',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'نقل البيانات'],
        ],
    ],
    [
        'concept' => 'inbox folder',
        'approved' => 'البريد الوارد',
        'row' => '§4.6 inbox — 26-row table row 21',
        'scope' => 'locale',
        // The rejected rendering is the *bare folder label* (`A`:1758, an adjective with no noun).
        // In prose `الوارد` is an ordinary adjective — `بريدك الوارد`, `التدفق الوارد` — so the rule
        // only fires on a value that is the label and nothing else. See the report's declined-rules list.
        'rejected' => [
            ['text' => 'الوارد', 'label_only' => true],
        ],
    ],
    [
        'concept' => 'sent folder',
        'approved' => 'المرسل',
        'row' => '§4.6 sent — 26-row table row 22',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'تم الإرسال'],
        ],
    ],
    [
        'concept' => 'trash',
        'approved' => 'سلة المهملات / نقل إلى سلة المهملات',
        'row' => '§4.6 trash — 26-row table row 23',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'المهملات', 'unless_prefixed' => ['سلة ']],
            ['text' => 'تم النقل إلى سلة المهملات'],
            ['text' => 'المحذوفات'],
        ],
    ],
    [
        'concept' => 'lead type: Existing Business',
        'approved' => 'عمل قائم',
        'row' => '§5.4 lead types — 26-row table row 24',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'عمل موجود'],
        ],
    ],
    [
        'concept' => 'dashboard',
        'approved' => 'لوحة المعلومات',
        'row' => '§4.5 dashboard — 26-row table row 25 (operator decision 6)',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'لوحة القيادة'],
        ],
    ],
    [
        'concept' => 'warehouse',
        'approved' => 'المخزن / المخازن',
        'row' => '§4.3 warehouse — 26-row table row 26',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'المستودع'],
            ['text' => 'المستودعات'],
        ],
    ],
    [
        'concept' => 'webhook endpoint',
        'approved' => 'عنوان URL',
        'row' => '§4.5 webhook endpoint (operator decision 8b)',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'نقطة النهاية'],
            ['text' => 'نقطة نهاية الرابط'],
        ],
    ],
    [
        // The row already rejected this plural, but nothing enforced it: `الويب هوكس` shipped as the
        // screen title, its breadcrumb (same key) and both form hints. The approved transliteration
        // is singular and plural alike, so `الويب هوك` (17 shipped occurrences) must stay clean.
        'concept' => 'webhook',
        'approved' => 'ويب هوك / الويب هوك',
        'row' => '§4.5 webhook (the `webhook` row of the settings table)',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'الويب هوكس'],
        ],
    ],
    [
        // Operator decision 7 protects three renderings of ONE word (`نموذج الويب`, `نموذج ويب`,
        // `نماذج الويب`) and forbids merging them; `استمارة` is a *fourth* name for the same object,
        // which is the class of defect that row exists to prevent. The family regex catches the
        // proclitics and the plurals (`والاستمارة`, `استمارات`) that a fixed list of forms would miss.
        // Locale-only: the client guide still records the old title by name, and this lane may not
        // edit prose surfaces — reported, not exempted.
        'concept' => 'web form',
        'approved' => 'نموذج الويب / نماذج الويب / نموذج ويب',
        'row' => '§4.5 web form (operator decision 7 — the three protected renderings, and no fourth)',
        'scope' => 'locale',
        'rejected' => [
            [
                'text' => 'استمار',
                'regex' => '/(?<![\p{Arabic}])[\p{Arabic}]*استمار[\p{Arabic}]*(?![\p{Arabic}])/u',
            ],
        ],
    ],
    [
        'concept' => 'email template',
        'approved' => 'قالب البريد الإلكتروني',
        'row' => '§4.5 email template',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'نموذج البريد الإلكتروني'],
        ],
    ],
    [
        'concept' => 'billing address',
        'approved' => 'عنوان الفوترة',
        'row' => '§4.3 billing address',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'عنوان الفواتير'],
        ],
    ],
    [
        'concept' => 'fixture: top-persons empty state',
        'approved' => 'لا توجد أشخاص متاحون للفترة المختارة',
        'row' => '§4.7 fixture defects (gender agreement)',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'لا توجد أشخاص متاحة للفترة المختارة'],
        ],
    ],
    [
        'concept' => 'fixture: quote line empty state',
        'approved' => 'أضف منتجًا إلى هذا العرض.',
        'row' => '§4.7 fixture defects',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'أضف طلب المنتج لهذا العرض'],
        ],
    ],
    [
        'concept' => 'fixture: webhook format token',
        'approved' => 'JSON',
        'row' => '§4.7.1 (the `Json` case defect)',
        'scope' => 'locale',
        'rejected' => [
            ['text' => 'Json'],
        ],
    ],
    [
        // The `ID` rule the §4.7.1 row decided, encoded at last: the imports grid header shipped
        // `الرقم التعريفي` beside the 23 keys that already read `المعرف`. §3's kept-in-Latin rule and
        // §4.7.1 both pick one word for a column header, so the second name is rejected wherever it
        // appears.
        'concept' => 'ID (column header)',
        'approved' => 'المعرف',
        'row' => '§4.7.1 (`ID` as a column header) + §3 the kept-in-Latin rule',
        'scope' => 'both',
        'rejected' => [
            ['text' => 'الرقم التعريفي'],
        ],
    ],
    [
        // §4.7.3's decision: one screen shipped both `مدعوم بواسطة` and `تم تطويره بواسطة` for the
        // same footer field. The English source (`general.settings.footer.label`'s own labels —
        // `footer.show`, `.show-info`, `.info`, `.powered-by`, `.title`) says "Powered by" in all
        // five, so `مدعوم بواسطة` is the approved one and the other verb is rejected. Locale-only:
        // four client-guide lines still quote the old label, and this lane may not edit prose.
        'concept' => 'footer credit',
        'approved' => 'مدعوم بواسطة',
        'row' => '§4.7.3 (the footer labels — the English source says "Powered by" in every key)',
        'scope' => 'locale',
        'rejected' => [
            ['text' => 'تم تطويره بواسطة'],
            ['text' => 'تمطويره بواسطة'],
        ],
    ],
    [
        // A live sales-screen verification found the lead grid's expected-close-date column labelled
        // `تاريخ إلى` — a word-for-word "Date To" — beside the create form's `تاريخ الإغلاق المتوقع`,
        // for the field whose code is `expected_close_date` (LeadDataGrid.php:284-285,
        // LeadController.php:670-671). The filter range in the same toolbar keeps its own `من` / `إلى`
        // pair (`leads.index.kanban.toolbar.filters.from` / `.to`), which is a range, not this field, so
        // it is untouched. Locale-only: `docs/client-guide/ar/04-leads-pipeline.md:60` still lists the
        // old column name and this lane may not edit prose — reported, not exempted.
        'concept' => 'expected close date (the lead grid and kanban column)',
        'approved' => 'تاريخ الإغلاق المتوقع',
        'row' => '§4.1 expected close date (the lead column — one field, one name; added 2026-11-04 v1.7)',
        'scope' => 'locale',
        'rejected' => [
            ['text' => 'تاريخ إلى'],
        ],
    ],
    [
        // The heading above a *single* attached contact read `حول الأشخاص` (About Persons) while the
        // panel's own buttons in the same block already said `إرفاق جهة اتصال` / `تغيير جهة الاتصال`.
        // §4.2 approves `جهة الاتصال` for the contact person; the key is rendered twice
        // (leads/view/person.blade.php:8 and :111), both times over one record. Locale-only: the client
        // guide still names the section `حول الأشخاص` in four lines this lane may not edit.
        'concept' => 'contact person (the lead record\'s panel heading)',
        'approved' => 'حول جهة الاتصال',
        'row' => '§4.2 contact person — 26-row table row 10 (applied to the panel heading, 2026-11-04 v1.7)',
        'scope' => 'locale',
        'rejected' => [
            ['text' => 'حول الأشخاص'],
        ],
    ],
    [
        // §4.3's `expired at` row, and the two shipped forms it left standing. The English source is
        // `Expired At` (`en`:408/427/2038) and every sibling date label in the file is a `تاريخ …` noun
        // (`تاريخ الإنشاء`, `تاريخ الإغلاق المتوقع`, `تاريخ الانتهاء`), while `منتهي في` / `تنتهي في` are
        // past-tense sentences used as field labels — the same class §4.6 rejects for `تم الإرسال`.
        // This used to be narrowed to the Admin file because the third form, `منتهي في`, still shipped
        // as the Installer's seeded attribute label (`I`:55) and in the pending migration
        // `2026_11_05_120000_rename_seeded_reference_rows_to_arabic`. Both moved to `تاريخ الانتهاء` on
        // 2026-11-04, so the rule is back to its proper scope: every `ar` locale file, no `files`.
        'concept' => 'expired at (the quote date field)',
        'approved' => 'تاريخ الانتهاء',
        'row' => '§4.3 expired at (added 2026-11-04 v1.7 — the English source is `Expired At`)',
        'scope' => 'locale',
        'rejected' => [
            ['text' => 'منتهي في'],
            ['text' => 'تنتهي في'],
        ],
    ],
    [
        // The activities screen rendered a bare Latin `N/A` as a value (three hardcoded literals, not a
        // key — see §4.4). No locale file ever shipped it; the rule exists so the placeholder cannot
        // enter one, and so the approved Arabic is on record where the fix will read it. Locale-only:
        // `docs/` carries no `N/A`, and prose is out of this lane anyway.
        'concept' => 'empty value placeholder (N/A)',
        'approved' => 'غير متاح',
        'row' => '§4.4 empty value placeholder (added 2026-11-04 v1.7)',
        'scope' => 'locale',
        'rejected' => [
            ['text' => 'N/A'],
        ],
    ],
    [
        'concept' => 'one written form per word (shadda)',
        'approved' => 'تعذّر',
        'row' => '§2 "One written form per word — shadda included" (glossary §2, in the file since v1.3)',
        'scope' => 'both',
        // The bare spelling is a different string from the shadda form (no substring relation),
        // so no guard is needed. Only this documented instance is encoded — see the handoff's
        // declined-rules list for why a mechanical shadda-grouping check is unsound.
        'rejected' => [
            ['text' => 'تعذر'],
        ],
    ],
    [
        // The address component's region field. Its label said `الولاية` while the picker under it
        // lists Egypt's 27 **governorates** (`القاهرة … مطروح`), so the label contradicted its own
        // data. Applied to every occurrence, not only to the Egypt-specific copy: the field is one
        // key rendered for every country, so a mixed scope would need per-country wording the
        // component does not have (`components/attributes/edit/address.blade.php`:65/68/89/90 — the
        // same `state` key labels both the list-backed select and the free-text input) — and the
        // only Arabic state names in the database are Egypt's 27 (measured 2026-11-05 in
        // `country_states`: 27 Arabic rows, all `EG`, `0` for the other 15 countries), so
        // `المحافظة` is the one Arabic word this label is ever read above. Saudi Arabia and the
        // Emirates have no list at all and fall back to free text, where the label contradicts
        // nothing. See §4.9 decision 11 for the whole argument, including what it costs a US
        // address in an Arabic interface.
        'concept' => 'state / province field (the address component\'s region field)',
        'approved' => 'المحافظة / المحافظات',
        'row' => '§4.9 state / province field (operator decision 11, 2026-11-05)',
        'scope' => 'both',
        // `الولايات المتحدة` is the country name, not this field: the `unless_followed` guard keeps
        // the whole family honest without exempting the file that lists the countries. Both forms
        // carry `unless_quoted`, because a glossary or change-log line records a rejected term by
        // quoting it in «…» — the same convention the صفقة documents rule uses.
        'rejected' => [
            ['text' => 'الولاية', 'unless_quoted' => true],
            ['text' => 'الولايات', 'unless_followed' => [' المتحدة'], 'unless_quoted' => true],
        ],
    ],
];

/**
 * One concept, one name — the pairs that are measurable without semantic analysis. Each fires when
 * **two different** strings of the same family survive in one locale file after exemptions, so the
 * splits the glossary decided (dashboard §4.5 decision 6, drafts §4.6 decision 10) pass because the
 * keys that carry the other sense are exempted by key path.
 */
const ONE_NAME = [
    [
        'concept' => 'dashboard',
        'row' => '§4.5 dashboard (operator decision 6 — split by key, never by string)',
        'variants' => ['لوحة المعلومات', 'لوحة التحكم'],
    ],
    [
        'concept' => 'drafts',
        'row' => '§4.6 drafts (operator decision 10 — folder plural, compose-dialog singular)',
        'variants' => ['المسودات', 'مسودة'],
    ],
];

/**
 * Keys whose *value* the glossary fixes exactly (`must_equal`) or whose value must not be a foreign
 * sentence (`must_contain_arabic`). Checked against the flattened array, so the assertion is about
 * the key the glossary names, not about a string that happens to appear somewhere.
 */
const KEY_RULES = [
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'leads.index.datagrid.id',
        'must_equal' => 'المعرف',
        'row' => '§4.7.1 (bare Latin `ID` as a column header)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'leads.index.kanban.columns.id',
        'must_equal' => 'المعرف',
        'row' => '§4.7.1 (bare Latin `ID` as a column header)',
    ],
    [
        // §4.7.5 pins the label slot itself: the leads-grid header is the short state adjective. Without
        // this, replacing it with the sentence form `عميل مهمل` passes every rule — the phrase is legal
        // prose, so no token rule rejects it. The grid renders this header from `LeadDataGrid.php`:266.
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'leads.index.datagrid.rotten-lead',
        'must_equal' => 'مهمل',
        'row' => '§4.7.5 (the short state label — the column header, not the sentence form)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'settings.webhooks.create.post',
        'must_equal' => 'POST',
        'row' => '§4.5 HTTP method label (operator decision 8a)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'settings.webhooks.create.put',
        'must_equal' => 'PUT',
        'row' => '§4.5 HTTP method label (operator decision 8a)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'settings.webhooks.edit.post',
        'must_equal' => 'POST',
        'row' => '§4.5 HTTP method label (operator decision 8a)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'settings.webhooks.edit.put',
        'must_equal' => 'PUT',
        'row' => '§4.5 HTTP method label (operator decision 8a)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'settings.webhooks.create.json',
        'must_equal' => 'JSON',
        'row' => '§4.7.1 (the `Json` case defect)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'settings.webhooks.edit.json',
        'must_equal' => 'JSON',
        'row' => '§4.7.1 (the `Json` case defect)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'users.forget-password.create.page-title',
        'must_equal' => 'استعادة كلمة المرور',
        'row' => '§4.8 password reset (operator decision 9)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'leads.update-failed',
        'must_contain_arabic' => true,
        'row' => '§4.7.1 (a full English sentence inside the Arabic file)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'configuration.index.general.info',
        'must_not_contain' => 'تكوين',
        'row' => '§4.5 configuration (the row lists this key as shipping `تكوين عام`)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'layouts.configuration',
        'must_equal' => 'التهيئة',
        'row' => '§4.5 configuration — 26-row table row 15 (the Configuration menu label; `layouts.settings` keeps الإعدادات for the other menu)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'products.view.inventory.add-warehouse',
        'must_equal' => 'إضافة مخزن',
        'row' => '§4.3 warehouse — 26-row table row 26 (the key beside `products.view.inventory.warehouse` = المخزن)',
    ],
    [
        // The quote's `person` is the contact person (`persons.name` through `quotes.person_id`,
        // QuoteDataGrid.php:32-38), and the section shipped it as two different Arabic nouns in two
        // surfaces: the grid said `الشخص` and the PDF said `شخص`, while the lead grid calls the same
        // object `جهة الاتصال` (§4.2 contact person). A bare-`شخص` text rule cannot be written —
        // `الشخص` is the approved name of the person *entity* in the web-form field list, the layouts
        // fallback and the report metric — so the assertion is by key.
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'quotes.index.datagrid.person',
        'must_equal' => 'جهة الاتصال',
        'row' => '§4.2 contact person — 26-row table row 10 (added 2026-11-04 v1.7, the quote grid)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'quotes.index.pdf.person',
        'must_equal' => 'جهة الاتصال',
        'row' => '§4.2 contact person — 26-row table row 10 (added 2026-11-04 v1.7, the quote PDF)',
    ],
    [
        // A column header, so it has to be a noun: the shipped `تم` is the bare past-tense verb, and
        // the sibling mass-action options (`activities.index.datagrid.done` / `.not-done`, `تم` / `لم يتم`)
        // stay as they are — those are values in a dropdown, not a header. See §4.4.
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'activities.index.datagrid.is_done',
        'must_equal' => 'حالة الإنجاز',
        'row' => '§4.4 activity done state (added 2026-11-04 v1.7 — a header is a noun, §1)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'settings.data-transfer.validation.errors.invalid-attribute',
        'must_equal' => 'اسم العمود "%s" غير صالح.',
        'row' => '§4.7.2 (the import header error named `سمة`)',
    ],

    /*
     * §4.9 decision 11 — the three keys of the address region field. The token rule above rejects
     * `الولاية`; these pin the approved word, so a third name (`المنطقة`, `الإقليم`) fails too.
     */
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'common.custom-attributes.state',
        'must_equal' => 'المحافظة',
        'row' => '§4.9 state / province field (operator decision 11, 2026-11-05)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'common.custom-attributes.select-state',
        'must_equal' => 'اختر المحافظة',
        'row' => '§4.9 state / province field (operator decision 11, 2026-11-05)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'configuration.index.select-state',
        'must_equal' => 'اختر المحافظة',
        'row' => '§4.9 state / province field (operator decision 11, 2026-11-05)',
    ],

    /*
     * §4.9 decision 12 — the quote adjustment. Deliberately **key rules, not a token rule**:
     * `التعديل` is the ordinary word for "the edit" and ships as the edit action/verb in 60-odd
     * keys (`'edit' => 'تعديل'` and the `تعديل عرض السعر` / `تعديل الفرصة` titles), so a token rule
     * would be a false-positive machine and would have to be exempted back to nothing. The English
     * source of every key below is `Adjustment` (`en`:414, 421, 466, 496, 2062), which is the
     * amount adjustment on a quote, so each is asserted by its own full key path.
     */
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'quotes.index.datagrid.adjustment',
        'must_equal' => 'تعديل المبلغ',
        'row' => '§4.9 quote adjustment (operator decision 12, 2026-11-05)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'quotes.index.pdf.adjustment',
        'must_equal' => 'تعديل المبلغ',
        'row' => '§4.9 quote adjustment (operator decision 12, 2026-11-05)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'leads.view.quotes.adjustment',
        'must_equal' => 'تعديل المبلغ',
        'row' => '§4.9 quote adjustment (operator decision 12, 2026-11-05)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'quotes.create.total-adjustment',
        'must_equal' => 'تعديل المبلغ (:symbol)',
        'row' => '§4.9 quote adjustment (operator decision 12, 2026-11-05)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'key' => 'quotes.edit.total-adjustment',
        'must_equal' => 'تعديل المبلغ (:symbol)',
        'row' => '§4.9 quote adjustment (operator decision 12, 2026-11-05)',
    ],
];

/**
 * The reserved word (§4.2 `customer` row, §6.1 OQ-1). `عميل` may not label a lead or a user; the
 * approved phrases below are stripped from a value before the scan, because they are the documented
 * legitimate senses. Locale files only — the client guide is *prose*, where `العميل` is the customer
 * the chapter is talking to, and the reserved-word rule does not reach prose.
 */
const RESERVED = [
    'concept' => 'customer (reserved word)',
    'row' => '§4.2 customer / §6.1 OQ-1 — `عميل` is reserved for the customer record that does not exist yet',
    'tokens' => ['عميل', 'العميل', 'عملاء', 'العملاء'],
    'approved_phrases' => [
        'إدارة علاقات العملاء',   // the frozen CRM expansion — A:2444, A:2446
        'عميل محتمل',             // the approved prospect stage — §4.1 prospect row, §5.1
        'عميل مهمل',              // the approved rotten-lead term — §4.1 rotten lead row
        'لوحة العميل',            // a panel, not a person/organisation/deal — §6.1 OQ-1's constraint
    ],
    'hint' => 'use الفرصة for a deal and المستخدم for a user',
];

/**
 * The shipped Arabic brand strings this script fixes **by value**, not only by rejected rendering.
 *
 * `app/Providers/BrandingServiceProvider.php` is a config surface: it is neither a locale file (the
 * file returns a class, so it cannot be included and flattened) nor a markdown surface, so neither
 * scan reaches it. The tagline lives there, ships on the login screen and in the mail chrome, and
 * carried the word the documents rule purges, so "did anyone revert it?" has to be a failing check
 * rather than a grep in a review. `§4.1 lead` states the decision; `brand/copy-deck.md` decision 1
 * records it.
 */
const BRAND_VALUES = [
    [
        'file' => 'app/Providers/BrandingServiceProvider.php',
        'key' => 'sijilna.tagline.ar',
        'must_equal' => 'كل فرصة لها سجل.',
        'row' => '§4.1 lead — the tagline carried the documents rule (operator decision, 2026-11-04)',
    ],
];

/**
 * The exemptions. `match` is tested against the markdown line, or against "<key.path> <line>" for a
 * locale file. Every entry below was verified in the tree before it was written down.
 */
const EXEMPTIONS = [
    [
        'file' => 'packages/Webkul/Installer/src/Resources/lang/ar/app.php',
        'match' => 'lead.pipeline.pipeline-stages.prospect',
        'concept' => 'lead',
        'why' => '§4.1 prospect / §5.1 — the seeded stage name is APPROVED as عميل محتمل',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'match' => 'wrong-quotes',
        'concept' => 'quote (one document)',
        'why' => '§4.3 quote — `اقتباس` means quotation marks here (A:1593, the string the glossary cites)',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'match' => 'brand-color',
        'concept' => 'tag',
        'why' => '§4.5 tag — `لون العلامة التجارية` is the brand mark, the collision the row names (A:2165)',
    ],
    [
        'file' => 'brand/copy-deck.md',
        'match' => '(was ',
        'concept' => '*',
        'why' => 'the glossary\'s own record of what each term replaced (operator decisions, 2026-11-04)',
    ],
    [
        'file' => '*',
        'match' => '← **عميل محتمل**',
        'concept' => 'lead',
        'why' => '§5.1 — the stage name in a stage chain (`جديد ← متابعة ← عميل محتمل ← …`); an unbolded عميل محتمل used as a lead label is still a violation',
    ],
    [
        'file' => '*',
        'match' => '| 3 | **عميل محتمل**',
        'concept' => 'lead',
        'why' => '§5.1 — the stage name in the delivered-stages table of the pipeline chapter',
    ],
    [
        'file' => '*',
        'match' => '← عميل محتمل',
        'concept' => 'lead',
        'why' => '§5.1 — the same stage name in an unbolded stage chain',
    ],
    [
        'file' => '*',
        'match' => '| `prospect` | عميل محتمل',
        'concept' => 'lead',
        'why' => '§5.1 — the seeded-stage table names the stage `prospect` in the next cell',
    ],
    [
        'file' => 'docs/client-guide/_internal/*.md',
        'match' => 'المستودع',
        'concept' => 'warehouse',
        'why' => 'homograph: in the internal measurement docs `المستودع` is the git repository ("هذا المستودع", "جذر المستودع", "من المستودع"), never the warehouse. Reported as a known limitation in the handoff; the client-facing chapters are not exempted.',
    ],
    [
        'file' => 'docs/client-guide/_internal/facts.md',
        'match' => 'مغلق:',
        'concept' => '*',
        'why' => 'the close-out table records the former term by name ("مغلق: كان «…»") — a change log, not a shipped label',
    ],
    [
        'file' => 'docs/client-guide/_internal/facts.md',
        'match' => 'قبل تطبيق المصطلحات',
        'concept' => '*',
        'why' => 'the same change log, in prose: "before the terminology pass this read «…»"',
    ],
    [
        'file' => 'docs/client-guide/_internal/conventions.md',
        'match' => 'لا تُكتب',
        'concept' => '*',
        'why' => '§8 of the conventions states the do-not-write rule by quoting the rejected rendering',
    ],
    [
        'file' => 'docs/client-guide/ar/04-leads-pipeline.md',
        'match' => 'استخدم العلامة كمؤشر',
        'concept' => 'tag',
        'why' => '§4.5 tag — a priority *indicator*, not the tag object',
    ],
    [
        'file' => 'docs/client-guide/ar/07-activities.md',
        'match' => 'وضع العلامة هو ما يفرّغ',
        'concept' => 'tag',
        'why' => '§4.5 tag — a check mark on an activity',
    ],
    [
        'file' => 'docs/client-guide/ar/07-activities.md',
        'match' => 'بلا هذه العلامة',
        'concept' => 'tag',
        'why' => '§4.5 tag — the same check mark, second mention',
    ],
    [
        'file' => 'docs/client-guide/ar/12-configure.md',
        'match' => 'لون العلامة التجارية',
        'concept' => 'tag',
        'why' => '§4.5 tag — the brand mark field label',
    ],
    [
        'file' => 'docs/client-guide/ar/12-configure.md',
        'match' => 'العروض المبدئية',
        'concept' => 'quotes (the section)',
        'why' => '§4.3 quotes — a client\'s own renaming example in the guide, not the shipped label',
    ],
    [
        'file' => 'docs/client-guide/ar/15-troubleshooting.md',
        'match' => 'السبب المحتمل',
        'concept' => 'lead',
        'why' => 'the "probable cause" column header; kept so a future broadening of the lead rule cannot fire on it',
    ],
    [
        'file' => 'packages/Webkul/Installer/src/Resources/lang/ar/app.php',
        'match' => 'installer.index.krayin-info',
        'concept' => 'customer (reserved word)',
        'why' => '§4.2 customer / §6.1 OQ-1 — `نظام إدارة عملاء تستضيفه بنفسك` names the product category in prose (the English is `a self-hosted CRM`), not a person, organisation, deal or user, so the reserved-word constraint does not reach it. Judged and kept on 2026-11-04, second look: it is also the only place that phrase ships, and it names the wizard tagline rather than being a second name for the `عميل` record. The neighbouring Arabic expansions of the CRM acronym say `إدارة علاقات العملاء (CRM)` (`A`:2444, `A`:2446) — a shorter form for a different slot, not a rival record name; unifying them would change the installer tagline, which is a decision for the copy deck, not for this rule. Its sibling `installation-completed.customer-panel` (`I`:240 `لوحة العميل`) is stripped as an approved phrase because it labels a panel, and no second name for that panel ships.',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'match' => 'configuration.index.general.general.locale-settings.title-info',
        'concept' => 'dashboard',
        'why' => '§4.5 dashboard (operator decision 6) — this key means the admin panel, and keeps لوحة التحكم',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'match' => 'configuration.index.general.settings.footer.show-info',
        'concept' => 'dashboard',
        'why' => '§4.5 dashboard (operator decision 6) — the admin panel as a whole, keeps لوحة التحكم',
    ],
    [
        'file' => 'packages/Webkul/Admin/src/Resources/lang/ar/app.php',
        'match' => 'mail.index.mail.draft',
        'concept' => 'drafts',
        'why' => '§4.6 drafts (operator decision 10) — the compose dialog saves ONE draft, so مسودة stays',
    ],

    /*
     * The صفقة documents rule (operator decision, 2026-11-04). Two senses legitimately keep the word,
     * and each entry below names one of them; every other occurrence in the docs is a violation that
     * the guide has to fix, because the product never shows the word at all.
     *
     * The third entry that used to sit here exempted the brand tagline, which was approved carrying
     * `الصفقة` (see `brand.md`). It was deleted, not reworded, when the operator carried the tagline
     * with the terminology decision: the tagline now reads `كل فرصة لها سجل.` and is asserted by value
     * in BRAND_VALUES below, so a revert fails twice over — the docs rule rejects the earlier wording
     * and the value rule rejects it in the provider that ships it.
     */
    [
        'file' => 'docs/client-guide/ar/17-glossary.md',
        'match' => '«الصفقة',
        'concept' => 'deal, in documents (the صفقة family)',
        'why' => 'the glossary\'s own record rows: a glossary names a rejected term by quoting it, which is this file\'s record convention («…»). The term table\'s *prose* is not exempt — a definition that reads «الصفقة التي تُتابَع …» is a violation and must say الفرصة.',
    ],
    [
        'file' => 'docs/client-guide/_internal/facts.md',
        'match' => 'مغلق:',
        'concept' => 'deal, in documents (the صفقة family)',
        'why' => 'a change-log line that records the old name ("مغلق: كان «…»") — a record of what changed, not shipped prose. The concept-`*` entry above already covers this line for every rule; this entry makes the coverage explicit for this one.',
    ],
    [
        'file' => 'docs/sales/discovery-script-ar.md',
        'match' => '«دي كل الصفقات',
        'concept' => 'deal, in documents (the صفقة family)',
        'why' => 'a verbatim client quotation: the discovery script prints the sentence the rep says out loud, in Egyptian colloquial, inside «…», and الصفقة is the word the client hears himself say. The instruction text around a quotation is NOT exempt — a line that says «انقل الصفقة لمفاوضات» beside a quoted sentence is still a violation.',
    ],
    [
        'file' => 'docs/client-guide/README.md',
        'match' => 'the quotes menu reads',
        'concept' => 'quotes (the section)',
        'why' => 'the internal README records the shipped contradiction by quoting both labels («العروض» beside «عروض الأسعار») — a defect record that tells a writer not to re-decide, in the same class as `_internal/conventions.md`\'s do-not-write rule. Widening DOC_GLOBS to `docs/client-guide/**` brought the file into scope; the record line stays legitimate even though the tree now ships the fix (§4.3 quotes: `العروض` measures 0 in `A`), which is reported rather than exempted away.',
    ],
    [
        'file' => 'docs/sales/discovery-script-ar.md',
        'match' => '«زي ما إنت شايف',
        'concept' => 'deal, in documents (the صفقة family)',
        'why' => 'the same verbatim client quotation, second quoted line of the demo script.',
    ],
];

// -------------------------------------------------------------------------------------------------
// Helpers
// -------------------------------------------------------------------------------------------------

/**
 * Collapse a nested PHP array into "a.b.c => scalar" paths. Arrays themselves are dropped, so the
 * resulting key set is what parity and KEY_RULES are asserted against.
 *
 * @param  array<mixed>  $data
 * @return array<string, mixed>
 */
function flatten(array $data, string $prefix = ''): array
{
    $flat = [];

    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $flat += flatten($value, $path);

            continue;
        }

        $flat[$path] = $value;
    }

    return $flat;
}

/**
 * Map "a.b.c" => the line the value sits on, by walking the PHP tokens. The files are plain nested
 * short-array literals (`'key' => 'value',`), so a small key/value state machine is enough — and it
 * is why `tokenizer` (core PHP, no Composer) is the only tool this script needs to find a line.
 *
 * @return array<string, int>
 */
function key_lines(string $source): array
{
    $tokens = token_get_all($source);
    $map = [];
    $stack = [];
    $pendingKey = null;
    $state = 'key';

    foreach ($tokens as $token) {
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $text = is_array($token) ? $token[1] : $token;
        $line = is_array($token) ? $token[2] : 0;

        if ($text === '[') {
            $stack[] = $pendingKey;
            $pendingKey = null;
            $state = 'key';

            continue;
        }

        if ($text === ']') {
            array_pop($stack);
            $state = 'comma';

            continue;
        }

        if ($text === ',') {
            $state = 'key';
            $pendingKey = null;

            continue;
        }

        if (is_array($token) && $token[0] === T_DOUBLE_ARROW) {
            $state = 'value';

            continue;
        }

        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $value = trim($text, "'\"");

            if ($state === 'key') {
                $pendingKey = $value;
                $state = 'arrow';

                continue;
            }

            if ($state === 'value') {
                // The key of the value being recorded is still pending: the stack holds the keys of
                // the arrays that are open around it.
                $parts = array_filter($stack, static fn ($part) => $part !== null);
                $parts[] = $pendingKey;
                $path = implode('.', $parts);

                if ($path !== '' && ! isset($map[$path])) {
                    $map[$path] = $line;
                }

                $state = 'comma';
            }
        }
    }

    return $map;
}

function is_arabic_letter(string $char): bool
{
    return $char !== '' && preg_match('/^\p{Arabic}$/u', $char) === 1;
}

/**
 * Is the character at $pos inside an Arabic quotation pair (« … »)? Quoted speech is reproduced, not
 * authored, so a term inside it is evidence the term exists rather than a name this tree chose.
 */
function is_quoted(string $text, int $pos): bool
{
    $before = mb_substr($text, 0, $pos, 'UTF-8');

    return mb_substr_count($before, '«') > mb_substr_count($before, '»');
}

/**
 * Matches of a pattern, as `offset` (in characters — the unit every caller slices with, because the
 * literal path below works in characters too) plus the text that actually matched. Used where the
 * violation is the family and not one written form.
 *
 * @return list<array{offset: int, found: string}>
 */
function regex_matches(string $text, string $pattern): array
{
    if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
        return [];
    }

    $found = [];

    foreach ($matches[0] as $match) {
        $byteOffset = (int) $match[1];
        $found[] = [
            'offset' => (int) mb_strlen(substr($text, 0, $byteOffset), 'UTF-8'),
            'found' => (string) $match[0],
        ];
    }

    return $found;
}

/**
 * Every occurrence of $token in $text that is a whole word (Arabic-aware: PCRE's \b is ASCII, so the
 * boundary is "the neighbouring character is not an Arabic letter") and is not excluded by the
 * token's homograph guards. Each match carries the offset **and** the text that matched, so a rule
 * written over a whole family reports the word it found rather than the stem it searched for.
 *
 * @param  array{text: string, regex?: string, unless_quoted?: bool, unless_prefixed?: list<string>, unless_followed?: list<string>}  $token
 * @return list<array{offset: int, found: string}>
 */
function token_offsets(string $text, array $token): array
{
    $needle = $token['text'];
    $offsets = [];
    $offset = 0;
    $needleLength = mb_strlen($needle, 'UTF-8');

    // `label_only` compares the whole value: the rendering is rejected as a *label*, so a sentence
    // that merely contains the word is a different sense (`الوارد` as an adjective).
    if (($token['label_only'] ?? false) === true) {
        return trim($text, " \t\n\r\0\x0B") === $needle ? [['offset' => 0, 'found' => $needle]] : [];
    }

    // A `regex` token names a whole family, so the pattern carries the boundary checks and only the
    // quotation guard below applies on top of it.
    if (isset($token['regex'])) {
        foreach (regex_matches($text, $token['regex']) as $match) {
            if (($token['unless_quoted'] ?? false) === true && is_quoted($text, $match['offset'])) {
                continue;
            }

            $offsets[] = $match;
        }

        return $offsets;
    }

    while (($pos = mb_strpos($text, $needle, $offset, 'UTF-8')) !== false) {
        $offset = $pos + 1;

        // A word inside Arabic quotation marks is verbatim quoted speech, which keeps the word it
        // quotes; the prose around it does not.
        if (($token['unless_quoted'] ?? false) === true && is_quoted($text, $pos)) {
            continue;
        }

        $before = $pos > 0 ? mb_substr($text, $pos - 1, 1, 'UTF-8') : '';
        if (is_arabic_letter($before)) {
            continue;
        }

        $after = mb_substr($text, $pos + $needleLength, 1, 'UTF-8');
        if (is_arabic_letter($after)) {
            continue;
        }

        $accepted = true;

        foreach ($token['unless_prefixed'] ?? [] as $prefix) {
            $prefixLength = mb_strlen($prefix, 'UTF-8');

            if ($pos >= $prefixLength && mb_substr($text, $pos - $prefixLength, $prefixLength, 'UTF-8') === $prefix) {
                $accepted = false;

                break;
            }
        }

        foreach ($token['unless_followed'] ?? [] as $suffix) {
            $suffixLength = mb_strlen($suffix, 'UTF-8');

            if (mb_substr($text, $pos + $needleLength, $suffixLength, 'UTF-8') === $suffix) {
                $accepted = false;

                break;
            }
        }

        if ($accepted) {
            $offsets[] = ['offset' => $pos, 'found' => $needle];
        }
    }

    return $offsets;
}

/**
 * @param  list<array{file: string, match: string, concept: string, why: string}>  $exemptions
 * @return array{file: string, match: string, concept: string, why: string}|null
 */
function find_exemption(array $exemptions, string $file, string $concept, string $context): ?array
{
    foreach ($exemptions as $exemption) {
        if ($exemption['file'] !== $file && ! fnmatch($exemption['file'], $file)) {
            continue;
        }

        if ($exemption['concept'] !== '*' && $exemption['concept'] !== $concept) {
            continue;
        }

        if (str_contains($context, $exemption['match'])) {
            return $exemption;
        }
    }

    return null;
}

/**
 * @param  array<string, list<array{line: int, message: string}>>  $violations
 */
function report(string $file, int $line, string $message, array &$violations): void
{
    $violations[$file][] = ['line' => $line, 'message' => $message];
}

// -------------------------------------------------------------------------------------------------
// Checks
// -------------------------------------------------------------------------------------------------

/**
 * @param  array<string, list<array{line: int, message: string}>>  $violations
 * @param  array<string, list<string>>  $structural
 */
function check_locale_file(string $root, string $path, array &$violations, array &$structural): void
{
    $absolute = $root.'/'.$path;

    if (! is_file($absolute)) {
        $structural[$path][] = 'locale file is missing';

        return;
    }

    $source = (string) file_get_contents($absolute);

    if (! mb_check_encoding($source, 'UTF-8')) {
        $structural[$path][] = 'file is not valid UTF-8';

        return;
    }

    // Structural invariant the apply phase relied on: the file must still parse, as a PHP array.
    $data = null;
    $level = ob_get_level();

    ob_start();

    try {
        $data = include $absolute;
    } catch (Throwable $exception) {
        ob_end_clean();
        $structural[$path][] = 'include failed: '.$exception->getMessage();

        return;
    }

    ob_end_clean();

    if (! is_array($data)) {
        $structural[$path][] = 'file did not return a PHP array';

        return;
    }

    $values = flatten($data);
    $lines = key_lines($source);
    $sourceLines = explode("\n", $source);

    foreach ($values as $key => $value) {
        if (! is_string($value)) {
            continue;
        }

        $line = $lines[$key] ?? 0;
        $lineText = $line > 0 ? ($sourceLines[$line - 1] ?? '') : '';
        $context = $key.' '.$lineText;

        foreach (RULES as $rule) {
            if ($rule['scope'] === 'docs') {
                continue;
            }

            // A rule may name the files it is sound on; absent means "every file in scope".
            if (isset($rule['files']) && ! in_array($path, $rule['files'], true)) {
                continue;
            }

            foreach ($rule['rejected'] as $token) {
                $matches = token_offsets($value, $token);

                if ($matches === []) {
                    continue;
                }

                if (find_exemption(EXEMPTIONS, $path, $rule['concept'], $context) !== null) {
                    continue;
                }

                report($path, $line, sprintf(
                    "%s — found '%s', approved '%s' (%s)",
                    $rule['concept'],
                    $matches[0]['found'],
                    $rule['approved'],
                    $rule['row'],
                ), $violations);
            }
        }

        // §2 numeral rule: Western digits only, no Arabic-Indic digits, anywhere in the ar files.
        if (preg_match('/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u', $value, $digit) === 1) {
            report($path, $line, sprintf(
                "numerals — found '%s', approved 'Western digits 0-9' (§2)",
                $digit[0],
            ), $violations);
        }

        // §4.2 customer / §6.1 OQ-1: the reserved word.
        $probe = $value;

        foreach (RESERVED['approved_phrases'] as $phrase) {
            $probe = str_replace($phrase, '', $probe);
        }

        foreach (RESERVED['tokens'] as $token) {
            if (token_offsets($probe, ['text' => $token]) === []) {
                continue;
            }

            if (find_exemption(EXEMPTIONS, $path, RESERVED['concept'], $context) !== null) {
                break;
            }

            report($path, $line, sprintf(
                "%s — found '%s', approved 'not a label for a lead or a user (%s)' (%s)",
                RESERVED['concept'],
                $token,
                RESERVED['hint'],
                RESERVED['row'],
            ), $violations);

            break;
        }
    }

    // One name per concept, measured inside this file only.
    foreach (ONE_NAME as $group) {
        $found = [];

        foreach ($group['variants'] as $variant) {
            foreach ($values as $key => $value) {
                if (! is_string($value)) {
                    continue;
                }

                if (token_offsets($value, ['text' => $variant]) === []) {
                    continue;
                }

                $line = $lines[$key] ?? 0;
                $lineText = $line > 0 ? ($sourceLines[$line - 1] ?? '') : '';

                if (find_exemption(EXEMPTIONS, $path, $group['concept'], $key.' '.$lineText) !== null) {
                    continue;
                }

                $found[$variant] = $line;

                break;
            }
        }

        if (count($found) < 2) {
            continue;
        }

        $names = [];

        foreach ($found as $variant => $line) {
            $names[] = sprintf("'%s' (line %d)", $variant, $line);
        }

        report($path, max($found), sprintf(
            "%s — two names for one concept: %s; approved '%s' (%s)",
            $group['concept'],
            implode(' and ', $names),
            $group['variants'][0],
            $group['row'],
        ), $violations);
    }

    // Keys whose value the glossary fixes exactly. Checked against the flattened array, so the
    // assertion is about the key the glossary names, not about a string that happens to appear
    // somewhere — and each rule names the file that owns the key.
    foreach (KEY_RULES as $rule) {
        if ($rule['file'] !== $path) {
            continue;
        }

        if (! array_key_exists($rule['key'], $values)) {
            $structural[$path][] = sprintf(
                "key '%s' from %s no longer exists — the rule is stale",
                $rule['key'],
                $rule['row'],
            );

            continue;
        }

        $value = (string) $values[$rule['key']];
        $line = $lines[$rule['key']] ?? 0;

        if (isset($rule['must_equal']) && $value !== $rule['must_equal']) {
            report($path, $line, sprintf(
                "key '%s' — found '%s', approved '%s' (%s)",
                $rule['key'],
                $value,
                $rule['must_equal'],
                $rule['row'],
            ), $violations);
        }

        if (isset($rule['must_contain_arabic']) && preg_match('/\p{Arabic}/u', $value) !== 1) {
            report($path, $line, sprintf(
                "key '%s' — found '%s', approved 'an Arabic sentence' (%s)",
                $rule['key'],
                $value,
                $rule['row'],
            ), $violations);
        }

        if (isset($rule['must_not_contain']) && token_offsets($value, ['text' => $rule['must_not_contain']]) !== []) {
            report($path, $line, sprintf(
                "key '%s' — found '%s', approved 'the التهيئة family' (%s)",
                $rule['key'],
                $value,
                $rule['row'],
            ), $violations);
        }
    }
}

/**
 * @param  array<string, list<array{line: int, message: string}>>  $violations
 */
function check_doc_file(string $root, string $path, array &$violations): void
{
    $absolute = $root.'/'.$path;

    if (! is_file($absolute)) {
        return;
    }

    $lines = explode("\n", (string) file_get_contents($absolute));

    foreach ($lines as $index => $text) {
        foreach (RULES as $rule) {
            if ($rule['scope'] === 'locale') {
                continue;
            }

            foreach ($rule['rejected'] as $token) {
                $matches = token_offsets($text, $token);

                if ($matches === []) {
                    continue;
                }

                if (find_exemption(EXEMPTIONS, $path, $rule['concept'], $text) !== null) {
                    continue;
                }

                report($path, $index + 1, sprintf(
                    "%s — found '%s', approved '%s' (%s)",
                    $rule['concept'],
                    $matches[0]['found'],
                    $rule['approved'],
                    $rule['row'],
                ), $violations);
            }
        }
    }
}

/**
 * Assert a brand string by value.
 *
 * The provider is a PHP class, not a returned array, so it is read as text: the key's own line is
 * located by the same tokenizer walk `key_lines()` does for the locale files, and the value is taken
 * off that line. That keeps the check independent of how the array is laid out (it is not a parsed
 * comparison — it is an assertion that the key still ships that string, on the line that ships it).
 *
 * @param  array{file: string, key: string, must_equal: string, row: string}  $rule
 * @param  array<string, list<array{line: int, message: string}>>  $violations
 * @param  array<string, list<string>>  $structural
 */
function check_brand_value(string $root, array $rule, array &$violations, array &$structural): void
{
    $absolute = $root.'/'.$rule['file'];

    if (! is_file($absolute)) {
        $structural[$rule['file']][] = 'the brand string\'s file is missing';

        return;
    }

    $source = (string) file_get_contents($absolute);
    $lines = key_lines($source);

    if (! isset($lines[$rule['key']])) {
        $structural[$rule['file']][] = sprintf(
            "key '%s' from %s no longer exists — the rule is stale",
            $rule['key'],
            $rule['row'],
        );

        return;
    }

    $line = $lines[$rule['key']];
    $lineText = explode("\n", $source)[$line - 1] ?? '';
    $found = preg_match('/=>\s*([\'\"])(.*?)\1/u', $lineText, $match) === 1 ? $match[2] : null;

    if ($found !== $rule['must_equal']) {
        report($rule['file'], $line, sprintf(
            "brand string '%s' — found '%s', approved '%s' (%s)",
            $rule['key'],
            $found ?? 'no string literal on the key line',
            $rule['must_equal'],
            $rule['row'],
        ), $violations);
    }
}

// -------------------------------------------------------------------------------------------------
// Run
// -------------------------------------------------------------------------------------------------

$options = array_slice($argv, 1);

if (in_array('--help', $options, true) || in_array('-h', $options, true)) {
    fwrite(STDOUT, <<<'TXT'
        Usage: php scripts/check-arabic-glossary.php [--locale|--docs]

          (no flag)  check the ar locale files and the Arabic prose surfaces
          --locale   check the ar locale files only
          --docs     check the Arabic markdown surfaces only

        Specification: .agents/context/guides/arabic-terminology.md
        Exit code: 0 clean, 1 violations or a structural failure.

        TXT);

    exit(0);
}

$checkLocale = ! in_array('--docs', $options, true);
$checkDocs = ! in_array('--locale', $options, true);

$violations = [];
$structural = [];

if ($checkLocale) {
    foreach (LOCALE_FILES as $path) {
        check_locale_file($root, $path, $violations, $structural);
    }

    // The shipped brand values. They live in a config provider rather than a locale file, so they are
    // checked beside the locale scan: `--docs` stays a markdown-only mode, and any full run fails.
    foreach (BRAND_VALUES as $rule) {
        check_brand_value($root, $rule, $violations, $structural);
    }

    // Structural invariant the apply phase relied on: the Admin ar and en files hold the same keys.
    // Compared to each other, never to a hard-coded count. A file that will not parse is reported as
    // a structural failure here too, so a broken edit can never pass quietly through this block.
    $arPath = $root.'/'.PARITY_AR;
    $enPath = $root.'/'.PARITY_EN;
    $arData = null;
    $enData = null;
    $parityLoaded = false;

    if (! is_file($arPath) || ! is_file($enPath)) {
        $structural[PARITY_AR][] = 'the Admin ar/en pair is not both present, so key parity cannot be checked';
    } else {
        try {
            ob_start();
            $arData = include $arPath;
            $enData = include $enPath;
            $parityLoaded = true;
        } catch (Throwable $exception) {
            $structural[PARITY_AR][] = 'include failed: '.$exception->getMessage();
        } finally {
            ob_end_clean();
        }
    }

    if ($parityLoaded && is_array($arData) && is_array($enData)) {
        $arKeys = flatten($arData);
        $enKeys = flatten($enData);

        if (count($arKeys) !== count($enKeys)) {
            $structural[PARITY_AR][] = sprintf(
                'Admin ar holds %d keys, en holds %d — the two must stay in step',
                count($arKeys),
                count($enKeys),
            );
        }

        $onlyAr = array_diff_key($arKeys, $enKeys);
        $onlyEn = array_diff_key($enKeys, $arKeys);

        foreach (array_keys($onlyAr) as $key) {
            $structural[PARITY_AR][] = sprintf("key '%s' exists in ar but not in en", $key);
        }

        foreach (array_keys($onlyEn) as $key) {
            $structural[PARITY_AR][] = sprintf("key '%s' exists in en but not in ar", $key);
        }
    } elseif ($parityLoaded) {
        $structural[PARITY_AR][] = 'the Admin ar/en pair did not load as PHP arrays';
    }
}

if ($checkDocs) {
    foreach (DOC_GLOBS as $glob) {
        $matches = glob($root.'/'.$glob) ?: [];
        sort($matches);

        foreach ($matches as $absolute) {
            check_doc_file($root, ltrim(str_replace($root, '', $absolute), '/'), $violations);
        }
    }
}

ksort($violations);
ksort($structural);

$total = 0;

foreach ($violations as $file => $fileViolations) {
    usort($fileViolations, static fn (array $a, array $b) => $a['line'] <=> $b['line']);

    foreach ($fileViolations as $violation) {
        $total++;
        fwrite(STDOUT, sprintf("%s:%d: %s\n", $file, $violation['line'], $violation['message']));
    }
}

foreach ($structural as $file => $messages) {
    foreach (array_unique($messages) as $message) {
        $total++;
        fwrite(STDOUT, sprintf("%s: structural — %s\n", $file, $message));
    }
}

if ($total > 0) {
    fwrite(STDOUT, sprintf(
        "\n%d violation%s across %d file%s. Specification: .agents/context/guides/arabic-terminology.md\n",
        $total,
        $total === 1 ? '' : 's',
        count($violations) + count($structural),
        count($violations) + count($structural) === 1 ? '' : 's',
    ));

    exit(1);
}

fwrite(STDOUT, "Arabic glossary: clean.\n");

exit(0);
