<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiTenantWhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent real external AI API calls during tests (use deterministic local RAG fallback)
        putenv('GEMINI_API_KEY=');
        putenv('OPENAI_API_KEY=');
    }

    protected function createCompany(array $overrides = []): Company
    {
        static $counter = 1;
        $num = $counter++;

        return Company::create(array_merge([
            'name' => "شركة رقم {$num}",
            'slug' => "company-slug-{$num}",
            'phone' => "77000000{$num}",
            'whatsapp_number' => "96777000000{$num}",
            'about_us' => "نبذة خاصة بالشركة رقم {$num}",
            'services_offered' => "خدمات خاصة بالشركة رقم {$num}",
            'address' => "عنوان الشركة رقم {$num}",
            'working_hours' => '8 صباحاً - 8 مساءً',
            'status' => 'active',
            'whatsapp_session_status' => 'connected',
            'subscription_plan' => 'monthly_pro',
            'subscription_starts_at' => now()->subDay(),
            'subscription_expires_at' => now()->addMonth(),
            'settings' => [
                'greeting_message' => "أهلاً بك في شركة رقم {$num}!",
            ],
        ], $overrides));
    }

    public function test_each_company_replies_only_to_customers_messaging_its_own_waha_session(): void
    {
        $company1 = $this->createCompany([
            'name' => 'الشركة الأولى للسيارات',
            'slug' => 'smartora-main',
            'whatsapp_number' => '967730881873',
            'settings' => ['greeting_message' => 'أهلاً بك في الشركة الأولى للسيارات!'],
        ]);

        $company2 = $this->createCompany([
            'name' => 'شركة الأمل للإلكترونيات',
            'slug' => 'alamal-tech',
            'whatsapp_number' => '967771234567',
            'settings' => ['greeting_message' => 'أهلاً بك في شركة الأمل للإلكترونيات!'],
        ]);

        // Customer messages Company 2's WhatsApp session
        $response = $this->postJson('/api/webhook/whatsapp', [
            'session' => $company2->getWahaSessionName(),
            'company_whatsapp' => '967771234567',
            'customer_phone' => '967700111222',
            'message' => 'مرحبا',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('session', 'company_' . $company2->id)
            ->assertJsonPath('company.id', $company2->id)
            ->assertJsonPath('company.name', 'شركة الأمل للإلكترونيات')
            ->assertJsonPath('company.session', 'company_' . $company2->id);

        $this->assertStringContainsString('شركة الأمل للإلكترونيات', $response->json('reply'));
        $this->assertStringNotContainsString('الشركة الأولى للسيارات', $response->json('reply'));

        // Conversation must belong ONLY to Company 2, NOT Company 1
        $this->assertDatabaseHas('conversations', [
            'company_id' => $company2->id,
            'customer_phone' => '967700111222',
        ]);
        $this->assertDatabaseMissing('conversations', [
            'company_id' => $company1->id,
            'customer_phone' => '967700111222',
        ]);
    }

    public function test_waha_session_takes_priority_over_conflicting_company_whatsapp(): void
    {
        $company1 = $this->createCompany([
            'name' => 'الشركة الأولى',
            'whatsapp_number' => '967730881873',
        ]);

        $company2 = $this->createCompany([
            'name' => 'الشركة الثانية الجديدة',
            'whatsapp_number' => '967779876543',
            'settings' => ['greeting_message' => 'مرحباً بك في الشركة الثانية الجديدة!'],
        ]);

        // Even if an old caller sends Company 1's number, session=company_{id2} MUST route to Company 2
        $response = $this->postJson('/api/webhook/whatsapp', [
            'session' => 'company_' . $company2->id,
            'company_whatsapp' => $company1->whatsapp_number,
            'customer_phone' => '967711223344',
            'message' => 'السلام عليكم',
        ]);

        $response->assertOk()
            ->assertJsonPath('company.id', $company2->id)
            ->assertJsonPath('session', 'company_' . $company2->id);

        $this->assertStringContainsString('الشركة الثانية الجديدة', $response->json('reply'));
    }

    public function test_legacy_default_session_maps_to_company_4_while_company_5_uses_its_own_session(): void
    {
        $company4 = new Company([
            'name' => 'الشركة الرابعة الأصلية',
            'slug' => 'company-4',
            'whatsapp_number' => '967730881873',
            'status' => 'active',
            'whatsapp_session_status' => 'connected',
            'subscription_plan' => 'monthly_pro',
            'subscription_starts_at' => now()->subDay(),
            'subscription_expires_at' => now()->addMonth(),
            'settings' => ['greeting_message' => 'مرحباً من الشركة الرابعة الأصلية!'],
        ]);
        $company4->id = 4;
        $company4->save();

        $company5 = new Company([
            'name' => 'الشركة الخامسة الجديدة',
            'slug' => 'company-5',
            'whatsapp_number' => '967775554443',
            'status' => 'active',
            'whatsapp_session_status' => 'connected',
            'subscription_plan' => 'monthly_pro',
            'subscription_starts_at' => now()->subDay(),
            'subscription_expires_at' => now()->addMonth(),
            'settings' => ['greeting_message' => 'مرحباً من الشركة الخامسة الجديدة!'],
        ]);
        $company5->id = 5;
        $company5->save();

        // Message to Company 5
        $res5 = $this->postJson('/api/webhook/whatsapp', [
            'event' => 'message',
            'session' => 'company_5',
            'me' => ['id' => '967775554443@c.us'],
            'payload' => [
                'from' => '967700999888@c.us',
                'to' => '967775554443@c.us',
                'fromMe' => false,
                'body' => 'مرحبا',
            ],
        ]);

        $res5->assertOk()
            ->assertJsonPath('company.id', 5)
            ->assertJsonPath('session', 'company_5');
        $this->assertStringContainsString('الشركة الخامسة الجديدة', $res5->json('reply'));

        // Message to Company 4 (default session)
        $res4 = $this->postJson('/api/webhook/whatsapp', [
            'event' => 'message',
            'session' => 'default',
            'me' => ['id' => '967730881873@c.us'],
            'payload' => [
                'from' => '967700999888@c.us',
                'to' => '967730881873@c.us',
                'fromMe' => false,
                'body' => 'مرحبا',
            ],
        ]);

        $res4->assertOk()
            ->assertJsonPath('company.id', 4)
            ->assertJsonPath('session', 'default');
        $this->assertStringContainsString('الشركة الرابعة الأصلية', $res4->json('reply'));
    }

    public function test_matches_company_by_multi_device_jid_and_local_number_format(): void
    {
        $this->createCompany([
            'name' => 'الشركة الأولى',
            'whatsapp_number' => '967730881873',
        ]);

        $company2 = $this->createCompany([
            'name' => 'متجر النخبة',
            'whatsapp_number' => '777222333', // stored without country code 967
            'settings' => ['greeting_message' => 'أهلاً بك في متجر النخبة!'],
        ]);

        // WAHA sends multi-device JID with country code and :14 suffix
        $response = $this->postJson('/api/webhook/whatsapp', [
            'me' => ['id' => '967777222333:14@s.whatsapp.net'],
            'payload' => [
                'from' => '967701122334@c.us',
                'fromMe' => false,
                'body' => 'مرحبا',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('company.id', $company2->id)
            ->assertJsonPath('session', 'company_' . $company2->id);
        $this->assertStringContainsString('متجر النخبة', $response->json('reply'));
    }

    public function test_never_falls_back_to_first_company_for_unknown_session_or_number_or_slug(): void
    {
        $company1 = $this->createCompany([
            'name' => 'الشركة الأولى',
            'slug' => 'smartora-main',
            'whatsapp_number' => '967730881873',
        ]);

        // 1. Unknown WAHA session
        $this->postJson('/api/webhook/whatsapp', [
            'session' => 'company_999',
            'customer_phone' => '967700111222',
            'message' => 'مرحبا',
        ])->assertStatus(404);

        // 2. Unknown receiving WhatsApp number
        $this->postJson('/api/webhook/whatsapp', [
            'company_whatsapp' => '967799999999',
            'customer_phone' => '967700111222',
            'message' => 'مرحبا',
        ])->assertStatus(404);

        // 3. Non-existent slug (regression test for empty $cleanNumber LIKE '%%' bug)
        $this->postJson('/api/webhook/whatsapp', [
            'company_whatsapp' => 'non-existent-company-slug',
            'customer_phone' => '967700111222',
            'message' => 'مرحبا',
        ])->assertStatus(404);

        // Ensure Company 1 NEVER received any conversation
        $this->assertEquals(0, Conversation::where('company_id', $company1->id)->count());
    }

    public function test_direct_waha_webhook_sends_reply_from_the_exact_company_session_and_handles_lid(): void
    {
        Http::fake([
            '*/api/sendText' => Http::response(['id' => 'msg_123'], 200),
        ]);

        $this->createCompany([
            'name' => 'الشركة الأولى',
            'whatsapp_number' => '967730881873',
        ]);

        $company2 = $this->createCompany([
            'name' => 'عيادة الشفاء',
            'whatsapp_number' => '967778889990',
            'settings' => ['greeting_message' => 'أهلاً بك في عيادة الشفاء!'],
        ]);

        $expectedSession = 'company_' . $company2->id;

        $response = $this->postJson('/api/webhook/waha', [
            'event' => 'message',
            'session' => $expectedSession,
            'me' => ['id' => '967778889990@c.us'],
            'payload' => [
                'from' => '182736455@lid',
                'to' => '967778889990@c.us',
                'fromMe' => false,
                'body' => 'مرحبا',
                '_data' => [
                    'key' => [
                        'remoteJid' => '182736455@lid',
                        'remoteJidAlt' => '967712345678@s.whatsapp.net',
                        'fromMe' => false,
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('sent', true)
            ->assertJsonPath('session', $expectedSession)
            ->assertJsonPath('company.id', $company2->id)
            ->assertJsonPath('customer_phone', '967712345678')
            ->assertJsonPath('customer_chat_id', '967712345678@c.us');

        Http::assertSent(function ($request) use ($expectedSession) {
            return str_ends_with($request->url(), '/api/sendText')
                && $request['session'] === $expectedSession
                && $request['chatId'] === '967712345678@c.us'
                && str_contains($request['text'], 'عيادة الشفاء');
        });
    }

    public function test_ignores_from_me_group_broadcast_and_non_message_events(): void
    {
        Http::fake();

        $company = $this->createCompany([
            'name' => 'شركة اختبار',
            'whatsapp_number' => '967771112223',
        ]);
        $session = 'company_' . $company->id;

        // 1. Non-message event
        $this->postJson('/api/webhook/waha', [
            'event' => 'session.status',
            'session' => $session,
            'payload' => ['status' => 'WORKING'],
        ])->assertOk()->assertJsonPath('status', 'ignored');

        // 2. fromMe in nested _data.key
        $this->postJson('/api/webhook/waha', [
            'event' => 'message',
            'session' => $session,
            'payload' => [
                'from' => '967700112233@c.us',
                'body' => 'رد البوت',
                '_data' => ['key' => ['fromMe' => true]],
            ],
        ])->assertOk()->assertJsonPath('status', 'ignored');

        // 3. Group message
        $this->postJson('/api/webhook/waha', [
            'event' => 'message',
            'session' => $session,
            'payload' => [
                'from' => '120363022222222222@g.us',
                'fromMe' => false,
                'body' => 'مرحبا بالمجموعة',
            ],
        ])->assertOk()->assertJsonPath('status', 'ignored');

        // 4. Status broadcast
        $this->postJson('/api/webhook/waha', [
            'event' => 'message',
            'session' => $session,
            'payload' => [
                'from' => 'status@broadcast',
                'fromMe' => false,
                'body' => 'حالة واتساب',
            ],
        ])->assertOk()->assertJsonPath('status', 'ignored');

        Http::assertNothingSent();
        $this->assertEquals(0, Conversation::count());
    }
}
