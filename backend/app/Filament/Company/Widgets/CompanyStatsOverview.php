<?php

namespace App\Filament\Company\Widgets;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CompanyStatsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $company = $user->isCompanyAdmin() ? $user->company : ($user->company ?? Company::first());
        if (! $company) {
            return [];
        }

        $isActive = $company->isSubscriptionActive();
        $daysLeft = $company->subscription_expires_at
            ? max(0, (int) now()->diffInDays($company->subscription_expires_at, false))
            : null;
        $planLabel = match ($company->subscription_plan) {
            'monthly_pro' => 'الباقة الشهرية',
            '3_months' => 'باقة 3 أشهر',
            '6_months' => 'باقة 6 أشهر',
            'yearly' => 'الباقة السنوية',
            'enterprise' => 'باقة الشركات',
            default => 'الاشتراك',
        };

        $waConnected = $company->isWhatsAppConnected();
        $igConnected = $company->isInstagramConnected();
        $channelsCount = ($waConnected ? 1 : 0) + ($igConnected ? 1 : 0);
        $channelsDesc = 'واتساب: ' . ($waConnected ? 'متصل' : 'غير مربوط') . ' · انستقرام: ' . ($igConnected ? 'متصل' : 'غير مربوط');

        $readyDocsCount = KnowledgeDocument::where('company_id', $company->id)->where('status', 'ready')->count();
        $totalItems = (int) KnowledgeDocument::where('company_id', $company->id)->where('status', 'ready')->sum('total_items');

        $convsCount = Conversation::where('company_id', $company->id)->count();
        $todayConvs = Conversation::where('company_id', $company->id)
            ->whereDate('last_interaction_at', today())
            ->count();

        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $chartData[] = Conversation::where('company_id', $company->id)
                ->whereDate('created_at', now()->subDays($i)->toDateString())
                ->count();
        }

        return [
            Stat::make('حالة الاشتراك', $isActive ? 'نشط' : 'متوقف / منتهي')
                ->description($isActive
                    ? ($daysLeft !== null ? "{$planLabel} · متبقي {$daysLeft} يوم" : "{$planLabel} · مفتوح")
                    : 'يرجى التواصل مع الإدارة للتجديد')
                ->descriptionIcon($isActive ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                ->color($isActive ? 'success' : 'danger'),

            Stat::make('القنوات المتصلة', "{$channelsCount} / 2")
                ->description($channelsDesc)
                ->descriptionIcon('heroicon-m-signal')
                ->color($channelsCount === 2 ? 'success' : ($channelsCount === 1 ? 'info' : 'warning'))
                ->url('/company/whats-app-connection'),

            Stat::make('قاعدة المعرفة', "{$readyDocsCount} ملف")
                ->description("{$totalItems} صنف ومعلومة مستخرجة")
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->url('/company/knowledge-documents'),

            Stat::make('محادثات العملاء', $convsCount)
                ->description("{$todayConvs} محادثة اليوم")
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info')
                ->chart($chartData)
                ->url('/company/conversations'),
        ];
    }
}
