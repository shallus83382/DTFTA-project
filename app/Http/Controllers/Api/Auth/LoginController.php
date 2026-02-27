<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    /**
     * Login user and return authentication token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            if (!$request->expectsJson()) {
                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'This account has been deactivated.']);
            }

            return response()->json([
                'message' => 'This account has been deactivated.',
            ], 403);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('admin-token', ['admin'])->plainTextToken;

        $payload = [
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => substr($user->name, 0, 1),
            ],
            'token' => $token,
        ];

        if (!$request->expectsJson()) {
            return redirect()
                ->intended('/crm/dashboard')
                ->withCookie(cookie(
                    'auth_token',
                    $token,
                    60 * 24 * 30,
                    '/',
                    null,
                    false,
                    false,
                    false,
                    'Lax'
                ));
        }

        $response = response()->json($payload);

        // Keep CRM web routes authenticated via sanctum + cookie bridge middleware.
        return $response->cookie(
            'auth_token',
            $token,
            60 * 24 * 30,
            '/',
            null,
            false,
            false,
            false,
            'Lax'
        );
    }

    /**
     * Logout user and revoke tokens
     */
    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully',
        ])->withCookie(cookie()->forget('auth_token'));
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role,
                'avatar' => substr($request->user()->name, 0, 1),
            ],
        ]);
    }
}
