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
Route::get('/admin/switch-company/{company}', function (\App\Models\Company $company) {
    $user = auth()->user();
    if (! $user || ! $user->isSuperAdmin()) {
        return redirect('/admin/login');
    }
    $user->update(['company_id' => $company->id]);
    return redirect('/company');
})->middleware(['web', 'auth'])->name('admin.switch-company');

Route::get('/company/instagram/oauth/callback', function (\Illuminate\Http\Request $request) {
    $error = $request->query('error_description') ?? $request->query('error');
    if ($error) {
        \Filament\Notifications\Notification::make()
            ->title('تعذر إتمام ربط انستقرام')
            ->body((string) $error)
            ->danger()
            ->send();
        return redirect('/company/instagram-connection');
    }

    $code = (string) $request->query('code', '');
    $state = (string) $request->query('state', '');

    if ($code === '' || $state === '') {
        return redirect('/company/instagram-connection');
    }

    try {
        $decoded = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($state), true);
        $companyId = $decoded['company_id'] ?? null;
    } catch (\Throwable $e) {
        \Filament\Notifications\Notification::make()
            ->title('انتهت صلاحية جلسة الربط، يرجى المحاولة مجدداً')
            ->danger()
            ->send();
        return redirect('/company/instagram-connection');
    }

    $user = auth()->user();
    $company = $companyId ? \App\Models\Company::find($companyId) : ($user?->company);
    if (!$company || ($user && !$user->isSuperAdmin() && $user->company_id !== $company->id)) {
        abort(403);
    }

    $igService = app(\App\Services\InstagramService::class);
    $res = $igService->exchangeOAuthCode($code);

    if (!empty($res['success'])) {
        $company->update([
            'instagram_account_id' => $res['account_id'] ?? $company->instagram_account_id,
            'instagram_username' => $res['username'] ?? $company->instagram_username,
            'instagram_access_token' => $res['access_token'],
            'instagram_status' => 'connected',
            'instagram_connected_at' => now(),
        ]);

        \Filament\Notifications\Notification::make()
            ->title('تم ربط حساب انستقرام بنجاح! 🎉')
            ->body('الحساب المرتبط: @' . ($res['username'] ?? $res['account_id']))
            ->success()
            ->send();
    } else {
        \Filament\Notifications\Notification::make()
            ->title('فشل تبديل رمز الدخول مع انستقرام')
            ->body($res['error'] ?? 'يرجى المحاولة مرة أخرى.')
            ->danger()
            ->send();
    }

    return redirect('/company/instagram-connection');
})->middleware(['web', 'auth'])->name('company.instagram.oauth.callback');


