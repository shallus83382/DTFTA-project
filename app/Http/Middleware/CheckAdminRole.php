<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return redirect()->route('login');
        }

        if ($request->user()->role !== 'admin') {
            if (!$request->expectsJson()) {
                abort(403, 'Unauthorized. Admin role required.');
            }

            return response()->json([
                'message' => 'Unauthorized. Admin role required.',
            ], 403);
        }

        return $next($request);
    }
}
