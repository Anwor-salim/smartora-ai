<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use App\Models\SystemNotification;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $modelLabel = 'شركة ومشترك';
    protected static ?string $pluralModelLabel = 'الشركات والمشتركون';
    protected static ?string $navigationLabel = 'إدارة الشركات';
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('تفاصيل المنشأة والاشتراك')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('بيانات المنشأة والاشتراك')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Forms\Components\Section::make('معلومات المنشأة وأرقام التواصل')
                                    ->schema([
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('name')
                                                ->label('اسم الشركة / المتجر')
                                                ->required()
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state, '-', 'ar'))),

                                            Forms\Components\TextInput::make('slug')
                                                ->label('المعرّف المختصر (Slug)')
                                                ->required()
                                                ->unique(ignoreRecord: true),

                                            Forms\Components\TextInput::make('phone')
                                                ->label('رقم الهاتف للتواصل')
                                                ->tel(),

                                            Forms\Components\TextInput::make('whatsapp_number')
                                                ->label('رقم الواتساب المربوط (الرقم المستقبل)')
                                                ->required()
                                                ->unique(ignoreRecord: true)
                                                ->helperText('الرقم الذي يستقبل رسائل العملاء (مثال: 967771314738)')
                                                ->tel(),

                                            Forms\Components\TextInput::make('support_phone')
                                                ->label('رقم الرد الأولي / التحويل للدعم البشري')
                                                ->helperText('رقم مسؤول المبيعات للتحويل البشري')
                                                ->tel(),

                                            Forms\Components\TextInput::make('email')
                                                ->label('البريد الإلكتروني للمنشأة')
                                                ->email(),
                                        ]),
                                    ]),

                                Forms\Components\Section::make('حساب تسجيل الدخول للمنشأة (بوابة الشركات /company)')
                                    ->description('حدد البريد الإلكتروني وكلمة المرور لمالك المنشأة ليتمكن من تسجيل الدخول إلى بوابته والاطلاع على بياناته فقط:')
                                    ->icon('heroicon-o-lock-closed')
                                    ->schema([
                                        Forms\Components\Grid::make(3)->schema([
                                            Forms\Components\TextInput::make('admin_user_name')
                                                ->label('اسم مالك / مدير المنشأة')
                                                ->placeholder('مثال: محمد العمري')
                                                ->formatStateUsing(fn (?Company $record) => $record?->users()->where('role', 'company_admin')->first()?->name ?? ('مدير ' . ($record?->name ?? '')))
                                                ->dehydrated(false),

                                            Forms\Components\TextInput::make('admin_user_email')
                                                ->label('البريد الإلكتروني للدخول (Email) *')
                                                ->email()
                                                ->required(fn (string $context): bool => $context === 'create')
                                                ->placeholder('client@company.com')
                                                ->helperText('يُستخدم لتسجيل دخول المنشأة في بوابة الشركات.')
                                                ->formatStateUsing(fn (?Company $record) => $record?->users()->where('role', 'company_admin')->first()?->email ?? $record?->email)
                                                ->dehydrated(false),

                                             Forms\Components\TextInput::make('admin_user_password')
                                                ->label('كلمة المرور للدخول (Password) *')
                                                ->password()
                                                ->default('password123')
                                                ->required(fn (string $context): bool => $context === 'create')
                                                ->helperText('الافتراضية: password123 (يمكنك كتابة أي كلمة مرور).')
                                                ->dehydrated(false),
                                        ]),
                                    ]),

                                Forms\Components\Section::make('تفاصيل الاشتراك والصلاحية')
                                    ->schema([
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\Select::make('status')
                                                ->label('حالة الاشتراك')
                                                ->options([
                                                    'pending' => 'بانتظار التفعيل',
                                                    'active' => 'نشط (مفعل)',
                                                    'suspended' => 'متوقف مؤقتاً',
                                                    'expired' => 'منتهي الصلاحية',
                                                ])
                                                ->default('active')
                                                ->required(),

                                            Forms\Components\Select::make('subscription_plan')
                                                ->label('خطة الاشتراك')
                                                ->options([
                                                    'free_trial' => 'تجريبي - شهر مجاني (30 يوم)',
                                                    'monthly_pro' => 'شهري (30 يوم)',
                                                    '3_months' => '3 أشهر (90 يوم - $13/شهر)',
                                                    '6_months' => '6 أشهر (180 يوم - $12/شهر)',
                                                    'yearly' => 'سنوي (365 يوم - $11/شهر)',
                                                    'enterprise' => 'خطة الشركات (مخصصة)',
                                                ])
                                                ->default('monthly_pro')
                                                ->live()
                                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                    $starts = $get('subscription_starts_at') ? Carbon::parse($get('subscription_starts_at')) : Carbon::now();
                                                    $expires = match ($state) {
                                                        '3_months' => $starts->copy()->addMonths(3),
                                                        '6_months' => $starts->copy()->addMonths(6),
                                                        'yearly' => $starts->copy()->addYear(),
                                                        'enterprise' => $starts->copy()->addYears(2),
                                                        default => $starts->copy()->addDays(30),
                                                    };
                                                    $set('subscription_expires_at', $expires->format('Y-m-d H:i:s'));
                                                }),

                                            Forms\Components\DateTimePicker::make('subscription_starts_at')
                                                ->label('تاريخ بدء الاشتراك')
                                                ->default(now()),

                                            Forms\Components\DateTimePicker::make('subscription_expires_at')
                                                ->label('تاريخ انتهاء الاشتراك')
                                                ->default(now()->addDays(30)),
                                        ]),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('إعدادات الذكاء الاصطناعي والتواصل')
                            ->icon('heroicon-o-cpu-chip')
                            ->schema([
                                Forms\Components\Textarea::make('about_us')
                                    ->label('نبذة تعريفية عن المنشأة / المحل')
                                    ->placeholder('اكتب نبذة عن نشاط المحل ليجيب بها الذكاء الاصطناعي على أسئلة العملاء...')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('services_offered')
                                    ->label('نبذة عن الخدمات والمنتجات المقدمة')
                                    ->placeholder('مثال: توفير قطع الغيار، فحص كمبيوتر، خدمة شحن وتوصيل...')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('working_hours')
                                    ->label('ساعات وأيام العمل')
                                    ->placeholder('السبت إلى الخميس: من 8 صباحاً حتى 8 مساءً')
                                    ->rows(2),

                                Forms\Components\TextInput::make('address')
                                    ->label('العنوان الفعلي')
                                    ->placeholder('صنعاء - شارع الستين')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('google_maps_url')
                                    ->label('رابط خرائط جوجل (Google Maps)')
                                    ->url()
                                    ->placeholder('https://maps.google.com/?q=...')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('settings.greeting_message')
                                    ->label('رسالة الترحيب التلقائية للعميل')
                                    ->placeholder('أهلاً بك! تفضل بالاستفسار عن المنتجات والأسعار.')
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('custom_system_prompt')
                                    ->label('التلقين المخصص (System Prompt)')
                                    ->placeholder('أنت المساعد الذكي لشركة ...، أجب فقط بناءً على البيانات المتوفرة.')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'users' => fn ($q) => $q->where('role', 'company_admin'),
            ]))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الشركة')
                    ->searchable(['name', 'email', 'whatsapp_number'])
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Company $record) => $record->users->first()?->email ?? $record->email ?? 'بدون حساب دخول'),

                Tables\Columns\TextColumn::make('whatsapp_number')
                    ->label('القنوات والربط')
                    ->fontFamily('mono')
                    ->placeholder('غير مربوط')
                    ->copyable()
                    ->icon('heroicon-m-signal')
                    ->iconColor(fn (Company $record) => $record->isWhatsAppConnected()
                        ? 'success'
                        : ($record->whatsapp_session_status === 'scanning' ? 'warning' : 'gray'))
                    ->description(function (Company $record): string {
                        $wa = $record->isWhatsAppConnected()
                            ? ($record->whatsapp_provider === 'meta_cloud' ? 'واتساب متصل (Meta)' : 'واتساب متصل (QR)')
                            : ($record->whatsapp_session_status === 'scanning' ? 'بانتظار المسح' : 'واتساب غير مربوط');

                        if ($record->isInstagramConnected()) {
                            return $wa . ' · انستقرام ✓';
                        }

                        return $wa;
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('الاشتراك')
                    ->badge()
                    ->state(function (Company $record): string {
                        if ($record->status === 'active' && $record->subscription_expires_at?->isPast()) {
                            return 'expired';
                        }

                        return $record->status ?? 'active';
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'بانتظار التفعيل',
                        'active' => 'نشط',
                        'suspended' => 'متوقف',
                        'expired' => 'منتهي',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'active' => 'success',
                        'suspended' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    })
                    ->description(function (Company $record): string {
                        $plan = match ($record->subscription_plan) {
                            'free_trial' => 'شهر مجاني',
                            'monthly_pro' => 'شهري',
                            '3_months' => '3 أشهر',
                            '6_months' => '6 أشهر',
                            'yearly' => 'سنوي',
                            'enterprise' => 'شركات',
                            default => $record->subscription_plan ?? 'افتراضي',
                        };

                        if ($record->status === 'pending') {
                            return $plan . ' · طلب جديد';
                        }

                        if (! $record->subscription_expires_at) {
                            return $plan . ' · مفتوح';
                        }

                        if ($record->subscription_expires_at->isPast()) {
                            return $plan . ' · منتهي';
                        }

                        $days = (int) now()->diffInDays($record->subscription_expires_at, false);

                        return "{$plan} · متبقي {$days} يوم";
                    }),

                Tables\Columns\TextColumn::make('documents_count')
                    ->label('الملفات')
                    ->counts('documents')
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('conversations_count')
                    ->label('المحادثات')
                    ->counts('conversations')
                    ->badge()
                    ->color('info')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('حالة الاشتراك')
                    ->options([
                        'pending' => 'بانتظار التفعيل',
                        'active' => 'نشط',
                        'suspended' => 'متوقف',
                        'expired' => 'منتهي',
                    ]),

                Tables\Filters\SelectFilter::make('whatsapp_connected')
                    ->label('ربط الواتساب')
                    ->options([
                        'connected' => 'متصل',
                        'disconnected' => 'غير متصل',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $val = $data['value'] ?? null;
                        if ($val === 'connected') {
                            return $query->where(function (Builder $q) {
                                $q->where('whatsapp_session_status', 'connected')
                                    ->orWhere(function (Builder $q2) {
                                        $q2->where('whatsapp_provider', 'meta_cloud')
                                            ->whereNotNull('meta_phone_number_id')
                                            ->whereNotNull('meta_access_token');
                                    });
                            });
                        }
                        if ($val === 'disconnected') {
                            return $query->where('whatsapp_session_status', '!=', 'connected')
                                ->where(function (Builder $q) {
                                    $q->where('whatsapp_provider', '!=', 'meta_cloud')
                                        ->orWhereNull('whatsapp_provider')
                                        ->orWhereNull('meta_phone_number_id')
                                        ->orWhereNull('meta_access_token');
                                });
                        }

                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('approve_company')
                    ->label('اعتماد وتفعيل')
                    ->button()
                    ->size('xs')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Company $record): bool => $record->status === 'pending')
                    ->modalHeading(fn (Company $record): string => "اعتماد وتفعيل منشأة: {$record->name}")
                    ->modalDescription(fn (Company $record): string => !empty($record->settings['registration_notes'])
                        ? 'ملاحظات العميل عند التسجيل: ' . $record->settings['registration_notes']
                        : 'اختر الباقة المعتمدة وسيتم تفعيل المنشأة وحساب المدير فوراً.')
                    ->form([
                        Forms\Components\Select::make('approved_plan')
                            ->label('الباقة المعتمدة للاشتراك')
                            ->options([
                                'free_trial' => 'شهر مجاني (30 يوم - $0)',
                                '3_months' => '3 أشهر (90 يوم - $13/شهر)',
                                '6_months' => '6 أشهر (180 يوم - $12/شهر)',
                                'yearly' => 'سنة كاملة (365 يوم - $11/شهر)',
                                'enterprise' => 'خطة الشركات (مخصصة - سنتان)',
                            ])
                            ->default(fn (Company $record): string => $record->subscription_plan ?: 'free_trial')
                            ->required(),
                    ])
                    ->action(function (Company $record, array $data): void {
                        $plan = $data['approved_plan'];
                        $starts = Carbon::now();
                        $expires = match ($plan) {
                            '3_months' => $starts->copy()->addMonths(3),
                            '6_months' => $starts->copy()->addMonths(6),
                            'yearly' => $starts->copy()->addYear(),
                            'enterprise' => $starts->copy()->addYears(2),
                            default => $starts->copy()->addDays(30),
                        };

                        $record->update([
                            'status' => 'active',
                            'subscription_plan' => $plan,
                            'subscription_starts_at' => $starts,
                            'subscription_expires_at' => $expires,
                        ]);

                        $record->users()
                            ->where('role', 'company_admin')
                            ->update(['status' => 'active']);

                        SystemNotification::where('company_id', $record->id)
                            ->where('is_read', false)
                            ->update(['is_read' => true]);

                        SystemNotification::create([
                            'company_id' => $record->id,
                            'title' => 'تم تفعيل حساب المنشأة',
                            'message' => "تم اعتماد وتفعيل منشأة \"{$record->name}\" بنجاح حتى " . $expires->format('Y-m-d') . '.',
                            'type' => 'success',
                            'is_read' => true,
                        ]);

                        $adminEmail = $record->users()->where('role', 'company_admin')->value('email') ?? $record->email;

                        Notification::make()
                            ->title('تم اعتماد وتفعيل المنشأة بنجاح 🎉')
                            ->body("يمكن للعميل الآن الدخول عبر " . url('/company/login') . ($adminEmail ? "\nالبريد: {$adminEmail}" : ''))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('open_portal')
                    ->label('بوابة الشركة')
                    ->button()
                    ->size('xs')
                    ->tooltip('دخول لوحة تحكم هذه المنشأة مباشرة كمسؤول')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->visible(fn (Company $record): bool => $record->status !== 'pending')
                    ->url(fn (Company $record) => route('admin.switch-company', ['company' => $record->id]), shouldOpenInNewTab: true),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->label('تعديل المنشأة'),

                    Tables\Actions\Action::make('manage_credentials')
                        ->label('بيانات الدخول')
                        ->icon('heroicon-o-key')
                        ->color('info')
                        ->modalHeading(fn (Company $record) => "بيانات دخول مدير منشأة: {$record->name}")
                        ->modalDescription('يمكنك من هنا عرض البريد الإلكتروني أو تعيين كلمة مرور جديدة لمالك الشركة للدخول إلى لوحة الشركة (/company/login)')
                        ->form([
                            Forms\Components\TextInput::make('manager_email')
                                ->label('البريد الإلكتروني للدخول')
                                ->default(fn (Company $record) => $record->users()->where('role', 'company_admin')->first()?->email ?? $record->email ?? ($record->slug . '@smartora.com'))
                                ->required()
                                ->email(),
                            Forms\Components\TextInput::make('new_password')
                                ->label('تعيين كلمة مرور جديدة')
                                ->placeholder('اتركه فارغاً للإبقاء على الحالية (الافتراضية: password123)')
                                ->password(),
                        ])
                        ->action(function (Company $record, array $data) {
                            $user = $record->users()->where('role', 'company_admin')->first();
                            $targetEmail = trim($data['manager_email']);

                            $fields = [
                                'name' => $user?->name ?? ('مدير ' . $record->name),
                                'email' => $targetEmail,
                                'role' => 'company_admin',
                                'company_id' => $record->id,
                                'status' => 'active',
                                'phone' => $record->phone ?? $record->whatsapp_number,
                            ];

                            if (!empty($data['new_password'])) {
                                $fields['password'] = Hash::make(trim($data['new_password']));
                            } elseif (!$user) {
                                $fields['password'] = Hash::make('password123');
                            }

                            if ($user) {
                                $user->update($fields);
                            } else {
                                User::create($fields);
                            }

                            Notification::make()
                                ->title('تم حفظ وتحديث بيانات دخول المنشأة بنجاح 🎉')
                                ->body("البريد الإلكتروني: {$targetEmail}\nرابط الدخول: " . url('/company/login'))
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('extend_30_days')
                        ->label('تمديد +30 يوم')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('تمديد اشتراك الشركة 30 يوماً إضافية')
                        ->action(function (Company $record) {
                            $currentExpiry = $record->subscription_expires_at && $record->subscription_expires_at->isFuture()
                                ? $record->subscription_expires_at
                                : Carbon::now();

                            $record->update([
                                'subscription_expires_at' => $currentExpiry->copy()->addDays(30),
                                'status' => 'active',
                            ]);

                            SystemNotification::create([
                                'company_id' => $record->id,
                                'title' => 'تمديد الاشتراك',
                                'message' => 'تم تمديد اشتراك المنشأة لمدة 30 يوماً بنجاح.',
                                'type' => 'success',
                            ]);

                            Notification::make()
                                ->title('تم تمديد الاشتراك +30 يوم وتفعيل المنشأة')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('toggle_status')
                        ->label(fn (Company $record) => $record->status === 'active' ? 'إيقاف مؤقت' : 'تفعيل')
                        ->icon(fn (Company $record) => $record->status === 'active' ? 'heroicon-o-pause' : 'heroicon-o-play')
                        ->color(fn (Company $record) => $record->status === 'active' ? 'warning' : 'success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Company $record) => $record->status === 'active' ? 'إيقاف اشتراك الشركة مؤقتاً' : 'تفعيل اشتراك الشركة')
                        ->modalDescription('سيؤثر هذا الإجراء فورياً على ردود البوت الذكي عبر الواتساب.')
                        ->action(function (Company $record) {
                            $newStatus = $record->status === 'active' ? 'suspended' : 'active';
                            $record->update(['status' => $newStatus]);

                            if ($newStatus === 'active') {
                                $record->users()->where('role', 'company_admin')->update(['status' => 'active']);
                            }

                            Notification::make()
                                ->title($newStatus === 'active' ? 'تم تفعيل الاشتراك بنجاح' : 'تم إيقاف الاشتراك مؤقتاً')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->label('حذف المنشأة'),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('المزيد من الإجراءات'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
