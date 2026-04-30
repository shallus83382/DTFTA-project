<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class AdminActivityLog extends Model
{
    protected $table = 'admin_activity_logs';

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'changes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'changes' => 'json',
    ];

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an activity
     */
    public static function logActivity(
        $userId,
        $action,
        $modelType,
        $modelId = null,
        $changes = null,
        $ipAddress = null,
        $userAgent = null
    ) {
        if (!$userId) {
            $userId = self::resolveFallbackUserId();
        }

        if (!$userId) {
            Log::warning('AdminActivityLog skipped because no user is available', [
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
            ]);
            return null;
        }

        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'changes' => $changes,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public static function logSystemActivity(
        string $action,
        string $modelType,
        $modelId = null,
        $changes = null
    ) {
        return self::logActivity(
            null,
            $action,
            $modelType,
            $modelId,
            $changes,
            request()?->ip(),
            request()?->userAgent()
        );
    }

    private static function resolveFallbackUserId(): ?int
    {
        $adminId = User::query()
            ->where('role', 'admin')
            ->orderBy('id')
            ->value('id');

        if ($adminId) {
            return (int) $adminId;
        }

        $firstUserId = User::query()->orderBy('id')->value('id');
        return $firstUserId ? (int) $firstUserId : null;
    }
}
