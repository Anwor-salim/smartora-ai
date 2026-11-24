<?php

namespace App\Filament\Company\Pages;

use App\Models\Company;
use App\Models\SystemNotification;
use App\Services\AiChatService;
use App\Services\MetaWhatsAppService;
use App\Services\WahaWhatsAppService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class WhatsAppConnection extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static string $view = 'filament.company.pages.whats-app-connection';

    protected static ?string $title = 'ربط الواتساب وأتمتة الردود (WhatsApp Connection)';
    protected static ?string $navigationLabel = 'ربط الواتساب والباركود (QR & API)';
    protected static ?int $navigationSort = 3;

    public string $whatsappProvider = 'waha'; // 'waha' or 'meta_cloud'
    public ?string $whatsappNumber = '';
    public ?string $supportPhone = '';
    public string $connectionStatus = 'disconnected';
    public ?string $qrCodeUrl = null;
    public bool $isGeneratingQr = false;
    public ?string $testPhoneNumber = '';
    public ?string $testMessage = 'مرحباً، هذه رسالة تجريبية من نظام سمارتورا للتأكد من اكتمال الربط بنجاح!';
    public ?string $connectedAt = null;
    public ?string $pushName = null;

    // Meta Cloud API Properties
    public ?string $metaPhoneNumberId = '';
    public ?string $metaWabaId = '';
    public ?string $metaAccessToken = '';
    public ?string $metaVerifyToken = '';
    public ?string $metaVerifiedName = null;
    public ?string $metaQualityRating = null;

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

    public function switchProvider(string $provider): void
    {
        if (!in_array($provider, ['waha', 'meta_cloud'], true)) {
            return;
        }

        $company = $this->getCompany();
        if (!$company) {
            return;
        }

        $this->whatsappProvider = $provider;
        $company->update(['whatsapp_provider' => $provider]);
        $this->refreshStatus();

        Notification::make()
            ->title('تم تفعيل: ' . ($provider === 'meta_cloud' ? 'Meta Cloud API الرسمي' : 'ربط الباركود الفوري (QR)'))
            ->success()
            ->send();
    }

    public function refreshStatus(?WahaWhatsAppService $waha = null): void
    {
        $company = $this->getCompany();
        if (! $company) {
            return;
        }

        $this->whatsappProvider = $company->whatsapp_provider ?: 'waha';
        $this->supportPhone = $company->support_phone;
        $this->metaPhoneNumberId = $company->meta_phone_number_id;
        $this->metaWabaId = $company->meta_waba_id;
        $this->metaAccessToken = $company->meta_access_token;
        $this->metaVerifyToken = $company->getEffectiveVerifyToken();

        // 1. If Meta Cloud API is selected
        if ($this->whatsappProvider === 'meta_cloud') {
            if ($company->isMetaConnected()) {
                $this->connectionStatus = 'connected';
                $this->whatsappNumber = $company->whatsapp_number;
                $this->pushName = 'واتساب معتمد (Meta Cloud API)';
                $this->connectedAt = $company->whatsapp_connected_at?->format('d/m/Y H:i') ?? 'اتصال دائم';
                $this->qrCodeUrl = null;
            } else {
                $this->connectionStatus = 'disconnected';
                $this->qrCodeUrl = null;
            }
            return;
        }

        // 2. If WAHA (QR Code) is selected
        $waha = $waha ?? app(WahaWhatsAppService::class);
        $session = $company->getWahaSessionName();

        if ($waha->isWorking($session)) {
            $connectedPhone = $waha->getConnectedPhone($session) ?? $company->whatsapp_number;
            $this->pushName = $waha->getPushName($session);
            $this->whatsappNumber = $connectedPhone;
            $this->connectionStatus = 'connected';
            $this->connectedAt = $company->whatsapp_connected_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
            $this->qrCodeUrl = null;

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
                    \Illuminate\Support\Facades\Log::warning("Could not sync whatsapp_number for company {$company->id}: " . $e->getMessage());
                }
            }
        } else {
            $this->connectionStatus = 'disconnected';
            $this->whatsappNumber = $company->whatsapp_number;
            $this->connectedAt = null;
            $this->pushName = null;

            if ($company->whatsapp_session_status !== 'disconnected') {
                try {
                    $company->update(['whatsapp_session_status' => 'disconnected']);
                } catch (\Throwable $e) {
                    // Ignore safe error
                }
            }

            $this->loadQr($waha);
        }
    }

    public function loadQr(?WahaWhatsAppService $waha = null): void
    {
        $waha = $waha ?? app(WahaWhatsAppService::class);
        $company = $this->getCompany();
        if (! $company) {
            return;
        }

        $session = $company->getWahaSessionName();
        $qr = $waha->getQrCode($session);

        if ($qr) {
            $this->qrCodeUrl = $qr;
            $this->connectionStatus = 'scanning';
        } else {
            $this->qrCodeUrl = null;
        }
    }

    public function generateQr(): void
    {
        $company = $this->getCompany();
        if (! $company) {
            return;
        }

        $session = $company->getWahaSessionName();
        $waha = app(WahaWhatsAppService::class);
        $waha->restartSession($session);
        sleep(1);
        $this->loadQr($waha);

        Notification::make()
            ->title('تم تحديث وتوليد رمز QR جديد')
            ->info()
            ->send();
    }

    public function checkConnection(): void
    {
        if ($this->whatsappProvider === 'meta_cloud') {
            $this->testMetaConnection();
            return;
        }

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
        if (! $company) {
            return;
        }

        if ($this->whatsappProvider === 'meta_cloud') {
            $this->disconnectMeta();
            return;
        }

        $session = $company->getWahaSessionName();
        $waha = app(WahaWhatsAppService::class);
        $waha->logoutSession($session);

        try {
            $company->update([
                'whatsapp_session_status' => 'disconnected',
                'whatsapp_connected_at' => null,
            ]);
        } catch (\Throwable $e) {
            // Ignore safe update error
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

    public function saveMetaCredentials(): void
    {
        $company = $this->getCompany();
        if (!$company) {
            return;
        }

        $phoneId = trim($this->metaPhoneNumberId);
        $token = trim($this->metaAccessToken);

        $company->update([
            'whatsapp_provider' => 'meta_cloud',
            'meta_phone_number_id' => $phoneId ?: null,
            'meta_waba_id' => trim($this->metaWabaId) ?: null,
            'meta_access_token' => $token ?: null,
            'meta_verify_token' => trim($this->metaVerifyToken) ?: null,
        ]);

        Notification::make()
            ->title('تم حفظ بيانات Meta Cloud API بنجاح')
            ->success()
            ->send();

        if (!empty($phoneId) && !empty($token)) {
            $this->testMetaConnection();
        } else {
            $this->refreshStatus();
        }
    }

    public function testMetaConnection(): void
    {
        $company = $this->getCompany();
        if (!$company) {
            return;
        }

        $metaService = app(MetaWhatsAppService::class);
        $phoneId = trim($this->metaPhoneNumberId);
        $token = trim($this->metaAccessToken);

        if (empty($phoneId) || empty($token)) {
            Notification::make()
                ->title('يرجى إدخال Phone Number ID و Access Token أولاً')
                ->warning()
                ->send();
            return;
        }

        $result = $metaService->verifyConnection($phoneId, $token);

        if ($result['success']) {
            $data = $result['data'] ?? [];
            $displayPhone = $data['display_phone_number'] ?? null;
            $verifiedName = $data['verified_name'] ?? null;

            $this->metaVerifiedName = $verifiedName;
            $this->metaQualityRating = $data['quality_rating'] ?? null;

            $updateData = [
                'whatsapp_provider' => 'meta_cloud',
                'whatsapp_session_status' => 'connected',
                'whatsapp_connected_at' => now(),
            ];

            if ($displayPhone) {
                $cleanPhone = preg_replace('/\D/', '', $displayPhone);
                $updateData['whatsapp_number'] = $cleanPhone;
            }

            $company->update($updateData);
            $this->refreshStatus();

            Notification::make()
                ->title('تم الربط الرسمي بنجاح! 🚀')
                ->body('الرقم المعتمد: ' . ($displayPhone ?? 'مربوط') . ' (' . ($verifiedName ?? 'معتمد') . ')')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('فشل فحص الاتصال مع Meta')
                ->body($result['error'] ?? 'يرجى مراجعة بيانات الاعتماد والتأكد من صحتها في Meta Developers.')
                ->danger()
                ->send();
        }
    }

    public function disconnectMeta(): void
    {
        $company = $this->getCompany();
        if (!$company) {
            return;
        }

        $company->update([
            'meta_phone_number_id' => null,
            'meta_access_token' => null,
            'meta_waba_id' => null,
            'whatsapp_session_status' => 'disconnected',
            'whatsapp_connected_at' => null,
        ]);

        $this->refreshStatus();

        Notification::make()
            ->title('تم فصل اتصال Meta Cloud API')
            ->warning()
            ->send();
    }

    public function saveNumbers(): void
    {
        $company = $this->getCompany();
        if (! $company) return;

        $number = trim($this->whatsappNumber);
        try {
            $company->update([
                'whatsapp_number' => $number ?: null,
                'support_phone' => trim($this->supportPhone) ?: null,
            ]);

            Notification::make()
                ->title('تم حفظ وتحديث الأرقام بنجاح')
                ->success()
                ->send();
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            Notification::make()
                ->title('رقم الواتساب هذا مسجل مسبقاً لشركة أخرى')
                ->body('يرجى التأكد من إدخال رقم واتساب خاص بشركتك.')
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('تعذر حفظ البيانات: ' . $e->getMessage())
                ->danger()
                ->send();
        }
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

            if ($this->whatsappProvider === 'meta_cloud') {
                $metaService = app(MetaWhatsAppService::class);
                if ($company->isMetaConnected() && !empty($res['reply'])) {
                    $metaService->sendText($company, $target, $res['reply']);
                }
            } else {
                $session = $company->getWahaSessionName();
                $waha = app(WahaWhatsAppService::class);
                if ($waha->isWorking($session) && !empty($res['reply'])) {
                    $waha->sendText($target, $res['reply'], $session);
                }
            }

            Notification::make()
                ->title('تم إرسال الرسالة ومعالجتها بنجاح!')
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
