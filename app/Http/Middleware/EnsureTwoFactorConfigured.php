<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->two_factor_confirmed_at) {
            return redirect()->route('security.2fa');
        }

        return $next($request);
    }
}
