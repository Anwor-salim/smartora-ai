<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectCompanyAdminFromAdminPanel
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && !auth()->user()->isSuperAdmin()) {
            // User is a company admin trying to access the Super Admin control panel
            return redirect('/company');
        }

        return $next($request);
    }
}
