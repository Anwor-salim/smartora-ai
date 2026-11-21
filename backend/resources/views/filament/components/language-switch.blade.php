@php
    $routeName = request()->is('company*') ? 'company.switch-language' : (request()->is('admin*') ? 'admin.switch-language' : 'switch-language');
    $targetLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
@endphp
<div class="flex items-center gap-2 px-2">
    <a href="{{ route($routeName, ['locale' => $targetLocale]) }}"
       title="{{ app()->getLocale() === 'ar' ? 'Switch to English' : 'التحويل للغة العربية' }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition shadow-sm border border-gray-200 dark:border-gray-700">
        <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
        </svg>
        <span>{{ app()->getLocale() === 'ar' ? 'English (EN)' : 'العربية (AR)' }}</span>
    </a>
</div>
