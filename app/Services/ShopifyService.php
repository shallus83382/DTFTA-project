<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\FulfillmentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            $payload = [
                'query' => $query,
                // Shopify expects variables to be a JSON object, not an array.
                'variables' => !empty($variables) ? $variables : (object) [],
            ];

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])
                ->acceptJson()
                ->withoutVerifying()
                ->asJson()
                ->timeout(20)
                ->retry(2, 250)
                ->post($url, $payload);

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

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => [],
            ];
        }
    }



    public function getFulfillmentOrder(int $shopId, string $fulfillmentOrderId): array
    {
        $gid = $this->toFulfillmentOrderGid($fulfillmentOrderId);
    
        $query = <<<'GQL'
        query GetFulfillmentOrder($id: ID!) {
          fulfillmentOrder(id: $id) {
            id
            status
            requestStatus
            fulfillAt
            fulfillBy
            supportedActions {
              action
              externalUrl
            }
            order {
              id
              legacyResourceId
              name
            }
            assignedLocation {
              location {
                id
                name
              }
            }
            destination {
              firstName
              lastName
              company
              address1
              address2
              city
              zip
              countryCode
              phone
              email
            }
            merchantRequests(first: 10) {
              edges {
                node {
                  kind
                  message
                  sentAt
                }
              }
            }
            lineItems(first: 100) {
              edges {
                node {
                  id
                  totalQuantity
                  remainingQuantity
                  inventoryItemId
                  lineItem {
                    id
                    sku
                    name
                  }
                }
              }
            }
          }
        }
        GQL;
    
        $result = $this->graphqlRequest($shopId, $query, [
            'id' => $gid,
        ]);
    
        if (!$result['success']) {
            return $result;
        }
    
        $fo = $result['data']['fulfillmentOrder'] ?? null;
    
        if (!$fo) {
            return [
                'success' => false,
                'status' => 404,
                'message' => 'Fulfillment order not found',
                'data' => null,
                'errors' => [],
            ];
        }
    
        return [
            'success' => true,
            'status' => $result['status'],
            'message' => 'Fulfillment order fetched successfully',
            'data' => [
                'fulfillment_order' => [
                    'id' => $fo['id'] ?? null,
                    'legacy_id' => $this->legacyIdFromGid($fo['id'] ?? null),
                    'status' => $fo['status'] ?? null,
                    'request_status' => $fo['requestStatus'] ?? null,
                    'assignment_status' => $fo['assignmentStatus'] ?? null,
                    'fulfill_at' => $fo['fulfillAt'] ?? null,
                    'fulfill_by' => $fo['fulfillBy'] ?? null,
                    'supported_actions' => $fo['supportedActions'] ?? [],
                    'order_gid' => data_get($fo, 'order.id'),
                    'order_id' => data_get($fo, 'order.legacyResourceId'),
                    'order_name' => data_get($fo, 'order.name'),
                    'assigned_location_gid' => data_get($fo, 'assignedLocation.location.id'),
                    'assigned_location_id' => $this->legacyIdFromGid(data_get($fo, 'assignedLocation.location.id')),
                    'assigned_location_name' => data_get($fo, 'assignedLocation.location.name'),
                    'destination' => $fo['destination'] ?? null,
                    'merchant_requests' => collect(data_get($fo, 'merchantRequests.edges', []))
                        ->pluck('node')
                        ->values()
                        ->all(),
                    'line_items' => collect(data_get($fo, 'lineItems.edges', []))
                        ->map(function ($edge) {
                            $node = $edge['node'] ?? [];
    
                            return [
                                'id' => $node['id'] ?? null,
                                'legacy_id' => $this->legacyIdFromGid($node['id'] ?? null),
                                'total_quantity' => $node['totalQuantity'] ?? null,
                                'remaining_quantity' => $node['remainingQuantity'] ?? null,
                                'inventory_item_id' => $node['inventoryItemId'] ?? null,
                                'line_item_gid' => data_get($node, 'lineItem.id'),
                                'line_item_id' => $this->legacyIdFromGid(data_get($node, 'lineItem.id')),
                                'sku' => data_get($node, 'lineItem.sku'),
                                'name' => data_get($node, 'lineItem.name'),
                                'raw' => $node,
                            ];
                        })
                        ->values()
                        ->all(),
                    'raw' => $fo,
                ],
            ],
            'errors' => [],
        ];
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
        ?string $message = null,
        ?string $estimatedShippedAt = null
    ): array {
        $gid = $this->toFulfillmentOrderGid($fulfillmentOrderId);
        $selection = $mutationName;
    
        $usesEstimatedShippedAt = $mutationName === 'fulfillmentOrderAcceptFulfillmentRequest';
    
            $query = $usesEstimatedShippedAt
                ? <<<'GQL'
        mutation Mutation($id: ID!, $message: String, $estimatedShippedAt: DateTime) {
        fulfillmentOrderAcceptFulfillmentRequest(
            id: $id,
            message: $message,
            estimatedShippedAt: $estimatedShippedAt
        ) {
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
        GQL
                : <<<GQL
        mutation Mutation(\$id: ID!, \$message: String) {
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
    
        $variables = [
            'id' => $gid,
            'message' => $message,
        ];
    
        if ($usesEstimatedShippedAt) {
            $variables['estimatedShippedAt'] = $estimatedShippedAt;
        }
    
        $result = $this->graphqlRequest($shopId, $query, $variables);
    
        if (!$result['success']) {
            return $result;
        }
    
        $payload = $result['data'][$selection] ?? [];
        $userErrors = $payload['userErrors'] ?? [];
    
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => $userErrors[0]['message'] ?? 'Shopify mutation returned user errors',
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

    public function getOrderById(int $shopId, $shopifyOrderId): array
    {
        return $this->getOrder($shopId, $shopifyOrderId);
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

    public function getAssignedFulfillmentOrdersGraphql(int $shopId, ?array $locationIds = null): array
    {
            $query = <<<'GQL'
        query AssignedFulfillmentOrders($first: Int!, $assignmentStatus: FulfillmentOrderAssignmentStatus, $locationIds: [ID!]) {
        assignedFulfillmentOrders(
            first: $first,
            assignmentStatus: $assignmentStatus,
            locationIds: $locationIds
        ) {
            edges {
            node {
                id
                status
                requestStatus
                assignmentStatus
                order {
                id
                legacyResourceId
                name
                }
                assignedLocation {
                location {
                    id
                    name
                }
                }
            }
            }
        }
        }
        GQL;

        $variables = [
            'first' => 50,
            'assignmentStatus' => null,
            'locationIds' => $locationIds,
        ];

        $result = $this->graphqlRequest($shopId, $query, $variables);

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'status' => $result['status'],
            'message' => 'Assigned fulfillment orders fetched successfully',
            'data' => collect(data_get($result, 'data.assignedFulfillmentOrders.edges', []))
                ->pluck('node')
                ->values()
                ->all(),
            'errors' => [],
        ];
    }

    /**
     * acceptFulfillmentRequest - Accept a fulfillment request for a specific fulfillment order. This is used when the app wants to accept responsibility for fulfilling a fulfillment order that has requested fulfillment.
     */
    public function acceptFulfillmentRequest(
        int $shopId,
        $fulfillmentOrderId,
        ?string $message = null,
        ?string $estimatedShippedAt = null
    ): array {
        return $this->runFulfillmentOrderMutation(
            $shopId,
            'fulfillmentOrderAcceptFulfillmentRequest',
            $fulfillmentOrderId,
            $message,
            $estimatedShippedAt
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


        $result = $this->graphqlRequest($shopId, <<<'GQL'
        {
          shop {
            name
            myshopifyDomain
          }
        }
        GQL, []);
        
        Log::info('Shopify auth smoke test', [
            'shop_id' => $shopId,
            'result' => $result,
        ]);


        Log::info('Shopify create Fullfillment request', [
            'shop_id' => $shopId,
        ]);

        try {
            $lineItemsByFO = $data['line_items_by_fulfillment_order'] ?? [];
    
            if (empty($lineItemsByFO)) {
                if (!empty($data['fulfillment_order_id'])) {
                    $lineItemsByFO[] = [
                        'fulfillmentOrderId' => $this->toFulfillmentOrderGid($data['fulfillment_order_id']),
                        'fulfillmentOrderLineItems' => collect($data['line_items'] ?? [])
                            ->map(function ($item) {
                                return array_filter([
                                    'id' => !empty($item['id'])
                                        ? (str_starts_with((string) $item['id'], 'gid://')
                                            ? (string) $item['id']
                                            : 'gid://shopify/FulfillmentOrderLineItem/' . $item['id'])
                                        : null,
                                    'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : null,
                                ], fn ($v) => $v !== null);
                            })
                            ->values()
                            ->all(),
                    ];
                } else {
                    return [
                        'success' => false,
                        'status' => 422,
                        'message' => 'fulfillment_order_id or line_items_by_fulfillment_order is required',
                        'data' => null,
                        'errors' => [],
                    ];
                }
            } else {
                $lineItemsByFO = collect($lineItemsByFO)
                    ->map(function ($fo) {
                        return [
                            'fulfillmentOrderId' => $this->toFulfillmentOrderGid($fo['fulfillment_order_id'] ?? $fo['fulfillmentOrderId']),
                            'fulfillmentOrderLineItems' => collect($fo['fulfillment_order_line_items'] ?? $fo['fulfillmentOrderLineItems'] ?? [])
                                ->map(function ($item) {
                                    return array_filter([
                                        'id' => !empty($item['id'])
                                            ? (str_starts_with((string) $item['id'], 'gid://')
                                                ? (string) $item['id']
                                                : 'gid://shopify/FulfillmentOrderLineItem/' . $item['id'])
                                            : null,
                                        'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : null,
                                    ], fn ($v) => $v !== null);
                                })
                                ->values()
                                ->all(),
                        ];
                    })
                    ->values()
                    ->all();
            }
    
            $trackingInfo = null;
            if (!empty($data['tracking_info']) && is_array($data['tracking_info'])) {
                $trackingInfo = array_filter([
                    'company' => $data['tracking_info']['company'] ?? $data['tracking_info']['tracking_company'] ?? null,
                    'number' => $data['tracking_info']['number'] ?? $data['tracking_info']['tracking_number'] ?? null,
                    'numbers' => !empty($data['tracking_info']['numbers']) ? array_values($data['tracking_info']['numbers']) : null,
                    'url' => $data['tracking_info']['url'] ?? $data['tracking_info']['tracking_url'] ?? null,
                    'urls' => !empty($data['tracking_info']['urls']) ? array_values($data['tracking_info']['urls']) : null,
                ], fn ($v) => $v !== null && $v !== '');
            }
    
                    $mutation = <<<'GQL'
            mutation FulfillmentCreate($fulfillment: FulfillmentInput!, $message: String) {
            fulfillmentCreate(fulfillment: $fulfillment, message: $message) {
                fulfillment {
                id
                status
                trackingInfo(first: 10) {
                    company
                    number
                    url
                }
                fulfillmentLineItems(first: 50) {
                    edges {
                    node {
                        id
                        quantity
                        lineItem {
                        id
                        name
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
    
            $variables = [
                'fulfillment' => [
                    'lineItemsByFulfillmentOrder' => $lineItemsByFO,
                    'notifyCustomer' => (bool) ($data['notify_customer'] ?? true),
                    'trackingInfo' => $trackingInfo,
                ],
                'message' => $data['message'] ?? null,
            ];
    
            $result = $this->graphqlRequest((int) $shopId, $mutation, $variables);
    

            Log::info('Shopify create Fullfillment response', [
                'shop_id' => $shopId,
                'result' => $result,
            ]);

            if (!$result['success']) {
                return $result;
            }
    
            $payload = data_get($result, 'data.fulfillmentCreate');
            $userErrors = $payload['userErrors'] ?? [];
    
            if (!empty($userErrors)) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => $userErrors[0]['message'] ?? 'Failed to create fulfillment',
                    'data' => $payload,
                    'errors' => $userErrors,
                ];
            }
    
            return [
                'success' => true,
                'status' => $result['status'],
                'message' => 'Fulfillment created successfully',
                'data' => $payload['fulfillment'] ?? null,
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('Fulfillment created exception', [
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
     * updateFulfillmentTracking - Update the tracking information for a specific fulfillment. This is used to add or change tracking details for a fulfillment that has already been created.
     */
    public function updateFulfillmentTracking($shopId, $fulfillmentId, $trackingInfo): array
    {
        try {
            $trackingInput = array_filter([
                'number'  => $trackingInfo['number'] ?? $trackingInfo['tracking_number'] ?? null,
                'company' => $trackingInfo['company'] ?? $trackingInfo['tracking_company'] ?? null,
                'url'     => $trackingInfo['url'] ?? $trackingInfo['tracking_url'] ?? null,
    
                // Optional support for multi-package tracking
                'numbers' => !empty($trackingInfo['numbers']) ? array_values($trackingInfo['numbers']) : null,
                'urls'    => !empty($trackingInfo['urls']) ? array_values($trackingInfo['urls']) : null,
            ], fn ($v) => $v !== null && $v !== '');
    
                    $mutation = <<<'GQL'
            mutation FulfillmentTrackingInfoUpdate(
            $fulfillmentId: ID!,
            $trackingInfoInput: FulfillmentTrackingInput!,
            $notifyCustomer: Boolean
            ) {
            fulfillmentTrackingInfoUpdate(
                fulfillmentId: $fulfillmentId,
                trackingInfoInput: $trackingInfoInput,
                notifyCustomer: $notifyCustomer
            ) {
                fulfillment {
                id
                status
                trackingInfo {
                    company
                    number
                    url
                }
                }
                userErrors {
                field
                message
                }
            }
            }
            GQL;
    
            $variables = [
                'fulfillmentId' => str_starts_with((string) $fulfillmentId, 'gid://')
                    ? (string) $fulfillmentId
                    : 'gid://shopify/Fulfillment/' . $fulfillmentId,
                'trackingInfoInput' => $trackingInput,
                'notifyCustomer' => (bool) ($trackingInfo['notify_customer'] ?? true),
            ];
    
            $result = $this->graphqlRequest((int) $shopId, $mutation, $variables);
    
            if (!$result['success']) {
                return $result;
            }
    
            $payload = data_get($result, 'data.fulfillmentTrackingInfoUpdate');
            $userErrors = $payload['userErrors'] ?? [];
    
            if (!empty($userErrors)) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => $userErrors[0]['message'] ?? 'Failed to update fulfillment tracking',
                    'data' => $payload,
                    'errors' => $userErrors,
                ];
            }
    
            return [
                'success' => true,
                'status' => $result['status'],
                'message' => 'Fulfillment tracking updated successfully',
                'data' => $payload['fulfillment'] ?? null,
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('Fulfillment tracking update exception', [
                'shop_id' => $shopId,
                'fulfillment_id' => $fulfillmentId,
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
     * cancelFulfillment - Cancel a specific fulfillment. This is used to cancel a fulfillment that has been created but not yet fulfilled, often when an order is cancelled or a fulfillment needs to be voided.
     */
    public function cancelFulfillment($shopId, $shopifyOrderId, $fulfillmentId): array
    {
        try {
                        $mutation = <<<'GQL'
                mutation FulfillmentCancel($id: ID!) {
                fulfillmentCancel(id: $id) {
                    fulfillment {
                    id
                    status
                    trackingInfo {
                        company
                        number
                        url
                    }
                    fulfillmentLineItems(first: 50) {
                        edges {
                        node {
                            id
                            quantity
                            lineItem {
                            id
                            name
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
    
            $variables = [
                'id' => str_starts_with((string) $fulfillmentId, 'gid://')
                    ? (string) $fulfillmentId
                    : 'gid://shopify/Fulfillment/' . $fulfillmentId,
            ];
    
            $result = $this->graphqlRequest((int) $shopId, $mutation, $variables);
    
            if (!$result['success']) {
                return $result;
            }
    
            $payload = data_get($result, 'data.fulfillmentCancel');
            $userErrors = $payload['userErrors'] ?? [];
    
            if (!empty($userErrors)) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => $userErrors[0]['message'] ?? 'Failed to cancel fulfillment',
                    'data' => $payload,
                    'errors' => $userErrors,
                ];
            }
    
            return [
                'success' => true,
                'data' => $payload['fulfillment'] ?? null,
                'status' => $result['status'],
                'message' => 'Fulfillment cancelled successfully',
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('Fulfillment cancel exception', [
                'shop_id' => $shopId,
                'fulfillment_id' => $fulfillmentId,
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
     * getFulfillment - Retrieve a specific fulfillment from Shopify. This is used to get details about a fulfillment, including its status and tracking information.
     */
    public function getFulfillmentServices($shopId): array
    {
        try {
                    $query = <<<'GQL'
            query GetFulfillmentServices($first: Int!) {
            locations(first: $first, includeLegacy: true) {
                edges {
                node {
                    id
                    name
                    fulfillmentService {
                    id
                    serviceName
                    handle
                    inventoryManagement
                    trackingSupport
                    productBased
                    permitsSkuSharing
                    requiresShippingMethod
                    callbackUrl
                    location {
                        id
                        name
                    }
                    }
                }
                }
            }
            }
            GQL;
    
            $variables = [
                'first' => 100,
            ];
    
            $result = $this->graphqlRequest((int) $shopId, $query, $variables);
    
            if (!$result['success']) {
                return $result;
            }
    
            $locations = data_get($result, 'data.locations.edges', []);
    
            $services = collect($locations)
                ->map(fn ($edge) => $edge['node']['fulfillmentService'] ?? null)
                ->filter()
                ->values()
                ->all();
    
            return [
                'success' => true,
                'data' => $services,
                'status' => $result['status'],
                'message' => 'Fulfillment services fetched successfully',
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('Get fulfillment services exception', [
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
     * getFulfillment - Retrieve a specific fulfillment from Shopify.
     * This is used to get details about a fulfillment, including its status and tracking information.
     */
    public function getFulfillment($shopId, $fulfillmentId): array
    {
        try {
                    $query = <<<'GQL'
            query GetFulfillment($id: ID!) {
            fulfillment(id: $id) {
                id
                status
                createdAt
                updatedAt
                trackingInfo(first: 10) {
                company
                number
                url
                }
                fulfillmentLineItems(first: 50) {
                edges {
                    node {
                    id
                    quantity
                    lineItem {
                        id
                        name
                        sku
                    }
                    }
                }
                }
                order {
                id
                name
                }
            }
            }
            GQL;
    
            $variables = [
                'id' => str_starts_with((string) $fulfillmentId, 'gid://')
                    ? (string) $fulfillmentId
                    : 'gid://shopify/Fulfillment/' . $fulfillmentId,
            ];
    
            $result = $this->graphqlRequest((int) $shopId, $query, $variables);
    
            if (!$result['success']) {
                return $result;
            }
    
            $fulfillment = data_get($result, 'data.fulfillment');
    
            if ($fulfillment) {
                $fulfillment['line_items'] = collect($fulfillment['fulfillmentLineItems']['edges'] ?? [])
                    ->map(fn ($edge) => $edge['node'] ?? null)
                    ->filter()
                    ->values()
                    ->all();
            }
    
            return [
                'success' => true,
                'data' => $fulfillment,
                'status' => $result['status'],
                'message' => 'Fulfillment fetched successfully',
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('Get fulfillment exception', [
                'shop_id' => $shopId,
                'fulfillment_id' => $fulfillmentId,
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


    public function getOrderIdFromFulfillmentOrderGid(int $shopId, string $fulfillmentOrderGid): array
    {
            $query = <<<'GRAPHQL'
        query GetFulfillmentOrderOrderId($id: ID!) {
        fulfillmentOrder(id: $id) {
            id
            order {
            id
            legacyResourceId
            name
            }
        }
        }
        GRAPHQL;
        
            $response = $this->graphqlRequest($shopId, $query, [
                'id' => $fulfillmentOrderGid,
            ]);
        
            if (!$response['success']) {
                return $response;
            }
        
            $order = $response['data']['fulfillmentOrder']['order'] ?? null;
        
            return [
                'success' => true,
                'status' => $response['status'],
                'message' => 'Order resolved successfully',
                'data' => [
                    'order_gid' => $order['id'] ?? null,
                    'order_id' => $order['legacyResourceId'] ?? null,
                ],
                'errors' => [],
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

    private function legacyIdFromGid(string $gid): string
    {
        return Str::afterLast($gid, '/');
    }

    private function buildWeightBasedMethodDefinition(
        string $name,
        float $amount,
        string $currencyCode,
        float $minWeightLb,
        ?float $maxWeightLb = null
    ): array {
        $conditions = [
            [
                'operator' => 'GREATER_THAN_OR_EQUAL_TO',
                'criteria' => [
                    'value' => $minWeightLb,
                    'unit' => 'POUNDS',
                ],
            ],
        ];
    
        if ($maxWeightLb !== null) {
            $conditions[] = [
                'operator' => 'LESS_THAN_OR_EQUAL_TO',
                'criteria' => [
                    'value' => $maxWeightLb,
                    'unit' => 'POUNDS',
                ],
            ];
        }
    
        return [
            'name' => $name,
            'active' => true,
            'rateDefinition' => [
                'price' => [
                    'amount' => $amount,
                    'currencyCode' => $currencyCode,
                ],
            ],
            'weightConditionsToCreate' => $conditions,
        ];
    }
    
    private function getFallbackWeightRateSlabs(string $currencyCode = 'USD'): array
    {
        return [
            $this->buildWeightBasedMethodDefinition('Standard', 4.99, $currencyCode, 0, 1),
            $this->buildWeightBasedMethodDefinition('Standard', 7.99, $currencyCode, 1.0001, 5),
            $this->buildWeightBasedMethodDefinition('Standard', 12.99, $currencyCode, 5.0001, null),
        ];
    }
    
    private function buildWeightBasedUsZoneInput(string $currencyCode = 'USD'): array
    {
        return [
            'name' => 'United States',
            'countries' => [
                [
                    'code' => 'US',
                    'provinces' => array_map(
                        fn (string $code) => ['code' => $code],
                        $this->getUsProvinceCodes()
                    ),
                ],
            ],
            'methodDefinitionsToCreate' => $this->getFallbackWeightRateSlabs($currencyCode),
        ];
    }


    public function createCarrierService(
        int $shopId,
        string $callbackUrl,
        string $name = 'DTFTA USPS Rates'
    ): array {
            $query = <<<'GRAPHQL'
        mutation CarrierServiceCreate($input: DeliveryCarrierServiceCreateInput!) {
        carrierServiceCreate(input: $input) {
            carrierService {
            id
            name
            callbackUrl
            active
            supportsServiceDiscovery
            }
            userErrors {
            field
            message
            }
        }
        }
        GRAPHQL;
    
        $variables = [
            'input' => [
                'name' => $name,
                'callbackUrl' => $callbackUrl,
                'supportsServiceDiscovery' => true,
                'active' => true,
            ],
        ];
    
        return $this->graphqlRequest($shopId, $query, $variables);
    }


    public function createDeliveryProfile(int $shopId, string $locationId, string $profileName = 'DTFTA Shipping'): array
    {
            try {
                $locationGid = str_starts_with($locationId, 'gid://')
                    ? $locationId
                    : "gid://shopify/Location/{$locationId}";
        
                $query = <<<'GRAPHQL'
        mutation CreateDeliveryProfile($profile: DeliveryProfileInput!) {
        deliveryProfileCreate(profile: $profile) {
            profile {
            id
            name
            profileLocationGroups {
                locationGroup {
                id
                locations(first: 10) {
                    nodes {
                    id
                    name
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
        GRAPHQL;
        
            $variables = [
                'profile' => [
                    'name' => $profileName,
                    'locationGroupsToCreate' => [
                        [
                            'locationsToAdd' => [$locationGid],
                        'zonesToCreate' => [
                            $this->buildWeightBasedUsZoneInput('USD'),
                        ],
                        ],
                    ],
                ],
            ];
        
        
                $result = $this->graphqlRequest($shopId, $query, $variables);
        
        
                if (!($result['success'] ?? false)) {
                    return [
                        'success' => false,
                        'message' => $result['message'] ?? 'GraphQL request failed',
                        'errors' => $result['errors'] ?? [],
                        'data' => $result['data'] ?? null,
                    ];
                }
        
                if (!empty($result['errors'])) {
                    return [
                        'success' => false,
                        'message' => $result['errors'][0]['message'] ?? 'Shopify GraphQL returned errors',
                        'errors' => $result['errors'],
                        'data' => $result['data'] ?? null,
                    ];
                }
        
                $payload = data_get($result, 'data.deliveryProfileCreate');
        
                if (!$payload) {
                    return [
                        'success' => false,
                        'message' => 'Missing deliveryProfileCreate payload in Shopify response',
                        'data' => $result['data'] ?? null,
                        'errors' => $result['errors'] ?? [],
                    ];
                }
        
                if (!empty($payload['userErrors'])) {
                    return [
                        'success' => false,
                        'message' => $payload['userErrors'][0]['message'] ?? 'Failed to create delivery profile',
                        'errors' => $payload['userErrors'],
                        'data' => $payload,
                    ];
                }
        
                return [
                    'success' => true,
                    'message' => 'Delivery profile created successfully',
                    'data' => $payload['profile'] ?? null,
                ];
            } catch (\Throwable $e) {
                Log::error('createDeliveryProfile exception', [
                    'shop_id' => $shopId,
                    'location_id' => $locationId,
                    'error' => $e->getMessage(),
                ]);
        
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
    }

    public function createDeliveryProfileWithCarrierRate(
        int $shopId,
        string $locationId,
        string $carrierServiceId,
        string $profileName = 'DTFTA Shipping',
        string $methodName = 'Standard'
    ): array {
        try {
            $locationGid = str_starts_with($locationId, 'gid://')
                ? $locationId
                : "gid://shopify/Location/{$locationId}";
    
            $carrierServiceGid = str_starts_with($carrierServiceId, 'gid://')
                ? $carrierServiceId
                : "gid://shopify/DeliveryCarrierService/{$carrierServiceId}";
    
                    $query = <<<'GRAPHQL'
            mutation CreateDeliveryProfile($profile: DeliveryProfileInput!) {
            deliveryProfileCreate(profile: $profile) {
                profile {
                id
                name
                profileLocationGroups {
                    locationGroup {
                    id
                    locations(first: 10) {
                        nodes {
                        id
                        name
                        }
                    }
                    }
                    locationGroupZones(first: 10) {
                    nodes {
                        zone {
                        id
                        name
                        }
                        methodDefinitions(first: 20) {
                        nodes {
                            id
                            name
                            active
                            rateProvider {
                            ... on DeliveryParticipant {
                                id
                                carrierService {
                                id
                                name
                                }
                                participantServices {
                                name
                                active
                                }
                            }
                            ... on DeliveryRateDefinition {
                                id
                            }
                            }
                        }
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
            GRAPHQL;
    
            $variables = [
                'profile' => [
                    'name' => $profileName,
                    'locationGroupsToCreate' => [
                        [
                            'locationsToAdd' => [$locationGid],
                            'zonesToCreate' => [
                                $this->buildAppCalculatedUsZoneInput($carrierServiceGid, $methodName),
                            ],
                        ],
                    ],
                ],
            ];
    
            $result = $this->graphqlRequest($shopId, $query, $variables);
    
            if (!($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'GraphQL request failed',
                    'errors' => $result['errors'] ?? [],
                    'data' => $result['data'] ?? null,
                ];
            }
    
            if (!empty($result['errors'])) {
                return [
                    'success' => false,
                    'message' => $result['errors'][0]['message'] ?? 'Shopify GraphQL returned errors',
                    'errors' => $result['errors'],
                    'data' => $result['data'] ?? null,
                ];
            }
    
            $payload = data_get($result, 'data.deliveryProfileCreate');
    
            if (!$payload) {
                return [
                    'success' => false,
                    'message' => 'Missing deliveryProfileCreate payload in Shopify response',
                    'data' => $result['data'] ?? null,
                    'errors' => $result['errors'] ?? [],
                ];
            }
    
            if (!empty($payload['userErrors'])) {
                return [
                    'success' => false,
                    'message' => $payload['userErrors'][0]['message'] ?? 'Failed to create delivery profile',
                    'errors' => $payload['userErrors'],
                    'data' => $payload,
                ];
            }
    
            return [
                'success' => true,
                'message' => 'Delivery profile with app-calculated carrier rate created successfully',
                'data' => $payload['profile'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('createDeliveryProfileWithCarrierRate exception', [
                'shop_id' => $shopId,
                'location_id' => $locationId,
                'carrier_service_id' => $carrierServiceId,
                'error' => $e->getMessage(),
            ]);
    
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getDeliveryProfileDetails(int $shopId, string $deliveryProfileId): array
    {
        try {
            $profileGid = str_starts_with($deliveryProfileId, 'gid://')
                ? $deliveryProfileId
                : "gid://shopify/DeliveryProfile/{$deliveryProfileId}";
    
                    $query = <<<'GRAPHQL'
            query GetDeliveryProfile($id: ID!) {
            node(id: $id) {
                ... on DeliveryProfile {
                id
                name
                profileLocationGroups {
                    locationGroup {
                    id
                    locations(first: 20) {
                        nodes {
                        id
                        name
                        }
                    }
                    }
                    locationGroupZones(first: 20) {
                    nodes {
                        zone {
                        id
                        name
                        countries {
                            code {
                            countryCode
                            }
                            provinces {
                            code
                            }
                        }
                        }
                        methodDefinitions(first: 50) {
                        nodes {
                            id
                            name
                            description
                            active
                            methodConditions {
                            id
                            field
                            operator
                            conditionCriteria {
                                ... on Weight {
                                value
                                unit
                                }
                                ... on MoneyV2 {
                                amount
                                currencyCode
                                }
                            }
                            }
                            rateProvider {
                            ... on DeliveryRateDefinition {
                                id
                                price {
                                amount
                                currencyCode
                                }
                            }
                            ... on DeliveryParticipant {
                                id
                                carrierService {
                                id
                                name
                                active
                                callbackUrl
                                }
                                participantServices {
                                name
                                active
                                }
                                fixedFee {
                                amount
                                currencyCode
                                }
                                percentageOfRateFee
                            }
                            }
                        }
                        }
                    }
                    }
                }
                }
            }
            }
            GRAPHQL;
    
    
            $result = $this->graphqlRequest($shopId, $query, [
                'id' => $profileGid,
            ]);
    
    
            if (!($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'GraphQL request failed',
                    'errors' => $result['errors'] ?? [],
                    'data' => $result['data'] ?? null,
                ];
            }
    
            if (!empty($result['errors'])) {
                return [
                    'success' => false,
                    'message' => $result['errors'][0]['message'] ?? 'Shopify GraphQL returned errors',
                    'errors' => $result['errors'],
                    'data' => $result['data'] ?? null,
                ];
            }
    
            $profile = data_get($result, 'data.node');
    
            if (!$profile) {
                return [
                    'success' => false,
                    'message' => 'Delivery profile not found',
                    'data' => $result['data'] ?? null,
                ];
            }
    
            return [
                'success' => true,
                'message' => 'Delivery profile fetched successfully',
                'data' => $profile,
            ];
        } catch (\Throwable $e) {
            Log::error('getDeliveryProfileDetails exception', [
                'shop_id' => $shopId,
                'delivery_profile_id' => $deliveryProfileId,
                'error' => $e->getMessage(),
            ]);
    
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function getUsProvinceCodes(): array
    {
        return [
            'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA',
            'HI','ID','IL','IN','IA','KS','KY','LA','ME','MD',
            'MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
            'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC',
            'SD','TN','TX','UT','VT','VA','WA','WV','WI','WY',
        ];
    }

    private function buildStandardUsZoneInput(float $amount = 4.99, string $currencyCode = 'USD'): array
    {
        return [
            'name' => 'United States',
            'countries' => [
                [
                    'code' => 'US',
                    'provinces' => array_map(
                        fn (string $code) => ['code' => $code],
                        $this->getUsProvinceCodes()
                    ),
                ],
            ],
            'methodDefinitionsToCreate' => [
                [
                    'name' => 'Standard',
                    'rateDefinition' => [
                        'price' => [
                            'amount' => $amount,
                            'currencyCode' => $currencyCode,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildAppCalculatedUsZoneInput(
        string $carrierServiceId,
        string $methodName = 'Standard'
    ): array {
        return [
            'name' => 'United States',
            'countries' => [
                [
                    'code' => 'US',
                    'provinces' => array_map(
                        fn (string $code) => ['code' => $code],
                        $this->getUsProvinceCodes()
                    ),
                ],
            ],
            'methodDefinitionsToCreate' => [
                [
                    'name' => $methodName,
                    'active' => true,
                    'participant' => [
                        'carrierServiceId' => $carrierServiceId,
                        'adaptToNewServices' => true,
                        'participantServices' => [
                            [
                                'name' => $methodName,
                                'active' => true,
                            ],
                        ],
                        // optional merchant markup:
                        // 'fixedFee' => [
                        //     'amount' => 0,
                        //     'currencyCode' => 'USD',
                        // ],
                        // 'percentageOfRateFee' => 0,
                    ],
                ],
            ],
        ];
    }

    public function syncDeliveryProfileConfiguration(
        int $shopId,
        string $deliveryProfileId,
        string $locationId,
        string $carrierServiceId,
        string $profileName = 'DTFTA Shipping',
        string $methodName = 'Standard'
    ): array {
        try {
            $profileGid = str_starts_with($deliveryProfileId, 'gid://')
                ? $deliveryProfileId
                : "gid://shopify/DeliveryProfile/{$deliveryProfileId}";
    
            $locationGid = str_starts_with($locationId, 'gid://')
                ? $locationId
                : "gid://shopify/Location/{$locationId}";
    
            $carrierServiceGid = str_starts_with($carrierServiceId, 'gid://')
                ? $carrierServiceId
                : "gid://shopify/DeliveryCarrierService/{$carrierServiceId}";
    
            $details = $this->getDeliveryProfileDetails($shopId, $profileGid);
            if (!($details['success'] ?? false)) {
                return $details;
            }
    
            $profile = $details['data'] ?? [];
            $profileLocationGroups = $profile['profileLocationGroups'] ?? [];
    
            $existingGroup = $profileLocationGroups[0] ?? null;
            $locationGroup = $existingGroup['locationGroup'] ?? null;
            $existingZones = data_get($existingGroup, 'locationGroupZones.nodes', []);
    
            $groupId = $locationGroup['id'] ?? null;
            $existingLocationIds = collect(data_get($locationGroup, 'locations.nodes', []))
                ->pluck('id')
                ->filter()
                ->values()
                ->all();
    
            $hasLocationAlready = in_array($locationGid, $existingLocationIds, true);
    
            $usZoneNode = null;
            foreach ($existingZones as $zoneNode) {
                $zoneName = data_get($zoneNode, 'zone.name');
                $countryCode = data_get($zoneNode, 'zone.countries.0.code.countryCode');
    
                if ($zoneName === 'United States' || $countryCode === 'US') {
                    $usZoneNode = $zoneNode;
                    break;
                }
            }
    
            $profileInput = [
                'name' => $profileName,
            ];
    
            if (!$groupId) {
                // No location group exists yet -> create full structure
                $profileInput['locationGroupsToCreate'] = [
                    [
                        'locationsToAdd' => [$locationGid],
                        'zonesToCreate' => [
                            [
                                'name' => 'United States',
                                'countries' => [
                                    [
                                        'code' => 'US',
                                        'provinces' => array_map(
                                            fn (string $code) => ['code' => $code],
                                            $this->getUsProvinceCodes()
                                        ),
                                    ],
                                ],
                                'methodDefinitionsToCreate' => [
                                    [
                                        'name' => $methodName,
                                        'active' => true,
                                        'participant' => [
                                            'carrierServiceId' => $carrierServiceGid,
                                            'adaptToNewServices' => true,
                                            'participantServices' => [
                                                [
                                                    'name' => $methodName,
                                                    'active' => true,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            } else {
                $groupUpdate = [
                    'id' => $groupId,
                ];
    
                if (!$hasLocationAlready) {
                    $groupUpdate['locationsToAdd'] = [$locationGid];
                }
    
                // Optional cleanup: keep only this location in the group
                $locationsToRemove = array_values(array_filter(
                    $existingLocationIds,
                    fn (string $id) => $id !== $locationGid
                ));
    
                if (!empty($locationsToRemove)) {
                    $groupUpdate['locationsToRemove'] = $locationsToRemove;
                }
    
                if (!$usZoneNode) {
                    // Group exists, but US zone does not -> create zone with carrier/app-calculated rate
                    $groupUpdate['zonesToCreate'] = [
                        [
                            'name' => 'United States',
                            'countries' => [
                                [
                                    'code' => 'US',
                                    'provinces' => array_map(
                                        fn (string $code) => ['code' => $code],
                                        $this->getUsProvinceCodes()
                                    ),
                                ],
                            ],
                            'methodDefinitionsToCreate' => [
                                [
                                    'name' => $methodName,
                                    'active' => true,
                                    'participant' => [
                                        'carrierServiceId' => $carrierServiceGid,
                                        'adaptToNewServices' => true,
                                        'participantServices' => [
                                            [
                                                'name' => $methodName,
                                                'active' => true,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ];
                } else {
                    // Zone already exists -> update existing method(s) or create one if missing
                    $zoneId = data_get($usZoneNode, 'zone.id');
                    $methodNodes = data_get($usZoneNode, 'methodDefinitions.nodes', []);
    
                    $zoneUpdate = [
                        'id' => $zoneId,
                        'name' => 'United States',
                    ];
    
                    if (!empty($methodNodes)) {
                        $zoneUpdate['methodDefinitionsToUpdate'] = array_map(
                            function (array $method) use ($carrierServiceGid, $methodName) {
                                return [
                                    'id' => $method['id'],
                                    'name' => $method['name'] ?? $methodName,
                                    'active' => true,
                                    'participant' => [
                                        'carrierServiceId' => $carrierServiceGid,
                                        'adaptToNewServices' => true,
                                        'participantServices' => [
                                            [
                                                'name' => $method['name'] ?? $methodName,
                                                'active' => true,
                                            ],
                                        ],
                                    ],
                                ];
                            },
                            $methodNodes
                        );
                    } else {
                        $zoneUpdate['methodDefinitionsToCreate'] = [
                            [
                                'name' => $methodName,
                                'active' => true,
                                'participant' => [
                                    'carrierServiceId' => $carrierServiceGid,
                                    'adaptToNewServices' => true,
                                    'participantServices' => [
                                        [
                                            'name' => $methodName,
                                            'active' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ];
                    }
    
                    $groupUpdate['zonesToUpdate'] = [$zoneUpdate];
                }
    
                $profileInput['locationGroupsToUpdate'] = [$groupUpdate];
            }
    
                    $mutation = <<<'GRAPHQL'
            mutation SyncDeliveryProfile($id: ID!, $profile: DeliveryProfileInput!) {
            deliveryProfileUpdate(id: $id, profile: $profile) {
                profile {
                id
                name
                profileLocationGroups {
                    locationGroup {
                    id
                    locations(first: 20) {
                        nodes {
                        id
                        name
                        }
                    }
                    }
                    locationGroupZones(first: 20) {
                    nodes {
                        zone {
                        id
                        name
                        countries {
                            code {
                            countryCode
                            }
                            provinces {
                            code
                            }
                        }
                        }
                        methodDefinitions(first: 20) {
                        nodes {
                            id
                            name
                            active
                            rateProvider {
                            ... on DeliveryRateDefinition {
                                id
                                price {
                                amount
                                currencyCode
                                }
                            }
                            ... on DeliveryParticipant {
                                id
                                carrierService {
                                id
                                name
                                active
                                callbackUrl
                                }
                                participantServices {
                                name
                                active
                                }
                                fixedFee {
                                amount
                                currencyCode
                                }
                                percentageOfRateFee
                            }
                            }
                        }
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
            GRAPHQL;
    
            $variables = [
                'id' => $profileGid,
                'profile' => $profileInput,
            ];
    
    
            $result = $this->graphqlRequest($shopId, $mutation, $variables);
    
            Log::info('Shopify deliveryProfileUpdate sync response', [
                'shop_id' => $shopId,
                'result' => $result,
            ]);
    
            if (!($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'GraphQL request failed',
                    'errors' => $result['errors'] ?? [],
                    'data' => $result['data'] ?? null,
                ];
            }
    
            if (!empty($result['errors'])) {
                return [
                    'success' => false,
                    'message' => $result['errors'][0]['message'] ?? 'Shopify GraphQL returned errors',
                    'errors' => $result['errors'],
                    'data' => $result['data'] ?? null,
                ];
            }
    
            $payload = data_get($result, 'data.deliveryProfileUpdate');
    
            if (!$payload) {
                return [
                    'success' => false,
                    'message' => 'Missing deliveryProfileUpdate payload in Shopify response',
                    'data' => $result['data'] ?? null,
                    'errors' => $result['errors'] ?? [],
                ];
            }
    
            if (!empty($payload['userErrors'])) {
                return [
                    'success' => false,
                    'message' => $payload['userErrors'][0]['message'] ?? 'Failed to sync delivery profile',
                    'errors' => $payload['userErrors'],
                    'data' => $payload,
                ];
            }
    
            return [
                'success' => true,
                'message' => 'Delivery profile synchronized successfully',
                'data' => $payload['profile'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('syncDeliveryProfileConfiguration exception', [
                'shop_id' => $shopId,
                'delivery_profile_id' => $deliveryProfileId,
                'location_id' => $locationId,
                'carrier_service_id' => $carrierServiceId,
                'error' => $e->getMessage(),
            ]);
    
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function syncDeliveryProfileConfigurationFlat(
        int $shopId,
        string $deliveryProfileId,
        string $locationId,
        string $profileName = 'DTFTA Shipping',
        string $currencyCode = 'USD'
    ): array {
        try {
            $profileGid = str_starts_with($deliveryProfileId, 'gid://')
                ? $deliveryProfileId
                : "gid://shopify/DeliveryProfile/{$deliveryProfileId}";
    
            $locationGid = str_starts_with($locationId, 'gid://')
                ? $locationId
                : "gid://shopify/Location/{$locationId}";
    
            $details = $this->getDeliveryProfileDetails($shopId, $profileGid);
            if (!($details['success'] ?? false)) {
                return $details;
            }
    
            $profile = $details['data'] ?? [];
            $profileLocationGroups = $profile['profileLocationGroups'] ?? [];
    
            $existingGroup = $profileLocationGroups[0] ?? null;
            $locationGroup = $existingGroup['locationGroup'] ?? null;
            $existingZones = data_get($existingGroup, 'locationGroupZones.nodes', []);
    
            $groupId = $locationGroup['id'] ?? null;
            $existingLocationIds = collect(data_get($locationGroup, 'locations.nodes', []))
                ->pluck('id')
                ->filter()
                ->values()
                ->all();
    
            $hasLocationAlready = in_array($locationGid, $existingLocationIds, true);
    
            $usZoneNode = null;
            foreach ($existingZones as $zoneNode) {
                $zoneName = data_get($zoneNode, 'zone.name');
                $countryCode = data_get($zoneNode, 'zone.countries.0.code.countryCode');
    
                if ($zoneName === 'United States' || $countryCode === 'US') {
                    $usZoneNode = $zoneNode;
                    break;
                }
            }
    
            $profileInput = [
                'name' => $profileName,
            ];
    
            if (!$groupId) {
                $profileInput['locationGroupsToCreate'] = [
                    [
                        'locationsToAdd' => [$locationGid],
                        'zonesToCreate' => [
                            $this->buildWeightBasedUsZoneInput($currencyCode),
                        ],
                    ],
                ];
            } else {
                $groupUpdate = [
                    'id' => $groupId,
                ];
    
                if (!$hasLocationAlready) {
                    $groupUpdate['locationsToAdd'] = [$locationGid];
                }
    
                $locationsToRemove = array_values(array_filter(
                    $existingLocationIds,
                    fn (string $id) => $id !== $locationGid
                ));
    
                if (!empty($locationsToRemove)) {
                    $groupUpdate['locationsToRemove'] = $locationsToRemove;
                }
    
                if (!$usZoneNode) {
                    $groupUpdate['zonesToCreate'] = [
                        $this->buildWeightBasedUsZoneInput($currencyCode),
                    ];
                } else {
                    $zoneId = data_get($usZoneNode, 'zone.id');
                    $methodNodes = data_get($usZoneNode, 'methodDefinitions.nodes', []);
    
                    $zoneUpdate = [
                        'id' => $zoneId,
                        'name' => 'United States',
                    ];
    
                    $existingMethodIds = collect($methodNodes)
                        ->pluck('id')
                        ->filter()
                        ->values()
                        ->all();
    
                    if (!empty($existingMethodIds)) {
                        $zoneUpdate['methodDefinitionsToDelete'] = $existingMethodIds;
                    }
    
                    $zoneUpdate['methodDefinitionsToCreate'] = $this->getFallbackWeightRateSlabs($currencyCode);
    
                    $groupUpdate['zonesToUpdate'] = [$zoneUpdate];
                }
    
                $profileInput['locationGroupsToUpdate'] = [$groupUpdate];
            }
    
                        $mutation = <<<'GRAPHQL'
                mutation SyncDeliveryProfileFlat($id: ID!, $profile: DeliveryProfileInput!) {
                deliveryProfileUpdate(id: $id, profile: $profile) {
                    profile {
                    id
                    name
                    profileLocationGroups {
                        locationGroup {
                        id
                        locations(first: 20) {
                            nodes {
                            id
                            name
                            }
                        }
                        }
                        locationGroupZones(first: 20) {
                        nodes {
                            zone {
                            id
                            name
                            }
                            methodDefinitions(first: 50) {
                            nodes {
                                id
                                name
                                active
                            }
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
                GRAPHQL;
    
            $variables = [
                'id' => $profileGid,
                'profile' => $profileInput,
            ];
    
    
            $result = $this->graphqlRequest($shopId, $mutation, $variables);
    
    
            if (!($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'GraphQL request failed',
                    'errors' => $result['errors'] ?? [],
                    'data' => $result['data'] ?? null,
                ];
            }
    
            if (!empty($result['errors'])) {
                return [
                    'success' => false,
                    'message' => $result['errors'][0]['message'] ?? 'Shopify GraphQL returned errors',
                    'errors' => $result['errors'],
                    'data' => $result['data'] ?? null,
                ];
            }
    
            $payload = data_get($result, 'data.deliveryProfileUpdate');
    
            if (!$payload) {
                return [
                    'success' => false,
                    'message' => 'Missing deliveryProfileUpdate payload in Shopify response',
                    'data' => $result['data'] ?? null,
                    'errors' => $result['errors'] ?? [],
                ];
            }
    
            if (!empty($payload['userErrors'])) {
                return [
                    'success' => false,
                    'message' => $payload['userErrors'][0]['message'] ?? 'Failed to sync weight-based fallback delivery profile',
                    'errors' => $payload['userErrors'],
                    'data' => $payload,
                ];
            }
    
            return [
                'success' => true,
                'message' => 'Weight-based fallback delivery profile synchronized successfully',
                'data' => $payload['profile'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('syncDeliveryProfileConfigurationFlat exception', [
                'shop_id' => $shopId,
                'delivery_profile_id' => $deliveryProfileId,
                'location_id' => $locationId,
                'error' => $e->getMessage(),
            ]);
    
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function removeDeliveryProfile(int $shopId, string $profileId): array
    {
                $mutation = <<<'GQL'
        mutation RemoveDeliveryProfile($id: ID!) {
        deliveryProfileRemove(id: $id) {
            job {
            id
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;

            $result = $this->graphqlRequest($shopId, $mutation, [
                'id' => $profileId,
            ]);

            if (!($result['success'] ?? false)) {
                return $result;
            }

            $payload = data_get($result, 'data.deliveryProfileRemove');

            if (!empty($payload['userErrors'])) {
                return [
                    'success' => false,
                    'message' => $payload['userErrors'][0]['message'] ?? 'Failed to remove delivery profile',
                    'errors' => $payload['userErrors'],
                    'data' => $payload,
                ];
            }

            return [
                'success' => true,
                'message' => 'Delivery profile removal queued',
                'data' => $payload,
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
        $callbackUrl = $baseUrl . '/api/v1';

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
        Log::info('Provision start', ['shop_id' => $shopId]);
    
        $shop = Shop::find($shopId);
    
        if (!$shop) {
            Log::error('Provision failed: shop not found', ['shop_id' => $shopId]);
            return ['success' => false, 'message' => 'Shop not found'];
        }
    
        $baseUrl = rtrim($baseUrl ?: (string) config('app.url'), '/');
        $fulfillmentCallbackUrl = "{$baseUrl}/api/v1";
        $carrierCallbackUrl = "{$baseUrl}/api/v1/carrier-service";
    
        Log::info('Provision config prepared', [
            'shop_id' => $shopId,
            'base_url' => $baseUrl,
            'fulfillment_callback' => $fulfillmentCallbackUrl,
            'carrier_callback' => $carrierCallbackUrl,
            'existing_fulfillment_service_id' => $shop->fulfillment_service_id,
            'existing_location_id' => $shop->location_id,
            'existing_carrier_service_id' => $shop->carrier_service_id,
            'existing_shipping_profile_id' => $shop->shipping_profile_id,
        ]);
    
        $serviceResult = null;
        $carrierServiceResult = null;
        $deliveryProfileResult = null;
        $useFallbackRate = false;
        $fallbackReason = null;
        // Default shipping profile should use weight-based slabs (fallback),
        // even when carrier calculated shipping (app-calculated) is available.
        $deliveryProfileUsedWeightBasedDefault = false;
    
        // =============================
        // Fulfillment Service
        // =============================
        if (empty($shop->fulfillment_service_id) || empty($shop->location_id)) {
            Log::info('Creating fulfillment service', ['shop_id' => $shopId]);
    
            $serviceResult = $this->createFulfillmentService(
                $shopId,
                $fulfillmentCallbackUrl,
                'DTFTA Fulfillment Service'
            );
    
    
            if (!($serviceResult['success'] ?? false)) {
                Log::error('Fulfillment service creation failed', [
                    'shop_id' => $shopId,
                    'result' => $serviceResult,
                ]);
    
                return [
                    'success' => false,
                    'message' => $serviceResult['message'] ?? 'Failed to create fulfillment service',
                    'data' => [
                        'fulfillment_service' => $serviceResult['data'] ?? null,
                    ],
                ];
            }
    
            $service = $serviceResult['data'] ?? [];
    
            $shop->fulfillment_service_id = !empty($service['id'])
                ? "gid://shopify/FulfillmentService/{$service['id']}"
                : $shop->fulfillment_service_id;
    
            $shop->location_id = !empty($service['location_id'])
                ? "gid://shopify/Location/{$service['location_id']}"
                : $shop->location_id;
    
            $shop->save();


    
            Log::info('Fulfillment service saved', [
                'shop_id' => $shopId,
                'fulfillment_service_id' => $shop->fulfillment_service_id,
                'location_id' => $shop->location_id,
            ]);

            $service = FulfillmentService::withTrashed()->firstOrNew(['shop_id' => $shop->id]);
        
                if ($service->exists && $service->trashed()) {
                    $service->restore();
                }
                
                $service->fill([
                    'service_id' => basename($shop->fulfillment_service_id),
                    'shopify_fulfillment_service_id' => $shop->fulfillment_service_id,
                    'shopify_location_id' => $shop->location_id,
                    'name' => FulfillmentService::DEFAULT_NAME,
                    'status' => FulfillmentService::STATUS_ACTIVE,
                    'tracking_support' => true,
                ]);
                
                $service->save();
        }
    
        // =============================
        // Carrier Service / CCS detection
        // =============================
        if (empty($shop->carrier_service_id)) {
            Log::info('Creating carrier service', ['shop_id' => $shopId]);
    
            $carrierServiceResult = $this->createCarrierService(
                $shopId,
                $carrierCallbackUrl,
                'DTFTA USPS Rates'
            );
    
            $carrierUserErrors = data_get($carrierServiceResult, 'data.carrierServiceCreate.userErrors', []);
            $carrierErrorMessage = $carrierUserErrors[0]['message'] ?? null;
    
            if (!empty($carrierUserErrors)) {
                if (is_string($carrierErrorMessage) && str_contains($carrierErrorMessage, 'Carrier Calculated Shipping must be enabled')) {
                    $useFallbackRate = true;
                    $fallbackReason = $carrierErrorMessage;
    
                    Log::warning('CCS not enabled, falling back to flat Standard rate', [
                        'shop_id' => $shopId,
                        'user_errors' => $carrierUserErrors,
                    ]);
                } else {
                    Log::error('Carrier service returned user errors', [
                        'shop_id' => $shopId,
                        'user_errors' => $carrierUserErrors,
                        'result' => $carrierServiceResult,
                    ]);
    
                    return [
                        'success' => false,
                        'message' => $carrierErrorMessage ?? 'Failed to create carrier service',
                        'data' => [
                            'fulfillment_service' => $serviceResult['data'] ?? null,
                            'carrier_service' => data_get($carrierServiceResult, 'data.carrierServiceCreate.carrierService'),
                        ],
                    ];
                }
            } elseif (!($carrierServiceResult['success'] ?? false)) {
                Log::error('Carrier service request failed', [
                    'shop_id' => $shopId,
                    'result' => $carrierServiceResult,
                ]);
    
                return [
                    'success' => false,
                    'message' => $carrierServiceResult['message'] ?? 'Failed to create carrier service',
                    'data' => [
                        'fulfillment_service' => $serviceResult['data'] ?? null,
                        'carrier_service' => $carrierServiceResult['data'] ?? null,
                    ],
                ];
            } else {
                $carrierService = data_get($carrierServiceResult, 'data.carrierServiceCreate.carrierService', []);
    
                if (!empty($carrierService['id'])) {
                    $shop->carrier_service_id = (string) $carrierService['id'];
                    $shop->save();
    
                    Log::info('Carrier service saved', [
                        'shop_id' => $shopId,
                        'carrier_service_id' => $shop->carrier_service_id,
                    ]);
                }
            }
        } else {
            Log::info('Carrier service already exists; using weight-based delivery profile default', [
                'shop_id' => $shopId,
                'carrier_service_id' => $shop->carrier_service_id,
            ]);
        }
    
        // =============================
        // Delivery Profile
        // =============================
        if (!empty($shop->location_id)) {
            if (empty($shop->shipping_profile_id)) {
                if ($useFallbackRate || empty($shop->carrier_service_id)) {
                    Log::info('Creating weight-based delivery profile (fallback slabs)', [
                        'shop_id' => $shopId,
                        'location_id' => $shop->location_id,
                        'fallback_reason' => $fallbackReason,
                    ]);
    
                    $deliveryProfileResult = $this->createDeliveryProfile(
                        (int) $shop->id,
                        (string) $shop->location_id,
                        'DTFTA Shipping'
                    );
                    $deliveryProfileUsedWeightBasedDefault = true;
                } else {
                    Log::info('Creating weight-based delivery profile (replacing app-calculated default)', [
                        'shop_id' => $shopId,
                        'location_id' => $shop->location_id,
                        'carrier_service_id' => $shop->carrier_service_id,
                    ]);
    
                    // Use weight-based slabs as the default delivery method
                    // even when app-calculated/carrier calculated shipping is available.
                    $deliveryProfileResult = $this->createDeliveryProfile(
                        (int) $shop->id,
                        (string) $shop->location_id,
                        'DTFTA Shipping'
                    );
                    $deliveryProfileUsedWeightBasedDefault = true;
                }
    
                Log::info('Delivery profile create response', [
                    'shop_id' => $shopId,
                    'result' => $deliveryProfileResult,
                ]);
    
                if (!($deliveryProfileResult['success'] ?? false)) {
                    Log::error('Delivery profile creation failed', [
                        'shop_id' => $shopId,
                        'result' => $deliveryProfileResult,
                    ]);
    
                    return [
                        'success' => false,
                        'message' => $deliveryProfileResult['message'] ?? 'Failed to create delivery profile',
                        'data' => [
                            'delivery_profile' => $deliveryProfileResult['data'] ?? null,
                        ],
                    ];
                }
    
                $profile = $deliveryProfileResult['data'] ?? [];
    
                if (!empty($profile['id'])) {
                    $shop->shipping_profile_id = (string) $profile['id'];
                }
    
                $groupId = data_get($profile, 'profileLocationGroups.0.locationGroup.id');
                if ($groupId) {
                    $shop->delivery_location_group_id = (string) $groupId;
                }
    
                $shop->save();
    
                Log::info('Delivery profile saved', [
                    'shop_id' => $shopId,
                    'shipping_profile_id' => $shop->shipping_profile_id,
                    'delivery_location_group_id' => $shop->delivery_location_group_id,
                    'used_fallback_rate' => $deliveryProfileUsedWeightBasedDefault,
                ]);
            } else {
                if ($useFallbackRate || empty($shop->carrier_service_id)) {
                    Log::info('Syncing weight-based delivery profile (fallback slabs)', [
                        'shop_id' => $shopId,
                        'profile_id' => $shop->shipping_profile_id,
                        'location_id' => $shop->location_id,
                        'fallback_reason' => $fallbackReason,
                    ]);
    
                    $deliveryProfileResult = $this->syncDeliveryProfileConfigurationFlat(
                        (int) $shop->id,
                        (string) $shop->shipping_profile_id,
                        (string) $shop->location_id,
                        'DTFTA Shipping',
                        'USD'
                    );
                    $deliveryProfileUsedWeightBasedDefault = true;
                } else {
                    Log::info('Syncing weight-based delivery profile (replacing app-calculated default)', [
                        'shop_id' => $shopId,
                        'profile_id' => $shop->shipping_profile_id,
                        'carrier_service_id' => $shop->carrier_service_id,
                    ]);
    
                    // Replace any app-calculated carrier service methods with
                    // weight-based fallback slabs.
                    $deliveryProfileResult = $this->syncDeliveryProfileConfigurationFlat(
                        (int) $shop->id,
                        (string) $shop->shipping_profile_id,
                        (string) $shop->location_id,
                        'DTFTA Shipping',
                        'USD'
                    );
                    $deliveryProfileUsedWeightBasedDefault = true;
                }
    
                Log::info('Delivery profile sync response', [
                    'shop_id' => $shopId,
                    'result' => $deliveryProfileResult,
                ]);
    
                if (!($deliveryProfileResult['success'] ?? false)) {
                    Log::error('Delivery profile sync failed', [
                        'shop_id' => $shopId,
                        'result' => $deliveryProfileResult,
                    ]);
    
                    return [
                        'success' => false,
                        'message' => $deliveryProfileResult['message'] ?? 'Failed to sync delivery profile',
                        'data' => [
                            'delivery_profile' => $deliveryProfileResult['data'] ?? null,
                        ],
                    ];
                }
    
                $profile = $deliveryProfileResult['data'] ?? [];
                $groupId = data_get($profile, 'profileLocationGroups.0.locationGroup.id');
    
                if ($groupId) {
                    $shop->delivery_location_group_id = (string) $groupId;
                    $shop->save();
                }
    
                Log::info('Delivery profile sync saved', [
                    'shop_id' => $shopId,
                    'delivery_location_group_id' => $shop->delivery_location_group_id,
                    'used_fallback_rate' => $deliveryProfileUsedWeightBasedDefault,
                ]);
            }
        } else {
            Log::warning('Skipping delivery profile setup because location_id is missing', [
                'shop_id' => $shopId,
            ]);
        }
    
        // =============================
        // Webhooks
        // =============================
        // Log::info('Registering webhooks', ['shop_id' => $shopId]);
    
        // $webhooks = $this->ensureRequiredWebhooks($shopId, $baseUrl);
    
        // Log::info('Webhook result', [
        //     'shop_id' => $shopId,
        //     'result' => $webhooks,
        // ]);
    
        Log::info('Provision completed', [
            'shop_id' => $shopId,
            'used_fallback_rate' => $deliveryProfileUsedWeightBasedDefault,
            'fallback_reason' => $fallbackReason,
        ]);
    
        return [
            'success' =>
                ($serviceResult ? (bool) ($serviceResult['success'] ?? false) : true) &&
                ($deliveryProfileResult ? (bool) ($deliveryProfileResult['success'] ?? false) : true) &&
                (bool) ($webhooks['success'] ?? false),
            'data' => [
                'fulfillment_service' => $serviceResult['data'] ?? null,
                'carrier_service' => data_get($carrierServiceResult, 'data.carrierServiceCreate.carrierService'),
                'delivery_profile' => $deliveryProfileResult['data'] ?? null,
                'webhooks' => $webhooks['data'] ?? [],
                'used_fallback_rate' => $deliveryProfileUsedWeightBasedDefault,
                'fallback_reason' => $fallbackReason,
            ],
            'message' => $deliveryProfileUsedWeightBasedDefault
                ? 'Provisioning completed with weight-based delivery profile default'
                : 'Provisioning completed',
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
                'variants' => $this->normalizeGraphqlProductVariantList($payloadData['productVariants'] ?? null),
            ],
            'errors' => [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeGraphqlProductVariantList(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        if (! is_array($raw)) {
            return [];
        }

        if (isset($raw['edges']) && is_array($raw['edges'])) {
            return collect($raw['edges'])
                ->map(fn ($edge) => is_array($edge) ? ($edge['node'] ?? null) : null)
                ->filter(fn ($node) => is_array($node) && ! empty($node['id']))
                ->values()
                ->all();
        }

        if (array_is_list($raw)) {
            return collect($raw)
                ->filter(fn ($item) => is_array($item) && ! empty($item['id']))
                ->values()
                ->all();
        }

        if (! empty($raw['id'])) {
            return [$raw];
        }

        return [];
    }
    
    private function normalizeArtworkBatchForShopify(array $artwork): array
    {
        $normalized = [];
    
        foreach ($artwork as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
    
            $placement = strtolower(trim((string) ($item['placement'] ?? 'artwork_' . ($index + 1))));
            $title = trim((string) ($item['title'] ?? ucfirst(str_replace('_', ' ', $placement)) . ' artwork'));
    
            $url = trim((string) (
                $item['url']
                ?? $item['artwork_url']
                ?? ''
            ));
    
            $sourceCode = trim((string) (
                $item['source_code']
                ?? data_get($item, 'meta.source_code')
                ?? ''
            ));
    
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
        $isDataUrl = static function (string $value): bool {
            return (bool) preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', $value);
        };

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
    
            // source_code can be either data-url or already-hosted URL.
            if ($sourceCode !== '' && $isDataUrl($sourceCode)) {
                $filename = $placement . '.png';

                $uploadResult = $this->uploadArtworkToShopify($shopId, $sourceCode, $filename, $title);

                if (!$uploadResult['success']) {
                    return $uploadResult;
                }

                $finalUrl = (string) ($uploadResult['data']['resourceUrl'] ?? '');
            } elseif ($sourceCode !== '') {
                $finalUrl = $sourceCode;
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

    public function setDtftaTemplateIdMetafield(int $shopId, string $productId, int|string $templateId): array
    {
        $template = trim((string) $templateId);
        if ($template === '') {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Template id is required.',
                'data' => null,
                'errors' => [],
            ];
        }

        return $this->setCustomProductMetafields($shopId, $productId, [
            'line_item_meta' => [
                ['template_id' => $template],
            ],
        ]);
    }

    public function getActiveManagedSubscription(int $shopId): array
    {
        $query = <<<'GQL'
        query CurrentManagedSubscription {
          currentAppInstallation {
            activeSubscriptions {
              id
              name
              status
              test
              lineItems {
                id
                plan {
                  pricingDetails {
                    __typename
                    ... on AppRecurringPricing {
                      interval
                      price {
                        amount
                        currencyCode
                      }
                    }
                    ... on AppUsagePricing {
                      terms
                      cappedAmount {
                        amount
                        currencyCode
                      }
                      balanceUsed {
                        amount
                        currencyCode
                      }
                    }
                  }
                }
              }
            }
          }
        }
        GQL;

        $result = $this->graphqlRequest($shopId, $query);
        if (!($result['success'] ?? false)) {
            return $result;
        }

        $subscriptions = data_get($result, 'data.currentAppInstallation.activeSubscriptions', []);
        if (!is_array($subscriptions)) {
            $subscriptions = [];
        }

        $active = collect($subscriptions)->first(function ($subscription) {
            $status = strtoupper((string) data_get($subscription, 'status', ''));
            return in_array($status, ['ACTIVE', 'ACCEPTED'], true);
        });

        if (!$active) {
            return [
                'success' => true,
                'status' => 200,
                'message' => 'No active managed subscription',
                'data' => [
                    'subscription' => null,
                    'line_item_id' => null,
                ],
                'errors' => [],
            ];
        }

        $usageLineItem = collect((array) data_get($active, 'lineItems', []))
            ->first(function ($lineItem) {
                return data_get($lineItem, 'plan.pricingDetails.__typename') === 'AppUsagePricing';
            });

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Active managed subscription found',
            'data' => [
                'subscription' => $active,
                'line_item_id' => data_get($usageLineItem, 'id'),
            ],
            'errors' => [],
        ];
    }

    public function createManagedSubscriptionApproval(int $shopId, ?string $returnUrl = null): array
    {
        $planName = (string) config('services.shopify.billing.plan_name', 'DTFTA Merchant Usage');
        $currency = (string) config('services.shopify.billing.currency_code', 'USD');
        $basePriceAmount = (float) config('services.shopify.billing.base_price_amount', 0);
        $usageCapAmount = (float) config('services.shopify.billing.usage_cap_amount', 1000);
        $testMode = (bool) config('services.shopify.billing.test_mode', true);
        $effectiveReturnUrl = $returnUrl ?: (string) config('services.shopify.billing.return_url');

        $mutation = <<<'GQL'
        mutation AppSubscriptionCreate($name: String!, $returnUrl: URL!, $lineItems: [AppSubscriptionLineItemInput!]!, $test: Boolean!) {
          appSubscriptionCreate(name: $name, returnUrl: $returnUrl, lineItems: $lineItems, test: $test) {
            confirmationUrl
            appSubscription {
              id
              status
            }
            userErrors {
              field
              message
            }
          }
        }
        GQL;

        $variables = [
            'name' => $planName,
            'returnUrl' => $effectiveReturnUrl,
            'lineItems' => [
                [
                    'plan' => [
                        'appRecurringPricingDetails' => [
                            'price' => [
                                'amount' => $basePriceAmount,
                                'currencyCode' => $currency,
                            ],
                            'interval' => 'EVERY_30_DAYS',
                        ],
                    ],
                ],
                [
                    'plan' => [
                        'appUsagePricingDetails' => [
                            'terms' => 'Per-order charge before fulfillment starts',
                            'cappedAmount' => [
                                'amount' => $usageCapAmount,
                                'currencyCode' => $currency,
                            ],
                        ],
                    ],
                ],
            ],
            'test' => $testMode,
        ];

        $result = $this->graphqlRequest($shopId, $mutation, $variables);
        if (!($result['success'] ?? false)) {
            return $result;
        }

        $payload = data_get($result, 'data.appSubscriptionCreate', []);
        $userErrors = data_get($payload, 'userErrors', []);
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify appSubscriptionCreate returned user errors',
                'data' => $payload,
                'errors' => $userErrors,
            ];
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Managed subscription approval URL generated',
            'data' => [
                'confirmation_url' => data_get($payload, 'confirmationUrl'),
                'subscription' => data_get($payload, 'appSubscription'),
            ],
            'errors' => [],
        ];
    }

    public function createUsageRecord(
        int $shopId,
        string $lineItemId,
        float $amount,
        string $description,
        string $idempotencyKey,
        ?string $currencyCode = null
    ): array {
        $currency = (string) ($currencyCode ?: config('services.shopify.billing.currency_code', 'USD'));

        $mutation = <<<'GQL'
        mutation AppUsageRecordCreate(
          $subscriptionLineItemId: ID!,
          $description: String!,
          $price: MoneyInput!,
          $idempotencyKey: String!
        ) {
          appUsageRecordCreate(
            subscriptionLineItemId: $subscriptionLineItemId,
            description: $description,
            price: $price,
            idempotencyKey: $idempotencyKey
          ) {
            appUsageRecord {
              id
            }
            userErrors {
              field
              message
            }
          }
        }
        GQL;

        $result = $this->graphqlRequest($shopId, $mutation, [
            'subscriptionLineItemId' => $lineItemId,
            'description' => $description,
            'price' => [
                'amount' => $amount,
                'currencyCode' => $currency,
            ],
            'idempotencyKey' => $idempotencyKey,
        ]);

        if (!($result['success'] ?? false)) {
            return $result;
        }

        $payload = data_get($result, 'data.appUsageRecordCreate', []);
        $userErrors = data_get($payload, 'userErrors', []);
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify appUsageRecordCreate returned user errors',
                'data' => $payload,
                'errors' => $userErrors,
            ];
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Usage record created successfully',
            'data' => [
                'usage_record' => data_get($payload, 'appUsageRecord'),
            ],
            'errors' => [],
        ];
    }


    /**
     * Assign one or more product variants to an existing Shopify delivery profile.
     *
     * $deliveryProfileId can be either:
     * - gid://shopify/DeliveryProfile/123
     * - 123
     *
     * $variantIds can be:
     * - ["gid://shopify/ProductVariant/111", "gid://shopify/ProductVariant/222"]
     * - [111, 222]
     */
    public function assignVariantsToDeliveryProfile(
        int $shopId,
        string $deliveryProfileId,
        array $variantIds
    ): array {
        try {
            $profileGid = str_starts_with($deliveryProfileId, 'gid://')
                ? $deliveryProfileId
                : "gid://shopify/DeliveryProfile/{$deliveryProfileId}";

            $variantGids = array_values(array_filter(array_map(function ($id) {
                $value = trim((string) $id);
                if ($value === '') {
                    return null;
                }

                return str_starts_with($value, 'gid://')
                    ? $value
                    : "gid://shopify/ProductVariant/{$value}";
            }, $variantIds)));

            if (empty($variantGids)) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => 'At least one valid variant ID is required',
                    'data' => null,
                    'errors' => [],
                ];
            }

                    $mutation = <<<'GRAPHQL'
            mutation AssignVariantsToDeliveryProfile($id: ID!, $profile: DeliveryProfileInput!) {
            deliveryProfileUpdate(id: $id, profile: $profile) {
                profile {
                id
                name
                productVariantsCount {
                    count
                }
                profileItems(first: 20) {
                    edges {
                    node {
                        id
                        product {
                        id
                        title
                        }
                        variants(first: 20) {
                        edges {
                            node {
                            id
                            title
                            sku
                            }
                        }
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
            GRAPHQL;

            $variables = [
                'id' => $profileGid,
                'profile' => [
                    'variantsToAssociate' => $variantGids,
                ],
            ];


            $result = $this->graphqlRequest($shopId, $mutation, $variables);


            if (!($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'status' => $result['status'] ?? 500,
                    'message' => $result['message'] ?? 'GraphQL request failed',
                    'data' => $result['data'] ?? null,
                    'errors' => $result['errors'] ?? [],
                ];
            }

            $payload = data_get($result, 'data.deliveryProfileUpdate');

            if (!$payload) {
                return [
                    'success' => false,
                    'status' => 500,
                    'message' => 'Missing deliveryProfileUpdate payload in Shopify response',
                    'data' => $result['data'] ?? null,
                    'errors' => $result['errors'] ?? [],
                ];
            }

            if (!empty($payload['userErrors'])) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => $payload['userErrors'][0]['message'] ?? 'Failed to assign variants to delivery profile',
                    'data' => $payload,
                    'errors' => $payload['userErrors'],
                ];
            }

            return [
                'success' => true,
                'status' => $result['status'],
                'message' => 'Variants assigned to delivery profile successfully',
                'data' => $payload['profile'] ?? null,
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('assignVariantsToDeliveryProfile exception', [
                'shop_id' => $shopId,
                'delivery_profile_id' => $deliveryProfileId,
                'variant_ids' => $variantIds,
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

    public function attachMediaToProductVariant(
        int $shopId,
        string $productId,
        string $variantId,
        array $mediaIds
    ): array {
        try {
            $productGid = str_starts_with($productId, 'gid://')
                ? $productId
                : "gid://shopify/Product/{$productId}";

            $variantGid = str_starts_with($variantId, 'gid://')
                ? $variantId
                : "gid://shopify/ProductVariant/{$variantId}";

            $mediaGids = array_values(array_filter(array_map(function ($mediaId) {
                $value = trim((string) $mediaId);
                if ($value === '') {
                    return null;
                }

                return str_starts_with($value, 'gid://')
                    ? $value
                    : "gid://shopify/MediaImage/{$value}";
            }, $mediaIds)));

            if (empty($mediaGids)) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => 'At least one valid media ID is required',
                    'data' => null,
                    'errors' => [],
                ];
            }

            $mutation = <<<'GRAPHQL'
            mutation ProductVariantAppendMedia($productId: ID!, $variantMedia: [ProductVariantAppendMediaInput!]!) {
                productVariantAppendMedia(productId: $productId, variantMedia: $variantMedia) {
                    product {
                        id
                        title
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
            GRAPHQL;

            $lastPayload = null;
            foreach ($mediaGids as $mediaGid) {
                $result = $this->graphqlRequest($shopId, $mutation, [
                    'productId' => $productGid,
                    'variantMedia' => [[
                        'variantId' => $variantGid,
                        'mediaIds' => [$mediaGid],
                    ]],
                ]);

                if (!($result['success'] ?? false)) {
                    return [
                        'success' => false,
                        'status' => $result['status'] ?? 500,
                        'message' => $result['message'] ?? 'GraphQL request failed',
                        'data' => $result['data'] ?? null,
                        'errors' => $result['errors'] ?? [],
                    ];
                }

                $payload = data_get($result, 'data.productVariantAppendMedia');
                $lastPayload = $payload;

                if (!$payload) {
                    return [
                        'success' => false,
                        'status' => 500,
                        'message' => 'Missing productVariantAppendMedia payload in Shopify response',
                        'data' => $result['data'] ?? null,
                        'errors' => $result['errors'] ?? [],
                    ];
                }

                if (!empty($payload['userErrors'])) {
                    return [
                        'success' => false,
                        'status' => 422,
                        'message' => $payload['userErrors'][0]['message'] ?? 'Failed to attach media to variant',
                        'data' => $payload,
                        'errors' => $payload['userErrors'],
                    ];
                }
            }

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Media attached to variant successfully',
                'data' => [
                    'product' => $lastPayload['product'] ?? null,
                    'product_variant_id' => $variantGid,
                    'media_ids' => $mediaGids,
                ],
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('attachMediaToProductVariant exception', [
                'shop_id' => $shopId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'media_ids' => $mediaIds,
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

    public function getProductVariantsByProductId(int $shopId, string $productId): array
    {
        try {
            $productGid = str_starts_with($productId, 'gid://')
                ? $productId
                : "gid://shopify/Product/{$productId}";

            $query = <<<'GRAPHQL'
            query GetProductVariantsForMapping($id: ID!) {
                product(id: $id) {
                    id
                    variants(first: 100) {
                        edges {
                            node {
                                id
                                title
                                sku
                                inventoryItem {
                                    id
                                    sku
                                }
                                selectedOptions {
                                    name
                                    value
                                }
                            }
                        }
                    }
                }
            }
            GRAPHQL;

            $result = $this->graphqlRequest($shopId, $query, [
                'id' => $productGid,
            ]);

            if (!($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'status' => $result['status'] ?? 500,
                    'message' => $result['message'] ?? 'GraphQL request failed',
                    'data' => $result['data'] ?? null,
                    'errors' => $result['errors'] ?? [],
                ];
            }

            $edges = data_get($result, 'data.product.variants.edges', []);
            $nodes = collect(is_array($edges) ? $edges : [])
                ->map(fn ($edge) => $edge['node'] ?? null)
                ->filter()
                ->values()
                ->all();

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Product variants fetched successfully',
                'data' => [
                    'variants' => is_array($nodes) ? $nodes : [],
                ],
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('getProductVariantsByProductId exception', [
                'shop_id' => $shopId,
                'product_id' => $productId,
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
     * Assign a product variant to a specific Shopify inventory/fulfillment location.
     *
     * This works by:
     * 1) Resolving the variant's inventoryItem ID
     * 2) Activating that inventory item at the target location
     *
     * $variantId can be:
     * - gid://shopify/ProductVariant/123
     * - 123
     *
     * $locationId can be:
     * - gid://shopify/Location/456
     * - 456
     */
    public function assignVariantToInventoryLocation(
        int $shopId,
        string $variantId,
        string $locationId,
        ?int $available = null,
        ?int $onHand = null
    ): array {
        try {
            $variantGid = str_starts_with($variantId, 'gid://')
                ? $variantId
                : "gid://shopify/ProductVariant/{$variantId}";
    
            $locationGid = str_starts_with($locationId, 'gid://')
                ? $locationId
                : "gid://shopify/Location/{$locationId}";
    
                    $variantQuery = <<<'GRAPHQL'
            query GetVariantInventoryItemAndLevels($id: ID!) {
            productVariant(id: $id) {
                id
                title
                inventoryItem {
                id
                sku
                tracked
                inventoryLevels(first: 50) {
                    edges {
                    node {
                        id
                        location {
                        id
                        name
                        }
                    }
                    }
                }
                }
            }
            }
            GRAPHQL;
    
            $variantResult = $this->graphqlRequest($shopId, $variantQuery, [
                'id' => $variantGid,
            ]);
    
            if (!($variantResult['success'] ?? false)) {
                return [
                    'success' => false,
                    'status' => $variantResult['status'] ?? 500,
                    'message' => $variantResult['message'] ?? 'Failed to resolve variant inventory item',
                    'data' => $variantResult['data'] ?? null,
                    'errors' => $variantResult['errors'] ?? [],
                ];
            }
    
            $variantData = data_get($variantResult, 'data.productVariant');
            $inventoryItemId = data_get($variantData, 'inventoryItem.id');
    
            if (!$inventoryItemId) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => 'Inventory item not found for variant',
                    'data' => $variantData,
                    'errors' => [],
                ];
            }
    
                    // STEP 1: Activate target location first
                    $activateMutation = <<<'GRAPHQL'
            mutation ActivateInventoryAtLocation(
            $inventoryItemId: ID!,
            $locationId: ID!,
            $available: Int,
            $onHand: Int
            ) {
            inventoryActivate(
                inventoryItemId: $inventoryItemId,
                locationId: $locationId,
                available: $available,
                onHand: $onHand
            ) {
                inventoryLevel {
                id
                location {
                    id
                    name
                }
                quantities(names: ["available", "on_hand"]) {
                    name
                    quantity
                }
                }
                userErrors {
                field
                message
                }
            }
            }
            GRAPHQL;
    
            $activateResult = $this->graphqlRequest($shopId, $activateMutation, [
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationGid,
                'available' => $available,
                'onHand' => $onHand,
            ]);
    
            if (!($activateResult['success'] ?? false)) {
                return [
                    'success' => false,
                    'status' => $activateResult['status'] ?? 500,
                    'message' => $activateResult['message'] ?? 'Failed to activate inventory at target location',
                    'data' => $activateResult['data'] ?? null,
                    'errors' => $activateResult['errors'] ?? [],
                ];
            }
    
            $activatePayload = data_get($activateResult, 'data.inventoryActivate');
            if (!empty($activatePayload['userErrors'])) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => $activatePayload['userErrors'][0]['message'] ?? 'Failed to activate target location',
                    'data' => $activatePayload,
                    'errors' => $activatePayload['userErrors'],
                ];
            }
    
            // STEP 2: Re-fetch levels after activation
            $refreshResult = $this->graphqlRequest($shopId, $variantQuery, [
                'id' => $variantGid,
            ]);
    
            if (!($refreshResult['success'] ?? false)) {
                return [
                    'success' => false,
                    'status' => $refreshResult['status'] ?? 500,
                    'message' => 'Target location activated, but failed to refresh inventory levels',
                    'data' => $refreshResult['data'] ?? null,
                    'errors' => $refreshResult['errors'] ?? [],
                ];
            }
    
            $inventoryLevels = data_get($refreshResult, 'data.productVariant.inventoryItem.inventoryLevels.edges', []);
            $deactivated = [];
    
            // STEP 3: Deactivate all other locations
            foreach ($inventoryLevels as $edge) {
                $inventoryLevelId = data_get($edge, 'node.id');
                $existingLocationId = data_get($edge, 'node.location.id');
    
                if (!$inventoryLevelId || !$existingLocationId) {
                    continue;
                }
    
                if ($existingLocationId === $locationGid) {
                    continue;
                }
    
                            $deactivateMutation = <<<'GRAPHQL'
                mutation DeactivateInventoryLevel($inventoryLevelId: ID!) {
                inventoryDeactivate(inventoryLevelId: $inventoryLevelId) {
                    userErrors {
                    field
                    message
                    }
                }
                }
                GRAPHQL;
    
                $deactivateResult = $this->graphqlRequest($shopId, $deactivateMutation, [
                    'inventoryLevelId' => $inventoryLevelId,
                ]);
    
                if (!($deactivateResult['success'] ?? false)) {
                    return [
                        'success' => false,
                        'status' => $deactivateResult['status'] ?? 500,
                        'message' => 'Activated target location, but failed to deactivate another location',
                        'data' => $deactivateResult['data'] ?? null,
                        'errors' => $deactivateResult['errors'] ?? [],
                    ];
                }
    
                $deactivatePayload = data_get($deactivateResult, 'data.inventoryDeactivate');
                $deactivateErrors = $deactivatePayload['userErrors'] ?? [];
    
                if (!empty($deactivateErrors)) {
                    return [
                        'success' => false,
                        'status' => 422,
                        'message' => $deactivateErrors[0]['message'] ?? 'Failed to deactivate another location',
                        'data' => $deactivatePayload,
                        'errors' => $deactivateErrors,
                    ];
                }
    
                $deactivated[] = [
                    'inventory_level_id' => $inventoryLevelId,
                    'location_id' => $existingLocationId,
                ];
            }
    
            return [
                'success' => true,
                'status' => 200,
                'message' => 'Variant assigned to target location and removed from other locations successfully',
                'data' => [
                    'variant' => $variantData,
                    'target_inventory_level' => $activatePayload['inventoryLevel'] ?? null,
                    'deactivated_locations' => $deactivated,
                ],
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('assignVariantToInventoryLocation exception', [
                'shop_id' => $shopId,
                'variant_id' => $variantId,
                'location_id' => $locationId,
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



}
