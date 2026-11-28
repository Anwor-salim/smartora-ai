<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\AiChatService;
use App\Services\InstagramService;
use App\Services\MessengerService;
use App\Services\MetaWhatsAppService;
use App\Services\WahaWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompanyChannelsController extends Controller
{
    protected function resolveCompany(Request $request): ?Company
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        if ($user->isCompanyAdmin()) {
            return $user->company;
        }

        return $user->company ?? Company::first();
    }

    protected function buildWhatsAppPayload(Company $company, WahaWhatsAppService $waha, bool $fetchQr = true): array
    {
        $provider = $company->whatsapp_provider ?: 'waha';
        $connectionStatus = 'disconnected';
        $qrCodeUrl = null;
        $pushName = null;
        $connectedAt = null;
        $whatsappNumber = $company->whatsapp_number;

        if ($provider === 'meta_cloud') {
            if ($company->isMetaConnected()) {
                $connectionStatus = 'connected';
                $pushName = 'واتساب معتمد (Meta Cloud API)';
                $connectedAt = $company->whatsapp_connected_at?->format('d/m/Y H:i') ?? 'اتصال دائم';
            }
        } else {
            $session = $company->getWahaSessionName();
            if ($waha->isWorking($session)) {
                $connectedPhone = $waha->getConnectedPhone($session) ?? $company->whatsapp_number;
                $pushName = $waha->getPushName($session);
                $whatsappNumber = $connectedPhone;
                $connectionStatus = 'connected';
                $connectedAt = $company->whatsapp_connected_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');

                if ($company->whatsapp_session_status !== 'connected' || ($connectedPhone && $company->whatsapp_number !== $connectedPhone)) {
                    $updateData = [
                        'whatsapp_session_status' => 'connected',
                        'whatsapp_connected_at' => $company->whatsapp_connected_at ?? now(),
                    ];

                    $numberBelongsToAnother = $connectedPhone
                        && Company::where('id', '!=', $company->id)
                            ->where('whatsapp_number', $connectedPhone)
                            ->exists();

                    if ($connectedPhone && !$numberBelongsToAnother) {
                        $updateData['whatsapp_number'] = $connectedPhone;
                    }

                    try {
                        $company->update($updateData);
                    } catch (\Throwable $e) {
                        Log::warning("Could not sync whatsapp_number for company {$company->id}: " . $e->getMessage());
                    }
                }
            } else {
                if ($company->whatsapp_session_status !== 'disconnected') {
                    try {
                        $company->update(['whatsapp_session_status' => 'disconnected']);
                    } catch (\Throwable) {
                    }
                }

                if ($fetchQr) {
                    $qr = $waha->getQrCode($session);
                    if ($qr) {
                        $qrCodeUrl = $qr;
                        $connectionStatus = 'scanning';
                    }
                }
            }
        }

        return [
            'whatsapp_provider' => $provider,
            'connection_status' => $connectionStatus,
            'whatsapp_number' => $whatsappNumber,
            'support_phone' => $company->support_phone,
            'push_name' => $pushName,
            'connected_at' => $connectedAt,
            'qr_code_url' => $qrCodeUrl,
            'waha_session' => $company->getWahaSessionName(),
            'meta_phone_number_id' => $company->meta_phone_number_id,
            'meta_waba_id' => $company->meta_waba_id,
            'meta_access_token' => $company->meta_access_token,
            'meta_verify_token' => $company->getEffectiveVerifyToken(),
            'meta_webhook_url' => url("/api/webhook/meta/{$company->id}"),
        ];
    }

    public function whatsappStatus(Request $request, WahaWhatsAppService $waha): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        return response()->json($this->buildWhatsAppPayload($company, $waha, true));
    }

    public function switchWhatsappProvider(Request $request, WahaWhatsAppService $waha): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $validated = $request->validate([
            'provider' => 'required|in:waha,meta_cloud',
        ]);

        $company->update(['whatsapp_provider' => $validated['provider']]);
        $company->refresh();

        return response()->json(array_merge(
            ['message' => 'تم تفعيل: ' . ($validated['provider'] === 'meta_cloud' ? 'Meta Cloud API الرسمي' : 'ربط الباركود الفوري (QR)')],
            $this->buildWhatsAppPayload($company, $waha, true)
        ));
    }

    public function generateWhatsappQr(Request $request, WahaWhatsAppService $waha): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $waha->restartSession($company->getWahaSessionName());
        usleep(400000);

        return response()->json(array_merge(
            ['message' => 'تم تحديث وتوليد رمز QR جديد'],
            $this->buildWhatsAppPayload($company, $waha, true)
        ));
    }

    public function checkWhatsappConnection(Request $request, WahaWhatsAppService $waha): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $payload = $this->buildWhatsAppPayload($company, $waha, true);
        $payload['message'] = $payload['connection_status'] === 'connected'
            ? 'الواتساب متصل بنجاح!'
            : 'لم يكتمل مسح الباركود بعد، يرجى مسح الرمز من تطبيق واتساب.';

        return response()->json($payload);
    }

    public function disconnectWhatsapp(Request $request, WahaWhatsAppService $waha): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        if (($company->whatsapp_provider ?: 'waha') === 'meta_cloud') {
            $company->update([
                'meta_phone_number_id' => null,
                'meta_access_token' => null,
                'meta_waba_id' => null,
                'whatsapp_session_status' => 'disconnected',
                'whatsapp_connected_at' => null,
            ]);

            return response()->json(array_merge(
                ['message' => 'تم فصل اتصال Meta Cloud API'],
                $this->buildWhatsAppPayload($company->fresh(), $waha, false)
            ));
        }

        $waha->logoutSession($company->getWahaSessionName());
        try {
            $company->update([
                'whatsapp_session_status' => 'disconnected',
                'whatsapp_connected_at' => null,
            ]);
        } catch (\Throwable) {
        }

        usleep(400000);

        return response()->json(array_merge(
            ['message' => 'تم تسجيل الخروج وفصل الجلسة بنجاح'],
            $this->buildWhatsAppPayload($company->fresh(), $waha, true)
        ));
    }

    public function saveWhatsappNumbers(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $validated = $request->validate([
            'whatsapp_number' => 'nullable|string|max:40',
            'support_phone' => 'nullable|string|max:40',
        ]);

        try {
            $company->update([
                'whatsapp_number' => trim((string) ($validated['whatsapp_number'] ?? '')) ?: null,
                'support_phone' => trim((string) ($validated['support_phone'] ?? '')) ?: null,
            ]);

            return response()->json([
                'message' => 'تم حفظ وتحديث الأرقام بنجاح',
                'whatsapp_number' => $company->whatsapp_number,
                'support_phone' => $company->support_phone,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'رقم الواتساب هذا مسجل مسبقاً لشركة أخرى',
            ], 422);
        }
    }

    public function saveMetaCredentials(Request $request, MetaWhatsAppService $metaService, WahaWhatsAppService $waha): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $validated = $request->validate([
            'meta_phone_number_id' => 'nullable|string|max:100',
            'meta_waba_id' => 'nullable|string|max:100',
            'meta_access_token' => 'nullable|string',
            'meta_verify_token' => 'nullable|string|max:100',
        ]);

        $phoneId = trim((string) ($validated['meta_phone_number_id'] ?? ''));
        $token = trim((string) ($validated['meta_access_token'] ?? ''));

        $company->update([
            'whatsapp_provider' => 'meta_cloud',
            'meta_phone_number_id' => $phoneId ?: null,
            'meta_waba_id' => trim((string) ($validated['meta_waba_id'] ?? '')) ?: null,
            'meta_access_token' => $token ?: null,
            'meta_verify_token' => trim((string) ($validated['meta_verify_token'] ?? '')) ?: null,
        ]);

        $verifiedInfo = null;
        if ($phoneId !== '' && $token !== '') {
            $res = $metaService->verifyConnection($phoneId, $token);
            if (!empty($res['success'])) {
                $data = $res['data'] ?? [];
                $displayPhone = $data['display_phone_number'] ?? null;
                $verifiedInfo = [
                    'verified_name' => $data['verified_name'] ?? null,
                    'quality_rating' => $data['quality_rating'] ?? null,
                ];
                $updateData = [
                    'whatsapp_session_status' => 'connected',
                    'whatsapp_connected_at' => now(),
                ];
                if ($displayPhone) {
                    $updateData['whatsapp_number'] = preg_replace('/\D/', '', $displayPhone);
                }
                $company->update($updateData);
            } else {
                return response()->json(array_merge(
                    ['message' => 'تم حفظ البيانات لكن تعذر التحقق من Meta: ' . ($res['error'] ?? 'بيانات غير صحيحة')],
                    $this->buildWhatsAppPayload($company->fresh(), $waha, false)
                ), 422);
            }
        }

        return response()->json(array_merge(
            [
                'message' => 'تم حفظ بيانات Meta Cloud API بنجاح',
                'verified_info' => $verifiedInfo,
            ],
            $this->buildWhatsAppPayload($company->fresh(), $waha, false)
        ));
    }

    public function sendWhatsappTestMessage(Request $request, AiChatService $aiService, MetaWhatsAppService $metaService, WahaWhatsAppService $waha): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $validated = $request->validate([
            'phone' => 'nullable|string|max:40',
            'message' => 'required|string|max:2000',
        ]);

        $target = trim((string) ($validated['phone'] ?: $company->whatsapp_number));
        if ($target === '') {
            return response()->json(['message' => 'يرجى تحديد رقم هاتف لإرسال الرسالة التجريبية إليه'], 422);
        }

        $res = $aiService->handleCustomerMessage($company, $target, $validated['message']);
        $delivered = false;

        if (($company->whatsapp_provider ?: 'waha') === 'meta_cloud') {
            if ($company->isMetaConnected() && !empty($res['reply'])) {
                $sendRes = $metaService->sendText($company, $target, $res['reply']);
                $delivered = (bool) ($sendRes['success'] ?? false);
            }
        } else {
            $session = $company->getWahaSessionName();
            if ($waha->isWorking($session) && !empty($res['reply'])) {
                $delivered = $waha->sendText($target, $res['reply'], $session);
            }
        }

        return response()->json([
            'message' => 'تمت معالجة الرسالة التجريبية بنجاح: ' . Str::limit((string) ($res['reply'] ?? ''), 120),
            'reply' => $res['reply'] ?? '',
            'delivered' => $delivered,
        ]);
    }

    protected function buildInstagramPayload(Company $company, InstagramService $igService): array
    {
        $isOAuthConfigured = $igService->isOAuthConfigured();

        return [
            'connection_status' => $company->isInstagramConnected() ? 'connected' : 'disconnected',
            'connected_at' => $company->isInstagramConnected()
                ? ($company->instagram_connected_at?->format('d/m/Y H:i') ?? 'متصل')
                : null,
            'instagram_account_id' => $company->instagram_account_id,
            'instagram_username' => $company->instagram_username,
            'instagram_access_token' => $company->instagram_access_token,
            'instagram_verify_token' => $company->getEffectiveInstagramVerifyToken(),
            'webhook_url' => url("/api/webhook/instagram/{$company->id}"),
            'comment_reply_enabled' => (bool) $company->instagram_comment_reply_enabled,
            'comment_keywords' => $company->instagram_comment_keywords ?? '',
            'comment_public_reply' => $company->instagram_comment_public_reply ?: 'أهلاً بك! تم إرسال كافة التفاصيل على الخاص 📩',
            'is_oauth_configured' => $isOAuthConfigured,
            'oauth_url' => $isOAuthConfigured ? $igService->buildOAuthUrl($company) : null,
        ];
    }

    public function instagramStatus(Request $request, InstagramService $igService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        return response()->json($this->buildInstagramPayload($company, $igService));
    }

    public function saveInstagramCredentials(Request $request, InstagramService $igService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $validated = $request->validate([
            'instagram_account_id' => 'nullable|string|max:100',
            'instagram_username' => 'nullable|string|max:100',
            'instagram_access_token' => 'nullable|string',
            'instagram_verify_token' => 'nullable|string|max:120',
        ]);

        $accountId = trim((string) ($validated['instagram_account_id'] ?? ''));
        $token = trim((string) ($validated['instagram_access_token'] ?? ''));

        $company->update([
            'instagram_account_id' => $accountId ?: null,
            'instagram_username' => trim((string) ($validated['instagram_username'] ?? ''), "@ \t\n\r") ?: null,
            'instagram_access_token' => $token ?: null,
            'instagram_verify_token' => trim((string) ($validated['instagram_verify_token'] ?? '')) ?: null,
        ]);

        $followersCount = null;
        if ($token !== '') {
            $res = $igService->verifyConnection($accountId, $token);
            if (!empty($res['success'])) {
                $data = $res['data'] ?? [];
                $resolvedId = (string) ($data['user_id'] ?? $data['id'] ?? $accountId);
                $resolvedUsername = $data['username'] ?? $company->instagram_username;
                $followersCount = isset($data['followers_count']) ? (int) $data['followers_count'] : null;

                $company->update([
                    'instagram_account_id' => $resolvedId ?: null,
                    'instagram_username' => $resolvedUsername ? ltrim((string) $resolvedUsername, '@') : null,
                    'instagram_status' => 'connected',
                    'instagram_connected_at' => $company->instagram_connected_at ?? now(),
                ]);
            } else {
                $company->update(['instagram_status' => 'disconnected']);

                return response()->json(array_merge(
                    ['message' => 'تعذر التحقق من حساب انستقرام: ' . ($res['error'] ?? 'بيانات غير صحيحة')],
                    $this->buildInstagramPayload($company->fresh(), $igService)
                ), 422);
            }
        }

        return response()->json(array_merge(
            [
                'message' => 'تم حفظ وربط حساب انستقرام بنجاح!',
                'followers_count' => $followersCount,
            ],
            $this->buildInstagramPayload($company->fresh(), $igService)
        ));
    }

    public function disconnectInstagram(Request $request, InstagramService $igService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $company->update([
            'instagram_account_id' => null,
            'instagram_username' => null,
            'instagram_access_token' => null,
            'instagram_status' => 'disconnected',
            'instagram_connected_at' => null,
        ]);

        return response()->json(array_merge(
            ['message' => 'تم فصل حساب انستقرام بنجاح'],
            $this->buildInstagramPayload($company->fresh(), $igService)
        ));
    }

    public function saveInstagramComments(Request $request, InstagramService $igService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $validated = $request->validate([
            'comment_reply_enabled' => 'required|boolean',
            'comment_keywords' => 'nullable|string|max:1000',
            'comment_public_reply' => 'nullable|string|max:500',
        ]);

        $company->update([
            'instagram_comment_reply_enabled' => (bool) $validated['comment_reply_enabled'],
            'instagram_comment_keywords' => trim((string) ($validated['comment_keywords'] ?? '')) ?: null,
            'instagram_comment_public_reply' => trim((string) ($validated['comment_public_reply'] ?? '')) ?: null,
        ]);

        return response()->json(array_merge(
            ['message' => 'تم حفظ إعدادات الرد على التعليقات بنجاح'],
            $this->buildInstagramPayload($company->fresh(), $igService)
        ));
    }

    public function simulateInstagramReply(Request $request, AiChatService $aiService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $res = $aiService->handleCustomerMessage(
            $company,
            'ig:simulator_preview',
            trim($validated['message']),
            'instagram',
            '@test_visitor'
        );

        return response()->json([
            'reply' => $res['reply'] ?? '',
            'engine' => $res['engine'] ?? null,
        ]);
    }

    protected function buildMessengerPayload(Company $company, MessengerService $messengerService): array
    {
        $isOAuthConfigured = $messengerService->isOAuthConfigured();

        return [
            'connection_status' => $company->isMessengerConnected() ? 'connected' : 'disconnected',
            'connected_at' => $company->isMessengerConnected()
                ? ($company->messenger_connected_at?->format('d/m/Y H:i') ?? 'متصل')
                : null,
            'messenger_page_id' => $company->messenger_page_id,
            'messenger_page_name' => $company->messenger_page_name,
            'messenger_access_token' => $company->messenger_access_token,
            'messenger_verify_token' => $company->getEffectiveMessengerVerifyToken(),
            'webhook_url' => url("/api/webhook/messenger/{$company->id}"),
            'comment_reply_enabled' => (bool) $company->messenger_comment_reply_enabled,
            'comment_keywords' => $company->messenger_comment_keywords ?? '',
            'comment_public_reply' => $company->messenger_comment_public_reply ?: 'أهلاً بك! تم إرسال كافة التفاصيل على ماسنجر 📩',
            'is_oauth_configured' => $isOAuthConfigured,
            'oauth_url' => $isOAuthConfigured ? $messengerService->buildOAuthUrl($company) : null,
        ];
    }

    public function messengerStatus(Request $request, MessengerService $messengerService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        return response()->json($this->buildMessengerPayload($company, $messengerService));
    }

    public function saveMessengerCredentials(Request $request, MessengerService $messengerService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $validated = $request->validate([
            'messenger_page_id' => 'nullable|string|max:100',
            'messenger_page_name' => 'nullable|string|max:150',
            'messenger_access_token' => 'nullable|string',
            'messenger_verify_token' => 'nullable|string|max:120',
        ]);

        $pageId = trim((string) ($validated['messenger_page_id'] ?? ''));
        $token = trim((string) ($validated['messenger_access_token'] ?? ''));

        $company->update([
            'messenger_page_id' => $pageId ?: null,
            'messenger_page_name' => trim((string) ($validated['messenger_page_name'] ?? '')) ?: null,
            'messenger_access_token' => $token ?: null,
            'messenger_verify_token' => trim((string) ($validated['messenger_verify_token'] ?? '')) ?: null,
        ]);

        $fanCount = null;
        if ($token !== '') {
            $res = $messengerService->verifyConnection($pageId, $token);
            if (!empty($res['success'])) {
                $data = $res['data'] ?? [];
                $resolvedId = (string) ($data['id'] ?? $pageId);
                $resolvedName = $data['name'] ?? $company->messenger_page_name;
                $fanCount = isset($data['fan_count']) ? (int) $data['fan_count'] : null;

                $company->update([
                    'messenger_page_id' => $resolvedId ?: null,
                    'messenger_page_name' => $resolvedName ?: null,
                    'messenger_status' => 'connected',
                    'messenger_connected_at' => $company->messenger_connected_at ?? now(),
                ]);
            } else {
                $company->update(['messenger_status' => 'disconnected']);

                return response()->json(array_merge(
                    ['message' => 'تعذر التحقق من صفحة فيسبوك: ' . ($res['error'] ?? 'بيانات غير صحيحة')],
                    $this->buildMessengerPayload($company->fresh(), $messengerService)
                ), 422);
            }
        }

        return response()->json(array_merge(
            [
                'message' => 'تم حفظ وربط صفحة ماسنجر فيسبوك بنجاح!',
                'fan_count' => $fanCount,
            ],
            $this->buildMessengerPayload($company->fresh(), $messengerService)
        ));
    }

    public function disconnectMessenger(Request $request, MessengerService $messengerService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $company->update([
            'messenger_page_id' => null,
            'messenger_page_name' => null,
            'messenger_access_token' => null,
            'messenger_status' => 'disconnected',
            'messenger_connected_at' => null,
        ]);

        return response()->json(array_merge(
            ['message' => 'تم فصل صفحة ماسنجر فيسبوك بنجاح'],
            $this->buildMessengerPayload($company->fresh(), $messengerService)
        ));
    }

    public function saveMessengerComments(Request $request, MessengerService $messengerService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $validated = $request->validate([
            'comment_reply_enabled' => 'required|boolean',
            'comment_keywords' => 'nullable|string|max:1000',
            'comment_public_reply' => 'nullable|string|max:500',
        ]);

        $company->update([
            'messenger_comment_reply_enabled' => (bool) $validated['comment_reply_enabled'],
            'messenger_comment_keywords' => trim((string) ($validated['comment_keywords'] ?? '')) ?: null,
            'messenger_comment_public_reply' => trim((string) ($validated['comment_public_reply'] ?? '')) ?: null,
        ]);

        return response()->json(array_merge(
            ['message' => 'تم حفظ إعدادات الرد على تعليقات فيسبوك بنجاح'],
            $this->buildMessengerPayload($company->fresh(), $messengerService)
        ));
    }

    public function simulateMessengerReply(Request $request, AiChatService $aiService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $res = $aiService->handleCustomerMessage(
            $company,
            'fb:simulator_preview',
            trim($validated['message']),
            'messenger',
            'عميل ماسنجر تجريبي'
        );

        return response()->json([
            'reply' => $res['reply'] ?? '',
            'engine' => $res['engine'] ?? null,
        ]);
    }
}
