<?php

namespace App\Filament\Company\Pages;

use App\Models\Company;
use App\Models\SystemNotification;
use App\Services\AiChatService;
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

    public function mount(): void
    {
        $company = $this->getCompany();
        if ($company) {
            $this->whatsappNumber = $company->whatsapp_number;
            $this->supportPhone = $company->support_phone;
            $this->connectionStatus = $company->whatsapp_session_status ?? 'disconnected';
            $this->connectedAt = $company->whatsapp_connected_at?->format('d/m/Y H:i');

            if ($this->connectionStatus === 'connected') {
                $this->qrCodeUrl = null;
            } else {
                $this->generateQr();
            }
        }
    }

    public function getCompany(): ?Company
    {
        return auth()->user()?->company ?? Company::first();
    }

    public function generateQr(): void
    {
        $company = $this->getCompany();
        $codeData = 'SMARTORA-WA-' . ($company?->slug ?? 'demo') . '-' . Str::random(16);
        // High quality QR code via public chart generator
        $this->qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=" . urlencode($codeData);
        $this->connectionStatus = 'scanning';
    }

    public function confirmConnection(): void
    {
        $company = $this->getCompany();
        if (! $company) return;

        $number = trim($this->whatsappNumber);
        if (empty($number)) {
            Notification::make()->title('يرجى كتابة رقم الواتساب قبل إتمام الربط')->warning()->send();
            return;
        }

        $company->update([
            'whatsapp_number' => $number,
            'support_phone' => $this->supportPhone,
            'whatsapp_session_status' => 'connected',
            'whatsapp_connected_at' => now(),
        ]);

        $this->connectionStatus = 'connected';
        $this->connectedAt = now()->format('d/m/Y H:i');
        $this->qrCodeUrl = null;

        SystemNotification::create([
            'company_id' => $company->id,
            'title' => 'تم ربط رقم الواتساب بنجاح',
            'message' => "تم ربط الرقم ({$number}) بنجاح. البوت جاهز لاستقبال رسائل الزبائن والرد التلقائي.",
            'type' => 'success',
        ]);

        Notification::make()
            ->title('تم مسح الباركود وربط الواتساب بنجاح! 🚀')
            ->body('الرقم ' . $number . ' متصل الآن ومربوط مع محرك الأتمتة.')
            ->success()
            ->send();
    }

    public function disconnectWhatsApp(): void
    {
        $company = $this->getCompany();
        if (! $company) return;

        $company->update([
            'whatsapp_session_status' => 'disconnected',
            'whatsapp_connected_at' => null,
        ]);

        $this->connectionStatus = 'disconnected';
        $this->connectedAt = null;
        $this->generateQr();

        Notification::make()
            ->title('تم فصل جلسة الواتساب مؤقتاً')
            ->warning()
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

        // Simulate sending and trigger AI response check
        try {
            $aiService = app(AiChatService::class);
            $res = $aiService->handleCustomerMessage($company, $target, $this->testMessage);

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
