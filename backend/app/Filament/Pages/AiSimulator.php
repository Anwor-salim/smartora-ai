<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Services\AiChatService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AiSimulator extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static string $view = 'filament.pages.ai-simulator';

    protected static ?string $title = 'محاكي الواتساب الذكي (AI Simulator)';
    protected static ?string $navigationLabel = 'محاكي الواتساب';
    protected static ?int $navigationSort = 5;

    public ?int $selectedCompanyId = null;
    public string $customerPhone = '96777000999';
    public string $inputMessage = '';
    public array $chatHistory = [];
    public bool $isLoading = false;

    public function mount(): void
    {
        $firstCompany = Company::first();
        if ($firstCompany) {
            $this->selectedCompanyId = $firstCompany->id;
            $this->resetChat();
        }
    }

    public function updatedSelectedCompanyId(): void
    {
        $this->resetChat();
    }

    public function resetChat(): void
    {
        $this->chatHistory = [];
        $company = $this->getSelectedCompany();
        if ($company) {
            $greeting = $company->settings['greeting_message'] 
                ?? "مرحباً بك في {$company->name}! كيف يمكنني مساعدتك اليوم؟";
            
            $this->chatHistory[] = [
                'sender' => 'bot',
                'message' => $greeting,
                'time' => now()->format('H:i'),
            ];
        }
    }

    public function getSelectedCompany(): ?Company
    {
        return $this->selectedCompanyId ? Company::with('documents')->find($this->selectedCompanyId) : null;
    }

    public function sendMessage(): void
    {
        $text = trim($this->inputMessage);
        if (empty($text)) {
            return;
        }

        $company = $this->getSelectedCompany();
        if (! $company) {
            Notification::make()->title('يرجى اختيار شركة أولاً')->warning()->send();
            return;
        }

        // Add user message to UI
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
                'message' => $res['reply'] ?? 'لم يتم استلام رد من المحرك الذكي.',
                'time' => now()->format('H:i'),
            ];
        } catch (\Throwable $e) {
            $this->chatHistory[] = [
                'sender' => 'bot',
                'message' => 'عذراً، حدث خطأ أثناء معالجة الطلب: ' . $e->getMessage(),
                'time' => now()->format('H:i'),
            ];
        }
    }
}
