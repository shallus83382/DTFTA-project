<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserManagementController extends Controller
{
    use AuthorizesRequests;
    /**
     * Get all users (admin only)
     */
    public function index(Request $request)
    {
        $this->authorize('isAdmin', User::class);

        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 10);
        $role = $request->query('role');
        $active = $request->query('active');

        $query = User::query();

        if ($role) {
            $query->where('role', $role);
        }

        if ($active !== null) {
            $query->where('is_active', (bool) $active);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $users->items(),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Create new user (admin only)
     */
    public function store(Request $request)
    {
        $this->authorize('isAdmin', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'role' => 'required|in:admin,manager,user',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => true,
        ]);

        AdminActivityLog::logActivity(
            $request->user()->id,
            'create',
            'User',
            $user->id,
            ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
            $request->ip()
        );

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ], 201);
    }

    /**
     * Get user by ID
     */
    public function show(Request $request, User $user)
    {
        if ($request->user()->id !== $user->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Update user (admin only, or own profile)
     */
    public function update(Request $request, User $user)
    {
        if ($request->user()->id !== $user->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'role' => $request->user()->isAdmin() ? 'nullable|in:admin,manager,user' : null,
            'is_active' => $request->user()->isAdmin() ? 'nullable|boolean' : null,
        ]);

        $changes = [];

        foreach ($validated as $key => $value) {
            if ($value !== null && $user->$key !== $value) {
                $changes[$key] = ['old' => $user->$key, 'new' => $value];
            }
        }

        if (!empty($changes)) {
            $user->update($validated);

            if ($request->user()->isAdmin()) {
                AdminActivityLog::logActivity(
                    $request->user()->id,
                    'update',
                    'User',
                    $user->id,
                    $changes,
                    $request->ip()
                );
            }
        }

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user,
        ]);
    }


    /**
     * Delete user (admin only)
     */
    public function destroy(Request $request, User $user)
    {
        $this->authorize('isAdmin', User::class);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];

        $user->delete();

        AdminActivityLog::logActivity(
            $request->user()->id,
            'delete',
            'User',
            $user->id,
            $userData,
            $request->ip()
        );

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Get activity logs (admin only)
     */
    public function activityLogs(Request $request)
    {
        $this->authorize('isAdmin', User::class);

        $logs = AdminActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }
}
