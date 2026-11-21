<x-filament-panels::page>
    @php
        $company = $this->getSelectedCompany();
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Settings & Company Selector Panel -->
        <div class="lg:col-span-1 space-y-4">
            <x-filament::card>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <x-heroicon-o-building-office-2 class="w-5 h-5 text-emerald-500" />
                    اختيار الشركة للتجربة
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">المنشأة المستهدفة</label>
                        <select wire:model.live="selectedCompanyId" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            @foreach(\App\Models\Company::all() as $comp)
                                <option value="{{ $comp->id }}">{{ $comp->name }} ({{ $comp->whatsapp_number }})</option>
                            @endforeach
                        </select>
                    </div>

                    @if($company)
                        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg space-y-2 text-sm border border-gray-200 dark:border-gray-700">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">حالة الاشتراك:</span>
                                @if($company->isSubscriptionActive())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                        ● نشط ومتصل
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        ● متوقف / منتهي
                                    </span>
                                @endif
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">رقم واتساب الشركة:</span>
                                <span class="font-mono text-gray-900 dark:text-gray-100">{{ $company->whatsapp_number }}</span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">ملفات المعرفة:</span>
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $company->documents->count() }} ملف جاهز</span>
                            </div>

                            <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-xs text-gray-500 block mb-1">ملخص التلقين (System Prompt):</span>
                                <p class="text-xs text-gray-700 dark:text-gray-300 italic line-clamp-3">
                                    {{ $company->custom_system_prompt ?? 'التلقين الافتراضي لسمارتورا' }}
                                </p>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">رقم هاتف العميل الافتراضي</label>
                        <input type="text" wire:model="customerPhone" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm font-mono text-sm" />
                    </div>

                    <button type="button" wire:click="resetChat" class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-medium transition">
                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                        إعادة تعيين المحادثة
                    </button>
                </div>
            </x-filament::card>

            <!-- Quick Questions Panel -->
            <x-filament::card>
                <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-2">⚡ أسئلة سريعة للتجربة:</h4>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="$set('inputMessage', 'ما هي ساعات العمل لديكم؟')" class="text-xs bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 px-2.5 py-1.5 rounded-full hover:bg-emerald-100 transition">
                        🕒 ساعات العمل؟
                    </button>
                    <button type="button" wire:click="$set('inputMessage', 'أين موقعكم وهل عندكم رابط خريطة؟')" class="text-xs bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 px-2.5 py-1.5 rounded-full hover:bg-emerald-100 transition">
                        📍 الموقع والخريطة؟
                    </button>
                    <button type="button" wire:click="$set('inputMessage', 'هل تتوفر فحمات فرامل وكم سعرها؟')" class="text-xs bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 px-2.5 py-1.5 rounded-full hover:bg-emerald-100 transition">
                        🚗 توفر قطع وأسعار؟
                    </button>
                    <button type="button" wire:click="$set('inputMessage', 'ما هي شروط وسياسة الضمان لديكم؟')" class="text-xs bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 px-2.5 py-1.5 rounded-full hover:bg-emerald-100 transition">
                        🛡️ سياسة الضمان؟
                    </button>
                </div>
            </x-filament::card>
        </div>

        <!-- WhatsApp Chat Simulator Window -->
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xl overflow-hidden flex flex-col h-[680px] bg-[#efeae2] dark:bg-[#0b141a]">
                <!-- Chat Header -->
                <div class="bg-[#005d4b] dark:bg-[#202c33] text-white px-4 py-3 flex items-center justify-between shadow">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-emerald-600 flex items-center justify-center text-white font-bold text-lg shadow-inner">
                            {{ mb_substr($company->name ?? 'S', 0, 1) }}
                        </div>
                        <div>
                            <h2 class="font-bold text-base leading-tight">{{ $company->name ?? 'بوت سمارتورا الذكي' }}</h2>
                            <p class="text-xs text-emerald-200 dark:text-emerald-400 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                بوت الرد التلقائي متصل ومستعد
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs bg-black/20 px-3 py-1.5 rounded-full text-emerald-100">
                        <x-heroicon-m-sparkles class="w-4 h-4 text-amber-300" />
                        مدعوم بمحرك سمارتورا AI
                    </div>
                </div>

                <!-- Chat Messages Body -->
                <div class="flex-1 p-4 overflow-y-auto space-y-3" id="chat-box">
                    @forelse($chatHistory as $item)
                        @if($item['sender'] === 'user')
                            <!-- Customer Message (Right aligned in RTL) -->
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
                            <!-- Bot Message (Left aligned in RTL) -->
                            <div class="flex justify-start">
                                <div class="max-w-[80%] bg-white dark:bg-[#202c33] text-gray-900 dark:text-gray-100 rounded-2xl rounded-tl-none px-4 py-2.5 shadow-sm border border-gray-100 dark:border-transparent">
                                    <div class="flex items-center gap-1 mb-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                        <x-heroicon-m-bolt class="w-3.5 h-3.5" />
                                        {{ $company->name ?? 'سمارتورا AI' }}
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
                            لا توجد رسائل بعد. ابدأ بكتابة رسالة للاختبار.
                        </div>
                    @endforelse

                    <!-- Loading Indicator -->
                    <div wire:loading wire:target="sendMessage" class="flex justify-start">
                        <div class="bg-white dark:bg-[#202c33] text-gray-500 dark:text-gray-400 rounded-2xl rounded-tl-none px-4 py-2 shadow-sm text-xs flex items-center gap-2">
                            <span class="flex gap-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-bounce"></span>
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-bounce [animation-delay:0.2s]"></span>
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-bounce [animation-delay:0.4s]"></span>
                            </span>
                            جاري قراءة البيانات وتوليد الرد بالذكاء الاصطناعي...
                        </div>
                    </div>
                </div>

                <!-- Chat Input Bar -->
                <div class="bg-white dark:bg-[#202c33] p-3 border-t border-gray-200 dark:border-gray-800 flex items-center gap-2">
                    <input 
                        type="text" 
                        wire:model="inputMessage" 
                        wire:keydown.enter="sendMessage"
                        placeholder="اكتب سؤالك كعميل هنا (اضغط Enter للإرسال)..."
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
