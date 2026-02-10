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
        $shop = Shop::updateOrCreate(
            [
                'shop_domain' => $request->shop_domain,
            ],
            [
                'shopify_access_token' => $request->shopify_access_token
                    ? encrypt($request->shopify_access_token)
                    : null,

                'shopify_scopes' => $request->shopify_scopes ?? '',

                'shopify_api_version' => config('services.shopify.api_version', '2025-10'),

                'fulfillment_service_id' => $request->fulfillment_service_id ?? null,
                'location_id' => $request->location_id ?? null,

                'status' => $request->status ?? 'active',

                'installed_at' => $request->installed_at ?? now(),
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
}
