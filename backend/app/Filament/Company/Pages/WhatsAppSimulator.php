<?php

namespace App\Filament\Company\Pages;

use App\Models\Company;
use App\Models\Conversation;
use App\Services\AiChatService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WhatsAppSimulator extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static string $view = 'filament.company.pages.whatsapp-simulator';

    protected static ?string $title = 'محاكي الرد الذكي';
    protected static ?string $navigationLabel = 'محاكي البوت';
    protected static ?int $navigationSort = 6;

    public string $customerPhone = '96777000888';
    public string $inputMessage = '';
    public array $chatHistory = [];

    public function mount(): void
    {
        $this->resetChat();
    }

    public function getCompany(): ?Company
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        if ($user->isCompanyAdmin()) {
            return $user->company;
        }

        return $user->company ?? Company::first();
    }

    public function resetChat(): void
    {
        $this->chatHistory = [];
        $company = $this->getCompany();
        if ($company) {
            $conversation = Conversation::where('company_id', $company->id)
                ->where('customer_phone', $this->customerPhone)
                ->first();

            if ($conversation) {
                $conversation->messages()->delete();
                $conversation->resetContext();
                $conversation->update([
                    'last_message' => null,
                    'last_interaction_at' => null,
                ]);
            }

            $greeting = $company->settings['greeting_message']
                ?? "مرحباً بك في {$company->name}! كيف يمكننا خدمتك اليوم؟";

            $this->chatHistory[] = [
                'sender' => 'bot',
                'message' => $greeting,
                'time' => now()->format('H:i'),
            ];
        }
    }

    public function sendMessage(): void
    {
        $text = trim($this->inputMessage);
        if (empty($text)) {
            return;
        }

        $company = $this->getCompany();
        if (! $company) {
            Notification::make()->title('لا توجد منشأة مرتبطة بحسابك')->danger()->send();
            return;
        }

        // Add user message
        $this->chatHistory[] = [
            'sender' => 'user',
            'message' => $text,
            'time' => now()->format('H:i'),
        ];
        $this->inputMessage = '';

        // Call AI Service
        try {
            $aiService = app(AiChatService::class);
            $res = $aiService->handleCustomerMessage($company, $this->customerPhone, $text);

            $this->chatHistory[] = [
                'sender' => 'bot',
                'message' => $res['reply'] ?? 'لم يتم استلام رد من المحرك.',
                'time' => now()->format('H:i'),
            ];
        } catch (\Throwable $e) {
            $this->chatHistory[] = [
                'sender' => 'bot',
                'message' => 'حدث خطأ: ' . $e->getMessage(),
                'time' => now()->format('H:i'),
            ];
        }
    }
}
