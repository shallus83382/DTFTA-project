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
     * Construct the base URL for Shopify API requests based on shop domain and API version
     */
    private function baseUrl(string $shopDomain): string
    {
        return "https://{$shopDomain}/admin/api/" . $this->apiVersion();
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
    private function request(int $shopId, string $method, string $path, array $payload = [], array $query = []): array
    {
        $auth = $this->getShopAndToken($shopId);
        if (!$auth['success']) {
            return $auth;
        }

        $shop = $auth['shop'];
        $token = $auth['token'];
        $url = rtrim($this->baseUrl($shop->shop_domain), '/') . '/' . ltrim($path, '/') . '.json';

        try {
            $client = Http::withToken($token)
                ->acceptJson()
                ->asJson()
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
            $response = Http::withToken($token)
                ->acceptJson()
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
     * registerWebhook - Register a webhook in Shopify for a specific topic and callback URL. This is used to set up webhooks that notify the app of relevant events in Shopify.
     */
    public function registerWebhook($shopId, $topic, $callbackUrl): array
    {
        $payload = [
            'webhook' => [
                'topic' => $topic,
                'address' => $callbackUrl,
                'format' => 'json',
            ],
        ];

        $result = $this->request((int) $shopId, 'POST', 'webhooks', $payload);
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
        $result = $this->request($shopId, 'GET', 'webhooks');
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
        return $this->request($shopId, 'DELETE', "webhooks/{$webhookId}");
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
}
