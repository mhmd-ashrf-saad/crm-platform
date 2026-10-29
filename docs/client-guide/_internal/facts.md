# حقائق المنتج المُتحقَّق منها — سجلنا (Krayin fork)

**الغرض:** المصدر الوحيد للحقائق الذي تقتبس منه فصول دليل العميل السبعة عشر. لا تعِد اشتقاق أي حقيقة من الواجهة أو من الذاكرة — اقتبس من هنا، واتبع الإحالة إلى الملف الأصلي إن احتجت نصاً أطول.

**الأساس:** قراءة ساكنة لهذا المستودع عند `04c20425` (2026-10-20)، `KRAYIN_VERSION = 2.2.7` (`packages/Webkul/Core/src/Core.php:21`). لم يُشغَّل أي اختبار أو خادم أو أمر قاعدة بيانات أثناء إعداد هذا الملف.

**شكل الإحالة:** كل ادعاء يحمل الملف الذي بُني عليه بين قوسين معكوسين. المسارات نسبية إلى جذر المستودع.

**قاعدة صارمة:** كل نص عربي هنا منقول حرفياً من `packages/Webkul/Admin/src/Resources/lang/ar/app.php` أو من مُبدِّل (seeder) أو من ملف لغة آخر مُشار إليه. لا صياغة موازية.

---

## 1. المنتج واللغة

- **المنتج:** سجلنا — CRM عربي أولاً، مستضاف ذاتياً، مبني على Krayin (ترخيص MIT). الاسم والوسم «سجلنا» منقولان في `packages/Sijilna/Brand`، والنبرة المعتمدة «كل صفقة لها سجل.» (`app/Providers/BrandingServiceProvider.php:341-342`).
- **الوسم يظهر على سطحين فقط:** شاشة الدخول وقشرة البريد. شاشة الدخول تقرأ `sijilna.tagline.ar` (`packages/Sijilna/Brand/resources/views/admin/sessions/login.blade.php:24-27`)، والبريد يقرؤه في `packages/Sijilna/Brand/resources/views/admin/emails/layout.blade.php:27`. لا يظهر الوسم في شاشات العمل ولا في تذييل الصفحة (`.agents/context/operations/brand.md`).
- **اللغة العربية مكتملة في واجهة الإدارة:** `packages/Webkul/Admin/src/Resources/lang/ar/app.php` يحوي **1922** مفتاحاً، وتطابق 1:1 مع `en/app.php` (1922 مقابل 1922، صفر مفاتيح زائدة في أي جهة).
- **بقية ملفات اللغة العربية وتطابقها:** المُثبِّت `packages/Webkul/Installer/src/Resources/lang/ar/app.php` = **224** (تطابق 1:1)؛ نماذج الويب `packages/Webkul/WebForm/src/Resources/lang/ar/app.php` = **8** (تطابق 1:1)؛ جهات اتصال Google `packages/Webkul/GoogleContact/src/Resources/lang/ar/app.php` = **3** (تطابق 1:1).
- **`lang/ar/` غير موجود** في جذر Laravel — لذلك أخطاء Laravel وأخطاء الخادم تبقى بالإنجليزية ولا تُعرَّب.
- **اللغة إعداد عام واحد للمنصة كلها، لا لكل مستخدم:** تُقرأ في `app()->setLocale()` من `general.general.locale_settings.locale` داخل الوسيط `admin_locale` (`packages/Webkul/Admin/src/Http/Middleware/Locale.php:38-42`)، والوسيط مُسجَّل في `packages/Webkul/Admin/src/Providers/AdminServiceProvider.php:34,42,46`.
- **الاتجاه RTL من سمة `dir` على التخطيط:** `dir="{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'rtl' : 'ltr' }}"` في `packages/Webkul/Admin/src/Resources/views/components/layouts/index.blade.php:6` و`.../anonymous.blade.php:5`. ليس إعداداً مستقلاً — يتبع اللغة.
- **اللغات المتاحة للاختيار عشر:** `config/app.php:107-118` (`ar, en, es, fa, ja, ko, pt_BR, tr, vi, zh_CN`).
- **في هذا الـ checkout بالذات `APP_LOCALE=en` و`APP_TIMEZONE=Asia/Kolkata`:** في `.env` و`.env.example` كليهما. أي أن الواجهة العربية **غير مُفعَّلة افتراضياً هنا** حتى يغيّرها المالك من الإعدادات. الافتراضيات في `config/app.php:82,95`.
- **البريد يُعرَض LTR عند العميل حتى في نشر عربي؛ أما ملف PDF العرض العربي فيُعرَض RTL.** لا تُعِد وعد RTL للبريد. أما ملف PDF فليس LTR: في `packages/Webkul/Core/src/Traits/PDFHandler.php:28-41`، إذا كانت اللغة `ar` أو `he` يُبنى الملف بـ`Mpdf` مع `SetDirectionality('ar')` و`WriteHTML($this->adjustArabicAndPersianContent($html))` (تشكيل ArPHP)، والمسار نفسه في `packages/Webkul/Admin/src/Http/Controllers/Lead/QuoteController.php:152-167`. وdompdf (`barryvdh/laravel-dompdf`) يُستعمل لباقي اللغات فقط. (مكدّس PDF: `mpdf/mpdf ^8.2` و`khaled.alshamaa/ar-php ^6.3` و`barryvdh/laravel-dompdf ^3.1` في `composer.json:12,17,26`.)
- **الترجمة العربية للواجهة لا تعني ترجمة بيانات العميل.** أسماء قنوات البيع والمراحل والمصادر والأنواع تأتي من المُبدِّلات ومن إدخال المالك، وقد تكون عربية أو إنجليزية حسب لغة التثبيت (`$defaultLocale` في `packages/Webkul/Installer/src/Database/Seeders/Lead/PipelineSeeder.php:27`).

## 2. التنقّل و ACL

- **القائمة الجانبية تُبنى من `packages/Webkul/Admin/src/Config/menu.php`** وتُعرَض في `packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/desktop/index.blade.php`.
- **عشرة عناصر في المستوى الأول، بهذا الترتيب وبالنص المشحون:**

  | # | النص المشحون | مفتاح | المسار | مفتاح ACL |
  | --- | --- | --- | --- | --- |
  | 1 | لوحة القيادة | `dashboard` | `/{admin}/dashboard` | `dashboard` |
  | 2 | العملاء المحتملون | `leads` | `/{admin}/leads` | `leads` |
  | 3 | **العروض** | `quotes` | `/{admin}/quotes` | `quotes` |
  | 4 | البريد | `mail` | `/{admin}/mail` | `mail` |
  | 5 | الأنشطة | `activities` | `/{admin}/activities` | `activities` |
  | 6 | جهات الاتصال | `contacts` | `/{admin}/contacts/persons` | `contacts` |
  | 7 | المنتجات | `products` | `/{admin}/products` | `products` |
  | 8 | **الإعدادات** | `settings` | `/{admin}/settings` | `settings` |
  | 9 | **الإعدادات** | `configuration` | `/{admin}/configuration` | `configuration` |
  | 10 | المساعدة والموارد | `help` | `/{admin}/help` | `help` |

  المصدر: `packages/Webkul/Admin/src/Config/menu.php` (مفاتيح `key`/`route`)، والنصوص من `layouts.*` في `ar/app.php`. البادئة `/{admin}` = `config('app.admin_path')` = `env('APP_ADMIN_PATH','admin')` (`config/app.php:67`, `packages/Webkul/Admin/src/Providers/AdminServiceProvider.php:42`).
- **البند الثالث يُقرأ «العروض» لا «عروض الأسعار».** `layouts.quotes` = «العروض»، بينما عنوان الشاشة `quotes.index.title` = «عروض الأسعار» وتسمية ACL `acl.quotes` = «عروض الأسعار». ثلاث تسميات لنفس الشيء — انظر §8.
- **البندان 8 و9 متطابقان نصاً («الإعدادات»)** وهما شاشتان مختلفتان تماماً: صفحة بطاقات الإعدادات، وشجرة التهيئة. هذا أكبر مصدر لبس في القائمة كلها.
- **عنوان صفحة الإعدادات هو `/{admin}/settings/settings` — الجزء مكرَّر.** مسبارا `settings` في `packages/Webkul/Admin/src/Routes/Admin/settings-routes.php:29,33` يتراكبان على مسار الابن. من يكتب `/admin/settings` وحده لا يصل إلى صفحة البطاقات.
- **عنوان «حسابي» هو `/{admin}/account`** (`packages/Webkul/Admin/src/Routes/Admin/rest-routes.php:52-53`)، لا `/{admin}/user/account`.
- **عنوان التهيئة:** `/{admin}/configuration` بمقطعَين اختياريَّين (`{slug?}/{slug2?}`، `configuration-routes.php:9`)؛ ولغة الواجهة على `/{admin}/configuration/general/general`.
- **عنوان «البريد» يقبل الصيغتين:** `/{admin}/mail` و`/{admin}/mail/inbox` (`Route::get('{route?}', 'index')` مع `sanitize_url`، `mail-routes.php:7,15`).
- **عنصران يفتحان قائمة فرعية (flyout) بمرور المؤشر** — كل ما عداهما رابط مباشر، و`settings`/`configuration` **لا** تُظهر قائمة فرعية رغم امتلاكهما أبناءً (استثناء صريح في `sidebar/desktop/index.blade.php:20,34`):
  - **البريد:** البريد الوارد ← المسودات ← الصادر ← المرسل ← سلة المهملات (`layouts.mail.inbox/draft/outbox/sent/trash`؛ الترتيب من `sort` في `menu.php`، والصادر والمرسل يحملان `sort => 4` نفسه).
  - **جهات الاتصال:** الأشخاص ← المؤسسات (`layouts.persons` / `layouts.organizations`).
- **كل بند قائمة يُرشَّح بـ ACL:** دور بلا المفتاح لا يرى البند إطلاقاً (الترشيح في `packages/Webkul/Admin/src/Http/Middleware/Bouncer.php`، موسوم باسم `user` في `packages/Webkul/Admin/src/Providers/AdminServiceProvider.php:32`، ويُستدعى عبر `menu()`).
- **شجرة التهيئة كلها محكومة بمفتاح واحد:** `configuration` (`packages/Webkul/Admin/src/Config/acl.php`). من يملكه يرى الشجرة كاملة، ولا توجد صلاحية لكل تبويب.
- **بطاقات صفحة الإعدادات** (`packages/Webkul/Admin/src/Resources/views/settings/index.blade.php` تقرأ أبناء `settings` من `menu.php`):
  - **المستخدم** ← المجموعات، الأدوار، المستخدمين (الاسم `layouts.user` = «المستخدم»، لا «المستخدمون»).
  - **عميل محتمل** ← **الأنابيب**، المصادر، الأنواع.
  - **المخزون** ← المستودعات.
  - **الأتمتة** ← الخصائص، قوالب البريد الإلكتروني، الفعاليات، الحملات، الويب هوك، سير العمل، نقل البيانات.
  - **إعدادات أخرى** ← العلامات، **Google Contacts**، **نماذج الويب**.
- **الشاشتان في «إعدادات أخرى» لا في القائمة الجانبية:** جهات اتصال Google (`/{admin}/settings/google-contacts`، `packages/Webkul/Admin/src/Routes/Admin/settings-routes.php:89`) ونماذج الويب (`/{admin}/settings/web-forms`، السطر 102) بلا بند في القائمة الجانبية، لأن القائمة لا تُظهر قوائم فرعية تحت بندَي `settings` و`configuration` (`packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/desktop/index.blade.php:20,34`). لكن لكل منهما **بطاقة في صفحة الإعدادات تحت «إعدادات أخرى»** (`admin::app.layouts.other-settings`): كل حزمة تُدمج بندها في `menu.admin` بـ`mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin')` (`packages/Webkul/GoogleContact/src/Providers/GoogleContactServiceProvider.php:34`، `packages/Webkul/WebForm/src/Providers/WebFormServiceProvider.php:74`) بمفتاحَي `settings.other_settings.google_contacts` و`settings.other_settings.web_forms` (`Config/menu.php:5` في الحزمتين)، والمزوّدان مسجَّلان في `bootstrap/providers.php:56,65`، وصفحة البطاقات ترسم أبناء `settings` كلهم (`packages/Webkul/Admin/src/Resources/views/settings/index.blade.php:18,34`). **ولكل شاشة مفتاح ACL** (تُدمج `Config/acl.php` بالمفتاح `acl` في المزوّدَين): `settings.other_settings.google_contacts` (`packages/Webkul/GoogleContact/src/Config/acl.php:5`) و`settings.other_settings.web_forms` مع `.view/.create/.edit/.delete` (`packages/Webkul/WebForm/src/Config/acl.php:5,10,15,20,25`)، وشجرة الأذونات تُبنى من `config('acl')` كاملاً (`packages/Webkul/Core/src/Acl.php:93`). **وتسمية بطاقة Google إنجليزية:** `google_contact::app.menu.title` = **Google Contacts** كما هي (`packages/Webkul/GoogleContact/src/Resources/lang/ar/app.php:9`).
- **`permission_type`:** `all` (مسؤول يتجاوز ACL) أو `custom` (قائمة مفاتيح) — `packages/Webkul/User/src/Database/Migrations/2021_03_12_074597_create_roles_table.php:20`.
- **نطاق الرؤية منفصل عن الدور:** عمود `view_permission` بقيم `global | group | individual` (`packages/Webkul/Admin/src/Http/Controllers/Settings/UserController.php:90`). مع `individual` يُرى فقط ما أنشأه المستخدم.
- **حرّاس لا تُخترق من الواجهة** (`ar/app.php` تحت `errors.*`):
  - المسؤول الرئيسي (id 1) لا يُخفَّض ولا يُعطَّل: «حساب المسؤول الرئيسي محمي ولا يمكن تعديله من هنا.»
  - لا يمنح غير المسؤول صلاحية لا يملكها: «يمكنك فقط تعيين دور صلاحياته مماثلة لصلاحياتك أو أقل منها.»
  - ولا يمنح نطاقاً أوسع: «لا يمكنك منح نطاق بيانات أوسع من نطاقك.»
  - ولا يغيّر دوره أو نطاقه: «لا يمكنك تغيير دورك أو نطاق بياناتك.»
- **لا يوجد تدفّق دعوة.** المدير يضع كلمة المرور في نافذة إنشاء المستخدم؛ وكلمة المرور **اختيارية** (`'password' => 'nullable'`، `UserController.php:88`). بريد «تم إضافتك كعضو.» يحمل **اسم المستخدم فقط** ولا كلمة مرور ولا رمزاً (`packages/Webkul/Admin/src/Notifications/User/Create.php:24-30`: يمرّر `user_name` وحده)، وفشله يُبتلع صامتاً (`try { Mail::queue(...) } catch (\Exception $e) { report($e); }`، `UserController.php:135-139`). فإن تُركَت كلمة المرور فارغة، الطريق الوحيد هو «نسيت كلمة المرور؟» — أي SMTP عامل.

## 3. سطح البيع

- **لوحة القيادة.** ستة مربّعات: «إجمالي العملاء المحتملين»، «متوسط قيمة العملاء المحتملين»، «متوسط العملاء المحتملين في اليوم»، «إجمالي العروض»، «إجمالي الأشخاص»، «إجمالي المؤسسات» (`dashboard.index.over-all.*`). بطاقة إيرادات: «الإيرادات المكتسبة» / «الإيرادات المفقودة» (`dashboard.index.revenue.*`). بطاقة عملاء محتملين. خمسة رسوم: «الإيرادات حسب المصادر»، «الإيرادات حسب الأنواع»، «أفضل المنتجات مبيعاً»، «أفضل الأشخاص»، «العملاء المحتملين المفتوحين حسب المراحل». زر «تصدير PDF» (`dashboard.index.export-pdf`). المحدّدات: اليوم / أمس / آخر 30 يومًا / هذا الأسبوع / هذا الشهر / الشهر الماضي / آخر 3 أشهر / آخر 6 أشهر / هذه السنة (`dashboard.index.date-presets.*`).
- **تحذير الأرقام:** الأدوات لا تتفق على أساس تاريخي واحد. «إجمالي الأشخاص» يعدّ `persons.created_at` (`packages/Webkul/Admin/src/Helpers/Reporting/Person.php:44`) بينما «أفضل الأشخاص» يعدّ `leads.closed_at` (`Person.php:62`). «أفضل المنتجات مبيعاً» و«إيرادات المنتجات» يعتمدون `leads.closed_at` **بلا تقييد بمرحلة الفوز** (`packages/Webkul/Admin/src/Helpers/Reporting/Product.php:38,79`) — فالعروض الخاسرة تدخل في «الأفضل مبيعاً». بالمقابل بطاقة الإيرادات **مقيَّدة** بمراحل الفوز/الخسارة عبر `stageIds` (`Lead.php:85-110`). النتيجة: الأرقام لا تُجمَع يدوياً ولا تُطابَق. الشروع في التفصيل في الفصل 03.
- **العملاء المحتملون:** قائمة + كانبان، **وكانبان هو العرض الافتراضي**. قنوات بيع بمراحل، قاعدة «أيام التعفن»، علامات، بنود منتجات داخل الصفقة، رفع ملفات Magic AI، ملاحظات وملفات داخل البطاقة، ومطابقة شخص موجود. (`packages/Webkul/Admin/src/DataGrids/Lead/LeadDataGrid.php`، `packages/Webkul/Admin/src/Resources/views/leads/`.)
- **الأنشطة — سبعة أنواع** في واجهة البطاقة: `note, call, meeting, lunch, file, email, system`، وعلاماتها «الملاحظات/المكالمات/الاجتماعات/الغداء/الملفات/البريد الإلكتروني/سجلات التغيير» (`components.activities.index.*`، والقائمة في `packages/Webkul/Admin/src/Resources/views/components/activities/index.blade.php:418-444`).
- **لكن صفحة «الأنشطة» العامة في القائمة الجانبية تعرض ثلاثة أنواع فقط:** `->whereIn('type', ['call', 'meeting', 'lunch'])` (`packages/Webkul/Admin/src/DataGrids/Activity/ActivityDataGrid.php:35`). الملاحظات والملفات والبريد لا تظهر إلا داخل السجل الذي تنتمي إليه.
- **لا تذكيرات موعَد بها، لكن البريد يخرج:** لا شيء ينطلق عند الموعد المجدول ولا قبله — لا منبّه ولا رسالة تذكير. المُجدولان الوحيدان `inbound-emails:process` كل 5 دقائق (`routes/console.php:22`) و`campaign:process` يومياً (`packages/Webkul/Marketing/src/Providers/MarketingServiceProvider.php:19`). وأما البريد فله إجراءان في وحدة الأتمتة: `send_email_to_sales_owner` و`send_email_to_participants` (`packages/Webkul/Automation/src/Helpers/Entity/Activity.php:181,185`)، يُنفَّذان بـ`Mail::queue(...)` مع مرفق `invite.ics` (`Activity.php:224-270`)، ويُربطان بحدثَي `activity.create.after` و`activity.update.after` (`packages/Webkul/Automation/src/Config/workflows.php:28,31`). المُبدَّأ: سيرا عمل يرسلان `send_email_to_participants` عند الإنشاء والتحديث لأنشطة `call/meeting/lunch` (`packages/Webkul/Installer/src/Database/Seeders/Workflow/WorkflowSeeder.php:34,45`). والافتراضي في الواجهة إشعار جرس داخلي عند **الإنشاء** وحده، لا عند كل تعديل (`packages/Webkul/Admin/src/Providers/EventServiceProvider.php:25-32`).
- **جهات الاتصال:** أشخاص ومؤسسات. **حذف مؤسسة لا يحذف أشخاصها:** أحدث ترحيل يُنشئ مفتاح الربط من جديد بـ`onDelete('set null')`، فيبقى الشخص ويُفرَّغ حقل `organization_id` وحده (`packages/Webkul/Contact/src/Database/Migrations/2025_03_19_132236_update_organization_id_column_in_persons_table.php:17`). فالنتيجة شخص بلا مؤسسة في قائمة **الأشخاص**، لا شخص محذوف. شخص مرتبط بعملاء محتملين لا يُحذف — والرسائل المشحونة: «تم حذف بعض الأشخاص بنجاح. لم يتم حذف الآخرين لأنهم مرتبطون بعملاء محتملين.» و«لم يتم حذف أي من الأشخاص المحددين لأنهم مرتبطون بعملاء محتملين.» (`contacts.persons.index.partial-delete-warning` / `none-delete-warning`).
- **الاستيراد:** ثلاثة كيانات فقط — الأشخاص والمنتجات والعملاء المحتملون (`packages/Webkul/DataTransfer/src/Config/importers.php`، والمجلدات `Helpers/Importers/{Persons,Products,Leads}`).
- **التصدير:** موجود لجدول العملاء المحتملين والأشخاص فقط — عنصر `<x-admin::datagrid.export` مشحون في `leads/index.blade.php:33` و`contacts/persons/index.blade.php:18` ولا مكان آخر.
- **عروض الأسعار:** بنود بسعر وكمية وخصم و`tax_amount`، وتاريخ `expired_at`، وملف PDF عربي (mPDF + ArPHP)، ويُرسَل بالبريد بقالب ثابت. **لا حالات لعرض السعر إطلاقاً** — لا مسودة ولا مُرسَل ولا مقبول؛ جدول `quotes` لا يحمل عمود حالة (`packages/Webkul/Quote/src/Database/Migrations/2021_07_01_230345_create_quotes_table.php`). القبول يُسجَّل بنقل **العميل المحتمل** إلى مرحلة فوز. **العروض لا تمسّ المخزون** (لا ذكر للمخزون في `packages/Webkul/Quote/` ولا في `packages/Webkul/Admin/src/Http/Controllers/Quote/`).
- **المنتجات:** نوع SKU مسطّح واحد، بلا متغيّرات ولا `type` ولا `parent_id` في الجدول (`packages/Webkul/Product/src/Database/Migrations/2021_04_09_065617_create_products_table.php`). المخزون لكل مستودع في `product_inventories`.
- **البريد:** صندوق مشترك بالمجلدات الخمس. الوارد عبر مزامنة IMAP (`inbound-emails:process`) أو Sendgrid. **الإنشاء يُبلّغ بالنجاح حتى لو فشل الإرسال** — انظر §7.
- **شريط الرأس:** «البحث الشامل» بستة تبويبات: العملاء المحتملين / عروض الأسعار / الأشخاص / المنتجات / الإعدادات / التهيئة (`components.layouts.header.mega-search.tabs.*`). «إضافة سريعة» (`layouts.quick-add.title`). جرس «الإشعارات». مبدّل الوضع الليلي. قائمة الصورة الشخصية: «الإصدار: :version» ثم «حسابي» ثم «تسجيل الخروج» (`layouts.app-version`, `layouts.my-account`, `layouts.sign-out`؛ `components/layouts/header/index.blade.php:88-141`).
- **«إضافة سريعة» تنشئ ستة أشياء:** عميل محتمل، **العروض**، شخص، مؤسسة، منتج، **البريد الإلكتروني** (رسالة) — `<x-admin::attributes>` لكل نوع (`components/layouts/header/quick-creation.blade.php:378-396`). كل تبويب مشروط بصلاحية `<entity>.create.quick-create` (`quick-creation.blade.php:2-7`). **التبويب السادس بريد لا نشاط.**

## 4. سطح التهيئة (شجرة واحدة: `packages/Webkul/Admin/src/Config/core_config.php`)

**بنية المسار في الواجهة — لا تُدرِج «التهيئة» خطوةً.** بند القائمة اسمه **الإعدادات** (مثل بند صفحة البطاقات)، و«التهيئة» لا تظهر إلا تسميةً في ACL وتبويباً في البحث الشامل. الشجرة ثلاث طبقات، وكل طبقة تعرض بطاقات:

| المسار | البطاقات المعروضة |
| --- | --- |
| `/{admin}/configuration` | **عام**، **إعدادات البريد الإلكتروني** |
| `/{admin}/configuration/general` | **عام**، **الإعدادات**، **الذكاء الاصطناعي السحري** |
| `/{admin}/configuration/general/general` | نموذج فيه **إعدادات اللغة** و**شعار الإدارة** |
| `/{admin}/configuration/general/settings` | **إعدادات قسم "تم تطويره بواسطة"**، **إعدادات عناصر القائمة**، **إعدادات ألوان عناصر القائمة**، **إعدادات لوحة التحكم**، **إعدادات الإشعارات** |
| `/{admin}/configuration/general/magic_ai` | **الذكاء الاصطناعي السحري** (عدا زر التفعيل) و**إنشاء المستندات** |
| `/{admin}/configuration/email/imap` | **حساب IMAP** |

- **مسار اللغة الصحيح:** **الإعدادات ← عام ← عام ← إعدادات اللغة**. مسار تسميات القائمة: **الإعدادات ← عام ← الإعدادات ← إعدادات عناصر القائمة** — والاسمان «الإعدادات» في هذا المسار مختلفان (`layouts.configuration` ثم `configuration.index.general.settings.title`).
- **عنوان شاشة التهيئة يُقرأ من تسمية بند القائمة** لا من `configuration.index.title`: `menu()->getLabel('configuration', 'admin::app.configuration.index.title')` (`packages/Webkul/Admin/src/Resources/views/configuration/index.blade.php:4,15`). فإذا أعاد المالك تسمية البند تغيّر عنوان الصفحة معه.
- **زر الحفظ في كل شاشات التهيئة هو «حفظ التكوين»** (`configuration.index.save-btn`)، لا «حفظ الإعدادات».

- **`/configuration/general/general`:**
  - «إعدادات اللغة» — `locale`، قائمة منسدلة من `Webkul\Core\Core@locales`، الافتراضي `en` (الأسطر 30-45). **هذا هو المفتاح العام للغة.**
  - «شعار الإدارة» — `logo_image` (`mimes:bmp,jpeg,jpg,png,webp`) و`favicon_image` (`mimes:ico,jpeg,jpg,png,webp`) (الأسطر 46-68).
- **`/configuration/general/settings`** (الأسطر 69-…):
  - «إعدادات قسم "تم تطويره بواسطة"» — `show` و`label`.
  - «إعدادات عناصر القائمة» — **ستة عشر** حقلاً نصياً، `validation => 'max:20'`، بمفاتيح `dashboard, leads, quotes, mail, mail.inbox, mail.draft, mail.outbox, mail.sent, mail.trash, activities, contacts, contacts.persons, contacts.organizations, products, settings, configuration`. **تسميات القائمة قابلة للتغيير لكل عميل، بحد 20 حرفاً.**
  - «إعدادات ألوان عناصر القائمة» — `brand_color` (نوع `color`).
  - «إعدادات لوحة التحكم» — `date_range` بقيم `1_month, 3_months, 9_months, 1_year, 2_years, custom` («شهر واحد، 3 أشهر، 9 أشهر، سنة واحدة، سنتان، مخصص») و`custom_days` (الافتراضي 30). **هذه ليست محدّدات اليوم/الأسبوع الظاهرة على اللوحة نفسها.**
  - «إعدادات الإشعارات» — `enabled` (الافتراضي 1) و`sound` (الافتراضي 0).
- **`/configuration/general/magic_ai`:** «الذكاء الاصطناعي السحري» — `enable`، و`api_key` مفتاح OpenRouter/الذكاء الاصطناعي **يُكتَب في الواجهة**، و`model` من ستة خيارات، و`other_model` نص حر، و`doc_generation` (الأسطر 216-300). يعتمد إنشاء وصف العميل المحتمل من ملف على هذا القسم.
- **`/configuration/email/imap`:** «حساب IMAP» — `host, port, encryption, validate_cert, username, password` (الأسطر 330-373). **هذا وارد فقط.**
- **لا شاشة SMTP في المنتج إطلاقاً.** البريد الصادر من `.env` وحده: `MAIL_MAILER / MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD / MAIL_ENCRYPTION / MAIL_FROM_ADDRESS / MAIL_FROM_NAME`، ويحتاج `php artisan config:clear` بعده.
- **قائد الوارد `MAIL_RECEIVER_DRIVER`:** `webklex-imap` أو `sendgrid`، والافتراضي **sendgrid**. الويب هوك `POST /{admin}/mail/inbound-parse` موسوم بالوسيط `inbound_email_token` ويحتاج `MAIL_RECEIVER_INBOUND_TOKEN` وإلا 503 (`packages/Webkul/Admin/src/Providers/AdminServiceProvider.php:38`, `config/mail-receiver.php:16,35`).
- **الخصائص (`/configuration` → لا؛ بل الإعدادات ← الأتمتة ← «الخصائص»):** **ستة** أنواع كيانات: `leads`, `persons`, `organizations`, `products`, `quotes`, `warehouses` (`packages/Webkul/Admin/src/Config/attribute_entity_types.php`)؛ أنواع الحقول تشمل `select`, `multiselect`, `checkbox`, `lookup`. **`code` غير قابل للتعديل بعد الاستخدام** لأنه يصبح رأس عمود الاستيراد. خصائص النظام لا تُحذف. إضافة «مطلوب» متأخراً تُقفل تعديل السجلات القديمة. **حسّم الخصائص قبل الاستيراد.** (`packages/Webkul/Attribute/`.) **وتسميات نوع الحقل تختلف بين الشاشتين:** شبكة الخصائص تقول `قائمة منسدلة` و`قيمة منطقية` و`مربع اختيار` و`سعر`، وشاشة الإنشاء تقول `اختيار` و`منطقي` و`خانة اختيار` و`السعر` (`Admin/src/Resources/lang/ar/app.php`، تحت `settings.attributes.index.datagrid.types` و`settings.attributes.create`). في الدليل يُنقَل نص الشاشة التي يوصفها الفصل، لا نص الشبكة.
- **نماذج الويب:** اختيار الخصائص والمطلوب والمخفي، و`lead_pipeline_id` (يسقط إلى القناة الافتراضية)، ومفتاح `create_lead`، والتضمين `…/web-forms/forms/<id>/form.js` (التقديم العام `POST …/web-forms/forms/<id>`، `packages/Webkul/WebForm/src/Routes/routes.php:7,11`). **لا حماية من السبام** — تُعالَج عند الوسيط/الخادم.
- **نقل البيانات:** استيراد الأشخاص والمنتجات والعملاء المحتملين. `php artisan storage:link` مطلوب وقد نفّذه المُثبِّت (`packages/Webkul/Installer/src/Console/Commands/Installer.php:153`, `Helpers/DatabaseManager.php:208`).
- **الأتمتة:** سير العمل، الفعاليات، الحملات، الويب هوك. **أبقِ الحملة على `status=0` حتى يُفهَم التقسيم** — حملة عاملة تراسل كل جهة اتصال.

## 5. ما لا واجهة له (مستوى النشر)

| المفتاح | القيمة في `.env` و`.env.example` هنا | الأثر |
| --- | --- | --- |
| `APP_LOCALE` | `en` | اللغة الافتراضية للمنصة؛ عربي يحتاج `ar` |
| `APP_TIMEZONE` | `Asia/Kolkata` | التوقيت المشحون — يجب تغييره لتوقيت العميل |
| `APP_CURRENCY` | `USD` | **عملة واحدة فقط** لكل مثبَّت؛ لا تعدّد عملات |
| `APP_ADMIN_PATH` | فارغ → `admin` | مسار لوحة الإدارة (`config/app.php:67`) |
| `NOTIFICATION_DRIVER` | `polling` | `polling \| reverb \| pusher` (`config/notifications.php:34`) |
| `NOTIFICATION_POLL_INTERVAL` | `30` | ثواني بين طلبات المتصفح |
| `QUEUE_CONNECTION` | `sync` | لا حاجة لعامل قائمة انتظار؛ البريد يُرسل داخل الطلب |
| `MAIL_RECEIVER_DRIVER` | `sendgrid` | بلا `MAIL_RECEIVER_INBOUND_TOKEN` يبقى الويب هوك 503 |

- **الافتراضيات مفاتيح `config/app.php`:** التوقيت السطر 82، اللغة 95، العملة 155، مسار الإدارة 67.
- **مفتاح طريقة الإشعار ومدّته لهما نصوص عربية مشحونة** (`configuration.index.general.settings.notifications.driver*` و`poll-interval*`) **لكن لا حقول لهما في `core_config.php`** — أي أنهما من `.env` فقط ولا تُغيَّران من الواجهة.
- **لا شيء في هذا المستودع يُثبِّت مُجدولاً ولا عامل قائمة انتظار.** المهام المجدولة: `inbound-emails:process` كل 5 دقائق و`campaign:process` يومياً. بدون `schedule:run` على الخادم **لا يصل بريد وارد ولا تنطلق حملة، وبصمت تام**.
- **النسخ الاحتياطي والاحتفاظ خارج الواجهة:** خط النشر يُفرّغ MySQL قبل كل نشر (يحتفظ بـ10)، وهناك تفريغ ليلي. **لا سياسة احتفاظ داخل CRM** — البريد والأنشطة والاستيرادات تنمو بلا حد.
- **المُثبِّت ست خطوات عربية** (بداية/متطلبات الخادم/إعدادات المتجر/جاهز للتثبيت/إنشاء المسؤول/اكتمال التثبيت) وبعلامة «سجلنا»، واختياراته للقاعدة والعملة والتوقيت **دائمة** (العملة `env('APP_CURRENCY')` في `config/app.php:155` بلا شاشة، والتوقيت كذلك). **واللغة ليست دائمة:** هي إعداد عام واحد للمنصة كلها يتغيّر **بعد التثبيت** من **الإعدادات ← عام ← عام ← إعدادات اللغة** — `general.general.locale_settings.locale` (`packages/Webkul/Admin/src/Config/core_config.php:19-31`) — ويقرؤه الوسيط `admin_locale` (§1). لكن التثبيت **عمل المزوّد**، فلا يأمر دليل العميل أحداً بتشغيله. نصوصه في `packages/Webkul/Installer/src/Resources/lang/ar/app.php`.

### سقف رفع الملفات — ثلاث طبقات، ولا إعداد له في الواجهة (مُقاس 2026-10-29)

- **السقف لا يُضبط من الواجهة إطلاقاً.** ثلاث طبقات ترفض الملف الكبير، وكل واحدة برسالة مختلفة: `nginx` (`client_max_body_size`)، ثم `PHP` (`post_max_size` ثم `upload_max_filesize`)، ثم تحقّق التطبيق (`max:` في قواعد التحقق). من يرفع ملفاً أكبر من سقف PHP لا يرى رسالة عربية: يرى صفحة **500/413** بنص `The POST data is too large.` من `vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php:24` — لأن `lang/ar/` غير موجود في جذر Laravel (§1).
- **السقف المشحون في الحاوية `64M` في الطبقتين الأوليين:** `deploy/docker/php.ini` (`upload_max_filesize = 64M`, `post_max_size = 64M`) و`deploy/docker/nginx.conf` (`client_max_body_size 64m`).
- **سقف التطبيق ملف واحد مركزي:** `packages/Webkul/Core/src/Config/uploads.php`، يُدمج بمفتاح `uploads` من `packages/Webkul/Core/src/Providers/CoreServiceProvider.php` (سطر `mergeConfigFrom`).
  - `uploads.max_size_kb` = **65536** (64 م.ب)، يُغيَّر بـ`UPLOAD_MAX_SIZE_KB`. يقرأه رفع صورة المحرّر (`.../Admin/src/Http/Controllers/TinyMCEController.php:30`، وكان `max:8192`). وأما خصائص الملف على العملاء المحتملين وجهات الاتصال فبلا سقف تطبيقي إطلاقاً (`LeadForm.php`)، فسقفها الوحيد هو PHP.
  - `uploads.mail_attachment_max_kb` = **20480** (20 م.ب)، يُغيَّر بـ`UPLOAD_MAIL_ATTACHMENT_MAX_KB`، ويقرأه مرفق البريد (`.../Admin/src/Http/Controllers/Mail/EmailController.php:165`). **أقل من 64 عن قصد:** `AttachmentRepository::prepareData()` يقرأ الملف كاملاً في الذاكرة ثم يُرمَّز base64 للإرسال، أي نحو ثلاثة أمثال حجمه ذاكرةً، و`memory_limit` في الحاوية `256M` (`deploy/docker/php.ini`).
  - `uploads.web_form_max_size_kb` = **2048** (2 م.ب)، يُغيَّر بـ`UPLOAD_WEB_FORM_MAX_SIZE_KB`، ويقرأه نوع **ملف/صورة** في نموذج الويب (`packages/Webkul/WebForm/src/Http/Requests/WebForm.php:95`). **القيمة لم ترتفع عن قصد:** المسار مجهول بلا مصادقة وبلا حدّ معدّل ويكتب على قرص منشور، فسقف مرتفع يعني أن يقرّر زائر مجهول كم يشغل من قرص. الاختبار `tests/Feature/Security/WebFormFileUploadTest.php` يرفض ملفاً بحجم 4 م.ب لهذا السبب. العقود والكشوف الممسوحة تُرفع من الشاشات الموثّقة، لا من هنا.
- **خصائص الملف على العملاء المحتملين وجهات الاتصال بلا سقف في التطبيق:** `LeadForm.php` لا يضع `max:` على نوعي `file` و`image`، فالسقف الوحيد لها هو PHP.
- **القيمة المقاسة على هذا الجهاز قبل الإصلاح وبعده (PHP 8.5 CLI، `/etc/php/8.5/cli/php.ini`):** `upload_max_filesize` من `2M` إلى `64M`، `post_max_size` من `8M` إلى `64M`، `memory_limit` بقي `-1`. بملف PDF حقيقي 12,338,004 بايت (12 صفحة فاتورة ممسوحة): قبل ← `413`، بعد ← `302` (حُفظ)، ثم تنزيله من `/admin/mail/attachment-download/{id}` بـ`200` و`Content-Length: 12338004`، وبقي متاحاً بعد إعادة تشغيل الخادم بنفس البايتات (`sha256` مطابق).
- **`post_max_size` يجب أن يكون أكبر من `upload_max_filesize`**: الطلب يحمل الملف إضافةً إلى الحقول وحدود multipart، فتركهما متساويين يجعل ملفاً بحجم السقف بالضبط يُرَدّ.
- **رفع السقف على الخادم ليس من الواجهة ولا من المستودع:** يُكتب في `php.ini` الذي يحمّله الخادم (على هذا الجهاز `/etc/php/8.5/cli/php.ini`، أو ملف في `conf.d/` بعده أبجدياً) ويحتاج صلاحية `root`، ثم إعادة تشغيل الخادم. `public/.user.ini` **لا يُقرأ** في تشغيل `php artisan serve` (يعمل بـ SAPI `cli-server`، و`.user.ini` خاص بـ`CGI/FPM`)، فلا تعتمد عليه هنا. وبلا `root` يمكن للاختبار فقط تشغيل الخادم بمجلد إضافي للقراءة: `PHP_INI_SCAN_DIR="/etc/php/8.5/cli/conf.d:<dir>" make serve` مع ملف `<dir>/99-uploads.ini` يحمل نفس المفاتيح — وهذا ما استُخدم في القياس أعلاه، وليس إصلاً.

## 6. البيانات المُبدَّأة وحالات الفراغ

**قناة البيع الافتراضية «قناة المبيعات الافتراضية»** بست مراحل، **بهذا الترتيب** (`sort_order` 1→6)، من `packages/Webkul/Installer/src/Database/Seeders/Lead/PipelineSeeder.php:33-83` ونصوص `seeders.lead.pipeline.*`:

| sort | code | النص المشحون | الاحتمال |
| --- | --- | --- | --- |
| 1 | `new` | جديد | 100 |
| 2 | `follow-up` | متابعة | 100 |
| 3 | `prospect` | عميل محتمل | 100 |
| 4 | `negotiation` | مفاوضات | 100 |
| 5 | `won` | مُنتصر | 100 |
| 6 | `lost` | مفقود | 0 |

- **المصادر الخمسة:** مباشر، البريد الإلكتروني، الهاتف، ويب، نموذج ويب (`Lead/SourceSeeder.php`).
- **النوعان:** عمل جديد، عمل موجود (`Lead/TypeSeeder.php`).
- **الدور المُبدَّأ:** اسمه «مسؤول» ووصفه «دور المسؤول»، و`permission_type='all'` (`User/RoleSeeder.php`, `seeders.user.role.*`).
- **سيرا عمل مُبدَّآن:** «رسائل بريد إلكتروني للمشاركين بعد إنشاء النشاط» و«… بعد تحديث النشاط» (`Workflow/WorkflowSeeder.php`).
- **حالات الفراغ — تُقتبَس حرفياً ولا تُعاد صياغتها:**
  - الشبكات: «لا توجد سجلات متاحة.» (`components.datagrid.table.no-records-available`)
  - كانبان العملاء المحتملين: «قائمة العملاء الخاصة بك فارغة» + «قم بإنشاء عميل لتنظيم أهدافك.» (`leads.index.kanban.empty-list` / `empty-list-description`)
  - جرس الإشعارات: «لا توجد إشعارات بعد.» (`notifications.empty`)
  - الرسوم: «لا توجد بيانات متاحة للفترة المختارة» (`dashboard.index.revenue-by-sources.empty-info` وغيرها بنفس النص)
  - «أفضل المنتجات مبيعاً»: «لم يتم العثور على منتجات» (`dashboard.index.top-selling-products.empty-title`)
  - «أفضل الأشخاص»: «لم يتم العثور على أشخاص» (`dashboard.index.top-persons.empty-title`)
  - البحث الشامل: «لا توجد سجلات تطابق بحثك.» (`components.layouts.header.mega-search.empty-results`)
  - عروض السعر داخل العميل المحتمل: «لم يتم العثور على عروض لهذه الفرصة» (`leads.view.quotes.empty-info`)
  - نشاط داخل سجل: المفتاح `components.activities.index.empty-placeholders.all.title` = «لم يتم العثور على أي أنشطة».

## 7. العيوب والمصائد المؤكَّدة

كل بند هنا مُتحقَّق من كود المصدر، لا من انطباع.

1. **الدخول المُقيَّد يظهر كخطأ خادم 500.** `abort(429)` عند تجاوز المحاولات (`packages/Webkul/Admin/src/Http/Controllers/User/SessionController.php:107`)، ثم مُعالج الاستثناءات يحوّل كل رمز ليس في `[401, 403, 404, 503]` إلى **500** (`packages/Webkul/Admin/src/Exceptions/Handler.php:82-85`)، فتُعرَض صفحة «500 خطأ داخلي في الخادم». الحدّ: 5 محاولات فاشلة لكل مجموعة بيانات، و20 لكل عنوان IP في الدقيقة؛ المحاولة الناجحة لا تُحسَب ولا تُمسَح إلا لمجموعة البيانات نفسها.
2. **حفظ عميل محتمل من شاشة التعديل قد يُعيد مرحلته إلى الأولى.** إن لم يُرسَل `lead_pipeline_stage_id` في الطلب، يُسقَط العميل إلى أول مرحلة في القناة الافتراضية (`packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php:278-291`).
3. **العنوان والوصف غير قابلين للتحرير المضمَّن** في صفحة عرض العميل المحتمل.
4. **لا تحويل من عميل محتمل إلى جهة اتصال.** الطريق: أرفق شخصاً موجوداً، أو أنشئ الشخص أولاً.
5. **حذف مؤسسة لا يحذف أشخاصها:** مفتاح الربط `persons.organization_id` مُعرَّف بـ`onDelete('set null')` في الترحيل `2025_03_19_132236_update_organization_id_column_in_persons_table.php:17` (وكان `cascade` قبلها). الأشخاص يبقون بلا مؤسسة، ولا يذكُر نص الحذف هذا (§3).
6. **مفتاح ACL `contacts` يشير إلى مسار غير مسجَّل:** `'route' => 'admin.contacts.users.index'` (`packages/Webkul/Admin/src/Config/acl.php:150-152`). التحكم الفعلي يقع على المفاتيح الأبناء، لكن المفتاح الأب ما زال لازماً لظهور القائمة الفرعية.
7. **الحذف الجماعي والتصدير غير محكومين بـ ACL على العملاء المحتملين والعروض والمنتجات والمؤسسات والأنشطة.** الأشخاص وحدهم محميّون: `if (bouncer()->hasPermission('contacts.persons.delete'))` في `packages/Webkul/Admin/src/DataGrids/Contact/PersonDataGrid.php:231`. قارن `LeadDataGrid.php:369`, `Product/ProductDataGrid.php:165`, `OrganizationDataGrid.php:111`, `Activity/ActivityDataGrid.php:212`, `Quote/QuoteDataGrid.php:228` — بلا شرط.
8. **إجراء الحذف الجماعي مُسجَّل مرتين في شبكة العروض**، فتظهر في الواجهة ميزتان متطابقتان كلتاهما «حذف» إلى `admin.quotes.mass_delete` (`packages/Webkul/Admin/src/DataGrids/Quote/QuoteDataGrid.php:228-243`).
9. **إرفاق علامة بعميل محتمل يحتاج صلاحية إعدادات العلامات.** المساران `admin.leads.tags.attach` و`admin.leads.tags.detach` مربوطان بمفتاحَي `settings.other_settings.tags.create` و`.delete` (`acl.php:570-582`). فالمستخدم العادي يرى أداة العلامات ويُرفَض.
10. **رسالة تصدير الأشخاص تسمّي البطاقة بالعربية وهي مسجّلة بالإنجليزية:** «يرجى توصيل حساب Google أولاً من الإعدادات > إعدادات أخرى > جهات اتصال Google.» والمسار صحيح — البطاقة موجودة في **إعدادات أخرى** — لكن تسميتها من `google_contact::app.menu.title` وهي **Google Contacts** بلا تعريب (`packages/Webkul/GoogleContact/src/Resources/lang/ar/app.php:9`)، فمن يبحث عنها بالعربية لا يجدها (`contacts.persons.index.datagrid.google-export-not-connected`).
11. **لا شاشة SMTP** — انظر §4.
12. **الإنشاء في البريد يبلّغ بالنجاح حتى مع فشل الإرسال:** `try { Mail::send(...) } catch (Exception $e) {}` فارغ، ثم استجابة `create-success` وإعادة توجيه إلى «المرسل» في كل الحالات (`packages/Webkul/Admin/src/Http/Controllers/Mail/EmailController.php:170-200`). التحقق الحقيقي: هل تظهر الرسالة في «المرسل»؟
13. **بريد عرض السعر لا يُحفظ في أي مجلد:** يرسل بـ`Mail::send` وحده ولا ينشئ سجلاً في صندوق النظام (`packages/Webkul/Admin/src/Http/Controllers/Lead/QuoteController.php:119-141`)، فلا شيء يظهر في **المرسل** أبداً — لا نجاحاً ولا فشلاً. الحكم الوحيد: رسالة **تعذر إرسال بريد عرض السعر.** عند الفشل، وسؤال العميل عن المرفق.
14. **أرقام اللوحة لا تتجمع** — انظر §3.
15. **تلميحات عامل قائمة الانتظار موجَّهة للمشغّل لا للبائع:** نصّان مشحونان يطلبان `php artisan queue:work` (`ar/app.php:1667,1690`). في دليل العميل يُعاد توجيههما إلى المزوّد.
16. **العروض بلا حالات، وتعبئة فراغ القبول بالمرحلة** — انظر §3.
17. **حسابي يطلب كلمة المرور الحالية دائماً**، حتى لتغيير الاسم وحده (`packages/Webkul/Admin/src/Http/Controllers/User/AccountController.php:39`) — تفصيل في الفصل 02.
18. **`/{admin}/settings` ليس صفحة.** الصفحة على `/{admin}/settings/settings` (§2).
19. **الحملة العاملة تراسل كل جهة اتصال** إن لم يُفهَم التقسيم — أبقِ `status=0`.
20. **لا تذكيرات موعَد بها، والبريد من سير العمل لا من موعد.** لا منبّه عند الموعد ولا قبله، والمُجدولان الوحيدان مهام الوارد والحملات؛ والبريد يخرج فقط بأن يُربَط إجراء `send_email_to_*` بحدثَي إنشاء أو تحديث النشاط (§3).
21. **نموذج ويب لا يُسنِد المصدر «نموذج ويب» في تثبيت عربي.** الكود يبحث عن الاسم الإنجليزي الحرفي: `$this->sourceRepository->findOneByField('name', 'Web Form')`، وإن لم يجده يأخذ `first()` (`packages/Webkul/WebForm/src/Http/Controllers/WebFormController.php:203-210`). والمُبدِّل العربي يكتب في `lead_sources` الاسم مُعرَّمًا «نموذج ويب» في الصف id 3، و`first()` يُرجع id 1 = «البريد الإلكتروني» (`packages/Webkul/Installer/src/Database/Seeders/Lead/SourceSeeder.php:24-45`). النتيجة: عميل من نموذج ويب يُسجَّل مصدره **البريد الإلكتروني**. الحل المقترح للمزوّد: تسمية المصدر `Web Form` بالإنجليزية، أو تعديل البحث ليطابق code بدل الاسم. **لا تستنتج «جاء من النموذج» من حقل المصدر.**
22. **المرحلة المسندة للنموذج هي أول مرحلة في القناة المختارة** (أو الافتراضية عند الفراغ) — `WebFormController.php:185-197`.

## 8. المصطلحات (مثبَّتة — لا يُعاد تقريرها)

| المفهوم | المصطلح في الدليل | الانحراف المشحون (يُذكَر مرة واحدة) |
| --- | --- | --- |
| lead | العميل المحتمل | — |
| pipeline | قناة المبيعات | شاشة الإعدادات تسمّي القائمة «الأنابيب» (`layouts.pipelines`)؛ و`acl.pipelines` = «قنوات البيع» |
| stage | المرحلة | — |
| person | الشخص | — |
| organization | المؤسسة | `acl.organizations` = «المنظمات» |
| (umbrella) | جهات الاتصال | — |
| activity | النشاط | — |
| quote | عرض السعر | القائمة «العروض»؛ الشاشة و`acl` «عروض الأسعار» |
| product | المنتج | — |
| warehouse | المستودع | — |
| role | الدور | — |
| group | المجموعة | — |
| data scope | نطاق الرؤية | العمود الداخلي `view_permission` |
| attribute | الخاصية | `acl.attributes` = «السمات» |
| workflow | سير العمل | — |
| campaign | الحملة | — |
| web form | نموذج الويب | — |
| owner | المالك | عمود «مالك المبيعات» (`seeders.attributes.leads.sales-owner`) |
| team | الفريق | — |
| tag | العلامة | `acl.tags` = «الوسوم» |
| won / lost | مكتسب / مفقود | المراحل المُبدَّأة: «مُنتصر» و«مفقود»؛ وكانبان يكتب «ربحت» و«خسرت» (`leads.index.kanban.stages.*`) — ثلاث صيغ لنفس الحالتين |
| configuration | التهيئة | `acl.configuration` = «التهيئة»؛ القائمة «الإعدادات» (نفس نص بند الإعدادات) |

- **الثلاث صيغ لـ won/lost ليست خطأ يجب إصلاحه في الدليل:** المرحلة اسمها «مُنتصر»/«مفقود»، ورأس عمود كانبان يكتب «ربحت»/«خسرت»، وبطاقة الإيراد تكتب «الإيرادات المكتسبة»/«الإيرادات المفقودة». اكتب ما تراه في الشاشة التي تصفها، وأشِر إلى الصيغة الأخرى عند أول ذكر فقط.
