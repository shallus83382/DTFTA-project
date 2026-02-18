<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if user is admin
     */
    public function isAdmin(User $user)
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if user is manager or admin
     */
    public function isManager(User $user)
    {
        return in_array($user->role, ['admin', 'manager']);
    }

    /**
     * View user
     */
    public function view(User $user, User $model)
    {
        return $user->id === $model->id || $user->isAdmin();
    }

    /**
     * Update user
     */
    public function update(User $user, User $model)
    {
        return $user->id === $model->id || $user->isAdmin();
    }

    /**
     * Delete user
     */
    public function delete(User $user, User $model)
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }
}
