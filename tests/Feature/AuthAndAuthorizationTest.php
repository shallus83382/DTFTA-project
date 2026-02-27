<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_login_redirects_to_dashboard_and_authenticates_user(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->from('/login')->post('/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect('/crm/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_web_login_rejects_inactive_user(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'user',
            'is_active' => false,
        ]);

        $response = $this->from('/login')->post('/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_admin_middleware_returns_correct_response_for_web_and_json_requests(): void
    {
        Route::middleware('admin')->get('/_test/admin-only', fn () => response()->json(['ok' => true]));

        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->actingAs($manager)->get('/_test/admin-only')->assertForbidden();
        $this->actingAs($manager)->getJson('/_test/admin-only')->assertStatus(403);
        auth()->logout();
        $this->getJson('/_test/admin-only')->assertStatus(401);
    }
}
