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
                                        ->helperText('الرقم الذي يستقبل رسائل العملاء (مثال: 967771314738)')
                                        ->tel(),

                                    Forms\Components\TextInput::make('support_phone')
                                        ->label('رقم الرد الأولي / التحويل للدعم البشري')
                                        ->helperText('رقم مسؤول المبيعات للتحويل البشري')
                                        ->tel(),

                                    Forms\Components\TextInput::make('email')
                                        ->label('البريد الإلكتروني للمنشأة')
                                        ->email(),

                                    Forms\Components\Select::make('status')
                                        ->label('حالة الاشتراك')
                                        ->options([
                                            'active' => 'نشط (مفعل)',
                                            'suspended' => 'متوقف مؤقتاً',
                                            'expired' => 'منتهي الصلاحية',
                                        ])
                                        ->default('active')
                                        ->required(),

                                    Forms\Components\Select::make('subscription_plan')
                                        ->label('خطة الاشتراك')
                                        ->options([
                                            'monthly_pro' => 'شهري (30 يوم)',
                                            '3_months' => '3 أشهر (90 يوم)',
                                            '6_months' => '6 أشهر (180 يوم)',
                                            'yearly' => 'سنوي (365 يوم)',
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

                        Forms\Components\Tabs\Tab::make('حساب مدير الشركة (تسجيل دخول)')
                            ->icon('heroicon-o-user-plus')
                            ->schema([
                                Forms\Components\Placeholder::make('info')
                                    ->content('يتم إنشاء حساب مستخدم تلقائياً لمالك المنشأة لتسجيل دخوله إلى لوحة تحكم الشركة (/company). إذا تركت البريد فارغاً، سيتم اعتماد بريد المنشأة تلقائياً:')
                                    ->columnSpanFull(),

                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('admin_user_name')
                                        ->label('اسم مالك / مدير الشركة')
                                        ->placeholder('مثال: محمد العمري')
                                        ->formatStateUsing(fn (?Company $record) => $record?->users()->where('role', 'company_admin')->first()?->name ?? ('مدير ' . ($record?->name ?? '')))
                                        ->dehydrated(false),

                                    Forms\Components\TextInput::make('admin_user_email')
                                        ->label('البريد الإلكتروني للدخول')
                                        ->email()
                                        ->placeholder('client@company.com')
                                        ->helperText('إذا ترك فارغاً سيتم استخدام البريد الإلكتروني للمنشأة.')
                                        ->formatStateUsing(fn (?Company $record) => $record?->users()->where('role', 'company_admin')->first()?->email ?? $record?->email)
                                        ->dehydrated(false),

                                    Forms\Components\TextInput::make('admin_user_password')
                                        ->label('كلمة المرور للدخول')
                                        ->password()
                                        ->default('password123')
                                        ->helperText('اتركه فارغاً عند التعديل للإبقاء على كلمة المرور الحالية (الافتراضية: password123).')
                                        ->dehydrated(false),
                                ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الشركة / المنشأة')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Company $record) => $record->email ?? $record->phone),

                Tables\Columns\TextColumn::make('manager_login')
                    ->label('حساب الدخول (المدير)')
                    ->state(fn (Company $record) => $record->users()->where('role', 'company_admin')->first()?->email ?? $record->email ?? 'لم ينشأ')
                    ->icon('heroicon-m-user')
                    ->badge()
                    ->color(fn ($state) => $state === 'لم ينشأ' ? 'danger' : 'success')
                    ->copyable()
                    ->tooltip('انقر لنسخ البريد الإلكتروني لدخول لوحة المنشأة'),

                Tables\Columns\TextColumn::make('whatsapp_number')
                    ->label('رقم الواتساب')
                    ->icon('heroicon-m-chat-bubble-left-ellipsis')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('whatsapp_session_status')
                    ->label('ربط الواتساب')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'connected' => 'متصل (تم المسح)',
                        'scanning' => 'بانتظار المسح',
                        default => 'غير مربوط',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'connected' => 'success',
                        'scanning' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('حالة الاشتراك')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'نشط',
                        'suspended' => 'متوقف',
                        'expired' => 'منتهي',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('subscription_plan')
                    ->label('الخطة')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'monthly_pro' => 'شهري',
                        '3_months' => '3 أشهر',
                        '6_months' => '6 أشهر',
                        'yearly' => 'سنوي',
                        'enterprise' => 'شركات',
                        default => $state ?? 'افتراضي',
                    }),

                Tables\Columns\TextColumn::make('subscription_expires_at')
                    ->label('ينتهي في')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->description(function (Company $record) {
                        if (! $record->subscription_expires_at) {
                            return 'غير محدد';
                        }
                        return $record->subscription_expires_at->diffForHumans();
                    })
                    ->color(function (Company $record) {
                        if (! $record->subscription_expires_at) {
                            return 'gray';
                        }
                        if ($record->subscription_expires_at->isPast()) {
                            return 'danger';
                        }
                        if ($record->subscription_expires_at->diffInDays(now()) < 7) {
                            return 'warning';
                        }
                        return 'success';
                    }),

                Tables\Columns\TextColumn::make('documents_count')
                    ->counts('documents')
                    ->label('ملفات المعرفة')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('conversations_count')
                    ->counts('conversations')
                    ->label('المحادثات')
                    ->badge()
                    ->color('secondary')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('فلترة حسب الحالة')
                    ->options([
                        'active' => 'نشط',
                        'suspended' => 'متوقف',
                        'expired' => 'منتهي',
                    ]),

                Tables\Filters\SelectFilter::make('subscription_plan')
                    ->label('فلترة حسب الخطة')
                    ->options([
                        'monthly_pro' => 'شهري',
                        '3_months' => '3 أشهر',
                        '6_months' => '6 أشهر',
                        'yearly' => 'سنوي',
                        'enterprise' => 'شركات',
                    ]),
            ])
            ->actions([
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

                        Notification::make()
                            ->title($newStatus === 'active' ? 'تم تفعيل الاشتراك بنجاح' : 'تم إيقاف الاشتراك مؤقتاً')
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
                            $fields['password'] = \Illuminate\Support\Facades\Hash::make(trim($data['new_password']));
                        } elseif (!$user) {
                            $fields['password'] = \Illuminate\Support\Facades\Hash::make('password123');
                        }

                        if ($user) {
                            $user->update($fields);
                        } else {
                            \App\Models\User::create($fields);
                        }

                        Notification::make()
                            ->title('تم حفظ وتحديث بيانات دخول المنشأة بنجاح 🎉')
                            ->body("البريد الإلكتروني: {$targetEmail}\nرابط الدخول: " . url('/company/login'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('open_portal')
                    ->label('بوابة الشركة')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(url('/company/login'), shouldOpenInNewTab: true),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
