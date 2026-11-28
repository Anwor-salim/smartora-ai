<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AiChatService;
use App\Services\AppointmentBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DynamicBookingAndSheetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('GEMINI_API_KEY=');
        putenv('OPENAI_API_KEY=');
        putenv('GOOGLE_SERVICE_ACCOUNT_JSON=');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        putenv('GEMINI_API_KEY=');
        putenv('OPENAI_API_KEY=');
        putenv('GOOGLE_SERVICE_ACCOUNT_JSON=');
        Cache::flush();

        parent::tearDown();
    }

    protected function configureFakeServiceAccount(): void
    {
        $cnfPath = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        if (is_file($cnfPath)) {
            $options['config'] = $cnfPath;
        }

        $res = openssl_pkey_new($options);
        if (!$res) {
            $this->fail('Unable to generate ephemeral RSA key for Google Service Account test: ' . (openssl_error_string() ?: 'unknown'));
        }

        openssl_pkey_export($res, $privateKeyPem, null, is_file($cnfPath) ? ['config' => $cnfPath] : []);

        $json = json_encode([
            'client_email' => 'smartora-sync@smartora-test.iam.gserviceaccount.com',
            'private_key' => $privateKeyPem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);

        putenv('GOOGLE_SERVICE_ACCOUNT_JSON=' . $json);
    }

    protected function createBookingCompany(array $settingsOverrides = []): Company
    {
        return Company::create([
            'name' => 'عيادة النور التخصصية',
            'slug' => 'alnoor-clinic-' . uniqid(),
            'phone' => '771122334',
            'whatsapp_number' => '967771122334',
            'support_phone' => '967779990001',
            'address' => 'المكلا - الشرج',
            'working_hours' => 'السبت - الخميس: 9 صباحاً - 9 مساءً',
            'status' => 'active',
            'whatsapp_session_status' => 'connected',
            'subscription_plan' => 'monthly_pro',
            'subscription_starts_at' => now()->subDay(),
            'subscription_expires_at' => now()->addMonth(),
            'settings' => array_merge([
                'greeting_message' => 'أهلاً بك في عيادة النور التخصصية!',
                'timezone' => 'Asia/Riyadh',
                'booking_enabled' => true,
                'booking_approval_mode' => 'reception_approval',
                'booking_daily_limit' => 10,
                'booking_working_days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'],
                'booking_sheet_id' => '1AbCdEfGhIjKlMnOpQrStUvWxYz12345',
                'booking_sheet_url' => 'https://docs.google.com/spreadsheets/d/1AbCdEfGhIjKlMnOpQrStUvWxYz12345/edit',
                'booking_sheet_tab' => 'الحجوزات',
            ], $settingsOverrides),
        ]);
    }

    public function test_dynamic_google_sheet_columns_drive_chat_questions_and_reflect_updates(): void
    {
        $this->configureFakeServiceAccount();

        $headersVersion = 1;
        Http::fake(function ($request) use (&$headersVersion) {
            $url = $request->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'fake_google_token', 'expires_in' => 3600], 200);
            }
            if (str_contains($url, '/values/') && $request->method() === 'GET') {
                $row1 = $headersVersion === 1
                    ? ['رقم الحجز', 'الاسم', 'العمر', 'اليوم', 'الوقت', 'الحالة']
                    : ['رقم الحجز', 'الاسم', 'العمر', 'نوع التأمين', 'اليوم', 'الوقت', 'الحالة'];

                return Http::response(['values' => [$row1]], 200);
            }

            return Http::response([], 200);
        });

        $company = $this->createBookingCompany();
        $ai = app(AiChatService::class);
        $bookingService = app(AppointmentBookingService::class);

        // Customer 1 asks to book -> bot asks for the 4 Sheet fields (excluding system columns)
        $r1 = $ai->handleCustomerMessage($company, '967733111001', 'ابغى احجز');
        $this->assertEquals('booking_engine', $r1['engine']);
        $this->assertStringContainsString('الاسم', $r1['reply']);
        $this->assertStringContainsString('العمر', $r1['reply']);
        $this->assertStringContainsString('اليوم', $r1['reply']);
        $this->assertStringContainsString('الوقت', $r1['reply']);
        $this->assertStringNotContainsString('نوع التأمين', $r1['reply']);
        $this->assertStringNotContainsString('رقم الحجز', $r1['reply']);

        // Company updates Sheet Row 1 to include 'نوع التأمين'
        $headersVersion = 2;
        $bookingService->refreshSheetHeaders($company, true);

        // Customer 2 asks to book -> bot immediately asks for 'نوع التأمين' as well
        $r2 = $ai->handleCustomerMessage($company->fresh(), '967733111002', 'ابغى احجز');
        $this->assertStringContainsString('نوع التأمين', $r2['reply']);
    }

    public function test_complete_multi_turn_booking_creates_pending_appointment_and_appends_to_google_sheet(): void
    {
        $this->configureFakeServiceAccount();

        $appendedPayloads = [];
        Http::fake(function ($request) use (&$appendedPayloads) {
            $url = $request->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'fake_google_token', 'expires_in' => 3600], 200);
            }
            if (str_contains($url, ':append')) {
                $appendedPayloads[] = $request->data();
                return Http::response([
                    'updates' => ['updatedRange' => "'الحجوزات'!A2:G2"],
                ], 200);
            }
            if (str_contains($url, '/values/') && $request->method() === 'GET') {
                return Http::response([
                    'values' => [['رقم الحجز', 'رقم الدور', 'رقم الواتساب', 'الاسم', 'العمر', 'اليوم', 'الوقت', 'الحالة']],
                ], 200);
            }

            return Http::response([], 200);
        });

        $company = $this->createBookingCompany();
        $ai = app(AiChatService::class);
        $phone = '967733222001';

        $r1 = $ai->handleCustomerMessage($company, $phone, 'ابغى احجز موعد');
        $this->assertEquals('booking', $r1['conversation_state']);

        // Customer provides all dynamic fields across messages
        $r2 = $ai->handleCustomerMessage($company, $phone, "الاسم: خالد سالم\nالعمر: 29\nاليوم: 2026-12-01\nالوقت: 4 عصراً");

        $this->assertEquals('success', $r2['status']);
        $this->assertStringContainsString('قيد المراجعة', $r2['reply']);
        $this->assertStringContainsString('2026-12-01', $r2['reply']);

        $appt = Appointment::where('company_id', $company->id)->where('customer_phone', $phone)->first();
        $this->assertNotNull($appt);
        $this->assertEquals('pending', $appt->status);
        $this->assertEquals(1, $appt->queue_number);
        $this->assertEquals('خالد سالم', $appt->customer_name);
        $this->assertEquals('2026-12-01', $appt->appointment_date->toDateString());
        $this->assertEquals(2, $appt->sheet_row_number);
        $this->assertEquals('29', $appt->form_data['العمر'] ?? null);

        $this->assertDatabaseHas('system_notifications', [
            'company_id' => $company->id,
            'type' => 'new_appointment',
        ]);

        // Verify Google Sheets append payload matched column order
        $this->assertCount(1, $appendedPayloads);
        $rowValues = $appendedPayloads[0]['values'][0];
        $this->assertEquals($appt->booking_code, $rowValues[0]);
        $this->assertEquals('1', $rowValues[1]);
        $this->assertEquals($phone, $rowValues[2]);
        $this->assertEquals('خالد سالم', $rowValues[3]);
        $this->assertEquals('29', $rowValues[4]);
        $this->assertStringContainsString('2026-12-01', $rowValues[5]);
        $this->assertEquals('4 عصراً', $rowValues[6]);
        $this->assertEquals('قيد المراجعة', $rowValues[7]);
    }

    public function test_duplicate_prevention_blocks_second_booking_and_customer_cancellation_frees_slot(): void
    {
        $this->configureFakeServiceAccount();

        $cellUpdates = [];
        Http::fake(function ($request) use (&$cellUpdates) {
            $url = $request->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'fake_google_token', 'expires_in' => 3600], 200);
            }
            if ($request->method() === 'PUT' && str_contains($url, '/values/')) {
                $cellUpdates[] = ['url' => $url, 'data' => $request->data()];
                return Http::response([], 200);
            }
            if (str_contains($url, '/values/') && $request->method() === 'GET') {
                return Http::response([
                    'values' => [['رقم الحجز', 'الاسم', 'اليوم', 'الحالة']],
                ], 200);
            }

            return Http::response([], 200);
        });

        $company = $this->createBookingCompany(['booking_daily_limit' => 1]);
        $phone = '967733333001';
        $targetDate = Carbon::now('Asia/Riyadh')->addDays(2)->toDateString();

        $conv = Conversation::create([
            'company_id' => $company->id,
            'channel' => 'whatsapp',
            'customer_phone' => $phone,
            'customer_name' => 'أحمد علي',
            'status' => 'active',
        ]);

        $appt = Appointment::create([
            'company_id' => $company->id,
            'conversation_id' => $conv->id,
            'booking_code' => 'BK-DUP-01',
            'channel' => 'whatsapp',
            'customer_phone' => $phone,
            'customer_name' => 'أحمد علي',
            'appointment_date' => $targetDate,
            'queue_number' => 1,
            'form_data' => ['الاسم' => 'أحمد علي'],
            'status' => 'pending',
            'sheet_row_number' => 4,
        ]);

        $ai = app(AiChatService::class);
        $bookingService = app(AppointmentBookingService::class);

        $this->assertEquals(0, $bookingService->getRemainingSlots($company, Carbon::parse($targetDate)));

        // Duplicate booking attempt -> blocked politely with existing booking details
        $dupReply = $ai->handleCustomerMessage($company, $phone, 'ابغى احجز موعد');
        $this->assertStringContainsString('مسجّل مسبقاً', $dupReply['reply']);
        $this->assertStringContainsString('BK-DUP-01', $dupReply['reply']);
        $this->assertStringContainsString('إلغاء الحجز', $dupReply['reply']);

        // Customer cancels existing booking -> status becomes cancelled, Sheet cell updated, slot freed
        $cancelReply = $ai->handleCustomerMessage($company, $phone, 'إلغاء الحجز');
        $this->assertStringContainsString('تم إلغاء حجزك', $cancelReply['reply']);

        $appt->refresh();
        $this->assertEquals('cancelled', $appt->status);
        $this->assertEquals(1, $bookingService->getRemainingSlots($company, Carbon::parse($targetDate)));
        $this->assertNotEmpty($cellUpdates);
        $this->assertEquals('ملغي', $cellUpdates[0]['data']['values'][0][0] ?? null);
    }

    public function test_daily_capacity_limit_and_multi_day_rollover_skips_full_and_closed_days(): void
    {
        $company = $this->createBookingCompany([
            'booking_daily_limit' => 2,
            'booking_sheet_id' => null,
            'booking_sheet_url' => null,
            'booking_custom_fields' => ['الاسم', 'اليوم', 'الوقت'],
        ]);

        // 2026-11-29 = Sunday (full: 2/2), 2026-11-30 = Monday (full: 2/2), 2026-12-01 = Tuesday (open: 0/2)
        foreach (['2026-11-29', '2026-11-30'] as $dIdx => $dateStr) {
            for ($q = 1; $q <= 2; $q++) {
                Appointment::create([
                    'company_id' => $company->id,
                    'booking_code' => "BK-FULL-{$dIdx}-{$q}",
                    'channel' => 'whatsapp',
                    'customer_phone' => "96770000{$dIdx}{$q}",
                    'customer_name' => "مراجع {$dIdx}{$q}",
                    'appointment_date' => $dateStr,
                    'queue_number' => $q,
                    'status' => 'approved',
                ]);
            }
        }

        $ai = app(AiChatService::class);
        $phone = '967733444001';

        // Turn 1: Customer asks for Sunday 2026-11-29 -> bot skips full Sunday & full Monday, suggests Tuesday 2026-12-01
        $r1 = $ai->handleCustomerMessage($company, $phone, 'ابغى احجز يوم 2026-11-29');
        $this->assertStringContainsString('2026-11-29', $r1['reply']);
        $this->assertStringContainsString('2026-12-01', $r1['reply']);
        $this->assertStringContainsString('الثلاثاء', $r1['reply']);
        $this->assertEquals('booking', $r1['conversation_state']);

        // Turn 2: Customer accepts suggested rollover date ("نعم") -> bot locks Tuesday and asks remaining fields (الاسم، الوقت)
        $r2 = $ai->handleCustomerMessage($company, $phone, 'نعم');
        $this->assertStringContainsString('الاسم', $r2['reply']);
        $this->assertStringContainsString('الوقت', $r2['reply']);

        // Turn 3: Customer supplies Name + Time -> appointment booked on Tuesday 2026-12-01!
        $r3 = $ai->handleCustomerMessage($company, $phone, "الاسم: محمد صالح\nالوقت: 5 عصراً");
        $this->assertStringContainsString('2026-12-01', $r3['reply']);

        $booked = Appointment::where('company_id', $company->id)->where('customer_phone', $phone)->first();
        $this->assertNotNull($booked);
        $this->assertEquals('محمد صالح', $booked->customer_name);
        $this->assertEquals('2026-12-01', $booked->appointment_date->toDateString());
        $this->assertEquals('5 عصراً', $booked->appointment_time);
        $this->assertEquals(1, $booked->queue_number);
    }

    public function test_two_way_reception_approval_and_rejection_via_service_and_google_sheet_sync(): void
    {
        $this->configureFakeServiceAccount();

        $sheetStatusA = 'قيد المراجعة';
        $sheetStatusB = 'مقبول';
        $sheetStatusC = 'مرفوض';
        $wahaSent = [];
        $sheetUpdates = [];

        Http::fake(function ($request) use (&$sheetStatusA, &$sheetStatusB, &$sheetStatusC, &$wahaSent, &$sheetUpdates) {
            $url = $request->url();

            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'fake_google_token', 'expires_in' => 3600], 200);
            }
            if (str_contains($url, '/api/sendText')) {
                $wahaSent[] = $request->data();
                return Http::response(['id' => 'waha_msg_' . count($wahaSent)], 200);
            }
            if ($request->method() === 'PUT' && str_contains($url, '/values/')) {
                $sheetUpdates[] = ['url' => $url, 'data' => $request->data()];
                return Http::response([], 200);
            }
            if ($request->method() === 'GET' && str_contains($url, '/values/')) {
                // Header-only read vs full A1:Z2000 read
                if (str_contains($url, 'A1%3AZ1') || str_contains($url, 'A1:Z1')) {
                    return Http::response([
                        'values' => [['رقم الحجز', 'الاسم', 'الحالة', 'ملاحظات الإدارة']],
                    ], 200);
                }

                return Http::response([
                    'values' => [
                        ['رقم الحجز', 'الاسم', 'الحالة', 'ملاحظات الإدارة'],
                        ['BK-SYNC-A', 'عميل أ', $sheetStatusA, ''],
                        ['BK-SYNC-B', 'عميل ب', $sheetStatusB, ''],
                        ['BK-SYNC-C', 'عميل ج', $sheetStatusC, 'العيادة ممتلئة'],
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });

        $company = $this->createBookingCompany();
        $bookingService = app(AppointmentBookingService::class);
        $date = Carbon::now('Asia/Riyadh')->addDays(3)->toDateString();

        $convA = Conversation::create(['company_id' => $company->id, 'channel' => 'whatsapp', 'customer_phone' => '967733555001', 'status' => 'active']);
        $convB = Conversation::create(['company_id' => $company->id, 'channel' => 'whatsapp', 'customer_phone' => '967733555002', 'status' => 'active']);
        $convC = Conversation::create(['company_id' => $company->id, 'channel' => 'whatsapp', 'customer_phone' => '967733555003', 'status' => 'active']);

        $apptA = Appointment::create([
            'company_id' => $company->id,
            'conversation_id' => $convA->id,
            'booking_code' => 'BK-SYNC-A',
            'channel' => 'whatsapp',
            'customer_phone' => '967733555001',
            'customer_name' => 'عميل أ',
            'appointment_date' => $date,
            'queue_number' => 1,
            'status' => 'pending',
            'sheet_row_number' => 2,
        ]);

        $apptB = Appointment::create([
            'company_id' => $company->id,
            'conversation_id' => $convB->id,
            'booking_code' => 'BK-SYNC-B',
            'channel' => 'whatsapp',
            'customer_phone' => '967733555002',
            'customer_name' => 'عميل ب',
            'appointment_date' => $date,
            'queue_number' => 2,
            'status' => 'pending',
            'sheet_row_number' => 3,
        ]);

        $apptC = Appointment::create([
            'company_id' => $company->id,
            'conversation_id' => $convC->id,
            'booking_code' => 'BK-SYNC-C',
            'channel' => 'whatsapp',
            'customer_phone' => '967733555003',
            'customer_name' => 'عميل ج',
            'appointment_date' => $date,
            'queue_number' => 3,
            'status' => 'pending',
            'sheet_row_number' => 4,
        ]);

        // 1. Approve A via Filament/service -> updates Sheet cell & sends 1 WhatsApp confirmation
        $bookingService->approveAppointment($apptA);
        $bookingService->approveAppointment($apptA->fresh()); // second call must not send duplicate WAHA message
        $this->assertEquals('approved', $apptA->fresh()->status);
        $this->assertCount(1, $wahaSent);
        $this->assertStringContainsString('BK-SYNC-A', $wahaSent[0]['text']);
        $this->assertCount(1, $sheetUpdates);
        $this->assertEquals('مؤكد', $sheetUpdates[0]['data']['values'][0][0] ?? null);

        // Reflect A as confirmed in fake Sheet so sync command leaves A untouched
        $sheetStatusA = 'مؤكد';

        // 2. Run Artisan sync command -> picks up B ('مقبول') & C ('مرفوض' with reason) directly from Sheet
        $this->artisan('appointments:sync-sheets', ['company_id' => $company->id])
            ->assertExitCode(0);

        $apptB->refresh();
        $apptC->refresh();
        $this->assertEquals('approved', $apptB->status);
        $this->assertEquals('rejected', $apptC->status);
        $this->assertEquals('العيادة ممتلئة', $apptC->rejection_reason);

        // Total WAHA messages sent: 1 (A) + 1 (B) + 1 (C) = 3
        $this->assertCount(3, $wahaSent);
        $this->assertStringContainsString('BK-SYNC-B', $wahaSent[1]['text']);
        $this->assertStringContainsString('BK-SYNC-C', $wahaSent[2]['text']);
        $this->assertStringContainsString('العيادة ممتلئة', $wahaSent[2]['text']);
    }

    public function test_affirmative_reply_after_bot_booking_offer_enters_booking_engine_and_creates_real_appointment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 17:30:00', 'Asia/Riyadh'));

        try {
            $company = $this->createBookingCompany([
                'booking_sheet_id' => null,
                'booking_sheet_url' => null,
                'booking_custom_fields' => ['الاسم', 'العمر', 'التاريخ', 'الوقت'],
            ]);

            $phone = '967733666001';
            $conv = Conversation::create([
                'company_id' => $company->id,
                'channel' => 'whatsapp',
                'customer_phone' => $phone,
                'customer_name' => 'عميل واتساب (6001)',
                'status' => 'active',
                'conversation_state' => 'inquiring',
                'last_interaction_at' => Carbon::now('Asia/Riyadh'),
            ]);

            Message::create([
                'conversation_id' => $conv->id,
                'sender' => 'customer',
                'message' => 'عيادة الأطفال',
            ]);
            Message::create([
                'conversation_id' => $conv->id,
                'sender' => 'bot',
                'message' => 'أهلاً بك، تقع عيادة الأطفال في الدور الأول. هل ترغب في حجز موعد في عيادة الأطفال، وما هو اليوم والوقت المناسب لك؟',
                'metadata' => ['engine' => 'gemini', 'intent' => 'item_inquiry'],
            ]);

            $ai = app(AiChatService::class);

            // Turn 1: Customer replies "نعم" without the word "حجز"
            $r1 = $ai->handleCustomerMessage($company, $phone, 'نعم');
            $this->assertEquals('booking_engine', $r1['engine']);
            $this->assertEquals('booking', $r1['conversation_state']);
            $this->assertStringContainsString('الاسم', $r1['reply']);
            $this->assertStringContainsString('العمر', $r1['reply']);

            // Turn 2: Customer sends name, Saturday, and period-of-day time "العصر"
            $r2 = $ai->handleCustomerMessage($company, $phone, 'الاسم محمد بامطرف يوم السبت الوقت العصر');
            $this->assertEquals('booking_engine', $r2['engine']);
            $this->assertEquals('booking', $r2['conversation_state']);
            $this->assertStringContainsString('العمر', $r2['reply']);
            $this->assertStringNotContainsString('الاسم', $r2['reply']);
            $this->assertStringNotContainsString('الوقت', $r2['reply']);

            // Turn 3: Customer sends remaining age -> creates real appointment
            $r3 = $ai->handleCustomerMessage($company, $phone, '28');
            $this->assertEquals('booking_engine', $r3['engine']);
            $this->assertStringContainsString('تم تسجيل طلب حجزك بنجاح', $r3['reply']);

            $appt = Appointment::where('company_id', $company->id)->where('customer_phone', $phone)->first();
            $this->assertNotNull($appt);
            $this->assertEquals('محمد بامطرف', $appt->customer_name);
            $this->assertEquals('العصر', $appt->appointment_time);
            $this->assertEquals('2026-11-28', $appt->appointment_date->toDateString());
            $this->assertEquals('28', $appt->form_data['العمر'] ?? null);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_direct_booking_details_after_bot_offer_enters_booking_engine_while_followup_questions_stay_in_qa(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 17:30:00', 'Asia/Riyadh'));

        try {
            $company = $this->createBookingCompany([
                'booking_sheet_id' => null,
                'booking_sheet_url' => null,
                'booking_working_days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'],
                'booking_custom_fields' => ['الاسم', 'العمر', 'التاريخ', 'الوقت'],
            ]);

            $ai = app(AiChatService::class);

            // Case A: Bot offered clinic info or booking, customer asks a location question -> stays in Q&A
            $phoneA = '967733666002';
            $convA = Conversation::create([
                'company_id' => $company->id,
                'channel' => 'whatsapp',
                'customer_phone' => $phoneA,
                'status' => 'active',
                'conversation_state' => 'inquiring',
                'last_interaction_at' => Carbon::now('Asia/Riyadh'),
            ]);
            Message::create([
                'conversation_id' => $convA->id,
                'sender' => 'bot',
                'message' => 'هل تود معرفة موقع العيادة أو حجز موعد؟',
                'metadata' => ['engine' => 'rag_fallback', 'intent' => 'item_inquiry'],
            ]);

            $rQa = $ai->handleCustomerMessage($company, $phoneA, 'وين موقعها؟');
            $this->assertNotEquals('booking_engine', $rQa['engine']);

            // Case B: Bot offered booking, customer replies directly with details for closed Friday -> enters booking engine & suggests Saturday
            $phoneB = '967733666003';
            $convB = Conversation::create([
                'company_id' => $company->id,
                'channel' => 'whatsapp',
                'customer_phone' => $phoneB,
                'status' => 'active',
                'conversation_state' => 'inquiring',
                'last_interaction_at' => Carbon::now('Asia/Riyadh'),
            ]);
            Message::create([
                'conversation_id' => $convB->id,
                'sender' => 'bot',
                'message' => 'هل ترغب في حجز موعد في عيادة الأطفال، وما هو اليوم والوقت المناسب لك؟',
                'metadata' => ['engine' => 'gemini', 'intent' => 'item_inquiry'],
            ]);

            $rBook = $ai->handleCustomerMessage($company, $phoneB, 'الاسم محمد بامطرف يوم الجمعه الوقت العصر');
            $this->assertEquals('booking_engine', $rBook['engine']);
            $this->assertEquals('booking', $rBook['conversation_state']);
            $this->assertStringContainsString('يوم إجازة', $rBook['reply']);
            $this->assertStringContainsString('السبت', $rBook['reply']);
            $this->assertStringContainsString('2026-11-28', $rBook['reply']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_date_words_are_never_saved_into_time_field_and_explicit_day_of_month_parses_correctly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 17:30:00', 'Asia/Riyadh'));

        try {
            $bookingService = app(AppointmentBookingService::class);
            $this->assertEquals('2026-11-28', $bookingService->parseArabicDate('تاريخ 28', 'Asia/Riyadh')?->toDateString());
            $this->assertEquals('2026-11-28', $bookingService->parseArabicDate('يوم 28', 'Asia/Riyadh')?->toDateString());
            $this->assertEquals('2026-11-28', $bookingService->parseArabicDate('28 نوفمبر', 'Asia/Riyadh')?->toDateString());
            $this->assertNull($bookingService->parseArabicDate('28', 'Asia/Riyadh'));

            $company = $this->createBookingCompany([
                'booking_sheet_id' => null,
                'booking_sheet_url' => null,
                'booking_working_days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'],
                'booking_custom_fields' => ['الاسم', 'العمر', 'التاريخ', 'الوقت'],
            ]);

            $ai = app(AiChatService::class);
            $phone = '967733777001';

            // Turn 1: Customer asks to book tomorrow (Friday Nov 27 -> closed) and puts "غداً العصر" in time
            $r1 = $ai->handleCustomerMessage(
                $company,
                $phone,
                "ابغى احجز\nالاسم: سارة علي\nالعمر: 28\nالتاريخ: غداً\nالوقت: غداً العصر"
            );
            $this->assertEquals('booking_engine', $r1['engine']);
            $this->assertStringContainsString('2026-11-28', $r1['reply']);

            // Turn 2: Customer accepts Saturday Nov 28
            $r2 = $ai->handleCustomerMessage($company, $phone, 'نعم');
            $this->assertEquals('booking_engine', $r2['engine']);
            $this->assertStringContainsString('2026-11-28', $r2['reply']);
            $this->assertStringNotContainsString('غداً', $r2['reply']);

            $appt = Appointment::where('company_id', $company->id)->where('customer_phone', $phone)->first();
            $this->assertNotNull($appt);
            $this->assertEquals('2026-11-28', $appt->appointment_date->toDateString());
            $this->assertEquals('العصر', $appt->appointment_time);
            $this->assertStringNotContainsString('غداً', (string) $appt->appointment_time);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_system_prompt_includes_exact_calendar_anchors_and_active_appointment_relative_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 17:30:00', 'Asia/Riyadh'));
        putenv('GEMINI_API_KEY=test-gemini-key');

        $capturedPayload = null;
        Http::fake(function ($request) use (&$capturedPayload) {
            $capturedPayload = $request->data();

            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'أهلاً بك، دوامنا من 9 صباحاً إلى 9 مساءً.'],
                            ],
                        ],
                    ],
                ],
            ], 200);
        });

        try {
            $company = $this->createBookingCompany([
                'booking_sheet_id' => null,
                'booking_sheet_url' => null,
            ]);

            $phone = '967733777002';
            $conv = Conversation::create([
                'company_id' => $company->id,
                'channel' => 'whatsapp',
                'customer_phone' => $phone,
                'customer_name' => 'أحمد سالم',
                'status' => 'active',
            ]);

            Appointment::create([
                'company_id' => $company->id,
                'conversation_id' => $conv->id,
                'booking_code' => 'BK-ANCHOR-01',
                'channel' => 'whatsapp',
                'customer_phone' => $phone,
                'customer_name' => 'أحمد سالم',
                'appointment_date' => '2026-11-28',
                'appointment_time' => 'العصر',
                'queue_number' => 1,
                'status' => 'approved',
            ]);

            $ai = app(AiChatService::class);
            $res = $ai->handleCustomerMessage($company, $phone, 'متى دوامكم؟');
            $this->assertEquals('gemini', $res['engine']);

            $sysText = $capturedPayload['systemInstruction']['parts'][0]['text'] ?? '';
            $this->assertStringContainsString('غداً = الجمعة (2026-11-27)', $sysText);
            $this->assertStringContainsString('بعد غد = السبت (2026-11-28)', $sysText);
            $this->assertStringContainsString('BK-ANCHOR-01', $sysText);
            $this->assertStringContainsString('[بعد غد]', $sysText);
            $this->assertStringNotContainsString('[غداً]', $sysText);
        } finally {
            putenv('GEMINI_API_KEY=');
            Carbon::setTestNow();
        }
    }

    public function test_month_day_format_and_typo_time_parse_and_generic_section_title_hidden_from_booking_prompt(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 19:45:00', 'Asia/Riyadh'));

        try {
            $bookingService = app(AppointmentBookingService::class);
            $this->assertEquals('2026-11-27', $bookingService->parseArabicDate('11-27', 'Asia/Riyadh')?->toDateString());
            $this->assertEquals('2026-11-27', $bookingService->parseArabicDate('27-11', 'Asia/Riyadh')?->toDateString());
            $this->assertEquals(
                '2026-11-27',
                $bookingService->parseArabicDate('سالم محمد 22 11-27 العصر السعه 5:30', 'Asia/Riyadh')?->toDateString()
            );

            $company = $this->createBookingCompany([
                'booking_sheet_id' => null,
                'booking_sheet_url' => null,
                'booking_working_days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'],
                'booking_custom_fields' => ['الاسم', 'العمر', 'التاريخ', 'الوقت'],
            ]);

            $phone = '967733888001';
            Conversation::create([
                'company_id' => $company->id,
                'channel' => 'whatsapp',
                'customer_phone' => $phone,
                'status' => 'active',
                'conversation_state' => 'inquiring',
                'context_data' => [
                    'last_matched_title' => 'نبذة عن الخدمات والمنتجات التي تقدمها المنشأة',
                ],
                'last_interaction_at' => Carbon::now('Asia/Riyadh'),
            ]);

            $ai = app(AiChatService::class);

            // Turn 1: Generic section title must not appear in "(بخصوص ...)"
            $r1 = $ai->handleCustomerMessage($company, $phone, 'حجز لي موعد');
            $this->assertEquals('booking_engine', $r1['engine']);
            $this->assertStringNotContainsString('نبذة عن الخدمات والمنتجات التي تقدمها المنشأة', $r1['reply']);

            // Turn 2: 11-27 (closed Friday) + "العصر السعه 5:30" -> date recognized & rolled over to Saturday 2026-11-28
            $r2 = $ai->handleCustomerMessage($company, $phone, "الاسم: سالم محمد\nالعمر: 22\nالتاريخ: 11-27\nالعصر السعه 5:30");
            $this->assertEquals('booking_engine', $r2['engine']);
            $this->assertStringNotContainsString('اليوم أو التاريخ المناسب', $r2['reply']);
            $this->assertStringContainsString('2026-11-27', $r2['reply']);
            $this->assertStringContainsString('2026-11-28', $r2['reply']);

            // Turn 3: Accept Saturday -> appointment created with 5:30 time
            $r3 = $ai->handleCustomerMessage($company, $phone, 'نعم');
            $this->assertEquals('booking_engine', $r3['engine']);
            $this->assertStringContainsString('تم تسجيل طلب حجزك بنجاح', $r3['reply']);

            $appt = Appointment::where('company_id', $company->id)->where('customer_phone', $phone)->first();
            $this->assertNotNull($appt);
            $this->assertEquals('2026-11-28', $appt->appointment_date->toDateString());
            $this->assertStringContainsString('5:30', (string) $appt->appointment_time);
        } finally {
            Carbon::setTestNow();
        }
    }
}
