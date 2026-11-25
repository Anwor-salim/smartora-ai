@php
    $isCompany = ($panel ?? 'admin') === 'company';
    $companyName = $isCompany ? auth()->user()?->company?->name : null;
    $subtitle = $isCompany ? ($companyName ?: 'بوابة الشركات') : 'إدارة المنصة';
@endphp

<div class="flex items-center gap-2.5 select-none" dir="rtl">
    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-sm text-white {{ $isCompany ? 'bg-gradient-to-br from-sky-500 to-indigo-600 shadow-sky-500/20' : 'bg-gradient-to-br from-emerald-500 to-teal-600 shadow-emerald-500/20' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.1" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
    </div>
    <div class="leading-tight min-w-0">
        <div class="text-sm font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-1">
            <span>SmartOra</span>
            <span class="{{ $isCompany ? 'text-sky-600 dark:text-sky-400' : 'text-emerald-600 dark:text-emerald-400' }}">AI</span>
        </div>
        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 truncate max-w-[160px]">
            {{ $subtitle }}
        </div>
    </div>
</div>
