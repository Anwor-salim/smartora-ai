<x-filament-panels::page>
    @php
        $company = $this->getCompany();
        $webhookUrl = url('/api/webhook/instagram/' . ($company?->slug ?: $company?->id));
        $verifyToken = $company?->getEffectiveInstagramVerifyToken();
    @endphp

    <div class="space-y-6" dir="rtl">

        <!-- 1-Click OAuth Banner -->
        <x-filament::card>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 via-pink-500 to-purple-600 text-white flex items-center justify-center shrink-0 shadow-md">
                        <x-heroicon-o-camera class="w-7 h-7" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">الربط الرسمي المباشر مع انستقرام (Instagram Business API)</h3>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                آمن 100% ومجاني ($0)
                            </span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 leading-relaxed">
                            يرد البوت تلقائياً على رسائل الخاص (DM)، ردود الستوري (Story Replies)، والتعليقات باستخدام نفس قاعدة معرفة وملفات منشأة <strong>{{ $company?->name }}</strong>.
                        </p>
                    </div>
                </div>

                <div class="shrink-0">
                    @if($isOAuthConfigured && $oauthUrl)
                        <a href="{{ $oauthUrl }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-pink-600 to-purple-600 hover:from-pink-700 hover:to-purple-700 shadow-lg transition">
                            <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                            ربط حساب انستقرام بضغطة زر
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                            <x-heroicon-o-key class="w-4 h-4 text-pink-500" />
                            متاح حالياً بالربط المباشر أدناه (API Token)
                        </span>
                    @endif
                </div>
            </div>
        </x-filament::card>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left Column: Connection Card + Credentials + Comment-to-DM -->
            <div class="lg:col-span-7 space-y-6">

                <!-- Status & Credentials Card -->
                <x-filament::card>
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-4 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-pink-500/10 text-pink-600 dark:text-pink-400 flex items-center justify-center">
                                <x-heroicon-o-shield-check class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900 dark:text-white">حالة ربط انستقرام لمنشأة: {{ $company?->name }}</h3>
                                <p class="text-xs text-gray-500">اتصال رسمي معتمد من Meta — بدون كلمة مرور وبدون خطر حظر</p>
                            </div>
                        </div>

                        @if($connectionStatus === 'connected')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                متصل ونشط ✅
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                                بانتظار ربط الحساب
                            </span>
                        @endif
                    </div>

                    @if($connectionStatus === 'connected')
                        <div class="p-5 mb-6 bg-gradient-to-br from-pink-50 to-purple-50 dark:from-pink-950/30 dark:to-purple-950/20 rounded-2xl border border-pink-200 dark:border-pink-800 text-center space-y-3">
                            <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-amber-500 via-pink-500 to-purple-600 text-white mx-auto flex items-center justify-center shadow-lg">
                                <x-heroicon-m-check class="w-8 h-8" />
                            </div>
                            <div>
                                <h4 class="text-base font-bold text-gray-900 dark:text-white">حساب انستقرام متصل بنجاح بمحرك سمارتورا! 🚀</h4>
                                @if($instagramUsername)
                                    <p class="text-base font-mono font-bold text-pink-600 dark:text-pink-400 mt-1" dir="ltr">
                                        {{ '@' . ltrim($instagramUsername, '@') }}
                                    </p>
                                @endif
                                <p class="text-xs text-gray-500 font-mono mt-1">Account ID: {{ $instagramAccountId }}</p>
                                @if($followersCount !== null)
                                    <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">عدد المتابعين: <strong>{{ number_format($followersCount) }}</strong></p>
                                @endif
                                <p class="text-xs text-gray-500 mt-1">تاريخ الربط: {{ $connectedAt }}</p>
                            </div>

                            <div class="pt-3 border-t border-pink-200 dark:border-pink-800 flex justify-center gap-3">
                                <button type="button" wire:click="testConnection" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 transition">
                                    <x-heroicon-o-arrow-path class="w-4 h-4 text-pink-600" />
                                    فحص وتحديث الاتصال
                                </button>
                                <button type="button" wire:click="disconnectInstagram" class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 dark:bg-red-950/50 text-red-700 dark:text-red-300 text-xs font-semibold rounded-lg border border-red-200 dark:border-red-800 transition">
                                    <x-heroicon-o-arrow-left-on-rectangle class="w-4 h-4" />
                                    فصل الحساب
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Direct API Credentials Form -->
                    <div class="space-y-4">
                        <h4 class="font-bold text-sm text-gray-800 dark:text-gray-200 flex items-center gap-2">
                            <x-heroicon-o-key class="w-4 h-4 text-pink-500" />
                            بيانات الربط من Meta Developers (Instagram API Setup):
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                    Instagram Account ID <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="instagramAccountId"
                                    placeholder="مثال: 17841400123456789"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs font-mono"
                                />
                                <p class="text-[11px] text-gray-500 mt-1">معرّف حساب انستقرام الاحترافي من لوحة تطبيق ميتا.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                    اسم الحساب (Username)
                                </label>
                                <input
                                    type="text"
                                    wire:model="instagramUsername"
                                    placeholder="مثال: arab_hospital"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs font-mono"
                                />
                                <p class="text-[11px] text-gray-500 mt-1">يُجلب تلقائياً عند فحص الاتصال.</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Access Token (رمز الوصول الدائم) <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                wire:model="instagramAccessToken"
                                rows="2"
                                placeholder="IGAA... أو EAAG..."
                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs font-mono"
                            ></textarea>
                            <p class="text-[11px] text-gray-500 mt-1">يدعم رموز Instagram Login (`IGAA...`) ورموز Facebook Page (`EAAG...`) تلقائياً.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                    Verify Token (رمز تحقق الويب هوك)
                                </label>
                                <input
                                    type="text"
                                    wire:model="instagramVerifyToken"
                                    placeholder="{{ $verifyToken }}"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs font-mono"
                                />
                            </div>
                            <div class="flex items-end">
                                <button type="button" wire:click="saveCredentials" class="w-full inline-flex justify-center items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-pink-600 to-purple-600 hover:from-pink-700 hover:to-purple-700 text-white rounded-lg text-xs font-bold shadow transition">
                                    <x-heroicon-m-check-badge class="w-4 h-4" />
                                    حفظ واختبار ربط انستقرام
                                </button>
                            </div>
                        </div>
                    </div>
                </x-filament::card>

                <!-- Comment-to-DM Automation Card -->
                <x-filament::card>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                        <x-heroicon-o-chat-bubble-bottom-center-text class="w-5 h-5 text-purple-500" />
                        الرد التلقائي على التعليقات وإرسال التفاصيل في الخاص (Comment-to-DM)
                    </h3>
                    <p class="text-xs text-gray-500 mb-4">عندما يعلق متابع على منشور أو ريلز، يرسل البوت الإجابة الكاملة لخاص المتابع تلقائياً.</p>

                    <div class="space-y-4">
                        <label class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/70 border border-gray-200 dark:border-gray-700 cursor-pointer">
                            <input type="checkbox" wire:model="commentReplyEnabled" class="rounded border-gray-300 text-pink-600 focus:ring-pink-500 w-4 h-4" />
                            <div>
                                <span class="text-xs font-bold text-gray-900 dark:text-white block">تفعيل الرد الآلي على تعليقات انستقرام (Comment-to-DM)</span>
                                <span class="text-[11px] text-gray-500">تحويل المعلقين مباشرة إلى محادثة خاصة ذكية دون تدخل بشري.</span>
                            </div>
                        </label>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                    كلمات التفعيل (اختياري)
                                </label>
                                <input
                                    type="text"
                                    wire:model="commentKeywords"
                                    placeholder="مثال: سعر,تفاصيل,عيادة,حجز (اتركه فارغاً للرد على الكل)"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs"
                                />
                                <p class="text-[11px] text-gray-500 mt-1">افصل بين الكلمات بفاصلة، أو اتركه فارغاً للرد على كل التعليقات.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                    الرد العام على التعليق
                                </label>
                                <input
                                    type="text"
                                    wire:model="commentPublicReply"
                                    placeholder="تم إرسال التفاصيل على الخاص 📩"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs"
                                />
                                <p class="text-[11px] text-gray-500 mt-1">اتركه فارغاً إذا أردت الإرسال في الخاص فقط دون رد عام.</p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="button" wire:click="saveCommentAutomation" class="inline-flex items-center gap-2 px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-bold shadow transition">
                                <x-heroicon-m-check class="w-4 h-4" />
                                حفظ إعدادات التعليقات
                            </button>
                        </div>
                    </div>
                </x-filament::card>
            </div>

            <!-- Right Column: Simulator + Webhook Setup Guide -->
            <div class="lg:col-span-5 space-y-6">
                <!-- Simulator -->
                <x-filament::card>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                        <x-heroicon-o-sparkles class="w-5 h-5 text-pink-500" />
                        محاكي الرد الآلي في انستقرام (Live Test)
                    </h3>
                    <p class="text-xs text-gray-500 mb-4">جرّب سؤال البوت كما لو أن زبوناً راسلك في خاص انستقرام الآن للتأكد من دقة الإجابات من ملفاتك.</p>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">رسالة العميل الافتراضية</label>
                            <textarea
                                wire:model="testMessage"
                                rows="2"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs"
                            ></textarea>
                        </div>

                        <button type="button" wire:click="simulateInstagramReply" class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 bg-pink-600 hover:bg-pink-700 text-white text-xs font-bold rounded-lg shadow transition">
                            <x-heroicon-m-bolt class="w-4 h-4" />
                            اختبار الرد من قاعدة المعرفة
                        </button>

                        @if($testReplyPreview)
                            <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-pink-200 dark:border-pink-800 text-xs text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed max-h-60 overflow-y-auto">
                                {{ $testReplyPreview }}
                            </div>
                        @endif
                    </div>
                </x-filament::card>

                <!-- Webhook Setup Card -->
                <x-filament::card>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                        <x-heroicon-o-globe-alt class="w-5 h-5 text-sky-500" />
                        بيانات ربط Webhook في Meta انستقرام
                    </h3>

                    <div class="space-y-3 text-xs">
                        <div>
                            <span class="text-gray-500 block mb-1">Callback URL (ضعه في إعدادات Instagram Webhook في Meta):</span>
                            <div class="p-2.5 bg-gray-50 dark:bg-gray-900 font-mono text-[11px] rounded-lg border border-gray-200 dark:border-gray-800 select-all break-all text-pink-600 dark:text-pink-400 font-bold" dir="ltr">
                                {{ $webhookUrl }}
                            </div>
                        </div>

                        <div>
                            <span class="text-gray-500 block mb-1">Verify Token (رمز التحقق):</span>
                            <div class="p-2.5 bg-gray-50 dark:bg-gray-900 font-mono text-[11px] rounded-lg border border-gray-200 dark:border-gray-800 select-all break-all text-emerald-600 dark:text-emerald-400 font-bold" dir="ltr">
                                {{ $verifyToken }}
                            </div>
                        </div>

                        <div class="p-3 bg-purple-50 dark:bg-purple-950/40 rounded-lg border border-purple-200 dark:border-purple-800/60 text-purple-900 dark:text-purple-200 space-y-1.5">
                            <span class="font-bold flex items-center gap-1 text-[11px]">
                                <x-heroicon-m-information-circle class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                                خطوات التفعيل في 4 خطوات:
                            </span>
                            <ol class="list-decimal list-inside text-[11px] leading-relaxed space-y-1">
                                <li>حوّل حساب انستقرام إلى <strong>احترافي (Business/Creator)</strong> وفعّل خيار <strong>"السماح بالوصول إلى الرسائل"</strong> من إعدادات التطبيق.</li>
                                <li>من لوحة <strong>Meta for Developers</strong> أضف منتج <strong>Instagram</strong>.</li>
                                <li>انسخ <strong>Account ID</strong> و<strong>Access Token</strong> وضعهما في النموذج المقابل.</li>
                                <li>أدخل رابط الـ Webhook ورمز التحقق أعلاه واشترك في حقلَي <code>messages</code> و<code>comments</code>.</li>
                            </ol>
                        </div>
                    </div>
                </x-filament::card>
            </div>
        </div>
    </div>
</x-filament-panels::page>
