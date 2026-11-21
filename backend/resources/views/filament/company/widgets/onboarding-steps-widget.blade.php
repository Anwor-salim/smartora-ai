<x-filament-widgets::widget>
    @php
        $company = $this->getCompany();
        $progress = $company ? $company->getOnboardingProgress() : [
            'steps' => [],
            'percentage' => 0,
            'is_fully_onboarded' => false
        ];
    @endphp

    <div class="p-6 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 space-y-6" dir="rtl">
        <!-- Header & Progress bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                        <x-heroicon-m-rocket-launch class="w-6 h-6" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                            معالج إعداد وأتمتة الرد الذكي لمنشأتك
                        </h2>
                        <p class="text-xs text-gray-500">
                            اتبع الخطوات الـ 4 التالية ليصبح بوت الواتساب الخاص بـ ({{ $company?->name }}) جاهزاً للرد على عملائك آلياً!
                        </p>
                    </div>
                </div>
            </div>

            <!-- Percentage Badge -->
            <div class="flex items-center gap-3">
                <div class="text-left">
                    <span class="text-xs text-gray-500 block">نسبة اكتمال الإعداد:</span>
                    <span class="text-lg font-extrabold {{ $progress['percentage'] === 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-sky-600 dark:text-sky-400' }}">
                        {{ $progress['percentage'] }}%
                    </span>
                </div>
                <div class="w-24 bg-gray-100 dark:bg-gray-800 rounded-full h-3 overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div 
                        class="h-full rounded-full transition-all duration-500 {{ $progress['percentage'] === 100 ? 'bg-emerald-500' : 'bg-sky-500' }}" 
                        style="width: {{ $progress['percentage'] }}%"
                    ></div>
                </div>
            </div>
        </div>

        <!-- The 4 Setup Steps Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Step 1: Company Profile & Services -->
            @php $s1 = $progress['steps']['profile'] ?? ['completed' => false, 'url' => '/company/company-settings']; @endphp
            <div class="relative p-4 rounded-xl border {{ $s1['completed'] ? 'border-emerald-200 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40' }} flex flex-col justify-between transition hover:shadow-md">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="w-6 h-6 rounded-full text-xs font-bold flex items-center justify-center {{ $s1['completed'] ? 'bg-emerald-600 text-white' : 'bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                            1
                        </span>
                        @if($s1['completed'])
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                <x-heroicon-m-check-circle class="w-4 h-4 text-emerald-600" />
                                مكتمل
                            </span>
                        @else
                            <span class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                بانتظار الإدخال
                            </span>
                        @endif
                    </div>
                    <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-1">بيانات المحل والخدمات</h3>
                    <p class="text-xs text-gray-500 leading-relaxed mb-3">
                        تعريف عن المتجر، نبذة عن الخدمات، العنوان، ورابط الخريطة وساعات العمل.
                    </p>
                </div>
                <a href="{{ url($s1['url']) }}" class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-3 rounded-lg text-xs font-bold transition {{ $s1['completed'] ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 border border-gray-200 dark:border-gray-700' : 'bg-sky-600 hover:bg-sky-700 text-white shadow' }}">
                    <span>{{ $s1['completed'] ? 'تعديل البيانات' : 'ابدأ الخطوة 1' }}</span>
                    <x-heroicon-m-arrow-left class="w-3.5 h-3.5" />
                </a>
            </div>

            <!-- Step 2: Knowledge Base & Prices -->
            @php $s2 = $progress['steps']['products'] ?? ['completed' => false, 'url' => '/company/knowledge-documents']; @endphp
            <div class="relative p-4 rounded-xl border {{ $s2['completed'] ? 'border-emerald-200 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40' }} flex flex-col justify-between transition hover:shadow-md">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="w-6 h-6 rounded-full text-xs font-bold flex items-center justify-center {{ $s2['completed'] ? 'bg-emerald-600 text-white' : 'bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                            2
                        </span>
                        @if($s2['completed'])
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                <x-heroicon-m-check-circle class="w-4 h-4 text-emerald-600" />
                                تم الرفع
                            </span>
                        @else
                            <span class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                يلزم رفع ملف
                            </span>
                        @endif
                    </div>
                    <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-1">ملف المنتجات والأسعار</h3>
                    <p class="text-xs text-gray-500 leading-relaxed mb-3">
                        رفع ملف Excel أو PDF يحتوي على أسعار وأصناف المتجر ليقرأ منها البوت.
                    </p>
                </div>
                <a href="{{ url($s2['url']) }}" class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-3 rounded-lg text-xs font-bold transition {{ $s2['completed'] ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 border border-gray-200 dark:border-gray-700' : 'bg-sky-600 hover:bg-sky-700 text-white shadow' }}">
                    <span>{{ $s2['completed'] ? 'إدارة الملفات' : 'رفع ملف الأسعار' }}</span>
                    <x-heroicon-m-arrow-left class="w-3.5 h-3.5" />
                </a>
            </div>

            <!-- Step 3: WhatsApp QR Connection -->
            @php $s3 = $progress['steps']['whatsapp'] ?? ['completed' => false, 'url' => '/company/whats-app-connection']; @endphp
            <div class="relative p-4 rounded-xl border {{ $s3['completed'] ? 'border-emerald-200 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40' }} flex flex-col justify-between transition hover:shadow-md">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="w-6 h-6 rounded-full text-xs font-bold flex items-center justify-center {{ $s3['completed'] ? 'bg-emerald-600 text-white' : 'bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                            3
                        </span>
                        @if($s3['completed'])
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                <x-heroicon-m-check-circle class="w-4 h-4 text-emerald-600" />
                                متصل بالواتساب
                            </span>
                        @else
                            <span class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                بانتظار مسح الباركود
                            </span>
                        @endif
                    </div>
                    <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-1">مسح الباركود والربط</h3>
                    <p class="text-xs text-gray-500 leading-relaxed mb-3">
                        مسح رمز QR من واتساب هاتفك وضبط رقم الرد التلقائي ورقم التحويل البشري.
                    </p>
                </div>
                <a href="{{ url($s3['url']) }}" class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-3 rounded-lg text-xs font-bold transition {{ $s3['completed'] ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 border border-gray-200 dark:border-gray-700' : 'bg-emerald-600 hover:bg-emerald-700 text-white shadow' }}">
                    <span>{{ $s3['completed'] ? 'إدارة الاتصال' : 'مسح الباركود الآن' }}</span>
                    <x-heroicon-m-arrow-left class="w-3.5 h-3.5" />
                </a>
            </div>

            <!-- Step 4: AI Simulator Testing -->
            @php $s4 = $progress['steps']['simulator'] ?? ['completed' => false, 'url' => '/company/whats-app-simulator']; @endphp
            <div class="relative p-4 rounded-xl border {{ $s4['completed'] ? 'border-emerald-200 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40' }} flex flex-col justify-between transition hover:shadow-md">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="w-6 h-6 rounded-full text-xs font-bold flex items-center justify-center {{ $s4['completed'] ? 'bg-emerald-600 text-white' : 'bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                            4
                        </span>
                        @if($s4['completed'])
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                <x-heroicon-m-check-circle class="w-4 h-4 text-emerald-600" />
                                تم الاختبار
                            </span>
                        @else
                            <span class="text-[11px] font-semibold text-gray-500">
                                تجربة الردود
                            </span>
                        @endif
                    </div>
                    <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-1">تجربة البوت في المحاكي</h3>
                    <p class="text-xs text-gray-500 leading-relaxed mb-3">
                        اختبار طرح أسئلة كعميل عن المنتجات والأسعار للتأكد من جاهزية البوت.
                    </p>
                </div>
                <a href="{{ url($s4['url']) }}" class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-3 rounded-lg text-xs font-bold transition bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 border border-gray-200 dark:border-gray-700">
                    <span>فتح المحاكي التجريبي</span>
                    <x-heroicon-m-arrow-left class="w-3.5 h-3.5" />
                </a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
