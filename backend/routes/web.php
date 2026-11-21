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
