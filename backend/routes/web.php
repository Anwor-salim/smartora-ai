<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        if (auth()->user()->isSuperAdmin()) {
            return redirect('/admin');
        }
        return redirect('/company');
    }
    return redirect('/company/login');
});

$handleLocaleSwitch = function (string $locale) {
    if (in_array($locale, ['ar', 'en'])) {
        session(['locale' => $locale]);
        app()->setLocale($locale);
    }
    $referer = request()->headers->get('referer');
    if ($referer) {
        return redirect()->away($referer);
    }
    return redirect()->back();
};

Route::get('/switch-language/{locale}', $handleLocaleSwitch)->name('switch-language');
Route::get('/company/switch-language/{locale}', $handleLocaleSwitch)->name('company.switch-language');
Route::get('/admin/switch-language/{locale}', $handleLocaleSwitch)->name('admin.switch-language');


