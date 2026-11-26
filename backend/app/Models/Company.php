<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'about_us',
        'services_offered',
        'phone',
        'whatsapp_number',
        'whatsapp_provider',
        'meta_phone_number_id',
        'meta_waba_id',
        'meta_access_token',
        'meta_verify_token',
        'meta_app_id',
        'instagram_account_id',
        'instagram_username',
        'instagram_access_token',
        'instagram_verify_token',
        'instagram_status',
        'instagram_connected_at',
        'instagram_comment_reply_enabled',
        'instagram_comment_keywords',
        'instagram_comment_public_reply',
        'messenger_page_id',
        'messenger_page_name',
        'messenger_access_token',
        'messenger_verify_token',
        'messenger_status',
        'messenger_connected_at',
        'messenger_comment_reply_enabled',
        'messenger_comment_keywords',
        'messenger_comment_public_reply',
        'support_phone',
        'email',
        'address',
        'google_maps_url',
        'working_hours',
        'custom_system_prompt',
        'status',
        'whatsapp_session_status',
        'whatsapp_qr_code',
        'whatsapp_connected_at',
        'subscription_plan',
        'subscription_starts_at',
        'subscription_expires_at',
        'settings',
    ];

    protected $casts = [
        'subscription_starts_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
        'whatsapp_connected_at' => 'datetime',
        'instagram_connected_at' => 'datetime',
        'instagram_comment_reply_enabled' => 'boolean',
        'messenger_connected_at' => 'datetime',
        'messenger_comment_reply_enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function isWhatsAppConnected(): bool
    {
        if ($this->whatsapp_provider === 'meta_cloud') {
            return $this->isMetaConnected();
        }

        return $this->whatsapp_session_status === 'connected';
    }

    public function isMetaConnected(): bool
    {
        return !empty($this->meta_phone_number_id) && !empty($this->meta_access_token);
    }

    public function getEffectiveVerifyToken(): string
    {
        return $this->meta_verify_token ?: ('smartora_meta_' . ($this->slug ?: $this->id));
    }

    public function isInstagramConnected(): bool
    {
        return $this->instagram_status === 'connected'
            && !empty($this->instagram_account_id)
            && !empty($this->instagram_access_token);
    }

    public function getEffectiveInstagramVerifyToken(): string
    {
        return $this->instagram_verify_token ?: ('smartora_ig_' . ($this->slug ?: $this->id));
    }

    public function isMessengerConnected(): bool
    {
        return $this->messenger_status === 'connected'
            && !empty($this->messenger_page_id)
            && !empty($this->messenger_access_token);
    }

    public function getEffectiveMessengerVerifyToken(): string
    {
        return $this->messenger_verify_token ?: ('smartora_fb_' . ($this->slug ?: $this->id));
    }

    public function getWahaSessionName(): string
    {
        if (!empty($this->settings['waha_session'])) {
            return trim((string) $this->settings['waha_session']);
        }

        if ($this->id === 4) {
            return 'default';
        }

        return 'company_' . $this->id;
    }


    public function getOnboardingProgress(): array
    {
        $hasProfile = !empty($this->about_us) || !empty($this->working_hours) || !empty($this->address);
        $hasProducts = $this->documents()->where('status', 'ready')->exists();
        $hasWhatsApp = !empty($this->whatsapp_number) && $this->isWhatsAppConnected();
        $hasTested = $this->conversations()->exists();

        $steps = [
            'profile' => [
                'title' => 'تعريف المنشأة والخدمات والموقع',
                'completed' => $hasProfile,
                'url' => '/company/company-settings',
            ],
            'products' => [
                'title' => 'رفع ملف المنتجات والأسعار',
                'completed' => $hasProducts,
                'url' => '/company/knowledge-documents',
            ],
            'whatsapp' => [
                'title' => 'ربط الواتساب ومسح الباركود',
                'completed' => $hasWhatsApp,
                'url' => '/company/whats-app-connection',
            ],
            'simulator' => [
                'title' => 'اختبار وتجربة المحاكي الذكي',
                'completed' => $hasTested,
                'url' => '/company/whats-app-simulator',
            ],
        ];

        $completedCount = collect($steps)->where('completed', true)->count();
        $percentage = (int) round(($completedCount / count($steps)) * 100);

        return [
            'steps' => $steps,
            'percentage' => $percentage,
            'is_fully_onboarded' => $completedCount === count($steps),
        ];
    }

    public function isSubscriptionActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->subscription_expires_at && $this->subscription_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(SystemNotification::class);
    }
}
