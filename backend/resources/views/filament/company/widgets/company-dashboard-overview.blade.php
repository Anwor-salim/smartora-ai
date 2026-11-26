<x-filament-widgets::widget>
    @php
        $company = $this->getCompany();
        $conversations = $this->getRecentConversations();
        $waConnected = $company?->isWhatsAppConnected() ?? false;
        $igConnected = $company?->isInstagramConnected() ?? false;
        $fbConnected = $company?->isMessengerConnected() ?? false;
        $docsCount = $company ? $company->documents()->where('status', 'ready')->count() : 0;
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5" dir="rtl">
        <!-- Main Column: Recent Customer Conversations -->
        <div class="lg:col-span-8 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                        <x-heroicon-o-chat-bubble-left-right class="w-4.5 h-4.5" />
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">أحدث محادثات العملاء</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">آخر تفاعلات الزبائن مع البوت عبر واتساب وانستقرام وماسنجر</p>
                    </div>
                </div>

                <a href="/company/conversations" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition shrink-0">
                    <span>عرض الكل</span>
                </a>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800/80 flex-1">
                @forelse($conversations as $conv)
                    @php
                        $isIg = $conv->channel === 'instagram';
                        $isFb = $conv->channel === 'messenger';
                        $badgeClasses = $isIg
                            ? 'bg-pink-50 text-pink-700 dark:bg-pink-950/60 dark:text-pink-300'
                            : ($isFb ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300');
                        $channelName = $isIg ? 'انستقرام' : ($isFb ? 'ماسنجر' : 'واتساب');
                        $customerDisplay = (string) preg_replace('/^(ig|fb):/', '', (string) $conv->customer_phone);
                    @endphp
                    <a href="/company/conversations/{{ $conv->id }}" class="px-5 py-3 flex items-center justify-between gap-4 hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span class="px-2 py-1 rounded-lg text-[11px] font-bold shrink-0 {{ $badgeClasses }}">
                                {{ $channelName }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900 dark:text-white truncate">
                                        {{ $conv->customer_name ?: $customerDisplay }}
                                    </span>
                                    @if($conv->customer_name && $customerDisplay)
                                        <span class="text-[11px] font-mono text-slate-400 truncate" dir="ltr">{{ $customerDisplay }}</span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">
                                    {{ $conv->last_message ?: '—' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0 text-[11px]">
                            <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-semibold">
                                {{ $conv->messages_count }} رسالة
                            </span>
                            <span class="text-slate-400 hidden sm:inline">
                                {{ $conv->last_interaction_at?->diffForHumans() }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="p-8 text-center space-y-3">
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            لا توجد محادثات مع العملاء حتى الآن. جرّب التحدث مع البوت في المحاكي للتأكد من جاهزية الردود!
                        </p>
                        <a href="/company/whats-app-simulator" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm">
                            <x-heroicon-o-sparkles class="w-4 h-4" />
                            <span>تجربة محاكي البوت</span>
                        </a>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Side Column: Channel Status & Quick Access -->
        <div class="lg:col-span-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col justify-between">
            <div>
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <x-heroicon-o-signal class="w-4.5 h-4.5" />
                    </span>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">حالة القنوات والوصول السريع</h3>
                </div>

                <div class="p-4 space-y-2.5">
                    <!-- WhatsApp -->
                    <a href="/company/whats-app-connection" class="flex items-center justify-between gap-3 p-3 rounded-xl border border-slate-200/80 dark:border-slate-800 hover:border-sky-400 transition bg-slate-50/50 dark:bg-slate-800/40">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $waConnected ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-900 dark:text-white">واتساب</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                    {{ $waConnected ? ($company?->whatsapp_provider === 'meta_cloud' ? 'متصل · Meta API' : 'متصل · QR') : 'غير مربوط بعد' }}
                                </div>
                            </div>
                        </div>
                        <span class="text-[11px] font-semibold text-sky-600 dark:text-sky-400 shrink-0">إدارة</span>
                    </a>

                    <!-- Instagram -->
                    <a href="/company/instagram-connection" class="flex items-center justify-between gap-3 p-3 rounded-xl border border-slate-200/80 dark:border-slate-800 hover:border-pink-400 transition bg-slate-50/50 dark:bg-slate-800/40">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $igConnected ? 'bg-pink-500' : 'bg-slate-300 dark:bg-slate-600' }}"></span>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-900 dark:text-white">انستقرام DM</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate" dir="ltr">
                                    {{ $igConnected && $company?->instagram_username ? '@' . ltrim($company->instagram_username, '@') : ($igConnected ? 'متصل' : 'غير مربوط') }}
                                </div>
                            </div>
                        </div>
                        <span class="text-[11px] font-semibold text-pink-600 dark:text-pink-400 shrink-0">إدارة</span>
                    </a>

                    <!-- Facebook Messenger -->
                    <a href="/company/messenger-connection" class="flex items-center justify-between gap-3 p-3 rounded-xl border border-slate-200/80 dark:border-slate-800 hover:border-blue-400 transition bg-slate-50/50 dark:bg-slate-800/40">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $fbConnected ? 'bg-blue-500' : 'bg-slate-300 dark:bg-slate-600' }}"></span>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-900 dark:text-white">ماسنجر فيسبوك</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                    {{ $fbConnected && $company?->messenger_page_name ? $company->messenger_page_name : ($fbConnected ? 'متصل' : 'غير مربوط') }}
                                </div>
                            </div>
                        </div>
                        <span class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 shrink-0">إدارة</span>
                    </a>

                    <!-- Knowledge Base -->
                    <a href="/company/knowledge-documents" class="flex items-center justify-between gap-3 p-3 rounded-xl border border-slate-200/80 dark:border-slate-800 hover:border-sky-400 transition bg-slate-50/50 dark:bg-slate-800/40">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $docsCount > 0 ? 'bg-sky-500' : 'bg-amber-400' }}"></span>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-900 dark:text-white">قاعدة المعرفة</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                    {{ $docsCount }} ملف أسعار ومعلومات جاهز
                                </div>
                            </div>
                        </div>
                        <span class="text-[11px] font-semibold text-sky-600 dark:text-sky-400 shrink-0">رفع ملف</span>
                    </a>
                </div>
            </div>

            <div class="p-4 pt-0">
                <a href="/company/whats-app-simulator" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold bg-sky-600 hover:bg-sky-700 text-white shadow-sm transition">
                    <x-heroicon-o-sparkles class="w-4 h-4" />
                    <span>تجربة محاكي البوت</span>
                </a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
