<?php

namespace Database\Seeders\Demo;

/**
 * Static, reviewed fixture data for the demo dataset.
 *
 * Every string here is written by hand on purpose. The random-data generator is a development
 * dependency only (`composer.json` `require-dev`) and the production image is built with
 * `composer install --no-dev` (deploy/docker/php.Dockerfile), so nothing may be generated at run
 * time: on the live instance that dependency is simply not installed.
 *
 * Amounts are Egyptian pounds. Dates are stored as offsets in days relative to the day the
 * seeder runs, so the dashboard date-range report, "this month" and the calendar always have
 * something in them however long after this file was written the seeder is executed.
 */
final class DemoFixtures
{
    /**
     * Demo users, keyed by the role they play in the demo story.
     *
     * The email domain is reserved (`@sijilna.demo`) and is the only thing the wipe is allowed
     * to delete from the `users` table. See DemoIdentity.
     *
     * @return array<int, array<string, string>>
     */
    public static function users(): array
    {
        return [
            [
                'key' => 'manager',
                'name' => 'سارة عبد الرحمن',
                'email' => 'manager',
                'job_title' => 'مدير المبيعات',
            ],
            [
                'key' => 'agent',
                'name' => 'كريم مصطفى',
                'email' => 'agent',
                'job_title' => 'مندوب مبيعات',
            ],
            [
                'key' => 'support',
                'name' => 'منة الله حسن',
                'email' => 'support',
                'job_title' => 'مسؤول دعم العملاء',
            ],
        ];
    }

    /**
     * Organizations (شركات).
     *
     * @return array<int, array<string, string>>
     */
    public static function organizations(): array
    {
        return [
            [
                'name' => 'شركة النيل للتوريدات الصناعية',
                'address' => '١٢ شارع مصطفى النحاس، مدينة نصر',
                'city' => 'القاهرة',
                'postcode' => '11371',
            ],
            [
                'name' => 'مجموعة الأهرام الطبية',
                'address' => '٤٤ شارع جامعة الدول العربية، المهندسين',
                'city' => 'الجيزة',
                'postcode' => '12411',
            ],
            [
                'name' => 'مؤسسة الدلتا للتجارة والتوزيع',
                'address' => '٧ شارع البحر، وسط البلد',
                'city' => 'طنطا',
                'postcode' => '31511',
            ],
            [
                'name' => 'شركة الإسكندرية للخدمات اللوجستية',
                'address' => '٢١ طريق الجيش، سموحة',
                'city' => 'الإسكندرية',
                'postcode' => '21615',
            ],
            [
                'name' => 'مصنع السلام للأثاث المكتبي',
                'address' => 'المنطقة الصناعية A٣، العاشر من رمضان',
                'city' => 'الشرقية',
                'postcode' => '44621',
            ],
            [
                'name' => 'شركة الصفا للمقاولات العامة',
                'address' => '٩ شارع ٩، المعادي',
                'city' => 'القاهرة',
                'postcode' => '11728',
            ],
            [
                'name' => 'تك سمارت للأنظمة المتكاملة',
                'address' => 'مبنى C٤، التجمع الخامس',
                'city' => 'القاهرة الجديدة',
                'postcode' => '11835',
            ],
            [
                'name' => 'شركة أسوان للطاقة المتجددة',
                'address' => 'طريق السد العالي، حي الصداقة',
                'city' => 'أسوان',
                'postcode' => '81511',
            ],
        ];
    }

    /**
     * Contacts (جهات اتصال). `organization` is an index into organizations().
     *
     * @return array<int, array<string, mixed>>
     */
    public static function persons(): array
    {
        return [
            ['name' => 'أحمد فتحي', 'job_title' => 'مدير المشتريات', 'email' => 'ahmed.fathy@example.com', 'phone' => '+20 100 234 5671', 'organization' => 0],
            ['name' => 'منى سعيد', 'job_title' => 'المدير المالي', 'email' => 'mona.said@example.com', 'phone' => '+20 101 234 5672', 'organization' => 1],
            ['name' => 'خالد عبد الله', 'job_title' => 'مدير العمليات', 'email' => 'khaled.abdullah@example.com', 'phone' => '+20 102 234 5673', 'organization' => 2],
            ['name' => 'ياسمين فؤاد', 'job_title' => 'مديرة تقنية المعلومات', 'email' => 'yasmin.fouad@example.com', 'phone' => '+20 106 234 5674', 'organization' => 3],
            ['name' => 'محمود رجب', 'job_title' => 'المدير العام', 'email' => 'mahmoud.ragab@example.com', 'phone' => '+20 109 234 5675', 'organization' => 4],
            ['name' => 'هدى الشربيني', 'job_title' => 'مديرة الموارد البشرية', 'email' => 'hoda.elsherbiny@example.com', 'phone' => '+20 111 234 5676', 'organization' => 5],
            ['name' => 'عمرو سليم', 'job_title' => 'مدير المشاريع', 'email' => 'amr.selim@example.com', 'phone' => '+20 112 234 5677', 'organization' => 6],
            ['name' => 'رنا حجازي', 'job_title' => 'مديرة التسويق', 'email' => 'rana.hegazy@example.com', 'phone' => '+20 114 234 5678', 'organization' => 7],
            ['name' => 'طارق النجار', 'job_title' => 'مدير المبيعات', 'email' => 'tarek.elnaggar@example.com', 'phone' => '+20 115 234 5679', 'organization' => 0],
            ['name' => 'دينا منصور', 'job_title' => 'مسؤولة المشتريات', 'email' => 'dina.mansour@example.com', 'phone' => '+20 120 234 5680', 'organization' => 1],
            ['name' => 'حسام الدين عمر', 'job_title' => 'مدير الأنظمة', 'email' => 'hossam.omar@example.com', 'phone' => '+20 122 234 5681', 'organization' => 3],
            ['name' => 'شيرين عادل', 'job_title' => 'مديرة خدمة العملاء', 'email' => 'sherine.adel@example.com', 'phone' => '+20 127 234 5682', 'organization' => 2],
            ['name' => 'مصطفى كمال', 'job_title' => 'مستشار تقني مستقل', 'email' => 'mostafa.kamal@example.com', 'phone' => '+20 128 234 5683', 'organization' => null],
            ['name' => 'نورهان إبراهيم', 'job_title' => 'منسقة المشتريات', 'email' => 'nourhan.ibrahim@example.com', 'phone' => '+20 155 234 5684', 'organization' => 6],
        ];
    }

    /**
     * Products (منتجات) — the packaged offer the demo tenant sells.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function products(): array
    {
        return [
            ['sku' => 'SLP-CRM-CORE', 'name' => 'رخصة نظام إدارة العملاء — أساسية', 'price' => 45000, 'quantity' => 25, 'description' => 'ترخيص دائم لمستخدم واحد مع كل وحدات الفرص وجهات الاتصال.'],
            ['sku' => 'SLP-USER-01', 'name' => 'ترخيص مستخدم إضافي', 'price' => 3500, 'quantity' => 120, 'description' => 'مستخدم إضافي على نفس التركيب السنوي.'],
            ['sku' => 'SLP-IMPL-BAS', 'name' => 'حزمة التطبيق والتهيئة', 'price' => 25000, 'quantity' => 15, 'description' => 'تركيب النظام، تهيئة المراحل والصلاحيات، وضبط القوالب.'],
            ['sku' => 'SLP-MIG-01', 'name' => 'ترحيل البيانات التاريخية', 'price' => 12000, 'quantity' => 10, 'description' => 'ترحيل جهات الاتصال والفرص من ملفات Excel أو نظام سابق.'],
            ['sku' => 'SLP-TRN-05', 'name' => 'تدريب ٥ مستخدمين', 'price' => 8000, 'quantity' => 30, 'description' => 'جلستان تدريب عن بُعد مع دليل مستخدم عربي.'],
            ['sku' => 'SLP-SUP-ANN', 'name' => 'عقد دعم وصيانة سنوي', 'price' => 18000, 'quantity' => 40, 'description' => 'تحديثات أمنية، تصحيحات، ودعم فني خلال ساعات العمل.'],
            ['sku' => 'SLP-API-01', 'name' => 'تكامل مع منظومة الفاتورة الإلكترونية', 'price' => 15000, 'quantity' => 12, 'description' => 'ربط عروض الأسعار والفواتير بمصلحة الضرائب.'],
            ['sku' => 'SLP-WA-01', 'name' => 'وحدة واتساب بزنس', 'price' => 9500, 'quantity' => 18, 'description' => 'إرسال رسائل المتابعة وتذكيرات المواعيد عبر واتساب.'],
            ['sku' => 'SLP-POS-01', 'name' => 'ربط نقاط البيع', 'price' => 11000, 'quantity' => 9, 'description' => 'مزامنة الأصناف والفواتير مع أنظمة الكاشير.'],
            ['sku' => 'SLP-REP-01', 'name' => 'حزمة تقارير مخصصة — ٥ تقارير', 'price' => 7500, 'quantity' => 20, 'description' => 'بناء خمسة تقارير حسب مؤشرات العميل.'],
            ['sku' => 'SLP-HOST-Y', 'name' => 'استضافة سحابية سنوية', 'price' => 6000, 'quantity' => 35, 'description' => 'استضافة على سيرفر خاص مع نسخ احتياطي يومي.'],
            ['sku' => 'SLP-AUD-01', 'name' => 'مراجعة أمنية للموقع', 'price' => 20000, 'quantity' => 6, 'description' => 'تقييم للثغرات وتقرير بالإصلاحات المطلوبة.'],
        ];
    }

    /**
     * Tags (وسوم).
     *
     * @return array<int, array<string, string>>
     */
    public static function tags(): array
    {
        return [
            ['name' => 'عميل مميز', 'color' => '#16a34a'],
            ['name' => 'عرض سعر مرسل', 'color' => '#2563eb'],
            ['name' => 'متابعة عاجلة', 'color' => '#dc2626'],
            ['name' => 'قطاع حكومي', 'color' => '#7c3aed'],
            ['name' => 'إحالة', 'color' => '#ea580c'],
            ['name' => 'خصم خاص', 'color' => '#0891b2'],
        ];
    }

    /**
     * Leads (فرص) — `stage` is a stage code of the installed default pipeline.
     *
     * Every one of the six installed stages is represented. Won and lost leads carry a closure
     * date and, for lost ones, a reason; open leads carry an expected close date in the future.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function leads(): array
    {
        return [
            ['title' => 'تجديد رخصة إدارة العملاء — النيل للتوريدات', 'person' => 0, 'stage' => 'new', 'source' => 1, 'type' => 2, 'value' => 45000, 'created_days_ago' => 0, 'close_in_days' => 25, 'tags' => ['عميل مميز'], 'description' => 'العميل يريد تجديد الرخصة وإضافة مستخدمين قبل نهاية الربع.'],
            ['title' => 'تطبيق وحدة نقاط البيع — الدلتا للتجارة', 'person' => 2, 'stage' => 'new', 'source' => 5, 'type' => 1, 'value' => 38000, 'created_days_ago' => 1, 'close_in_days' => 30, 'tags' => [], 'description' => 'طلب ورد بعد زيارة ميدانية لفرع طنطا.'],
            ['title' => 'ترحيل بيانات العملاء — الأهرام الطبية', 'person' => 1, 'stage' => 'new', 'source' => 2, 'type' => 1, 'value' => 12000, 'created_days_ago' => 2, 'close_in_days' => 40, 'tags' => [], 'description' => 'ثلاثة ملفات Excel تضم نحو ٤٠٠٠ جهة اتصال.'],
            ['title' => 'تكامل الفاتورة الإلكترونية — تك سمارت', 'person' => 6, 'stage' => 'new', 'source' => 3, 'type' => 1, 'value' => 15000, 'created_days_ago' => 4, 'close_in_days' => 20, 'tags' => ['عرض سعر مرسل'], 'description' => 'وصل الطلب من نموذج الموقع، والمهندس عمرو هو نقطة الاتصال.'],
            ['title' => 'تدريب فرق المبيعات — الصفا للمقاولات', 'person' => 5, 'stage' => 'new', 'source' => 4, 'type' => 1, 'value' => 8000, 'created_days_ago' => 6, 'close_in_days' => 35, 'tags' => [], 'description' => 'طلب تدريب لثمانية مندوبين على مراحل الفرص.'],

            ['title' => 'إضافة ١٠ مستخدمين — الإسكندرية للوجستية', 'person' => 3, 'stage' => 'follow-up', 'source' => 1, 'type' => 2, 'value' => 35000, 'created_days_ago' => 15, 'close_in_days' => 18, 'tags' => ['خصم خاص'], 'description' => 'بانتظار موافقة المدير المالي على الخصم.'],
            ['title' => 'وحدة واتساب بزنس — مصنع السلام', 'person' => 4, 'stage' => 'follow-up', 'source' => 2, 'type' => 1, 'value' => 9500, 'created_days_ago' => 18, 'close_in_days' => 21, 'tags' => [], 'description' => 'جلسة عرض عن بُعد تمت، ولم تُحدد جلسة المتابعة بعد.'],
            ['title' => 'عقد دعم وصيانة سنوي — أسوان للطاقة', 'person' => 7, 'stage' => 'follow-up', 'source' => 5, 'type' => 2, 'value' => 18000, 'created_days_ago' => 22, 'close_in_days' => 14, 'tags' => ['متابعة عاجلة'], 'description' => 'العقد ينتهي هذا الشهر ويحتاج تجديداً قبل الموعد.'],
            ['title' => 'مراجعة أمنية للموقع — الأهرام الطبية', 'person' => 9, 'stage' => 'follow-up', 'source' => 4, 'type' => 2, 'value' => 20000, 'created_days_ago' => 25, 'close_in_days' => 27, 'tags' => [], 'description' => 'طلبت إدارة تقنية المعلومات تقريراً بنطاق العمل أولاً.'],

            ['title' => 'حزمة تقارير مخصصة — النيل للتوريدات', 'person' => 8, 'stage' => 'prospect', 'source' => 1, 'type' => 2, 'value' => 7500, 'created_days_ago' => 30, 'close_in_days' => 12, 'tags' => [], 'description' => 'خمسة تقارير لمتابعة أداء المندوبين شهرياً.'],
            ['title' => 'استضافة سحابية سنوية — الدلتا للتجارة', 'person' => 11, 'stage' => 'prospect', 'source' => 3, 'type' => 2, 'value' => 6000, 'created_days_ago' => 33, 'close_in_days' => 9, 'tags' => [], 'description' => 'استبدال الاستضافة الحالية بسيرفر خاص مع نسخ احتياطي يومي.'],
            ['title' => 'ربط نقاط البيع بفروع الإسكندرية', 'person' => 3, 'stage' => 'prospect', 'source' => 2, 'type' => 1, 'value' => 11000, 'created_days_ago' => 36, 'close_in_days' => 16, 'tags' => ['عرض سعر مرسل'], 'description' => 'أربعة فروع، والربط مطلوب مع نظام الكاشير الحالي.'],
            ['title' => 'رخصة أساسية — مؤسسة تقنية ناشئة', 'person' => 12, 'stage' => 'prospect', 'source' => 5, 'type' => 1, 'value' => 45000, 'created_days_ago' => 40, 'close_in_days' => 22, 'tags' => ['إحالة'], 'description' => 'إحالة من مصطفى كمال بعد مشروع سابق في شركة أخرى.'],

            ['title' => 'تجديد سنوي و٥ مستخدمين — الصفا للمقاولات', 'person' => 5, 'stage' => 'negotiation', 'source' => 1, 'type' => 2, 'value' => 62000, 'created_days_ago' => 45, 'close_in_days' => 8, 'tags' => ['عميل مميز', 'خصم خاص'], 'description' => 'التفاوض على ١٥٪ خصم مقابل التعاقد لثلاث سنوات.'],
            ['title' => 'تطبيق النظام على ٣ فروع — مصنع السلام', 'person' => 4, 'stage' => 'negotiation', 'source' => 4, 'type' => 1, 'value' => 88000, 'created_days_ago' => 48, 'close_in_days' => 11, 'tags' => [], 'description' => 'بانتظار خطاب الضمان قبل توقيع العقد.'],
            ['title' => 'تكامل الفاتورة الإلكترونية — الإسكندرية للوجستية', 'person' => 10, 'stage' => 'negotiation', 'source' => 1, 'type' => 2, 'value' => 15000, 'created_days_ago' => 52, 'close_in_days' => 6, 'tags' => ['متابعة عاجلة'], 'description' => 'الاتفاق على النطاق، والنقاش الآن على جدول التسليم.'],
            ['title' => 'عقد دعم وتدريب — أسوان للطاقة', 'person' => 7, 'stage' => 'negotiation', 'source' => 4, 'type' => 2, 'value' => 26000, 'created_days_ago' => 55, 'close_in_days' => 19, 'tags' => [], 'description' => 'حزمة تجمع الدعم السنوي مع تدريب فريق الموقع.'],

            ['title' => 'رخصة إدارة العملاء — الأهرام الطبية', 'person' => 1, 'stage' => 'won', 'source' => 2, 'type' => 1, 'value' => 45000, 'created_days_ago' => 60, 'closed_days_ago' => 0, 'tags' => ['عميل مميز'], 'description' => 'تم التوقيع بعد مقارنة ثلاثة عروض.'],
            ['title' => 'حزمة التطبيق والتهيئة — النيل للتوريدات', 'person' => 0, 'stage' => 'won', 'source' => 1, 'type' => 2, 'value' => 25000, 'created_days_ago' => 66, 'closed_days_ago' => 25, 'tags' => [], 'description' => 'التنفيذ بدأ فعلياً الأسبوع الماضي.'],
            ['title' => 'ربط نقاط البيع — الإسكندرية للوجستية', 'person' => 3, 'stage' => 'won', 'source' => 4, 'type' => 2, 'value' => 11000, 'created_days_ago' => 70, 'closed_days_ago' => 30, 'tags' => ['عميل مميز'], 'description' => 'أُغلق بعد زيارة ميدانية لفرع سموحة.'],
            ['title' => 'اشتراك دعم — مصنع السلام', 'person' => 4, 'stage' => 'won', 'source' => 2, 'type' => 2, 'value' => 18000, 'created_days_ago' => 74, 'closed_days_ago' => 35, 'tags' => [], 'description' => 'تجديد تلقائي لمدة سنة.'],

            ['title' => 'أتمتة كاملة للمبيعات — الدلتا للتجارة', 'person' => 11, 'stage' => 'lost', 'source' => 2, 'type' => 1, 'value' => 120000, 'created_days_ago' => 58, 'closed_days_ago' => 18, 'tags' => [], 'lost_reason' => 'الميزانية المعتمدة أقل من نصف قيمة العرض.', 'description' => 'مشروع يضم ٤٠ مستخدماً وأتمتة كاملة للعمليات.'],
            ['title' => 'تطبيق نظام متكامل — مؤسسة تقنية ناشئة', 'person' => 12, 'stage' => 'lost', 'source' => 5, 'type' => 1, 'value' => 45000, 'created_days_ago' => 64, 'closed_days_ago' => 22, 'tags' => [], 'lost_reason' => 'اختار العميل عرض منافس أقل سعراً بنسبة ٢٠٪.', 'description' => 'لم يُقبل الفرق في السعر مقابل نطاق التنفيذ.'],
            ['title' => 'تأجيل مشروع التقارير — الصفا للمقاولات', 'person' => 13, 'stage' => 'lost', 'source' => 1, 'type' => 1, 'value' => 12000, 'created_days_ago' => 68, 'closed_days_ago' => 28, 'tags' => [], 'lost_reason' => 'العميل أجّل المشروع إلى السنة المالية القادمة.', 'description' => 'يُعاد فتح الفرصة في الربع الأول.'],
        ];

    }

    /**
     * Quotes (عروض أسعار). `lead` is an index into leads(), `items` reference products by SKU.
     *
     * `expires_in_days` is relative to today, so a negative value produces an expired quote —
     * the only "state" a quote has in this product (there is no status column).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function quotes(): array
    {
        return [
            [
                'subject' => 'عرض سعر — تجديد الرخصة و٥ مستخدمين',
                'lead' => 17,
                'person' => 1,
                'created_days_ago' => 20,
                'expires_in_days' => 25,
                'description' => 'العرض ساري ٣٠ يوماً من تاريخ الإصدار، ويشمل التطبيق والترحيل.',
                'items' => [
                    ['sku' => 'SLP-CRM-CORE', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                    ['sku' => 'SLP-USER-01', 'quantity' => 5, 'tax_percent' => 14, 'discount_amount' => 0],
                    ['sku' => 'SLP-IMPL-BAS', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 2500],
                ],
                'adjustment_amount' => 0,
            ],
            [
                'subject' => 'عرض سعر — حزمة التطبيق والتهيئة',
                'lead' => 18,
                'person' => 0,
                'created_days_ago' => 25,
                'expires_in_days' => 5,
                'description' => 'يشمل الترحيل من ملفات Excel وجلستي تدريب.',
                'items' => [
                    ['sku' => 'SLP-IMPL-BAS', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                    ['sku' => 'SLP-MIG-01', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                    ['sku' => 'SLP-TRN-05', 'quantity' => 2, 'tax_percent' => 14, 'discount_amount' => 500],
                ],
                'adjustment_amount' => 0,
            ],
            [
                'subject' => 'عرض سعر — تجديد سنوي و٥ مستخدمين (الصفا)',
                'lead' => 13,
                'person' => 5,
                'created_days_ago' => 14,
                'expires_in_days' => 16,
                'description' => 'العرض يشمل خصم التعاقد لثلاث سنوات بقيمة ١٥٪.',
                'items' => [
                    ['sku' => 'SLP-CRM-CORE', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                    ['sku' => 'SLP-USER-01', 'quantity' => 5, 'tax_percent' => 14, 'discount_amount' => 0],
                    ['sku' => 'SLP-SUP-ANN', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 4200],
                ],
                'adjustment_amount' => -1500,
            ],
            [
                'subject' => 'عرض سعر — تطبيق النظام على ٣ فروع',
                'lead' => 14,
                'person' => 4,
                'created_days_ago' => 10,
                'expires_in_days' => 20,
                'description' => 'التسليم على ثلاث مراحل، فرع كل أسبوعين.',
                'items' => [
                    ['sku' => 'SLP-CRM-CORE', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                    ['sku' => 'SLP-USER-01', 'quantity' => 8, 'tax_percent' => 14, 'discount_amount' => 0],
                    ['sku' => 'SLP-POS-01', 'quantity' => 2, 'tax_percent' => 14, 'discount_amount' => 0],
                ],
                'adjustment_amount' => 2000,
            ],
            [
                'subject' => 'عرض سعر — ١٠ مستخدمين إضافيين',
                'lead' => 5,
                'person' => 3,
                'created_days_ago' => 12,
                'expires_in_days' => 8,
                'description' => 'يُطبَّق الخصم عند السداد خلال ١٥ يوماً.',
                'items' => [
                    ['sku' => 'SLP-USER-01', 'quantity' => 10, 'tax_percent' => 14, 'discount_amount' => 3500],
                    ['sku' => 'SLP-TRN-05', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                ],
                'adjustment_amount' => 0,
            ],
            [
                'subject' => 'عرض سعر — حزمة التقارير المخصصة',
                'lead' => 9,
                'person' => 8,
                'created_days_ago' => 6,
                'expires_in_days' => 24,
                'description' => 'خمسة تقارير، ويُسلَّم كل تقرير خلال أسبوع من اعتماد تصميمه.',
                'items' => [
                    ['sku' => 'SLP-REP-01', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                ],
                'adjustment_amount' => 0,
            ],
            [
                'subject' => 'عرض سعر — تكامل الفاتورة الإلكترونية',
                'lead' => 3,
                'person' => 6,
                'created_days_ago' => 4,
                'expires_in_days' => 26,
                'description' => 'يشمل اختبار التكامل مع بيئة الاختبار الرسمية.',
                'items' => [
                    ['sku' => 'SLP-API-01', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                    ['sku' => 'SLP-SUP-ANN', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                ],
                'adjustment_amount' => 0,
            ],
            [
                'subject' => 'عرض سعر — ربط نقاط البيع بالفروع',
                'lead' => 11,
                'person' => 3,
                'created_days_ago' => 33,
                'expires_in_days' => -5,
                'description' => 'عرض منتهي — لم يرد العميل قبل تاريخ الانتهاء.',
                'items' => [
                    ['sku' => 'SLP-POS-01', 'quantity' => 4, 'tax_percent' => 14, 'discount_amount' => 0],
                    ['sku' => 'SLP-HOST-Y', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                ],
                'adjustment_amount' => 0,
            ],
            [
                'subject' => 'عرض سعر — وحدة واتساب بزنس',
                'lead' => 6,
                'person' => 4,
                'created_days_ago' => 40,
                'expires_in_days' => -12,
                'description' => 'عرض منتهي — طلب العميل إعادة التسعير لاحقاً.',
                'items' => [
                    ['sku' => 'SLP-WA-01', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                ],
                'adjustment_amount' => 0,
            ],
            [
                'subject' => 'عرض سعر — ترحيل بيانات العملاء',
                'lead' => 2,
                'person' => 1,
                'created_days_ago' => 0,
                'expires_in_days' => 28,
                'description' => 'السعر يشمل تنظيف البيانات وإزالة التكرار.',
                'items' => [
                    ['sku' => 'SLP-MIG-01', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                    ['sku' => 'SLP-TRN-05', 'quantity' => 1, 'tax_percent' => 14, 'discount_amount' => 0],
                ],
                'adjustment_amount' => 0,
            ],
        ];
    }

    /**
     * Activities (أنشطة) of the types this product actually writes: call, meeting, lunch and note.
     *
     * `schedule_in_days` is relative to today, so the calendar and the "planned" filter always
     * contain overdue, today's and upcoming entries.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function activities(): array
    {
        return [
            // Past and completed.
            ['lead' => 0, 'type' => 'call', 'title' => 'مكالمة تعريفية مع مدير المشتريات', 'user' => 'agent', 'schedule_in_days' => -12, 'at' => '10:30', 'duration' => 30, 'is_done' => 1, 'location' => 'مكالمة هاتفية', 'comment' => 'تأكدت الحاجة لتجديد الرخصة قبل نهاية الربع.'],
            ['lead' => 1, 'type' => 'meeting', 'title' => 'زيارة ميدانية لفرع طنطا', 'user' => 'manager', 'schedule_in_days' => -10, 'at' => '12:00', 'duration' => 90, 'is_done' => 1, 'location' => 'مقر الدلتا — طنطا', 'comment' => 'تم قياس عدد الفواتير اليومية لتقدير حجم الربط.'],
            ['lead' => 2, 'type' => 'call', 'title' => 'مكالمة متابعة عرض ترحيل البيانات', 'user' => 'agent', 'schedule_in_days' => -8, 'at' => '09:15', 'duration' => 20, 'is_done' => 1, 'location' => 'مكالمة هاتفية', 'comment' => 'العميل أرسل ملفات Excel بعد المكالمة.'],
            ['lead' => 17, 'type' => 'meeting', 'title' => 'اجتماع توقيع العقد — الأهرام الطبية', 'user' => 'manager', 'schedule_in_days' => -20, 'at' => '11:00', 'duration' => 60, 'is_done' => 1, 'location' => 'مقر الأهرام الطبية — المهندسين', 'comment' => 'تم التوقيع على الرخصة والتطبيق.'],
            ['lead' => 18, 'type' => 'lunch', 'title' => 'غداء عمل مع مدير المشتريات', 'user' => 'manager', 'schedule_in_days' => -24, 'at' => '14:00', 'duration' => 120, 'is_done' => 1, 'location' => 'مطعم النيل — مدينة نصر', 'comment' => 'جلسة غير رسمية لتثبيت العلاقة بعد التوقيع.'],
            ['lead' => 21, 'type' => 'call', 'title' => 'مكالمة استكشاف الميزانية', 'user' => 'agent', 'schedule_in_days' => -17, 'at' => '13:30', 'duration' => 25, 'is_done' => 1, 'location' => 'مكالمة هاتفية', 'comment' => 'الميزانية المعتمدة أقل من نصف قيمة العرض.'],
            ['lead' => 13, 'type' => 'note', 'title' => 'ملاحظة تفاوض — طلب خصم ١٥٪', 'user' => 'manager', 'schedule_in_days' => -6, 'at' => '16:00', 'duration' => 0, 'is_done' => 1, 'location' => null, 'comment' => 'طلبت إدارة الصفا خصماً ١٥٪ مقابل التعاقد لثلاث سنوات؛ يحتاج قراراً من الإدارة.'],
            ['lead' => 5, 'type' => 'call', 'title' => 'مكالمة مع المدير المالي للإسكندرية للوجستية', 'user' => 'agent', 'schedule_in_days' => -4, 'at' => '10:00', 'duration' => 30, 'is_done' => 1, 'location' => 'مكالمة هاتفية', 'comment' => 'بانتظار موافقة نهائية على الخصم الخاص.'],

            // Today.
            ['lead' => 3, 'type' => 'call', 'title' => 'مكالمة متابعة عرض الفاتورة الإلكترونية', 'user' => 'agent', 'schedule_in_days' => 0, 'at' => '11:00', 'duration' => 30, 'is_done' => 0, 'location' => 'مكالمة هاتفية', 'comment' => 'مناقشة موعد اختبار التكامل.'],
            ['lead' => 14, 'type' => 'meeting', 'title' => 'اجتماع مراجعة خطاب الضمان', 'user' => 'manager', 'schedule_in_days' => 0, 'at' => '13:00', 'duration' => 60, 'is_done' => 0, 'location' => 'أونلاين — Teams', 'comment' => 'حضور مدير المشاريع وممثل الإدارة المالية.'],
            ['lead' => 9, 'type' => 'lunch', 'title' => 'غداء عمل مع مدير المبيعات', 'user' => 'manager', 'schedule_in_days' => 0, 'at' => '15:00', 'duration' => 90, 'is_done' => 0, 'location' => 'كافيه النيل — الزمالك', 'comment' => 'مناقشة التقارير الخمسة المطلوبة.'],

            // Upcoming.
            ['lead' => 4, 'type' => 'meeting', 'title' => 'جلسة تدريب فريق المبيعات — الصفا', 'user' => 'support', 'schedule_in_days' => 2, 'at' => '10:00', 'duration' => 180, 'is_done' => 0, 'location' => 'أونلاين — Zoom', 'comment' => 'ثمانية مندوبين، جلستان على مدار يوم واحد.'],
            ['lead' => 6, 'type' => 'call', 'title' => 'مكالمة تحديد جلسة المتابعة', 'user' => 'agent', 'schedule_in_days' => 3, 'at' => '09:30', 'duration' => 20, 'is_done' => 0, 'location' => 'مكالمة هاتفية', 'comment' => 'تحديد موعد العرض الفني مع مدير عام المصنع.'],
            ['lead' => 7, 'type' => 'call', 'title' => 'مكالمة تجديد عقد الدعم — أسوان للطاقة', 'user' => 'manager', 'schedule_in_days' => 4, 'at' => '12:30', 'duration' => 30, 'is_done' => 0, 'location' => 'مكالمة هاتفية', 'comment' => 'العقد ينتهي هذا الشهر؛ يجب إرسال عرض التجديد قبل المكالمة.'],
            ['lead' => 10, 'type' => 'meeting', 'title' => 'عرض فني للاستضافة السحابية', 'user' => 'agent', 'schedule_in_days' => 5, 'at' => '11:00', 'duration' => 60, 'is_done' => 0, 'location' => 'أونلاين — Meet', 'comment' => 'عرض خطة النسخ الاحتياطي اليومي واستعادة البيانات.'],
            ['lead' => 15, 'type' => 'meeting', 'title' => 'اجتماع نطاق تكامل الفاتورة — الإسكندرية', 'user' => 'manager', 'schedule_in_days' => 6, 'at' => '10:30', 'duration' => 90, 'is_done' => 0, 'location' => 'مقر الشركة — سموحة', 'comment' => 'الاتفاق على النطاق وجدول التسليم.'],
            ['lead' => 11, 'type' => 'call', 'title' => 'مكالمة مراجعة عرض ربط نقاط البيع', 'user' => 'agent', 'schedule_in_days' => 8, 'at' => '14:00', 'duration' => 30, 'is_done' => 0, 'location' => 'مكالمة هاتفية', 'comment' => 'مراجعة ملاحظات مديرة تقنية المعلومات على البنود.'],
            ['lead' => 12, 'type' => 'meeting', 'title' => 'اجتماع تعريفي — تقنية ناشئة', 'user' => 'manager', 'schedule_in_days' => 10, 'at' => '13:00', 'duration' => 45, 'is_done' => 0, 'location' => 'أونلاين — Teams', 'comment' => 'عرض عام للنظام والاتفاق على الخطوات التالية.'],
            ['lead' => 16, 'type' => 'lunch', 'title' => 'غداء عمل مع مديرة الموارد البشرية', 'user' => 'manager', 'schedule_in_days' => 12, 'at' => '15:30', 'duration' => 120, 'is_done' => 0, 'location' => 'فندق سميراميس — القاهرة', 'comment' => 'مناقشة حزمة الدعم والتدريب لأسوان للطاقة.'],
            ['lead' => 1, 'type' => 'note', 'title' => 'ملاحظة — حجم الفواتير اليومية', 'user' => 'agent', 'schedule_in_days' => 1, 'at' => '09:00', 'duration' => 0, 'is_done' => 0, 'location' => null, 'comment' => 'نحو ٤٠٠ فاتورة يومياً على أربعة فروع؛ يجب تحديد حجم الترخيص بناءً على ذلك.'],
            ['lead' => 20, 'type' => 'note', 'title' => 'ملاحظة بعد الإغلاق — فرص التجديد', 'user' => 'manager', 'schedule_in_days' => -2, 'at' => '17:00', 'duration' => 0, 'is_done' => 1, 'location' => null, 'comment' => 'العميل مرشح قوي لإضافة وحدة واتساب بزنس في الربع القادم.'],

            // Attached to a contact rather than a lead.
            ['person' => 3, 'type' => 'call', 'title' => 'مكالمة مع مديرة تقنية المعلومات', 'user' => 'support', 'schedule_in_days' => -3, 'at' => '10:00', 'duration' => 20, 'is_done' => 1, 'location' => 'مكالمة هاتفية', 'comment' => 'متابعة تذكرة دعم بشأن تصدير عروض الأسعار.'],
            ['person' => 9, 'type' => 'note', 'title' => 'ملاحظة — تفضيلات التواصل', 'user' => 'support', 'schedule_in_days' => -5, 'at' => '12:00', 'duration' => 0, 'is_done' => 1, 'location' => null, 'comment' => 'تفضّل التواصل صباحاً على البريد بخصوص الطلبات الرسمية.'],
        ];
    }

    /**
     * Email templates added for the marketing module (the installer seeds two of its own).
     *
     * @return array<int, array<string, string>>
     */
    public static function emailTemplates(): array
    {
        return [
            [
                'name' => 'عرض سعر جديد — قالب التسويق',
                'subject' => 'عرض سعر جديد من سِجلنا',
                'content' => '<p>مرحباً {%persons.name%}،</p><p>أرفقنا لك عرض سعر مخصصاً لنظام إدارة العملاء. العرض ساري ٣٠ يوماً.</p><p>مع التحية،<br>فريق سِجلنا</p>',
            ],
            [
                'name' => 'تذكير بموعد — قالب التسويق',
                'subject' => 'تذكير: لديك موعد غداً',
                'content' => '<p>مرحباً {%persons.name%}،</p><p>نذكّرك بموعدك غداً. إن احتجت تغييره، ردّ على هذه الرسالة.</p><p>مع التحية،<br>فريق سجلنا</p>',
            ],
        ];
    }

    /**
     * Marketing events (حملات — الأحداث).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function marketingEvents(): array
    {
        return [
            ['name' => 'معرض Cairo ICT', 'description' => 'جناح سِجلنا في قاعة الأنظمة المتكاملة، ثلاثة أيام.', 'date_in_days' => 21],
            ['name' => 'حملة بداية السنة المالية', 'description' => 'حملة بريدية موجّهة لعملاء الرخص المنتهية.', 'date_in_days' => 45],
        ];
    }

    /**
     * Campaigns (حملات).
     *
     * `type` and `mail_to` are NOT NULL in the schema and are not fillable on the model, so the
     * seeder writes them with an insert; no screen in this fork reads them (the Marketing package
     * ships no controller), and `campaign:process` only looks at `status`, the template and the
     * event date.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function campaigns(): array
    {
        return [
            ['name' => 'حملة عروض الربع الأخير', 'subject' => 'عرض سعر جاهز لمؤسستك', 'status' => 1, 'template' => 0, 'event' => null, 'spooling' => '12:00'],
            ['name' => 'دعوة معرض Cairo ICT', 'subject' => 'دعوة لحضور جناح سجلنا في Cairo ICT', 'status' => 0, 'template' => 1, 'event' => 0, 'spooling' => null],
            ['name' => 'حملة تجديد الاشتراكات', 'subject' => 'اشتراكك ينتهي قريباً — جدّده الآن', 'status' => 0, 'template' => 0, 'event' => 1, 'spooling' => '09:00'],
        ];
    }

    /**
     * Email threads (inbox / sent / draft), oldest message first.
     *
     * `lead` is an index into leads(); `person` is an index into persons().
     *
     * @return array<int, array<string, mixed>>
     */
    public static function emailThreads(): array
    {
        return [
            [
                'lead' => 13,
                'person' => 5,
                'messages' => [
                    [
                        'direction' => 'in',
                        'days_ago' => 9,
                        'subject' => 'استفسار عن خصم التعاقد لثلاث سنوات',
                        'body' => 'السلام عليكم، راجعنا عرض السعر المرفق ووجدنا أن بند الدعم السنوي أعلى من الميزانية المرصودة. هل يمكن إعادة النظر في نسبة الخصم مقابل التعاقد لثلاث سنوات؟ مع التحية، هدى الشربيني — مديرة الموارد البشرية.',
                    ],
                    [
                        'direction' => 'out',
                        'days_ago' => 8,
                        'subject' => 'رد: استفسار عن خصم التعاقد لثلاث سنوات',
                        'body' => 'أهلاً هدى، شكراً على ملاحظاتك. أرفقنا نسخة معدلة بخصم ١٥٪ على بند الدعم السنوي مع تثبيت السعر لثلاث سنوات. العرض ساري حتى نهاية الشهر، ويسعدنا عقد اجتماع لمراجعة البنود.',
                    ],
                    [
                        'direction' => 'in',
                        'days_ago' => 2,
                        'subject' => 'رد: استفسار عن خصم التعاقد لثلاث سنوات',
                        'body' => 'شكراً على التعديل. عرضنا الموضوع على الإدارة وننتظر موافقتها النهائية هذا الأسبوع. هل يمكن تحديد موعد مراجعة للبنود قبل التوقيع؟',
                        'is_read' => 0,
                    ],
                ],
            ],
            [
                'lead' => 0,
                'person' => 0,
                'messages' => [
                    [
                        'direction' => 'in',
                        'days_ago' => 5,
                        'subject' => 'تجديد الرخصة وإضافة مستخدمين',
                        'body' => 'تحية طيبة، نود تجديد رخصة نظام إدارة العملاء وإضافة ثلاثة مستخدمين جدد لفريق المشتريات. من فضلكم أرسلوا عرض السعر والفاتورة المبدئية.',
                    ],
                    [
                        'direction' => 'out',
                        'days_ago' => 4,
                        'subject' => 'رد: تجديد الرخصة وإضافة مستخدمين',
                        'body' => 'أهلاً أستاذ أحمد، أرسلنا العرض من خلال النظام وهو مرفق بهذه المحادثة. التجديد يشمل الرخصة الأساسية وثلاثة مستخدمين إضافيين مع دعم سنوي. سنتابع هاتفياً غداً لتأكيد التفاصيل.',
                    ],
                ],
            ],
            [
                'lead' => null,
                'person' => 3,
                'messages' => [
                    [
                        'direction' => 'in',
                        'days_ago' => 3,
                        'subject' => 'مشكلة في تصدير عروض الأسعار',
                        'body' => 'السلام عليكم، عند محاولة تصدير عروض الأسعار إلى PDF يظهر خطأ في الصفحة، ولا يمكننا إرسال العروض للعملاء. أرجو المساعدة في أسرع وقت لأن لدينا عرضين مستحقين اليوم.',
                    ],
                    [
                        'direction' => 'out',
                        'days_ago' => 3,
                        'subject' => 'رد: مشكلة في تصدير عروض الأسعار',
                        'body' => 'أهلاً ياسمين، تم إصلاح المشكلة من جانبنا وأصبح التصدير يعمل. جرّبي الآن وأخبرينا بالنتيجة. إن تكرر الخطأ، أرسلي لنا رقم العرض الذي يظهر معه.',
                        'is_read' => 0,
                    ],
                ],
            ],
            [
                'lead' => 17,
                'person' => 1,
                'messages' => [
                    [
                        'direction' => 'out',
                        'days_ago' => 19,
                        'subject' => 'فاتورة الرخصة والتطبيق — الأهرام الطبية',
                        'body' => 'أهلاً منى، مرفق فاتورة الرخصة والتطبيق بعد التوقيع. بدأ فريق التنفيذ في تجهيز بيئة العمل، وسيتم التواصل معكم لتحديد جلسة الترحيل.',
                    ],
                ],
            ],
            [
                'lead' => 9,
                'person' => 8,
                'messages' => [
                    [
                        'direction' => 'out',
                        'days_ago' => 0,
                        'subject' => 'مسودة: عرض التقارير المخصصة',
                        'body' => 'مسودة لم تُرسل بعد — يجب إضافة أسعار التقارير الخمسة وتحديد موعد التسليم قبل الإرسال.',
                        'folder' => 'draft',
                    ],
                ],
            ],
        ];
    }

    /**
     * Web forms (نماذج الويب). `attributes` reference lead/person attribute codes from the installer.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function webForms(): array
    {
        return [
            [
                'title' => 'اطلب عرض سعر',
                'description' => 'اترك بياناتك وسيتواصل معك فريق المبيعات خلال يوم عمل.',
                'submit_button_label' => 'أرسل الطلب',
                'submit_success_action' => 'message',
                'submit_success_content' => 'تم استلام طلبك، وسيتواصل معك فريق المبيعات قريباً.',
                'create_lead' => 1,
                'background_color' => '#f3f4f6',
                'form_background_color' => '#ffffff',
                'form_title_color' => '#111827',
                'form_submit_button_color' => '#1d4ed8',
                'attribute_label_color' => '#374151',
                'attributes' => [
                    ['entity_type' => 'persons', 'code' => 'name', 'placeholder' => 'الاسم بالكامل', 'is_required' => 1, 'sort_order' => 1],
                    ['entity_type' => 'persons', 'code' => 'emails', 'placeholder' => 'البريد الإلكتروني', 'is_required' => 1, 'sort_order' => 2],
                    ['entity_type' => 'persons', 'code' => 'contact_numbers', 'placeholder' => 'رقم الموبايل', 'is_required' => 0, 'sort_order' => 3],
                    ['entity_type' => 'leads', 'code' => 'title', 'placeholder' => 'ما الذي تحتاجه؟', 'is_required' => 1, 'sort_order' => 4],
                    ['entity_type' => 'leads', 'code' => 'description', 'placeholder' => 'تفاصيل إضافية', 'is_required' => 0, 'sort_order' => 5],
                ],
                'submissions' => [
                    ['name' => 'إيمان صبري', 'email' => 'eman.sabry@example.com', 'phone' => '+20 100 555 1201', 'title' => 'طلب عرض سعر — نظام إدارة العملاء', 'description' => 'شركة توزيع مواد غذائية بعدد ٦ مندوبين، تريد متابعة الفرص والعروض.'],
                    ['name' => 'شريف حمادة', 'email' => 'sherif.hamada@example.com', 'phone' => '+20 101 555 1202', 'title' => 'طلب عرض سعر — وحدة الفاتورة الإلكترونية', 'description' => 'مطلوب ربط الفواتير بمصلحة الضرائب قبل نهاية الربع.'],
                    ['name' => 'هالة رمزي', 'email' => 'hala.ramzy@example.com', 'phone' => '+20 106 555 1203', 'title' => 'طلب عرض سعر — ترحيل بيانات', 'description' => 'لدينا ملفات Excel لجهات الاتصال والفرص من النظام الحالي.'],
                ],
            ],
            [
                'title' => 'تواصل معنا',
                'description' => 'نموذج للاستفسارات العامة، لا يُنشئ فرصة بيع.',
                'submit_button_label' => 'إرسال',
                'submit_success_action' => 'message',
                'submit_success_content' => 'شكراً لتواصلك، سنرد عليك في أقرب وقت.',
                'create_lead' => 0,
                'background_color' => '#eef2ff',
                'form_background_color' => '#ffffff',
                'form_title_color' => '#1e293b',
                'form_submit_button_color' => '#0f766e',
                'attribute_label_color' => '#334155',
                'attributes' => [
                    ['entity_type' => 'persons', 'code' => 'name', 'placeholder' => 'الاسم بالكامل', 'is_required' => 1, 'sort_order' => 1],
                    ['entity_type' => 'persons', 'code' => 'emails', 'placeholder' => 'البريد الإلكتروني', 'is_required' => 1, 'sort_order' => 2],
                    ['entity_type' => 'persons', 'code' => 'job_title', 'placeholder' => 'المسمى الوظيفي', 'is_required' => 0, 'sort_order' => 3],
                ],
                'submissions' => [
                    ['name' => 'عمر الجندي', 'email' => 'omar.elgendi@example.com', 'job_title' => 'مدير تسويق'],
                    ['name' => 'فاطمة الزهراء', 'email' => 'fatma.zehra@example.com', 'job_title' => 'محاسبة'],
                ],
            ],
        ];
    }
}
