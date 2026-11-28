<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Services\AppointmentBookingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static ?string $modelLabel = 'حجز موعد';
    protected static ?string $pluralModelLabel = 'إدارة الحجوزات والمواعيد';
    protected static ?string $navigationLabel = 'الحجوزات والمواعيد';
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        if ($user && !$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id ?: 0);
        } elseif ($user && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('بيانات الحجز')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('booking_code')
                                ->label('رقم الحجز')
                                ->disabled(),

                            Forms\Components\TextInput::make('queue_number')
                                ->label('رقم الدور')
                                ->disabled(),

                            Forms\Components\Select::make('status')
                                ->label('الحالة')
                                ->options([
                                    'pending' => 'قيد المراجعة',
                                    'approved' => 'مؤكد',
                                    'rejected' => 'مرفوض',
                                    'cancelled' => 'ملغي',
                                    'completed' => 'مكتمل',
                                ])
                                ->disabled(),

                            Forms\Components\TextInput::make('customer_name')
                                ->label('اسم العميل')
                                ->disabled(),

                            Forms\Components\TextInput::make('customer_phone')
                                ->label('رقم / معرّف العميل')
                                ->disabled(),

                            Forms\Components\DatePicker::make('appointment_date')
                                ->label('تاريخ الموعد')
                                ->disabled(),

                            Forms\Components\TextInput::make('appointment_time')
                                ->label('الوقت')
                                ->disabled(),

                            Forms\Components\TextInput::make('sheet_row_number')
                                ->label('رقم الصف في Google Sheet')
                                ->disabled(),

                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('سبب الرفض / ملاحظات')
                                ->disabled()
                                ->columnSpanFull(),
                        ]),

                        Forms\Components\KeyValue::make('form_data')
                            ->label('البيانات المجمّعة حسب ملف Google Sheet')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('booking_code')
                    ->label('الكود')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('queue_number')
                    ->label('الدور')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => '#' . $state)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('العميل')
                    ->searchable()
                    ->description(fn (Appointment $record): string => (string) $record->customer_phone),

                Tables\Columns\TextColumn::make('appointment_date')
                    ->label('تاريخ الموعد')
                    ->date('Y-m-d')
                    ->sortable()
                    ->description(fn (Appointment $record): ?string => $record->appointment_time ? ('الوقت: ' . $record->appointment_time) : null),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'approved' => 'مؤكد',
                        'rejected' => 'مرفوض',
                        'cancelled' => 'ملغي',
                        'completed' => 'مكتمل',
                        default => 'قيد المراجعة',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'cancelled' => 'gray',
                        'completed' => 'info',
                        default => 'warning',
                    }),

                Tables\Columns\TextColumn::make('sheet_row_number')
                    ->label('مزامنة Sheet')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? "صف #{$state}" : 'غير متزامن')
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('وقت الطلب')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد المراجعة',
                        'approved' => 'مؤكد',
                        'rejected' => 'مرفوض',
                        'cancelled' => 'ملغي',
                        'completed' => 'مكتمل',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('قبول وتأكيد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Appointment $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد الموعد وإشعار العميل')
                    ->modalDescription('سيتم اعتماد الموعد وتحديث ملف Google Sheet وإرسال رسالة تأكيد فورية للعميل.')
                    ->action(function (Appointment $record): void {
                        app(AppointmentBookingService::class)->approveAppointment($record);
                        Notification::make()
                            ->title('تم قبول الموعد وإرسال التأكيد للعميل')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Appointment $record): bool => in_array($record->status, ['pending', 'approved'], true))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('سبب الرفض (اختياري - يُرسل للعميل)')
                            ->rows(2),
                    ])
                    ->action(function (Appointment $record, array $data): void {
                        app(AppointmentBookingService::class)->rejectAppointment($record, $data['rejection_reason'] ?? null);
                        Notification::make()
                            ->title('تم رفض الطلب وإشعار العميل وإفراغ المقعد')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('إلغاء')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->visible(fn (Appointment $record): bool => in_array($record->status, ['pending', 'approved'], true))
                    ->requiresConfirmation()
                    ->action(function (Appointment $record): void {
                        app(AppointmentBookingService::class)->cancelAppointment($record, 'reception');
                        Notification::make()
                            ->title('تم إلغاء الموعد وإشعار العميل')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->tooltip('عرض التفاصيل'),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointments::route('/'),
            'view' => Pages\ViewAppointment::route('/{record}'),
        ];
    }
}
