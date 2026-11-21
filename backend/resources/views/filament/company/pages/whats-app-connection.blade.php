<x-filament-panels::page>
    @php
        $company = $this->getCompany();
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6" dir="rtl">
        <!-- Main Connection Card (QR Scanner & Pairing) -->
        <div class="lg:col-span-7 space-y-6">
            <x-filament::card>
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-4 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <x-heroicon-o-qr-code class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">ربط جهاز الواتساب لمحل / منشأة: {{ $company?->name }}</h3>
                            <p class="text-xs text-gray-500">امسح رمز الاستجابة السريعة (QR) لبدء استقبال الرسائل وأتمتة الردود</p>
                        </div>
                    </div>

                    @if($connectionStatus === 'connected')
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            متصل ونشط
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                            بانتظار مسح الباركود
                        </span>
                    @endif
                </div>

                @if($connectionStatus === 'connected')
                    <!-- Active Connected Session State -->
                    <div class="p-6 bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-950/30 dark:to-teal-950/20 rounded-2xl border border-emerald-200 dark:border-emerald-800 text-center space-y-4">
                        <div class="w-16 h-16 rounded-full bg-emerald-600 text-white mx-auto flex items-center justify-center shadow-lg">
                            <x-heroicon-m-check class="w-10 h-10" />
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-gray-900 dark:text-white">رقم الواتساب متصل بنجاح بمحرك سمارتورا!</h4>
                            <p class="text-sm font-mono text-emerald-700 dark:text-emerald-300 mt-1 font-semibold">
                                {{ $whatsappNumber }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">تاريخ ووقت الربط: {{ $connectedAt ?? now()->format('d/m/Y H:i') }}</p>
                        </div>

                        <div class="pt-4 border-t border-emerald-200 dark:border-emerald-800 flex justify-center gap-3">
                            <button type="button" wire:click="disconnectWhatsApp" class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 dark:bg-red-950/50 text-red-700 dark:text-red-300 text-xs font-semibold rounded-lg border border-red-200 dark:border-red-800 transition">
                                <x-heroicon-o-arrow-left-on-rectangle class="w-4 h-4" />
                                فصل جلسة الواتساب الحالية
                            </button>
                        </div>
                    </div>
                @else
                    <!-- QR Code Scanning State -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                        <!-- QR Image Container -->
                        <div class="text-center p-4 bg-gray-50 dark:bg-gray-800/80 rounded-2xl border border-gray-200 dark:border-gray-700">
                            @if($qrCodeUrl)
                                <div class="relative inline-block p-3 bg-white rounded-xl shadow-md border border-gray-100">
                                    <img src="{{ $qrCodeUrl }}" alt="WhatsApp QR Code" class="w-56 h-56 mx-auto rounded-lg object-contain" />
                                    <div class="absolute inset-0 border-2 border-emerald-500 rounded-xl pointer-events-none opacity-40 animate-pulse"></div>
                                </div>
                            @else
                                <div class="w-56 h-56 mx-auto bg-gray-200 dark:bg-gray-700 rounded-xl flex items-center justify-center text-gray-400">
                                    جاري تجهيز الرمز...
                                </div>
                            @endif

                            <div class="mt-3 flex justify-center gap-2">
                                <button type="button" wire:click="generateQr" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-xs rounded-lg text-gray-700 dark:text-gray-200 transition">
                                    <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                                    تحديث الرمز
                                </button>
                                <button type="button" wire:click="confirmConnection" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                                    <x-heroicon-m-check class="w-3.5 h-3.5" />
                                    تم المسح والربط
                                </button>
                            </div>
                        </div>

                        <!-- Instructions -->
                        <div class="space-y-4">
                            <h4 class="font-bold text-sm text-gray-800 dark:text-gray-200">خطوات ربط الواتساب في هاتفك:</h4>
                            <ol class="space-y-3 text-xs text-gray-600 dark:text-gray-300">
                                <li class="flex items-start gap-2">
                                    <span class="w-5 h-5 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">1</span>
                                    <span>افتح تطبيق **WhatsApp** على هاتفك الخاص بالمنشأة.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="w-5 h-5 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">2</span>
                                    <span>انقر على **الخيارات الثلاث نقاط ( ⋮ )** أو الإعدادات ثم اختر **الأجهزة المرتبطة (Linked Devices)**.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="w-5 h-5 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">3</span>
                                    <span>اضغط على **ربط جهاز (Link a device)** ووجّه كاميرا هاتفك نحو رمز الباركود المعروض في الشاشة.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="w-5 h-5 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">4</span>
                                    <span>بمجرد المسح، اضغط زر **"تم المسح والربط"** لتأكيد تنشيط الرد الآلي للرقم فورياً!</span>
                                </li>
                            </ol>
                        </div>
                    </div>
                @endif
            </x-filament::card>

            <!-- Numbers Configuration Form -->
            <x-filament::card>
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <x-heroicon-o-phone class="w-5 h-5 text-sky-500" />
                    أرقام الرد الآلي والمسؤول الأولي
                </h3>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                رقم الواتساب المربوط (الذي يرد آلياً) <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                wire:model="whatsappNumber" 
                                placeholder="مثال: 967771314738" 
                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-sm font-mono focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            <p class="text-[11px] text-gray-500 mt-1">الرقم المستلم للرسائل الذي يقوم الذكاء الاصطناعي بالرد من خلاله.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                الرقم المسؤول عن الرد الأولي / التحويل البشري
                            </label>
                            <input 
                                type="text" 
                                wire:model="supportPhone" 
                                placeholder="مثال: 967770001122" 
                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-sm font-mono focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            <p class="text-[11px] text-gray-500 mt-1">رقم مسؤول المبيعات الذي يرسله البوت للزبون عند طلب التحدث مع إنسان.</p>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="button" wire:click="confirmConnection" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold shadow transition">
                            <x-heroicon-m-check-badge class="w-4 h-4" />
                            حفظ وتحديث الأرقام
                        </button>
                    </div>
                </div>
            </x-filament::card>
        </div>

        <!-- Sidebar (Test Automation & Webhook Info) -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Test Sending Message -->
            <x-filament::card>
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                    <x-heroicon-o-paper-airplane class="w-5 h-5 text-emerald-500 rtl:rotate-180" />
                    اختبار إرسال رسالة وتفاعل الأتمتة
                </h3>
                <p class="text-xs text-gray-500 mb-4">جرّب إرسال رسالة تجريبية للتأكد من أن النظام يستقبل الرسائل ويرد عليها بنجاح.</p>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">رقم المستلم للتجربة</label>
                        <input 
                            type="text" 
                            wire:model="testPhoneNumber" 
                            placeholder="اكتب رقمك (مثال: 96777000111)" 
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs font-mono"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">نص الرسالة</label>
                        <textarea 
                            wire:model="testMessage" 
                            rows="2" 
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs"
                        ></textarea>
                    </div>

                    <button type="button" wire:click="sendTestMessage" class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-lg shadow transition">
                        <x-heroicon-m-bolt class="w-4 h-4" />
                        إرسال الرسالة واختبار استجابة البوت
                    </button>
                </div>
            </x-filament::card>

            <!-- Webhook & n8n Automation Credentials -->
            <x-filament::card>
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                    <x-heroicon-o-command-line class="w-5 h-5 text-purple-500" />
                    نقطة ربط الأتمتة (Webhook / n8n)
                </h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-gray-500 block mb-1">رابط الويب هوك المخصص (Webhook URL):</span>
                        <div class="p-2.5 bg-gray-50 dark:bg-gray-900 font-mono text-[11px] rounded-lg border border-gray-200 dark:border-gray-800 select-all break-all text-emerald-600 dark:text-emerald-400">
                            {{ url('/api/webhook/whatsapp') }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-gray-500 block mb-1">طريقة الإرسال:</span>
                            <span class="inline-block px-2 py-1 bg-blue-100 dark:bg-blue-950 text-blue-800 dark:text-blue-300 rounded font-mono font-bold">
                                POST (JSON)
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-500 block mb-1">معرّف المنشأة (Slug):</span>
                            <span class="font-mono text-gray-800 dark:text-gray-200 font-bold">
                                {{ $company?->slug }}
                            </span>
                        </div>
                    </div>

                    <div class="p-3 bg-purple-50 dark:bg-purple-950/40 rounded-lg border border-purple-200 dark:border-purple-800/60 text-purple-900 dark:text-purple-200 space-y-1">
                        <span class="font-bold flex items-center gap-1 text-[11px]">
                            <x-heroicon-m-sparkles class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
                            كيف تعمل الأتمتة؟
                        </span>
                        <p class="text-[11px] leading-relaxed">
                            يقوم خادم n8n أو مزود خدمة الواتساب بإرسال رسائل العملاء القادمة للرابط أعلاه متضمنة رقم المرسل والرقم المستلم. يتعرف سمارتورا فورياً على منشأتك، يقرأ من ملفات الأسعار والخدمات، ويرسل الرد المناسب تلقائياً.
                        </p>
                    </div>
                </div>
            </x-filament::card>
        </div>
    </div>
</x-filament-panels::page>
