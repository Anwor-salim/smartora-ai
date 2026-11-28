<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ChannelMessageService
{
    public function __construct(
        protected WahaWhatsAppService $wahaService,
        protected MetaWhatsAppService $metaWhatsAppService,
        protected InstagramService $instagramService,
        protected MessengerService $messengerService,
    ) {}

    /**
     * Send an outbound notification message to a customer on their channel and log it in conversation history.
     */
    public function sendToCustomer(
        Company $company,
        string $customerPhone,
        string $message,
        string $channel = 'whatsapp',
        ?Conversation $conversation = null
    ): bool {
        $formatted = $channel === 'whatsapp'
            ? (preg_replace('/\*\*(.+?)\*\*/u', '*$1*', $message) ?? $message)
            : $message;

        $sent = false;

        try {
            if ($channel === 'instagram') {
                if ($company->isInstagramConnected()) {
                    $recipientId = (string) preg_replace('/^ig:/', '', $customerPhone);
                    $res = $this->instagramService->sendDirectMessage($company, $recipientId, $formatted);
                    $sent = (bool) ($res['success'] ?? false);
                }
            } elseif ($channel === 'messenger') {
                if ($company->isMessengerConnected()) {
                    $recipientId = (string) preg_replace('/^fb:/', '', $customerPhone);
                    $res = $this->messengerService->sendDirectMessage($company, $recipientId, $formatted);
                    $sent = (bool) ($res['success'] ?? false);
                }
            } else {
                $explicitChatId = $conversation?->context_data['whatsapp_chat_id'] ?? null;
                $sent = $this->sendWhatsApp(
                    $company,
                    $customerPhone,
                    $formatted,
                    is_string($explicitChatId) ? $explicitChatId : null
                );
            }
        } catch (\Throwable $e) {
            Log::warning("ChannelMessageService sendToCustomer failed: " . $e->getMessage());
            $sent = false;
        }

        if ($conversation) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender' => 'bot',
                'message' => $formatted,
                'metadata' => [
                    'engine' => 'booking_notification',
                    'delivered' => $sent,
                ],
            ]);

            $conversation->update([
                'last_interaction_at' => Carbon::now(),
            ]);
        }

        return $sent;
    }

    /**
     * Send an optional WhatsApp alert to the company's reception/support phone.
     */
    public function notifyReception(Company $company, string $message): bool
    {
        if (empty($company->settings['booking_notify_reception_whatsapp'])) {
            return false;
        }

        $supportPhone = trim((string) ($company->support_phone ?? ''));
        if ($supportPhone === '') {
            return false;
        }

        $formatted = preg_replace('/\*\*(.+?)\*\*/u', '*$1*', $message) ?? $message;

        try {
            return $this->sendWhatsApp($company, $supportPhone, $formatted);
        } catch (\Throwable $e) {
            Log::warning("ChannelMessageService notifyReception failed: " . $e->getMessage());
            return false;
        }
    }

    protected function sendWhatsApp(Company $company, string $phone, string $message, ?string $explicitChatId = null): bool
    {
        if ($company->whatsapp_provider === 'meta_cloud' && $company->isMetaConnected()) {
            $res = $this->metaWhatsAppService->sendText($company, $phone, $message);
            return (bool) ($res['success'] ?? false);
        }

        if (!empty($explicitChatId) && str_contains($explicitChatId, '@')) {
            return $this->wahaService->sendText($explicitChatId, $message, $company->getWahaSessionName());
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($digits === '') {
            return false;
        }

        return $this->wahaService->sendText($digits . '@c.us', $message, $company->getWahaSessionName());
    }
}
