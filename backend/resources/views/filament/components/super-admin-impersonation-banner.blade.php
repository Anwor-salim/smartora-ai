@if(auth()->user()?->isSuperAdmin())
    <div class="mb-3 px-3.5 py-2 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-xl flex items-center justify-between gap-3 text-xs text-amber-900 dark:text-amber-200" dir="rtl">
        <div class="flex items-center gap-2 min-w-0">
            <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0 animate-pulse"></span>
            <span class="truncate">
                وضع المشرف العام: تتحكم حالياً في منشأة
                <strong class="font-bold text-amber-950 dark:text-white">{{ auth()->user()->company?->name ?? 'غير محدد' }}</strong>
            </span>
        </div>
        <a href="/admin/companies" class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-lg transition shrink-0 text-[11px]">
            <span>العودة للإدارة</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
    </div>
@endif
