<?php

namespace App\Filament\Company\Pages;

use App\Models\Company;
use App\Services\AiChatService;
use App\Services\InstagramService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class InstagramConnection extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static string $view = 'filament.company.pages.instagram-connection';

    protected static ?string $title = 'ربط حساب انستقرام والرد الآلي (Instagram DM & Comments)';
    protected static ?string $navigationLabel = 'ربط انستقرام (Instagram DM)';
    protected static ?int $navigationSort = 4;

    public ?string $instagramAccountId = '';
    public ?string $instagramUsername = '';
    public ?string $instagramAccessToken = '';
    public ?string $instagramVerifyToken = '';
    public string $connectionStatus = 'disconnected';
    public ?string $connectedAt = null;
    public ?int $followersCount = null;

    // Comment-to-DM automation settings
    public bool $commentReplyEnabled = false;
    public ?string $commentKeywords = '';
    public ?string $commentPublicReply = 'أهلاً بك! تم إرسال كافة التفاصيل على الخاص 📩';

    // Live test simulator
    public string $testMessage = 'ما هي العيادات والخدمات المتوفرة لديكم؟';
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

        $igService = app(InstagramService::class);
        $this->isOAuthConfigured = $igService->isOAuthConfigured();
        $this->oauthUrl = $this->isOAuthConfigured ? $igService->buildOAuthUrl($company) : null;

        $this->instagramAccountId = $company->instagram_account_id;
        $this->instagramUsername = $company->instagram_username;
        $this->instagramAccessToken = $company->instagram_access_token;
        $this->instagramVerifyToken = $company->getEffectiveInstagramVerifyToken();

        $this->commentReplyEnabled = (bool) $company->instagram_comment_reply_enabled;
        $this->commentKeywords = $company->instagram_comment_keywords;
        $this->commentPublicReply = $company->instagram_comment_public_reply ?: 'أهلاً بك! تم إرسال كافة التفاصيل على الخاص 📩';

        if ($company->isInstagramConnected()) {
            $this->connectionStatus = 'connected';
            $this->connectedAt = $company->instagram_connected_at?->format('d/m/Y H:i') ?? 'متصل';
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

        $accountId = trim((string) $this->instagramAccountId);
        $token = trim((string) $this->instagramAccessToken);
        $verifyToken = trim((string) $this->instagramVerifyToken);

        $company->update([
            'instagram_account_id' => $accountId ?: null,
            'instagram_username' => trim((string) $this->instagramUsername, "@ \t\n\r") ?: null,
            'instagram_access_token' => $token ?: null,
            'instagram_verify_token' => $verifyToken ?: null,
        ]);

        if ($token !== '') {
            $this->testConnection();
            return;
        }

        $this->refreshStatus();
        Notification::make()
            ->title('تم حفظ بيانات انستقرام')
            ->success()
            ->send();
    }

    public function testConnection(): void
    {
        $company = $this->getCompany();
        if (!$company) {
            return;
        }

        $accountId = trim((string) $this->instagramAccountId);
        $token = trim((string) $this->instagramAccessToken);

        if ($token === '') {
            Notification::make()
                ->title('يرجى إدخال رمز الوصول (Access Token) أولاً')
                ->warning()
                ->send();
            return;
        }

        $igService = app(InstagramService::class);
        $result = $igService->verifyConnection($accountId, $token);

        if (!empty($result['success'])) {
            $data = $result['data'] ?? [];
            $resolvedId = (string) ($data['user_id'] ?? $data['id'] ?? $accountId);
            $resolvedUsername = $data['username'] ?? $this->instagramUsername;
            $this->followersCount = isset($data['followers_count']) ? (int) $data['followers_count'] : null;

            $company->update([
                'instagram_account_id' => $resolvedId ?: null,
                'instagram_username' => $resolvedUsername ? ltrim((string) $resolvedUsername, '@') : null,
                'instagram_access_token' => $token,
                'instagram_verify_token' => trim((string) $this->instagramVerifyToken) ?: null,
                'instagram_status' => 'connected',
                'instagram_connected_at' => $company->instagram_connected_at ?? now(),
            ]);

            $this->refreshStatus();

            Notification::make()
                ->title('تم ربط حساب انستقرام رسمياً بنجاح! 📸')
                ->body('الحساب: @' . ($resolvedUsername ?: $resolvedId))
                ->success()
                ->send();
        } else {
            $company->update(['instagram_status' => 'disconnected']);
            $this->refreshStatus();

            Notification::make()
                ->title('تعذر التحقق من حساب انستقرام')
                ->body($result['error'] ?? 'يرجى التأكد من صحة Access Token و Instagram Account ID.')
                ->danger()
                ->send();
        }
    }

    public function disconnectInstagram(): void
    {
        $company = $this->getCompany();
        if (!$company) {
            return;
        }

        $company->update([
            'instagram_account_id' => null,
            'instagram_username' => null,
            'instagram_access_token' => null,
            'instagram_status' => 'disconnected',
            'instagram_connected_at' => null,
        ]);

        $this->followersCount = null;
        $this->refreshStatus();

        Notification::make()
            ->title('تم فصل حساب انستقرام بنجاح')
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
            'instagram_comment_reply_enabled' => $this->commentReplyEnabled,
            'instagram_comment_keywords' => trim((string) $this->commentKeywords) ?: null,
            'instagram_comment_public_reply' => trim((string) $this->commentPublicReply) ?: null,
        ]);

        $this->refreshStatus();

        Notification::make()
            ->title('تم حفظ إعدادات الرد على التعليقات بنجاح')
            ->success()
            ->send();
    }

    public function simulateInstagramReply(): void
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
                'ig:simulator_preview',
                $msg,
                'instagram',
                '@test_visitor'
            );
            $this->testReplyPreview = $res['reply'] ?? '';

            Notification::make()
                ->title('تمت محاكاة رد انستقرام الذكي بنجاح!')
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
