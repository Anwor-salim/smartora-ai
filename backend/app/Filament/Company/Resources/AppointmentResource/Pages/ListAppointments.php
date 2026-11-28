<?php

namespace App\Filament\Company\Resources\AppointmentResource\Pages;

use App\Filament\Company\Resources\AppointmentResource;
use App\Models\Company;
use App\Services\AppointmentBookingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncFromSheet')
                ->label('مزامنة من Google Sheet')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function (): void {
                    $user = auth()->user();
                    $company = $user?->company ?? Company::first();
                    if (!$company) {
                        return;
                    }

                    $stats = app(AppointmentBookingService::class)->syncCompanySheetStatuses($company);
                    if ($stats['skipped']) {
                        Notification::make()
                            ->title('لم تكتمل المزامنة')
                            ->body('تأكد من ربط Google Sheet وتهيئة حساب الخدمة.')
                            ->warning()
                            ->send();
                        return;
                    }

                    Notification::make()
                        ->title('تمت المزامنة مع Google Sheet بنجاح')
                        ->body("تحديثات: مقبول ({$stats['approved']}) | مرفوض ({$stats['rejected']}) | ملغي ({$stats['cancelled']})")
                        ->success()
                        ->send();
                }),
        ];
    }
}
