<x-filament-panels::page>
    @php
        $company = $this->getCompany();
        $webhookUrl = url('/api/webhook/messenger/' . ($company?->slug ?: $company?->id));
        $verifyToken = $company?->getEffectiveMessengerVerifyToken();
    @endphp

    <div class="space-y-5" dir="rtl">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
            <!-- Left Column: Status, Credentials & Comment-to-DM -->
            <div class="lg:col-span-7 space-y-5">

                <!-- Status & Credentials Card -->
                <x-filament::card>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3.5 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-sky-500 text-white flex items-center justify-center shrink-0">
                                <x-heroicon-o-chat-bubble-oval-left-ellipsis class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">ماسنجر فيسبوك — {{ $company?->name }}</h3>
                                <p class="text-[11px] text-slate-500">الرد التلقائي المجاني على رسائل الصفحة (Messenger DM) وتعليقات المنشورات</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            @if($isOAuthConfigured && $oauthUrl)
                                <a href="{{ $oauthUrl }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 shadow-sm transition">
                                    <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5" />
                                    ربط بضغطة زر
                                </a>
                            @endif

                            @if($connectionStatus === 'connected')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    متصل
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300">
                                    غير مربوط
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($connectionStatus === 'connected')
                        <div class="p-4 mb-4 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-950/30 dark:to-indigo-950/20 rounded-2xl border border-blue-200 dark:border-blue-800 flex flex-wrap items-center justify-between gap-3">
                            <div class="space-y-0.5">
                                <div class="text-sm font-bold text-slate-900 dark:text-white">
                                    {{ $messengerPageName ?: 'Facebook Page Connected' }}
                                </div>
                                <div class="text-[11px] text-slate-500 font-mono">
                                    Page ID: {{ $messengerPageId }}
                                    @if($fanCount !== null)
                                        · {{ number_format($fanCount) }} متابع
                                    @endif
                                    · {{ $connectedAt }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="testConnection" class="inline-flex items-center gap-1 px-3 py-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 transition">
                                    <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-blue-600" />
                                    فحص
                                </button>
                                <button type="button" wire:click="disconnectMessenger" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-50 hover:bg-red-100 dark:bg-red-950/50 text-red-700 dark:text-red-300 text-xs font-semibold rounded-lg border border-red-200 dark:border-red-800 transition">
                                    فصل
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Direct API Credentials Form -->
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Facebook Page ID <span class="text-red-500">*</span>
                                </label>
                                <input type="text" wire:model="messengerPageId" placeholder="102938475610293" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs font-mono" dir="ltr" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    اسم الصفحة (Page Name)
                                </label>
                                <input type="text" wire:model="messengerPageName" placeholder="متجر سمارتورا" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Page Access Token <span class="text-red-500">*</span>
                            </label>
                            <textarea wire:model="messengerAccessToken" rows="2" placeholder="EAAG..." class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs font-mono" dir="ltr"></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Verify Token
                                </label>
                                <input type="text" wire:model="messengerVerifyToken" placeholder="{{ $verifyToken }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs font-mono" dir="ltr" />
                            </div>
                            <button type="button" wire:click="saveCredentials" class="w-full inline-flex justify-center items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                                <x-heroicon-m-check-badge class="w-4 h-4" />
                                حفظ واختبار الربط
                            </button>
                        </div>
                    </div>
                </x-filament::card>

                <!-- Comment-to-DM Automation Card -->
                <x-filament::card>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                        <x-heroicon-o-chat-bubble-bottom-center-text class="w-4.5 h-4.5 text-indigo-500" />
                        الرد التلقائي على تعليقات صفحة فيسبوك (Comment-to-DM)
                    </h3>

                    <div class="space-y-3">
                        <label class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-700 cursor-pointer">
                            <input type="checkbox" wire:model="commentReplyEnabled" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4" />
                            <span class="text-xs font-bold text-slate-900 dark:text-white">تفعيل الرد الآلي على تعليقات المنشورات وإرسال التفاصيل في الماسنجر</span>
                        </label>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    كلمات التفعيل (اختياري)
                                </label>
                                <input type="text" wire:model="commentKeywords" placeholder="سعر,تفاصيل,حجز" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    الرد العام على التعليق
                                </label>
                                <input type="text" wire:model="commentPublicReply" placeholder="تم إرسال التفاصيل على ماسنجر 📩" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs" />
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="button" wire:click="saveCommentAutomation" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                                <x-heroicon-m-check class="w-4 h-4" />
                                حفظ إعدادات التعليقات
                            </button>
                        </div>
                    </div>
                </x-filament::card>
            </div>

            <!-- Right Column: Simulator + Webhook Setup -->
            <div class="lg:col-span-5 space-y-5">
                <x-filament::card>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-2">
                        <x-heroicon-o-sparkles class="w-4.5 h-4.5 text-blue-500" />
                        اختبار الرد الذكي لماسنجر
                    </h3>
                    <p class="text-[11px] text-slate-500 mb-3">جرّب سؤال البوت من ملفات منشأتك.</p>

                    <div class="space-y-2.5">
                        <textarea wire:model="testMessage" rows="2" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs"></textarea>
                        <button type="button" wire:click="simulateMessengerReply" class="w-full inline-flex justify-center items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow-sm transition">
                            <x-heroicon-m-bolt class="w-4 h-4" />
                            اختبار الرد الآن
                        </button>

                        @if($testReplyPreview)
                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-blue-200 dark:border-blue-800 text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-relaxed max-h-48 overflow-y-auto smartora-scroll">
                                {{ $testReplyPreview }}
                            </div>
                        @endif
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                        <x-heroicon-o-globe-alt class="w-4.5 h-4.5 text-sky-500" />
                        بيانات Webhook في Meta
                    </h3>

                    <div class="space-y-3 text-xs">
                        <div x-data="{ copied: false }">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[11px] text-slate-500">Callback URL</span>
                                <button type="button" @click="navigator.clipboard.writeText(@js($webhookUrl)); copied = true; setTimeout(() => copied = false, 1500)" class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                    <span x-text="copied ? 'تم النسخ ✓' : 'نسخ الرابط'"></span>
                                </button>
                            </div>
                            <div class="p-2 bg-slate-50 dark:bg-slate-900 font-mono text-[11px] rounded-lg border border-slate-200 dark:border-slate-800 break-all text-blue-600 dark:text-blue-400 font-bold" dir="ltr">
                                {{ $webhookUrl }}
                            </div>
                        </div>

                        <div x-data="{ copied: false }">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[11px] text-slate-500">Verify Token</span>
                                <button type="button" @click="navigator.clipboard.writeText(@js($verifyToken)); copied = true; setTimeout(() => copied = false, 1500)" class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                                    <span x-text="copied ? 'تم النسخ ✓' : 'نسخ الرمز'"></span>
                                </button>
                            </div>
                            <div class="p-2 bg-slate-50 dark:bg-slate-900 font-mono text-[11px] rounded-lg border border-slate-200 dark:border-slate-800 break-all text-emerald-600 dark:text-emerald-400 font-bold" dir="ltr">
                                {{ $verifyToken }}
                            </div>
                        </div>

                        <div class="p-3 bg-blue-50 dark:bg-blue-950/40 rounded-xl border border-blue-200/70 dark:border-blue-800/60 text-blue-900 dark:text-blue-200 text-[11px] leading-relaxed">
                            في <strong>Meta for Developers</strong> أضف منتج <strong>Messenger</strong>، أدخل الرابط والرمز أعلاه في إعدادات Webhooks، ثم اشترك في حقول <code>messages</code> و<code>messaging_postbacks</code> و<code>feed</code> (لتعليقات الصفحة).
                        </div>
                    </div>
                </x-filament::card>
            </div>
        </div>
    </div>
</x-filament-panels::page>
