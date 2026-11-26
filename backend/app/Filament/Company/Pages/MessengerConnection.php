<?php

namespace App\Filament\Company\Pages;

use App\Models\Company;
use App\Services\AiChatService;
use App\Services\MessengerService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MessengerConnection extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-oval-left-ellipsis';

    protected static string $view = 'filament.company.pages.messenger-connection';

    protected static ?string $title = 'ربط ماسنجر فيسبوك';
    protected static ?string $navigationLabel = 'ربط ماسنجر فيسبوك';
    protected static ?int $navigationSort = 5;

    public ?string $messengerPageId = '';
    public ?string $messengerPageName = '';
    public ?string $messengerAccessToken = '';
    public ?string $messengerVerifyToken = '';
    public string $connectionStatus = 'disconnected';
    public ?string $connectedAt = null;
    public ?int $fanCount = null;

    // Comment-to-DM automation settings
    public bool $commentReplyEnabled = false;
    public ?string $commentKeywords = '';
    public ?string $commentPublicReply = 'أهلاً بك! تم إرسال كافة التفاصيل على ماسنجر 📩';

    // Live test simulator
    public string $testMessage = 'ما هي المنتجات والأسعار المتوفرة لديكم؟';
    public ?string $testReplyPreview = null;

    // 1-click OAuth availability
    public bool $isOAuthConfigured = false;
    public ?string $oauthUrl = null;

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function getCompany(): ?Company
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        if ($user->isCompanyAdmin() && $user->company) {
            return $user->company;
        }

        return $user->company ?? Company::latest()->first();
    }

    public function refreshStatus(): void
    {
        $company = $this->getCompany();
        if (!$company) {
            return;
        }

        $messengerService = app(MessengerService::class);
        $this->isOAuthConfigured = $messengerService->isOAuthConfigured();
        $this->oauthUrl = $this->isOAuthConfigured ? $messengerService->buildOAuthUrl($company) : null;

        $this->messengerPageId = $company->messenger_page_id;
        $this->messengerPageName = $company->messenger_page_name;
        $this->messengerAccessToken = $company->messenger_access_token;
        $this->messengerVerifyToken = $company->getEffectiveMessengerVerifyToken();

        $this->commentReplyEnabled = (bool) $company->messenger_comment_reply_enabled;
        $this->commentKeywords = $company->messenger_comment_keywords;
        $this->commentPublicReply = $company->messenger_comment_public_reply ?: 'أهلاً بك! تم إرسال كافة التفاصيل على ماسنجر 📩';

        if ($company->isMessengerConnected()) {
            $this->connectionStatus = 'connected';
            $this->connectedAt = $company->messenger_connected_at?->format('d/m/Y H:i') ?? 'متصل';
        } else {
            $this->connectionStatus = 'disconnected';
            $this->connectedAt = null;
        }
    }

    public function saveCredentials(): void
    {
        $company = $this->getCompany();
        if (!$company) {
            return;
        }

        $pageId = trim((string) $this->messengerPageId);
        $token = trim((string) $this->messengerAccessToken);
        $verifyToken = trim((string) $this->messengerVerifyToken);

        $company->update([
            'messenger_page_id' => $pageId ?: null,
            'messenger_page_name' => trim((string) $this->messengerPageName) ?: null,
            'messenger_access_token' => $token ?: null,
            'messenger_verify_token' => $verifyToken ?: null,
        ]);

        if ($token !== '') {
            $this->testConnection();
            return;
        }

        $this->refreshStatus();
        Notification::make()
            ->title('تم حفظ بيانات ماسنجر فيسبوك')
            ->success()
            ->send();
    }

    public function testConnection(): void
    {
        $company = $this->getCompany();
        if (!$company) {
            return;
        }

        $pageId = trim((string) $this->messengerPageId);
        $token = trim((string) $this->messengerAccessToken);

        if ($token === '') {
            Notification::make()
                ->title('يرجى إدخال Page Access Token أولاً')
                ->warning()
                ->send();
            return;
        }

        $messengerService = app(MessengerService::class);
        $result = $messengerService->verifyConnection($pageId, $token);

        if (!empty($result['success'])) {
            $data = $result['data'] ?? [];
            $resolvedId = (string) ($data['id'] ?? $pageId);
            $resolvedName = $data['name'] ?? $this->messengerPageName;
            $this->fanCount = isset($data['fan_count']) ? (int) $data['fan_count'] : null;

            $company->update([
                'messenger_page_id' => $resolvedId ?: null,
                'messenger_page_name' => $resolvedName ?: null,
                'messenger_access_token' => $token,
                'messenger_verify_token' => trim((string) $this->messengerVerifyToken) ?: null,
                'messenger_status' => 'connected',
                'messenger_connected_at' => $company->messenger_connected_at ?? now(),
            ]);

            $this->refreshStatus();

            Notification::make()
                ->title('تم ربط صفحة ماسنجر فيسبوك رسمياً بنجاح! 💬')
                ->body('الصفحة: ' . ($resolvedName ?: $resolvedId))
                ->success()
                ->send();
        } else {
            $company->update(['messenger_status' => 'disconnected']);
            $this->refreshStatus();

            Notification::make()
                ->title('تعذر التحقق من صفحة فيسبوك')
                ->body($result['error'] ?? 'يرجى التأكد من صحة Page Access Token و Page ID.')
                ->danger()
                ->send();
        }
    }

    public function disconnectMessenger(): void
    {
        $company = $this->getCompany();
        if (!$company) {
            return;
        }

        $company->update([
            'messenger_page_id' => null,
            'messenger_page_name' => null,
            'messenger_access_token' => null,
            'messenger_status' => 'disconnected',
            'messenger_connected_at' => null,
        ]);

        $this->fanCount = null;
        $this->refreshStatus();

        Notification::make()
            ->title('تم فصل صفحة ماسنجر فيسبوك بنجاح')
            ->warning()
            ->send();
    }

    public function saveCommentAutomation(): void
    {
        $company = $this->getCompany();
        if (!$company) {
            return;
        }

        $company->update([
            'messenger_comment_reply_enabled' => $this->commentReplyEnabled,
            'messenger_comment_keywords' => trim((string) $this->commentKeywords) ?: null,
            'messenger_comment_public_reply' => trim((string) $this->commentPublicReply) ?: null,
        ]);

        $this->refreshStatus();

        Notification::make()
            ->title('تم حفظ إعدادات الرد على تعليقات فيسبوك بنجاح')
            ->success()
            ->send();
    }

    public function simulateMessengerReply(): void
    {
        $company = $this->getCompany();
        if (!$company) {
            return;
        }

        $msg = trim($this->testMessage);
        if ($msg === '') {
            Notification::make()->title('يرجى كتابة رسالة تجريبية أولاً')->warning()->send();
            return;
        }

        try {
            $aiService = app(AiChatService::class);
            $res = $aiService->handleCustomerMessage(
                $company,
                'fb:simulator_preview',
                $msg,
                'messenger',
                'عميل ماسنجر تجريبي'
            );
            $this->testReplyPreview = $res['reply'] ?? '';

            Notification::make()
                ->title('تمت محاكاة رد ماسنجر الذكي بنجاح!')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('حدث خطأ أثناء المحاكاة: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
