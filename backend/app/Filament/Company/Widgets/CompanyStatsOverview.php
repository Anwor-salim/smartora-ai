<?php

namespace App\Filament\Company\Widgets;

use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CompanyStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $company = $user->isCompanyAdmin() ? $user->company : ($user->company ?? \App\Models\Company::first());
        if (! $company) {
            return [];
        }

        $isActive = $company->isSubscriptionActive();
        $daysLeft = $company->subscription_expires_at ? max(0, (int) now()->diffInDays($company->subscription_expires_at, false)) : 0;
        $docsCount = KnowledgeDocument::where('company_id', $company->id)->count();
        $convsCount = Conversation::where('company_id', $company->id)->count();

        return [
            Stat::make('حالة الاشتراك', $isActive ? 'نشط ومفعل' : 'متوقف / منتهي')
                ->description($isActive ? "متبقي {$daysLeft} يوماً" : 'يرجى مراجعة إدارة المنصة للتجديد')
                ->descriptionIcon($isActive ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                ->color($isActive ? 'success' : 'danger'),

            Stat::make('رقم واتساب البوت', $company->whatsapp_number ?? 'غير محدد')
                ->description('الرقم الذي يستقبل رسائل عملائك')
                ->descriptionIcon('heroicon-m-chat-bubble-oval-left-ellipsis')
                ->color('info'),

            Stat::make('ملفات المعرفة والأسعار', $docsCount)
                ->description('ملفات إكسل و PDF معتمدة للرد')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make('إجمالي محادثات الزبائن', $convsCount)
                ->description('زبائن تواصلوا مع البوت الذكي')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('warning'),
        ];
    }
}
