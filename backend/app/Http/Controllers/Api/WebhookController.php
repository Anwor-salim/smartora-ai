<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        protected AiChatService $aiChatService
    ) {}

    /**
     * Inbound WhatsApp Webhook (From WhatsApp API / n8n / Meta).
     */
    public function whatsapp(Request $request): JsonResponse
    {
        // Support multiple payload styles (Direct, n8n, WAHA nested):
        $payload = $request->input('payload');
        if (!is_array($payload)) {
            $payload = [];
        }

        // 1. Check if fromMe (ignore bot's own sent messages)
        $fromMe = $request->input('fromMe') ?? ($payload['fromMe'] ?? false);
        if ($fromMe) {
            return response()->json(['status' => 'ignored', 'message' => 'Outgoing bot message ignored']);
        }

        $customerPhone = $request->input('customer_phone')
            ?? $request->input('from')
            ?? $request->input('phone')
            ?? $request->input('sender')
            ?? ($payload['from'] ?? null)
            ?? ($payload['sender'] ?? null);

        $companyIdentifier = $request->input('company_whatsapp')
            ?? $request->input('to')
            ?? $request->input('recipient')
            ?? $request->input('company_id')
            ?? $request->input('company_slug')
            ?? ($payload['to'] ?? null)
            ?? ($payload['recipient'] ?? null);

        $messageText = $request->input('message')
            ?? $request->input('body')
            ?? $request->input('text')
            ?? ($payload['body'] ?? null)
            ?? ($payload['text'] ?? null)
            ?? '';

        // Clean whatsapp @c.us or @s.whatsapp.net suffix
        if ($customerPhone && str_contains($customerPhone, '@')) {
            $customerPhone = explode('@', $customerPhone)[0];
        }
        $customerPhone = preg_replace('/\D/', '', (string)$customerPhone);

        if (!$customerPhone || !$messageText) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing customer phone or message body',
            ], 400);
        }

        // Find company
        $company = null;
        if (is_numeric($companyIdentifier) && strlen((string)$companyIdentifier) <= 6) {
            $company = Company::find($companyIdentifier);
        } elseif ($companyIdentifier) {
            $cleanNumber = preg_replace('/[^0-9]/', '', (string)$companyIdentifier);
            $company = Company::where('whatsapp_number', 'like', "%$cleanNumber%")
                ->orWhere('phone', 'like', "%$cleanNumber%")
                ->orWhere('slug', $companyIdentifier)
                ->first();
        }

        // If company not explicitly provided, fallback to the main company #1 (Super Admin tenant)
        if (!$company) {
            $company = Company::where('slug', 'smartora-main')->first() ?? Company::first();
        }

        if (!$company) {
            return response()->json([
                'status' => 'error',
                'message' => 'Company not found',
            ], 404);
        }

        // Process message through AI Multi-tenant Engine
        $result = $this->aiChatService->handleCustomerMessage($company, $customerPhone, $messageText);

        return response()->json([
            'status' => $result['status'],
            'reply' => $result['reply'],
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'is_active' => $company->isSubscriptionActive(),
            ],
            'customer_phone' => $customerPhone,
            'is_new_customer' => $result['is_new_customer'] ?? false,
        ]);
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