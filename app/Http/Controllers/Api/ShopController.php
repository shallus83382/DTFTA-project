<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shop;

class ShopController extends Controller
{
    /**
     * POST /api/v1/shops
     * Creates or updates a shop record based on the provided shop_domain.
     * Expects JSON body with shop_domain, shopify_access_token, shopify_scopes, fulfillment_service_id, location_id, status, installed_at.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shop_domain' => 'required|string|max:255',
            'shopify_access_token' => 'nullable|string',
            'shopify_scopes' => 'nullable|string',
            'fulfillment_service_id' => 'nullable|string|max:255',
            'location_id' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,suspended,uninstalled',
            'installed_at' => 'nullable|date',
        ]);

        $shop = Shop::updateOrCreate(
            [
                'shop_domain' => $validated['shop_domain'],
            ],
            [
                'shopify_access_token' => !empty($validated['shopify_access_token'])
                    ? encrypt($validated['shopify_access_token'])
                    : null,

                'shopify_scopes' => $validated['shopify_scopes'] ?? '',

                'shopify_api_version' => config('services.shopify.api_version', '2025-10'),

                'fulfillment_service_id' => $validated['fulfillment_service_id'] ?? null,
                'location_id' => $validated['location_id'] ?? null,

                'status' => $validated['status'] ?? 'active',

                'installed_at' => $validated['installed_at'] ?? now(),
                'uninstalled_at' => null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Shop record created successfully.',
            'data' => $shop
        ], 201);
    }
    /**
     * GET /api/v1/shops/{shop_domain}
     * Retrieves a shop record by its shop_domain, including its partner profile if it exists.
     */
    public function show($shop_domain)
    {
        $data = Shop::where('shop_domain', $shop_domain)->with('partnerProfile')->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Shop record not found.',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shop record retrieved successfully.',
            'data' => $data
        ]);
    }

    /**
     * DELETE /api/v1/shops/{shop_domain}
     * Deletes a shop record by its shop_domain.
     */
    public function destroy($shop_domain)
    {
        $shop = Shop::where('shop_domain', $shop_domain)->first();
        if ($shop) {
            $shop->delete();
            return response()->json([
                'success' => true,
                'message' => 'Shop record deleted successfully.'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Shop record not found.'
            ], 404);
        }
    }
    /** Get all shops */
    public function index()
    {
        $shops = Shop::get();   
        return response()->json([
            'success' => true,
            'message' => 'Shops retrieved successfully.',
            'data' => $shops
        ]);
    }

    /**
     * PUT /api/v1/shops/{shop_domain}
     * Update an existing shop by domain.
     */
    public function update(Request $request, $shop_domain)
    {
        $shop = Shop::where('shop_domain', $shop_domain)->first();

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop record not found.'
            ], 404);
        }

        $validated = $request->validate([
            'shopify_access_token' => 'nullable|string',
            'shopify_scopes' => 'nullable|string',
            'fulfillment_service_id' => 'nullable|string|max:255',
            'location_id' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,suspended,uninstalled',
            'installed_at' => 'nullable|date',
            'uninstalled_at' => 'nullable|date',
        ]);

        $updateData = [];
        if (array_key_exists('shopify_access_token', $validated)) {
            $updateData['shopify_access_token'] = !empty($validated['shopify_access_token'])
                ? encrypt($validated['shopify_access_token'])
                : null;
        }
        if (array_key_exists('shopify_scopes', $validated)) {
            $updateData['shopify_scopes'] = $validated['shopify_scopes'];
        }
        foreach (['fulfillment_service_id', 'location_id', 'status', 'installed_at', 'uninstalled_at'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }

        if (!empty($updateData)) {
            $shop->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shop record updated successfully.',
            'data' => $shop->fresh()
        ]);
    }
}
