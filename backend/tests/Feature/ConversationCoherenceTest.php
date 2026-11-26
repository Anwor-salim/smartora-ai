<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\SystemNotification;
use App\Services\AiChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConversationCoherenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('GEMINI_API_KEY=');
        putenv('OPENAI_API_KEY=');
    }

    protected function tearDown(): void
    {
        putenv('GEMINI_API_KEY=');
        putenv('OPENAI_API_KEY=');

        parent::tearDown();
    }

    protected function createActiveCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'مركز الشفاء التخصصي',
            'slug' => 'alshifa-center-' . uniqid(),
            'phone' => '771122334',
            'whatsapp_number' => '967771122334',
            'support_phone' => '967779998887',
            'about_us' => 'مركز طبي متكامل يقدم خدمات العيادات التخصصية.',
            'services_offered' => 'عيادة الأسنان، عيادة العظام، المختبر.',
            'address' => 'صنعاء - شارع حدة',
            'working_hours' => 'السبت إلى الخميس: 9 صباحاً - 9 مساءً',
            'status' => 'active',
            'whatsapp_session_status' => 'connected',
            'subscription_plan' => 'monthly_pro',
            'subscription_starts_at' => now()->subDay(),
            'subscription_expires_at' => now()->addMonth(),
            'settings' => [
                'greeting_message' => 'أهلاً بك في مركز الشفاء التخصصي!',
                'timezone' => 'Asia/Riyadh',
                'response_tone' => 'professional_friendly',
                'memory_messages_limit' => 12,
            ],
        ], $overrides));
    }

    public function test_gemini_receives_system_instruction_and_multi_turn_history_without_repeating_greeting(): void
    {
        putenv('GEMINI_API_KEY=test-gemini-key');

        $capturedPayloads = [];
        $turn = 0;

        Http::fake(function ($request) use (&$capturedPayloads, &$turn) {
            $capturedPayloads[] = $request->data();
            $turn++;

            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => $turn === 1
                                        ? 'أهلاً بك في مركز الشفاء التخصصي! نعم، تتوفر لدينا عيادة الأسنان مع د. سارة.'
                                        : 'سعر الكشف في عيادة الأسنان هو 5,000 ريال. هل تود حجز موعد؟',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200);
        });

        $company = $this->createActiveCompany();
        $ai = app(AiChatService::class);

        // Turn 1 (fresh session)
        $res1 = $ai->handleCustomerMessage($company, '967733000111', 'مرحبا، عندكم عيادة أسنان؟');
        $this->assertEquals('success', $res1['status']);
        $this->assertEquals('gemini', $res1['engine']);

        // Turn 2 (ongoing session)
        $res2 = $ai->handleCustomerMessage($company, '967733000111', 'كم سعر الكشف؟');
        $this->assertEquals('success', $res2['status']);
        $this->assertEquals('gemini', $res2['engine']);

        $this->assertCount(2, $capturedPayloads);

        // Verify Turn 1 payload
        $payload1 = $capturedPayloads[0];
        $sys1 = $payload1['systemInstruction']['parts'][0]['text'] ?? '';
        $this->assertStringContainsString('بداية محادثة جديدة', $sys1);
        $this->assertStringContainsString('Asia/Riyadh', $sys1);
        $this->assertCount(1, $payload1['contents']);
        $this->assertEquals('user', $payload1['contents'][0]['role']);

        // Verify Turn 2 payload carries full alternating history + ongoing instruction
        $payload2 = $capturedPayloads[1];
        $sys2 = $payload2['systemInstruction']['parts'][0]['text'] ?? '';
        $this->assertStringContainsString('محادثة مستمرة', $sys2);
        $this->assertCount(3, $payload2['contents']);
        $this->assertEquals('user', $payload2['contents'][0]['role']);
        $this->assertEquals('مرحبا، عندكم عيادة أسنان؟', $payload2['contents'][0]['parts'][0]['text']);
        $this->assertEquals('model', $payload2['contents'][1]['role']);
        $this->assertStringContainsString('عيادة الأسنان', $payload2['contents'][1]['parts'][0]['text']);
        $this->assertEquals('user', $payload2['contents'][2]['role']);
        $this->assertEquals('كم سعر الكشف؟', $payload2['contents'][2]['parts'][0]['text']);
    }

    public function test_openai_receives_multi_turn_messages_when_gemini_is_absent(): void
    {
        putenv('GEMINI_API_KEY=');
        putenv('OPENAI_API_KEY=test-openai-key');

        $capturedPayloads = [];

        Http::fake(function ($request) use (&$capturedPayloads) {
            $capturedPayloads[] = $request->data();

            return Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => count($capturedPayloads) === 1
                                ? 'أهلاً بك! لدينا عيادة العظام يومياً.'
                                : 'سعر الكشف في عيادة العظام 7,000 ريال.',
                        ],
                    ],
                ],
            ], 200);
        });

        $company = $this->createActiveCompany();
        $ai = app(AiChatService::class);

        $ai->handleCustomerMessage($company, '967733000222', 'عندكم عيادة عظام؟');
        $res2 = $ai->handleCustomerMessage($company, '967733000222', 'كم الكشف؟');

        $this->assertEquals('openai', $res2['engine']);
        $this->assertCount(2, $capturedPayloads);

        $messages2 = $capturedPayloads[1]['messages'];
        $this->assertEquals('system', $messages2[0]['role']);
        $this->assertEquals('user', $messages2[1]['role']);
        $this->assertEquals('عندكم عيادة عظام؟', $messages2[1]['content']);
        $this->assertEquals('assistant', $messages2[2]['role']);
        $this->assertStringContainsString('عيادة العظام', $messages2[2]['content']);
        $this->assertEquals('user', $messages2[3]['role']);
        $this->assertEquals('كم الكشف؟', $messages2[3]['content']);
    }

    public function test_local_fallback_resolves_follow_up_pronouns_and_tracks_booking_state(): void
    {
        $company = $this->createActiveCompany();

        KnowledgeDocument::create([
            'company_id' => $company->id,
            'title' => 'دليل العيادات والأسعار',
            'file_path' => 'docs/clinics.csv',
            'file_type' => 'csv',
            'status' => 'ready',
            'extracted_text' => "عيادة الأسنان - د. سارة - سعر الكشف 5000\nعيادة العظام - د. علي - سعر الكشف 7000",
            'extracted_data' => [
                [
                    'القسم / العيادة' => 'عيادة الأسنان',
                    'الطبيب' => 'د. سارة أحمد',
                    'سعر الكشف' => '5000',
                    'المواعيد' => 'من 4 عصراً إلى 9 مساءً',
                ],
                [
                    'القسم / العيادة' => 'عيادة العظام',
                    'الطبيب' => 'د. علي سالم',
                    'سعر الكشف' => '7000',
                    'المواعيد' => 'من 9 صباحاً إلى 2 ظهراً',
                ],
            ],
        ]);

        $ai = app(AiChatService::class);
        $phone = '967733000333';

        // Turn 1: Specific item inquiry
        $r1 = $ai->handleCustomerMessage($company, $phone, 'عندكم عيادة أسنان؟');
        $this->assertEquals('rag_fallback', $r1['engine']);
        $this->assertStringContainsString('عيادة الأسنان', $r1['reply']);
        $this->assertEquals('inquiring', $r1['conversation_state']);

        $conv = Conversation::findOrFail($r1['conversation_id']);
        $this->assertEquals('عيادة الأسنان', $conv->context_data['last_matched_title'] ?? null);

        // Turn 2: Short pronoun follow-up ("كم سعره؟") without repeating the clinic name
        $r2 = $ai->handleCustomerMessage($company, $phone, 'كم سعره؟');
        $this->assertStringContainsString('عيادة الأسنان', $r2['reply']);
        $this->assertStringContainsString('5,000 ريال', $r2['reply']);
        $this->assertStringNotContainsString('أهلاً بك في مركز الشفاء التخصصي!', $r2['reply']);

        // Turn 3: Booking intent ("ابغى احجز موعد") remembers عيادة الأسنان
        $r3 = $ai->handleCustomerMessage($company, $phone, 'ابغى احجز موعد');
        $this->assertStringContainsString('عيادة الأسنان', $r3['reply']);
        $this->assertEquals('booking', $r3['conversation_state']);

        $conv->refresh();
        $this->assertEquals('booking', $conv->conversation_state);
        $this->assertNotEmpty($conv->summary);
    }

    public function test_human_handoff_creates_notification_and_ai_pause_stops_bot_replies(): void
    {
        Http::fake();

        $company = $this->createActiveCompany([
            'settings' => [
                'greeting_message' => 'أهلاً بك!',
                'auto_pause_on_handoff' => true,
            ],
        ]);

        $ai = app(AiChatService::class);
        $phone = '967733000444';

        // Customer asks for a human agent
        $r1 = $ai->handleCustomerMessage($company, $phone, 'ابغى اكلم موظف بشري لو سمحت');
        $this->assertEquals('success', $r1['status']);
        $this->assertEquals('human_handoff', $r1['conversation_state']);
        $this->assertEquals('handoff_router', $r1['engine']);
        $this->assertStringContainsString('967779998887', $r1['reply']);

        $this->assertDatabaseHas('system_notifications', [
            'company_id' => $company->id,
            'type' => 'human_handoff',
        ]);

        $conv = Conversation::findOrFail($r1['conversation_id']);
        $this->assertTrue($conv->ai_paused);

        // Subsequent message while AI is paused saves to DB but sends no reply
        $r2 = $ai->handleCustomerMessage($company, $phone, 'هل يوجد احد للرد؟');
        $this->assertEquals('paused', $r2['status']);
        $this->assertNull($r2['reply']);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conv->id,
            'sender' => 'customer',
            'message' => 'هل يوجد احد للرد؟',
        ]);

        // Also verify WAHA direct webhook does NOT dispatch any outbound message while paused
        $session = 'company_' . $company->id;
        $webhookRes = $this->postJson('/api/webhook/waha', [
            'event' => 'message',
            'session' => $session,
            'payload' => [
                'from' => $phone . '@c.us',
                'fromMe' => false,
                'body' => 'رسالة أخرى أثناء التوقف',
            ],
        ]);

        $webhookRes->assertOk()->assertJsonPath('status', 'paused');
        Http::assertNothingSent();
    }

    public function test_reset_context_clears_memory_and_restores_browsing_state(): void
    {
        $company = $this->createActiveCompany();

        $conversation = Conversation::create([
            'company_id' => $company->id,
            'channel' => 'whatsapp',
            'customer_phone' => '967733000555',
            'customer_name' => 'أحمد',
            'status' => 'active',
            'conversation_state' => 'booking',
            'context_data' => ['last_topic' => 'عيادة الأسنان', 'turns_count' => 5],
            'summary' => 'المواضيع: عيادة الأسنان | المرحلة: طلب حجز موعد',
            'ai_paused' => true,
            'ai_paused_at' => now(),
        ]);

        $conversation->resetContext();
        $conversation->refresh();

        $this->assertEquals('browsing', $conversation->conversation_state);
        $this->assertNull($conversation->context_data);
        $this->assertNull($conversation->summary);
        $this->assertFalse($conversation->ai_paused);
        $this->assertNull($conversation->ai_paused_at);
    }
}
