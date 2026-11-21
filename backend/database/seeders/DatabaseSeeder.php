<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\Message;
use App\Models\SystemNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create or Update SmartOra Company (User's company in Hadramout Mukalla)
        $myCompany = Company::updateOrCreate(
            ['slug' => 'smartora-main'],
            [
                'name' => 'سمارتورا لخدمات الذكاء الاصطناعي',
                'phone' => '730881873',
                'whatsapp_number' => '967730881873',
                'support_phone' => '730881873',
                'email' => 'info@smartora.com',
                'address' => 'اليمن - حضرموت - المكلا',
                'google_maps_url' => 'https://maps.google.com/?q=Mukalla+Hadramout+Yemen',
                'about_us' => 'منصة سمارتورا هي أول نظام يمني ذكي متكامل لتقديم خدمات الرد الآلي وأتمتة خدمة العملاء عبر الواتساب للشركات والمتاجر والمؤسسات باستخدام الذكاء الاصطناعي. يقوم النظام بقراءة ملفات الأسعار وقوائم الخدمات وقواعد المعرفة الخاصة بالمنشأة والرد الفوري على استفسارات العملاء على مدار 24 ساعة بدقة واحترافية فائقة دون أي انقطاع.',
                'services_offered' => "1. الرد الآلي الذكي عبر الواتساب على مدار 24/7 دون توقف.\n2. قراءة وتحليل ملفات المنتجات والأسعار وقواعد المعرفة (Excel, CSV, PDF).\n3. لوحة تحكم متكاملة وسهلة لإدارة المحادثات ومتابعة العملاء والتقارير.\n4. الربط السريع عبر مسح كود الاستجابة السريعة (QR Code) في ثوانٍ معدودة.\n5. التحويل الذكي للدعم البشري واستقبال طلبات الزبائن مباشرة.\n6. باقات اشتراك شهرية وفصلية وسنوية مرنة ومنافسة تناسب كافة الأنشطة التجارية.",
                'working_hours' => 'على مدار 24 ساعة (خدمة الرد الآلي الذكي مستمرة دون توقف)',
                'custom_system_prompt' => 'أنت المساعد الذكي والممثل الرسمي لمنصة سمارتورا لخدمات الذكاء الاصطناعي والرد الآلي في حضرموت - المكلا. مهمتك الإجابة بلباقة عن خدمات المنصة وباقات الاشتراك (شهر بـ 20$، 3 أشهر بـ 25$، سنة بـ 200$) ومساعدة العملاء في ربط أرقامهم ورفع ملفات الأسعار، ورقم التواصل والرد الآلي هو 730881873.',
                'whatsapp_session_status' => 'connected',
                'whatsapp_connected_at' => Carbon::now(),
                'status' => 'active',
                'subscription_plan' => 'enterprise',
                'subscription_starts_at' => Carbon::now(),
                'subscription_expires_at' => Carbon::now()->addYears(5),
                'settings' => [
                    'greeting_message' => 'مرحباً بك في منصة سمارتورا لخدمات الرد الآلي الذكي في حضرموت - المكلا! يسعدنا مساعدتك في أتمتة ردود الواتساب لمتجرك أو شركتك. كيف يمكننا خدمتك اليوم؟',
                    'auto_reply_enabled' => true,
                ],
            ]
        );

        // 2. Super Admin User linked to SmartOra
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@smartora.com'],
            [
                'company_id' => $myCompany->id,
                'name' => 'أنور سالم (السوبر أدمن)',
                'password' => 'password123',
                'role' => 'super_admin',
                'phone' => '730881873',
                'status' => 'active',
            ]
        );

        // 3. Prepare Packages CSV File in both storage locations
        $csvContent = "اسم الباقة,المدة,السعر,الميزات والخدمات المشمولة,حالة التوفر\n" .
            "باقة البداية الشهرية,شهر واحد (30 يوم),20$,رد آلي ذكي غير محدود بالذكاء الاصطناعي عبر الواتساب + رفع حتى 5 ملفات أسعار ومنتجات + دعم فني متواصل,متاحة فوراً\n" .
            "باقة التوفير (3 أشهر),3 أشهر (90 يوم),25$,رد آلي غير محدود + رفع حتى 15 ملف منتجات وأسعار + التحويل للدعم البشري + تقارير وإحصائيات,متاحة فوراً\n" .
            "باقة الأعمال السنوية,سنة كاملة (365 يوم),200$ (أو 20$ شهرياً),رد آلي غير محدود + رفع غير محدود لملفات المنتجات والأسعار + أولوية الدعم الفني + ربط مخصص وتحديثات دورية,متاحة فوراً\n";

        $paths = [
            storage_path('app/knowledge_documents'),
            storage_path('app/private/knowledge_documents'),
        ];

        foreach ($paths as $dir) {
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true, true);
            }
            File::put($dir . '/smartora_packages.csv', $csvContent);
        }

        // 4. Attach Packages Knowledge Document to SmartOra
        $packagesData = [
            [
                'اسم الباقة' => 'باقة البداية الشهرية',
                'المدة' => 'شهر واحد (30 يوم)',
                'السعر' => '20$',
                'الميزات والخدمات المشمولة' => 'رد آلي ذكي غير محدود بالذكاء الاصطناعي عبر الواتساب + رفع حتى 5 ملفات أسعار ومنتجات + دعم فني متواصل',
                'حالة التوفر' => 'متاحة فوراً',
            ],
            [
                'اسم الباقة' => 'باقة التوفير (3 أشهر)',
                'المدة' => '3 أشهر (90 يوم)',
                'السعر' => '25$',
                'الميزات والخدمات المشمولة' => 'رد آلي غير محدود + رفع حتى 15 ملف منتجات وأسعار + التحويل للدعم البشري + تقارير وإحصائيات',
                'حالة التوفر' => 'متاحة فوراً',
            ],
            [
                'اسم الباقة' => 'باقة الأعمال السنوية',
                'المدة' => 'سنة كاملة (365 يوم)',
                'السعر' => '200$ (أو 20$ شهرياً)',
                'الميزات والخدمات المشمولة' => 'رد آلي غير محدود + رفع غير محدود لملفات المنتجات والأسعار + أولوية الدعم الفني + ربط مخصص وتحديثات دورية',
                'حالة التوفر' => 'متاحة فوراً',
            ],
        ];

        $markdownTable = "### باقات وأسعار اشتراك منصة سمارتورا للرد الآلي:\n" .
            "| اسم الباقة | المدة | السعر | الميزات والخدمات المشمولة | حالة التوفر |\n" .
            "| --- | --- | --- | --- | --- |\n" .
            "| باقة البداية الشهرية | شهر واحد (30 يوم) | 20$ | رد آلي ذكي غير محدود بالذكاء الاصطناعي عبر الواتساب + رفع حتى 5 ملفات أسعار ومنتجات + دعم فني متواصل | متاحة فوراً |\n" .
            "| باقة التوفير (3 أشهر) | 3 أشهر (90 يوم) | 25$ | رد آلي غير محدود + رفع حتى 15 ملف منتجات وأسعار + التحويل للدعم البشري + تقارير وإحصائيات | متاحة فوراً |\n" .
            "| باقة الأعمال السنوية | سنة كاملة (365 يوم) | 200$ (أو 20$ شهرياً) | رد آلي غير محدود + رفع غير محدود لملفات المنتجات والأسعار + أولوية الدعم الفني + ربط مخصص وتحديثات دورية | متاحة فوراً |";

        KnowledgeDocument::updateOrCreate(
            [
                'company_id' => $myCompany->id,
                'file_path' => 'knowledge_documents/smartora_packages.csv',
            ],
            [
                'title' => 'ملف باقات وأسعار اشتراكات سمارتورا للرد الآلي',
                'file_type' => 'csv',
                'file_size' => strlen($csvContent),
                'extracted_text' => $markdownTable,
                'extracted_data' => $packagesData,
                'total_items' => 3,
                'status' => 'ready',
                'error_message' => null,
            ]
        );

        // 5. Client Demo Company (Al-Nour)
        $clientCompany = Company::updateOrCreate(
            ['slug' => 'al-nour-parts'],
            [
                'name' => 'شركة النور لقطع الغيار',
                'phone' => '777000111',
                'whatsapp_number' => '967777000111',
                'support_phone' => '777000111',
                'email' => 'contact@al-nour.com',
                'address' => 'صنعاء - شارع الستين',
                'google_maps_url' => 'https://maps.google.com/?q=Sanaa-60',
                'about_us' => 'شركة النور متخصصة في استيراد وتوزيع قطع غيار السيارات الأصلية اليابانية والكورية.',
                'services_offered' => 'توفير قطع الغيار الأصلية، فحص ومطابقة أرقام القطع، شحن لجميع المحافظات.',
                'working_hours' => 'يومياً ما عدا الجمعة: من 8 صباحاً حتى 8 مساءً',
                'custom_system_prompt' => 'أنت المساعد الذكي لشركة النور لقطع الغيار. أجب على استفسارات الزبائن عن توفر القطع وأسعارها بدقة مستنداً فقط إلى ملفات الأسعار.',
                'whatsapp_session_status' => 'connected',
                'whatsapp_connected_at' => Carbon::now(),
                'status' => 'active',
                'subscription_plan' => 'monthly_pro',
                'subscription_starts_at' => Carbon::now(),
                'subscription_expires_at' => Carbon::now()->addMonth(),
                'settings' => [
                    'greeting_message' => 'مرحباً بك في شركة النور لقطع الغيار! تفضل بكتابة اسم القطعة للاستفسار.',
                    'auto_reply_enabled' => true,
                ],
            ]
        );

        User::updateOrCreate(
            ['email' => 'client@al-nour.com'],
            [
                'company_id' => $clientCompany->id,
                'name' => 'مدير شركة النور',
                'password' => 'password123',
                'role' => 'company_admin',
                'phone' => '777000111',
                'status' => 'active',
            ]
        );

        // 6. Seed a sample conversation for SmartOra so onboarding shows 100%
        $conversation = Conversation::firstOrCreate(
            [
                'company_id' => $myCompany->id,
                'customer_phone' => '967730881873',
            ],
            [
                'customer_name' => 'مهتم بالخدمة (حضرموت)',
                'last_message' => 'ما هي باقات الاشتراك المتوفرة لديكم؟',
                'status' => 'active',
                'last_interaction_at' => Carbon::now(),
            ]
        );

        Message::firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'sender' => 'customer',
                'message' => 'السلام عليكم، ما هي باقات الاشتراك المتوفرة لديكم في سمارتورا؟',
            ]
        );

        Message::firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'sender' => 'bot',
                'message' => "وعليكم السلام ورحمة الله وبركاته! مرحباً بك في سمارتورا لخدمات الذكاء الاصطناعي بالمكلا.\n\nباقات الاشتراك المتاحة:\n🔹 باقة شهر: 20$\n🔹 باقة 3 أشهر: 25$\n🔹 باقة سنة: 200$ (أو 20$ شهرياً)\n\nيسعدنا تفعيل اشتراكك فوراً عبر الرقم: 730881873",
            ]
        );

        // 7. System Notifications
        SystemNotification::create([
            'company_id' => $myCompany->id,
            'title' => 'مرحباً بك في منصة سمارتورا - حضرموت المكلا!',
            'message' => 'تم ضبط وتفعيل بيانات المنصة والرد الآلي ورقم الخدمة 730881873 بنجاح مع إرفاق باقات الاشتراك.',
            'type' => 'success',
            'is_read' => false,
        ]);

        SystemNotification::create([
            'company_id' => $clientCompany->id,
            'title' => 'تفعيل الاشتراك',
            'message' => 'تم تفعيل اشتراك شركة النور لقطع الغيار بنجاح لمدة شهر كامل.',
            'type' => 'info',
            'is_read' => false,
        ]);
    }
}