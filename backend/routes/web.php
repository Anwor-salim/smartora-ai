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

Route::get('/switch-language/{locale}', function (string $locale) {
    if (in_array($locale, ['ar', 'en'])) {
        session(['locale' => $locale]);
        app()->setLocale($locale);
    }
    return redirect()->back();
})->name('switch-language');


