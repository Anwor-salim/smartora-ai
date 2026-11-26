@php
    $record = $getRecord();
    $messages = $record ? $record->messages()->orderBy('id')->get() : collect();
    $channel = $record?->channel ?? 'whatsapp';
    $isInstagram = $channel === 'instagram';
    $isMessenger = $channel === 'messenger';
    $dotColor = $isInstagram ? 'bg-pink-500' : ($isMessenger ? 'bg-blue-500' : 'bg-emerald-500');
    $channelTitle = $isInstagram ? 'Instagram DM' : ($isMessenger ? 'Facebook Messenger' : 'WhatsApp');
    $botLabelColor = $isInstagram
        ? 'text-pink-600 dark:text-pink-400'
        : ($isMessenger ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400');

    $state = $record?->conversation_state ?? 'browsing';
    $stateMeta = match ($state) {
        'booking' => ['label' => 'طلب حجز موعد', 'classes' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'inquiring' => ['label' => 'استفسار نشط', 'classes' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300'],
        'human_handoff' => ['label' => 'تحويل لموظف', 'classes' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'],
        default => ['label' => 'تصفح عام', 'classes' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'],
    };
@endphp

<div class="rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden bg-[#efeae2] dark:bg-[#0b141a]" dir="rtl">
    <div class="px-4 py-2.5 border-b border-slate-200/70 dark:border-slate-800 bg-white/90 dark:bg-slate-900/90 flex flex-wrap items-center justify-between gap-2 text-xs">
        <div class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100">
            <span class="w-2 h-2 rounded-full {{ $dotColor }}"></span>
            <span>سجل المحادثة ({{ $messages->count() }} رسالة)</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $stateMeta['classes'] }}">
                {{ $stateMeta['label'] }}
            </span>
            @if($record?->ai_paused)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300">
                    البوت متوقف مؤقتاً
                </span>
            @endif
        </div>
        <span class="text-[11px] text-slate-500 dark:text-slate-400">
            {{ $channelTitle }}
        </span>
    </div>

    @if(!empty($record?->summary))
        <div class="px-4 py-2 bg-slate-50/95 dark:bg-slate-900/70 border-b border-slate-200/70 dark:border-slate-800 text-[11px] text-slate-600 dark:text-slate-300">
            <span class="font-bold text-slate-800 dark:text-slate-100">ملخص السياق:</span>
            <span>{{ $record->summary }}</span>
        </div>
    @endif

    <div class="p-4 space-y-2.5 max-h-[460px] overflow-y-auto smartora-scroll">
        @forelse($messages as $msg)
            @if($msg->sender === 'customer')
                <div class="flex justify-end">
                    <div class="max-w-[78%] bg-[#d9fdd3] dark:bg-[#005c4b] text-slate-900 dark:text-slate-100 rounded-2xl rounded-tr-none px-3.5 py-2 shadow-sm">
                        <div class="text-[10px] font-bold text-emerald-700 dark:text-emerald-300 mb-0.5">العميل</div>
                        <p class="text-xs sm:text-sm whitespace-pre-wrap leading-relaxed">{{ $msg->message }}</p>
                        <div class="text-[10px] text-slate-500 dark:text-slate-300 text-left mt-1">
                            {{ $msg->created_at?->format('d/m H:i') }}
                        </div>
                    </div>
                </div>
            @elseif($msg->sender === 'human')
                <div class="flex justify-start">
                    <div class="max-w-[78%] bg-amber-50 dark:bg-amber-950/50 text-slate-900 dark:text-slate-100 rounded-2xl rounded-tl-none px-3.5 py-2 shadow-sm border border-amber-200 dark:border-amber-800/60">
                        <div class="text-[10px] font-bold text-amber-700 dark:text-amber-300 mb-0.5">
                            موظف الدعم
                        </div>
                        <p class="text-xs sm:text-sm whitespace-pre-wrap leading-relaxed">{{ $msg->message }}</p>
                        <div class="text-[10px] text-slate-400 text-left mt-1">
                            {{ $msg->created_at?->format('d/m H:i') }}
                        </div>
                    </div>
                </div>
            @else
                <div class="flex justify-start">
                    <div class="max-w-[78%] bg-white dark:bg-[#202c33] text-slate-900 dark:text-slate-100 rounded-2xl rounded-tl-none px-3.5 py-2 shadow-sm border border-slate-100 dark:border-transparent">
                        <div class="text-[10px] font-bold {{ $botLabelColor }} mb-0.5">
                            البوت الذكي
                        </div>
                        <p class="text-xs sm:text-sm whitespace-pre-wrap leading-relaxed">{{ $msg->message }}</p>
                        <div class="text-[10px] text-slate-400 text-left mt-1">
                            {{ $msg->created_at?->format('d/m H:i') }}
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="py-10 text-center text-xs text-slate-500 dark:text-slate-400">
                لا توجد رسائل مسجلة في هذه المحادثة.
            </div>
        @endforelse
    </div>
</div>
