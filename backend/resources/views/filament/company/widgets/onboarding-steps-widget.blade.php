<x-filament-widgets::widget>
    @php
        $company = $this->getCompany();
        $progress = $company ? $company->getOnboardingProgress() : [
            'steps' => [],
            'percentage' => 0,
            'is_fully_onboarded' => false,
        ];
        $steps = $progress['steps'] ?? [];
        $stepMeta = [
            'profile' => ['num' => 1, 'label' => 'بيانات المنشأة', 'cta' => 'إكمال البيانات'],
            'products' => ['num' => 2, 'label' => 'ملفات الأسعار', 'cta' => 'رفع ملف'],
            'whatsapp' => ['num' => 3, 'label' => 'ربط الواتساب', 'cta' => 'ربط القناة'],
            'simulator' => ['num' => 4, 'label' => 'تجربة المحاكي', 'cta' => 'بدء الاختبار'],
        ];
    @endphp

    <div class="p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3" dir="rtl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                    <x-heroicon-m-rocket-launch class="w-4.5 h-4.5" />
                </span>
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">خطوات تفعيل البوت الذكي لمنشأة {{ $company?->name }}</h2>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">أكمل الخطوات المتبقية ليبدأ النظام بالرد على عملائك تلقائياً</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <span class="text-xs font-extrabold text-sky-600 dark:text-sky-400">{{ $progress['percentage'] }}%</span>
                <div class="w-28 bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                    <div class="h-full rounded-full bg-sky-500 transition-all duration-500" style="width: {{ $progress['percentage'] }}%"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
            @foreach($stepMeta as $key => $meta)
                @php
                    $step = $steps[$key] ?? ['completed' => false, 'url' => '#'];
                    $done = (bool) ($step['completed'] ?? false);
                @endphp
                <a href="{{ url($step['url']) }}"
                   class="group flex items-center justify-between gap-2.5 px-3.5 py-2.5 rounded-xl border transition {{ $done ? 'border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/50 hover:border-sky-400' }}">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-6 h-6 rounded-full text-[11px] font-bold flex items-center justify-center shrink-0 {{ $done ? 'bg-emerald-600 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200' }}">
                            @if($done)
                                <x-heroicon-m-check class="w-3.5 h-3.5" />
                            @else
                                {{ $meta['num'] }}
                            @endif
                        </span>
                        <span class="text-xs font-bold truncate {{ $done ? 'text-emerald-800 dark:text-emerald-300' : 'text-slate-800 dark:text-slate-100' }}">
                            {{ $meta['label'] }}
                        </span>
                    </div>
                    <span class="text-[11px] font-semibold shrink-0 {{ $done ? 'text-emerald-600 dark:text-emerald-400' : 'text-sky-600 dark:text-sky-400 group-hover:translate-x-[-2px] transition-transform' }}">
                        {{ $done ? 'مكتمل' : $meta['cta'] }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
