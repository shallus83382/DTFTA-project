<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FailedWebhook;

class FailedWebhookController extends Controller
{
    /**
     * GET /api/v1/failed-webhooks
     * Get all failed webhooks for retry
     */
    public function index(Request $request)
    {
        $query = FailedWebhook::query();

        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $failedWebhooks = $query->where('retry_count', '<', 5)->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Failed webhooks retrieved successfully.',
            'data' => $failedWebhooks
        ]);
    }

    /**
     * GET /api/v1/failed-webhooks/{id}
     * Get a specific failed webhook
     */
    public function show($id)
    {
        $failedWebhook = FailedWebhook::find($id);

        if (!$failedWebhook) {
            return response()->json([
                'success' => false,
                'message' => 'Failed webhook not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Failed webhook retrieved successfully.',
            'data' => $failedWebhook
        ]);
    }

    /**
     * POST /api/v1/failed-webhooks/{id}/retry
     * Retry a failed webhook
     */
    public function retry(Request $request, $id)
    {
        $failedWebhook = FailedWebhook::find($id);

        if (!$failedWebhook) {
            return response()->json([
                'success' => false,
                'message' => 'Failed webhook not found.'
            ], 404);
        }

        if ($failedWebhook->retry_count >= $failedWebhook->max_retries) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum retries exceeded.'
            ], 400);
        }

        try {
            // Attempt to process the webhook again
            // This would typically delegate to webhook processor service
            
            $failedWebhook->update([
                'retry_count' => $failedWebhook->retry_count + 1,
                'last_attempted_at' => now(),
                'next_retry_at' => now()->addMinutes(5 * ($failedWebhook->retry_count + 1))
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook retry scheduled successfully.',
                'data' => $failedWebhook
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry webhook: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/failed-webhooks/{id}
     * Delete a failed webhook
     */
    public function destroy($id)
    {
        $failedWebhook = FailedWebhook::find($id);

        if (!$failedWebhook) {
            return response()->json([
                'success' => false,
                'message' => 'Failed webhook not found.'
            ], 404);
        }

        $failedWebhook->delete();

        return response()->json([
            'success' => true,
            'message' => 'Failed webhook deleted successfully.'
        ]);
    }

    /**
     * GET /api/v1/failed-webhooks/stats/summary
     * Get failed webhooks statistics
     */
    public function stats()
    {
        $total = FailedWebhook::count();
        $byStatus = FailedWebhook::selectRaw('event_type, count(*) as count')
            ->groupBy('event_type')
            ->get();
        $byShop = FailedWebhook::selectRaw('shop_id, count(*) as count')
            ->groupBy('shop_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_failed' => $total,
                'by_event_type' => $byStatus,
                'by_shop' => $byShop
            ]
        ]);
    }
}
