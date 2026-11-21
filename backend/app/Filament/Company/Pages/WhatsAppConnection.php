<?php

namespace App\Filament\Company\Pages;

use App\Models\Company;
use App\Models\SystemNotification;
use App\Services\AiChatService;
use App\Services\WahaWhatsAppService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class WhatsAppConnection extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static string $view = 'filament.company.pages.whats-app-connection';

    protected static ?string $title = 'ربط الواتساب وأتمتة الردود (WhatsApp QR Connection)';
    protected static ?string $navigationLabel = 'ربط الواتساب والباركود (QR)';
    protected static ?int $navigationSort = 3;

    public ?string $whatsappNumber = '';
    public ?string $supportPhone = '';
    public string $connectionStatus = 'disconnected';
    public ?string $qrCodeUrl = null;
    public bool $isGeneratingQr = false;
    public ?string $testPhoneNumber = '';
    public ?string $testMessage = 'مرحباً، هذه رسالة تجريبية من نظام سمارتورا للتأكد من اكتمال الربط بنجاح!';
    public ?string $connectedAt = null;
    public ?string $pushName = null;

    public function mount(WahaWhatsAppService $waha): void
    {
        $this->refreshStatus($waha);
    }

    public function getCompany(): ?Company
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        if ($user->isCompanyAdmin() && $user->company) {
            return $user->company;
        }

        return $user->company ?? Company::latest()->first();
    }

    public function refreshStatus(?WahaWhatsAppService $waha = null): void
    {
        $waha = $waha ?? app(WahaWhatsAppService::class);
        $company = $this->getCompany();
        if (! $company) {
            return;
        }

        $this->supportPhone = $company->support_phone;

        // Check live session with WAHA engine
        if ($waha->isWorking()) {
            $connectedPhone = $waha->getConnectedPhone() ?? $company->whatsapp_number;
            $this->pushName = $waha->getPushName();
            $this->whatsappNumber = $connectedPhone;
            $this->connectionStatus = 'connected';
            $this->connectedAt = $company->whatsapp_connected_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
            $this->qrCodeUrl = null;

            // Sync with company model if needed
            if ($company->whatsapp_session_status !== 'connected' || $company->whatsapp_number !== $connectedPhone) {
                $company->update([
                    'whatsapp_session_status' => 'connected',
                    'whatsapp_number' => $connectedPhone,
                    'whatsapp_connected_at' => $company->whatsapp_connected_at ?? now(),
                ]);
            }
        } else {
            $this->connectionStatus = 'disconnected';
            $this->whatsappNumber = $company->whatsapp_number;
            $this->connectedAt = null;
            $this->pushName = null;

            if ($company->whatsapp_session_status !== 'disconnected') {
                $company->update(['whatsapp_session_status' => 'disconnected']);
            }

            // Fetch real QR code
            $this->loadQr($waha);
        }
    }

    public function loadQr(?WahaWhatsAppService $waha = null): void
    {
        $waha = $waha ?? app(WahaWhatsAppService::class);
        $qr = $waha->getQrCode();

        if ($qr) {
            $this->qrCodeUrl = $qr;
            $this->connectionStatus = 'scanning';
        } else {
            $this->qrCodeUrl = null;
        }
    }

    public function generateQr(): void
    {
        $waha = app(WahaWhatsAppService::class);
        $waha->restartSession();
        sleep(1);
        $this->loadQr($waha);

        Notification::make()
            ->title('تم تحديث وتوليد رمز QR جديد')
            ->info()
            ->send();
    }

    public function checkConnection(): void
    {
        $waha = app(WahaWhatsAppService::class);
        $this->refreshStatus($waha);

        if ($this->connectionStatus === 'connected') {
            Notification::make()
                ->title('الواتساب متصل بنجاح! 🚀')
                ->body('الرقم ' . $this->whatsappNumber . ' متصل ويعمل حالياً.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('لم يكتمل مسح الباركود بعد')
                ->body('يرجى توجيه كاميرا تطبيق واتساب من هاتفك لمسح الرمز الظاهر.')
                ->warning()
                ->send();
        }
    }

    public function disconnectWhatsApp(): void
    {
        $company = $this->getCompany();
        $waha = app(WahaWhatsAppService::class);
        $waha->logoutSession();

        if ($company) {
            $company->update([
                'whatsapp_session_status' => 'disconnected',
                'whatsapp_connected_at' => null,
            ]);
        }

        $this->connectionStatus = 'disconnected';
        $this->connectedAt = null;
        $this->pushName = null;

        sleep(1);
        $this->loadQr($waha);

        Notification::make()
            ->title('تم تسجيل الخروج وفصل الجلسة')
            ->body('يمكنك الآن مسح الرمز الجديد برقم آخر.')
            ->warning()
            ->send();
    }

    public function saveNumbers(): void
    {
        $company = $this->getCompany();
        if (! $company) return;

        $number = trim($this->whatsappNumber);
        $company->update([
            'whatsapp_number' => $number,
            'support_phone' => trim($this->supportPhone),
        ]);

        Notification::make()
            ->title('تم حفظ وتحديث الأرقام بنجاح')
            ->success()
            ->send();
    }

    public function sendTestMessage(): void
    {
        $company = $this->getCompany();
        if (! $company) return;

        $target = trim($this->testPhoneNumber ?: $this->whatsappNumber);
        if (empty($target)) {
            Notification::make()->title('يرجى تحديد رقم هاتف لإرسال الرسالة التجريبية إليه')->warning()->send();
            return;
        }

        try {
            $aiService = app(AiChatService::class);
            $res = $aiService->handleCustomerMessage($company, $target, $this->testMessage);

            // Also try sending real message via WAHA if working
            $waha = app(WahaWhatsAppService::class);
            if ($waha->isWorking() && !empty($res['reply'])) {
                $waha->sendText($target, $res['reply']);
            }

            Notification::make()
                ->title('تم إرسال الرسالة ومعالجتها بالأتمتة بنجاح!')
                ->body('رد البوت الذكي: ' . Str::limit($res['reply'] ?? '', 100))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('حدث خطأ أثناء إرسال الرسالة: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
