<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\SystemNotification;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalCompanies = Company::count();
        $pendingCompanies = Company::where('status', 'pending')->count();
        $activeCompanies = Company::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('subscription_expires_at')
                    ->orWhere('subscription_expires_at', '>', now());
            })
            ->count();
        $inactiveOrExpired = max(0, $totalCompanies - $activeCompanies - $pendingCompanies);

        $waConnected = Company::where(function ($q) {
            $q->where('whatsapp_session_status', 'connected')
                ->orWhere(function ($q2) {
                    $q2->where('whatsapp_provider', 'meta_cloud')
                        ->whereNotNull('meta_phone_number_id')
                        ->whereNotNull('meta_access_token');
                });
        })->count();

        $igConnected = Company::where('instagram_status', 'connected')
            ->whereNotNull('instagram_account_id')
            ->whereNotNull('instagram_access_token')
            ->count();

        $totalConversations = Conversation::count();
        $todayConversations = Conversation::whereDate('last_interaction_at', today())->count();

        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $chartData[] = Conversation::whereDate('created_at', now()->subDays($i)->toDateString())->count();
        }

        $unreadNotifs = SystemNotification::where('is_read', false)->count();

        return [
            Stat::make('الشركات المسجلة', $totalCompanies)
                ->description($pendingCompanies > 0
                    ? "{$activeCompanies} نشطة · {$pendingCompanies} بانتظار التفعيل"
                    : "{$activeCompanies} نشطة · {$inactiveOrExpired} متوقفة")
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary')
                ->url('/admin/companies'),

            Stat::make('القنوات المتصلة', $waConnected)
                ->description("انستقرام متصل: {$igConnected} شركة")
                ->descriptionIcon('heroicon-m-signal')
                ->color('success'),

            Stat::make('المحادثات', $totalConversations)
                ->description("{$todayConversations} محادثة اليوم")
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info')
                ->chart($chartData)
                ->url('/admin/conversations'),

            Stat::make('التنبيهات غير المقروءة', $unreadNotifs)
                ->description($unreadNotifs > 0 ? 'تحتاج مراجعة' : 'كل شيء مستقر')
                ->descriptionIcon($unreadNotifs > 0 ? 'heroicon-m-bell-alert' : 'heroicon-m-check-badge')
                ->color($unreadNotifs > 0 ? 'warning' : 'success')
                ->url('/admin/system-notifications'),
        ];
    }
}
