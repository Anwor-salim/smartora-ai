<?php

namespace App\Filament\Company\Pages;

use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CompanySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static string $view = 'filament.company.pages.company-settings';

    protected static ?string $title = 'بيانات المنشأة وإعدادات الذكاء';
    protected static ?string $navigationLabel = 'بيانات المنشأة';
    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public function mount(): void
    {
        $company = $this->getCompany();
        if ($company) {
            $this->form->fill([
                'name' => $company->name,
                'about_us' => $company->about_us,
                'services_offered' => $company->services_offered,
                'phone' => $company->phone,
                'whatsapp_number' => $company->whatsapp_number,
                'support_phone' => $company->support_phone,
                'email' => $company->email,
                'address' => $company->address,
                'google_maps_url' => $company->google_maps_url,
                'working_hours' => $company->working_hours,
                'greeting_message' => $company->settings['greeting_message'] ?? '',
                'custom_system_prompt' => $company->custom_system_prompt,
                'gemini_api_key' => $company->settings['gemini_api_key'] ?? '',
                'response_tone' => $company->settings['response_tone'] ?? 'professional_friendly',
                'memory_messages_limit' => (int) ($company->settings['memory_messages_limit'] ?? 12),
                'timezone' => $company->settings['timezone'] ?? 'Asia/Riyadh',
                'auto_pause_on_handoff' => (bool) ($company->settings['auto_pause_on_handoff'] ?? false),
            ]);
        }
    }

    public function getCompany(): ?Company
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        if ($user->isCompanyAdmin()) {
            return $user->company;
        }

        return $user->company ?? Company::first();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('معلومات المنشأة وأتمتة الردود')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('تعريف المحل والخدمات والموقع')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('اسم المتجر / المحل / المنشأة')
                                        ->required()
                                        ->placeholder('مثال: شركة النور لقطع الغيار'),

                                    Forms\Components\TextInput::make('working_hours')
                                        ->label('أوقات وساعات الدوام')
                                        ->placeholder('مثال: السبت إلى الخميس: 8 صباحاً - 8 مساءً')
                                        ->required(),
                                ]),

                                Forms\Components\Textarea::make('about_us')
                                    ->label('نبذة تعريفية عن المحل / المتجر')
                                    ->placeholder('اكتب نبذة مختصرة عن نشاط المحل وسنة التأسيس وما يميزكم ليستخدمها الذكاء الاصطناعي في الرد على العملاء...')
                                    ->helperText('يقرأ الذكاء الاصطناعي هذه النبذة عند سؤال العملاء "من أنتم؟" أو "ما هو نشاطكم؟"')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('services_offered')
                                    ->label('نبذة عن الخدمات والمنتجات التي تقدمها المنشأة')
                                    ->placeholder('مثال: نوفر قطع الغيار الأصلية للسيارات، فحص وبرمجة كمبيوتر، خدمة التوصيل والشحن...')
                                    ->helperText('يستخدمها البوت لتوضيح نوعية الخدمات المتاحة للزبائن بشكل ذكي')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('address')
                                        ->label('الموقع والعنوان الفعلي')
                                        ->placeholder('مثال: صنعاء - شارع الستين الجنوبي - جوار محطة النور')
                                        ->required(),

                                    Forms\Components\TextInput::make('google_maps_url')
                                        ->label('رابط خرائط جوجل (Google Maps)')
                                        ->url()
                                        ->placeholder('https://maps.google.com/?q=...')
                                        ->helperText('يرسله البوت فوراً للعملاء عند سؤالهم "وين موقعكم؟" أو "أرسل لي اللوكيشن"'),
                                ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('أرقام الواتساب والدعم')
                            ->icon('heroicon-o-phone-arrow-up-right')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('whatsapp_number')
                                        ->label('رقم الواتساب لاستقبال الردود الذكية')
                                        ->helperText('الرقم الذي يستقبل رسائل الزبائن ويرد عليهم تلقائياً (بصيغة دولية: 967xxxxxxxxx)')
                                        ->required(),

                                    Forms\Components\TextInput::make('support_phone')
                                        ->label('الرقم المسؤول عن الرد الأولي / التحويل للدعم البشري')
                                        ->helperText('رقم مسؤول المبيعات أو الدعم الذي يتم تحويل العميل إليه عند الطلب أو الاستفسارات الخاصة')
                                        ->placeholder('967770000000'),

                                    Forms\Components\TextInput::make('phone')
                                        ->label('هاتف الاتصال الأرضي / الجوال'),

                                    Forms\Components\TextInput::make('email')
                                        ->label('البريد الإلكتروني الرسمي')
                                        ->email(),
                                ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('التلقين الذكي ورسالة الترحيب')
                            ->icon('heroicon-o-cpu-chip')
                            ->schema([
                                Forms\Components\TextInput::make('greeting_message')
                                    ->label('رسالة الترحيب التلقائية للعميل')
                                    ->placeholder('أهلاً بك في متجرنا! تفضل بالاستفسار عن المنتجات والأسعار المتوفرة.')
                                    ->helperText('أول رسالة تظهر للعميل عند بدء المحادثة')
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('custom_system_prompt')
                                    ->label('التلقين المخصص للذكاء الاصطناعي (System Prompt)')
                                    ->placeholder('أنت الموظف الذكي لمتجر ...، أجب باحترام ودقة بناءً على ملفات الأسعار والخدمات المرفوعة فقط.')
                                    ->helperText('تعليمات خاصة توجه أسلوب البوت ولهجته وطريقة تفاعله مع العملاء')
                                    ->rows(4)
                                    ->columnSpanFull(),

                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Select::make('response_tone')
                                        ->label('نبرة وأسلوب الرد')
                                        ->options([
                                            'professional_friendly' => 'ودود ومهني (موصى به)',
                                            'formal' => 'رسمي ومباشر',
                                            'concise' => 'مختصر وسريع',
                                        ])
                                        ->default('professional_friendly')
                                        ->required(),

                                    Forms\Components\Select::make('memory_messages_limit')
                                        ->label('عمق ذاكرة المحادثة')
                                        ->options([
                                            6 => 'آخر 6 رسائل',
                                            10 => 'آخر 10 رسائل',
                                            12 => 'آخر 12 رسالة (موصى به)',
                                            16 => 'آخر 16 رسالة',
                                            20 => 'آخر 20 رسالة',
                                        ])
                                        ->default(12)
                                        ->required(),

                                    Forms\Components\Select::make('timezone')
                                        ->label('المنطقة الزمنية للمنشأة')
                                        ->options([
                                            'Asia/Riyadh' => 'الرياض (Asia/Riyadh)',
                                            'Asia/Aden' => 'عدن / صنعاء (Asia/Aden)',
                                            'Asia/Dubai' => 'دبي (Asia/Dubai)',
                                            'Asia/Kuwait' => 'الكويت (Asia/Kuwait)',
                                            'Africa/Cairo' => 'القاهرة (Africa/Cairo)',
                                            'UTC' => 'التوقيت العالمي (UTC)',
                                        ])
                                        ->default('Asia/Riyadh')
                                        ->required(),
                                ]),

                                Forms\Components\Toggle::make('auto_pause_on_handoff')
                                    ->label('إيقاف البوت تلقائياً عند طلب التحدث مع موظف بشري')
                                    ->helperText('عند التفعيل، يتوقف البوت عن الرد التلقائي بمجرد أن يطلب العميل موظفاً بشرياً حتى تقوم بإعادة تفعيله.')
                                    ->default(false)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('gemini_api_key')
                                    ->label('مفتاح Google Gemini API Key (اختياري)')
                                    ->password()
                                    ->revealable()
                                    ->placeholder('AIzaSy...')
                                    ->helperText('مفتاح الربط من Google AI Studio، عند إدخاله يتولى Google Gemini صياغة الردود لهذه المنشأة.')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $company = $this->getCompany();
        if (! $company) {
            Notification::make()->title('لم يتم العثور على منشأة')->danger()->send();
            return;
        }

        $formState = $this->form->getState();

        $settings = $company->settings ?? [];
        $settings['greeting_message'] = $formState['greeting_message'] ?? '';
        $settings['gemini_api_key'] = trim($formState['gemini_api_key'] ?? '');
        $settings['response_tone'] = $formState['response_tone'] ?? 'professional_friendly';
        $settings['memory_messages_limit'] = (int) ($formState['memory_messages_limit'] ?? 12);
        $settings['timezone'] = $formState['timezone'] ?? 'Asia/Riyadh';
        $settings['auto_pause_on_handoff'] = (bool) ($formState['auto_pause_on_handoff'] ?? false);

        $company->update([
            'name' => $formState['name'],
            'about_us' => $formState['about_us'] ?? null,
            'services_offered' => $formState['services_offered'] ?? null,
            'phone' => $formState['phone'] ?? null,
            'whatsapp_number' => $formState['whatsapp_number'],
            'support_phone' => $formState['support_phone'] ?? null,
            'email' => $formState['email'] ?? null,
            'address' => $formState['address'] ?? null,
            'google_maps_url' => $formState['google_maps_url'] ?? null,
            'working_hours' => $formState['working_hours'] ?? null,
            'custom_system_prompt' => $formState['custom_system_prompt'] ?? null,
            'settings' => $settings,
        ]);

        Notification::make()
            ->title('تم حفظ وتحديث بيانات المنشأة بنجاح!')
            ->body('الذكاء الاصطناعي يعتمد الآن هذه المعلومات في جميع إجاباته.')
            ->success()
            ->send();
    }
}
