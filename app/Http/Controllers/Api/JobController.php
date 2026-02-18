<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Job;

class JobController extends Controller
{
    /**
     * POST /api/v1/jobs
     * Create a new background job
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shop_id' => 'required|exists:shops,id',
            'order_id' => 'nullable|exists:orders,id',
            'job_type' => 'required|string',
            'payload' => 'nullable|array'
        ]);

        $job = Job::create([
            ...$validated,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Job created successfully.',
            'data' => $job
        ], 201);
    }

    /**
     * GET /api/v1/jobs
     * Get all jobs with filtering
     */
    public function index(Request $request)
    {
        $query = Job::query();

        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('job_type')) {
            $query->where('job_type', $request->job_type);
        }

        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        $jobs = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Jobs retrieved successfully.',
            'data' => $jobs
        ]);
    }

    /**
     * GET /api/v1/jobs/{id}
     * Get a specific job
     */
    public function show($id)
    {
        $job = Job::with('shop', 'order')->find($id);

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Job retrieved successfully.',
            'data' => $job
        ]);
    }

    /**
     * PUT /api/v1/jobs/{id}
     * Update a job status
     */
    public function update(Request $request, $id)
    {
        $job = Job::find($id);

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found.'
            ], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,failed,artwork_needed,in_production,shipped,cancelled,exception',
            'result' => 'nullable|array',
            'error_message' => 'nullable|string'
        ]);

        $updateData = $validated;
        
        if ($validated['status'] === 'processing' || $validated['status'] === 'in_production') {
            $updateData['started_at'] = now();
        } elseif ($validated['status'] === 'completed' || $validated['status'] === 'shipped') {
            $updateData['completed_at'] = now();
        } elseif (in_array($validated['status'], ['failed', 'exception', 'cancelled'], true)) {
            $updateData['failed_at'] = now();
        }

        $job->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Job updated successfully.',
            'data' => $job
        ]);
    }

    /**
     * DELETE /api/v1/jobs/{id}
     * Delete a job
     */
    public function destroy($id)
    {
        $job = Job::find($id);

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found.'
            ], 404);
        }

        $job->delete();

        return response()->json([
            'success' => true,
            'message' => 'Job deleted successfully.'
        ]);
    }

    /**
     * GET /api/v1/jobs/stats/summary
     * Get job statistics
     */
    public function stats()
    {
        $total = Job::count();
        $byStatus = Job::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();
        $byType = Job::selectRaw('job_type, count(*) as count')
            ->groupBy('job_type')
            ->get();
        $pending = Job::where('status', 'pending')->count();
        $failed = Job::where('status', 'failed')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_jobs' => $total,
                'pending_jobs' => $pending,
                'failed_jobs' => $failed,
                'by_status' => $byStatus,
                'by_type' => $byType
            ]
        ]);
    }

    /**
     * POST /api/v1/jobs/retry-failed
     * Retry all failed jobs
     */
    public function retryFailed()
    {
        $failedJobs = Job::where('status', 'failed')->get();

        foreach ($failedJobs as $job) {
            $job->update([
                'status' => 'pending',
                'error_message' => null,
                'failed_at' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($failedJobs) . ' failed jobs marked for retry.',
            'data' => [
                'retried_count' => count($failedJobs)
            ]
        ]);
    }
}
