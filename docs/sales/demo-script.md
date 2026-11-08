# Demo script — first Egyptian SME client (20-30 min)

Seller speaks Arabic; English gloss follows each talking point. Base URL: `https://<demo-host>/admin/...`. Demo login: `manager@sijilna.demo` from `/var/tmp/pi-scratch/demo-seed/credentials-krayin.txt`. The beat minutes sum to 31, or 33 with the optional beat 11; on a 20-minute slot drop beat 9 and beat 10 rather than rushing the three moments that land.

## Pre-demo checklist

- [ ] Instance answers over TLS: `curl -s -o /dev/null -w '%{http_code}\n' https://<demo-host>/admin/login` -> `200`.
- [ ] `APP_DEBUG=false` in `.env` on the instance.
- [ ] Mail configured and a test message received; do not promise inbound sync (see "If they ask").
- [ ] Demo dataset seeded: `php artisan db:seed --class='Database\Seeders\Demo\DemoSeeder' --force`.
- [ ] Counts match `docs/ops/demo-dataset.md`: 3 users, 8 organizations, 19 contacts, 12 products, 27 leads, 10 quotes / 22 lines, 23 activities, 5 mail threads.
- [ ] Read `/var/tmp/pi-scratch/demo-seed/credentials-krayin.txt`; header must say `# Bound to connection [mysql], database [krayin]`.
- [ ] All three accounts verified by logging in through the real form: `manager@`, `agent@`, `support@sijilna.demo`.
- [ ] Delete the credentials file when the demo ends.

## The demo

**Beat 1 - Login and dashboard (3 min)**
Screen: login -> dashboard. URLs: `/admin/login`, `/admin/dashboard`.
Click: log in as manager; leave default date range; point at the six tiles and the revenue card.
Say (AR): «هذه هي لوحة المعلومات: عدد الفرص وقيمتها، والإيرادات المكتسبة والمفقودة، وأفضل المنتجات والأشخاص — كلها من بياناتك، بالعربية، على خادمك.»
Gloss (EN): "This is the dashboard: opportunity counts and values, won/lost revenue, top products and people — all from your data, in Arabic, on your server."
Why: proves Arabic-first, self-hosted, one screen for pipeline health.

**Beat 2 - Kanban is the daily screen (6 min)**
Screen: leads. URL: `/admin/leads`.
Click: show kanban columns; search by title; open card settings; drag one fixture lead to **مكتسب** and fill **قيمة الإغلاق** and the close date in the modal.
Say (AR): «هنا تعمل كل يوم. كل فرصة بطاقة، وكل مرحلة عمود. سحب البطاقة إلى مكتسب يطلب قيمة الإغلاق وتاريخها؛ سحبها إلى مفقود يطلب سبب الخسارة.»
Gloss (EN): "This is where you work daily. Each opportunity is a card, each stage a column. Dragging to won asks for close value and date; dragging to lost asks for loss reason."
Why: shows the core workflow and audit-friendly close data.

**Beat 3 - One record holds the whole client history (5 min)**
Screen: lead record. URL: `/admin/leads/view/<id>`.
Click: open a lead with activities; show **الكل**, **الملاحظات**, **المكالمات**, **الاجتماعات**, **الملفات**, **البريد الإلكتروني**, **سجلات التغيير**, **المنتجات**, **عروض الأسعار** tabs; add a note.
Say (AR): «كل مكالمة واجتماع وملاحظة وملف وبريد على هذه الفرصة في سجل واحد، مع سجل تغييرات يوضح من غيّر ماذا ومتى.»
Gloss (EN): "Every call, meeting, note, file and email for this opportunity sits in one record, with a change log showing who changed what and when."
Why: replaces scattered notes and WhatsApp history.

**Beat 4 - Contacts and companies (3 min)**
Screen: people and organizations. URLs: `/admin/contacts/persons`, `/admin/contacts/organizations`.
Click: open one person linked to a company; show 19 contacts and 8 companies.
Say (AR): «الأشخاص والمؤسسات سجلات مستقلة تُربط بالفرصة، فلا تُفقد عند انتقال الفرصة من مرحلة إلى أخرى.»
Gloss (EN): "People and companies are independent records linked to opportunities, so they are not lost when a deal moves."
Why: shows contact management beyond spreadsheets.

**Beat 5 - Arabic quote PDF (4 min)**
Screen: quotes. URLs: `/admin/quotes`, `/admin/quotes/create`, `/admin/quotes/print/<id>`.
Click: open an existing quote; show lines with quantity, price, discount, tax, expiry; print PDF.
Say (AR): «عرض سعر ببنوده وخصمه وضريبته وانتهاء صلاحيته، ويخرج ملف PDF عربي جاهزاً للإرسال.»
Gloss (EN): "A quote with line items, discount, tax and expiry, exported as a ready-to-send Arabic PDF."
Why: the document a buyer actually sends.

**Beat 6 - Calendar and invitations (2 min)**
Screen: activities. URLs: `/admin/activities`, `/admin/activities?view-type=calendar`.
Click: show meetings; mention invite.ics.
Say (AR): «الاجتماعات: تُرسَل دعوة تقويم (ics) إلى المشاركين عند الإنشاء أو التعديل. لا تذكيرات تلقائية قبل الموعد.»
Gloss (EN): "Meetings send a calendar invite (ics) to participants on create or edit. There are no automatic reminders before the appointment."
Why: honest about automation limits.

**Beat 7 - Shared mailbox (2 min)**
Screen: mail. URL: `/admin/mail/inbox`.
Click: show inbox, sent, draft folders; open a thread.
Say (AR): «صندوق بريد مشترك بخمسة مجلدات. الصادر يمرّ عبر مزوّد البريد، والبريد الوارد يُسحب من حساب IMAP.»
Gloss (EN): "A shared mailbox with five folders. Outbound goes through your mail provider; inbound is pulled from an IMAP account."
Why: shows email tied to opportunities. Do not promise inbound IMAP is complete.

**Beat 8 - Web forms capture leads (2 min)**
Screen: web forms. URL: `/admin/settings/web-forms`.
Click: show two forms with 8 fields and 5 submissions that became leads.
Say (AR): «نموذج تُضمّنه في موقعك، والفرصة تدخل مسار المبيعات مباشرة.»
Gloss (EN): "A form embedded in your website, and the opportunity enters the sales pipeline directly."
Why: shows website lead capture.

**Beat 9 - Products (2 min)**
Screen: products. URL: `/admin/products`.
Click: show a product with one SKU and a price, and the product lines it feeds into a quote. Warehouses are configured at `/admin/settings/warehouses`, but the demo dataset seeds none, so that grid is empty on a seeded instance — do not open it in front of a client, and do not claim stock is populated.
Say (AR): «منتج بترميز SKU واحد وسعر، ويُدرج في عرض السعر؛ والمخزون يُسجّل لكل مخزن عند الحاجة.»
Gloss (EN): "One product with one SKU and a price, inserted straight into a quote; stock is recorded per warehouse when you need it."
Why: shows the product catalog behind the quote lines. Do not say quotes deduct stock.

**Beat 10 - Team and data scope (2 min)**
Screen: roles, users, groups. URLs: `/admin/settings/roles`, `/admin/settings/users`, `/admin/settings/groups`.
Click: show the roles list, then open a user and show its **نطاق البيانات** field: global, group, own.
Say (AR): «أدوار ونطاق بيانات لكل مستخدم: عالمي، أو مجموعته، أو ما أنشأه هو.»
Gloss (EN): "Roles and data scope per user: global, their group, or what they created."
Why: answers "who sees what".

**Beat 11 - Automation configuration (2 min, optional)**
Screen: workflows, events, campaigns, email templates, webhooks. URLs: `/admin/settings/workflows`, `/admin/settings/marketing/events`, `/admin/settings/marketing/campaigns`, `/admin/settings/email-templates`, `/admin/settings/webhooks`.
Click: show configured items only; do not trigger anything.
Say (AR): «سير عمل، أحداث، قوالب بريد، حملات، ويب هوك — قابلة للتهيئة. تشغيلها المجدول قيد الإثبات.»
Gloss (EN): "Workflows, events, email templates, campaigns and webhooks are configurable. Scheduled execution is still being proven."
Why: shows capability without promising scheduler reliability.

## The three moments that land

1. Dragging a lead to **مكتسب** and entering close value/date.
2. The lead record: one place for notes, calls, meetings, files, emails and audit history.
3. Printing an Arabic quote PDF from a live opportunity.

## What not to show

- WhatsApp inbox: open bead `crm-2vi`; not shipped.
- Inbound email as "fully synced IMAP": open bead `crm-968`; only configured inbound parsing exists.
- Live automation/scheduler: open beads `crm-7y0`, `crm-86i`; show configuration only.
- CSV/Excel import as a finished migration tool: open bead `crm-o62`.
- Backups and error tracking: open beads `crm-7rj`, `crm-bfx`. A `/up` health route exists, but a dead queue worker leaves it green and no error tracker is wired.
- E-invoicing/tax authority integration: not a product; tax is entered manually per line.
- Quote status workflow: no draft/sent/accepted statuses; acceptance is recorded by moving the lead to **مكتسب**.
- Stock deduction on quotes: does not happen.
- Reminders before appointments: none; only invite.ics on create/edit.
- Multi-currency or multi-language in one install: one currency and one language per install.

## If they ask about X

- **CSV/Excel import of existing data:** Basic import screens exist (`/admin/settings/data-transfer/imports`), but full client data migration is open bead `crm-o62`. Migration service is priced from 8,000 EGP.
- **WhatsApp:** Open bead `crm-2vi`; not shipped.
- **Inbound email via IMAP:** Open bead `crm-968`. Outbound email works; do not promise mailbox sync.
- **Roles and permission groups:** Roles and data scopes ship; advanced permission groups are open bead `crm-px1`.
- **Scheduler reliability:** Open beads `crm-7y0` and `crm-86i`; do not claim campaigns or reminders fire reliably.
- **Backups:** Open bead `crm-7rj`; backups are operator-run on an agreed cadence, and rehearsed restore is not yet proven.
- **Error tracking and health:** A `/up` health route is registered, but it does not cover a dead queue worker, and no error tracker (Sentry/Flare/Bugsnag) is wired — exceptions only reach `storage/logs` on the client's server. Open bead `crm-bfx`.
- **Tax/e-invoicing:** Not a product; tax value is entered per line in the install's base currency (`APP_CURRENCY`, `USD` on the reference demo), not in EGP.
- **Stock deduction:** Quotes do not deduct or reserve stock.
- **Quote status:** No status field; acceptance is recorded by moving the lead to **مكتسب**.
- **Reminders:** No pre-event reminders; only invite.ics on create/edit.
- **Mobile use:** Not audited for phones; use desktop.
- **Multi-branch/multi-currency:** Scale supports more users and branches, but one currency and one language per install (`APP_CURRENCY`, set at install — `USD` on the reference demo, so prices on screen are not EGP; the EGP numbers in the offer sheet are commercial, not what the demo prints).
