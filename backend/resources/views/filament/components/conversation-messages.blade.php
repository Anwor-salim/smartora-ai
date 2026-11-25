@php
    $record = $getRecord();
    $messages = $record ? $record->messages()->orderBy('created_at')->get() : collect();
    $isInstagram = ($record?->channel ?? 'whatsapp') === 'instagram';
@endphp

<div class="rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden bg-[#efeae2] dark:bg-[#0b141a]" dir="rtl">
    <div class="px-4 py-2.5 border-b border-slate-200/70 dark:border-slate-800 bg-white/80 dark:bg-slate-900/90 flex items-center justify-between text-xs">
        <div class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100">
            <span class="w-2 h-2 rounded-full {{ $isInstagram ? 'bg-pink-500' : 'bg-emerald-500' }}"></span>
            <span>سجل المحادثة ({{ $messages->count() }} رسالة)</span>
        </div>
        <span class="text-[11px] text-slate-500 dark:text-slate-400">
            {{ $isInstagram ? 'Instagram DM' : 'WhatsApp' }}
        </span>
    </div>

    <div class="p-4 space-y-2.5 max-h-[460px] overflow-y-auto smartora-scroll">
        @forelse($messages as $msg)
            @if($msg->direction === 'inbound')
                <div class="flex justify-end">
                    <div class="max-w-[78%] bg-[#d9fdd3] dark:bg-[#005c4b] text-slate-900 dark:text-slate-100 rounded-2xl rounded-tr-none px-3.5 py-2 shadow-sm">
                        <div class="text-[10px] font-bold text-emerald-700 dark:text-emerald-300 mb-0.5">العميل</div>
                        <p class="text-xs sm:text-sm whitespace-pre-wrap leading-relaxed">{{ $msg->content }}</p>
                        <div class="text-[10px] text-slate-500 dark:text-slate-300 text-left mt-1">
                            {{ $msg->created_at?->format('d/m H:i') }}
                        </div>
                    </div>
                </div>
            @else
                <div class="flex justify-start">
                    <div class="max-w-[78%] bg-white dark:bg-[#202c33] text-slate-900 dark:text-slate-100 rounded-2xl rounded-tl-none px-3.5 py-2 shadow-sm border border-slate-100 dark:border-transparent">
                        <div class="text-[10px] font-bold {{ $isInstagram ? 'text-pink-600 dark:text-pink-400' : 'text-emerald-600 dark:text-emerald-400' }} mb-0.5">
                            البوت الذكي
                        </div>
                        <p class="text-xs sm:text-sm whitespace-pre-wrap leading-relaxed">{{ $msg->content }}</p>
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
