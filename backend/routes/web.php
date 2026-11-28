<?php

use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/register', [LandingController::class, 'registerPage'])->name('company.register');
Route::post('/register', [LandingController::class, 'register'])->name('company.register.submit');

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
$frontendBase = fn (): string => rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');

Route::get('/admin/switch-company/{company}', function (\App\Models\Company $company) use ($frontendBase) {
    $user = auth()->user();
    if (! $user || ! $user->isSuperAdmin()) {
        return redirect('/admin/login');
    }
    $user->update(['company_id' => $company->id]);
    return redirect()->away($frontendBase() . '/company');
})->middleware(['web', 'auth'])->name('admin.switch-company');

Route::get('/company/instagram/oauth/callback', function (\Illuminate\Http\Request $request) use ($frontendBase) {
    $targetBase = $frontendBase() . '/company/channels/instagram';
    $error = $request->query('error_description') ?? $request->query('error');
    if ($error) {
        return redirect()->away($targetBase . '?oauth=error&message=' . urlencode((string) $error));
    }

    $code = (string) $request->query('code', '');
    $state = (string) $request->query('state', '');

    if ($code === '' || $state === '') {
        return redirect()->away($targetBase);
    }

    try {
        $decoded = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($state), true);
        $companyId = $decoded['company_id'] ?? null;
    } catch (\Throwable) {
        return redirect()->away($targetBase . '?oauth=error&message=' . urlencode('انتهت صلاحية جلسة الربط، يرجى المحاولة مجدداً'));
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

        return redirect()->away($targetBase . '?oauth=success');
    }

    return redirect()->away($targetBase . '?oauth=error&message=' . urlencode((string) ($res['error'] ?? 'يرجى المحاولة مرة أخرى.')));
})->name('company.instagram.oauth.callback');

Route::get('/company/messenger/oauth/callback', function (\Illuminate\Http\Request $request) use ($frontendBase) {
    $targetBase = $frontendBase() . '/company/channels/messenger';
    $error = $request->query('error_description') ?? $request->query('error');
    if ($error) {
        return redirect()->away($targetBase . '?oauth=error&message=' . urlencode((string) $error));
    }

    $code = (string) $request->query('code', '');
    $state = (string) $request->query('state', '');

    if ($code === '' || $state === '') {
        return redirect()->away($targetBase);
    }

    try {
        $decoded = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($state), true);
        $companyId = $decoded['company_id'] ?? null;
    } catch (\Throwable) {
        return redirect()->away($targetBase . '?oauth=error&message=' . urlencode('انتهت صلاحية جلسة الربط، يرجى المحاولة مجدداً'));
    }

    $user = auth()->user();
    $company = $companyId ? \App\Models\Company::find($companyId) : ($user?->company);
    if (!$company || ($user && !$user->isSuperAdmin() && $user->company_id !== $company->id)) {
        abort(403);
    }

    $messengerService = app(\App\Services\MessengerService::class);
    $res = $messengerService->exchangeOAuthCode($code);

    if (!empty($res['success'])) {
        $company->update([
            'messenger_page_id' => $res['page_id'] ?? $company->messenger_page_id,
            'messenger_page_name' => $res['page_name'] ?? $company->messenger_page_name,
            'messenger_access_token' => $res['access_token'],
            'messenger_status' => 'connected',
            'messenger_connected_at' => now(),
        ]);

        return redirect()->away($targetBase . '?oauth=success');
    }

    return redirect()->away($targetBase . '?oauth=error&message=' . urlencode((string) ($res['error'] ?? 'يرجى المحاولة مرة أخرى.')));
})->name('company.messenger.oauth.callback');

// Phase 1: Redirect legacy Filament /company paths to the standalone Nuxt 4 Company Dashboard
Route::get('/company/login', fn () => redirect()->away($frontendBase() . '/login'));
Route::get('/company/{any?}', function (?string $any = null) use ($frontendBase) {
    $suffix = $any ? ('/company/' . ltrim($any, '/')) : '/company';
    return redirect()->away($frontendBase() . $suffix);
})->where('any', '.*');


