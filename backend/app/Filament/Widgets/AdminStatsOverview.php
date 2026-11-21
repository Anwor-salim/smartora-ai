<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Conversation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalCompanies = Company::count();
        $activeCompanies = Company::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('subscription_expires_at')
                  ->orWhere('subscription_expires_at', '>', now());
            })
            ->count();

        $inactiveOrExpired = Company::where('status', '!=', 'active')
            ->orWhere('subscription_expires_at', '<=', now())
            ->count();

        $totalConversations = Conversation::count();

        return [
            Stat::make('إجمالي الشركات المسجلة', $totalCompanies)
                ->description('المنشآت المضافة للنظام')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary')
                ->chart([3, 5, 8, 12, 15, $totalCompanies]),

            Stat::make('الاشتراكات النشطة', $activeCompanies)
                ->description('شركات متصلة وتعمل بنجاح')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->chart([2, 4, 7, 10, 12, $activeCompanies]),

            Stat::make('المتوقفة أو المنتهية', $inactiveOrExpired)
                ->description('تحتاج تمديد أو تفعيل')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('إجمالي محادثات الواتساب', $totalConversations)
                ->description('محادثات تم الرد عليها بالذكاء الاصطناعي')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info')
                ->chart([10, 25, 45, 80, 120, $totalConversations]),
        ];
    }
}
