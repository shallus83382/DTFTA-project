<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FulfillmentService;

class FulfillmentServiceController extends Controller
{
    /**
     * POST /api/v1/fulfillment-services
     * Create a fulfillment service
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shop_id' => 'required|exists:shops,id',
            'service_id' => 'required|string|unique:fulfillment_services,service_id',
            'name' => 'required|string',
            'tracking_support' => 'nullable|boolean',
            'requires_shipping_method' => 'nullable|boolean',
            'inventory_management' => 'nullable|boolean',
            'handle' => 'required|string'
        ]);

        $service = FulfillmentService::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fulfillment service created successfully.',
            'data' => $service
        ], 201);
    }

    /**
     * GET /api/v1/fulfillment-services
     * Get all fulfillment services with filters
     */
    public function index(Request $request)
    {
        $query = FulfillmentService::query();

        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }

        $services = $query->paginate(20);

        return response()->json([
                'success' => true, 
                'message' => 'Fulfillment services retrieved successfully.',
                'data' => $services
            ]);
    }

    /**
     * GET /api/v1/fulfillment-services/{id}
     * Get a specific fulfillment service
     */
    public function show($id)
    {
        $service = FulfillmentService::with('shop', 'shipments')->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Fulfillment service not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fulfillment service retrieved successfully.',
            'data' => $service
        ]);
    }

    /**
     * PUT /api/v1/fulfillment-services/{id}
     * Update a fulfillment service
     */
    public function update(Request $request, $id)
    {
        $service = FulfillmentService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Fulfillment service not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string',
            'tracking_support' => 'nullable|boolean',
            'requires_shipping_method' => 'nullable|boolean',
            'inventory_management' => 'nullable|boolean'
        ]);

        $service->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fulfillment service updated successfully.',
            'data' => $service
        ]);
    }

    /**
     * DELETE /api/v1/fulfillment-services/{id}
     * Delete a fulfillment service
     */
    public function destroy($id)
    {
        $service = FulfillmentService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Fulfillment service not found.'
            ], 404);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fulfillment service deleted successfully.'
        ]);
    }
}
