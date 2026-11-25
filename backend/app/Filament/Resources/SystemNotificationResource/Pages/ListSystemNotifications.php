<?php

namespace App\Filament\Resources\SystemNotificationResource\Pages;

use App\Filament\Resources\SystemNotificationResource;
use App\Models\SystemNotification;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSystemNotifications extends ListRecords
{
    protected static string $resource = SystemNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('mark_all_read')
                ->label('تحديد الكل كمقروء')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => SystemNotification::where('is_read', false)->exists())
                ->action(function () {
                    SystemNotification::where('is_read', false)->update(['is_read' => true]);
                    Notification::make()
                        ->title('تم تحديد جميع التنبيهات كمقروءة')
                        ->success()
                        ->send();
                }),
        ];
    }
}
