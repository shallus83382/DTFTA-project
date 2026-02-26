<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnsureFrontendTokenAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = (string) $request->cookie('auth_token', '');
        if ($plainTextToken === '') {
            return redirect()->route('login');
        }

        $accessToken = PersonalAccessToken::findToken($plainTextToken);
        if (!$accessToken || !$accessToken->tokenable) {
            return redirect()
                ->route('login')
                ->withCookie(cookie()->forget('auth_token'));
        }

        Auth::setUser($accessToken->tokenable);

        return $next($request);
    }
}
