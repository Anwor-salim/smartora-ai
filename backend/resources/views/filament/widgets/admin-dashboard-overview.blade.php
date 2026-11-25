<x-filament-widgets::widget>
    @php
        $companies = $this->getCompanies();
        $conversations = $this->getRecentConversations();
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5" dir="rtl">
        <!-- Main Column: Companies Quick View -->
        <div class="lg:col-span-8 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <x-heroicon-o-building-office-2 class="w-4.5 h-4.5" />
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">الشركات المسجلة</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">حالة القنوات والاشتراكات والدخول السريع لبوابة كل منشأة</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <a href="/admin/companies/create" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white transition">
                        <x-heroicon-m-plus class="w-3.5 h-3.5" />
                        <span>إضافة شركة</span>
                    </a>
                    <a href="/admin/companies" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition">
                        <span>عرض الكل</span>
                    </a>
                </div>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800/80 flex-1">
                @forelse($companies as $company)
                    @php
                        $adminEmail = $company->users->first()?->email ?? $company->email ?? 'بدون بريد';
                        $isPending = $company->status === 'pending';
                        $isActive = $company->isSubscriptionActive();
                        $waConnected = $company->isWhatsAppConnected();
                        $igConnected = $company->isInstagramConnected();
                        $daysLeft = $company->subscription_expires_at
                            ? (int) now()->diffInDays($company->subscription_expires_at, false)
                            : null;
                    @endphp
                    <div class="px-5 py-3 flex items-center justify-between gap-4 hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-sm flex items-center justify-center shrink-0">
                                {{ mb_substr($company->name, 0, 1) }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <a href="/admin/companies/{{ $company->id }}/edit" class="text-sm font-bold text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 truncate">
                                        {{ $company->name }}
                                    </a>
                                    @if($isPending)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">
                                            بانتظار التفعيل
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $isActive ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' }}">
                                            {{ $isActive ? ($daysLeft !== null ? "نشط · {$daysLeft}ي" : 'نشط') : 'متوقف / منتهي' }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">
                                    {{ $adminEmail }}
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:flex items-center gap-3 shrink-0 text-[11px]">
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg {{ $waConnected ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $waConnected ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                واتساب
                            </span>
                            @if($igConnected)
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-pink-50 text-pink-700 dark:bg-pink-950/50 dark:text-pink-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-pink-500"></span>
                                    انستقرام
                                </span>
                            @endif
                        </div>

                        <div class="hidden md:flex items-center gap-2 shrink-0 text-[11px] text-slate-500 dark:text-slate-400">
                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-medium">{{ $company->documents_count }} ملف</span>
                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-medium">{{ $company->conversations_count }} محادثة</span>
                        </div>

                        @if($isPending)
                            <a href="/admin/companies?activeTab=pending"
                               class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/50 dark:hover:bg-amber-900/60 text-amber-700 dark:text-amber-300 border border-amber-200/70 dark:border-amber-800/60 transition shrink-0">
                                <span>مراجعة وتفعيل</span>
                            </a>
                        @else
                            <a href="{{ route('admin.switch-company', ['company' => $company->id]) }}"
                               target="_blank"
                               class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/70 dark:border-emerald-800/60 transition shrink-0">
                                <span>دخول البوابة</span>
                                <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5" />
                            </a>
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-slate-500 dark:text-slate-400">
                        لا توجد شركات مسجلة حتى الآن. ابدأ بإضافة أول شركة.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Side Column: Recent Conversations & Quick Tools -->
        <div class="lg:col-span-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col justify-between">
            <div>
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                            <x-heroicon-o-chat-bubble-left-right class="w-4.5 h-4.5" />
                        </span>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">أحدث المحادثات</h3>
                    </div>
                    <a href="/admin/conversations" class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                        عرض الكل
                    </a>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse($conversations as $conv)
                        @php
                            $isIg = $conv->channel === 'instagram';
                            $customerDisplay = str_starts_with((string) $conv->customer_phone, 'ig:')
                                ? substr((string) $conv->customer_phone, 3)
                                : $conv->customer_phone;
                        @endphp
                        <a href="/admin/conversations/{{ $conv->id }}" class="block px-4 py-2.5 hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold shrink-0 {{ $isIg ? 'bg-pink-50 text-pink-700 dark:bg-pink-950/60 dark:text-pink-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' }}">
                                        {{ $isIg ? 'انستقرام' : 'واتساب' }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate">
                                        {{ $conv->customer_name ?: $customerDisplay }}
                                    </span>
                                </div>
                                <span class="text-[10px] text-slate-400 shrink-0">
                                    {{ $conv->last_interaction_at?->diffForHumans() }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-2 mt-1">
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                    {{ $conv->last_message ?: '—' }}
                                </p>
                                <span class="text-[10px] text-slate-400 shrink-0 truncate max-w-[90px]">
                                    {{ $conv->company?->name }}
                                </span>
                            </div>
                        </a>
                    @empty
                        <div class="p-6 text-center text-xs text-slate-500 dark:text-slate-400">
                            لا توجد محادثات مسجلة بعد.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="p-3.5 border-t border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/60 grid grid-cols-2 gap-2">
                <a href="/admin/ai-simulator" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-emerald-400 transition">
                    <x-heroicon-o-sparkles class="w-4 h-4 text-emerald-500" />
                    <span>محاكي البوت</span>
                </a>
                <a href="/admin/system-notifications" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-amber-400 transition">
                    <x-heroicon-o-bell-alert class="w-4 h-4 text-amber-500" />
                    <span>التنبيهات</span>
                </a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
