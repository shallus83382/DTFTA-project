<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AttachBearerTokenFromCookie
{
    /**
     * Attach Bearer token from auth_token cookie so auth:sanctum can authenticate web routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->bearerToken()) {
            $token = (string) $request->cookie('auth_token', '');
            // Cookie value may be URL-encoded by browser/client JS.
            $token = trim(rawurldecode($token), "\"' \t\n\r\0\x0B");
            if ($token !== '') {
                $request->headers->set('Authorization', 'Bearer ' . $token);

                // Fallback: pre-authenticate user from personal access token
                // so downstream auth:sanctum and web guard both see an authenticated user.
                $accessToken = PersonalAccessToken::findToken($token);
                if ($accessToken && $accessToken->tokenable) {
                    Auth::setUser($accessToken->tokenable);
                }
            }
        }

        return $next($request);
    }
}
