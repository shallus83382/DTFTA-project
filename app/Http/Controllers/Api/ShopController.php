<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Services\ShopifyService;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class ShopController extends Controller
{
    public function __construct(private ShopifyService $shopifyService)
    {
    }

    /**
     * POST /api/v1/shops
     * Creates or updates a shop record based on the provided shop_domain.
     * Expects JSON body with shop_domain, shopify_access_token, shopify_scopes, fulfillment_service_id, location_id, status, installed_at.
     */
    public function store(Request $request)
    {
        $shopDomain = trim((string) $request->input('shop_domain', ''));
        $existingShop = $shopDomain !== ''
            ? Shop::where('shop_domain', $shopDomain)->first()
            : null;

        $validated = $request->validate([
            'shop_domain' => 'required|string|max:255',
            'store_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('shops', 'store_id')->ignore($existingShop?->id),
            ],
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'domain' => 'nullable|string|max:255',
            'shop_owner' => 'nullable|string|max:255',
            'shopify_access_token' => 'nullable|string',
            'shopify_scopes' => 'nullable|string',
            'fulfillment_service_id' => 'nullable|string|max:255',
            'location_id' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,suspended,uninstalled',
            'installed_at' => 'nullable|date',
        ]);

        $wasExisting = $existingShop !== null;

        $shop = Shop::updateOrCreate(
            [
                'shop_domain' => $validated['shop_domain'],
            ],
            [
                'store_id' => $validated['store_id'] ?? null,
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'domain' => $validated['domain'] ?? null,
                'shop_owner' => $validated['shop_owner'] ?? null,
                'shopify_access_token' => !empty($validated['shopify_access_token'])
                    ? encrypt($validated['shopify_access_token'])
                    : null,

                'shopify_scopes' => $validated['shopify_scopes'] ?? '',

                'shopify_api_version' => config('services.shopify.api_version', '2025-10'),
                'shopify_webhook_api_version' => config('services.shopify.webhook_api_version', '2026-04'),

                'fulfillment_service_id' => $validated['fulfillment_service_id'] ?? null,
                'location_id' => $validated['location_id'] ?? null,

                'status' => $validated['status'] ?? 'active',

                'installed_at' => $validated['installed_at'] ?? now(),
                'uninstalled_at' => null,
            ]
        );

        $fulfillmentProvision = null;
        if (
            $shop->status === 'active'
            && !empty($shop->shopify_access_token)
            && empty($shop->fulfillment_service_id)
        ) {
            $fulfillmentProvision = $this->shopifyService->ensureFulfillmentServiceAndLocation((int) $shop->id, config('app.url'));
            if (!($fulfillmentProvision['success'] ?? false)) {
                Log::warning('Fulfillment provisioning failed after shop store', [
                    'shop_id' => $shop->id,
                    'shop_domain' => $shop->shop_domain,
                    'result' => $fulfillmentProvision,
                ]);
            } else {
                $shop = $shop->fresh();
            }
        }

        return response()->json([
            'success' => true,
            'message' => $wasExisting ? 'Shop record updated successfully.' : 'Shop record created successfully.',
            'data' => $shop,
            'fulfillment_provisioning' => $fulfillmentProvision,
        ], $wasExisting ? 200 : 201);
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
            'store_id' => 'nullable|string|max:255|unique:shops,store_id,' . $shop->id,
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'domain' => 'nullable|string|max:255',
            'shop_owner' => 'nullable|string|max:255',
            'shopify_access_token' => 'nullable|string',
            'shopify_scopes' => 'nullable|string',
            'fulfillment_service_id' => 'nullable|string|max:255',
            'location_id' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,suspended,uninstalled',
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
        foreach (['store_id', 'name', 'email', 'domain', 'shop_owner', 'fulfillment_service_id', 'location_id', 'status', 'installed_at', 'uninstalled_at'] as $field) {
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
