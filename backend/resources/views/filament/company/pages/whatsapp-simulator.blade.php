<x-filament-panels::page>
    @php
        $company = $this->getCompany();
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Company Info & Status -->
        <div class="lg:col-span-1 space-y-4">
            <x-filament::card>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-sky-600 text-white font-bold flex items-center justify-center text-xl shadow">
                        {{ mb_substr($company->name ?? 'C', 0, 1) }}
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white">{{ $company?->name ?? 'المنشأة' }}</h3>
                        <p class="text-xs text-gray-500 font-mono">{{ $company?->whatsapp_number ?? '-' }}</p>
                    </div>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg space-y-2 border border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">حالة الاشتراك:</span>
                            @if($company && $company->isSubscriptionActive())
                                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                    مفعل ونشط
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    متوقف أو منتهي
                                </span>
                            @endif
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">ينتهي في:</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                {{ $company?->subscription_expires_at ? $company->subscription_expires_at->format('d/m/Y') : 'غير محدد' }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">ملفات الأسعار الجاهزة:</span>
                            <span class="font-semibold text-sky-600 dark:text-sky-400">
                                {{ $company ? $company->documents()->where('status', 'ready')->count() : 0 }} ملف معتمد
                            </span>
                        </div>
                    </div>

                    <button type="button" wire:click="resetChat" class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-medium transition">
                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                        بدء محادثة تجريبية جديدة
                    </button>
                </div>
            </x-filament::card>

            <x-filament::card>
                <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-2">💡 تجارب سريعة:</h4>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="$set('inputMessage', 'ما هي أسعار وتوفر المنتجات لديكم؟')" class="text-xs bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 px-2.5 py-1.5 rounded-full hover:bg-sky-100 transition">
                        💰 الأسعار والتوفر؟
                    </button>
                    <button type="button" wire:click="$set('inputMessage', 'أين موقع متجركم؟')" class="text-xs bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 px-2.5 py-1.5 rounded-full hover:bg-sky-100 transition">
                        📍 موقع المتجر؟
                    </button>
                    <button type="button" wire:click="$set('inputMessage', 'متى تبدأ وتنتهي ساعات العمل؟')" class="text-xs bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 px-2.5 py-1.5 rounded-full hover:bg-sky-100 transition">
                        ⏰ ساعات العمل؟
                    </button>
                </div>
            </x-filament::card>
        </div>

        <!-- WhatsApp Chat Window -->
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 shadow-lg overflow-hidden flex flex-col h-[calc(100vh-13.5rem)] min-h-[440px] max-h-[620px] bg-[#efeae2] dark:bg-[#0b141a]"
                 x-data
                 x-init="$nextTick(() => { const el = $refs.chatBox; if (el) el.scrollTop = el.scrollHeight; })"
                 x-effect="const count = {{ count($chatHistory) }}; $nextTick(() => { const el = $refs.chatBox; if (el) el.scrollTop = el.scrollHeight; })">
                <!-- Header -->
                <div class="bg-[#005d4b] dark:bg-[#202c33] text-white px-4 py-3 flex items-center justify-between shadow">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-emerald-600 flex items-center justify-center text-white font-bold text-lg shadow-inner">
                            {{ mb_substr($company->name ?? 'C', 0, 1) }}
                        </div>
                        <div>
                            <h2 class="font-bold text-base leading-tight">{{ $company->name ?? 'البوت الذكي' }}</h2>
                            <p class="text-xs text-emerald-200 dark:text-emerald-400 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                متصل الآن (يقرأ من ملفاتك مباشرة)
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs bg-black/20 px-3 py-1.5 rounded-full text-emerald-100">
                        <x-heroicon-m-sparkles class="w-4 h-4 text-amber-300" />
                        SmartOra AI
                    </div>
                </div>

                <!-- Messages -->
                <div x-ref="chatBox" class="flex-1 p-4 overflow-y-auto space-y-3 smartora-scroll">
                    @forelse($chatHistory as $item)
                        @if($item['sender'] === 'user')
                            <div class="flex justify-end">
                                <div class="max-w-[80%] bg-[#d9fdd3] dark:bg-[#005c4b] text-gray-900 dark:text-gray-100 rounded-2xl rounded-tr-none px-4 py-2.5 shadow-sm">
                                    <p class="text-sm whitespace-pre-wrap leading-relaxed">{{ $item['message'] }}</p>
                                    <div class="text-[10px] text-gray-500 dark:text-gray-300 text-left mt-1 flex items-center justify-end gap-1">
                                        <span>{{ $item['time'] }}</span>
                                        <span class="text-blue-500">✓✓</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="flex justify-start">
                                <div class="max-w-[80%] bg-white dark:bg-[#202c33] text-gray-900 dark:text-gray-100 rounded-2xl rounded-tl-none px-4 py-2.5 shadow-sm border border-gray-100 dark:border-transparent">
                                    <div class="flex items-center gap-1 mb-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                        <x-heroicon-m-bolt class="w-3.5 h-3.5" />
                                        {{ $company?->name ?? 'البوت الذكي' }}
                                    </div>
                                    <p class="text-sm whitespace-pre-wrap leading-relaxed">{{ $item['message'] }}</p>
                                    <div class="text-[10px] text-gray-400 dark:text-gray-400 text-left mt-1">
                                        <span>{{ $item['time'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="text-center py-16 text-gray-400 text-sm">
                            لا توجد رسائل بعد.
                        </div>
                    @endforelse

                    <div wire:loading wire:target="sendMessage" class="flex justify-start">
                        <div class="bg-white dark:bg-[#202c33] text-gray-500 dark:text-gray-400 rounded-2xl rounded-tl-none px-4 py-2 shadow-sm text-xs flex items-center gap-2">
                            <span class="flex gap-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-bounce"></span>
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-bounce [animation-delay:0.2s]"></span>
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-bounce [animation-delay:0.4s]"></span>
                            </span>
                            جاري توليد الرد بالذكاء الاصطناعي بناءً على ملفاتك...
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <div class="bg-white dark:bg-[#202c33] p-3 border-t border-gray-200 dark:border-gray-800 flex items-center gap-2">
                    <input 
                        type="text" 
                        wire:model="inputMessage" 
                        wire:keydown.enter="sendMessage"
                        placeholder="اكتب رسالة تجريبية كعميل للاستفسار عن منتج أو سعر..."
                        class="flex-1 rounded-full border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-[#2a3942] text-gray-900 dark:text-white px-4 py-2.5 text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                    />

                    <button 
                        type="button" 
                        wire:click="sendMessage" 
                        wire:loading.attr="disabled"
                        class="w-10 h-10 rounded-full bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white flex items-center justify-center transition shadow-md disabled:opacity-50"
                    >
                        <x-heroicon-m-paper-airplane class="w-5 h-5 rtl:rotate-180" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
