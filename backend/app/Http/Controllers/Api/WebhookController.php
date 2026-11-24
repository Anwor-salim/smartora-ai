<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\AiChatService;
use App\Services\WahaWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected AiChatService $aiChatService,
        protected WahaWhatsAppService $wahaService
    ) {}

    /**
     * Inbound WhatsApp Webhook (From n8n / WAHA / Meta / Direct API).
     * Resolves the exact tenant company strictly by WAHA session or receiving WhatsApp number
     * and returns the reply + exact WAHA session to send from.
     */
    public function whatsapp(Request $request): JsonResponse
    {
        return $this->processInboundWebhook($request, autoSendViaWaha: false);
    }

    /**
     * Direct WAHA Inbound Webhook (WAHA -> Laravel directly without n8n).
     * Processes the message for the exact company owning the WAHA session
     * and sends the reply directly from that company's own WAHA session.
     */
    public function waha(Request $request): JsonResponse
    {
        return $this->processInboundWebhook($request, autoSendViaWaha: true);
    }

    /**
     * Shared multi-tenant inbound WhatsApp webhook processor.
     */
    protected function processInboundWebhook(Request $request, bool $autoSendViaWaha): JsonResponse
    {
        $payload = $request->input('payload');
        if (!is_array($payload)) {
            $payload = [];
        }

        // 1. Ignore non-message WAHA webhook events (e.g. session.status, message.ack)
        $event = $request->input('event');
        if (is_string($event) && $event !== '' && !in_array($event, ['message', 'message.any'], true)) {
            return response()->json([
                'status' => 'ignored',
                'message' => "Non-message event ({$event}) ignored",
            ]);
        }

        // 2. Ignore outgoing messages sent by the bot / connected account itself
        $fromMe = $request->input('fromMe')
            ?? ($payload['fromMe'] ?? null)
            ?? ($payload['_data']['key']['fromMe'] ?? false);

        if ($fromMe) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Outgoing bot message ignored',
            ]);
        }

        // 3. Ignore group chats, status broadcasts, and channels/newsletters
        $rawSenderJid = (string) (
            $request->input('from')
            ?? ($payload['from'] ?? null)
            ?? ($payload['_data']['key']['remoteJid'] ?? '')
        );

        if (
            str_contains($rawSenderJid, '@g.us')
            || str_contains($rawSenderJid, '@broadcast')
            || str_contains($rawSenderJid, '@newsletter')
        ) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Group or broadcast message ignored',
            ]);
        }

        // 4. Extract customer phone, reply chatId, and message text
        [$customerPhone, $customerChatId] = $this->extractCustomerIdentity($request, $payload);
        $messageText = $this->extractMessageText($request, $payload);

        if (!$customerPhone || !$messageText) {
            if ($autoSendViaWaha) {
                return response()->json([
                    'status' => 'ignored',
                    'message' => 'Missing customer phone or text body',
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Missing customer phone or message body',
            ], 400);
        }

        // 5. Resolve the exact receiving company (strict multi-tenant isolation)
        $incomingSession = $request->input('session') ?? ($payload['session'] ?? null);
        $incomingSession = is_string($incomingSession) ? trim($incomingSession) : null;

        $company = $this->resolveCompany($incomingSession, $request, $payload);

        if (!$company) {
            Log::warning('WhatsApp webhook could not resolve target company (cross-tenant fallback blocked).', [
                'session' => $incomingSession,
                'me_id' => $request->input('me.id') ?? ($payload['me']['id'] ?? null),
                'company_whatsapp' => $request->input('company_whatsapp'),
                'to' => $request->input('to') ?? ($payload['to'] ?? null),
                'company_id' => $request->input('company_id'),
                'company_slug' => $request->input('company_slug'),
                'customer_phone' => $customerPhone,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Company not found for this WhatsApp session or number',
            ], 404);
        }

        // Always reply via the exact company's WAHA session
        $replySession = $incomingSession ?: $company->getWahaSessionName();

        // Process message through AI Multi-tenant Engine
        $result = $this->aiChatService->handleCustomerMessage($company, $customerPhone, $messageText);

        $sent = null;
        if ($autoSendViaWaha && !empty($result['reply'])) {
            $sent = $this->wahaService->sendText($customerChatId, $result['reply'], $replySession);
        }

        $response = [
            'status' => $result['status'],
            'reply' => $result['reply'],
            'session' => $replySession,
            'customer_phone' => $customerPhone,
            'customer_chat_id' => $customerChatId,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'whatsapp_number' => $company->whatsapp_number,
                'session' => $replySession,
                'is_active' => $company->isSubscriptionActive(),
            ],
            'is_new_customer' => $result['is_new_customer'] ?? false,
        ];

        if ($autoSendViaWaha) {
            $response['sent'] = $sent;
        }

        return response()->json($response);
    }

    /**
     * Resolve the exact company that owns the receiving WhatsApp session or number.
     * Strict multi-tenant isolation: NEVER falls back to Company::first() or smartora-main.
     */
    protected function resolveCompany(?string $sessionName, Request $request, array $payload): ?Company
    {
        // 1. WAHA multi-tenant session name: "company_{id}" (highest priority, strict)
        if ($sessionName && preg_match('/^company_(\d+)$/', $sessionName, $matches)) {
            return Company::find((int) $matches[1]);
        }

        // 2. Custom non-default WAHA session configured on a company
        if ($sessionName && $sessionName !== 'default') {
            $byCustomSession = Company::all()->first(
                fn (Company $company) => $company->getWahaSessionName() === $sessionName
            );
            if ($byCustomSession) {
                return $byCustomSession;
            }
        }

        // 3. Explicit company_id or company_slug
        $explicitId = $request->input('company_id');
        if ($explicitId !== null && $explicitId !== '' && is_numeric($explicitId)) {
            return Company::find((int) $explicitId);
        }

        $explicitSlug = $request->input('company_slug');
        if (is_string($explicitSlug) && trim($explicitSlug) !== '') {
            return Company::where('slug', trim($explicitSlug))->first();
        }

        // 4. Receiving WhatsApp account identifier (WAHA me.id, company_whatsapp, to, recipient)
        $receivingCandidates = array_filter([
            $request->input('me.id'),
            $payload['me']['id'] ?? null,
            $request->input('company_whatsapp'),
            $request->input('to'),
            $request->input('recipient'),
            $payload['to'] ?? null,
            $payload['recipient'] ?? null,
        ], fn ($val) => is_scalar($val) && trim((string) $val) !== '');

        foreach ($receivingCandidates as $candidate) {
            $candidateStr = trim((string) $candidate);

            // Allow passing a company slug in company_whatsapp/recipient if non-JID alphabetic
            if (!str_contains($candidateStr, '@') && preg_match('/[a-zA-Z]/', $candidateStr)) {
                $bySlug = Company::where('slug', $candidateStr)->first();
                if ($bySlug) {
                    return $bySlug;
                }
            }

            $normalizedCandidate = $this->normalizePhone($candidateStr);
            if (strlen($normalizedCandidate) >= 7) {
                $matched = $this->findCompanyByPhone($normalizedCandidate);
                if ($matched) {
                    return $matched;
                }
            }
        }

        // 5. Legacy WAHA "default" session resolution (only when session === "default")
        if ($sessionName === 'default') {
            $defaultCompany = Company::all()->first(
                fn (Company $company) => $company->getWahaSessionName() === 'default'
            );
            if ($defaultCompany) {
                return $defaultCompany;
            }
        }

        return null;
    }

    /**
     * Find a company by normalized WhatsApp number (preferred) or phone number.
     */
    protected function findCompanyByPhone(string $normalizedNumber): ?Company
    {
        $companies = Company::all(['id', 'whatsapp_number', 'phone', 'name', 'slug', 'status', 'subscription_expires_at', 'settings', 'about_us', 'services_offered', 'support_phone', 'email', 'address', 'google_maps_url', 'working_hours', 'custom_system_prompt', 'whatsapp_session_status', 'whatsapp_connected_at']);

        // Exact normalized whatsapp_number match
        foreach ($companies as $company) {
            $wa = $this->normalizePhone((string) $company->whatsapp_number);
            if ($wa !== '' && $wa === $normalizedNumber) {
                return $company;
            }
        }

        // Suffix match (last 9 digits) on whatsapp_number to handle local vs country-code formats
        $tail = strlen($normalizedNumber) >= 9 ? substr($normalizedNumber, -9) : $normalizedNumber;
        if (strlen($tail) >= 7) {
            foreach ($companies as $company) {
                $wa = $this->normalizePhone((string) $company->whatsapp_number);
                if (strlen($wa) >= 7 && (str_ends_with($wa, $tail) || str_ends_with($normalizedNumber, $wa))) {
                    return $company;
                }
            }
        }

        // Exact normalized phone match
        foreach ($companies as $company) {
            $phone = $this->normalizePhone((string) $company->phone);
            if ($phone !== '' && $phone === $normalizedNumber) {
                return $company;
            }
        }

        // Suffix match on phone
        if (strlen($tail) >= 7) {
            foreach ($companies as $company) {
                $phone = $this->normalizePhone((string) $company->phone);
                if (strlen($phone) >= 7 && (str_ends_with($phone, $tail) || str_ends_with($normalizedNumber, $phone))) {
                    return $company;
                }
            }
        }

        return null;
    }

    /**
     * Extract normalized customer phone and WhatsApp chatId (supporting @c.us, @s.whatsapp.net, and @lid with remoteJidAlt).
     *
     * @return array{0: string, 1: string}
     */
    protected function extractCustomerIdentity(Request $request, array $payload): array
    {
        $explicitChatId = $request->input('customer_chatId')
            ?? $request->input('customer_chat_id')
            ?? $request->input('chatId');

        $remoteJid = (string) ($payload['_data']['key']['remoteJid'] ?? $payload['from'] ?? '');
        $remoteJidAlt = (string) ($payload['_data']['key']['remoteJidAlt'] ?? '');

        $rawPhone = $request->input('customer_phone')
            ?? $request->input('phone')
            ?? $request->input('sender')
            ?? $request->input('from');

        if (!$rawPhone) {
            if ($remoteJidAlt !== '' && str_contains($remoteJidAlt, '@')) {
                $rawPhone = $remoteJidAlt;
            } elseif ($remoteJid !== '' && (str_contains($remoteJid, '@s.whatsapp.net') || str_contains($remoteJid, '@c.us'))) {
                $rawPhone = $remoteJid;
            } else {
                $rawPhone = $payload['from'] ?? ($payload['sender'] ?? $remoteJid);
            }
        } elseif (is_string($rawPhone) && str_contains($rawPhone, '@lid') && $remoteJidAlt !== '') {
            $rawPhone = $remoteJidAlt;
        }

        $customerPhone = $this->normalizePhone((string) $rawPhone);

        if (is_string($explicitChatId) && trim($explicitChatId) !== '') {
            $customerChatId = trim($explicitChatId);
        } elseif ($customerPhone !== '' && !str_contains((string) $rawPhone, '@lid')) {
            $customerChatId = $customerPhone . '@c.us';
        } elseif (!empty($payload['from'])) {
            $customerChatId = (string) $payload['from'];
        } elseif ($remoteJid !== '') {
            $customerChatId = $remoteJid;
        } else {
            $customerChatId = $customerPhone !== '' ? ($customerPhone . '@c.us') : '';
        }

        return [$customerPhone, $customerChatId];
    }

    /**
     * Extract message text from Direct, n8n, or nested WAHA payloads.
     */
    protected function extractMessageText(Request $request, array $payload): string
    {
        $candidates = [
            $request->input('message'),
            $request->input('body'),
            $request->input('text'),
            $payload['body'] ?? null,
            $payload['text'] ?? null,
            $payload['message']['conversation'] ?? null,
            $payload['_data']['message']['conversation'] ?? null,
            $payload['_data']['message']['extendedTextMessage']['text'] ?? null,
        ];

        foreach ($candidates as $text) {
            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }
        }

        return '';
    }

    /**
     * Normalize a WhatsApp JID or phone string into clean digits (stripping @domain, :deviceId, +, spaces, leading 00).
     */
    protected function normalizePhone(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '@')) {
            $value = explode('@', $value)[0];
        }

        if (str_contains($value, ':')) {
            $value = explode(':', $value)[0];
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Test Chat for Dashboard preview.
     */
    public function testChat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
            'company_id' => 'nullable|integer',
        ]);

        $user = $request->user();
        $company = null;

        if ($user && $user->role === 'super_admin' && $request->company_id) {
            $company = Company::find($request->company_id);
        } elseif ($user && $user->company) {
            $company = $user->company;
        } else {
            $company = Company::first();
        }

        if (!$company) {
            return response()->json(['message' => 'الشركة غير موجودة'], 404);
        }

        $testPhone = '967' . ($user->phone ?: '771314738');
        $result = $this->aiChatService->handleCustomerMessage($company, $testPhone, $request->message);

        return response()->json([
            'reply' => $result['reply'],
            'status' => $result['status'],
            'company_name' => $company->name,
            'is_subscription_active' => $company->isSubscriptionActive(),
        ]);
    }
}
