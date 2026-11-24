<x-filament-panels::page>
    @php
        $company = $this->getCompany();
    @endphp

    <div class="space-y-6" dir="rtl" @if($whatsappProvider === 'waha' && $connectionStatus !== 'connected') wire:poll.5s="refreshStatus" @endif>

        <!-- Provider Selection Tabs -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Option 1: QR Code -->
            <div 
                wire:click="switchProvider('waha')"
                class="relative cursor-pointer rounded-2xl p-5 border-2 transition-all duration-200 {{ $whatsappProvider === 'waha' ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20 shadow-md ring-2 ring-emerald-500/20' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-gray-300 dark:hover:border-gray-700' }}"
            >
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl {{ $whatsappProvider === 'waha' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/30' : 'bg-gray-100 dark:bg-gray-800 text-gray-500' }} flex items-center justify-center shrink-0">
                        <x-heroicon-o-qr-code class="w-7 h-7" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-base text-gray-900 dark:text-white">الخيار 1: ربط فوري بكود QR</h4>
                            @if($whatsappProvider === 'waha')
                                <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                    <x-heroicon-m-check-circle class="w-4 h-4" />
                                    مفعل حالياً
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 leading-relaxed">
                            امسح الباركود من تطبيق واتساب العادي أو الأعمال خلال 30 ثانية. مجاني تماماً وبدون أي اشتراكات لدى ميتا.
                        </p>
                        <div class="flex flex-wrap gap-1.5 mt-3">
                            <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">ربط فوري 30 ثانية</span>
                            <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">مجاني 100%</span>
                            <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">للمشاريع والمتاجر</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Option 2: Meta Cloud API -->
            <div 
                wire:click="switchProvider('meta_cloud')"
                class="relative cursor-pointer rounded-2xl p-5 border-2 transition-all duration-200 {{ $whatsappProvider === 'meta_cloud' ? 'border-sky-500 bg-sky-50/50 dark:bg-sky-950/20 shadow-md ring-2 ring-sky-500/20' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-gray-300 dark:hover:border-gray-700' }}"
            >
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl {{ $whatsappProvider === 'meta_cloud' ? 'bg-sky-500 text-white shadow-md shadow-sky-500/30' : 'bg-gray-100 dark:bg-gray-800 text-gray-500' }} flex items-center justify-center shrink-0">
                        <x-heroicon-o-shield-check class="w-7 h-7" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-base text-gray-900 dark:text-white">الخيار 2: ربط رسمي Meta Cloud API</h4>
                            @if($whatsappProvider === 'meta_cloud')
                                <span class="inline-flex items-center gap-1 text-xs font-bold text-sky-600 dark:text-sky-400">
                                    <x-heroicon-m-check-circle class="w-4 h-4" />
                                    مفعل حالياً
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 leading-relaxed">
                            ربط سحابي رسمي معتمد من شركة Meta (واتساب). حماية مطلقة للأرقام من الحظر، استقرار دائم 24/7 دون الحاجة لبقاء الهاتف متصلاً.
                        </p>
                        <div class="flex flex-wrap gap-1.5 mt-3">
                            <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300">رسمي 100% معتمد</span>
                            <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">حماية من الحظر</span>
                            <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">1000 محادثة مجاناً/شهر</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left Column: Active Connection UI (QR or Meta) -->
            <div class="lg:col-span-7 space-y-6">

                @if($whatsappProvider === 'waha')
                    <!-- ============================================== -->
                    <!-- WAHA QR Code Section -->
                    <!-- ============================================== -->
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
                            <div class="p-6 bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-950/30 dark:to-teal-950/20 rounded-2xl border border-emerald-200 dark:border-emerald-800 text-center space-y-4">
                                <div class="w-16 h-16 rounded-full bg-emerald-600 text-white mx-auto flex items-center justify-center shadow-lg">
                                    <x-heroicon-m-check class="w-10 h-10" />
                                </div>
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 dark:text-white">رقم الواتساب متصل بنجاح بمحرك سمارتورا! 🚀</h4>
                                    @if($pushName)
                                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-1">
                                            الاسم المسجل: <span class="text-emerald-700 dark:text-emerald-400 font-bold">{{ $pushName }}</span>
                                        </p>
                                    @endif
                                    <p class="text-base font-mono text-emerald-700 dark:text-emerald-300 mt-1 font-bold">
                                        {{ $whatsappNumber }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">تاريخ ووقت الربط: {{ $connectedAt ?? now()->format('d/m/Y H:i') }}</p>
                                </div>

                                <div class="pt-4 border-t border-emerald-200 dark:border-emerald-800 flex justify-center gap-3">
                                    <button type="button" wire:click="refreshStatus" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 transition">
                                        <x-heroicon-o-arrow-path class="w-4 h-4 text-emerald-600" />
                                        فحص وتحديث الحالة
                                    </button>
                                    <button type="button" wire:click="disconnectWhatsApp" class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 dark:bg-red-950/50 text-red-700 dark:text-red-300 text-xs font-semibold rounded-lg border border-red-200 dark:border-red-800 transition">
                                        <x-heroicon-o-arrow-left-on-rectangle class="w-4 h-4" />
                                        فصل الجلسة وربط رقم آخر
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                                <div class="text-center p-4 bg-gray-50 dark:bg-gray-800/80 rounded-2xl border border-gray-200 dark:border-gray-700">
                                    @if($qrCodeUrl)
                                        <div class="relative inline-block p-3 bg-white rounded-xl shadow-md border border-gray-100">
                                            <img src="{{ $qrCodeUrl }}" alt="WhatsApp QR Code" class="w-56 h-56 mx-auto rounded-lg object-contain" />
                                            <div class="absolute inset-0 border-2 border-emerald-500 rounded-xl pointer-events-none opacity-40 animate-pulse"></div>
                                        </div>
                                    @else
                                        <div class="w-56 h-56 mx-auto bg-gray-100 dark:bg-gray-700 rounded-xl flex flex-col items-center justify-center text-gray-500 p-4 space-y-2">
                                            <x-heroicon-o-arrow-path class="w-8 h-8 animate-spin text-emerald-600" />
                                            <span class="text-xs font-medium">جاري تجهيز رمز QR الفعلي من واتساب...</span>
                                        </div>
                                    @endif

                                    <div class="mt-3 flex justify-center gap-2">
                                        <button type="button" wire:click="generateQr" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-xs rounded-lg text-gray-700 dark:text-gray-200 transition">
                                            <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                                            تحديث الرمز
                                        </button>
                                        <button type="button" wire:click="checkConnection" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                                            <x-heroicon-m-check class="w-3.5 h-3.5" />
                                            تحقق بعد المسح
                                        </button>
                                    </div>
                                </div>

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
                                            <span>سيتم التحديث تلقائياً وبدء تشغيل الرد الآلي فور إتمام المسح بنجاح!</span>
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        @endif
                    </x-filament::card>

                @else
                    <!-- ============================================== -->
                    <!-- Meta Cloud API Section -->
                    <!-- ============================================== -->
                    <x-filament::card>
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-4 mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                                    <x-heroicon-o-shield-check class="w-6 h-6" />
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-900 dark:text-white">الربط الرسمي المعتمد (WhatsApp Cloud API - Meta)</h3>
                                    <p class="text-xs text-gray-500">حماية تامة لأرقام منشأة {{ $company?->name }} بدون أي مخاطر حظر</p>
                                </div>
                            </div>

                            @if($company?->isMetaConnected())
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-800 dark:bg-sky-950/70 dark:text-sky-300 border border-sky-300 dark:border-sky-700">
                                    <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                                    رسمي ومعتمد ✅
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-300 dark:border-gray-700">
                                    بانتظار إدخال المفاتيح
                                </span>
                            @endif
                        </div>

                        @if($company?->isMetaConnected())
                            <!-- Connected Meta Banner -->
                            <div class="p-6 bg-gradient-to-br from-sky-50 to-indigo-50 dark:from-sky-950/30 dark:to-indigo-950/20 rounded-2xl border border-sky-200 dark:border-sky-800 text-center space-y-4 mb-6">
                                <div class="w-16 h-16 rounded-full bg-sky-600 text-white mx-auto flex items-center justify-center shadow-lg">
                                    <x-heroicon-m-shield-check class="w-10 h-10" />
                                </div>
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 dark:text-white">رقم الواتساب مربوط رسمياً عبر Meta Cloud API 🚀</h4>
                                    @if($metaVerifiedName)
                                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-1">
                                            الاسم المعتمد لدى ميتا: <span class="text-sky-700 dark:text-sky-400 font-bold">{{ $metaVerifiedName }}</span>
                                        </p>
                                    @endif
                                    <p class="text-base font-mono text-sky-700 dark:text-sky-300 mt-1 font-bold">
                                        {{ $whatsappNumber ?: $company->whatsapp_number }}
                                    </p>
                                    @if($metaQualityRating)
                                        <span class="inline-block mt-2 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            جودة الرقم: {{ $metaQualityRating }} (ممتاز)
                                        </span>
                                    @endif
                                </div>

                                <div class="pt-4 border-t border-sky-200 dark:border-sky-800 flex justify-center gap-3">
                                    <button type="button" wire:click="testMetaConnection" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 transition">
                                        <x-heroicon-o-arrow-path class="w-4 h-4 text-sky-600" />
                                        فحص وتحديث الاتصال
                                    </button>
                                    <button type="button" wire:click="disconnectMeta" class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 dark:bg-red-950/50 text-red-700 dark:text-red-300 text-xs font-semibold rounded-lg border border-red-200 dark:border-red-800 transition">
                                        <x-heroicon-o-arrow-left-on-rectangle class="w-4 h-4" />
                                        فصل الاتصال وتعديل المفاتيح
                                    </button>
                                </div>
                            </div>
                        @endif

                        <!-- Meta Credentials Form -->
                        <div class="space-y-4">
                            <h4 class="font-bold text-sm text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <x-heroicon-o-key class="w-4 h-4 text-sky-500" />
                                بيانات الاعتماد الرسمية من Meta for Developers:
                            </h4>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                        Phone Number ID (معرف رقم الهاتف) <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        wire:model="metaPhoneNumberId" 
                                        placeholder="مثال: 108294829102834" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs font-mono"
                                    />
                                    <p class="text-[11px] text-gray-500 mt-1">تستخرجه من صفحة WhatsApp > API Setup في حساب المطورين.</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                        WABA ID (معرف حساب واتساب للأعمال)
                                    </label>
                                    <input 
                                        type="text" 
                                        wire:model="metaWabaId" 
                                        placeholder="مثال: 104829482910283" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs font-mono"
                                    />
                                    <p class="text-[11px] text-gray-500 mt-1">معرف الحساب التجاري (WhatsApp Business Account ID).</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                    Permanent Access Token (رمز الوصول الدائم) <span class="text-red-500">*</span>
                                </label>
                                <textarea 
                                    wire:model="metaAccessToken" 
                                    rows="2" 
                                    placeholder="EAAGm..." 
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs font-mono"
                                ></textarea>
                                <p class="text-[11px] text-gray-500 mt-1">رمز System User Token الدائم من Business Manager بصلاحيات `whatsapp_business_messaging`.</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                        Verify Token (رمز التحقق الخاص بالويب هوك)
                                    </label>
                                    <input 
                                        type="text" 
                                        wire:model="metaVerifyToken" 
                                        placeholder="{{ $company?->getEffectiveVerifyToken() }}" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs font-mono"
                                    />
                                </div>
                                <div class="flex items-end">
                                    <button type="button" wire:click="saveMetaCredentials" class="w-full inline-flex justify-center items-center gap-2 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-bold shadow transition">
                                        <x-heroicon-m-check-badge class="w-4 h-4" />
                                        حفظ واختبار الربط الرسمي
                                    </button>
                                </div>
                            </div>
                        </div>
                    </x-filament::card>
                @endif

                <!-- Numbers Configuration Form -->
                <x-filament::card>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <x-heroicon-o-phone class="w-5 h-5 text-sky-500" />
                        أرقام الرد الآلي والمسؤول البشري
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
                                    placeholder="مثال: 967730881873" 
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-sm font-mono focus:ring-emerald-500 focus:border-emerald-500"
                                />
                                <p class="text-[11px] text-gray-500 mt-1">الرقم المستلم للرسائل الذي يقوم الذكاء الاصطناعي بالرد من خلاله.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                    الرقم المسؤول عن التحويل البشري
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
                            <button type="button" wire:click="saveNumbers" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold shadow transition">
                                <x-heroicon-m-check-badge class="w-4 h-4" />
                                حفظ وتحديث الأرقام
                            </button>
                        </div>
                    </div>
                </x-filament::card>
            </div>

            <!-- Right Column: Sidebar (Test Message & Meta Webhook Info) -->
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

                @if($whatsappProvider === 'meta_cloud')
                    <!-- Meta Webhook Configuration Details -->
                    <x-filament::card>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <x-heroicon-o-globe-alt class="w-5 h-5 text-sky-500" />
                            بيانات ربط الويب هوك في Meta:
                        </h3>

                        <div class="space-y-3 text-xs">
                            <div>
                                <span class="text-gray-500 block mb-1">Callback URL (ضعه في Webhook فيسبوك):</span>
                                <div class="p-2.5 bg-gray-50 dark:bg-gray-900 font-mono text-[11px] rounded-lg border border-gray-200 dark:border-gray-800 select-all break-all text-sky-600 dark:text-sky-400 font-bold">
                                    {{ url('/api/webhook/meta/' . ($company?->slug ?: $company?->id)) }}
                                </div>
                            </div>

                            <div>
                                <span class="text-gray-500 block mb-1">Verify Token (رمز التحقق المطلوب في فيسبوك):</span>
                                <div class="p-2.5 bg-gray-50 dark:bg-gray-900 font-mono text-[11px] rounded-lg border border-gray-200 dark:border-gray-800 select-all break-all text-emerald-600 dark:text-emerald-400 font-bold">
                                    {{ $company?->getEffectiveVerifyToken() }}
                                </div>
                            </div>

                            <div class="p-3 bg-sky-50 dark:bg-sky-950/40 rounded-lg border border-sky-200 dark:border-sky-800/60 text-sky-900 dark:text-sky-200 space-y-1">
                                <span class="font-bold flex items-center gap-1 text-[11px]">
                                    <x-heroicon-m-sparkles class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" />
                                    خطوات التفعيل في Meta:
                                </span>
                                <p class="text-[11px] leading-relaxed">
                                    1. الصق الرابط والرمز في صفحة **WhatsApp > Configuration > Webhook** في لوحة مطوري فيسبوك.<br>
                                    2. انقر على **Verify and Save**.<br>
                                    3. اشترك في حقل **messages** من قائمة Webhook fields.
                                </p>
                            </div>
                        </div>
                    </x-filament::card>
                @else
                    <!-- WAHA Webhook Info -->
                    <x-filament::card>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <x-heroicon-o-command-line class="w-5 h-5 text-purple-500" />
                            نقطة ربط الأتمتة (Webhook / WAHA)
                        </h3>

                        <div class="space-y-3 text-xs">
                            <div>
                                <span class="text-gray-500 block mb-1">رابط الويب هوك (n8n / API Webhook URL):</span>
                                <div class="p-2.5 bg-gray-50 dark:bg-gray-900 font-mono text-[11px] rounded-lg border border-gray-200 dark:border-gray-800 select-all break-all text-emerald-600 dark:text-emerald-400">
                                    {{ url('/api/webhook/whatsapp') }}
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <span class="text-gray-500 block mb-1">جلسة WAHA الخاصة بالمنشأة:</span>
                                    <span class="inline-block px-2 py-1 bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 rounded font-mono font-bold">
                                        {{ $company?->getWahaSessionName() }}
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
                                    يقوم خادم WAHA / n8n بإرسال رسائل العملاء القادمة للرابط أعلاه. يتعرف سمارتورا فورياً على منشأتك، يقرأ من ملفات الأسعار والخدمات، ويرسل الرد المناسب تلقائياً.
                                </p>
                            </div>
                        </div>
                    </x-filament::card>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
