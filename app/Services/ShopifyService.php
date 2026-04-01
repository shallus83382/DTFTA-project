<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    /**
     * Get the API version to use for Shopify requests
     */
    private function apiVersion(): string
    {
        return (string) config('services.shopify.api_version', '2025-10');
    }

    /**
     * Get webhook API version used for webhook registration/list/delete calls
     */
    private function webhookApiVersion(?Shop $shop = null): string
    {
        $shopValue = $shop?->shopify_webhook_api_version;
        if (is_string($shopValue) && trim($shopValue) !== '') {
            return trim($shopValue);
        }

        return (string) config('services.shopify.webhook_api_version', '2026-04');
    }

    /**
     * Construct the base URL for Shopify API requests based on shop domain and API version
     */
    private function baseUrl(string $shopDomain, ?string $apiVersion = null): string
    {
        return "https://{$shopDomain}/admin/api/" . ($apiVersion ?: $this->apiVersion());
    }

    /**
     * Retrieve shop information and decrypted access token for a given shop ID
     */
    private function getShopAndToken(int $shopId): array
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return ['success' => false, 'message' => 'Shop not found'];
        }

        if (empty($shop->shopify_access_token)) {
            return ['success' => false, 'message' => 'Shop access token not available'];
        }

        try {
            $token = decrypt($shop->shopify_access_token);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Failed to decrypt access token'];
        }

        return ['success' => true, 'shop' => $shop, 'token' => $token];
    }

    /**
     * Make a REST API request to Shopify for a specific shop
     */
    private function request(
        int $shopId,
        string $method,
        string $path,
        array $payload = [],
        array $query = [],
        ?string $apiVersion = null
    ): array
    {
        $auth = $this->getShopAndToken($shopId);
        if (!$auth['success']) {
            return $auth;
        }

        $shop = $auth['shop'];
        $token = $auth['token'];
        $url = rtrim($this->baseUrl($shop->shop_domain, $apiVersion), '/') . '/' . ltrim($path, '/') . '.json';

        try {
            $client = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])
                ->acceptJson()
                ->asJson()
                ->withoutVerifying()
                ->timeout(20)
                ->retry(2, 250);

            $response = $client->send(strtoupper($method), $url, [
                'query' => $query,
                'json' => $payload,
            ]);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
                'message' => $response->successful() ? 'ok' : 'Shopify API request failed',
            ];
        } catch (\Throwable $e) {
            Log::error('Shopify API request error', [
                'shop_id' => $shopId,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Make a GraphQL API request to Shopify for a specific shop
     */
    private function graphqlRequest(int $shopId, string $query, array $variables = []): array
    {
        $auth = $this->getShopAndToken($shopId);
        if (!$auth['success']) {
            return $auth;
        }

        $shop = $auth['shop'];
        $token = $auth['token'];
        $url = rtrim($this->baseUrl($shop->shop_domain), '/') . '/graphql.json';

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])
                ->acceptJson()
                ->withoutVerifying()
                ->asJson()
                ->timeout(20)
                ->retry(2, 250)
                ->post($url, [
                    'query' => $query,
                    'variables' => $variables,
                ]);

            $data = $response->json() ?? [];
            $hasErrors = !empty($data['errors']);

            return [
                'success' => $response->successful() && !$hasErrors,
                'status' => $response->status(),
                'data' => $data['data'] ?? null,
                'errors' => $data['errors'] ?? [],
                'message' => $response->successful() && !$hasErrors ? 'ok' : 'Shopify GraphQL request failed',
            ];
        } catch (\Throwable $e) {
            Log::error('Shopify GraphQL request error', [
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => [],
            ];
        }
    }

    /**
     * Convert a fulfillment order ID to a GraphQL global ID format if it's not already
     */
    private function toFulfillmentOrderGid($fulfillmentOrderId): string
    {
        $value = trim((string) $fulfillmentOrderId);
        if (str_starts_with($value, 'gid://shopify/FulfillmentOrder/')) {
            return $value;
        }

        return 'gid://shopify/FulfillmentOrder/' . $value;
    }

    private function runFulfillmentOrderMutation(
        int $shopId,
        string $mutationName,
        $fulfillmentOrderId,
        ?string $message = null
    ): array {
        $gid = $this->toFulfillmentOrderGid($fulfillmentOrderId);
        $selection = $mutationName;

        $query = <<<GQL
mutation {$mutationName}(\$id: ID!, \$message: String) {
  {$selection}(id: \$id, message: \$message) {
    fulfillmentOrder {
      id
      status
      requestStatus
    }
    userErrors {
      field
      message
    }
  }
}
GQL;

        $result = $this->graphqlRequest($shopId, $query, [
            'id' => $gid,
            'message' => $message,
        ]);
        if (!$result['success']) {
            return $result;
        }

        $payload = $result['data'][$selection] ?? [];
        $userErrors = $payload['userErrors'] ?? [];
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify mutation returned user errors',
                'data' => $payload,
                'errors' => $userErrors,
            ];
        }

        return [
            'success' => true,
            'status' => $result['status'],
            'message' => 'Mutation completed successfully',
            'data' => $payload['fulfillmentOrder'] ?? $payload,
            'errors' => [],
        ];
    }

    /**
     * getOrder - Retrieve a specific order from Shopify using the REST API. This is used in the fulfillment order flow to get order details needed for creating fulfillments.
     */
    public function getOrder(int $shopId, $shopifyOrderId): array
    {
        $result = $this->request($shopId, 'GET', "orders/{$shopifyOrderId}");
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['order'] ?? null,
            'status' => $result['status'],
            'message' => 'Order fetched successfully',
        ];
    }

    /**
     * getFulfillmentOrdersForOrder - Retrieve fulfillment orders for a specific Shopify order. This is used to determine which fulfillment orders are associated with an order when creating fulfillments.
     */
    public function getFulfillmentOrdersForOrder(int $shopId, $shopifyOrderId): array
    {
        $result = $this->request($shopId, 'GET', "orders/{$shopifyOrderId}/fulfillment_orders");
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment_orders'] ?? [],
            'status' => $result['status'],
            'message' => 'Fulfillment orders fetched successfully',
        ];
    }

    /**
     * getAssignedFulfillmentOrders - Retrieve fulfillment orders that are currently assigned to the app's fulfillment service. This is used to identify which fulfillment orders the app is responsible for fulfilling.
     */
    public function getAssignedFulfillmentOrders(int $shopId, array $filters = []): array
    {
        $result = $this->request($shopId, 'GET', 'assigned_fulfillment_orders', [], $filters);
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment_orders'] ?? [],
            'status' => $result['status'],
            'message' => 'Assigned fulfillment orders fetched successfully',
        ];
    }

    /**
     * acceptFulfillmentRequest - Accept a fulfillment request for a specific fulfillment order. This is used when the app wants to accept responsibility for fulfilling a fulfillment order that has requested fulfillment.
     */
    public function acceptFulfillmentRequest(int $shopId, $fulfillmentOrderId, ?string $message = null): array
    {
        return $this->runFulfillmentOrderMutation(
            $shopId,
            'fulfillmentOrderAcceptFulfillmentRequest',
            $fulfillmentOrderId,
            $message
        );
    }

    /**
     * rejectFulfillmentRequest - Reject a fulfillment request for a specific fulfillment order. This is used when the app wants to decline responsibility for fulfilling a fulfillment order that has requested fulfillment, often with a message explaining why.
     */
    public function rejectFulfillmentRequest(int $shopId, $fulfillmentOrderId, string $message): array
    {
        return $this->runFulfillmentOrderMutation(
            $shopId,
            'fulfillmentOrderRejectFulfillmentRequest',
            $fulfillmentOrderId,
            $message
        );
    }

    /**
     * acceptCancellationRequest - Accept a cancellation request for a specific fulfillment order. This is used when the app wants to approve a cancellation request that has been submitted for a fulfillment order.
     */
    public function acceptCancellationRequest(int $shopId, $fulfillmentOrderId, ?string $message = null): array
    {
        return $this->runFulfillmentOrderMutation(
            $shopId,
            'fulfillmentOrderAcceptCancellationRequest',
            $fulfillmentOrderId,
            $message
        );
    }

    /**
     * rejectCancellationRequest - Reject a cancellation request for a specific fulfillment order. This is used when the app wants to deny a cancellation request that has been submitted for a fulfillment order, often with a message explaining why.
     */
    public function rejectCancellationRequest(int $shopId, $fulfillmentOrderId, string $message): array
    {
        return $this->runFulfillmentOrderMutation(
            $shopId,
            'fulfillmentOrderRejectCancellationRequest',
            $fulfillmentOrderId,
            $message
        );
    }

    /**
     * createFulfillment - Create a fulfillment for a specific order and fulfillment order. This is used to fulfill items in a fulfillment order that the app has accepted responsibility for.
     */
    public function createFulfillment($shopId, $shopifyOrderId, $data): array
    {
        try {
            $lineItemsByFO = $data['line_items_by_fulfillment_order'] ?? [];

            if (empty($lineItemsByFO)) {
                if (!empty($data['fulfillment_order_id'])) {
                    $lineItemsByFO[] = [
                        'fulfillment_order_id' => $data['fulfillment_order_id'],
                        'fulfillment_order_line_items' => $data['line_items'] ?? [],
                    ];
                } else {
                    $fo = $this->getFulfillmentOrdersForOrder((int) $shopId, $shopifyOrderId);
                    if (!$fo['success'] || empty($fo['data'])) {
                        return [
                            'success' => false,
                            'message' => 'No fulfillment order found for this Shopify order',
                            'data' => $fo['data'] ?? null,
                        ];
                    }

                    foreach ($fo['data'] as $fulfillmentOrder) {
                        $lineItemsByFO[] = [
                            'fulfillment_order_id' => $fulfillmentOrder['id'],
                            'fulfillment_order_line_items' => [],
                        ];
                    }
                }
            }

            $payload = [
                'fulfillment' => [
                    'line_items_by_fulfillment_order' => $lineItemsByFO,
                    'tracking_info' => $data['tracking_info'] ?? null,
                    'notify_customer' => (bool) ($data['notify_customer'] ?? true),
                ]
            ];

            $result = $this->request((int) $shopId, 'POST', 'fulfillments', $payload);
            if (!$result['success']) {
                return $result;
            }

            return [
                'success' => true,
                'data' => $result['data']['fulfillment'] ?? $result['data'],
                'status' => $result['status'],
                'message' => 'Fulfillment created successfully',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * updateFulfillmentTracking - Update the tracking information for a specific fulfillment. This is used to add or change tracking details for a fulfillment that has already been created.
     */
    public function updateFulfillmentTracking($shopId, $fulfillmentId, $trackingInfo): array
    {
        $payload = [
            'fulfillment' => [
                'tracking_info' => [
                    'number' => $trackingInfo['number'] ?? $trackingInfo['tracking_number'] ?? null,
                    'company' => $trackingInfo['company'] ?? $trackingInfo['tracking_company'] ?? null,
                    'url' => $trackingInfo['url'] ?? $trackingInfo['tracking_url'] ?? null,
                ],
                'notify_customer' => (bool) ($trackingInfo['notify_customer'] ?? true),
            ]
        ];

        $result = $this->request((int) $shopId, 'POST', "fulfillments/{$fulfillmentId}/update_tracking", $payload);
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment'] ?? $result['data'],
            'status' => $result['status'],
            'message' => 'Fulfillment tracking updated successfully',
        ];
    }

    /**
     * cancelFulfillment - Cancel a specific fulfillment. This is used to cancel a fulfillment that has been created but not yet fulfilled, often when an order is cancelled or a fulfillment needs to be voided.
     */
    public function cancelFulfillment($shopId, $shopifyOrderId, $fulfillmentId): array
    {
        $result = $this->request((int) $shopId, 'POST', "fulfillments/{$fulfillmentId}/cancel");
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment'] ?? $result['data'],
            'status' => $result['status'],
            'message' => 'Fulfillment cancelled successfully',
        ];
    }

    /**
     * getFulfillment - Retrieve a specific fulfillment from Shopify. This is used to get details about a fulfillment, including its status and tracking information.
     */
    public function getFulfillmentServices($shopId): array
    {
        $result = $this->request((int) $shopId, 'GET', 'fulfillment_services');
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment_services'] ?? [],
            'status' => $result['status'],
            'message' => 'Fulfillment services fetched successfully',
        ];
    }

    /**
     * createFulfillmentService - Create a fulfillment service in Shopify. This is used to register a fulfillment service with Shopify that can handle fulfillment orders.
     */
    public function createFulfillmentService(int $shopId, string $callbackUrl, string $serviceName = 'DTFTA Fulfillment Service'): array
    {
        $payload = [
            'fulfillment_service' => [
                'name' => $serviceName,
                'callback_url' => $callbackUrl,
                'inventory_management' => false,
                'tracking_support' => true,
                'requires_shipping_method' => true,
                'fulfillment_orders_opt_in' => true,
                'format' => 'json',
            ],
        ];

        $result = $this->request($shopId, 'POST', 'fulfillment_services', $payload);
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment_service'] ?? $result['data'],
            'status' => $result['status'],
            'message' => 'Fulfillment service created successfully',
        ];
    }

    /**
     * Ensure the shop has a fulfillment service and a US-configured location.
     * Uses Shopify GraphQL as requested by frontend integration.
     */
    public function ensureFulfillmentServiceAndLocation(int $shopId, ?string $baseUrl = null): array
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return ['success' => false, 'message' => 'Shop not found'];
        }

        if (!empty($shop->fulfillment_service_id) && !empty($shop->location_id)) {
            return [
                'success' => true,
                'message' => 'Fulfillment service already configured',
                'data' => [
                    'fulfillment_service_id' => (string) $shop->fulfillment_service_id,
                    'location_id' => (string) $shop->location_id,
                ],
            ];
        }

        $baseUrl = rtrim((string) ($baseUrl ?: config('app.url')), '/');
        $callbackUrl = $baseUrl . '/api/v1/fulfillment_order_notification';

                $createMutation = <<<'GQL'
        mutation fulfillmentServiceCreate(
        $name: String!,
        $callbackUrl: URL!,
        $inventoryManagement: Boolean!,
        $trackingSupport: Boolean!
        ) {
        fulfillmentServiceCreate(
            name: $name,
            callbackUrl: $callbackUrl,
            inventoryManagement: $inventoryManagement,
            trackingSupport: $trackingSupport
        ) {
            fulfillmentService {
            id
            location {
                id
            }
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;

        $createResult = $this->graphqlRequest($shopId, $createMutation, [
            'name' => 'DTFTA',
            'callbackUrl' => $callbackUrl,
            'inventoryManagement' => true,
            'trackingSupport' => true,
        ]);

        if (!$createResult['success']) {
            return $createResult;
        }

        $serviceData = $createResult['data']['fulfillmentServiceCreate'] ?? [];
        $userErrors = $serviceData['userErrors'] ?? [];
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Fulfillment service create returned user errors',
                'errors' => $userErrors,
                'data' => $serviceData,
            ];
        }

        $fulfillmentServiceId = (string) ($serviceData['fulfillmentService']['id'] ?? '');
        $createdLocationId = (string) ($serviceData['fulfillmentService']['location']['id'] ?? '');

        if ($fulfillmentServiceId === '') {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Fulfillment service ID missing in Shopify response',
                'data' => $serviceData,
            ];
        }

        $locationUpdateErrors = [];
        if ($createdLocationId !== '') {
                        $locationMutation = <<<'GQL'
            mutation updateLocation($id: ID!, $input: LocationEditInput!) {
            locationEdit(id: $id, input: $input) {
                location {
                id
                name
                }
                userErrors {
                field
                message
                }
            }
            }
            GQL;

            $locationResult = $this->graphqlRequest($shopId, $locationMutation, [
                'id' => $createdLocationId,
                'input' => [
                    'address' => [
                        'countryCode' => 'US',
                    ],
                ],
            ]);

            if (!$locationResult['success']) {
                $locationUpdateErrors = $locationResult['errors'] ?? [];
            } else {
                $locationEditData = $locationResult['data']['locationEdit'] ?? [];
                if (!empty($locationEditData['userErrors'])) {
                    $locationUpdateErrors = $locationEditData['userErrors'];
                }
            }
        }

        $shop->fulfillment_service_id = $fulfillmentServiceId;
        if ($createdLocationId !== '') {
            $shop->location_id = $createdLocationId;
        }
        $shop->save();

        return [
            'success' => true,
            'message' => 'Fulfillment service configured successfully',
            'data' => [
                'fulfillment_service_id' => $fulfillmentServiceId,
                'location_id' => $createdLocationId !== '' ? $createdLocationId : null,
                'location_update_errors' => $locationUpdateErrors,
            ],
        ];
    }

    /**
     * registerWebhook - Register a webhook in Shopify for a specific topic and callback URL. This is used to set up webhooks that notify the app of relevant events in Shopify.
     */
    public function registerWebhook($shopId, $topic, $callbackUrl): array
    {
        $shop = Shop::find((int) $shopId);
        $webhookApiVersion = $this->webhookApiVersion($shop);
        $payload = [
            'webhook' => [
                'topic' => $topic,
                'address' => $callbackUrl,
                'format' => 'json',
            ],
        ];

        $result = $this->request((int) $shopId, 'POST', 'webhooks', $payload, [], $webhookApiVersion);
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['webhook'] ?? $result['data'],
            'status' => $result['status'],
            'message' => 'Webhook registered successfully',
        ];
    }

    /**
     * listWebhooks - Retrieve a list of registered webhooks in Shopify. This is used to check which webhooks are currently set up for the shop.
     */
    public function listWebhooks(int $shopId): array
    {
        $shop = Shop::find($shopId);
        $webhookApiVersion = $this->webhookApiVersion($shop);
        $result = $this->request($shopId, 'GET', 'webhooks', [], [], $webhookApiVersion);
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['webhooks'] ?? [],
            'status' => $result['status'],
            'message' => 'Webhooks fetched successfully',
        ];
    }

    /**
     * deleteWebhook - Delete a specific webhook in Shopify by its ID. This is used to remove webhooks that are no longer needed or to clean up during uninstallation.
     */
    public function deleteWebhook(int $shopId, $webhookId): array
    {
        $shop = Shop::find($shopId);
        $webhookApiVersion = $this->webhookApiVersion($shop);
        return $this->request($shopId, 'DELETE', "webhooks/{$webhookId}", [], [], $webhookApiVersion);
    }

    /**
     * ensureRequiredWebhooks - Ensure that all required webhooks for the app are registered in Shopify. This is used during installation or setup to make sure the app is subscribed to the necessary events.
     */
    public function ensureRequiredWebhooks(int $shopId, string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $mainWebhookAddress = "{$baseUrl}/api/v1/webhooks/shopify";
        $topics = [
            'app/uninstalled',
            'orders/create',
            'orders/updated',
            'orders/cancelled',
            'fulfillments/create',
            'fulfillments/update',
            'fulfillment_orders/fulfillment_request_submitted',
            'fulfillment_orders/cancellation_request_submitted',
        ];

        $existing = $this->listWebhooks($shopId);
        $existingTopics = [];
        if ($existing['success']) {
            foreach (($existing['data'] ?? []) as $webhook) {
                $existingTopics[] = (string) ($webhook['topic'] ?? '');
            }
        }

        $created = [];
        $errors = [];
        foreach ($topics as $topic) {
            if (in_array($topic, $existingTopics, true)) {
                continue;
            }

            $result = $this->registerWebhook($shopId, $topic, $mainWebhookAddress);
            if ($result['success']) {
                $created[] = $topic;
            } else {
                $errors[] = ['topic' => $topic, 'error' => $result['message'] ?? 'Unknown error'];
            }
        }

        return [
            'success' => empty($errors),
            'data' => [
                'created_topics' => $created,
                'errors' => $errors,
            ],
        ];
    }

    /**
     * provisionShopOnInstall - Perform necessary setup for a shop when the app is installed, including creating a fulfillment service and registering required webhooks. This is used to prepare the shop for using the app's features.
     */
    public function provisionShopOnInstall(int $shopId, ?string $baseUrl = null): array
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return ['success' => false, 'message' => 'Shop not found'];
        }

        $baseUrl = $baseUrl ?: (string) config('app.url');
        $baseUrl = rtrim($baseUrl, '/');
        $fulfillmentCallbackUrl = "{$baseUrl}/api/v1/fulfillment_order_notification";

        $serviceResult = null;
        if (empty($shop->fulfillment_service_id)) {
            $serviceResult = $this->createFulfillmentService($shopId, $fulfillmentCallbackUrl, 'DTFTA Fulfillment Service');
            if ($serviceResult['success']) {
                $service = $serviceResult['data'] ?? [];
                $shop->fulfillment_service_id = (string) ($service['id'] ?? '');
                $shop->location_id = isset($service['location_id']) ? (string) $service['location_id'] : ($shop->location_id ?? null);
                $shop->save();
            }
        }

        $webhooks = $this->ensureRequiredWebhooks($shopId, $baseUrl);

        return [
            'success' => ($serviceResult ? (bool) $serviceResult['success'] : true) && (bool) $webhooks['success'],
            'data' => [
                'fulfillment_service' => $serviceResult['data'] ?? null,
                'webhooks' => $webhooks['data'] ?? [],
            ],
            'message' => 'Provisioning completed',
        ];
    }


    /**
     * Create custom product with:
     * - product
     * - variants (with SKU)
     * - print area metafield
     * - artwork metafield
     * - print plan metafield
     * - artwork attached as product media
     *
     * Expected payload:
     * [
     *   'title' => 'T-Shirt Custom',
     *   'descriptionHtml' => '<p>Custom t-shirt</p>',
     *   'vendor' => 'DTFTA',
     *   'productType' => 'T-Shirt',
     *   'status' => 'DRAFT',
     *   'tags' => ['custom', 'print-on-demand'],
     *   'options' => [
     *      ['name' => 'Color', 'values' => ['Black', 'White']],
     *      ['name' => 'Size', 'values' => ['S', 'M']],
     *   ],
     *   'variants' => [
     *      [
     *          'price' => '19.99',
     *          'compareAtPrice' => '24.99',
     *          'sku' => 'TS-BLK-S',
     *          'barcode' => '123456',
     *          'optionValues' => [
     *              ['optionName' => 'Color', 'name' => 'Black'],
     *              ['optionName' => 'Size', 'name' => 'S'],
     *          ],
     *      ],
     *   ],
     *   'print_areas' => [
     *      [
     *          'placement' => 'front',
     *          'width' => 12,
     *          'height' => 16,
     *          'unit' => 'in',
     *          'position_x' => 125,
     *          'position_y' => 125,
     *      ],
     *   ],
     *   'artwork' => [
     *      'front' => 'data:image/png;base64,...',
     *      'back' => 'data:image/png;base64,...',
     *   ],
     *   'print_plan' => [
     *      'front' => ['width' => 12, 'height' => 16],
     *   ],
     * ]
     */
    public function createCustomProductWithPrintAreas(int $shopId, array $payload): array
    {
        try {
            Log::info('Shopify custom product create started', [
                'shop_id' => $shopId,
                'payload' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]);
    
            $productCreateResult = $this->createShopifyProduct($shopId, $payload);
            if (!$productCreateResult['success']) {
                return $productCreateResult;
            }
    
            $product = $productCreateResult['data']['product'] ?? null;
            $productId = $product['id'] ?? null;
    
            if (!$productId) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => 'Product ID missing after product creation',
                    'data' => $productCreateResult['data'] ?? null,
                    'errors' => [],
                ];
            }
    
            $createdVariants = [];
    
            if (!empty($payload['variants'])) {
                $variantResult = $this->createShopifyVariants(
                    $shopId,
                    $productId,
                    $product,
                    $payload['variants']
                );
    
                if (!$variantResult['success']) {
                    return $variantResult;
                }
    
                $createdVariants = $variantResult['data']['variants'] ?? [];
                $product = $variantResult['data']['product'] ?? $product;
            }
    
            $artworkUrls = [];
            $mediaResultData = [];
    
            if (!empty($payload['artwork']) && is_array($payload['artwork'])) {
                $normalized = $this->normalizeArtworkBatchForShopify($payload['artwork']);
    
                if (!$normalized['success']) {
                    return $normalized;
                }
    
                $resolved = $this->resolveArtworkSourcesForShopify(
                    $shopId,
                    $normalized['data']['artwork']
                );
    
                if (!$resolved['success']) {
                    return $resolved;
                }
    
                $artworkUrls = $resolved['data']['artwork'] ?? [];
    
                $attachResult = $this->attachArtworkAsProductMedia(
                    $shopId,
                    $productId,
                    $resolved['data']['media'] ?? []
                );
    
                if (!$attachResult['success']) {
                    return $attachResult;
                }
    
                $mediaResultData = $attachResult['data']['media'] ?? [];
                $product = $attachResult['data']['product'] ?? $product;
            }
    
            // $metafieldResult = $this->setCustomProductMetafields($shopId, $productId, [
            //     'print_areas' => $payload['print_areas'] ?? [],
            //     'artwork' => $artworkUrls,
            //     'print_plan' => $payload['print_plan'] ?? null,
            //     'line_item_meta' => $payload['line_item_meta'] ?? [],
            // ]);

            $metafieldResult = $this->setCustomProductMetafields($shopId, $productId, [
                'print_areas' => [],
                'artwork' => [],
                'print_plan' => [],
                'line_item_meta' => $payload['line_item_meta'] ?? [],
            ]);
    
            if (!$metafieldResult['success']) {
                return $metafieldResult;
            }
    
            return [
                'success' => true,
                'status' => 200,
                'message' => 'Custom product created successfully',
                'data' => [
                    'product' => $product,
                    'variants' => $createdVariants,
                    'artwork' => $artworkUrls,
                    'media' => $mediaResultData,
                    'metafields' => $metafieldResult['data']['metafields'] ?? [],
                ],
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('Shopify createCustomProductWithPrintAreas error', [
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]);
    
            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => [],
            ];
        }
    }
    
    private function createShopifyProduct(int $shopId, array $payload): array
    {
            $mutation = <<<'GQL'
        mutation productCreate($product: ProductCreateInput!) {
        productCreate(product: $product) {
            product {
            id
            title
            handle
            status
            options {
                id
                name
                optionValues {
                id
                name
                }
            }
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;
    
        $productInput = [
            'title' => (string) ($payload['title'] ?? ''),
            'descriptionHtml' => $payload['descriptionHtml'] ?? null,
            'vendor' => $payload['vendor'] ?? null,
            'productType' => $payload['productType'] ?? null,
            'status' => $payload['status'] ?? 'DRAFT',
            'tags' => array_values($payload['tags'] ?? []),
            'productOptions' => collect($payload['options'] ?? [])
                ->map(function ($option) {
                    return [
                        'name' => (string) ($option['name'] ?? ''),
                        'values' => collect($option['values'] ?? [])
                            ->map(fn ($value) => ['name' => (string) $value])
                            ->values()
                            ->all(),
                    ];
                })
                ->filter(fn ($option) => !empty($option['name']) && !empty($option['values']))
                ->values()
                ->all(),
        ];
    
        if ($productInput['title'] === '') {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Product title is required',
                'data' => null,
                'errors' => [],
            ];
        }
    
        Log::info('Shopify productCreate request', [
            'shop_id' => $shopId,
            'product_input' => json_encode($productInput, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
        $result = $this->graphqlRequest($shopId, $mutation, [
            'product' => $productInput,
        ]);
    
        Log::info('Shopify productCreate response', [
            'shop_id' => $shopId,
            'response' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
        if (!$result['success']) {
            return $result;
        }
    
        $payloadData = $result['data']['productCreate'] ?? [];
        $userErrors = $payloadData['userErrors'] ?? [];
    
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify productCreate returned user errors',
                'data' => $payloadData,
                'errors' => $userErrors,
            ];
        }
    
        return [
            'success' => true,
            'status' => $result['status'],
            'message' => 'Product created successfully',
            'data' => [
                'product' => $payloadData['product'] ?? null,
            ],
            'errors' => [],
        ];
    }
    
    private function createShopifyVariants(int $shopId, string $productId, array $product, array $variants): array
    {
        $optionIdMap = [];
    
        foreach (($product['options'] ?? []) as $option) {
            $optionName = (string) ($option['name'] ?? '');
            $optionId = (string) ($option['id'] ?? '');
    
            if ($optionName === '' || $optionId === '') {
                continue;
            }
    
            foreach (($option['optionValues'] ?? []) as $value) {
                $valueName = (string) ($value['name'] ?? '');
                if ($valueName !== '') {
                    $optionIdMap[$optionName][$valueName] = $optionId;
                }
            }
        }
    
        $variantInputs = collect($variants)->map(function ($variant) use ($optionIdMap) {
            $trackInventory = array_key_exists('trackInventory', $variant)
                ? (bool) $variant['trackInventory']
                : false; // default: do not track inventory
    
            $allowBackorder = array_key_exists('allowBackorder', $variant)
                ? (bool) $variant['allowBackorder']
                : true; // default: allow selling when out of stock
    
            $inventoryItem = array_filter([
                'sku' => $variant['sku'] ?? null,
                'tracked' => $trackInventory,
            ], fn ($value) => $value !== null && $value !== '');
    
            return array_filter([
                'price' => isset($variant['price']) ? (string) $variant['price'] : null,
                'compareAtPrice' => isset($variant['compareAtPrice']) ? (string) $variant['compareAtPrice'] : null,
                'barcode' => $variant['barcode'] ?? null,
                'inventoryItem' => !empty($inventoryItem) ? $inventoryItem : null,
                'inventoryPolicy' => $allowBackorder ? 'CONTINUE' : 'DENY',
                'optionValues' => collect($variant['optionValues'] ?? [])
                    ->map(function ($optionValue) use ($optionIdMap) {
                        $optionName = (string) ($optionValue['optionName'] ?? '');
                        $valueName = (string) ($optionValue['name'] ?? '');
                        $optionId = $optionIdMap[$optionName][$valueName] ?? null;
    
                        if (!$optionId || $valueName === '') {
                            return null;
                        }
    
                        return [
                            'optionId' => $optionId,
                            'name' => $valueName,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all(),
            ], fn ($value) => $value !== null);
        })->values()->all();
    
        Log::info('Shopify productVariantsBulkCreate request', [
            'shop_id' => $shopId,
            'product_id' => $productId,
            'variants' => json_encode($variantInputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
            $mutation = <<<'GQL'
        mutation productVariantsBulkCreate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
        productVariantsBulkCreate(
            productId: $productId,
            variants: $variants,
            strategy: REMOVE_STANDALONE_VARIANT
        ) {
            product {
            id
            title
            handle
            }
            productVariants {
            id
            title
            price
            barcode
            inventoryPolicy
            inventoryItem {
                id
                sku
                tracked
            }
            selectedOptions {
                name
                value
            }
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;
    
        $result = $this->graphqlRequest($shopId, $mutation, [
            'productId' => $productId,
            'variants' => $variantInputs,
        ]);
    
        Log::info('Shopify productVariantsBulkCreate response', [
            'shop_id' => $shopId,
            'response' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
        if (!$result['success']) {
            return $result;
        }
    
        $payloadData = $result['data']['productVariantsBulkCreate'] ?? [];
        $userErrors = $payloadData['userErrors'] ?? [];
    
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify productVariantsBulkCreate returned user errors',
                'data' => $payloadData,
                'errors' => $userErrors,
            ];
        }
    
        return [
            'success' => true,
            'status' => $result['status'],
            'message' => 'Variants created successfully',
            'data' => [
                'product' => $payloadData['product'] ?? null,
                'variants' => $payloadData['productVariants'] ?? [],
            ],
            'errors' => [],
        ];
    }
    
    private function normalizeArtworkBatchForShopify(array $artwork): array
    {
        $normalized = [];
    
        foreach ($artwork as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
    
            $placement = strtolower(trim((string) ($item['placement'] ?? 'artwork_' . ($index + 1))));
            $url = trim((string) ($item['url'] ?? ''));
            $sourceCode = trim((string) ($item['source_code'] ?? ''));
            $title = trim((string) ($item['title'] ?? ucfirst(str_replace('_', ' ', $placement)) . ' artwork'));
    
            if ($url === '' && $sourceCode === '') {
                continue;
            }
    
            $normalized[] = [
                'placement' => $placement,
                'url' => $url,
                'source_code' => $sourceCode,
                'title' => $title,
            ];
        }
    
        if (empty($normalized)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'No artwork URL or source_code was provided',
                'data' => null,
                'errors' => [],
            ];
        }
    
        Log::info('Normalized artwork batch for Shopify', [
            'artwork' => json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
        return [
            'success' => true,
            'status' => 200,
            'message' => 'Artwork batch normalized successfully',
            'data' => [
                'artwork' => $normalized,
            ],
            'errors' => [],
        ];
    }
    
    private function uploadArtworkToShopify(
            int $shopId,
            string $base64Image,
            string $filename = 'artwork.png',
            string $alt = 'Artwork'
        ): array {
        if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,/', $base64Image, $matches)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Invalid base64 image format',
                'data' => null,
                'errors' => [],
            ];
        }
    
        $mimeType = $matches[1];
        $binary = base64_decode(substr($base64Image, strpos($base64Image, ',') + 1), true);
    
        if ($binary === false) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Failed to decode base64 image',
                'data' => null,
                'errors' => [],
            ];
        }
    
        $fileSize = strlen($binary);
    
            $stagedMutation = <<<'GQL'
        mutation stagedUploadsCreate($input: [StagedUploadInput!]!) {
        stagedUploadsCreate(input: $input) {
            stagedTargets {
            url
            resourceUrl
            parameters {
                name
                value
            }
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;
    
        $stagedResult = $this->graphqlRequest($shopId, $stagedMutation, [
            'input' => [
                [
                    'resource' => 'IMAGE',
                    'filename' => $filename,
                    'mimeType' => $mimeType,
                    'httpMethod' => 'POST',
                    'fileSize' => (string) $fileSize,
                ],
            ],
        ]);
    
        if (!$stagedResult['success']) {
            return $stagedResult;
        }
    
        $stagedPayload = $stagedResult['data']['stagedUploadsCreate'] ?? [];
        $stagedErrors = $stagedPayload['userErrors'] ?? [];
    
        if (!empty($stagedErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify stagedUploadsCreate returned user errors',
                'data' => $stagedPayload,
                'errors' => $stagedErrors,
            ];
        }
    
        $target = $stagedPayload['stagedTargets'][0] ?? null;
    
        if (!$target || empty($target['url']) || empty($target['resourceUrl'])) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Missing staged upload target',
                'data' => $stagedPayload,
                'errors' => [],
            ];
        }
    
        $formFields = [];
        foreach (($target['parameters'] ?? []) as $param) {
            $formFields[$param['name']] = $param['value'];
        }
    
        $uploadResponse = Http::timeout(120)
            ->withoutVerifying()
            ->attach('file', $binary, $filename, [
                'Content-Type' => $mimeType,
            ])
            ->post($target['url'], $formFields);
    
        if (!$uploadResponse->successful()) {
            Log::error('Shopify staged upload failed', [
                'shop_id' => $shopId,
                'status' => $uploadResponse->status(),
                'body' => $uploadResponse->body(),
                'target_url' => $target['url'],
                'resource_url' => $target['resourceUrl'],
                'form_fields' => $formFields,
            ]);
    
            return [
                'success' => false,
                'status' => $uploadResponse->status(),
                'message' => 'Failed to upload image to Shopify staged target',
                'data' => [
                    'body' => $uploadResponse->body(),
                    'target' => $target,
                ],
                'errors' => [],
            ];
        }
    
        return [
            'success' => true,
            'status' => 200,
            'message' => 'Artwork uploaded successfully',
            'data' => [
                'resourceUrl' => $target['resourceUrl'],
                'filename' => $filename,
                'alt' => $alt,
            ],
            'errors' => [],
        ];
    }
    
    private function resolveArtworkSourcesForShopify(int $shopId, array $artwork): array
    {
        $resolvedArtwork = [];
        $media = [];
    
        foreach ($artwork as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
    
            $placement = strtolower(trim((string) ($item['placement'] ?? 'artwork_' . ($index + 1))));
            $title = trim((string) ($item['title'] ?? ucfirst(str_replace('_', ' ', $placement)) . ' artwork'));
            $url = trim((string) ($item['url'] ?? ''));
            $sourceCode = trim((string) ($item['source_code'] ?? ''));
    
            $finalUrl = '';
    
            // Prefer source_code upload first, so Shopify-hosted originalSource is used
            if ($sourceCode !== '') {
                $filename = $placement . '.png';
    
                $uploadResult = $this->uploadArtworkToShopify($shopId, $sourceCode, $filename, $title);
    
                if (!$uploadResult['success']) {
                    return $uploadResult;
                }
    
                $finalUrl = (string) ($uploadResult['data']['resourceUrl'] ?? '');
            }
    
            // Fallback to direct URL only if source_code was not available or upload failed to resolve
            if ($finalUrl === '' && $url !== '') {
                $finalUrl = $url;
            }
    
            if ($finalUrl === '') {
                continue;
            }
    
            $resolvedArtwork[] = [
                'placement' => $placement,
                'url' => $finalUrl,
                'title' => $title,
            ];
    
            $media[] = [
                'originalSource' => $finalUrl,
                'mediaContentType' => 'IMAGE',
                'alt' => $title,
            ];
        }
    
        if (empty($resolvedArtwork)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'No artwork could be resolved from URL or source_code',
                'data' => null,
                'errors' => [],
            ];
        }
    
        return [
            'success' => true,
            'status' => 200,
            'message' => 'Artwork sources resolved successfully',
            'data' => [
                'artwork' => $resolvedArtwork,
                'media' => $media,
            ],
            'errors' => [],
        ];
    }
    
    private function attachArtworkAsProductMedia(int $shopId, string $productId, array $media): array
    {
        if (empty($media)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'No valid artwork media provided',
                'data' => null,
                'errors' => [],
            ];
        }
    
        Log::info('Shopify product media attach request', [
            'shop_id' => $shopId,
            'product_id' => $productId,
            'media' => json_encode($media, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
            $mutation = <<<'GQL'
        mutation productUpdate($product: ProductUpdateInput!, $media: [CreateMediaInput!]) {
        productUpdate(product: $product, media: $media) {
            product {
            id
            title
            media(first: 20) {
                nodes {
                id
                alt
                mediaContentType
                status
                ... on MediaImage {
                    image {
                    url
                    }
                }
                }
            }
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;
    
        $result = $this->graphqlRequest($shopId, $mutation, [
            'product' => [
                'id' => $productId,
            ],
            'media' => $media,
        ]);
    
        Log::info('Shopify product media attach response', [
            'shop_id' => $shopId,
            'response' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
        if (!$result['success']) {
            return $result;
        }
    
        $payloadData = $result['data']['productUpdate'] ?? [];
        $userErrors = $payloadData['userErrors'] ?? [];
    
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify productUpdate returned user errors',
                'data' => $payloadData,
                'errors' => $userErrors,
            ];
        }
    
        return [
            'success' => true,
            'status' => $result['status'],
            'message' => 'Artwork attached as product media successfully',
            'data' => [
                'product' => $payloadData['product'] ?? null,
                'media' => $payloadData['product']['media']['nodes'] ?? [],
            ],
            'errors' => [],
        ];
    }
    
    private function setCustomProductMetafields(int $shopId, string $productId, array $data): array
    {
        $metafields = [];
    
        if (!empty($data['print_areas'])) {
            $metafields[] = [
                'ownerId' => $productId,
                'namespace' => 'dtfta',
                'key' => 'print_areas',
                'type' => 'json',
                'value' => json_encode(array_values($data['print_areas']), JSON_UNESCAPED_SLASHES),
            ];
        }
    
        if (!empty($data['artwork'])) {
            $metafields[] = [
                'ownerId' => $productId,
                'namespace' => 'dtfta',
                'key' => 'artwork',
                'type' => 'json',
                'value' => json_encode(array_values($data['artwork']), JSON_UNESCAPED_SLASHES),
            ];
        }
    
        if (!empty($data['print_plan'])) {
            $metafields[] = [
                'ownerId' => $productId,
                'namespace' => 'dtfta',
                'key' => 'print_plan',
                'type' => 'json',
                'value' => json_encode($data['print_plan'], JSON_UNESCAPED_SLASHES),
            ];
        }

        if(!empty($data['line_item_meta'])){
            $lineItemMetas = $data['line_item_meta'] ?? [];
            foreach($lineItemMetas as $lineItemMeta ){
                $metafields[] = [
                    'ownerId' => $productId,
                    'namespace' => 'dtfta',
                    'key' => 'template_id',
                    'type' => 'single_line_text_field',
                    'value' => (string) $lineItemMeta['template_id'],
                ];
            }
        }
    
        if (empty($metafields)) {
            return [
                'success' => true,
                'status' => 200,
                'message' => 'No metafields to save',
                'data' => [
                    'metafields' => [],
                ],
                'errors' => [],
            ];
        }
    
        Log::info('Shopify metafieldsSet request', [
            'shop_id' => $shopId,
            'product_id' => $productId,
            'metafields' => json_encode($metafields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
            $mutation = <<<'GQL'
        mutation MetafieldsSet($metafields: [MetafieldsSetInput!]!) {
        metafieldsSet(metafields: $metafields) {
            metafields {
            id
            namespace
            key
            type
            value
            }
            userErrors {
            field
            message
            code
            }
        }
        }
        GQL;
    
        $result = $this->graphqlRequest($shopId, $mutation, [
            'metafields' => $metafields,
        ]);
    
        Log::info('Shopify metafieldsSet response', [
            'shop_id' => $shopId,
            'response' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
        if (!$result['success']) {
            return $result;
        }
    
        $payloadData = $result['data']['metafieldsSet'] ?? [];
        $userErrors = $payloadData['userErrors'] ?? [];
    
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify metafieldsSet returned user errors',
                'data' => $payloadData,
                'errors' => $userErrors,
            ];
        }
    
        return [
            'success' => true,
            'status' => $result['status'],
            'message' => 'Metafields saved successfully',
            'data' => [
                'metafields' => $payloadData['metafields'] ?? [],
            ],
            'errors' => [],
        ];
    }

}
