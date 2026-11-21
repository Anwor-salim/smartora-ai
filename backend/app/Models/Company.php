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
        'settings' => 'array',
    ];

    public function isWhatsAppConnected(): bool
    {
        return $this->whatsapp_session_status === 'connected';
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
