<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookMessengerWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent real external AI API calls during tests (use deterministic local fallback)
        putenv('GEMINI_API_KEY=');
        putenv('OPENAI_API_KEY=');
        putenv('META_MESSENGER_APP_SECRET=');
    }

    protected function tearDown(): void
    {
        putenv('META_MESSENGER_APP_SECRET=');

        parent::tearDown();
    }

    protected function createCompany(array $overrides = []): Company
    {
        static $counter = 1;
        $num = $counter++;

        return Company::create(array_merge([
            'name' => "شركة ماسنجر {$num}",
            'slug' => "fb-company-{$num}",
            'phone' => "77100000{$num}",
            'whatsapp_number' => "96777100000{$num}",
            'about_us' => "متجر متخصص رقم {$num}",
            'services_offered' => "منتجات وخدمات الشركة {$num}",
            'address' => "المكلا - شارع {$num}",
            'working_hours' => '9 صباحاً - 9 مساءً',
            'status' => 'active',
            'subscription_plan' => 'monthly_pro',
            'subscription_starts_at' => now()->subDay(),
            'subscription_expires_at' => now()->addMonth(),
            'messenger_page_id' => "10000000000{$num}",
            'messenger_page_name' => "صفحة شركة {$num}",
            'messenger_access_token' => "EAAG_PAGE_TOKEN_{$num}",
            'messenger_verify_token' => "verify_fb_{$num}",
            'messenger_status' => 'connected',
            'messenger_connected_at' => now(),
            'settings' => [
                'greeting_message' => "أهلاً بك في شركة ماسنجر {$num}!",
            ],
        ], $overrides));
    }

    public function test_messenger_webhook_verification_accepts_company_and_global_tokens_and_rejects_invalid(): void
    {
        $company = $this->createCompany([
            'slug' => 'elite-store',
            'messenger_verify_token' => 'custom_fb_token_123',
        ]);

        // 1. Company-specific verify token
        $this->get('/api/webhook/messenger/' . $company->slug . '?hub_mode=subscribe&hub_verify_token=custom_fb_token_123&hub_challenge=CHALLENGE_OK')
            ->assertOk()
            ->assertSeeText('CHALLENGE_OK');

        // 2. Global verify token
        $this->get('/api/webhook/messenger?hub_mode=subscribe&hub_verify_token=smartora_fb_global_2026&hub_challenge=GLOBAL_OK')
            ->assertOk()
            ->assertSeeText('GLOBAL_OK');

        // 3. Invalid token rejected
        $this->get('/api/webhook/messenger/' . $company->slug . '?hub_mode=subscribe&hub_verify_token=wrong_token&hub_challenge=NOPE')
            ->assertStatus(403);
    }

    public function test_inbound_messenger_dm_routes_to_exact_company_and_sends_graph_api_reply(): void
    {
        Http::fake([
            'https://graph.facebook.com/*/9988776655*' => Http::response([
                'name' => 'أحمد باوزير',
                'first_name' => 'أحمد',
                'last_name' => 'باوزير',
            ], 200),
            'https://graph.facebook.com/*/2222222222/messages' => Http::response([
                'recipient_id' => '9988776655',
                'message_id' => 'm_mid_12345',
            ], 200),
        ]);

        $company1 = $this->createCompany([
            'name' => 'المتجر الأول',
            'messenger_page_id' => '1111111111',
            'messenger_access_token' => 'EAAG_TOKEN_1',
        ]);

        $company2 = $this->createCompany([
            'name' => 'معرض النخبة للسيارات',
            'messenger_page_id' => '2222222222',
            'messenger_access_token' => 'EAAG_TOKEN_2',
            'settings' => ['greeting_message' => 'أهلاً بك في معرض النخبة للسيارات!'],
        ]);

        $response = $this->postJson('/api/webhook/messenger', [
            'object' => 'page',
            'entry' => [
                [
                    'id' => '2222222222',
                    'time' => 1764000000,
                    'messaging' => [
                        [
                            'sender' => ['id' => '9988776655'],
                            'recipient' => ['id' => '2222222222'],
                            'timestamp' => 1764000000,
                            'message' => [
                                'mid' => 'mid.abc',
                                'text' => 'مرحبا، ما هي ساعات الدوام؟',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('conversations', [
            'company_id' => $company2->id,
            'channel' => 'messenger',
            'customer_phone' => 'fb:9988776655',
            'customer_name' => 'أحمد باوزير',
        ]);

        $this->assertDatabaseMissing('conversations', [
            'company_id' => $company1->id,
            'customer_phone' => 'fb:9988776655',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://graph.facebook.com/v21.0/2222222222/messages')
                && $request->hasHeader('Authorization', 'Bearer EAAG_TOKEN_2')
                && $request['messaging_type'] === 'RESPONSE'
                && ($request['recipient']['id'] ?? null) === '9988776655'
                && str_contains((string) ($request['message']['text'] ?? ''), 'معرض النخبة للسيارات');
        });
    }

    public function test_messenger_webhook_ignores_echoes_self_messages_and_unknown_pages(): void
    {
        Http::fake();

        $company = $this->createCompany([
            'messenger_page_id' => '5555555555',
            'messenger_access_token' => 'EAAG_TOKEN_5',
        ]);

        // 1. Non-page object
        $this->postJson('/api/webhook/messenger', [
            'object' => 'instagram',
            'entry' => [],
        ])->assertOk()->assertJsonPath('status', 'ignored');

        // 2. Echo message & self message & unknown page
        $this->postJson('/api/webhook/messenger', [
            'object' => 'page',
            'entry' => [
                [
                    'id' => '5555555555',
                    'messaging' => [
                        [
                            'sender' => ['id' => '123456'],
                            'recipient' => ['id' => '5555555555'],
                            'message' => ['text' => 'echo text', 'is_echo' => true],
                        ],
                        [
                            'sender' => ['id' => '5555555555'],
                            'recipient' => ['id' => '5555555555'],
                            'message' => ['text' => 'self message'],
                        ],
                    ],
                ],
                [
                    'id' => '9999999999',
                    'messaging' => [
                        [
                            'sender' => ['id' => '777777'],
                            'recipient' => ['id' => '9999999999'],
                            'message' => ['text' => 'unknown page message'],
                        ],
                    ],
                ],
            ],
        ])->assertOk();

        Http::assertNothingSent();
        $this->assertEquals(0, Conversation::where('company_id', $company->id)->count());
        $this->assertEquals(0, Conversation::count());
    }

    public function test_messenger_webhook_validates_hmac_signature_when_app_secret_is_configured(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['message_id' => 'mid_hmac'], 200),
        ]);

        putenv('META_MESSENGER_APP_SECRET=super_secret_fb_key');

        $company = $this->createCompany([
            'messenger_page_id' => '3333333333',
            'messenger_access_token' => 'EAAG_TOKEN_3',
        ]);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => '3333333333',
                    'messaging' => [
                        [
                            'sender' => ['id' => '4444444444'],
                            'recipient' => ['id' => '3333333333'],
                            'message' => ['text' => 'مرحبا'],
                        ],
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($payload);

        // 1. Missing signature header -> 403
        $this->call('POST', '/api/webhook/messenger', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawBody)->assertStatus(403);

        // 2. Wrong signature header -> 403
        $this->call('POST', '/api/webhook/messenger', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalidhash',
        ], $rawBody)->assertStatus(403);

        // 3. Valid HMAC signature -> 200
        $validSig = 'sha256=' . hash_hmac('sha256', $rawBody, 'super_secret_fb_key');
        $this->call('POST', '/api/webhook/messenger', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $validSig,
        ], $rawBody)->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('conversations', [
            'company_id' => $company->id,
            'channel' => 'messenger',
            'customer_phone' => 'fb:4444444444',
        ]);
    }

    public function test_messenger_postback_triggers_ai_reply(): void
    {
        Http::fake([
            'https://graph.facebook.com/*/66778899*' => Http::response(['name' => 'سارة علي'], 200),
            'https://graph.facebook.com/*/7777777777/messages' => Http::response(['message_id' => 'mid_pb'], 200),
        ]);

        $company = $this->createCompany([
            'name' => 'عيادة الندى',
            'messenger_page_id' => '7777777777',
            'messenger_access_token' => 'EAAG_TOKEN_7',
            'settings' => ['greeting_message' => 'مرحباً بك في عيادة الندى!'],
        ]);

        $this->postJson('/api/webhook/messenger', [
            'object' => 'page',
            'entry' => [
                [
                    'id' => '7777777777',
                    'messaging' => [
                        [
                            'sender' => ['id' => '66778899'],
                            'recipient' => ['id' => '7777777777'],
                            'postback' => [
                                'title' => 'عرض الخدمات والأسعار',
                                'payload' => 'GET_STARTED',
                            ],
                        ],
                    ],
                ],
            ],
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('conversations', [
            'company_id' => $company->id,
            'channel' => 'messenger',
            'customer_phone' => 'fb:66778899',
            'last_message' => 'عرض الخدمات والأسعار',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://graph.facebook.com/v21.0/7777777777/messages')
                && ($request['recipient']['id'] ?? null) === '66778899'
                && str_contains((string) ($request['message']['text'] ?? ''), 'عيادة الندى');
        });
    }

    public function test_facebook_page_comment_to_dm_respects_keywords_and_sends_private_and_public_replies(): void
    {
        Http::fake([
            'https://graph.facebook.com/*/8888888888/messages' => Http::response(['message_id' => 'mid_dm'], 200),
            'https://graph.facebook.com/*/comment_match_1/comments' => Http::response(['id' => 'reply_1'], 200),
        ]);

        $company = $this->createCompany([
            'name' => 'متجر الأناقة',
            'messenger_page_id' => '8888888888',
            'messenger_access_token' => 'EAAG_TOKEN_8',
            'messenger_comment_reply_enabled' => true,
            'messenger_comment_keywords' => 'سعر, تفاصيل',
            'messenger_comment_public_reply' => 'تم إرسال التفاصيل على ماسنجر 📩',
            'settings' => ['greeting_message' => 'أهلاً بك في متجر الأناقة!'],
        ]);

        // 1. Comment without matching keyword -> ignored
        $this->postJson('/api/webhook/messenger', [
            'object' => 'page',
            'entry' => [
                [
                    'id' => '8888888888',
                    'changes' => [
                        [
                            'field' => 'feed',
                            'value' => [
                                'item' => 'comment',
                                'verb' => 'add',
                                'comment_id' => 'comment_nomatch_1',
                                'message' => 'جميل ما شاء الله',
                                'from' => ['id' => '101010', 'name' => 'خالد'],
                            ],
                        ],
                    ],
                ],
            ],
        ])->assertOk();

        Http::assertNothingSent();

        // 2. Comment matching keyword -> private DM + public comment reply
        $this->postJson('/api/webhook/messenger', [
            'object' => 'page',
            'entry' => [
                [
                    'id' => '8888888888',
                    'changes' => [
                        [
                            'field' => 'feed',
                            'value' => [
                                'item' => 'comment',
                                'verb' => 'add',
                                'comment_id' => 'comment_match_1',
                                'message' => 'كم السعر لو سمحت؟',
                                'from' => ['id' => '202020', 'name' => 'نورة'],
                            ],
                        ],
                    ],
                ],
            ],
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('conversations', [
            'company_id' => $company->id,
            'channel' => 'messenger',
            'customer_phone' => 'fb:202020',
            'customer_name' => 'نورة',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://graph.facebook.com/v21.0/8888888888/messages')
                && ($request['recipient']['comment_id'] ?? null) === 'comment_match_1';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://graph.facebook.com/v21.0/comment_match_1/comments')
                && ($request['message'] ?? null) === 'تم إرسال التفاصيل على ماسنجر 📩';
        });
    }
}
