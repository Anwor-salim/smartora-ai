@if(auth()->user()?->isSuperAdmin())
    <div class="mb-4 p-3 bg-amber-500/15 border border-amber-500/30 rounded-xl flex items-center justify-between text-xs text-amber-900 dark:text-amber-200 shadow-sm" dir="rtl">
        <div class="flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-500 text-white font-bold text-[11px] shrink-0">⚡</span>
            <span>وضع المشرف العام: أنت تتحكم حالياً بلوحة المنشأة: <strong class="text-sm font-bold text-amber-950 dark:text-white">{{ auth()->user()->company?->name ?? 'غير محدد' }}</strong> (معرف: {{ auth()->user()->company_id }})</span>
        </div>
        <a href="/admin/companies" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg transition shadow-sm shrink-0">
            <span>العودة لقائمة الشركات</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
    </div>
@endif
