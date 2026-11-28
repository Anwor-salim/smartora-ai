<?php

namespace App\Filament\Company\Pages;

use App\Models\Company;
use App\Services\AppointmentBookingService;
use App\Services\GoogleSheetsService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class BookingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static string $view = 'filament.company.pages.booking-settings';

    protected static ?string $title = 'إعدادات الحجز و Google Sheets';
    protected static ?string $navigationLabel = 'إعدادات الحجز و Sheets';
    protected static ?int $navigationSort = 4;

    public ?array $data = [];

    public function mount(): void
    {
        $company = $this->getCompany();
        $sheets = app(GoogleSheetsService::class);
        $serviceEmail = $sheets->getServiceAccountEmail()
            ?: 'لم يتم تهيئة ملف Google Service Account على السيرفر بعد';

        if ($company) {
            $settings = is_array($company->settings) ? $company->settings : [];
            $customFields = $settings['booking_custom_fields'] ?? ['الاسم', 'العمر', 'التاريخ', 'الوقت'];
            $customFieldsStr = is_array($customFields) ? implode('، ', $customFields) : (string) $customFields;

            $overrides = $settings['booking_date_overrides'] ?? [];
            $overridesStr = [];
            if (is_array($overrides)) {
                foreach ($overrides as $k => $v) {
                    $overridesStr[(string) $k] = (string) $v;
                }
            }

            $this->form->fill([
                'booking_enabled' => (bool) ($settings['booking_enabled'] ?? false),
                'booking_approval_mode' => $settings['booking_approval_mode'] ?? 'reception_approval',
                'booking_notify_reception_whatsapp' => (bool) ($settings['booking_notify_reception_whatsapp'] ?? false),
                'booking_daily_limit' => (int) ($settings['booking_daily_limit'] ?? 10),
                'booking_working_days' => $settings['booking_working_days'] ?? ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'],
                'booking_date_overrides' => $overridesStr,
                'service_account_email' => $serviceEmail,
                'booking_sheet_url' => $settings['booking_sheet_url'] ?? '',
                'booking_sheet_tab' => $settings['booking_sheet_tab'] ?? '',
                'booking_custom_fields' => $customFieldsStr,
            ]);
        }
    }

    public function getCompany(): ?Company
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        if ($user->isCompanyAdmin()) {
            return $user->company;
        }

        return $user->company ?? Company::first();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncSheetHeaders')
                ->label('فحص الربط ومزامنة الأعمدة')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function (): void {
                    $this->submit(silent: true);
                    $company = $this->getCompany();
                    if (!$company) {
                        return;
                    }

                    $sheets = app(GoogleSheetsService::class);
                    if (!$sheets->isConfigured()) {
                        Notification::make()
                            ->title('حساب الخدمة (Service Account) غير مهيأ على السيرفر')
                            ->body('يرجى إضافة بيانات Google Service Account أولاً.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $bookingService = app(AppointmentBookingService::class);
                    $schema = $bookingService->refreshSheetHeaders($company, true);
                    if (!in_array($schema['source'] ?? '', ['google_sheets', 'google_sheets_cached'], true)) {
                        Notification::make()
                            ->title('تعذر قراءة ملف Google Sheet')
                            ->body('تأكد من صحة رابط الملف ومشاركته مع بريد الخدمة كـ Editor.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $labels = array_map(fn ($f) => $f['label'], $schema['customer_fields'] ?? []);
                    Notification::make()
                        ->title('تمت مزامنة أعمدة Google Sheet بنجاح!')
                        ->body('الحقول التي سيطلبها البوت من العميل: ' . (!empty($labels) ? implode('، ', $labels) : 'لا توجد حقول عميل'))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('نظام الحجز والربط مع Google Sheets')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('التفعيل والاعتماد')
                            ->icon('heroicon-o-check-badge')
                            ->schema([
                                Forms\Components\Toggle::make('booking_enabled')
                                    ->label('تفعيل الحجز التلقائي عبر البوت')
                                    ->helperText('عند التفعيل، يجمع البوت حقول التسجيل من العميل ويفحص السعة اليومية ويسجل الحجز تلقائياً.')
                                    ->default(false)
                                    ->columnSpanFull(),

                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('booking_approval_mode')
                                        ->label('نظام اعتماد الطلبات')
                                        ->options([
                                            'reception_approval' => 'مراجعة الاستقبال أولاً (قيد المراجعة ← تأكيد بعد القبول)',
                                            'auto_confirm' => 'تأكيد فوري تلقائي مباشرة',
                                        ])
                                        ->default('reception_approval')
                                        ->required(),

                                    Forms\Components\Toggle::make('booking_notify_reception_whatsapp')
                                        ->label('إرسال تنبيه واتساب للاستقبال عند كل طلب جديد')
                                        ->helperText('يُرسل التنبيه إلى رقم مسؤول الدعم/المبيعات المسجل في بيانات المنشأة.')
                                        ->default(false),
                                ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('السعة اليومية وأيام العمل')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Forms\Components\TextInput::make('booking_daily_limit')
                                    ->label('الحد الأقصى لعدد الحجوزات في اليوم الواحد')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(500)
                                    ->default(10)
                                    ->helperText('عند اكتمال هذا العدد في يوم معين، يقترح البوت تلقائياً أقرب يوم متاح تالٍ.')
                                    ->required(),

                                Forms\Components\CheckboxList::make('booking_working_days')
                                    ->label('أيام العمل المتاحة للحجز')
                                    ->options([
                                        'sat' => 'السبت',
                                        'sun' => 'الأحد',
                                        'mon' => 'الإثنين',
                                        'tue' => 'الثلاثاء',
                                        'wed' => 'الأربعاء',
                                        'thu' => 'الخميس',
                                        'fri' => 'الجمعة',
                                    ])
                                    ->columns(4)
                                    ->required(),

                                Forms\Components\KeyValue::make('booking_date_overrides')
                                    ->label('استثناءات تواريخ محددة (اختياري)')
                                    ->keyLabel('التاريخ (YYYY-MM-DD)')
                                    ->valueLabel('السعة المسموحة (0 = مغلق / إجازة)')
                                    ->addActionLabel('إضافة تاريخ مستثنى')
                                    ->helperText('مثال: 2026-12-01 => 15 (رفع السعة)، أو 2026-12-05 => 0 (إجازة خاصة).')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('ربط Google Sheets (درايف)')
                            ->icon('heroicon-o-table-cells')
                            ->schema([
                                Forms\Components\TextInput::make('service_account_email')
                                    ->label('بريد الربط (شارِك ملف Google Sheet مع هذا البريد كـ Editor)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('افتح ملف Google Sheet ← مشاركة (Share) ← أضف هذا البريد بصلاحية محرر (Editor).')
                                    ->columnSpanFull(),

                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('booking_sheet_url')
                                        ->label('رابط أو معرّف ملف Google Sheet')
                                        ->placeholder('https://docs.google.com/spreadsheets/d/...')
                                        ->helperText('الصق رابط ملف Google Sheet الخاص بهذه المنشأة.')
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('booking_sheet_tab')
                                        ->label('اسم الورقة / التبويب (اختياري)')
                                        ->placeholder('اتركه فارغاً لاستخدام الورقة الأولى')
                                        ->helperText('مثال: Sheet1 أو الحجوزات'),

                                    Forms\Components\TextInput::make('booking_custom_fields')
                                        ->label('الحقول الافتراضية (عند عدم ربط Google Sheet)')
                                        ->placeholder('الاسم، العمر، التاريخ، الوقت')
                                        ->helperText('تُستخدم تلقائياً إذا لم يتم ربط شيت؛ وعند الربط يقرأ البوت الأعمدة من الصف الأول في الشيت مباشرة.'),
                                ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function submit(bool $silent = false): void
    {
        $company = $this->getCompany();
        if (!$company) {
            Notification::make()->title('لم يتم العثور على منشأة')->danger()->send();
            return;
        }

        $formState = $this->form->getState();
        $settings = is_array($company->settings) ? $company->settings : [];

        $sheets = app(GoogleSheetsService::class);
        $sheetUrl = trim((string) ($formState['booking_sheet_url'] ?? ''));
        $sheetId = $sheets->extractSpreadsheetId($sheetUrl);

        $customFields = array_values(array_filter(array_map(
            'trim',
            preg_split('/[,،\n]+/u', (string) ($formState['booking_custom_fields'] ?? '')) ?: []
        )));

        $overrides = [];
        if (!empty($formState['booking_date_overrides']) && is_array($formState['booking_date_overrides'])) {
            foreach ($formState['booking_date_overrides'] as $dateKey => $limitVal) {
                $cleanDate = trim((string) $dateKey);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $cleanDate) && is_numeric($limitVal)) {
                    $overrides[$cleanDate] = max(0, (int) $limitVal);
                }
            }
        }

        $settings['booking_enabled'] = (bool) ($formState['booking_enabled'] ?? false);
        $settings['booking_approval_mode'] = $formState['booking_approval_mode'] ?? 'reception_approval';
        $settings['booking_notify_reception_whatsapp'] = (bool) ($formState['booking_notify_reception_whatsapp'] ?? false);
        $settings['booking_daily_limit'] = max(1, (int) ($formState['booking_daily_limit'] ?? 10));
        $settings['booking_working_days'] = $formState['booking_working_days'] ?? ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'];
        $settings['booking_date_overrides'] = $overrides;
        $settings['booking_sheet_url'] = $sheetUrl;
        $settings['booking_sheet_id'] = $sheetId;
        $settings['booking_sheet_tab'] = trim((string) ($formState['booking_sheet_tab'] ?? ''));
        $settings['booking_custom_fields'] = !empty($customFields) ? $customFields : ['الاسم', 'العمر', 'التاريخ', 'الوقت'];

        $company->update(['settings' => $settings]);

        if (!$silent) {
            Notification::make()
                ->title('تم حفظ إعدادات الحجز و Google Sheets بنجاح!')
                ->success()
                ->send();
        }
    }
}
