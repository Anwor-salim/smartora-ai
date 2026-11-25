<x-filament-panels::page>
    @php
        $company = $this->getCompany();
    @endphp

    <div class="space-y-4" dir="rtl" @if($whatsappProvider === 'waha' && $connectionStatus !== 'connected') wire:poll.5s="refreshStatus" @endif>

        <!-- Compact Segmented Provider Switcher -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-2 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 shadow-sm">
            <div class="flex items-center gap-2 px-2">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200">طريقة الربط:</span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 hidden md:inline">اختر مزوّد الواتساب المناسب لمنشأتك</span>
            </div>

            <div class="grid grid-cols-2 gap-1.5 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl">
                <button
                    type="button"
                    wire:click="switchProvider('waha')"
                    class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold transition {{ $whatsappProvider === 'waha' ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}"
                >
                    <x-heroicon-o-qr-code class="w-4 h-4" />
                    <span>ربط سريع عبر QR</span>
                    @if($whatsappProvider === 'waha')
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    @endif
                </button>

                <button
                    type="button"
                    wire:click="switchProvider('meta_cloud')"
                    class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold transition {{ $whatsappProvider === 'meta_cloud' ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}"
                >
                    <x-heroicon-o-shield-check class="w-4 h-4" />
                    <span>Meta Cloud API الرسمي</span>
                    @if($whatsappProvider === 'meta_cloud')
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                    @endif
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
            <!-- Left Column: Active Connection UI (QR or Meta) -->
            <div class="lg:col-span-7 space-y-5">

                @if($whatsappProvider === 'waha')
                    <x-filament::card>
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3.5 mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                    <x-heroicon-o-qr-code class="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">ربط الواتساب عبر الباركود — {{ $company?->name }}</h3>
                                    <p class="text-[11px] text-slate-500">امسح رمز QR من تطبيق واتساب لتفعيل الرد الآلي</p>
                                </div>
                            </div>

                            @if($connectionStatus === 'connected')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    متصل ونشط
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300">
                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                                    بانتظار المسح
                                </span>
                            @endif
                        </div>

                        @if($connectionStatus === 'connected')
                            <div class="p-5 bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-950/30 dark:to-teal-950/20 rounded-2xl border border-emerald-200 dark:border-emerald-800 text-center space-y-3">
                                <div class="w-12 h-12 rounded-full bg-emerald-600 text-white mx-auto flex items-center justify-center shadow">
                                    <x-heroicon-m-check class="w-7 h-7" />
                                </div>
                                <div>
                                    <h4 class="text-base font-bold text-slate-900 dark:text-white">الواتساب متصل ويعمل بنجاح</h4>
                                    @if($pushName)
                                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 mt-1">
                                            الاسم المسجل: <span class="text-emerald-700 dark:text-emerald-400 font-bold">{{ $pushName }}</span>
                                        </p>
                                    @endif
                                    <p class="text-sm font-mono text-emerald-700 dark:text-emerald-300 mt-1 font-bold" dir="ltr">
                                        {{ $whatsappNumber }}
                                    </p>
                                    <p class="text-[11px] text-slate-500 mt-1">وقت الربط: {{ $connectedAt ?? now()->format('d/m/Y H:i') }}</p>
                                </div>

                                <div class="pt-3 border-t border-emerald-200 dark:border-emerald-800 flex justify-center gap-2.5">
                                    <button type="button" wire:click="refreshStatus" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 transition">
                                        <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-emerald-600" />
                                        تحديث الحالة
                                    </button>
                                    <button type="button" wire:click="disconnectWhatsApp" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-50 hover:bg-red-100 dark:bg-red-950/50 text-red-700 dark:text-red-300 text-xs font-semibold rounded-lg border border-red-200 dark:border-red-800 transition">
                                        <x-heroicon-o-arrow-left-on-rectangle class="w-3.5 h-3.5" />
                                        فصل الجلسة
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-center">
                                <div class="text-center p-4 bg-slate-50 dark:bg-slate-800/80 rounded-2xl border border-slate-200 dark:border-slate-700">
                                    @if($qrCodeUrl)
                                        <div class="relative inline-block p-2.5 bg-white rounded-xl shadow-sm border border-slate-100">
                                            <img src="{{ $qrCodeUrl }}" alt="WhatsApp QR Code" class="w-48 h-48 mx-auto rounded-lg object-contain" />
                                        </div>
                                    @else
                                        <div class="w-48 h-48 mx-auto bg-slate-100 dark:bg-slate-700 rounded-xl flex flex-col items-center justify-center text-slate-500 p-4 space-y-2">
                                            <x-heroicon-o-arrow-path class="w-7 h-7 animate-spin text-emerald-600" />
                                            <span class="text-xs font-medium">جاري تجهيز رمز QR...</span>
                                        </div>
                                    @endif

                                    <div class="mt-3 flex justify-center gap-2">
                                        <button type="button" wire:click="generateQr" class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-200/70 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-xs font-medium rounded-lg text-slate-700 dark:text-slate-200 transition">
                                            <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                                            تحديث الرمز
                                        </button>
                                        <button type="button" wire:click="checkConnection" class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-sm transition">
                                            <x-heroicon-m-check class="w-3.5 h-3.5" />
                                            تحقق الآن
                                        </button>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <h4 class="font-bold text-xs text-slate-800 dark:text-slate-200">خطوات الربط من هاتفك:</h4>
                                    <ol class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300">
                                        <li class="flex items-start gap-2">
                                            <span class="w-5 h-5 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">1</span>
                                            <span>افتح تطبيق <strong>WhatsApp</strong> على هاتف المنشأة.</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span class="w-5 h-5 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">2</span>
                                            <span>افتح القائمة أو الإعدادات ثم اختر <strong>الأجهزة المرتبطة (Linked Devices)</strong>.</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span class="w-5 h-5 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">3</span>
                                            <span>اضغط على <strong>ربط جهاز</strong> وامسح رمز الباركود الظاهر.</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span class="w-5 h-5 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">4</span>
                                            <span>يتفعل الرد الآلي تلقائياً بمجرد اكتمال المسح.</span>
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        @endif
                    </x-filament::card>

                @else
                    <x-filament::card>
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3.5 mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                                    <x-heroicon-o-shield-check class="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">الربط الرسمي (Meta Cloud API)</h3>
                                    <p class="text-[11px] text-slate-500">اتصال سحابي معتمد لمنشأة {{ $company?->name }}</p>
                                </div>
                            </div>

                            @if($company?->isMetaConnected())
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-sky-100 text-sky-800 dark:bg-sky-950/70 dark:text-sky-300">
                                    <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                                    معتمد ومتصل
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                    بانتظار المفاتيح
                                </span>
                            @endif
                        </div>

                        @if($company?->isMetaConnected())
                            <div class="p-4 bg-gradient-to-br from-sky-50 to-indigo-50 dark:from-sky-950/30 dark:to-indigo-950/20 rounded-2xl border border-sky-200 dark:border-sky-800 flex flex-wrap items-center justify-between gap-3 mb-4">
                                <div class="space-y-0.5">
                                    <div class="text-sm font-bold text-slate-900 dark:text-white">
                                        {{ $metaVerifiedName ?: 'حساب واتساب رسمي معتمد' }}
                                    </div>
                                    <div class="text-xs font-mono text-sky-700 dark:text-sky-300 font-bold" dir="ltr">
                                        {{ $whatsappNumber ?: $company->whatsapp_number }}
                                        @if($metaQualityRating)
                                            <span class="mr-2 px-2 py-0.5 rounded-full text-[10px] bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">{{ $metaQualityRating }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="testMetaConnection" class="inline-flex items-center gap-1 px-3 py-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 transition">
                                        <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-sky-600" />
                                        فحص
                                    </button>
                                    <button type="button" wire:click="disconnectMeta" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-50 hover:bg-red-100 dark:bg-red-950/50 text-red-700 dark:text-red-300 text-xs font-semibold rounded-lg border border-red-200 dark:border-red-800 transition">
                                        فصل
                                    </button>
                                </div>
                            </div>
                        @endif

                        <div class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Phone Number ID <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" wire:model="metaPhoneNumberId" placeholder="108294829102834" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs font-mono" dir="ltr" />
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        WABA ID (اختياري)
                                    </label>
                                    <input type="text" wire:model="metaWabaId" placeholder="104829482910283" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs font-mono" dir="ltr" />
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Permanent Access Token <span class="text-red-500">*</span>
                                </label>
                                <textarea wire:model="metaAccessToken" rows="2" placeholder="EAAGm..." class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs font-mono" dir="ltr"></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Verify Token
                                    </label>
                                    <input type="text" wire:model="metaVerifyToken" placeholder="{{ $company?->getEffectiveVerifyToken() }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs font-mono" dir="ltr" />
                                </div>
                                <button type="button" wire:click="saveMetaCredentials" class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                                    <x-heroicon-m-check-badge class="w-4 h-4" />
                                    حفظ واختبار الربط الرسمي
                                </button>
                            </div>
                        </div>
                    </x-filament::card>
                @endif

                <!-- Numbers Configuration Form -->
                <x-filament::card>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                        <x-heroicon-o-phone class="w-4.5 h-4.5 text-sky-500" />
                        أرقام الرد الآلي والدعم البشري
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                رقم الواتساب المربوط <span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="whatsappNumber" placeholder="967730881873" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs font-mono" dir="ltr" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                رقم التحويل البشري
                            </label>
                            <input type="text" wire:model="supportPhone" placeholder="967770001122" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs font-mono" dir="ltr" />
                        </div>
                    </div>

                    <div class="flex justify-end mt-3">
                        <button type="button" wire:click="saveNumbers" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                            <x-heroicon-m-check-badge class="w-4 h-4" />
                            حفظ الأرقام
                        </button>
                    </div>
                </x-filament::card>
            </div>

            <!-- Right Column: Test Message & Webhook Info -->
            <div class="lg:col-span-5 space-y-5">
                <x-filament::card>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-2">
                        <x-heroicon-o-paper-airplane class="w-4.5 h-4.5 text-emerald-500 rtl:rotate-180" />
                        إرسال رسالة تجريبية
                    </h3>
                    <p class="text-[11px] text-slate-500 mb-3">تحقق من استجابة الإرسال الفعلي على واتساب.</p>

                    <div class="space-y-2.5">
                        <input type="text" wire:model="testPhoneNumber" placeholder="رقم المستلم (مثال: 96777000111)" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs font-mono" dir="ltr" />
                        <textarea wire:model="testMessage" rows="2" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs"></textarea>
                        <button type="button" wire:click="sendTestMessage" class="w-full inline-flex justify-center items-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-lg shadow-sm transition">
                            <x-heroicon-m-bolt class="w-4 h-4" />
                            إرسال واختبار الاستجابة
                        </button>
                    </div>
                </x-filament::card>

                @if($whatsappProvider === 'meta_cloud')
                    @php
                        $metaWebhookUrl = url('/api/webhook/meta/' . ($company?->slug ?: $company?->id));
                        $metaToken = $company?->getEffectiveVerifyToken();
                    @endphp
                    <x-filament::card>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                            <x-heroicon-o-globe-alt class="w-4.5 h-4.5 text-sky-500" />
                            بيانات Webhook في Meta
                        </h3>

                        <div class="space-y-3 text-xs">
                            <div x-data="{ copied: false }">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[11px] text-slate-500">Callback URL</span>
                                    <button type="button" @click="navigator.clipboard.writeText(@js($metaWebhookUrl)); copied = true; setTimeout(() => copied = false, 1500)" class="text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                                        <span x-text="copied ? 'تم النسخ ✓' : 'نسخ الرابط'"></span>
                                    </button>
                                </div>
                                <div class="p-2 bg-slate-50 dark:bg-slate-900 font-mono text-[11px] rounded-lg border border-slate-200 dark:border-slate-800 break-all text-sky-600 dark:text-sky-400 font-bold" dir="ltr">
                                    {{ $metaWebhookUrl }}
                                </div>
                            </div>

                            <div x-data="{ copied: false }">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[11px] text-slate-500">Verify Token</span>
                                    <button type="button" @click="navigator.clipboard.writeText(@js($metaToken)); copied = true; setTimeout(() => copied = false, 1500)" class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                                        <span x-text="copied ? 'تم النسخ ✓' : 'نسخ الرمز'"></span>
                                    </button>
                                </div>
                                <div class="p-2 bg-slate-50 dark:bg-slate-900 font-mono text-[11px] rounded-lg border border-slate-200 dark:border-slate-800 break-all text-emerald-600 dark:text-emerald-400 font-bold" dir="ltr">
                                    {{ $metaToken }}
                                </div>
                            </div>

                            <div class="p-3 bg-sky-50 dark:bg-sky-950/40 rounded-xl border border-sky-200/70 dark:border-sky-800/60 text-sky-900 dark:text-sky-200 text-[11px] leading-relaxed">
                                الصق الرابط والرمز في <strong>WhatsApp &gt; Configuration &gt; Webhook</strong> بلوحة Meta ثم اشترك في حقل <strong>messages</strong>.
                            </div>
                        </div>
                    </x-filament::card>
                @else
                    @php
                        $wahaWebhookUrl = url('/api/webhook/whatsapp');
                    @endphp
                    <x-filament::card>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                            <x-heroicon-o-command-line class="w-4.5 h-4.5 text-purple-500" />
                            معلومات جلسة الأتمتة (WAHA)
                        </h3>

                        <div class="space-y-3 text-xs">
                            <div x-data="{ copied: false }">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[11px] text-slate-500">Webhook URL</span>
                                    <button type="button" @click="navigator.clipboard.writeText(@js($wahaWebhookUrl)); copied = true; setTimeout(() => copied = false, 1500)" class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                                        <span x-text="copied ? 'تم النسخ ✓' : 'نسخ الرابط'"></span>
                                    </button>
                                </div>
                                <div class="p-2 bg-slate-50 dark:bg-slate-900 font-mono text-[11px] rounded-lg border border-slate-200 dark:border-slate-800 break-all text-emerald-600 dark:text-emerald-400" dir="ltr">
                                    {{ $wahaWebhookUrl }}
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-[11px]">
                                <div class="p-2 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-800">
                                    <span class="text-slate-500 block">اسم الجلسة</span>
                                    <span class="font-mono font-bold text-emerald-700 dark:text-emerald-300">{{ $company?->getWahaSessionName() }}</span>
                                </div>
                                <div class="p-2 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-800">
                                    <span class="text-slate-500 block">معرّف المنشأة</span>
                                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $company?->slug }}</span>
                                </div>
                            </div>
                        </div>
                    </x-filament::card>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
