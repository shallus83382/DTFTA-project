<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Http;
use App\Models\Shop;

class ShopifyService
{
    protected $apiVersion = '2025-10';

    /**
     * Create fulfillment (shipment) via Shopify API
     */
    public function createFulfillment($shopId, $shopifyOrderId, $data)
    {
        try {
            $shop = Shop::find($shopId);
            if (!$shop) {
                return [
                    'success' => false,
                    'message' => 'Shop not found'
                ];
            }

            // TODO: Replace with actual Shopify API call when credentials are available
            // For now, return dummy response
            return [
                'success' => true,
                'data' => [
                    'id' => fake()->numerify('gid://shopify/Fulfillment/######'),
                    'order_id' => $shopifyOrderId,
                    'status' => 'success',
                    'line_items' => $data['line_items'] ?? [],
                    'tracking_info' => $data['tracking_info'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ];

            // Actual implementation below (uncomment when ready):
            /*
            $url = "https://{$shop->shop_domain}/admin/api/{$this->apiVersion}/orders/{$shopifyOrderId}/fulfillments.json";
            
            $response = Http::withToken(decrypt($shop->shopify_access_token))
                ->post($url, [
                    'fulfillment' => [
                        'line_items_by_fulfillment_orders' => [
                            [
                                'fulfillment_orders' => $data['fulfillment_orders'] ?? [],
                                'fulfillment_service_id' => $data['fulfillment_service_id'] ?? null
                            ]
                        ],
                        'tracking_info' => $data['tracking_info'] ?? null
                    ]
                ]);

            return [
                'success' => $response->successful(),
                'data' => $response->json('fulfillment'),
                'error' => !$response->successful() ? $response->json() : null
            ];
            */
        } catch (\Exception $e) {
            Log::error('Shopify API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel fulfillment via Shopify API
     */
    public function cancelFulfillment($shopId, $shopifyOrderId, $fulfillmentId)
    {
        try {
            // Dummy response for now
            return [
                'success' => true,
                'data' => [
                    'id' => $fulfillmentId,
                    'status' => 'cancelled',
                    'cancelled_at' => now()
                ]
            ];

            // Actual implementation:
            /*
            $shop = Shop::find($shopId);
            if (!$shop) {
                return ['success' => false, 'message' => 'Shop not found'];
            }

            $url = "https://{$shop->shop_domain}/admin/api/{$this->apiVersion}/fulfillments/{$fulfillmentId}/cancel.json";
            
            $response = Http::withToken(decrypt($shop->shopify_access_token))
                ->post($url);

            return [
                'success' => $response->successful(),
                'data' => $response->json('fulfillment')
            ];
            */
        } catch (\Exception $e) {
            Log::error('Cancel Fulfillment Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get order details from Shopify
     */
    public function getOrder($shopId, $shopifyOrderId)
    {
        try {
            // Dummy response
            return [
                'success' => true,
                'data' => [
                    'id' => $shopifyOrderId,
                    'order_number' => fake()->numerify('####'),
                    'fulfillment_status' => 'unfulfilled',
                    'line_items' => [],
                    'customer' => [
                        'email' => fake()->email(),
                        'first_name' => fake()->firstName(),
                        'last_name' => fake()->lastName()
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get fulfillment services for shop
     */
    public function getFulfillmentServices($shopId)
    {
        try {
            // Dummy response
            return [
                'success' => true,
                'data' => [
                    [
                        'id' => 'gid://shopify/FulfillmentService/1',
                        'name' => 'Manual Fulfillment',
                        'type' => 'manual',
                        'tracking_support' => true
                    ],
                    [
                        'id' => 'gid://shopify/FulfillmentService/2',
                        'name' => '3PL Integration',
                        'type' => 'partner',
                        'tracking_support' => true
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update fulfillment tracking
     */
    public function updateFulfillmentTracking($shopId, $fulfillmentId, $trackingInfo)
    {
        try {
            // Dummy response
            return [
                'success' => true,
                'data' => [
                    'id' => $fulfillmentId,
                    'tracking_info' => $trackingInfo,
                    'updated_at' => now()
                ]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Register webhook with Shopify
     */
    public function registerWebhook($shopId, $topic, $callbackUrl)
    {
        try {
            // Dummy response
            return [
                'success' => true,
                'data' => [
                    'id' => fake()->numerify('###########'),
                    'topic' => $topic,
                    'address' => $callbackUrl,
                    'created_at' => now()
                ]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
