<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LandingController extends Controller
{
    public static function getPlans(): array
    {
        return [
            'free_trial' => [
                'key' => 'free_trial',
                'name' => 'الباقة التجريبية',
                'duration_label' => 'شهر واحد (30 يوم)',
                'duration_days' => 30,
                'monthly_price' => 0,
                'original_monthly_price' => 13,
                'total_price' => 0,
                'price_display' => 'مجاناً',
                'per_month_note' => 'لأول 30 يوماً',
                'discount_badge' => 'عرض البداية · شهر مجاناً',
                'savings_text' => 'ابدأ مجاناً بدون أي رسوم تأسيس',
                'highlighted' => false,
                'cta' => 'ابدأ شهرك المجاني',
                'features' => [
                    'رد آلي ذكي عبر واتساب وانستقرام',
                    'رفع قوائم الأسعار (Excel / PDF)',
                    'لوحة تحكم ومحاكي مباشر للبوت',
                    'دعم فني للتهيئة والربط الأولي',
                ],
            ],
            '3_months' => [
                'key' => '3_months',
                'name' => 'باقة 3 أشهر',
                'duration_label' => '3 أشهر (90 يوم)',
                'duration_days' => 90,
                'monthly_price' => 13,
                'original_monthly_price' => 13,
                'total_price' => 39,
                'price_display' => '$13',
                'per_month_note' => 'شهرياً · الإجمالي $39',
                'discount_badge' => 'انطلاقة مرنة',
                'savings_text' => 'سعر ثابت ومناسب للانطلاقة السريعة',
                'highlighted' => false,
                'cta' => 'اختر باقة 3 أشهر',
                'features' => [
                    'كافة مميزات البوت الذكي 24/7',
                    'ربط واتساب (QR أو Meta الرسمي)',
                    'رد تلقائي على الخاص وتعليقات انستقرام',
                    'تحديثات مستمرة لملفات الأسعار',
                ],
            ],
            '6_months' => [
                'key' => '6_months',
                'name' => 'باقة 6 أشهر',
                'duration_label' => '6 أشهر (180 يوم)',
                'duration_days' => 180,
                'monthly_price' => 12,
                'original_monthly_price' => 13,
                'total_price' => 72,
                'price_display' => '$12',
                'per_month_note' => 'شهرياً · الإجمالي $72',
                'discount_badge' => 'الأكثر طلباً · خصم $1/شهر',
                'savings_text' => 'توفير $6 مقارنة بالسعر الشهري',
                'highlighted' => true,
                'cta' => 'اختر باقة 6 أشهر',
                'features' => [
                    'خصم $1 على كل شهر ($12 بدلاً من $13)',
                    'كافة مميزات واتساب وانستقرام كاملة',
                    'أولوية في الدعم الفني والمساعدة بالربط',
                    'السعر قابل للنقاش حسب حجم النشاط',
                ],
            ],
            'yearly' => [
                'key' => 'yearly',
                'name' => 'الباقة السنوية',
                'duration_label' => 'سنة كاملة (365 يوم)',
                'duration_days' => 365,
                'monthly_price' => 11,
                'original_monthly_price' => 13,
                'total_price' => 132,
                'price_display' => '$11',
                'per_month_note' => 'شهرياً · الإجمالي $132',
                'discount_badge' => 'أفضل توفير · خصم $2/شهر',
                'savings_text' => 'توفير $24 سنوياً مقارنة بالسعر الشهري',
                'highlighted' => false,
                'cta' => 'اختر الباقة السنوية',
                'features' => [
                    'خصم $2 على كل شهر ($11 بدلاً من $13)',
                    'استقرار سنوي كامل لكافة القنوات',
                    'تهيئة ومراجعة مجانية لملفات المعرفة',
                    'السعر قابل للنقاش والتخصيص للشركات',
                ],
            ],
        ];
    }

    public function resolveSalesWhatsapp(): string
    {
        $raw = env('SALES_WHATSAPP_NUMBER');

        if (! $raw) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
                    $raw = User::where('role', 'super_admin')->value('phone');
                }
            } catch (\Throwable) {
                $raw = null;
            }
        }

        $digits = preg_replace('/\D/', '', (string) ($raw ?: '967771314738'));

        return $digits !== '' ? $digits : '967771314738';
    }

    public function index(Request $request): View
    {
        $plans = self::getPlans();
        $requestedPlan = (string) $request->query('plan', 'free_trial');
        $selectedPlan = array_key_exists($requestedPlan, $plans) ? $requestedPlan : 'free_trial';

        return view('landing', [
            'plans' => $plans,
            'salesWhatsapp' => $this->resolveSalesWhatsapp(),
            'selectedPlan' => $selectedPlan,
            'scrollToRegister' => false,
        ]);
    }

    public function registerPage(Request $request): View
    {
        $plans = self::getPlans();
        $requestedPlan = (string) $request->query('plan', 'free_trial');
        $selectedPlan = array_key_exists($requestedPlan, $plans) ? $requestedPlan : 'free_trial';

        return view('landing', [
            'plans' => $plans,
            'salesWhatsapp' => $this->resolveSalesWhatsapp(),
            'selectedPlan' => $selectedPlan,
            'scrollToRegister' => true,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'min:2', 'max:150'],
            'owner_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:7', 'max:30'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'subscription_plan' => ['required', 'in:free_trial,3_months,6_months,yearly'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'company_name.required' => 'يرجى إدخال اسم الشركة أو المتجر.',
            'owner_name.required' => 'يرجى إدخال اسم المسؤول.',
            'email.required' => 'يرجى إدخال البريد الإلكتروني.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique' => 'هذا البريد الإلكتروني مسجل مسبقاً.',
            'phone.required' => 'يرجى إدخال رقم الهاتف للتواصل.',
            'subscription_plan.required' => 'يرجى اختيار الباقة المطلوبة.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
        ]);

        $cleanWhatsapp = preg_replace('/\D/', '', (string) ($validated['whatsapp_number'] ?? ''));
        $cleanWhatsapp = $cleanWhatsapp !== '' ? $cleanWhatsapp : null;

        if ($cleanWhatsapp !== null && Company::where('whatsapp_number', $cleanWhatsapp)->exists()) {
            throw ValidationException::withMessages([
                'whatsapp_number' => 'رقم الواتساب هذا مسجل مسبقاً لمنشأة أخرى.',
            ]);
        }

        $plans = self::getPlans();
        $planMeta = $plans[$validated['subscription_plan']] ?? $plans['free_trial'];
        $cleanEmail = strtolower(trim($validated['email']));

        $result = DB::transaction(function () use ($validated, $cleanWhatsapp, $cleanEmail, $planMeta) {
            $baseSlug = Str::slug($validated['company_name']) ?: ('company-' . Str::lower(Str::random(6)));
            $slug = $baseSlug;
            $counter = 2;
            while (Company::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $company = Company::create([
                'name' => trim($validated['company_name']),
                'slug' => $slug,
                'phone' => trim($validated['phone']),
                'whatsapp_number' => $cleanWhatsapp,
                'email' => $cleanEmail,
                'status' => 'pending',
                'subscription_plan' => $validated['subscription_plan'],
                'subscription_starts_at' => null,
                'subscription_expires_at' => null,
                'settings' => [
                    'registered_via' => 'landing',
                    'registration_notes' => !empty($validated['notes']) ? trim($validated['notes']) : null,
                ],
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'name' => trim($validated['owner_name']),
                'email' => $cleanEmail,
                'password' => Hash::make($validated['password']),
                'role' => 'company_admin',
                'phone' => trim($validated['phone']),
                'status' => 'inactive',
            ]);

            SystemNotification::create([
                'company_id' => $company->id,
                'title' => 'طلب تسجيل شركة جديدة بانتظار التفعيل',
                'message' => "سجلت المنشأة \"{$company->name}\" ({$user->email}) على باقة ({$planMeta['name']}) وتنتظر مراجعة وتفعيل الإدارة.",
                'type' => 'warning',
                'is_read' => false,
            ]);

            return compact('company', 'user');
        });

        return redirect()
            ->to(url('/#register'))
            ->with('registration_success', [
                'company_name' => $result['company']->name,
                'email' => $result['user']->email,
                'plan_label' => $planMeta['name'],
            ]);
    }
}
