<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\Company;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('إضافة شركة جديدة')
                ->icon('heroicon-m-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->badge(Company::count()),

            'pending' => Tab::make('بانتظار التفعيل')
                ->badge(Company::where('status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),

            'active' => Tab::make('النشطة')
                ->badge(Company::where('status', 'active')
                    ->where(function (Builder $q) {
                        $q->whereNull('subscription_expires_at')
                            ->orWhere('subscription_expires_at', '>', now());
                    })->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', 'active')
                    ->where(function (Builder $q) {
                        $q->whereNull('subscription_expires_at')
                            ->orWhere('subscription_expires_at', '>', now());
                    })),

            'connected' => Tab::make('متصلة بالواتساب')
                ->badge(Company::where(function (Builder $q) {
                    $q->where('whatsapp_session_status', 'connected')
                        ->orWhere(function (Builder $q2) {
                            $q2->where('whatsapp_provider', 'meta_cloud')
                                ->whereNotNull('meta_phone_number_id')
                                ->whereNotNull('meta_access_token');
                        });
                })->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function (Builder $q) {
                    $q->where('whatsapp_session_status', 'connected')
                        ->orWhere(function (Builder $q2) {
                            $q2->where('whatsapp_provider', 'meta_cloud')
                                ->whereNotNull('meta_phone_number_id')
                                ->whereNotNull('meta_access_token');
                        });
                })),

            'inactive' => Tab::make('متوقفة / منتهية')
                ->badge(Company::where(function (Builder $q) {
                    $q->whereIn('status', ['suspended', 'expired'])
                        ->orWhere(function (Builder $q2) {
                            $q2->where('status', 'active')
                                ->where('subscription_expires_at', '<=', now());
                        });
                })->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function (Builder $q) {
                    $q->whereIn('status', ['suspended', 'expired'])
                        ->orWhere(function (Builder $q2) {
                            $q2->where('status', 'active')
                                ->where('subscription_expires_at', '<=', now());
                        });
                })),
        ];
    }
}
