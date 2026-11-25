<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemNotificationResource\Pages;
use App\Models\SystemNotification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SystemNotificationResource extends Resource
{
    protected static ?string $model = SystemNotification::class;

    protected static ?string $modelLabel = 'تنبيه نظام';
    protected static ?string $pluralModelLabel = 'التنبيهات الفورية';
    protected static ?string $navigationLabel = 'التنبيهات';
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';
    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $unreadCount = static::getModel()::where('is_read', false)->count();

        return $unreadCount > 0 ? (string) $unreadCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('المنشأة المتعلقة')
                        ->relationship('company', 'name')
                        ->nullable(),

                    Forms\Components\TextInput::make('title')
                        ->label('عنوان التنبيه')
                        ->required(),

                    Forms\Components\Select::make('type')
                        ->label('نوع الإشعار')
                        ->options([
                            'info' => 'معلومات (أزرق)',
                            'success' => 'نجاح (أخضر)',
                            'warning' => 'تحذير (برتقالي)',
                            'danger' => 'تنبيه حرج (أحمر)',
                        ])
                        ->default('info')
                        ->required(),

                    Forms\Components\Textarea::make('message')
                        ->label('نص التنبيه')
                        ->rows(3)
                        ->required(),

                    Forms\Components\Toggle::make('is_read')
                        ->label('تمت القراءة')
                        ->default(false),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('type')
                    ->label('النوع')
                    ->icon(fn (string $state): string => match ($state) {
                        'success' => 'heroicon-o-check-circle',
                        'warning' => 'heroicon-o-exclamation-triangle',
                        'danger' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-information-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'warning' => 'warning',
                        'danger' => 'danger',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('التنبيه')
                    ->weight('bold')
                    ->searchable(['title', 'message'])
                    ->description(fn (SystemNotification $record) => Str::limit((string) $record->message, 80))
                    ->tooltip(fn (SystemNotification $record) => $record->message),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('المنشأة')
                    ->badge()
                    ->color('gray')
                    ->placeholder('النظام العام'),

                Tables\Columns\IconColumn::make('is_read')
                    ->label('مقروء')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('الوقت')
                    ->since()
                    ->sortable()
                    ->tooltip(fn (SystemNotification $record) => $record->created_at?->format('d/m/Y H:i')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_read')
                    ->label('حالة القراءة')
                    ->placeholder('الكل')
                    ->trueLabel('المقروءة فقط')
                    ->falseLabel('غير المقروءة فقط'),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_as_read')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('تحديد كمقروء')
                    ->visible(fn (SystemNotification $record) => ! $record->is_read)
                    ->action(function (SystemNotification $record) {
                        $record->update(['is_read' => true]);
                        Notification::make()
                            ->title('تم تحديد الإشعار كمقروء')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('حذف'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_all_read')
                        ->label('تحديد المحدد كمقروء')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['is_read' => true])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemNotifications::route('/'),
        ];
    }
}
